<?php
namespace App\Controllers\Admin;

use App\Models\User;

/**
 * Quản lý khách hàng: tìm kiếm, lọc hạng / trạng thái, khung chi tiết (vé, điểm, thông tin), khóa / mở khóa
 */
class UserController extends AdminController
{
    const SORTS = [
        'newest'   => 'u.id DESC',
        'spending' => 'u.totalSpending DESC, u.id DESC',
        'points'   => 'u.points DESC, u.id DESC',
        'name'     => 'u.name ASC',
    ];

    public function index()
    {
        $keyword = mb_substr($this->query('q'), 0, 100);
        $levelId = (int)$this->query('level');
        $status = in_array($this->query('status'), ['active', 'blocked'], true) ? $this->query('status') : '';
        $sort = array_key_exists($this->query('sort'), self::SORTS) ? $this->query('sort') : 'newest';
        $roleId = (new User())->getRoleIdByName(User::ROLE_CUSTOMER);

        $where = 'u.roleId = ?';
        $params = [$roleId];
        if ($keyword !== '') {
            $where .= " AND (u.name LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\' OR u.phoneNumber LIKE ? ESCAPE '\\\\')";
            $params = array_merge($params, array_fill(0, 3, $this->like($keyword)));
        }
        if ($levelId > 0) {
            $where .= ' AND u.memberLevelId = ?';
            $params[] = $levelId;
        }
        if ($status !== '') {
            $where .= ' AND u.status = ?';
            $params[] = $status;
        }

        $pager = $this->paginator((int)$this->db->query("SELECT COUNT(*) AS c FROM users u WHERE {$where}", $params, true)['c']);
        $users = $this->db->query(
            "SELECT u.id, u.name, u.email, u.phoneNumber, u.points, u.totalSpending, u.status, u.createdAt, ml.name AS levelName
             FROM users u LEFT JOIN member_level ml ON ml.id = u.memberLevelId
             WHERE {$where}
             ORDER BY " . self::SORTS[$sort] . "
             LIMIT ? OFFSET ?",
            array_merge($params, [$pager['perPage'], $pager['offset']])
        );

        $viewId = (int)$this->query('view');
        $selected = $viewId ? $this->detail($viewId, $roleId) : null;

        return $this->page('users.index', [
            'users'    => $users,
            'pager'    => $pager,
            'levels'   => $this->db->query('SELECT id, name FROM member_level ORDER BY minSpending'),
            'filters'  => ['q' => $keyword, 'level' => $levelId, 'status' => $status, 'sort' => $sort],
            'selected' => $selected,
            'tab'      => in_array($this->query('tab'), ['bookings', 'points', 'info'], true) ? $this->query('tab') : 'bookings',
        ]);
    }

    public function block($id)
    {
        $roleId = (new User())->getRoleIdByName(User::ROLE_CUSTOMER);
        $user = ctype_digit((string)$id) ? $this->db->query('SELECT id, name, status FROM users WHERE id = ? AND roleId = ?', [(int)$id, $roleId], true) : null;
        if (!$user) {
            $this->notFound();
        }

        $status = $user['status'] === 'active' ? 'blocked' : 'active';
        // Khóa: AuthHelper::user() tự đăng xuất ở request kế tiếp; xóa token ghi nhớ để không tự đăng nhập lại
        $this->db->update('users', ['status' => $status, 'rememberToken' => null], 'id = ?', [(int)$user['id']]);

        $back = $this->input('back');
        $path = is_string($back) && preg_match('#^/admin/users(\?[^\s]*)?$#', $back) ? $back : '/admin/users';

        return $this->done($path, ($status === 'blocked' ? 'Đã khóa tài khoản ' : 'Đã mở khóa tài khoản ') . $user['name']);
    }

    private function detail($id, $roleId)
    {
        $user = $this->db->query(
            "SELECT u.*, ml.name AS levelName FROM users u LEFT JOIN member_level ml ON ml.id = u.memberLevelId
             WHERE u.id = ? AND u.roleId = ?",
            [(int)$id, $roleId],
            true
        );
        if (!$user) {
            return null;
        }
        unset($user['password'], $user['rememberToken'], $user['resetToken'], $user['resetExpiredAt']);

        $user['bookings'] = $this->db->query(
            "SELECT b.id, b.code, b.status, b.totalAmount, b.expiredAt, b.createdAt, m.name AS movieName
             FROM booking b JOIN showtime s ON s.id = b.showtimeId JOIN movie m ON m.id = s.movieId
             WHERE b.userId = ? ORDER BY b.id DESC LIMIT 10",
            [(int)$user['id']]
        );
        $user['pointHistory'] = $this->db->query(
            'SELECT points, note, createdAt FROM point_history WHERE userId = ? ORDER BY id DESC LIMIT 10',
            [(int)$user['id']]
        );
        $user['stats'] = $this->db->query(
            "SELECT COUNT(*) AS total, SUM(status = 'paid') AS paid FROM booking WHERE userId = ?",
            [(int)$user['id']],
            true
        );

        return $user;
    }
}
