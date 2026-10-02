-- =====================================================================
-- 003: Bán bắp nước tại quầy (POS) - đơn không gắn suất chiếu
-- booking_product bắt buộc thuộc 1 booking (có suất chiếu) nên cần bảng riêng.
-- Chạy 1 lần, không xóa dữ liệu:
--   mysql -u root cinema_booking < database/migrations/003_pos.sql
-- =====================================================================

CREATE TABLE IF NOT EXISTS pos_order (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code           VARCHAR(20)   NOT NULL UNIQUE,            -- mã hóa đơn quầy: PS + 8 ký tự
    staffId        BIGINT UNSIGNED NOT NULL,                  -- nhân viên bán
    customerId     BIGINT UNSIGNED NULL,                      -- thành viên tích điểm (nếu có)
    totalAmount    DECIMAL(12,0) NOT NULL DEFAULT 0,
    method         ENUM('cash','transfer','card') NOT NULL DEFAULT 'cash',
    receivedAmount DECIMAL(12,0) NULL,                        -- tiền khách đưa (tiền mặt)
    pointsEarned   INT UNSIGNED  NOT NULL DEFAULT 0,
    createdAt      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_created (createdAt),
    CONSTRAINT fk_pos_staff    FOREIGN KEY (staffId)    REFERENCES users(id),
    CONSTRAINT fk_pos_customer FOREIGN KEY (customerId) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pos_order_item (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    posOrderId BIGINT UNSIGNED NOT NULL,
    productId  BIGINT UNSIGNED NOT NULL,
    quantity   INT UNSIGNED  NOT NULL DEFAULT 1,
    price      DECIMAL(10,0) NOT NULL,                        -- đơn giá chốt
    UNIQUE KEY uq_pos_item (posOrderId, productId),
    CONSTRAINT fk_posi_order   FOREIGN KEY (posOrderId) REFERENCES pos_order(id) ON DELETE CASCADE,
    CONSTRAINT fk_posi_product FOREIGN KEY (productId)  REFERENCES product(id)
) ENGINE=InnoDB;
