<?php
/**
 * FASTFOOD - Customer Voucher API Endpoint (/api/vouchers.php)
 * Xử lý: Lấy danh sách ví, Săn/Lưu voucher, Áp dụng vào giỏ hàng, Hủy áp dụng, Trạng thái sinh nhật
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/helpers/response.php';
require_once ROOT_PATH . '/helpers/auth.php';
require_once ROOT_PATH . '/helpers/voucher.php';

$rawBody = file_get_contents('php://input');
$jsonBody = json_decode($rawBody, true);
$input = is_array($jsonBody) ? $jsonBody : [];

$action = trim($input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '');
$user = currentUser();
$userId = $user ? (int)$user['id'] : 0;
$typeFilter = trim($input['type'] ?? $_GET['type'] ?? $_POST['type'] ?? '');
if ($typeFilter === 'all' || !in_array($typeFilter, ['percentage', 'fixed', 'free_shipping', 'bogo'])) {
    $typeFilter = null;
}

switch ($action) {
    // -------------------------------------------------------------------------
    // 1. Lấy toàn bộ voucher công khai trên Sàn Săn Voucher (Voucher Shop)
    // -------------------------------------------------------------------------
    case 'get_all':
        $vouchers = getAllPublicVouchers($userId, $typeFilter);
        jsonSuccess('Lấy danh sách voucher thành công!', [
            'vouchers'      => $vouchers,
            'is_logged_in'  => $userId > 0,
            'user_tier'     => $userId > 0 ? getUserMemberTier($userId) : null
        ]);
        break;

    // -------------------------------------------------------------------------
    // 2. Lấy toàn bộ voucher trong ví của khách hàng
    // -------------------------------------------------------------------------
    case 'get_wallet':
        if (!$userId) {
            jsonError('Vui lòng đăng nhập để xem ví voucher của bạn.', 401);
        }
        $vouchers = getUserWalletVouchers($userId, $typeFilter);
        $count = getUserVoucherCount($userId);
        jsonSuccess('Lấy danh sách ví voucher thành công!', [
            'vouchers'     => $vouchers,
            'active_count' => $count,
            'user_tier'    => getUserMemberTier($userId)
        ]);
        break;

    // -------------------------------------------------------------------------
    // 3. Khách hàng 1-Click lưu voucher vào ví
    // -------------------------------------------------------------------------
    case 'claim':
        if (!$userId) {
            jsonError('Vui lòng đăng nhập tài khoản để lưu voucher vào ví.', 401);
        }
        $voucherId = (int)($input['voucher_id'] ?? $input['coupon_id'] ?? $_POST['voucher_id'] ?? $_POST['coupon_id'] ?? 0);
        if ($voucherId <= 0) {
            jsonError('Mã ID voucher không hợp lệ.', 400);
        }
        $result = claimPublicVoucher($userId, $voucherId);
        if (!$result['success']) {
            jsonError($result['error'] ?? 'Không thể lưu voucher.');
        }
        jsonSuccess($result['message'], [
            'voucher'      => $result['voucher'] ?? null,
            'wallet_count' => getUserVoucherCount($userId)
        ]);
        break;

    // -------------------------------------------------------------------------
    // 4. Lấy danh sách voucher cho Modal Chọn Voucher tại Cart / Checkout
    // -------------------------------------------------------------------------
    case 'get_eligible':
        $subtotal = (float)($input['subtotal'] ?? $_GET['subtotal'] ?? $_POST['subtotal'] ?? 0);
        $shippingFee = (float)($input['shipping_fee'] ?? $_GET['shipping_fee'] ?? 18000.0);

        $candidates = [];
        if ($userId > 0) {
            $candidates = getUserWalletVouchers($userId);
            // Bổ sung voucher công khai chưa lưu
            $publics = getAllPublicVouchers($userId);
            $existingIds = array_column($candidates, 'id');
            foreach ($publics as $p) {
                if (!in_array($p['id'], $existingIds)) {
                    $candidates[] = $p;
                }
            }
        } else {
            $candidates = getAllPublicVouchers(null);
        }

        $list = [];
        $appliedId = !empty($_SESSION['applied_coupon']['id']) ? (int)$_SESSION['applied_coupon']['id'] : 0;
        $appliedCode = !empty($_SESSION['applied_coupon']['code']) ? strtoupper($_SESSION['applied_coupon']['code']) : '';

        foreach ($candidates as $c) {
            $evaluation = validateAndCalculateVoucher($c, $subtotal, $userId ?: null, $shippingFee);
            $isApplied = ($appliedId > 0 && (int)$c['id'] === $appliedId) || ($appliedCode && strtoupper($c['code']) === $appliedCode);

            $discVal = (float)$c['discount_value'];
            $vType = $c['voucher_type'] ?? ($c['discount_type'] ?? 'percentage');
            $discountBadge = match($vType) {
                'percentage'    => 'GIẢM ' . rtrim(rtrim(number_format($discVal, 1), '0'), '.') . '%',
                'free_shipping' => 'FREESHIP',
                'bogo'          => 'TẶNG MÓN',
                default         => 'GIẢM ' . number_format($discVal, 0, ',', '.') . 'đ'
            };

            $list[] = [
                'id'                      => (int)$c['id'],
                'code'                    => $c['code'],
                'title'                   => $c['title'],
                'voucher_type'            => $vType,
                'discount_badge'          => $discountBadge,
                'discount_value'          => $discVal,
                'min_order'               => (float)($c['min_order_value'] ?? 0),
                'max_discount'            => !empty($c['max_discount_amount']) ? (float)$c['max_discount_amount'] : null,
                'min_member_tier'         => $c['min_member_tier'] ?? 'ALL',
                'end_date'                => !empty($c['end_date']) ? date('d/m/Y', strtotime($c['end_date'])) : 'Không thời hạn',
                'is_eligible'             => $evaluation['valid'],
                'ineligibility_reason'    => $evaluation['valid'] ? '' : ($evaluation['reason'] ?? ''),
                'estimated_discount'      => $evaluation['valid'] ? (float)$evaluation['discount_amount'] : 0,
                'estimated_discount_text' => $evaluation['valid'] ? $evaluation['discount_text'] : '',
                'is_applied'              => $isApplied
            ];
        }

        jsonSuccess('Lấy danh sách voucher áp dụng thành công!', [
            'vouchers'       => $list,
            'applied_coupon' => $_SESSION['applied_coupon'] ?? null,
            'is_logged_in'   => $userId > 0,
            'user_tier'      => $userId > 0 ? getUserMemberTier($userId) : null
        ]);
        break;

    // -------------------------------------------------------------------------
    // 5. Áp dụng voucher vào giỏ hàng / thanh toán
    // -------------------------------------------------------------------------
    case 'apply':
        $code = strtoupper(trim($input['code'] ?? $_POST['code'] ?? ''));
        $voucherId = (int)($input['voucher_id'] ?? $input['coupon_id'] ?? $_POST['voucher_id'] ?? 0);
        $subtotal = (float)($input['subtotal'] ?? $_POST['subtotal'] ?? 0);
        $shippingFee = (float)($input['shipping_fee'] ?? $_POST['shipping_fee'] ?? 18000.0);

        $db = getDB();
        $vc = null;

        if ($voucherId > 0) {
            $stmt = $db->prepare("SELECT * FROM vouchers WHERE id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$voucherId]);
            $vc = $stmt->fetch();
        } elseif ($code !== '') {
            $stmt = $db->prepare("SELECT * FROM vouchers WHERE UPPER(code) = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$code]);
            $vc = $stmt->fetch();

            if (!$vc) {
                // Fallback coupons table
                $stmtC = $db->prepare("SELECT * FROM coupons WHERE UPPER(code) = ? AND is_active = 1 LIMIT 1");
                $stmtC->execute([$code]);
                $vc = $stmtC->fetch();
            }
        }

        if (!$vc) {
            jsonError('Mã ưu đãi không tồn tại hoặc đã ngừng hoạt động.');
        }

        // Tự động tính subtotal từ session nếu chưa có
        if ($subtotal <= 0 && !empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $item) {
                $subtotal += (float)$item['price'] * (int)$item['qty'];
            }
        }

        $eval = validateAndCalculateVoucher($vc, $subtotal, $userId ?: null, $shippingFee);
        if (!$eval['valid']) {
            jsonError($eval['reason'] ?? 'Không đủ điều kiện áp dụng mã này.');
        }

        // Kiểm tra voucher cấp riêng cho user
        if (!empty($vc['user_id'])) {
            if (!$userId) {
                jsonError('Mã giảm giá này dành riêng cho tài khoản được cấp. Vui lòng đăng nhập để sử dụng.');
            } elseif ((int)$vc['user_id'] !== $userId) {
                jsonError('Mã giảm giá này chỉ dành riêng cho chủ sở hữu tài khoản được cấp.');
            }
        }

        // Kiểm tra voucher chào mừng NEW50
        $voucherCode = strtoupper($vc['code']);
        if ($voucherCode === 'NEW50' || $voucherCode === 'NEW50K' || strpos($voucherCode, 'NEW50K-') === 0) {
            if ($userId <= 0) {
                jsonError('Vui lòng đăng nhập và xác thực thông tin bạn mới để sử dụng Voucher 50.000đ.');
            }
            $claimChk = $db->prepare("SELECT id FROM customer_voucher_claims WHERE user_id = ? LIMIT 1");
            $claimChk->execute([$userId]);
            if (!$claimChk->fetch()) {
                jsonError('Bạn chưa nhập thông tin xác thực bạn mới nên chưa nhận được Voucher 50.000đ này.');
            }
            $ordCheck = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND order_status != 'cancelled'");
            $ordCheck->execute([$userId]);
            if ((int)$ordCheck->fetchColumn() > 0) {
                jsonError('Voucher chào mừng ' . $voucherCode . ' chỉ áp dụng cho đơn hàng đầu tiên của khách hàng mới.');
            }
        }

        // Tự động lưu vào ví của khách hàng nếu chưa có trong ví
        if ($userId > 0 && empty($vc['user_id'])) {
            @claimPublicVoucher($userId, (int)$vc['id']);
        }

        $_SESSION['applied_coupon'] = [
            'id'                  => (int)$vc['id'],
            'code'                => $vc['code'],
            'title'               => $vc['title'],
            'voucher_type'        => $vc['voucher_type'] ?? ($vc['discount_type'] ?? 'percentage'),
            'discount_type'       => $vc['discount_type'] ?? ($vc['voucher_type'] ?? 'percentage'),
            'discount_value'      => (float)$vc['discount_value'],
            'min_order_value'     => (float)$vc['min_order_value'],
            'max_discount_amount' => (float)$vc['max_discount_amount'],
            'discount_amount'     => (float)$eval['discount_amount']
        ];

        jsonSuccess('Áp dụng voucher ' . $vc['code'] . ' thành công!', [
            'applied_coupon'  => $_SESSION['applied_coupon'],
            'discount_amount' => (float)$eval['discount_amount'],
            'discount_text'   => $eval['discount_text']
        ]);
        break;

    // -------------------------------------------------------------------------
    // 6. Gỡ bỏ voucher khỏi giỏ hàng / thanh toán
    // -------------------------------------------------------------------------
    case 'remove':
        $prevCode = $_SESSION['applied_coupon']['code'] ?? '';
        unset($_SESSION['applied_coupon']);
        jsonSuccess('Đã hủy áp dụng mã ưu đãi ' . ($prevCode ? "({$prevCode})" : '') . '.', [
            'applied_coupon' => null,
            'discount_amount' => 0
        ]);
        break;

    // -------------------------------------------------------------------------
    // 7. Lấy trạng thái sinh nhật của khách hàng
    // -------------------------------------------------------------------------
    case 'birthday_status':
        if (!$userId) {
            jsonError('Vui lòng đăng nhập.', 401);
        }
        $status = getUserBirthdayStatus($userId);
        jsonSuccess('Lấy trạng thái sinh nhật thành công!', $status);
        break;

    // -------------------------------------------------------------------------
    // 8. Tự động nhận quà sinh nhật nếu hôm nay là sinh nhật
    // -------------------------------------------------------------------------
    case 'claim_birthday':
        if (!$userId) {
            jsonError('Vui lòng đăng nhập tài khoản để nhận quà sinh nhật.', 401);
        }
        $gift = checkAndGiftBirthdayVoucher($userId);
        if ($gift) {
            jsonSuccess("Chúc mừng sinh nhật! Bạn đã nhận được voucher {$gift['code']} giảm {$gift['discount_percent']}%!", $gift);
        } else {
            $status = getUserBirthdayStatus($userId);
            if (!$status['has_dob']) {
                jsonError('Bạn chưa cập nhật ngày sinh trong hồ sơ cá nhân.');
            } elseif (!$status['is_birthday_today']) {
                jsonError('Hôm nay chưa phải ngày sinh nhật của bạn (' . $status['formatted_dob'] . '). Hãy quay lại vào đúng ngày sinh nhé!');
            } elseif ($status['received_this_year']) {
                jsonSuccess('Bạn đã nhận voucher sinh nhật của năm nay rồi! Hẹn gặp lại bạn vào sinh nhật năm sau.', $status['birthday_voucher']);
            } else {
                jsonError('Không thể tạo voucher sinh nhật. Vui lòng thử lại sau.');
            }
        }
        break;

    default:
        jsonError('Hành động không hợp lệ.', 400);
}
