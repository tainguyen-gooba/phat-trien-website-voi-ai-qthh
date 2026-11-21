<?php
/**
 * Test script for Chat Image & Admin Product Picker Backend APIs
 */

require_once __DIR__ . '/../src/config/Database.php';
require_once __DIR__ . '/../src/services/AiChatService.php';
require_once __DIR__ . '/../src/constants/HttpStatusCodes.php';

use FastFood\Config\Database;
use FastFood\Services\AiChatService;

echo "====================================================\n";
echo " TEST BACKEND CHAT IMAGE & PRODUCT PICKER INTEGRATION\n";
echo "====================================================\n\n";

$db = Database::getConnection();

// Test 1: Verify DB columns
echo "[TEST 1] Kiểm tra cột Database...\n";
$pCols = $db->query("SHOW COLUMNS FROM products LIKE 'status'")->fetch();
assert($pCols !== false, "Cột products.status phải tồn tại");
echo "  ✓ products.status: " . $pCols['Type'] . "\n";

$mCols = $db->query("SHOW COLUMNS FROM ai_chat_messages LIKE 'message_type'")->fetch();
assert($mCols !== false, "Cột ai_chat_messages.message_type phải tồn tại");
echo "  ✓ ai_chat_messages.message_type: " . $mCols['Type'] . "\n";

// Test 2: Save and retrieve image message
echo "\n[TEST 2] Lưu và lấy tin nhắn loại 'image'...\n";
$testSessionId = 'test_sess_' . time() . '_' . bin2hex(random_bytes(4));
$imgMetadata = [
    'type'     => 'image',
    'imageUrl' => '/uploads/chat/chat_test_123.jpg',
    'fileName' => 'chat_test_123.jpg',
    'fileSize' => 102400,
    'mimeType' => 'image/jpeg',
    'caption'  => 'Món này còn không ạ?'
];
$msgId1 = AiChatService::saveMessage($testSessionId, null, 'user', 'Món này còn không ạ?', $imgMetadata, 'image');
echo "  ✓ Saved image message ID: $msgId1\n";

$history = AiChatService::getHistory($testSessionId);
assert(count($history) === 1, "Phải có đúng 1 tin nhắn");
assert($history[0]['messageType'] === 'image', "messageType phải là 'image'");
assert($history[0]['metadata']['imageUrl'] === '/uploads/chat/chat_test_123.jpg', "metadata.imageUrl phải khớp");
echo "  ✓ getHistory trả về đúng messageType='image' và metadata\n";

// Test 3: Admin reply with product_card
echo "\n[TEST 3] Admin gửi tin nhắn loại 'product_card'...\n";
$productMetadata = [
    'type'    => 'product_card',
    'product' => [
        'id'          => 1,
        'name'        => 'Burger Bò Nướng Phô Mai',
        'price'       => 69000,
        'status'      => 'in_stock',
        'statusText'  => 'Còn hàng',
        'image'       => '/assets/images/hero-burger.svg'
    ],
    'products' => [
        [
            'id'          => 1,
            'name'        => 'Burger Bò Nướng Phô Mai',
            'price'       => 69000,
            'status'      => 'in_stock',
            'statusText'  => 'Còn hàng',
            'image'       => '/assets/images/hero-burger.svg'
        ]
    ]
];

$reply = AiChatService::sendAdminReply(
    $testSessionId,
    "FASTFOOD xin gửi bạn thông tin món: Burger Bò Nướng Phô Mai - Giá: 69.000đ",
    1,
    $productMetadata,
    'product_card'
);

assert($reply['messageType'] === 'product_card', "Admin reply messageType phải là 'product_card'");
assert(!empty($reply['metadata']['product']), "metadata.product phải tồn tại");
echo "  ✓ sendAdminReply tạo thành công tin nhắn product_card ID: {$reply['id']}\n";

$history2 = AiChatService::getHistory($testSessionId);
assert(count($history2) === 2, "Lịch sử phải có 2 tin nhắn");
assert($history2[1]['messageType'] === 'product_card', "Tin nhắn thứ 2 phải là 'product_card'");
echo "  ✓ getHistory trả về đúng tin nhắn thứ 2 với type='product_card'\n";

// Test 4: Query products with status filter & count
echo "\n[TEST 4] Kiểm tra truy vấn sản phẩm và bộ đếm status...\n";
$stmtCounts = $db->query("SELECT status, COUNT(*) as cnt FROM products GROUP BY status");
$counts = $stmtCounts->fetchAll(PDO::FETCH_KEY_PAIR);
echo "  ✓ Phân bố trạng thái sản phẩm hiện tại trong CSDL:\n";
foreach ($counts as $st => $c) {
    echo "    - $st: $c món\n";
}

// Test 5: Clean up test session
AiChatService::deleteSession($testSessionId);
echo "\n  ✓ Đã dọn dẹp phiên thử nghiệm: $testSessionId\n";

echo "\n====================================================\n";
echo "        TẤT CẢ CÁC BÀI TEST BACKEND ĐÃ ĐẠT!         \n";
echo "====================================================\n";
