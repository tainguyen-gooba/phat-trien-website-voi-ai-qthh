<?php
/**
 * FASTFOOD - Đăng ký tài khoản (Register)
 * Giao diện hiện đại, chuẩn UI/UX Food Delivery
 */
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    header('Location: ' . getUrl('index.php'));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName        = trim($_POST['fullName'] ?? '');
    $email           = strtolower(trim($_POST['email'] ?? ''));
    $phone           = trim($_POST['phone'] ?? '');
    $cleanPhone      = preg_replace('/[^0-9]/', '', $phone);
    $password        = trim($_POST['password'] ?? '');
    $passwordConfirm = trim($_POST['passwordConfirm'] ?? '');

    if (mb_strlen($fullName) < 3) {
        $error = 'Vui lòng nhập họ và tên hợp lệ (tối thiểu 3 ký tự).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Địa chỉ email không đúng định dạng.';
    } elseif (strlen($cleanPhone) < 9 || strlen($cleanPhone) > 11) {
        $error = 'Số điện thoại không hợp lệ (yêu cầu từ 9 - 11 chữ số).';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Mật khẩu xác nhận không trùng khớp.';
    } elseif ($password === $email || $password === $phone || $password === $cleanPhone) {
        $error = 'Mật khẩu không được trùng với Email hoặc Số điện thoại để đảm bảo bảo mật.';
    } else {
        $db = getDB();

        // 1. Kiểm tra trùng Email
        $checkEmail = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $checkEmail->execute([$email]);
        if ($checkEmail->fetch()) {
            $error = 'Địa chỉ Email này đã được đăng ký. Vui lòng sử dụng email khác hoặc Đăng nhập.';
        } else {
            // 2. Kiểm tra trùng Số điện thoại
            $checkPhone = $db->prepare("SELECT id FROM users WHERE phone = ? OR REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+84', '0') = ? LIMIT 1");
            $checkPhone->execute([$phone, $cleanPhone]);
            if ($checkPhone->fetch()) {
                $error = 'Số điện thoại này đã được đăng ký. Vui lòng sử dụng số điện thoại khác hoặc Đăng nhập.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $insertStmt = $db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status, created_at)
                                            VALUES (2, ?, ?, ?, ?, 'active', NOW())");
                $insertStmt->execute([$fullName, $email, $phone, $hash]);
                $newUserId = (int)$db->lastInsertId();

                // 3. Đảm bảo mã mẫu NEW50K chung tồn tại trong hệ thống (nếu chưa có)
                try {
                    $checkGlobal2 = $db->prepare("SELECT id FROM coupons WHERE code = 'NEW50K' LIMIT 1");
                    $checkGlobal2->execute();
                    if (!$checkGlobal2->fetch()) {
                        $db->prepare("INSERT INTO coupons (code, title, discount_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, start_date, end_date, is_active, created_at)
                                      VALUES ('NEW50K', 'Voucher bạn mới - Giảm ngay 50.000đ', 'fixed', 50000.00, 0.00, 50000.00, 10000, 0, '2026-01-01 00:00:00', '2027-12-31 23:59:59', 1, NOW())")->execute();
                    }
                } catch (Exception $e) {}

                // 4. Lưu phiên đăng nhập cho tài khoản mới vừa tạo
                setUserSession([
                    'id'         => $newUserId,
                    'full_name'  => $fullName,
                    'email'      => $email,
                    'phone'      => $phone,
                    'role_id'    => 2,
                    'avatar_url' => '',
                ]);

                // 5. Kích hoạt cờ hiển thị Modal Nhập Thông Tin Khách Hàng để nhận Voucher 50.000đ
                $_SESSION['show_voucher_claim_modal'] = true;
                $_SESSION['pending_voucher_user'] = [
                    'id'        => $newUserId,
                    'full_name' => $fullName,
                    'email'     => $email,
                    'phone'     => $phone
                ];

                // Xóa cờ popup chào mừng cũ (không tự động cấp mà phải qua modal định danh)
                unset($_SESSION['show_welcome_popup']);
                unset($_SESSION['welcome_popup_data']);
                unset($_SESSION['welcome_discount_applied']);

                // Đánh dấu phát sự kiện realtime user:new sang Admin sau khi chuyển trang
                $_SESSION['new_user_broadcast'] = [
                    'id'     => (string)$newUserId,
                    'name'   => $fullName,
                    'email'  => $email,
                    'phone'  => $phone ?: 'Chưa cập nhật',
                    'role'   => 'Khách hàng',
                    'roleId' => 2,
                    'status' => 'Đang hoạt động'
                ];

                header('Location: ' . getUrl('index.php'));
                exit;
            }
        }
    }
}

$pageTitle = 'Đăng Ký Tài Khoản - FASTFOOD';
$activeNav = '';
$authCssVer = @filemtime(__DIR__ . '/css/auth.css') ?: '20261126';
$authJsVer  = @filemtime(__DIR__ . '/js/auth.js') ?: '20261126';
$extraCss  = ['css/pages.css', 'css/auth.css?v=' . $authCssVer];
$extraJs   = ['js/auth.js?v=' . $authJsVer];

require_once ROOT_PATH . '/includes/header.php';
?>

<main class="auth-page-wrap auth-split-wrap">
  <div class="auth-bg-orb auth-bg-orb-1" aria-hidden="true"></div>
  <div class="auth-bg-orb auth-bg-orb-2" aria-hidden="true"></div>

  <div class="auth-topbar">
    <a href="<?= getUrl('index.php') ?>" class="auth-topbar-logo" title="Quay về Trang Chủ FASTFOOD">
      <img src="<?= getAssetUrl('assets/images/logo.svg') ?>" alt="FASTFOOD" class="auth-brand-top-img">
    </a>
    <a href="<?= getUrl('index.php') ?>" class="auth-back-home-btn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="19" y1="12" x2="5" y2="12"></line>
        <polyline points="12 19 5 12 12 5"></polyline>
      </svg>
      <span>Về trang chủ</span>
    </a>
  </div>

  <div class="auth-shell auth-shell-register">
    <!-- Left Brand Showcase -->
    <aside class="auth-showcase" aria-label="Quyền lợi thành viên FASTFOOD">
      <div class="auth-showcase-pattern" aria-hidden="true"></div>
      <div class="auth-showcase-glow" aria-hidden="true"></div>

      <div class="auth-showcase-top">
        <div class="auth-showcase-pill">
          <span class="auth-pill-dot" aria-hidden="true"></span>
          <span>ĐĂNG KÝ THÀNH VIÊN FASTFOOD</span>
        </div>

        <h2 class="auth-showcase-title">Gia nhập hội sành ăn, nhận đặc quyền mỗi ngày</h2>
        <p class="auth-showcase-desc">
          Chỉ 30 giây đăng ký để mở khóa hệ thống tích điểm thành viên VIP, kho voucher giảm giá và theo dõi đơn hàng siêu nhanh.
        </p>
      </div>

      <div class="auth-showcase-features">
        <div class="auth-feature-item">
          <div class="auth-feature-icon" aria-hidden="true">🎁</div>
          <div>
            <div class="auth-feature-title">Ưu Đãi Thành Viên Mới</div>
            <div class="auth-feature-sub">Săn voucher 50.000đ ngay khi hoàn tất xác nhận thông tin</div>
          </div>
        </div>
        <div class="auth-feature-item">
          <div class="auth-feature-icon" aria-hidden="true">👑</div>
          <div>
            <div class="auth-feature-title">4 Hạng VIP: Đồng → Kim Cương</div>
            <div class="auth-feature-sub">Tích điểm tự động sau mỗi đơn, quy đổi giảm trực tiếp</div>
          </div>
        </div>
        <div class="auth-feature-item">
          <div class="auth-feature-icon" aria-hidden="true">⚡</div>
          <div>
            <div class="auth-feature-title">Đặt Lại Nhanh Trong 1 Chạm</div>
            <div class="auth-feature-sub">Lưu địa chỉ giao hàng &amp; lịch sử món yêu thích tiện lợi</div>
          </div>
        </div>
      </div>

      <div class="auth-showcase-stats">
        <div class="auth-stat-col">
          <strong>30 giây</strong>
          <span>Tạo tài khoản</span>
        </div>
        <div class="auth-stat-divider" aria-hidden="true"></div>
        <div class="auth-stat-col">
          <strong>x3.0 pts</strong>
          <span>Tích điểm Kim Cương</span>
        </div>
        <div class="auth-stat-divider" aria-hidden="true"></div>
        <div class="auth-stat-col">
          <strong>0đ Ship</strong>
          <span>Ưu đãi Freeship</span>
        </div>
      </div>
    </aside>

    <!-- Right Form Card -->
    <div class="auth-card-box auth-card-wide">
      <!-- Tab chuyển đổi giữa Đăng Nhập & Đăng Ký -->
      <div class="auth-tab-switch">
        <a href="<?= getUrl('login.php') ?>" class="auth-tab-btn">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
          Đăng Nhập
        </a>
        <a href="<?= getUrl('register.php') ?>" class="auth-tab-btn active">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
          Đăng Ký
        </a>
      </div>

      <!-- Header Form -->
      <div class="auth-head auth-head-split">
        <div class="auth-head-icon-sm" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="8.5" cy="7" r="4"></circle>
            <line x1="20" y1="8" x2="20" y2="14"></line>
            <line x1="23" y1="11" x2="17" y2="11"></line>
          </svg>
        </div>
        <div>
          <h1 class="auth-head-title">Đăng Ký Tài Khoản</h1>
          <p class="auth-head-desc">
            Tạo tài khoản thành viên FASTFOOD để trải nghiệm đặt món ngon nhanh chóng và tiện lợi
          </p>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="auth-error-alert">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
          <span><?= escapeHtml($error) ?></span>
        </div>
      <?php endif; ?>

      <form action="<?= getUrl('register.php') ?>" method="POST" id="main-register-form">
        <!-- Họ và tên -->
        <div class="auth-field-group">
          <label class="auth-label" for="reg-name">
            Họ và Tên <span class="auth-label-req">*</span>
          </label>
          <div class="auth-input-wrapper">
            <input type="text" name="fullName" id="reg-name" class="auth-input" 
                   placeholder="Nguyễn Văn A" value="<?= escapeHtml($_POST['fullName'] ?? '') ?>" required autocomplete="name">
            <svg class="auth-input-icon" viewBox="0 0 24 24">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </div>
        </div>

        <!-- Email & SĐT (2 cột trên desktop) -->
        <div class="auth-grid-2">
          <div class="auth-field-group">
            <label class="auth-label" for="reg-email">
              Địa chỉ Email <span class="auth-label-req">*</span>
            </label>
            <div class="auth-input-wrapper">
              <input type="email" name="email" id="reg-email" class="auth-input" 
                     placeholder="tenban@example.com" value="<?= escapeHtml($_POST['email'] ?? '') ?>" required autocomplete="email">
              <svg class="auth-input-icon" viewBox="0 0 24 24">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                <polyline points="22,6 12,13 2,6"></polyline>
              </svg>
            </div>
          </div>

          <div class="auth-field-group">
            <label class="auth-label" for="reg-phone">
              Số Điện Thoại <span class="auth-label-req">*</span>
            </label>
            <div class="auth-input-wrapper">
              <input type="tel" name="phone" id="reg-phone" class="auth-input" 
                     placeholder="0912 345 678" value="<?= escapeHtml($_POST['phone'] ?? '') ?>" required autocomplete="tel">
              <svg class="auth-input-icon" viewBox="0 0 24 24">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
              </svg>
            </div>
          </div>
        </div>

        <!-- Mật khẩu & Xác nhận mật khẩu (2 Cột) -->
        <div class="auth-grid-2">
          <div class="auth-field-group auth-field-tight">
            <label class="auth-label" for="reg-pass">
              Mật Khẩu <span class="auth-label-req">*</span>
            </label>
            <div class="auth-input-wrapper">
              <input type="password" name="password" id="reg-pass" class="auth-input auth-input-has-toggle" 
                     placeholder="Tối thiểu 6 ký tự" required autocomplete="new-password">
              <svg class="auth-input-icon" viewBox="0 0 24 24">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
              <button type="button" class="auth-toggle-pass" data-target="reg-pass" title="Hiện mật khẩu">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              </button>
            </div>
          </div>

          <div class="auth-field-group auth-field-tight">
            <label class="auth-label" for="reg-pass-confirm">
              Xác Nhận Mật Khẩu <span class="auth-label-req">*</span>
            </label>
            <div class="auth-input-wrapper">
              <input type="password" name="passwordConfirm" id="reg-pass-confirm" class="auth-input auth-input-has-toggle" 
                     placeholder="Nhập lại mật khẩu" required autocomplete="new-password">
              <svg class="auth-input-icon" viewBox="0 0 24 24">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
              <button type="button" class="auth-toggle-pass" data-target="reg-pass-confirm" title="Hiện mật khẩu">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              </button>
            </div>
            <div class="auth-pass-match" id="pass-match-hint" aria-live="polite"></div>
          </div>
        </div>

        <!-- Password Strength Meter -->
        <div class="auth-pass-meter">
          <div class="auth-meter-bars">
            <div class="auth-meter-bar"></div>
            <div class="auth-meter-bar"></div>
            <div class="auth-meter-bar"></div>
            <div class="auth-meter-bar"></div>
          </div>
          <div class="auth-meter-text">
            <span id="pass-strength-text">Độ mạnh: Chưa nhập</span>
            <span>Nên gồm chữ hoa, số &amp; ký tự</span>
          </div>
        </div>

        <!-- Điều khoản & Cam kết -->
        <p class="auth-terms-note">
          Bằng cách bấm Đăng ký, bạn đồng ý với <a href="<?= getUrl('policies.php#terms') ?>" target="_blank">Điều khoản dịch vụ</a> và <a href="<?= getUrl('policies.php#privacy') ?>" target="_blank">Chính sách bảo mật</a> của FASTFOOD.
        </p>

        <!-- Nút Đăng Ký -->
        <button type="submit" class="auth-submit-btn">
          <span>Tạo Tài Khoản Ngay</span>
          <svg class="auth-btn-arrow" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
        </button>
      </form>

      <!-- Chuyển sang Đăng Nhập -->
      <div class="auth-foot-text">
        Đã có tài khoản? 
        <a href="<?= getUrl('login.php') ?>" class="auth-foot-link">Đăng nhập ngay</a>
      </div>

      <!-- Cam kết tin cậy -->
      <div class="auth-trust-row">
        <div class="auth-trust-item">
          <span>🔒</span>
          <span>Bảo mật 100%</span>
        </div>
        <div class="auth-trust-item">
          <span>⚡</span>
          <span>Giao trong 20 phút</span>
        </div>
        <div class="auth-trust-item">
          <span>🍔</span>
          <span>Món ngon chuẩn vị</span>
        </div>
      </div>
    </div>
  </div>
</main>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
