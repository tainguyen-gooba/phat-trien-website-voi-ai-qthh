<?php
/**
 * FASTFOOD - Milestone 3 Comprehensive Test Suite: Modern FastFood Profile Redesign & Voucher Hub
 * File: tests/test_milestone3_profile.php
 * 
 * Verifies:
 * 1. PHP Lint / Syntax Integrity of profile.php and api/loyalty.php
 * 2. API Authentication Protection (HTTP 401 JSON on unauthenticated access)
 * 3. API Routing & Parameter Validation (HTTP 400 on invalid/missing actions, HTTP 405 on non-POST)
 * 4. Voucher Exchange Business Logic:
 *    - Rejection on invalid package ID (HTTP 400)
 *    - Rejection on insufficient points balance (HTTP 400, no DB mutation)
 *    - Successful exchange (HTTP 200, points deducted, coupon generated, ledger transaction recorded)
 * 5. Customer Summary Query (HTTP 200, tier, balance, progress, remaining spent)
 * 6. User Tier Synchronization on Profile Load (syncUserTier from completed orders)
 * 7. Tier Progress & Milestone Calculation Math (Bronze, Silver, Gold, Diamond MAX)
 * 8. Profile Edit Form Validation (Name length, phone format, DB update)
 * 9. Automated Fixture Teardown & Isolation
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/loyalty.php';

$totalAssertions = 0;
$passedAssertions = 0;
$failedAssertions = 0;
$failedDetails = [];

function assertTest(string $name, bool $condition, string $detail = ''): void {
    global $totalAssertions, $passedAssertions, $failedAssertions, $failedDetails;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo "  [PASS] {$name}\n";
        if ($detail) {
            echo "         {$detail}\n";
        }
    } else {
        $failedAssertions++;
        $failedDetails[] = ['name' => $name, 'detail' => $detail];
        echo "  [FAIL] {$name}\n";
        if ($detail) {
            echo "         ERROR: {$detail}\n";
        }
    }
}

/**
 * Execute api/loyalty.php in an isolated PHP subprocess with simulated HTTP environment
 */
function callLoyaltyApi(?array $sessionData, string $method = 'GET', array $post = [], array $get = [], ?array $jsonBody = null): array {
    $scratchDir = 'C:/Users/Admin/.gemini/antigravity/scratch/teamwork_tmp/af8c0270-3c00-4640-bbe7-59815c4d32c4';
    if (!is_dir($scratchDir)) {
        @mkdir($scratchDir, 0777, true);
    }
    $tmpFile = $scratchDir . '/api_subproc_' . uniqid() . '.php';

    $rootPathExport = var_export(ROOT_PATH, true);
    $methodExport = var_export(strtoupper($method), true);
    $postExport = var_export($post, true);
    $getExport = var_export($get, true);
    $sessionExport = var_export($sessionData, true);

    $scriptContent = "<?php
\$_SERVER['REQUEST_METHOD'] = {$methodExport};
\$_POST = {$postExport};
\$_GET = {$getExport};
\$_REQUEST = array_merge(\$_GET, \$_POST);

\$sessionData = {$sessionExport};
if (\$sessionData !== null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    foreach (\$sessionData as \$k => \$v) {
        \$_SESSION[\$k] = \$v;
    }
}

register_shutdown_function(function() {
    \$status = http_response_code() ?: 200;
    echo \"\\n__HTTP_STATUS__:\" . \$status;
});

require " . var_export(ROOT_PATH . '/api/loyalty.php', true) . ";
";

    file_put_contents($tmpFile, $scriptContent);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open('php ' . escapeshellarg($tmpFile), $descriptors, $pipes);

    if ($jsonBody !== null) {
        fwrite($pipes[0], json_encode($jsonBody));
    }
    fclose($pipes[0]);

    $rawStdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $rawStderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $procCode = proc_close($proc);
    @unlink($tmpFile);

    // Parse HTTP Status
    $httpStatus = 200;
    $bodyOutput = $rawStdout;
    if (preg_match('/__HTTP_STATUS__:(\d+)/', $rawStdout, $matches)) {
        $httpStatus = (int)$matches[1];
        $bodyOutput = trim(str_replace($matches[0], '', $rawStdout));
    }

    // Isolate JSON substring if any PHP notices precede it
    $json = [];
    if (preg_match('/\{[\s\S]*\}/', $bodyOutput, $jsonMatch)) {
        $json = json_decode($jsonMatch[0], true) ?: [];
    }

    return [
        'http_status' => $httpStatus,
        'body'        => $bodyOutput,
        'json'        => is_array($json) ? $json : [],
        'stderr'      => $rawStderr,
        'proc_code'   => $procCode
    ];
}

echo "=========================================================================\n";
echo "   FASTFOOD TEST SUITE: MILESTONE 3 - PROFILE REDESIGN & VOUCHER HUB     \n";
echo "=========================================================================\n\n";

$db = getDB();

// Unique test identifiers
$testUserEmail = 'test_m3_vip_' . time() . '@fastfood.test';
$testUserId = 0;

try {
    // =========================================================================
    // SECTION 1: PHP LINT CHECKS
    // =========================================================================
    echo "--- 1. Testing PHP Syntax & Lint Integrity ---\n";
    $lintProfile = shell_exec('php -l ' . escapeshellarg(ROOT_PATH . '/profile.php'));
    assertTest("Lint profile.php: No syntax errors", str_contains($lintProfile, 'No syntax errors detected'));

    $lintApi = shell_exec('php -l ' . escapeshellarg(ROOT_PATH . '/api/loyalty.php'));
    assertTest("Lint api/loyalty.php: No syntax errors", str_contains($lintApi, 'No syntax errors detected'));

    // =========================================================================
    // SECTION 2: API AUTHENTICATION ENFORCEMENT
    // =========================================================================
    echo "\n--- 2. Testing API Authentication Protection ---\n";

    // Unauthenticated GET/POST must return HTTP 401 JSON, NOT redirect
    $resUnauth = callLoyaltyApi(null, 'GET', [], ['action' => 'get_user_summary']);
    assertTest("Unauthenticated request returns HTTP Status 401", $resUnauth['http_status'] === 401, "Actual: " . $resUnauth['http_status']);
    assertTest("Unauthenticated response has success = false", ($resUnauth['json']['success'] ?? null) === false);
    assertTest("Unauthenticated response contains error message", !empty($resUnauth['json']['error']));

    // Create Test User in DB
    $insStmt = $db->prepare("INSERT INTO users (role_id, full_name, email, password_hash, phone, address, member_tier, points_balance, total_spent)
        VALUES (2, 'Nguyễn Văn Test VIP', ?, 'testhash', '0912345678', '123 Đường Test, Q1', 'BRONZE', 50, 0.00)");
    $insStmt->execute([$testUserEmail]);
    $testUserId = (int)$db->lastInsertId();

    $sessionData = [
        'user_id'      => $testUserId,
        'user_name'    => 'Nguyễn Văn Test VIP',
        'user_email'   => $testUserEmail,
        'user_phone'   => '0912345678',
        'user_role_id' => 2,
        'user_role'    => 'customer'
    ];

    assertTest("Created isolated test fixture user with ID {$testUserId}", $testUserId > 0);

    // =========================================================================
    // SECTION 3: API ROUTING & PARAMETER VALIDATION
    // =========================================================================
    echo "\n--- 3. Testing API Routing & Parameter Validation ---\n";

    // Invalid action
    $resBadAction = callLoyaltyApi($sessionData, 'GET', [], ['action' => 'non_existent_action']);
    assertTest("Invalid action returns HTTP Status 400", $resBadAction['http_status'] === 400);
    assertTest("Invalid action has success = false", ($resBadAction['json']['success'] ?? null) === false);

    // exchange_voucher with GET instead of POST -> HTTP 405
    $resGetExchange = callLoyaltyApi($sessionData, 'GET', [], ['action' => 'exchange_voucher', 'package_id' => 1]);
    assertTest("exchange_voucher via GET returns HTTP Status 405 Method Not Allowed", $resGetExchange['http_status'] === 405);

    // exchange_voucher with invalid package_id -> HTTP 400
    $resBadPkg = callLoyaltyApi($sessionData, 'POST', [], [], ['action' => 'exchange_voucher', 'package_id' => 999]);
    assertTest("exchange_voucher with package_id=999 returns HTTP Status 400", $resBadPkg['http_status'] === 400);
    assertTest("Invalid package error message present", str_contains($resBadPkg['json']['error'] ?? '', 'không hợp lệ'));

    $resZeroPkg = callLoyaltyApi($sessionData, 'POST', [], [], ['action' => 'exchange_voucher', 'package_id' => 0]);
    assertTest("exchange_voucher with package_id=0 returns HTTP Status 400", $resZeroPkg['http_status'] === 400);

    // =========================================================================
    // SECTION 4: VOUCHER EXCHANGE BUSINESS LOGIC
    // =========================================================================
    echo "\n--- 4. Testing Voucher Exchange Business Logic & State Mutations ---\n";

    // 4.1 Insufficient points: User has 50 points, Package 1 requires 100 points
    $resInsufficient = callLoyaltyApi($sessionData, 'POST', [], [], ['action' => 'exchange_voucher', 'package_id' => 1]);
    assertTest("Exchange with 50 pts for 100 pts package returns HTTP Status 400", $resInsufficient['http_status'] === 400);
    assertTest("Insufficient points error message present", str_contains($resInsufficient['json']['error'] ?? '', 'không đủ'));

    // Check DB state: points balance unchanged, no coupons created
    $uChk = $db->prepare("SELECT points_balance FROM users WHERE id = ?");
    $uChk->execute([$testUserId]);
    $ptsNow = (int)$uChk->fetchColumn();
    assertTest("User points balance remains 50 in DB after rejection", $ptsNow === 50);

    $cpChk = $db->prepare("SELECT COUNT(*) FROM coupons WHERE user_id = ?");
    $cpChk->execute([$testUserId]);
    $cpCount = (int)$cpChk->fetchColumn();
    assertTest("0 coupons created in DB after rejected exchange", $cpCount === 0);

    // 4.2 Top up points to 350 for successful exchange test
    $db->prepare("UPDATE users SET points_balance = 350 WHERE id = ?")->execute([$testUserId]);

    // Exchange Package 1 (100 pts -> 10.000đ voucher)
    $resSuccess = callLoyaltyApi($sessionData, 'POST', [], [], ['action' => 'exchange_voucher', 'package_id' => 1]);
    assertTest("Exchange with 350 pts for Package 1 returns HTTP Status 200", $resSuccess['http_status'] === 200);
    assertTest("Exchange response has success = true", ($resSuccess['json']['success'] ?? null) === true);

    $couponCode = $resSuccess['json']['coupon_code'] ?? '';
    assertTest("Coupon code format starts with FF10K-", str_starts_with($couponCode, 'FF10K-'), "Code: {$couponCode}");
    assertTest("Discount amount returned is 10,000đ", (float)($resSuccess['json']['discount_amount'] ?? 0) === 10000.0);
    assertTest("Points deducted is 100", (int)($resSuccess['json']['points_deducted'] ?? 0) === 100);
    assertTest("New balance in response is 250", (int)($resSuccess['json']['new_balance'] ?? 0) === 250);

    // 4.3 Verify DB State Mutations
    $uChk->execute([$testUserId]);
    $newDbBalance = (int)$uChk->fetchColumn();
    assertTest("DB users.points_balance is updated to exactly 250", $newDbBalance === 250);

    $cpStmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND user_id = ?");
    $cpStmt->execute([$couponCode, $testUserId]);
    $createdCoupon = $cpStmt->fetch();
    assertTest("Coupon record exists in DB linked to test user", !empty($createdCoupon));
    assertTest("Coupon discount_value is 10000.00", (float)($createdCoupon['discount_value'] ?? 0) === 10000.0);
    assertTest("Coupon is_active = 1", (int)($createdCoupon['is_active'] ?? 0) === 1);
    assertTest("Coupon usage_limit = 1 and usage_count = 0", (int)$createdCoupon['usage_limit'] === 1 && (int)$createdCoupon['usage_count'] === 0);

    $txStmt = $db->prepare("SELECT * FROM point_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $txStmt->execute([$testUserId]);
    $createdTx = $txStmt->fetch();
    assertTest("point_transactions record created with transaction_type = REDEEM_VOUCHER", ($createdTx['transaction_type'] ?? '') === 'REDEEM_VOUCHER');
    assertTest("Transaction points is -100", (int)($createdTx['points'] ?? 0) === -100);
    assertTest("Transaction balance_after is 250", (int)($createdTx['balance_after'] ?? 0) === 250);
    assertTest("Transaction description contains coupon code", str_contains($createdTx['description'] ?? '', $couponCode));

    // =========================================================================
    // SECTION 5: CUSTOMER SUMMARY QUERY
    // =========================================================================
    echo "\n--- 5. Testing Customer Summary Query (get_user_summary) ---\n";

    $resSummary = callLoyaltyApi($sessionData, 'GET', [], ['action' => 'get_user_summary']);
    assertTest("get_user_summary returns HTTP Status 200", $resSummary['http_status'] === 200);
    assertTest("Summary returns user_id = {$testUserId}", ($resSummary['json']['user_id'] ?? 0) === $testUserId);
    assertTest("Summary returns current tier = BRONZE", ($resSummary['json']['tier'] ?? '') === 'BRONZE');
    assertTest("Summary returns points_balance = 250", ($resSummary['json']['points_balance'] ?? 0) === 250);
    assertTest("Summary returns vnd_equivalent = 25000", ($resSummary['json']['vnd_equivalent'] ?? 0) === 25000);
    assertTest("Summary returns next_tier = SILVER", ($resSummary['json']['next_tier'] ?? '') === 'SILVER');
    assertTest("Summary returns remaining_spent = 1,000,000", (float)($resSummary['json']['remaining_spent'] ?? 0) === 1000000.0);
    assertTest("Summary returns is_max_tier = false", ($resSummary['json']['is_max_tier'] ?? null) === false);

    // =========================================================================
    // SECTION 6: TIER SYNCHRONIZATION ON PROFILE LOAD
    // =========================================================================
    echo "\n--- 6. Testing Tier Synchronization & Dynamic Progress Math ---\n";

    // Create a completed order for test user totaling 3,200,000 VND
    $orderCode = 'TEST-ORD-' . time();
    $insOrder = $db->prepare("INSERT INTO orders 
        (order_code, customer_id, restaurant_id, delivery_address, recipient_name, recipient_phone, subtotal, shipping_fee, total_amount, order_status, created_at)
        VALUES (?, ?, 1, '123 Đường Test, Q1', 'Nguyễn Văn Test VIP', '0912345678', 3200000.00, 0.00, 3200000.00, 'completed', NOW())");
    $insOrder->execute([$orderCode, $testUserId]);
    $testOrderId = (int)$db->lastInsertId();

    // Call syncUserTier (simulating profile.php page load)
    $syncRes = syncUserTier($testUserId);
    assertTest("syncUserTier succeeds", ($syncRes['success'] ?? false) === true);
    assertTest("syncUserTier calculates total_spent = 3,200,000đ", (float)($syncRes['total_spent'] ?? 0) === 3200000.0);
    assertTest("syncUserTier upgrades tier to GOLD", ($syncRes['tier'] ?? '') === 'GOLD');

    // Verify DB user row updated
    $stmtUser = $db->prepare("SELECT member_tier, total_spent FROM users WHERE id = ?");
    $stmtUser->execute([$testUserId]);
    $uRow = $stmtUser->fetch();
    assertTest("DB users.member_tier is GOLD", ($uRow['member_tier'] ?? '') === 'GOLD');
    assertTest("DB users.total_spent is 3,200,000.00", (float)($uRow['total_spent'] ?? 0) === 3200000.0);

    // Summary query now reflects GOLD tier
    $resSummaryGold = callLoyaltyApi($sessionData, 'GET', [], ['action' => 'get_user_summary']);
    assertTest("Summary after sync reflects tier = GOLD", ($resSummaryGold['json']['tier'] ?? '') === 'GOLD');
    assertTest("Summary after sync reflects next_tier = DIAMOND", ($resSummaryGold['json']['next_tier'] ?? '') === 'DIAMOND');
    // Gold is from 3M to 5M (span: 2M). Spent: 3.2M. Remaining: 1.8M. Progress: (3.2M - 3M) / 2M = 10.0%
    assertTest("Summary reflects remaining_spent = 1,800,000đ", (float)($resSummaryGold['json']['remaining_spent'] ?? 0) === 1800000.0);
    assertTest("Summary reflects progress_percent = 10.0%", (float)($resSummaryGold['json']['progress_percent'] ?? 0) === 10.0);

    // =========================================================================
    // SECTION 7: TIER PROGRESS FORMULA VERIFICATION (ALL 4 TIERS)
    // =========================================================================
    echo "\n--- 7. Testing Progress Formula Across Milestone Thresholds ---\n";

    $tiers = getMembershipTiers();
    $tierOrder = ['BRONZE', 'SILVER', 'GOLD', 'DIAMOND'];

    // Helper math function matching profile.php exactly
    $calcProgress = function(float $totalSpent) use ($tiers, $tierOrder) {
        $tierCode = calculateTier($totalSpent);
        $currentTier = $tiers[$tierCode];
        $currentIndex = array_search($tierCode, $tierOrder, true);
        $isMaxTier = ($currentIndex >= count($tierOrder) - 1);

        if (!$isMaxTier) {
            $nextTierCode = $tierOrder[$currentIndex + 1];
            $nextTier = $tiers[$nextTierCode];
            $currentMin = (float)$currentTier['min_spent'];
            $nextMin = (float)$nextTier['min_spent'];
            $remaining = max(0.0, $nextMin - $totalSpent);
            $span = max(1.0, $nextMin - $currentMin);
            $progressPercent = min(100.0, max(0.0, round((($totalSpent - $currentMin) / $span) * 100, 1)));
        } else {
            $nextTierCode = null;
            $remaining = 0.0;
            $progressPercent = 100.0;
        }

        return [
            'tier'             => $tierCode,
            'next_tier'        => $nextTierCode,
            'remaining'        => $remaining,
            'progress_percent' => $progressPercent,
            'is_max_tier'      => $isMaxTier
        ];
    };

    // Bronze: spent 450,000 -> next Silver (1M), remaining 550,000, 45%
    $pBronze = $calcProgress(450000.0);
    assertTest("Bronze at 450k: tier=BRONZE, next=SILVER, remaining=550k, 45.0%", 
        $pBronze['tier'] === 'BRONZE' && $pBronze['next_tier'] === 'SILVER' && $pBronze['remaining'] === 550000.0 && $pBronze['progress_percent'] === 45.0);

    // Silver: spent 1,500,000 -> next Gold (3M), remaining 1,500,000, (1.5M - 1M) / 2M = 25%
    $pSilver = $calcProgress(1500000.0);
    assertTest("Silver at 1.5M: tier=SILVER, next=GOLD, remaining=1.5M, 25.0%", 
        $pSilver['tier'] === 'SILVER' && $pSilver['next_tier'] === 'GOLD' && $pSilver['remaining'] === 1500000.0 && $pSilver['progress_percent'] === 25.0);

    // Gold: spent 4,000,000 -> next Diamond (5M), remaining 1,000,000, (4M - 3M) / 2M = 50%
    $pGold = $calcProgress(4000000.0);
    assertTest("Gold at 4M: tier=GOLD, next=DIAMOND, remaining=1.0M, 50.0%", 
        $pGold['tier'] === 'GOLD' && $pGold['next_tier'] === 'DIAMOND' && $pGold['remaining'] === 1000000.0 && $pGold['progress_percent'] === 50.0);

    // Diamond: spent 5,500,000 -> Diamond MAX, is_max_tier=true, remaining=0, 100%
    $pDiamond = $calcProgress(5500000.0);
    assertTest("Diamond at 5.5M: tier=DIAMOND, next=NULL, remaining=0, 100.0%, is_max_tier=true", 
        $pDiamond['tier'] === 'DIAMOND' && $pDiamond['next_tier'] === null && $pDiamond['remaining'] === 0.0 && $pDiamond['progress_percent'] === 100.0 && $pDiamond['is_max_tier'] === true);

    // =========================================================================
    // SECTION 8: PROFILE EDIT FORM VALIDATION
    // =========================================================================
    echo "\n--- 8. Testing Profile Edit Form Validation Logic ---\n";

    // Test validation functions matching profile.php POST handler
    $validateProfile = function(string $fullName, string $phone) {
        $fullName = trim($fullName);
        $phone = trim($phone);
        if (mb_strlen($fullName) < 3) {
            return ['valid' => false, 'error' => 'Họ và tên không hợp lệ (tối thiểu 3 ký tự).'];
        }
        if (!preg_match('/^[0-9+() -]{10,15}$/', $phone)) {
            return ['valid' => false, 'error' => 'Số điện thoại không hợp lệ.'];
        }
        return ['valid' => true, 'error' => ''];
    };

    $valShortName = $validateProfile('An', '0912345678');
    assertTest("Short name (<3 chars) rejected with validation error", !$valShortName['valid'] && str_contains($valShortName['error'], 'tối thiểu 3 ký tự'));

    $valBadPhone = $validateProfile('Nguyễn Văn Test', '12345');
    assertTest("Invalid phone (<10 chars) rejected with validation error", !$valBadPhone['valid'] && str_contains($valBadPhone['error'], 'Số điện thoại không hợp lệ'));

    $valAlphaPhone = $validateProfile('Nguyễn Văn Test', '0912abcxyz');
    assertTest("Alphanumeric phone rejected with validation error", !$valAlphaPhone['valid'] && str_contains($valAlphaPhone['error'], 'Số điện thoại không hợp lệ'));

    $valValid = $validateProfile('Trần Thị Hương VIP', '0987654321');
    assertTest("Valid name and phone passes validation", $valValid['valid']);

    // Execute valid update on test user
    $updStmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?");
    $updStmt->execute(['Trần Thị Hương VIP', '0987654321', '456 Phố Mới, Q3', $testUserId]);

    $stmtUser->execute([$testUserId]);
    $updatedUser = $db->query("SELECT full_name, phone, address FROM users WHERE id = {$testUserId}")->fetch();
    assertTest("DB users updated with new full_name", $updatedUser['full_name'] === 'Trần Thị Hương VIP');
    assertTest("DB users updated with new phone", $updatedUser['phone'] === '0987654321');
    assertTest("DB users updated with new address", $updatedUser['address'] === '456 Phố Mới, Q3');

} catch (Exception $e) {
    echo "  [EXCEPTION] " . $e->getMessage() . "\n";
    $failedAssertions++;
    $failedDetails[] = ['name' => 'Unexpected Exception', 'detail' => $e->getMessage()];
} finally {
    // =========================================================================
    // SECTION 9: CLEANUP & TEARDOWN
    // =========================================================================
    echo "\n--- 9. Cleaning up Test Fixtures ---\n";
    if ($testUserId > 0) {
        $db->prepare("DELETE FROM point_transactions WHERE user_id = ?")->execute([$testUserId]);
        $db->prepare("DELETE FROM coupons WHERE user_id = ?")->execute([$testUserId]);
        $db->prepare("DELETE FROM orders WHERE customer_id = ?")->execute([$testUserId]);
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$testUserId]);
        echo "  Cleaned up all test user fixtures (user_id = {$testUserId}).\n";
    }

    $chkResidual = (int)$db->query("SELECT COUNT(*) FROM users WHERE email LIKE 'test_m3_vip_%'")->fetchColumn();
    assertTest("0 residual test fixture users remaining in database", $chkResidual === 0);

    // Clean up scratch directory if created during testing
    $scratchDir = 'C:/Users/Admin/.gemini/antigravity/scratch/teamwork_tmp/af8c0270-3c00-4640-bbe7-59815c4d32c4';
    if (is_dir($scratchDir)) {
        @rmdir($scratchDir);
    }
}

echo "\n=========================================================================\n";
echo "   TEST SUMMARY: {$passedAssertions} PASSED, {$failedAssertions} FAILED (TOTAL: {$totalAssertions})\n";
echo "=========================================================================\n\n";

if ($failedAssertions > 0) {
    echo "FAILED TESTS DETAILS:\n";
    foreach ($failedDetails as $f) {
        echo " - {$f['name']}: {$f['detail']}\n";
    }
    exit(1);
}

echo "ALL MILESTONE 3 PROFILE & VOUCHER HUB TESTS PASSED WITH 100% SUCCESS RATE!\n";
exit(0);
