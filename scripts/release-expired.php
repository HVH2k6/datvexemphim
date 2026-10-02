<?php
/**
 * Cron: hết hạn đơn giữ ghế quá giờ + nhả ghế (thay cho MySQL event khi hosting không bật event_scheduler)
 *
 *   php scripts/release-expired.php
 *
 * Linux (crontab -e), chạy mỗi phút:
 *   * * * * * cd /var/www/booking-ticket-movie && php scripts/release-expired.php >> storage/logs/cron.log 2>&1
 * Windows: scripts/install-cron.ps1 tạo Task Scheduler chạy mỗi phút.
 *
 * Chỉ chạy từ dòng lệnh; trả mã thoát 0 nếu thành công, 1 nếu lỗi.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

if (is_file(BASE_PATH . '/.env')) {
    Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();
}
foreach ($_ENV as $key => $value) {
    if (is_string($key) && is_scalar($value) && getenv($key) === false) {
        putenv($key . '=' . $value);
    }
}
$config = require BASE_PATH . '/config/app.php';
date_default_timezone_set($config['timezone']);

// Khóa file: không chạy chồng 2 tiến trình nếu lần trước còn treo
$lock = fopen(BASE_PATH . '/storage/release-expired.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, date('c') . " skip: previous run still active\n");
    exit(0);
}

try {
    $db = new DBHelper();
    $before = (int)$db->query("SELECT COUNT(*) AS c FROM booking WHERE status = 'pending' AND expiredAt < NOW()", [], true)['c'];
    (new App\Models\Booking())->releaseExpired();
    if ($before > 0) {
        echo date('c') . " expired {$before} booking(s)\n";
    }
    exit(0);
} catch (Throwable $e) {
    error_log('[cron release-expired] ' . $e->getMessage());
    fwrite(STDERR, date('c') . ' error: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
