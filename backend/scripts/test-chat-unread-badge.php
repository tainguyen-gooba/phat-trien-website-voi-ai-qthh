<?php
/**
 * Automated Verification Test Suite for Chat Unread Notification Badge Infrastructure
 * Tests:
 *  1. Database columns & covering indexes on ai_chat_messages and chat_messages
 *  2. AiChatService::saveMessage() read state handling (user: is_read=0, admin/assistant: is_read=1)
 *  3. AiChatService::getTotalUnreadCount() dynamic calculation
 *  4. AiChatService::getAdminConversations() per-session unreadCount and totalUnread aggregation
 *  5. AiChatService::markSessionAsRead() state transition and unread count decrement
 *  6. Idempotency of markSessionAsRead()
 *  7. Sync with legacy chat_messages table
 *  8. Router dispatch verification for unread endpoints
 *  9. Cleanup of all test artifacts
 */

require_once __DIR__ . '/../src/config/Database.php';
require_once __DIR__ . '/../src/services/AiChatService.php';
require_once __DIR__ . '/../src/controllers/BaseController.php';
require_once __DIR__ . '/../src/controllers/ChatController.php';
require_once __DIR__ . '/../src/routes/ApiRouter.php';

use FastFood\Config\Database;
use FastFood\Services\AiChatService;

echo "====================================================================\n";
echo " FASTFOOD TEST SUITE: CHAT UNREAD BADGE BACKEND INFRASTRUCTURE\n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$testsPassed = 0;
$testsTotal = 0;

function runTest(string $description, callable $fn): void {
    global $testsPassed, $testsTotal;
    $testsTotal++;
    echo "[TEST $testsTotal] $description... ";
    try {
        $fn();
        echo "PASS ✓\n";
        $testsPassed++;
    } catch (\Throwable $e) {
        echo "FAIL ✗\n";
        echo "  [ERROR] " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

// --------------------------------------------------------------------
// 1. Database Schema Checks
// --------------------------------------------------------------------
runTest("Kiểm tra cột `is_read` trong `ai_chat_messages`", function() use ($db) {
    $stmt = $db->query("SHOW COLUMNS FROM `ai_chat_messages` LIKE 'is_read'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        throw new Exception("Cột `is_read` không tồn tại trong `ai_chat_messages`");
    }
    if (strpos(strtolower($col['Type']), 'tinyint(1)') === false) {
        throw new Exception("Kiểu dữ liệu của `is_read` phải là tinyint(1), thực tế: " . $col['Type']);
    }
    if ($col['Default'] !== '0') {
        throw new Exception("Giá trị mặc định của `is_read` phải là '0', thực tế: " . var_export($col['Default'], true));
    }
});

runTest("Kiểm tra covering index `idx_chat_msg_unread` trên `ai_chat_messages`", function() use ($db) {
    $stmt = $db->query("SHOW INDEX FROM `ai_chat_messages` WHERE Key_name = 'idx_chat_msg_unread'");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($indexes)) {
        throw new Exception("Index `idx_chat_msg_unread` không tồn tại");
    }
    $columns = array_column($indexes, 'Column_name');
    if ($columns !== ['sender', 'is_read', 'session_id']) {
        throw new Exception("Index `idx_chat_msg_unread` phải gồm [sender, is_read, session_id], thực tế: " . json_encode($columns));
    }
});

runTest("Kiểm tra cột `is_read` trong `chat_messages`", function() use ($db) {
    $stmt = $db->query("SHOW COLUMNS FROM `chat_messages` LIKE 'is_read'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        throw new Exception("Cột `is_read` không tồn tại trong `chat_messages`");
    }
    if (strpos(strtolower($col['Type']), 'tinyint(1)') === false) {
        throw new Exception("Kiểu dữ liệu của `is_read` phải là tinyint(1), thực tế: " . $col['Type']);
    }
});

runTest("Kiểm tra index `idx_chat_messages_unread` trên `chat_messages`", function() use ($db) {
    $stmt = $db->query("SHOW INDEX FROM `chat_messages` WHERE Key_name = 'idx_chat_messages_unread'");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($indexes)) {
        throw new Exception("Index `idx_chat_messages_unread` không tồn tại");
    }
    $columns = array_column($indexes, 'Column_name');
    if ($columns !== ['sender_role', 'is_read', 'customer_id']) {
        throw new Exception("Index `idx_chat_messages_unread` phải gồm [sender_role, is_read, customer_id], thực tế: " . json_encode($columns));
    }
});

// --------------------------------------------------------------------
// 2. Service Layer State Transition Tests
// --------------------------------------------------------------------
$baseUnread = AiChatService::getTotalUnreadCount();
$testSessionId = 'test_unread_sess_' . time() . '_' . bin2hex(random_bytes(4));
$userMsgId1 = 0;
$userMsgId2 = 0;
$adminMsgId = 0;
$aiMsgId = 0;

runTest("Lưu tin nhắn của user -> is_read = 0 và unread count tăng", function() use ($db, $testSessionId, $baseUnread, &$userMsgId1) {
    $userMsgId1 = AiChatService::saveMessage($testSessionId, null, 'user', 'Tin nhắn test số 1 từ khách hàng');
    if ($userMsgId1 <= 0) {
        throw new Exception("Không thể lưu tin nhắn user");
    }

    $stmt = $db->prepare("SELECT is_read FROM ai_chat_messages WHERE id = ?");
    $stmt->execute([$userMsgId1]);
    $isRead = (int)$stmt->fetchColumn();
    if ($isRead !== 0) {
        throw new Exception("Tin nhắn user mới phải có is_read = 0, thực tế: $isRead");
    }

    $newUnread = AiChatService::getTotalUnreadCount();
    if ($newUnread !== $baseUnread + 1) {
        throw new Exception("Tổng số tin chưa đọc phải tăng 1 (kỳ vọng: " . ($baseUnread + 1) . ", thực tế: $newUnread)");
    }
});

runTest("Lưu tin nhắn thứ hai của user trong cùng phiên -> unread count tăng tiếp", function() use ($db, $testSessionId, $baseUnread, &$userMsgId2) {
    $userMsgId2 = AiChatService::saveMessage($testSessionId, null, 'user', 'Tin nhắn test số 2 từ khách hàng');
    if ($userMsgId2 <= 0) {
        throw new Exception("Không thể lưu tin nhắn user 2");
    }

    $stmt = $db->prepare("SELECT is_read FROM ai_chat_messages WHERE id = ?");
    $stmt->execute([$userMsgId2]);
    $isRead = (int)$stmt->fetchColumn();
    if ($isRead !== 0) {
        throw new Exception("Tin nhắn user 2 phải có is_read = 0");
    }

    $newUnread = AiChatService::getTotalUnreadCount();
    if ($newUnread !== $baseUnread + 2) {
        throw new Exception("Tổng số tin chưa đọc phải tăng 2 (kỳ vọng: " . ($baseUnread + 2) . ", thực tế: $newUnread)");
    }
});

runTest("Lưu tin nhắn admin -> is_read = 1 và unread count không tăng", function() use ($db, $testSessionId, $baseUnread, &$adminMsgId) {
    $adminMsgId = AiChatService::saveMessage($testSessionId, null, 'admin', 'Chào bạn! Admin đã nhận tin nhắn');
    if ($adminMsgId <= 0) {
        throw new Exception("Không thể lưu tin nhắn admin");
    }

    $stmt = $db->prepare("SELECT is_read FROM ai_chat_messages WHERE id = ?");
    $stmt->execute([$adminMsgId]);
    $isRead = (int)$stmt->fetchColumn();
    if ($isRead !== 1) {
        throw new Exception("Tin nhắn admin phải có is_read = 1, thực tế: $isRead");
    }

    $currentUnread = AiChatService::getTotalUnreadCount();
    if ($currentUnread !== $baseUnread + 2) {
        throw new Exception("Tin nhắn admin không được làm tăng unread count");
    }
});

runTest("Lưu tin nhắn AI assistant -> is_read = 1 và unread count không tăng", function() use ($db, $testSessionId, $baseUnread, &$aiMsgId) {
    $aiMsgId = AiChatService::saveMessage($testSessionId, null, 'assistant', 'Bot tự động tư vấn');
    if ($aiMsgId <= 0) {
        throw new Exception("Không thể lưu tin nhắn assistant");
    }

    $stmt = $db->prepare("SELECT is_read FROM ai_chat_messages WHERE id = ?");
    $stmt->execute([$aiMsgId]);
    $isRead = (int)$stmt->fetchColumn();
    if ($isRead !== 1) {
        throw new Exception("Tin nhắn assistant phải có is_read = 1, thực tế: $isRead");
    }

    $currentUnread = AiChatService::getTotalUnreadCount();
    if ($currentUnread !== $baseUnread + 2) {
        throw new Exception("Tin nhắn assistant không được làm tăng unread count");
    }
});

runTest("Kiểm tra `AiChatService::getAdminConversations()` trả về `unreadCount` cho phiên", function() use ($testSessionId) {
    $res = AiChatService::getAdminConversations();
    $convs = $res['conversations'] ?? $res;
    $matching = null;
    foreach ($convs as $c) {
        if ($c['sessionId'] === $testSessionId) {
            $matching = $c;
            break;
        }
    }
    if (!$matching) {
        throw new Exception("Không tìm thấy phiên test trong danh sách hội thoại");
    }
    if ($matching['unreadCount'] !== 2) {
        throw new Exception("unreadCount của phiên phải là 2, thực tế: " . var_export($matching['unreadCount'], true));
    }
    if (!isset($res['totalUnread'])) {
        throw new Exception("getAdminConversations() phải trả về key 'totalUnread'");
    }
});

runTest("Thực hiện `AiChatService::markSessionAsRead()` -> is_read = 1 và unread count giảm", function() use ($db, $testSessionId, $baseUnread) {
    $affected = AiChatService::markSessionAsRead($testSessionId);
    if ($affected !== 2) {
        throw new Exception("markSessionAsRead phải cập nhật đúng 2 tin nhắn của user, thực tế: $affected");
    }

    // Kiểm tra trong DB các tin nhắn của user đã thành is_read = 1
    $stmt = $db->prepare("SELECT COUNT(*) FROM ai_chat_messages WHERE session_id = ? AND sender = 'user' AND is_read = 0");
    $stmt->execute([$testSessionId]);
    $remainingUnread = (int)$stmt->fetchColumn();
    if ($remainingUnread !== 0) {
        throw new Exception("Tất cả tin nhắn user trong phiên phải có is_read = 1, còn lại: $remainingUnread");
    }

    $currentUnread = AiChatService::getTotalUnreadCount();
    if ($currentUnread !== $baseUnread) {
        throw new Exception("Unread count phải giảm về baseline $baseUnread, thực tế: $currentUnread");
    }
});

runTest("Kiểm tra tính Idempotent của `markSessionAsRead()` khi gọi lại lần 2", function() use ($testSessionId, $baseUnread) {
    $affected = AiChatService::markSessionAsRead($testSessionId);
    if ($affected !== 0) {
        throw new Exception("Gọi lại lần 2 phải trả về 0 hàng bị ảnh hưởng, thực tế: $affected");
    }

    $currentUnread = AiChatService::getTotalUnreadCount();
    if ($currentUnread !== $baseUnread) {
        throw new Exception("Unread count không được thay đổi");
    }
});

runTest("Kiểm tra `AiChatService::getAdminConversations()` sau khi đọc -> unreadCount = 0", function() use ($testSessionId) {
    $res = AiChatService::getAdminConversations();
    $convs = $res['conversations'] ?? $res;
    $matching = null;
    foreach ($convs as $c) {
        if ($c['sessionId'] === $testSessionId) {
            $matching = $c;
            break;
        }
    }
    if (!$matching) {
        throw new Exception("Không tìm thấy phiên test trong danh sách hội thoại");
    }
    if ($matching['unreadCount'] !== 0) {
        throw new Exception("unreadCount của phiên sau khi đọc phải là 0, thực tế: " . var_export($matching['unreadCount'], true));
    }
});

// --------------------------------------------------------------------
// 3. Router & Endpoint Pattern Verification
// --------------------------------------------------------------------
runTest("Kiểm tra đăng ký route trong `ApiRouter.php`", function() {
    $routerContent = file_get_contents(__DIR__ . '/../src/routes/ApiRouter.php');
    if (strpos($routerContent, 'markSessionAsRead') === false) {
        throw new Exception("ApiRouter chưa đăng ký ChatController::markSessionAsRead");
    }
    if (strpos($routerContent, 'getUnreadCount') === false) {
        throw new Exception("ApiRouter chưa đăng ký ChatController::getUnreadCount");
    }
    if (strpos($routerContent, 'admin/sessions/([^/]+)/read') === false) {
        throw new Exception("ApiRouter thiếu pattern cho route read session: admin/sessions/([^/]+)/read");
    }
    if (strpos($routerContent, 'admin/unread-count') === false) {
        throw new Exception("ApiRouter thiếu pattern cho route unread-count");
    }
});

// --------------------------------------------------------------------
// 4. Realtime API Polling Engine Verification
// --------------------------------------------------------------------
runTest("Kiểm tra logic `unreadChatCount` trong `api/realtime.php`", function() {
    $realtimeContent = file_get_contents(__DIR__ . '/../../api/realtime.php');
    if (strpos($realtimeContent, 'unreadChatCount') === false) {
        throw new Exception("api/realtime.php chưa chứa unreadChatCount");
    }
    if (strpos($realtimeContent, "WHERE sender = 'user' AND is_read = 0") === false) {
        throw new Exception("api/realtime.php chưa query unread từ ai_chat_messages");
    }
    if (strpos($realtimeContent, "\$pollData['unreadChatCount'] = \$unreadChatCount") === false &&
        strpos($realtimeContent, "'unreadChatCount'") === false) {
        throw new Exception("api/realtime.php chưa trả về unreadChatCount trong response");
    }
});

// --------------------------------------------------------------------
// 5. Cleanup Test Artifacts
// --------------------------------------------------------------------
runTest("Dọn dẹp bản ghi mock test và khôi phục baseline", function() use ($db, $testSessionId, $baseUnread) {
    $delMsg = $db->prepare("DELETE FROM ai_chat_messages WHERE session_id = ?");
    $delMsg->execute([$testSessionId]);

    $delSess = $db->prepare("DELETE FROM ai_chat_sessions WHERE session_id = ?");
    $delSess->execute([$testSessionId]);

    $finalUnread = AiChatService::getTotalUnreadCount();
    if ($finalUnread !== $baseUnread) {
        throw new Exception("Unread count sau dọn dẹp ($finalUnread) không khớp baseline ($baseUnread)");
    }
});

echo "\n====================================================================\n";
echo " KẾT QUẢ KIỂM THỬ: $testsPassed / $testsTotal TESTS THÀNH CÔNG\n";
echo "====================================================================\n";

if ($testsPassed === $testsTotal) {
    echo ">>> TẤT CẢ CÁC BÀI TEST ĐẠT 100% TIÊU CHUẨN M1 (BACKEND & DB) <<<\n\n";
    exit(0);
} else {
    echo ">>> CÓ $testsTotal - $testsPassed BÀI TEST THẤT BẠI! <<<\n\n";
    exit(1);
}
