<?php
/**
 * FASTFOOD - API Xác thực thông tin khách hàng & Cấp Voucher Chào Mừng 50.000đ
 * Bao gồm kiểm tra 4 tầng chống trùng lặp (Anti-Duplication Validation)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/helpers/response.php';
require_once ROOT_PATH . '/helpers/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = currentUser();
if (!$user || empty($user['id'])) {
    jsonError('Vui lòng đăng nhập để thực hiện thao tác này.', 401);
}

$userId = (int)$user['id'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: [];

$action = trim($input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '');

// 1. Xử lý hành động Bỏ qua / Đóng modal không nhận voucher
if ($action === 'skip_welcome' || $action === 'skip' || $action === 'dismiss') {
    unset($_SESSION['show_voucher_claim_modal']);
    unset($_SESSION['pending_voucher_user']);
    unset($_SESSION['show_welcome_popup']);
    unset($_SESSION['welcome_popup_data']);

    jsonSuccess('Đã bỏ qua nhận voucher chào mừng. Bạn có thể sử dụng website bình thường.', [
        'skipped' => true,
        'user_id' => $userId
    ]);
}

// 2. Tiếp nhận dữ liệu đăng ký nhận voucher
$fullName = trim($input['fullName'] ?? $input['full_name'] ?? $_POST['fullName'] ?? $_POST['full_name'] ?? $user['full_name'] ?? '');
$phone    = trim($input['phone'] ?? $_POST['phone'] ?? $user['phone'] ?? '');
$address  = trim($input['address'] ?? $_POST['address'] ?? '');
$dob      = trim($input['date_of_birth'] ?? $input['dob'] ?? $_POST['date_of_birth'] ?? $_POST['dob'] ?? '');

// 3. Kiểm tra định dạng đầu vào cơ bản
if (mb_strlen($fullName) < 2) {
    jsonError('Họ và tên không hợp lệ (tối thiểu 2 ký tự).', 400);
}

// Chuẩn hóa số điện thoại
$cleanPhone = preg_replace('/[^0-9]/', '', $phone);
if (strpos($phone, '+84') === 0) {
    $cleanPhone = '0' . substr($cleanPhone, 2);
}
if (strlen($cleanPhone) < 9 || strlen($cleanPhone) > 11) {
    jsonError('Số điện thoại không hợp lệ (yêu cầu từ 9 đến 11 chữ số).', 400);
}

// Kiểm tra Ngày sinh hợp lệ (YYYY-MM-DD, không ở tương lai, năm >= 1920)
if ($dob === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dob, $dobMatches)) {
    jsonError('Vui lòng chọn Ngày sinh hợp lệ.', 400);
}
if (!checkdate((int)$dobMatches[2], (int)$dobMatches[3], (int)$dobMatches[1])) {
    jsonError('Ngày sinh không tồn tại trên lịch. Vui lòng kiểm tra lại.', 400);
}
if (strtotime($dob) > strtotime(date('Y-m-d'))) {
    jsonError('Ngày sinh không thể là một ngày trong tương lai.', 400);
}
if (strtotime($dob) < strtotime('1920-01-01')) {
    jsonError('Năm sinh không hợp lệ (tối thiểu từ năm 1920).', 400);
}

if (mb_strlen($address) < 5) {
    jsonError('Vui lòng nhập địa chỉ giao hàng chi tiết (tối thiểu 5 ký tự).', 400);
}

$db = getDB();

// ===================================================================
// KIỂM TRA CHỐNG TRÙNG LẶP (ANTI-DUPLICATION VALIDATION)
// ===================================================================

// Tier 1: Kiểm tra tài khoản hiện tại đã nhận voucher chào mừng chưa
$tier1Claims = $db->prepare("SELECT id FROM customer_voucher_claims WHERE user_id = ? LIMIT 1");
$tier1Claims->execute([$userId]);
if ($tier1Claims->fetch()) {
    jsonError('Tài khoản của bạn đã nhận voucher chào mừng 50.000đ trước đó. Mỗi tài khoản chỉ được nhận ưu đãi 1 lần duy nhất.', 400);
}

$tier1Coupons = $db->prepare("SELECT id FROM coupons WHERE user_id = ? AND (code LIKE 'NEW50K%' OR code LIKE 'NEW50%') LIMIT 1");
$tier1Coupons->execute([$userId]);
if ($tier1Coupons->fetch()) {
    jsonError('Tài khoản của bạn đã sở hữu voucher chào mừng bạn mới trên hệ thống.', 400);
}

// Tier 3: Kiểm tra số điện thoại có bị trùng lặp nhận voucher không
$tier3Claims = $db->prepare("SELECT id, user_id FROM customer_voucher_claims 
    WHERE (phone = ? OR phone = ? OR REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+84', '0') = ?) 
      AND user_id != ? LIMIT 1");
$tier3Claims->execute([$phone, $cleanPhone, $cleanPhone, $userId]);
if ($tier3Claims->fetch()) {
    jsonError('Số điện thoại này đã được sử dụng để nhận voucher chào mừng trên hệ thống.', 400);
}

// Tier 4: Kiểm tra lịch sử đơn hàng (Voucher chào mừng chỉ dành cho đơn hàng đầu tiên)
$tier4Orders = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND order_status != 'cancelled'");
$tier4Orders->execute([$userId]);
if ((int)$tier4Orders->fetchColumn() > 0) {
    jsonError('Voucher chào mừng 50.000đ chỉ áp dụng cho đơn hàng đầu tiên của khách hàng mới.', 400);
}

// ===================================================================
// CẤP VOUCHER VÀ GHI NHẬN HỆ THỐNG (TRANSACTION)
// ===================================================================
try {
    $db->beginTransaction();

    // 1. Cập nhật thông tin khách hàng và ngày sinh trong bảng users
    $updateUser = $db->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, date_of_birth = ?, updated_at = NOW() WHERE id = ?");
    $updateUser->execute([$fullName, $cleanPhone, $address, $dob, $userId]);

    $claimIdentityKey = "DOB:{$dob}:U{$userId}";

    // 2. Tạo mã voucher ngẫu nhiên độc nhất dạng NEW50K-XXXXXX
    $userVoucherCode = 'NEW50K-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    while (true) {
        $chkCode = $db->prepare("SELECT id FROM coupons WHERE code = ? LIMIT 1");
        $chkCode->execute([$userVoucherCode]);
        if (!$chkCode->fetch()) {
            break;
        }
        $userVoucherCode = 'NEW50K-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    $startDate = date('Y-m-d H:i:s');
    $endDate   = date('Y-m-d H:i:s', strtotime('+30 days'));

    // 3. Thêm bản ghi voucher giảm cố định 50.000đ vào bảng coupons
    $insertCoupon = $db->prepare("INSERT INTO coupons 
        (user_id, code, title, discount_type, voucher_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, min_member_tier, start_date, end_date, is_active, created_at)
        VALUES (?, ?, 'Voucher chào mừng bạn mới - Giảm ngay 50.000đ', 'fixed', 'fixed', 50000.00, 0.00, 50000.00, 1, 0, 'ALL', ?, ?, 1, NOW())");
    $insertCoupon->execute([$userId, $userVoucherCode, $startDate, $endDate]);
    $couponId = (int)$db->lastInsertId();

    // 3b. Đồng bộ vào bảng vouchers & ví user_vouchers để hiển thị trong Voucher của tôi & Modal Giỏ hàng
    try {
        $voucherId = $couponId;
        $chkV = $db->prepare("SELECT id FROM vouchers WHERE id = ? LIMIT 1");
        $chkV->execute([$couponId]);
        $welcomeDesc = 'Ưu đãi chào mừng bạn mới sau khi xác thực thông tin - Giảm 50.000đ cho đơn đầu tiên.';
        if ($chkV->fetch()) {
            $insV = $db->prepare("INSERT INTO vouchers (code, title, description, voucher_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, user_limit, min_member_tier, is_public, start_date, end_date, is_active)
                                  VALUES (?, 'Voucher chào mừng bạn mới - Giảm ngay 50.000đ', ?, 'fixed', 50000.00, 0.00, 50000.00, 1, 0, 1, 'ALL', 0, ?, ?, 1)");
            $insV->execute([$userVoucherCode, $welcomeDesc, $startDate, $endDate]);
            $voucherId = (int)$db->lastInsertId();
        } else {
            $insV = $db->prepare("INSERT INTO vouchers (id, code, title, description, voucher_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, user_limit, min_member_tier, is_public, start_date, end_date, is_active)
                                  VALUES (?, ?, 'Voucher chào mừng bạn mới - Giảm ngay 50.000đ', ?, 'fixed', 50000.00, 0.00, 50000.00, 1, 0, 1, 'ALL', 0, ?, ?, 1)");
            $insV->execute([$couponId, $userVoucherCode, $welcomeDesc, $startDate, $endDate]);
        }

        $insUV = $db->prepare("INSERT INTO user_vouchers (user_id, voucher_id, source, is_used, expires_at) VALUES (?, ?, 'admin', 0, ?)");
        $insUV->execute([$userId, $voucherId, $endDate]);
    } catch (Exception $e) {}

    // 4. Ghi nhận vào bảng kiểm soát trùng lặp customer_voucher_claims
    $insertClaim = $db->prepare("INSERT INTO customer_voucher_claims 
        (user_id, full_name, phone, identity_number, address, coupon_id, coupon_code, claimed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $insertClaim->execute([$userId, $fullName, $cleanPhone, $claimIdentityKey, $address, $couponId, $userVoucherCode]);

    // 5. Gửi thông báo trong hệ thống
    try {
        $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, content, type, is_read, created_at)
                                   VALUES (?, ?, ?, 'promotion', 0, NOW())");
        $notifStmt->execute([
            $userId,
            '🎁 Bạn vừa nhận Voucher Chào Mừng 50.000đ!',
            'Chúc mừng ' . $fullName . ' đã kích hoạt thành công Voucher 50.000đ (Mã: ' . $userVoucherCode . '). Mã đã được áp dụng sẵn vào giỏ hàng cho đơn hàng đầu tiên của bạn.'
        ]);
    } catch (Exception $e) {}

    $db->commit();

    // 6. Tự động kích hoạt voucher vào phiên giỏ hàng của người dùng
    $_SESSION['applied_coupon'] = [
        'id'                  => $couponId,
        'code'                => $userVoucherCode,
        'title'               => 'Voucher chào mừng bạn mới - Giảm ngay 50.000đ',
        'voucher_type'        => 'fixed',
        'discount_type'       => 'fixed',
        'discount_value'      => 50000.00,
        'min_order_value'     => 0.00,
        'max_discount_amount' => 50000.00
    ];

    // Cập nhật thông tin trong session người dùng (cả customer_user và legacy keys)
    $_SESSION['user_name']  = $fullName;
    $_SESSION['user_phone'] = $cleanPhone;
    if (!empty($_SESSION['customer_user']) && is_array($_SESSION['customer_user'])) {
        $_SESSION['customer_user']['full_name'] = $fullName;
        $_SESSION['customer_user']['name']      = $fullName;
        $_SESSION['customer_user']['phone']     = $cleanPhone;
    }

    // Xóa cờ modal để không hiển thị lại sau khi đã nhận thành công
    unset($_SESSION['show_voucher_claim_modal']);
    unset($_SESSION['pending_voucher_user']);
    unset($_SESSION['show_welcome_popup']);
    unset($_SESSION['welcome_popup_data']);

    jsonSuccess('Xác thực thành công! Bạn đã nhận Voucher giảm 50.000đ.', [
        'voucher' => [
            'id'             => $couponId,
            'code'           => $userVoucherCode,
            'title'          => 'Voucher chào mừng bạn mới - Giảm ngay 50.000đ',
            'discount_type'  => 'fixed',
            'discount_value' => 50000,
            'formatted'      => '50.000đ',
            'end_date'       => $endDate
        ]
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonError('Lỗi xử lý hệ thống: ' . $e->getMessage(), 500);
}
