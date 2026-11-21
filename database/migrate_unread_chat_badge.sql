-- ===================================================================
-- FASTFOOD DATABASE MIGRATION
-- Thêm cột is_read và index vào ai_chat_messages và chat_messages
-- Phục vụ Unread Notification Badge cho Admin Chat
-- ===================================================================

USE `fastfood_db`;

-- 1. Bổ sung cột is_read vào bảng ai_chat_messages nếu chưa có
SET @col_ai_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'ai_chat_messages' 
      AND COLUMN_NAME = 'is_read'
);

SET @stmt_ai = IF(@col_ai_exists = 0,
    'ALTER TABLE `ai_chat_messages` ADD COLUMN `is_read` TINYINT(1) NOT NULL DEFAULT 0 AFTER `message`',
    'SELECT "Column ai_chat_messages.is_read already exists" AS info'
);
PREPARE s1 FROM @stmt_ai;
EXECUTE s1;
DEALLOCATE PREPARE s1;

-- 2. Thêm covering index idx_chat_msg_unread vào ai_chat_messages
SET @idx_ai_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'ai_chat_messages' 
      AND INDEX_NAME = 'idx_chat_msg_unread'
);

SET @stmt_ai_idx = IF(@idx_ai_exists = 0,
    'ALTER TABLE `ai_chat_messages` ADD INDEX `idx_chat_msg_unread` (`sender`, `is_read`, `session_id`)',
    'SELECT "Index idx_chat_msg_unread already exists" AS info'
);
PREPARE s2 FROM @stmt_ai_idx;
EXECUTE s2;
DEALLOCATE PREPARE s2;

-- 3. Bổ sung cột is_read vào bảng chat_messages nếu chưa có
SET @col_chat_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'chat_messages' 
      AND COLUMN_NAME = 'is_read'
);

SET @stmt_chat = IF(@col_chat_exists = 0,
    'ALTER TABLE `chat_messages` ADD COLUMN `is_read` TINYINT(1) NOT NULL DEFAULT 0 AFTER `message`',
    'SELECT "Column chat_messages.is_read already exists" AS info'
);
PREPARE s3 FROM @stmt_chat;
EXECUTE s3;
DEALLOCATE PREPARE s3;

-- 4. Thêm index idx_chat_messages_unread vào chat_messages
SET @idx_chat_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'chat_messages' 
      AND INDEX_NAME = 'idx_chat_messages_unread'
);

SET @stmt_chat_idx = IF(@idx_chat_exists = 0,
    'ALTER TABLE `chat_messages` ADD INDEX `idx_chat_messages_unread` (`sender_role`, `is_read`, `customer_id`)',
    'SELECT "Index idx_chat_messages_unread already exists" AS info'
);
PREPARE s4 FROM @stmt_chat_idx;
EXECUTE s4;
DEALLOCATE PREPARE s4;

-- 5. Khởi tạo trạng thái đã đọc cho các bản ghi lịch sử
UPDATE `ai_chat_messages` SET `is_read` = 1 WHERE `sender` != 'user';
UPDATE `ai_chat_messages` SET `is_read` = 1 WHERE `sender` = 'user' AND `created_at` < NOW() - INTERVAL 1 HOUR;
UPDATE `chat_messages` SET `is_read` = 1 WHERE `sender_role` != 'customer';
UPDATE `chat_messages` SET `is_read` = 1 WHERE `sender_role` = 'customer' AND `created_at` < NOW() - INTERVAL 1 HOUR;
