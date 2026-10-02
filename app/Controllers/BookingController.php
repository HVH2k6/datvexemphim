<?php
namespace App\Controllers;

use App\Core\ValidationException;
use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Product;
use App\Models\Showtime;
use App\Models\User;

class BookingController extends BaseController
{
    /**
     * /booking/movie/{slug}: chọn suất chiếu nằm ở trang chi tiết phim
     */
    public function selectMovie($movieSlug)
    {
        $movie = (new Movie())->getMovieBySlug($movieSlug);
        if (!$movie) {
            return $this->notFound();
        }

        return $this->redirect('/movies/' . rawurlencode($movie['slug']) . '#lich-chieu');
    }

    public function showSeats($showtimeId)
    {
        $showtime = $this->findBookableShowtime($showtimeId);
        if (!$showtime) {
            $_SESSION['flash_message'] = 'Suất chiếu không còn bán vé';
            $_SESSION['flash_type'] = 'warning';
            return $this->redirect('/movies');
        }

        (new Booking())->releaseExpired();
        $user = (new User())->getUserById(\AuthHelper::id());
        $seats = (new Showtime())->getSeatMap($showtime, $user['id']);

        $rows = [];
        foreach ($seats as $seat) {
            if ($seat['status'] !== 'hidden') {
                $rows[$seat['rowLabel']][] = $seat;
            }
        }

        // Lối đi: chia hàng ghế thường thành 3 cụm (vd 12 cột -> 3 | 6 | 3), sau ghế số $aisles[i]
        $cols = (int)$showtime['totalCols'];
        $side = $cols >= 8 ? (int)floor($cols / 4) : 0;
        $aisles = $side ? [$side, $cols - $side] : [];

        return $this->render('customer.seats', [
            'showtime'   => $showtime,
            'rows'       => $rows,
            'aisles'     => $aisles,
            'totalCols'  => $cols,
            'seatTypes'  => $this->seatTypesOf($seats),
            'products'   => (new Product())->getActive(),
            'user'       => $user,
            'maxSeats'   => Booking::MAX_SEATS,
            'pointValue' => Booking::POINT_VALUE,
            'timeout'    => \App\Models\Setting::holdMinutes(),
        ]);
    }

    /**
     * AJAX: giữ ghế + tạo đơn pending, trả về URL trang thanh toán
     */
    public function saveSeats()
    {
        $showtime = $this->findBookableShowtime($_POST['showtimeId'] ?? 0);
        if (!$showtime) {
            return $this->error('Suất chiếu không còn bán vé', 422);
        }

        try {
            $user = (new User())->getUserById(\AuthHelper::id());
            $booking = (new Booking())->hold(
                $user,
                $showtime,
                (array)($_POST['seatIds'] ?? []),
                (array)($_POST['products'] ?? []),
                $_POST['voucherCode'] ?? '',
                $_POST['points'] ?? 0
            );
        } catch (ValidationException $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage(), 'field' => $e->getField()], 422);
        }

        return $this->success([
            'bookingId'   => $booking['id'],
            'code'        => $booking['code'],
            'redirectUrl' => \CommonHelper::url('checkout/' . $booking['id']),
        ], 'Đã giữ ghế');
    }

    /**
     * AJAX: kiểm tra voucher / điểm, trả về bảng giá đã tính ở server
     */
    public function validateVoucher()
    {
        return $this->quoteResponse();
    }

    public function applyPoints()
    {
        return $this->quoteResponse();
    }

    private function quoteResponse()
    {
        $showtime = $this->findBookableShowtime($_POST['showtimeId'] ?? 0);
        if (!$showtime) {
            return $this->error('Suất chiếu không còn bán vé', 422);
        }

        try {
            $user = (new User())->getUserById(\AuthHelper::id());
            $quote = (new Booking())->quote(
                $user,
                $showtime,
                (array)($_POST['seatIds'] ?? []),
                (array)($_POST['products'] ?? []),
                $_POST['voucherCode'] ?? '',
                $_POST['points'] ?? 0
            );
        } catch (ValidationException $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage(), 'field' => $e->getField()], 422);
        }

        return $this->success($quote, 'OK');
    }

    // ---------------- API công khai ----------------

    public function getCinemasByCity()
    {
        $cityId = filter_var($_GET['cityId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$cityId) {
            return $this->error('Vui lòng chọn thành phố', 422);
        }

        return $this->success((new Cinema())->getByCity($cityId));
    }

    public function getShowtimesByMovie()
    {
        $movieId = filter_var($_GET['movieId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $cityId = filter_var($_GET['cityId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $cinemaId = filter_var($_GET['cinemaId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $date = is_string($_GET['date'] ?? null) ? $_GET['date'] : date('Y-m-d');

        $parsed = \DateTime::createFromFormat('!Y-m-d', $date);
        $today = strtotime(date('Y-m-d'));
        $lastDay = strtotime('+' . (Showtime::BOOKING_DAYS - 1) . ' day', $today);
        if (!$movieId || !$parsed || $parsed->format('Y-m-d') !== $date
            || $parsed->getTimestamp() < $today || $parsed->getTimestamp() > $lastDay) {
            return $this->error('Tham số không hợp lệ', 422);
        }

        return $this->success((new Showtime())->searchByMovie($movieId, $date, $cityId, $cinemaId));
    }

    public function getSeats($showtimeId)
    {
        $showtime = $this->findBookableShowtime($showtimeId);
        if (!$showtime) {
            return $this->error('Suất chiếu không còn bán vé', 404);
        }

        (new Booking())->releaseExpired();
        $seats = (new Showtime())->getSeatMap($showtime, \AuthHelper::check() ? \AuthHelper::id() : null);

        return $this->success(array_map(function ($seat) {
            return [
                'id' => $seat['id'], 'label' => $seat['label'], 'type' => $seat['seatType'],
                'status' => $seat['status'], 'price' => $seat['price'], 'isTaken' => $seat['isTaken'],
            ];
        }, $seats));
    }

    // ---------------- Helpers ----------------

    private function findBookableShowtime($showtimeId)
    {
        $id = filter_var($showtimeId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) {
            return null;
        }

        $showtime = (new Showtime())->getDetail($id);

        return $showtime && Showtime::isBookable($showtime) ? $showtime : null;
    }

    private function seatTypesOf(array $seats)
    {
        $types = [];
        foreach ($seats as $seat) {
            if ($seat['status'] === 'hidden' || isset($types[$seat['seatType']])) {
                continue;
            }
            $types[$seat['seatType']] = [
                'code'  => $seat['seatType'],
                'name'  => $seat['seatTypeName'],
                'color' => $seat['color'] ?: '#9ca3af',
                'price' => $seat['price'],
            ];
        }

        return array_values($types);
    }
}
