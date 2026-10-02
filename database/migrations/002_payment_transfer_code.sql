-- =====================================================================
-- 002: Mã nội dung chuyển khoản riêng cho mỗi lần thanh toán SePay
-- (BK + 6-8 ký tự ngẫu nhiên, khác mã vé). Chạy 1 lần, không xóa dữ liệu:
--   mysql -u root cinema_booking < database/migrations/002_payment_transfer_code.sql
-- =====================================================================

ALTER TABLE payment
    ADD COLUMN transferCode VARCHAR(20) NULL UNIQUE AFTER transactionCode;
