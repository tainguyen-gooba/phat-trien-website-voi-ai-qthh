<?php
/**
 * FASTFOOD - Xác nhận & Thanh toán đơn hàng (Checkout)
 */
require_once __DIR__ . '/config/config.php';
requireLogin();

$currentUser = currentUser();
$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    header('Location: ' . getUrl('cart.php'));
    exit;
}

$db = getDB();
$error = '';
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += (float)$item['price'] * (int)$item['qty'];
}
$shippingFee = ($subtotal > 0 && $subtotal < 100000) ? 18000 : 0;
$total = $subtotal + $shippingFee;

// Xử lý gửi Form Đặt Hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['customer_name'] ?? '');
    $phone   = trim($_POST['customer_phone'] ?? '');
    $address = trim($_POST['customer_address'] ?? '');
    $notes   = trim($_POST['customer_notes'] ?? '');
    $method  = trim($_POST['paymentMethod'] ?? 'cod');

    if (mb_strlen($name) < 3) {
        $error = 'Vui lòng nhập họ và tên người nhận hợp lệ.';
    } elseif (!preg_match('/^[0-9+() -]{10,15}$/', $phone)) {
        $error = 'Số điện thoại người nhận không hợp lệ.';
    } elseif (mb_strlen($address) < 6) {
        $error = 'Vui lòng nhập địa chỉ giao hàng cụ thể.';
    } else {
        // Sinh mã đơn hàng ngẫu nhiên FFxxxx
        $orderCode = 'FF' . rand(1000, 9999);
        
        // Đảm bảo user_id hợp lệ trong bảng users để tránh lỗi foreign key constraint
        $userId = (int)($currentUser['id'] ?? 0);
        $userCheck = $db->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $userCheck->execute([$userId]);
        if (!$userCheck->fetch()) {
            $fallbackUser = $db->query("SELECT id FROM users WHERE role_id = 2 ORDER BY id ASC LIMIT 1")->fetch();
            $userId = $fallbackUser ? (int)$fallbackUser['id'] : 1;
            $_SESSION['user_id'] = $userId;
        }

        // Lấy restaurant_id từ sản phẩm đầu tiên trong giỏ hàng (mặc định 1)
        $restaurantId = 1;
        foreach ($cart as $id => $item) {
            if (is_numeric($id) && (int)$id > 0) {
                $pCheck = $db->prepare("SELECT restaurant_id FROM products WHERE id = ? LIMIT 1");
                $pCheck->execute([(int)$id]);
                $rRow = $pCheck->fetch();
                if (!empty($rRow['restaurant_id'])) {
                    $restaurantId = (int)$rRow['restaurant_id'];
                    break;
                }
            }
        }

        $db->beginTransaction();
        try {
            $paymentStatus = 'pending';
            $orderStatus   = ($method === 'cod') ? 'pending' : 'awaiting_payment';
            $paymentMethodDb = ($method === 'vietqr' || $method === 'banking') ? 'banking' : (($method === 'momo') ? 'momo' : 'cod');

            // 1. Lưu vào bảng orders
            $stmt = $db->prepare("INSERT INTO orders 
                (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, note_for_restaurant, 
                 subtotal, shipping_fee, discount_amount, total_amount, payment_method, payment_status, order_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $orderCode,
                $userId,
                $restaurantId,
                $name,
                $phone,
                $address,
                $notes,
                $subtotal,
                $shippingFee,
                $total,
                $paymentMethodDb,
                $paymentStatus,
                $orderStatus
            ]);
            $orderId = $db->lastInsertId();

            // 2. Lưu từng món vào order_items
            $itemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal)
                                      VALUES (?, ?, ?, ?, ?, ?)");
            $orderItemsData = [];
            foreach ($cart as $id => $item) {
                $itemSub = (float)$item['price'] * (int)$item['qty'];
                $itemStmt->execute([
                    $orderId,
                    is_numeric($id) ? (int)$id : 1,
                    $item['name'],
                    $item['qty'],
                    $item['price'],
                    $itemSub
                ]);
                $orderItemsData[] = [
                    'id'    => $id,
                    'name'  => $item['name'],
                    'price' => $item['price'],
                    'qty'   => $item['qty'],
                    'image' => $item['image'] ?? '',
                ];
            }

            // 3. Đồng bộ với realtime_orders để hỗ trợ Socket và SePay
            $orderData = [
                'id'              => $orderCode,
                'orderCode'       => $orderCode,
                'userId'          => $userId,
                'customerName'    => $name,
                'recipient'       => ['name' => $name, 'phone' => $phone, 'address' => $address],
                'deliveryAddress' => $address,
                'phone'           => $phone,
                'subtotal'        => $subtotal,
                'shippingFee'     => $shippingFee,
                'total'           => $total,
                'paymentMethod'   => $paymentMethodDb,
                'paymentStatus'   => $paymentStatus,
                'status'          => $orderStatus,
                'statusText'      => ($method === 'cod') ? 'Chờ xác nhận' : 'Chờ thanh toán',
                'items'           => $orderItemsData,
                'notes'           => $notes,
                'createdAt'       => date('c'),
            ];

            $rtStmt = $db->prepare("INSERT INTO realtime_orders (order_code, user_id, order_data, status, created_at)
                                    VALUES (?, ?, ?, ?, NOW())");
            $rtStmt->execute([$orderCode, $userId, json_encode($orderData, JSON_UNESCAPED_UNICODE), $orderStatus]);

            // 4. Nếu thanh toán Online (VietQR / MoMo / SePay), tạo realtime_payments
            if ($paymentMethodDb === 'banking' || $paymentMethodDb === 'momo') {
                $qrUrl = "https://qr.sepay.vn/img?acc=" . SEPAY_ACCOUNT_NUMBER . "&bank=" . SEPAY_BANK_CODE . "&amount=" . $total . "&des=" . $orderCode . "&template=compact";
                $rpStmt = $db->prepare("INSERT INTO realtime_payments 
                    (order_code, payment_method, amount, status, qr_url, expires_at, created_at)
                    VALUES (?, ?, ?, 'pending', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW())");
                $rpStmt->execute([$orderCode, $paymentMethodDb, $total, $qrUrl]);
            }

            $db->commit();

            // Xóa giỏ hàng
            unset($_SESSION['cart']);

            // Điều hướng
            if ($paymentMethodDb === 'banking' || $paymentMethodDb === 'momo') {
                header('Location: ' . getUrl("payment.php?orderCode=" . urlencode($orderCode)));
            } else {
                header('Location: ' . getUrl("order-tracking.php?id=" . urlencode($orderCode)));
            }
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Lỗi hệ thống khi đặt hàng: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Xác Nhận & Thanh Toán Đơn Hàng - FASTFOOD';
$activeNav = 'cart';
$extraCss  = ['css/pages.css'];

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= getUrl('index.php') ?>">Trang chủ</a> <span>/</span> 
      <a href="<?= getUrl('cart.php') ?>">Giỏ hàng</a> <span>/</span> 
      <span>Thanh toán</span>
    </div>
    <h1 class="page-title">Xác Nhận & Thanh Toán Đơn Hàng</h1>
  </div>
</div>

<main class="container">
  <?php if ($error): ?>
    <div style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:1rem 1.25rem; border-radius:10px; margin-bottom:1.5rem; font-weight:600;">
      ❌ <?= escapeHtml($error) ?>
    </div>
  <?php endif; ?>

  <form action="<?= getUrl('checkout.php') ?>" method="POST" class="checkout-layout">
    <!-- Left: Delivery Information & Payment Method -->
    <div>
      <!-- 1. Thông tin nhận hàng -->
      <div class="card" style="margin-bottom:2rem; padding:1.5rem; background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.04);">
        <h3 style="font-size:1.2rem; font-weight:800; color:var(--text-main); margin-bottom:1.25rem;">
          📍 1. Thông Tin Nhận Hàng
        </h3>

        <div class="grid grid-cols-2" style="gap:1rem; margin-bottom:1rem;">
          <div class="form-group">
            <label class="form-label" for="customer-name">Họ và Tên người nhận *</label>
            <input type="text" name="customer_name" id="customer-name" class="form-control" 
                   value="<?= escapeHtml($_POST['customer_name'] ?? $currentUser['full_name']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="customer-phone">Số điện thoại liên hệ *</label>
            <input type="tel" name="customer_phone" id="customer-phone" class="form-control" 
                   value="<?= escapeHtml($_POST['customer_phone'] ?? $currentUser['phone']) ?>" placeholder="0912345678" required>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
          <label class="form-label" for="customer-address">Địa chỉ nhận món chi tiết *</label>
          <input type="text" name="customer_address" id="customer-address" class="form-control" 
                 value="<?= escapeHtml($_POST['customer_address'] ?? '') ?>" placeholder="Số nhà, tên đường, phường/xã..." required>
        </div>

        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label" for="customer-notes">Ghi chú cho tài xế hoặc nhà hàng</label>
          <input type="text" name="customer_notes" id="customer-notes" class="form-control" 
                 value="<?= escapeHtml($_POST['customer_notes'] ?? '') ?>" placeholder="Ví dụ: Gọi trước khi giao 5 phút...">
        </div>
      </div>

      <!-- 2. Phương thức thanh toán -->
      <div class="card" style="padding:1.5rem; background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.04);">
        <h3 style="font-size:1.2rem; font-weight:800; color:var(--text-main); margin-bottom:1.25rem;">
          💳 2. Phương Thức Thanh Toán
        </h3>

        <label class="payment-method-card" style="display:flex; align-items:center; gap:1rem; padding:1rem; border:2px solid var(--border-color); border-radius:10px; margin-bottom:1rem; cursor:pointer;">
          <input type="radio" name="paymentMethod" value="cod" checked style="accent-color:var(--primary-color);">
          <div style="width:44px; height:44px; border-radius:8px; background:#e8f8ee; color:#00b14f; display:flex; align-items:center; justify-content:center; font-weight:800;">
            COD
          </div>
          <div>
            <strong style="display:block; font-size:0.95rem;">Thanh toán tiền mặt khi nhận hàng (COD)</strong>
            <small style="color:var(--text-secondary);">Thanh toán trực tiếp cho shipper khi giao đồ ăn</small>
          </div>
        </label>

        <label class="payment-method-card" style="display:flex; align-items:center; gap:1rem; padding:1rem; border:2px solid #ea580c; background:#fffaf6; border-radius:10px; margin-bottom:1rem; cursor:pointer;">
          <input type="radio" name="paymentMethod" value="vietqr" style="accent-color:var(--primary-color);">
          <div style="width:44px; height:44px; border-radius:8px; background:#ffedd5; color:#ea580c; display:flex; align-items:center; justify-content:center; font-weight:900;">
            QR
          </div>
          <div>
            <strong style="display:block; font-size:0.95rem; color:#ea580c;">Chuyển khoản SePay / VietQR tự động (Khuyên dùng)</strong>
            <small style="color:var(--text-secondary);">Quét mã QR qua app ngân hàng, hệ thống tự động duyệt đơn trong 5-30 giây</small>
          </div>
        </label>
      </div>
    </div>

    <!-- Right: Order Summary Bill -->
    <div>
      <div class="cart-summary-card" style="padding:1.5rem; background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.04); position:sticky; top:90px;">
        <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-main); margin-bottom:1.25rem;">
          Đơn Hàng Của Bạn (<?= count($cart) ?> món)
        </h3>

        <!-- Danh sách tóm tắt món -->
        <div style="max-height:240px; overflow-y:auto; margin-bottom:1rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
          <?php foreach ($cart as $item): ?>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem; font-size:0.9rem;">
              <div>
                <strong><?= escapeHtml($item['name']) ?></strong>
                <span style="color:var(--text-muted);"> × <?= $item['qty'] ?></span>
                <?php if (!empty($item['toppings']) && is_array($item['toppings'])): ?>
                  <div style="font-size:0.75rem; color:#ea580c; font-weight:600; margin-top:2px;">
                    ✨ <?= implode(', ', array_map(function($t) { return escapeHtml($t['name']); }, $item['toppings'])) ?>
                  </div>
                <?php endif; ?>
              </div>
              <span style="font-weight:700;"><?= formatVND((float)$item['price'] * (int)$item['qty']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="summary-row">
          <span>Tạm tính món ăn:</span>
          <span style="font-weight:700;"><?= formatVND($subtotal) ?></span>
        </div>

        <div class="summary-row">
          <span>Phí vận chuyển:</span>
          <span style="font-weight:700;"><?= $shippingFee > 0 ? formatVND($shippingFee) : '<span style="color:var(--success);">Miễn phí</span>' ?></span>
        </div>

        <div class="summary-row total-row" style="border-top:1px solid var(--border-color); padding-top:1rem; margin-top:0.75rem;">
          <span style="font-size:1.1rem; font-weight:800;">Tổng thanh toán:</span>
          <span style="color:var(--primary-color); font-size:1.4rem; font-weight:800;"><?= formatVND($total) ?></span>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width:100%; margin-top:1.5rem; font-size:1.05rem;">
          🚀 Xác Nhận Đặt Hàng
        </button>

        <p style="font-size:0.8rem; color:var(--text-muted); text-align:center; margin-top:0.75rem; line-height:1.4;">
          Bằng việc nhấn đặt hàng, bạn đồng ý với Điều khoản dịch vụ và Chính sách giao nhận của FASTFOOD.
        </p>
      </div>
    </div>
  </form>
</main>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

