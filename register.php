<?php
/**
 * FASTFOOD - Đăng ký tài khoản (Register)
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
    $password        = trim($_POST['password'] ?? '');
    $passwordConfirm = trim($_POST['passwordConfirm'] ?? '');

    if (mb_strlen($fullName) < 3) {
        $error = 'Vui lòng nhập họ và tên hợp lệ (tối thiểu 3 ký tự).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Địa chỉ email không đúng định dạng.';
    } elseif (!preg_match('/^[0-9+() -]{10,15}$/', $phone)) {
        $error = 'Số điện thoại không hợp lệ.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Mật khẩu xác nhận không trùng khớp.';
    } else {
        $db = getDB();
        $checkStmt = $db->prepare("SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            $error = 'Email này đã được sử dụng. Vui lòng chọn email khác hoặc đăng nhập.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $insertStmt = $db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status, created_at)
                                        VALUES (2, ?, ?, ?, ?, 'active', NOW())");
            $insertStmt->execute([$fullName, $email, $phone, $hash]);
            $newUserId = $db->lastInsertId();

            setUserSession([
                'id'         => $newUserId,
                'full_name'  => $fullName,
                'email'      => $email,
                'phone'      => $phone,
                'role_id'    => 2,
                'avatar_url' => '',
            ]);

            header('Location: ' . getUrl('index.php'));
            exit;
        }
    }
}

$pageTitle = 'Đăng Ký Tài Khoản - FASTFOOD';
$activeNav = '';
$extraCss  = ['css/pages.css'];

require_once ROOT_PATH . '/includes/header.php';
?>

<main class="auth-page-wrap" style="padding: 4rem 1rem;">
  <div class="auth-form-card" style="max-width: 480px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 2.2rem; box-shadow: 0 10px 35px rgba(0,0,0,0.06);">
    <div style="text-align: center; margin-bottom: 1.75rem;">
      <h1 style="font-size: 1.8rem; font-weight: 900; color: var(--text-main); margin-bottom: 0.35rem;">
        Đăng Ký Tài Khoản
      </h1>
      <p style="font-size: 0.9rem; color: var(--text-secondary);">
        Tạo tài khoản miễn phí để nhận ưu đãi ngập tràn từ FASTFOOD!
      </p>
    </div>

    <?php if ($error): ?>
      <div style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; font-weight: 600;">
        ⚠️ <?= escapeHtml($error) ?>
      </div>
    <?php endif; ?>

    <form action="<?= getUrl('register.php') ?>" method="POST">
      <div class="form-group" style="margin-bottom: 1.1rem;">
        <label class="form-label" for="reg-name">Họ và Tên *</label>
        <input type="text" name="fullName" id="reg-name" class="form-control" 
               placeholder="Nguyễn Văn A" value="<?= escapeHtml($_POST['fullName'] ?? '') ?>" required>
      </div>

      <div class="form-group" style="margin-bottom: 1.1rem;">
        <label class="form-label" for="reg-email">Địa chỉ Email *</label>
        <input type="email" name="email" id="reg-email" class="form-control" 
               placeholder="example@gmail.com" value="<?= escapeHtml($_POST['email'] ?? '') ?>" required>
      </div>

      <div class="form-group" style="margin-bottom: 1.1rem;">
        <label class="form-label" for="reg-phone">Số điện thoại *</label>
        <input type="tel" name="phone" id="reg-phone" class="form-control" 
               placeholder="0912345678" value="<?= escapeHtml($_POST['phone'] ?? '') ?>" required>
      </div>

      <div class="grid grid-cols-2" style="gap: 1rem; margin-bottom: 1.25rem;">
        <div class="form-group">
          <label class="form-label" for="reg-pass">Mật khẩu *</label>
          <input type="password" name="password" id="reg-pass" class="form-control" placeholder="••••••••" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="reg-pass-confirm">Xác nhận mật khẩu *</label>
          <input type="password" name="passwordConfirm" id="reg-pass-confirm" class="form-control" placeholder="••••••••" required>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; font-size: 1.05rem;">
        Đăng Ký Ngay
      </button>
    </form>

    <div style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--text-secondary);">
      Đã có tài khoản? <a href="<?= getUrl('login.php') ?>" style="color: var(--primary-color); font-weight: 700;">Đăng nhập</a>
    </div>
  </div>
</main>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

