-- =============================================================================
-- Migration: Rename brand to Heramba Fashion
-- Updates deployed database: settings, banners, offers, reviews, categories, products
-- Safe to run multiple times (idempotent)
-- =============================================================================

-- 1. Settings Table
-- Clear old placeholder / brand-specific URLs and IDs so they can be set via Settings
UPDATE `settings`
SET `setting_value` = ''
WHERE `setting_key` IN ('upi_id', 'instagram_url', 'facebook_url', 'youtube_url', 'pinterest_url')
  AND (`setting_value` LIKE '%herbama%' OR `setting_value` LIKE '%herabama%' OR `setting_value` LIKE '%herbamba%');

-- Clean store_email if using old domain
UPDATE `settings`
SET `setting_value` = 'admin@yourdomain.com'
WHERE `setting_key` = 'store_email'
  AND (`setting_value` LIKE '%herbama%' OR `setting_value` LIKE '%herabama%' OR `setting_value` LIKE '%herbamba%');

-- Replace brand text in general settings
UPDATE `settings`
SET `setting_value` = REPLACE(
    REPLACE(
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(`setting_value`, 'Herbama Fashions', 'Heramba Fashion'),
                    'Herabama Fashions', 'Heramba Fashion'
                ),
                'Herbamba Fashions', 'Heramba Fashion'
            ),
            'Herbama', 'Heramba Fashion'
        ),
        'Herabama', 'Heramba Fashion'
    ),
    'Herbamba', 'Heramba Fashion'
)
WHERE `setting_value` LIKE '%herbama%' OR `setting_value` LIKE '%herabama%' OR `setting_value` LIKE '%herbamba%';

-- 2. Banners Table
UPDATE `banners`
SET 
  `title` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`title`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion'),
  `subtitle` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`subtitle`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion'),
  `badge_text` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`badge_text`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion'),
  `button_text` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`button_text`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion')
WHERE `title` LIKE '%herbama%' OR `title` LIKE '%herabama%' OR `title` LIKE '%herbamba%'
   OR `subtitle` LIKE '%herbama%' OR `subtitle` LIKE '%herabama%' OR `subtitle` LIKE '%herbamba%'
   OR `badge_text` LIKE '%herbama%' OR `badge_text` LIKE '%herabama%' OR `badge_text` LIKE '%herbamba%'
   OR `button_text` LIKE '%herbama%' OR `button_text` LIKE '%herabama%' OR `button_text` LIKE '%herbamba%';

-- 3. Offers Table
UPDATE `offers`
SET 
  `title` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`title`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion'),
  `description` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`description`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion')
WHERE `title` LIKE '%herbama%' OR `title` LIKE '%herabama%' OR `title` LIKE '%herbamba%'
   OR `description` LIKE '%herbama%' OR `description` LIKE '%herabama%' OR `description` LIKE '%herbamba%';

-- 4. Reviews Table
UPDATE `reviews`
SET 
  `review_title` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`review_title`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion'),
  `review_text` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`review_text`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion'),
  `author_name` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`author_name`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion')
WHERE `review_title` LIKE '%herbama%' OR `review_title` LIKE '%herabama%' OR `review_title` LIKE '%herbamba%'
   OR `review_text` LIKE '%herbama%' OR `review_text` LIKE '%herabama%' OR `review_text` LIKE '%herbamba%'
   OR `author_name` LIKE '%herbama%' OR `author_name` LIKE '%herabama%' OR `author_name` LIKE '%herbamba%';

-- 5. Categories Table
UPDATE `categories`
SET 
  `name` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`name`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion'),
  `description` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`description`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion')
WHERE `name` LIKE '%herbama%' OR `name` LIKE '%herabama%' OR `name` LIKE '%herbamba%'
   OR `description` LIKE '%herbama%' OR `description` LIKE '%herabama%' OR `description` LIKE '%herbamba%';

-- 6. Products Table
UPDATE `products`
SET 
  `name` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`name`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion'),
  `description` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`description`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion'),
  `short_desc` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`short_desc`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion'),
  `meta_title` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`meta_title`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion'),
  `meta_desc` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`meta_desc`, 'Herbama Fashions', 'Heramba Fashion'), 'Herabama Fashions', 'Heramba Fashion'), 'Herbamba Fashions', 'Heramba Fashion'), 'Herbama', 'Heramba Fashion'), 'Herabama', 'Heramba Fashion'), 'Herbamba', 'Heramba Fashion')
WHERE `name` LIKE '%herbama%' OR `name` LIKE '%herabama%' OR `name` LIKE '%herbamba%'
   OR `description` LIKE '%herbama%' OR `description` LIKE '%herabama%' OR `description` LIKE '%herbamba%'
   OR `short_desc` LIKE '%herbama%' OR `short_desc` LIKE '%herabama%' OR `short_desc` LIKE '%herbamba%'
   OR `meta_title` LIKE '%herbama%' OR `meta_title` LIKE '%herabama%' OR `meta_title` LIKE '%herbamba%'
   OR `meta_desc` LIKE '%herbama%' OR `meta_desc` LIKE '%herabama%' OR `meta_desc` LIKE '%herbamba%';
