<?php
namespace App\Models;

use App\Core\ValidationException;

/**
 * Soát vé tại rạp: tra cứu vé theo mã, xác nhận vào rạp, thu tiền vé "thanh toán tại quầy"
 */
class Checkin extends BaseModel
{
    /** Cho vào rạp từ trước giờ chiếu tối đa bao lâu / trễ tối đa bao lâu sau giờ bắt đầu */
    const OPEN_BEFORE_MINUTES = 60;
    const LATE_AFTER_MINUTES = 30;

    /** Mã vé BK + 8 ký tự (Booking::generateCode) */
    const CODE_PATTERN = '/^BK[A-Z0-9]{6,10}$/';

    protected $table = 'booking';

    /**
     * Chuẩn hóa mã quét được / nhập tay (bỏ khoảng trắng, chữ hoa)
     */
    public static function normalizeCode($raw)
    {
        return is_string($raw) ? strtoupper(preg_replace('/\s+/', '', $raw)) : '';
    }

    /**
     * Tra cứu vé + đánh giá có cho vào rạp được không
     * @return array ['booking' => ..., 'seats' => [...], 'products' => [...], 'state' => ..., 'message' => ...]
     *   state: ready | need_payment | checked_in | too_early | ended | invalid
     * @throws ValidationException mã sai định dạng / không tồn tại
     */
    public function lookup($code)
    {
        if (!preg_match(self::CODE_PATTERN, $code)) {
            throw new ValidationException('Mã vé không đúng định dạng');
        }

        $booking = $this->db->query(
            "SELECT b.*, s.startTime, s.endTime, s.format, s.language,
                    m.name AS movieName, m.ageRating, r.name AS roomName, c.name AS cinemaName,
                    u.name AS customerName, u.phoneNumber AS customerPhone,
                    ck.name AS checkedInByName,
                    EXISTS (SELECT 1 FROM payment p WHERE p.bookingId = b.id AND p.method = 'cash' AND p.status = 'pending') AS cashPending
             FROM booking b
             JOIN showtime s ON s.id = b.showtimeId
             JOIN movie m ON m.id = s.movieId
             JOIN room r ON r.id = s.roomId
             JOIN cinema c ON c.id = r.cinemaId
             JOIN users u ON u.id = b.userId
             LEFT JOIN users ck ON ck.id = b.checkedInBy
             WHERE b.code = ?",
            [$code],
            true
        );
        if (!$booking) {
            throw new ValidationException('Không tìm thấy vé ' . $code);
        }

        $bookingModel = new Booking();
        $now = time();
        $start = strtotime($booking['startTime']);
        $end = strtotime($booking['endTime']);
        $cashPending = (bool)$booking['cashPending'] && $booking['status'] === 'pending' && strtotime($booking['expiredAt']) > $now;

        if ($booking['checkedInAt']) {
            [$state, $message] = ['checked_in', 'Vé đã được soát lúc ' . date('H:i d/m', strtotime($booking['checkedInAt']))];
        } elseif ($booking['status'] !== 'paid' && !$cashPending) {
            $label = ['pending' => 'chưa thanh toán', 'cancelled' => 'đã hủy', 'expired' => 'đã hết hạn'][$booking['status']] ?? $booking['status'];
            [$state, $message] = ['invalid', 'Vé ' . $label . ', không được vào rạp'];
        } elseif ($now > min($end, $start + self::LATE_AFTER_MINUTES * 60)) {
            [$state, $message] = ['ended', $now > $end ? 'Suất chiếu đã kết thúc' : 'Đã quá ' . self::LATE_AFTER_MINUTES . ' phút sau giờ chiếu'];
        } elseif ($now < $start - self::OPEN_BEFORE_MINUTES * 60) {
            [$state, $message] = ['too_early', 'Chưa tới giờ soát vé (mở trước ' . self::OPEN_BEFORE_MINUTES . ' phút)'];
        } elseif ($cashPending) {
            [$state, $message] = ['need_payment', 'Khách chưa thanh toán: thu ' . \CommonHelper::money($booking['totalAmount']) . ' trước khi cho vào'];
        } else {
            [$state, $message] = ['ready', 'Vé hợp lệ, có thể cho vào rạp'];
        }

        return [
            'booking'  => $booking,
            'seats'    => $bookingModel->getSeats($booking['id']),
            'products' => $bookingModel->getProducts($booking['id']),
            'state'    => $state,
            'message'  => $message,
        ];
    }

    /**
     * Xác nhận vào rạp. Vé "thanh toán tại quầy" cần $collectCash = true: ghi nhận đã thu tiền (dùng lại markPaid
     * để cộng điểm, chi tiêu, voucher như thanh toán online) rồi mới soát.
     * Chống soát 2 lần bằng điều kiện checkedInAt IS NULL.
     * @return array lookup() sau khi soát
     * @throws ValidationException
     */
    public function confirm($code, $staffId, $collectCash = false)
    {
        $info = $this->lookup($code);
        $booking = $info['booking'];

        if ($info['state'] === 'need_payment') {
            if (!$collectCash) {
                throw new ValidationException($info['message']);
            }
            $payment = (new Payment())->findPending($booking['id'], 'cash');
            $result = $payment ? (new Booking())->markPaid($payment['id'], null, ['collectedBy' => (int)$staffId, 'at' => date('c')]) : 'missing';
            if ($result !== 'paid') {
                throw new ValidationException('Không ghi nhận được thanh toán, vui lòng thử lại');
            }
        } elseif ($info['state'] !== 'ready') {
            throw new ValidationException($info['message']);
        }

        $updated = $this->db->execute(
            "UPDATE booking SET checkedInAt = NOW(), checkedInBy = ? WHERE id = ? AND status = 'paid' AND checkedInAt IS NULL",
            [(int)$staffId, (int)$booking['id']]
        );
        if ($updated !== 1) {
            throw new ValidationException('Vé vừa được soát ở quầy khác');
        }

        return $this->lookup($code);
    }

    /**
     * Vé đã soát hôm nay (mới nhất trước)
     */
    public function recent($limit = 10, $staffId = null)
    {
        $where = 'b.checkedInAt >= CURDATE()';
        $params = [];
        if ($staffId) {
            $where .= ' AND b.checkedInBy = ?';
            $params[] = (int)$staffId;
        }
        $params[] = (int)$limit;

        return $this->db->query(
            "SELECT b.code, b.checkedInAt, m.name AS movieName, s.startTime, r.name AS roomName,
                    (SELECT COUNT(*) FROM booking_seat bs WHERE bs.bookingId = b.id) AS seatCount
             FROM booking b
             JOIN showtime s ON s.id = b.showtimeId
             JOIN movie m ON m.id = s.movieId
             JOIN room r ON r.id = s.roomId
             WHERE {$where}
             ORDER BY b.checkedInAt DESC LIMIT ?",
            $params
        );
    }

    /**
     * Suất chiếu trong ngày kèm số vé đã bán / đã soát (dashboard nhân viên)
     */
    public function todayShowtimes()
    {
        return $this->db->query(
            "SELECT s.id, s.startTime, s.endTime, s.format, m.name AS movieName, r.name AS roomName, c.name AS cinemaName,
                    (SELECT COUNT(*) FROM seat se WHERE se.roomId = s.roomId AND se.status = 'active') AS totalSeats,
                    (SELECT COUNT(*) FROM booking_seat bs JOIN booking b ON b.id = bs.bookingId
                      WHERE bs.showtimeId = s.id AND b.status = 'paid') AS soldSeats,
                    (SELECT COUNT(*) FROM booking_seat bs JOIN booking b ON b.id = bs.bookingId
                      WHERE bs.showtimeId = s.id AND b.checkedInAt IS NOT NULL) AS checkedSeats
             FROM showtime s
             JOIN movie m ON m.id = s.movieId
             JOIN room r ON r.id = s.roomId
             JOIN cinema c ON c.id = r.cinemaId
             WHERE s.status = 'open' AND s.startTime >= CURDATE() AND s.startTime < CURDATE() + INTERVAL 1 DAY
             ORDER BY s.startTime"
        );
    }
}
