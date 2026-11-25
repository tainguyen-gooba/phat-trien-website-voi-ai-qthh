<?php
/**
 * FASTFOOD - Helper Xử Lý Quên Mật Khẩu & Xác Thực OTP / Token
 * File: helpers/password_reset.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

/**
 * Ẩn bớt ký tự email để bảo mật thông tin khi hiển thị
 * VD: nguyenvana@gmail.com -> ngu***@gmail.com
 */
function maskEmail(string $email): string {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    $name = $parts[0];
    $domain = $parts[1];
    
    $len = mb_strlen($name);
    if ($len <= 3) {
        $masked = mb_substr($name, 0, 1) . '***';
    } else {
        $visible = min(3, (int)floor($len / 2));
        $masked = mb_substr($name, 0, $visible) . str_repeat('*', max(3, $len - $visible));
    }
    return $masked . '@' . $domain;
}

/**
 * Khởi tạo yêu cầu khôi phục mật khẩu:
 * - Kiểm tra email tồn tại
 * - Tạo mã OTP 6 số ngẫu nhiên
 * - Tạo mã Token 64 ký tự duy nhất
 * - Hạn sử dụng: 15 phút
 */
function createPasswordReset(string $email): array {
    $email = strtolower(trim($email));
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Địa chỉ email không đúng định dạng.'];
    }

    $db = getDB();
    $uStmt = $db->prepare("SELECT id, email, full_name, status FROM users WHERE LOWER(email) = ? LIMIT 1");
    $uStmt->execute([$email]);
    $user = $uStmt->fetch();

    if (!$user) {
        return [
            'success' => false, 
            'error'   => 'Không tìm thấy tài khoản nào gắn với email "' . escapeHtml($email) . '". Vui lòng kiểm tra lại.'
        ];
    }

    if (in_array($user['status'], ['inactive', 'banned'])) {
        return [
            'success' => false, 
            'error'   => 'Tài khoản này đã bị khóa hoặc tạm ngưng hoạt động. Vui lòng liên hệ CSKH để được trợ giúp.'
        ];
    }

    // Đánh dấu các mã OTP cũ chưa sử dụng của email này thành hết hiệu lực
    $expireOldStmt = $db->prepare("UPDATE password_resets SET is_used = 1 WHERE LOWER(email) = ? AND is_used = 0");
    $expireOldStmt->execute([$email]);

    // Tạo mã OTP 6 số
    $otpCode = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    // Tạo token 64 ký tự hex bảo mật
    $token = bin2hex(random_bytes(32));
    // Thời hạn 15 phút
    $expiresAt = date('Y-m-d H:i:s', time() + (15 * 60));

    $insStmt = $db->prepare("
        INSERT INTO password_resets (user_id, email, otp_code, token, expires_at, is_used, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    $insStmt->execute([
        $user['id'],
        $user['email'],
        $otpCode,
        $token,
        $expiresAt
    ]);

    $resetUrl = getUrl('reset-password.php?token=' . $token);

    return [
        'success'      => true,
        'user_id'      => (int)$user['id'],
        'email'        => $user['email'],
        'masked_email' => maskEmail($user['email']),
        'full_name'    => $user['full_name'],
        'otp_code'     => $otpCode,
        'token'        => $token,
        'expires_at'   => $expiresAt,
        'reset_url'    => $resetUrl,
        'message'      => 'Mã xác thực OTP đã được gửi thành công!'
    ];
}

/**
 * Xác thực mã OTP 6 số của email
 */
function verifyPasswordResetOTP(string $email, string $otp): array {
    $email = strtolower(trim($email));
    $otp   = trim($otp);

    if (!$email || !$otp) {
        return ['success' => false, 'error' => 'Vui lòng nhập đầy đủ email và mã OTP.'];
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM password_resets 
        WHERE LOWER(email) = ? 
          AND otp_code = ? 
          AND is_used = 0 
          AND expires_at > NOW() 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$email, $otp]);
    $reset = $stmt->fetch();

    if (!$reset) {
        // Kiểm tra xem mã có bị hết hạn hay sai
        $checkOld = $db->prepare("
            SELECT * FROM password_resets 
            WHERE LOWER(email) = ? AND otp_code = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $checkOld->execute([$email, $otp]);
        $oldRecord = $checkOld->fetch();

        if ($oldRecord) {
            if ((int)$oldRecord['is_used'] === 1) {
                return ['success' => false, 'error' => 'Mã OTP này đã được sử dụng. Vui lòng yêu cầu mã mới.'];
            }
            if (strtotime($oldRecord['expires_at']) <= time()) {
                return ['success' => false, 'error' => 'Mã OTP đã hết thời gian hiệu lực (15 phút). Vui lòng gửi lại mã mới.'];
            }
        }
        return ['success' => false, 'error' => 'Mã OTP không chính xác. Vui lòng kiểm tra kỹ 6 chữ số.'];
    }

    return [
        'success'   => true,
        'token'     => $reset['token'],
        'email'     => $reset['email'],
        'user_id'   => (int)$reset['user_id'],
        'reset_url' => getUrl('reset-password.php?token=' . $reset['token'])
    ];
}

/**
 * Kiểm tra tính hợp lệ của Token đặt lại mật khẩu
 */
function verifyPasswordResetToken(string $token): array {
    $token = trim($token);
    if (!$token) {
        return ['success' => false, 'error' => 'Mã token khôi phục không hợp lệ hoặc bị thiếu.'];
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name, u.email as user_email, u.status as user_status 
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token = ? 
          AND pr.is_used = 0 
          AND pr.expires_at > NOW() 
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        // Kiểm tra nguyên nhân
        $chk = $db->prepare("SELECT * FROM password_resets WHERE token = ? LIMIT 1");
        $chk->execute([$token]);
        $rec = $chk->fetch();

        if ($rec) {
            if ((int)$rec['is_used'] === 1) {
                return ['success' => false, 'error' => 'Liên kết khôi phục này đã được sử dụng trước đó.'];
            }
            if (strtotime($rec['expires_at']) <= time()) {
                return ['success' => false, 'error' => 'Liên kết khôi phục đã hết thời gian hiệu lực (15 phút).'];
            }
        }
        return ['success' => false, 'error' => 'Liên kết đặt lại mật khẩu không tồn tại hoặc không hợp lệ.'];
    }

    return [
        'success' => true,
        'reset'   => $reset
    ];
}

/**
 * Tiến hành cập nhật mật khẩu mới cho tài khoản
 */
function resetUserPassword(string $token, string $newPassword, string $confirmPassword): array {
    $token = trim($token);
    $newPassword = (string)$newPassword;
    $confirmPassword = (string)$confirmPassword;

    if (mb_strlen($newPassword) < 6) {
        return ['success' => false, 'error' => 'Mật khẩu mới phải có tối thiểu 6 ký tự.'];
    }

    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'error' => 'Mật khẩu xác nhận không khớp với mật khẩu mới.'];
    }

    $check = verifyPasswordResetToken($token);
    if (!$check['success']) {
        return $check;
    }

    $reset = $check['reset'];
    $userId = (int)$reset['user_id'];
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $db = getDB();
    $db->beginTransaction();
    try {
        // Cập nhật mật khẩu mới vào bảng users
        $uStmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $uStmt->execute([$newHash, $userId]);

        // Đánh dấu token này đã được sử dụng
        $prStmt = $db->prepare("UPDATE password_resets SET is_used = 1 WHERE token = ?");
        $prStmt->execute([$token]);

        $db->commit();

        return [
            'success'   => true,
            'message'   => 'Đặt lại mật khẩu thành công! Bây giờ bạn có thể đăng nhập bằng mật khẩu mới.',
            'email'     => $reset['user_email'],
            'full_name' => $reset['full_name']
        ];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'error' => 'Có lỗi xảy ra khi lưu mật khẩu: ' . $e->getMessage()];
    }
}
