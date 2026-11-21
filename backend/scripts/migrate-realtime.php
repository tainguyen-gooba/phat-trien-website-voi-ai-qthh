<?php
/**
 * FASTFOOD Realtime Tables Migration (PHP)
 * Chạy lệnh: php backend/scripts/migrate-realtime.php
 */
require_once dirname(__DIR__) . '/src/config/Database.php';

use FastFood\Config\Database;

echo "====================================================\n";
echo "      MIGRATE CƠ SỞ DỮ LIỆU REALTIME (PHP)          \n";
echo "====================================================\n";

try {
    $pdo = Database::getConnection();

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS website_content (
        collection_name VARCHAR(50) PRIMARY KEY,
        content JSON NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS realtime_orders (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_code VARCHAR(30) NOT NULL UNIQUE,
        user_id BIGINT UNSIGNED NOT NULL,
        order_data JSON NOT NULL,
        status ENUM('awaiting_payment', 'payment_failed', 'pending', 'preparing', 'delivering', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_realtime_orders_user (user_id, id),
        INDEX idx_realtime_orders_status (status)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS realtime_payments (
        order_code VARCHAR(30) PRIMARY KEY,
        payment_method ENUM('banking', 'momo', 'vnpay') NOT NULL DEFAULT 'banking',
        request_id VARCHAR(64) NULL,
        amount BIGINT UNSIGNED NOT NULL,
        status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
        provider_transaction_id VARCHAR(100) NULL,
        payment_url TEXT NULL,
        qr_url TEXT NULL,
        expires_at DATETIME NULL,
        retry_until DATETIME NULL,
        paid_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_realtime_payments_status (status)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "[SUCCESS] Đã hoàn tất migrate các bảng realtime thành công!\n";
} catch (Exception $e) {
    echo "[ERROR] Lỗi: " . $e->getMessage() . "\n";
}

