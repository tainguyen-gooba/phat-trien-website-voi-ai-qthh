<?php
/**
 * FASTFOOD - Universal Footer Template
 */
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
            FASTFOOD Buôn Ma Thuột - Đặt món ngon địa phương, giao tận nơi nóng hổi và siêu tốc trong 20 phút.
          </p>
          <div class="footer-quick-links" aria-label="Liên hệ nhanh">
            <a href="tel:19008888" aria-label="Gọi hotline 1900 8888" title="Gọi hotline">
              <svg viewBox="0 0 24 24" aria-hidden="true" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M6.6 3.5 9 3l2 4.7-2 1.7a15 15 0 0 0 5.6 5.6l1.7-2 4.7 2-.5 2.4a3 3 0 0 1-3.3 2.4A17.5 17.5 0 0 1 4.2 6.8a3 3 0 0 1 2.4-3.3Z"/></svg>
            </a>
            <a href="mailto:hotro@fastfood.vn" aria-label="Gửi email hỗ trợ" title="Gửi email">
              <svg viewBox="0 0 24 24" aria-hidden="true" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
            </a>
            <a href="https://www.google.com/maps/search/?api=1&query=Buon+Ma+Thuot%2C+Dak+Lak" target="_blank" rel="noopener noreferrer" aria-label="Xem Buôn Ma Thuột trên bản đồ" title="Xem bản đồ">
              <svg viewBox="0 0 24 24" aria-hidden="true" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
            </a>
          </div>
        </div>

        <!-- Cột 2: Khám Phá -->
        <div>
          <h4 class="footer-col-title">Khám Phá</h4>
          <div class="footer-links">
            <a href="<?= getUrl('index.php') ?>" class="footer-link">Trang chủ FASTFOOD</a>
            <a href="<?= getUrl('foods.php') ?>" class="footer-link">Thực đơn hôm nay</a>
            <a href="<?= getUrl('restaurants.php') ?>" class="footer-link">Quán ăn đối tác</a>
            <a href="<?= getUrl('foods.php?filter=deals') ?>" class="footer-link">Khuyến mãi & Combo</a>
          </div>
        </div>

        <!-- Cột 3: Chính Sách & Hỗ Trợ -->
        <div>
          <h4 class="footer-col-title">Chính Sách & Hỗ Trợ</h4>
          <div class="footer-links">
            <a href="<?= getUrl('policies.php') ?>" class="footer-link">Chính sách giao hàng</a>
            <a href="<?= getUrl('policies.php#cancellation') ?>" class="footer-link">Quy định hủy đơn & phí ship</a>
            <a href="<?= getUrl('policies.php#privacy') ?>" class="footer-link">Bảo mật thông tin khách hàng</a>
            <a href="<?= getUrl('policies.php#terms') ?>" class="footer-link">Điều khoản dịch vụ</a>
          </div>
        </div>

        <!-- Cột 4: Tổng Đài Hỗ Trợ -->
        <div id="support-contact">
          <h4 class="footer-col-title">Tổng Đài Hỗ Trợ</h4>
          <div class="footer-contact-card">
            <span class="footer-hotline-label">Hotline Đặt Món (24/7):</span>
            <a href="tel:19008888" class="footer-hotline-number">1900 8888</a>
            <p class="footer-hours">Giờ hoạt động: 06:00 - 23:30 hàng ngày</p>
            <p class="footer-address">16 Hoàng Diệu, P. Thắng Lợi, TP. Buôn Ma Thuột</p>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <p>© <?= date('Y') ?> FASTFOOD Buôn Ma Thuột. Dự án phát triển Website đặt đồ ăn trực tuyến chuẩn PHP MVC.</p>
      </div>
    </div>
  </footer>

  <!-- Tích hợp Khung Chat Nhân Viên Hỗ Trợ AI -->
  <?php require_once __DIR__ . '/chatbot.php'; ?>

  <!-- Scripts dùng chung -->
  <script src="<?= getAssetUrl('js/storage.js') ?>"></script>
  <script src="<?= getAssetUrl('js/components.js') ?>"></script>
  <script src="<?= getAssetUrl('js/chatbot.js') ?>"></script>
  <?php if (!empty($extraJs)): ?>
    <?php foreach ((array)$extraJs as $js): ?>
      <script src="<?= getAssetUrl($js) ?>"></script>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>

