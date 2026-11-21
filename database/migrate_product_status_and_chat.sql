-- ===================================================================
-- FASTFOOD DATABASE MIGRATION
-- Thêm cột status vào bảng products và message_type vào ai_chat_messages
-- ===================================================================

USE `fastfood_db`;

-- 1. Bổ sung cột status vào bảng products nếu chưa tồn tại
-- Giá trị enum: 'in_stock', 'out_of_stock', 'unavailable_today'
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'products' 
      AND COLUMN_NAME = 'status'
);

SET @stmt = IF(@col_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `status` ENUM(\'in_stock\', \'out_of_stock\', \'unavailable_today\') NOT NULL DEFAULT \'in_stock\' AFTER `is_available`, ADD INDEX `idx_products_status` (`status`)',
    'SELECT "Column products.status already exists" AS info'
);

PREPARE s1 FROM @stmt;
EXECUTE s1;
DEALLOCATE PREPARE s1;

-- Đồng bộ dữ liệu hiện tại từ is_available sang status
UPDATE `products` 
SET `status` = CASE 
    WHEN `is_available` = 1 THEN 'in_stock'
    ELSE 'out_of_stock'
END
WHERE `status` IS NULL OR `status` = 'in_stock';

-- 2. Bổ sung cột message_type vào bảng ai_chat_messages nếu chưa tồn tại
-- Giá trị enum: 'text', 'image', 'product_card'
SET @col_chat_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'ai_chat_messages' 
      AND COLUMN_NAME = 'message_type'
);

SET @stmt_chat = IF(@col_chat_exists = 0,
    'ALTER TABLE `ai_chat_messages` ADD COLUMN `message_type` ENUM(\'text\', \'image\', \'product_card\') NOT NULL DEFAULT \'text\' AFTER `sender`, ADD INDEX `idx_chat_msg_type` (`message_type`)',
    'SELECT "Column ai_chat_messages.message_type already exists" AS info'
);

PREPARE s2 FROM @stmt_chat;
EXECUTE s2;
DEALLOCATE PREPARE s2;

-- 3. Bổ sung cột message_type vào bảng chat_messages (nếu bảng tồn tại)
SET @table_legacy_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_messages'
);

SET @col_legacy_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_messages'
      AND COLUMN_NAME = 'message_type'
);

SET @stmt_legacy = IF(@table_legacy_exists > 0 AND @col_legacy_exists = 0,
    'ALTER TABLE `chat_messages` ADD COLUMN `message_type` ENUM(\'text\', \'image\', \'product_card\') NOT NULL DEFAULT \'text\' AFTER `sender_role`',
    'SELECT "Legacy chat_messages message_type check skipped or column exists" AS info'
);

PREPARE s3 FROM @stmt_legacy;
EXECUTE s3;
DEALLOCATE PREPARE s3;
