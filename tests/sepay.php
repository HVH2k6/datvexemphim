<?php
/**
 * Test thanh toán SePay qua HTTP thật: php tests/sepay.php
 * Tự bật PHP built-in server với API key giả, giả lập webhook SePay, dọn sạch dữ liệu test.
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

const PORT = 18769;
const BASE_URL = 'http://127.0.0.1:' . PORT;
const NULL_DEVICE = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
const TEST_DOMAIN = '@sepay.test';
const TEST_API_KEY = 'sepay-test-api-key-0123456789';
const TEST_ACCOUNT = '10005486961';
const SEPAY_ID_BASE = 990000000;

$passed = 0;
$failed = 0;
function check($label, $ok)
{
    global $passed, $failed;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

class Client
{
    public $cookies = [];
    private $csrf = null;

    public function request($method, $path, $body = null, array $extraHeaders = [], $ajax = false)
    {
        $headers = [$ajax ? 'Accept: application/json' : 'Accept: text/html'];
        if ($ajax) {
            $headers[] = 'X-Requested-With: XMLHttpRequest';
            if ($this->csrf) {
                $headers[] = 'X-CSRF-Token: ' . $this->csrf;
            }
        }
        if ($this->cookies) {
            $headers[] = 'Cookie: ' . implode('; ', array_map(function ($k, $v) {
                return "{$k}={$v}";
            }, array_keys($this->cookies), $this->cookies));
        }
        $headers = array_merge($headers, $extraHeaders);

        $options = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15];
        if ($body !== null) {
            if (is_array($body)) {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                $body = http_build_query($body);
            }
            $options['content'] = $body;
        }
        $options['header'] = implode("\r\n", $headers);

        $raw = (string)file_get_contents(BASE_URL . $path, false, stream_context_create(['http' => $options]));
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
                    $this->cookies[$c[1]] = $c[2];
                }
            }
        }
        if (preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $raw, $m)) {
            $this->csrf = $m[1];
        }

        return [
            'status' => $status,
            'path'   => $location ? (parse_url($location, PHP_URL_PATH) ?: '/') : null,
            'body'   => $raw,
            'json'   => json_decode($raw, true),
        ];
    }

    public function get($path)
    {
        return $this->request('GET', $path);
    }

    public function post($path, array $data)
    {
        return $this->request('POST', $path, $data + ['csrf_token' => $this->csrf]);
    }

    public function ajax($method, $path, $data = null)
    {
        return $this->request($method, $path, $data, [], true);
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
 * Giả lập SePay gọi webhook
 */
function webhook(Client $c, array $tx, $apiKey = TEST_API_KEY)
{
    $headers = ['Content-Type: application/json'];
    if ($apiKey !== null) {
        $headers[] = 'Authorization: Apikey ' . $apiKey;
    }
    return $c->request('POST', '/payment/sepay-webhook', json_encode($tx), $headers);
}

$sepaySeq = SEPAY_ID_BASE;
function tx(array $overrides = [])
{
    global $sepaySeq;
    return $overrides + [
        'id' => ++$sepaySeq, 'gateway' => 'TPBank', 'transactionDate' => date('Y-m-d H:i:s'),
        'accountNumber' => TEST_ACCOUNT, 'code' => null, 'content' => '', 'transferType' => 'in',
        'transferAmount' => 0, 'accumulated' => 1000000, 'subAccount' => null,
        'referenceCode' => 'FT' . $sepaySeq, 'description' => '',
    ];
}

/**
 * Nội dung chuyển khoản của payment SePay đang chờ của đơn
 */
function transferCodeOf($db, $bookingId)
{
    $row = $db->query("SELECT transferCode FROM payment WHERE bookingId = ? AND method = 'sepay' ORDER BY id DESC LIMIT 1", [(int)$bookingId], true);
    return $row["transferCode"] ?? null;
}

$db = new DBHelper();
$ids = ['showtimes' => []];
$cleanup = function () use ($db, &$ids) {
    $db->execute('DELETE FROM sepay_transaction WHERE sepayId > ?', [SEPAY_ID_BASE]);
    $users = array_column($db->query('SELECT id FROM users WHERE email LIKE ?', ['%' . TEST_DOMAIN]), 'id');
    if ($users) {
        $in = implode(',', array_map('intval', $users));
        $bookings = array_column($db->query("SELECT id FROM booking WHERE userId IN ({$in})"), 'id');
        if ($bookings) {
            $bin = implode(',', array_map('intval', $bookings));
            $db->execute("DELETE FROM sepay_transaction WHERE bookingId IN ({$bin})");
            $db->execute("DELETE FROM point_history WHERE bookingId IN ({$bin})");
            $db->execute("DELETE FROM booking WHERE id IN ({$bin})");
        }
        $db->execute("DELETE FROM point_history WHERE userId IN ({$in})");
        $db->execute("DELETE FROM users WHERE id IN ({$in})");
    }
    if ($ids['showtimes']) {
        $db->execute('DELETE FROM showtime WHERE id IN (' . implode(',', array_map('intval', $ids['showtimes'])) . ')');
    }
};
$cleanup();

$env = array_merge(getenv(), [
    'APP_URL' => BASE_URL, 'APP_DEBUG' => 'false',
    'SEPAY_BANK' => 'TPBank', 'SEPAY_ACCOUNT_NUMBER' => TEST_ACCOUNT, 'SEPAY_ACCOUNT_NAME' => 'HA VAN HUNG',
    'SEPAY_API_KEY' => TEST_API_KEY,
]);
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

    $movieId = (int)$db->query("SELECT id FROM movie WHERE slug = 'phim-hanh-dong-mau'", [], true)['id'];
    $roomId = (int)$db->query("SELECT r.id FROM room r JOIN cinema c ON c.id = r.cinemaId WHERE c.slug = 'cinema-ha-noi-plaza' LIMIT 1", [], true)['id'];
    $day = date('Y-m-d', strtotime('+3 day'));
    $showtimeId = (int)$db->insert('showtime', [
        'movieId' => $movieId, 'roomId' => $roomId, 'startTime' => "{$day} 02:00:00",
        'endTime' => "{$day} 04:20:00", 'format' => '2D', 'language' => 'sub', 'basePrice' => 70000,
    ]);
    $ids['showtimes'][] = $showtimeId;
    $seats = array_column($db->query("SELECT id FROM seat WHERE roomId = ? AND status = 'active' ORDER BY id LIMIT 10", [$roomId]), 'id');

    $userModel = new \App\Models\User();
    $customerId = (int)$db->insert('users', [
        'name' => 'Khách SePay', 'email' => 'khach' . TEST_DOMAIN, 'password' => password_hash('123456', PASSWORD_BCRYPT),
        'roleId' => $userModel->getRoleIdByName('customer'), 'memberLevelId' => $userModel->getLevelIdByName('Member'),
    ]);

    $client = new Client();
    $client->login('khach' . TEST_DOMAIN, '123456');
    $sepayServer = new Client();

    $hold = function ($seatIndex) use ($client, $showtimeId, $seats, $db) {
        $res = $client->ajax('POST', '/booking/save-seats', ['showtimeId' => $showtimeId, 'seatIds' => [$seats[$seatIndex]]]);
        $id = $res['json']['data']['bookingId'] ?? null;
        return $id ? $db->query('SELECT * FROM booking WHERE id = ?', [$id], true) : null;
    };

    // ---------- Chọn chuyển khoản ----------
    $booking = $hold(0);
    check('giữ ghế thành công', $booking !== null);
    $checkout = $client->get("/checkout/{$booking['id']}");
    check('trang thanh toán có lựa chọn chuyển khoản (mặc định)', (bool)preg_match('/value="sepay"\s+checked/', $checkout['body']));

    $res = $client->post("/checkout/{$booking['id']}/pay", ['method' => 'sepay']);
    check('chọn chuyển khoản -> trang QR', $res['path'] === "/checkout/{$booking['id']}/transfer");
    $payment = $db->query("SELECT * FROM payment WHERE bookingId = ? AND method = 'sepay'", [$booking['id']], true);
    check('tạo payment sepay pending đúng số tiền', $payment && $payment['status'] === 'pending' && (int)$payment['amount'] === (int)$booking['totalAmount']);

    $client->post("/checkout/{$booking['id']}/pay", ['method' => 'sepay']);
    check('chọn lại chuyển khoản không tạo payment trùng',
        (int)$db->query("SELECT COUNT(*) c FROM payment WHERE bookingId = ? AND method = 'sepay'", [$booking['id']], true)['c'] === 1);

    $tc = transferCodeOf($db, $booking['id']);
    check('nội dung CK dạng BK + 6-8 ký tự, khác mã vé', (bool)preg_match('/^BK[A-Z0-9]{6,8}$/', (string)$tc) && $tc !== $booking['code']);
    $page = $client->get("/checkout/{$booking['id']}/transfer");
    check('trang QR: ảnh VietQR đúng tài khoản/số tiền/nội dung', $page['status'] === 200
        && str_contains($page['body'], 'https://qr.sepay.vn/img?acc=' . TEST_ACCOUNT . '&amp;bank=TPBank&amp;amount=' . (int)$booking['totalAmount'] . '&amp;des=' . $tc));
    check('trang QR: hiện chủ tài khoản + nội dung CK', str_contains($page['body'], 'HA VAN HUNG') && str_contains($page['body'], $tc));

    $status = $client->ajax('GET', "/checkout/{$booking['id']}/status");
    check('API trạng thái: pending', ($status['json']['data']['status'] ?? '') === 'pending');
    check('người khác không xem được trang QR (404)', (function () use ($booking, $db, $userModel) {
        $other = new Client();
        $db->insert('users', [
            'name' => 'Người khác', 'email' => 'other' . TEST_DOMAIN, 'password' => password_hash('123456', PASSWORD_BCRYPT),
            'roleId' => $userModel->getRoleIdByName('customer'),
        ]);
        $other->login('other' . TEST_DOMAIN, '123456');
        return $other->get("/checkout/{$booking['id']}/transfer")['status'] === 404
            && $other->ajax('GET', "/checkout/{$booking['id']}/status")['status'] === 404;
    })());

    // ---------- Xác thực webhook ----------
    $good = tx(['content' => 'HA VAN HUNG chuyen tien ' . $tc . ' FT123', 'transferAmount' => (int)$booking['totalAmount']]);
    check('webhook thiếu API key -> 401', webhook($sepayServer, $good, null)['status'] === 401);
    check('webhook sai API key -> 401', webhook($sepayServer, $good, 'wrong-key')['status'] === 401);
    check('webhook header chữ thường vẫn nhận', (function () use ($sepayServer) {
        $r = $sepayServer->request('POST', '/payment/sepay-webhook', '{}', ['Content-Type: application/json', 'authorization: apikey ' . TEST_API_KEY]);
        return $r['status'] === 400;   // qua xác thực, lỗi do payload rỗng
    })());
    check('webhook JSON lỗi -> 400', webhook($sepayServer, [])['status'] === 400);
    check('chưa ghi nhận thanh toán khi xác thực sai', $db->query('SELECT status FROM booking WHERE id = ?', [$booking['id']], true)['status'] === 'pending');

    // ---------- Không khớp ----------
    $res = webhook($sepayServer, tx(['content' => $tc, 'transferAmount' => (int)$booking['totalAmount'], 'accountNumber' => '999999']));
    check('sai tài khoản nhận: success nhưng không ghi nhận', ($res['json']['success'] ?? null) === true && ($res['json']['message'] ?? '') === 'wrong_account'
        && $db->query('SELECT status FROM booking WHERE id = ?', [$booking['id']], true)['status'] === 'pending');
    $res = webhook($sepayServer, tx(['content' => $tc, 'transferAmount' => (int)$booking['totalAmount'], 'transferType' => 'out']));
    check('tiền ra bị bỏ qua', ($res['json']['message'] ?? '') === 'ignored_out');
    $res = webhook($sepayServer, tx(['content' => 'chuyen tien an trua', 'transferAmount' => 50000]));
    check('nội dung không có mã vé: no_match', ($res['json']['message'] ?? '') === 'no_match');
    $res = webhook($sepayServer, tx(['content' => $tc, 'transferAmount' => (int)$booking['totalAmount'] - 1000]));
    check('thiếu tiền: amount_mismatch, đơn vẫn pending', ($res['json']['message'] ?? '') === 'amount_mismatch'
        && $db->query('SELECT status FROM booking WHERE id = ?', [$booking['id']], true)['status'] === 'pending');
    check('giao dịch không khớp vẫn được lưu để đối soát',
        (int)$db->query('SELECT COUNT(*) c FROM sepay_transaction WHERE sepayId > ?', [SEPAY_ID_BASE], true)['c'] === 4);

    // ---------- Khớp ----------
    $res = webhook($sepayServer, $good);
    $booking1 = $db->query('SELECT * FROM booking WHERE id = ?', [$booking['id']], true);
    $payment = $db->query('SELECT * FROM payment WHERE id = ?', [$payment['id']], true);
    check('webhook hợp lệ: HTTP 200 + success true', $res['status'] === 200 && ($res['json']['success'] ?? null) === true && ($res['json']['message'] ?? '') === 'paid');
    check('booking paid, payment success + mã tham chiếu', $booking1['status'] === 'paid' && $payment['status'] === 'success'
        && $payment['transactionCode'] === 'SEPAY' . $good['id'] . '-' . $good['referenceCode'] && $payment['paidAt']);
    check('cộng điểm + chi tiêu (dùng lại logic markPaid)',
        (int)$db->query('SELECT totalSpending FROM users WHERE id = ?', [$customerId], true)['totalSpending'] === (int)$booking1['totalAmount']);
    check('giao dịch lưu kèm booking + payment', ($t = $db->query('SELECT * FROM sepay_transaction WHERE sepayId = ?', [$good['id']], true))
        && (int)$t['bookingId'] === (int)$booking['id'] && (int)$t['paymentId'] === (int)$payment['id'] && $t['result'] === 'paid');

    $status = $client->ajax('GET', "/checkout/{$booking['id']}/status");
    check('API trạng thái: paid + link vé', ($status['json']['data']['status'] ?? '') === 'paid'
        && str_ends_with($status['json']['data']['redirectUrl'] ?? '', "/booking/{$booking['id']}/confirmation"));
    check('trang QR sau khi trả -> trang vé', $client->get("/checkout/{$booking['id']}/transfer")['path'] === "/booking/{$booking['id']}/confirmation");
    check('trang vé hiện "qua chuyển khoản"', str_contains($client->get("/booking/{$booking['id']}/confirmation")['body'], 'qua chuyển khoản'));

    $spending = (int)$db->query('SELECT totalSpending FROM users WHERE id = ?', [$customerId], true)['totalSpending'];
    $res = webhook($sepayServer, $good);
    check('SePay gửi lại cùng giao dịch: success, không xử lý lần 2', ($res['json']['success'] ?? null) === true
        && ($res['json']['message'] ?? '') === 'Duplicate transaction'
        && (int)$db->query('SELECT totalSpending FROM users WHERE id = ?', [$customerId], true)['totalSpending'] === $spending);
    $res = webhook($sepayServer, tx(['content' => $tc, 'transferAmount' => (int)$booking['totalAmount']]));
    check('chuyển thêm lần nữa cho đơn đã trả: already', ($res['json']['message'] ?? '') === 'already');

    // ---------- Chỉ khớp theo nội dung CK riêng ----------
    $bx = $hold(4);
    $client->post("/checkout/{$bx['id']}/pay", ['method' => 'sepay']);
    $res = webhook($sepayServer, tx(['content' => $bx['code'], 'transferAmount' => (int)$bx['totalAmount']]));
    check('ghi mã vé thay vì nội dung CK: không khớp', ($res['json']['message'] ?? '') === 'no_match'
        && $db->query('SELECT status FROM booking WHERE id = ?', [$bx['id']], true)['status'] === 'pending');
    $res = webhook($sepayServer, tx(['content' => 'MBVCB.123 BKLOGISTIC ' . transferCodeOf($db, $bx['id']) . ' GD 998877', 'transferAmount' => (int)$bx['totalAmount']]));
    check('nội dung CK lẫn chữ khác vẫn khớp đúng mã', ($res['json']['message'] ?? '') === 'paid');
    $codes = [];
    for ($i = 0; $i < 200; $i++) {
        $codes[] = \App\Services\SepayGateway::generateTransferCode();
    }
    $lengths = array_unique(array_map(function ($c) { return strlen($c) - 2; }, $codes));
    sort($lengths);
    check('mã sinh ngẫu nhiên đủ độ dài 6, 7, 8 và đúng định dạng', $lengths === [6, 7, 8]
        && count(preg_grep('/^BK[A-Z0-9]{6,8}$/', $codes)) === 200);

    // ---------- Mã vé nằm trong field code / chữ thường ----------
    $b2 = $hold(1);
    $client->post("/checkout/{$b2['id']}/pay", ['method' => 'sepay']);
    $res = webhook($sepayServer, tx(['code' => strtolower(transferCodeOf($db, $b2['id'])), 'content' => 'IBFT', 'transferAmount' => (int)$b2['totalAmount']]));
    check('nhận mã vé từ field code (không phân biệt hoa thường)', ($res['json']['message'] ?? '') === 'paid'
        && $db->query('SELECT status FROM booking WHERE id = ?', [$b2['id']], true)['status'] === 'paid');

    // ---------- Đổi phương thức rồi mới chuyển khoản ----------
    $b3 = $hold(2);
    $client->post("/checkout/{$b3['id']}/pay", ['method' => 'sepay']);
    $client->post("/checkout/{$b3['id']}/pay", ['method' => 'cash']);
    check('đổi sang tại quầy: payment sepay cũ bị hủy',
        $db->query("SELECT status FROM payment WHERE bookingId = ? AND method = 'sepay'", [$b3['id']], true)['status'] === 'failed');
    $res = webhook($sepayServer, tx(['content' => transferCodeOf($db, $b3['id']), 'transferAmount' => (int)$b3['totalAmount']]));
    check('vẫn chuyển khoản sau khi đổi: vẫn ghi nhận paid', ($res['json']['message'] ?? '') === 'paid'
        && $db->query('SELECT status FROM booking WHERE id = ?', [$b3['id']], true)['status'] === 'paid');

    // ---------- Tiền về sau khi hết hạn giữ ghế ----------
    $b4 = $hold(3);
    $client->post("/checkout/{$b4['id']}/pay", ['method' => 'sepay']);
    $db->execute('UPDATE booking SET expiredAt = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 5), $b4['id']]);
    $status = $client->ajax('GET', "/checkout/{$b4['id']}/status");
    check('hết hạn: API trạng thái expired', ($status['json']['data']['status'] ?? '') === 'expired');
    check('hết hạn: trang QR chuyển về thanh toán', $client->get("/checkout/{$b4['id']}/transfer")['path'] === "/checkout/{$b4['id']}");
    $res = webhook($sepayServer, tx(['content' => transferCodeOf($db, $b4['id']), 'transferAmount' => (int)$b4['totalAmount']]));
    check('tiền về muộn: late, đơn không paid, cần hoàn tiền', ($res['json']['message'] ?? '') === 'late'
        && $db->query('SELECT status FROM booking WHERE id = ?', [$b4['id']], true)['status'] === 'expired'
        && $db->query("SELECT status FROM payment WHERE bookingId = ? AND method = 'sepay'", [$b4['id']], true)['status'] === 'success');

    // ---------- Chưa cấu hình ----------
    check('route webhook không yêu cầu CSRF/session nhưng GET bị từ chối (405)', $sepayServer->get('/payment/sepay-webhook')['status'] === 405);
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    $cleanup();
}

check('đã dọn dữ liệu test', (int)$db->query('SELECT COUNT(*) c FROM users WHERE email LIKE ?', ['%' . TEST_DOMAIN], true)['c'] === 0
    && (int)$db->query('SELECT COUNT(*) c FROM sepay_transaction WHERE sepayId > ?', [SEPAY_ID_BASE], true)['c'] === 0);

echo PHP_EOL . "{$passed} passed, {$failed} failed" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
