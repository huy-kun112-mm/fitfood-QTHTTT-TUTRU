-- ============================================================
-- FitFood Inventory Migration
-- Thêm cột `sku` cho bảng products + auto-generate SKU
-- cho 15 sản phẩm seed gốc.
--
-- CHẠY 1 LẦN: phpMyAdmin (http://localhost:8081)
--   → DB fitfood → tab SQL → paste → Go.
-- An toàn để chạy lại: dùng IF NOT EXISTS pattern.
-- ============================================================

USE `fitfood`;

-- 1. Thêm cột sku (nullable + unique để admin có thể nhập sau)
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'products'
    AND COLUMN_NAME  = 'sku'
);
SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE `products` ADD COLUMN `sku` VARCHAR(50) DEFAULT NULL AFTER `id`',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Tự động sinh SKU cho các row chưa có
--    Format: FF-{cat_code}-{id_padded}
--      cat 1 (Gói ăn)        → PKG
--      cat 2 (Chế biến sẵn)  → RTE   (Ready-To-Eat)
--      cat 3 (Gói nước)      → JCE   (Juice)
--      cat 4 (Snacks)        → SNK
UPDATE `products`
SET `sku` = CASE `category_id`
    WHEN 1 THEN CONCAT('FF-PKG-', LPAD(id, 3, '0'))
    WHEN 2 THEN CONCAT('FF-RTE-', LPAD(id, 3, '0'))
    WHEN 3 THEN CONCAT('FF-JCE-', LPAD(id, 3, '0'))
    WHEN 4 THEN CONCAT('FF-SNK-', LPAD(id, 3, '0'))
    ELSE        CONCAT('FF-',     LPAD(id, 3, '0'))
END
WHERE `sku` IS NULL OR `sku` = '';

-- 3. Thêm UNIQUE index trên sku (sau khi đã populate xong)
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'products'
    AND INDEX_NAME   = 'uniq_products_sku'
);
SET @ddl2 := IF(@idx_exists = 0,
  'ALTER TABLE `products` ADD UNIQUE KEY `uniq_products_sku` (`sku`)',
  'SELECT 1');
PREPARE stmt2 FROM @ddl2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;
