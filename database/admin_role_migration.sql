-- ============================================================
-- FitFood Admin Role Migration
-- 1. Thêm cột `role` vào bảng users (idempotent — kiểm tra trước
--    khi ALTER để chạy lại file này không lỗi).
-- 2. Seed 1 tài khoản admin mặc định:
--      email:    admin@fitfood.vn
--      password: admin123  (đã hash bằng password_hash)
--
-- Cách chạy: phpMyAdmin (http://localhost:8081) → chọn DB `fitfood`
--            → tab SQL → paste file này → Go.
-- ============================================================

USE `fitfood`;

-- 1. Thêm cột role nếu chưa có
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'users'
    AND COLUMN_NAME  = 'role'
);
SET @ddl := IF(@col_exists = 0,
  "ALTER TABLE `users` ADD COLUMN `role` ENUM('user','admin') NOT NULL DEFAULT 'user' AFTER `status`",
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Seed admin mặc định.
--    Hash của 'admin123' được tạo bằng password_hash(..., PASSWORD_DEFAULT) trong PHP 8.2.
--    Dùng ON DUPLICATE KEY UPDATE để upsert role/password nếu email đã tồn tại.
INSERT INTO `users` (`full_name`, `email`, `password`, `provider`, `status`, `role`)
VALUES (
  'Admin FitFood',
  'admin@fitfood.vn',
  '$2y$10$piUK.x0Sfh/l/oMGd.LE/.0o6u3AQYoh6mWBf3lMnNg9UxSnvSeZy',
  'local',
  1,
  'admin'
)
ON DUPLICATE KEY UPDATE
  `password` = VALUES(`password`),
  `role`     = 'admin',
  `status`   = 1;
