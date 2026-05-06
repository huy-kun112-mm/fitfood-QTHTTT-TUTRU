-- ============================================================
-- FitFood — Reviews extension
-- ============================================================
-- Cách chạy thủ công:
--   mysql -u root -p fitfood < reviews_extension.sql
--
-- Bảng `product_reviews` lưu bình luận của user cho sản phẩm.
-- Quy tắc: 1 user / 1 sản phẩm = 1 review (unique key).
-- Trang detail-product dùng INSERT ... ON DUPLICATE KEY UPDATE
-- để vừa tạo mới vừa cho phép user sửa bình luận của mình.
 USE `fitfood`;
CREATE TABLE IF NOT EXISTS `product_reviews` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `content`    TEXT         NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_review_user_product`     (`user_id`, `product_id`),
  KEY        `idx_review_product_created`   (`product_id`, `created_at`),
  CONSTRAINT `fk_review_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
