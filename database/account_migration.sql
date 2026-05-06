  -- ============================================================
  -- FitFood Account Migration
  -- - Thêm gender + dob vào bảng users
  -- - Tạo bảng user_addresses (địa chỉ giao hàng)
  --
  -- CHẠY 1 LẦN: phpMyAdmin → DB fitfood → tab SQL → paste → Go.
  -- An toàn để chạy lại: dùng IF NOT EXISTS pattern.
  -- ============================================================

  USE `fitfood`;

  -- 1. users.gender
  SET @c1 := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'gender');
  SET @s1 := IF(@c1 = 0,
    "ALTER TABLE `users` ADD COLUMN `gender` ENUM('M','F','O') DEFAULT NULL AFTER `phone`",
    'SELECT 1');
  PREPARE p1 FROM @s1; EXECUTE p1; DEALLOCATE PREPARE p1;

  -- 2. users.dob
  SET @c2 := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'dob');
  SET @s2 := IF(@c2 = 0,
    'ALTER TABLE `users` ADD COLUMN `dob` DATE DEFAULT NULL AFTER `gender`',
    'SELECT 1');
  PREPARE p2 FROM @s2; EXECUTE p2; DEALLOCATE PREPARE p2;

  -- 3. user_addresses table
  CREATE TABLE IF NOT EXISTS `user_addresses` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED NOT NULL,
    `recipient_name` VARCHAR(100) NOT NULL,
    `phone`          VARCHAR(20)  NOT NULL,
    `gender`         ENUM('M','F','O') DEFAULT NULL,
    `address`        VARCHAR(500) NOT NULL,
    `is_default`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_addresses_user` (`user_id`),
    KEY `idx_addresses_default` (`user_id`, `is_default`),
    CONSTRAINT `fk_addresses_user`
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
      ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
