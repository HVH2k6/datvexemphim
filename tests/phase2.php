<?php
/**
 * Test Phase 2 (Authentication) qua HTTP thật: php tests/phase2.php
 * Tự bật PHP built-in server, chỉ tạo/xóa user có email *@phase2.test.
 * Yêu cầu: composer install và database cinema_booking đã import schema.sql.
 */

// Chỉ chạy từ dòng lệnh: test tạo / xóa dữ liệu và bật server, không được gọi qua web
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

if (is_file(BASE_PATH . '/.env')) {
    Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();
}
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $key) {
    if (array_key_exists($key, $_ENV) && getenv($key) === false) {
        putenv($key . '=' . $_ENV[$key]);
    }
}

const TEST_DOMAIN = '@phase2.test';
const PORT = 18766;
const BASE_URL = 'http://127.0.0.1:' . PORT;
const NULL_DEVICE = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

$passed = 0;
$failed = 0;
function check($label, $ok)
{
    global $passed, $failed;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

/**
 * HTTP client tối giản có cookie jar, không tự follow redirect
 */
class Client
{
    public $cookies = [];
    public $setCookieHeaders = [];

    public function request($method, $path, $data = null)
    {
        $headers = ['Accept: text/html'];
        if ($this->cookies) {
            $pairs = [];
            foreach ($this->cookies as $k => $v) {
                $pairs[] = $k . "=" . rawurlencode($v);
            }
            $headers[] = 'Cookie: ' . implode('; ', $pairs);
        }

        $options = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10];
        if ($data !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options['content'] = http_build_query($data);
        }
        $options['header'] = implode("\r\n", $headers);

        $body = file_get_contents(BASE_URL . $path, false, stream_context_create(['http' => $options]));
        $responseHeaders = $http_response_header ?? [];

        preg_match('#^HTTP/\S+ (\d{3})#', $responseHeaders[0] ?? '', $m);
        $status = (int)($m[1] ?? 0);
        $location = null;
        foreach ($responseHeaders as $h) {
            if (stripos($h, 'Location:') === 0) {
                $location = parse_url(trim(substr($h, 9)), PHP_URL_PATH) ?: '/';
            }
            if (stripos($h, 'Set-Cookie:') === 0 && preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $h, $c)) {
                $this->setCookieHeaders[$c[1]] = $h;
                if ($c[2] === '' || $c[2] === 'deleted') {
                    unset($this->cookies[$c[1]]);
                } else {
                    $this->cookies[$c[1]] = urldecode($c[2]);
                }
            }
        }

        return ['status' => $status, 'location' => $location, 'body' => (string)$body];
    }

    public function get($path)
    {
        return $this->request('GET', $path);
    }

    public function post($path, $data)
    {
        return $this->request('POST', $path, $data);
    }

    /** Lấy csrf_token từ form trên trang */
    public function token($path)
    {
        $res = $this->get($path);
        preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $res['body'], $m);
        return $m[1] ?? null;
    }

    public function sessionId()
    {
        return $this->cookies['cinema_booking'] ?? null;
    }
}

$db = new DBHelper();
$cleanup = function () use ($db) {
    $db->execute('DELETE FROM users WHERE email LIKE ?', ['%' . TEST_DOMAIN]);
};
$cleanup();

// Bật server với APP_URL khớp port test
$env = array_merge(getenv(), ['APP_URL' => BASE_URL, 'APP_DEBUG' => 'false']);
$server = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . PORT, '-t', BASE_PATH . '/public', BASE_PATH . '/public/index.php'],
    [1 => ['file', NULL_DEVICE, 'w'], 2 => ['file', NULL_DEVICE, 'w']],
    $pipes,
    BASE_PATH,
    $env
);

$ready = false;
for ($i = 0; $i < 50 && !$ready; $i++) {
    usleep(100000);
    $ready = @fsockopen('127.0.0.1', PORT) !== false;
}

try {
    check('server khởi động', $ready);
    if (!$ready) {
        throw new RuntimeException('Server không khởi động');
    }

    $seedBefore = $db->query("SELECT id, email, password, roleId, status FROM users WHERE email NOT LIKE ? ORDER BY id", ['%' . TEST_DOMAIN]);

    // ---------- Form ----------
    $guest = new Client();
    $res = $guest->get('/login');
    check('GET /login trả 200 + form có CSRF', $res['status'] === 200 && preg_match('/name="csrf_token" value="[a-f0-9]{64}"/', $res['body']));
    $res = $guest->get('/register');
    check('GET /register trả 200 + có ô xác nhận mật khẩu', $res['status'] === 200 && str_contains($res['body'], 'name="password_confirm"'));

    // ---------- CSRF ----------
    $res = $guest->post('/register', ['name' => 'Test', 'email' => 'x' . TEST_DOMAIN, 'password' => '123456', 'password_confirm' => '123456']);
    check('POST /register thiếu CSRF trả 403', $res['status'] === 403);
    $res = $guest->post('/login', ['csrf_token' => str_repeat('a', 64), 'email' => 'x' . TEST_DOMAIN, 'password' => '123456']);
    check('POST /login sai CSRF trả 403', $res['status'] === 403);

    // ---------- Validation đăng ký ----------
    $email = 'khach' . TEST_DOMAIN;
    $cases = [
        'tên quá ngắn'          => ['name' => 'AB', 'email' => $email, 'password' => '123456', 'password_confirm' => '123456'],
        'email sai định dạng'   => ['name' => 'Khách Test', 'email' => 'khong-phai-email', 'password' => '123456', 'password_confirm' => '123456'],
        'mật khẩu quá ngắn'     => ['name' => 'Khách Test', 'email' => $email, 'password' => '123', 'password_confirm' => '123'],
        'xác nhận không khớp'   => ['name' => 'Khách Test', 'email' => $email, 'password' => '123456', 'password_confirm' => '654321'],
        'mảng thay vì chuỗi'    => ['name' => ['x'], 'email' => $email, 'password' => '123456', 'password_confirm' => '123456'],
    ];
    foreach ($cases as $label => $data) {
        $data['csrf_token'] = $guest->token('/register');
        $res = $guest->post('/register', $data);
        check("đăng ký lỗi ({$label}) quay lại /register", $res['status'] === 302 && $res['location'] === '/register');
    }
    check('validation lỗi không tạo user', $db->query('SELECT COUNT(*) c FROM users WHERE email = ?', [$email], true)['c'] == 0);

    $res = $guest->get('/register');
    check('flash lỗi hiển thị và giữ lại email, không có mật khẩu', str_contains($res['body'], 'alert-danger')
        && str_contains($res['body'], 'value="' . $email . '"') && !str_contains($res['body'], 'value="123456"'));

    // ---------- Đăng ký thành công (kèm field đặc quyền giả mạo) ----------
    $res = $guest->post('/register', [
        'csrf_token' => $guest->token('/register'),
        'name' => '  Khách   Test  ', 'email' => '  KHACH' . strtoupper(TEST_DOMAIN) . ' ',
        'password' => 'matkhau123', 'password_confirm' => 'matkhau123',
        'roleId' => 1, 'status' => 'blocked', 'points' => 9999, 'memberLevelId' => 3,
    ]);
    check('đăng ký thành công chuyển tới /login', $res['status'] === 302 && $res['location'] === '/login');

    $created = $db->query(
        'SELECT u.*, r.name AS roleName, ml.name AS levelName FROM users u
         JOIN role r ON r.id = u.roleId LEFT JOIN member_level ml ON ml.id = u.memberLevelId WHERE u.email = ?',
        [$email], true
    );
    check('user được tạo với email chuẩn hóa', $created !== null);
    check('tên được chuẩn hóa khoảng trắng', ($created['name'] ?? '') === 'Khách Test');
    check('mật khẩu lưu dạng bcrypt hash', $created && str_starts_with($created['password'], '$2y$') && password_verify('matkhau123', $created['password']));
    check('role = customer dù gửi roleId=1', ($created['roleName'] ?? '') === 'customer');
    check('status/points/hạng không bị ghi đè', ($created['status'] ?? '') === 'active' && (int)($created['points'] ?? -1) === 0 && ($created['levelName'] ?? '') === 'Member');

    $res = $guest->post('/register', [
        'csrf_token' => $guest->token('/register'),
        'name' => 'Người Khác', 'email' => $email, 'password' => '123456', 'password_confirm' => '123456',
    ]);
    check('email trùng bị từ chối', $res['status'] === 302 && $res['location'] === '/register');
    check('không tạo bản ghi trùng', $db->query('SELECT COUNT(*) c FROM users WHERE email = ?', [$email], true)['c'] == 1);

    // ---------- Đăng nhập ----------
    $res = $guest->post('/login', ['csrf_token' => $guest->token('/login'), 'email' => $email, 'password' => 'sai-mat-khau']);
    check('sai mật khẩu quay lại /login', $res['status'] === 302 && $res['location'] === '/login');
    $wrongPasswordMsg = $guest->get('/login')['body'];
    $guest->post('/login', ['csrf_token' => $guest->token('/login'), 'email' => 'khongton' . TEST_DOMAIN, 'password' => 'sai-mat-khau']);
    $unknownEmailMsg = $guest->get('/login')['body'];
    check('cùng thông báo cho email không tồn tại và sai mật khẩu',
        str_contains($wrongPasswordMsg, 'Email hoặc mật khẩu không đúng') && str_contains($unknownEmailMsg, 'Email hoặc mật khẩu không đúng'));

    $customer = new Client();
    $token = $customer->token('/login');
    $sessionBefore = $customer->sessionId();
    $res = $customer->post('/login', ['csrf_token' => $token, 'email' => strtoupper($email), 'password' => 'matkhau123']);
    check('customer đăng nhập (email không phân biệt hoa thường) chuyển tới /', $res['status'] === 302 && $res['location'] === '/');
    check('session id đổi sau khi đăng nhập', $sessionBefore && $customer->sessionId() && $customer->sessionId() !== $sessionBefore);
    check('đã đăng nhập vào /login bị chuyển về trang chủ role', ($r = $customer->get('/login'))['status'] === 302 && $r['location'] === '/');
    check('customer vào route customer (200)', $customer->get('/user/profile')['status'] === 200);
    check('customer bị chặn /admin (403)', $customer->get('/admin')['status'] === 403);
    check('customer bị chặn /staff (403)', $customer->get('/staff')['status'] === 403);
    check('navbar hiển thị tên + nút đăng xuất', str_contains($body = $customer->get('/404')['body'], 'Khách Test') && str_contains($body, 'Đăng xuất'));

    // ---------- Đăng xuất ----------
    $res = $customer->post('/logout', []);
    check('POST /logout thiếu CSRF trả 403 và vẫn giữ đăng nhập', $res['status'] === 403 && $customer->get('/admin')['status'] === 403);
    preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $customer->get('/404')['body'], $m);
    $res = $customer->post('/logout', ['csrf_token' => $m[1] ?? '']);
    check('POST /logout chuyển về /', $res['status'] === 302 && $res['location'] === '/');
    $res = $customer->get('/user/profile');
    check('sau đăng xuất route bảo vệ chuyển tới /login', $res['status'] === 302 && $res['location'] === '/login');
    check('GET /logout không được hỗ trợ (405)', $customer->get('/logout')['status'] === 405);

    // ---------- Tài khoản bị khóa ----------
    $blockedEmail = 'khoa' . TEST_DOMAIN;
    $db->insert('users', [
        'name' => 'Bị Khóa', 'email' => $blockedEmail, 'password' => password_hash('123456', PASSWORD_BCRYPT),
        'roleId' => (new \App\Models\User())->getRoleIdByName('customer'), 'status' => 'blocked',
    ]);
    $blocked = new Client();
    $res = $blocked->post('/login', ['csrf_token' => $blocked->token('/login'), 'email' => $blockedEmail, 'password' => '123456']);
    check('tài khoản blocked không đăng nhập được', $res['status'] === 302 && $res['location'] === '/login'
        && str_contains($blocked->get('/login')['body'], 'đã bị khóa'));
    check('tài khoản blocked không có quyền', ($r = $blocked->get('/user/profile'))['status'] === 302 && $r['location'] === '/login');

    // Khóa tài khoản khi đang đăng nhập -> bị đăng xuất ở request tiếp theo
    $active = new Client();
    $active->post('/login', ['csrf_token' => $active->token('/login'), 'email' => $email, 'password' => 'matkhau123']);
    $db->update('users', ['status' => 'blocked'], 'email = ?', [$email]);
    $res = $active->get('/user/profile');
    check('bị khóa khi đang đăng nhập -> mất phiên', $res['status'] === 302 && $res['location'] === '/login');
    $db->update('users', ['status' => 'active'], 'email = ?', [$email]);

    // ---------- Staff / Admin (tài khoản test riêng) ----------
    $userModel = new \App\Models\User();
    foreach (['staff' => '/staff', 'admin' => '/admin'] as $role => $home) {
        $roleEmail = $role . TEST_DOMAIN;
        $db->insert('users', [
            'name' => ucfirst($role) . ' Test', 'email' => $roleEmail,
            'password' => password_hash('123456', PASSWORD_BCRYPT), 'roleId' => $userModel->getRoleIdByName($role),
        ]);
        $client = new Client();
        $res = $client->post('/login', ['csrf_token' => $client->token('/login'), 'email' => $roleEmail, 'password' => '123456']);
        check("{$role} đăng nhập chuyển tới {$home}", $res['status'] === 302 && $res['location'] === $home);
        // /staff làm ở Phase 5, /admin ở Phase 6-7
        $expected = 200;
        check("{$role} vào {$home} ({$expected})", $client->get($home)['status'] === $expected);
        check("{$role} bị chặn route customer (403)", $client->get('/user/profile')['status'] === 403);
    }
    $client = new Client();
    $client->post('/login', ['csrf_token' => $client->token('/login'), 'email' => 'staff' . TEST_DOMAIN, 'password' => '123456']);
    check('staff bị chặn /admin (403)', $client->get('/admin')['status'] === 403);

    // ---------- Ghi nhớ tôi (100 ngày) ----------
    $rememberEmail = 'nho' . TEST_DOMAIN;
    $db->insert('users', [
        'name' => 'Ghi Nhớ', 'email' => $rememberEmail, 'password' => password_hash('123456', PASSWORD_BCRYPT),
        'roleId' => $userModel->getRoleIdByName('customer'),
    ]);
    check('form đăng nhập có ô "Ghi nhớ tôi"', str_contains($guest->get('/login')['body'], 'name="remember"'));

    $plain = new Client();
    $plain->post('/login', ['csrf_token' => $plain->token('/login'), 'email' => $rememberEmail, 'password' => '123456']);
    check('không tick ghi nhớ: không có cookie ghi nhớ', !isset($plain->cookies['cinema_remember'])
        && $db->query('SELECT rememberToken FROM users WHERE email = ?', [$rememberEmail], true)['rememberToken'] === null);

    $remember = new Client();
    $res = $remember->post('/login', ['csrf_token' => $remember->token('/login'), 'email' => $rememberEmail, 'password' => '123456', 'remember' => '1']);
    $cookie = $remember->cookies['cinema_remember'] ?? '';
    $header = $remember->setCookieHeaders['cinema_remember'] ?? '';
    preg_match('/expires=([^;]+)/i', $header, $exp);
    $days = isset($exp[1]) ? (strtotime($exp[1]) - time()) / 86400 : 0;
    check('tick ghi nhớ: cookie HttpOnly, SameSite=Lax, hạn 100 ngày', $res['status'] === 302 && $cookie !== ''
        && stripos($header, 'HttpOnly') !== false && stripos($header, 'SameSite=Lax') !== false && abs($days - 100) < 0.1);
    $stored = $db->query('SELECT rememberToken FROM users WHERE email = ?', [$rememberEmail], true)['rememberToken'];
    [, $selector, $validator] = array_pad(explode(':', $cookie), 3, '');
    check('DB chỉ lưu hash của token, không lưu token gốc', $stored === $selector . ':' . hash('sha256', $validator)
        && !str_contains($stored, $validator));

    // Mất session (đóng trình duyệt / session hết hạn) -> vẫn đăng nhập bằng cookie ghi nhớ
    unset($remember->cookies['cinema_booking']);
    $res = $remember->get('/login');
    check('hết session + còn cookie ghi nhớ: tự đăng nhập lại', $res['status'] === 302 && $res['location'] === '/');
    check('tự đăng nhập lại: token được xoay vòng', ($remember->cookies['cinema_remember'] ?? '') !== $cookie
        && $db->query('SELECT rememberToken FROM users WHERE email = ?', [$rememberEmail], true)['rememberToken'] !== $stored);

    // Cookie cũ (đã xoay vòng) hoặc bị sửa không dùng được
    $stolen = new Client();
    $stolen->cookies['cinema_remember'] = $cookie;
    $res = $stolen->get('/user/profile');
    check('cookie cũ sau khi xoay vòng bị từ chối', $res['status'] === 302 && $res['location'] === '/login');
    $forged = new Client();
    $forged->cookies['cinema_remember'] = preg_replace('/.$/', '0', $remember->cookies['cinema_remember']) === $remember->cookies['cinema_remember']
        ? preg_replace('/.$/', '1', $remember->cookies['cinema_remember'])
        : preg_replace('/.$/', '0', $remember->cookies['cinema_remember']);
    check('cookie bị sửa bị từ chối', ($r = $forged->get('/user/profile'))['status'] === 302 && $r['location'] === '/login');
    $garbage = new Client();
    $garbage->cookies['cinema_remember'] = "1:' OR 1=1 --";
    check('cookie rác bị từ chối', ($r = $garbage->get('/user/profile'))['status'] === 302 && $r['location'] === '/login');

    // Tài khoản bị khóa: cookie ghi nhớ không còn tác dụng
    $blockedRemember = new Client();
    $blockedRemember->cookies['cinema_remember'] = $remember->cookies['cinema_remember'];
    $db->update('users', ['status' => 'blocked'], 'email = ?', [$rememberEmail]);
    check('tài khoản bị khóa: không đăng nhập bằng cookie', ($r = $blockedRemember->get('/user/profile'))['status'] === 302 && $r['location'] === '/login');
    $db->update('users', ['status' => 'active'], 'email = ?', [$rememberEmail]);

    // Đăng xuất: hủy token -> cookie không đăng nhập lại được
    $remember->get('/');
    preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $remember->get('/404')['body'], $m);
    $cookieBeforeLogout = $remember->cookies['cinema_remember'] ?? '';
    $remember->post('/logout', ['csrf_token' => $m[1] ?? '']);
    check('đăng xuất: xóa cookie + token trong DB', !isset($remember->cookies['cinema_remember'])
        && $db->query('SELECT rememberToken FROM users WHERE email = ?', [$rememberEmail], true)['rememberToken'] === null);
    $replay = new Client();
    $replay->cookies['cinema_remember'] = $cookieBeforeLogout;
    check('sau đăng xuất cookie cũ không dùng được', ($r = $replay->get('/user/profile'))['status'] === 302 && $r['location'] === '/login');

    // ---------- Dữ liệu seed không bị ảnh hưởng ----------
    $seedAfter = $db->query("SELECT id, email, password, roleId, status FROM users WHERE email NOT LIKE ? ORDER BY id", ['%' . TEST_DOMAIN]);
    check('user seed (admin/staff/khách) không bị thay đổi', $seedBefore === $seedAfter);
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    $cleanup();
}

check('đã dọn user test', $db->query('SELECT COUNT(*) c FROM users WHERE email LIKE ?', ['%' . TEST_DOMAIN], true)['c'] == 0);

echo PHP_EOL . "{$passed} passed, {$failed} failed" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
