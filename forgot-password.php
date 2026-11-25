<?php
/**
 * FASTFOOD - Quên Mật Khẩu & Đặt Lại Mật Khẩu (Forgot Password)
 * Hỗ trợ xác thực qua mã OTP 6 số và liên kết Token bảo mật
 * Giao diện Modern Orange - White chuẩn UI/UX Food Delivery
 */
require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/helpers/auth.php';
require_once ROOT_PATH . '/helpers/password_reset.php';

// Nếu đã đăng nhập, chuyển về trang chủ hoặc hồ sơ
if (isLoggedIn() && empty($_POST)) {
    header('Location: ' . getUrl('index.php'));
    exit;
}

$step = trim($_GET['step'] ?? 'request');
$email = strtolower(trim($_GET['email'] ?? $_POST['email'] ?? ''));
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$otp = trim($_POST['otp'] ?? '');

$error = '';
$success = '';
$demoOtpInfo = null;

// Xử lý các Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // BƯỚC 1: Gửi yêu cầu cấp mã OTP
    if ($action === 'request_otp') {
        $res = createPasswordReset($email);
        if ($res['success']) {
            $_SESSION['pr_demo_otp'] = $res['otp_code'];
            $_SESSION['pr_demo_token'] = $res['token'];
            $_SESSION['pr_demo_email'] = $res['email'];
            header('Location: ' . getUrl('forgot-password.php?step=verify&email=' . urlencode($res['email'])));
            exit;
        } else {
            $error = $res['error'] ?? 'Không thể gửi yêu cầu.';
            $step = 'request';
        }
    }
    // BƯỚC 2: Xác thực mã OTP
    elseif ($action === 'verify_otp') {
        $res = verifyPasswordResetOTP($email, $otp);
        if ($res['success']) {
            unset($_SESSION['pr_demo_otp']);
            header('Location: ' . getUrl('forgot-password.php?step=reset&token=' . urlencode($res['token'])));
            exit;
        } else {
            $error = $res['error'] ?? 'Mã OTP không hợp lệ.';
            $step = 'verify';
        }
    }
    // BƯỚC 3: Đặt lại mật khẩu mới
    elseif ($action === 'reset_password') {
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $res = resetUserPassword($token, $newPass, $confirmPass);
        if ($res['success']) {
            $step = 'success';
            $success = $res['message'];
            unset($_SESSION['pr_demo_otp']);
            unset($_SESSION['pr_demo_token']);
            unset($_SESSION['pr_demo_email']);
        } else {
            $error = $res['error'] ?? 'Không thể đặt lại mật khẩu.';
            $step = 'reset';
        }
    }
}

// Kiểm tra tính hợp lệ nếu đang ở bước reset
$tokenUser = null;
if ($step === 'reset') {
    if (!$token) {
        $error = 'Thiếu mã token đặt lại mật khẩu.';
        $step = 'request';
    } else {
        $chk = verifyPasswordResetToken($token);
        if (!$chk['success']) {
            $error = $chk['error'];
            $step = 'request';
        } else {
            $tokenUser = $chk['reset'];
            $email = $tokenUser['user_email'];
        }
    }
}

// Lấy thông tin Demo OTP trong Session nếu có
if (!empty($_SESSION['pr_demo_otp']) && $step === 'verify') {
    $demoOtpInfo = [
        'otp'   => $_SESSION['pr_demo_otp'],
        'token' => $_SESSION['pr_demo_token'] ?? '',
        'email' => $_SESSION['pr_demo_email'] ?? $email
    ];
}

$pageTitle = 'Quên Mật Khẩu - FASTFOOD';
$activeNav = '';
$extraCss  = ['css/pages.css', 'css/auth.css'];
$extraJs   = ['js/auth.js'];

require_once ROOT_PATH . '/includes/header.php';
?>

<main class="auth-page-wrap">
  <!-- Floating Background Badges -->
  <div class="auth-bg-decor auth-bg-decor-1">
    <div class="auth-floating-badge">
      <span class="badge-icon">🔐</span>
      <span>Bảo mật 2 lớp OTP</span>
    </div>
  </div>
  <div class="auth-bg-decor auth-bg-decor-2">
    <div class="auth-floating-badge">
      <span class="badge-icon">⚡</span>
      <span>Khôi phục tức thì</span>
    </div>
  </div>

  <div class="auth-brand-top">
    <a href="<?= getUrl('index.php') ?>" title="Quay về Trang Chủ FASTFOOD">
      <img src="<?= getAssetUrl('assets/images/logo.svg') ?>" alt="FASTFOOD" class="auth-brand-top-img">
    </a>
  </div>

  <div class="auth-card-box">
    <!-- Stepper Indicator -->
    <div class="fp-stepper-wrap">
      <div class="fp-step-item <?= $step === 'request' ? 'active' : ($step !== 'request' ? 'completed' : '') ?>">
        <div class="fp-step-circle">1</div>
        <span class="fp-step-label">Nhập Email</span>
      </div>
      <div class="fp-step-line <?= in_array($step, ['verify', 'reset', 'success']) ? 'active' : '' ?>"></div>
      <div class="fp-step-item <?= $step === 'verify' ? 'active' : (in_array($step, ['reset', 'success']) ? 'completed' : '') ?>">
        <div class="fp-step-circle">2</div>
        <span class="fp-step-label">Mã OTP</span>
      </div>
      <div class="fp-step-line <?= in_array($step, ['reset', 'success']) ? 'active' : '' ?>"></div>
      <div class="fp-step-item <?= in_array($step, ['reset', 'success']) ? 'active' : '' ?>">
        <div class="fp-step-circle">3</div>
        <span class="fp-step-label">Mật Khẩu Mới</span>
      </div>
    </div>

    <!-- Error Alert -->
    <?php if ($error): ?>
      <div class="auth-error-alert" style="margin-top:1.25rem;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <span><?= escapeHtml($error) ?></span>
      </div>
    <?php endif; ?>

    <!-- Success Alert -->
    <?php if ($success): ?>
      <div class="auth-success-alert" style="margin-top:1.25rem; background:#dcfce7; border:1px solid #86efac; color:#15803d; padding:0.85rem 1.1rem; border-radius:12px; font-size:0.9rem; font-weight:700; display:flex; align-items:center; gap:8px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <span><?= escapeHtml($success) ?></span>
      </div>
    <?php endif; ?>

    <!-- =====================================================================
         BƯỚC 1: FORM YÊU CẦU NHẬP EMAIL
         ===================================================================== -->
    <?php if ($step === 'request'): ?>
      <div class="auth-head" style="margin-top:1rem;">
        <h1 class="auth-head-title">Quên Mật Khẩu?</h1>
        <p class="auth-head-desc">
          Đừng lo lắng! Hãy nhập địa chỉ email đã đăng ký của bạn. FASTFOOD sẽ gửi mã xác thực OTP 6 số để bạn thiết lập lại mật khẩu an toàn.
        </p>
      </div>

      <form action="<?= getUrl('forgot-password.php') ?>" method="POST" id="fp-request-form">
        <input type="hidden" name="action" value="request_otp">

        <div class="auth-field-group">
          <label class="auth-label" for="fp-email">
            Địa chỉ Email của bạn <span class="auth-label-req">*</span>
          </label>
          <div class="auth-input-wrapper">
            <input type="email" name="email" id="fp-email" class="auth-input" 
                   placeholder="VD: nguyenvana@gmail.com" value="<?= escapeHtml($email) ?>" required autofocus autocomplete="email">
            <svg class="auth-input-icon" viewBox="0 0 24 24">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
              <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
          </div>
        </div>

        <button type="submit" class="auth-submit-btn" style="margin-top:1.5rem;">
          <span>Gửi Mã Xác Thực OTP</span>
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
        </button>
      </form>

      <div class="auth-foot-text" style="margin-top:1.5rem;">
        Nhớ mật khẩu rồi? 
        <a href="<?= getUrl('login.php') ?>" class="auth-foot-link">Quay lại đăng nhập</a>
      </div>

    <!-- =====================================================================
         BƯỚC 2: FORM NHẬP MÃ XÁC THỰC OTP
         ===================================================================== -->
    <?php elseif ($step === 'verify'): ?>
      <div class="auth-head" style="margin-top:1rem;">
        <h1 class="auth-head-title">Nhập Mã OTP</h1>
        <p class="auth-head-desc">
          Mã xác thực gồm 6 chữ số đã được gửi tới email <strong><?= escapeHtml(maskEmail($email)) ?></strong>.
        </p>
      </div>

      <!-- Demo Notification Card -->
      <?php if ($demoOtpInfo): ?>
        <div class="fp-demo-box">
          <div class="fp-demo-badge">✨ MÔ PHỎNG MÃ OTP (MÔI TRƯỜNG DEV/DEMO)</div>
          <div class="fp-demo-body">
            <div>Mã OTP xác thực của bạn là: <strong class="fp-demo-code" id="demo-otp-val"><?= escapeHtml($demoOtpInfo['otp']) ?></strong></div>
            <div style="font-size:0.8rem; color:#64748b; margin-top:2px;">(Hiệu lực trong vòng 15 phút)</div>
          </div>
          <div style="display:flex; gap:8px; margin-top:8px;">
            <button type="button" class="fp-demo-btn" onclick="autofillOtp('<?= escapeHtml($demoOtpInfo['otp']) ?>')">
              ⚡ Điền nhanh mã này
            </button>
            <?php if (!empty($demoOtpInfo['token'])): ?>
              <a href="<?= getUrl('forgot-password.php?step=reset&token=' . urlencode($demoOtpInfo['token'])) ?>" class="fp-demo-btn" style="text-decoration:none;">
                ⏩ Vào thẳng đổi mật khẩu
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <form action="<?= getUrl('forgot-password.php?step=verify&email=' . urlencode($email)) ?>" method="POST" id="fp-verify-form">
        <input type="hidden" name="action" value="verify_otp">
        <input type="hidden" name="email" value="<?= escapeHtml($email) ?>">

        <div class="auth-field-group">
          <label class="auth-label" style="text-align:center; display:block;" for="otp-input-field">
            Mã OTP 6 chữ số <span class="auth-label-req">*</span>
          </label>
          <div style="display:flex; justify-content:center; margin-top:0.5rem;">
            <input type="text" name="otp" id="otp-input-field" class="fp-otp-input" 
                   maxlength="6" placeholder="000000" pattern="[0-9]{6}" inputmode="numeric" required autofocus autocomplete="one-time-code">
          </div>
        </div>

        <!-- Countdown Timer & Resend -->
        <div class="fp-timer-row">
          <span>Thời gian còn lại: <strong id="fp-timer">15:00</strong></span>
          <form action="<?= getUrl('forgot-password.php') ?>" method="POST" style="display:inline;" id="fp-resend-form">
            <input type="hidden" name="action" value="request_otp">
            <input type="hidden" name="email" value="<?= escapeHtml($email) ?>">
            <button type="submit" class="fp-resend-link" id="btn-resend-otp">
              🔄 Gửi lại mã OTP
            </button>
          </form>
        </div>

        <button type="submit" class="auth-submit-btn" style="margin-top:1.5rem;">
          <span>Xác Thực & Tiếp Tục</span>
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
        </button>
      </form>

      <div class="auth-foot-text" style="margin-top:1.5rem;">
        Sai email? 
        <a href="<?= getUrl('forgot-password.php?step=request') ?>" class="auth-foot-link">Nhập email khác</a>
      </div>

    <!-- =====================================================================
         BƯỚC 3: FORM THIẾT LẬP MẬT KHẨU MỚI
         ===================================================================== -->
    <?php elseif ($step === 'reset'): ?>
      <div class="auth-head" style="margin-top:1rem;">
        <h1 class="auth-head-title">Mật Khẩu Mới</h1>
        <p class="auth-head-desc">
          Tạo mật khẩu mới cho tài khoản <strong><?= escapeHtml($email) ?></strong>. Hãy chọn mật khẩu an toàn và dễ nhớ với bạn.
        </p>
      </div>

      <form action="<?= getUrl('forgot-password.php') ?>" method="POST" id="fp-reset-form">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="token" value="<?= escapeHtml($token) ?>">

        <!-- New Password -->
        <div class="auth-field-group">
          <label class="auth-label" for="fp-new-password">
            Mật khẩu mới <span class="auth-label-req">*</span>
          </label>
          <div class="auth-input-wrapper">
            <input type="password" name="new_password" id="fp-new-password" class="auth-input" 
                   placeholder="Tối thiểu 6 ký tự" minlength="6" required autocomplete="new-password">
            <svg class="auth-input-icon" viewBox="0 0 24 24">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <button type="button" class="auth-toggle-pass" data-target="fp-new-password" title="Hiện mật khẩu">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </button>
          </div>
        </div>

        <!-- Confirm Password -->
        <div class="auth-field-group">
          <label class="auth-label" for="fp-confirm-password">
            Xác nhận mật khẩu mới <span class="auth-label-req">*</span>
          </label>
          <div class="auth-input-wrapper">
            <input type="password" name="confirm_password" id="fp-confirm-password" class="auth-input" 
                   placeholder="Nhập lại mật khẩu mới" minlength="6" required autocomplete="new-password">
            <svg class="auth-input-icon" viewBox="0 0 24 24">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            <button type="button" class="auth-toggle-pass" data-target="fp-confirm-password" title="Hiện mật khẩu">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </button>
          </div>
        </div>

        <!-- Live Password Strength Meter -->
        <div class="fp-strength-wrap">
          <div class="fp-strength-bar">
            <div class="fp-strength-fill" id="fp-strength-fill"></div>
          </div>
          <span class="fp-strength-label" id="fp-strength-label">Độ mạnh mật khẩu</span>
        </div>

        <button type="submit" class="auth-submit-btn" style="margin-top:1.5rem;">
          <span>Lưu Mật Khẩu Mới</span>
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
        </button>
      </form>

    <!-- =====================================================================
         BƯỚC 4: THÀNH CÔNG (SUCCESS STATE)
         ===================================================================== -->
    <?php elseif ($step === 'success'): ?>
      <div class="fp-success-card">
        <div class="fp-success-icon-wrap">
          <span style="font-size:2.8rem;">🎉</span>
        </div>
        <h2 style="font-size:1.45rem; font-weight:850; color:#1e293b; margin:1rem 0 0.5rem;">
          Đổi Mật Khẩu Thành Công!
        </h2>
        <p style="color:#64748b; font-size:0.92rem; line-height:1.5; margin-bottom:1.5rem;">
          Mật khẩu mới của bạn đã được cập nhật thành công vào hệ thống FASTFOOD. Bây giờ bạn có thể đăng nhập và tiếp tục đặt món ngon!
        </p>

        <a href="<?= getUrl('login.php') ?>" class="auth-submit-btn" style="text-decoration:none; justify-content:center;">
          <span>Đăng Nhập Ngay</span>
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
        </a>
      </div>
    <?php endif; ?>

  </div>
</main>

<script>
  function autofillOtp(code) {
    const input = document.getElementById('otp-input-field');
    if (input) {
      input.value = code;
      input.focus();
    }
  }

  // Countdown timer cho OTP
  (function initOtpTimer() {
    const timerEl = document.getElementById('fp-timer');
    if (!timerEl) return;

    let timeLeft = 15 * 60; // 15 phút
    const interval = setInterval(() => {
      timeLeft--;
      if (timeLeft <= 0) {
        clearInterval(interval);
        timerEl.textContent = 'Đã hết hạn';
        timerEl.style.color = '#ef4444';
        return;
      }
      const m = Math.floor(timeLeft / 60);
      const s = timeLeft % 60;
      timerEl.textContent = `${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}`;
    }, 1000);
  })();

  // Live Password Strength Meter
  (function initPasswordMeter() {
    const passInput = document.getElementById('fp-new-password');
    const fill = document.getElementById('fp-strength-fill');
    const label = document.getElementById('fp-strength-label');
    if (!passInput || !fill || !label) return;

    passInput.addEventListener('input', () => {
      const val = passInput.value;
      if (!val) {
        fill.style.width = '0%';
        fill.style.background = '#e2e8f0';
        label.textContent = 'Độ mạnh mật khẩu';
        label.style.color = '#94a3b8';
        return;
      }

      let score = 0;
      if (val.length >= 6) score++;
      if (val.length >= 8) score++;
      if (/[A-Z]/.test(val)) score++;
      if (/[0-9]/.test(val)) score++;
      if (/[^A-Za-z0-9]/.test(val)) score++;

      if (score <= 2) {
        fill.style.width = '33%';
        fill.style.background = '#ef4444';
        label.textContent = 'Mật khẩu yếu';
        label.style.color = '#ef4444';
      } else if (score <= 4) {
        fill.style.width = '66%';
        fill.style.background = '#f59e0b';
        label.textContent = 'Mật khẩu trung bình';
        label.style.color = '#f59e0b';
      } else {
        fill.style.width = '100%';
        fill.style.background = '#10b981';
        label.textContent = 'Mật khẩu rất mạnh';
        label.style.color = '#10b981';
      }
    });
  })();
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
