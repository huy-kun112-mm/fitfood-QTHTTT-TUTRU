-- ============================================================
-- FitFood Finance Migration
-- Tạo 2 bảng cho trang quản lý thu chi (admin/finance.php):
--   - purchases : nhập nguyên vật liệu  → Total Purchase
--   - expenses  : chi phí vận hành      → Total Expenses
--
-- CHẠY 1 LẦN: phpMyAdmin (http://localhost:8081)
--   → DB fitfood → tab SQL → paste → Go.
-- An toàn để chạy lại: dùng IF NOT EXISTS pattern.
-- ============================================================

USE `fitfood`;

-- 1. Bảng `purchases` — chi phí nguyên vật liệu đầu vào
--    total_amount là cột generated (quantity * unit_price), tự tính, không cần INSERT.
CREATE TABLE IF NOT EXISTS `purchases` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `material_name` VARCHAR(255) NOT NULL,
  `quantity`      DECIMAL(12,3) NOT NULL,
  `unit_price`    DECIMAL(15,2) NOT NULL,
  `total_amount`  DECIMAL(18,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
  `expiry_date`   DATE NULL,
  `import_date`   DATE NOT NULL,
  `is_deleted`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_purchases_import_date` (`import_date`),
  KEY `idx_purchases_is_deleted`  (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng `expenses` — chi phí vận hành (lương, điện, nước, ...)
CREATE TABLE IF NOT EXISTS `expenses` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bill_name`     VARCHAR(255) NOT NULL,
  `amount`        DECIMAL(15,2) NOT NULL,
  `bill_code`     VARCHAR(100) NULL,
  `received_date` DATE NOT NULL,
  `due_date`      DATE NULL,
  `is_deleted`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expenses_received_date` (`received_date`),
  KEY `idx_expenses_is_deleted`    (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
