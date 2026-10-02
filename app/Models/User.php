<?php
namespace App\Models;

class User extends BaseModel
{
    const ROLE_CUSTOMER = 'customer';
    const DEFAULT_LEVEL = 'Member';

    protected $table = 'users';

    /**
     * Lấy user kèm role + hạng thành viên, KHÔNG trả password/token
     * @return array|null
     */
    public function getUserById($id)
    {
        return $this->db->query(
            "SELECT u.id, u.name, u.email, u.phoneNumber, u.birthday, u.gender, u.avatar,
                    u.roleId, u.memberLevelId, u.points, u.totalSpending, u.status,
                    u.createdAt, u.updatedAt,
                    r.name AS roleName, ml.name AS levelName,
                    COALESCE(ml.discountPercent, 0) AS discountPercent,
                    COALESCE(ml.pointRate, 0) AS pointRate
             FROM users u
             JOIN role r ON r.id = u.roleId
             LEFT JOIN member_level ml ON ml.id = u.memberLevelId
             WHERE u.id = ?",
            [$id],
            true
        );
    }

    /**
     * Dùng cho đăng nhập: có password hash + tên role
     * @return array|null
     */
    public function getAuthByEmail($email)
    {
        return $this->db->query(
            "SELECT u.id, u.email, u.password, u.status, r.name AS roleName
             FROM users u
             JOIN role r ON r.id = u.roleId
             WHERE u.email = ?",
            [$email],
            true
        );
    }

    public function emailExists($email)
    {
        return $this->count('email = ?', [$email]) > 0;
    }

    public function getRoleIdByName($roleName)
    {
        $role = $this->db->query('SELECT id FROM role WHERE name = ?', [$roleName], true);
        return $role ? (int)$role['id'] : null;
    }

    public function getLevelIdByName($levelName)
    {
        $level = $this->db->query('SELECT id FROM member_level WHERE name = ?', [$levelName], true);
        return $level ? (int)$level['id'] : null;
    }

    /**
     * Tạo tài khoản khách hàng. Role/hạng lấy theo tên, không hardcode id.
     * status, points, timestamps dùng default của schema.
     * @return string - id vừa tạo
     * @throws \RuntimeException nếu thiếu role customer
     * @throws \PDOException 23000 nếu email trùng (race condition)
     */
    public function createCustomer($name, $email, $plainPassword)
    {
        $roleId = $this->getRoleIdByName(self::ROLE_CUSTOMER);
        if (!$roleId) {
            throw new \RuntimeException('Missing role: ' . self::ROLE_CUSTOMER);
        }

        return $this->create([
            'name'          => $name,
            'email'         => $email,
            'password'      => password_hash($plainPassword, PASSWORD_BCRYPT),
            'roleId'        => $roleId,
            'memberLevelId' => $this->getLevelIdByName(self::DEFAULT_LEVEL),
        ]);
    }

    public function phoneTaken($phone, $exceptUserId)
    {
        return $this->count('phoneNumber = ? AND id <> ?', [$phone, (int)$exceptUserId]) > 0;
    }

    public function getPasswordHash($userId)
    {
        $row = $this->db->query('SELECT password FROM users WHERE id = ?', [(int)$userId], true);
        return $row['password'] ?? null;
    }

    /**
     * Tìm thành viên theo SĐT hoặc email (nhân viên quầy tích điểm cho khách)
     * @return array|null
     */
    public function findCustomerByContact($contact)
    {
        return $this->db->query(
            "SELECT u.id, u.name, u.email, u.phoneNumber, u.points, u.status,
                    ml.name AS levelName, COALESCE(ml.pointRate, 0) AS pointRate
             FROM users u
             JOIN role r ON r.id = u.roleId
             LEFT JOIN member_level ml ON ml.id = u.memberLevelId
             WHERE r.name = 'customer' AND u.status = 'active' AND (u.phoneNumber = ? OR u.email = ?)
             LIMIT 1",
            [$contact, strtolower($contact)],
            true
        );
    }

    public function updatePasswordHash($userId, $hash)
    {
        return $this->update($userId, ['password' => $hash]);
    }
}
