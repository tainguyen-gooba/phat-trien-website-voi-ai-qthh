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
                $newUserId = $db->lastInsertId();

                // 3. Tự động cấp Voucher giảm 50.000đ cho khách hàng mới
                $userVoucherCode = 'NEW50K-' . strtoupper(substr(md5($newUserId . uniqid((string)mt_rand(), true)), 0, 6));
                $startDate = date('Y-m-d H:i:s');
                $endDate = date('Y-m-d H:i:s', strtotime('+30 days'));

                try {
                    // Đảm bảo coupon mẫu NEW50K chung tồn tại
                    $checkGlobal = $db->prepare("SELECT id FROM coupons WHERE code = 'NEW50K' LIMIT 1");
                    $checkGlobal->execute();
                    if (!$checkGlobal->fetch()) {
                        $db->prepare("INSERT INTO coupons (code, title, discount_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, start_date, end_date, is_active, created_at)
                                      VALUES ('NEW50K', 'Voucher bạn mới - Giảm ngay 50.000đ', 'fixed', 50000.00, 0.00, 50000.00, 10000, 0, '2026-01-01 00:00:00', '2027-12-31 23:59:59', 1, NOW())")->execute();
                    }

                    // Tạo mã voucher riêng cho user
                    $cpInsert = $db->prepare("INSERT INTO coupons (user_id, code, title, discount_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, start_date, end_date, is_active, created_at)
                                              VALUES (?, ?, 'Voucher chào mừng bạn mới - Giảm 50.000đ', 'fixed', 50000.00, 0.00, 50000.00, 1, 0, ?, ?, 1, NOW())");
                    $cpInsert->execute([$newUserId, $userVoucherCode, $startDate, $endDate]);
                    $couponId = (int)$db->lastInsertId();

                    $_SESSION['applied_coupon'] = [
                        'id'                  => $couponId,
                        'code'                => $userVoucherCode,
                        'title'               => 'Voucher chào mừng bạn mới - Giảm 50.000đ',
                        'discount_type'       => 'fixed',
                        'discount_value'      => 50000.00,
                        'min_order_value'     => 0.00,
                        'max_discount_amount' => 50000.00
                    ];
                } catch (Exception $e) {}

                // 4. Tạo thông báo quà tặng thành viên mới
                try {
                    $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, content, type, is_read, created_at)
                                                VALUES (?, ?, ?, 'promotion', 0, NOW())");
                    $notifStmt->execute([
                        $newUserId,
                        '🎉 Tặng bạn mới Voucher giảm 50.000đ!',
                        'Chào mừng ' . $fullName . ' đến với FASTFOOD! Bạn đã được tặng Voucher giảm trực tiếp 50.000đ (Mã: ' . $userVoucherCode . ' hoặc NEW50K) cho đơn hàng đầu tiên.'
                    ]);
                } catch (Exception $e) {}

                setUserSession([
                    'id'         => $newUserId,
                    'full_name'  => $fullName,
                    'email'      => $email,
                    'phone'      => $phone,
                    'role_id'    => 2,
                    'avatar_url' => '',
                ]);

                $_SESSION['welcome_discount_applied'] = true;

                header('Location: ' . getUrl('index.php'));
                exit;
            }
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

    <!-- Banner Khuyến Mãi Bạn Mới 50k -->
    <div style="background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); border: 1.5px dashed #f97316; border-radius: 12px; padding: 1rem 1.15rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.85rem;">
      <div style="font-size: 2rem; flex-shrink: 0;">🎁</div>
      <div>
        <strong style="color: #ea580c; font-size: 0.95rem; display: block; margin-bottom: 2px;">
          Tặng Ngay Voucher Giảm 50.000đ Cho Khách Hàng Mới
        </strong>
        <p style="font-size: 0.82rem; color: #7c2d12; margin: 0; line-height: 1.35;">
          Đăng ký tài khoản thành viên hôm nay nhận ngay Voucher giảm trực tiếp <strong>50.000 đ</strong> (Mã <strong>NEW50K</strong>) tự động áp dụng ngay cho đơn hàng đầu tiên của bạn!
        </p>
      </div>
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

