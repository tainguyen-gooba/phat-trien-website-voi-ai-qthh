-- FASTFOOD - Bảng quản lý yêu cầu Quên Mật Khẩu & Mã xác thực OTP
-- File: database/migrate_password_resets.sql

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `otp_code` VARCHAR(10) NOT NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `is_used` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pr_email` (`email`),
  INDEX `idx_pr_token` (`token`),
  INDEX `idx_pr_otp` (`email`, `otp_code`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
