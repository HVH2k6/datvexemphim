<?php
/**
 * Test Phase 3 (Frontend khách hàng + đặt vé) qua HTTP thật: php tests/phase3.php
 * Tự bật PHP built-in server, tạo suất chiếu / voucher / user test riêng rồi dọn sạch.
 * VNPay dùng khóa giả: test tự ký callback giống VNPay.
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

const PORT = 18768;
const BASE_URL = 'http://127.0.0.1:' . PORT;
const NULL_DEVICE = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
const TEST_DOMAIN = '@phase3.test';
const TEST_VNPAY_TMN = 'TESTTMN1';
const TEST_VNPAY_SECRET = 'phase3-test-secret-key';

putenv('VNPAY_TMN_CODE=' . TEST_VNPAY_TMN);
putenv('VNPAY_HASH_SECRET=' . TEST_VNPAY_SECRET);

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

    public function request($method, $path, $data = null, $ajax = false)
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

        $options = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15];
        if ($data !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options['content'] = http_build_query($data);
        }
        $options['header'] = implode("\r\n", $headers);

        $body = (string)file_get_contents(BASE_URL . $path, false, stream_context_create(['http' => $options]));
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
        if (preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $body, $m)) {
            $this->csrf = $m[1];
        }

        return [
            'status'   => $status,
            'location' => $location,
            'path'     => $location ? (parse_url($location, PHP_URL_PATH) ?: '/') : null,
            'body'     => $body,
            'json'     => json_decode($body, true),
        ];
    }

    public function get($path)
    {
        return $this->request('GET', $path);
    }

    public function post($path, $data)
    {
        return $this->request('POST', $path, $data + ['csrf_token' => $this->csrf]);
    }

    public function ajax($path, $data)
    {
        return $this->request('POST', $path, $data, true);
    }

    /**
     * Đăng nhập rồi tải trang trang chủ như trình duyệt, để nhận CSRF token mới
     * (token được tạo lại sau khi đăng nhập)
     */
    public function login($email, $password)
    {
        $this->get('/login');
        $res = $this->post('/login', ['email' => $email, 'password' => $password]);
        $this->get('/');

        return $res;
    }
}

/**
 * Ký dữ liệu trả về giống VNPay
 */
function vnpaySigned(array $params)
{
    ksort($params);
    $pairs = [];
    foreach ($params as $k => $v) {
        $pairs[] = urlencode($k) . '=' . urlencode($v);
    }
    $params['vnp_SecureHash'] = hash_hmac('sha512', implode('&', $pairs), TEST_VNPAY_SECRET);
    return $params;
}

$db = new DBHelper();
$ids = ['showtimes' => [], 'vouchers' => []];

$cleanup = function () use ($db, &$ids) {
    $testUsers = array_column($db->query('SELECT id FROM users WHERE email LIKE ?', ['%' . TEST_DOMAIN]), 'id');
    $showtimeIds = $ids['showtimes'];
    $bookingIds = [];
    if ($testUsers) {
        $in = implode(',', array_map('intval', $testUsers));
        $bookingIds = array_merge($bookingIds, array_column($db->query("SELECT id FROM booking WHERE userId IN ({$in})"), 'id'));
    }
    if ($showtimeIds) {
        $in = implode(',', array_map('intval', $showtimeIds));
        $bookingIds = array_merge($bookingIds, array_column($db->query("SELECT id FROM booking WHERE showtimeId IN ({$in})"), 'id'));
    }
    if ($bookingIds) {
        $in = implode(',', array_map('intval', array_unique($bookingIds)));
        $db->execute("DELETE FROM point_history WHERE bookingId IN ({$in})");
        $db->execute("DELETE FROM booking WHERE id IN ({$in})");     // cascade: booking_seat, booking_product, payment
    }
    if ($testUsers) {
        $in = implode(',', array_map('intval', $testUsers));
        $db->execute("DELETE FROM point_history WHERE userId IN ({$in})");
        $db->execute("DELETE FROM users WHERE id IN ({$in})");
    }
    if ($showtimeIds) {
        $db->execute('DELETE FROM showtime WHERE id IN (' . implode(',', array_map('intval', $showtimeIds)) . ')');
    }
    if ($ids['vouchers']) {
        $db->execute('DELETE FROM voucher WHERE id IN (' . implode(',', array_map('intval', $ids['vouchers'])) . ')');
    }
    $db->execute("DELETE FROM voucher WHERE code LIKE 'P3TEST%'");
};

$env = array_merge(getenv(), [
    'APP_URL' => BASE_URL, 'APP_DEBUG' => 'false',
    'VNPAY_TMN_CODE' => TEST_VNPAY_TMN, 'VNPAY_HASH_SECRET' => TEST_VNPAY_SECRET,
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

$seedSnapshot = function () use ($db) {
    return [
        $db->query("SELECT id, points, totalSpending, memberLevelId FROM users WHERE email NOT LIKE ? ORDER BY id", ['%' . TEST_DOMAIN]),
        $db->query("SELECT id, usedCount FROM voucher WHERE code NOT LIKE 'P3TEST%' ORDER BY id"),
        $db->query('SELECT id, status FROM booking WHERE id = 1'),
    ];
};

try {
    check('server khởi động', $ready);
    if (!$ready) {
        throw new RuntimeException('Server không khởi động');
    }
    $seedBefore = $seedSnapshot();

    // ---------- Dữ liệu test ----------
    $movieId = (int)$db->query("SELECT id FROM movie WHERE slug = 'phim-hanh-dong-mau'", [], true)['id'];
    $roomId = (int)$db->query("SELECT r.id FROM room r JOIN cinema c ON c.id = r.cinemaId WHERE c.slug = 'cinema-ha-noi-plaza' LIMIT 1", [], true)['id'];
    $tomorrow = date('Y-m-d', strtotime('+2 day'));
    // Giờ lẻ ban đêm để không trùng suất chiếu có sẵn (trigger chặn trùng giờ)
    $mkShowtime = function ($start, $price) use ($db, $movieId, $roomId, &$ids) {
        $id = $db->insert('showtime', [
            'movieId' => $movieId, 'roomId' => $roomId, 'startTime' => $start,
            'endTime' => date('Y-m-d H:i:s', strtotime($start) + 140 * 60),
            'format' => '2D', 'language' => 'sub', 'basePrice' => $price,
        ]);
        $ids['showtimes'][] = (int)$id;
        return (int)$id;
    };
    $st1 = $mkShowtime("{$tomorrow} 01:00:00", 100000);
    $st2 = $mkShowtime("{$tomorrow} 04:00:00", 80000);

    $mkVoucher = function ($data) use ($db, &$ids) {
        $id = $db->insert('voucher', $data + [
            'maxDiscount' => null, 'minOrder' => 0, 'quantity' => 100, 'usedCount' => 0, 'perUserLimit' => 1,
            'startDate' => date('Y-m-d H:i:s', time() - 3600), 'endDate' => date('Y-m-d H:i:s', time() + 86400), 'status' => 'active',
        ]);
        $ids['vouchers'][] = (int)$id;
        return (int)$id;
    };
    $vPercent = $mkVoucher(['code' => 'P3TESTPCT', 'name' => 'Test 10%', 'discountType' => 'percent', 'discountValue' => 10, 'maxDiscount' => 15000]);
    $vMin = $mkVoucher(['code' => 'P3TESTMIN', 'name' => 'Test min', 'discountType' => 'fixed', 'discountValue' => 20000, 'minOrder' => 5000000]);
    $vExpired = $mkVoucher(['code' => 'P3TESTOLD', 'name' => 'Test old', 'discountType' => 'fixed', 'discountValue' => 20000,
        'endDate' => date('Y-m-d H:i:s', time() - 60)]);
    $vFull = $mkVoucher(['code' => 'P3TESTFULL', 'name' => 'Test full', 'discountType' => 'fixed', 'discountValue' => 20000, 'quantity' => 1, 'usedCount' => 1]);

    $roleCustomer = (new \App\Models\User())->getRoleIdByName('customer');
    $memberLevel = (new \App\Models\User())->getLevelIdByName('Member');
    $vipLevel = (new \App\Models\User())->getLevelIdByName('VIP');
    $mkUser = function ($prefix, $points, $levelId) use ($db, $roleCustomer) {
        return (int)$db->insert('users', [
            'name' => 'Khách ' . $prefix, 'email' => $prefix . TEST_DOMAIN, 'password' => password_hash('123456', PASSWORD_BCRYPT),
            'roleId' => $roleCustomer, 'memberLevelId' => $levelId, 'points' => $points,
        ]);
    };
    $aliceId = $mkUser('alice', 50, $memberLevel);
    $bobId = $mkUser('bob', 0, $vipLevel);

    // Ghế theo loại trong phòng test
    $seatsByType = [];
    foreach ($db->query(
        'SELECT se.id, st.code, st.extraPrice FROM seat se JOIN seat_type st ON st.id = se.seatTypeId
         WHERE se.roomId = ? AND se.status = ? ORDER BY se.rowLabel, se.seatNumber',
        [$roomId, 'active']
    ) as $seat) {
        $seatsByType[$seat['code']][] = $seat;
    }
    $std = $seatsByType['standard'];
    $vip = $seatsByType['vip'];
    $productId = (int)$db->query("SELECT id, price FROM product WHERE status = 'active' ORDER BY id LIMIT 1", [], true)['id'];
    $productPrice = (int)$db->query('SELECT price FROM product WHERE id = ?', [$productId], true)['price'];

    // ---------- Trang công khai ----------
    $guest = new Client();
    $home = $guest->get('/');
    check('trang chủ 200, có phim đang chiếu + sắp chiếu', $home['status'] === 200
        && str_contains($home['body'], 'Phim Hành Động Mẫu') && str_contains($home['body'], 'Phim Kinh Dị Sắp Chiếu'));
    check('poster thiếu ảnh hiện khung màu + tên phim', str_contains($home['body'], 'class="poster-title"') && !str_contains($home['body'], '/uploads/movies/p1.jpg'));

    $list = $guest->get('/movies');
    check('/movies mặc định phim đang chiếu', $list['status'] === 200
        && str_contains($list['body'], 'Phim Hoạt Hình Mẫu') && !str_contains($list['body'], 'Phim Kinh Dị Sắp Chiếu'));
    $genreHorror = (int)$db->query("SELECT id FROM genre WHERE slug = 'kinh-di'", [], true)['id'];
    $filtered = $guest->get('/movies?status=coming_soon&genre=' . $genreHorror);
    check('lọc sắp chiếu + thể loại', str_contains($filtered['body'], 'Phim Kinh Dị Sắp Chiếu') && !str_contains($filtered['body'], 'Phim Hoạt Hình Mẫu'));
    check('status không hợp lệ quay về đang chiếu', str_contains($guest->get('/movies?status=abc')['body'], 'Phim Hoạt Hình Mẫu'));
    check('trang vượt quá trả 404', $guest->get('/movies?page=99')['status'] === 404);
    check('XSS qua query bị escape', !str_contains($guest->get('/movies?genre=<script>')['body'], '<script>alert'));

    $detail = $guest->get('/movies/phim-hanh-dong-mau');
    check('chi tiết phim 200 + thể loại + đánh giá', $detail['status'] === 200
        && str_contains($detail['body'], 'Hành động') && str_contains($detail['body'], 'Phim hay!'));
    check('chi tiết phim có khung lịch chiếu 7 ngày', substr_count($detail['body'], 'class="date-btn') === 7);
    check('phim không tồn tại 404', $guest->get('/movies/khong-ton-tai')['status'] === 404);
    check('phim sắp chiếu chưa mở bán', str_contains($guest->get('/movies/phim-kinh-di-sap-chieu')['body'], 'chưa mở bán'));

    // ---------- API ----------
    $api = $guest->request('GET', "/api/showtimes?movieId={$movieId}&date={$tomorrow}", null, true);
    $found = [];
    foreach ($api['json']['data'] ?? [] as $group) {
        foreach ($group['showtimes'] as $st) {
            $found[$st['id']] = $st;
        }
    }
    check('API suất chiếu trả suất test, nhóm theo rạp', isset($found[$st1], $found[$st2]) && $found[$st1]['time'] === '01:00');
    check('API suất chiếu có số ghế trống', ($found[$st1]['availableSeats'] ?? 0) === count($std) + count($vip) + count($seatsByType['sweetbox'] ?? []));
    check('API lọc theo thành phố khác không có suất', (function () use ($guest, $movieId, $tomorrow, $st1) {
        $vinh = (new DBHelper())->query("SELECT id FROM city WHERE name = 'Nghệ An'", [], true)['id'];
        $r = $guest->request('GET', "/api/showtimes?movieId={$movieId}&date={$tomorrow}&cityId={$vinh}", null, true);
        foreach ($r['json']['data'] ?? [] as $g) {
            foreach ($g['showtimes'] as $s) {
                if ($s['id'] === $st1) {
                    return false;
                }
            }
        }
        return $r['status'] === 200;
    })());
    check('API ngày ngoài 7 ngày bị từ chối (422)',
        $guest->request('GET', "/api/showtimes?movieId={$movieId}&date=" . date('Y-m-d', strtotime('+30 day')), null, true)['status'] === 422);
    check('API ngày sai định dạng bị từ chối (422)',
        $guest->request('GET', "/api/showtimes?movieId={$movieId}&date=2026-13-45", null, true)['status'] === 422);
    $cinemas = $guest->request('GET', '/api/cinemas?cityId=1', null, true);
    check('API rạp theo thành phố', $cinemas['status'] === 200 && ($cinemas['json']['data'][0]['name'] ?? '') === 'Cinema Hà Nội Plaza');
    $seatApi = $guest->request('GET', "/api/seats/{$st1}", null, true);
    check('API ghế có giá = base + phụ thu loại ghế', ($seatApi['json']['data'][0]['price'] ?? 0) === 100000 + (int)$std[0]['extraPrice']);

    // ---------- Yêu cầu đăng nhập ----------
    $res = $guest->get("/booking/showtime/{$st1}");
    check('khách chưa đăng nhập vào chọn ghế -> /login', $res['status'] === 302 && $res['path'] === '/login');
    check('AJAX giữ ghế khi chưa đăng nhập -> 401', $guest->ajax('/booking/save-seats', ['showtimeId' => $st1, 'seatIds' => [$std[0]['id']]])['status'] === 401);

    $alice = new Client();
    $alice->get("/booking/showtime/{$st1}");     // lưu intended path
    $res = $alice->login('alice' . TEST_DOMAIN, '123456');
    check('đăng nhập xong quay lại trang chọn ghế', $res['status'] === 302 && $res['path'] === "/booking/showtime/{$st1}");

    // ---------- Trang chọn ghế ----------
    $seatsPage = $alice->get("/booking/showtime/{$st1}");
    check('trang chọn ghế 200 + sơ đồ ghế + bắp nước', $seatsPage['status'] === 200
        && str_contains($seatsPage['body'], 'data-seat-id="' . $std[0]['id'] . '"') && str_contains($seatsPage['body'], 'Combo 1 người'));
    $pastId = $mkShowtime(date('Y-m-d', strtotime('-3 day')) . ' 03:00:00', 90000);
    check('suất đã chiếu không cho đặt', $alice->get("/booking/showtime/{$pastId}")['path'] === '/movies');
    check('staff không vào được trang chọn ghế (403)', (function () use ($db) {
        $staff = new Client();
        $staff->login('staff@cinema.vn', '123456');
        return $staff->get('/booking/showtime/4')['status'] === 403;
    })());

    // ---------- Báo giá / voucher / điểm ----------
    $base = ['showtimeId' => $st1, 'seatIds' => [$std[0]['id'], $std[1]['id']], 'products' => [$productId => 1]];
    $ticket = 2 * (100000 + (int)$std[0]['extraPrice']);
    check('báo giá thiếu CSRF bị chặn (403)', $alice->request('POST', '/api/validate-voucher', $base, false)['status'] === 403);
    $q = $alice->ajax('/api/validate-voucher', $base)['json']['data'] ?? [];
    check('báo giá: tiền ghế + bắp nước tính ở server', ($q['ticketAmount'] ?? 0) === $ticket && ($q['productAmount'] ?? 0) === $productPrice
        && ($q['totalAmount'] ?? 0) === $ticket + $productPrice);
    $q = $alice->ajax('/api/validate-voucher', $base + ['voucherCode' => 'p3testpct'])['json']['data'] ?? [];
    check('voucher % có trần giảm (không phân biệt hoa thường)', ($q['voucherDiscount'] ?? 0) === min(15000, (int)floor(($ticket + $productPrice) / 10)));
    foreach (['P3TESTMIN' => 'tối thiểu', 'P3TESTOLD' => 'hết hạn', 'P3TESTFULL' => 'hết lượt', 'KHONGCO' => 'hết hạn'] as $code => $word) {
        $r = $alice->ajax('/api/validate-voucher', $base + ['voucherCode' => $code]);
        check("voucher {$code} bị từ chối", $r['status'] === 422 && ($r['json']['field'] ?? '') === 'voucher' && str_contains($r['json']['message'] ?? '', $word));
    }
    $q = $alice->ajax('/api/apply-points', $base + ['points' => 20])['json']['data'] ?? [];
    check('dùng 20 điểm = giảm 20.000đ', ($q['pointsDiscount'] ?? 0) === 20000);
    $r = $alice->ajax('/api/apply-points', $base + ['points' => 51]);
    check('dùng quá số điểm đang có bị từ chối', $r['status'] === 422 && ($r['json']['field'] ?? '') === 'points');
    $r = $alice->ajax('/api/apply-points', $base + ['points' => -5]);
    check('số điểm âm bị từ chối', $r['status'] === 422);
    $r = $alice->ajax('/api/validate-voucher', ['showtimeId' => $st1, 'seatIds' => array_column(array_slice($std, 0, 9), 'id')]);
    check('chọn quá 8 ghế bị từ chối', $r['status'] === 422 && ($r['json']['field'] ?? '') === 'seats');
    $r = $alice->ajax('/api/validate-voucher', ['showtimeId' => $st1, 'seatIds' => [999999]]);
    check('ghế không thuộc phòng bị từ chối', $r['status'] === 422);
    $r = $alice->ajax('/api/validate-voucher', ['showtimeId' => $st1, 'seatIds' => [$std[0]['id']], 'products' => [$productId => 11]]);
    check('quá 10 phần 1 món bị từ chối', $r['status'] === 422 && ($r['json']['field'] ?? '') === 'products');

    $bob = new Client();
    $bob->login('bob' . TEST_DOMAIN, '123456');
    $q = $bob->ajax('/api/validate-voucher', ['showtimeId' => $st1, 'seatIds' => [$vip[0]['id']]])['json']['data'] ?? [];
    $vipPrice = 100000 + (int)$vip[0]['extraPrice'];
    check("hạng VIP được giảm 5%", ($q['memberDiscount'] ?? 0) === (int)floor($vipPrice * 5 / 100));

    // ---------- Giữ ghế ----------
    $res = $alice->ajax('/booking/save-seats', $base + ['voucherCode' => 'P3TESTPCT', 'points' => 20]);
    $booking1 = $res['json']['data']['bookingId'] ?? null;
    check('giữ ghế thành công, trả URL thanh toán', $res['status'] === 200 && $booking1
        && str_ends_with($res['json']['data']['redirectUrl'], "/checkout/{$booking1}"));
    $b1 = $db->query('SELECT * FROM booking WHERE id = ?', [$booking1], true);
    $expectedVoucher = min(15000, (int)floor(($ticket + $productPrice) / 10));
    check('booking pending đúng số tiền', $b1 && $b1['status'] === 'pending'
        && (int)$b1['totalAmount'] === $ticket + $productPrice - $expectedVoucher - 20000
        && (int)$b1['pointsUsed'] === 20 && (int)$b1['voucherId'] === $vPercent);
    check('giữ ghế 10 phút', abs(strtotime($b1['expiredAt']) - time() - 600) < 30);
    check('booking_seat + booking_product được tạo', (int)$db->query('SELECT COUNT(*) c FROM booking_seat WHERE bookingId = ?', [$booking1], true)['c'] === 2
        && (int)$db->query('SELECT quantity FROM booking_product WHERE bookingId = ?', [$booking1], true)['quantity'] === 1);
    check('mã vé dạng BK + 8 ký tự', (bool)preg_match('/^BK[A-Z2-9]{8}$/', $b1['code']));

    $r = $bob->ajax('/booking/save-seats', ['showtimeId' => $st1, 'seatIds' => [$std[0]['id']]]);
    check("người khác không giữ được ghế đã giữ", $r['status'] === 422 && ($r['json']['field'] ?? '') === 'seats');
    $bobSeats = $bob->get("/booking/showtime/{$st1}")['body'];
    check('ghế đang giữ hiển thị đã đặt với người khác', (bool)preg_match('/data-seat-id="' . $std[0]['id'] . '"[^>]*disabled/s', $bobSeats));
    check('chủ đơn vẫn chọn lại được ghế của mình', !preg_match('/data-seat-id="' . $std[0]['id'] . '"[^>]*disabled/s', $alice->get("/booking/showtime/{$st1}")['body']));

    // Chọn lại ghế: đơn cũ bị thay thế
    $res = $alice->ajax('/booking/save-seats', ['showtimeId' => $st1, 'seatIds' => [$std[2]['id']], 'voucherCode' => 'P3TESTPCT']);
    $booking2 = $res['json']['data']['bookingId'] ?? null;
    check('chọn lại ghế tạo đơn mới', $res['status'] === 200 && $booking2 && $booking2 !== $booking1);
    check('đơn cũ bị hủy + nhả ghế', $db->query('SELECT status FROM booking WHERE id = ?', [$booking1], true)['status'] === 'cancelled'
        && (int)$db->query('SELECT COUNT(*) c FROM booking_seat WHERE bookingId = ?', [$booking1], true)['c'] === 0);
    check('ghế cũ đã trống cho người khác', $bob->ajax('/api/validate-voucher', ['showtimeId' => $st1, 'seatIds' => [$std[0]['id']]])['status'] === 200);
    check('trang thanh toán đơn cũ báo đã hủy', str_contains($alice->get("/checkout/{$booking1}")['body'], 'thay thế bằng lần chọn ghế mới'));

    // Đua ghế: 2 người cùng giữ 1 ghế
    $r1 = $bob->ajax('/booking/save-seats', ['showtimeId' => $st2, 'seatIds' => [$std[5]['id']]]);
    $r2 = $alice->ajax('/booking/save-seats', ['showtimeId' => $st2, 'seatIds' => [$std[5]['id']]]);
    check('chỉ 1 người giữ được ghế', $r1['status'] === 200 && $r2['status'] === 422
        && (int)$db->query('SELECT COUNT(*) c FROM booking_seat WHERE showtimeId = ? AND seatId = ?', [$st2, $std[5]['id']], true)['c'] === 1);
    $bobHold = $r1['json']['data']['bookingId'];

    // ---------- Quyền truy cập đơn ----------
    check('xem đơn của người khác -> 404', $bob->get("/checkout/{$booking2}")['status'] === 404);
    check('xem xác nhận khi chưa trả tiền -> về trang thanh toán', $alice->get("/booking/{$booking2}/confirmation")['path'] === "/checkout/{$booking2}");
    $checkout = $alice->get("/checkout/{$booking2}");
    check('trang thanh toán có đếm ngược + VNPay + tại quầy', $checkout['status'] === 200
        && str_contains($checkout['body'], 'id="countdown"') && str_contains($checkout['body'], 'value="vnpay"') && str_contains($checkout['body'], 'value="cash"'));
    check('Momo chưa hỗ trợ bị từ chối', $alice->post("/checkout/{$booking2}/pay", ['method' => 'momo'])['path'] === "/checkout/{$booking2}"
        && !$db->query('SELECT id FROM payment WHERE bookingId = ?', [$booking2], true));
    check('thanh toán thiếu CSRF bị chặn', $alice->request('POST', "/checkout/{$booking2}/pay", ['method' => 'cash'])['status'] === 403);
    check('thanh toán đơn người khác -> 404', $bob->post("/checkout/{$booking2}/pay", ['method' => 'cash'])['status'] === 404);

    // ---------- VNPay ----------
    $res = $alice->post("/checkout/{$booking2}/pay", ['method' => 'vnpay']);
    parse_str((string)parse_url((string)$res['location'], PHP_URL_QUERY), $vnp);
    $payment = $db->query("SELECT * FROM payment WHERE bookingId = ? AND method = 'vnpay'", [$booking2], true);
    $b2 = $db->query('SELECT * FROM booking WHERE id = ?', [$booking2], true);
    check('chuyển sang VNPay với số tiền x100 + chữ ký', $res['status'] === 302 && str_starts_with((string)$res['location'], 'https://sandbox.vnpayment.vn/')
        && (int)($vnp['vnp_Amount'] ?? 0) === (int)$b2['totalAmount'] * 100 && ($vnp['vnp_TmnCode'] ?? '') === TEST_VNPAY_TMN
        && strlen($vnp['vnp_SecureHash'] ?? '') === 128);
    check('payment pending được tạo', $payment && $payment['status'] === 'pending' && (int)$vnp['vnp_TxnRef'] === (int)$payment['id']);

    $callback = [
        'vnp_Amount' => (string)((int)$b2['totalAmount'] * 100), 'vnp_BankCode' => 'NCB', 'vnp_OrderInfo' => $vnp['vnp_OrderInfo'],
        'vnp_PayDate' => date('YmdHis'), 'vnp_ResponseCode' => '00', 'vnp_TmnCode' => TEST_VNPAY_TMN,
        'vnp_TransactionNo' => '1400' . $payment['id'], 'vnp_TransactionStatus' => '00', 'vnp_TxnRef' => (string)$payment['id'],
    ];
    $tampered = vnpaySigned($callback);
    $tampered['vnp_Amount'] = '100';
    $res = $alice->get("/checkout/{$booking2}/callback?" . http_build_query($tampered));
    check('callback sửa số tiền bị từ chối (sai chữ ký)', $res['path'] === "/checkout/{$booking2}"
        && $db->query('SELECT status FROM payment WHERE id = ?', [$payment['id']], true)['status'] === 'pending');
    $res = $alice->get("/checkout/{$booking2}/callback?" . http_build_query(vnpaySigned(['vnp_Amount' => '100'] + $callback)));
    check('callback ký đúng nhưng sai số tiền bị từ chối', $db->query('SELECT status FROM booking WHERE id = ?', [$booking2], true)['status'] === 'pending');
    check('IPN chữ ký sai trả 97', ($bob->get('/payment/vnpay-ipn?' . http_build_query($tampered))['json']['RspCode'] ?? '') === '97');

    $pointsBefore = (int)$db->query('SELECT points FROM users WHERE id = ?', [$aliceId], true)['points'];
    $voucherUsedBefore = (int)$db->query('SELECT usedCount FROM voucher WHERE id = ?', [$vPercent], true)['usedCount'];
    $res = $alice->get("/checkout/{$booking2}/callback?" . http_build_query(vnpaySigned($callback)));
    $b2 = $db->query('SELECT * FROM booking WHERE id = ?', [$booking2], true);
    $payment = $db->query('SELECT * FROM payment WHERE id = ?', [$payment['id']], true);
    check('callback thành công -> trang xác nhận', $res['path'] === "/booking/{$booking2}/confirmation");
    check('booking paid + payment success + mã GD', $b2['status'] === 'paid' && $payment['status'] === 'success'
        && $payment['transactionCode'] === 'VNP1400' . $payment['id'] && $payment['paidAt']);
    $earned = (int)floor((int)$b2['totalAmount'] * 5 / 100 / 1000);
    $aliceAfter = $db->query('SELECT points, totalSpending FROM users WHERE id = ?', [$aliceId], true);
    check('cộng điểm 5% + tổng chi tiêu', (int)$b2['pointsEarned'] === $earned
        && (int)$aliceAfter['points'] === $pointsBefore + $earned && (int)$aliceAfter['totalSpending'] === (int)$b2['totalAmount']);
    check('lịch sử điểm ghi nhận', (int)$db->query('SELECT SUM(points) s FROM point_history WHERE bookingId = ?', [$booking2], true)['s'] === $earned);
    check('voucher tăng lượt dùng', (int)$db->query('SELECT usedCount FROM voucher WHERE id = ?', [$vPercent], true)['usedCount'] === $voucherUsedBefore + 1);

    $alice->get("/checkout/{$booking2}/callback?" . http_build_query(vnpaySigned($callback)));
    check('callback lặp lại không cộng điểm lần 2', (int)$db->query('SELECT points FROM users WHERE id = ?', [$aliceId], true)['points'] === $pointsBefore + $earned);
    check('IPN sau khi đã xác nhận trả 02', ($bob->get('/payment/vnpay-ipn?' . http_build_query(vnpaySigned($callback)))['json']['RspCode'] ?? '') === '02');

    $confirm = $alice->get("/booking/{$booking2}/confirmation");
    check('trang xác nhận có mã vé + QR + đã thanh toán', $confirm['status'] === 200 && str_contains($confirm['body'], $b2['code'])
        && str_contains($confirm['body'], 'data:image/svg+xml;base64,') && str_contains($confirm['body'], 'Đã thanh toán'));
    check('mở trang thanh toán của đơn đã trả -> xác nhận', $alice->get("/checkout/{$booking2}")['path'] === "/booking/{$booking2}/confirmation");
    check('voucher đã dùng hết lượt/người', ($alice->ajax('/api/validate-voucher', ['showtimeId' => $st2, 'seatIds' => [$std[8]['id']], 'voucherCode' => 'P3TESTPCT'])['json']['field'] ?? '') === 'voucher');
    check('ghế đã thanh toán không đặt lại được', $bob->ajax('/booking/save-seats', ['showtimeId' => $st1, 'seatIds' => [$std[2]['id']]])['status'] === 422);

    // ---------- VNPay thất bại + IPN ----------
    $res = $bob->ajax('/booking/save-seats', ['showtimeId' => $st1, 'seatIds' => [$vip[0]['id']]]);
    $booking3 = $res['json']['data']['bookingId'];
    $res = $bob->post("/checkout/{$booking3}/pay", ['method' => 'vnpay']);
    parse_str((string)parse_url((string)$res['location'], PHP_URL_QUERY), $vnp3);
    $b3 = $db->query('SELECT * FROM booking WHERE id = ?', [$booking3], true);
    $cb3 = [
        'vnp_Amount' => (string)((int)$b3['totalAmount'] * 100), 'vnp_ResponseCode' => '24', 'vnp_TmnCode' => TEST_VNPAY_TMN,
        'vnp_TransactionNo' => '0', 'vnp_TransactionStatus' => '02', 'vnp_TxnRef' => $vnp3['vnp_TxnRef'],
    ];
    $res = $bob->get("/checkout/{$booking3}/callback?" . http_build_query(vnpaySigned($cb3)));
    check('khách hủy trên VNPay -> payment failed, đơn vẫn pending', $res['path'] === "/checkout/{$booking3}"
        && $db->query('SELECT status FROM payment WHERE id = ?', [$vnp3['vnp_TxnRef']], true)['status'] === 'failed'
        && $db->query('SELECT status FROM booking WHERE id = ?', [$booking3], true)['status'] === 'pending');

    $bob->post("/checkout/{$booking3}/pay", ['method' => 'vnpay']);
    $pay3 = $db->query("SELECT * FROM payment WHERE bookingId = ? AND status = 'pending'", [$booking3], true);
    $ipn = $guest->get('/payment/vnpay-ipn?' . http_build_query(vnpaySigned([
        'vnp_Amount' => (string)((int)$b3['totalAmount'] * 100), 'vnp_ResponseCode' => '00', 'vnp_TmnCode' => TEST_VNPAY_TMN,
        'vnp_TransactionNo' => '1500' . $pay3['id'], 'vnp_TransactionStatus' => '00', 'vnp_TxnRef' => (string)$pay3['id'],
    ])));
    check('thử lại + IPN (không cần session) xác nhận thanh toán', ($ipn['json']['RspCode'] ?? '') === '00'
        && $db->query('SELECT status FROM booking WHERE id = ?', [$booking3], true)['status'] === 'paid');
    check('hạng VIP tích 7% điểm', (int)$db->query('SELECT pointsEarned FROM booking WHERE id = ?', [$booking3], true)['pointsEarned']
        === (int)floor((int)$b3['totalAmount'] * 7 / 100 / 1000));

    // ---------- Thanh toán tại quầy ----------
    $res = $bob->post("/checkout/{$bobHold}/pay", ['method' => 'cash']);
    $bh = $db->query('SELECT * FROM booking WHERE id = ?', [$bobHold], true);
    check('tại quầy -> trang xác nhận, đơn giữ tới giờ chiếu', $res['path'] === "/booking/{$bobHold}/confirmation"
        && $bh['status'] === 'pending' && $bh['expiredAt'] === "{$tomorrow} 04:00:00"
        && $db->query("SELECT status FROM payment WHERE bookingId = ? AND method = 'cash'", [$bobHold], true)['status'] === 'pending');
    check('xác nhận tại quầy hiện chờ thanh toán', str_contains($bob->get("/booking/{$bobHold}/confirmation")['body'], 'thanh toán <strong>') && str_contains($bob->get("/booking/{$bobHold}/confirmation")['body'], 'Cần thanh toán'));
    check('đơn tại quầy không bị thay thế khi chọn lại ghế', $bob->ajax('/booking/save-seats', ['showtimeId' => $st2, 'seatIds' => [$std[6]['id']]])['status'] === 200
        && $db->query('SELECT status FROM booking WHERE id = ?', [$bobHold], true)['status'] === 'pending'
        && (int)$db->query('SELECT COUNT(*) c FROM booking_seat WHERE bookingId = ?', [$bobHold], true)['c'] === 1);
    check('không chọn VNPay lại cho đơn tại quầy', $bob->post("/checkout/{$bobHold}/pay", ['method' => 'vnpay'])['path'] === "/booking/{$bobHold}/confirmation"
        || !$db->query("SELECT id FROM payment WHERE bookingId = ? AND method = 'vnpay'", [$bobHold], true));

    // ---------- Hết hạn giữ ghế ----------
    $res = $alice->ajax('/booking/save-seats', ['showtimeId' => $st2, 'seatIds' => [$std[9]['id']]]);
    $booking4 = $res['json']['data']['bookingId'];
    $db->execute('UPDATE booking SET expiredAt = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 5), $booking4]);
    $page = $alice->get("/checkout/{$booking4}");
    check('đơn quá hạn: trang thanh toán báo hết giờ', str_contains($page['body'], 'hết thời gian giữ ghế') && !str_contains($page['body'], 'id="payForm"'));
    check('đơn quá hạn bị expired + nhả ghế', $db->query('SELECT status FROM booking WHERE id = ?', [$booking4], true)['status'] === 'expired'
        && (int)$db->query('SELECT COUNT(*) c FROM booking_seat WHERE bookingId = ?', [$booking4], true)['c'] === 0);
    check('thanh toán đơn quá hạn bị từ chối', $alice->post("/checkout/{$booking4}/pay", ['method' => 'cash'])['path'] === "/checkout/{$booking4}"
        && !$db->query('SELECT id FROM payment WHERE bookingId = ?', [$booking4], true));

    // Tiền về sau khi đơn hết hạn: không đánh dấu paid, ghi log hoàn tiền
    $res = $alice->ajax('/booking/save-seats', ['showtimeId' => $st2, 'seatIds' => [$std[10]['id']]]);
    $booking5 = $res['json']['data']['bookingId'];
    $alice->post("/checkout/{$booking5}/pay", ['method' => 'vnpay']);
    $pay5 = $db->query("SELECT * FROM payment WHERE bookingId = ? AND status = 'pending'", [$booking5], true);
    $b5 = $db->query('SELECT * FROM booking WHERE id = ?', [$booking5], true);
    $db->execute("UPDATE booking SET expiredAt = ?, status = 'expired' WHERE id = ?", [date('Y-m-d H:i:s', time() - 5), $booking5]);
    $db->execute('DELETE FROM booking_seat WHERE bookingId = ?', [$booking5]);
    $res = $alice->get("/checkout/{$booking5}/callback?" . http_build_query(vnpaySigned([
        'vnp_Amount' => (string)((int)$b5['totalAmount'] * 100), 'vnp_ResponseCode' => '00', 'vnp_TmnCode' => TEST_VNPAY_TMN,
        'vnp_TransactionNo' => '1600' . $pay5['id'], 'vnp_TransactionStatus' => '00', 'vnp_TxnRef' => (string)$pay5['id'],
    ])));
    check('tiền về sau khi hết hạn: báo hoàn tiền, không paid', str_contains($alice->get("/checkout/{$booking5}")['body'], 'hoàn tiền')
        && $db->query('SELECT status FROM booking WHERE id = ?', [$booking5], true)['status'] === 'expired');

    // ---------- Dữ liệu seed ----------
    check('dữ liệu seed không bị thay đổi', $seedBefore === $seedSnapshot());
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    $cleanup();
}

check('đã dọn dữ liệu test', (int)$db->query('SELECT COUNT(*) c FROM users WHERE email LIKE ?', ['%' . TEST_DOMAIN], true)['c'] === 0
    && (int)$db->query("SELECT COUNT(*) c FROM voucher WHERE code LIKE 'P3TEST%'", [], true)['c'] === 0
    && (!$ids['showtimes'] || (int)$db->query('SELECT COUNT(*) c FROM showtime WHERE id IN (' . implode(',', $ids['showtimes']) . ')', [], true)['c'] === 0));

echo PHP_EOL . "{$passed} passed, {$failed} failed" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
