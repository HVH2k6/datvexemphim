<?php
/**
 * Test Phase 5 (Nhân viên) qua HTTP thật: php tests/phase5.php
 * Tổng quan ca, soát vé (tra cứu / xác nhận / thu tiền tại quầy), bán bắp nước tại quầy (POS) + tích điểm.
 * Chỉ tạo / xóa user *@phase5.test và suất chiếu test.
 */

// Chỉ chạy từ dòng lệnh: test tạo / xóa dữ liệu và bật server, không được gọi qua web
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/lib.php';

const PORT = 18782;
const DOMAIN = '@phase5.test';

$db = new DBHelper();
$showtimes = [];
cleanupTestData($db, '%' . DOMAIN, []);
$server = startServer(PORT);

try {
    $roomId = freeRoom($db, [time() + 30 * 60 + 11, strtotime(date('Y-m-d', strtotime('+3 day'))) + 2 * 3600, strtotime(date('Y-m-d', strtotime('-2 day'))) + 3 * 3600]);
    $seats = array_map('intval', array_column($db->query("SELECT id FROM seat WHERE roomId = ? AND status = 'active' ORDER BY id LIMIT 12", [$roomId]), 'id'));
    $nowShow = makeShowtime($db, $roomId, time() + 30 * 60 + 11);                                     // đang mở soát vé
    $laterShow = makeShowtime($db, $roomId, strtotime(date('Y-m-d', strtotime('+3 day'))) + 2 * 3600); // chưa tới giờ soát
    $pastShow = makeShowtime($db, $roomId, strtotime(date('Y-m-d', strtotime('-2 day'))) + 3 * 3600);  // đã chiếu
    $showtimes = [$nowShow, $laterShow, $pastShow];
    $products = $db->query("SELECT id, name, price FROM product WHERE status = 'active' ORDER BY id LIMIT 3");

    $staffId = makeUser($db, 'staff' . DOMAIN, 'staff', ['name' => 'Nhân Viên A']);
    $staff2Id = makeUser($db, 'staff2' . DOMAIN, 'staff', ['name' => 'Nhân Viên B']);
    $custId = makeUser($db, 'khach' . DOMAIN, 'customer', ['name' => 'Khách Năm', 'phoneNumber' => '0966000005']);
    $blockedId = makeUser($db, 'khoa' . DOMAIN, 'customer', ['phoneNumber' => '0966000006', 'status' => 'blocked']);

    $staff = new Client(PORT);
    $staff->login('staff' . DOMAIN, '123456');
    $staff2 = new Client(PORT);
    $staff2->login('staff2' . DOMAIN, '123456');
    $customer = new Client(PORT);
    $customer->login('khach' . DOMAIN, '123456');

    // ---------- Phân quyền ----------
    $guest = new Client(PORT);
    check('khách chưa đăng nhập vào /staff -> /login', ($r = $guest->get('/staff'))['status'] === 302 && $r['path'] === '/login');
    foreach (['/staff', '/staff/checkin', '/staff/pos'] as $p) {
        check("khách hàng vào {$p} bị chặn (403)", $customer->get($p)['status'] === 403);
    }
    check('nhân viên đăng nhập chuyển tới /staff', ($c = new Client(PORT))->login('staff' . DOMAIN, '123456')['path'] === '/staff');

    // ---------- Dữ liệu vé ----------
    $paid = makePaidBooking($db, $custId, $nowShow, [$seats[0], $seats[1]], [$products[0]['id'] => 1]);
    $paidLater = makePaidBooking($db, $custId, $laterShow, [$seats[2]]);
    $paidPast = makePaidBooking($db, $custId, $pastShow, [$seats[3]]);
    $bookingModel = new App\Models\Booking();
    $cust = (new App\Models\User())->getUserById($custId);
    $cash = $bookingModel->hold($cust, (new App\Models\Showtime())->getDetail($nowShow), [$seats[4]], []);
    $cashTotal = (int)$db->query('SELECT totalAmount FROM booking WHERE id = ?', [$cash['id']], true)['totalAmount'];
    (new App\Models\Payment())->createPending($cash['id'], 'cash', $cashTotal);
    $bookingModel->extendForCash($cash['id'], date('Y-m-d H:i:s', time() + 30 * 60 + 11));
    $unpaid = $bookingModel->hold($cust, (new App\Models\Showtime())->getDetail($nowShow), [$seats[5]], []);

    // ---------- Tổng quan ----------
    $dash = $staff->get('/staff');
    check('tổng quan 200: suất hôm nay + số vé đã bán', $dash['status'] === 200 && str_contains($dash['body'], 'Suất chiếu hôm nay')
        && str_contains($dash['body'], date('H:i', time() + 30 * 60 + 11)) && str_contains($dash['body'], 'Đang soát vé'));
    check('header nhân viên có 3 mục + đăng xuất', str_contains($dash['body'], 'Soát vé') && str_contains($dash['body'], 'Bán tại quầy')
        && str_contains($dash['body'], 'Đăng xuất') && !str_contains($dash['body'], 'class="navbar'));

    // ---------- Soát vé: tra cứu ----------
    $page = $staff->get('/staff/checkin');
    check('trang soát vé 200: camera + ô nhập mã + jsQR', $page['status'] === 200 && str_contains($page['body'], 'id="scanVideo"')
        && str_contains($page['body'], 'id="ticketCode"') && str_contains($page['body'], 'jsQR.min.js'));

    $r = $staff->get('/staff/checkin?code=' . rawurlencode(strtolower(' ' . $paid['code'] . ' ')));
    check('tra cứu vé hợp lệ (chuẩn hóa chữ thường / khoảng trắng)', str_contains($r['body'], 'Vé hợp lệ') && str_contains($r['body'], 'CHO VÀO RẠP'));
    check('kết quả hiện phim, phòng, ghế, bắp nước, tên khách', str_contains($r['body'], 'Phim Hành Động Mẫu') && str_contains($r['body'], 'Khách Năm')
        && substr_count($r['body'], 'class="seat-pill"') === 2 && str_contains($r['body'], $products[0]['name']));
    check('mã sai định dạng báo lỗi', str_contains($staff->get('/staff/checkin?code=HELLO')['body'], 'không đúng định dạng'));
    check('mã không tồn tại báo lỗi', str_contains($staff->get('/staff/checkin?code=BKZZZZZZZZ')['body'], 'Không tìm thấy vé'));
    check('vé suất chưa tới giờ: chưa cho soát', str_contains(($r = $staff->get('/staff/checkin?code=' . $paidLater['code']))['body'], 'Chưa tới giờ soát vé')
        && !str_contains($r['body'], 'CHO VÀO RẠP'));
    check('vé suất đã chiếu: hết hạn soát', str_contains(($r = $staff->get('/staff/checkin?code=' . $paidPast['code']))['body'], 'Suất chiếu đã kết thúc')
        && !str_contains($r['body'], 'CHO VÀO RẠP'));
    check('đơn giữ ghế chưa trả: không hợp lệ', str_contains(($r = $staff->get('/staff/checkin?code=' . $unpaid['code']))['body'], 'chưa thanh toán, không được vào rạp')
        && !str_contains($r['body'], 'CHO VÀO RẠP'));

    $r = $staff->request('POST', '/staff/checkin', ['code' => $paid['code'], 'action' => 'lookup', 'csrf_token' => '']);
    check('tra cứu POST thiếu CSRF bị chặn (403)', $r['status'] === 403);
    $staff->get('/staff/checkin');
    $r = $staff->ajax('/staff/checkin', ['code' => $paid['code'], 'action' => 'lookup']);
    check('tra cứu AJAX (máy quét) trả JSON đủ thông tin', $r['status'] === 200 && ($r['json']['data']['state'] ?? '') === 'ready'
        && count($r['json']['data']['seats'] ?? []) === 2 && !isset($r['json']['data']['customerPhone']));

    // ---------- Soát vé: xác nhận ----------
    $staff->get('/staff/checkin?code=' . $paid['code']);
    $r = $staff->post('/staff/checkin', ['code' => $paid['code'], 'action' => 'confirm']);
    $row = $db->query('SELECT checkedInAt, checkedInBy FROM booking WHERE id = ?', [$paid['id']], true);
    check('cho vào rạp: báo thành công + tên phòng', str_contains($staff->follow($r)['body'], 'Đã soát vé ' . $paid['code']));
    check('ghi giờ soát + nhân viên soát', $row['checkedInAt'] !== null && (int)$row['checkedInBy'] === $staffId);

    $staff2->get('/staff/checkin?code=' . $paid['code']);
    check('quầy khác tra lại: hiện "Đã soát" + người soát, không có nút', str_contains(($r = $staff2->get('/staff/checkin?code=' . $paid['code']))['body'], 'Vé đã được soát lúc')
        && str_contains($r['body'], 'Nhân Viên A') && !str_contains($r['body'], 'CHO VÀO RẠP'));
    $r = $staff2->post('/staff/checkin', ['code' => $paid['code'], 'action' => 'confirm']);
    check('soát lần 2 bị từ chối, không đổi người soát', str_contains($staff2->follow($r)['body'], 'đã được soát')
        && (int)$db->query('SELECT checkedInBy FROM booking WHERE id = ?', [$paid['id']], true)['checkedInBy'] === $staffId);

    $staff->get('/staff/checkin');
    $r = $staff->post('/staff/checkin', ['code' => $paidLater['code'], 'action' => 'confirm']);
    check('xác nhận vé chưa tới giờ bị từ chối (gửi thẳng POST)', str_contains($staff->follow($r)['body'], 'Chưa tới giờ')
        && $db->query('SELECT checkedInAt FROM booking WHERE id = ?', [$paidLater['id']], true)['checkedInAt'] === null);

    // Khách đã vào rạp thì không tự hủy vé được nữa
    $customer->get('/user/bookings');
    $r = $customer->post('/user/cancel-booking/' . $paid['id'], []);
    check('vé đã soát: khách không hủy được', $db->query('SELECT status FROM booking WHERE id = ?', [$paid['id']], true)['status'] === 'paid');

    // ---------- Soát vé: thu tiền "trả tại quầy" ----------
    $pointsBefore = (int)$db->query('SELECT points FROM users WHERE id = ?', [$custId], true)['points'];
    $r = $staff->get('/staff/checkin?code=' . $cash['code']);
    check('vé trả tại quầy: yêu cầu thu tiền trước', str_contains($r['body'], 'Khách chưa thanh toán: thu ' . \CommonHelper::money($cashTotal))
        && str_contains($r['body'], 'name="collect_cash"'));
    $r = $staff->post('/staff/checkin', ['code' => $cash['code'], 'action' => 'confirm']);
    check('chưa tick "Đã thu tiền" thì không cho vào', str_contains($staff->follow($r)['body'], 'Khách chưa thanh toán')
        && $db->query('SELECT status FROM booking WHERE id = ?', [$cash['id']], true)['status'] === 'pending');
    $staff->get('/staff/checkin?code=' . $cash['code']);
    $r = $staff->post('/staff/checkin', ['code' => $cash['code'], 'action' => 'confirm', 'collect_cash' => '1']);
    $bk = $db->query('SELECT status, checkedInAt, pointsEarned FROM booking WHERE id = ?', [$cash['id']], true);
    check('thu tiền + cho vào: đơn paid và đã soát', str_contains($staff->follow($r)['body'], 'Đã soát vé') && $bk['status'] === 'paid' && $bk['checkedInAt'] !== null);
    check('payment tiền mặt chuyển success', $db->query("SELECT status FROM payment WHERE bookingId = ? AND method = 'cash'", [$cash['id']], true)['status'] === 'success');
    check('thu tại quầy vẫn cộng điểm + lịch sử như thanh toán online', (int)$db->query('SELECT points FROM users WHERE id = ?', [$custId], true)['points'] === $pointsBefore + (int)$bk['pointsEarned']
        && (int)$db->query('SELECT COUNT(*) c FROM point_history WHERE bookingId = ?', [$cash['id']], true)['c'] === ((int)$bk['pointsEarned'] > 0 ? 1 : 0));

    $recent = $staff->get('/staff/checkin');
    check('danh sách "Vừa soát hôm nay" có 2 vé vừa soát', str_contains($recent['body'], $paid['code']) && str_contains($recent['body'], $cash['code']));

    // ---------- POS ----------
    $pos = $staff->get('/staff/pos');
    check('trang POS 200: lưới sản phẩm + giỏ + phương thức', $pos['status'] === 200 && substr_count($pos['body'], 'class="pos-item"') >= count($products)
        && str_contains($pos['body'], 'id="cartList"') && str_contains($pos['body'], 'value="transfer"'));

    $p0 = $products[0];
    $p1 = $products[1];
    $expected = (int)$p0['price'] * 2 + (int)$p1['price'];

    $r = $staff->post('/staff/pos/checkout', ['items' => [], 'method' => 'cash', 'received' => 100000]);
    check('giỏ rỗng bị từ chối', str_contains($staff->follow($r)['body'], 'Chưa chọn sản phẩm'));
    $staff->get('/staff/pos');
    $r = $staff->post('/staff/pos/checkout', ['items' => [$p0['id'] => 2, $p1['id'] => 1], 'method' => 'cash', 'received' => $expected - 1000]);
    check('tiền khách đưa thiếu bị từ chối', str_contains($staff->follow($r)['body'], 'chưa đủ'));
    $staff->get('/staff/pos');
    $r = $staff->post('/staff/pos/checkout', ['items' => [$p0['id'] => 51], 'method' => 'cash', 'received' => 99999999]);
    check('quá 50 phần một món bị từ chối', str_contains($staff->follow($r)['body'], 'tối đa 50'));
    $staff->get('/staff/pos');
    $r = $staff->post('/staff/pos/checkout', ['items' => [999999 => 1], 'method' => 'cash', 'received' => 500000]);
    check('sản phẩm không tồn tại bị từ chối', str_contains($staff->follow($r)['body'], 'ngừng bán'));
    $staff->get('/staff/pos');
    $r = $staff->post('/staff/pos/checkout', ['items' => [$p0['id'] => 1], 'method' => 'bitcoin', 'received' => 500000]);
    check('phương thức lạ bị từ chối', str_contains($staff->follow($r)['body'], 'Phương thức thanh toán không hợp lệ'));
    $staff->get('/staff/pos');
    check('POS thiếu CSRF bị chặn (403)', $staff->request('POST', '/staff/pos/checkout', ['items' => [$p0['id'] => 1], 'method' => 'card'])['status'] === 403);
    check('chưa có đơn quầy nào được tạo từ các lần lỗi', (int)$db->query('SELECT COUNT(*) c FROM pos_order WHERE staffId = ?', [$staffId], true)['c'] === 0);

    // Khách vãng lai, tiền mặt, giá client gửi lên bị bỏ qua
    $staff->get('/staff/pos');
    $r = $staff->post('/staff/pos/checkout', ['items' => [$p0['id'] => 2, $p1['id'] => 1], 'method' => 'cash', 'received' => 500000, 'price' => 1, 'total' => 1]);
    $order = $db->query('SELECT * FROM pos_order WHERE staffId = ? ORDER BY id DESC LIMIT 1', [$staffId], true);
    $receipt = $staff->follow($r);
    check('bán tiền mặt: tạo đơn PS + tính tiền từ DB', $order && preg_match('/^PS[A-Z2-9]{8}$/', $order['code']) && (int)$order['totalAmount'] === $expected);
    check('ghi tiền khách đưa, không gắn khách, không điểm', (int)$order['receivedAmount'] === 500000 && $order['customerId'] === null && (int)$order['pointsEarned'] === 0);
    check('2 dòng sản phẩm với đơn giá chốt', count($db->query('SELECT * FROM pos_order_item WHERE posOrderId = ?', [$order['id']])) === 2
        && (int)$db->query('SELECT price FROM pos_order_item WHERE posOrderId = ? AND productId = ?', [$order['id'], $p0['id']], true)['price'] === (int)$p0['price']);
    check('hóa đơn hiện tiền thối đúng', $r['query'] === 'receipt=' . $order['id'] && str_contains($receipt['body'], 'Hóa đơn ' . $order['code'])
        && str_contains($receipt['body'], 'tiền thối ' . \CommonHelper::money(500000 - $expected)));

    // Thành viên tích điểm theo SĐT
    $staff->get('/staff/pos');
    $r = $staff->ajax('/staff/pos/checkout', ['action' => 'lookup_member', 'member' => '0966 000 005']);
    check('tra cứu thành viên theo SĐT (bỏ khoảng trắng)', $r['status'] === 200 && ($r['json']['data']['name'] ?? '') === 'Khách Năm');
    check('tra cứu thành viên theo email', ($staff->ajax('/staff/pos/checkout', ['action' => 'lookup_member', 'member' => 'KHACH' . strtoupper(DOMAIN)])['json']['data']['name'] ?? '') === 'Khách Năm');
    check('không tìm thấy / tài khoản bị khóa / nhân viên -> 404', $staff->ajax('/staff/pos/checkout', ['action' => 'lookup_member', 'member' => '0900999999'])['status'] === 404
        && $staff->ajax('/staff/pos/checkout', ['action' => 'lookup_member', 'member' => '0966000006'])['status'] === 404
        && $staff->ajax('/staff/pos/checkout', ['action' => 'lookup_member', 'member' => 'staff2' . DOMAIN])['status'] === 404);

    $before = $db->query('SELECT points, totalSpending FROM users WHERE id = ?', [$custId], true);
    $staff->get('/staff/pos');
    $r = $staff->post('/staff/pos/checkout', ['items' => [$p1['id'] => 3], 'method' => 'card', 'member' => '0966000005']);
    $order2 = $db->query('SELECT * FROM pos_order WHERE staffId = ? ORDER BY id DESC LIMIT 1', [$staffId], true);
    $after = $db->query('SELECT points, totalSpending FROM users WHERE id = ?', [$custId], true);
    $earned = App\Models\PointHistory::pointsFor((int)$p1['price'] * 3, 5);
    check('quẹt thẻ cho thành viên: gắn khách, không ghi tiền khách đưa', (int)$order2['customerId'] === $custId && $order2['method'] === 'card' && $order2['receivedAmount'] === null);
    check('cộng điểm 5% + chi tiêu cho thành viên', (int)$order2['pointsEarned'] === $earned
        && (int)$after['points'] === (int)$before['points'] + $earned && (int)$after['totalSpending'] === (int)$before['totalSpending'] + (int)$p1['price'] * 3);
    check('lịch sử điểm ghi mã đơn quầy', $earned === 0 || (int)$db->query('SELECT COUNT(*) c FROM point_history WHERE userId = ? AND note = ?', [$custId, 'Tích điểm mua tại quầy ' . $order2['code']], true)['c'] === 1);
    check('khách thấy điểm quầy ở trang Điểm thưởng', $earned === 0 || str_contains($customer->get('/user/points')['body'], 'Tích điểm mua tại quầy ' . $order2['code']));
    $staff->get('/staff/pos');
    $r = $staff->post('/staff/pos/checkout', ['items' => [$p1['id'] => 1], 'method' => 'card', 'member' => '0900999999']);
    check('SĐT thành viên không tồn tại: không tạo đơn', str_contains($staff->follow($r)['body'], 'Không tìm thấy thành viên')
        && (int)$db->query('SELECT COUNT(*) c FROM pos_order WHERE staffId = ?', [$staffId], true)['c'] === 2);

    // Thống kê + quyền xem hóa đơn
    $pos = $staff->get('/staff/pos');
    check('danh sách đơn hôm nay + tổng doanh thu quầy', str_contains($pos['body'], $order['code']) && str_contains($pos['body'], $order2['code'])
        && str_contains($pos['body'], '2 đơn · ' . \CommonHelper::money($expected + (int)$p1['price'] * 3)));
    check('nhân viên khác không xem được hóa đơn', !str_contains($staff2->get('/staff/pos?receipt=' . $order['id'])['body'], 'Hóa đơn ' . $order['code'])
        && !str_contains($staff2->get('/staff/pos')['body'], $order['code']));
    check('tổng quan hiện doanh thu quầy của ca', str_contains($staff->get('/staff')['body'], \CommonHelper::money($expected + (int)$p1['price'] * 3)));
} finally {
    stopServer($server);
    cleanupTestData($db, '%' . DOMAIN, $showtimes);
}

check('đã dọn dữ liệu test', (int)$db->query('SELECT COUNT(*) c FROM users WHERE email LIKE ?', ['%' . DOMAIN], true)['c'] === 0
    && (int)$db->query('SELECT COUNT(*) c FROM showtime WHERE id IN (' . implode(',', array_map('intval', $showtimes ?: [0])) . ')', [], true)['c'] === 0);
finish();
