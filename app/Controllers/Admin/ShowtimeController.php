<?php
namespace App\Controllers\Admin;

use App\Support\Admin;

/**
 * Quản lý suất chiếu: timeline theo rạp + ngày, danh sách suất của 1 phòng, form thêm / sửa ở khung bên.
 *
 * endTime = startTime + thời lượng phim + CLEANING_MINUTES (dọn phòng).
 * Trigger trg_showtime_no_overlap_* trong DB là chốt chặn cuối; PHP kiểm tra trước để báo lỗi rõ ràng.
 */
class ShowtimeController extends AdminController
{
    const CLEANING_MINUTES = 15;
    const DAY_START = 8;     // timeline 08:00 -> 24:00
    const DAY_END = 24;

    public function index()
    {
        $cinemas = $this->db->query("SELECT id, name FROM cinema ORDER BY status = 'active' DESC, name");
        $cinemaId = (int)$this->query('cinema') ?: (int)($cinemas[0]['id'] ?? 0);
        $date = $this->validDate($this->query('date')) ? $this->query('date') : date('Y-m-d');
        $rooms = $this->db->query('SELECT * FROM room WHERE cinemaId = ? ORDER BY name', [$cinemaId]);
        $roomIds = array_map('intval', array_column($rooms, 'id'));
        $roomId = in_array((int)$this->query('room'), $roomIds, true) ? (int)$this->query('room') : ($roomIds[0] ?? 0);

        $showtimes = $roomIds ? $this->db->query(
            "SELECT s.*, m.name AS movieName, m.duration,
                    (SELECT COUNT(*) FROM booking_seat bs WHERE bs.showtimeId = s.id) AS sold,
                    (SELECT COUNT(*) FROM seat se WHERE se.roomId = s.roomId AND se.status = 'active') AS capacity
             FROM showtime s JOIN movie m ON m.id = s.movieId
             WHERE s.roomId IN (" . implode(',', $roomIds) . ") AND s.startTime >= ? AND s.startTime < ?
             ORDER BY s.startTime",
            [$date . ' 00:00:00', date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00']
        ) : [];

        $editId = (int)$this->query('edit');
        $editing = null;
        foreach ($showtimes as $s) {
            if ((int)$s['id'] === $editId) {
                $editing = $s;
            }
        }
        if ($editId && !$editing) {
            $editing = $this->db->query('SELECT s.*, m.name AS movieName FROM showtime s JOIN movie m ON m.id = s.movieId WHERE s.id = ?', [$editId], true);
        }

        return $this->page('showtimes.index', [
            'cinemas'   => $cinemas,
            'cinemaId'  => $cinemaId,
            'date'      => $date,
            'rooms'     => $rooms,
            'roomId'    => $roomId,
            'showtimes' => $showtimes,
            'editing'   => $editing,
            'movies'    => $this->db->query("SELECT id, name, duration, status FROM movie WHERE status <> 'ended' OR id = ? ORDER BY status = 'now_showing' DESC, name", [(int)($editing['movieId'] ?? 0)]),
            'allRooms'  => $this->db->query('SELECT r.id, r.name, r.cinemaId, r.roomType, r.status FROM room r ORDER BY r.cinemaId, r.name'),
            'cleaning'  => self::CLEANING_MINUTES,
            'dayStart'  => self::DAY_START,
            'dayEnd'    => self::DAY_END,
        ]);
    }

    public function store()
    {
        return $this->save(null);
    }

    public function update($id)
    {
        return $this->save($this->find($id));
    }

    /**
     * Xóa suất chưa có vé. Suất đã có vé chỉ được chuyển "Đã hủy" (giữ lịch sử đơn).
     */
    public function destroy($id)
    {
        $showtime = $this->find($id);
        $back = $this->backUrl($showtime);
        if ($this->db->query('SELECT 1 FROM booking WHERE showtimeId = ? LIMIT 1', [(int)$showtime['id']], true)) {
            return $this->done($back, 'Suất chiếu đã có đơn đặt vé, không thể xóa. Hãy chuyển trạng thái sang "Đã hủy".', 'danger');
        }

        $this->db->execute('DELETE FROM showtime WHERE id = ?', [(int)$showtime['id']]);

        return $this->done($back, 'Đã xóa suất ' . date('H:i d/m', strtotime($showtime['startTime'])));
    }

    // ---------------- Helpers ----------------

    private function find($id)
    {
        $showtime = ctype_digit((string)$id) ? $this->db->query(
            'SELECT s.*, r.cinemaId FROM showtime s JOIN room r ON r.id = s.roomId WHERE s.id = ?',
            [(int)$id],
            true
        ) : null;
        if (!$showtime) {
            $this->notFound();
        }

        return $showtime;
    }

    private function backUrl(array $showtime)
    {
        return '/admin/showtimes?' . http_build_query([
            'cinema' => $showtime['cinemaId'],
            'room'   => $showtime['roomId'],
            'date'   => date('Y-m-d', strtotime($showtime['startTime'])),
        ]);
    }

    private function save($showtime)
    {
        $movieId = (int)$this->input('movieId');
        $roomId = (int)$this->input('roomId');
        $date = $this->input('date');
        $time = $this->input('time');
        $format = $this->input('format');
        $language = $this->input('language');
        $price = $this->input('basePrice');
        $status = $this->input('status', 'open');

        $errors = [];
        $movie = $movieId ? $this->db->query('SELECT id, name, duration, status FROM movie WHERE id = ?', [$movieId], true) : null;
        $room = $roomId ? $this->db->query('SELECT r.*, c.name AS cinemaName FROM room r JOIN cinema c ON c.id = r.cinemaId WHERE r.id = ?', [$roomId], true) : null;
        if (!$movie) {
            $errors['movieId'] = 'Chọn phim';
        } elseif ($movie['status'] === 'ended' && (int)($showtime['movieId'] ?? 0) !== $movieId) {
            $errors['movieId'] = 'Phim đã kết thúc chiếu';
        }
        if (!$room) {
            $errors['roomId'] = 'Chọn phòng chiếu';
        }
        if (!$this->validDate($date)) {
            $errors['date'] = 'Ngày chiếu không hợp lệ';
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $errors['time'] = 'Giờ bắt đầu không hợp lệ';
        }
        if (!in_array($format, ['2D', '3D'], true)) {
            $errors['format'] = 'Chọn định dạng';
        }
        if (!in_array($language, ['sub', 'dub'], true)) {
            $errors['language'] = 'Chọn phụ đề / lồng tiếng';
        }
        if (!ctype_digit($price) || (int)$price < 10000 || (int)$price > 1000000) {
            $errors['basePrice'] = 'Giá vé từ 10.000đ đến 1.000.000đ';
        }
        if (!array_key_exists($status, Admin::SHOWTIME_STATUSES)) {
            $errors['status'] = 'Trạng thái không hợp lệ';
        }
        if ($errors) {
            return $this->invalid($errors);
        }

        $start = strtotime($date . ' ' . $time . ':00');
        $end = $start + ((int)$movie['duration'] + self::CLEANING_MINUTES) * 60;
        $hasBookings = $showtime && $this->db->query('SELECT 1 FROM booking WHERE showtimeId = ? LIMIT 1', [(int)$showtime['id']], true);

        if (!$showtime && $start <= time()) {
            $errors['time'] = 'Không tạo được suất chiếu trong quá khứ';
        }
        if ($hasBookings && ((int)$showtime['movieId'] !== $movieId || (int)$showtime['roomId'] !== $roomId)) {
            $errors['roomId'] = 'Suất đã có vé, không đổi được phim / phòng';
        }
        if ($hasBookings && (int)$price !== (int)$showtime['basePrice']) {
            $errors['basePrice'] = 'Suất đã có vé, không đổi được giá';
        }
        if ($status !== 'cancelled') {
            if ($room['status'] !== 'active') {
                $errors['roomId'] = $room['name'] . ' đang bảo trì';
            }
            $conflict = $this->db->query(
                "SELECT s.startTime, s.endTime, m.name FROM showtime s JOIN movie m ON m.id = s.movieId
                 WHERE s.roomId = ? AND s.id <> ? AND s.status <> 'cancelled' AND s.startTime < ? AND s.endTime > ?
                 ORDER BY s.startTime LIMIT 1",
                [$roomId, (int)($showtime['id'] ?? 0), date('Y-m-d H:i:s', $end), date('Y-m-d H:i:s', $start)],
                true
            );
            if ($conflict) {
                $errors['time'] = sprintf(
                    'Trùng suất %s–%s "%s" trong %s (đã tính %d phút dọn phòng)',
                    date('H:i', strtotime($conflict['startTime'])),
                    date('H:i', strtotime($conflict['endTime'])),
                    $conflict['name'],
                    $room['name'],
                    self::CLEANING_MINUTES
                );
            }
        }
        if ($errors) {
            return $this->invalid($errors);
        }

        $data = [
            'movieId'   => $movieId,
            'roomId'    => $roomId,
            'startTime' => date('Y-m-d H:i:s', $start),
            'endTime'   => date('Y-m-d H:i:s', $end),
            'format'    => $format,
            'language'  => $language,
            'basePrice' => (int)$price,
            'status'    => $status,
        ];

        try {
            if ($showtime) {
                $this->db->update('showtime', $data, 'id = ?', [(int)$showtime['id']]);
            } else {
                $this->db->insert('showtime', $data);
            }
        } catch (\PDOException $e) {
            // Trigger chống trùng giờ (2 admin cùng lưu một lúc)
            if ($e->getCode() === '45000') {
                return $this->invalid(['time' => 'Suất chiếu bị trùng giờ trong phòng này, vui lòng chọn giờ khác']);
            }
            throw $e;
        }

        return $this->done(
            $this->backUrl(['cinemaId' => $room['cinemaId'], 'roomId' => $roomId, 'startTime' => $data['startTime']]),
            ($showtime ? 'Đã cập nhật suất ' : 'Đã thêm suất ') . date('H:i d/m', $start) . ' · ' . $movie['name'] . ' · ' . $room['name']
        );
    }
}
