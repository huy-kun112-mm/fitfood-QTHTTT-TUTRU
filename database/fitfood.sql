-- ============================================================
-- FitFood Database Schema
-- Chạy lần đầu khi container MySQL khởi tạo volume mới.
-- Để áp dụng lại sau khi đã có dữ liệu cũ:
--   docker compose down -v
--   docker compose up -d
-- ============================================================

CREATE DATABASE IF NOT EXISTS `fitfood`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `fitfood`;

-- ============================================================
-- 1. USERS — tài khoản người dùng
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`  VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NULL,
  `phone`      VARCHAR(20)  DEFAULT NULL,
  `avatar`     VARCHAR(255) DEFAULT NULL,
  `provider`   ENUM('local','google','facebook') NOT NULL DEFAULT 'local',
  `google_id`  VARCHAR(50)  NULL UNIQUE,
  `status`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. CATEGORIES — danh mục (Gói ăn, Chế biến sẵn, Nước, Snacks…)
-- ============================================================
CREATE TABLE IF NOT EXISTS `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120) NOT NULL,
  `slug`        VARCHAR(140) NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `image_url`   VARCHAR(500) DEFAULT NULL,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_categories_slug` (`slug`),
  KEY `idx_categories_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. PRODUCTS — sản phẩm và gói ăn
-- ============================================================
CREATE TABLE IF NOT EXISTS `products` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`       INT UNSIGNED DEFAULT NULL,
  `name`              VARCHAR(200) NOT NULL,
  `slug`              VARCHAR(220) NOT NULL,
  `type`              ENUM('package','product') NOT NULL DEFAULT 'product',
  `short_description` VARCHAR(255) DEFAULT NULL,
  `description`       TEXT         DEFAULT NULL,
  `ingredients`       TEXT         DEFAULT NULL,
  `price`             DECIMAL(12,2) UNSIGNED NOT NULL,
  `sale_price`        DECIMAL(12,2) UNSIGNED DEFAULT NULL,
  `calories`          INT UNSIGNED DEFAULT NULL,
  `unit`              VARCHAR(50)  DEFAULT NULL,
  `stock`             INT          NOT NULL DEFAULT 0,
  `image_url`         VARCHAR(500) DEFAULT NULL,
  `is_featured`       TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`        INT          NOT NULL DEFAULT 0,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_products_slug` (`slug`),
  KEY `idx_products_category`        (`category_id`),
  KEY `idx_products_type_active`     (`type`, `is_active`),
  KEY `idx_products_active_featured` (`is_active`, `is_featured`, `sort_order`),
  CONSTRAINT `fk_products_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- 4 danh mục chính
INSERT INTO `categories` (`name`, `slug`, `description`, `sort_order`) VALUES
  ('Gói ăn',        'goi-an',        'Các gói ăn healthy theo tuần', 1),
  ('Chế biến sẵn',  'che-bien-san',  'Sản phẩm chế biến sẵn ăn liền', 2),
  ('Gói nước',      'goi-nuoc',      'Nước ép detox theo tuần',       3),
  ('Snacks',        'snacks',        'Đồ ăn vặt healthy',             4);

-- 15 sản phẩm
INSERT INTO `products`
  (`category_id`, `name`, `slug`, `type`,
   `short_description`, `price`, `sale_price`, `calories`, `unit`,
   `stock`, `image_url`, `is_featured`, `sort_order`)
VALUES
  -- ===== Gói ăn (category_id = 1, type = package) =====
  (1, 'Gói FIT 3',  'goi-fit-3',  'package',
   'Trưa - Tối. Best seller', 650000, NULL, 1100, '6 bữa/tuần',
   100, 'https://fitfood.vn/static/sizes/260x200-fitfood-goi-fit3-healthy-2-17521258413949.jpg',
   1, 1),

  (1, 'Gói FULL',   'goi-full',   'package',
   '3 bữa/ngày. Giữ cân healthy', 825000, NULL, 1400, '9 bữa/tuần',
   100, 'https://fitfood.vn/static/sizes/260x200-fitfood-goi-full-healthy-1-17521259281132.jpg',
   1, 2),

  (1, 'Gói FIT 1',  'goi-fit-1',  'package',
   'Sáng - Trưa. Giảm cân', 650000, NULL, 900, '6 bữa/tuần',
   100, 'https://fitfood.vn/static/sizes/260x200-fitfood-goi-fit1-healthy-1-17521260026858.jpg',
   0, 3),

  (1, 'Gói MEAT',   'goi-meat',   'package',
   'Gấp 1.5 lượng thịt. Tăng cơ', 950000, NULL, 2000, '6 bữa/tuần',
   100, 'https://fitfood.vn/static/sizes/260x200-fitfood-goi-meat-healthy-17521260514909.jpg',
   0, 4),

  (1, 'Gói SLIM',   'goi-slim',   'package',
   'Gấp đôi rau, KO tinh bột', 600000, NULL, 800, '6 bữa/tuần',
   100, 'https://fitfood.vn/static/sizes/260x200-fitfood-goi-slim-healthy-1-17521260981757.jpg',
   0, 5),

  (1, 'Gói LUNCH',  'goi-lunch',  'package',
   '1 bữa trưa. Giao NÓNG', 349000, NULL, 500, '5 bữa/tuần',
   100, 'https://fitfood.vn/static/sizes/260x200-fitfood-goi-lunch-healthy-3-17521262682624.jpg',
   0, 6),

  -- ===== Chế biến sẵn (category_id = 2, type = product) =====
  (2, '10 gói Ức gà ăn liền 150gr',         '10-goi-uc-ga-an-lien-150gr',
   'product', NULL, 370000, 400000, NULL, '150 Gram/Gói',
   200, 'https://fitfood.vn/static/sizes/260x200-fitfood-ready-to-eat-chick-150g-17465038582117.jpg',
   1, 1),

  (2, 'COMBO 04 GÓI ỨC GÀ VIÊN',            'combo-04-goi-uc-ga-vien',
   'product', NULL, 180000, 200000, NULL, '200 Gram/Gói',
   200, 'https://fitfood.vn/static/sizes/260x200-uc-ga-vien-17151506845857.png',
   0, 2),

  (2, '05 gói Cơm gạo lức ăn liền',         '05-goi-com-gao-luc-an-lien',
   'product', NULL, 119000, NULL, NULL, '200 Gram/Gói',
   200, 'https://fitfood.vn/img/260x200/images/product-web-gaoluc-16488112478108.jpg',
   0, 3),

  -- ===== Gói nước (category_id = 3, type = product) =====
  (3, 'FITFOOD JUICE SWEETIE', 'fitfood-juice-sweetie', 'product',
   NULL, 200000, 220000, NULL, '5 Chai/Tuần',
   150, 'https://fitfood.vn/static/sizes/260x200-combo-sweetie-16865797187113.jpeg',
   0, 1),

  (3, 'FITFOOD JUICE GREENIE', 'fitfood-juice-greenie', 'product',
   NULL, 200000, 220000, NULL, '5 Chai/Tuần',
   150, 'https://fitfood.vn/static/sizes/260x200-combo-greenie-16865797541141.jpeg',
   0, 2),

  -- ===== Snacks (category_id = 4, type = product) =====
  (4, 'Gạo lứt Rong Biển',       'gao-lut-rong-bien',       'product',
   NULL, 100000, 110000, NULL, '200 Gram/Hộp',
   300, 'https://fitfood.vn/static/sizes/260x200-gao-lut-rong-bien-17421804089263.jpg',
   0, 1),

  (4, 'Biscotti vị socola',      'biscotti-vi-socola',      'product',
   NULL, 100000, 110000, NULL, '200 Gram/Hộp',
   300, 'https://fitfood.vn/static/sizes/260x200-biscotti-chocolate-17421800072156.jpg',
   0, 2),

  (4, 'Biscotti vị trà xanh',    'biscotti-vi-tra-xanh',    'product',
   NULL, 100000, 110000, NULL, '200 Gram/Hộp',
   300, 'https://fitfood.vn/static/sizes/260x200-matcha-17421792038386.jpg',
   0, 3),

  (4, 'Biscotti vị truyền thống','biscotti-vi-truyen-thong','product',
   NULL, 100000, 110000, NULL, '200 Gram/Hộp',
   300, 'https://fitfood.vn/static/sizes/260x200-biscotti-truyen-thong-17421804193565.jpg',
   0, 4);


-- ============================================================
-- 4. MENU_WEEKS — tuần thực đơn (regular / vegetarian)
-- ============================================================
CREATE TABLE IF NOT EXISTS `menu_weeks` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`            ENUM('regular','vegetarian') NOT NULL,
  `week_start_date` DATE NOT NULL,
  `cover_image_url` VARCHAR(500) DEFAULT NULL,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_type_week` (`type`, `week_start_date`),
  KEY `idx_type_active` (`type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. MENU_ITEMS — từng món của 1 ngày, 1 bữa
-- ============================================================
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_week_id`   INT UNSIGNED NOT NULL,
  `day_of_week`    TINYINT NOT NULL,
  `meal_slot`      ENUM('breakfast','lunch','dinner','meal1','meal2') NOT NULL,
  `name_vi`        VARCHAR(200) NOT NULL,
  `name_en`        VARCHAR(200) DEFAULT NULL,
  `nutrition_info` VARCHAR(255) DEFAULT NULL,
  `sticker_url`    VARCHAR(500) DEFAULT NULL,
  `sort_order`     INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_week_day_slot` (`menu_week_id`, `day_of_week`, `meal_slot`),
  CONSTRAINT `fk_items_week` FOREIGN KEY (`menu_week_id`)
    REFERENCES `menu_weeks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed 1 tuần active cho mỗi loại (regular: 15 món, vegetarian: 10 món)
INSERT INTO menu_weeks (type, week_start_date, cover_image_url, is_active) VALUES ('regular', '2026-04-20', 'https://fitfood.vn/img/original/menus/thuc-don-healthy-fitfood-4-17758014612757.jpg', 1);
SET @reg_id = LAST_INSERT_ID();
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 2, 'breakfast', 'Salad Bò Nướng Sốt Balsamic', 'Beef Salad W Balsamic Dijon', '410 Kcal | 30, 20, 29', NULL, 1);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 2, 'lunch', 'Mì Xào Gà xá xíu', 'Charsiu Chicken Noodles', '480 Kcal | 38, 23, 30', NULL, 2);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 2, 'dinner', 'Sườn kho thơm + Lức Nâu', 'Braised Pork Ribs with Pineapple + Brown Rice', '510 Kcal | 35, 25, 34', NULL, 3);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 3, 'breakfast', 'Nui nấu sườn non', 'Macaroni Pork Sparerib Soup', '550 Kcal | 40, 32, 25', 'https://fitfood.vn/img/original/uploads/icon-web-menu-fitfood-17307105107826.png', 4);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 3, 'lunch', 'TÔM CÀ RI MALAYSIA + Lức Nâu', 'Malaysian Shrimp Curry Rice', '420 Kcal | 40, 15, 28', 'https://fitfood.vn/img/original/uploads/7-1730711265852.png', 5);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 3, 'dinner', 'Bò Xào + Khoai Lang', 'Stir Fried Beef &amp; Sweet Potato', '571 Kcal | 30, 25, 43', NULL, 6);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 4, 'breakfast', 'Miến Kimchi Hải Sản', 'Kimchi Seafood Noodle Soup', '390 Kcal | 45, 7, 34', 'https://fitfood.vn/img/original/uploads/7-1730711265852.png', 7);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 4, 'lunch', 'Cá Nướng sả + Bún rau củ', 'Grilled Lemongrass Fish &amp; Veggie Vermicelli', '490 Kcal | 44, 20, 30', NULL, 8);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 4, 'dinner', 'Heo kho cải chua + Lức nâu', 'Pork with Pickled Mustard Greens &amp; Brown Rice', '550 Kcal | 35, 20, 25', NULL, 9);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 5, 'breakfast', 'Salad Gà Sốt Cà Rốt Gừng', 'Chicken Salad Served With Carrot &amp; Ginger Sauce', '410 Kcal | 32, 20, 25', NULL, 10);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 5, 'lunch', 'Bò trứng muối + Lức Nâu', 'Beef Meatball &amp; Brown Rice', '457 Kcal | 30, 21, 33', 'https://fitfood.vn/img/original/uploads/7-1730711265852.png', 11);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 5, 'dinner', 'Cá Nướng Muối Ớt + Lức Tím', 'Spicy Grilled Fish &amp; Brown Rice', '493 Kcal | 41, 18, 38', NULL, 12);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 6, 'breakfast', 'Hủ Tiếu Cá Lóc', 'Fish Noodle Soup', '455 Kcal | 50, 15, 30', 'https://fitfood.vn/img/original/uploads/icon-web-menu-fitfood-17307105107826.png', 13);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 6, 'lunch', 'Gà mắm nhĩ + Lức Tím', 'Chicken Brown Rice with Fish Sauce', '500 Kcal | 33, 26, 30', 'https://fitfood.vn/img/original/uploads/1-1729587033284.png', 14);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@reg_id, 6, 'dinner', 'Mì bò đài loan khô', 'Dry Taiwanese beef noodles', '495 Kcal | 35, 23, 36', NULL, 15);

INSERT INTO menu_weeks (type, week_start_date, cover_image_url, is_active) VALUES ('vegetarian', '2026-04-20', 'https://fitfood.vn/img/original/menus/thuc-don-chay-healthy-fitfood-5-1775801473205.jpg', 1);
SET @veg_id = LAST_INSERT_ID();
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@veg_id, 2, 'meal1', 'XÍU MẠI CHAY', 'Vegetarian Meatball', NULL, NULL, 1);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@veg_id, 2, 'meal2', 'GÀ CHAY SỐT CHUA NGỌT', 'Vegetarian Chicken with sweet and sour sauce', NULL, NULL, 2);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@veg_id, 3, 'meal1', 'MÌ Ý ĐÚT LÒ', 'Baked Spaghetti with Veggies', NULL, NULL, 3);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@veg_id, 3, 'meal2', 'Miến Gochujang chay', 'Vegetarian Gochujang Vermicelli', NULL, NULL, 4);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@veg_id, 4, 'meal1', 'Nấm xào xả ớt', 'Stir-fried Mushrooms with Lemongrass and Chili', NULL, NULL, 5);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@veg_id, 4, 'meal2', 'Bún thịt nướng chay', 'Vegetarian Grilled Pork and Brown Rice Vermicelli', NULL, NULL, 6);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@veg_id, 5, 'meal1', 'CẢI THẢO CUỘN GÀ CHAY', 'Vegetable Chicken Roll + Corn Vermicelli', NULL, NULL, 7);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@veg_id, 5, 'meal2', 'bún nem rán', 'Vermicelli with Fried Spring Rolls', NULL, NULL, 8);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@veg_id, 6, 'meal1', 'Hủ tiếu thái chay', 'Vegan Thai Noodle Soup', NULL, NULL, 9);
INSERT INTO menu_items (menu_week_id, day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url, sort_order) VALUES (@veg_id, 6, 'meal2', 'Cơm gà teriyaki chay', 'Vegetarian Teriyaki Chicken', NULL, NULL, 10);
