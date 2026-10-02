-- =====================================================================
-- 001: Thanh toán chuyển khoản qua SePay
-- Chạy 1 lần trên database đã có dữ liệu (không xóa dữ liệu):
--   mysql -u root cinema_booking < database/migrations/001_sepay.sql
-- schema.sql / cinema_booking.sql đã bao gồm thay đổi này cho lần import mới.
-- =====================================================================

ALTER TABLE payment
    MODIFY method ENUM('vnpay','momo','payos','sepay','cash') NOT NULL;

-- Mỗi giao dịch SePay gửi qua webhook (kể cả không khớp đơn nào) để đối soát.
-- UNIQUE(sepayId): SePay gửi lại cùng giao dịch khi retry -> chỉ xử lý 1 lần.
CREATE TABLE IF NOT EXISTS sepay_transaction (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sepayId         BIGINT UNSIGNED NOT NULL UNIQUE,     -- id giao dịch phía SePay
    gateway         VARCHAR(100)  NULL,                  -- TPBank, Vietcombank...
    transactionDate DATETIME      NULL,
    accountNumber   VARCHAR(50)   NULL,
    subAccount      VARCHAR(250)  NULL,
    code            VARCHAR(250)  NULL,                  -- mã thanh toán SePay tự nhận diện
    content         TEXT          NULL,                  -- nội dung chuyển khoản
    transferType    ENUM('in','out') NOT NULL,
    transferAmount  DECIMAL(15,0) NOT NULL DEFAULT 0,
    accumulated     DECIMAL(15,0) NULL,
    referenceCode   VARCHAR(255)  NULL,
    description     TEXT          NULL,
    bookingId       BIGINT UNSIGNED NULL,                -- đơn khớp được (nếu có)
    paymentId       BIGINT UNSIGNED NULL,
    result          VARCHAR(30)   NOT NULL,              -- paid | late | no_match | amount_mismatch | ignored ...
    createdAt       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sepay_booking (bookingId),
    CONSTRAINT fk_sepay_booking FOREIGN KEY (bookingId) REFERENCES booking(id) ON DELETE SET NULL,
    CONSTRAINT fk_sepay_payment FOREIGN KEY (paymentId) REFERENCES payment(id) ON DELETE SET NULL
) ENGINE=InnoDB;
