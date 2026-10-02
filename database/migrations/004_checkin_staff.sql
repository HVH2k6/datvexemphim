-- =====================================================================
-- 004: Ghi nhận nhân viên soát vé (booking.checkedInBy)
-- Chạy 1 lần, không xóa dữ liệu:
--   mysql -u root cinema_booking < database/migrations/004_checkin_staff.sql
-- =====================================================================

ALTER TABLE booking
    ADD COLUMN checkedInBy BIGINT UNSIGNED NULL AFTER checkedInAt,
    ADD INDEX idx_booking_checkin (checkedInAt),
    ADD CONSTRAINT fk_bk_checkin_staff FOREIGN KEY (checkedInBy) REFERENCES users(id) ON DELETE SET NULL;
