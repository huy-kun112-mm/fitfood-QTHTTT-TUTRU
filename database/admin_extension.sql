-- ============================================================
-- FitFood Admin Extension Migration
-- Thêm bảng orders + order_items + cột sold_count + dữ liệu test
-- cho dashboard admin.
--
-- CHẠY 1 LẦN sau khi đã có schema gốc (database/fitfood.sql).
-- Cách chạy:
--   1) Mở phpMyAdmin: http://localhost:8081
--   2) Chọn database `fitfood` ở sidebar trái
--   3) Vào tab SQL → paste toàn bộ nội dung file này → bấm Go
-- File an toàn để chạy lại: orders/order_items sẽ bị xoá rồi tạo lại,
-- users dùng INSERT IGNORE để bỏ qua nếu trùng email.
-- ============================================================

USE `fitfood`;

-- ============================================================
-- 1. Thêm cột sold_count denormalized cho products
--    (lưu tổng số đã bán để query Top Selling cho nhanh,
--     được cập nhật từ order_items ở cuối file)
-- ============================================================
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'products'
    AND COLUMN_NAME  = 'sold_count'
);
SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE `products` ADD COLUMN `sold_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `stock`',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 2. Bảng orders & order_items (drop trước để chạy lại được)
-- ============================================================
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;

CREATE TABLE `orders` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED DEFAULT NULL,
  `total_amount` DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0,
  `status`       ENUM('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `note`         VARCHAR(255) DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orders_status_created` (`status`, `created_at`),
  KEY `idx_orders_user`           (`user_id`),
  CONSTRAINT `fk_orders_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`   INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity`   INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(12,2) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oi_order`   (`order_id`),
  KEY `idx_oi_product` (`product_id`),
  CONSTRAINT `fk_oi_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oi_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. Test users (5 khách hàng giả lập)
--    Password hash là placeholder — không dùng để login thật.
-- ============================================================
INSERT IGNORE INTO `users` (`full_name`, `email`, `password`, `phone`) VALUES
  ('Nguyễn Thị Hương', 'huong.nguyen@example.com', '$2y$10$placeholderHashForSeedDataOnlyXX', '0901234567'),
  ('Trần Văn Minh',    'minh.tran@example.com',   '$2y$10$placeholderHashForSeedDataOnlyXX', '0902345678'),
  ('Lê Quốc Bảo',      'bao.le@example.com',      '$2y$10$placeholderHashForSeedDataOnlyXX', '0903456789'),
  ('Phạm Mai Anh',     'mai.pham@example.com',    '$2y$10$placeholderHashForSeedDataOnlyXX', '0904567890'),
  ('Vũ Thanh Hà',      'ha.vu@example.com',       '$2y$10$placeholderHashForSeedDataOnlyXX', '0905678901');

-- Lấy 5 user_id đầu tiên (theo email) để dùng cho seed orders
SET @u1 := (SELECT id FROM users WHERE email = 'huong.nguyen@example.com');
SET @u2 := (SELECT id FROM users WHERE email = 'minh.tran@example.com');
SET @u3 := (SELECT id FROM users WHERE email = 'bao.le@example.com');
SET @u4 := (SELECT id FROM users WHERE email = 'mai.pham@example.com');
SET @u5 := (SELECT id FROM users WHERE email = 'ha.vu@example.com');

-- ============================================================
-- 4. Seed 40 orders rải đều 30 ngày gần nhất
--    Status: ~75% completed, một ít processing/pending/cancelled
-- ============================================================
INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `status`, `created_at`) VALUES
  ( 1, @u1,  650000, 'completed',  DATE_SUB(NOW(), INTERVAL 1  DAY)),
  ( 2, @u2,  825000, 'completed',  DATE_SUB(NOW(), INTERVAL 1  DAY)),
  ( 3, @u3,  740000, 'completed',  DATE_SUB(NOW(), INTERVAL 2  DAY)),
  ( 4, @u4,  650000, 'processing', DATE_SUB(NOW(), INTERVAL 2  DAY)),
  ( 5, @u5,  850000, 'completed',  DATE_SUB(NOW(), INTERVAL 3  DAY)),
  ( 6, @u1,  600000, 'completed',  DATE_SUB(NOW(), INTERVAL 3  DAY)),
  ( 7, @u2, 1300000, 'completed',  DATE_SUB(NOW(), INTERVAL 4  DAY)),
  ( 8, @u3,  349000, 'pending',    DATE_SUB(NOW(), INTERVAL 4  DAY)),
  ( 9, @u4,  370000, 'completed',  DATE_SUB(NOW(), INTERVAL 5  DAY)),
  (10, @u5,  850000, 'completed',  DATE_SUB(NOW(), INTERVAL 5  DAY)),
  (11, @u1,  825000, 'completed',  DATE_SUB(NOW(), INTERVAL 6  DAY)),
  (12, @u2,  950000, 'cancelled',  DATE_SUB(NOW(), INTERVAL 6  DAY)),
  (13, @u3,  650000, 'completed',  DATE_SUB(NOW(), INTERVAL 7  DAY)),
  (14, @u4,  608000, 'completed',  DATE_SUB(NOW(), INTERVAL 7  DAY)),
  (15, @u5,  650000, 'completed',  DATE_SUB(NOW(), INTERVAL 8  DAY)),
  (16, @u1,  400000, 'completed',  DATE_SUB(NOW(), INTERVAL 8  DAY)),
  (17, @u2,  925000, 'completed',  DATE_SUB(NOW(), INTERVAL 9  DAY)),
  (18, @u3,  950000, 'processing', DATE_SUB(NOW(), INTERVAL 9  DAY)),
  (19, @u4, 1300000, 'completed',  DATE_SUB(NOW(), INTERVAL 10 DAY)),
  (20, @u5,  370000, 'completed',  DATE_SUB(NOW(), INTERVAL 10 DAY)),
  (21, @u1,  600000, 'completed',  DATE_SUB(NOW(), INTERVAL 11 DAY)),
  (22, @u2,  650000, 'pending',    DATE_SUB(NOW(), INTERVAL 12 DAY)),
  (23, @u3,  650000, 'completed',  DATE_SUB(NOW(), INTERVAL 12 DAY)),
  (24, @u4,  360000, 'completed',  DATE_SUB(NOW(), INTERVAL 13 DAY)),
  (25, @u5,  825000, 'completed',  DATE_SUB(NOW(), INTERVAL 14 DAY)),
  (26, @u1,  349000, 'cancelled',  DATE_SUB(NOW(), INTERVAL 15 DAY)),
  (27, @u2,  650000, 'completed',  DATE_SUB(NOW(), INTERVAL 15 DAY)),
  (28, @u3,  370000, 'completed',  DATE_SUB(NOW(), INTERVAL 16 DAY)),
  (29, @u4, 1150000, 'completed',  DATE_SUB(NOW(), INTERVAL 17 DAY)),
  (30, @u5,  650000, 'completed',  DATE_SUB(NOW(), INTERVAL 18 DAY)),
  (31, @u1,  300000, 'completed',  DATE_SUB(NOW(), INTERVAL 19 DAY)),
  (32, @u2,  825000, 'completed',  DATE_SUB(NOW(), INTERVAL 20 DAY)),
  (33, @u3,  650000, 'completed',  DATE_SUB(NOW(), INTERVAL 21 DAY)),
  (34, @u4,  740000, 'processing', DATE_SUB(NOW(), INTERVAL 22 DAY)),
  (35, @u5,  600000, 'completed',  DATE_SUB(NOW(), INTERVAL 24 DAY)),
  (36, @u1,  650000, 'completed',  DATE_SUB(NOW(), INTERVAL 25 DAY)),
  (37, @u2,  400000, 'completed',  DATE_SUB(NOW(), INTERVAL 26 DAY)),
  (38, @u3,  200000, 'completed',  DATE_SUB(NOW(), INTERVAL 27 DAY)),
  (39, @u4,  650000, 'cancelled',  DATE_SUB(NOW(), INTERVAL 28 DAY)),
  (40, @u5,  650000, 'completed',  DATE_SUB(NOW(), INTERVAL 30 DAY));

-- ============================================================
-- 5. Seed order_items
--    product_id mapping (theo seed gốc fitfood.sql):
--      1=Gói FIT 3,  2=Gói FULL,  3=Gói FIT 1,  4=Gói MEAT,
--      5=Gói SLIM,   6=Gói LUNCH, 7=10 gói Ức gà 150gr,
--      8=Combo Ức gà viên, 9=05 gói Cơm gạo lức,
--      10=Juice Sweetie,   11=Juice Greenie,
--      12=Gạo lứt Rong Biển, 13=Biscotti socola,
--      14=Biscotti trà xanh, 15=Biscotti truyền thống
-- ============================================================
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `unit_price`) VALUES
  ( 1,  1, 1, 650000),
  ( 2,  2, 1, 825000),
  ( 3,  7, 2, 370000),
  ( 4,  1, 1, 650000),
  ( 5,  1, 1, 650000), ( 5, 13, 2, 100000),
  ( 6,  5, 1, 600000),
  ( 7,  1, 2, 650000),
  ( 8,  6, 1, 349000),
  ( 9,  7, 1, 370000),
  (10,  1, 1, 650000), (10, 10, 1, 200000),
  (11,  2, 1, 825000),
  (12,  4, 1, 950000),
  (13,  1, 1, 650000),
  (14,  7, 1, 370000), (14,  9, 2, 119000),
  (15,  1, 1, 650000),
  (16, 10, 2, 200000),
  (17,  2, 1, 825000), (17, 14, 1, 100000),
  (18,  4, 1, 950000),
  (19,  1, 2, 650000),
  (20,  7, 1, 370000),
  (21,  5, 1, 600000),
  (22,  3, 1, 650000),
  (23,  1, 1, 650000),
  (24,  8, 2, 180000),
  (25,  2, 1, 825000),
  (26,  6, 1, 349000),
  (27,  1, 1, 650000),
  (28,  7, 1, 370000),
  (29,  4, 1, 950000), (29, 11, 1, 200000),
  (30,  1, 1, 650000),
  (31, 15, 3, 100000),
  (32,  2, 1, 825000),
  (33,  1, 1, 650000),
  (34,  7, 2, 370000),
  (35,  5, 1, 600000),
  (36,  1, 1, 650000),
  (37, 10, 1, 200000), (37, 11, 1, 200000),
  (38, 12, 2, 100000),
  (39,  3, 1, 650000),
  (40,  1, 1, 650000);

-- ============================================================
-- 6. Cập nhật sold_count cho products
--    (đếm tổng quantity từ order_items với order completed/processing,
--     bỏ qua pending và cancelled)
-- ============================================================
UPDATE `products` p
LEFT JOIN (
  SELECT oi.product_id, SUM(oi.quantity) AS total_sold
  FROM order_items oi
  INNER JOIN orders o ON o.id = oi.order_id
  WHERE o.status IN ('completed', 'processing')
  GROUP BY oi.product_id
) s ON s.product_id = p.id
SET p.sold_count = COALESCE(s.total_sold, 0);

-- ============================================================
-- 7. Hạ stock cho vài sản phẩm để có dữ liệu "Low Stock"
-- ============================================================
UPDATE `products` SET `stock` = 8  WHERE id =  3;  -- Gói FIT 1
UPDATE `products` SET `stock` = 4  WHERE id =  6;  -- Gói LUNCH
UPDATE `products` SET `stock` = 2  WHERE id = 11;  -- Juice Greenie
UPDATE `products` SET `stock` = 12 WHERE id = 13;  -- Biscotti socola
UPDATE `products` SET `stock` = 15 WHERE id = 14;  -- Biscotti trà xanh
