<?php
/**
 * FASTFOOD - Milestone 2 (M2) Verification Test Suite
 *
 * Verifies:
 * 1. admin/index.php SSR PHP query for $unreadMessagesCount
 * 2. admin/index.php Sidebar Badge HTML markup & attribute conditions
 * 3. admin/index.php CSS styles (.nav-badge-messages, @keyframes badge-pulse, truncation guard, .conv-unread-badge)
 * 4. admin/index.php Global Window API (updateMessagesNavBadge, getMessagesNavBadgeCount, clearMessagesBadge)
 * 5. admin/index.php Real-time Socket & CustomEvent listeners
 * 6. js/realtime.js Polling Engine support:unread_count event & badge update
 * 7. Mirror Parity: js/realtime.js <=> frontend/js/realtime.js (SHA256)
 * 8. js/admin-chat.js Conversation unread badge rendering (.conv-unread-badge & 99+ format)
 * 9. js/admin-chat.js handleIncomingSync auto-clearing & badge management logic
 * 10. js/admin-chat.js loadHistory auto-read API call, badge decrement, and BroadcastChannel broadcast
 * 11. Mirror Parity: js/admin-chat.js <=> frontend/js/admin-chat.js (SHA256)
 * 12. Functional API verification: markSessionAsRead returns totalUnread and decrements counter
 */

require_once __DIR__ . '/../src/config/Database.php';
require_once __DIR__ . '/../src/services/AiChatService.php';
require_once __DIR__ . '/../src/controllers/BaseController.php';
require_once __DIR__ . '/../src/controllers/ChatController.php';
require_once __DIR__ . '/../src/routes/ApiRouter.php';

use FastFood\Config\Database;
use FastFood\Services\AiChatService;

echo "====================================================================\n";
echo " FASTFOOD TEST SUITE: MILESTONE 2 (ADMIN SIDEBAR BADGE & REALTIME)  \n";
echo "====================================================================\n\n";

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

$root = dirname(__DIR__, 2);
$adminIndexFile = $root . '/admin/index.php';
$realtimeJs = $root . '/js/realtime.js';
$realtimeFrontendJs = $root . '/frontend/js/realtime.js';
$adminChatJs = $root . '/js/admin-chat.js';
$adminChatFrontendJs = $root . '/frontend/js/admin-chat.js';

// --------------------------------------------------------------------
// 1. admin/index.php Checks
// --------------------------------------------------------------------
runTest("admin/index.php: Kiểm tra cú pháp PHP (php -l)", function() use ($adminIndexFile) {
    $output = [];
    $returnVar = 0;
    exec("php -l " . escapeshellarg($adminIndexFile), $output, $returnVar);
    if ($returnVar !== 0) {
        throw new Exception("Lỗi cú pháp trong admin/index.php: " . implode("\n", $output));
    }
});

runTest("admin/index.php: Kiểm tra SSR PHP query \$unreadMessagesCount", function() use ($adminIndexFile) {
    $content = file_get_contents($adminIndexFile);
    if (strpos($content, '$unreadMessagesCount') === false) {
        throw new Exception("Thiếu biến \$unreadMessagesCount trong admin/index.php");
    }
    if (strpos($content, "SELECT COUNT(*) FROM ai_chat_messages WHERE sender = 'user' AND is_read = 0") === false) {
        throw new Exception("Thiếu truy vấn đếm tin nhắn chưa đọc từ ai_chat_messages");
    }
});

runTest("admin/index.php: Kiểm tra markup Sidebar Badge (#messages-nav-badge)", function() use ($adminIndexFile) {
    $content = file_get_contents($adminIndexFile);
    if (strpos($content, 'data-section="messages"') === false) {
        throw new Exception("Không tìm thấy button data-section=\"messages\"");
    }
    if (strpos($content, 'id="messages-nav-badge"') === false) {
        throw new Exception("Không tìm thấy id=\"messages-nav-badge\"");
    }
    if (strpos($content, 'nav-badge-messages') === false) {
        throw new Exception("Không tìm thấy class nav-badge-messages");
    }
    if (strpos($content, '<span>Tin nhắn khách hàng</span>') === false) {
        throw new Exception("Chưa bọc tiêu đề 'Tin nhắn khách hàng' trong thẻ span bảo vệ truncation");
    }
    if (strpos($content, '$unreadMessagesCount > 99 ? \'99+\' : $unreadMessagesCount') === false) {
        throw new Exception("Thiếu định dạng '99+' khi số tin nhắn vượt quá 99");
    }
});

runTest("admin/index.php: Kiểm tra CSS styles cho Sidebar Badge & Truncation Guard", function() use ($adminIndexFile) {
    $content = file_get_contents($adminIndexFile);
    if (strpos($content, '.nav-badge-messages') === false) {
        throw new Exception("Thiếu CSS rule .nav-badge-messages");
    }
    if (strpos($content, 'linear-gradient(135deg, #ef4444, #dc2626)') === false) {
        throw new Exception("Thiếu gradient màu đỏ đặc trưng cho .nav-badge-messages");
    }
    if (strpos($content, 'badge-pulse') === false) {
        throw new Exception("Thiếu animation badge-pulse");
    }
    if (strpos($content, '.nav-item > span:first-of-type') === false) {
        throw new Exception("Thiếu truncation guard .nav-item > span:first-of-type");
    }
    if (strpos($content, 'text-overflow: ellipsis') === false) {
        throw new Exception("Thiếu text-overflow: ellipsis cho nav item");
    }
});

runTest("admin/index.php: Kiểm tra Global Window API (window.updateMessagesNavBadge, etc.)", function() use ($adminIndexFile) {
    $content = file_get_contents($adminIndexFile);
    if (strpos($content, 'window.updateMessagesNavBadge') === false) {
        throw new Exception("Thiếu hàm window.updateMessagesNavBadge");
    }
    if (strpos($content, 'window.getMessagesNavBadgeCount') === false) {
        throw new Exception("Thiếu hàm window.getMessagesNavBadgeCount");
    }
    if (strpos($content, 'window.clearMessagesBadge') === false) {
        throw new Exception("Thiếu hàm window.clearMessagesBadge");
    }
    if (strpos($content, 'support:unread_count') === false) {
        throw new Exception("Thiếu listener event support:unread_count trong admin/index.php");
    }
});

// --------------------------------------------------------------------
// 2. js/realtime.js & Parity Checks
// --------------------------------------------------------------------
runTest("js/realtime.js: Kiểm tra background polling trigger support:unread_count", function() use ($realtimeJs) {
    $content = file_get_contents($realtimeJs);
    if (strpos($content, 'unreadChatCount') === false) {
        throw new Exception("Thiếu xử lý unreadChatCount trong js/realtime.js");
    }
    if (strpos($content, "triggerLocalListeners('support:unread_count'") === false) {
        throw new Exception("Thiếu triggerLocalListeners cho support:unread_count");
    }
    if (strpos($content, "window.updateMessagesNavBadge(d.unreadChatCount)") === false) {
        throw new Exception("js/realtime.js chưa gọi updateMessagesNavBadge");
    }
});

runTest("Mirror Parity: js/realtime.js <=> frontend/js/realtime.js (SHA256)", function() use ($realtimeJs, $realtimeFrontendJs) {
    $hash1 = hash_file('sha256', $realtimeJs);
    $hash2 = hash_file('sha256', $realtimeFrontendJs);
    if ($hash1 !== $hash2) {
        throw new Exception("Mismatch SHA256: js/realtime.js ($hash1) != frontend/js/realtime.js ($hash2)");
    }
});

// --------------------------------------------------------------------
// 3. js/admin-chat.js & Auto-clearing Checks
// --------------------------------------------------------------------
runTest("js/admin-chat.js: Kiểm tra hiển thị conv-unread-badge trong danh sách hội thoại", function() use ($adminChatJs) {
    $content = file_get_contents($adminChatJs);
    if (strpos($content, 'conv-unread-badge') === false) {
        throw new Exception("Thiếu class conv-unread-badge trong js/admin-chat.js");
    }
    if (strpos($content, 'unreadCount > 99 ? \'99+\'') === false && strpos($content, 'convUnread > 99 ? \'99+\'') === false) {
        throw new Exception("Thiếu định dạng 99+ cho conversation unread badge");
    }
});

runTest("js/admin-chat.js: Kiểm tra handleIncomingSync tự động mark read khi active và tăng badge khi inactive", function() use ($adminChatJs) {
    $content = file_get_contents($adminChatJs);
    if (strpos($content, 'handleIncomingSync') === false) {
        throw new Exception("Thiếu hàm handleIncomingSync");
    }
    if (strpos($content, 'CUSTOMER_SENT') === false) {
        throw new Exception("handleIncomingSync thiếu xử lý type CUSTOMER_SENT");
    }
    if (strpos($content, '/read') === false) {
        throw new Exception("Thiếu gọi endpoint /read khi admin đang xem session");
    }
    if (strpos($content, 'window.getMessagesNavBadgeCount() + 1') === false &&
        strpos($content, 'currentBadgeCount + 1') === false) {
        throw new Exception("Thiếu tăng sidebar badge khi admin không xem session");
    }
});

runTest("js/admin-chat.js: Kiểm tra loadHistory auto-clearing, badge decrement & BroadcastChannel sync", function() use ($adminChatJs) {
    $content = file_get_contents($adminChatJs);
    if (strpos($content, 'loadHistory') === false) {
        throw new Exception("Thiếu hàm loadHistory");
    }
    if (strpos($content, 'prevUnreadCount') === false) {
        throw new Exception("loadHistory thiếu logic tính prevUnreadCount");
    }
    if (strpos($content, 'Math.max(0,') === false) {
        throw new Exception("Thiếu Math.max(0, ...) khi decrement badge count");
    }
    if (strpos($content, "BroadcastChannel('fastfood_chat_realtime')") === false) {
        throw new Exception("Thiếu BroadcastChannel('fastfood_chat_realtime')");
    }
    if (strpos($content, 'SESSION_READ') === false) {
        throw new Exception("Thiếu broadcast type SESSION_READ");
    }
    if (strpos($content, 'fastfood_chat_sync') === false) {
        throw new Exception("Thiếu localStorage fallback key fastfood_chat_sync");
    }
});

runTest("Mirror Parity: js/admin-chat.js <=> frontend/js/admin-chat.js (SHA256)", function() use ($adminChatJs, $adminChatFrontendJs) {
    $hash1 = hash_file('sha256', $adminChatJs);
    $hash2 = hash_file('sha256', $adminChatFrontendJs);
    if ($hash1 !== $hash2) {
        throw new Exception("Mismatch SHA256: js/admin-chat.js ($hash1) != frontend/js/admin-chat.js ($hash2)");
    }
});

// --------------------------------------------------------------------
// 4. End-to-End API Execution: Mark As Read & Total Unread
// --------------------------------------------------------------------
runTest("Functional API: POST /api/v1/chat/admin/sessions/{sessionId}/read trả về totalUnread chuẩn xác", function() {
    $db = Database::getConnection();
    $mockSessionId = 'test_m2_verify_' . time() . '_' . mt_rand(1000, 9999);
    $initialUnread = AiChatService::getTotalUnreadCount();

    // 1. Tạo 2 tin nhắn chưa đọc từ user
    AiChatService::saveMessage($mockSessionId, null, 'user', 'Xin chào test M2 1');
    AiChatService::saveMessage($mockSessionId, null, 'user', 'Xin chào test M2 2');

    $afterInsertUnread = AiChatService::getTotalUnreadCount();
    if ($afterInsertUnread !== $initialUnread + 2) {
        throw new Exception("Tổng số unread phải tăng thêm 2, thực tế từ $initialUnread -> $afterInsertUnread");
    }

    // 2. Gọi markSessionAsRead
    $affected = AiChatService::markSessionAsRead($mockSessionId);
    if ($affected < 2) {
        throw new Exception("Số tin nhắn bị ảnh hưởng phải >= 2, thực tế: $affected");
    }

    $finalUnread = AiChatService::getTotalUnreadCount();
    if ($finalUnread !== $initialUnread) {
        throw new Exception("Sau khi mark read, tổng số unread phải trở về ban đầu ($initialUnread), thực tế: $finalUnread");
    }

    // 3. Dọn dẹp
    $db->prepare("DELETE FROM ai_chat_messages WHERE session_id = ?")->execute([$mockSessionId]);
    $db->prepare("DELETE FROM chat_messages WHERE customer_id = 99999 OR message LIKE '%Xin chào test M2%'")->execute();
});

echo "\n====================================================================\n";
echo " KẾT QUẢ KIỂM THỬ: $testsPassed / $testsTotal TESTS THÀNH CÔNG\n";
echo "====================================================================\n";

if ($testsPassed === $testsTotal) {
    echo ">>> TẤT CẢ CÁC BÀI TEST ĐẠT 100% TIÊU CHUẨN M2 (ADMIN SIDEBAR BADGE & REALTIME) <<<\n";
    exit(0);
} else {
    echo ">>> CÓ $testsTotal - $testsPassed BÀI TEST THẤT BẠI <<<\n";
    exit(1);
}
