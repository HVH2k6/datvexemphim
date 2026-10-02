<?php
/**
 * Entry point - mọi request đi qua router và middleware
 */

// PHP built-in server (php -S ... public/index.php): trả file tĩnh có thật (css, js, ảnh) trực tiếp
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if ($file !== __FILE__ && is_file($file)) {
        return false;
    }
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

if (is_file(BASE_PATH . '/.env')) {
    Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();
}

// dotenv::createImmutable() ghi vào $_ENV, không ghi vào getenv() mặc định.
// Đồng bộ sang getenv() để config và helpers đều đọc cùng giá trị (biến môi trường thật được ưu tiên).
foreach ($_ENV as $key => $value) {
    if (is_string($key) && is_scalar($value) && getenv($key) === false) {
        putenv($key . '=' . $value);
    }
}

$config = require BASE_PATH . '/config/app.php';
date_default_timezone_set($config['timezone']);
// Chi tiết lỗi (file:line, SQLSTATE) chỉ hiện cho máy chạy local, kể cả khi lỡ để APP_DEBUG=true trên server
$fromLocalhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    && empty($_SERVER['HTTP_X_FORWARDED_FOR']);
\App\Core\ErrorHandler::register($config['debug'] && $fromLocalhost);

\App\Core\SecurityHeaders::send();

$secure = \App\Core\SecurityHeaders::isHttps();
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
session_name('cinema_booking');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Hết session nhưng còn cookie "Ghi nhớ tôi" hợp lệ -> tự đăng nhập lại
\AuthHelper::loginFromRememberCookie();

$router = new \App\Core\Router(require BASE_PATH . '/routes/web.php');
echo $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
