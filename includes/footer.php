<?php
/**
 * FASTFOOD - Universal Footer Template
 */

// Lấy cấu hình chân trang từ database (website_content / footer_settings)
$footerRaw = null;
try {
    $db = getDB();
    $footerRaw = $db->query("SELECT content FROM website_content WHERE collection_name = 'footer_settings' LIMIT 1")->fetchColumn();
} catch (Exception $e) {}

$footerData = $footerRaw ? json_decode($footerRaw, true) : null;

$fBrandDesc    = $footerData['brand_desc'] ?? 'FASTFOOD Buôn Ma Thuột - Đặt món ngon địa phương, giao tận nơi nóng hổi và siêu tốc trong 20 phút.';
$fHotline      = $footerData['hotline'] ?? '1900 8888';
$fEmail        = $footerData['email'] ?? 'hotro@fastfood.vn';
$fMapUrl       = $footerData['map_url'] ?? 'https://www.google.com/maps/search/?api=1&query=Buon+Ma+Thuot%2C+Dak+Lak';
$fWorkingHours = $footerData['working_hours'] ?? '06:00 - 23:30 hàng ngày';
$fAddress      = $footerData['address'] ?? '16 Hoàng Diệu, P. Thắng Lợi, TP. Buôn Ma Thuột';
$fCopyright    = $footerData['copyright'] ?? ('© ' . date('Y') . ' FASTFOOD Buôn Ma Thuột. Dự án phát triển Website đặt đồ ăn trực tuyến chuẩn PHP MVC.');
$fExploreLinks = !empty($footerData['explore_links']) && is_array($footerData['explore_links']) ? $footerData['explore_links'] : [
    ['label' => 'Trang chủ FASTFOOD', 'url' => 'index.php'],
    ['label' => 'Thực đơn hôm nay', 'url' => 'foods.php'],
    ['label' => 'Món ăn bán chạy', 'url' => 'foods.php?sort=popular'],
    ['label' => 'Khuyến mãi & Combo', 'url' => 'foods.php?sort=price-asc']
];
$fPolicyLinks  = !empty($footerData['policy_links']) && is_array($footerData['policy_links']) ? $footerData['policy_links'] : [
    ['label' => 'Chính sách giao hàng', 'url' => 'policies.php'],
    ['label' => 'Quy định hủy đơn & phí ship', 'url' => 'policies.php#cancellation'],
    ['label' => 'Bảo mật thông tin khách hàng', 'url' => 'policies.php#privacy'],
    ['label' => 'Điều khoản dịch vụ', 'url' => 'policies.php#terms']
];
?>
  <!-- Universal Footer -->
  <footer class="site-footer" id="site-footer">
    <div class="container">
      <div class="footer-grid">
        <!-- Cột 1: Brand & Giới thiệu -->
        <div>
          <a href="<?= getUrl('index.php') ?>" class="footer-brand-logo" aria-label="FASTFOOD Buôn Ma Thuột - Trang chủ">
            <img src="<?= getAssetUrl('assets/images/logo-footer.svg') ?>" alt="FASTFOOD Buôn Ma Thuột" width="360" height="64">
          </a>
          <p class="footer-brand-desc">
            <?= escapeHtml($fBrandDesc) ?>
          </p>
          <div class="footer-quick-links" aria-label="Liên hệ nhanh">
            <a href="tel:<?= escapeHtml(preg_replace('/[^0-9+]/', '', $fHotline)) ?>" aria-label="Gọi hotline <?= escapeHtml($fHotline) ?>" title="Gọi hotline">
              <svg viewBox="0 0 24 24" aria-hidden="true" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M6.6 3.5 9 3l2 4.7-2 1.7a15 15 0 0 0 5.6 5.6l1.7-2 4.7 2-.5 2.4a3 3 0 0 1-3.3 2.4A17.5 17.5 0 0 1 4.2 6.8a3 3 0 0 1 2.4-3.3Z"/></svg>
            </a>
            <a href="mailto:<?= escapeHtml($fEmail) ?>" aria-label="Gửi email hỗ trợ" title="Gửi email">
              <svg viewBox="0 0 24 24" aria-hidden="true" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
            </a>
            <a href="<?= escapeHtml($fMapUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Xem trên bản đồ" title="Xem bản đồ">
              <svg viewBox="0 0 24 24" aria-hidden="true" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
            </a>
          </div>
        </div>

        <!-- Cột 2: Khám Phá -->
        <div>
          <h4 class="footer-col-title">Khám Phá</h4>
          <div class="footer-links">
            <?php foreach ($fExploreLinks as $link): ?>
              <?php $u = (strpos($link['url'], 'http') === 0) ? $link['url'] : getUrl($link['url']); ?>
              <a href="<?= escapeHtml($u) ?>" class="footer-link"><?= escapeHtml($link['label']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Cột 3: Chính Sách & Hỗ Trợ -->
        <div>
          <h4 class="footer-col-title">Chính Sách & Hỗ Trợ</h4>
          <div class="footer-links">
            <?php foreach ($fPolicyLinks as $link): ?>
              <?php $u = (strpos($link['url'], 'http') === 0) ? $link['url'] : getUrl($link['url']); ?>
              <a href="<?= escapeHtml($u) ?>" class="footer-link"><?= escapeHtml($link['label']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Cột 4: Tổng Đài Hỗ Trợ -->
        <div id="support-contact">
          <h4 class="footer-col-title">Tổng Đài Hỗ Trợ</h4>
          <div class="footer-contact-card">
            <span class="footer-hotline-label">Hotline Đặt Món (24/7):</span>
            <a href="tel:<?= escapeHtml(preg_replace('/[^0-9+]/', '', $fHotline)) ?>" class="footer-hotline-number"><?= escapeHtml($fHotline) ?></a>
            <p class="footer-hours">Giờ hoạt động: <?= escapeHtml($fWorkingHours) ?></p>
            <p class="footer-address"><?= escapeHtml($fAddress) ?></p>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <p><?= escapeHtml($fCopyright) ?></p>
      </div>
    </div>
  </footer>

  <!-- Tích hợp Khung Chat Nhân Viên Hỗ Trợ AI -->
  <?php require_once __DIR__ . '/chatbot.php'; ?>

  <?php
  // ===================================================================
  // MODAL SỔ RA THÔNG BÁO CHO NGƯỜI MỚI ĐĂNG KÝ / ĐĂNG NHẬP VOUCHER 50%
  // ===================================================================
  $welcomePopupData = $_SESSION['welcome_popup_data'] ?? null;
  $showWelcomeModal = !empty($_SESSION['show_welcome_popup']);
  if ($showWelcomeModal) {
      unset($_SESSION['show_welcome_popup']);
      unset($_SESSION['welcome_popup_data']);
  }
  ?>
  <?php if ($showWelcomeModal && $welcomePopupData): ?>
    <link rel="stylesheet" href="<?= getAssetUrl('css/auth.css') ?>">
    <div class="welcome-modal-overlay" id="welcome-voucher-modal">
      <div class="welcome-modal-card">
        <canvas id="welcome-confetti-canvas"></canvas>
        <button type="button" class="welcome-close-btn js-close-welcome-modal" aria-label="Đóng thông báo">✕</button>

        <div class="welcome-gift-wrap">
          <div class="welcome-glow-ring"></div>
          <div class="welcome-gift-circle">🎁</div>
        </div>

        <h2 class="welcome-title">Chào Mừng Bạn Mới!</h2>
        <p class="welcome-subtitle">
          Chúc mừng <span class="welcome-user-highlight"><?= escapeHtml($welcomePopupData['user_name'] ?? 'Bạn') ?></span> đã đăng ký tài khoản thành công! FASTFOOD thân tặng bạn phần quà đặc biệt:
        </p>

        <div class="welcome-ticket">
          <div class="welcome-ticket-val">
            <span class="welcome-percent-badge">GIẢM 50%</span>
            <span class="welcome-ticket-title">Voucher Bạn Mới</span>
          </div>
          <p class="welcome-ticket-desc">
            Giảm trực tiếp <strong>50%</strong> giá trị món ăn cho đơn hàng đầu tiên của bạn!
          </p>

          <div class="welcome-code-box">
            <span class="welcome-code-text" id="welcome-voucher-display"><?= escapeHtml($welcomePopupData['voucher_code'] ?? 'NEW50') ?></span>
            <button type="button" class="welcome-copy-btn" id="welcome-copy-code-btn" data-code="<?= escapeHtml($welcomePopupData['voucher_code'] ?? 'NEW50') ?>">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
              Sao Chép Mã
            </button>
          </div>

          <div class="welcome-applied-status">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <span>Đã tự động kích hoạt vào giỏ hàng của bạn!</span>
          </div>
        </div>

        <a href="<?= getUrl('foods.php') ?>" class="welcome-cta-btn">
          <span>Khám Phá Món Ăn & Dùng Mã Ngay</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
        </a>

        <button type="button" class="welcome-later-btn js-close-welcome-modal">
          Để sau, tôi muốn xem trang chủ trước
        </button>
      </div>
    </div>
    <script src="<?= getAssetUrl('js/auth.js') ?>"></script>
  <?php endif; ?>

  <!-- Scripts dùng chung -->
  <script src="<?= getAssetUrl('js/storage.js') ?>"></script>
  <script src="<?= getAssetUrl('js/components.js') ?>"></script>
  <script src="<?= getAssetUrl('js/realtime.js') ?>"></script>
  <script src="<?= getAssetUrl('js/chatbot.js') ?>"></script>
  <?php if (!empty($extraJs)): ?>
    <?php foreach ((array)$extraJs as $js): ?>
      <script src="<?= getAssetUrl($js) ?>"></script>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>

