<?php
/**
 * Hàm dùng chung cho test HTTP thật (phase4, phase5): bật PHP built-in server, client có cookie jar.
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
foreach ($_ENV as $key => $value) {
    if (is_string($key) && is_scalar($value) && getenv($key) === false) {
        putenv($key . '=' . $value);
    }
}
date_default_timezone_set('Asia/Ho_Chi_Minh');

const NULL_DEVICE = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

$passed = 0;
$failed = 0;
function check($label, $ok)
{
    global $passed, $failed;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

function finish()
{
    global $passed, $failed;
    echo PHP_EOL . "{$passed} passed, {$failed} failed" . PHP_EOL;
    exit($failed === 0 ? 0 : 1);
}

/**
 * Bật server ở cổng riêng, trả về resource để tắt sau
 */
function startServer($port, array $env = [])
{
    $server = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', BASE_PATH . '/public', BASE_PATH . '/public/index.php'],
        [1 => ['file', NULL_DEVICE, 'w'], 2 => ['file', NULL_DEVICE, 'w']],
        $pipes,
        BASE_PATH,
        array_merge(getenv(), ['APP_URL' => 'http://127.0.0.1:' . $port, 'APP_DEBUG' => 'false'], $env)
    );
    for ($i = 0; $i < 50; $i++) {
        usleep(100000);
        if (@fsockopen('127.0.0.1', $port) !== false) {
            return $server;
        }
    }
    proc_terminate($server);
    throw new RuntimeException('Server không khởi động');
}

function stopServer($server)
{
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
}

class Client
{
    public $cookies = [];
    private $base;
    private $csrf = null;

    public function __construct($port)
    {
        $this->base = 'http://127.0.0.1:' . $port;
    }

    public function request($method, $path, $data = null, $ajax = false)
    {
        $headers = [$ajax ? 'Accept: application/json' : 'Accept: text/html'];
        if ($ajax) {
            $headers[] = 'X-Requested-With: XMLHttpRequest';
        }
        if ($this->cookies) {
            $pairs = [];
            foreach ($this->cookies as $k => $v) {
                $pairs[] = $k . '=' . rawurlencode($v);
            }
            $headers[] = 'Cookie: ' . implode('; ', $pairs);
        }

        $options = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15];
        if ($data !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options['content'] = http_build_query($data);
        }
        $options['header'] = implode("\r\n", $headers);

        $body = (string)file_get_contents($this->base . $path, false, stream_context_create(['http' => $options]));
        $status = 0;
        $location = null;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+ (\d{3})#', $h, $m)) {
                $status = (int)$m[1];
            } elseif (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            } elseif (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $h, $c)) {
                if ($c[2] === '' || $c[2] === 'deleted') {
                    unset($this->cookies[$c[1]]);
                } else {
                    $this->cookies[$c[1]] = urldecode($c[2]);
                }
            }
        }
        if (preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $body, $m)) {
            $this->csrf = $m[1];
        }

        return [
            'status' => $status,
            'path'   => $location ? (parse_url($location, PHP_URL_PATH) ?: '/') : null,
            'query'  => $location ? (string)parse_url($location, PHP_URL_QUERY) : '',
            'body'   => $body,
            'json'   => json_decode($body, true),
        ];
    }

    public function get($path)
    {
        return $this->request('GET', $path);
    }

    /** POST form kèm CSRF token lấy từ trang đã mở gần nhất */
    public function post($path, array $data)
    {
        return $this->request('POST', $path, $data + ['csrf_token' => $this->csrf]);
    }

    /** POST AJAX (JSON) kèm CSRF */
    public function ajax($path, array $data)
    {
        return $this->request('POST', $path, $data + ['csrf_token' => $this->csrf], true);
    }

    /** Đọc thông báo flash trên trang được chuyển tới */
    public function follow(array $res)
    {
        return $this->get(($res['path'] ?? '/') . ($res['query'] !== '' ? '?' . $res['query'] : ''));
    }

    public function login($email, $password)
    {
        $this->get('/login');
        $res = $this->post('/login', ['email' => $email, 'password' => $password]);
        $this->get('/');

        return $res;
    }
}

/**
 * Dữ liệu dùng chung: user, suất chiếu (giờ đêm để không đụng suất thật), đơn đã thanh toán
 */
function makeUser(DBHelper $db, $email, $role, array $extra = [])
{
    $user = new App\Models\User();

    return (int)$db->insert('users', $extra + [
        'name'          => 'Test ' . ucfirst($role),
        'email'         => $email,
        'password'      => password_hash('123456', PASSWORD_BCRYPT),
        'roleId'        => $user->getRoleIdByName($role),
        'memberLevelId' => $role === 'customer' ? $user->getLevelIdByName('Member') : null,
    ]);
}

/**
 * Phòng đang hoạt động không có suất nào chồng giờ với các khoảng [start, start + minutes)
 * (tránh đụng suất chiếu thật admin đã tạo; ưu tiên rạp $preferSlug)
 */
function freeRoom(DBHelper $db, array $starts, $minutes = 120, $preferSlug = 'cinema-ha-noi-plaza')
{
    $rooms = $db->query(
        "SELECT r.id FROM room r JOIN cinema c ON c.id = r.cinemaId
         WHERE r.status = 'active' AND c.status = 'active'
           AND (SELECT COUNT(*) FROM seat s WHERE s.roomId = r.id AND s.status = 'active') >= 12
         ORDER BY c.slug = ? DESC, r.id",
        [$preferSlug]
    );
    foreach ($rooms as $room) {
        $busy = false;
        foreach ($starts as $start) {
            $busy = $busy || $db->query(
                "SELECT 1 FROM showtime WHERE roomId = ? AND status <> 'cancelled' AND startTime < ? AND endTime > ? LIMIT 1",
                [(int)$room['id'], date('Y-m-d H:i:s', $start + $minutes * 60), date('Y-m-d H:i:s', $start)],
                true
            );
        }
        if (!$busy) {
            return (int)$room['id'];
        }
    }
    throw new RuntimeException('Không có phòng trống cho suất test');
}

function makeShowtime(DBHelper $db, $roomId, $start, $minutes = 120, $price = 90000)
{
    $movieId = (int)$db->query("SELECT id FROM movie WHERE slug = 'phim-hanh-dong-mau'", [], true)['id'];

    return (int)$db->insert('showtime', [
        'movieId' => $movieId, 'roomId' => (int)$roomId, 'startTime' => date('Y-m-d H:i:s', $start),
        'endTime' => date('Y-m-d H:i:s', $start + $minutes * 60), 'format' => '2D', 'language' => 'sub', 'basePrice' => $price,
    ]);
}

/**
 * Đơn qua đúng luồng thật: giữ ghế (Booking::hold) rồi thanh toán (markPaid) -> cộng điểm, chi tiêu như thật
 * @return array booking row
 */
function makePaidBooking(DBHelper $db, $userId, $showtimeId, array $seatIds, array $products = [], $points = 0, $method = 'sepay')
{
    $bookingModel = new App\Models\Booking();
    $user = (new App\Models\User())->getUserById($userId);
    $showtime = (new App\Models\Showtime())->getDetail($showtimeId);
    $held = $bookingModel->hold($user, $showtime, $seatIds, $products, '', $points);
    $paymentId = (new App\Models\Payment())->createPending($held['id'], $method, $db->query('SELECT totalAmount FROM booking WHERE id = ?', [$held['id']], true)['totalAmount']);
    $bookingModel->markPaid($paymentId, 'TEST' . $paymentId, ['test' => true]);

    return $db->query('SELECT * FROM booking WHERE id = ?', [$held['id']], true);
}

/**
 * Xóa toàn bộ dữ liệu của các user test (đơn, điểm, đơn quầy) và các suất chiếu test
 */
function cleanupTestData(DBHelper $db, $emailLike, array $showtimeIds)
{
    $users = array_map('intval', array_column($db->query('SELECT id FROM users WHERE email LIKE ?', [$emailLike]), 'id'));
    if ($users) {
        $in = implode(',', $users);
        $bookings = array_map('intval', array_column($db->query("SELECT id FROM booking WHERE userId IN ($in)"), 'id'));
        if ($bookings) {
            $bin = implode(',', $bookings);
            $db->execute("DELETE FROM point_history WHERE bookingId IN ($bin)");
            $db->execute("DELETE FROM review WHERE userId IN ($in)");
            $db->execute("DELETE FROM booking WHERE id IN ($bin)");
        }
        $db->execute("DELETE FROM pos_order WHERE staffId IN ($in) OR customerId IN ($in)");
        $db->execute("DELETE FROM point_history WHERE userId IN ($in)");
        $db->execute("UPDATE booking SET checkedInBy = NULL WHERE checkedInBy IN ($in)");
        $db->execute("DELETE FROM users WHERE id IN ($in)");
    }
    $showtimeIds = array_filter(array_map('intval', $showtimeIds));
    if ($showtimeIds) {
        $sin = implode(',', $showtimeIds);
        $db->execute("DELETE FROM booking WHERE showtimeId IN ($sin)");
        $db->execute("DELETE FROM showtime WHERE id IN ($sin)");
    }
}
