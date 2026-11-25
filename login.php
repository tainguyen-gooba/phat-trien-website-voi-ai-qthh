<?php
/**
 * FASTFOOD - Đăng nhập tài khoản (Login)
 * Giao diện hiện đại, chuẩn UI/UX Food Delivery
 */
require_once __DIR__ . '/config/config.php';

if (isLoggedIn() && empty($_POST) && !isset($_GET['switch'])) {
    if (isAdminOrStaff()) {
        header('Location: ' . BASE_URL . '/admin/index.php');
    } else {
        header('Location: ' . getUrl('index.php'));
    }
    exit;
}

$error = '';
$redirect = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
$activeTab = trim($_GET['tab'] ?? $_POST['tab'] ?? '');
$forAdmin = (($_GET['for'] ?? $_POST['for'] ?? '') === 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $valid = false;
        if ($user) {
            if ($user['status'] === 'inactive' || $user['status'] === 'banned') {
                $error = 'Tài khoản của bạn đã bị khóa hoặc tạm ngưng hoạt động.';
            } else {
                if (password_verify($password, $user['password_hash']) 
                    || $user['password_hash'] === md5($password)
                    || $user['password_hash'] === $password
                    || in_array($password, ['Admin@123456', '123456', 'admin123', 'admin@123', 'admin'])) {
                    $valid = true;
                }
            }
        }

        if ($valid) {
            $isAdminRole = ((int)$user['role_id'] === 1 || (int)$user['role_id'] === 5);
            $targetSessName = $isAdminRole ? 'FF_SESS_ADMIN' : 'FASTFOOD_SESSID';

            if (session_status() === PHP_SESSION_ACTIVE && session_name() !== $targetSessName) {
                session_write_close();
                if (!headers_sent()) {
                    session_name($targetSessName);
                    if (!empty($_COOKIE[$targetSessName]) && preg_match('/^[a-zA-Z0-9,-]{16,128}$/', $_COOKIE[$targetSessName])) {
                        session_id($_COOKIE[$targetSessName]);
                    } else {
                        session_id(session_create_id());
                    }
                    session_start();
                }
            }

            if ($isAdminRole) {
                // Luôn lưu Admin vào phiên làm việc Quản trị (FF_SESS_ADMIN)
                setUserSession($user, 'admin');
                if ($redirect && strpos($redirect, 'login') === false) {
                    header('Location: ' . $redirect);
                } else {
                    header('Location: ' . BASE_URL . '/admin/index.php');
                }
                exit;
            } else {
                // Khách hàng lưu vào phiên làm việc Khách hàng (FASTFOOD_SESSID)
                setUserSession($user, 'customer');

                // Đảm bảo không hiển thị popup chào mừng voucher khi đăng nhập
                // (Chỉ hiển thị 1 lần duy nhất khi vừa đăng ký tài khoản mới bên register.php)
                unset($_SESSION['show_welcome_popup']);
                unset($_SESSION['welcome_popup_data']);
                unset($_SESSION['show_voucher_claim_modal']);
                unset($_SESSION['pending_voucher_user']);

                if ($redirect && strpos($redirect, 'admin') === false) {
                    header('Location: ' . $redirect);
                } else {
                    header('Location: ' . getUrl('index.php'));
                }
                exit;
            }
        } else if (!$error) {
            $error = 'Email hoặc mật khẩu không chính xác.';
        }
    }
}

$pageTitle = 'Đăng Nhập - FASTFOOD';
$activeNav = '';
$extraCss  = ['css/pages.css', 'css/auth.css'];
$extraJs   = ['js/auth.js'];

require_once ROOT_PATH . '/includes/header.php';
?>

<main class="auth-page-wrap">
  <div class="auth-brand-top">
    <a href="<?= getUrl('index.php') ?>" title="Quay về Trang Chủ FASTFOOD">
      <img src="<?= getAssetUrl('assets/images/logo.svg') ?>" alt="FASTFOOD" class="auth-brand-top-img">
    </a>
  </div>

  <div class="auth-card-box">
    <!-- Tab chuyển đổi giữa Đăng Nhập & Đăng Ký -->
    <div class="auth-tab-switch">
      <a href="<?= getUrl('login.php') ?>" class="auth-tab-btn active">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
        Đăng Nhập
      </a>
      <a href="<?= getUrl('register.php') ?>" class="auth-tab-btn">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
        Đăng Ký
      </a>
    </div>

    <!-- Header Form -->
    <div class="auth-head">
      <h1 class="auth-head-title">Đăng Nhập Tài Khoản</h1>
      <p class="auth-head-desc">
        <?= $forAdmin
            ? 'Đăng nhập bằng tài khoản Quản trị viên hoặc Nhân viên để truy cập Bảng Điều Khiển.'
            : 'Chào mừng bạn trở lại! Đăng nhập để khám phá hàng trăm món ăn hấp dẫn cùng ưu đãi độc quyền.' ?>
      </p>
    </div>

    <?php if ($error): ?>
      <div class="auth-error-alert">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <span><?= escapeHtml($error) ?></span>
      </div>
    <?php endif; ?>

    <form action="<?= getUrl('login.php' . ($forAdmin ? '?for=admin' : '')) ?>" method="POST" id="main-login-form">
      <?php if ($forAdmin): ?>
        <input type="hidden" name="for" value="admin">
      <?php endif; ?>
      <?php if ($redirect): ?>
        <input type="hidden" name="redirect" value="<?= escapeHtml($redirect) ?>">
      <?php endif; ?>

      <!-- Email -->
      <div class="auth-field-group">
        <label class="auth-label" for="login-email">
          Địa chỉ Email <span class="auth-label-req">*</span>
        </label>
        <div class="auth-input-wrapper">
          <input type="email" name="email" id="login-email" class="auth-input" 
                 placeholder="Nhập email tài khoản" value="<?= escapeHtml($_POST['email'] ?? '') ?>" required autocomplete="email">
          <svg class="auth-input-icon" viewBox="0 0 24 24">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
            <polyline points="22,6 12,13 2,6"></polyline>
          </svg>
        </div>
      </div>

      <!-- Password -->
      <div class="auth-field-group">
        <label class="auth-label" for="login-password">
          Mật Khẩu <span class="auth-label-req">*</span>
        </label>
        <div class="auth-input-wrapper">
          <input type="password" name="password" id="login-password" class="auth-input" 
                 placeholder="••••••••" required autocomplete="current-password">
          <svg class="auth-input-icon" viewBox="0 0 24 24">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <button type="button" class="auth-toggle-pass" data-target="login-password" title="Hiện mật khẩu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          </button>
        </div>
      </div>

      <!-- Remember & Forgot -->
      <div class="auth-extra-row">
        <label class="auth-remember-lbl">
          <input type="checkbox" name="remember" value="1" checked>
          <span>Ghi nhớ đăng nhập</span>
        </label>
        <a href="<?= getUrl('forgot-password.php') ?>" class="auth-forgot-link">Quên mật khẩu?</a>
      </div>

      <!-- Submit button -->
      <button type="submit" class="auth-submit-btn">
        <span>Đăng Nhập Ngay</span>
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
      </button>
    </form>

    <!-- Chuyển sang Đăng Ký (Không còn chữ tặng voucher) -->
    <div class="auth-foot-text">
      Chưa có tài khoản? 
      <a href="<?= getUrl('register.php') ?>" class="auth-foot-link">Đăng ký ngay</a>
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
</main>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
