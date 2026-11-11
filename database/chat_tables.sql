-- ===================================================================
-- FASTFOOD AI CHAT TABLES
-- Lưu trữ phiên hội thoại và lịch sử chat của khách hàng với Trợ lý AI
-- ===================================================================

USE `fastfood_db`;

-- 1. Bảng phiên trò chuyện AI (Hỗ trợ cả khách vãng lai và khách đã đăng nhập)
CREATE TABLE IF NOT EXISTS `ai_chat_sessions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(64) NOT NULL UNIQUE,
  `user_id` BIGINT UNSIGNED NULL,
  `customer_name` VARCHAR(150) NULL DEFAULT 'Khách hàng',
  `title` VARCHAR(255) NOT NULL DEFAULT 'Hội thoại mới',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_chat_session_user` (`user_id`),
  INDEX `idx_chat_session_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng lịch sử tin nhắn trò chuyện AI
CREATE TABLE IF NOT EXISTS `ai_chat_messages` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(64) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `sender` ENUM('user', 'assistant', 'system') NOT NULL DEFAULT 'user',
  `message` TEXT NOT NULL,
  `metadata` LONGTEXT NULL, -- JSON chứa danh sách sản phẩm gợi ý, đơn hàng tra cứu
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_chat_msg_session` (`session_id`),
  INDEX `idx_chat_msg_user` (`user_id`),
  INDEX `idx_chat_msg_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

