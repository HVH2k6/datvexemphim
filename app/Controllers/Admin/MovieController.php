<?php
namespace App\Controllers\Admin;

use App\Core\ValidationException;
use App\Support\Admin;

/**
 * Quản lý phim: danh sách + lọc, thêm / sửa trong modal, bật tắt "Hot", xóa khi chưa có suất chiếu
 */
class MovieController extends AdminController
{
    const SORTS = [
        'newest'  => 'm.releaseDate DESC, m.id DESC',
        'oldest'  => 'm.releaseDate ASC, m.id ASC',
        'name'    => 'm.name ASC',
        'rating'  => 'm.avgRating DESC, m.id DESC',
    ];

    public function index()
    {
        $keyword = mb_substr($this->query('q'), 0, 100);
        $status = $this->query('status');
        $sort = array_key_exists($this->query('sort'), self::SORTS) ? $this->query('sort') : 'newest';

        $where = '1 = 1';
        $params = [];
        if ($keyword !== '') {
            $where .= " AND (m.name LIKE ? ESCAPE '\\\\' OR m.director LIKE ? ESCAPE '\\\\')";
            $params[] = $this->like($keyword);
            $params[] = $this->like($keyword);
        }
        if (array_key_exists($status, Admin::MOVIE_STATUSES)) {
            $where .= ' AND m.status = ?';
            $params[] = $status;
        }

        $pager = $this->paginator((int)$this->db->query("SELECT COUNT(*) AS c FROM movie m WHERE {$where}", $params, true)['c']);
        $movies = $this->db->query(
            "SELECT m.*,
                    (SELECT GROUP_CONCAT(g.name ORDER BY g.name SEPARATOR ', ') FROM movie_genre mg JOIN genre g ON g.id = mg.genreId WHERE mg.movieId = m.id) AS genreNames,
                    (SELECT GROUP_CONCAT(mg.genreId) FROM movie_genre mg WHERE mg.movieId = m.id) AS genreIds,
                    (SELECT COUNT(*) FROM showtime s WHERE s.movieId = m.id) AS showtimeCount
             FROM movie m WHERE {$where}
             ORDER BY " . self::SORTS[$sort] . "
             LIMIT ? OFFSET ?",
            array_merge($params, [$pager['perPage'], $pager['offset']])
        );

        return $this->page('movies.index', [
            'movies'  => $movies,
            'genres'  => $this->db->query('SELECT id, name FROM genre ORDER BY name'),
            'pager'   => $pager,
            'filters' => ['q' => $keyword, 'status' => $status, 'sort' => $sort],
        ]);
    }

    public function store()
    {
        return $this->save(null);
    }

    public function update($id)
    {
        $movie = $this->find($id);
        return $this->save($movie);
    }

    public function toggleHot($id)
    {
        $movie = $this->find($id);
        $this->db->execute('UPDATE movie SET isHot = ? WHERE id = ?', [$movie['isHot'] ? 0 : 1, (int)$movie['id']]);

        return $this->done('/admin/movies', ($movie['isHot'] ? 'Đã bỏ "Hot" cho phim ' : 'Đã đánh dấu "Hot" cho phim ') . $movie['name']);
    }

    public function destroy($id)
    {
        $movie = $this->find($id);
        if ((int)$this->db->query('SELECT COUNT(*) AS c FROM showtime WHERE movieId = ?', [(int)$movie['id']], true)['c'] > 0) {
            return $this->done('/admin/movies', 'Phim "' . $movie['name'] . '" đã có suất chiếu, không thể xóa. Hãy chuyển trạng thái sang "Đã kết thúc".', 'danger');
        }

        $this->db->execute('DELETE FROM movie WHERE id = ?', [(int)$movie['id']]);   // movie_genre, review xóa theo CASCADE
        $this->removeUpload($movie['image']);
        $this->removeUpload($movie['banner']);

        return $this->done('/admin/movies', 'Đã xóa phim ' . $movie['name']);
    }

    // ---------------- Helpers ----------------

    private function find($id)
    {
        $movie = ctype_digit((string)$id) ? $this->db->query('SELECT * FROM movie WHERE id = ?', [(int)$id], true) : null;
        if (!$movie) {
            $this->notFound();
        }

        return $movie;
    }

    private function save($movie)
    {
        $data = [
            'name'        => mb_substr($this->input('name'), 0, 255),
            'slug'        => strtolower($this->input('slug')),
            'description' => $this->input('description'),
            'trailerUrl'  => $this->input('trailerUrl'),
            'director'    => mb_substr($this->input('director'), 0, 255),
            'actors'      => mb_substr($this->input('actors'), 0, 500),
            'duration'    => $this->input('duration'),
            'language'    => mb_substr($this->input('language'), 0, 100),
            'country'     => mb_substr($this->input('country'), 0, 100),
            'ageRating'   => $this->input('ageRating'),
            'releaseDate' => $this->input('releaseDate'),
            'endDate'     => isset($_POST['noEndDate']) ? '' : $this->input('endDate'),
            'status'      => $this->input('status'),
        ];
        $genreIds = $this->ids($_POST['genres'] ?? []);

        $errors = [];
        if ($data['name'] === '') {
            $errors['name'] = 'Vui lòng nhập tên phim';
        }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['slug']) || strlen($data['slug']) > 200) {
            $errors['slug'] = 'Slug chỉ gồm chữ thường không dấu, số và dấu gạch ngang';
        } elseif ($this->db->query('SELECT id FROM movie WHERE slug = ? AND id <> ?', [$data['slug'], (int)($movie['id'] ?? 0)], true)) {
            $errors['slug'] = 'Slug đã được dùng cho phim khác';
        }
        if (!ctype_digit($data['duration']) || (int)$data['duration'] < 1 || (int)$data['duration'] > 600) {
            $errors['duration'] = 'Thời lượng từ 1 đến 600 phút';
        }
        if (!in_array($data['ageRating'], Admin::AGE_RATINGS, true)) {
            $errors['ageRating'] = 'Chọn độ tuổi';
        }
        if (!array_key_exists($data['status'], Admin::MOVIE_STATUSES)) {
            $errors['status'] = 'Trạng thái không hợp lệ';
        }
        if (!$this->validDate($data['releaseDate'])) {
            $errors['releaseDate'] = 'Ngày khởi chiếu không hợp lệ';
        }
        if ($data['endDate'] !== '' && (!$this->validDate($data['endDate']) || ($this->validDate($data['releaseDate']) && $data['endDate'] < $data['releaseDate']))) {
            $errors['endDate'] = 'Ngày kết thúc phải sau ngày khởi chiếu';
        }
        if ($data['trailerUrl'] !== '' && (!filter_var($data['trailerUrl'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $data['trailerUrl']) || strlen($data['trailerUrl']) > 255)) {
            $errors['trailerUrl'] = 'URL trailer phải bắt đầu bằng http:// hoặc https://';
        }
        $validGenres = $genreIds ? array_map('intval', array_column($this->db->query(
            'SELECT id FROM genre WHERE id IN (' . implode(',', array_fill(0, count($genreIds), '?')) . ')',
            $genreIds
        ), 'id')) : [];
        if (!$validGenres) {
            $errors['genres'] = 'Chọn ít nhất 1 thể loại';
        }
        if (!$movie && empty($_FILES['image']['name'])) {
            $errors['image'] = 'Vui lòng chọn poster';
        }
        if ($errors) {
            return $this->invalid($errors);
        }

        try {
            $image = $this->upload('image', 'movies');
            $banner = $this->upload('banner', 'movies');
        } catch (ValidationException $e) {
            return $this->invalid([$e->getField() => $e->getMessage()]);
        }

        $data['duration'] = (int)$data['duration'];
        $data['endDate'] = $data['endDate'] === '' ? null : $data['endDate'];
        foreach (['description', 'trailerUrl', 'director', 'actors', 'language', 'country'] as $key) {
            $data[$key] = $data[$key] === '' ? null : $data[$key];
        }
        if ($image) {
            $data['image'] = $image;
        }
        if ($banner) {
            $data['banner'] = $banner;
        }

        $this->db->beginTransaction();
        try {
            if ($movie) {
                $this->db->update('movie', $data, 'id = ?', [(int)$movie['id']]);
                $movieId = (int)$movie['id'];
            } else {
                $movieId = (int)$this->db->insert('movie', $data);
            }
            $this->db->execute('DELETE FROM movie_genre WHERE movieId = ?', [$movieId]);
            foreach ($validGenres as $genreId) {
                $this->db->insert('movie_genre', ['movieId' => $movieId, 'genreId' => $genreId]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        if ($movie && $image) {
            $this->removeUpload($movie['image']);
        }
        if ($movie && $banner) {
            $this->removeUpload($movie['banner']);
        }

        return $this->done('/admin/movies', ($movie ? 'Đã cập nhật phim ' : 'Đã thêm phim ') . $data['name']);
    }
}
