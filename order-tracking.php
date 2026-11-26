<?php
/**
 * FASTFOOD - Theo dõi tiến độ đơn hàng (Order Tracking)
 */
require_once __DIR__ . '/config/config.php';

$orderCode = strtoupper(trim($_GET['id'] ?? $_GET['orderCode'] ?? ''));
if (!$orderCode) {
    header('Location: ' . getUrl('order-history.php'));
    exit;
}

$db = getDB();

// Xử lý hủy đơn nếu khách yêu cầu và đơn còn ở trạng thái chờ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_cancel'])) {
    $chkStmt = $db->prepare("SELECT id, customer_id, order_status FROM orders WHERE order_code = ? LIMIT 1");
    $chkStmt->execute([$orderCode]);
    $ordRow = $chkStmt->fetch();

    if ($ordRow && in_array(strtolower($ordRow['order_status']), ['placed', 'pending', 'awaiting_payment'])) {
        $cancelStmt = $db->prepare("UPDATE orders SET order_status = 'cancelled', cancellation_reason = 'Khách hàng tự hủy trên website', cancelled_at = NOW(), updated_at = NOW() WHERE id = ?");
        $cancelStmt->execute([$ordRow['id']]);

        $cancelRt = $db->prepare("UPDATE realtime_orders SET status = 'cancelled', updated_at = NOW() WHERE order_code = ?");
        $cancelRt->execute([$orderCode]);

        // Loyalty rollback: restore redeemed points
        require_once ROOT_PATH . '/helpers/loyalty.php';
        if (!empty($ordRow['customer_id']) && (int)$ordRow['customer_id'] > 0) {
            handleOrderCancellation((int)$ordRow['id']);
        }
        $_SESSION['cancelled_order_broadcast'] = $orderCode;
    }

    header('Location: ' . getUrl("order-tracking.php?id=" . urlencode($orderCode)));
    exit;
}

$stmt = $db->prepare("SELECT o.*, p.status AS realtime_payment_status 
                      FROM orders o
                      LEFT JOIN realtime_payments p ON o.order_code = p.order_code
                      WHERE o.order_code = ? LIMIT 1");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    die("Không tìm thấy đơn hàng $orderCode.");
}

// Lấy danh sách món trong đơn
$itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemStmt->execute([$order['id']]);
$items = $itemStmt->fetchAll();

// Kiểm tra xem đơn hàng đã được đánh giá chưa
$existingReview = null;
try {
    $revStmt = $db->prepare("SELECT id, rating, comment FROM reviews WHERE order_id = ? LIMIT 1");
    $revStmt->execute([(int)$order['id']]);
    $existingReview = $revStmt->fetch() ?: null;
} catch (Exception $e) {}

$pageTitle = "Theo dõi đơn hàng $orderCode - FASTFOOD";
$activeNav = 'orders';
$extraCss  = ['css/pages.css'];

$status = strtolower($order['order_status']);
$steps = [
    'pending'          => 1,
    'awaiting_payment' => 1,
    'preparing'        => 2,
    'delivering'       => 3,
    'delivered'        => 4,
    'completed'        => 4,
    'cancelled'        => 0,
];
$currentStep = $steps[$status] ?? 1;

require_once ROOT_PATH . '/includes/header.php';
?>

<style>
  .tracking-steps { display: flex; justify-content: space-between; position: relative; margin: 2.5rem 0 3rem; }
  .tracking-steps::before { content: ''; position: absolute; top: 20px; left: 10%; right: 10%; height: 4px; background: #e2e8f0; z-index: 1; }
  .tracking-step { position: relative; z-index: 2; text-align: center; flex: 1; }
  .tracking-dot { width: 42px; height: 42px; border-radius: 50%; background: #fff; border: 3px solid #cbd5e1; color: #64748b; display: flex; align-items: center; justify-content: center; font-weight: 800; margin: 0 auto 0.65rem; transition: all .3s; }
  .tracking-step.active .tracking-dot { background: var(--primary-color); border-color: var(--primary-color); color: #fff; box-shadow: 0 0 0 4px rgba(249,115,22,0.2); }
  .tracking-step.done .tracking-dot { background: #10b981; border-color: #10b981; color: #fff; }
  .tracking-step-label { font-size: 0.88rem; font-weight: 700; color: #475569; }
</style>

<!-- Page Header Bar -->
<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= getUrl('index.php') ?>">Trang chủ</a> <span>/</span> 
      <a href="<?= getUrl('order-history.php') ?>">Đơn hàng</a> <span>/</span> 
      <span>#<?= escapeHtml($orderCode) ?></span>
    </div>
    <h1 class="page-title">Tiến Độ Đơn Hàng #<?= escapeHtml($orderCode) ?></h1>
  </div>
</div>

<main class="container" style="max-width:860px; margin:2.5rem auto 5rem;">
  <!-- Card Trạng thái & Timeline -->
  <div class="card" style="padding:2rem; background:#fff; border-radius:16px; box-shadow:0 4px 18px rgba(0,0,0,0.04); margin-bottom:2rem;">
    <?php if ($status === 'cancelled'): ?>
      <div style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:1.25rem; border-radius:12px; text-align:center;">
        <h3 style="font-size:1.25rem; margin:0 0 0.35rem;">❌ Đơn hàng đã bị hủy</h3>
        <p style="margin:0; font-size:0.95rem;">Lý do: <?= escapeHtml($order['cancellation_reason'] ?: 'Khách hàng yêu cầu hủy') ?></p>
        <p style="font-size:0.8rem; margin:0.5rem 0 0; color:#b91c1c;">Lưu ý: Hủy đơn có thể khiến phí ship các đơn tiếp theo tăng thêm 3% theo chính sách vận hành.</p>
      </div>
    <?php else: ?>
      <div class="tracking-steps">
        <div class="tracking-step <?= $currentStep >= 1 ? ($currentStep > 1 ? 'done' : 'active') : '' ?>">
          <div class="tracking-dot"><?= $currentStep > 1 ? '✓' : '1' ?></div>
          <span class="tracking-step-label">Tiếp nhận đơn</span>
        </div>
        <div class="tracking-step <?= $currentStep >= 2 ? ($currentStep > 2 ? 'done' : 'active') : '' ?>">
          <div class="tracking-dot"><?= $currentStep > 2 ? '✓' : '2' ?></div>
          <span class="tracking-step-label">Bếp nấu món</span>
        </div>
        <div class="tracking-step <?= $currentStep >= 3 ? ($currentStep > 3 ? 'done' : 'active') : '' ?>">
          <div class="tracking-dot"><?= $currentStep > 3 ? '✓' : '3' ?></div>
          <span class="tracking-step-label">Đang giao hàng</span>
        </div>
        <div class="tracking-step <?= $currentStep >= 4 ? 'done' : '' ?>">
          <div class="tracking-dot"><?= $currentStep >= 4 ? '✓' : '4' ?></div>
          <span class="tracking-step-label">Giao thành công</span>
        </div>
      </div>

      <!-- Banner trạng thái Giao hàng thành công -->
      <div id="tracking-delivered-banner" style="<?= $currentStep >= 4 ? '' : 'display:none;' ?> background:#ecfdf5; border:1px solid #a7f3d0; border-radius:12px; padding:1rem 1.25rem; margin-bottom:1.25rem; display:<?= $currentStep >= 4 ? 'flex' : 'none' ?>; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem;">
        <div style="display:flex; align-items:center; gap:0.75rem;">
          <span style="font-size:1.6rem;">🎉</span>
          <div>
            <strong style="color:#065f46; font-size:1.05rem; display:block;">Giao hàng thành công</strong>
            <span style="color:#047857; font-size:0.88rem;">Đơn hàng #<?= escapeHtml($orderCode) ?> đã được giao đến bạn. Chúc bạn ngon miệng!</span>
          </div>
        </div>
        <button type="button" id="btn-open-review-modal" class="btn btn-primary btn-sm" style="white-space:nowrap;">
          <?= $existingReview ? '⭐ Xem / Sửa đánh giá (' . (int)$existingReview['rating'] . '★)' : '⭐ Đánh giá đơn hàng' ?>
        </button>
      </div>
    <?php endif; ?>

    <!-- Chi tiết thông tin giao nhận -->
    <div style="background:#f8fafc; border-radius:12px; padding:1.25rem; margin-top:1.5rem; display:grid; grid-template-columns:1fr 1fr; gap:1.25rem;">
      <div>
        <span style="color:#64748b; font-size:0.85rem; display:block; margin-bottom:3px;">Người nhận:</span>
        <strong style="color:#0f172a;"><?= escapeHtml($order['recipient_name']) ?> (<?= escapeHtml($order['recipient_phone']) ?>)</strong>
        <span style="color:#64748b; font-size:0.85rem; display:block; margin-top:0.75rem; margin-bottom:3px;">Địa chỉ giao hàng:</span>
        <strong style="color:#0f172a;"><?= escapeHtml($order['delivery_address']) ?></strong>
      </div>
      <div>
        <span style="color:#64748b; font-size:0.85rem; display:block; margin-bottom:3px;">Phương thức thanh toán:</span>
        <strong style="color:#0f172a;"><?= strtoupper($order['payment_method']) ?></strong>
        <span style="color:#64748b; font-size:0.85rem; display:block; margin-top:0.75rem; margin-bottom:3px;">Trạng thái thanh toán:</span>
        <div id="tracking-payment-status">
        <?php if ($order['payment_status'] === 'completed' || ($order['realtime_payment_status'] ?? '') === 'completed' || $currentStep >= 4): ?>
          <strong style="color:#10b981;">Đã thanh toán thành công</strong>
        <?php else: ?>
          <strong style="color:#ea580c;">Chưa thanh toán (Chờ thanh toán)</strong>
          <?php if ($order['payment_method'] === 'banking'): ?>
            <a href="<?= getUrl('payment.php?orderCode=' . urlencode($orderCode)) ?>" class="btn btn-primary btn-sm" style="margin-left:0.5rem; padding:0.2rem 0.6rem;">Quét QR thanh toán</a>
          <?php endif; ?>
        <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Danh sách món ăn trong đơn -->
  <div class="card" style="padding:2rem; background:#fff; border-radius:16px; box-shadow:0 4px 18px rgba(0,0,0,0.04);">
    <h3 style="font-size:1.15rem; font-weight:800; margin-bottom:1.25rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;">
      Chi Tiết Món Ăn
    </h3>

    <div>
      <?php foreach ($items as $it): 
        $itImg = '';
        if (!empty($it['product_id'])) {
            try {
                $itImg = $db->query("SELECT image_url FROM products WHERE id = " . (int)$it['product_id'] . " LIMIT 1")->fetchColumn() ?: '';
            } catch (Exception $e) {}
        }
        $itImg = getFoodImageUrl($itImg);
      ?>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem 0; border-bottom:1px dashed #f1f5f9; gap:0.75rem;">
          <div style="display:flex; align-items:center; gap:0.75rem;">
            <img src="<?= escapeHtml($itImg) ?>" alt="<?= escapeHtml($it['product_name']) ?>" style="width:48px; height:48px; border-radius:8px; object-fit:cover; border:1px solid #f1f5f9; box-shadow:0 1px 3px rgba(0,0,0,0.06);" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=160&auto=format&fit=crop&q=80';">
            <div>
              <strong style="font-size:0.95rem; display:block;"><?= escapeHtml($it['product_name']) ?></strong>
              <span style="color:#64748b; font-size:0.85rem;">Số lượng: <?= $it['quantity'] ?></span>
            </div>
          </div>
          <strong style="color:#0f172a;"><?= formatVND($it['subtotal']) ?></strong>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Tóm tắt số tiền & Điểm thưởng Loyalty -->
    <div style="margin-top:1.25rem; border-top:1px solid #e2e8f0; padding-top:1rem;">
      <div class="summary-row"><span>Tạm tính:</span><span><?= formatVND($order['subtotal']) ?></span></div>

      <?php if (!empty($order['discount_amount']) && (float)$order['discount_amount'] > 0): ?>
        <div class="summary-row" style="color:#16a34a;">
          <span>Giảm giá khuyến mãi / Voucher:</span>
          <span>-<?= formatVND($order['discount_amount']) ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($order['points_used']) && (int)$order['points_used'] > 0): ?>
        <div class="summary-row" style="color:#ea580c; font-weight:600;">
          <span>Giảm giá tích điểm (<?= number_format($order['points_used']) ?> điểm):</span>
          <span>-<?= formatVND($order['points_discount']) ?></span>
        </div>
      <?php endif; ?>

      <div class="summary-row"><span>Phí giao hàng:</span><span><?= formatVND($order['shipping_fee']) ?></span></div>

      <div class="summary-row total-row" style="font-size:1.3rem; font-weight:900; color:var(--primary-color); margin-top:0.5rem;">
        <span>Tổng cộng:</span>
        <span><?= formatVND($order['total_amount']) ?></span>
      </div>

      <?php if (!empty($order['points_earned']) && (int)$order['points_earned'] > 0): ?>
        <div style="margin-top:1rem; padding:0.75rem 1rem; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:10px; color:#065f46; display:flex; align-items:center; gap:0.6rem; font-size:0.92rem;">
          <span style="font-size:1.3rem;">🎁</span>
          <div>
            Đơn hàng đã tích lũy thành công <strong>+<?= number_format($order['points_earned']) ?> điểm thưởng</strong> vào tài khoản thành viên của bạn!
          </div>
        </div>
      <?php elseif ($status === 'cancelled' && !empty($order['points_used']) && (int)$order['points_used'] > 0): ?>
        <div style="margin-top:1rem; padding:0.75rem 1rem; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; color:#1e40af; display:flex; align-items:center; gap:0.6rem; font-size:0.92rem;">
          <span style="font-size:1.3rem;">🔄</span>
          <div>
            Đã hoàn trả <strong><?= number_format($order['points_used']) ?> điểm thưởng</strong> về tài khoản thành viên của bạn.
          </div>
        </div>
      <?php elseif ($status !== 'cancelled' && !empty($order['customer_id']) && (int)$order['customer_id'] > 0): ?>
        <?php
          require_once ROOT_PATH . '/helpers/loyalty.php';
          $uTier = 'BRONZE';
          try {
              $uRow = $db->query("SELECT member_tier FROM users WHERE id = " . (int)$order['customer_id'])->fetch();
              if ($uRow && !empty($uRow['member_tier'])) {
                  $uTier = $uRow['member_tier'];
              }
          } catch (Exception $e) {}
          $estPoints = calculateEarnPoints((float)$order['total_amount'], $uTier);
          if ($estPoints > 0):
        ?>
          <div style="margin-top:1rem; padding:0.75rem 1rem; background:#fffbeb; border:1px solid #fde68a; border-radius:10px; color:#92400e; display:flex; align-items:center; gap:0.6rem; font-size:0.88rem;">
            <span style="font-size:1.2rem;">✨</span>
            <div>
              Dự kiến tích lũy <strong>+<?= number_format($estPoints) ?> điểm</strong> sau khi đơn hàng giao thành công!
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Nút hủy đơn nếu còn ở trạng thái chờ -->
    <?php if (in_array($status, ['placed', 'pending', 'awaiting_payment'])): ?>
      <div id="tracking-cancel-wrap" style="margin-top:2rem; text-align:right;">
        <form action="<?= getUrl('order-tracking.php?id=' . urlencode($orderCode)) ?>" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn hủy đơn hàng này? Theo quy định, mỗi lần hủy đơn có thể tăng 3% phí ship cho đơn sau.')">
          <input type="hidden" name="action_cancel" value="1">
          <button type="submit" class="btn btn-outline text-danger btn-sm">
            Hủy đơn hàng này
          </button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- Pop-up Đánh Giá Đơn Hàng khi Giao hàng thành công -->
<div id="order-review-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.55); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1rem;">
  <div style="background:#fff; width:100%; max-width:460px; border-radius:18px; padding:1.75rem; box-shadow:0 20px 50px rgba(0,0,0,0.2); position:relative; animation:fadeIn .2s ease;">
    <button type="button" id="btn-close-review-modal" aria-label="Đóng" style="position:absolute; top:14px; right:14px; width:32px; height:32px; border-radius:50%; border:none; background:#f1f5f9; color:#475569; font-size:1.1rem; cursor:pointer;">✕</button>
    <div style="text-align:center; margin-bottom:1.25rem;">
      <div style="font-size:2.4rem; margin-bottom:0.35rem;">🎉</div>
      <h3 style="margin:0 0 0.35rem; font-size:1.25rem; font-weight:800; color:#0f172a;">Giao hàng thành công!</h3>
      <p style="margin:0; color:#64748b; font-size:0.9rem;">Hãy chia sẻ cảm nhận của bạn về đơn hàng <strong>#<?= escapeHtml($orderCode) ?></strong></p>
    </div>

    <form id="order-review-form">
      <div style="text-align:center; margin-bottom:1.1rem;">
        <div id="review-stars-picker" style="display:inline-flex; gap:0.45rem; font-size:2rem; cursor:pointer; user-select:none;">
          <?php $initRating = $existingReview ? (int)$existingReview['rating'] : 5; ?>
          <?php for ($s = 1; $s <= 5; $s++): ?>
            <span class="review-star-item" data-star="<?= $s ?>" style="color:<?= $s <= $initRating ? '#f59e0b' : '#cbd5e1' ?>; transition:transform .15s;">★</span>
          <?php endfor; ?>
        </div>
        <div id="review-rating-label" style="font-size:0.85rem; font-weight:700; color:#ea580c; margin-top:0.25rem;">Tuyệt vời (5/5 sao)</div>
        <input type="hidden" id="review-rating-input" value="<?= $initRating ?>">
      </div>

      <div style="margin-bottom:1.1rem;">
        <label for="review-comment-input" style="display:block; font-size:0.88rem; font-weight:700; color:#334155; margin-bottom:0.4rem;">Nhận xét của bạn:</label>
        <textarea id="review-comment-input" rows="3" placeholder="Món ăn nóng hổi, giao hàng nhanh chóng..." style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:10px; font-family:inherit; font-size:0.92rem; resize:vertical;"><?= escapeHtml($existingReview['comment'] ?? '') ?></textarea>
      </div>

      <div style="display:flex; gap:0.65rem; justify-content:flex-end;">
        <button type="button" id="btn-skip-review-modal" class="btn btn-outline btn-sm">Để sau</button>
        <button type="submit" id="btn-submit-review" class="btn btn-primary btn-sm">Gửi đánh giá</button>
      </div>
    </form>
  </div>
</div>

<script>
  window.TRACKED_ORDER_CODE = '<?= escapeHtml($orderCode) ?>';

  (function() {
    const channel = (typeof BroadcastChannel !== 'undefined') ? new BroadcastChannel('fastfood_realtime') : null;

    // 1. Nếu vừa đặt hàng xong (chuyển hướng từ checkout), lập tức báo sang Tab Admin
    <?php if (!empty($_GET['just_ordered'])): ?>
    if (channel) {
      const orderPayload = {
        order: {
          id: '<?= escapeHtml($order['order_code']) ?>',
          orderNo: '#<?= escapeHtml($order['order_code']) ?>',
          orderCode: '<?= escapeHtml($order['order_code']) ?>',
          customer: '<?= escapeHtml($order['recipient_name']) ?>',
          recipient: {
            name: '<?= escapeHtml($order['recipient_name']) ?>',
            phone: '<?= escapeHtml($order['recipient_phone']) ?>',
            address: '<?= escapeHtml($order['delivery_address']) ?>'
          },
          total: <?= (float)$order['total_amount'] ?>,
          paymentMethod: '<?= escapeHtml($order['payment_method']) ?>',
          paymentStatus: '<?= escapeHtml($order['payment_status']) ?>',
          status: '<?= escapeHtml($order['order_status']) ?>',
          createdAt: '<?= date('c') ?>'
        }
      };
      channel.postMessage({ type: 'order:new', data: orderPayload });
    }
    <?php endif; ?>

    <?php
    $cancelledBroadcast = $_SESSION['cancelled_order_broadcast'] ?? '';
    if ($cancelledBroadcast) {
        unset($_SESSION['cancelled_order_broadcast']);
    }
    ?>
    <?php if ($cancelledBroadcast === $orderCode): ?>
    if (channel) {
      channel.postMessage({
        type: 'order:updated',
        data: {
          id: '<?= escapeHtml($orderCode) ?>',
          orderCode: '<?= escapeHtml($orderCode) ?>',
          orderNo: '#<?= escapeHtml($orderCode) ?>',
          status: 'cancelled',
          order_status: 'cancelled'
        }
      });
    }
    <?php endif; ?>

    // 2. Hàm cập nhật trực quan tiến độ bước nhảy Realtime
    const stepMap = {
      'pending': 1,
      'awaiting_payment': 1,
      'preparing': 2,
      'delivering': 3,
      'delivered': 4,
      'completed': 4,
      'cancelled': 0
    };

    let currentStepVal = <?= (int)$currentStep ?>;

    function applyTrackingUpdate(newStatus, newPaymentStatus) {
      if (newPaymentStatus === 'completed') {
        const payEl = document.getElementById('tracking-payment-status');
        if (payEl) {
          payEl.innerHTML = '<strong style="color:#10b981;">Đã thanh toán thành công</strong>';
        }
      }
      if (!newStatus) return;
      const clean = String(newStatus).toLowerCase();
      if (clean === 'cancelled') {
        window.location.reload();
        return;
      }
      const targetStep = stepMap[clean] || 1;
      if (targetStep >= 2) {
        const cancelWrap = document.getElementById('tracking-cancel-wrap');
        if (cancelWrap) cancelWrap.style.display = 'none';
      }
      if (targetStep >= 4) {
        const deliveredBanner = document.getElementById('tracking-delivered-banner');
        if (deliveredBanner) deliveredBanner.style.display = 'flex';
        const payEl = document.getElementById('tracking-payment-status');
        if (payEl) payEl.innerHTML = '<strong style="color:#10b981;">Đã thanh toán thành công</strong>';
        if (!window.__orderReviewed && !window.__reviewModalOpenedOnce) {
          window.__reviewModalOpenedOnce = true;
          setTimeout(() => openReviewModal(), 350);
        }
      }
      if (targetStep === currentStepVal) return;
      currentStepVal = targetStep;

      const steps = document.querySelectorAll('.tracking-step');
      steps.forEach((el, idx) => {
        const stepNum = idx + 1;
        const dot = el.querySelector('.tracking-dot');
        el.classList.remove('active', 'done');
        if (targetStep > stepNum) {
          el.classList.add('done');
          if (dot) dot.textContent = '✓';
        } else if (targetStep === stepNum) {
          if (targetStep === 4) {
            el.classList.add('done');
            if (dot) dot.textContent = '✓';
          } else {
            el.classList.add('active');
            if (dot) dot.textContent = String(stepNum);
          }
        } else {
          if (dot) dot.textContent = String(stepNum);
        }
      });

      if (window.UIComponents?.showToast) {
        const statusLabels = {
          'preparing': '🍳 Bếp đã xác nhận và bắt đầu nấu món ăn!',
          'delivering': '🛵 Shipper đang trên đường giao hàng đến bạn!',
          'delivered': '🎉 Đơn hàng đã được giao thành công! Chúc bạn ngon miệng!'
        };
        window.UIComponents.showToast(statusLabels[clean] || 'Trạng thái đơn hàng vừa được cập nhật!', 'info');
      }
    }

    // 3. Lắng nghe qua BroadcastChannel & CustomEvent
    if (channel) {
      channel.onmessage = function(e) {
        if (!e.data) return;
        if (e.data.type === 'order:updated') {
          const d = e.data.data || e.data;
          const code = String(d.id || d.orderCode || d.orderNo || '').replace(/^#/, '');
          if (code === window.TRACKED_ORDER_CODE) {
            applyTrackingUpdate(d.status || d.order_status, d.paymentStatus || d.payment_status);
          }
        } else if (e.data.type === 'payment:updated') {
          const d = e.data.data || e.data;
          const code = String(d.orderCode || d.id || '').replace(/^#/, '');
          if (code === window.TRACKED_ORDER_CODE) {
            applyTrackingUpdate(null, d.paymentStatus);
          }
        } else if (e.data.type === 'content:updated' && e.data.collection === 'orders') {
          checkOrderStatus();
        }
      };
    }

    window.addEventListener('fastfood:order-updated', function(e) {
      const d = e.detail;
      if (!d) return;
      const code = String(d.id || d.orderCode || d.orderNo || '').replace(/^#/, '');
      if (code === window.TRACKED_ORDER_CODE) {
        applyTrackingUpdate(d.status || d.order_status, d.paymentStatus || d.payment_status);
      }
    });

    // 4. Polling chủ động tốc độ cao (1.5s/lần) đảm bảo cập nhật ngay cả khi khác trình duyệt/thiết bị
    async function checkOrderStatus() {
      if (!window.TRACKED_ORDER_CODE) return;
      try {
        const pollUrl = (typeof window.getApiUrl === 'function')
          ? window.getApiUrl('api/realtime.php')
          : ((window.BASE_URL || '').replace(/\/+$/, '') + '/api/realtime.php');
        const res = await fetch(`${pollUrl}?action=poll&orderCode=${encodeURIComponent(window.TRACKED_ORDER_CODE)}`);
        const json = await res.json();
        if (json.success && json.data && json.data.trackedOrder) {
          const ord = json.data.trackedOrder;
          applyTrackingUpdate(ord.order_status, ord.payment_status);
        }
      } catch (e) {}
    }

    const trackingTimer = setInterval(checkOrderStatus, 1500);

    // 5. Logic Pop-up Đánh Giá Đơn Hàng
    window.__orderReviewed = <?= $existingReview ? 'true' : 'false' ?>;
    window.__reviewModalOpenedOnce = false;
    const reviewModal = document.getElementById('order-review-modal');
    const starLabels = { 1: 'Rất tệ (1/5 sao)', 2: 'Không hài lòng (2/5 sao)', 3: 'Bình thường (3/5 sao)', 4: 'Hài lòng (4/5 sao)', 5: 'Tuyệt vời (5/5 sao)' };

    function openReviewModal() {
      if (reviewModal) reviewModal.style.display = 'flex';
    }
    function closeReviewModal() {
      if (reviewModal) reviewModal.style.display = 'none';
    }
    function setStars(val) {
      const n = Math.max(1, Math.min(5, Number(val) || 5));
      document.getElementById('review-rating-input').value = String(n);
      document.getElementById('review-rating-label').textContent = starLabels[n] || '';
      document.querySelectorAll('.review-star-item').forEach(el => {
        const s = Number(el.dataset.star);
        el.style.color = s <= n ? '#f59e0b' : '#cbd5e1';
      });
    }

    document.querySelectorAll('.review-star-item').forEach(el => {
      el.addEventListener('click', () => setStars(el.dataset.star));
    });
    document.getElementById('btn-open-review-modal')?.addEventListener('click', openReviewModal);
    document.getElementById('btn-close-review-modal')?.addEventListener('click', closeReviewModal);
    document.getElementById('btn-skip-review-modal')?.addEventListener('click', closeReviewModal);
    reviewModal?.addEventListener('click', (e) => { if (e.target === reviewModal) closeReviewModal(); });

    setStars(document.getElementById('review-rating-input')?.value || 5);
    if (currentStepVal >= 4 && !window.__orderReviewed) {
      window.__reviewModalOpenedOnce = true;
      setTimeout(openReviewModal, 400);
    }

    document.getElementById('order-review-form')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('btn-submit-review');
      const rating = Number(document.getElementById('review-rating-input').value || 5);
      const comment = document.getElementById('review-comment-input').value.trim();
      btn.disabled = true;
      btn.textContent = 'Đang gửi...';
      try {
        const apiUrl = (typeof window.getApiUrl === 'function')
          ? window.getApiUrl('api/realtime.php')
          : ((window.BASE_URL || '').replace(/\/+$/, '') + '/api/realtime.php');
        const res = await fetch(apiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'submit_review',
            orderCode: window.TRACKED_ORDER_CODE,
            rating,
            comment
          })
        });
        const json = await res.json();
        if (json.success) {
          window.__orderReviewed = true;
          closeReviewModal();
          const openBtn = document.getElementById('btn-open-review-modal');
          if (openBtn) openBtn.textContent = `⭐ Xem / Sửa đánh giá (${rating}★)`;
          if (channel && json.data?.review) {
            channel.postMessage({ type: 'review:new', data: json.data.review });
          }
          if (window.UIComponents?.showToast) {
            window.UIComponents.showToast('Cảm ơn bạn đã đánh giá đơn hàng!', 'success');
          }
        } else {
          alert(json.message || 'Không thể gửi đánh giá.');
        }
      } catch (err) {
        alert('Lỗi kết nối: ' + err.message);
      } finally {
        btn.disabled = false;
        btn.textContent = 'Gửi đánh giá';
      }
    });

  })();
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

