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
require_once ROOT_PATH . '/helpers/loyalty.php';

// Lấy thông tin điểm thưởng và hạng thành viên mới nhất
$userLoyalty = null;
$userPoints = 0;
$userTier = 'BRONZE';
$userId = (int)($currentUser['id'] ?? 0);

if ($userId > 0) {
    $uStmt = $db->prepare("SELECT points_balance, member_tier, total_spent FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $userLoyalty = $uStmt->fetch(PDO::FETCH_ASSOC);
    if ($userLoyalty) {
        $userPoints = (int)$userLoyalty['points_balance'];
        $userTier = strtoupper($userLoyalty['member_tier'] ?? 'BRONZE');
    }
}

$loyaltySettings = getLoyaltySettings();
$pointRate = !empty($loyaltySettings['point_to_vnd_rate']) ? (float)$loyaltySettings['point_to_vnd_rate'] : 100.0;
$minRedeem = !empty($loyaltySettings['min_redeem_points']) ? (int)$loyaltySettings['min_redeem_points'] : 100;
$tiers = getMembershipTiers();
$currentTierData = $tiers[$userTier] ?? ($tiers['BRONZE'] ?? [
    'tier_name' => 'Đồng',
    'badge_icon' => '🥉',
    'badge_color' => '#cd7f32'
]);

$error = '';
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += (float)$item['price'] * (int)$item['qty'];
}
$shippingFee = ($subtotal > 0 && $subtotal < 100000) ? 18000 : 0;

// Xử lý giảm giá từ Coupon / Voucher
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;
$discount = 0;
if ($appliedCoupon && $subtotal > 0) {
    if (($appliedCoupon['discount_type'] ?? '') === 'percentage') {
        $discount = ($subtotal * (float)$appliedCoupon['discount_value']) / 100.0;
        if (!empty($appliedCoupon['max_discount_amount']) && (float)$appliedCoupon['max_discount_amount'] > 0) {
            $discount = min($discount, (float)$appliedCoupon['max_discount_amount']);
        }
    } else {
        $discount = min($subtotal, (float)($appliedCoupon['discount_value'] ?? 0));
    }
}
$payableBeforePoints = max(0, $subtotal + $shippingFee - $discount);
$maxPointsForOrder = (int)floor($payableBeforePoints / $pointRate);
$maxUsablePoints = min($userPoints, $maxPointsForOrder);
if ($maxUsablePoints < $minRedeem) {
    $maxUsablePoints = 0;
}
$total = $payableBeforePoints;

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
        // Xử lý điểm thưởng muốn sử dụng
        $pointsToUse = 0;
        $pointsDiscount = 0.0;
        if (isset($_POST['points_to_use'])) {
            $rawPoints = trim((string)$_POST['points_to_use']);
            if ($rawPoints !== '') {
                if (!ctype_digit($rawPoints)) {
                    $error = 'Số điểm thưởng phải là một số nguyên không âm.';
                } else {
                    $pointsToUse = (int)$rawPoints;
                }
            }
        }

        if (!$error && $pointsToUse > 0) {
            if (!$currentUser || $userId <= 0) {
                $error = 'Bạn cần đăng nhập để sử dụng điểm thưởng.';
            } elseif ($pointsToUse < $minRedeem) {
                $error = "Số điểm tối thiểu để sử dụng là {$minRedeem} điểm.";
            } elseif ($pointsToUse > $userPoints) {
                $error = "Số điểm sử dụng ({$pointsToUse}) vượt quá số dư hiện tại của bạn (" . number_format($userPoints) . " điểm).";
            } else {
                $pointsDiscount = $pointsToUse * $pointRate;
                if ($pointsDiscount > $payableBeforePoints) {
                    $error = "Số tiền giảm giá từ điểm (" . formatVND($pointsDiscount) . ") không được vượt quá số tiền cần thanh toán (" . formatVND($payableBeforePoints) . ").";
                }
            }
        }
    }

    if (!$error) {
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
            $total = max(0, $payableBeforePoints - $pointsDiscount);

            // Xác định trạng thái thanh toán nếu đơn hàng được thanh toán 100% bằng điểm (total == 0)
            if ($total == 0) {
                $paymentStatus = 'paid';
                $orderStatus   = 'pending';
            } else {
                $paymentStatus = 'pending';
                $orderStatus   = ($method === 'cod') ? 'pending' : 'awaiting_payment';
            }
            $paymentMethodDb = ($method === 'vietqr' || $method === 'banking') ? 'banking' : (($method === 'momo') ? 'momo' : 'cod');

            // 1. Lưu vào bảng orders bao gồm points_used và points_discount
            $couponId = !empty($appliedCoupon['id']) ? (int)$appliedCoupon['id'] : null;
            $stmt = $db->prepare("INSERT INTO orders 
                (order_code, customer_id, restaurant_id, coupon_id, recipient_name, recipient_phone, delivery_address, note_for_restaurant, 
                 subtotal, shipping_fee, discount_amount, points_used, points_discount, total_amount, payment_method, payment_status, order_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $orderCode,
                $userId,
                $restaurantId,
                $couponId,
                $name,
                $phone,
                $address,
                $notes,
                $subtotal,
                $shippingFee,
                $discount,
                $pointsToUse,
                $pointsDiscount,
                $total,
                $paymentMethodDb,
                $paymentStatus,
                $orderStatus
            ]);
            $orderId = (int)$db->lastInsertId();

            // Áp dụng trừ điểm thưởng qua helpers/loyalty.php
            if ($pointsToUse > 0) {
                $redeemRes = redeemPointsAtCheckout($userId, $orderId, $pointsToUse, $orderCode);
                if (empty($redeemRes['success'])) {
                    throw new Exception($redeemRes['error'] ?? 'Không thể áp dụng trừ điểm thưởng.');
                }
            }

            if ($couponId) {
                try {
                    $db->prepare("UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ?")->execute([$couponId]);
                } catch (Exception $e) {}
            }

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
                'discount'        => $discount,
                'pointsUsed'      => $pointsToUse,
                'pointsDiscount'  => $pointsDiscount,
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

            // 4. Nếu thanh toán Online (VietQR / MoMo / SePay), tạo realtime_payments (nếu còn số tiền cần trả)
            if ($total > 0 && ($paymentMethodDb === 'banking' || $paymentMethodDb === 'momo')) {
                $qrUrl = "https://qr.sepay.vn/img?acc=" . SEPAY_ACCOUNT_NUMBER . "&bank=" . SEPAY_BANK_CODE . "&amount=" . $total . "&des=" . $orderCode . "&template=compact";
                $rpStmt = $db->prepare("INSERT INTO realtime_payments 
                    (order_code, payment_method, amount, status, qr_url, expires_at, created_at)
                    VALUES (?, ?, ?, 'pending', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW())");
                $rpStmt->execute([$orderCode, $paymentMethodDb, $total, $qrUrl]);
            }

            $db->commit();

            // Xóa giỏ hàng và voucher đã áp dụng
            unset($_SESSION['cart']);
            unset($_SESSION['applied_coupon']);

            // Điều hướng
            if ($total > 0 && ($paymentMethodDb === 'banking' || $paymentMethodDb === 'momo')) {
                header('Location: ' . getUrl("payment.php?orderCode=" . urlencode($orderCode) . "&just_ordered=1"));
            } else {
                header('Location: ' . getUrl("order-tracking.php?id=" . urlencode($orderCode) . "&just_ordered=1"));
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
          <label class="form-label" for="customer-notes">Ghi chú đơn hàng (cho tài xế/bếp làm món)</label>
          <input type="text" name="customer_notes" id="customer-notes" class="form-control" 
                 value="<?= escapeHtml($_POST['customer_notes'] ?? '') ?>" placeholder="Ví dụ: Gọi trước khi giao 5 phút, ít đá...">
        </div>
      </div>

      <!-- 2. Sử Dụng Điểm Thưởng FASTFOOD Rewards -->
      <?php if ($userId > 0): ?>
        <div class="card" style="margin-bottom:2rem; padding:1.5rem; background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.04); border-left:4px solid #ea580c;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
            <h3 style="font-size:1.2rem; font-weight:800; color:var(--text-main); margin:0;">
              🎁 2. Điểm Thưởng FASTFOOD Rewards
            </h3>
            <span style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.25rem 0.75rem; border-radius:20px; font-size:0.8rem; font-weight:700; background:<?= escapeHtml($currentTierData['badge_color'] ?? '#cd7f32') ?>18; color:<?= escapeHtml($currentTierData['badge_color'] ?? '#cd7f32') ?>; border:1px solid <?= escapeHtml($currentTierData['badge_color'] ?? '#cd7f32') ?>44;">
              <?= escapeHtml($currentTierData['badge_icon'] ?? '🥉') ?> Hạng <?= escapeHtml($currentTierData['tier_name'] ?? 'Đồng') ?>
            </span>
          </div>

          <div style="display:flex; align-items:center; justify-content:space-between; background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:0.85rem 1rem; margin-bottom:1.25rem;">
            <div>
              <span style="font-size:0.85rem; color:#9a3412;">Số dư điểm khả dụng:</span>
              <div style="font-size:1.25rem; font-weight:900; color:#ea580c;">
                <?= number_format($userPoints) ?> <span style="font-size:0.85rem; font-weight:600;">điểm</span>
              </div>
            </div>
            <div style="text-align:right;">
              <span style="font-size:0.8rem; color:#9a3412;">Quy đổi tương đương:</span>
              <div style="font-size:1.05rem; font-weight:800; color:#c2410c;">
                ≈ <?= formatVND($userPoints * $pointRate) ?>
              </div>
            </div>
          </div>

          <?php if ($maxUsablePoints >= $minRedeem): ?>
            <div class="form-group" style="margin-bottom:0.75rem;">
              <label class="form-label" for="points-to-use" style="display:flex; justify-content:space-between; align-items:center; font-weight:700; font-size:0.9rem;">
                <span>Số điểm bạn muốn sử dụng:</span>
                <span style="font-size:0.8rem; color:var(--text-muted);">
                  Tối đa: <strong style="color:#ea580c;"><?= number_format($maxUsablePoints) ?></strong> điểm
                </span>
              </label>
              <div style="display:flex; gap:0.5rem; align-items:center;">
                <input type="number" 
                       name="points_to_use" 
                       id="points-to-use" 
                       class="form-control" 
                       min="0" 
                       max="<?= $maxUsablePoints ?>" 
                       step="10" 
                       placeholder="Nhập số điểm (VD: 100, 200...)" 
                       value="<?= escapeHtml($_POST['points_to_use'] ?? '') ?>"
                       style="font-weight:700; font-size:1.05rem; color:#ea580c;">
                <button type="button" id="btn-clear-points" class="btn" style="padding:0.6rem 0.9rem; white-space:nowrap; border-radius:8px; border:1px solid #cbd5e1; background:#f8fafc; font-weight:600; font-size:0.85rem; color:#64748b; cursor:pointer;">
                  ✕ Bỏ chọn
                </button>
              </div>
            </div>

            <!-- Quick selection chips -->
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.85rem;">
              <?php if ($maxUsablePoints >= 100): ?>
                <button type="button" class="btn-quick-points" data-points="100" style="padding:0.35rem 0.75rem; border-radius:6px; border:1px solid #fdba74; background:#fffaf0; font-size:0.82rem; font-weight:700; color:#c2410c; cursor:pointer;">
                  +100 điểm (-10K)
                </button>
              <?php endif; ?>
              <?php if ($maxUsablePoints >= 200): ?>
                <button type="button" class="btn-quick-points" data-points="200" style="padding:0.35rem 0.75rem; border-radius:6px; border:1px solid #fdba74; background:#fffaf0; font-size:0.82rem; font-weight:700; color:#c2410c; cursor:pointer;">
                  +200 điểm (-20K)
                </button>
              <?php endif; ?>
              <?php if ($maxUsablePoints >= 500): ?>
                <button type="button" class="btn-quick-points" data-points="500" style="padding:0.35rem 0.75rem; border-radius:6px; border:1px solid #fdba74; background:#fffaf0; font-size:0.82rem; font-weight:700; color:#c2410c; cursor:pointer;">
                  +500 điểm (-50K)
                </button>
              <?php endif; ?>
              <button type="button" class="btn-quick-points" data-points="<?= $maxUsablePoints ?>" style="padding:0.35rem 0.75rem; border-radius:6px; border:1px solid #ea580c; background:#ea580c; font-size:0.82rem; font-weight:700; color:#fff; cursor:pointer;">
                ⭐ Dùng tối đa (<?= number_format($maxUsablePoints) ?> điểm)
              </button>
            </div>

            <div id="points-feedback-msg" style="font-size:0.85rem; font-weight:600; min-height:1.25rem; margin-bottom:0.5rem;"></div>

            <p style="font-size:0.78rem; color:var(--text-muted); margin:0; line-height:1.4;">
              💡 <strong>Tỷ lệ quy đổi:</strong> 100 điểm = 10.000 VNĐ. Điểm sẽ được trừ trực tiếp vào giá trị thanh toán của đơn hàng.
            </p>
          <?php else: ?>
            <p style="font-size:0.85rem; color:#64748b; margin:0; line-height:1.5;">
              ℹ️ Bạn cần tối thiểu <strong><?= $minRedeem ?> điểm</strong> (tương đương <?= formatVND($minRedeem * $pointRate) ?>) để áp dụng giảm giá. Hãy tích lũy thêm điểm từ các đơn hàng tiếp theo nhé!
            </p>
            <input type="hidden" name="points_to_use" value="0">
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- 3. Phương thức thanh toán -->
      <div class="card" style="padding:1.5rem; background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.04);">
        <h3 style="font-size:1.2rem; font-weight:800; color:var(--text-main); margin-bottom:1.25rem;">
          💳 3. Phương Thức Thanh Toán
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
          <?php foreach ($cart as $cKey => $item): 
            $cImg = !empty($item['image']) ? $item['image'] : '';
            if (!$cImg) {
                $pId = (int)preg_replace('/\D/', '', $item['productId'] ?? $cKey);
                if ($pId > 0) {
                    try {
                        $cImg = $db->query("SELECT image_url FROM products WHERE id = $pId LIMIT 1")->fetchColumn() ?: '';
                    } catch (Exception $e) {}
                }
            }
            $cImg = getFoodImageUrl($cImg);
          ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; font-size:0.9rem; gap:0.75rem;">
              <div style="display:flex; align-items:center; gap:0.75rem;">
                <img src="<?= escapeHtml($cImg) ?>" alt="<?= escapeHtml($item['name']) ?>" style="width:44px; height:44px; border-radius:8px; object-fit:cover; border:1px solid #f1f5f9; box-shadow:0 1px 3px rgba(0,0,0,0.06);" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=160&auto=format&fit=crop&q=80';">
                <div>
                  <strong><?= escapeHtml($item['name']) ?></strong>
                  <span style="color:var(--text-muted);"> × <?= $item['qty'] ?></span>
                  <?php if (!empty($item['size']['name'])): ?>
                    <div style="font-size:0.75rem; color:#2563eb; font-weight:700; margin-top:2px;">
                      📏 Cỡ: <?= escapeHtml($item['size']['name']) ?><?= !empty($item['size']['price']) ? ' (+' . formatVND($item['size']['price']) . ')' : '' ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($item['toppings']) && is_array($item['toppings'])): ?>
                    <div style="font-size:0.75rem; color:#ea580c; font-weight:600; margin-top:2px;">
                      ✨ Topping: <?= implode(', ', array_map(function($t) { return escapeHtml($t['name']); }, $item['toppings'])) ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($item['attributes']) && is_array($item['attributes'])): ?>
                    <div style="font-size:0.72rem; color:#64748b; font-weight:500; margin-top:1px;">
                      🏷️ <?= implode(' • ', array_map(function($a) { return escapeHtml($a['name'] . ': ' . $a['value']); }, $item['attributes'])) ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
              <span style="font-weight:700; white-space:nowrap;"><?= formatVND((float)$item['price'] * (int)$item['qty']) ?></span>
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

        <?php if ($discount > 0): ?>
          <div class="summary-row" style="color:var(--success);">
            <span>Giảm giá khuyến mãi:</span>
            <span style="font-weight:700;">-<?= formatVND($discount) ?></span>
          </div>
        <?php endif; ?>

        <?php if ($appliedCoupon): ?>
          <div style="margin: 0.75rem 0; background: #fff7ed; border: 1.5px dashed #fb923c; border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.82rem;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <div>
                <span style="background:#ea580c; color:#fff; font-weight:800; font-size:0.75rem; padding:0.15rem 0.5rem; border-radius:4px; text-transform:uppercase;">
                  🎟️ <?= escapeHtml($appliedCoupon['code']) ?>
                </span>
                <span style="font-weight:700; color:#ea580c; margin-left:0.35rem;">-<?= formatVND($discount) ?></span>
              </div>
              <a href="<?= getUrl('cart.php') ?>" style="font-size:0.75rem; color:var(--text-muted); text-decoration:underline;">Thay đổi</a>
            </div>
            <div style="font-size:0.74rem; color:#9a3412; margin-top:0.25rem;">
              <?= escapeHtml($appliedCoupon['title']) ?>
            </div>
          </div>
        <?php endif; ?>

        <div id="points-discount-row" class="summary-row" style="color:#ea580c; display:none;">
          <span>Giảm giá từ điểm (<span id="points-used-text">0</span> điểm):</span>
          <span style="font-weight:700;" id="points-discount-display">-0đ</span>
        </div>

        <div class="summary-row total-row" style="border-top:1px solid var(--border-color); padding-top:1rem; margin-top:0.75rem;">
          <span style="font-size:1.1rem; font-weight:800;">Tổng thanh toán:</span>
          <span id="order-total-display" style="color:var(--primary-color); font-size:1.4rem; font-weight:800;"><?= formatVND($total) ?></span>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
  const cfg = {
    pointRate: <?= (float)$pointRate ?>,
    minRedeem: <?= (int)$minRedeem ?>,
    maxUsablePoints: <?= (int)$maxUsablePoints ?>,
    payableBeforePoints: <?= (float)$payableBeforePoints ?>
  };

  const pointsInput = document.getElementById('points-to-use');
  const clearBtn = document.getElementById('btn-clear-points');
  const quickBtns = document.querySelectorAll('.btn-quick-points');
  const discountRow = document.getElementById('points-discount-row');
  const pointsUsedText = document.getElementById('points-used-text');
  const discountDisplay = document.getElementById('points-discount-display');
  const totalDisplay = document.getElementById('order-total-display');
  const feedbackMsg = document.getElementById('points-feedback-msg');

  function formatVND(val) {
    return new Intl.NumberFormat('vi-VN').format(Math.round(val)) + 'đ';
  }

  function updatePreview() {
    if (!pointsInput) return;
    let valStr = pointsInput.value.trim();
    if (valStr === '') {
      resetPreview();
      return;
    }
    let pts = parseInt(valStr, 10);
    if (isNaN(pts) || pts <= 0) {
      resetPreview();
      return;
    }

    if (pts < cfg.minRedeem) {
      if (discountRow) discountRow.style.display = 'none';
      if (totalDisplay) totalDisplay.textContent = formatVND(cfg.payableBeforePoints);
      if (feedbackMsg) {
        feedbackMsg.style.color = '#dc2626';
        feedbackMsg.textContent = '⚠️ Tối thiểu cần ' + cfg.minRedeem + ' điểm để đổi giảm giá.';
      }
      return;
    }

    if (pts > cfg.maxUsablePoints) {
      if (discountRow) discountRow.style.display = 'none';
      if (totalDisplay) totalDisplay.textContent = formatVND(cfg.payableBeforePoints);
      if (feedbackMsg) {
        feedbackMsg.style.color = '#dc2626';
        feedbackMsg.textContent = '⚠️ Số điểm không được vượt quá ' + cfg.maxUsablePoints.toLocaleString('vi-VN') + ' điểm.';
      }
      return;
    }

    // Valid points
    let discountVnd = pts * cfg.pointRate;
    let netTotal = Math.max(0, cfg.payableBeforePoints - discountVnd);

    if (discountRow) discountRow.style.display = 'flex';
    if (pointsUsedText) pointsUsedText.textContent = pts.toLocaleString('vi-VN');
    if (discountDisplay) discountDisplay.textContent = '-' + formatVND(discountVnd);
    if (totalDisplay) totalDisplay.textContent = formatVND(netTotal);
    if (feedbackMsg) {
      feedbackMsg.style.color = '#16a34a';
      feedbackMsg.textContent = '✅ Áp dụng giảm ' + formatVND(discountVnd) + ' thành công!';
    }
  }

  function resetPreview() {
    if (discountRow) discountRow.style.display = 'none';
    if (totalDisplay) totalDisplay.textContent = formatVND(cfg.payableBeforePoints);
    if (feedbackMsg) {
      feedbackMsg.textContent = '';
    }
  }

  if (pointsInput) {
    pointsInput.addEventListener('input', updatePreview);
    pointsInput.addEventListener('change', updatePreview);
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', function() {
      if (pointsInput) {
        pointsInput.value = '';
        updatePreview();
      }
    });
  }

  quickBtns.forEach(function(btn) {
    btn.addEventListener('click', function() {
      let pts = parseInt(this.getAttribute('data-points'), 10);
      if (pointsInput && !isNaN(pts)) {
        pointsInput.value = pts;
        updatePreview();
      }
    });
  });

  // Initial trigger if POST validation previously returned with points_to_use
  if (pointsInput && pointsInput.value) {
    updatePreview();
  }

  // Intercept form submit if invalid points entered
  const form = document.querySelector('.checkout-layout');
  if (form && pointsInput) {
    form.addEventListener('submit', function(e) {
      let pts = parseInt(pointsInput.value.trim(), 10) || 0;
      if (pts > 0 && pts < cfg.minRedeem) {
        e.preventDefault();
        alert('Số điểm tối thiểu để sử dụng là ' + cfg.minRedeem + ' điểm. Vui lòng kiểm tra lại.');
        pointsInput.focus();
        return false;
      }
      if (pts > cfg.maxUsablePoints) {
        e.preventDefault();
        alert('Số điểm không được vượt quá mức tối đa ' + cfg.maxUsablePoints.toLocaleString('vi-VN') + ' điểm.');
        pointsInput.focus();
        return false;
      }
    });
  }
});
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

