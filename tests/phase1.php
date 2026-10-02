<?php
/**
 * Smoke test Phase 1: php tests/phase1.php
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

foreach (['APP_URL', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $key) {
    if (array_key_exists($key, $_ENV) && getenv($key) === false) {
        putenv($key . '=' . $_ENV[$key]);
    }
}

$config = require BASE_PATH . '/config/app.php';
date_default_timezone_set($config['timezone']);
ini_set('session.use_strict_mode', '1');
session_start();

// Giữ output đến cuối để header() và session_regenerate_id() trong test không bị ảnh hưởng.
ob_start();
$passed = 0;
$failed = 0;
function check($label, $ok)
{
    global $passed, $failed;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

function statusOf($router, $method, $uri)
{
    try {
        $router->dispatch($method, $uri);
        return 200;
    } catch (\App\Core\HttpException $e) {
        return $e->getStatusCode();
    }
}

$db = new DBHelper();
$router = new \App\Core\Router(require BASE_PATH . '/routes/web.php');
$originalSession = $_SESSION;

try {
    check('router: URL lạ trả 404', statusOf($router, 'GET', '/khong-co') === 404);
    check('router: route tham số vào controller (phim không có -> 404)', statusOf($router, 'GET', '/movies/phim-abc') === 404
        && statusOf($router, 'GET', '/movies/phim-hanh-dong-mau') === 200);
    check('router: GET /login trả 200', statusOf($router, 'GET', '/login') === 200);
    check('router: POST /login thiếu CSRF trả 403', statusOf($router, 'POST', '/login') === 403);
    check('router: PUT /login trả 405', statusOf($router, 'PUT', '/login') === 405);
    check('router: path với encoded slash trả 404', statusOf($router, 'GET', '/movies/a%2Fb') === 404);

    $token = AuthHelper::csrfToken();
    check('CSRF: token hợp lệ', AuthHelper::verifyCsrf($token));
    check('CSRF: token sai', !AuthHelper::verifyCsrf(str_repeat('0', 64)));
    $_POST['csrf_token'] = $token;
    check('router: POST có CSRF vào controller (200)', statusOf($router, 'POST', '/login') === 200);
    unset($_POST['csrf_token']);

    $user = $db->query('SELECT u.id, r.name AS roleName FROM users u JOIN role r ON r.id = u.roleId WHERE r.name = ?', ['customer'], true);
    check('database: có customer mẫu', $user !== null);
    if ($user) {
        AuthHelper::login($user['id'], $user['roleName']);
        check('auth: login và đọc user', AuthHelper::user()['id'] == $user['id']);
        check('auth: role = customer', AuthHelper::role() === 'customer');
        check('middleware: customer route vào controller (200)', statusOf($router, 'GET', '/user/profile') === 200);
        check('middleware: customer bị chặn admin (403)', statusOf($router, 'GET', '/admin') === 403);
        check('middleware: customer bị chặn staff (403)', statusOf($router, 'GET', '/staff') === 403);
    }

    $view = \App\Core\View::getInstance();
    $html = $view->make('errors.404', ['message' => '<script>alert(1)</script>']);
    check('Blade: render layout và navbar', str_contains($html, '<nav') && str_contains($html, '<h1>404</h1>'));
    check('Blade: escape HTML output', !str_contains($html, '<script>alert(1)</script>') && str_contains($html, '&lt;script&gt;'));
} finally {
    $_SESSION = $originalSession;
    session_destroy();
}

echo PHP_EOL . "{$passed} passed, {$failed} failed" . PHP_EOL;
ob_end_flush();
exit($failed === 0 ? 0 : 1);
