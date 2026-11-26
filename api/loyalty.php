<?php
/**
 * FASTFOOD - Customer Loyalty & Membership AJAX Endpoint
 * File: api/loyalty.php
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/helpers/loyalty.php';

// 1. Authentication Check (Return 401 JSON, do NOT redirect)
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Vui lòng đăng nhập để thực hiện thao tác này.',
        'message' => 'Vui lòng đăng nhập để thực hiện thao tác này.',
        'code'    => 401
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUser = currentUser();
$userId = (int)($currentUser['id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Phiên đăng nhập không hợp lệ.',
        'message' => 'Phiên đăng nhập không hợp lệ.',
        'code'    => 401
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Parse Request Input (Supports JSON and Form-urlencoded)
$rawBody = file_get_contents('php://input');
if (($rawBody === false || trim($rawBody) === '') && PHP_SAPI === 'cli') {
    $rawBody = @file_get_contents('php://stdin');
}
$jsonBody = json_decode($rawBody, true);
$input = is_array($jsonBody) ? $jsonBody : [];

$action = trim($input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    // -------------------------------------------------------------------------
    // ACTION: exchange_voucher
    // -------------------------------------------------------------------------
    case 'exchange_voucher':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error'   => 'Phương thức không được hỗ trợ (chỉ chấp nhận POST).',
                'message' => 'Phương thức không được hỗ trợ (chỉ chấp nhận POST).',
                'code'    => 405
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $packageId = isset($input['package_id']) ? (int)$input['package_id'] : (int)($_POST['package_id'] ?? 0);
        $allowedPackages = array_keys(getVoucherPackages());

        if (!in_array($packageId, $allowedPackages, true)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Gói voucher không hợp lệ. Vui lòng chọn gói quà 1, 2 hoặc 3.',
                'message' => 'Gói voucher không hợp lệ. Vui lòng chọn gói quà 1, 2 hoặc 3.',
                'code'    => 400
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = exchangePointsForVoucher($userId, $packageId);

        if (!$result['success']) {
            http_response_code(400);
            $errText = $result['error'] ?? 'Không thể đổi voucher vào lúc này.';
            echo json_encode([
                'success' => false,
                'error'   => $errText,
                'message' => $errText,
                'code'    => 400
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $settings = getLoyaltySettings();
        $rate = (float)($settings['point_to_vnd_rate'] ?? 100.0);
        $newBalance = (int)$result['new_balance'];
        require_once ROOT_PATH . '/helpers/voucher.php';
        $walletCount = getUserVoucherCount($userId);

        http_response_code(200);
        echo json_encode([
            'success'         => true,
            'message'         => 'Đổi voucher thành công! Mã ưu đãi đã được lưu vào Ví Voucher của bạn.',
            'coupon_id'       => $result['coupon_id'],
            'coupon_code'     => $result['coupon_code'],
            'discount_amount' => (float)$result['discount_amount'],
            'points_deducted' => (int)$result['points_deducted'],
            'new_balance'     => $newBalance,
            'new_balance_vnd' => $newBalance * $rate,
            'wallet_count'    => $walletCount,
            'data'            => [
                'coupon_id'       => $result['coupon_id'],
                'coupon_code'     => $result['coupon_code'],
                'discount_amount' => (float)$result['discount_amount'],
                'points_deducted' => (int)$result['points_deducted'],
                'new_balance'     => $newBalance,
                'new_balance_vnd' => $newBalance * $rate,
                'wallet_count'    => $walletCount
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;

    // -------------------------------------------------------------------------
    // ACTION: get_user_summary
    // -------------------------------------------------------------------------
    case 'get_user_summary':
        syncUserTier($userId);

        $db = getDB();
        $stmt = $db->prepare("SELECT id, full_name, email, member_tier, points_balance, total_spent FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error'   => 'Người dùng không tồn tại.',
                'message' => 'Người dùng không tồn tại.',
                'code'    => 404
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $tiers = getMembershipTiers();
        $currentTierCode = strtoupper($user['member_tier'] ?? 'BRONZE');
        $currentTier = $tiers[$currentTierCode] ?? $tiers['BRONZE'];
        $totalSpent = (float)$user['total_spent'];
        $pointsBalance = (int)$user['points_balance'];

        $tierOrder = ['BRONZE', 'SILVER', 'GOLD', 'DIAMOND'];
        $currentIndex = array_search($currentTierCode, $tierOrder, true);
        if ($currentIndex === false) $currentIndex = 0;
        $isMaxTier = ($currentIndex >= count($tierOrder) - 1);

        $nextTierCode = !$isMaxTier ? $tierOrder[$currentIndex + 1] : null;
        $nextTier = $nextTierCode ? ($tiers[$nextTierCode] ?? null) : null;

        if (!$isMaxTier && $nextTier) {
            $nextMin = (float)$nextTier['min_spent'];
            $curMin = (float)$currentTier['min_spent'];
            $remaining = max(0.0, $nextMin - $totalSpent);
            $span = max(1.0, $nextMin - $curMin);
            $progressPercent = min(100.0, max(0.0, round((($totalSpent - $curMin) / $span) * 100, 1)));
        } else {
            $remaining = 0.0;
            $progressPercent = 100.0;
        }

        $settings = getLoyaltySettings();
        $rate = (float)($settings['point_to_vnd_rate'] ?? 100.0);

        http_response_code(200);
        echo json_encode([
            'success'             => true,
            'user_id'             => $userId,
            'tier'                => $currentTierCode,
            'tier_name'           => $currentTier['tier_name'],
            'badge_color'         => $currentTier['badge_color'],
            'badge_icon'          => $currentTier['badge_icon'],
            'point_ratio'         => (float)$currentTier['point_ratio'],
            'points_balance'      => $pointsBalance,
            'vnd_equivalent'      => $pointsBalance * $rate,
            'total_spent'         => $totalSpent,
            'next_tier'           => $nextTierCode,
            'next_tier_name'      => $nextTier ? $nextTier['tier_name'] : null,
            'next_tier_threshold' => $nextTier ? (float)$nextTier['min_spent'] : null,
            'remaining_spent'     => $remaining,
            'progress_percent'    => $progressPercent,
            'is_max_tier'         => $isMaxTier
        ], JSON_UNESCAPED_UNICODE);
        exit;

    // -------------------------------------------------------------------------
    // DEFAULT: Unknown Action
    // -------------------------------------------------------------------------
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Hành động không hợp lệ hoặc thiếu tham số action.',
            'message' => 'Hành động không hợp lệ hoặc thiếu tham số action.',
            'code'    => 400
        ], JSON_UNESCAPED_UNICODE);
        exit;
}
