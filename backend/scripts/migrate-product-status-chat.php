<?php
/**
 * Migration Script: Product Status & Chat Message Type
 * Thêm cột status vào products và message_type vào ai_chat_messages
 */

require_once __DIR__ . '/../src/config/Database.php';

use FastFood\Config\Database;

echo "====================================================\n";
echo " FASTFOOD DB MIGRATION: PRODUCT STATUS & CHAT TYPE \n";
echo "====================================================\n\n";

try {
    $db = Database::getConnection();
    echo "[INFO] Đã kết nối cơ sở dữ liệu thành công.\n";

    // 1. Kiểm tra và thêm cột status vào bảng products
    echo "\n[1/3] Kiểm tra bảng `products`...\n";
    $stmt = $db->query("SHOW COLUMNS FROM `products` LIKE 'status'");
    $statusCol = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$statusCol) {
        echo "  -> Chưa có cột `status`. Đang thêm cột `status` ENUM('in_stock', 'out_of_stock', 'unavailable_today')...\n";
        $db->exec("ALTER TABLE `products` 
                   ADD COLUMN `status` ENUM('in_stock', 'out_of_stock', 'unavailable_today') NOT NULL DEFAULT 'in_stock' AFTER `is_available`,
                   ADD INDEX `idx_products_status` (`status`)");
        echo "  [OK] Đã thêm cột `status` và index `idx_products_status` thành công.\n";
    } else {
        echo "  [INFO] Cột `status` đã tồn tại trong `products`. Kiểu dữ liệu: " . $statusCol['Type'] . "\n";
    }

    // Đồng bộ giá trị ban đầu từ is_available sang status
    echo "  -> Đồng bộ dữ liệu từ `is_available` sang `status`...\n";
    $syncCount = $db->exec("UPDATE `products` 
                            SET `status` = CASE 
                                WHEN `is_available` = 1 THEN 'in_stock'
                                ELSE 'out_of_stock'
                            END");
    echo "  [OK] Đã đồng bộ $syncCount dòng sản phẩm.\n";

    // Thống kê phân bố status
    $stats = $db->query("SELECT status, is_available, COUNT(*) as cnt FROM products GROUP BY status, is_available")->fetchAll(PDO::FETCH_ASSOC);
    echo "  [THỐNG KÊ PRODUCTS]:\n";
    foreach ($stats as $row) {
        echo "    - status='{$row['status']}', is_available={$row['is_available']}: {$row['cnt']} món\n";
    }

    // 2. Kiểm tra và thêm cột message_type vào bảng ai_chat_messages
    echo "\n[2/3] Kiểm tra bảng `ai_chat_messages`...\n";
    $stmt = $db->query("SHOW COLUMNS FROM `ai_chat_messages` LIKE 'message_type'");
    $msgTypeCol = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$msgTypeCol) {
        echo "  -> Chưa có cột `message_type`. Đang thêm cột `message_type` ENUM('text', 'image', 'product_card')...\n";
        $db->exec("ALTER TABLE `ai_chat_messages` 
                   ADD COLUMN `message_type` ENUM('text', 'image', 'product_card') NOT NULL DEFAULT 'text' AFTER `sender`,
                   ADD INDEX `idx_chat_msg_type` (`message_type`)");
        echo "  [OK] Đã thêm cột `message_type` và index `idx_chat_msg_type` thành công.\n";
    } else {
        echo "  [INFO] Cột `message_type` đã tồn tại trong `ai_chat_messages`. Kiểu dữ liệu: " . $msgTypeCol['Type'] . "\n";
    }

    // 3. Kiểm tra và thêm cột message_type vào bảng legacy chat_messages nếu có
    echo "\n[3/3] Kiểm tra bảng `chat_messages` (legacy sync)...\n";
    $tableCheck = $db->query("SHOW TABLES LIKE 'chat_messages'")->fetch();
    if ($tableCheck) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_messages` LIKE 'message_type'");
        $legacyCol = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$legacyCol) {
            echo "  -> Thêm cột `message_type` vào `chat_messages`...\n";
            $db->exec("ALTER TABLE `chat_messages` 
                       ADD COLUMN `message_type` ENUM('text', 'image', 'product_card') NOT NULL DEFAULT 'text' AFTER `sender_role`");
            echo "  [OK] Đã thêm cột `message_type` vào `chat_messages`.\n";
        } else {
            echo "  [INFO] Cột `message_type` đã tồn tại trong `chat_messages`.\n";
        }
    } else {
        echo "  [INFO] Bảng `chat_messages` không tồn tại, bỏ qua.\n";
    }

    // Xác minh cấu trúc cuối cùng
    echo "\n[XÁC MINH CẤU TRÚC CUỐI CÙNG]:\n";
    $pCols = $db->query("DESCRIBE `products`")->fetchAll(PDO::FETCH_ASSOC);
    $pFound = false;
    foreach ($pCols as $c) {
        if ($c['Field'] === 'status') {
            $pFound = true;
            echo "  ✓ products.status: " . $c['Type'] . " | Default: " . $c['Default'] . "\n";
        }
    }
    if (!$pFound) {
        throw new Exception("Lỗi: Không tìm thấy cột status trong bảng products sau migration!");
    }

    $mCols = $db->query("DESCRIBE `ai_chat_messages`")->fetchAll(PDO::FETCH_ASSOC);
    $mFound = false;
    foreach ($mCols as $c) {
        if ($c['Field'] === 'message_type') {
            $mFound = true;
            echo "  ✓ ai_chat_messages.message_type: " . $c['Type'] . " | Default: " . $c['Default'] . "\n";
        }
    }
    if (!$mFound) {
        throw new Exception("Lỗi: Không tìm thấy cột message_type trong bảng ai_chat_messages sau migration!");
    }

    echo "\n====================================================\n";
    echo "           MIGRATION HOÀN TẤT THÀNH CÔNG!          \n";
    echo "====================================================\n";

} catch (Exception $e) {
    echo "\n[LỖI MIGRATION]: " . $e->getMessage() . "\n";
    exit(1);
}
