<?php
namespace App\Controllers;

use App\Models\Movie;
use App\Models\Post;
use App\Models\Voucher;

class HomeController extends BaseController
{
    const MOVIES_PER_SECTION = 4;
    const HERO_SLIDES = 3;
    const POSTS_PER_SECTION = 4;

    public function index()
    {
        $movieModel = new Movie();
        $nowShowing = $movieModel->withGenres($movieModel->getMoviesByStatus('now_showing', self::MOVIES_PER_SECTION));

        return $this->render('customer.home', [
            // Hero: trượt giữa các phim đang chiếu nổi bật (phim hot lên trước)
            'featured'   => array_map(function ($movie) {
                $movie['trailerEmbed'] = MovieController::youtubeEmbedUrl($movie['trailerUrl']);
                return $movie;
            }, array_slice($nowShowing, 0, self::HERO_SLIDES)),
            'nowShowing' => $nowShowing,
            'comingSoon' => $movieModel->withGenres($movieModel->getMoviesByStatus('coming_soon', self::MOVIES_PER_SECTION)),
            'promo'      => (new Voucher())->getFeatured(),
            'posts'      => (new Post())->getLatestPublished(self::POSTS_PER_SECTION),
        ]);
    }
}
