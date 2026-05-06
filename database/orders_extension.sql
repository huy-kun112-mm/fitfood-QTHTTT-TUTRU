-- ============================================================
-- Mở rộng bảng `orders` để chứa thông tin giao hàng + thanh toán
-- Chạy: mysql -u <user> -p <database> < orders_extension.sql
-- An toàn để chạy nhiều lần nếu bạn dùng MariaDB 10.0.2+ / MySQL 8
-- (IF NOT EXISTS cho ADD COLUMN). Với MySQL cũ hơn, chỉ chạy 1 lần.
-- ============================================================

ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `recipient_name` VARCHAR(100)  DEFAULT NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `phone`          VARCHAR(20)   DEFAULT NULL AFTER `recipient_name`,
  ADD COLUMN IF NOT EXISTS `email`          VARCHAR(150)  DEFAULT NULL AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `address`        VARCHAR(500)  DEFAULT NULL AFTER `email`,
  ADD COLUMN IF NOT EXISTS `delivery_time`  VARCHAR(100)  DEFAULT NULL AFTER `address`,
  ADD COLUMN IF NOT EXISTS `pay_method`     VARCHAR(20)   DEFAULT NULL AFTER `delivery_time`,
  ADD COLUMN IF NOT EXISTS `ship_fee`       DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0 AFTER `pay_method`,
  ADD COLUMN IF NOT EXISTS `discount`       DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0 AFTER `ship_fee`,
  ADD COLUMN IF NOT EXISTS `note_order`     VARCHAR(500)  DEFAULT NULL AFTER `discount`;
