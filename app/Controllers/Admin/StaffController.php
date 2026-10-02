<?php
namespace App\Controllers\Admin;

use App\Models\User;

/**
 * Quản lý nhân viên (role staff): thêm / sửa ở khung bên, khóa / mở, đặt lại mật khẩu.
 * Không xóa tài khoản nhân viên vì đơn quầy (pos_order.staffId) và lịch sử soát vé tham chiếu tới.
 */
class StaffController extends AdminController
{
    const MIN_PASSWORD = 6;

    public function index()
    {
        $keyword = mb_substr($this->query('q'), 0, 100);
        $status = in_array($this->query('status'), ['active', 'blocked'], true) ? $this->query('status') : '';
        $roleId = (new User())->getRoleIdByName('staff');

        $where = 'u.roleId = ?';
        $params = [$roleId];
        if ($keyword !== '') {
            $where .= " AND (u.name LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\' OR u.phoneNumber LIKE ? ESCAPE '\\\\')";
            $params = array_merge($params, array_fill(0, 3, $this->like($keyword)));
        }
        if ($status !== '') {
            $where .= ' AND u.status = ?';
            $params[] = $status;
        }

        $pager = $this->paginator((int)$this->db->query("SELECT COUNT(*) AS c FROM users u WHERE {$where}", $params, true)['c']);
        $staff = $this->db->query(
            "SELECT u.id, u.name, u.email, u.phoneNumber, u.status, u.createdAt,
                    (SELECT COUNT(*) FROM booking b WHERE b.checkedInBy = u.id) AS checkins,
                    (SELECT COUNT(*) FROM pos_order p WHERE p.staffId = u.id) AS posOrders,
                    (SELECT COALESCE(SUM(p.totalAmount), 0) FROM pos_order p WHERE p.staffId = u.id) AS posRevenue
             FROM users u WHERE {$where}
             ORDER BY u.status = 'active' DESC, u.name
             LIMIT ? OFFSET ?",
            array_merge($params, [$pager['perPage'], $pager['offset']])
        );

        $editId = (int)$this->query('edit');
        $editing = $editId ? $this->db->query('SELECT id, name, email, phoneNumber, status FROM users WHERE id = ? AND roleId = ?', [$editId, $roleId], true) : null;

        return $this->page('staff.index', [
            'staff'   => $staff,
            'pager'   => $pager,
            'editing' => $editing,
            'filters' => ['q' => $keyword, 'status' => $status],
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

    public function resetPassword($id)
    {
        $staff = $this->find($id);
        $password = $this->input('password');
        if (mb_strlen($password) < self::MIN_PASSWORD || mb_strlen($password) > 72) {
            return $this->invalid(['password' => 'Mật khẩu mới từ ' . self::MIN_PASSWORD . ' đến 72 ký tự']);
        }

        // Đổi mật khẩu thì hủy luôn "ghi nhớ đăng nhập" trên các máy cũ
        $this->db->update('users', ['password' => password_hash($password, PASSWORD_BCRYPT), 'rememberToken' => null], 'id = ?', [(int)$staff['id']]);

        return $this->done('/admin/staff?edit=' . $staff['id'], 'Đã đặt lại mật khẩu cho ' . $staff['name']);
    }

    public function toggle($id)
    {
        $staff = $this->find($id);
        $status = $staff['status'] === 'active' ? 'blocked' : 'active';
        $this->db->update('users', ['status' => $status, 'rememberToken' => null], 'id = ?', [(int)$staff['id']]);

        return $this->done('/admin/staff', ($status === 'blocked' ? 'Đã khóa tài khoản ' : 'Đã mở khóa tài khoản ') . $staff['name']);
    }

    // ---------------- Helpers ----------------

    private function find($id)
    {
        $roleId = (new User())->getRoleIdByName('staff');
        $staff = ctype_digit((string)$id)
            ? $this->db->query('SELECT id, name, email, phoneNumber, status FROM users WHERE id = ? AND roleId = ?', [(int)$id, $roleId], true)
            : null;
        if (!$staff) {
            $this->notFound();
        }

        return $staff;
    }

    private function save($staff)
    {
        $data = [
            'name'        => mb_substr($this->input('name'), 0, 100),
            'email'       => strtolower($this->input('email')),
            'phoneNumber' => preg_replace('/[\s.\-]/', '', $this->input('phoneNumber')),
            'status'      => $this->input('status') === 'blocked' ? 'blocked' : 'active',
        ];
        $password = $this->input('password');

        $errors = [];
        if ($data['name'] === '') {
            $errors['name'] = 'Vui lòng nhập họ tên';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 150) {
            $errors['email'] = 'Email không hợp lệ';
        } elseif ($this->db->query('SELECT id FROM users WHERE email = ? AND id <> ?', [$data['email'], (int)($staff['id'] ?? 0)], true)) {
            $errors['email'] = 'Email đã được dùng';
        }
        if ($data['phoneNumber'] !== '' && !preg_match('/^0\d{9,10}$/', $data['phoneNumber'])) {
            $errors['phoneNumber'] = 'Số điện thoại gồm 10-11 chữ số, bắt đầu bằng 0';
        } elseif ($data['phoneNumber'] !== '' && $this->db->query('SELECT id FROM users WHERE phoneNumber = ? AND id <> ?', [$data['phoneNumber'], (int)($staff['id'] ?? 0)], true)) {
            $errors['phoneNumber'] = 'Số điện thoại đã được dùng';
        }
        if (!$staff && (mb_strlen($password) < self::MIN_PASSWORD || mb_strlen($password) > 72)) {
            $errors['password'] = 'Mật khẩu từ ' . self::MIN_PASSWORD . ' đến 72 ký tự';
        }
        if ($errors) {
            return $this->invalid($errors);
        }

        $data['phoneNumber'] = $data['phoneNumber'] ?: null;
        if ($staff) {
            if ($data['status'] !== $staff['status']) {
                $data['rememberToken'] = null;
            }
            $this->db->update('users', $data, 'id = ?', [(int)$staff['id']]);
            return $this->done('/admin/staff', 'Đã cập nhật nhân viên ' . $data['name']);
        }

        try {
            $this->db->insert('users', $data + [
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'roleId'   => (new User())->getRoleIdByName('staff'),
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return $this->invalid(['email' => 'Email hoặc số điện thoại đã được dùng']);
            }
            throw $e;
        }

        return $this->done('/admin/staff', 'Đã thêm nhân viên ' . $data['name'] . '. Nhân viên đăng nhập bằng email ' . $data['email']);
    }
}
