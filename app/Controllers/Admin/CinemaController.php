<?php
namespace App\Controllers\Admin;

/**
 * Quản lý rạp (bước 1/3): danh sách rạp + form thêm / sửa ở khung bên
 */
class CinemaController extends AdminController
{
    public function index()
    {
        $keyword = mb_substr($this->query('q'), 0, 100);
        $cityId = (int)$this->query('city');

        $where = '1 = 1';
        $params = [];
        if ($keyword !== '') {
            $where .= " AND (c.name LIKE ? ESCAPE '\\\\' OR c.address LIKE ? ESCAPE '\\\\')";
            $params[] = $this->like($keyword);
            $params[] = $this->like($keyword);
        }
        if ($cityId > 0) {
            $where .= ' AND c.cityId = ?';
            $params[] = $cityId;
        }

        $cinemas = $this->db->query(
            "SELECT c.*, ci.name AS cityName,
                    (SELECT COUNT(*) FROM room r WHERE r.cinemaId = c.id) AS roomCount,
                    (SELECT COUNT(*) FROM seat se JOIN room r ON r.id = se.roomId WHERE r.cinemaId = c.id AND se.status = 'active') AS seatCount,
                    (SELECT COUNT(*) FROM showtime s JOIN room r ON r.id = s.roomId WHERE r.cinemaId = c.id AND s.startTime > NOW() AND s.status = 'open') AS upcoming
             FROM cinema c JOIN city ci ON ci.id = c.cityId
             WHERE {$where}
             ORDER BY ci.sortOrder, c.name",
            $params
        );

        $editId = (int)$this->query('edit');
        $editing = $editId ? $this->db->query('SELECT * FROM cinema WHERE id = ?', [$editId], true) : null;

        return $this->page('cinemas.index', [
            'cinemas' => $cinemas,
            'cities'  => $this->db->query('SELECT id, name FROM city ORDER BY sortOrder, name'),
            'editing' => $editing,
            'filters' => ['q' => $keyword, 'city' => $cityId],
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

    public function destroy($id)
    {
        $cinema = $this->find($id);
        $showtimes = (int)$this->db->query(
            'SELECT COUNT(*) AS c FROM showtime s JOIN room r ON r.id = s.roomId WHERE r.cinemaId = ?',
            [(int)$cinema['id']],
            true
        )['c'];
        if ($showtimes > 0) {
            return $this->done('/admin/cinemas', 'Rạp "' . $cinema['name'] . '" đã có suất chiếu, không thể xóa. Hãy chuyển sang "Tạm ngưng".', 'danger');
        }

        $this->db->execute('DELETE FROM cinema WHERE id = ?', [(int)$cinema['id']]);   // phòng + ghế xóa theo CASCADE

        return $this->done('/admin/cinemas', 'Đã xóa rạp ' . $cinema['name']);
    }

    private function find($id)
    {
        $cinema = ctype_digit((string)$id) ? $this->db->query('SELECT * FROM cinema WHERE id = ?', [(int)$id], true) : null;
        if (!$cinema) {
            $this->notFound();
        }

        return $cinema;
    }

    private function save($cinema)
    {
        $data = [
            'cityId'  => (int)$this->input('cityId'),
            'name'    => mb_substr($this->input('name'), 0, 150),
            'slug'    => strtolower($this->input('slug')),
            'address' => mb_substr($this->input('address'), 0, 255),
            'phone'   => preg_replace('/[\s.\-]/', '', $this->input('phone')),
            'mapUrl'  => $this->input('mapUrl'),
            'status'  => $this->input('status') === 'inactive' ? 'inactive' : 'active',
        ];

        $errors = [];
        if (!$this->db->query('SELECT id FROM city WHERE id = ?', [$data['cityId']], true)) {
            $errors['cityId'] = 'Chọn thành phố';
        }
        if ($data['name'] === '') {
            $errors['name'] = 'Vui lòng nhập tên rạp';
        }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['slug']) || strlen($data['slug']) > 170) {
            $errors['slug'] = 'Slug chỉ gồm chữ thường không dấu, số và dấu gạch ngang';
        } elseif ($this->db->query('SELECT id FROM cinema WHERE slug = ? AND id <> ?', [$data['slug'], (int)($cinema['id'] ?? 0)], true)) {
            $errors['slug'] = 'Slug đã được dùng cho rạp khác';
        }
        if ($data['address'] === '') {
            $errors['address'] = 'Vui lòng nhập địa chỉ';
        }
        if ($data['phone'] !== '' && !preg_match('/^\+?\d{8,15}$/', $data['phone'])) {
            $errors['phone'] = 'Số điện thoại không hợp lệ';
        }
        if ($data['mapUrl'] !== '' && (!preg_match('#^https://#i', $data['mapUrl']) || !filter_var($data['mapUrl'], FILTER_VALIDATE_URL) || strlen($data['mapUrl']) > 500)) {
            $errors['mapUrl'] = 'Link bản đồ phải là URL https://';
        }
        if ($errors) {
            return $this->invalid($errors);
        }

        $data['phone'] = $data['phone'] ?: null;
        $data['mapUrl'] = $data['mapUrl'] ?: null;
        if ($cinema) {
            $this->db->update('cinema', $data, 'id = ?', [(int)$cinema['id']]);
            return $this->done('/admin/cinemas', 'Đã cập nhật rạp ' . $data['name']);
        }

        $id = $this->db->insert('cinema', $data);
        return $this->done('/admin/cinemas/' . $id . '/rooms', 'Đã thêm rạp ' . $data['name'] . '. Tiếp theo: thêm phòng chiếu.');
    }
}
