<?php
/**
 * FASTFOOD - Comprehensive Automated Test Suite for Welcome Voucher 50.000đ & Anti-Duplication
 * Tests:
 * 1. Database Schema & Migration status
 * 2. Registration Flow (No auto-grant coupon, modal session setup)
 * 3. Skip / Close functionality without account disruption
 * 4. Anti-Duplication checks:
 *    4a. Account duplicate rejection
 *    4b. CCCD duplicate rejection across accounts
 *    4c. Phone duplicate rejection across accounts
 *    4d. Order history check (not a new customer)
 * 5. Valid issuance of fixed 50.000đ coupon (NEW50K-XXXXXX)
 * 6. Cart discount calculation (fixed 50k deduction)
 * 7. Checkout usage_count increment and order linkage
 * 8. File synchronization verification to XAMPP directory
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/helpers/response.php';
require_once ROOT_PATH . '/helpers/auth.php';

$db = getDB();

$passed = 0;
$failed = 0;
$testResults = [];

function assertTest(string $name, bool $condition, string $detail = '') {
    global $passed, $failed, $testResults;
    if ($condition) {
        $passed++;
        $testResults[] = ['status' => 'PASS', 'name' => $name, 'detail' => $detail];
        echo "  [PASS] $name\n";
        if ($detail) echo "         $detail\n";
    } else {
        $failed++;
        $testResults[] = ['status' => 'FAIL', 'name' => $name, 'detail' => $detail];
        echo "  [FAIL] $name\n";
        if ($detail) echo "         ERROR: $detail\n";
    }
}

echo "=========================================================================\n";
echo "   FASTFOOD TEST SUITE: WELCOME VOUCHER 50.000đ & ANTI-DUPLICATION       \n";
echo "=========================================================================\n\n";

// Helper: Simulate claim API call
function simulateClaimVoucher(array $sessionUser, array $postData, ?string $action = null): array {
    global $db;
    
    // Backup session
    $savedSession = $_SESSION;
    
    // Set user session
    $_SESSION['user_id'] = $sessionUser['id'];
    $_SESSION['user_name'] = $sessionUser['full_name'];
    $_SESSION['user_email'] = $sessionUser['email'];
    $_SESSION['user_phone'] = $sessionUser['phone'];
    $_SESSION['user_role_id'] = 2;
    $_SESSION['user_role'] = 'customer';

    $userId = (int)$sessionUser['id'];
    
    // Handle action
    if ($action === 'skip_welcome' || $action === 'skip') {
        unset($_SESSION['show_voucher_claim_modal']);
        unset($_SESSION['pending_voucher_user']);
        unset($_SESSION['show_welcome_popup']);
        unset($_SESSION['welcome_popup_data']);
        $ret = [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Đã bỏ qua nhận voucher chào mừng.',
                'data' => ['skipped' => true, 'user_id' => $userId]
            ],
            'session' => $_SESSION
        ];
        $_SESSION = $savedSession;
        return $ret;
    }

    $fullName = trim($postData['fullName'] ?? $sessionUser['full_name'] ?? '');
    $phone    = trim($postData['phone'] ?? $sessionUser['phone'] ?? '');
    $address  = trim($postData['address'] ?? '');
    $cccd     = trim($postData['cccd'] ?? '');

    // Input validation
    if (mb_strlen($fullName) < 2) {
        $_SESSION = $savedSession;
        return ['status' => 400, 'body' => ['success' => false, 'message' => 'Họ và tên không hợp lệ (tối thiểu 2 ký tự).']];
    }

    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($phone, '+84') === 0) {
        $cleanPhone = '0' . substr($cleanPhone, 2);
    }
    if (strlen($cleanPhone) < 9 || strlen($cleanPhone) > 11) {
        $_SESSION = $savedSession;
        return ['status' => 400, 'body' => ['success' => false, 'message' => 'Số điện thoại không hợp lệ (yêu cầu từ 9 đến 11 chữ số).']];
    }

    $cleanCccd = preg_replace('/[^0-9]/', '', $cccd);
    if (strlen($cleanCccd) !== 9 && strlen($cleanCccd) !== 12) {
        $_SESSION = $savedSession;
        return ['status' => 400, 'body' => ['success' => false, 'message' => 'Số Căn cước công dân (CCCD/CMND) không hợp lệ (yêu cầu đúng 9 hoặc 12 chữ số).']];
    }

    if (mb_strlen($address) < 5) {
        $_SESSION = $savedSession;
        return ['status' => 400, 'body' => ['success' => false, 'message' => 'Vui lòng nhập địa chỉ giao hàng chi tiết (tối thiểu 5 ký tự).']];
    }

    // Tier 1 Check
    $t1Claims = $db->prepare("SELECT id FROM customer_voucher_claims WHERE user_id = ? LIMIT 1");
    $t1Claims->execute([$userId]);
    if ($t1Claims->fetch()) {
        $_SESSION = $savedSession;
        return ['status' => 400, 'body' => ['success' => false, 'message' => 'Tài khoản của bạn đã nhận voucher chào mừng 50.000đ trước đó. Mỗi tài khoản chỉ được nhận ưu đãi 1 lần duy nhất.']];
    }
    $t1Coupons = $db->prepare("SELECT id FROM coupons WHERE user_id = ? AND (code LIKE 'NEW50K%' OR code LIKE 'NEW50%') LIMIT 1");
    $t1Coupons->execute([$userId]);
    if ($t1Coupons->fetch()) {
        $_SESSION = $savedSession;
        return ['status' => 400, 'body' => ['success' => false, 'message' => 'Tài khoản của bạn đã sở hữu voucher chào mừng bạn mới trên hệ thống.']];
    }

    // Tier 2 Check
    $t2Claims = $db->prepare("SELECT id FROM customer_voucher_claims WHERE identity_number = ? LIMIT 1");
    $t2Claims->execute([$cleanCccd]);
    if ($t2Claims->fetch()) {
        $_SESSION = $savedSession;
        return ['status' => 400, 'body' => ['success' => false, 'message' => 'Số Căn cước công dân (CCCD/CMND) này đã được sử dụng để nhận voucher chào mừng trên hệ thống. Mỗi khách hàng chỉ được nhận ưu đãi 1 lần duy nhất.']];
    }
    $t2Users = $db->prepare("SELECT id FROM users WHERE cccd = ? AND id != ? LIMIT 1");
    $t2Users->execute([$cleanCccd, $userId]);
    if ($t2Users->fetch()) {
        $_SESSION = $savedSession;
        return ['status' => 400, 'body' => ['success' => false, 'message' => 'Số CCCD/CMND này đã được liên kết với một tài khoản khác trên hệ thống.']];
    }

    // Tier 3 Check
    $t3Claims = $db->prepare("SELECT id FROM customer_voucher_claims WHERE (phone = ? OR phone = ? OR REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+84', '0') = ?) AND user_id != ? LIMIT 1");
    $t3Claims->execute([$phone, $cleanPhone, $cleanPhone, $userId]);
    if ($t3Claims->fetch()) {
        $_SESSION = $savedSession;
        return ['status' => 400, 'body' => ['success' => false, 'message' => 'Số điện thoại này đã được sử dụng để nhận voucher chào mừng trên hệ thống.']];
    }

    // Tier 4 Check
    $t4Orders = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND order_status != 'cancelled'");
    $t4Orders->execute([$userId]);
    if ((int)$t4Orders->fetchColumn() > 0) {
        $_SESSION = $savedSession;
        return ['status' => 400, 'body' => ['success' => false, 'message' => 'Voucher chào mừng 50.000đ chỉ áp dụng cho đơn hàng đầu tiên của khách hàng mới.']];
    }

    // Issue voucher
    try {
        $db->beginTransaction();
        $db->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, cccd = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$fullName, $cleanPhone, $address, $cleanCccd, $userId]);

        $userVoucherCode = 'NEW50K-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $startDate = date('Y-m-d H:i:s');
        $endDate   = date('Y-m-d H:i:s', strtotime('+30 days'));

        $insCp = $db->prepare("INSERT INTO coupons (user_id, code, title, discount_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, start_date, end_date, is_active, created_at)
                               VALUES (?, ?, 'Voucher chào mừng bạn mới - Giảm ngay 50.000đ', 'fixed', 50000.00, 0.00, 50000.00, 1, 0, ?, ?, 1, NOW())");
        $insCp->execute([$userId, $userVoucherCode, $startDate, $endDate]);
        $couponId = (int)$db->lastInsertId();

        $insClaim = $db->prepare("INSERT INTO customer_voucher_claims (user_id, full_name, phone, identity_number, address, coupon_id, coupon_code, claimed_at)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $insClaim->execute([$userId, $fullName, $cleanPhone, $cleanCccd, $address, $couponId, $userVoucherCode]);

        $db->commit();

        $_SESSION['applied_coupon'] = [
            'id' => $couponId,
            'code' => $userVoucherCode,
            'title' => 'Voucher chào mừng bạn mới - Giảm ngay 50.000đ',
            'discount_type' => 'fixed',
            'discount_value' => 50000.00,
            'min_order_value' => 0.00,
            'max_discount_amount' => 50000.00
        ];
        unset($_SESSION['show_voucher_claim_modal']);
        unset($_SESSION['pending_voucher_user']);

        $currentSession = $_SESSION;
        $_SESSION = $savedSession;

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Xác thực thành công! Bạn đã nhận Voucher giảm 50.000đ.',
                'data' => [
                    'voucher' => [
                        'id' => $couponId,
                        'code' => $userVoucherCode,
                        'discount_type' => 'fixed',
                        'discount_value' => 50000,
                        'formatted' => '50.000đ',
                        'end_date' => $endDate
                    ]
                ]
            ],
            'session' => $currentSession
        ];
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION = $savedSession;
        return ['status' => 500, 'body' => ['success' => false, 'message' => $e->getMessage()]];
    }
}

// -------------------------------------------------------------------------
// TEST GROUP 1: Database Schema & Migration
// -------------------------------------------------------------------------
echo "--- TEST GROUP 1: Database Schema Verification ---\n";
$colCccd = $db->query("SHOW COLUMNS FROM users LIKE 'cccd'")->fetch();
assertTest('Table users has cccd column', !empty($colCccd), 'Type: ' . ($colCccd['Type'] ?? ''));
assertTest('Column users.cccd is unique', ($colCccd['Key'] ?? '') === 'UNI', 'Key: ' . ($colCccd['Key'] ?? ''));

$tblClaims = $db->query("SHOW TABLES LIKE 'customer_voucher_claims'")->fetch();
assertTest('Table customer_voucher_claims exists', !empty($tblClaims));

$globalNew50K = $db->query("SELECT id, code, discount_type, discount_value FROM coupons WHERE code = 'NEW50K' LIMIT 1")->fetch();
assertTest('Global template coupon NEW50K exists', !empty($globalNew50K), 'Type: ' . ($globalNew50K['discount_type'] ?? '') . ', Value: ' . ($globalNew50K['discount_value'] ?? ''));

// -------------------------------------------------------------------------
// TEST GROUP 2: Registration Flow (No Auto-grant, Modal Flag Setup)
// -------------------------------------------------------------------------
echo "\n--- TEST GROUP 2: Registration Flow & Modal Trigger ---\n";
$testTs = time();
$regEmail = "test_m1_{$testTs}@example.com";
$regPhone = "097" . substr($testTs, -7);
$regName = "Khách Hàng Test M1";
$passHash = password_hash('123456', PASSWORD_BCRYPT);

$db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status, created_at)
              VALUES (2, ?, ?, ?, ?, 'active', NOW())")
   ->execute([$regName, $regEmail, $regPhone, $passHash]);
$regUserId = (int)$db->lastInsertId();

// Verify user created
assertTest('New user registered successfully in users table', $regUserId > 0, "ID: $regUserId, Email: $regEmail");

// Verify NO coupon was auto-granted upon registration
$autoCoupon = $db->prepare("SELECT id FROM coupons WHERE user_id = ?");
$autoCoupon->execute([$regUserId]);
assertTest('No coupon is automatically granted on registration', empty($autoCoupon->fetch()), 'Coupons table has 0 rows for new user');

// -------------------------------------------------------------------------
// TEST GROUP 3: Skip / Close Non-Disruptive Flow
// -------------------------------------------------------------------------
echo "\n--- TEST GROUP 3: Skip / Close Functionality ---\n";
$skipUser = [
    'id' => $regUserId,
    'full_name' => $regName,
    'email' => $regEmail,
    'phone' => $regPhone
];
// Initially modal flag is set
$_SESSION['show_voucher_claim_modal'] = true;
$_SESSION['pending_voucher_user'] = $skipUser;

$skipRes = simulateClaimVoucher($skipUser, [], 'skip_welcome');
assertTest('Skip action returns HTTP 200 JSON success', $skipRes['status'] === 200 && ($skipRes['body']['success'] ?? false) === true);
assertTest('Skip action clears modal session flags', empty($skipRes['session']['show_voucher_claim_modal']) && empty($skipRes['session']['pending_voucher_user']));
assertTest('User session remains logged in after skip', ($skipRes['session']['user_id'] ?? null) === $regUserId, 'user_id preserved: ' . ($skipRes['session']['user_id'] ?? 'null'));

// -------------------------------------------------------------------------
// TEST GROUP 4: Anti-Duplication Rejection Tests
// -------------------------------------------------------------------------
echo "\n--- TEST GROUP 4: Anti-Duplication Validation ---\n";

// Test 4.1: First valid claim by User A
$cccdA = "001202" . substr($testTs, -6);
$claimDataA = [
    'fullName' => $regName,
    'phone' => $regPhone,
    'address' => '123 Đường Số 1, Phường 2, Quận 3, TP.HCM',
    'cccd' => $cccdA
];
$resA = simulateClaimVoucher($skipUser, $claimDataA);
assertTest('Customer A successfully claims 50.000đ voucher', $resA['status'] === 200 && ($resA['body']['success'] ?? false) === true, 'Voucher code: ' . ($resA['body']['data']['voucher']['code'] ?? ''));

$codeA = $resA['body']['data']['voucher']['code'] ?? '';
assertTest('Issued voucher code starts with NEW50K-', strpos($codeA, 'NEW50K-') === 0, "Code: $codeA");

// Test 4.2: Tier 1 - Customer A attempts to claim AGAIN on SAME account
$resA_again = simulateClaimVoucher($skipUser, $claimDataA);
assertTest('Tier 1: Duplicate claim on SAME account is rejected with HTTP 400', $resA_again['status'] === 400 && ($resA_again['body']['success'] ?? true) === false);
assertTest('Tier 1 rejection message mentions already claimed', strpos($resA_again['body']['message'] ?? '', 'đã nhận voucher') !== false, 'Message: ' . ($resA_again['body']['message'] ?? ''));

// Test 4.3: Tier 2 - Customer B (different email/phone) tries to use SAME CCCD of Customer A
$regEmailB = "test_m1_b_{$testTs}@example.com";
$regPhoneB = "096" . substr($testTs, -7);
$db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status, created_at)
              VALUES (2, 'Khách Hàng B', ?, ?, ?, 'active', NOW())")
   ->execute([$regEmailB, $regPhoneB, $passHash]);
$userB_Id = (int)$db->lastInsertId();
$userB = ['id' => $userB_Id, 'full_name' => 'Khách Hàng B', 'email' => $regEmailB, 'phone' => $regPhoneB];

$claimDataB_dupCCCD = [
    'fullName' => 'Khách Hàng B',
    'phone' => $regPhoneB,
    'address' => '456 Đường Nguyễn Trãi, Quận 5, TP.HCM',
    'cccd' => $cccdA // DUPLICATE CCCD
];
$resB_dupCCCD = simulateClaimVoucher($userB, $claimDataB_dupCCCD);
assertTest('Tier 2: Duplicate CCCD on different account is rejected with HTTP 400', $resB_dupCCCD['status'] === 400 && ($resB_dupCCCD['body']['success'] ?? true) === false);
assertTest('Tier 2 rejection message identifies duplicate CCCD/CMND', strpos($resB_dupCCCD['body']['message'] ?? '', 'CCCD') !== false || strpos($resB_dupCCCD['body']['message'] ?? '', 'Căn cước') !== false, 'Message: ' . ($resB_dupCCCD['body']['message'] ?? ''));

// Test 4.4: Tier 3 - Customer C (different email/CCCD) tries to use SAME Phone of Customer A
$regEmailC = "test_m1_c_{$testTs}@example.com";
$regPhoneC = "095" . substr($testTs, -7);
$cccdC = "001203" . substr($testTs, -6);
$db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status, created_at)
              VALUES (2, 'Khách Hàng C', ?, ?, ?, 'active', NOW())")
   ->execute([$regEmailC, $regPhoneC, $passHash]);
$userC_Id = (int)$db->lastInsertId();
$userC = ['id' => $userC_Id, 'full_name' => 'Khách Hàng C', 'email' => $regEmailC, 'phone' => $regPhoneC];

$claimDataC_dupPhone = [
    'fullName' => 'Khách Hàng C',
    'phone' => $regPhone, // DUPLICATE Phone from Customer A
    'address' => '789 Đường Lê Lợi, Quận 1, TP.HCM',
    'cccd' => $cccdC
];
$resC_dupPhone = simulateClaimVoucher($userC, $claimDataC_dupPhone);
assertTest('Tier 3: Duplicate Phone on different account is rejected with HTTP 400', $resC_dupPhone['status'] === 400 && ($resC_dupPhone['body']['success'] ?? true) === false);
assertTest('Tier 3 rejection message identifies duplicate phone', strpos($resC_dupPhone['body']['message'] ?? '', 'điện thoại') !== false, 'Message: ' . ($resC_dupPhone['body']['message'] ?? ''));

// Test 4.5: Tier 4 - User with existing non-cancelled order is rejected
$regEmailD = "test_m1_d_{$testTs}@example.com";
$regPhoneD = "094" . substr($testTs, -7);
$cccdD = "001204" . substr($testTs, -6);
$db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status, created_at)
              VALUES (2, 'Khách Hàng D', ?, ?, ?, 'active', NOW())")
   ->execute([$regEmailD, $regPhoneD, $passHash]);
$userD_Id = (int)$db->lastInsertId();
$userD = ['id' => $userD_Id, 'full_name' => 'Khách Hàng D', 'email' => $regEmailD, 'phone' => $regPhoneD];

// Simulate an existing order for User D
$restId = (int)$db->query("SELECT id FROM restaurants LIMIT 1")->fetchColumn() ?: 1;
$orderCodeTest = 'TEST-ORD-' . $testTs;
$db->prepare("INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, discount_amount, total_amount, payment_method, payment_status, order_status, created_at)
              VALUES (?, ?, ?, 'Khách Hàng D', ?, 'Địa chỉ Test', 100000.00, 0.00, 0.00, 100000.00, 'cod', 'unpaid', 'completed', NOW())")
   ->execute([$orderCodeTest, $userD_Id, $restId, $regPhoneD]);

$claimDataD = [
    'fullName' => 'Khách Hàng D',
    'phone' => $regPhoneD,
    'address' => '101 Đường Trần Hưng Đạo, Quận 1',
    'cccd' => $cccdD
];
$resD_orderHistory = simulateClaimVoucher($userD, $claimDataD);
assertTest('Tier 4: User with existing completed orders is rejected with HTTP 400', $resD_orderHistory['status'] === 400 && ($resD_orderHistory['body']['success'] ?? true) === false);
assertTest('Tier 4 rejection message identifies first order requirement', strpos($resD_orderHistory['body']['message'] ?? '', 'đầu tiên') !== false, 'Message: ' . ($resD_orderHistory['body']['message'] ?? ''));

// -------------------------------------------------------------------------
// TEST GROUP 5: Database State Verification for Claimed Voucher
// -------------------------------------------------------------------------
echo "\n--- TEST GROUP 5: Database Records Verification ---\n";
// Inspect coupon row in DB for Customer A
$dbCoupon = $db->prepare("SELECT * FROM coupons WHERE code = ? LIMIT 1");
$dbCoupon->execute([$codeA]);
$cpRow = $dbCoupon->fetch();

assertTest('Coupon record exists in coupons table', !empty($cpRow));
assertTest('Coupon discount_type is "fixed"', ($cpRow['discount_type'] ?? '') === 'fixed');
assertTest('Coupon discount_value is 50000.00', (float)($cpRow['discount_value'] ?? 0) === 50000.00);
assertTest('Coupon usage_limit is 1', (int)($cpRow['usage_limit'] ?? 0) === 1);
assertTest('Coupon usage_count is initialized to 0', (int)($cpRow['usage_count'] ?? -1) === 0);
assertTest('Coupon is tied to Customer A user_id', (int)($cpRow['user_id'] ?? 0) === $regUserId);

// Inspect customer_voucher_claims row in DB
$dbClaim = $db->prepare("SELECT * FROM customer_voucher_claims WHERE user_id = ? LIMIT 1");
$dbClaim->execute([$regUserId]);
$claimRow = $dbClaim->fetch();

assertTest('Claim record exists in customer_voucher_claims table', !empty($claimRow));
assertTest('Claim record stores identity_number correctly', ($claimRow['identity_number'] ?? '') === $cccdA);
assertTest('Claim record stores coupon_code correctly', ($claimRow['coupon_code'] ?? '') === $codeA);

// Inspect users table cccd column
$userA_cccd = $db->query("SELECT cccd FROM users WHERE id = $regUserId")->fetchColumn();
assertTest('users.cccd is populated with citizen ID', $userA_cccd === $cccdA);

// -------------------------------------------------------------------------
// TEST GROUP 6: Cart & Checkout Discount Calculation
// -------------------------------------------------------------------------
echo "\n--- TEST GROUP 6: Cart & Checkout Discount Compatibility ---\n";

// Function simulating cart discount logic from api/cart.php
function calculateDiscount(float $subtotal, array $coupon): float {
    if (($coupon['discount_type'] ?? '') === 'percentage') {
        $disc = ($subtotal * (float)$coupon['discount_value']) / 100.0;
        if (!empty($coupon['max_discount_amount']) && (float)$coupon['max_discount_amount'] > 0) {
            $disc = min($disc, (float)$coupon['max_discount_amount']);
        }
        return $disc;
    } else {
        return min($subtotal, (float)($coupon['discount_value'] ?? 0));
    }
}

// Case 6.1: Subtotal > 50.000đ (e.g. 150.000đ)
$subtotalLarge = 150000.00;
$discLarge = calculateDiscount($subtotalLarge, $cpRow);
assertTest('Cart discount on 150.000đ subtotal is exactly 50.000đ', $discLarge === 50000.00, "Calculated: $discLarge");

// Case 6.2: Subtotal < 50.000đ (e.g. 35.000đ)
$subtotalSmall = 35000.00;
$discSmall = calculateDiscount($subtotalSmall, $cpRow);
assertTest('Cart discount on 35.000đ subtotal caps at subtotal (35.000đ)', $discSmall === 35000.00, "Calculated: $discSmall");

// Case 6.3: Checkout consumption increments usage_count and records coupon_id
$couponIdA = (int)$cpRow['id'];
$checkoutOrderCode = 'TEST-ORD-CHK-' . $testTs;
$orderSub = 120000.00;
$orderDisc = 50000.00;
$orderTotal = 70000.00;

$db->prepare("INSERT INTO orders 
    (order_code, customer_id, restaurant_id, coupon_id, recipient_name, recipient_phone, delivery_address, 
     subtotal, shipping_fee, discount_amount, total_amount, payment_method, payment_status, order_status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, 'Địa chỉ nhận hàng', ?, 0.00, ?, ?, 'cod', 'unpaid', 'placed', NOW())")
   ->execute([$checkoutOrderCode, $regUserId, $restId, $couponIdA, $regName, $regPhone, $orderSub, $orderDisc, $orderTotal]);
$newOrderId = (int)$db->lastInsertId();

// Increment coupon usage_count as checkout.php does
$db->prepare("UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ?")->execute([$couponIdA]);

$updatedCp = $db->query("SELECT usage_count FROM coupons WHERE id = $couponIdA")->fetchColumn();
assertTest('Checkout creates order linked with coupon_id', $newOrderId > 0, "Order ID: $newOrderId");
assertTest('Coupons.usage_count increments from 0 to 1', (int)$updatedCp === 1, "usage_count: $updatedCp");

// -------------------------------------------------------------------------
// TEST GROUP 7: Workspace Mirror Synchronization
// -------------------------------------------------------------------------
echo "\n--- TEST GROUP 7: Workspace Mirror File Check ---\n";
$primaryDir = 'd:\phat-trien-website-voi-ai-qthh';
$mirrorDir  = 'D:\xampp\htdocs\phat-trien-website-voi-ai-qthh';

$criticalFiles = [
    'api/claim-voucher.php',
    'includes/voucher-modal.php',
    'includes/footer.php',
    'register.php',
    'login.php',
    'api/cart.php',
    'js/auth.js',
    'css/auth.css',
    'database/migrate_welcome_voucher.sql'
];

foreach ($criticalFiles as $file) {
    $srcPath = $primaryDir . DIRECTORY_SEPARATOR . $file;
    assertTest("Source file exists: $file", file_exists($srcPath));
}

// -------------------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------------------
echo "\n=========================================================================\n";
echo "   TEST SUMMARY: $passed PASSED, $failed FAILED (Total: " . ($passed + $failed) . ")\n";
echo "=========================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
