<?php
/**
 * FASTFOOD - Quét mã thanh toán VietQR / SePay (Payment)
 */
require_once __DIR__ . '/config/config.php';

$orderCode = strtoupper(trim($_GET['orderCode'] ?? ''));
if (!$orderCode) {
    header('Location: ' . getUrl('order-history.php'));
    exit;
}

$db = getDB();

// Xử lý chuyển sang COD nếu khách yêu cầu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_to_cod'])) {
    $upStmt = $db->prepare("UPDATE orders SET payment_method = 'cod', payment_status = 'pending', order_status = 'pending' WHERE order_code = ?");
    $upStmt->execute([$orderCode]);
    $upRt = $db->prepare("UPDATE realtime_orders SET status = 'pending' WHERE order_code = ?");
    $upRt->execute([$orderCode]);
    header('Location: ' . getUrl("order-tracking.php?id=" . urlencode($orderCode)));
    exit;
}

// Xử lý xác nhận khách đã hoàn tất chuyển tiền (hỗ trợ tức thì cho VietQR & MoMo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_paid'])) {
    $upStmt = $db->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'placed' WHERE order_code = ?");
    $upStmt->execute([$orderCode]);
    $upRt = $db->prepare("UPDATE realtime_orders SET status = 'pending' WHERE order_code = ?");
    $upRt->execute([$orderCode]);
    $upRp = $db->prepare("UPDATE realtime_payments SET status = 'completed', paid_at = NOW() WHERE order_code = ?");
    $upRp->execute([$orderCode]);
    header('Location: ' . getUrl("order-tracking.php?id=" . urlencode($orderCode)));
    exit;
}

$stmt = $db->prepare("SELECT o.*, p.qr_url, p.status AS realtime_payment_status, p.expires_at 
                      FROM orders o
                      LEFT JOIN realtime_payments p ON o.order_code = p.order_code
                      WHERE o.order_code = ? LIMIT 1");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    die("Không tìm thấy thông tin đơn hàng $orderCode.");
}

$isPaid = ($order['payment_status'] === 'completed' || ($order['realtime_payment_status'] ?? '') === 'completed');

if ($isPaid) {
    header('Location: ' . getUrl("order-tracking.php?id=" . urlencode($orderCode)));
    exit;
}

$pageTitle = "Thanh toán đơn hàng $orderCode - FASTFOOD";
$activeNav = 'orders';
$extraCss  = ['css/pages.css'];

$qrUrl = $order['qr_url'];
if (!$qrUrl) {
    $qrUrl = "https://qr.sepay.vn/img?acc=" . SEPAY_ACCOUNT_NUMBER . "&bank=" . SEPAY_BANK_CODE . "&amount=" . (float)$order['total_amount'] . "&des=" . $orderCode . "&template=compact";
}

require_once ROOT_PATH . '/includes/header.php';
?>

<style>
  .payment-page { max-width: 620px; margin: 3rem auto; padding: 1.5rem; }
  .payment-panel { padding: 2.2rem; border-radius: 18px; background: #fff; box-shadow: 0 12px 40px rgba(0,0,0,0.06); text-align: center; }
  .payment-panel h1 { margin: 0 0 .5rem; font-size: 1.5rem; font-weight: 800; }
  .payment-order-code { color: var(--text-muted); font-size: 0.95rem; font-weight: 700; margin-bottom: 0.5rem; }
  .payment-amount { margin: 0.5rem 0 1rem; color: var(--primary-color); font-size: 2.2rem; font-weight: 900; }
  .payment-status-text { margin: 0.5rem 0; color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5; }
  .payment-countdown { color: #ea580c; font-weight: 700; font-size: 0.95rem; margin-bottom: 1.25rem; }
  .payment-qr { display: block; width: min(300px, 100%); margin: 1.25rem auto; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 4px 14px rgba(0,0,0,0.05); }
  .payment-details-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.1rem 1.25rem; margin: 1.25rem 0; text-align: left; font-size: 0.92rem; }
  .payment-details-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px dashed #e2e8f0; }
  .payment-details-row:last-child { border-bottom: none; }
  .payment-actions { display: flex; justify-content: center; gap: 0.85rem; flex-wrap: wrap; margin-top: 1.5rem; }
</style>

<main class="container payment-page">
  <section class="payment-panel">
    <?php $isMomo = ($order['payment_method'] === 'momo'); ?>
    <h1><?= $isMomo ? 'Thanh Toán Ví MoMo' : 'Thanh Toán Đơn Hàng' ?></h1>
    <div class="payment-order-code">Mã đơn: <strong><?= escapeHtml($orderCode) ?></strong></div>
    <div class="payment-amount"><?= formatVND($order['total_amount']) ?></div>
    
    <p class="payment-status-text" id="payment-status-desc">
      <?= $isMomo ? 'Mở App MoMo hoặc App Ngân hàng quét mã VietQR bên dưới để thanh toán.' : 'Mở App Ngân hàng bất kỳ hoặc App MoMo quét mã VietQR bên dưới để thanh toán.' ?>
    </p>

    <div class="payment-countdown" id="payment-countdown">
      Thời gian thanh toán còn <span id="timer">14:59</span>
    </div>

    <!-- VietQR Image -->
    <img src="<?= escapeHtml($qrUrl) ?>" alt="Mã VietQR thanh toán SePay" class="payment-qr">

    <!-- Thông tin chuyển khoản chi tiết -->
    <div class="payment-details-box">
      <div class="payment-details-row">
        <span style="color:#64748b;">Kênh thanh toán:</span>
        <strong style="color:<?= $isMomo ? '#a50064' : '#0f172a' ?>; font-weight:700;"><?= $isMomo ? 'Ví MoMo / VietQR' : SEPAY_BANK_CODE ?></strong>
      </div>
      <div class="payment-details-row">
        <span style="color:#64748b;"><?= $isMomo ? 'STK / Ví MoMo:' : 'Số tài khoản:' ?></span>
        <strong style="color:#0f172a; font-size:1.05rem; font-weight:700; letter-spacing:0.5px;"><?= SEPAY_ACCOUNT_NUMBER ?></strong>
      </div>
      <div class="payment-details-row">
        <span style="color:#64748b;">Chủ tài khoản:</span>
        <strong style="color:#0f172a; font-weight:700;"><?= SEPAY_ACCOUNT_NAME ?></strong>
      </div>
      <div class="payment-details-row">
        <span style="color:#64748b;">Số tiền:</span>
        <strong style="color:#ea580c; font-size:1.15rem; font-weight:800;"><?= formatVND($order['total_amount']) ?></strong>
      </div>
      <div class="payment-details-row">
        <span style="color:#64748b;">Nội dung CK:</span>
        <strong style="color:#ea580c; font-size:1.15rem; font-weight:800; background:#ffedd5; padding:2px 8px; border-radius:6px; letter-spacing:0.5px;"><?= escapeHtml($orderCode) ?></strong>
      </div>
    </div>

    <!-- Hướng dẫn tự động 24/7 -->
    <div style="padding:0.75rem 1rem; border-radius:10px; background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; font-size:0.85rem; text-align:left; line-height:1.5;">
      <div style="font-weight:700; margin-bottom:3px; display:flex; align-items:center; gap:5px;">
        <span>⚡</span> Tự động duyệt đơn 24/7 qua SePay / VietQR
      </div>
      <div>Hệ thống sẽ <strong>tự động chuyển trạng thái ngay khi nhận được tiền</strong> (khoảng 5 - 30 giây). Bạn không cần gửi biên lai hay hình ảnh chuyển khoản.</div>
    </div>

    <!-- Action Buttons -->
    <div class="payment-actions">
      <form action="<?= getUrl("payment.php?orderCode=" . urlencode($orderCode)) ?>" method="POST" style="margin:0;">
        <input type="hidden" name="confirm_paid" value="1">
        <button type="submit" class="btn btn-primary" style="background:#16a34a; border-color:#16a34a;">
          ✅ Tôi đã hoàn tất chuyển tiền
        </button>
      </form>

      <button type="button" class="btn btn-primary" id="btn-check-payment" onclick="checkPaymentStatus()">
        🔄 Kiểm tra giao dịch
      </button>

      <form action="<?= getUrl("payment.php?orderCode=" . urlencode($orderCode)) ?>" method="POST" style="margin:0;">
        <input type="hidden" name="switch_to_cod" value="1">
        <button type="submit" class="btn btn-outline" onclick="return confirm('Bạn có muốn đổi đơn hàng này sang thanh toán tiền mặt (COD)?')">
          Đổi sang COD
        </button>
      </form>
    </div>
  </section>
</main>

<script>
const orderCode = '<?= escapeHtml($orderCode) ?>';
let totalSeconds = 15 * 60;

// Báo sang Tab Admin qua BroadcastChannel nếu vừa đặt xong
<?php if (!empty($_GET['just_ordered'])): ?>
try {
  const channel = new BroadcastChannel('fastfood_realtime');
  channel.postMessage({
    type: 'order:new',
    data: {
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
        paymentStatus: 'pending',
        status: '<?= escapeHtml($order['order_status']) ?>',
        createdAt: '<?= date('c') ?>'
      }
    }
  });
} catch (e) {}
<?php endif; ?>

// Countdown Timer
const timerInterval = setInterval(() => {
  totalSeconds--;
  if (totalSeconds <= 0) {
    clearInterval(timerInterval);
    document.getElementById('timer').textContent = 'Hết hạn';
    return;
  }
  const m = Math.floor(totalSeconds / 60);
  const s = totalSeconds % 60;
  document.getElementById('timer').textContent = 
    `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}, 1000);

// Kiểm tra trạng thái thanh toán thủ công hoặc định kỳ
async function checkPaymentStatus(isManual = false) {
  const btn = document.getElementById('btn-check-payment');
  if (isManual && btn) {
    btn.disabled = true;
    btn.textContent = 'Đang kiểm tra...';
  }

  try {
    const res = await fetch(`<?= getUrl('api/payment-status.php') ?>?orderCode=${encodeURIComponent(orderCode)}`);
    const result = await res.json();
    if (result.success && result.data?.paymentStatus === 'completed') {
      clearInterval(timerInterval);
      try {
        const ch = new BroadcastChannel('fastfood_realtime');
        ch.postMessage({ type: 'payment:updated', data: { orderCode: orderCode, paymentStatus: 'completed' } });
        ch.postMessage({ type: 'order:updated', data: { id: orderCode, orderCode: orderCode, orderNo: '#' + orderCode, status: 'preparing', paymentStatus: 'completed' } });
        ch.postMessage({ type: 'content:updated', collection: 'vouchers' });
      } catch (e) {}
      try { localStorage.setItem('ff_vouchers_dirty', String(Date.now())); } catch (e) {}
      document.getElementById('payment-status-desc').innerHTML = 
        '<strong style="color:var(--success); font-size:1.1rem;">🎉 Thanh toán thành công! Đang chuyển đến đơn hàng...</strong>';
      setTimeout(() => {
        window.location.href = `<?= getUrl('order-tracking.php') ?>?id=${encodeURIComponent(orderCode)}`;
      }, 1200);
      return;
    }

    if (isManual) {
      if (window.UIComponents?.showToast) {
        window.UIComponents.showToast('Hệ thống chưa ghi nhận tiền vào tài khoản. Nếu bạn vừa chuyển, vui lòng đợi 5 - 30 giây để SePay xử lý.', 'info');
      } else {
        alert('Hệ thống chưa nhận được tiền từ ngân hàng. Vui lòng đợi 5 - 30 giây để hệ thống đối soát.');
      }
    }
  } catch (err) {
    console.error(err);
  } finally {
    if (isManual && btn) {
      btn.disabled = false;
      btn.textContent = '🔄 Kiểm tra giao dịch';
    }
  }
}

// Bắt sự kiện click nút kiểm tra
document.getElementById('btn-check-payment')?.addEventListener('click', () => checkPaymentStatus(true));

// Tự động Polling kiểm tra mỗi 5 giây
setInterval(() => checkPaymentStatus(false), 5000);
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

