<?php
namespace App\Models;

use App\Core\ValidationException;

class Booking extends BaseModel
{
    const BOOKING_TIMEOUT_MINUTES = 10;   // mặc định; giá trị thật lấy từ Admin > Cài đặt (Setting::holdMinutes)
    const MAX_SEATS = 8;
    const MAX_PRODUCT_QTY = 10;
    const POINT_VALUE = PointHistory::POINT_VALUE;   // 1 điểm = 1.000đ
    const CANCEL_BEFORE_HOURS = 2;     // khách tự hủy vé trước giờ chiếu ít nhất 2 giờ
    const MAX_CASH_HOLDS = 2;          // số đơn "trả tại quầy" chưa thanh toán tối đa / khách (mỗi suất 1 đơn)

    /**
     * Đơn giữ ghế còn hạn, chưa có thanh toán đang xử lý/thành công.
     * Chỉ loại đơn này mới được thay thế khi khách chọn lại ghế (alias bảng booking = b).
     */
    const REPLACEABLE_HOLD = "(b.status = 'pending' AND b.expiredAt > NOW()
        AND NOT EXISTS (SELECT 1 FROM payment p WHERE p.bookingId = b.id AND p.status IN ('pending', 'success')))";

    protected $table = 'booking';

    /**
     * Hết hạn các đơn pending quá giờ và nhả ghế (giống sp_release_expired_bookings,
     * chạy trong PHP để không phụ thuộc MySQL event scheduler)
     */
    public function releaseExpired()
    {
        $this->db->execute("UPDATE booking SET status = 'expired' WHERE status = 'pending' AND expiredAt < NOW()");
        $this->db->execute(
            "DELETE bs FROM booking_seat bs JOIN booking b ON b.id = bs.bookingId
             WHERE b.status IN ('expired', 'cancelled')"
        );
    }

    /**
     * Tính tiền đơn hàng. Mọi giá lấy từ DB, không tin giá client gửi lên.
     *
     * @param array $user     users + discountPercent, pointRate (User::getUserById)
     * @param array $showtime Showtime::getDetail
     * @param array $seatIds
     * @param array $productQty productId => quantity
     * @throws ValidationException
     */
    public function quote(array $user, array $showtime, array $seatIds, array $productQty, $voucherCode = '', $points = 0)
    {
        // ---------- Ghế ----------
        $seatIds = array_values(array_unique(array_filter(array_map('intval', $seatIds), function ($id) {
            return $id > 0;
        })));
        if (!$seatIds) {
            throw new ValidationException('Vui lòng chọn ít nhất 1 ghế', 'seats');
        }
        if (count($seatIds) > self::MAX_SEATS) {
            throw new ValidationException('Chỉ được đặt tối đa ' . self::MAX_SEATS . ' ghế mỗi lần', 'seats');
        }

        $seatMap = array_column((new Showtime())->getSeatMap($showtime, $user['id']), null, 'id');
        $seats = [];
        $taken = [];
        foreach ($seatIds as $seatId) {
            $seat = $seatMap[$seatId] ?? null;
            if (!$seat || $seat['status'] !== 'active') {
                throw new ValidationException('Ghế đã chọn không hợp lệ', 'seats');
            }
            if ($seat['isTaken']) {
                $taken[] = $seat['label'];
                continue;
            }
            $seats[] = [
                'id'       => $seat['id'],
                'label'    => $seat['label'],
                'typeName' => $seat['seatTypeName'],
                'price'    => $seat['price'],
            ];
        }
        if ($taken) {
            throw new ValidationException('Ghế ' . implode(', ', $taken) . ' đã có người đặt, vui lòng chọn ghế khác', 'seats');
        }
        $ticketAmount = array_sum(array_column($seats, 'price'));

        // ---------- Bắp nước ----------
        $wanted = [];
        foreach ($productQty as $productId => $qty) {
            $productId = (int)$productId;
            $qty = is_numeric($qty) ? (int)$qty : 0;
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            if ($qty > self::MAX_PRODUCT_QTY) {
                throw new ValidationException('Mỗi món tối đa ' . self::MAX_PRODUCT_QTY . ' phần', 'products');
            }
            $wanted[$productId] = $qty;
        }

        $productRows = (new Product())->getActiveByIds(array_keys($wanted));
        $products = [];
        foreach ($wanted as $productId => $qty) {
            if (!isset($productRows[$productId])) {
                throw new ValidationException('Sản phẩm không còn bán', 'products');
            }
            $price = (int)$productRows[$productId]['price'];
            $products[] = [
                'id'       => $productId,
                'name'     => $productRows[$productId]['name'],
                'quantity' => $qty,
                'price'    => $price,
                'total'    => $price * $qty,
            ];
        }
        $productAmount = array_sum(array_column($products, 'total'));
        $subtotal = $ticketAmount + $productAmount;

        // ---------- Giảm giá hạng thành viên ----------
        $memberDiscount = (int)floor($subtotal * (float)$user['discountPercent'] / 100);

        // ---------- Voucher ----------
        $voucher = null;
        $voucherDiscount = 0;
        $voucherCode = is_string($voucherCode) ? strtoupper(trim($voucherCode)) : '';
        if ($voucherCode !== '') {
            $voucher = $this->checkVoucher($voucherCode, $user['id'], $showtime['id'], $subtotal);
            $voucherDiscount = $voucher['discountType'] === 'percent'
                ? (int)floor($subtotal * (int)$voucher['discountValue'] / 100)
                : (int)$voucher['discountValue'];
            if ($voucher['maxDiscount'] !== null) {
                $voucherDiscount = min($voucherDiscount, (int)$voucher['maxDiscount']);
            }
            $voucherDiscount = min($voucherDiscount, $subtotal - $memberDiscount);
        }

        // ---------- Điểm thưởng ('max' = dùng tối đa số điểm được phép cho đơn) ----------
        $useMaxPoints = $points === 'max';
        $points = !$useMaxPoints && is_numeric($points) ? (int)$points : 0;
        $afterDiscount = $subtotal - $memberDiscount - $voucherDiscount;
        // Điểm đang "giữ" cho đơn chờ thanh toán khác không được dùng lại (chống dùng 1 điểm cho nhiều đơn)
        $availablePoints = max(0, (int)$user['points'] - $this->reservedPoints($user['id'], $showtime['id']));
        $maxPoints = min($availablePoints, intdiv($afterDiscount, self::POINT_VALUE));
        if ($useMaxPoints) {
            $points = $maxPoints;
        }
        if ($points < 0) {
            throw new ValidationException('Số điểm không hợp lệ', 'points');
        }
        if ($points > $availablePoints) {
            throw new ValidationException("Bạn chỉ có {$availablePoints} điểm", 'points');
        }
        if ($points > $maxPoints) {
            throw new ValidationException("Đơn này dùng tối đa {$maxPoints} điểm", 'points');
        }
        $pointsDiscount = $points * self::POINT_VALUE;

        $discountAmount = $memberDiscount + $voucherDiscount + $pointsDiscount;
        $totalAmount = $subtotal - $discountAmount;

        return [
            'seats'           => $seats,
            'products'        => $products,
            'ticketAmount'    => $ticketAmount,
            'productAmount'   => $productAmount,
            'subtotal'        => $subtotal,
            'memberLevel'     => $user['levelName'],
            'memberDiscount'  => $memberDiscount,
            'voucher'         => $voucher ? ['id' => (int)$voucher['id'], 'code' => $voucher['code'], 'name' => $voucher['name']] : null,
            'voucherDiscount' => $voucherDiscount,
            'pointsUsed'      => $points,
            'pointsDiscount'  => $pointsDiscount,
            'availablePoints' => $availablePoints,
            'maxPoints'       => $maxPoints,
            'discountAmount'  => $discountAmount,
            'totalAmount'     => $totalAmount,
            'pointsEarned'    => self::pointsFor($totalAmount, $user['pointRate']),
        ];
    }

    /**
     * Điểm tích lũy: pointRate% giá trị đơn, quy đổi 1 điểm = 1.000đ (xem PointHistory)
     */
    public static function pointsFor($amount, $pointRate)
    {
        return PointHistory::pointsFor($amount, $pointRate);
    }

    /**
     * Tổng điểm khách đã đặt cho các đơn còn giữ ghế (trừ đơn sẽ bị thay khi chọn lại ghế cùng suất)
     */
    public function reservedPoints($userId, $replacingShowtimeId = null)
    {
        return (int)$this->db->query(
            "SELECT COALESCE(SUM(b.pointsUsed), 0) AS total FROM booking b
             WHERE b.userId = ? AND b.status = 'pending' AND b.expiredAt > NOW()
               AND NOT (b.showtimeId <=> ? AND " . self::REPLACEABLE_HOLD . ")",
            [(int)$userId, $replacingShowtimeId === null ? null : (int)$replacingShowtimeId],
            true
        )['total'];
    }

    /**
     * @throws ValidationException
     */
    private function checkVoucher($code, $userId, $showtimeId, $subtotal)
    {
        $voucherModel = new Voucher();
        $voucher = $voucherModel->findByCode($code);
        $now = time();

        if (!$voucher || $voucher['status'] !== 'active'
            || strtotime($voucher['startDate']) > $now || strtotime($voucher['endDate']) < $now) {
            throw new ValidationException('Mã voucher không tồn tại hoặc đã hết hạn', 'voucher');
        }
        // Lượt còn lại tính cả đơn đang giữ ghế bằng mã này (usedCount chỉ tăng khi đơn được thanh toán)
        if ((int)$voucher['usedCount'] + $voucherModel->countHeld($voucher['id'], $userId, $showtimeId) >= (int)$voucher['quantity']) {
            throw new ValidationException('Mã voucher đã hết lượt sử dụng', 'voucher');
        }
        if ($subtotal < (int)$voucher['minOrder']) {
            throw new ValidationException('Đơn tối thiểu ' . \CommonHelper::money($voucher['minOrder']) . ' để dùng mã này', 'voucher');
        }
        if ($voucherModel->countUserUsage($voucher['id'], $userId, $showtimeId) >= (int)$voucher['perUserLimit']) {
            throw new ValidationException('Bạn đã dùng hết lượt của mã này', 'voucher');
        }

        return $voucher;
    }

    /**
     * Giữ ghế: thay đơn giữ ghế cũ của khách cho cùng suất chiếu, tính lại giá, tạo booking pending.
     * @return array ['id' => ..., 'code' => ...]
     * @throws ValidationException
     */
    public function hold(array $user, array $showtime, array $seatIds, array $productQty, $voucherCode = '', $points = 0)
    {
        $this->releaseExpired();
        $this->db->beginTransaction();

        try {
            // Khóa user: các lần giữ ghế song song của cùng khách chạy lần lượt (điểm / lượt voucher không bị tính trùng)
            $this->db->query('SELECT id FROM users WHERE id = ? FOR UPDATE', [(int)$user['id']], true);
            $user = (new User())->getUserById($user['id']) ?: $user;

            // Hủy đơn giữ ghế cũ (chưa thanh toán) của khách cho suất này để chọn lại
            $oldHolds = $this->db->query(
                "SELECT b.id FROM booking b
                 WHERE b.userId = ? AND b.showtimeId = ? AND " . self::REPLACEABLE_HOLD . " FOR UPDATE",
                [(int)$user['id'], (int)$showtime['id']]
            );
            foreach ($oldHolds as $old) {
                $this->db->execute("UPDATE booking SET status = 'cancelled' WHERE id = ?", [(int)$old['id']]);
                $this->db->execute('DELETE FROM booking_seat WHERE bookingId = ?', [(int)$old['id']]);
            }

            $quote = $this->quote($user, $showtime, $seatIds, $productQty, $voucherCode, $points);

            $expiredAt = min(time() + Setting::holdMinutes() * 60, strtotime($showtime['startTime']));
            $code = $this->generateCode();
            $bookingId = $this->create([
                'code'           => $code,
                'userId'         => (int)$user['id'],
                'showtimeId'     => (int)$showtime['id'],
                'voucherId'      => $quote['voucher']['id'] ?? null,
                'ticketAmount'   => $quote['ticketAmount'],
                'productAmount'  => $quote['productAmount'],
                'discountAmount' => $quote['discountAmount'],
                'pointsUsed'     => $quote['pointsUsed'],
                'totalAmount'    => $quote['totalAmount'],
                'pointsEarned'   => 0,
                'status'         => 'pending',
                'expiredAt'      => date('Y-m-d H:i:s', $expiredAt),
            ]);

            foreach ($quote['seats'] as $seat) {
                try {
                    $this->db->insert('booking_seat', [
                        'bookingId'  => (int)$bookingId,
                        'showtimeId' => (int)$showtime['id'],
                        'seatId'     => $seat['id'],
                        'price'      => $seat['price'],
                    ]);
                } catch (\PDOException $e) {
                    // UNIQUE(showtimeId, seatId): ghế vừa bị người khác giữ
                    if ($e->getCode() === '23000') {
                        throw new ValidationException("Ghế {$seat['label']} vừa được người khác đặt, vui lòng chọn lại", 'seats');
                    }
                    throw $e;
                }
            }

            foreach ($quote['products'] as $product) {
                $this->db->insert('booking_product', [
                    'bookingId' => (int)$bookingId,
                    'productId' => $product['id'],
                    'quantity'  => $product['quantity'],
                    'price'     => $product['price'],
                ]);
            }

            $this->db->commit();

            return ['id' => (int)$bookingId, 'code' => $code];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Mã vé: BK + 8 ký tự in hoa/số (bỏ các ký tự dễ nhầm O/0, I/1)
     */
    private function generateCode()
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = 'BK';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while ($this->count('code = ?', [$code]) > 0);

        return $code;
    }

    /**
     * Đơn của 1 khách kèm suất chiếu, phim, rạp
     * @return array|null
     */
    public function getForUser($bookingId, $userId)
    {
        return $this->db->query(
            "SELECT b.*, s.startTime, s.endTime, s.format, s.language, s.roomId,
                    m.name AS movieName, m.slug AS movieSlug, m.image AS moviePoster, m.ageRating, m.duration,
                    r.name AS roomName, c.name AS cinemaName, c.address AS cinemaAddress,
                    v.code AS voucherCode
             FROM booking b
             JOIN showtime s ON s.id = b.showtimeId
             JOIN movie m ON m.id = s.movieId
             JOIN room r ON r.id = s.roomId
             JOIN cinema c ON c.id = r.cinemaId
             LEFT JOIN voucher v ON v.id = b.voucherId
             WHERE b.id = ? AND b.userId = ?",
            [(int)$bookingId, (int)$userId],
            true
        );
    }

    public function getSeats($bookingId)
    {
        return $this->db->query(
            "SELECT bs.price, se.rowLabel, se.seatNumber, st.name AS typeName
             FROM booking_seat bs
             JOIN seat se ON se.id = bs.seatId
             JOIN seat_type st ON st.id = se.seatTypeId
             WHERE bs.bookingId = ?
             ORDER BY LENGTH(se.rowLabel), se.rowLabel, se.seatNumber",
            [(int)$bookingId]
        );
    }

    public function getProducts($bookingId)
    {
        return $this->db->query(
            "SELECT bp.quantity, bp.price, p.name
             FROM booking_product bp JOIN product p ON p.id = bp.productId
             WHERE bp.bookingId = ? ORDER BY p.name",
            [(int)$bookingId]
        );
    }

    public function getPayments($bookingId)
    {
        return $this->db->query(
            'SELECT * FROM payment WHERE bookingId = ? ORDER BY id DESC',
            [(int)$bookingId]
        );
    }

    /**
     * Lịch sử vé của khách, lọc theo nhóm: upcoming (sắp chiếu) | past (đã chiếu) | cancelled
     * @return array ['items' => [...], 'total' => int]
     */
    public function listForUser($userId, $filter, $limit, $offset)
    {
        $where = 'b.userId = ?';
        $params = [(int)$userId];
        switch ($filter) {
            case 'upcoming':
                // Đã trả hoặc đang giữ ghế còn hiệu lực, suất chưa kết thúc
                $where .= " AND s.endTime > NOW() AND (b.status = 'paid' OR (b.status = 'pending' AND b.expiredAt > NOW()))";
                break;
            case 'past':
                $where .= " AND s.endTime <= NOW() AND b.status = 'paid'";
                break;
            case 'cancelled':
                $where .= " AND (b.status IN ('cancelled', 'expired') OR (b.status = 'pending' AND b.expiredAt <= NOW()))";
                break;
        }

        $total = (int)$this->db->query(
            "SELECT COUNT(*) AS total FROM booking b JOIN showtime s ON s.id = b.showtimeId WHERE {$where}",
            $params,
            true
        )['total'];

        $items = $this->db->query(
            "SELECT b.id, b.code, b.status, b.totalAmount, b.pointsUsed, b.pointsEarned, b.expiredAt, b.checkedInAt, b.createdAt,
                    s.startTime, s.endTime, s.format, s.language,
                    m.name AS movieName, m.slug AS movieSlug, m.image AS moviePoster, m.ageRating,
                    r.name AS roomName, c.name AS cinemaName,
                    (SELECT GROUP_CONCAT(CONCAT(se.rowLabel, se.seatNumber) ORDER BY LENGTH(se.rowLabel), se.rowLabel, se.seatNumber SEPARATOR ', ')
                       FROM booking_seat bs JOIN seat se ON se.id = bs.seatId WHERE bs.bookingId = b.id) AS seats,
                    (SELECT p.method FROM payment p WHERE p.bookingId = b.id ORDER BY p.status = 'success' DESC, p.id DESC LIMIT 1) AS paymentMethod,
                    EXISTS (SELECT 1 FROM payment p WHERE p.bookingId = b.id AND p.method = 'cash' AND p.status = 'pending') AS cashPending
             FROM booking b
             JOIN showtime s ON s.id = b.showtimeId
             JOIN movie m ON m.id = s.movieId
             JOIN room r ON r.id = s.roomId
             JOIN cinema c ON c.id = r.cinemaId
             WHERE {$where}
             ORDER BY " . ($filter === 'upcoming' ? 's.startTime ASC' : 's.startTime DESC') . ", b.id DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [(int)$limit, (int)$offset])
        );

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Khách tự hủy vé:
     * - Đơn chưa trả (đang giữ ghế / chờ trả tại quầy): hủy + nhả ghế.
     * - Đơn đã trả: chỉ khi còn >= CANCEL_BEFORE_HOURS giờ trước suất chiếu và chưa soát vé.
     *   Nhả ghế, hoàn lại điểm đã dùng, thu hồi điểm đã tích, trừ chi tiêu, trả lượt voucher.
     *   Tiền được hoàn thủ công (payment chuyển sang refunded để đối soát).
     * @return string 'cancelled' | 'refund'
     * @throws ValidationException
     */
    public function cancelByUser($bookingId, $userId)
    {
        $this->db->beginTransaction();

        try {
            $booking = $this->db->query(
                'SELECT b.*, s.startTime FROM booking b JOIN showtime s ON s.id = b.showtimeId
                 WHERE b.id = ? AND b.userId = ? FOR UPDATE',
                [(int)$bookingId, (int)$userId],
                true
            );
            if (!$booking) {
                throw new ValidationException('Không tìm thấy vé');
            }
            if ($booking['checkedInAt']) {
                throw new ValidationException('Vé đã được soát, không thể hủy');
            }
            if (strtotime($booking['startTime']) - time() < self::CANCEL_BEFORE_HOURS * 3600) {
                throw new ValidationException('Chỉ được hủy vé trước giờ chiếu ít nhất ' . self::CANCEL_BEFORE_HOURS . ' giờ');
            }

            $wasPaid = $booking['status'] === 'paid';
            $holding = $booking['status'] === 'pending' && strtotime($booking['expiredAt']) > time();
            if (!$wasPaid && !$holding) {
                throw new ValidationException('Vé này đã hết hiệu lực');
            }
            // Đơn chờ chuyển khoản / VNPay đang xử lý: tiền có thể đang về, không cho hủy lúc này
            if (!$wasPaid && $this->db->query(
                "SELECT 1 FROM payment WHERE bookingId = ? AND status = 'pending' AND method <> 'cash'",
                [(int)$booking['id']],
                true
            )) {
                throw new ValidationException('Đơn đang chờ xác nhận thanh toán, vui lòng thử lại sau');
            }

            $this->db->execute("UPDATE booking SET status = 'cancelled' WHERE id = ?", [(int)$booking['id']]);
            $this->db->execute('DELETE FROM booking_seat WHERE bookingId = ?', [(int)$booking['id']]);

            if (!$wasPaid) {
                $this->db->execute(
                    "UPDATE payment SET status = 'failed' WHERE bookingId = ? AND status = 'pending'",
                    [(int)$booking['id']]
                );
                $this->db->commit();
                return 'cancelled';
            }

            $this->reversePaid($booking);

            $this->db->commit();
            error_log("Booking {$booking['code']} cancelled by user #{$userId} - refund " . (int)$booking['totalAmount'] . 'đ needed');
            return 'refund';
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Admin hủy đơn (không giới hạn giờ chiếu, trừ vé đã soát).
     * Đơn đã trả: hoàn điểm / thu hồi điểm / trừ chi tiêu / trả lượt voucher như khách tự hủy,
     * payment chuyển refunded để kế toán hoàn tiền thủ công.
     * @return string 'cancelled' | 'refund'
     * @throws ValidationException
     */
    public function cancelByAdmin($bookingId, $adminId)
    {
        $this->db->beginTransaction();

        try {
            $booking = $this->db->query('SELECT * FROM booking WHERE id = ? FOR UPDATE', [(int)$bookingId], true);
            if (!$booking) {
                throw new ValidationException('Không tìm thấy đơn');
            }
            if ($booking['checkedInAt']) {
                throw new ValidationException('Vé đã được soát, không thể hủy');
            }
            if (!in_array($booking['status'], ['paid', 'pending'], true)) {
                throw new ValidationException('Đơn đã hết hiệu lực');
            }

            $wasPaid = $booking['status'] === 'paid';
            $this->db->execute("UPDATE booking SET status = 'cancelled' WHERE id = ?", [(int)$booking['id']]);
            $this->db->execute('DELETE FROM booking_seat WHERE bookingId = ?', [(int)$booking['id']]);

            if ($wasPaid) {
                $this->reversePaid($booking);
            } else {
                $this->db->execute("UPDATE payment SET status = 'failed' WHERE bookingId = ? AND status = 'pending'", [(int)$booking['id']]);
            }

            $this->db->commit();
            if ($wasPaid) {
                error_log("Booking {$booking['code']} cancelled by admin #{$adminId} - refund " . (int)$booking['totalAmount'] . 'đ needed');
            }
            return $wasPaid ? 'refund' : 'cancelled';
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Đảo các tác động của đơn đã thanh toán (gọi trong transaction)
     */
    private function reversePaid(array $booking)
    {
        $userId = (int)$booking['userId'];
        $points = new PointHistory();
        // Chỉ hoàn số điểm thực sự đã trừ / thu hồi số điểm thực sự đã cộng cho đơn này (theo lịch sử điểm)
        [$deducted, $credited] = $this->pointMovements($booking['id']);
        $points->change($userId, $deducted, "Hoàn điểm do hủy vé {$booking['code']}", $booking['id']);
        $points->change($userId, -$credited, "Thu hồi điểm do hủy vé {$booking['code']}", $booking['id']);
        $points->addSpending($userId, -(int)$booking['totalAmount']);

        if ($booking['voucherId']) {
            $this->db->execute('UPDATE voucher SET usedCount = usedCount - 1 WHERE id = ? AND usedCount > 0', [(int)$booking['voucherId']]);
        }
        $this->db->execute(
            "UPDATE payment SET status = 'refunded' WHERE bookingId = ? AND status = 'success'",
            [(int)$booking['id']]
        );
    }

    /**
     * Điểm thực tế đã trừ (dùng điểm) và đã cộng (tích điểm) cho 1 đơn, theo point_history
     * @return int[] [deducted >= 0, credited >= 0]
     */
    private function pointMovements($bookingId)
    {
        $row = $this->db->query(
            'SELECT COALESCE(SUM(CASE WHEN points < 0 THEN -points ELSE 0 END), 0) AS deducted,
                    COALESCE(SUM(CASE WHEN points > 0 THEN points ELSE 0 END), 0) AS credited
             FROM point_history WHERE bookingId = ?',
            [(int)$bookingId],
            true
        );

        return [(int)$row['deducted'], (int)$row['credited']];
    }

    /**
     * Số đơn khác của khách đang giữ ghế chờ trả tại quầy (lọc theo suất nếu truyền $showtimeId)
     */
    public function countCashHolds($userId, $exceptBookingId, $showtimeId = null)
    {
        $sql = "SELECT COUNT(*) AS total FROM booking b
                WHERE b.userId = ? AND b.id <> ? AND b.status = 'pending' AND b.expiredAt > NOW()
                  AND EXISTS (SELECT 1 FROM payment p WHERE p.bookingId = b.id AND p.method = 'cash' AND p.status = 'pending')";
        $params = [(int)$userId, (int)$exceptBookingId];
        if ($showtimeId !== null) {
            $sql .= ' AND b.showtimeId = ?';
            $params[] = (int)$showtimeId;
        }

        return (int)$this->db->query($sql, $params, true)['total'];
    }

    public function hasPendingCashPayment($bookingId)
    {
        return (bool)$this->db->query(
            "SELECT 1 FROM payment WHERE bookingId = ? AND method = 'cash' AND status = 'pending'",
            [(int)$bookingId],
            true
        );
    }

    /**
     * Đơn còn có thể thanh toán: pending, còn hạn giữ ghế, suất chưa chiếu, còn ghế
     */
    public function isPayable(array $booking)
    {
        return $booking['status'] === 'pending'
            && strtotime($booking['expiredAt']) > time()
            && strtotime($booking['startTime']) > time()
            && count($this->getSeats($booking['id'])) > 0;
    }

    /**
     * Khóa đơn + kiểm tra lại voucher/điểm trước khi tạo thanh toán.
     * @throws ValidationException
     */
    public function lockForPayment($bookingId, $userId)
    {
        $booking = $this->db->query(
            'SELECT * FROM booking WHERE id = ? AND userId = ? FOR UPDATE',
            [(int)$bookingId, (int)$userId],
            true
        );
        $detail = $booking ? $this->getForUser($bookingId, $userId) : null;
        if (!$detail || !$this->isPayable($detail)) {
            throw new ValidationException('Đơn hàng đã hết hạn giữ ghế, vui lòng đặt lại');
        }
        if ($this->hasPendingCashPayment($bookingId)) {
            throw new ValidationException('Đơn đã chọn thanh toán tại quầy');
        }

        $user = (new User())->getUserById($userId);
        if ((int)$detail['pointsUsed'] > (int)$user['points']) {
            throw new ValidationException('Số điểm của bạn không còn đủ, vui lòng đặt lại');
        }
        if ($detail['voucherId']) {
            $voucher = (new Voucher())->getById($detail['voucherId']);
            if (!$voucher || $voucher['status'] !== 'active' || (int)$voucher['usedCount'] >= (int)$voucher['quantity']
                || strtotime($voucher['endDate']) < time()) {
                throw new ValidationException('Voucher không còn hiệu lực, vui lòng đặt lại');
            }
        }

        return $detail;
    }

    /**
     * Thanh toán tại quầy: giữ ghế tới giờ chiếu, nhân viên thu tiền khi khách tới
     */
    public function extendForCash($bookingId, $startTime)
    {
        return $this->db->execute(
            "UPDATE booking SET expiredAt = ? WHERE id = ? AND status = 'pending'",
            [date('Y-m-d H:i:s', strtotime($startTime)), (int)$bookingId]
        );
    }

    /**
     * Ghi nhận thanh toán thành công (idempotent):
     * payment success, booking paid, trừ điểm đã dùng, cộng điểm + chi tiêu, nâng hạng, tăng lượt voucher.
     *
     * @return string 'paid' | 'already' | 'late' (tiền đã nhận nhưng đơn đã hết hạn -> cần hoàn tiền)
     */
    public function markPaid($paymentId, $transactionCode, $rawResponse)
    {
        $this->db->beginTransaction();

        try {
            $payment = $this->db->query('SELECT * FROM payment WHERE id = ? FOR UPDATE', [(int)$paymentId], true);
            if (!$payment || $payment['status'] !== 'pending') {
                $this->db->commit();
                return 'already';
            }

            $this->db->execute(
                "UPDATE payment SET status = 'success', transactionCode = ?, rawResponse = ?, paidAt = NOW() WHERE id = ?",
                [$transactionCode, json_encode($rawResponse, JSON_UNESCAPED_UNICODE), (int)$paymentId]
            );

            $booking = $this->db->query('SELECT * FROM booking WHERE id = ? FOR UPDATE', [(int)$payment['bookingId']], true);
            $hasSeats = (int)$this->db->query(
                'SELECT COUNT(*) AS total FROM booking_seat WHERE bookingId = ?',
                [(int)$booking['id']],
                true
            )['total'] > 0;

            if ($booking['status'] !== 'pending' || !$hasSeats) {
                $this->db->commit();
                error_log("Late payment #{$paymentId} for booking {$booking['code']} (status {$booking['status']}) - refund needed");
                return 'late';
            }

            $user = $this->db->query(
                'SELECT u.id, u.points, COALESCE(ml.pointRate, 0) AS pointRate
                 FROM users u LEFT JOIN member_level ml ON ml.id = u.memberLevelId
                 WHERE u.id = ? FOR UPDATE',
                [(int)$booking['userId']],
                true
            );
            $earned = self::pointsFor($booking['totalAmount'], $user['pointRate']);

            // Điểm / lượt voucher không còn đủ (đã dùng cho đơn khác): không giảm giá "miễn phí" -> cần hoàn tiền
            if ((int)$user['points'] < (int)$booking['pointsUsed']) {
                $this->db->commit();
                error_log("Payment #{$paymentId} for booking {$booking['code']}: not enough points - refund needed");
                return 'late';
            }
            if ($booking['voucherId']) {
                $voucher = $this->db->query('SELECT usedCount, quantity FROM voucher WHERE id = ? FOR UPDATE', [(int)$booking['voucherId']], true);
                if (!$voucher || (int)$voucher['usedCount'] >= (int)$voucher['quantity']) {
                    $this->db->commit();
                    error_log("Payment #{$paymentId} for booking {$booking['code']}: voucher exhausted - refund needed");
                    return 'late';
                }
            }

            $this->db->execute(
                "UPDATE booking SET status = 'paid', pointsEarned = ? WHERE id = ?",
                [$earned, (int)$booking['id']]
            );

            // Trừ điểm đã dùng, cộng điểm tích lũy, cộng chi tiêu + xét hạng
            $points = new PointHistory();
            $points->change($user['id'], -(int)$booking['pointsUsed'], "Dùng điểm cho đơn {$booking['code']}", $booking['id']);
            $points->change($user['id'], $earned, "Tích điểm đơn {$booking['code']}", $booking['id']);
            $points->addSpending($user['id'], $booking['totalAmount']);

            if ($booking['voucherId']) {
                (new Voucher())->incrementUsage($booking['voucherId']);
            }

            $this->db->commit();
            return 'paid';
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
