<?php
/**
 * Migration Script: Chat Unread Notification Badge Infrastructure
 * Adds `is_read` column and covering indexes to `ai_chat_messages` and `chat_messages`.
 * Initializes historical read state.
 */

require_once __DIR__ . '/../src/config/Database.php';

use FastFood\Config\Database;

echo "========================================================\n";
echo " FASTFOOD DB MIGRATION: CHAT UNREAD BADGE INFRASTRUCTURE \n";
echo "========================================================\n\n";

try {
    $db = Database::getConnection();
    echo "[INFO] Đã kết nối cơ sở dữ liệu thành công.\n";

    // 1. Kiểm tra và thêm cột is_read vào bảng ai_chat_messages
    echo "\n[1/4] Kiểm tra bảng `ai_chat_messages` (cột is_read)...\n";
    $stmt = $db->query("SHOW COLUMNS FROM `ai_chat_messages` LIKE 'is_read'");
    $colAi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$colAi) {
        echo "  -> Chưa có cột `is_read`. Đang thêm cột `is_read` TINYINT(1) NOT NULL DEFAULT 0...\n";
        $db->exec("ALTER TABLE `ai_chat_messages` ADD COLUMN `is_read` TINYINT(1) NOT NULL DEFAULT 0 AFTER `message`");
        echo "  [OK] Đã thêm cột `is_read` vào `ai_chat_messages` thành công.\n";
    } else {
        echo "  [INFO] Cột `is_read` đã tồn tại trong `ai_chat_messages`. Kiểu dữ liệu: " . $colAi['Type'] . "\n";
    }

    // 2. Kiểm tra và thêm index idx_chat_msg_unread vào bảng ai_chat_messages
    echo "\n[2/4] Kiểm tra index `idx_chat_msg_unread` trên `ai_chat_messages`...\n";
    $idxStmt = $db->query("SHOW INDEX FROM `ai_chat_messages` WHERE Key_name = 'idx_chat_msg_unread'");
    $idxAi = $idxStmt->fetch(PDO::FETCH_ASSOC);

    if (!$idxAi) {
        echo "  -> Chưa có index `idx_chat_msg_unread`. Đang thêm covering index (sender, is_read, session_id)...\n";
        $db->exec("ALTER TABLE `ai_chat_messages` ADD INDEX `idx_chat_msg_unread` (`sender`, `is_read`, `session_id`)");
        echo "  [OK] Đã thêm index `idx_chat_msg_unread` vào `ai_chat_messages` thành công.\n";
    } else {
        echo "  [INFO] Index `idx_chat_msg_unread` đã tồn tại trên `ai_chat_messages`.\n";
    }

    // 3. Kiểm tra và thêm cột is_read vào bảng chat_messages
    echo "\n[3/4] Kiểm tra bảng `chat_messages` (cột is_read)...\n";
    $stmt2 = $db->query("SHOW COLUMNS FROM `chat_messages` LIKE 'is_read'");
    $colChat = $stmt2->fetch(PDO::FETCH_ASSOC);

    if (!$colChat) {
        echo "  -> Chưa có cột `is_read`. Đang thêm cột `is_read` TINYINT(1) NOT NULL DEFAULT 0...\n";
        $db->exec("ALTER TABLE `chat_messages` ADD COLUMN `is_read` TINYINT(1) NOT NULL DEFAULT 0 AFTER `message`");
        echo "  [OK] Đã thêm cột `is_read` vào `chat_messages` thành công.\n";
    } else {
        echo "  [INFO] Cột `is_read` đã tồn tại trong `chat_messages`. Kiểu dữ liệu: " . $colChat['Type'] . "\n";
    }

    // 4. Kiểm tra và thêm index idx_chat_messages_unread vào bảng chat_messages
    echo "\n[4/4] Kiểm tra index `idx_chat_messages_unread` trên `chat_messages`...\n";
    $idxStmt2 = $db->query("SHOW INDEX FROM `chat_messages` WHERE Key_name = 'idx_chat_messages_unread'");
    $idxChat = $idxStmt2->fetch(PDO::FETCH_ASSOC);

    if (!$idxChat) {
        echo "  -> Chưa có index `idx_chat_messages_unread`. Đang thêm index (sender_role, is_read, customer_id)...\n";
        $db->exec("ALTER TABLE `chat_messages` ADD INDEX `idx_chat_messages_unread` (`sender_role`, `is_read`, `customer_id`)");
        echo "  [OK] Đã thêm index `idx_chat_messages_unread` vào `chat_messages` thành công.\n";
    } else {
        echo "  [INFO] Index `idx_chat_messages_unread` đã tồn tại trên `chat_messages`.\n";
    }

    // 5. Khởi tạo dữ liệu lịch sử
    echo "\n[5/5] Cập nhật trạng thái đã đọc cho các tin nhắn lịch sử...\n";
    $up1 = $db->exec("UPDATE `ai_chat_messages` SET `is_read` = 1 WHERE `sender` != 'user'");
    $up2 = $db->exec("UPDATE `ai_chat_messages` SET `is_read` = 1 WHERE `sender` = 'user' AND `created_at` < NOW() - INTERVAL 1 HOUR");
    $up3 = $db->exec("UPDATE `chat_messages` SET `is_read` = 1 WHERE `sender_role` != 'customer'");
    $up4 = $db->exec("UPDATE `chat_messages` SET `is_read` = 1 WHERE `sender_role` = 'customer' AND `created_at` < NOW() - INTERVAL 1 HOUR");

    echo "  [OK] ai_chat_messages: cập nhật non-user: $up1 hàng, user cũ (>1h): $up2 hàng.\n";
    echo "  [OK] chat_messages: cập nhật non-customer: $up3 hàng, customer cũ (>1h): $up4 hàng.\n";

    // Thống kê tin nhắn chưa đọc hiện tại
    $unreadAi = (int)$db->query("SELECT COUNT(*) FROM `ai_chat_messages` WHERE `sender` = 'user' AND `is_read` = 0")->fetchColumn();
    $unreadChat = (int)$db->query("SELECT COUNT(*) FROM `chat_messages` WHERE `sender_role` = 'customer' AND `is_read` = 0")->fetchColumn();

    echo "\n[THỐNG KÊ UNREAD HIỆN TẠI]:\n";
    echo "  - ai_chat_messages (user unread): $unreadAi\n";
    echo "  - chat_messages (customer unread): $unreadChat\n";

    echo "\n========================================================\n";
    echo " MIGRATION HOÀN TẤT THÀNH CÔNG (100% IDEMPOTENT)\n";
    echo "========================================================\n";
    exit(0);

} catch (\Exception $e) {
    echo "\n[LỖI MIGRATION]: " . $e->getMessage() . "\n";
    exit(1);
}
