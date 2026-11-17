<?php
/**
 * FASTFOOD - API Kiểm tra trạng thái thanh toán đơn hàng (SePay / VietQR)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/helpers/response.php';

$orderCode = strtoupper(trim($_GET['orderCode'] ?? ''));
if (!$orderCode) {
    jsonError('Thiếu mã đơn hàng.');
}

$db = getDB();
$stmt = $db->prepare("SELECT o.order_code, o.payment_status, o.order_status, o.total_amount, 
                             p.status AS realtime_payment_status, p.paid_at
                      FROM orders o
                      LEFT JOIN realtime_payments p ON o.order_code = p.order_code
                      WHERE o.order_code = ? LIMIT 1");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    jsonError('Không tìm thấy đơn hàng.', 404);
}

$isPaid = ($order['payment_status'] === 'completed' || $order['realtime_payment_status'] === 'completed');

jsonSuccess('Lấy trạng thái thành công', [
    'orderCode'     => $order['order_code'],
    'paymentStatus' => $isPaid ? 'completed' : 'pending',
    'orderStatus'   => $order['order_status'],
    'total'         => (float)$order['total_amount'],
    'paidAt'        => $order['paid_at'],
]);

