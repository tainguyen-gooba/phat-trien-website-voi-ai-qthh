<?php
namespace FastFood\Controllers;

use FastFood\Config\Database;
use FastFood\Config\SepayConfig;
use FastFood\Constants\HttpStatusCodes;

/**
 * Payment & VietQR Controller
 */
class PaymentController extends BaseController {
    public function getPaymentStatus(string $orderCode): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT order_code, payment_method, status, amount, qr_url, paid_at FROM realtime_payments WHERE order_code = ? LIMIT 1");
        $stmt->execute([$orderCode]);
        $payment = $stmt->fetch();

        if (!$payment) {
            $this->sendError('Không tìm thấy thông tin thanh toán cho đơn hàng này.', HttpStatusCodes::NOT_FOUND);
        }

        $this->sendSuccess('Lấy trạng thái thanh toán thành công!', $payment);
    }

    public function createVietQr(): void {
        $body = $this->getJsonBody();
        $orderCode = trim($body['orderCode'] ?? '');
        $amount = (int)($body['amount'] ?? 0);

        if (!$orderCode || $amount <= 0) {
            $this->sendError('Mã đơn hoặc số tiền không hợp lệ.', HttpStatusCodes::BAD_REQUEST);
        }

        $qrUrl = SepayConfig::getQrUrl($orderCode, $amount);
        $this->sendSuccess('Tạo mã VietQR thành công!', [
            'orderCode' => $orderCode,
            'amount' => $amount,
            'qrUrl' => $qrUrl,
            'bank' => SepayConfig::BANK_CODE,
            'accountNumber' => SepayConfig::ACCOUNT_NUMBER,
            'accountName' => SepayConfig::ACCOUNT_NAME,
        ]);
    }

    public function handleWebhook(): void {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];

        $content = $body['content'] ?? ($body['description'] ?? '');
        $transferAmount = (float)($body['transferAmount'] ?? ($body['amountIn'] ?? 0));
        $transactionId = $body['id'] ?? ($body['referenceCode'] ?? '');

        if (!$content) {
            $this->sendError('Dữ liệu webhook không hợp lệ.', HttpStatusCodes::BAD_REQUEST);
        }

        if (preg_match('/(FF[\w-]*\d+)/i', $content, $matches)) {
            $orderCode = strtoupper($matches[1]);
            $db = Database::getConnection();

            $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'preparing', updated_at = NOW() WHERE order_code = ?");
            $stmt->execute([$orderCode]);

            $pStmt = $db->prepare("UPDATE realtime_payments SET status = 'completed', provider_transaction_id = ?, paid_at = NOW() WHERE order_code = ?");
            $pStmt->execute([$transactionId, $orderCode]);

            $rStmt = $db->prepare("UPDATE realtime_orders SET status = 'preparing', updated_at = NOW() WHERE order_code = ?");
            $rStmt->execute([$orderCode]);

            $this->sendSuccess('Xử lý webhook SePay thành công!', ['orderCode' => $orderCode]);
        }

        $this->sendSuccess('Webhook nhận nhưng không tìm thấy mã đơn hàng phù hợp.');
    }
}

