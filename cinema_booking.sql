-- =====================================================================
--  CINEMA BOOKING SYSTEM (mô phỏng CGV ~70% tính năng)
--  MySQL 8.0+ / MariaDB 10.6+   |   Charset: utf8mb4
--  Quy ước: tên bảng số ít (giữ nguyên `users` như thiết kế cũ),
--           tên cột camelCase giống schema hiện tại (roleId, phoneNumber...)
-- =====================================================================

DROP DATABASE IF EXISTS cinema_booking;
CREATE DATABASE cinema_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cinema_booking;

-- =====================================================================
-- 1. NGƯỜI DÙNG & PHÂN QUYỀN
-- =====================================================================

CREATE TABLE role (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL UNIQUE,          -- admin | staff | customer
    description VARCHAR(255) NULL
) ENGINE=InnoDB;

-- Hạng thành viên (CGV: Member / VIP / VVIP) - xét theo tổng chi tiêu
CREATE TABLE member_level (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50)   NOT NULL UNIQUE,
    minSpending     DECIMAL(12,0) NOT NULL DEFAULT 0,   -- chi tiêu tối thiểu để đạt hạng
    pointRate       DECIMAL(5,2)  NOT NULL DEFAULT 5,   -- % tích điểm trên giá trị đơn
    discountPercent DECIMAL(5,2)  NOT NULL DEFAULT 0    -- giảm giá riêng cho hạng
) ENGINE=InnoDB;

CREATE TABLE users (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,                -- password_hash() của PHP
    phoneNumber   VARCHAR(20)  NULL UNIQUE,
    birthday      DATE         NULL,
    gender        ENUM('male','female','other') NULL,
    avatar        VARCHAR(255) NULL,
    roleId        BIGINT UNSIGNED NOT NULL,
    memberLevelId BIGINT UNSIGNED NULL,
    points        INT UNSIGNED  NOT NULL DEFAULT 0,     -- điểm thưởng hiện có
    totalSpending DECIMAL(12,0) NOT NULL DEFAULT 0,     -- tổng chi tiêu (để xét hạng)
    status        ENUM('active','blocked') NOT NULL DEFAULT 'active',
    rememberToken VARCHAR(100) NULL,
    resetToken    VARCHAR(100) NULL,
    resetExpiredAt DATETIME    NULL,
    createdAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role  FOREIGN KEY (roleId)        REFERENCES role(id),
    CONSTRAINT fk_users_level FOREIGN KEY (memberLevelId) REFERENCES member_level(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 2. PHIM
-- =====================================================================

CREATE TABLE genre (
    id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE movie (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    slug        VARCHAR(255) NOT NULL UNIQUE,           -- /phim/lat-mat-8
    image       VARCHAR(255) NULL,                      -- poster dọc
    banner      VARCHAR(255) NULL,                      -- ảnh ngang cho slider
    listImage   JSON         NULL,                      -- ảnh phụ / still
    trailerUrl  VARCHAR(255) NULL,                      -- link YouTube
    description TEXT         NULL,
    director    VARCHAR(255) NULL,
    actors      VARCHAR(500) NULL,
    duration    INT UNSIGNED NOT NULL,                  -- phút
    language    VARCHAR(100) NULL,                      -- "Tiếng Anh - Phụ đề Tiếng Việt"
    country     VARCHAR(100) NULL,
    ageRating   ENUM('P','K','T13','T16','T18') NOT NULL DEFAULT 'P',
    releaseDate DATE NOT NULL,                          -- ngày khởi chiếu
    endDate     DATE NULL,                              -- ngày dừng chiếu dự kiến
    status      ENUM('coming_soon','now_showing','ended') NOT NULL DEFAULT 'coming_soon',
    isHot       TINYINT(1) NOT NULL DEFAULT 0,
    avgRating   DECIMAL(3,1) NOT NULL DEFAULT 0,        -- cache điểm đánh giá
    createdAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_movie_status (status, releaseDate)
) ENGINE=InnoDB;

CREATE TABLE movie_genre (
    movieId BIGINT UNSIGNED NOT NULL,
    genreId BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (movieId, genreId),
    CONSTRAINT fk_mg_movie FOREIGN KEY (movieId) REFERENCES movie(id) ON DELETE CASCADE,
    CONSTRAINT fk_mg_genre FOREIGN KEY (genreId) REFERENCES genre(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 3. RẠP - PHÒNG CHIẾU - GHẾ
-- =====================================================================

CREATE TABLE city (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(100) NOT NULL UNIQUE,
    sortOrder INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE cinema (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cityId    BIGINT UNSIGNED NOT NULL,
    name      VARCHAR(150) NOT NULL,                    -- CGV Vincom Vinh
    slug      VARCHAR(170) NOT NULL UNIQUE,
    address   VARCHAR(255) NOT NULL,
    phone     VARCHAR(20)  NULL,
    image     VARCHAR(255) NULL,
    mapUrl    VARCHAR(500) NULL,                        -- Google Maps embed
    status    ENUM('active','inactive') NOT NULL DEFAULT 'active',
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cinema_city FOREIGN KEY (cityId) REFERENCES city(id)
) ENGINE=InnoDB;

CREATE TABLE room (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cinemaId  BIGINT UNSIGNED NOT NULL,
    name      VARCHAR(50) NOT NULL,                     -- Cinema 1, Cinema 2...
    roomType  ENUM('standard','imax','4dx','gold_class') NOT NULL DEFAULT 'standard',
    totalRows TINYINT UNSIGNED NOT NULL,
    totalCols TINYINT UNSIGNED NOT NULL,
    surcharge DECIMAL(10,0) NOT NULL DEFAULT 0,         -- phụ thu theo loại phòng
    status    ENUM('active','maintenance') NOT NULL DEFAULT 'active',
    UNIQUE KEY uq_room (cinemaId, name),
    CONSTRAINT fk_room_cinema FOREIGN KEY (cinemaId) REFERENCES cinema(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Loại ghế: Thường / VIP / Sweetbox (ghế đôi)
CREATE TABLE seat_type (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50) NOT NULL UNIQUE,
    code       VARCHAR(20) NOT NULL UNIQUE,             -- standard | vip | sweetbox
    extraPrice DECIMAL(10,0) NOT NULL DEFAULT 0,        -- cộng thêm vào giá suất chiếu
    color      VARCHAR(20) NULL                         -- màu hiển thị trên sơ đồ ghế
) ENGINE=InnoDB;

CREATE TABLE seat (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    roomId     BIGINT UNSIGNED NOT NULL,
    seatTypeId BIGINT UNSIGNED NOT NULL,
    rowLabel   CHAR(2) NOT NULL,                        -- A, B, C...
    seatNumber TINYINT UNSIGNED NOT NULL,               -- 1, 2, 3...
    status     ENUM('active','broken','hidden') NOT NULL DEFAULT 'active', -- hidden = lối đi
    UNIQUE KEY uq_seat (roomId, rowLabel, seatNumber),
    CONSTRAINT fk_seat_room FOREIGN KEY (roomId)     REFERENCES room(id) ON DELETE CASCADE,
    CONSTRAINT fk_seat_type FOREIGN KEY (seatTypeId) REFERENCES seat_type(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 4. LỊCH CHIẾU (SUẤT CHIẾU)
-- =====================================================================

CREATE TABLE showtime (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    movieId   BIGINT UNSIGNED NOT NULL,
    roomId    BIGINT UNSIGNED NOT NULL,
    startTime DATETIME NOT NULL,
    endTime   DATETIME NOT NULL,                        -- = startTime + duration + dọn phòng
    format    ENUM('2D','3D') NOT NULL DEFAULT '2D',
    language  ENUM('sub','dub') NOT NULL DEFAULT 'sub', -- phụ đề / lồng tiếng
    basePrice DECIMAL(10,0) NOT NULL,                   -- giá vé ghế thường
    status    ENUM('open','closed','cancelled') NOT NULL DEFAULT 'open',
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_showtime_movie (movieId, startTime),
    INDEX idx_showtime_room  (roomId, startTime),
    CONSTRAINT fk_st_movie FOREIGN KEY (movieId) REFERENCES movie(id),
    CONSTRAINT fk_st_room  FOREIGN KEY (roomId)  REFERENCES room(id),
    CONSTRAINT chk_st_time CHECK (endTime > startTime)
) ENGINE=InnoDB;

-- =====================================================================
-- 5. BẮP NƯỚC (CONCESSION) & KHUYẾN MÃI
-- =====================================================================

CREATE TABLE product (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,                  -- CGV Combo, My Combo...
    image       VARCHAR(255) NULL,
    description VARCHAR(500) NULL,
    type        ENUM('combo','food','drink') NOT NULL DEFAULT 'combo',
    price       DECIMAL(10,0) NOT NULL,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE voucher (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(50)  NOT NULL UNIQUE,
    name          VARCHAR(150) NOT NULL,
    discountType  ENUM('percent','fixed') NOT NULL,
    discountValue DECIMAL(10,0) NOT NULL,
    maxDiscount   DECIMAL(10,0) NULL,                   -- trần giảm cho loại percent
    minOrder      DECIMAL(10,0) NOT NULL DEFAULT 0,
    quantity      INT UNSIGNED NOT NULL DEFAULT 0,      -- tổng lượt dùng
    usedCount     INT UNSIGNED NOT NULL DEFAULT 0,
    perUserLimit  INT UNSIGNED NOT NULL DEFAULT 1,
    startDate     DATETIME NOT NULL,
    endDate       DATETIME NOT NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

-- =====================================================================
-- 6. ĐẶT VÉ - THANH TOÁN
-- =====================================================================

CREATE TABLE booking (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code           VARCHAR(20) NOT NULL UNIQUE,         -- mã vé để quét QR tại quầy
    userId         BIGINT UNSIGNED NOT NULL,
    showtimeId     BIGINT UNSIGNED NOT NULL,
    voucherId      BIGINT UNSIGNED NULL,
    ticketAmount   DECIMAL(12,0) NOT NULL DEFAULT 0,    -- tổng tiền ghế
    productAmount  DECIMAL(12,0) NOT NULL DEFAULT 0,    -- tổng tiền bắp nước
    discountAmount DECIMAL(12,0) NOT NULL DEFAULT 0,    -- voucher + hạng thành viên
    pointsUsed     INT UNSIGNED  NOT NULL DEFAULT 0,    -- 1 điểm = 1.000đ (tùy chỉnh)
    totalAmount    DECIMAL(12,0) NOT NULL DEFAULT 0,    -- số tiền phải trả
    pointsEarned   INT UNSIGNED  NOT NULL DEFAULT 0,
    status         ENUM('pending','paid','cancelled','expired') NOT NULL DEFAULT 'pending',
    expiredAt      DATETIME NOT NULL,                   -- giữ ghế ~10 phút
    checkedInAt    DATETIME NULL,                       -- nhân viên soát vé
    checkedInBy    BIGINT UNSIGNED NULL,                -- id nhân viên đã soát
    createdAt      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_booking_user   (userId, createdAt),
    INDEX idx_booking_status (status, expiredAt),
    INDEX idx_booking_checkin (checkedInAt),
    CONSTRAINT fk_bk_user     FOREIGN KEY (userId)     REFERENCES users(id),
    CONSTRAINT fk_bk_showtime FOREIGN KEY (showtimeId) REFERENCES showtime(id),
    CONSTRAINT fk_bk_voucher  FOREIGN KEY (voucherId)  REFERENCES voucher(id) ON DELETE SET NULL,
    CONSTRAINT fk_bk_checkin_staff FOREIGN KEY (checkedInBy) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Mỗi dòng = 1 vé (1 ghế).
-- UNIQUE(showtimeId, seatId) là "khóa" chống 2 người đặt trùng ghế
-- -> khi booking bị hủy/hết hạn phải XÓA dòng ở đây để nhả ghế.
CREATE TABLE booking_seat (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bookingId  BIGINT UNSIGNED NOT NULL,
    showtimeId BIGINT UNSIGNED NOT NULL,
    seatId     BIGINT UNSIGNED NOT NULL,
    price      DECIMAL(10,0) NOT NULL,                  -- giá chốt tại thời điểm đặt
    UNIQUE KEY uq_showtime_seat (showtimeId, seatId),
    CONSTRAINT fk_bs_booking  FOREIGN KEY (bookingId)  REFERENCES booking(id) ON DELETE CASCADE,
    CONSTRAINT fk_bs_showtime FOREIGN KEY (showtimeId) REFERENCES showtime(id),
    CONSTRAINT fk_bs_seat     FOREIGN KEY (seatId)     REFERENCES seat(id)
) ENGINE=InnoDB;

CREATE TABLE booking_product (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bookingId BIGINT UNSIGNED NOT NULL,
    productId BIGINT UNSIGNED NOT NULL,
    quantity  INT UNSIGNED NOT NULL DEFAULT 1,
    price     DECIMAL(10,0) NOT NULL,                   -- đơn giá chốt
    UNIQUE KEY uq_bp (bookingId, productId),
    CONSTRAINT fk_bp_booking FOREIGN KEY (bookingId) REFERENCES booking(id) ON DELETE CASCADE,
    CONSTRAINT fk_bp_product FOREIGN KEY (productId) REFERENCES product(id)
) ENGINE=InnoDB;

CREATE TABLE payment (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bookingId       BIGINT UNSIGNED NOT NULL,
    method          ENUM('vnpay','momo','payos','sepay','cash') NOT NULL,
    amount          DECIMAL(12,0) NOT NULL,
    transactionCode VARCHAR(100) NULL UNIQUE,           -- mã GD từ cổng thanh toán
    transferCode    VARCHAR(20)  NULL UNIQUE,           -- nội dung chuyển khoản SePay (BK + 6-8 ký tự)
    status          ENUM('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
    rawResponse     JSON NULL,                          -- lưu callback/IPN để đối soát
    paidAt          DATETIME NULL,
    createdAt       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_booking (bookingId),
    CONSTRAINT fk_pay_booking FOREIGN KEY (bookingId) REFERENCES booking(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Mỗi giao dịch SePay gửi qua webhook (kể cả không khớp đơn nào) để đối soát.
-- UNIQUE(sepayId): SePay gửi lại cùng giao dịch khi retry -> chỉ xử lý 1 lần.
CREATE TABLE sepay_transaction (
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

-- Bán bắp nước tại quầy (POS): đơn không gắn suất chiếu
CREATE TABLE pos_order (
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

CREATE TABLE pos_order_item (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    posOrderId BIGINT UNSIGNED NOT NULL,
    productId  BIGINT UNSIGNED NOT NULL,
    quantity   INT UNSIGNED  NOT NULL DEFAULT 1,
    price      DECIMAL(10,0) NOT NULL,                        -- đơn giá chốt
    UNIQUE KEY uq_pos_item (posOrderId, productId),
    CONSTRAINT fk_posi_order   FOREIGN KEY (posOrderId) REFERENCES pos_order(id) ON DELETE CASCADE,
    CONSTRAINT fk_posi_product FOREIGN KEY (productId)  REFERENCES product(id)
) ENGINE=InnoDB;

-- Lịch sử điểm thưởng (cộng khi thanh toán, trừ khi dùng điểm)
CREATE TABLE point_history (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    userId    BIGINT UNSIGNED NOT NULL,
    bookingId BIGINT UNSIGNED NULL,
    points    INT NOT NULL,                             -- dương = cộng, âm = trừ
    note      VARCHAR(255) NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ph_user    FOREIGN KEY (userId)    REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ph_booking FOREIGN KEY (bookingId) REFERENCES booking(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 7. NỘI DUNG: ĐÁNH GIÁ, BANNER, TIN TỨC / ƯU ĐÃI
-- =====================================================================

CREATE TABLE review (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    userId    BIGINT UNSIGNED NOT NULL,
    movieId   BIGINT UNSIGNED NOT NULL,
    rating    TINYINT UNSIGNED NOT NULL,                -- 1..10
    content   TEXT NULL,
    status    ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review (userId, movieId),             -- mỗi người 1 đánh giá / phim
    CONSTRAINT fk_rv_user  FOREIGN KEY (userId)  REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_rv_movie FOREIGN KEY (movieId) REFERENCES movie(id) ON DELETE CASCADE,
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 10)
) ENGINE=InnoDB;

CREATE TABLE banner (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title     VARCHAR(255) NULL,
    image     VARCHAR(255) NOT NULL,
    link      VARCHAR(255) NULL,
    sortOrder INT NOT NULL DEFAULT 0,
    status    ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE post (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title     VARCHAR(255) NOT NULL,
    slug      VARCHAR(255) NOT NULL UNIQUE,
    thumbnail VARCHAR(255) NULL,
    summary   VARCHAR(500) NULL,
    content   LONGTEXT NOT NULL,
    type      ENUM('news','promotion') NOT NULL DEFAULT 'news',
    authorId  BIGINT UNSIGNED NULL,
    status    ENUM('draft','published') NOT NULL DEFAULT 'draft',
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_post_author FOREIGN KEY (authorId) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 8. TRIGGER / PROCEDURE / EVENT
-- =====================================================================

DELIMITER $$

-- Chặn tạo 2 suất chiếu chồng giờ trong cùng 1 phòng
CREATE TRIGGER trg_showtime_no_overlap_ins
BEFORE INSERT ON showtime
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1 FROM showtime
        WHERE roomId = NEW.roomId
          AND status <> 'cancelled'
          AND NEW.startTime < endTime
          AND NEW.endTime   > startTime
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Suat chieu bi trung gio trong phong nay';
    END IF;
END$$

CREATE TRIGGER trg_showtime_no_overlap_upd
BEFORE UPDATE ON showtime
FOR EACH ROW
BEGIN
    IF NEW.status <> 'cancelled' AND EXISTS (
        SELECT 1 FROM showtime
        WHERE roomId = NEW.roomId
          AND id <> NEW.id
          AND status <> 'cancelled'
          AND NEW.startTime < endTime
          AND NEW.endTime   > startTime
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Suat chieu bi trung gio trong phong nay';
    END IF;
END$$

-- Cập nhật điểm trung bình phim khi có đánh giá mới / sửa / xóa
CREATE TRIGGER trg_review_ai AFTER INSERT ON review FOR EACH ROW
BEGIN
    UPDATE movie SET avgRating = (SELECT IFNULL(ROUND(AVG(rating),1),0) FROM review
                                  WHERE movieId = NEW.movieId AND status = 'visible')
    WHERE id = NEW.movieId;
END$$

CREATE TRIGGER trg_review_au AFTER UPDATE ON review FOR EACH ROW
BEGIN
    UPDATE movie SET avgRating = (SELECT IFNULL(ROUND(AVG(rating),1),0) FROM review
                                  WHERE movieId = NEW.movieId AND status = 'visible')
    WHERE id = NEW.movieId;
END$$

CREATE TRIGGER trg_review_ad AFTER DELETE ON review FOR EACH ROW
BEGIN
    UPDATE movie SET avgRating = (SELECT IFNULL(ROUND(AVG(rating),1),0) FROM review
                                  WHERE movieId = OLD.movieId AND status = 'visible')
    WHERE id = OLD.movieId;
END$$

-- Sinh sơ đồ ghế cho 1 phòng:
--   3 hàng đầu = ghế thường, các hàng giữa = VIP, hàng cuối = Sweetbox (ghế đôi)
CREATE PROCEDURE sp_generate_seats(IN p_roomId BIGINT UNSIGNED)
BEGIN
    DECLARE v_rows INT; DECLARE v_cols INT;
    DECLARE r INT DEFAULT 1; DECLARE c INT;
    DECLARE v_standard BIGINT; DECLARE v_vip BIGINT; DECLARE v_sweet BIGINT;
    DECLARE v_type BIGINT; DECLARE v_maxCol INT;

    SELECT totalRows, totalCols INTO v_rows, v_cols FROM room WHERE id = p_roomId;
    SELECT id INTO v_standard FROM seat_type WHERE code = 'standard';
    SELECT id INTO v_vip      FROM seat_type WHERE code = 'vip';
    SELECT id INTO v_sweet    FROM seat_type WHERE code = 'sweetbox';

    DELETE FROM seat WHERE roomId = p_roomId;

    WHILE r <= v_rows DO
        IF r = v_rows THEN
            SET v_type = v_sweet;  SET v_maxCol = FLOOR(v_cols / 2);  -- ghế đôi chiếm 2 chỗ
        ELSEIF r <= 3 THEN
            SET v_type = v_standard; SET v_maxCol = v_cols;
        ELSE
            SET v_type = v_vip;      SET v_maxCol = v_cols;
        END IF;

        SET c = 1;
        WHILE c <= v_maxCol DO
            INSERT INTO seat (roomId, seatTypeId, rowLabel, seatNumber)
            VALUES (p_roomId, v_type, CHAR(64 + r), c);
            SET c = c + 1;
        END WHILE;
        SET r = r + 1;
    END WHILE;
END$$

-- Hủy các đơn "pending" quá hạn và nhả ghế
CREATE PROCEDURE sp_release_expired_bookings()
BEGIN
    UPDATE booking SET status = 'expired'
    WHERE status = 'pending' AND expiredAt < NOW();

    DELETE bs FROM booking_seat bs
    JOIN booking b ON b.id = bs.bookingId
    WHERE b.status IN ('expired','cancelled');
END$$

DELIMITER ;

-- Chạy tự động mỗi phút (cần: SET GLOBAL event_scheduler = ON;)
-- Nếu hosting không cho bật event -> gọi CALL sp_release_expired_bookings()
-- trong PHP trước khi load sơ đồ ghế, hoặc dùng cron.
CREATE EVENT IF NOT EXISTS ev_release_expired_bookings
ON SCHEDULE EVERY 1 MINUTE
DO CALL sp_release_expired_bookings();

-- =====================================================================
-- 9. VIEW HỖ TRỢ
-- =====================================================================

-- Lịch chiếu đầy đủ thông tin (dùng cho trang "Mua vé" / "Lịch chiếu theo rạp")
CREATE VIEW v_showtime_detail AS
SELECT  st.id           AS showtimeId,
        st.startTime, st.endTime, st.format, st.language, st.basePrice, st.status,
        m.id            AS movieId,  m.name AS movieName, m.slug AS movieSlug,
        m.image         AS moviePoster, m.ageRating, m.duration,
        r.id            AS roomId,   r.name AS roomName, r.roomType, r.surcharge,
        c.id            AS cinemaId, c.name AS cinemaName,
        ci.id           AS cityId,   ci.name AS cityName,
        (SELECT COUNT(*) FROM seat s WHERE s.roomId = r.id AND s.status = 'active')
            - (SELECT COUNT(*) FROM booking_seat bs WHERE bs.showtimeId = st.id)
                        AS availableSeats
FROM showtime st
JOIN movie  m  ON m.id  = st.movieId
JOIN room   r  ON r.id  = st.roomId
JOIN cinema c  ON c.id  = r.cinemaId
JOIN city   ci ON ci.id = c.cityId;

-- =====================================================================
-- 10. DỮ LIỆU MẪU
-- =====================================================================

INSERT INTO role (name, description) VALUES
('admin',    'Quản trị toàn hệ thống'),
('staff',    'Nhân viên rạp - soát vé, bán tại quầy'),
('customer', 'Khách hàng');

INSERT INTO member_level (name, minSpending, pointRate, discountPercent) VALUES
('Member', 0,       5,  0),
('VIP',    4000000, 7,  5),
('VVIP',   8000000, 10, 10);

-- Mật khẩu mẫu: 123456  (password_hash bcrypt)
INSERT INTO users (name, email, password, phoneNumber, roleId, memberLevelId) VALUES
('Admin',      'admin@cinema.vn',  '$2y$10$Jc1CYtYopBvMOKMuLw8ESuUlcMdQ.gcn2Lvm5Vmd2uABGNu0Xo8Je', '0900000001', 1, NULL),
('Nhân viên',  'staff@cinema.vn',  '$2y$10$Jc1CYtYopBvMOKMuLw8ESuUlcMdQ.gcn2Lvm5Vmd2uABGNu0Xo8Je', '0900000002', 2, NULL),
('Khách hàng', 'khach@gmail.com',  '$2y$10$Jc1CYtYopBvMOKMuLw8ESuUlcMdQ.gcn2Lvm5Vmd2uABGNu0Xo8Je', '0900000003', 3, 1);

INSERT INTO genre (name, slug) VALUES
('Hành động','hanh-dong'), ('Hài','hai'), ('Kinh dị','kinh-di'),
('Tình cảm','tinh-cam'), ('Hoạt hình','hoat-hinh'), ('Khoa học viễn tưởng','khoa-hoc-vien-tuong'),
('Phiêu lưu','phieu-luu'), ('Tâm lý','tam-ly');

INSERT INTO movie (name, slug, image, trailerUrl, description, director, actors, duration,
                   language, country, ageRating, releaseDate, endDate, status, isHot) VALUES
('Phim Hành Động Mẫu', 'phim-hanh-dong-mau', '/uploads/movies/p1.jpg', 'https://youtube.com/watch?v=xxx',
 'Mô tả phim...', 'Đạo diễn A', 'Diễn viên A, Diễn viên B', 125,
 'Tiếng Anh - Phụ đề Tiếng Việt', 'Mỹ', 'T13', CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 25 DAY, 'now_showing', 1),
('Phim Hoạt Hình Mẫu', 'phim-hoat-hinh-mau', '/uploads/movies/p2.jpg', NULL,
 'Mô tả phim...', 'Đạo diễn B', 'Lồng tiếng A', 98,
 'Lồng tiếng Việt', 'Nhật Bản', 'P', CURDATE() - INTERVAL 2 DAY, CURDATE() + INTERVAL 28 DAY, 'now_showing', 0),
('Phim Kinh Dị Sắp Chiếu', 'phim-kinh-di-sap-chieu', '/uploads/movies/p3.jpg', NULL,
 'Mô tả phim...', 'Đạo diễn C', 'Diễn viên C', 110,
 'Tiếng Việt', 'Việt Nam', 'T18', CURDATE() + INTERVAL 10 DAY, NULL, 'coming_soon', 0);

INSERT INTO movie_genre VALUES (1,1),(1,7),(2,5),(2,2),(3,3);

INSERT INTO city (name, sortOrder) VALUES ('Hà Nội',1), ('TP. Hồ Chí Minh',2), ('Nghệ An',3);

INSERT INTO cinema (cityId, name, slug, address, phone) VALUES
(3, 'Cinema Vinh Center', 'cinema-vinh-center', 'Số 1 Quang Trung, TP. Vinh', '02383000000'),
(1, 'Cinema Hà Nội Plaza', 'cinema-ha-noi-plaza', 'Số 10 Tràng Tiền, Hà Nội', '02430000000');

INSERT INTO room (cinemaId, name, roomType, totalRows, totalCols, surcharge) VALUES
(1, 'Cinema 1', 'standard', 8, 12, 0),
(1, 'Cinema 2', 'imax',     10, 14, 30000),
(2, 'Cinema 1', 'standard', 8, 12, 0);

INSERT INTO seat_type (name, code, extraPrice, color) VALUES
('Ghế thường', 'standard', 0,     '#9ca3af'),
('Ghế VIP',    'vip',      15000, '#f59e0b'),
('Sweetbox',   'sweetbox', 90000, '#ec4899');   -- giá ghế đôi = basePrice*2 + ... tùy logic PHP

CALL sp_generate_seats(1);
CALL sp_generate_seats(2);
CALL sp_generate_seats(3);

INSERT INTO showtime (movieId, roomId, startTime, endTime, format, language, basePrice) VALUES
(1, 1, CONCAT(CURDATE(), ' 18:00:00'), CONCAT(CURDATE(), ' 20:20:00'), '2D', 'sub', 90000),
(1, 1, CONCAT(CURDATE(), ' 20:30:00'), CONCAT(CURDATE(), ' 22:50:00'), '2D', 'sub', 100000),
(2, 2, CONCAT(CURDATE(), ' 19:00:00'), CONCAT(CURDATE(), ' 20:50:00'), '3D', 'dub', 110000),
(1, 3, CONCAT(CURDATE() + INTERVAL 1 DAY, ' 19:00:00'), CONCAT(CURDATE() + INTERVAL 1 DAY, ' 21:20:00'), '2D', 'sub', 95000);

INSERT INTO product (name, description, type, price) VALUES
('Combo 1 người', '1 bắp ngọt lớn + 1 nước 32oz', 'combo', 89000),
('Combo 2 người', '1 bắp ngọt lớn + 2 nước 32oz', 'combo', 115000),
('Bắp phô mai',   'Bắp vị phô mai size L',         'food',  65000),
('Coca-Cola 32oz','Nước ngọt',                     'drink', 39000);

INSERT INTO voucher (code, name, discountType, discountValue, maxDiscount, minOrder, quantity, startDate, endDate) VALUES
('WELCOME20', 'Giảm 20% cho thành viên mới', 'percent', 20, 50000, 100000, 1000, NOW(), NOW() + INTERVAL 30 DAY),
('GIAM30K',   'Giảm 30.000đ',                'fixed',   30000, NULL, 150000, 500, NOW(), NOW() + INTERVAL 30 DAY);

INSERT INTO banner (title, image, link, sortOrder) VALUES
('Phim Hành Động Mẫu', '/uploads/banners/b1.jpg', '/phim/phim-hanh-dong-mau', 1);

-- Đơn mẫu đã thanh toán: khách đặt ghế A5, A6 + 1 combo cho suất 1
INSERT INTO booking (code, userId, showtimeId, ticketAmount, productAmount, totalAmount, pointsEarned, status, expiredAt)
VALUES ('BK00000001', 3, 1, 180000, 89000, 269000, 13, 'paid', NOW() + INTERVAL 10 MINUTE);

INSERT INTO booking_seat (bookingId, showtimeId, seatId, price)
SELECT 1, 1, id, 90000 FROM seat WHERE roomId = 1 AND rowLabel = 'A' AND seatNumber IN (5,6);

INSERT INTO booking_product (bookingId, productId, quantity, price) VALUES (1, 1, 1, 89000);
INSERT INTO payment (bookingId, method, amount, transactionCode, status, paidAt)
VALUES (1, 'vnpay', 269000, 'VNP0000001', 'success', NOW());
INSERT INTO point_history (userId, bookingId, points, note) VALUES (3, 1, 13, 'Tích điểm đơn BK00000001');
UPDATE users SET points = points + 13, totalSpending = totalSpending + 269000 WHERE id = 3;

INSERT INTO review (userId, movieId, rating, content) VALUES (3, 1, 9, 'Phim hay!');

-- =====================================================================
-- 11. CÁC QUERY HAY DÙNG TRONG PHP (tham khảo)
-- =====================================================================

-- (a) Phim đang chiếu / sắp chiếu
-- SELECT * FROM movie WHERE status = 'now_showing' ORDER BY isHot DESC, releaseDate DESC;

-- (b) Lịch chiếu 1 phim theo ngày + thành phố, nhóm theo rạp
-- SELECT * FROM v_showtime_detail
-- WHERE movieId = ? AND cityId = ? AND DATE(startTime) = ?
--   AND status = 'open' AND startTime > NOW()
-- ORDER BY cinemaName, startTime;

-- (c) Sơ đồ ghế của 1 suất + trạng thái đã đặt + giá từng ghế
-- SELECT s.id, s.rowLabel, s.seatNumber, s.status, st.code AS seatType, st.color,
--        (sh.basePrice + st.extraPrice + r.surcharge) AS price,
--        (bs.id IS NOT NULL) AS isBooked
-- FROM showtime sh
-- JOIN room r        ON r.id = sh.roomId
-- JOIN seat s        ON s.roomId = r.id
-- JOIN seat_type st  ON st.id = s.seatTypeId
-- LEFT JOIN booking_seat bs ON bs.showtimeId = sh.id AND bs.seatId = s.id
-- WHERE sh.id = ?
-- ORDER BY s.rowLabel, s.seatNumber;

-- (d) Giữ ghế (trong TRANSACTION). Nếu INSERT booking_seat lỗi Duplicate (1062)
--     => ghế vừa bị người khác lấy -> ROLLBACK và báo khách chọn lại.
-- START TRANSACTION;
--   INSERT INTO booking (code, userId, showtimeId, ticketAmount, totalAmount, expiredAt)
--   VALUES (?, ?, ?, ?, ?, NOW() + INTERVAL 10 MINUTE);
--   INSERT INTO booking_seat (bookingId, showtimeId, seatId, price) VALUES (?,?,?,?), (?,?,?,?);
-- COMMIT;

-- (e) Lịch sử vé của khách
-- SELECT b.code, b.totalAmount, b.status, b.createdAt, v.movieName, v.cinemaName, v.roomName, v.startTime,
--        GROUP_CONCAT(CONCAT(s.rowLabel, s.seatNumber) ORDER BY s.rowLabel, s.seatNumber) AS seats
-- FROM booking b
-- JOIN v_showtime_detail v ON v.showtimeId = b.showtimeId
-- LEFT JOIN booking_seat bs ON bs.bookingId = b.id
-- LEFT JOIN seat s ON s.id = bs.seatId
-- WHERE b.userId = ?
-- GROUP BY b.id ORDER BY b.createdAt DESC;

-- (f) Doanh thu theo phim trong khoảng ngày (admin)
-- SELECT m.name,
--        SUM((SELECT COUNT(*) FROM booking_seat bs WHERE bs.bookingId = b.id)) AS tickets,
--        SUM(b.totalAmount) AS revenue
-- FROM booking b
-- JOIN showtime sh ON sh.id = b.showtimeId
-- JOIN movie m ON m.id = sh.movieId
-- WHERE b.status = 'paid' AND b.createdAt BETWEEN ? AND ?
-- GROUP BY m.id ORDER BY revenue DESC;
