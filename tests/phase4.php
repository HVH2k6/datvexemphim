<?php
/**
 * Test Phase 4 (Tài khoản khách hàng) qua HTTP thật: php tests/phase4.php
 * Hồ sơ, đổi mật khẩu, lịch sử vé, hủy vé + hoàn điểm, điểm thưởng / hạng, voucher.
 * Chỉ tạo / xóa user *@phase4.test, voucher P4TEST*, suất chiếu test.
 */

// Chỉ chạy từ dòng lệnh: test tạo / xóa dữ liệu và bật server, không được gọi qua web
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/lib.php';

const PORT = 18781;
const DOMAIN = '@phase4.test';

$db = new DBHelper();
$showtimes = [];
cleanupTestData($db, '%' . DOMAIN, []);
$db->execute("DELETE FROM voucher WHERE code LIKE 'P4TEST%'");
$server = startServer(PORT);

try {
    $day = strtotime(date('Y-m-d', strtotime('+4 day')));
    $roomId = freeRoom($db, [$day + 3600, time() + 3600 + 37, strtotime(date('Y-m-d', strtotime('-2 day'))) + 2 * 3600]);
    $seats = array_map('intval', array_column($db->query("SELECT id FROM seat WHERE roomId = ? AND status = 'active' ORDER BY id LIMIT 12", [$roomId]), 'id'));
    $farShowtime = makeShowtime($db, $roomId, $day + 1 * 3600);          // 01:00 ngày +4: hủy được
    $soonShowtime = makeShowtime($db, $roomId, time() + 3600 + 37);       // trong 2 giờ tới: không hủy được
    $pastShowtime = makeShowtime($db, $roomId, strtotime(date('Y-m-d', strtotime('-2 day'))) + 2 * 3600);
    $showtimes = [$farShowtime, $soonShowtime, $pastShowtime];
    $productId = (int)$db->query("SELECT id FROM product WHERE status = 'active' ORDER BY id LIMIT 1", [], true)['id'];

    $aliceId = makeUser($db, 'alice' . DOMAIN, 'customer', ['name' => 'Alice Nguyen', 'points' => 40]);
    $bobId = makeUser($db, 'bob' . DOMAIN, 'customer', ['name' => 'Bob Tran', 'phoneNumber' => '0977000004']);
    $staffId = makeUser($db, 'staff' . DOMAIN, 'staff');

    $alice = new Client(PORT);
    $alice->login('alice' . DOMAIN, '123456');
    $bob = new Client(PORT);
    $bob->login('bob' . DOMAIN, '123456');

    // ---------- Phân quyền ----------
    $guest = new Client(PORT);
    foreach (['/user/profile', '/user/bookings', '/user/points', '/user/vouchers'] as $p) {
        $r = $guest->get($p);
        check("khách chưa đăng nhập vào {$p} -> /login", $r['status'] === 302 && $r['path'] === '/login');
    }
    $staff = new Client(PORT);
    $staff->login('staff' . DOMAIN, '123456');
    check('nhân viên vào trang khách bị chặn (403)', $staff->get('/user/profile')['status'] === 403);

    // ---------- Hồ sơ ----------
    $page = $alice->get('/user/profile');
    check('trang hồ sơ 200, có tên + email + hạng', $page['status'] === 200
        && str_contains($page['body'], 'Alice Nguyen') && str_contains($page['body'], 'alice' . DOMAIN) && str_contains($page['body'], 'Hạng thành viên'));
    check('sidebar có 4 mục + đăng xuất', substr_count($page['body'], 'href="http://127.0.0.1:' . PORT . '/user/') >= 4 && str_contains($page['body'], 'Đăng xuất'));

    $r = $alice->post('/user/profile/update', ['name' => 'AB', 'phoneNumber' => '', 'birthday' => '', 'gender' => '']);
    check('tên quá ngắn bị từ chối, giữ lại dữ liệu đã nhập', $r['path'] === '/user/profile'
        && str_contains($alice->follow($r)['body'], 'Họ tên phải từ 3'));
    $alice->get('/user/profile');
    $r = $alice->post('/user/profile/update', ['name' => 'Alice', 'phoneNumber' => '12345', 'birthday' => '', 'gender' => '']);
    check('SĐT sai định dạng bị từ chối', str_contains($alice->follow($r)['body'], 'Số điện thoại không hợp lệ'));
    $alice->get('/user/profile');
    $r = $alice->post('/user/profile/update', ['name' => 'Alice', 'phoneNumber' => '0977000004', 'birthday' => '', 'gender' => '']);
    check('SĐT trùng tài khoản khác bị từ chối', str_contains($alice->follow($r)['body'], 'đã được dùng'));
    $alice->get('/user/profile');
    $r = $alice->post('/user/profile/update', ['name' => 'Alice', 'phoneNumber' => '', 'birthday' => date('Y-m-d', strtotime('+1 day')), 'gender' => '']);
    check('ngày sinh tương lai bị từ chối', str_contains($alice->follow($r)['body'], 'Ngày sinh không hợp lệ'));
    $alice->get('/user/profile');
    $r = $alice->post('/user/profile/update', ['name' => 'Alice', 'phoneNumber' => '', 'birthday' => '', 'gender' => 'robot']);
    check('giới tính lạ bị từ chối', str_contains($alice->follow($r)['body'], 'Giới tính không hợp lệ'));

    $alice->get('/user/profile');
    $r = $alice->post('/user/profile/update', [
        'name' => '  Alice   Nguyễn  ', 'phoneNumber' => '0912 345 678', 'birthday' => '1998-04-20', 'gender' => 'female',
        'email' => 'hacker' . DOMAIN, 'points' => 99999, 'roleId' => 1,
    ]);
    $u = $db->query('SELECT * FROM users WHERE id = ?', [$aliceId], true);
    check('cập nhật hồ sơ thành công', $r['path'] === '/user/profile' && str_contains($alice->follow($r)['body'], 'Đã cập nhật'));
    check('tên chuẩn hóa, SĐT bỏ khoảng trắng, ngày sinh + giới tính lưu đúng', $u['name'] === 'Alice Nguyễn'
        && $u['phoneNumber'] === '0912345678' && $u['birthday'] === '1998-04-20' && $u['gender'] === 'female');
    check('không sửa được email / điểm / role qua form', $u['email'] === 'alice' . DOMAIN && (int)$u['points'] === 40
        && (int)$u['roleId'] === (new App\Models\User())->getRoleIdByName('customer'));
    check('cập nhật thiếu CSRF bị chặn (403)', $alice->request('POST', '/user/profile/update', ['name' => 'X Y Z'])['status'] === 403);

    // ---------- Đổi mật khẩu ----------
    $alice->get('/user/profile');
    $r = $alice->post('/user/profile/update', ['form' => 'password', 'current_password' => 'sai', 'new_password' => 'moi123456', 'new_password_confirm' => 'moi123456']);
    check('đổi mật khẩu: sai mật khẩu hiện tại', str_contains($alice->follow($r)['body'], 'Mật khẩu hiện tại không đúng'));
    $alice->get('/user/profile');
    $r = $alice->post('/user/profile/update', ['form' => 'password', 'current_password' => '123456', 'new_password' => 'moi123456', 'new_password_confirm' => 'khac']);
    check('đổi mật khẩu: xác nhận không khớp', str_contains($alice->follow($r)['body'], 'không khớp'));

    // Thiết bị khác đang "Ghi nhớ tôi" phải bị đăng xuất sau khi đổi mật khẩu
    $device2 = new Client(PORT);
    $device2->get('/login');
    $device2->post('/login', ['email' => 'alice' . DOMAIN, 'password' => '123456', 'remember' => '1']);
    $rememberCookie = $device2->cookies['cinema_remember'] ?? '';
    $alice->get('/user/profile');
    $r = $alice->post('/user/profile/update', ['form' => 'password', 'current_password' => '123456', 'new_password' => 'moi123456', 'new_password_confirm' => 'moi123456']);
    check('đổi mật khẩu thành công, phiên hiện tại vẫn đăng nhập', str_contains($alice->follow($r)['body'], 'Đã đổi mật khẩu')
        && $alice->get('/user/profile')['status'] === 200);
    check('mật khẩu mới dùng được, mật khẩu cũ không', password_verify('moi123456', $db->query('SELECT password FROM users WHERE id = ?', [$aliceId], true)['password'])
        && ($c = new Client(PORT))->login('alice' . DOMAIN, '123456')['path'] === '/login');
    $stolen = new Client(PORT);
    $stolen->cookies['cinema_remember'] = $rememberCookie;
    check('cookie ghi nhớ cũ hết hiệu lực', $rememberCookie !== '' && $stolen->get('/user/profile')['path'] === '/login');

    // ---------- Lịch sử vé ----------
    $paidFar = makePaidBooking($db, $aliceId, $farShowtime, [$seats[0], $seats[1]], [$productId => 1], 20);
    $paidSoon = makePaidBooking($db, $aliceId, $soonShowtime, [$seats[2]]);
    $paidPast = makePaidBooking($db, $aliceId, $pastShowtime, [$seats[3]]);
    $bobPaid = makePaidBooking($db, $bobId, $farShowtime, [$seats[4]]);
    // Đơn giữ ghế còn hạn + đơn đã hết hạn
    $holdFar = (new App\Models\Booking())->hold((new App\Models\User())->getUserById($aliceId), (new App\Models\Showtime())->getDetail($soonShowtime), [$seats[5]], []);
    $expired = (new App\Models\Booking())->hold((new App\Models\User())->getUserById($aliceId), (new App\Models\Showtime())->getDetail($farShowtime), [$seats[6]], []);
    $db->execute('UPDATE booking SET expiredAt = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 60), $expired['id']]);

    $up = $alice->get('/user/bookings');
    check('tab Sắp chiếu: vé đã trả + đơn đang giữ ghế', $up['status'] === 200 && str_contains($up['body'], $paidFar['code'])
        && str_contains($up['body'], $paidSoon['code']) && str_contains($up['body'], $holdFar['code']));
    check('tab Sắp chiếu không có vé đã chiếu / hết hạn / của người khác', !str_contains($up['body'], $paidPast['code'])
        && !str_contains($up['body'], $expired['code']) && !str_contains($up['body'], $bobPaid['code']));
    check('vé hiện ghế + tổng tiền + phương thức', str_contains($up['body'], 'Chuyển khoản') && preg_match('/[A-Z]\d+, [A-Z]\d+/', $up['body']));
    check('đơn giữ ghế có nút Tiếp tục thanh toán', str_contains($up['body'], 'Tiếp tục thanh toán'));
    check('vé xa giờ chiếu có nút Hủy vé', substr_count($up['body'], 'user/cancel-booking/' . $paidFar['id']) === 1);
    check('vé sắp chiếu (<2 giờ) không có nút Hủy', !str_contains($up['body'], 'user/cancel-booking/' . $paidSoon['id']));

    $past = $alice->get('/user/bookings?tab=past');
    check('tab Đã xem: vé suất đã chiếu + nút đánh giá', str_contains($past['body'], $paidPast['code']) && str_contains($past['body'], 'Đánh giá phim')
        && !str_contains($past['body'], $paidFar['code']));
    $cancelled = $alice->get('/user/bookings?tab=cancelled');
    check('tab Đã hủy: đơn hết hạn', str_contains($cancelled['body'], $expired['code']) && str_contains($cancelled['body'], 'Hết hạn'));
    check('tab lạ quay về Sắp chiếu', str_contains($alice->get('/user/bookings?tab=hack')['body'], $paidFar['code']));
    check('trang vượt quá số trang -> 404', $alice->get('/user/bookings?page=99')['status'] === 404);

    // ---------- Hủy vé ----------
    $beforeAlice = $db->query('SELECT points, totalSpending, memberLevelId FROM users WHERE id = ?', [$aliceId], true);
    check('vé đã trả có cộng điểm + trừ 20 điểm đã dùng', (int)$paidFar['pointsUsed'] === 20 && (int)$paidFar['pointsEarned'] >= 0
        && (int)$beforeAlice['points'] === 40 - 20 + (int)$paidFar['pointsEarned'] + (int)$paidSoon['pointsEarned'] + (int)$paidPast['pointsEarned']);

    $alice->get('/user/bookings');
    check('hủy vé của người khác bị từ chối', str_contains($alice->follow($alice->post('/user/cancel-booking/' . $bobPaid['id'], []))['body'], 'Không tìm thấy vé')
        && $db->query('SELECT status FROM booking WHERE id = ?', [$bobPaid['id']], true)['status'] === 'paid');
    $alice->get('/user/bookings');
    check('hủy vé sắp chiếu (<2 giờ) bị từ chối', str_contains($alice->follow($alice->post('/user/cancel-booking/' . $paidSoon['id'], []))['body'], 'ít nhất 2 giờ'));
    check('hủy thiếu CSRF bị chặn (403)', $alice->request('POST', '/user/cancel-booking/' . $paidFar['id'], [])['status'] === 403);

    $alice->get('/user/bookings');
    $r = $alice->post('/user/cancel-booking/' . $paidFar['id'], []);
    $after = $db->query('SELECT points, totalSpending FROM users WHERE id = ?', [$aliceId], true);
    $bk = $db->query('SELECT status FROM booking WHERE id = ?', [$paidFar['id']], true);
    check('hủy vé đã trả: chuyển sang tab Đã hủy + báo hoàn tiền', $r['query'] === 'tab=cancelled' && str_contains($alice->follow($r)['body'], 'hoàn trong 3-5 ngày'));
    check('đơn cancelled + nhả ghế', $bk['status'] === 'cancelled'
        && (int)$db->query('SELECT COUNT(*) c FROM booking_seat WHERE bookingId = ?', [$paidFar['id']], true)['c'] === 0);
    check('hoàn 20 điểm đã dùng, thu hồi điểm đã tích', (int)$after['points'] === (int)$beforeAlice['points'] + 20 - (int)$paidFar['pointsEarned']);
    check('trừ lại chi tiêu', (int)$after['totalSpending'] === (int)$beforeAlice['totalSpending'] - (int)$paidFar['totalAmount']);
    check('payment chuyển refunded để đối soát', $db->query('SELECT status FROM payment WHERE bookingId = ? ORDER BY id DESC LIMIT 1', [$paidFar['id']], true)['status'] === 'refunded');
    check('ghế đã nhả cho người khác đặt lại', (new App\Models\Booking())->hold((new App\Models\User())->getUserById($bobId), (new App\Models\Showtime())->getDetail($farShowtime), [$seats[0]], [])['id'] > 0);
    $alice->get('/user/bookings?tab=cancelled');
    check('hủy lại lần 2 bị từ chối', str_contains($alice->follow($alice->post('/user/cancel-booking/' . $paidFar['id'], []))['body'], 'hết hiệu lực'));

    // Hủy đơn đang giữ ghế (chưa trả): chỉ nhả ghế, không đụng điểm
    $pointsBefore = (int)$db->query('SELECT points FROM users WHERE id = ?', [$aliceId], true)['points'];
    $cashHold = (new App\Models\Booking())->hold((new App\Models\User())->getUserById($aliceId), (new App\Models\Showtime())->getDetail($farShowtime), [$seats[7]], []);
    (new App\Models\Payment())->createPending($cashHold['id'], 'cash', 90000);
    (new App\Models\Booking())->extendForCash($cashHold['id'], date('Y-m-d H:i:s', $day + 3600));
    $alice->get('/user/bookings');
    $r = $alice->post('/user/cancel-booking/' . $cashHold['id'], []);
    check('hủy đơn "trả tại quầy" chưa thanh toán: nhả ghế, không đổi điểm', str_contains($alice->follow($r)['body'], 'nhả ghế')
        && $db->query('SELECT status FROM booking WHERE id = ?', [$cashHold['id']], true)['status'] === 'cancelled'
        && (int)$db->query('SELECT points FROM users WHERE id = ?', [$aliceId], true)['points'] === $pointsBefore);

    // ---------- Điểm thưởng ----------
    $points = $alice->get('/user/points');
    check('trang điểm 200: số điểm hiện có khớp DB', $points['status'] === 200
        && str_contains($points['body'], number_format($pointsBefore, 0, ',', '.')));
    check('lịch sử điểm có tích điểm, dùng điểm, hoàn điểm, thu hồi', str_contains($points['body'], 'Tích điểm đơn ' . $paidFar['code'])
        && str_contains($points['body'], 'Dùng điểm cho đơn ' . $paidFar['code'])
        && str_contains($points['body'], 'Hoàn điểm do hủy vé ' . $paidFar['code']));
    check('bảng quyền lợi 3 hạng, đánh dấu hạng hiện tại', str_contains($points['body'], 'VVIP') && str_contains($points['body'], 'Hạng của bạn'));

    // Logic điểm (bước 25): tỷ lệ theo hạng, lên / xuống hạng theo chi tiêu
    $pointModel = new App\Models\PointHistory();
    check('pointsFor: 5% của 269.000đ = 13 điểm, 7% = 18, 10% = 26', App\Models\PointHistory::pointsFor(269000, 5) === 13
        && App\Models\PointHistory::pointsFor(269000, 7) === 18 && App\Models\PointHistory::pointsFor(269000, 10) === 26);
    check('pointsFor: số tiền âm / 0 không ra điểm', App\Models\PointHistory::pointsFor(-5000, 10) === 0 && App\Models\PointHistory::pointsFor(999, 10) === 0);
    $db->beginTransaction();
    $pointModel->addSpending($bobId, 4000000);
    $vip = $db->query('SELECT ml.name FROM users u JOIN member_level ml ON ml.id = u.memberLevelId WHERE u.id = ?', [$bobId], true)['name'];
    $pointModel->addSpending($bobId, 4500000);
    $vvip = $db->query('SELECT ml.name FROM users u JOIN member_level ml ON ml.id = u.memberLevelId WHERE u.id = ?', [$bobId], true)['name'];
    $pointModel->addSpending($bobId, -20000000);
    $back = $db->query('SELECT ml.name, u.totalSpending FROM users u JOIN member_level ml ON ml.id = u.memberLevelId WHERE u.id = ?', [$bobId], true);
    $db->commit();
    check('chi tiêu 4tr -> VIP, 8.5tr -> VVIP', $vip === 'VIP' && $vvip === 'VVIP');
    check('trừ chi tiêu xuống dưới 0 -> 0 và về Member', (int)$back['totalSpending'] === 0 && $back['name'] === 'Member');
    $db->execute('UPDATE users SET points = 30 WHERE id = ?', [$bobId]);
    $db->beginTransaction();
    $taken = $pointModel->change($bobId, -500, 'Test trừ quá');
    $none = $pointModel->change($bobId, -10, 'Test trừ khi hết điểm');
    $db->commit();
    $history = $db->query("SELECT note, points FROM point_history WHERE userId = ? AND note LIKE 'Test trừ%'", [$bobId]);
    check('trừ quá số điểm đang có: chỉ trừ tối đa số đang có, không âm', $taken === -30
        && (int)$db->query('SELECT points FROM users WHERE id = ?', [$bobId], true)['points'] === 0);
    check('hết điểm thì không trừ, không ghi lịch sử rỗng', $none === 0 && count($history) === 1 && (int)$history[0]['points'] === -30);

    // ---------- Voucher ----------
    $db->insert('voucher', ['code' => 'P4TESTOK', 'name' => 'P4 đang chạy', 'discountType' => 'percent', 'discountValue' => 15, 'maxDiscount' => 40000,
        'quantity' => 10, 'perUserLimit' => 1, 'startDate' => date('Y-m-d H:i:s', time() - 60), 'endDate' => date('Y-m-d H:i:s', time() + 86400)]);
    $db->insert('voucher', ['code' => 'P4TESTSOON', 'name' => 'P4 sắp có', 'discountType' => 'fixed', 'discountValue' => 25000,
        'quantity' => 10, 'startDate' => date('Y-m-d H:i:s', time() + 86400), 'endDate' => date('Y-m-d H:i:s', time() + 2 * 86400)]);
    $db->insert('voucher', ['code' => 'P4TESTOLD', 'name' => 'P4 hết hạn', 'discountType' => 'fixed', 'discountValue' => 25000,
        'quantity' => 10, 'startDate' => date('Y-m-d H:i:s', time() - 2 * 86400), 'endDate' => date('Y-m-d H:i:s', time() - 60)]);
    $vouchers = $alice->get('/user/vouchers');
    check('trang voucher: mã đang chạy có nút sao chép', $vouchers['status'] === 200 && str_contains($vouchers['body'], 'P4TESTOK')
        && str_contains($vouchers['body'], 'data-copy="P4TESTOK"') && str_contains($vouchers['body'], 'tối đa 40.000đ'));
    check('mã sắp có hiện ngày bắt đầu, không sao chép', str_contains($vouchers['body'], 'P4TESTSOON') && !str_contains($vouchers['body'], 'data-copy="P4TESTSOON"')
        && str_contains($vouchers['body'], 'Bắt đầu từ'));
    check('mã hết hạn không hiện', !str_contains($vouchers['body'], 'P4TESTOLD'));

    // ---------- Navbar ----------
    check('navbar khách có link Vé của tôi + Tài khoản', str_contains($alice->get('/')['body'], '/user/bookings') && str_contains($alice->get('/')['body'], '/user/profile'));
} finally {
    stopServer($server);
    cleanupTestData($db, '%' . DOMAIN, $showtimes);
    $db->execute("DELETE FROM voucher WHERE code LIKE 'P4TEST%'");
}

check('đã dọn dữ liệu test', (int)$db->query('SELECT COUNT(*) c FROM users WHERE email LIKE ?', ['%' . DOMAIN], true)['c'] === 0
    && (int)$db->query("SELECT COUNT(*) c FROM voucher WHERE code LIKE 'P4TEST%'", [], true)['c'] === 0);
finish();
