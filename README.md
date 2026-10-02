# Cinema Booking

PHP 8.0+ | MySQL 8.0+ / MariaDB 10.6+ | MVC + Blade | Bootstrap 5 | jQuery

## Cài đặt

1. Chạy `composer install` (document root trỏ về thư mục `public/`).
2. Import `schema.sql` vào MySQL. **Lưu ý:** script này có `DROP DATABASE IF EXISTS cinema_booking`, chỉ dùng trên DB mới hoặc khi muốn reset dữ liệu.
3. Cấu hình `.env`: `APP_URL`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` (file `.env` không được commit).
   Thanh toán VNPay (tùy chọn): điền `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET` lấy từ [VNPay sandbox](https://sandbox.vnpayment.vn/devreg/); để trống thì chỉ có thanh toán tại quầy. Khai báo IPN URL `{APP_URL}/payment/vnpay-ipn` trong trang quản lý VNPay (cần URL public, không dùng được `localhost`).
   Nếu database đã import từ trước khi có SePay, chạy thêm `database/migrations/001_sepay.sql` (chỉ thêm bảng / giá trị ENUM, không xóa dữ liệu).

## Thanh toán chuyển khoản (SePay)

Khách chọn “Chuyển khoản ngân hàng”, quét VietQR (số tiền + nội dung chuyển khoản riêng cho mỗi lần thanh toán: `BK` + 6-8 chữ/số ngẫu nhiên, ví dụ `BK7Q2M9X`, khác mã vé). Nếu database có từ trước, chạy thêm `database/migrations/002_payment_transfer_code.sql`. SePay đọc biến động số dư TPBank rồi gọi webhook; web tự đánh dấu đơn đã thanh toán, cộng điểm, trang QR tự chuyển sang vé.

1. `.env`: `SEPAY_BANK=TPBank`, `SEPAY_ACCOUNT_NUMBER`, `SEPAY_ACCOUNT_NAME`, `SEPAY_API_KEY` (chuỗi ngẫu nhiên, đã tạo sẵn; đổi được).
2. Chạy qua ngrok để SePay gọi được về máy:
   ```powershell
   ngrok config add-authtoken <TOKEN>          # 1 lần, token tại dashboard.ngrok.com
   powershell -ExecutionPolicy Bypass -File scripts\ngrok-dev.ps1
   ```
   Script in ra URL public và Webhook URL. Mở web bằng URL ngrok (không dùng localhost) để link trả về đúng.
3. Trên [my.sepay.vn](https://my.sepay.vn): liên kết tài khoản TPBank → **Tích hợp Webhooks → Thêm Webhooks**: sự kiện *Có tiền vào*, URL = Webhook URL ở bước 2, kiểu chứng thực **API Key** = giá trị `SEPAY_API_KEY`.
4. Test: đặt vé → chuyển khoản số tiền nhỏ đúng nội dung → trang QR tự chuyển sang vé. Hoặc dùng nút **Gọi lại** trong nhật ký webhook của SePay. Request ngrok xem ở `http://127.0.0.1:4040`.

Mỗi lần chạy ngrok bản miễn phí URL đổi, cần cập nhật lại URL webhook trên SePay. Mọi giao dịch nhận được đều lưu ở bảng `sepay_transaction` (kể cả sai số tiền / không có mã vé) để đối soát; các trường hợp cần xử lý tay (sai số tiền, tiền về sau khi hết hạn giữ ghế) được ghi vào `storage/logs/app.log`.
4. Bật `mod_rewrite` nếu dùng Apache. Laragon: trỏ virtual host tới `public/`, ví dụ `http://booking-ticket-movie.test`. Có thể thử với PHP built-in server:
   ```sh
   php -S 127.0.0.1:8000 -t public public/index.php
   ```
   Khi dùng server này, đặt `APP_URL=http://127.0.0.1:8000` trong `.env`.
5. Chạy test (cần database mẫu đã import):
   - `php tests/phase1.php`: router, middleware, CSRF, session, Blade.
   - `php tests/phase2.php`: đăng ký / đăng nhập / đăng xuất qua HTTP thật. Test tự bật server ở cổng 18766, chỉ tạo và xóa user có email `*@phase2.test`.
   - `php tests/phase3.php`: trang phim, lịch chiếu, giữ ghế, voucher, điểm, VNPay (khóa giả, tự ký callback), thanh toán tại quầy, hết hạn giữ ghế. Test dùng cổng 18768, tự tạo rồi xóa suất chiếu / voucher `P3TEST*` / user `*@phase3.test`.
   - `php tests/sepay.php`: chuyển khoản SePay (giả lập webhook với API key giả): xác thực, trùng giao dịch, sai số tiền / tài khoản, tiền về muộn. Cổng 18769, dọn user `*@sepay.test`.
   - `php tests/phase4.php`: tài khoản khách (`/user/*`): hồ sơ, lịch sử vé, điểm thưởng, voucher. Các API dùng session có sẵn (không bật server).
   - `php tests/phase5.php`: nhân viên (`/staff/*`): quét mã vé check-in, bán tại quầy (POS) cho khách có tài khoản. Dùng session, không bật server.
   - `php tests/phase7.php`: trang quản trị (`/admin/*`): phân quyền, phim, rạp / phòng / ghế, suất chiếu (trùng giờ), voucher, hủy đơn, doanh thu + CSV, nhân viên, khóa khách, banner, bài viết, cài đặt. Cổng 18791, chỉ dọn dữ liệu `*@phase7.test` / `P7TEST*` / `p7test-*`, khôi phục cài đặt sau khi chạy.

   - `php tests/security.php`: header bảo mật, khóa tạm sau 5 lần đăng nhập sai, đổi mật khẩu đăng xuất phiên khác, chống dùng 1 lượng điểm / 1 lượt voucher cho nhiều đơn, giới hạn đơn "trả tại quầy", chặn chạy test qua web. Cổng 18793, dọn `*@sec.test` / `SECTEST*`.

   Database có từ trước Phase 7 cần chạy thêm `database/migrations/005_settings.sql` (bảng `setting` cho Admin > Cài đặt; chưa chạy thì web dùng giá trị mặc định).

**Tài khoản mẫu** (mật khẩu `123456`): `admin@cinema.vn`, `staff@cinema.vn`, `khach@gmail.com`.

**Hiện trạng:** Phase 1–7 hoàn tất. Trang quản trị `/admin/*` (giao diện theo bộ thiết kế 2.C.*): dashboard, phim, rạp → phòng → sơ đồ ghế, suất chiếu (timeline theo ngày, chặn trùng giờ có tính 15 phút dọn phòng), đơn đặt vé (hủy + hoàn điểm, hoàn tiền thủ công), voucher, thống kê doanh thu (lọc ngày / rạp / phim, xuất CSV, in PDF bằng trình duyệt), nhân viên, người dùng (khóa / mở), banner & tin tức, cài đặt (tên web, logo, liên hệ, thời gian giữ ghế, bật / tắt thanh toán tại quầy). Ảnh upload lưu ở `public/images/uploads/` (JPG / PNG / WebP ≤ 2MB). Khách xem phim, chọn suất chiếu, chọn ghế + bắp nước, dùng voucher / điểm, thanh toán VNPay / chuyển khoản SePay / tại quầy và nhận mã vé QR; quản lý hồ sơ, xem lịch sử vé / điểm / voucher của mình. Nhân viên quét mã vé để check-in, bán đồ ăn / nước tại quầy cho khách có tài khoản (tích điểm ngay). Ghế được giữ 10 phút; đơn quá hạn tự nhả ghế khi có người mở sơ đồ ghế hoặc trang thanh toán. Chưa làm: Admin quản lý rạp / phòng / ghế, voucher, báo cáo doanh thu chi tiết, người dùng, nhân viên, nội dung, cài đặt. Quên / đặt lại mật khẩu chưa làm. POST/PUT/DELETE yêu cầu token `csrf_token` (hoặc header `X-CSRF-Token`); token có thể lấy qua `AuthHelper::csrfToken()` hoặc meta tag trong layout. Đăng xuất dùng `POST /logout`, không phải GET.

## Bảo mật & vận hành

- Document root phải là `public/`. `.htaccess` ở gốc dự án chặn mọi truy cập trực tiếp (`.env`, `storage/`, `tests/`...) khi web server trỏ vào thư mục cha (vd `http://localhost/booking-ticket-movie/`). Nginx: tự cấu hình `root .../public;`.
- `APP_DEBUG=true` chỉ hiện chi tiết lỗi cho request từ 127.0.0.1; trên server thật vẫn nên đặt `APP_DEBUG=false`.
- Đổi mật khẩu 3 tài khoản mẫu (`123456`) trước khi đưa lên server.
- Nhả ghế quá hạn: MySQL event (cần `event_scheduler=ON`) hoặc cron `php scripts/release-expired.php` mỗi phút. Windows: `powershell -ExecutionPolicy Bypass -File scripts\install-cron.ps1`.

Bootstrap và jQuery hiện nạp từ CDN; các file local theo outline vẫn là file khung.

Xem kế hoạch trong `OUTLINE.md`.
