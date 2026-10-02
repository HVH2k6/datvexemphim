<?php
namespace App\Controllers;

use App\Models\User;

class AuthController extends BaseController
{
    const NAME_MIN = 3;
    const NAME_MAX = 100;
    const EMAIL_MAX = 150;
    const PASSWORD_MIN = 6;
    const PASSWORD_MAX = 72;    // bcrypt chỉ dùng 72 byte đầu
    // Hash của 1 chuỗi ngẫu nhiên không ai biết: dùng khi email không tồn tại để thời gian xử lý như nhau
    const DUMMY_HASH = '$2y$10$ZCN2o5jqsCK4DCPFIF8PKuZa3PcO6ZGOH2oehBWtvDE1jAUxV..Ny';

    const HOME_BY_ROLE = [
        'admin'    => '/admin',
        'staff'    => '/staff',
        'customer' => '/',
    ];

    public function login()
    {
        if ($this->isAuthenticated()) {
            return $this->redirect($this->homeFor($this->getRole()));
        }

        return $this->render('customer.auth.login', ['old' => $this->pullOldInput()]);
    }

    public function doLogin()
    {
        $email = $this->normalizeEmail($_POST['email'] ?? '');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($email === '' || $password === '') {
            return $this->redirectWithMessage('/login', 'Vui lòng nhập email và mật khẩu', 'danger', ['email' => $email]);
        }

        $wait = \LoginThrottle::blockedFor($email);
        if ($wait > 0) {
            return $this->redirectWithMessage('/login', 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau ' . (int)ceil($wait / 60) . ' phút', 'danger', ['email' => $email]);
        }

        $userModel = new User();
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $userModel->getAuthByEmail($email) : null;

        // Email không tồn tại vẫn chạy password_verify với hash giả: thời gian phản hồi như nhau, không lộ email
        $valid = password_verify($password, $user['password'] ?? self::DUMMY_HASH) && $user !== null;

        // Cùng 1 thông báo cho email sai / mật khẩu sai để không lộ email nào tồn tại
        if (!$valid) {
            \LoginThrottle::fail($email);
            return $this->redirectWithMessage('/login', 'Email hoặc mật khẩu không đúng', 'danger', ['email' => $email]);
        }
        \LoginThrottle::clear($email);

        if ($user['status'] !== 'active') {
            return $this->redirectWithMessage('/login', 'Tài khoản của bạn đã bị khóa', 'danger', ['email' => $email]);
        }

        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
            $userModel->updatePasswordHash($user['id'], password_hash($password, PASSWORD_BCRYPT));
        }

        // Trang khách đang mở trước khi bị yêu cầu đăng nhập (vd: chọn ghế)
        $intended = $_SESSION['intended_path'] ?? null;
        unset($_SESSION['intended_path']);

        // Role lấy từ DB (tên role), không lấy từ input. "Ghi nhớ tôi" -> không cần đăng nhập lại trong 100 ngày
        \AuthHelper::login($user['id'], $user['roleName'], ($_POST['remember'] ?? '') === '1');

        $target = $this->homeFor($user['roleName']);
        if ($user['roleName'] === 'customer' && is_string($intended) && preg_match('#^/(?!/)[A-Za-z0-9/_\-.%]*$#', $intended)) {
            $target = $intended;
        }

        return $this->redirectWithMessage($target, 'Đăng nhập thành công', 'success');
    }

    public function register()
    {
        if ($this->isAuthenticated()) {
            return $this->redirect($this->homeFor($this->getRole()));
        }

        return $this->render('customer.auth.register', ['old' => $this->pullOldInput()]);
    }

    public function doRegister()
    {
        $name = is_string($_POST['name'] ?? null) ? trim(preg_replace('/\s+/u', ' ', $_POST['name'])) : '';
        $email = $this->normalizeEmail($_POST['email'] ?? '');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $passwordConfirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';
        $old = ['name' => $name, 'email' => $email];

        $error = null;
        $nameLength = mb_strlen($name, 'UTF-8');
        if ($nameLength < self::NAME_MIN || $nameLength > self::NAME_MAX) {
            $error = 'Họ tên phải từ ' . self::NAME_MIN . ' đến ' . self::NAME_MAX . ' ký tự';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > self::EMAIL_MAX) {
            $error = 'Email không hợp lệ';
        } elseif (strlen($password) < self::PASSWORD_MIN || strlen($password) > self::PASSWORD_MAX) {
            $error = 'Mật khẩu phải từ ' . self::PASSWORD_MIN . ' đến ' . self::PASSWORD_MAX . ' ký tự';
        } elseif (!hash_equals($password, $passwordConfirm)) {
            $error = 'Mật khẩu xác nhận không khớp';
        }

        if ($error) {
            return $this->redirectWithMessage('/register', $error, 'danger', $old);
        }

        $userModel = new User();
        if ($userModel->emailExists($email)) {
            return $this->redirectWithMessage('/register', 'Email đã được đăng ký', 'danger', $old);
        }

        try {
            $userModel->createCustomer($name, $email, $password);
        } catch (\PDOException $e) {
            // 23000: vi phạm unique (2 request đăng ký cùng email cùng lúc)
            if ($e->getCode() === '23000') {
                return $this->redirectWithMessage('/register', 'Email đã được đăng ký', 'danger', $old);
            }
            error_log('Register error: ' . $e->getMessage());
            return $this->redirectWithMessage('/register', 'Đăng ký thất bại, vui lòng thử lại', 'danger', $old);
        }

        return $this->redirectWithMessage('/login', 'Đăng ký thành công. Vui lòng đăng nhập.', 'success', ['email' => $email]);
    }

    public function logout()
    {
        \AuthHelper::logout();

        // Mở session mới (id mới) để hiển thị thông báo sau khi đăng xuất
        session_id(session_create_id());
        session_start();
        return $this->redirectWithMessage('/', 'Bạn đã đăng xuất', 'info');
    }

    private function homeFor($roleName)
    {
        return self::HOME_BY_ROLE[$roleName] ?? '/';
    }

    private function normalizeEmail($email)
    {
        return is_string($email) ? strtolower(trim($email)) : '';
    }

    /**
     * Redirect tới path cố định kèm flash message và input cũ (không bao giờ lưu password)
     */
    private function redirectWithMessage($path, $message, $type = 'info', $old = [])
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
        if ($old) {
            $_SESSION['old_input'] = $old;
        }

        return $this->redirect($path);
    }

    private function pullOldInput()
    {
        $old = $_SESSION['old_input'] ?? [];
        unset($_SESSION['old_input']);

        return is_array($old) ? $old : [];
    }
}
