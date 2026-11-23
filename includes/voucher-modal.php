<?php
/**
 * FASTFOOD - Welcome Voucher 50.000đ & Anti-Duplication Modal
 * Hiển thị sau khi đăng ký tài khoản thành công để thu thập thông tin định danh và cấp voucher
 */
$showVoucherModal = !empty($_SESSION['show_voucher_claim_modal']) && isLoggedIn();
if (!$showVoucherModal) {
    return;
}

$pendingUser = $_SESSION['pending_voucher_user'] ?? currentUser() ?? [];
$prefillName    = $pendingUser['full_name'] ?? $pendingUser['fullName'] ?? '';
$prefillPhone   = $pendingUser['phone'] ?? '';
$prefillEmail   = $pendingUser['email'] ?? '';
$prefillAddress = $pendingUser['address'] ?? '';
?>
<link rel="stylesheet" href="<?= getAssetUrl('css/auth.css') ?>">

<div class="welcome-modal-overlay" id="welcome-voucher-modal" role="dialog" aria-modal="true" aria-labelledby="voucher-modal-title">
  <div class="welcome-modal-card welcome-voucher-card">
    <canvas id="welcome-confetti-canvas"></canvas>
    
    <!-- Nút đóng / Bỏ qua (không làm gián đoạn phiên đăng nhập) -->
    <button type="button" class="welcome-close-btn js-skip-voucher-modal" aria-label="Đóng và để sau" title="Bỏ qua / Để sau">✕</button>

    <!-- VIEW 1: Form thu thập thông tin khách hàng và kiểm tra chống trùng lặp -->
    <div id="voucher-form-view">
      <div class="welcome-gift-wrap">
        <div class="welcome-glow-ring"></div>
        <div class="welcome-gift-circle">🎁</div>
      </div>

      <h2 class="welcome-title" id="voucher-modal-title">Quà Tặng Bạn Mới - 50.000đ!</h2>
      <p class="welcome-subtitle">
        Chúc mừng <span class="welcome-user-highlight" id="voucher-user-display"><?= escapeHtml($prefillName ?: 'Bạn') ?></span> đã gia nhập FASTFOOD! Hoàn tất thông tin xác thực để nhận ngay voucher giảm <strong>50.000đ</strong> cho đơn đầu tiên.
      </p>

      <!-- Banner thông báo lỗi nếu trùng lặp dữ liệu hoặc sai định dạng -->
      <div class="welcome-alert-banner" id="voucher-error-alert" style="display:none;" role="alert">
        <div class="welcome-alert-icon">⚠️</div>
        <div class="welcome-alert-msg" id="voucher-error-msg"></div>
      </div>

      <form id="welcome-voucher-claim-form" class="welcome-voucher-form" novalidate>
        <div class="welcome-form-group">
          <label for="voucher-fullname" class="welcome-form-label">
            Họ và tên <span class="welcome-req">*</span>
          </label>
          <input type="text" id="voucher-fullname" name="fullName" class="welcome-form-input" 
                 value="<?= escapeHtml($prefillName) ?>" required placeholder="Ví dụ: Nguyễn Văn A">
        </div>

        <div class="welcome-form-group">
          <label for="voucher-phone" class="welcome-form-label">
            Số điện thoại <span class="welcome-req">*</span>
          </label>
          <input type="tel" id="voucher-phone" name="phone" class="welcome-form-input" 
                 value="<?= escapeHtml($prefillPhone) ?>" required placeholder="Ví dụ: 0912345678">
        </div>

        <div class="welcome-form-group">
          <label for="voucher-cccd" class="welcome-form-label">
            Số Căn cước công dân (CCCD / CMND) <span class="welcome-req">*</span>
          </label>
          <input type="text" id="voucher-cccd" name="cccd" class="welcome-form-input" 
                 required placeholder="Nhập đúng 9 hoặc 12 số CCCD / CMND" maxlength="20" autocomplete="off">
          <span class="welcome-input-hint">Định danh cá nhân duy nhất 1 lần để chống trục lợi khuyến mãi.</span>
        </div>

        <div class="welcome-form-group">
          <label for="voucher-address" class="welcome-form-label">
            Địa chỉ nhận hàng chi tiết <span class="welcome-req">*</span>
          </label>
          <input type="text" id="voucher-address" name="address" class="welcome-form-input" 
                 value="<?= escapeHtml($prefillAddress) ?>" required placeholder="Số nhà, tên đường, phường/xã, quận/huyện...">
        </div>

        <div class="welcome-btn-stack">
          <button type="submit" class="welcome-cta-btn" id="voucher-submit-btn">
            <span id="voucher-submit-text">🎁 Xác Nhận & Nhận Voucher 50.000đ</span>
            <span id="voucher-submit-spinner" style="display:none;">⏳ Đang kiểm tra...</span>
          </button>
          
          <button type="button" class="welcome-later-btn js-skip-voucher-modal" id="voucher-skip-btn">
            Bỏ qua / Để sau, tôi muốn xem trang chủ trước
          </button>
        </div>
      </form>
    </div>

    <!-- VIEW 2: Thông báo nhận voucher thành công -->
    <div id="voucher-success-view" style="display: none;">
      <div class="welcome-gift-wrap">
        <div class="welcome-glow-ring"></div>
        <div class="welcome-gift-circle">🎉</div>
      </div>

      <h2 class="welcome-title">Nhận Voucher Thành Công!</h2>
      <p class="welcome-subtitle">
        Voucher <strong>50.000đ</strong> đã được cấp thành công cho tài khoản của bạn và sẵn sàng áp dụng tại giỏ hàng!
      </p>

      <div class="welcome-ticket">
        <div class="welcome-ticket-val">
          <span class="welcome-percent-badge" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">GIẢM 50.000đ</span>
          <span class="welcome-ticket-title">Voucher Bạn Mới</span>
        </div>
        <p class="welcome-ticket-desc">
          Giảm trực tiếp <strong>50.000đ</strong> cho đơn hàng đầu tiên của bạn tại FASTFOOD!
        </p>

        <div class="welcome-code-box">
          <span class="welcome-code-text" id="voucher-granted-code">NEW50K-......</span>
          <button type="button" class="welcome-copy-btn" id="voucher-copy-btn" data-code="">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
            Sao Chép Mã
          </button>
        </div>

        <div class="welcome-applied-status">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span>Đã tự động áp dụng vào giỏ hàng của bạn!</span>
        </div>
      </div>

      <div class="welcome-btn-stack">
        <a href="<?= getUrl('foods.php') ?>" class="welcome-cta-btn">
          <span>Khám Phá Món Ăn & Dùng Mã Ngay</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
        </a>

        <button type="button" class="welcome-later-btn js-close-voucher-modal">
          Đóng cửa sổ
        </button>
      </div>
    </div>
  </div>
</div>
<script src="<?= getAssetUrl('js/auth.js') ?>"></script>

