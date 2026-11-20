-- =============================================================================
-- Herbama Fashions — Full Database Schema
-- Engine  : InnoDB
-- Charset : utf8mb4 / utf8mb4_unicode_ci
-- File    : sql/schema.sql
-- Re-runnable: drops all tables in reverse FK order before recreating them.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
SET time_zone='+05:30';

-- =============================================================================
-- DROP TABLES (reverse FK dependency order)
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `subscribers`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `banners`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `wishlist`;
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `carts`;
DROP TABLE IF EXISTS `offers`;
DROP TABLE IF EXISTS `product_variants`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `users`;

-- =============================================================================
-- TABLE: users
-- =============================================================================
CREATE TABLE `users` (
  `id`               BIGINT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `full_name`        VARCHAR(120)       NOT NULL,
  `email`            VARCHAR(255)       NOT NULL,
  `phone`            VARCHAR(20)            NULL DEFAULT NULL,
  `password_hash`    VARCHAR(255)       NOT NULL,
  `role`             ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  `is_blocked`       TINYINT(1)         NOT NULL DEFAULT 0,
  `login_attempts`   TINYINT UNSIGNED   NOT NULL DEFAULT 0,
  `locked_until`     DATETIME               NULL DEFAULT NULL,
  `last_login_at`    DATETIME               NULL DEFAULT NULL,
  `last_login_ip`    VARCHAR(45)            NULL DEFAULT NULL,
  `created_at`       DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role`       (`role`),
  KEY `idx_users_is_blocked` (`is_blocked`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Registered customers and admin accounts';

-- =============================================================================
-- TABLE: addresses
-- =============================================================================
CREATE TABLE `addresses` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `recipient_name` VARCHAR(120)    NOT NULL,
  `phone`          VARCHAR(20)     NOT NULL,
  `line1`          VARCHAR(255)    NOT NULL,
  `line2`          VARCHAR(255)        NULL DEFAULT NULL,
  `city`           VARCHAR(100)    NOT NULL,
  `state`          VARCHAR(100)    NOT NULL,
  `pincode`        VARCHAR(20)     NOT NULL,
  `country`        VARCHAR(80)     NOT NULL DEFAULT 'India',
  `is_default`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_addresses_user_id` (`user_id`),
  CONSTRAINT `fk_addresses_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Saved delivery addresses per user';

-- =============================================================================
-- TABLE: categories
-- =============================================================================
CREATE TABLE `categories` (
  `id`         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `parent_id`  BIGINT UNSIGNED      NULL DEFAULT NULL,
  `name`       VARCHAR(100)     NOT NULL,
  `slug`       VARCHAR(120)     NOT NULL,
  `image_path` VARCHAR(500)         NULL DEFAULT NULL,
  `sort_order` SMALLINT         NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_parent_id` (`parent_id`),
  KEY `idx_categories_is_active` (`is_active`),
  CONSTRAINT `fk_categories_parent_id`
    FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Product categories — supports one level of nesting via parent_id';

-- =============================================================================
-- TABLE: products
-- =============================================================================
CREATE TABLE `products` (
  `id`            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `category_id`   BIGINT UNSIGNED      NULL DEFAULT NULL,
  `name`          VARCHAR(255)     NOT NULL,
  `slug`          VARCHAR(300)     NOT NULL,
  `description`   TEXT                 NULL DEFAULT NULL,
  `short_desc`    VARCHAR(500)         NULL DEFAULT NULL,
  `base_price`    DECIMAL(10,2)    NOT NULL,
  `compare_price` DECIMAL(10,2)        NULL DEFAULT NULL COMMENT 'Original / crossed-out price',
  `discount_pct`  TINYINT UNSIGNED     NULL DEFAULT NULL COMMENT 'Display discount percent 0-100 (computed field)',
  `material`      VARCHAR(120)         NULL DEFAULT NULL,
  `design`        VARCHAR(120)         NULL DEFAULT NULL,
  `neck`          VARCHAR(80)          NULL DEFAULT NULL,
  `sleeves`       VARCHAR(80)          NULL DEFAULT NULL,
  `closure`       VARCHAR(80)          NULL DEFAULT NULL,
  `is_new`        TINYINT(1)       NOT NULL DEFAULT 1,
  `is_featured`   TINYINT(1)       NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)       NOT NULL DEFAULT 1,
  `meta_title`    VARCHAR(255)         NULL DEFAULT NULL,
  `meta_desc`     VARCHAR(500)         NULL DEFAULT NULL,
  `sort_order`    SMALLINT         NOT NULL DEFAULT 0,
  `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_slug`        (`slug`),
  KEY `idx_products_category_id`       (`category_id`),
  KEY `idx_products_is_active`         (`is_active`),
  KEY `idx_products_is_new`            (`is_new`),
  KEY `idx_products_is_featured`       (`is_featured`),
  CONSTRAINT `fk_products_category_id`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Kidswear product catalogue';

-- =============================================================================
-- TABLE: product_images
-- =============================================================================
CREATE TABLE `product_images` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `image_path` VARCHAR(500)    NOT NULL,
  `alt_text`   VARCHAR(255)        NULL DEFAULT NULL,
  `is_primary` TINYINT(1)      NOT NULL DEFAULT 0,
  `position`   SMALLINT        NOT NULL DEFAULT 0,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_images_product_id` (`product_id`),
  CONSTRAINT `fk_product_images_product_id`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='One-to-many gallery images per product';

-- =============================================================================
-- TABLE: product_variants
-- Age labels: '0-6M', '6-12M', '1Y', '2Y', '3Y', '4Y', '5Y', '6Y'
-- =============================================================================
CREATE TABLE `product_variants` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `age_label`  VARCHAR(30)     NOT NULL COMMENT 'e.g. 0-6M, 6-12M, 1Y, 2Y … 6Y',
  `color`      VARCHAR(60)         NULL DEFAULT NULL,
  `sku`        VARCHAR(120)        NULL DEFAULT NULL,
  `stock_qty`  SMALLINT UNSIGNED   NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_variants_sku` (`sku`),
  KEY `idx_product_variants_product_id` (`product_id`),
  CONSTRAINT `fk_product_variants_product_id`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Size/colour variants per product';

-- =============================================================================
-- TABLE: offers (coupons and auto-applied promotions)
-- =============================================================================
CREATE TABLE `offers` (
  `id`               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title`            VARCHAR(200)     NOT NULL,
  `code`             VARCHAR(50)          NULL DEFAULT NULL COMMENT 'Coupon code; NULL = auto-applied',
  `type`             ENUM('percent','fixed_amount','free_shipping') NOT NULL,
  `value`            DECIMAL(10,2)    NOT NULL,
  `min_order_value`  DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  `max_discount`     DECIMAL(10,2)        NULL DEFAULT NULL,
  `applies_to`       ENUM('all','category','product') NOT NULL DEFAULT 'all',
  `applies_to_id`    BIGINT UNSIGNED      NULL DEFAULT NULL COMMENT 'category_id or product_id depending on applies_to',
  `usage_limit`      INT UNSIGNED         NULL DEFAULT NULL,
  `usage_count`      INT UNSIGNED     NOT NULL DEFAULT 0,
  `starts_at`        DATETIME             NULL DEFAULT NULL,
  `ends_at`          DATETIME             NULL DEFAULT NULL,
  `is_active`        TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_offers_code` (`code`),
  KEY `idx_offers_is_active` (`is_active`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Discount offers and coupon codes';

-- =============================================================================
-- TABLE: carts (one row per logged-in user)
-- =============================================================================
CREATE TABLE `carts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_carts_user_id` (`user_id`),
  CONSTRAINT `fk_carts_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Shopping cart header — one per user';

-- =============================================================================
-- TABLE: cart_items
-- =============================================================================
CREATE TABLE `cart_items` (
  `id`         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `cart_id`    BIGINT UNSIGNED  NOT NULL,
  `product_id` BIGINT UNSIGNED  NOT NULL,
  `variant_id` BIGINT UNSIGNED      NULL DEFAULT NULL,
  `quantity`   SMALLINT UNSIGNED    NOT NULL DEFAULT 1,
  `added_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_items_cart_variant` (`cart_id`, `variant_id`),
  KEY `idx_cart_items_cart_id`    (`cart_id`),
  KEY `idx_cart_items_product_id` (`product_id`),
  CONSTRAINT `fk_cart_items_cart_id`
    FOREIGN KEY (`cart_id`)    REFERENCES `carts`            (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_items_product_id`
    FOREIGN KEY (`product_id`) REFERENCES `products`         (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_items_variant_id`
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Line items within a user cart';

-- =============================================================================
-- TABLE: orders
-- =============================================================================
CREATE TABLE `orders` (
  `id`                 BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `public_order_id`    VARCHAR(30)      NOT NULL COMMENT 'Format: HF-XXXXXX — generated in PHP',
  `user_id`            BIGINT UNSIGNED      NULL DEFAULT NULL,
  -- Address snapshot (denormalized — preserved even if original address deleted)
  `ship_name`          VARCHAR(120)     NOT NULL,
  `ship_phone`         VARCHAR(20)      NOT NULL,
  `ship_line1`         VARCHAR(255)     NOT NULL,
  `ship_line2`         VARCHAR(255)         NULL DEFAULT NULL,
  `ship_city`          VARCHAR(100)     NOT NULL,
  `ship_state`         VARCHAR(100)     NOT NULL,
  `ship_pincode`       VARCHAR(20)      NOT NULL,
  `ship_country`       VARCHAR(80)      NOT NULL DEFAULT 'India',
  -- Financials (server-computed, never trusted from POST)
  `subtotal`           DECIMAL(10,2)    NOT NULL,
  `shipping_charge`    DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  `discount_amount`    DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  `total`              DECIMAL(10,2)    NOT NULL,
  -- Status
  `status`             ENUM('placed','confirmed','payment_pending','paid','packed','dispatched','delivered','cancelled') NOT NULL DEFAULT 'placed',
  `payment_status`     ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  `payment_ref`        VARCHAR(255)         NULL DEFAULT NULL COMMENT 'UPI transaction ID — filled by admin',
  `paid_at`            DATETIME             NULL DEFAULT NULL,
  `paid_by_admin`      BIGINT UNSIGNED      NULL DEFAULT NULL,
  -- Coupon / offer
  `coupon_code`        VARCHAR(50)          NULL DEFAULT NULL,
  `offer_id`           BIGINT UNSIGNED      NULL DEFAULT NULL,
  -- Customization
  `customization_notes` TEXT                NULL DEFAULT NULL,
  `is_customized`       TINYINT(1)      NOT NULL DEFAULT 0,
  -- Notes
  `notes`              TEXT                 NULL DEFAULT NULL COMMENT 'Customer-facing notes',
  `admin_notes`        TEXT                 NULL DEFAULT NULL,
  `created_at`         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_public_order_id` (`public_order_id`),
  KEY `idx_orders_user_id`        (`user_id`),
  KEY `idx_orders_status`         (`status`),
  KEY `idx_orders_payment_status` (`payment_status`),
  KEY `idx_orders_created_at`     (`created_at`),
  CONSTRAINT `fk_orders_user_id`
    FOREIGN KEY (`user_id`)       REFERENCES `users`  (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_paid_by_admin`
    FOREIGN KEY (`paid_by_admin`) REFERENCES `users`  (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_offer_id`
    FOREIGN KEY (`offer_id`)      REFERENCES `offers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Customer order headers with denormalized shipping address';

-- =============================================================================
-- TABLE: order_items
-- =============================================================================
CREATE TABLE `order_items` (
  `id`           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id`     BIGINT UNSIGNED  NOT NULL,
  `product_id`   BIGINT UNSIGNED      NULL DEFAULT NULL,
  `variant_id`   BIGINT UNSIGNED      NULL DEFAULT NULL,
  -- Snapshots at time of order
  `product_name` VARCHAR(255)     NOT NULL,
  `variant_info` VARCHAR(120)         NULL DEFAULT NULL COMMENT 'e.g. 2Y / Pink',
  `unit_price`   DECIMAL(10,2)    NOT NULL,
  `quantity`     SMALLINT UNSIGNED    NOT NULL,
  `line_total`   DECIMAL(10,2)    NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order_id`   (`order_id`),
  KEY `idx_order_items_product_id` (`product_id`),
  CONSTRAINT `fk_order_items_order_id`
    FOREIGN KEY (`order_id`)   REFERENCES `orders`           (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product_id`
    FOREIGN KEY (`product_id`) REFERENCES `products`         (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_items_variant_id`
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Individual line items within an order — snapshots product & variant data';

-- =============================================================================
-- TABLE: order_status_history
-- =============================================================================
CREATE TABLE `order_status_history` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`    BIGINT UNSIGNED NOT NULL,
  `from_status` VARCHAR(30)         NULL DEFAULT NULL,
  `to_status`   VARCHAR(30)     NOT NULL,
  `changed_by`  BIGINT UNSIGNED     NULL DEFAULT NULL,
  `note`        VARCHAR(500)        NULL DEFAULT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_status_history_order_id` (`order_id`),
  CONSTRAINT `fk_order_status_history_order_id`
    FOREIGN KEY (`order_id`)   REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_status_history_changed_by`
    FOREIGN KEY (`changed_by`) REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Full audit trail of order status transitions';

-- =============================================================================
-- TABLE: wishlist
-- =============================================================================
CREATE TABLE `wishlist` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wishlist_user_product` (`user_id`, `product_id`),
  CONSTRAINT `fk_wishlist_user_id`
    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wishlist_product_id`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-user saved/wishlisted products';

-- =============================================================================
-- TABLE: settings (key-value store)
-- Note: Primary key is the string key, so no BIGINT AI here.
-- =============================================================================
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT             NULL DEFAULT NULL,
  `setting_type`  VARCHAR(20)  NOT NULL DEFAULT 'string' COMMENT 'string | integer | boolean',
  `label`         VARCHAR(200)     NULL DEFAULT NULL,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Site-wide configuration key-value pairs';

-- =============================================================================
-- TABLE: audit_log
-- =============================================================================
CREATE TABLE `audit_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`   BIGINT UNSIGNED     NULL DEFAULT NULL,
  `action`     VARCHAR(100)    NOT NULL COMMENT 'e.g. ORDER_MARKED_PAID, PRODUCT_UPDATED',
  `entity`     VARCHAR(60)         NULL DEFAULT NULL COMMENT 'Table name, e.g. orders',
  `entity_id`  BIGINT UNSIGNED     NULL DEFAULT NULL,
  `details`    JSON                NULL DEFAULT NULL,
  `ip_address` VARCHAR(45)         NULL DEFAULT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_log_admin_id`   (`admin_id`),
  KEY `idx_audit_log_entity`     (`entity`, `entity_id`),
  KEY `idx_audit_log_created_at` (`created_at`),
  CONSTRAINT `fk_audit_log_admin_id`
    FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable admin action log — append-only';

-- =============================================================================
-- TABLE: login_attempts
-- Old rows should be pruned periodically (e.g. via a scheduled event or cron)
-- to keep this table lean:
--   DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 30 DAY;
-- =============================================================================
CREATE TABLE `login_attempts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(255)    NOT NULL,
  `ip`         VARCHAR(45)     NOT NULL,
  `success`    TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_email_ts` (`email`, `created_at`),
  KEY `idx_login_attempts_ip_ts`    (`ip`,    `created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Brute-force tracking — prune rows older than 30 days periodically';

-- =============================================================================
-- TABLE: password_resets (expiring password reset tokens)
-- =============================================================================
CREATE TABLE `password_resets` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(255)    NOT NULL,
  `token_hash` VARCHAR(64)     NOT NULL,
  `expires_at` DATETIME        NOT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_password_resets_email` (`email`),
  KEY `idx_password_resets_token` (`token_hash`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Expiring password reset tokens';

-- =============================================================================
-- TABLE: banners (hero slider and promo banners)
-- =============================================================================
CREATE TABLE `banners` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(255)    NOT NULL,
  `subtitle`    VARCHAR(500)        NULL DEFAULT NULL,
  `badge_text`  VARCHAR(100)        NULL DEFAULT NULL,
  `button_text` VARCHAR(100)    NOT NULL DEFAULT 'Shop Now',
  `button_url`  VARCHAR(255)    NOT NULL DEFAULT '/collection.php',
  `image_url`   VARCHAR(500)    NOT NULL,
  `bg_color`    VARCHAR(50)         NULL DEFAULT NULL,
  `sort_order`  SMALLINT        NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_banners_is_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Homepage hero carousel and collection promotion banners';

-- =============================================================================
-- TABLE: reviews (customer product reviews with moderation)
-- =============================================================================
CREATE TABLE `reviews` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`        BIGINT UNSIGNED NOT NULL,
  `user_id`           BIGINT UNSIGNED     NULL DEFAULT NULL,
  `author_name`       VARCHAR(120)    NOT NULL,
  `rating`            TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `review_title`      VARCHAR(200)        NULL DEFAULT NULL,
  `review_text`       TEXT            NOT NULL,
  `is_verified_buyer` TINYINT(1)      NOT NULL DEFAULT 1,
  `is_approved`       TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reviews_product_id` (`product_id`),
  KEY `idx_reviews_approved`   (`is_approved`),
  CONSTRAINT `fk_reviews_product_id`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_user_id`
    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Customer reviews on products with moderation';

-- =============================================================================
-- TABLE: subscribers (newsletter email signups)
-- =============================================================================
CREATE TABLE `subscribers` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(255)    NOT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subscribers_email` (`email`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Footer newsletter subscribers';

-- =============================================================================
-- TABLE: messages (contact us form submissions)
-- =============================================================================
CREATE TABLE `messages` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120)    NOT NULL,
  `email`      VARCHAR(255)    NOT NULL,
  `phone`      VARCHAR(20)         NULL DEFAULT NULL,
  `subject`    VARCHAR(200)        NULL DEFAULT NULL,
  `message`    TEXT            NOT NULL,
  `is_read`    TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Inquiries submitted via contact us page';

-- =============================================================================
-- =============================================================================
-- SEED DATA
-- =============================================================================
-- =============================================================================

-- ---------------------------------------------------------------------------
-- SEED: Categories
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `categories` (`name`, `slug`, `sort_order`) VALUES
('Girls Wear',   'girls-wear',   1),
('Boys Wear',    'boys-wear',    2),
('Infant Wear',  'infant-wear',  3),
('Festive Wear', 'festive-wear', 4);

-- Subcategories for Girls Wear
INSERT IGNORE INTO `categories` (`parent_id`, `name`, `slug`, `sort_order`) VALUES
((SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'girls-wear' LIMIT 1) AS t), 'Frocks', 'girls-frocks', 1),
((SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'girls-wear' LIMIT 1) AS t), 'Tops & Bottoms', 'girls-tops-bottoms', 2),
((SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'girls-wear' LIMIT 1) AS t), 'Palazzo Sets', 'girls-palazzo-sets', 3),
((SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'girls-wear' LIMIT 1) AS t), 'Pattu Pavadai', 'girls-pattu-pavadai', 4),
((SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'girls-wear' LIMIT 1) AS t), 'Casual Wear', 'girls-casual-wear', 5);

-- Subcategories for Boys Wear
INSERT IGNORE INTO `categories` (`parent_id`, `name`, `slug`, `sort_order`) VALUES
((SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'boys-wear' LIMIT 1) AS t), 'T-Shirts', 'boys-tshirts', 1),
((SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'boys-wear' LIMIT 1) AS t), 'Shirts', 'boys-shirts', 2),
((SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'boys-wear' LIMIT 1) AS t), 'Shorts', 'boys-shorts', 3),
((SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'boys-wear' LIMIT 1) AS t), 'Jeans', 'boys-jeans', 4),
((SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'boys-wear' LIMIT 1) AS t), 'Casual Sets', 'boys-casual-sets', 5),
((SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'boys-wear' LIMIT 1) AS t), 'Ethnic Wear', 'boys-ethnic-wear', 6);

-- ---------------------------------------------------------------------------
-- SEED: 12 Sample Products
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `products`
  (`category_id`, `name`, `slug`, `short_desc`,
   `base_price`, `compare_price`, `discount_pct`,
   `material`, `design`, `neck`, `sleeves`, `closure`,
   `is_new`, `is_featured`, `is_active`)
VALUES

-- 1. Floral Frock (Girls Wear)
(
  (SELECT `id` FROM `categories` WHERE `slug` = 'girls-wear' LIMIT 1),
  'Floral Frock',
  'floral-frock',
  'A charming floral frock in soft cotton — perfect for everyday playdates.',
  599.00, 799.00, 25,
  'Cotton', 'Floral', 'Round Neck', 'Short Sleeves', 'Zip',
  1, 1, 1
),

-- 2. Embroidered Lehenga Set (Girls Wear)
(
  (SELECT `id` FROM `categories` WHERE `slug` = 'girls-wear' LIMIT 1),
  'Embroidered Lehenga Set',
  'embroidered-lehenga-set',
  'Vibrant embroidered lehenga set with dupatta — a festival favourite.',
  1299.00, 1799.00, 28,
  'Georgette', 'Embroidered', 'Square Neck', 'Sleeveless', 'Hook & Eye',
  1, 0, 1
),

-- 3. Printed Cotton Kurti (Girls Wear)
(
  (SELECT `id` FROM `categories` WHERE `slug` = 'girls-wear' LIMIT 1),
  'Printed Cotton Kurti',
  'printed-cotton-kurti',
  'Breezy printed kurti in pure cotton — cool and comfortable for school.',
  449.00, 599.00, 25,
  'Cotton', 'Printed', 'V-Neck', '3/4 Sleeves', 'Button',
  1, 0, 1
),

-- 4. Striped Cargo Shorts (Boys Wear)
(
  (SELECT `id` FROM `categories` WHERE `slug` = 'boys-wear' LIMIT 1),
  'Striped Cargo Shorts',
  'striped-cargo-shorts',
  'Rugged cargo shorts with stripe detailing — built for active boys.',
  349.00, 499.00, 30,
  'Cotton Twill', 'Striped', NULL, NULL, 'Drawstring',
  1, 0, 1
),

-- 5. Denim Dungaree (Boys Wear)
(
  (SELECT `id` FROM `categories` WHERE `slug` = 'boys-wear' LIMIT 1),
  'Denim Dungaree',
  'denim-dungaree',
  'Classic denim dungaree with adjustable straps — timeless style for boys.',
  699.00, 999.00, 30,
  'Denim', 'Solid', NULL, NULL, 'Button',
  1, 1, 1
),

-- 6. Polo Neck T-Shirt (Boys Wear)
(
  (SELECT `id` FROM `categories` WHERE `slug` = 'boys-wear' LIMIT 1),
  'Polo Neck T-Shirt',
  'polo-neck-t-shirt',
  'Smart polo t-shirt in breathable cotton piqué — great for casual days.',
  299.00, 399.00, 25,
  'Cotton Piqué', 'Solid', 'Polo Neck', 'Short Sleeves', 'Button Placket',
  1, 0, 1
),

-- 7. Smocked Romper (Infant Wear)
(
  (SELECT `id` FROM `categories` WHERE `slug` = 'infant-wear' LIMIT 1),
  'Smocked Romper',
  'smocked-romper',
  'Adorable smocked romper in soft organic cotton — gentle on baby skin.',
  549.00, 699.00, 21,
  'Organic Cotton', 'Smocked', 'Round Neck', 'Short Sleeves', 'Snap Button',
  1, 1, 1
),

-- 8. Cotton Onesie Set (Infant Wear)
(
  (SELECT `id` FROM `categories` WHERE `slug` = 'infant-wear' LIMIT 1),
  'Cotton Onesie Set',
  'cotton-onesie-set',
  'Pack of 2 soft cotton onesies — everyday comfort for your little one.',
  399.00, 549.00, 27,
  'Cotton', 'Solid', 'Round Neck', 'Long Sleeves', 'Snap Button',
  1, 0, 1
),

-- 9. Velvet Sherpa Jacket (Infant Wear)
(
  (SELECT `id` FROM `categories` WHERE `slug` = 'infant-wear' LIMIT 1),
  'Velvet Sherpa Jacket',
  'velvet-sherpa-jacket',
  'Plush velvet outer with sherpa lining — keeps baby snug in winter.',
  799.00, 1099.00, 27,
  'Velvet / Sherpa', 'Solid', NULL, 'Long Sleeves', 'Zip',
  1, 0, 1
),

-- 10. Zari Work Sharara Set (Festive Wear)
(
  (SELECT `id` FROM `categories` WHERE `slug` = 'festive-wear' LIMIT 1),
  'Zari Work Sharara Set',
  'zari-work-sharara-set',
  'Luxurious zari-embroidered sharara set — made to dazzle at every celebration.',
  1599.00, 2199.00, 27,
  'Silk', 'Zari Work', 'Boat Neck', '3/4 Sleeves', 'Hook & Eye',
  1, 1, 1
),

-- 11. Silk Kurta Pyjama (Festive Wear — Boys)
(
  (SELECT `id` FROM `categories` WHERE `slug` = 'festive-wear' LIMIT 1),
  'Silk Kurta Pyjama',
  'silk-kurta-pyjama',
  'Elegant silk kurta pyjama set — the perfect festive outfit for little boys.',
  1199.00, 1699.00, 29,
  'Art Silk', 'Solid', 'Mandarin Collar', 'Full Sleeves', 'Button',
  1, 0, 1
),

-- 12. Mirror Work Ghagra (Festive Wear — Girls)
(
  (SELECT `id` FROM `categories` WHERE `slug` = 'festive-wear' LIMIT 1),
  'Mirror Work Ghagra',
  'mirror-work-ghagra',
  'Vibrant mirror-work ghagra choli — shines bright at every festive occasion.',
  1399.00, 1999.00, 30,
  'Rayon', 'Mirror Work', 'Off-Shoulder', 'Sleeveless', 'Tie-Up',
  1, 1, 1
);

-- ---------------------------------------------------------------------------
-- SEED: Product Variants
-- ---------------------------------------------------------------------------

-- 1. Floral Frock — Girls; sizes 2Y-5Y, colors: Peach, Pink
INSERT IGNORE INTO `product_variants` (`product_id`, `age_label`, `color`, `sku`, `stock_qty`) VALUES
((SELECT `id` FROM `products` WHERE `slug`='floral-frock' LIMIT 1), '2Y', 'Peach', 'FF-2Y-PCH', 15),
((SELECT `id` FROM `products` WHERE `slug`='floral-frock' LIMIT 1), '3Y', 'Peach', 'FF-3Y-PCH', 20),
((SELECT `id` FROM `products` WHERE `slug`='floral-frock' LIMIT 1), '4Y', 'Pink',  'FF-4Y-PNK', 18),
((SELECT `id` FROM `products` WHERE `slug`='floral-frock' LIMIT 1), '5Y', 'Pink',  'FF-5Y-PNK', 12);

-- 2. Embroidered Lehenga Set — Girls; sizes 2Y-5Y
INSERT IGNORE INTO `product_variants` (`product_id`, `age_label`, `color`, `sku`, `stock_qty`) VALUES
((SELECT `id` FROM `products` WHERE `slug`='embroidered-lehenga-set' LIMIT 1), '2Y', 'Red',    'ELS-2Y-RED', 10),
((SELECT `id` FROM `products` WHERE `slug`='embroidered-lehenga-set' LIMIT 1), '3Y', 'Red',    'ELS-3Y-RED', 14),
((SELECT `id` FROM `products` WHERE `slug`='embroidered-lehenga-set' LIMIT 1), '4Y', 'Purple', 'ELS-4Y-PRP', 12),
((SELECT `id` FROM `products` WHERE `slug`='embroidered-lehenga-set' LIMIT 1), '5Y', 'Purple', 'ELS-5Y-PRP', 10);

-- 3. Printed Cotton Kurti — Girls; sizes 2Y-5Y
INSERT IGNORE INTO `product_variants` (`product_id`, `age_label`, `color`, `sku`, `stock_qty`) VALUES
((SELECT `id` FROM `products` WHERE `slug`='printed-cotton-kurti' LIMIT 1), '2Y', 'Yellow', 'PCK-2Y-YEL', 20),
((SELECT `id` FROM `products` WHERE `slug`='printed-cotton-kurti' LIMIT 1), '3Y', 'Yellow', 'PCK-3Y-YEL', 25),
((SELECT `id` FROM `products` WHERE `slug`='printed-cotton-kurti' LIMIT 1), '4Y', 'Green',  'PCK-4Y-GRN', 18),
((SELECT `id` FROM `products` WHERE `slug`='printed-cotton-kurti' LIMIT 1), '5Y', 'Green',  'PCK-5Y-GRN', 15);

-- 4. Striped Cargo Shorts — Boys; sizes 2Y-5Y
INSERT IGNORE INTO `product_variants` (`product_id`, `age_label`, `color`, `sku`, `stock_qty`) VALUES
((SELECT `id` FROM `products` WHERE `slug`='striped-cargo-shorts' LIMIT 1), '2Y', 'Navy',  'SCS-2Y-NVY', 20),
((SELECT `id` FROM `products` WHERE `slug`='striped-cargo-shorts' LIMIT 1), '3Y', 'Navy',  'SCS-3Y-NVY', 22),
((SELECT `id` FROM `products` WHERE `slug`='striped-cargo-shorts' LIMIT 1), '4Y', 'Khaki', 'SCS-4Y-KHK', 18),
((SELECT `id` FROM `products` WHERE `slug`='striped-cargo-shorts' LIMIT 1), '5Y', 'Khaki', 'SCS-5Y-KHK', 15);

-- 5. Denim Dungaree — Boys; sizes 2Y-5Y
INSERT IGNORE INTO `product_variants` (`product_id`, `age_label`, `color`, `sku`, `stock_qty`) VALUES
((SELECT `id` FROM `products` WHERE `slug`='denim-dungaree' LIMIT 1), '2Y', 'Blue',      'DD-2Y-BLU', 20),
((SELECT `id` FROM `products` WHERE `slug`='denim-dungaree' LIMIT 1), '3Y', 'Blue',      'DD-3Y-BLU', 20),
((SELECT `id` FROM `products` WHERE `slug`='denim-dungaree' LIMIT 1), '4Y', 'Dark Blue', 'DD-4Y-DBL', 16),
((SELECT `id` FROM `products` WHERE `slug`='denim-dungaree' LIMIT 1), '5Y', 'Dark Blue', 'DD-5Y-DBL', 12);

-- 6. Polo Neck T-Shirt — Boys; sizes 2Y-5Y
INSERT IGNORE INTO `product_variants` (`product_id`, `age_label`, `color`, `sku`, `stock_qty`) VALUES
((SELECT `id` FROM `products` WHERE `slug`='polo-neck-t-shirt' LIMIT 1), '2Y', 'White', 'PNT-2Y-WHT', 25),
((SELECT `id` FROM `products` WHERE `slug`='polo-neck-t-shirt' LIMIT 1), '3Y', 'White', 'PNT-3Y-WHT', 25),
((SELECT `id` FROM `products` WHERE `slug`='polo-neck-t-shirt' LIMIT 1), '4Y', 'Red',   'PNT-4Y-RED', 20),
((SELECT `id` FROM `products` WHERE `slug`='polo-neck-t-shirt' LIMIT 1), '5Y', 'Red',   'PNT-5Y-RED', 18);

-- 7. Smocked Romper — Infant; sizes 0-6M, 6-12M, 1Y
INSERT IGNORE INTO `product_variants` (`product_id`, `age_label`, `color`, `sku`, `stock_qty`) VALUES
((SELECT `id` FROM `products` WHERE `slug`='smocked-romper' LIMIT 1), '0-6M',  'Peach', 'SR-06M-PCH', 15),
((SELECT `id` FROM `products` WHERE `slug`='smocked-romper' LIMIT 1), '6-12M', 'Peach', 'SR-612M-PCH', 15),
((SELECT `id` FROM `products` WHERE `slug`='smocked-romper' LIMIT 1), '1Y',    'White', 'SR-1Y-WHT', 12);

-- 8. Cotton Onesie Set — Infant; sizes 0-6M, 6-12M, 1Y
INSERT IGNORE INTO `product_variants` (`product_id`, `age_label`, `color`, `sku`, `stock_qty`) VALUES
((SELECT `id` FROM `products` WHERE `slug`='cotton-onesie-set' LIMIT 1), '0-6M',  'Yellow', 'COS-06M-YEL',  20),
((SELECT `id` FROM `products` WHERE `slug`='cotton-onesie-set' LIMIT 1), '6-12M', 'Yellow', 'COS-612M-YEL', 22),
((SELECT `id` FROM `products` WHERE `slug`='cotton-onesie-set' LIMIT 1), '1Y',    'Blue',   'COS-1Y-BLU',   18);

-- 9. Velvet Sherpa Jacket — Infant; sizes 0-6M, 6-12M, 1Y
INSERT IGNORE INTO `product_variants` (`product_id`, `age_label`, `color`, `sku`, `stock_qty`) VALUES
((SELECT `id` FROM `products` WHERE `slug`='velvet-sherpa-jacket' LIMIT 1), '0-6M',  'Cream',  'VSJ-06M-CRM',  12),
((SELECT `id` FROM `products` WHERE `slug`='velvet-sherpa-jacket' LIMIT 1), '6-12M', 'Cream',  'VSJ-612M-CRM', 14),
((SELECT `id` FROM `products` WHERE `slug`='velvet-sherpa-jacket' LIMIT 1), '1Y',    'Maroon', 'VSJ-1Y-MRN',   10);

-- 10. Zari Work Sharara Set — Festive; sizes 3Y-6Y
INSERT IGNORE INTO `product_variants` (`product_id`, `age_label`, `color`, `sku`, `stock_qty`) VALUES
((SELECT `id` FROM `products` WHERE `slug`='zari-work-sharara-set' LIMIT 1), '3Y', 'Gold',  'ZWS-3Y-GLD', 10),
((SELECT `id` FROM `products` WHERE `slug`='zari-work-sharara-set' LIMIT 1), '4Y', 'Gold',  'ZWS-4Y-GLD', 12),
((SELECT `id` FROM `products` WHERE `slug`='zari-work-sharara-set' LIMIT 1), '5Y', 'Green', 'ZWS-5Y-GRN', 10),
((SELECT `id` FROM `products` WHERE `slug`='zari-work-sharara-set' LIMIT 1), '6Y', 'Green', 'ZWS-6Y-GRN',  8);

-- 11. Silk Kurta Pyjama — Festive Boys; sizes 3Y-6Y
INSERT IGNORE INTO `product_variants` (`product_id`, `age_label`, `color`, `sku`, `stock_qty`) VALUES
((SELECT `id` FROM `products` WHERE `slug`='silk-kurta-pyjama' LIMIT 1), '3Y', 'Ivory',  'SKP-3Y-IVR', 12),
((SELECT `id` FROM `products` WHERE `slug`='silk-kurta-pyjama' LIMIT 1), '4Y', 'Ivory',  'SKP-4Y-IVR', 14),
((SELECT `id` FROM `products` WHERE `slug`='silk-kurta-pyjama' LIMIT 1), '5Y', 'Maroon', 'SKP-5Y-MRN', 10),
((SELECT `id` FROM `products` WHERE `slug`='silk-kurta-pyjama' LIMIT 1), '6Y', 'Maroon', 'SKP-6Y-MRN',  8);

-- 12. Mirror Work Ghagra — Festive Girls; sizes 3Y-6Y
INSERT IGNORE INTO `product_variants` (`product_id`, `age_label`, `color`, `sku`, `stock_qty`) VALUES
((SELECT `id` FROM `products` WHERE `slug`='mirror-work-ghagra' LIMIT 1), '3Y', 'Red',    'MWG-3Y-RED', 12),
((SELECT `id` FROM `products` WHERE `slug`='mirror-work-ghagra' LIMIT 1), '4Y', 'Red',    'MWG-4Y-RED', 14),
((SELECT `id` FROM `products` WHERE `slug`='mirror-work-ghagra' LIMIT 1), '5Y', 'Orange', 'MWG-5Y-ORG', 10),
((SELECT `id` FROM `products` WHERE `slug`='mirror-work-ghagra' LIMIT 1), '6Y', 'Orange', 'MWG-6Y-ORG',  8);

-- ---------------------------------------------------------------------------
-- SEED: Default site settings
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `label`) VALUES
('whatsapp_number',              '919XXXXXXXXX',                                              'string',  'WhatsApp number for order messages'),
('upi_id',                       'herbama@upi',                                               'string',  'UPI payment ID'),
('announcement_text',            '🎉 New Festive Collection Now Live! Free shipping above ₹999', 'string',  'Top banner announcement text'),
('shipping_india',               '99',                                                        'integer', 'Flat shipping charge India (INR)'),
('shipping_uae',                 '1499',                                                      'integer', 'Flat shipping charge UAE/Dubai (INR)'),
('free_shipping_threshold_india','999',                                                        'integer', 'Free shipping above this amount for India'),
('customization_days',           '5',                                                         'integer', 'Working days for custom orders'),
('upi_qr_image',                 '',                                                          'string',  'Path to UPI QR code image'),
('maintenance_mode',             '0',                                                         'boolean', 'Enable maintenance mode'),
('tax_inclusive',                '1',                                                         'boolean', 'Prices are tax-inclusive'),
('instagram_url',                'https://instagram.com/herbamafashions',                    'string',  'Instagram profile URL'),
('facebook_url',                 'https://facebook.com/herbamafashions',                     'string',  'Facebook page URL'),
('announcement_bar_left',        'Pan-India Shipping | International Dispatch to UAE / Dubai', 'string', 'Announcement bar left text'),
('announcement_bar_right',       'No COD | 0-3 Years: Ready to Dispatch | Customization: 5 Working Days', 'string', 'Announcement bar right text');

-- ---------------------------------------------------------------------------
-- SEED: Promotional Banners (Hero Slider)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `banners` (`title`, `subtitle`, `badge_text`, `button_text`, `button_url`, `image_url`, `bg_color`, `sort_order`) VALUES
('Adorable Styles for Little Princesses', 'Handcrafted frocks, lehengas & gowns tailored with premium breathable fabrics.', 'NEW ARRIVALS 2024', 'Shop Girls Collection', '/collection.php?cat=girls', '/assets/images/banner1.svg', '#FAF4EB', 1),
('Trendy & Tough Styles for Active Boys', 'Breathable cotton shirts, dungarees and shorts built for comfort and playtime adventures.', 'BOYS ESSENTIALS', 'Shop Boys Collection', '/collection.php?cat=boys', '/assets/images/banner2.svg', '#F5EFEB', 2),
('Festive Royalty for Every Celebration', 'Exquisite silk kurtas, zari shararas and traditional ethnic outfits for festive days.', 'FESTIVE COLLECTION', 'Explore Festive Edit', '/collection.php?cat=festive', '/assets/images/banner3.svg', '#FDF8F0', 3);

-- ---------------------------------------------------------------------------
-- SEED: Offers & Promo Discounts
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `offers` (`title`, `code`, `type`, `value`, `min_order_value`, `max_discount`, `applies_to`, `is_active`) VALUES
('Everyday Essentials Special Offer', 'ESSENTIALS15', 'percent', 15.00, 799.00, 300.00, 'all', 1),
('Flat 10% OFF on Boys Collection', 'BOYS10', 'percent', 10.00, 499.00, 250.00, 'all', 1),
('Festive Special - Save ₹200', 'FESTIVE200', 'fixed_amount', 200.00, 1499.00, 200.00, 'all', 1);

-- ---------------------------------------------------------------------------
-- SEED: Product Reviews
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `reviews` (`product_id`, `author_name`, `rating`, `review_title`, `review_text`, `is_verified_buyer`, `is_approved`) VALUES
((SELECT `id` FROM `products` WHERE `slug` = 'floral-frock' LIMIT 1), 'Priya Sundaram', 5, 'Super soft cotton!', 'The material is so gentle on my 2-year old daughter’s skin. Stitching is top notch!', 1, 1),
((SELECT `id` FROM `products` WHERE `slug` = 'floral-frock' LIMIT 1), 'Ananya Mishra', 5, 'Looks exactly like picture', 'Very elegant pastel floral print and quick dispatch. Highly recommend Herbama!', 1, 1),
((SELECT `id` FROM `products` WHERE `slug` = 'embroidered-lehenga-set' LIMIT 1), 'Sneha Patel', 5, 'Perfect for Diwali!', 'The embroidery has no rough edges on the inside and my daughter twirled in it all evening.', 1, 1),
((SELECT `id` FROM `products` WHERE `slug` = 'denim-dungaree' LIMIT 1), 'Kavita Rao', 5, 'Sturdy & stylish', 'My son wore it all day long and was totally comfy. Pockets are adorable.', 1, 1);

-- =============================================================================
SET FOREIGN_KEY_CHECKS=1;
-- =============================================================================
