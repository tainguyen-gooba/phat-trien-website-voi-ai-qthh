<?php
/**
 * FASTFOOD - SePay Webhook Endpoint (Tự động nhận tiền và duyệt đơn)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/helpers/response.php';

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];

$transactionId = (string)($body['id'] ?? '');
$accountNumber = (string)($body['accountNumber'] ?? '');
$content       = strtoupper((string)($body['content'] ?? $body['description'] ?? ''));
$transferType  = strtolower((string)($body['transferType'] ?? ''));
$amount        = (float)($body['transferAmount'] ?? 0);

// Trích xuất mã đơn hàng FFxxxx
if (!preg_match('/FF(?:\d{4}|-[A-Z0-9]{6,8}|-\d{8}-\d{4})/', $content, $matches)) {
    jsonError('Không tìm thấy mã đơn hàng trong nội dung giao dịch.', 400);
}
$orderCode = $matches[0];

if (!$transactionId || $transferType !== 'in' || $amount <= 0) {
    jsonError('Dữ liệu giao dịch không hợp lệ.', 400);
}

$db = getDB();
$stmt = $db->prepare("SELECT id, total_amount, payment_status, order_status FROM orders WHERE order_code = ? LIMIT 1");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    jsonError("Không tìm thấy đơn hàng $orderCode.", 404);
}

// Kiểm tra số tiền
if ((float)$order['total_amount'] > $amount) {
    jsonError("Số tiền chuyển khoản ($amount) nhỏ hơn giá trị đơn hàng (" . $order['total_amount'] . ").", 400);
}

// Cập nhật trạng thái thanh toán thành completed và chuyển đơn sang bếp nấu (preparing)
$db->beginTransaction();
try {
    $upOrder = $db->prepare("UPDATE orders 
                             SET payment_status = 'completed', order_status = 'preparing', confirmed_at = NOW() 
                             WHERE order_code = ?");
    $upOrder->execute([$orderCode]);

    $upPayment = $db->prepare("UPDATE realtime_payments 
                               SET status = 'completed', provider_transaction_id = ?, paid_at = NOW() 
                               WHERE order_code = ?");
    $upPayment->execute([$transactionId, $orderCode]);

    $upRt = $db->prepare("UPDATE realtime_orders SET status = 'preparing' WHERE order_code = ?");
    $upRt->execute([$orderCode]);

    $db->commit();
    jsonSuccess('Xác nhận thanh toán SePay thành công!', [
        'orderCode'     => $orderCode,
        'transactionId' => $transactionId,
        'amount'        => $amount
    ]);
} catch (Exception $e) {
    $db->rollBack();
    jsonError('Lỗi cập nhật CSDL: ' . $e->getMessage(), 500);
}

