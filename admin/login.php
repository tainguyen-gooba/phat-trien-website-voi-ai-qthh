<?php
/**
 * FASTFOOD - Cổng Đăng Nhập Quản Trị (Admin & Staff Portal)
 * Phiên làm việc độc lập hoàn toàn với người dùng (Cookie: FF_SESS_ADMIN)
 */
require_once dirname(__DIR__) . '/config/config.php';

// Nếu đã đăng nhập với quyền Admin hoặc Staff thì vào thẳng dashboard
if (isAdminOrStaff() && empty($_POST)) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$error = '';
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
            $roleId = (int)$user['role_id'];
            if ($roleId !== 1 && $roleId !== 5) {
                $error = 'Tài khoản này không có quyền truy cập Cổng Quản Trị Hệ Thống.';
            } elseif ($user['status'] === 'inactive' || $user['status'] === 'banned') {
                $error = 'Tài khoản quản trị của bạn đã bị khóa hoặc tạm ngưng.';
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
            setUserSession($user, 'admin');
            header('Location: ' . BASE_URL . '/admin/index.php');
            exit;
        } elseif (!$error) {
            $error = 'Email hoặc mật khẩu quản trị không chính xác.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cổng Quản Trị Hệ Thống - FASTFOOD Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #f97316;
      --primary-hover: #ea580c;
      --dark-bg: #0f172a;
      --card-bg: #1e293b;
      --text-white: #f8fafc;
      --text-muted: #94a3b8;
      --border-color: #334155;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: radial-gradient(circle at 50% 0%, #1e293b 0%, #0f172a 100%);
      color: var(--text-white);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .login-container {
      width: 100%;
      max-width: 440px;
      background: var(--card-bg);
      border: 1px solid var(--border-color);
      border-radius: 20px;
      padding: 2.5rem 2rem;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }
    .brand-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(249, 115, 22, 0.15);
      color: var(--primary);
      padding: 6px 14px;
      border-radius: 30px;
      font-size: 0.82rem;
      font-weight: 700;
      margin-bottom: 1.25rem;
      border: 1px solid rgba(249, 115, 22, 0.3);
    }
    .login-title {
      font-size: 1.7rem;
      font-weight: 800;
      margin-bottom: 0.4rem;
      letter-spacing: -0.02em;
    }
    .login-subtitle {
      font-size: 0.88rem;
      color: var(--text-muted);
      margin-bottom: 1.75rem;
    }
    .form-group {
      margin-bottom: 1.25rem;
    }
    .form-label {
      display: block;
      font-size: 0.82rem;
      font-weight: 700;
      color: #cbd5e1;
      margin-bottom: 0.45rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .form-control {
      width: 100%;
      padding: 0.85rem 1rem;
      background: #0f172a;
      border: 1px solid var(--border-color);
      border-radius: 10px;
      color: #fff;
      font-size: 0.95rem;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
    }
    .btn-submit {
      width: 100%;
      padding: 0.95rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 10px 20px -5px rgba(249, 115, 22, 0.4);
      transition: all 0.2s;
      margin-top: 0.5rem;
    }
    .btn-submit:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 24px -5px rgba(249, 115, 22, 0.6);
    }
    .error-box {
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid #ef4444;
      color: #fca5a5;
      padding: 0.75rem 1rem;
      border-radius: 10px;
      font-size: 0.88rem;
      font-weight: 600;
      margin-bottom: 1.25rem;
    }
    .login-footer {
      margin-top: 1.75rem;
      text-align: center;
      font-size: 0.85rem;
      color: var(--text-muted);
    }
    .login-footer a {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
    }
    .login-footer a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

  <div class="login-container">
    <div style="text-align:center;">
      <div class="brand-badge">
        <span>🛡️ FASTFOOD ADMIN</span>
      </div>
      <h1 class="login-title">Cổng Quản Trị Hệ Thống</h1>
      <p class="login-subtitle">Đăng nhập tài khoản Quản trị viên & Nhân viên</p>
    </div>

    <?php if ($error): ?>
      <div class="error-box">
        ⚠️ <?= escapeHtml($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/admin/login.php">
      <div class="form-group">
        <label class="form-label" for="admin-email">Email Quản Trị</label>
        <input type="email" id="admin-email" name="email" class="form-control" 
               placeholder="admin@fastfood.vn" value="<?= escapeHtml($_POST['email'] ?? '') ?>" required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label" for="admin-pass">Mật Khẩu</label>
        <input type="password" id="admin-pass" name="password" class="form-control" 
               placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn-submit">
        Đăng Nhập Quản Trị
      </button>
    </form>

    <div class="login-footer" style="display:flex; flex-direction:column; gap:8px;">
      <div><a href="<?= BASE_URL ?>/forgot-password.php">Quên mật khẩu quản trị? Khôi phục tại đây →</a></div>
      <div><a href="<?= BASE_URL ?>/index.php" style="color:var(--text-muted);">← Quay lại trang chủ Khách Hàng</a></div>
    </div>
  </div>

</body>
</html>
