<?php
namespace App\Controllers\Admin;

use App\Core\ValidationException;
use App\Models\Booking;

/**
 * Đơn đặt vé: tra cứu theo mã / khách / phim, xem chi tiết, hủy đơn
 */
class BookingController extends AdminController
{
    const STATUSES = ['paid' => 'Thành công', 'pending' => 'Chờ thanh toán', 'cancelled' => 'Đã hủy', 'expired' => 'Hết hạn'];

    public function index()
    {
        (new Booking())->releaseExpired();
        $keyword = mb_substr($this->query('q'), 0, 100);
        $status = array_key_exists($this->query('status'), self::STATUSES) ? $this->query('status') : '';
        $from = $this->validDate($this->query('from')) ? $this->query('from') : '';
        $to = $this->validDate($this->query('to')) ? $this->query('to') : '';

        $where = '1 = 1';
        $params = [];
        if ($keyword !== '') {
            $where .= " AND (b.code LIKE ? ESCAPE '\\\\' OR u.name LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\' OR u.phoneNumber LIKE ? ESCAPE '\\\\' OR m.name LIKE ? ESCAPE '\\\\')";
            $params = array_merge($params, array_fill(0, 5, $this->like($keyword)));
        }
        if ($status !== '') {
            $where .= ' AND b.status = ?';
            $params[] = $status;
        }
        if ($from !== '') {
            $where .= ' AND b.createdAt >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where .= ' AND b.createdAt < ?';
            $params[] = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
        }

        $join = 'FROM booking b JOIN users u ON u.id = b.userId JOIN showtime s ON s.id = b.showtimeId
                 JOIN movie m ON m.id = s.movieId JOIN room r ON r.id = s.roomId JOIN cinema c ON c.id = r.cinemaId';
        $pager = $this->paginator((int)$this->db->query("SELECT COUNT(*) AS c {$join} WHERE {$where}", $params, true)['c'], 15);
        $bookings = $this->db->query(
            "SELECT b.id, b.code, b.status, b.totalAmount, b.expiredAt, b.checkedInAt, b.createdAt,
                    u.name AS customerName, u.email, m.name AS movieName, c.name AS cinemaName, r.name AS roomName, s.startTime,
                    (SELECT COUNT(*) FROM booking_seat bs WHERE bs.bookingId = b.id) AS seatCount,
                    (SELECT p.method FROM payment p WHERE p.bookingId = b.id ORDER BY p.status = 'success' DESC, p.id DESC LIMIT 1) AS method
             {$join} WHERE {$where}
             ORDER BY b.id DESC LIMIT ? OFFSET ?",
            array_merge($params, [$pager['perPage'], $pager['offset']])
        );

        return $this->page('bookings.index', [
            'bookings' => $bookings,
            'pager'    => $pager,
            'filters'  => ['q' => $keyword, 'status' => $status, 'from' => $from, 'to' => $to],
            'statuses' => self::STATUSES,
        ]);
    }

    public function show($id)
    {
        $booking = ctype_digit((string)$id) ? $this->db->query(
            "SELECT b.*, s.startTime, s.endTime, s.format, s.language, s.basePrice,
                    m.name AS movieName, m.image AS moviePoster, m.slug AS movieSlug, m.duration,
                    r.name AS roomName, c.name AS cinemaName,
                    u.name AS customerName, u.email, u.phoneNumber, ml.name AS levelName,
                    v.code AS voucherCode, st.name AS checkedInByName
             FROM booking b
             JOIN showtime s ON s.id = b.showtimeId
             JOIN movie m ON m.id = s.movieId
             JOIN room r ON r.id = s.roomId
             JOIN cinema c ON c.id = r.cinemaId
             JOIN users u ON u.id = b.userId
             LEFT JOIN member_level ml ON ml.id = u.memberLevelId
             LEFT JOIN voucher v ON v.id = b.voucherId
             LEFT JOIN users st ON st.id = b.checkedInBy
             WHERE b.id = ?",
            [(int)$id],
            true
        ) : null;
        if (!$booking) {
            $this->notFound();
        }

        $model = new Booking();

        return $this->page('bookings.detail', [
            'booking'  => $booking,
            'seats'    => $model->getSeats($booking['id']),
            'products' => $model->getProducts($booking['id']),
            'payments' => $model->getPayments($booking['id']),
            'points'   => $this->db->query('SELECT points, note, createdAt FROM point_history WHERE bookingId = ? ORDER BY id', [(int)$booking['id']]),
        ]);
    }

    public function cancel($id)
    {
        if (!ctype_digit((string)$id)) {
            $this->notFound();
        }

        try {
            $result = (new Booking())->cancelByAdmin((int)$id, (int)\AuthHelper::id());
        } catch (ValidationException $e) {
            return $this->done('/admin/bookings/' . (int)$id, $e->getMessage(), 'danger');
        }

        return $this->done('/admin/bookings/' . (int)$id, $result === 'refund'
            ? 'Đã hủy đơn, nhả ghế và hoàn điểm. Cần hoàn tiền thủ công cho khách (payment đã chuyển "refunded").'
            : 'Đã hủy đơn và nhả ghế.');
    }
}
