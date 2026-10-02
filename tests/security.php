<?php
// Chỉ chạy từ dòng lệnh: test tạo / xóa dữ liệu và bật server, không được gọi qua web
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Test bảo mật qua HTTP thật: php tests/security.php
 * Header, dò mật khẩu, phiên cũ sau đổi mật khẩu, dùng 1 điểm cho nhiều đơn, vượt lượt voucher,
 * giữ ghế "trả tại quầy" hàng loạt, chặn chạy test qua web, chi tiết lỗi không lộ ra ngoài.
 * Chỉ tạo / xóa user *@sec.test, voucher SECTEST*, suất chiếu test.
 */

require __DIR__ . '/lib.php';

const PORT = 18793;
const DOMAIN = '@sec.test';

$db = new DBHelper();
$showtimes = [];
$throttleDir = BASE_PATH . '/storage/framework/throttle';
array_map('unlink', glob($throttleDir . '/*.json') ?: []);
cleanupTestData($db, '%' . DOMAIN, []);
$db->execute("DELETE FROM voucher WHERE code LIKE 'SECTEST%'");
$server = startServer(PORT, ['APP_DEBUG' => 'true']);

try {
    $base = strtotime(date('Y-m-d', strtotime('+5 day')));
    $roomId = freeRoom($db, [$base + 3600 + 120, $base + 4 * 3600 + 120, $base + 7 * 3600 + 120]);
    $seats = array_map('intval', array_column($db->query("SELECT id FROM seat WHERE roomId = ? AND status = 'active' ORDER BY id LIMIT 12", [$roomId]), 'id'));
    foreach ([1, 4, 7] as $i => $hour) {
        $showtimes[$i] = makeShowtime($db, $roomId, $base + $hour * 3600 + 120);
    }

    // ---------- Header ----------
    $guest = new Client(PORT);
    $raw = @get_headers('http://127.0.0.1:' . PORT . '/login', true) ?: [];
    $h = array_change_key_case($raw, CASE_LOWER);
    check('CSP chặn nhúng iframe + object', str_contains($h['content-security-policy'] ?? '', "frame-ancestors 'none'")
        && str_contains($h['content-security-policy'] ?? '', "object-src 'none'"));
    check('X-Frame-Options, nosniff, Referrer-Policy', ($h['x-frame-options'] ?? '') === 'DENY'
        && ($h['x-content-type-options'] ?? '') === 'nosniff' && !empty($h['referrer-policy']));
    check('không lộ X-Powered-By', !isset($h['x-powered-by']));

    // ---------- Chống dò mật khẩu ----------
    $victimId = makeUser($db, 'victim' . DOMAIN, 'customer', ['name' => 'Nạn Nhân']);
    $attacker = new Client(PORT);
    for ($i = 0; $i < 5; $i++) {
        $attacker->login('victim' . DOMAIN, 'sai-mat-khau-' . $i);
    }
    $attacker->get('/login');
    $r = $attacker->post('/login', ['email' => 'victim' . DOMAIN, 'password' => '123456']);
    check('sai 5 lần: khóa tạm, kể cả khi nhập đúng', $r['path'] === '/login'
        && str_contains($attacker->get('/login')['body'], 'sai quá nhiều lần')
        && $attacker->get('/user/profile')['path'] === '/login');
    makeUser($db, 'other' . DOMAIN, 'customer');
    check('tài khoản khác cùng máy vẫn đăng nhập được', (new Client(PORT))->login('other' . DOMAIN, '123456')['path'] === '/');
    array_map('unlink', glob($throttleDir . '/*.json') ?: []);
    check('hết khóa thì đăng nhập lại được', (new Client(PORT))->login('victim' . DOMAIN, '123456')['path'] === '/');

    // ---------- Đổi mật khẩu: đăng xuất phiên khác ----------
    $phone = new Client(PORT);
    $phone->login('victim' . DOMAIN, '123456');
    $laptop = new Client(PORT);
    $laptop->login('victim' . DOMAIN, '123456');
    $laptop->get('/user/profile');
    $laptop->post('/user/profile/update', ['form' => 'password', 'current_password' => '123456', 'new_password' => 'moi123456', 'new_password_confirm' => 'moi123456']);
    check('phiên đổi mật khẩu vẫn đăng nhập', $laptop->get('/user/profile')['status'] === 200);
    check('phiên cũ trên máy khác bị đăng xuất', ($r = $phone->get('/user/profile'))['status'] === 302 && $r['path'] === '/login');
    $db->update('users', ['password' => password_hash('khac999', PASSWORD_BCRYPT)], 'id = ?', [$victimId]);
    check('admin đặt lại mật khẩu: phiên đang mở bị đăng xuất', $laptop->get('/user/profile')['path'] === '/login');

    // ---------- 1 lượng điểm không dùng được cho 2 đơn ----------
    $aliceId = makeUser($db, 'alice' . DOMAIN, 'customer', ['name' => 'Alice Sec', 'points' => 100]);
    $alice = new Client(PORT);
    $alice->login('alice' . DOMAIN, '123456');
    $alice->get('/booking/showtime/' . $showtimes[0]);
    $r1 = $alice->ajax('/booking/save-seats', ['showtimeId' => $showtimes[0], 'seatIds' => [$seats[0], $seats[1]], 'points' => 100]);
    check('đơn 1 dùng 100 điểm', ($r1['json']['status'] ?? '') === 'success');
    $r2 = $alice->ajax('/booking/save-seats', ['showtimeId' => $showtimes[1], 'seatIds' => [$seats[2], $seats[3]], 'points' => 100]);
    check('đơn 2 dùng lại 100 điểm đang giữ cho đơn 1 bị từ chối', $r2['status'] === 422);
    $q = $alice->ajax('/api/apply-points', ['showtimeId' => $showtimes[1], 'seatIds' => [$seats[2]], 'points' => 'max']);
    check('báo giá: điểm khả dụng trừ phần đang giữ', (int)($q['json']['data']['availablePoints'] ?? -1) === 0);

    // Điểm bị tiêu ở nơi khác trước khi trả tiền: không được giảm giá miễn phí
    $held = $db->query('SELECT id FROM booking WHERE userId = ? AND showtimeId = ? AND status = ?', [$aliceId, $showtimes[0], 'pending'], true);
    $db->update('users', ['points' => 10], 'id = ?', [$aliceId]);
    $payId = (new App\Models\Payment())->createPending($held['id'], 'sepay', (int)$db->query('SELECT totalAmount FROM booking WHERE id = ?', [$held['id']], true)['totalAmount']);
    $result = (new App\Models\Booking())->markPaid($payId, 'SECTEST' . $payId, ['test' => true]);
    check('thiếu điểm lúc tiền về: không ghi nhận đơn (cần hoàn tiền)', $result === 'late'
        && $db->query('SELECT status FROM booking WHERE id = ?', [$held['id']], true)['status'] === 'pending'
        && (int)$db->query('SELECT points FROM users WHERE id = ?', [$aliceId], true)['points'] === 10);

    // Hủy đơn chỉ hoàn đúng số điểm đã thực trừ (bỏ đơn đang giữ 100 điểm ở trên để điểm được dùng lại)
    $db->execute("UPDATE booking SET status = 'cancelled' WHERE userId = ? AND status = 'pending'", [$aliceId]);
    $db->update('users', ['points' => 50], 'id = ?', [$aliceId]);
    $paid = makePaidBooking($db, $aliceId, $showtimes[2], [$seats[4]], [], 30);
    $afterPay = (int)$db->query('SELECT points FROM users WHERE id = ?', [$aliceId], true)['points'];
    $db->update('users', ['points' => 0], 'id = ?', [$aliceId]);            // khách đã tiêu hết điểm tích được
    (new App\Models\Booking())->cancelByUser($paid['id'], $aliceId);
    $earned = (int)$paid['pointsEarned'];
    check('hủy đơn: hoàn 30 điểm đã dùng, thu hồi tối đa số đang có', (int)$db->query('SELECT points FROM users WHERE id = ?', [$aliceId], true)['points'] === max(0, 30 - $earned)
        && $afterPay === 50 - 30 + $earned);
    check('hủy lần 2 không hoàn điểm thêm', (function () use ($paid, $aliceId) {
        try { (new App\Models\Booking())->cancelByUser($paid['id'], $aliceId); return false; } catch (App\Core\ValidationException $e) { return true; }
    })());

    // ---------- Voucher: đơn đang giữ chiếm lượt ----------
    $db->insert('voucher', ['code' => 'SECTEST1', 'name' => 'Sec 1 lượt', 'discountType' => 'fixed', 'discountValue' => 10000, 'quantity' => 1, 'perUserLimit' => 1,
        'startDate' => date('Y-m-d H:i:s', time() - 60), 'endDate' => date('Y-m-d H:i:s', time() + 86400)]);
    $bobId = makeUser($db, 'bob' . DOMAIN, 'customer');
    $carolId = makeUser($db, 'carol' . DOMAIN, 'customer');
    $bob = new Client(PORT);
    $bob->login('bob' . DOMAIN, '123456');
    $carol = new Client(PORT);
    $carol->login('carol' . DOMAIN, '123456');
    $bob->get('/booking/showtime/' . $showtimes[1]);
    $carol->get('/booking/showtime/' . $showtimes[1]);
    check('khách 1 giữ ghế với voucher còn 1 lượt', ($bob->ajax('/booking/save-seats', ['showtimeId' => $showtimes[1], 'seatIds' => [$seats[5]], 'voucherCode' => 'SECTEST1'])['json']['status'] ?? '') === 'success');
    check('khách 2 không lấy được lượt đang được giữ', $carol->ajax('/booking/save-seats', ['showtimeId' => $showtimes[1], 'seatIds' => [$seats[6]], 'voucherCode' => 'SECTEST1'])['status'] === 422);
    $db->execute("UPDATE voucher SET usedCount = quantity WHERE code = 'SECTEST1'");
    $bobHold = $db->query('SELECT id, totalAmount FROM booking WHERE userId = ? AND status = ?', [$bobId, 'pending'], true);
    $bobPay = (new App\Models\Payment())->createPending($bobHold['id'], 'sepay', (int)$bobHold['totalAmount']);
    check('voucher hết lượt lúc tiền về: không ghi nhận đơn', (new App\Models\Booking())->markPaid($bobPay, 'SECTESTV' . $bobPay, []) === 'late'
        && (int)$db->query("SELECT usedCount FROM voucher WHERE code = 'SECTEST1'", [], true)['usedCount'] === 1);

    // ---------- Giữ ghế "trả tại quầy" hàng loạt ----------
    $daveId = makeUser($db, 'dave' . DOMAIN, 'customer');
    $dave = new Client(PORT);
    $dave->login('dave' . DOMAIN, '123456');
    $cashOk = 0;
    foreach ([[0, 7], [0, 8], [1, 9], [2, 10]] as [$st, $seat]) {
        $dave->get('/booking/showtime/' . $showtimes[$st]);
        $id = $dave->ajax('/booking/save-seats', ['showtimeId' => $showtimes[$st], 'seatIds' => [$seats[$seat]]])['json']['data']['bookingId'] ?? null;
        if ($id) {
            $dave->get('/checkout/' . $id);
            $dave->post('/checkout/' . $id . '/pay', ['method' => 'cash']);
        }
    }
    $cashOk = (int)$db->query(
        "SELECT COUNT(*) c FROM booking b WHERE b.userId = ? AND b.status = 'pending'
         AND EXISTS (SELECT 1 FROM payment p WHERE p.bookingId = b.id AND p.method = 'cash' AND p.status = 'pending')",
        [$daveId],
        true
    )['c'];
    check('mỗi khách tối đa 1 đơn tại quầy / suất, ' . App\Models\Booking::MAX_CASH_HOLDS . ' đơn tổng', $cashOk === App\Models\Booking::MAX_CASH_HOLDS);

    // ---------- Lỗi không lộ chi tiết cho máy ngoài ----------
    $index = file_get_contents(BASE_PATH . '/public/index.php');
    check('APP_DEBUG chỉ áp dụng cho localhost', str_contains($index, '$fromLocalhost') && str_contains($index, "'debug'] && \$fromLocalhost"));

    // ---------- Test / script không chạy qua web ----------
    $guard = true;
    foreach (array_merge(glob(BASE_PATH . '/tests/*.php'), [BASE_PATH . '/scripts/release-expired.php']) as $file) {
        $guard = $guard && str_contains(file_get_contents($file), "PHP_SAPI !== 'cli'");
    }
    check('mọi file test + cron có chặn chạy qua web', $guard);
    check('.htaccess gốc chặn truy cập thư mục dự án', str_contains((string)@file_get_contents(BASE_PATH . '/.htaccess'), 'Require all denied'));
    check('thư mục upload không chạy PHP', str_contains((string)@file_get_contents(BASE_PATH . '/public/images/uploads/.htaccess'), 'php_flag engine off'));
} finally {
    stopServer($server);
    cleanupTestData($db, '%' . DOMAIN, $showtimes);
    $db->execute("DELETE FROM voucher WHERE code LIKE 'SECTEST%'");
    array_map('unlink', glob($throttleDir . '/*.json') ?: []);
}

check('đã dọn dữ liệu test', (int)$db->query('SELECT COUNT(*) c FROM users WHERE email LIKE ?', ['%' . DOMAIN], true)['c'] === 0);
finish();
