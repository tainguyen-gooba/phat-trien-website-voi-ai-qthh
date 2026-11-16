<?php
/**
 * FASTFOOD - Hồ sơ người dùng (User Profile)
 */
require_once __DIR__ . '/config/config.php';
requireLogin();

$currentUser = currentUser();
$db = getDB();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['fullName'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');

    if (mb_strlen($fullName) < 3) {
        $error = 'Họ và tên không hợp lệ.';
    } elseif (!preg_match('/^[0-9+() -]{10,15}$/', $phone)) {
        $error = 'Số điện thoại không hợp lệ.';
    } else {
        $upStmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?");
        $upStmt->execute([$fullName, $phone, $address, $currentUser['id']]);

        $_SESSION['user_name']  = $fullName;
        $_SESSION['user_phone'] = $phone;
        $currentUser = currentUser();
        $message = 'Cập nhật thông tin thành công!';
    }
}

// Lấy thông tin mới nhất từ DB
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$currentUser['id']]);
$user = $stmt->fetch();

$pageTitle = 'Hồ Sơ Của Bạn - FASTFOOD';
$activeNav = 'profile';
$extraCss  = ['css/pages.css'];

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= getUrl('index.php') ?>">Trang chủ</a> <span>/</span> <span>Hồ sơ tài khoản</span>
    </div>
    <h1 class="page-title">Thông Tin Tài Khoản</h1>
  </div>
</div>

<main class="container" style="max-width: 680px; margin: 3rem auto 5rem;">
  <div class="card" style="padding: 2.2rem; background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
    <div style="display: flex; align-items: center; gap: 1.25rem; margin-bottom: 2rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1.5rem;">
      <img src="<?= escapeHtml($user['avatar_url'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=160&auto=format&fit=crop&q=80') ?>" 
           alt="Avatar" style="width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid var(--secondary-color);">
      <div>
        <h2 style="font-size: 1.35rem; font-weight: 800; margin: 0 0 0.35rem;"><?= escapeHtml($user['full_name']) ?></h2>
        <span class="badge badge-secondary"><?= strtoupper($currentUser['role']) ?></span>
        <span style="color: var(--text-muted); font-size: 0.85rem; margin-left: 0.5rem;"><?= escapeHtml($user['email']) ?></span>
      </div>
    </div>

    <?php if ($message): ?>
      <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #16a34a; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600;">
        ✅ <?= escapeHtml($message) ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600;">
        ❌ <?= escapeHtml($error) ?>
      </div>
    <?php endif; ?>

    <form action="<?= getUrl('profile.php') ?>" method="POST">
      <div class="form-group" style="margin-bottom: 1.25rem;">
        <label class="form-label" for="profile-name">Họ và Tên</label>
        <input type="text" name="fullName" id="profile-name" class="form-control" 
               value="<?= escapeHtml($user['full_name']) ?>" required>
      </div>

      <div class="form-group" style="margin-bottom: 1.25rem;">
        <label class="form-label" for="profile-email">Địa chỉ Email</label>
        <input type="email" id="profile-email" class="form-control" 
               value="<?= escapeHtml($user['email']) ?>" readonly style="background: #f8fafc; color: #64748b;">
      </div>

      <div class="form-group" style="margin-bottom: 1.25rem;">
        <label class="form-label" for="profile-phone">Số điện thoại</label>
        <input type="tel" name="phone" id="profile-phone" class="form-control" 
               value="<?= escapeHtml($user['phone']) ?>" required>
      </div>

      <div class="form-group" style="margin-bottom: 1.5rem;">
        <label class="form-label" for="profile-address">Địa chỉ mặc định</label>
        <input type="text" name="address" id="profile-address" class="form-control" 
               value="<?= escapeHtml($user['address'] ?? '') ?>" placeholder="Nhập địa chỉ nhà riêng hoặc cơ quan...">
      </div>

      <div style="display: flex; justify-content: space-between; align-items: center;">
        <button type="submit" class="btn btn-primary btn-lg">
          Lưu Thay Đổi
        </button>
        <a href="<?= getUrl('order-history.php') ?>" class="btn btn-outline">
          Xem Đơn Hàng Của Tôi
        </a>
      </div>
    </form>
  </div>
</main>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

