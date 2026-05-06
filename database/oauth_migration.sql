-- ============================================================
-- OAuth Migration cho FitFood
-- Áp dụng cho DB đã có sẵn bảng `users` từ fitfood.sql
-- Chạy 1 lần qua phpMyAdmin (http://localhost:8081) hoặc CLI
-- ============================================================

-- 1. Cho phép password NULL (user Google không có mật khẩu local)
ALTER TABLE `users`
    MODIFY COLUMN `password` VARCHAR(255) NULL;

-- 2. Thêm cột google_id để liên kết chính xác (an toàn hơn match qua email)
--    Để NULL & UNIQUE: nhiều user local cùng có NULL được phép
ALTER TABLE `users`
    ADD COLUMN `google_id` VARCHAR(50) NULL UNIQUE AFTER `provider`;
