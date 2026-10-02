<?php
namespace App\Models;

/**
 * Số liệu doanh thu cho Dashboard và trang Thống kê doanh thu.
 *
 * Doanh thu = đơn đặt vé đã thanh toán (booking.status = 'paid'), tính theo ngày nhận tiền
 * (payment.paidAt của lần thanh toán thành công), không theo ngày tạo đơn:
 * đơn trả tại quầy có thể tạo trước nhiều ngày rồi mới thu tiền.
 * Đơn bị hủy sau khi trả tiền chuyển sang 'cancelled' nên tự rời khỏi số liệu.
 */
class Report extends BaseModel
{
    const FROM_PAID = "FROM booking b
        JOIN (SELECT bookingId, MAX(paidAt) AS paidAt FROM payment WHERE status = 'success' GROUP BY bookingId) pay ON pay.bookingId = b.id
        JOIN showtime s ON s.id = b.showtimeId
        JOIN movie m ON m.id = s.movieId
        JOIN room r ON r.id = s.roomId
        JOIN cinema c ON c.id = r.cinemaId";

    const TICKETS = '(SELECT COUNT(*) FROM booking_seat bs WHERE bs.bookingId = b.id)';

    protected $table = 'booking';

    /**
     * @param string $from Y-m-d (gồm cả ngày này)
     * @param string $to   Y-m-d (gồm cả ngày này)
     * @return array [sql, params]
     */
    private function where($from, $to, array $cinemaIds = [], array $movieIds = [])
    {
        $sql = "b.status = 'paid' AND pay.paidAt >= ? AND pay.paidAt < ?";
        $params = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];

        foreach (['c.id' => $cinemaIds, 'm.id' => $movieIds] as $column => $ids) {
            $ids = array_values(array_map('intval', $ids));
            if ($ids) {
                $sql .= " AND {$column} IN (" . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = array_merge($params, $ids);
            }
        }

        return [$sql, $params];
    }

    public function summary($from, $to, array $cinemaIds = [], array $movieIds = [])
    {
        [$where, $params] = $this->where($from, $to, $cinemaIds, $movieIds);
        $row = $this->db->query(
            "SELECT COUNT(*) AS orders,
                    COALESCE(SUM(b.totalAmount), 0) AS revenue,
                    COALESCE(SUM(b.ticketAmount), 0) AS ticketRevenue,
                    COALESCE(SUM(b.productAmount), 0) AS productRevenue,
                    COALESCE(SUM(b.discountAmount), 0) AS discount,
                    COALESCE(SUM(" . self::TICKETS . "), 0) AS tickets
             " . self::FROM_PAID . " WHERE {$where}",
            $params,
            true
        );

        return array_map('intval', $row);
    }

    /**
     * Doanh thu từng ngày, đủ mọi ngày trong khoảng (ngày không bán = 0)
     * @return array [['day' => Y-m-d, 'revenue' => int, 'tickets' => int], ...]
     */
    public function byDay($from, $to, array $cinemaIds = [], array $movieIds = [])
    {
        [$where, $params] = $this->where($from, $to, $cinemaIds, $movieIds);
        $rows = $this->db->query(
            "SELECT DATE(pay.paidAt) AS day, SUM(b.totalAmount) AS revenue, SUM(" . self::TICKETS . ") AS tickets
             " . self::FROM_PAID . " WHERE {$where}
             GROUP BY DATE(pay.paidAt)",
            $params
        );
        $map = array_column($rows, null, 'day');

        $days = [];
        for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime('+1 day', $t)) {
            $day = date('Y-m-d', $t);
            $days[] = [
                'day'     => $day,
                'revenue' => (int)($map[$day]['revenue'] ?? 0),
                'tickets' => (int)($map[$day]['tickets'] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * @param string $orderBy revenue | tickets
     */
    public function byMovie($from, $to, $limit = 10, $orderBy = 'revenue', array $cinemaIds = [], array $movieIds = [])
    {
        [$where, $params] = $this->where($from, $to, $cinemaIds, $movieIds);
        $order = $orderBy === 'tickets' ? 'tickets DESC, revenue DESC' : 'revenue DESC, tickets DESC';

        return array_map([$this, 'ints'], $this->db->query(
            "SELECT m.id, m.name, SUM(" . self::TICKETS . ") AS tickets, SUM(b.totalAmount) AS revenue
             " . self::FROM_PAID . " WHERE {$where}
             GROUP BY m.id, m.name
             ORDER BY {$order}
             LIMIT ?",
            array_merge($params, [(int)$limit])
        ));
    }

    public function byCinema($from, $to, array $cinemaIds = [], array $movieIds = [])
    {
        [$where, $params] = $this->where($from, $to, $cinemaIds, $movieIds);

        return array_map([$this, 'ints'], $this->db->query(
            "SELECT c.id, c.name, SUM(" . self::TICKETS . ") AS tickets, SUM(b.totalAmount) AS revenue
             " . self::FROM_PAID . " WHERE {$where}
             GROUP BY c.id, c.name
             ORDER BY revenue DESC",
            $params
        ));
    }

    /**
     * Số vé theo giờ bắt đầu suất chiếu (giờ cao điểm), đủ các giờ từ $firstHour..$lastHour
     */
    public function byHour($from, $to, array $cinemaIds = [], array $movieIds = [], $firstHour = 8, $lastHour = 23)
    {
        [$where, $params] = $this->where($from, $to, $cinemaIds, $movieIds);
        $rows = $this->db->query(
            "SELECT HOUR(s.startTime) AS hour, SUM(" . self::TICKETS . ") AS tickets
             " . self::FROM_PAID . " WHERE {$where}
             GROUP BY HOUR(s.startTime)",
            $params
        );
        $map = array_column($rows, 'tickets', 'hour');
        $hours = array_map('intval', array_keys($map));
        $firstHour = $hours ? min($firstHour, min($hours)) : $firstHour;
        $lastHour = $hours ? max($lastHour, max($hours)) : $lastHour;

        $result = [];
        for ($h = $firstHour; $h <= $lastHour; $h++) {
            $result[] = ['hour' => $h, 'tickets' => (int)($map[$h] ?? 0)];
        }

        return $result;
    }

    /**
     * Chi tiết theo phim × rạp
     */
    public function detail($from, $to, array $cinemaIds = [], array $movieIds = [])
    {
        [$where, $params] = $this->where($from, $to, $cinemaIds, $movieIds);

        return array_map([$this, 'ints'], $this->db->query(
            "SELECT m.name AS movieName, c.name AS cinemaName,
                    COUNT(*) AS orders,
                    SUM(" . self::TICKETS . ") AS tickets,
                    SUM(b.ticketAmount) AS ticketRevenue,
                    SUM(b.productAmount) AS productRevenue,
                    SUM(b.discountAmount) AS discount,
                    SUM(b.totalAmount) AS revenue
             " . self::FROM_PAID . " WHERE {$where}
             GROUP BY m.id, m.name, c.id, c.name
             ORDER BY revenue DESC, m.name, c.name",
            $params
        ));
    }

    /**
     * Bán bắp nước tại quầy (POS) - không gắn rạp / phim
     */
    public function posSummary($from, $to)
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS orders, COALESCE(SUM(totalAmount), 0) AS revenue
             FROM pos_order WHERE createdAt >= ? AND createdAt < ?',
            [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'],
            true
        );

        return array_map('intval', $row);
    }

    /**
     * Đơn đặt vé mới nhất (mọi trạng thái) cho Dashboard
     */
    public function recentBookings($limit = 10)
    {
        return $this->db->query(
            "SELECT b.id, b.code, b.status, b.totalAmount, b.expiredAt, b.createdAt,
                    u.name AS customerName, m.name AS movieName, c.name AS cinemaName,
                    (SELECT p.method FROM payment p WHERE p.bookingId = b.id
                     ORDER BY p.status = 'success' DESC, p.id DESC LIMIT 1) AS method
             FROM booking b
             JOIN users u ON u.id = b.userId
             JOIN showtime s ON s.id = b.showtimeId
             JOIN movie m ON m.id = s.movieId
             JOIN room r ON r.id = s.roomId
             JOIN cinema c ON c.id = r.cinemaId
             ORDER BY b.id DESC
             LIMIT ?",
            [(int)$limit]
        );
    }

    private function ints(array $row)
    {
        foreach (['id', 'orders', 'tickets', 'revenue', 'ticketRevenue', 'productRevenue', 'discount'] as $key) {
            if (isset($row[$key])) {
                $row[$key] = (int)$row[$key];
            }
        }

        return $row;
    }
}
