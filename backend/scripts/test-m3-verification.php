<?php
/**
 * FASTFOOD Automated Verification Suite for Milestone 3 (M3: Chat UI/UX Redesign & Image Attachment)
 *
 * Verifies:
 * 1. PHP Syntax of admin/index.php
 * 2. DOM Elements for Admin Chat Attachment & Upload Preview (F5)
 * 3. CSS Modernization for Bubbles, Badges, Preview Strip & Tools Cluster (F5, F7)
 * 4. JS Upload Logic & Reply Payload Formatting in js/admin-chat.js (F6, F7)
 * 5. Customer Chatbot Widget Glassmorphism & Safe-Area Polish in css/chatbot.css (F8)
 * 6. Asset Mirror 100% SHA256 Parity
 * 7. Functional Backend Reply Simulation for Image Message
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/backend/src/config/Database.php';
require_once $projectRoot . '/backend/src/services/AiChatService.php';

$passCount = 0;
$failCount = 0;

function runTest(string $name, callable $fn): void {
    global $passCount, $failCount;
    echo "[TEST] {$name}... ";
    try {
        $result = $fn();
        if ($result === true || $result === null) {
            echo "PASS ✓\n";
            $passCount++;
        } else {
            echo "FAIL ✗ ({$result})\n";
            $failCount++;
        }
    } catch (Throwable $e) {
        echo "FAIL ✗ (" . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . ")\n";
        $failCount++;
    }
}

echo "====================================================================\n";
echo " FASTFOOD TEST SUITE: MILESTONE 3 (CHAT UI/UX & IMAGE ATTACHMENT)  \n";
echo "====================================================================\n\n";

// 1. PHP Syntax Check
runTest("admin/index.php: Kiểm tra cú pháp PHP (php -l)", function () use ($projectRoot) {
    $output = [];
    $returnVar = 0;
    exec("php -l " . escapeshellarg($projectRoot . '/admin/index.php'), $output, $returnVar);
    if ($returnVar !== 0) {
        return "Cú pháp PHP không hợp lệ: " . implode("\n", $output);
    }
    return true;
});

// 2. DOM Elements in admin/index.php
runTest("admin/index.php: Kiểm tra các phần tử DOM cho Attachment & Upload Preview (F5)", function () use ($projectRoot) {
    $content = file_get_contents($projectRoot . '/admin/index.php');
    if ($content === false) return "Không thể đọc file admin/index.php";

    $requiredElements = [
        'id="admin-chat-upload-preview"' => 'Upload preview strip',
        'id="admin-preview-thumb"'        => 'Thumbnail xem trước ảnh',
        'id="admin-preview-filename"'     => 'Tên tệp xem trước',
        'id="admin-preview-filesize"'     => 'Kích thước tệp xem trước',
        'id="admin-btn-remove-preview"'   => 'Nút hủy ảnh xem trước',
        'id="btn-open-product-picker"'    => 'Nút mở Kho món ăn',
        'id="admin-chat-file-input"'      => 'Input chọn tệp ảnh ẩn',
        'id="admin-chat-attach-btn"'      => 'Nút trigger đính kèm ảnh',
        'id="admin-chat-input-field"'     => 'Trường nhập tin nhắn chat',
        'id="admin-chat-submit-btn"'      => 'Nút gửi tin nhắn',
    ];

    foreach ($requiredElements as $needle => $desc) {
        if (!str_contains($content, $needle)) {
            return "Thiếu phần tử: {$desc} ({$needle})";
        }
    }
    return true;
});

// 3. CSS Modernization in admin/index.php
runTest("admin/index.php: Kiểm tra các quy tắc CSS cho Chat Bubbles, Badges & Toolbar (F5, F7)", function () use ($projectRoot) {
    $content = file_get_contents($projectRoot . '/admin/index.php');

    $requiredCssRules = [
        '.admin-chat-upload-preview' => 'CSS strip preview tải ảnh',
        '.admin-preview-thumb-wrap'  => 'CSS khung thumbnail preview',
        '.admin-btn-remove-preview'  => 'CSS nút xóa ảnh preview',
        '.btn-attach-trigger'        => 'CSS nút đính kèm ảnh',
        '.admin-chat-tools-cluster'  => 'CSS cụm công cụ đính kèm & kho món',
        '.admin-msg-sender-badge'    => 'CSS badge người gửi',
        '.badge-customer'            => 'CSS badge khách hàng (#eff6ff)',
        '.badge-admin'               => 'CSS badge quản trị viên (#fef3c7)',
        '.badge-ai'                  => 'CSS badge trợ lý AI (#f3e8ff)',
    ];

    foreach ($requiredCssRules as $rule => $desc) {
        if (!str_contains($content, $rule)) {
            return "Thiếu quy tắc CSS: {$desc} ({$rule})";
        }
    }
    return true;
});

// 4. JS Logic in js/admin-chat.js: Elements, Validation & Upload Execution
runTest("js/admin-chat.js: Kiểm tra logic đính kèm, xác thực ảnh & gửi payload (F6, F7)", function () use ($projectRoot) {
    $content = file_get_contents($projectRoot . '/js/admin-chat.js');
    if ($content === false) return "Không thể đọc js/admin-chat.js";

    $requiredPatterns = [
        'admin-chat-attach-btn'        => 'Tham chiếu nút đính kèm ảnh',
        'admin-chat-file-input'        => 'Tham chiếu file input ẩn',
        'admin-chat-upload-preview'    => 'Tham chiếu thanh preview ảnh',
        'createObjectURL'              => 'Tạo URL preview từ đối tượng File',
        'uploadChatImageFile'          => 'Hàm upload ảnh chat',
        'messageType'                  => 'Gắn messageType vào payload phản hồi',
        'admin-msg-sender-badge'       => 'Render badge phân biệt người gửi',
        'badge-admin'                  => 'Nhãn badge Admin',
        'badge-ai'                     => 'Nhãn badge AI',
        'badge-customer'               => 'Nhãn badge Khách hàng',
    ];

    foreach ($requiredPatterns as $pattern => $desc) {
        if (!str_contains($content, $pattern)) {
            return "Thiếu logic JS: {$desc} ({$pattern})";
        }
    }
    return true;
});

// 5. Customer Chatbot Widget Polish in css/chatbot.css (F8)
runTest("css/chatbot.css: Kiểm tra Glassmorphism, Tab Bar & Safe-Area Padding (F8)", function () use ($projectRoot) {
    $content = file_get_contents($projectRoot . '/css/chatbot.css');
    if ($content === false) return "Không thể đọc css/chatbot.css";

    $requiredRules = [
        'backdrop-filter: blur(12px)'                         => 'Header glassmorphism',
        'backdrop-filter: blur(8px)'                          => 'Tab bar glassmorphism',
        'padding-bottom: max(14px, env(safe-area-inset-bottom))' => 'Mobile safe area padding',
        '.ff-sender-tag.is-admin'                             => 'Nhãn người gửi Admin trong widget',
        '.ff-msg-row.admin .ff-msg-bubble'                    => 'Bong bóng tin nhắn Admin trong widget',
    ];

    foreach ($requiredRules as $rule => $desc) {
        if (!str_contains($content, $rule)) {
            return "Thiếu thuộc tính CSS chatbot: {$desc} ({$rule})";
        }
    }
    return true;
});

// 6. Asset Mirror SHA256 Parity
runTest("Mirror Parity: Kiểm tra tính toàn vẹn 100% SHA256 giữa root và frontend/", function () use ($projectRoot) {
    $pairs = [
        'js/admin-chat.js' => 'frontend/js/admin-chat.js',
        'css/chatbot.css'  => 'frontend/css/chatbot.css',
        'js/chatbot.js'    => 'frontend/js/chatbot.js',
    ];

    foreach ($pairs as $rootRel => $mirrorRel) {
        $rootPath = $projectRoot . '/' . $rootRel;
        $mirrorPath = $projectRoot . '/' . $mirrorRel;

        if (!file_exists($rootPath)) return "Thiếu file root: {$rootRel}";
        if (!file_exists($mirrorPath)) return "Thiếu file mirror: {$mirrorRel}";

        $rootHash = hash_file('sha256', $rootPath);
        $mirrorHash = hash_file('sha256', $mirrorPath);

        if ($rootHash !== $mirrorHash) {
            return "Lệch hash SHA256 giữa {$rootRel} và {$mirrorRel} (root: {$rootHash}, mirror: {$mirrorHash})";
        }
    }
    return true;
});

// 7. Functional Backend Reply Simulation with Image Message
runTest("Functional Backend: Mô phỏng Admin gửi tin nhắn ảnh và kiểm tra bản ghi", function () {
    $testSessionId = 'test_m3_img_' . time();
    $testImageUrl = '/uploads/chat/chat_test_sample.jpg';
    $testMeta = [
        'type'     => 'image',
        'imageUrl' => $testImageUrl,
        'fileName' => 'chat_test_sample.jpg',
        'fileSize' => 102400,
        'mimeType' => 'image/jpeg'
    ];

    // Admin gửi tin nhắn kèm ảnh
    $reply = \FastFood\Services\AiChatService::sendAdminReply(
        $testSessionId,
        '[Hình ảnh]',
        1,
        $testMeta,
        'image'
    );

    if (empty($reply['id'])) {
        return "Không tạo được ID tin nhắn";
    }
    if ($reply['messageType'] !== 'image') {
        return "messageType không phải là 'image', nhận được: " . ($reply['messageType'] ?? 'null');
    }
    if (($reply['metadata']['imageUrl'] ?? '') !== $testImageUrl) {
        return "metadata imageUrl không trùng khớp";
    }

    // Truy vấn lại lịch sử từ Database để xác thực tính toàn vẹn lưu trữ
    $history = \FastFood\Services\AiChatService::getSessionMessages($testSessionId);
    if (empty($history)) {
        return "Không truy vấn được lịch sử phiên từ database";
    }

    $lastMsg = end($history);
    if (($lastMsg['messageType'] ?? '') !== 'image') {
        return "Bản ghi trong database không lưu đúng messageType='image'";
    }

    // Dọn dẹp phiên test
    \FastFood\Services\AiChatService::deleteSession($testSessionId);

    return true;
});

echo "\n====================================================================\n";
echo " KẾT QUẢ KIỂM THỬ: {$passCount} / " . ($passCount + $failCount) . " TESTS THÀNH CÔNG\n";
echo "====================================================================\n";

if ($failCount === 0) {
    echo ">>> TẤT CẢ CÁC BÀI TEST ĐẠT 100% TIÊU CHUẨN MILESTONE 3 (M3) <<<\n";
    exit(0);
} else {
    echo ">>> CÓ {$failCount} BÀI TEST THẤT BẠI! CẦN KHẮC PHỤC TRƯỚC KHI BÀN GIAO <<<\n";
    exit(1);
}
