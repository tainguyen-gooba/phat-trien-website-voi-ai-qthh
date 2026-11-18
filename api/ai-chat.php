<?php
/**
 * FASTFOOD - API Chatbot Nhân Viên Hỗ Trợ AI
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/helpers/response.php';

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
$message = trim($body['message'] ?? $_POST['message'] ?? '');

if (!$message) {
    jsonError('Tin nhắn không được để trống.');
}

$currentUser = currentUser();
$db = getDB();

// Lưu tin nhắn khách hàng vào CSDL
if ($currentUser) {
    try {
        $stmt = $db->prepare("INSERT INTO chat_messages (customer_id, sender_id, sender_role, message, created_at)
                              VALUES (?, ?, 'customer', ?, NOW())");
        $stmt->execute([$currentUser['id'], $currentUser['id'], $message]);
    } catch (Exception $e) {}
}

// Logic phản hồi thông minh từ cơ sở dữ liệu FASTFOOD
$lower = mb_strtolower($message, 'UTF-8');
$reply = '';

if (strpos($lower, 'hôm nay ăn gì') !== false || strpos($lower, 'gợi ý') !== false || strpos($lower, 'món gì ngon') !== false) {
    $foods = $db->query("SELECT name, base_price, discount_price FROM products WHERE is_available = 1 ORDER BY RAND() LIMIT 3")->fetchAll();
    $list = [];
    foreach ($foods as $f) {
        $price = $f['discount_price'] > 0 ? $f['discount_price'] : $f['base_price'];
        $list[] = "• <strong>" . escapeHtml($f['name']) . "</strong> (" . formatVND($price) . ")";
    }
    $reply = "Dạ chào bạn! Hôm nay FASTFOOD gợi ý bạn một số món đang rất được yêu thích tại Buôn Ma Thuột nhé:<br>" . implode('<br>', $list) . "<br><br>Bạn bấm vào thực đơn để đặt ngay giao nóng hổi nhé!";
}
elseif (strpos($lower, 'dưới 50') !== false || strpos($lower, '50k') !== false || strpos($lower, 'rẻ') !== false) {
    $foods = $db->query("SELECT name, base_price, discount_price FROM products WHERE is_available = 1 AND (discount_price > 0 AND discount_price <= 50000 OR (discount_price = 0 AND base_price <= 50000)) LIMIT 4")->fetchAll();
    $list = [];
    foreach ($foods as $f) {
        $price = $f['discount_price'] > 0 ? $f['discount_price'] : $f['base_price'];
        $list[] = "• <strong>" . escapeHtml($f['name']) . "</strong>: " . formatVND($price);
    }
    $reply = "Dạ, các món ngon giá hạt dẻ dưới 50.000đ dành cho bạn đây ạ:<br>" . implode('<br>', $list) . "<br><br>Đặc biệt đơn từ 100K là được freeship 0đ luôn bạn nhé!";
}
elseif (strpos($lower, 'combo') !== false || strpos($lower, 'tiết kiệm') !== false || strpos($lower, 'khuyến mãi') !== false) {
    $combos = $db->query("SELECT name, base_price, discount_price FROM products WHERE is_available = 1 AND (LOWER(name) LIKE '%combo%' OR discount_price > 0) LIMIT 3")->fetchAll();
    $list = [];
    foreach ($combos as $c) {
        $list[] = "• <strong>" . escapeHtml($c['name']) . "</strong>: " . formatVND($c['discount_price'] ?: $c['base_price']) . " (Giá gốc: " . formatVND($c['base_price']) . ")";
    }
    $reply = "Dạ hiện tại quán đang có các Combo siêu tiết kiệm:<br>" . implode('<br>', $list) . "<br><br>Bạn hãy chọn món vào giỏ hàng ngay để thưởng thức nhé!";
}
elseif (strpos($lower, 'kiểm tra đơn') !== false || strpos($lower, 'đơn hàng của tôi') !== false) {
    if (!$currentUser) {
        $reply = "Dạ để kiểm tra đơn hàng, bạn vui lòng <a href='" . getUrl('login.php') . "' style='color:var(--primary-color); font-weight:700;'>Đăng nhập tài khoản</a> giúp em nhé!";
    } else {
        $stmt = $db->prepare("SELECT order_code, total_amount, order_status, created_at FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 2");
        $stmt->execute([$currentUser['id']]);
        $myOrders = $stmt->fetchAll();
        if (empty($myOrders)) {
            $reply = "Dạ hiện bạn chưa có đơn hàng nào trên hệ thống ạ. Bạn hãy chọn món và đặt ngay nhé!";
        } else {
            $list = [];
            foreach ($myOrders as $ord) {
                $statusMap = [
                    'pending'          => 'Chờ xác nhận',
                    'awaiting_payment' => 'Chờ thanh toán QR',
                    'preparing'        => 'Nhà bếp đang nấu',
                    'delivering'       => 'Shipper đang giao',
                    'delivered'        => 'Đã giao thành công',
                    'cancelled'        => 'Đã hủy',
                ];
                $stName = $statusMap[$ord['order_status']] ?? $ord['order_status'];
                $list[] = "• Đơn <strong>#" . escapeHtml($ord['order_code']) . "</strong> (" . formatVND($ord['total_amount']) . "): <strong>$stName</strong> (<a href='" . getUrl('order-tracking.php?id=' . urlencode($ord['order_code'])) . "'>Xem tiến độ</a>)";
            }
            $reply = "Dạ thông tin đơn hàng gần nhất của bạn đây ạ:<br>" . implode('<br>', $list);
        }
    }
}
elseif (strpos($lower, 'đặt đồ ăn') !== false || strpos($lower, 'đặt món') !== false) {
    $reply = "Dạ bạn có thể bấm vào mục <a href='" . getUrl('foods.php') . "' style='color:var(--primary-color); font-weight:700;'>Thực Đơn</a>, chọn món ăn yêu thích rồi nhấn <strong>Thêm vào giỏ hàng</strong> hoặc <strong>Mua ngay</strong>, điền địa chỉ là shipper giao đến liền trong 20 phút ạ!";
}
else {
    $reply = "Dạ FASTFOOD xin chào bạn! Em là nhân viên hỗ trợ trực tuyến. Em có thể giúp bạn tìm món ăn, tư vấn combo khuyến mãi hoặc kiểm tra đơn hàng. Bạn cần em hỗ trợ thêm thông tin gì ạ?";
}

// Lưu tin nhắn phản hồi của nhân viên/bot vào CSDL
if ($currentUser) {
    try {
        $stmt = $db->prepare("INSERT INTO chat_messages (customer_id, sender_id, sender_role, message, created_at)
                              VALUES (?, 1, 'admin', ?, NOW())");
        $stmt->execute([$currentUser['id'], $reply]);
    } catch (Exception $e) {}
}

jsonSuccess('Phản hồi thành công', [
    'reply'     => $reply,
    'timestamp' => date('H:i'),
]);

