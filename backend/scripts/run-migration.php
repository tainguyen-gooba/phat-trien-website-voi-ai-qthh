<?php
/**
 * Run migration for Welcome Voucher & Anti-Duplication System
 */
require_once dirname(__DIR__, 2) . '/config/database.php';

try {
    $db = getDB();
    echo "[INFO] Connected to DB successfully.\n";

    // 1. Check & Add users.cccd
    $colCheck = $db->query("SHOW COLUMNS FROM users LIKE 'cccd'")->fetchAll();
    if (empty($colCheck)) {
        echo "[MIGRATE] Adding 'cccd' column to 'users' table...\n";
        $db->exec("ALTER TABLE `users` ADD COLUMN `cccd` VARCHAR(20) DEFAULT NULL AFTER `phone`, ADD UNIQUE KEY `uq_users_cccd` (`cccd`)");
        echo "[OK] Added 'cccd' column and UNIQUE index to 'users'.\n";
    } else {
        echo "[INFO] Column 'users.cccd' already exists.\n";
    }

    // 2. Create customer_voucher_claims table
    echo "[MIGRATE] Creating table 'customer_voucher_claims' if not exists...\n";
    $sqlClaims = "CREATE TABLE IF NOT EXISTS `customer_voucher_claims` (
      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      `user_id` bigint(20) unsigned NOT NULL,
      `full_name` varchar(150) NOT NULL,
      `phone` varchar(20) NOT NULL,
      `identity_number` varchar(50) NOT NULL COMMENT 'Số định danh cá nhân CCCD/CMND',
      `address` varchar(255) DEFAULT NULL COMMENT 'Địa chỉ nhận hàng khách xác nhận',
      `coupon_id` bigint(20) unsigned DEFAULT NULL,
      `coupon_code` varchar(50) NOT NULL,
      `claimed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_user_voucher` (`user_id`),
      UNIQUE KEY `uniq_identity_voucher` (`identity_number`),
      KEY `idx_phone_voucher` (`phone`),
      CONSTRAINT `fk_claims_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sqlClaims);
    echo "[OK] Table 'customer_voucher_claims' is ready.\n";

    // 3. Ensure global NEW50K coupon exists
    $couponCheck = $db->prepare("SELECT id FROM coupons WHERE code = 'NEW50K' LIMIT 1");
    $couponCheck->execute();
    if (!$couponCheck->fetch()) {
        echo "[MIGRATE] Inserting global NEW50K fixed coupon...\n";
        $db->exec("INSERT INTO `coupons` (`code`, `title`, `discount_type`, `discount_value`, `min_order_value`, `max_discount_amount`, `usage_limit`, `usage_count`, `start_date`, `end_date`, `is_active`, `created_at`)
                   VALUES ('NEW50K', 'Voucher bạn mới - Giảm ngay 50.000đ', 'fixed', 50000.00, 0.00, 50000.00, 10000, 0, '2026-01-01 00:00:00', '2027-12-31 23:59:59', 1, NOW())");
        echo "[OK] Inserted global NEW50K coupon.\n";
    } else {
        echo "[INFO] Global NEW50K coupon already exists.\n";
    }

    echo "\n=== ALL MIGRATIONS COMPLETED SUCCESSFULLY ===\n";
} catch (Exception $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
