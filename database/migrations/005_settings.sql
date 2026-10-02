-- =====================================================================
-- 005: Bảng cài đặt hệ thống (trang Admin > Cài đặt)
-- Chạy 1 lần, không xóa dữ liệu:
--   mysql -u root cinema_booking < database/migrations/005_settings.sql
-- Chưa chạy migration thì web vẫn chạy với giá trị mặc định (App\Models\Setting::DEFAULTS).
-- =====================================================================

CREATE TABLE IF NOT EXISTS setting (
    name      VARCHAR(100) PRIMARY KEY,
    value     TEXT NULL,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
