<?php
namespace App\Controllers;

use App\Models\Cinema;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\Review;
use App\Models\Showtime;

class MovieController extends BaseController
{
    const PER_PAGE = 12;

    public function index()
    {
        $status = in_array($_GET['status'] ?? '', Movie::STATUSES, true) ? $_GET['status'] : 'now_showing';
        $genreId = filter_var($_GET['genre'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $keyword = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100, 'UTF-8') : '';

        $movieModel = new Movie();
        $result = $movieModel->paginate($status, $genreId, $page, self::PER_PAGE, $keyword);
        $totalPages = max(1, (int)ceil($result['total'] / self::PER_PAGE));

        if ($page > $totalPages) {
            return $this->notFound();
        }

        return $this->render('customer.movies', [
            'movies'      => $movieModel->withGenres($result['items']),
            'genres'      => (new Genre())->getAll('name'),
            'status'      => $status,
            'genreId'     => $genreId,
            'keyword'     => $keyword,
            'currentPage' => $page,
            'totalPages'  => $totalPages,
            'total'       => $result['total'],
        ]);
    }

    public function show($slug)
    {
        $movieModel = new Movie();
        $movie = $movieModel->getMovieBySlug($slug);
        if (!$movie) {
            return $this->notFound();
        }

        // 7 ngày tới cho thanh chọn ngày
        $dates = [];
        for ($i = 0; $i < Showtime::BOOKING_DAYS; $i++) {
            $dates[] = date('Y-m-d', strtotime("+{$i} day"));
        }

        $reviewModel = new Review();
        $userId = \AuthHelper::role() === 'customer' ? \AuthHelper::id() : null;

        return $this->render('customer.movie-detail', [
            'movie'        => $movie,
            'genres'       => $movieModel->getGenres($movie['id']),
            'formats'      => $movieModel->getUpcomingFormats($movie['id']),
            'reviews'      => $reviewModel->getVisibleForMovie($movie['id']),
            'reviewStats'  => $reviewModel->getStats($movie['id']),
            'myReview'     => $userId ? $reviewModel->findUserReview($userId, $movie['id']) : null,
            'watched'      => $userId ? $reviewModel->findWatchedShowtime($userId, $movie['id']) : null,
            'cities'       => (new Cinema())->getCities(),
            'dates'        => $dates,
            'trailerEmbed' => self::youtubeEmbedUrl($movie['trailerUrl']),
        ]);
    }

    /**
     * Khách đã xem phim (đơn đã thanh toán, suất đã kết thúc) gửi đánh giá 1-5 sao, lưu thang 10
     */
    public function storeReview($slug)
    {
        $movie = (new Movie())->getMovieBySlug($slug);
        if (!$movie) {
            return $this->notFound();
        }

        $back = '/movies/' . rawurlencode($movie['slug']) . '#danh-gia';
        $reviewModel = new Review();
        $userId = (int)\AuthHelper::id();

        if (!$reviewModel->findWatchedShowtime($userId, $movie['id'])) {
            return $this->flash($back, 'Bạn cần xem phim này trước khi đánh giá', 'warning');
        }
        if ($reviewModel->findUserReview($userId, $movie['id'])) {
            return $this->flash($back, 'Bạn đã đánh giá phim này rồi', 'info');
        }

        $stars = filter_var($_POST['stars'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
        $content = is_string($_POST['content'] ?? null) ? trim($_POST['content']) : '';
        if (!$stars) {
            return $this->flash($back, 'Vui lòng chọn số sao', 'danger');
        }
        if (mb_strlen($content, 'UTF-8') > Review::MAX_CONTENT) {
            return $this->flash($back, 'Nội dung tối đa ' . Review::MAX_CONTENT . ' ký tự', 'danger');
        }

        try {
            $reviewModel->create([
                'userId'  => $userId,
                'movieId' => (int)$movie['id'],
                'rating'  => $stars * 2,
                'content' => $content === '' ? null : $content,
            ]);
        } catch (\PDOException $e) {
            // UNIQUE(userId, movieId): gửi 2 lần cùng lúc
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }

        return $this->flash($back, 'Cảm ơn bạn đã đánh giá', 'success');
    }

    /**
     * Chỉ nhận link YouTube hợp lệ để nhúng iframe
     */
    public static function youtubeEmbedUrl($url)
    {
        if (!is_string($url) || !preg_match('#^https?://(?:www\.)?(?:youtube\.com/watch\?(?:.*&)?v=|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m)) {
            return null;
        }

        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }

    private function flash($path, $message, $type)
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;

        return $this->redirect($path);
    }
}
