<?php
namespace App\Controllers\Admin;

use App\Support\Admin;

/**
 * Quản lý rạp (bước 2-3/3): phòng chiếu của rạp + sơ đồ ghế
 *
 * Sơ đồ ghế sinh bằng procedure sp_generate_seats (3 hàng đầu thường, giữa VIP, hàng cuối Sweetbox).
 * Sinh lại sơ đồ xóa toàn bộ ghế cũ, nên chỉ được phép khi chưa có vé nào đặt ghế của phòng.
 */
class RoomController extends AdminController
{
    const MAX_ROWS = 20;     // A..T
    const MAX_COLS = 30;
    const SEAT_STATUSES = ['active', 'broken', 'hidden'];

    public function index($cinemaId)
    {
        $cinema = $this->findCinema($cinemaId);
        $rooms = $this->db->query(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM seat se WHERE se.roomId = r.id AND se.status = 'active') AS seatCount,
                    (SELECT COUNT(*) FROM showtime s WHERE s.roomId = r.id AND s.startTime > NOW() AND s.status = 'open') AS upcoming,
                    (SELECT COUNT(*) FROM showtime s WHERE s.roomId = r.id) AS showtimeCount
             FROM room r WHERE r.cinemaId = ? ORDER BY r.name",
            [(int)$cinema['id']]
        );
        $editId = (int)$this->query('edit');
        $editing = null;
        foreach ($rooms as $room) {
            if ((int)$room['id'] === $editId) {
                $editing = $room;
            }
        }

        return $this->page('cinemas.rooms', ['cinema' => $cinema, 'rooms' => $rooms, 'editing' => $editing]);
    }

    public function store($cinemaId)
    {
        $cinema = $this->findCinema($cinemaId);
        $data = $this->roomInput();
        $errors = $this->validateRoom($data, (int)$cinema['id'], 0);
        if ($errors) {
            return $this->invalid($errors);
        }

        $this->db->beginTransaction();
        try {
            $roomId = (int)$this->db->insert('room', $data + ['cinemaId' => (int)$cinema['id']]);
            $this->db->execute('CALL sp_generate_seats(?)', [$roomId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->done('/admin/rooms/' . $roomId . '/seats', 'Đã thêm ' . $data['name'] . ' và sinh sơ đồ ' . $data['totalRows'] . '×' . $data['totalCols'] . ' ghế. Chỉnh ghế hỏng / lối đi nếu cần.');
    }

    public function update($id)
    {
        $room = $this->findRoom($id);
        $data = $this->roomInput();
        $errors = $this->validateRoom($data, (int)$room['cinemaId'], (int)$room['id']);
        $sizeChanged = (int)$data['totalRows'] !== (int)$room['totalRows'] || (int)$data['totalCols'] !== (int)$room['totalCols'];
        if ($sizeChanged && $this->hasBookedSeats($room['id'])) {
            $errors['totalRows'] = 'Phòng đã có vé đặt, không đổi được số hàng / số ghế';
        }
        if ($errors) {
            return $this->invalid($errors);
        }

        $this->db->beginTransaction();
        try {
            $this->db->update('room', $data, 'id = ?', [(int)$room['id']]);
            if ($sizeChanged) {
                $this->db->execute('CALL sp_generate_seats(?)', [(int)$room['id']]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->done('/admin/cinemas/' . $room['cinemaId'] . '/rooms', 'Đã cập nhật ' . $data['name'] . ($sizeChanged ? ' và sinh lại sơ đồ ghế' : ''));
    }

    public function destroy($id)
    {
        $room = $this->findRoom($id);
        $back = '/admin/cinemas/' . $room['cinemaId'] . '/rooms';
        if ((int)$this->db->query('SELECT COUNT(*) AS c FROM showtime WHERE roomId = ?', [(int)$room['id']], true)['c'] > 0) {
            return $this->done($back, $room['name'] . ' đã có suất chiếu, không thể xóa. Hãy chuyển sang "Bảo trì".', 'danger');
        }

        $this->db->execute('DELETE FROM room WHERE id = ?', [(int)$room['id']]);   // ghế xóa theo CASCADE

        return $this->done($back, 'Đã xóa ' . $room['name']);
    }

    public function seats($id)
    {
        $room = $this->findRoom($id);
        $seats = $this->db->query(
            "SELECT se.id, se.rowLabel, se.seatNumber, se.status, se.seatTypeId, st.code AS typeCode
             FROM seat se JOIN seat_type st ON st.id = se.seatTypeId
             WHERE se.roomId = ? ORDER BY LENGTH(se.rowLabel), se.rowLabel, se.seatNumber",
            [(int)$room['id']]
        );
        $rows = [];
        foreach ($seats as $seat) {
            $rows[$seat['rowLabel']][] = $seat;
        }

        return $this->page('cinemas.seats', [
            'room'      => $room,
            'cinema'    => $this->findCinema($room['cinemaId']),
            'rows'      => $rows,
            'seatTypes' => $this->db->query('SELECT * FROM seat_type ORDER BY extraPrice, id'),
            'locked'    => $this->hasBookedSeats($room['id']),
            'stats'     => [
                'active' => count(array_filter($seats, function ($s) { return $s['status'] === 'active'; })),
                'broken' => count(array_filter($seats, function ($s) { return $s['status'] === 'broken'; })),
                'hidden' => count(array_filter($seats, function ($s) { return $s['status'] === 'hidden'; })),
            ],
        ]);
    }

    /**
     * Lưu sơ đồ: trạng thái từng ghế, loại ghế theo hàng (thường / VIP), giá loại ghế, phụ thu phòng.
     * Ghế đã có vé ở suất sắp chiếu không được chuyển sang hỏng / lối đi.
     */
    public function saveSeats($id)
    {
        $room = $this->findRoom($id);
        $back = '/admin/rooms/' . $room['id'] . '/seats';

        $types = array_column($this->db->query('SELECT * FROM seat_type'), null, 'id');
        $typeByCode = array_column($types, 'id', 'code');
        $seats = array_column($this->db->query(
            'SELECT se.id, se.rowLabel, se.seatNumber, se.status, se.seatTypeId FROM seat se WHERE se.roomId = ?',
            [(int)$room['id']]
        ), null, 'id');

        $errors = [];
        $surcharge = $this->input('surcharge');
        if (!ctype_digit($surcharge) || (int)$surcharge > 1000000) {
            $errors['surcharge'] = 'Phụ thu từ 0 đến 1.000.000đ';
        }
        $prices = is_array($_POST['prices'] ?? null) ? $_POST['prices'] : [];
        foreach ($prices as $typeId => $price) {
            if (!isset($types[$typeId]) || !is_string($price) || !ctype_digit($price) || (int)$price > 1000000) {
                $errors['prices[' . (int)$typeId . ']'] = 'Giá cộng thêm từ 0 đến 1.000.000đ';
            }
        }

        // Loại ghế theo hàng: chỉ đổi qua lại thường <-> VIP (Sweetbox là ghế đôi, đổi bằng cách sinh lại sơ đồ)
        $rowTypes = is_array($_POST['rowType'] ?? null) ? $_POST['rowType'] : [];
        $switchable = array_filter([$typeByCode['standard'] ?? null, $typeByCode['vip'] ?? null]);
        $seatStatus = is_array($_POST['seat'] ?? null) ? $_POST['seat'] : [];

        // Ghế đang có vé (đã trả / đang giữ) ở suất chưa chiếu xong
        $booked = array_flip(array_map('intval', array_column($this->db->query(
            "SELECT DISTINCT bs.seatId FROM booking_seat bs
             JOIN showtime s ON s.id = bs.showtimeId
             WHERE s.roomId = ? AND s.endTime > NOW()",
            [(int)$room['id']]
        ), 'seatId')));
        $blocked = [];
        foreach ($seatStatus as $seatId => $status) {
            if (!isset($seats[$seatId]) || !in_array($status, self::SEAT_STATUSES, true)) {
                return $this->invalid(['seat' => 'Dữ liệu ghế không hợp lệ, vui lòng tải lại trang']);
            }
            if ($status !== 'active' && isset($booked[(int)$seatId]) && $seats[$seatId]['status'] === 'active') {
                $blocked[] = $seats[$seatId]['rowLabel'] . $seats[$seatId]['seatNumber'];
            }
        }
        if ($blocked) {
            $errors['seat'] = 'Ghế ' . implode(', ', $blocked) . ' đã có vé ở suất sắp chiếu, chưa thể chuyển sang hỏng / lối đi';
        }
        if ($errors) {
            return $this->invalid($errors);
        }

        $this->db->beginTransaction();
        try {
            $this->db->update('room', ['surcharge' => (int)$surcharge], 'id = ?', [(int)$room['id']]);
            foreach ($prices as $typeId => $price) {
                $this->db->update('seat_type', ['extraPrice' => (int)$price], 'id = ?', [(int)$typeId]);
            }
            foreach ($rowTypes as $row => $typeId) {
                if (is_string($row) && preg_match('/^[A-Z]{1,2}$/', $row) && in_array((int)$typeId, array_map('intval', $switchable), true)) {
                    $this->db->execute(
                        'UPDATE seat SET seatTypeId = ? WHERE roomId = ? AND rowLabel = ? AND seatTypeId IN (' . implode(',', array_map('intval', $switchable)) . ')',
                        [(int)$typeId, (int)$room['id'], $row]
                    );
                }
            }
            foreach ($seatStatus as $seatId => $status) {
                if ($seats[$seatId]['status'] !== $status) {
                    $this->db->update('seat', ['status' => $status], 'id = ? AND roomId = ?', [(int)$seatId, (int)$room['id']]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->done($back, 'Đã lưu sơ đồ ghế ' . $room['name']);
    }

    public function generateSeats($id)
    {
        $room = $this->findRoom($id);
        $back = '/admin/rooms/' . $room['id'] . '/seats';
        if ($this->hasBookedSeats($room['id'])) {
            return $this->done($back, 'Phòng đã có vé đặt ghế, không thể sinh lại sơ đồ', 'danger');
        }

        $this->db->execute('CALL sp_generate_seats(?)', [(int)$room['id']]);

        return $this->done($back, 'Đã sinh lại sơ đồ ghế mặc định cho ' . $room['name']);
    }

    // ---------------- Helpers ----------------

    private function findCinema($id)
    {
        $cinema = ctype_digit((string)$id)
            ? $this->db->query('SELECT c.*, ci.name AS cityName FROM cinema c JOIN city ci ON ci.id = c.cityId WHERE c.id = ?', [(int)$id], true)
            : null;
        if (!$cinema) {
            $this->notFound();
        }

        return $cinema;
    }

    private function findRoom($id)
    {
        $room = ctype_digit((string)$id) ? $this->db->query('SELECT * FROM room WHERE id = ?', [(int)$id], true) : null;
        if (!$room) {
            $this->notFound();
        }

        return $room;
    }

    /** booking_seat giữ FK tới seat: còn dòng nào là không được xóa / sinh lại ghế */
    private function hasBookedSeats($roomId)
    {
        return (bool)$this->db->query(
            'SELECT 1 FROM booking_seat bs JOIN seat se ON se.id = bs.seatId WHERE se.roomId = ? LIMIT 1',
            [(int)$roomId],
            true
        );
    }

    private function roomInput()
    {
        return [
            'name'      => mb_substr($this->input('name'), 0, 50),
            'roomType'  => $this->input('roomType'),
            'totalRows' => $this->input('totalRows'),
            'totalCols' => $this->input('totalCols'),
            'surcharge' => $this->input('surcharge', '0'),
            'status'    => $this->input('status') === 'maintenance' ? 'maintenance' : 'active',
        ];
    }

    private function validateRoom(array &$data, $cinemaId, $roomId)
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors['name'] = 'Vui lòng nhập tên phòng';
        } elseif ($this->db->query('SELECT id FROM room WHERE cinemaId = ? AND name = ? AND id <> ?', [$cinemaId, $data['name'], $roomId], true)) {
            $errors['name'] = 'Rạp đã có phòng tên này';
        }
        if (!array_key_exists($data['roomType'], Admin::ROOM_TYPES)) {
            $errors['roomType'] = 'Chọn loại phòng';
        }
        if (!ctype_digit($data['totalRows']) || (int)$data['totalRows'] < 2 || (int)$data['totalRows'] > self::MAX_ROWS) {
            $errors['totalRows'] = 'Số hàng từ 2 đến ' . self::MAX_ROWS;
        }
        if (!ctype_digit($data['totalCols']) || (int)$data['totalCols'] < 2 || (int)$data['totalCols'] > self::MAX_COLS) {
            $errors['totalCols'] = 'Số ghế mỗi hàng từ 2 đến ' . self::MAX_COLS;
        }
        if (!ctype_digit($data['surcharge']) || (int)$data['surcharge'] > 1000000) {
            $errors['surcharge'] = 'Phụ thu từ 0 đến 1.000.000đ';
        }
        foreach (['totalRows', 'totalCols', 'surcharge'] as $key) {
            $data[$key] = (int)$data[$key];
        }

        return $errors;
    }
}
