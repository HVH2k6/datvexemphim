<?php
namespace App\Models;

class Movie extends BaseModel
{
    const STATUSES = ['now_showing', 'coming_soon'];

    protected $table = 'movie';

    public function getMovieBySlug($slug)
    {
        return $this->findOneWhere('slug = ?', [$slug]);
    }

    /**
     * Phim theo trạng thái cho trang chủ (phim hot lên trước)
     */
    public function getMoviesByStatus($status, $limit = 8)
    {
        return $this->db->query(
            "SELECT * FROM movie WHERE status = ?
             ORDER BY isHot DESC, releaseDate " . ($status === 'coming_soon' ? 'ASC' : 'DESC') . "
             LIMIT ?",
            [$status, (int)$limit]
        );
    }

    /**
     * Danh sách phim có phân trang + lọc thể loại
     * @return array ['items' => [...], 'total' => int]
     */
    public function paginate($status, $genreId, $page, $perPage, $keyword = '')
    {
        $where = 'm.status = ?';
        $params = [$status];
        if ($keyword !== '') {
            // Escape ký tự đặc biệt của LIKE để tìm đúng chuỗi người dùng nhập
            $where .= " AND m.name LIKE ? ESCAPE '\\\\'";
            $params[] = '%' . addcslashes($keyword, '%_\\') . '%';
        }
        if ($genreId) {
            $where .= ' AND EXISTS (SELECT 1 FROM movie_genre mg WHERE mg.movieId = m.id AND mg.genreId = ?)';
            $params[] = (int)$genreId;
        }

        $total = (int)$this->db->query("SELECT COUNT(*) AS total FROM movie m WHERE {$where}", $params, true)['total'];
        $order = $status === 'coming_soon' ? 'ASC' : 'DESC';
        $items = $this->db->query(
            "SELECT m.* FROM movie m WHERE {$where}
             ORDER BY m.isHot DESC, m.releaseDate {$order}, m.id DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [(int)$perPage, (int)(($page - 1) * $perPage)])
        );

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Định dạng của các suất sắp chiếu trong 7 ngày, vd: ['2D', '3D', 'IMAX']
     */
    public function getUpcomingFormats($movieId)
    {
        $rows = $this->db->query(
            "SELECT DISTINCT s.format, r.roomType FROM showtime s JOIN room r ON r.id = s.roomId
             WHERE s.movieId = ? AND s.status = 'open' AND s.startTime > NOW()
               AND s.startTime < NOW() + INTERVAL ? DAY",
            [(int)$movieId, Showtime::BOOKING_DAYS]
        );

        $labels = array_values(array_intersect(['2D', '3D'], array_column($rows, 'format')));
        $roomLabels = ['imax' => 'IMAX', '4dx' => '4DX', 'gold_class' => 'Gold Class'];
        foreach ($roomLabels as $type => $label) {
            if (in_array($type, array_column($rows, 'roomType'), true)) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    public function getGenres($movieId)
    {
        return $this->db->query(
            'SELECT g.* FROM genre g JOIN movie_genre mg ON mg.genreId = g.id
             WHERE mg.movieId = ? ORDER BY g.name',
            [$movieId]
        );
    }

    /**
     * Gắn mảng 'genres' (tên thể loại) cho danh sách phim, 1 query cho cả trang
     */
    public function withGenres(array $movies)
    {
        if (!$movies) {
            return $movies;
        }

        $ids = array_map('intval', array_column($movies, 'id'));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->query(
            "SELECT mg.movieId, g.name FROM movie_genre mg JOIN genre g ON g.id = mg.genreId
             WHERE mg.movieId IN ({$placeholders}) ORDER BY g.name",
            $ids
        );

        $map = [];
        foreach ($rows as $row) {
            $map[$row['movieId']][] = $row['name'];
        }
        foreach ($movies as &$movie) {
            $movie['genres'] = $map[$movie['id']] ?? [];
        }

        return $movies;
    }
}
