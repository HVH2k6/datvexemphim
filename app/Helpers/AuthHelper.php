<?php

/**
 * AuthHelper - quản lý đăng nhập, session và "Ghi nhớ tôi"
 *
 * Ghi nhớ đăng nhập (100 ngày): cookie chứa "selector:validator" ngẫu nhiên.
 * DB (users.rememberToken) chỉ lưu "selector:sha256(validator)" -> lộ DB không đăng nhập được.
 * Mỗi lần dùng cookie để đăng nhập lại, token được xoay vòng (cấp token mới).
 */
class AuthHelper
{
    const SESSION_USER_ID = 'user_id';
    const SESSION_ROLE = 'role';
    const SESSION_PASSWORD = 'pwd_stamp';

    const REMEMBER_COOKIE = 'cinema_remember';
    const REMEMBER_DAYS = 100;

    public static function check()
    {
        return isset($_SESSION[self::SESSION_USER_ID]);
    }

    public static function id()
    {
        return $_SESSION[self::SESSION_USER_ID] ?? null;
    }

    public static function role()
    {
        return $_SESSION[self::SESSION_ROLE] ?? null;
    }

    /**
     * Role lấy từ bảng role, không đoán role qua id
     * @param bool $remember - true: cấp cookie ghi nhớ đăng nhập 100 ngày
     */
    public static function login($userId, $roleName, $remember = false)
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = $userId;
        $_SESSION[self::SESSION_ROLE] = $roleName;
        unset($_SESSION['csrf_token']);
        self::stampPassword($userId);

        if ($remember) {
            self::issueRememberToken($userId);
        }
    }

    public static function user()
    {
        if (!self::check()) {
            return null;
        }

        $db = new DBHelper();
        $user = $db->query(
            'SELECT u.id, u.name, u.email, u.roleId, u.status, u.password, r.name AS roleName
             FROM users u JOIN role r ON r.id = u.roleId WHERE u.id = ?',
            [self::id()],
            true
        );

        // Mật khẩu đã đổi ở nơi khác (khách tự đổi / admin đặt lại): phiên cũ hết hiệu lực
        $stamp = $_SESSION[self::SESSION_PASSWORD] ?? null;
        if (!$user || $user['status'] !== 'active' || !is_string($stamp) || !hash_equals(self::fingerprint($user['password']), $stamp)) {
            self::logout();
            return null;
        }
        unset($user['password']);

        $_SESSION[self::SESSION_ROLE] = $user['roleName'];
        return $user;
    }

    /**
     * Ghi dấu mật khẩu hiện tại vào session (gọi sau đăng nhập / sau khi chính user đổi mật khẩu)
     */
    public static function stampPassword($userId)
    {
        $row = (new DBHelper())->query('SELECT password FROM users WHERE id = ?', [(int)$userId], true);
        $_SESSION[self::SESSION_PASSWORD] = $row ? self::fingerprint($row['password']) : '';
    }

    private static function fingerprint($passwordHash)
    {
        return hash_hmac('sha256', (string)$passwordHash, session_name());
    }

    /**
     * Đăng xuất: hủy session + token ghi nhớ (trên DB và cookie)
     */
    public static function logout()
    {
        if (self::check()) {
            (new DBHelper())->update('users', ['rememberToken' => null], 'id = ?', [(int)self::id()]);
        }
        self::forgetRememberCookie();

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Gọi ở đầu mỗi request (public/index.php): chưa có session nhưng có cookie ghi nhớ hợp lệ -> đăng nhập lại
     */
    public static function loginFromRememberCookie()
    {
        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? null;
        if (self::check() || !is_string($cookie)) {
            return;
        }

        $parsed = self::parseCookie($cookie);
        if (!$parsed) {
            self::forgetRememberCookie();
            return;
        }
        [$userId, $selector, $validator] = $parsed;

        $db = new DBHelper();
        $user = $db->query(
            'SELECT u.id, u.status, u.rememberToken, r.name AS roleName
             FROM users u JOIN role r ON r.id = u.roleId WHERE u.id = ?',
            [$userId],
            true
        );

        $stored = $user ? explode(':', (string)$user['rememberToken'], 2) : [];
        $valid = count($stored) === 2
            && hash_equals($stored[0], $selector)
            && hash_equals($stored[1], hash('sha256', $validator))
            && $user['status'] === 'active';

        if (!$valid) {
            self::forgetRememberCookie();
            return;
        }

        // Đăng nhập lại + xoay vòng token (cookie cũ hết giá trị)
        self::login((int)$user['id'], $user['roleName'], true);
    }

    /**
     * Tạo token mới, lưu hash vào DB và gửi cookie HttpOnly 100 ngày
     */
    private static function issueRememberToken($userId)
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));

        (new DBHelper())->update(
            'users',
            ['rememberToken' => $selector . ':' . hash('sha256', $validator)],
            'id = ?',
            [(int)$userId]
        );

        self::sendCookie((int)$userId . ':' . $selector . ':' . $validator, time() + self::REMEMBER_DAYS * 86400);
    }

    /**
     * @return array|null [userId, selector, validator]
     */
    private static function parseCookie($cookie)
    {
        if (!preg_match('/^(\d{1,20}):([a-f0-9]{24}):([a-f0-9]{64})$/', $cookie, $m)) {
            return null;
        }

        return [(int)$m[1], $m[2], $m[3]];
    }

    private static function forgetRememberCookie()
    {
        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            self::sendCookie('', time() - 42000);
            unset($_COOKIE[self::REMEMBER_COOKIE]);
        }
    }

    private static function sendCookie($value, $expires)
    {
        if (headers_sent()) {
            return;
        }

        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function csrfToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Ô hidden csrf_token cho form (dùng {!! \AuthHelper::csrfField() !!})
     */
    public static function csrfField()
    {
        return '<input type="hidden" name="csrf_token" value="' . self::csrfToken() . '">';
    }

    public static function verifyCsrf($token)
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}
