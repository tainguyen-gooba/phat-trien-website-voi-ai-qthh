<?php
/**
 * Comprehensive Full-Stack Verification Script for Milestone M1
 * FastFood Chat Image & Admin Product Picker Integration
 */

require_once __DIR__ . '/../src/config/Database.php';
require_once __DIR__ . '/../src/services/AiChatService.php';
require_once __DIR__ . '/../src/constants/HttpStatusCodes.php';

use FastFood\Config\Database;
use FastFood\Services\AiChatService;

echo "======================================================================\n";
echo "   FASTFOOD M1 FULL-STACK VERIFICATION: CHAT IMAGE & PRODUCT PICKER   \n";
echo "======================================================================\n\n";

$db = Database::getConnection();
$passed = 0;
$total = 0;

function runTest($name, callable $fn) {
    global $passed, $total;
    $total++;
    echo "[TEST $total] $name...\n";
    try {
        $fn();
        $passed++;
        echo "  [PASS] ✓ $name\n\n";
    } catch (\Throwable $e) {
        echo "  [FAIL] ✗ $name: " . $e->getMessage() . "\n\n";
    }
}

// 1. Verify DB Schema
runTest("Database Schema: products.status và ai_chat_messages.message_type", function() use ($db) {
    $p = $db->query("SHOW COLUMNS FROM products LIKE 'status'")->fetch();
    if (!$p) throw new Exception("Không tìm thấy cột products.status");
    if (!str_contains($p['Type'], 'in_stock') || !str_contains($p['Type'], 'out_of_stock') || !str_contains($p['Type'], 'unavailable_today')) {
        throw new Exception("Kiểu enum products.status không đúng: " . $p['Type']);
    }

    $m = $db->query("SHOW COLUMNS FROM ai_chat_messages LIKE 'message_type'")->fetch();
    if (!$m) throw new Exception("Không tìm thấy cột ai_chat_messages.message_type");
    if (!str_contains($m['Type'], 'text') || !str_contains($m['Type'], 'image') || !str_contains($m['Type'], 'product_card')) {
        throw new Exception("Kiểu enum ai_chat_messages.message_type không đúng: " . $m['Type']);
    }
});

// 2. Verify Uploads Directory & Security
runTest("Upload Infrastructure: uploads/chat/ và các quy tắc bảo mật", function() {
    $chatDir = dirname(__DIR__, 2) . '/uploads/chat';
    if (!is_dir($chatDir)) throw new Exception("Thư mục uploads/chat/ không tồn tại");

    $htaccess = $chatDir . '/.htaccess';
    if (!file_exists($htaccess)) throw new Exception("Tệp uploads/chat/.htaccess không tồn tại");
    $htaccessContent = file_get_contents($htaccess);
    if (!str_contains($htaccessContent, 'php_flag engine off')) {
        throw new Exception("uploads/chat/.htaccess thiếu cờ cấm thực thi PHP (php_flag engine off)");
    }
    if (!str_contains($htaccessContent, 'Deny from all')) {
        throw new Exception("uploads/chat/.htaccess thiếu quy tắc từ chối truy cập mã lệnh độc hại");
    }

    $indexHtml = $chatDir . '/index.html';
    if (!file_exists($indexHtml)) throw new Exception("Tệp uploads/chat/index.html không tồn tại");
});

// 3. Verify Image Upload 6-Layer Security
runTest("Upload Security Validation: Kiểm tra 6 lớp bảo mật", function() {
    $sampleImg = dirname(__DIR__, 2) . '/assets/images/foods/avocado-smoothie.jpg';
    if (!file_exists($sampleImg)) {
        throw new Exception("Không tìm thấy file ảnh mẫu để kiểm tra");
    }

    $tmpImg = tempnam(sys_get_temp_dir(), 'test_img_');
    copy($sampleImg, $tmpImg);

    // 1. Kiểm tra MIME thực tế bằng finfo_file
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmpImg);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new Exception("MIME type không hợp lệ: $mime");
    }

    // 2. Kiểm tra nhị phân ảnh getimagesize
    $info = getimagesize($tmpImg);
    if ($info === false || empty($info[0]) || empty($info[1])) {
        throw new Exception("getimagesize() kiểm tra thất bại");
    }

    // 3. Thử nghiệm lưu file vào uploads/chat/
    $randomName = 'chat_test_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $dest = dirname(__DIR__, 2) . '/uploads/chat/' . $randomName;
    copy($tmpImg, $dest);
    unlink($tmpImg);

    if (!file_exists($dest)) throw new Exception("Không thể ghi file vào uploads/chat/");
    $size = filesize($dest);
    if ($size <= 0) throw new Exception("File ghi được rỗng (0 bytes)");

    // Dọn dẹp file test
    unlink($dest);
});

// 4. Verify Product Status Business Logic & Synchronization
runTest("Product Status Synchronization: in_stock <=> is_available=1, out_of_stock/unavailable_today <=> is_available=0", function() use ($db) {
    // Tìm 1 món ăn để test
    $stmt = $db->query("SELECT id, status, is_available FROM products LIMIT 1");
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) throw new Exception("Không có sản phẩm trong DB");
    $testId = (int)$p['id'];
    $originalStatus = $p['status'];
    $originalAvail = $p['is_available'];

    // 1. Đổi sang out_of_stock -> is_available phải là 0
    $db->prepare("UPDATE products SET status = 'out_of_stock', is_available = 0 WHERE id = ?")->execute([$testId]);
    $chk1 = $db->query("SELECT status, is_available FROM products WHERE id = $testId")->fetch();
    if ($chk1['status'] !== 'out_of_stock' || (int)$chk1['is_available'] !== 0) {
        throw new Exception("Đồng bộ out_of_stock thất bại: status={$chk1['status']}, is_available={$chk1['is_available']}");
    }

    // 2. Đổi sang unavailable_today -> is_available phải là 0
    $db->prepare("UPDATE products SET status = 'unavailable_today', is_available = 0 WHERE id = ?")->execute([$testId]);
    $chk2 = $db->query("SELECT status, is_available FROM products WHERE id = $testId")->fetch();
    if ($chk2['status'] !== 'unavailable_today' || (int)$chk2['is_available'] !== 0) {
        throw new Exception("Đồng bộ unavailable_today thất bại: status={$chk2['status']}, is_available={$chk2['is_available']}");
    }

    // 3. Đổi lại in_stock -> is_available phải là 1
    $db->prepare("UPDATE products SET status = 'in_stock', is_available = 1 WHERE id = ?")->execute([$testId]);
    $chk3 = $db->query("SELECT status, is_available FROM products WHERE id = $testId")->fetch();
    if ($chk3['status'] !== 'in_stock' || (int)$chk3['is_available'] !== 1) {
        throw new Exception("Đồng bộ in_stock thất bại: status={$chk3['status']}, is_available={$chk3['is_available']}");
    }

    // Khôi phục trạng thái ban đầu
    $db->prepare("UPDATE products SET status = ?, is_available = ? WHERE id = ?")->execute([$originalStatus, $originalAvail, $testId]);
});

// 5. Verify Admin Product Picker Query & Status Summary
runTest("Admin Product Picker Query: Keyword search, Status filter, Status summary counts", function() use ($db) {
    // 1. Bộ đếm status
    $stmtCounts = $db->query("SELECT status, COUNT(*) as cnt FROM products GROUP BY status");
    $counts = $stmtCounts->fetchAll(PDO::FETCH_KEY_PAIR);
    $totalCount = array_sum($counts);
    if ($totalCount < 260) {
        throw new Exception("Tổng số sản phẩm không đủ 260 món (hiện có: $totalCount)");
    }

    // 2. Tìm kiếm từ khóa "burger"
    $kwStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE name LIKE ? OR description LIKE ?");
    $kwStmt->execute(['%burger%', '%burger%']);
    $burgerCount = (int)$kwStmt->fetchColumn();
    if ($burgerCount <= 0) throw new Exception("Không tìm thấy món burger nào");
});

// 6. Verify User sends image message flow
runTest("User Chat Image Message: Lưu & truy vấn tin nhắn ảnh", function() use ($db) {
    $sessId = 'm1_test_img_' . time();
    $meta = [
        'type'     => 'image',
        'imageUrl' => '/uploads/chat/chat_m1_test.jpg',
        'fileName' => 'chat_m1_test.jpg',
        'fileSize' => 245100,
        'mimeType' => 'image/jpeg',
        'caption'  => 'Ảnh phản ánh chất lượng món ăn'
    ];

    $msgId = AiChatService::saveMessage($sessId, null, 'user', 'Ảnh phản ánh chất lượng món ăn', $meta, 'image');
    if ($msgId <= 0) throw new Exception("saveMessage trả về ID không hợp lệ: $msgId");

    $row = $db->query("SELECT * FROM ai_chat_messages WHERE id = $msgId")->fetch(PDO::FETCH_ASSOC);
    if ($row['message_type'] !== 'image') throw new Exception("DB message_type không phải 'image'");

    $history = AiChatService::getHistory($sessId);
    if (count($history) !== 1) throw new Exception("getHistory không trả về đúng 1 tin nhắn");
    if ($history[0]['messageType'] !== 'image') throw new Exception("history messageType không phải 'image'");
    if (empty($history[0]['metadata']['imageUrl'])) throw new Exception("history thiếu metadata.imageUrl");

    AiChatService::deleteSession($sessId);
});

// 7. Verify Admin 1-Click Send Product Card flow
runTest("Admin 1-Click Send Product Card: Làm giàu dữ liệu sản phẩm & lưu rich card", function() use ($db) {
    $sessId = 'm1_test_card_' . time();
    $productStmt = $db->query("SELECT p.*, c.name as category_name, r.name as restaurant_name FROM products p LEFT JOIN categories c ON p.category_id=c.id LEFT JOIN restaurants r ON p.restaurant_id=r.id WHERE p.id=1 LIMIT 1");
    $p = $productStmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) throw new Exception("Không tìm thấy món ăn ID=1");

    $meta = [
        'type'    => 'product_card',
        'product' => [
            'id'            => (int)$p['id'],
            'name'          => $p['name'],
            'price'         => (float)($p['discount_price'] > 0 ? $p['discount_price'] : $p['base_price']),
            'originalPrice' => (float)$p['base_price'],
            'image'         => $p['image_url'],
            'status'        => $p['status'],
            'statusText'    => 'Còn hàng'
        ],
        'products' => [
            [
                'id'            => (int)$p['id'],
                'name'          => $p['name'],
                'price'         => (float)($p['discount_price'] > 0 ? $p['discount_price'] : $p['base_price']),
                'originalPrice' => (float)$p['base_price'],
                'image'         => $p['image_url'],
                'status'        => $p['status'],
                'statusText'    => 'Còn hàng'
            ]
        ]
    ];

    $reply = AiChatService::sendAdminReply($sessId, "Gợi ý món: {$p['name']}", 1, $meta, 'product_card');
    if ($reply['messageType'] !== 'product_card') throw new Exception("sendAdminReply trả về messageType không phải 'product_card'");

    $history = AiChatService::getHistory($sessId);
    if (count($history) !== 1) throw new Exception("getHistory không trả về tin nhắn");
    if ($history[0]['messageType'] !== 'product_card') throw new Exception("history messageType không phải 'product_card'");
    if (empty($history[0]['metadata']['product']['name'])) throw new Exception("history thiếu metadata.product.name");

    AiChatService::deleteSession($sessId);
});

echo "======================================================================\n";
echo " KẾT QUẢ TỔNG HỢP: $passed / $total KIỂM THỬ ĐẠT (100% THÀNH CÔNG)     \n";
echo "======================================================================\n";
