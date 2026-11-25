<?php
/**
 * FASTFOOD - Lịch sử đơn hàng của tôi (Order History)
 */
require_once __DIR__ . '/config/config.php';
requireLogin();

$currentUser = currentUser();
$db = getDB();

$stmt = $db->prepare("SELECT o.*, 
                             (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
                      FROM orders o
                      WHERE o.customer_id = ?
                      ORDER BY o.id DESC");
$stmt->execute([$currentUser['id']]);
$orders = $stmt->fetchAll();

$pageTitle = 'Lịch Sử Đơn Hàng Của Bạn - FASTFOOD';
$activeNav = 'orders';
$extraCss  = ['css/pages.css'];

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= getUrl('index.php') ?>">Trang chủ</a> <span>/</span> <span>Lịch sử đơn hàng</span>
    </div>
    <h1 class="page-title">Đơn Hàng Của Bạn</h1>
  </div>
</div>

<main class="container" style="max-width:860px; margin:2.5rem auto 5rem;">
  <?php if (empty($orders)): ?>
    <div class="state-box" style="text-align:center; padding:4rem 1rem;">
      <div style="font-size:3.5rem; margin-bottom:1rem;">🛍️</div>
      <h3 class="state-title">Bạn chưa có đơn hàng nào</h3>
      <p class="state-desc" style="color:var(--text-muted); margin:0.5rem auto 1.5rem;">Hãy khám phá các món ăn thơm ngon và đặt bữa ăn đầu tiên cùng FASTFOOD nhé!</p>
      <a href="<?= getUrl('foods.php') ?>" class="btn btn-primary btn-lg">Khám Phá Món Ngon Ngay</a>
    </div>
  <?php else: ?>
    <div style="display:flex; flex-direction:column; gap:1.25rem;">
      <?php foreach ($orders as $ord): 
        $status = strtolower($ord['order_status']);
        $statusLabels = [
            'pending'          => ['Chờ xác nhận', '#f59e0b', '#fef3c7'],
            'awaiting_payment' => ['Chờ thanh toán', '#ea580c', '#ffedd5'],
            'preparing'        => ['Đang chuẩn bị', '#3b82f6', '#dbeafe'],
            'delivering'       => ['Đang giao hàng', '#8b5cf6', '#ede9fe'],
            'delivered'        => ['Đã giao thành công', '#10b981', '#d1fae5'],
            'completed'        => ['Đã giao thành công', '#10b981', '#d1fae5'],
            'cancelled'        => ['Đã hủy', '#ef4444', '#fee2e2'],
        ];
        $st = $statusLabels[$status] ?? ['Đang xử lý', '#6b7280', '#f3f4f6'];
      ?>
        <div class="card order-history-card" data-order-code="<?= escapeHtml($ord['order_code']) ?>" style="padding:1.5rem; background:#fff; border-radius:14px; box-shadow:0 3px 12px rgba(0,0,0,0.04); border:1px solid #f1f5f9;">
          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:1rem; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
            <div>
              <span style="font-weight:800; font-size:1.1rem; color:var(--text-main);">#<?= escapeHtml($ord['order_code']) ?></span>
              <span style="color:var(--text-muted); font-size:0.85rem; margin-left:0.5rem;">• <?= formatDateTimeVN($ord['created_at']) ?></span>
            </div>
            <span class="order-status-badge" style="background:<?= $st[2] ?>; color:<?= $st[1] ?>; padding:0.35rem 0.85rem; border-radius:999px; font-weight:700; font-size:0.85rem;">
              <?= $st[0] ?>
            </span>
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
              <p style="margin:0 0 0.35rem; color:var(--text-secondary); font-size:0.92rem;">
                📍 Địa chỉ: <strong><?= escapeHtml($ord['delivery_address']) ?></strong>
              </p>
              <p style="margin:0; color:var(--text-secondary); font-size:0.92rem;">
                💳 Hình thức: <strong><?= strtoupper($ord['payment_method']) ?></strong>
                <span class="order-payment-badge"><?= ($ord['payment_status'] === 'completed') ? '<span style="color:#10b981;">(Đã thanh toán)</span>' : '<span style="color:#ea580c;">(Chưa thanh toán)</span>' ?></span>
              </p>
            </div>

            <div style="text-align:right;">
              <div style="font-size:1.35rem; font-weight:900; color:var(--primary-color); margin-bottom:0.5rem;">
                <?= formatVND($ord['total_amount']) ?>
              </div>
              <div style="display:flex; gap:0.5rem;">
                <?php if ($status === 'awaiting_payment' && $ord['payment_method'] === 'banking'): ?>
                  <a href="<?= getUrl('payment.php?orderCode=' . urlencode($ord['order_code'])) ?>" class="btn btn-primary btn-sm">
                    Thanh toán ngay
                  </a>
                <?php endif; ?>
                <a href="<?= getUrl('order-tracking.php?id=' . urlencode($ord['order_code'])) ?>" class="btn btn-outline btn-sm">
                  Theo dõi tiến độ
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<script>
  (function() {
    const statusMap = {
      'pending': ['Chờ xác nhận', '#f59e0b', '#fef3c7'],
      'awaiting_payment': ['Chờ thanh toán', '#ea580c', '#ffedd5'],
      'preparing': ['Đang chuẩn bị', '#3b82f6', '#dbeafe'],
      'delivering': ['Đang giao hàng', '#8b5cf6', '#ede9fe'],
      'delivered': ['Đã giao thành công', '#10b981', '#d1fae5'],
      'completed': ['Đã giao thành công', '#10b981', '#d1fae5'],
      'cancelled': ['Đã hủy', '#ef4444', '#fee2e2']
    };

    function applyHistoryUpdate(d) {
      if (!d) return;
      const code = String(d.orderCode || d.id || d.orderNo || '').replace(/^#/, '');
      if (!code) return;
      const card = document.querySelector(`.order-history-card[data-order-code="${CSS.escape(code)}"]`);
      if (!card) return;

      const rawStatus = String(d.status || d.order_status || '').toLowerCase();
      if (rawStatus && statusMap[rawStatus]) {
        const [label, color, bg] = statusMap[rawStatus];
        const badge = card.querySelector('.order-status-badge');
        if (badge && badge.textContent.trim() !== label) {
          badge.textContent = label;
          badge.style.color = color;
          badge.style.background = bg;
          if (window.UIComponents?.showToast) {
            window.UIComponents.showToast(`Đơn #${code}: ${label}`, 'info');
          }
        }
      }

      if (d.paymentStatus === 'completed' || d.payment_status === 'completed') {
        const payEl = card.querySelector('.order-payment-badge');
        if (payEl) {
          payEl.innerHTML = '<span style="color:#10b981;">(Đã thanh toán)</span>';
        }
      }
    }

    window.addEventListener('fastfood:order-updated', (e) => applyHistoryUpdate(e.detail));
    window.addEventListener('fastfood:payment-updated', (e) => applyHistoryUpdate(e.detail));
    if (typeof BroadcastChannel !== 'undefined') {
      const ch = new BroadcastChannel('fastfood_realtime');
      ch.onmessage = (e) => {
        if (e.data?.type === 'order:updated' || e.data?.type === 'payment:updated') {
          applyHistoryUpdate(e.data.data || e.data);
        }
      };
    }
  })();
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

