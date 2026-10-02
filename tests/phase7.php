<?php
/**
 * Test Phase 6-7 (Admin) qua HTTP thật: php tests/phase7.php
 * Phân quyền, CSRF, phim, rạp / phòng / ghế, suất chiếu (trùng giờ), voucher, đơn (hủy + hoàn điểm),
 * doanh thu + CSV, nhân viên, khóa khách, banner, bài viết, cài đặt (giữ ghế, tại quầy).
 * Chỉ tạo / xóa dữ liệu *@phase7.test và bản ghi tên P7TEST / slug p7test-*. Cài đặt được khôi phục.
 */

// Chỉ chạy từ dòng lệnh: test tạo / xóa dữ liệu và bật server, không được gọi qua web
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/lib.php';

const PORT = 18791;
const DOMAIN = '@phase7.test';

$db = new DBHelper();
$showtimes = [];
$settingsBackup = $db->query('SELECT name, value FROM setting');

function cleanupAdminData(DBHelper $db)
{
    $cinemaIds = array_map('intval', array_column($db->query("SELECT id FROM cinema WHERE slug LIKE 'p7test-%'"), 'id'));
    if ($cinemaIds) {
        $in = implode(',', $cinemaIds);
        $db->execute("DELETE b FROM booking b JOIN showtime s ON s.id = b.showtimeId JOIN room r ON r.id = s.roomId WHERE r.cinemaId IN ($in)");
        $db->execute("DELETE s FROM showtime s JOIN room r ON r.id = s.roomId WHERE r.cinemaId IN ($in)");
        $db->execute("DELETE FROM cinema WHERE id IN ($in)");
    }
    $db->execute("DELETE s FROM showtime s JOIN movie m ON m.id = s.movieId WHERE m.slug LIKE 'p7test-%'");
    $db->execute("DELETE FROM movie WHERE slug LIKE 'p7test-%'");
    $db->execute("DELETE FROM voucher WHERE code LIKE 'P7TEST%'");
    $db->execute("DELETE FROM banner WHERE title LIKE 'P7TEST%'");
    $db->execute("DELETE FROM post WHERE slug LIKE 'p7test-%'");
}

function resetSettingCache()
{
    (new ReflectionProperty(App\Models\Setting::class, 'cache'))->setValue(null, null);
}

cleanupTestData($db, '%' . DOMAIN, []);
cleanupAdminData($db);
$server = startServer(PORT);

try {
    makeUser($db, 'admin' . DOMAIN, 'admin', ['name' => 'Admin Bảy']);
    $custId = makeUser($db, 'khach' . DOMAIN, 'customer', ['name' => 'Khách Bảy', 'phoneNumber' => '0977000007']);
    makeUser($db, 'nv' . DOMAIN, 'staff', ['name' => 'NV Bảy']);
    $genreId = (int)$db->query('SELECT id FROM genre ORDER BY id LIMIT 1', [], true)['id'];
    $cityId = (int)$db->query('SELECT id FROM city ORDER BY id LIMIT 1', [], true)['id'];

    $admin = new Client(PORT);
    check('admin đăng nhập chuyển tới /admin', $admin->login('admin' . DOMAIN, '123456')['path'] === '/admin');
    $customer = new Client(PORT);
    $customer->login('khach' . DOMAIN, '123456');
    $staffClient = new Client(PORT);
    $staffClient->login('nv' . DOMAIN, '123456');

    // ---------- Phân quyền + CSRF ----------
    check('khách chưa đăng nhập vào /admin -> /login', ($r = (new Client(PORT))->get('/admin'))['status'] === 302 && $r['path'] === '/login');
    check('khách hàng vào /admin/movies bị chặn (403)', $customer->get('/admin/movies')['status'] === 403);
    check('nhân viên vào /admin/revenue bị chặn (403)', $staffClient->get('/admin/revenue')['status'] === 403);
    check('nhân viên không gửi được form admin (403)', $staffClient->ajax('/admin/vouchers', ['code' => 'P7TESTX'])['status'] === 403);
    check('POST admin thiếu CSRF bị chặn (403)', $admin->request('POST', '/admin/vouchers', ['code' => 'P7TESTX'], true)['status'] === 403);
    $dash = $admin->get('/admin');
    check('dashboard 200: 4 ô số liệu + biểu đồ + giao dịch', $dash['status'] === 200 && substr_count($dash['body'], 'class="stat-card"') === 4
        && str_contains($dash['body'], 'id="weekChart"') && str_contains($dash['body'], '10 giao dịch mới nhất'));
    check('sidebar có đủ 11 mục', substr_count($dash['body'], '<i class="bi ') >= 11 && str_contains($dash['body'], 'Banner &amp; Tin tức'));

    // ---------- Phim ----------
    $r = $admin->ajax('/admin/movies', ['name' => '', 'slug' => 'Sai Slug', 'duration' => '0']);
    check('thêm phim thiếu dữ liệu: 422 + lỗi từng ô', $r['status'] === 422 && isset($r['json']['errors']['name'], $r['json']['errors']['slug'],
        $r['json']['errors']['duration'], $r['json']['errors']['genres'], $r['json']['errors']['image']));
    $movieId = (int)$db->insert('movie', ['name' => 'P7TEST Phim', 'slug' => 'p7test-phim', 'duration' => 90, 'releaseDate' => date('Y-m-d'), 'status' => 'coming_soon']);
    $movieForm = ['_method' => 'PUT', 'name' => 'P7TEST Phim Sửa', 'slug' => 'p7test-phim', 'duration' => '100', 'ageRating' => 'T13',
        'status' => 'now_showing', 'releaseDate' => date('Y-m-d'), 'noEndDate' => '1', 'genres' => [$genreId], 'trailerUrl' => 'https://youtube.com/watch?v=x'];
    $r = $admin->ajax('/admin/movies/' . $movieId, $movieForm);
    $m = $db->query('SELECT * FROM movie WHERE id = ?', [$movieId], true);
    check('sửa phim không cần ảnh mới: lưu tên, thời lượng, thể loại', ($r['json']['status'] ?? '') === 'success' && $m['name'] === 'P7TEST Phim Sửa'
        && (int)$m['duration'] === 100 && $m['endDate'] === null
        && (int)$db->query('SELECT COUNT(*) c FROM movie_genre WHERE movieId = ?', [$movieId], true)['c'] === 1);
    check('slug trùng phim khác bị từ chối', isset($admin->ajax('/admin/movies/' . $movieId, ['slug' => 'phim-hanh-dong-mau'] + $movieForm)['json']['errors']['slug']));
    check('trailer javascript: bị từ chối', isset($admin->ajax('/admin/movies/' . $movieId, ['trailerUrl' => 'javascript:alert(1)'] + $movieForm)['json']['errors']['trailerUrl']));
    $admin->post('/admin/movies/' . $movieId . '/hot', []);
    check('bật "Hot" cho phim', (int)$db->query('SELECT isHot FROM movie WHERE id = ?', [$movieId], true)['isHot'] === 1);
    check('danh sách phim lọc theo tên', str_contains($admin->get('/admin/movies?q=P7TEST')['body'], 'P7TEST Phim Sửa'));

    // ---------- Rạp / phòng / ghế ----------
    $r = $admin->ajax('/admin/cinemas', ['name' => 'P7TEST Rạp', 'slug' => 'p7test-rap', 'cityId' => $cityId, 'address' => '1 Test', 'status' => 'active']);
    $cinemaId = (int)$db->query("SELECT id FROM cinema WHERE slug = 'p7test-rap'", [], true)['id'];
    check('thêm rạp: chuyển sang bước thêm phòng', $cinemaId > 0 && str_ends_with($r['json']['redirect'] ?? '', '/admin/cinemas/' . $cinemaId . '/rooms'));
    check('thêm rạp thiếu địa chỉ / slug trùng bị từ chối', isset(($e = $admin->ajax('/admin/cinemas', ['name' => 'X', 'slug' => 'p7test-rap', 'cityId' => $cityId])['json']['errors'])['address'], $e['slug']));

    $admin->ajax('/admin/cinemas/' . $cinemaId . '/rooms', ['name' => 'P1', 'roomType' => 'standard', 'totalRows' => '5', 'totalCols' => '8', 'surcharge' => '0']);
    $room = $db->query('SELECT * FROM room WHERE cinemaId = ?', [$cinemaId], true);
    $roomId = (int)$room['id'];
    check('thêm phòng 5×8: sinh 36 ghế (3 hàng thường, 1 VIP, 4 Sweetbox)', (int)$db->query('SELECT COUNT(*) c FROM seat WHERE roomId = ?', [$roomId], true)['c'] === 36);
    check('tên phòng trùng trong rạp bị từ chối', isset($admin->ajax('/admin/cinemas/' . $cinemaId . '/rooms', ['name' => 'P1', 'roomType' => 'standard', 'totalRows' => '5', 'totalCols' => '8'])['json']['errors']['name']));
    $seats = $db->query("SELECT id FROM seat WHERE roomId = ? ORDER BY id", [$roomId]);
    $admin->get('/admin/rooms/' . $roomId . '/seats');
    $r = $admin->ajax('/admin/rooms/' . $roomId . '/seats', ['seat' => [$seats[0]['id'] => 'broken', $seats[1]['id'] => 'hidden'], 'surcharge' => '10000']);
    check('lưu sơ đồ: ghế hỏng, lối đi, phụ thu phòng', ($r['json']['status'] ?? '') === 'success'
        && $db->query('SELECT status FROM seat WHERE id = ?', [$seats[0]['id']], true)['status'] === 'broken'
        && $db->query('SELECT status FROM seat WHERE id = ?', [$seats[1]['id']], true)['status'] === 'hidden'
        && (int)$db->query('SELECT surcharge FROM room WHERE id = ?', [$roomId], true)['surcharge'] === 10000);
    check('trạng thái ghế lạ bị từ chối', $admin->ajax('/admin/rooms/' . $roomId . '/seats', ['seat' => [$seats[2]['id'] => 'gold'], 'surcharge' => '0'])['status'] === 422);

    // ---------- Suất chiếu ----------
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $stForm = ['movieId' => $movieId, 'roomId' => $roomId, 'date' => $tomorrow, 'time' => '10:00', 'format' => '2D', 'language' => 'sub', 'basePrice' => '90000', 'status' => 'open'];
    $r = $admin->ajax('/admin/showtimes', $stForm);
    $st = $db->query('SELECT * FROM showtime WHERE roomId = ? ORDER BY id DESC LIMIT 1', [$roomId], true);
    $showtimes[] = (int)($st['id'] ?? 0);
    check('thêm suất: giờ kết thúc = thời lượng + 15 phút dọn phòng', ($r['json']['status'] ?? '') === 'success'
        && $st['endTime'] === $tomorrow . ' 11:55:00');
    $r = $admin->ajax('/admin/showtimes', ['time' => '11:30'] + $stForm);
    check('suất trùng giờ cùng phòng bị từ chối, nêu suất bị trùng', $r['status'] === 422 && str_contains($r['json']['errors']['time'] ?? '', '10:00–11:55'));
    check('suất bắt đầu sau giờ dọn phòng được nhận', ($admin->ajax('/admin/showtimes', ['time' => '11:55'] + $stForm)['json']['status'] ?? '') === 'success');
    $showtimes[] = (int)$db->query('SELECT MAX(id) id FROM showtime WHERE roomId = ?', [$roomId], true)['id'];
    check('suất trong quá khứ bị từ chối', isset($admin->ajax('/admin/showtimes', ['date' => date('Y-m-d', strtotime('-1 day'))] + $stForm)['json']['errors']['time']));
    $timeline = $admin->get('/admin/showtimes?cinema=' . $cinemaId . '&room=' . $roomId . '&date=' . $tomorrow);
    check('timeline hiện 2 suất của phòng', substr_count($timeline['body'], 'class="show-block') === 2);
    $admin->ajax('/admin/showtimes/' . $showtimes[1], ['_method' => 'DELETE']);
    check('xóa suất chưa có vé', !$db->query('SELECT id FROM showtime WHERE id = ?', [$showtimes[1]], true));

    // ---------- Voucher ----------
    $vForm = ['code' => 'p7test10', 'name' => 'P7TEST giảm 10%', 'discountType' => 'percent', 'discountValue' => '10', 'maxDiscount' => '20000',
        'minOrder' => '0', 'quantity' => '5', 'perUserLimit' => '1', 'startDate' => date('Y-m-d'), 'endDate' => date('Y-m-d', strtotime('+7 day')), 'status' => 'active'];
    $admin->ajax('/admin/vouchers', $vForm);
    $v = $db->query("SELECT * FROM voucher WHERE code = 'P7TEST10'", [], true);
    check('tạo voucher: mã in hoa, hết hạn cuối ngày', $v && (int)$v['maxDiscount'] === 20000 && str_ends_with($v['endDate'], '23:59:59'));
    check('mã trùng / ngày kết thúc trước ngày bắt đầu / % > 100 bị từ chối',
        isset(($e = $admin->ajax('/admin/vouchers', ['endDate' => date('Y-m-d', strtotime('-3 day')), 'discountValue' => '150'] + $vForm)['json']['errors'])['code'], $e['endDate'], $e['discountValue']));
    $admin->ajax('/admin/vouchers/' . $v['id'] . '/toggle', []);
    check('tắt voucher', $db->query('SELECT status FROM voucher WHERE id = ?', [$v['id']], true)['status'] === 'inactive');
    $admin->ajax('/admin/vouchers/' . $v['id'], ['_method' => 'DELETE']);
    check('xóa voucher chưa dùng', !$db->query('SELECT id FROM voucher WHERE id = ?', [$v['id']], true));

    // ---------- Cài đặt (trước khi giữ ghế để đọc lại giá trị mới) ----------
    check('giữ ghế ngoài 5-30 phút bị từ chối', isset($admin->ajax('/admin/settings', ['hold_minutes' => '3'])['json']['errors']['hold_minutes']));
    $admin->ajax('/admin/settings', ['hold_minutes' => '15', 'site_phone' => '1900 1234', 'site_name' => 'P7TEST Cinema']);
    resetSettingCache();
    check('lưu cài đặt: giữ ghế 15 phút, tắt tại quầy (bỏ tick)', App\Models\Setting::holdMinutes() === 15 && !App\Models\Setting::cashEnabled());
    check('tên website + hotline hiện trên trang khách', str_contains(($home = (new Client(PORT))->get('/'))['body'], 'P7TEST Cinema') && str_contains($home['body'], '19001234'));
    $activeSeats = array_map('intval', array_column($db->query("SELECT id FROM seat WHERE roomId = ? AND status = 'active' ORDER BY id", [$roomId]), 'id'));
    $held = (new App\Models\Booking())->hold((new App\Models\User())->getUserById($custId), (new App\Models\Showtime())->getDetail($showtimes[0]), [$activeSeats[5]], []);
    $expires = strtotime($db->query('SELECT expiredAt FROM booking WHERE id = ?', [$held['id']], true)['expiredAt']);
    check('đặt vé giữ ghế theo cài đặt (15 phút)', abs($expires - (time() + 15 * 60)) <= 5);
    $customer->get('/checkout/' . $held['id']);
    $r = $customer->post('/checkout/' . $held['id'] . '/pay', ['method' => 'cash']);
    check('tắt tại quầy: khách không chọn được thanh toán tại quầy', str_contains($customer->follow($r)['body'], 'tạm ngưng nhận thanh toán tại quầy')
        && !$db->query("SELECT id FROM payment WHERE bookingId = ? AND method = 'cash'", [$held['id']], true));
    $admin->ajax('/admin/settings/reset', []);
    resetSettingCache();
    check('đặt lại mặc định', App\Models\Setting::holdMinutes() === 10 && App\Models\Setting::cashEnabled());

    // ---------- Đơn + doanh thu ----------
    $pointsBefore = (int)$db->query('SELECT points FROM users WHERE id = ?', [$custId], true)['points'];
    $paid = makePaidBooking($db, $custId, $showtimes[0], [$activeSeats[0], $activeSeats[1]]);
    $rev = $admin->get('/admin/revenue?cinemas[]=' . $cinemaId);
    check('doanh thu lọc theo rạp: có phim + đúng tổng', str_contains($rev['body'], 'P7TEST Phim Sửa') && str_contains($rev['body'], \CommonHelper::money($paid['totalAmount'])));
    $csv = $admin->get('/admin/revenue/export?cinemas[]=' . $cinemaId);
    check('xuất CSV: BOM + tiêu đề + dòng phim', str_starts_with($csv['body'], "\xEF\xBB\xBF") && str_contains($csv['body'], 'Phim,Rạp') && str_contains($csv['body'], 'P7TEST Phim Sửa'));
    check('chi tiết đơn hiện ghế + thanh toán', str_contains(($d = $admin->get('/admin/bookings/' . $paid['id']))['body'], $paid['code']) && str_contains($d['body'], 'Chuyển khoản'));
    check('tìm đơn theo mã', str_contains($admin->get('/admin/bookings?q=' . $paid['code'])['body'], $paid['code']));
    $admin->post('/admin/bookings/' . $paid['id'] . '/cancel', []);
    $bk = $db->query('SELECT status FROM booking WHERE id = ?', [$paid['id']], true);
    check('admin hủy đơn đã trả: nhả ghế, payment refunded', $bk['status'] === 'cancelled'
        && (int)$db->query('SELECT COUNT(*) c FROM booking_seat WHERE bookingId = ?', [$paid['id']], true)['c'] === 0
        && $db->query('SELECT status FROM payment WHERE bookingId = ?', [$paid['id']], true)['status'] === 'refunded');
    check('hủy đơn: điểm tích được thu hồi', (int)$db->query('SELECT points FROM users WHERE id = ?', [$custId], true)['points'] === $pointsBefore);
    check('đơn đã hủy rời khỏi doanh thu', str_contains($admin->get('/admin/revenue?cinemas[]=' . $cinemaId)['body'], 'Không có đơn đã thanh toán trong kỳ này'));
    check('xóa suất đã có đơn bị từ chối', str_contains($admin->follow($admin->post('/admin/showtimes/' . $showtimes[0], ['_method' => 'DELETE']))['body'], 'đã có đơn')
        && $db->query('SELECT id FROM showtime WHERE id = ?', [$showtimes[0]], true));
    check('xóa phim đã có suất bị từ chối', $admin->ajax('/admin/movies/' . $movieId, ['_method' => 'DELETE']) && $db->query('SELECT id FROM movie WHERE id = ?', [$movieId], true));

    // ---------- Nhân viên ----------
    $r = $admin->ajax('/admin/staff', ['name' => 'NV Mới', 'email' => 'NVMOI' . strtoupper(DOMAIN), 'password' => '123456', 'status' => 'active']);
    $newStaff = $db->query("SELECT u.id, r.name AS role FROM users u JOIN role r ON r.id = u.roleId WHERE u.email = ?", ['nvmoi' . DOMAIN], true);
    check('thêm nhân viên: email chữ thường, role staff', ($r['json']['status'] ?? '') === 'success' && $newStaff && $newStaff['role'] === 'staff');
    check('nhân viên mới đăng nhập được vào /staff', (new Client(PORT))->login('nvmoi' . DOMAIN, '123456')['path'] === '/staff');
    check('email trùng / mật khẩu ngắn bị từ chối', isset(($e = $admin->ajax('/admin/staff', ['name' => 'X', 'email' => 'nvmoi' . DOMAIN, 'password' => '1'])['json']['errors'])['email'], $e['password']));
    $admin->ajax('/admin/staff/' . $newStaff['id'] . '/reset-password', ['password' => 'matkhau7']);
    check('đặt lại mật khẩu nhân viên', (new Client(PORT))->login('nvmoi' . DOMAIN, 'matkhau7')['path'] === '/staff');
    $admin->ajax('/admin/staff/' . $newStaff['id'] . '/toggle', []);
    check('khóa nhân viên: không đăng nhập được', (new Client(PORT))->login('nvmoi' . DOMAIN, 'matkhau7')['path'] !== '/staff');
    check('không sửa khách hàng qua mục nhân viên (404)', $admin->ajax('/admin/staff/' . $custId . '/toggle', [])['status'] === 404);

    // ---------- Người dùng ----------
    check('khung chi tiết khách: lịch sử vé', str_contains(($u = $admin->get('/admin/users?view=' . $custId))['body'], 'Chi tiết khách') && str_contains($u['body'], $paid['code']));
    $admin->post('/admin/users/' . $custId . '/block', ['back' => '/admin/users?view=' . $custId]);
    check('khóa khách: khách bị đăng xuất', $db->query('SELECT status FROM users WHERE id = ?', [$custId], true)['status'] === 'blocked'
        && $customer->get('/user/profile')['path'] === '/login');
    $admin->post('/admin/users/' . $custId . '/block', ['back' => 'https://evil.example/']);
    check('mở khóa khách; link quay lại bên ngoài bị bỏ qua', $db->query('SELECT status FROM users WHERE id = ?', [$custId], true)['status'] === 'active');

    // ---------- Banner + bài viết ----------
    check('thêm banner thiếu ảnh bị từ chối', isset($admin->ajax('/admin/content/banners', ['title' => 'P7TEST'])['json']['errors']['image']));
    $b1 = (int)$db->insert('banner', ['title' => 'P7TEST 1', 'image' => '/uploads/banners/x.jpg', 'sortOrder' => 900]);
    $b2 = (int)$db->insert('banner', ['title' => 'P7TEST 2', 'image' => '/uploads/banners/y.jpg', 'sortOrder' => 901]);
    $admin->ajax('/admin/content/banners/' . $b2 . '/move', ['direction' => 'up']);
    check('đổi thứ tự banner', (int)$db->query('SELECT sortOrder FROM banner WHERE id = ?', [$b2], true)['sortOrder']
        < (int)$db->query('SELECT sortOrder FROM banner WHERE id = ?', [$b1], true)['sortOrder']);
    check('link banner javascript: bị từ chối', isset($admin->ajax('/admin/content/banners/' . $b1, ['_method' => 'PUT', 'link' => 'javascript:alert(1)'])['json']['errors']['link']));
    $r = $admin->ajax('/admin/content/posts', ['title' => 'P7TEST Bài', 'slug' => 'p7test-bai', 'content' => 'Nội dung <b>test</b>', 'type' => 'promotion', 'status' => 'published']);
    $post = $db->query("SELECT * FROM post WHERE slug = 'p7test-bai'", [], true);
    check('đăng bài khuyến mãi, ghi tác giả', ($r['json']['status'] ?? '') === 'success' && $post && $post['type'] === 'promotion' && $post['authorId'] !== null);
    check('slug bài trùng bị từ chối', isset($admin->ajax('/admin/content/posts', ['title' => 'X', 'slug' => 'p7test-bai', 'content' => 'x', 'type' => 'news'])['json']['errors']['slug']));
    check('trang viết bài + sửa bài 200', $admin->get('/admin/content/posts?new=1')['status'] === 200 && str_contains($admin->get('/admin/content/posts?edit=' . $post['id'])['body'], 'P7TEST Bài'));
    $admin->ajax('/admin/content/posts/' . $post['id'], ['_method' => 'DELETE']);
    check('xóa bài', !$db->query('SELECT id FROM post WHERE id = ?', [$post['id']], true));

    check('id không phải số -> 404', $admin->get('/admin/bookings/abc')['status'] === 404 && $admin->ajax('/admin/movies/1x', ['_method' => 'DELETE'])['status'] === 404);
} finally {
    stopServer($server);
    cleanupTestData($db, '%' . DOMAIN, $showtimes);
    cleanupAdminData($db);
    $db->execute('DELETE FROM setting');
    foreach ($settingsBackup as $row) {
        $db->insert('setting', $row);
    }
}

check('đã dọn dữ liệu test', (int)$db->query('SELECT COUNT(*) c FROM users WHERE email LIKE ?', ['%' . DOMAIN], true)['c'] === 0
    && !$db->query("SELECT id FROM movie WHERE slug LIKE 'p7test-%'", [], true) && !$db->query("SELECT id FROM cinema WHERE slug LIKE 'p7test-%'", [], true));
finish();
