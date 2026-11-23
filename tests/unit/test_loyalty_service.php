<?php
/**
 * FASTFOOD - Comprehensive Loyalty Service Test Suite
 * File: tests/unit/test_loyalty_service.php
 * 
 * Tests:
 * 1. Tier threshold boundaries (calculateTier)
 * 2. Points earning calculations across all 4 tiers (calculateEarnPoints)
 * 3. Point redemption math & minimum thresholds
 * 4. Voucher package configurations
 * 5. Dynamic loyalty settings & membership tier loading & updateLoyaltySettings
 * 6. User tier synchronization (syncUserTier)
 * 7. Checkout point redemption (atomic balance check & deduction)
 * 8. Order completion points earning & auto-upgrade
 * 9. Idempotency protection of processOrderEarnPoints
 * 10. Order cancellation rollback (restore used points, revoke earned points & spending)
 * 11. Voucher exchange flow (points deduction & unique coupon generation)
 * 12. Point transaction ledger audit trail (getUserPointHistory)
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
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

echo "=========================================================================\n";
echo "   FASTFOOD TEST SUITE: LOYALTY ENGINE & MEMBERSHIP TIERS (UNIT & SERVICE)\n";
echo "=========================================================================\n\n";

$db = getDB();

// =============================================================================
// TEST SUITE 1: TIER CALCULATION & THRESHOLD BOUNDARIES (calculateTier)
// =============================================================================
echo "--- 1. Testing Tier Calculation & Threshold Boundaries ---\n";

assertTest("Tier at -100 VND is BRONZE (negative)", calculateTier(-100.0) === 'BRONZE');
assertTest("Tier at 0 VND is BRONZE", calculateTier(0.0) === 'BRONZE');
assertTest("Tier at 500,000 VND is BRONZE", calculateTier(500000.0) === 'BRONZE');
assertTest("Tier at 999,999 VND is BRONZE (boundary -1)", calculateTier(999999.0) === 'BRONZE');
assertTest("Tier at 1,000,000 VND is SILVER (boundary exact)", calculateTier(1000000.0) === 'SILVER');
assertTest("Tier at 2,000,000 VND is SILVER", calculateTier(2000000.0) === 'SILVER');
assertTest("Tier at 2,999,999 VND is SILVER (boundary -1)", calculateTier(2999999.0) === 'SILVER');
assertTest("Tier at 3,000,000 VND is GOLD (boundary exact)", calculateTier(3000000.0) === 'GOLD');
assertTest("Tier at 4,999,999 VND is GOLD (boundary -1)", calculateTier(4999999.0) === 'GOLD');
assertTest("Tier at 5,000,000 VND is DIAMOND (boundary exact)", calculateTier(5000000.0) === 'DIAMOND');
assertTest("Tier at 10,000,000 VND is DIAMOND", calculateTier(10000000.0) === 'DIAMOND');

// =============================================================================
// TEST SUITE 2: POINTS EARNING FORMULA ACROSS ALL 4 TIERS (calculateEarnPoints)
// =============================================================================
echo "\n--- 2. Testing Points Earning Formula Across All Tiers ---\n";

// BRONZE (Ratio 1.0 pt / 10k)
assertTest("Bronze: 0 VND earns 0 points", calculateEarnPoints(0.0, 'BRONZE') === 0);
assertTest("Bronze: 9,999 VND earns 0 points", calculateEarnPoints(9999.0, 'BRONZE') === 0);
assertTest("Bronze: 10,000 VND earns 1 point", calculateEarnPoints(10000.0, 'BRONZE') === 1);
assertTest("Bronze: 20,000 VND earns 2 points", calculateEarnPoints(20000.0, 'BRONZE') === 2);
assertTest("Bronze: 100,000 VND earns 10 points", calculateEarnPoints(100000.0, 'BRONZE') === 10);
assertTest("Bronze: 250,000 VND earns 25 points", calculateEarnPoints(250000.0, 'BRONZE') === 25);

// SILVER (Ratio 1.5 pts / 10k)
assertTest("Silver: 0 VND earns 0 points", calculateEarnPoints(0.0, 'SILVER') === 0);
assertTest("Silver: 9,999 VND earns 0 points", calculateEarnPoints(9999.0, 'SILVER') === 0);
assertTest("Silver: 10,000 VND earns 1 point (floor(1.5))", calculateEarnPoints(10000.0, 'SILVER') === 1);
assertTest("Silver: 20,000 VND earns 3 points (floor(3.0))", calculateEarnPoints(20000.0, 'SILVER') === 3);
assertTest("Silver: 100,000 VND earns 15 points (floor(15.0))", calculateEarnPoints(100000.0, 'SILVER') === 15);
assertTest("Silver: 250,000 VND earns 37 points (floor(37.5))", calculateEarnPoints(250000.0, 'SILVER') === 37);

// GOLD (Ratio 2.0 pts / 10k)
assertTest("Gold: 0 VND earns 0 points", calculateEarnPoints(0.0, 'GOLD') === 0);
assertTest("Gold: 9,999 VND earns 0 points", calculateEarnPoints(9999.0, 'GOLD') === 0);
assertTest("Gold: 10,000 VND earns 2 points", calculateEarnPoints(10000.0, 'GOLD') === 2);
assertTest("Gold: 20,000 VND earns 4 points", calculateEarnPoints(20000.0, 'GOLD') === 4);
assertTest("Gold: 100,000 VND earns 20 points", calculateEarnPoints(100000.0, 'GOLD') === 20);
assertTest("Gold: 250,000 VND earns 50 points", calculateEarnPoints(250000.0, 'GOLD') === 50);

// DIAMOND (Ratio 3.0 pts / 10k)
assertTest("Diamond: 0 VND earns 0 points", calculateEarnPoints(0.0, 'DIAMOND') === 0);
assertTest("Diamond: 9,999 VND earns 0 points", calculateEarnPoints(9999.0, 'DIAMOND') === 0);
assertTest("Diamond: 10,000 VND earns 3 points", calculateEarnPoints(10000.0, 'DIAMOND') === 3);
assertTest("Diamond: 20,000 VND earns 6 points", calculateEarnPoints(20000.0, 'DIAMOND') === 6);
assertTest("Diamond: 100,000 VND earns 30 points", calculateEarnPoints(100000.0, 'DIAMOND') === 30);
assertTest("Diamond: 250,000 VND earns 75 points", calculateEarnPoints(250000.0, 'DIAMOND') === 75);

// =============================================================================
// TEST SUITE 3: POINT REDEMPTION MATH & VOUCHER PACKAGES
// =============================================================================
echo "\n--- 3. Testing Redemption Math & Voucher Packages ---\n";

$settings = getLoyaltySettings();
assertTest("Settings load point_to_vnd_rate = 100", (int)$settings['point_to_vnd_rate'] === 100);
assertTest("Settings load spending_step_vnd = 10000", (int)$settings['spending_step_vnd'] === 10000);
assertTest("Settings load min_redeem_points = 100", (int)$settings['min_redeem_points'] === 100);
assertTest("Settings load max_redeem_percentage = 100", (int)$settings['max_redeem_percentage'] === 100);

$packages = getVoucherPackages();
assertTest("Voucher packages contains 3 standard packages", count($packages) === 3);
assertTest("Package 1: 100 pts -> 10,000 VND", $packages[1]['points_cost'] === 100 && (int)$packages[1]['discount_amount'] === 10000);
assertTest("Package 2: 200 pts -> 20,000 VND", $packages[2]['points_cost'] === 200 && (int)$packages[2]['discount_amount'] === 20000);
assertTest("Package 3: 500 pts -> 50,000 VND", $packages[3]['points_cost'] === 500 && (int)$packages[3]['discount_amount'] === 50000);

// Test updateLoyaltySettings and cache clearing
$origRate = $settings['point_to_vnd_rate'];
updateLoyaltySettings(['point_to_vnd_rate' => 120]);
$updatedSettings = getLoyaltySettings();
assertTest("Dynamic settings update took effect (120)", (int)$updatedSettings['point_to_vnd_rate'] === 120);
// Restore original settings
updateLoyaltySettings(['point_to_vnd_rate' => $origRate]);
$restoredSettings = getLoyaltySettings();
assertTest("Settings restored to original rate (100)", (int)$restoredSettings['point_to_vnd_rate'] === 100);

// =============================================================================
// TEST SUITE 4: SERVICE OPERATIONS & TRANSACTION INTEGRITY (DATABASE TESTS)
// =============================================================================
echo "\n--- 4. Testing Service Operations with Database Fixtures ---\n";

// Create test fixture user
$testEmail = 'loyalty_test_' . time() . '_' . mt_rand(1000, 9999) . '@fastfood.test';
$db->prepare("INSERT INTO users (role_id, full_name, email, password_hash, member_tier, points_balance, total_spent) 
    VALUES (2, 'Test Loyalty User', ?, 'dummy_hash', 'BRONZE', 350, 800000.00)")
   ->execute([$testEmail]);
$testUserId = (int)$db->lastInsertId();

assertTest("Created test user with ID {$testUserId}", $testUserId > 0);

// Test 4.1: Direct Checkout Redemption
$checkoutOrderCode = 'TEST-ORD-' . strtoupper(bin2hex(random_bytes(3)));
$db->prepare("INSERT INTO orders (order_code, customer_id, restaurant_id, delivery_address, recipient_name, recipient_phone, subtotal, shipping_fee, total_amount, order_status)
    VALUES (?, ?, 1, 'Test Address', 'Test Recipient', '0912345678', 150000.00, 18000.00, 168000.00, 'placed')")
   ->execute([$checkoutOrderCode, $testUserId]);
$testOrderId = (int)$db->lastInsertId();

// Try redeeming more than balance (e.g. 500 points when balance is 350)
$overRedeem = redeemPointsAtCheckout($testUserId, $testOrderId, 500, $checkoutOrderCode);
assertTest("Redeeming points exceeding balance is rejected", $overRedeem['success'] === false);

// Try redeeming below min_redeem_points (e.g. 50 points)
$underMinRedeem = redeemPointsAtCheckout($testUserId, $testOrderId, 50, $checkoutOrderCode);
assertTest("Redeeming points below minimum (100) is rejected", $underMinRedeem['success'] === false);

// Valid redemption (100 points = 10,000 VND)
$validRedeem = redeemPointsAtCheckout($testUserId, $testOrderId, 100, $checkoutOrderCode);
assertTest("Valid redemption of 100 points succeeds", $validRedeem['success'] === true && (int)$validRedeem['discount_amount'] === 10000);
assertTest("New balance is 250 points", (int)$validRedeem['new_balance'] === 250);

// Verify database state after redemption
$uCheck = $db->query("SELECT points_balance FROM users WHERE id = {$testUserId}")->fetch();
assertTest("User balance in DB updated to 250", (int)$uCheck['points_balance'] === 250);

$oCheck = $db->query("SELECT points_used, points_discount FROM orders WHERE id = {$testOrderId}")->fetch();
assertTest("Order points_used in DB is 100", (int)$oCheck['points_used'] === 100);
assertTest("Order points_discount in DB is 10,000", (int)$oCheck['points_discount'] === 10000);

// Test 4.2: Voucher Exchange Flow
$exchangeRes = exchangePointsForVoucher($testUserId, 2); // 200 pts for 20k voucher
assertTest("Voucher exchange for 200 points succeeds", $exchangeRes['success'] === true);
assertTest("Voucher coupon code starts with FF20K", strpos($exchangeRes['coupon_code'], 'FF20K') === 0);
assertTest("User balance after voucher exchange is 50", (int)$exchangeRes['new_balance'] === 50);

// Verify coupon in DB
$cCheck = $db->prepare("SELECT * FROM coupons WHERE code = ?");
$cCheck->execute([$exchangeRes['coupon_code']]);
$couponRow = $cCheck->fetch();
assertTest("Coupon row exists with user_id = {$testUserId}", (int)$couponRow['user_id'] === $testUserId);
assertTest("Coupon discount value is 20,000 VND", (float)$couponRow['discount_value'] === 20000.0);
assertTest("Coupon is active with usage_limit = 1", (int)$couponRow['is_active'] === 1 && (int)$couponRow['usage_limit'] === 1);

// Try exchanging when balance is 50 (< 100 required)
$failExchange = exchangePointsForVoucher($testUserId, 1);
assertTest("Voucher exchange fails when balance is insufficient", $failExchange['success'] === false);

// Test 4.3: Order Completion Earning & Auto-Upgrade
// Create a completing order of 300,000 VND (current total_spent = 800,000; + 300,000 = 1,100,000 -> upgrades to SILVER!)
$completeOrderCode = 'TEST-COMP-' . strtoupper(bin2hex(random_bytes(3)));
$db->prepare("INSERT INTO orders (order_code, customer_id, restaurant_id, delivery_address, recipient_name, recipient_phone, subtotal, shipping_fee, total_amount, order_status)
    VALUES (?, ?, 1, 'Test Address', 'Test Recipient', '0912345678', 300000.00, 0.00, 300000.00, 'completed')")
   ->execute([$completeOrderCode, $testUserId]);
$completeOrderId = (int)$db->lastInsertId();

// Award points for this completed order
// User is currently BRONZE (ratio 1.0): 300,000 VND -> 30 points earned!
$earnRes = processOrderEarnPoints($completeOrderId);
assertTest("Earning points for completed order succeeds", $earnRes['success'] === true);
assertTest("Points earned is 30 points", (int)$earnRes['points_earned'] === 30);
assertTest("New balance is 80 points (50 + 30)", (int)$earnRes['new_balance'] === 80);
assertTest("Total spent upgraded from 800k to 1.1M", (float)$earnRes['total_spent'] === 1100000.0);
assertTest("Tier automatically upgraded to SILVER", $earnRes['new_tier'] === 'SILVER');

// Verify DB after order earn
$uCheck2 = $db->query("SELECT member_tier, points_balance, total_spent FROM users WHERE id = {$testUserId}")->fetch();
assertTest("User in DB has tier = SILVER", $uCheck2['member_tier'] === 'SILVER');
assertTest("User in DB has total_spent = 1,100,000", (float)$uCheck2['total_spent'] === 1100000.0);

// Test 4.4: SyncUserTier
$syncRes = syncUserTier($testUserId);
assertTest("syncUserTier runs successfully", $syncRes['success'] === true);
assertTest("syncUserTier reports correct total_spent from completed orders", (float)$syncRes['total_spent'] === 300000.0);
assertTest("syncUserTier correctly recalibrates tier to BRONZE for 300k actual completed orders", $syncRes['tier'] === 'BRONZE');

// Restore total_spent to 1,100,000 and tier to SILVER for subsequent cancellation test
$db->prepare("UPDATE users SET total_spent = 1100000.00, member_tier = 'SILVER' WHERE id = ?")->execute([$testUserId]);

// =============================================================================
// TEST SUITE 5: IDEMPOTENCY PROTECTION (processOrderEarnPoints)
// =============================================================================
echo "\n--- 5. Testing Idempotency Protection ---\n";

// Call processOrderEarnPoints a SECOND time on the same order!
$secondEarnRes = processOrderEarnPoints($completeOrderId);
assertTest("Second call to processOrderEarnPoints is rejected (already awarded)", $secondEarnRes['success'] === false);
assertTest("Second call indicates already_awarded = true", !empty($secondEarnRes['already_awarded']));

// Verify DB balance is UNCHANGED after second call
$uCheck3 = $db->query("SELECT points_balance, total_spent FROM users WHERE id = {$testUserId}")->fetch();
assertTest("User balance in DB remains exactly 80 (no duplicate points)", (int)$uCheck3['points_balance'] === 80);
assertTest("User total_spent remains exactly 1,100,000", (float)$uCheck3['total_spent'] === 1100000.0);

// Verify transaction ledger has only 1 EARN record for this order
$txCheck = $db->prepare("SELECT COUNT(*) FROM point_transactions WHERE order_id = ? AND transaction_type = 'EARN'");
$txCheck->execute([$completeOrderId]);
assertTest("Ledger has exactly 1 EARN record for this order", (int)$txCheck->fetchColumn() === 1);

// =============================================================================
// TEST SUITE 6: ORDER CANCELLATION & ROLLBACK (handleOrderCancellation)
// =============================================================================
echo "\n--- 6. Testing Order Cancellation & Rollback ---\n";

// Cancel the completed order: earned points (30) should be revoked, spending (300k) deducted, tier downgraded back to BRONZE!
$cancelCompletedRes = handleOrderCancellation($completeOrderId);
assertTest("Cancellation of completed order succeeds", $cancelCompletedRes['success'] === true);
assertTest("Revoked points is 30", (int)$cancelCompletedRes['points_revoked'] === 30);
assertTest("New balance is 50 (80 - 30)", (int)$cancelCompletedRes['new_balance'] === 50);
assertTest("New total spent is 800,000", (float)$cancelCompletedRes['new_spent'] === 800000.0);
assertTest("Tier downgraded back to BRONZE", $cancelCompletedRes['new_tier'] === 'BRONZE');

// Idempotency test for cancellation of earned points: second cancellation call does NOT revoke points again!
$cancelSecondCall = handleOrderCancellation($completeOrderId);
assertTest("Second cancellation call does not re-revoke points", (int)$cancelSecondCall['points_revoked'] === 0);

// Cancel the order that used 100 points at checkout ($testOrderId): used points should be RESTORED!
$cancelUsedRes = handleOrderCancellation($testOrderId);
assertTest("Cancellation of order with points used succeeds", $cancelUsedRes['success'] === true);
assertTest("Restored points is 100", (int)$cancelUsedRes['points_restored'] === 100);
assertTest("New balance after restoration is 150 (50 + 100)", (int)$cancelUsedRes['new_balance'] === 150);

// Idempotency test for point restoration: second call does not restore points again!
$cancelUsedSecond = handleOrderCancellation($testOrderId);
assertTest("Second cancellation call does not duplicate point restoration", (int)$cancelUsedSecond['points_restored'] === 0);

// =============================================================================
// TEST SUITE 7: LEDGER HISTORY (getUserPointHistory)
// =============================================================================
echo "\n--- 7. Testing User Point History Ledger ---\n";

$history = getUserPointHistory($testUserId, 50);
assertTest("Ledger history contains records", count($history) >= 4);

$typesRecorded = array_column($history, 'transaction_type');
assertTest("Ledger contains REDEEM_CHECKOUT", in_array('REDEEM_CHECKOUT', $typesRecorded));
assertTest("Ledger contains REDEEM_VOUCHER", in_array('REDEEM_VOUCHER', $typesRecorded));
assertTest("Ledger contains EARN", in_array('EARN', $typesRecorded));
assertTest("Ledger contains REFUND_REVOKE", in_array('REFUND_REVOKE', $typesRecorded));
assertTest("Ledger contains REFUND_RESTORE", in_array('REFUND_RESTORE', $typesRecorded));

// =============================================================================
// CLEANUP & TEARDOWN
// =============================================================================
echo "\n--- 8. Cleaning up Test Fixtures ---\n";
try {
    $db->prepare("DELETE FROM point_transactions WHERE user_id = ?")->execute([$testUserId]);
    $db->prepare("DELETE FROM coupons WHERE user_id = ?")->execute([$testUserId]);
    $db->prepare("DELETE FROM orders WHERE id IN (?, ?)")->execute([$testOrderId, $completeOrderId]);
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$testUserId]);
    echo "  Cleaned up all test fixture records successfully.\n";
} catch (Exception $e) {
    echo "  Warning: Cleanup encountered: " . $e->getMessage() . "\n";
}

echo "\n=========================================================================\n";
echo "   TEST SUMMARY: {$passedAssertions} PASSED, {$failedAssertions} FAILED (TOTAL: {$totalAssertions})\n";
echo "=========================================================================\n";

if ($failedAssertions > 0) {
    echo "\nFailed Assertions Detail:\n";
    foreach ($failedDetails as $f) {
        echo "- {$f['name']}: {$f['detail']}\n";
    }
    exit(1);
} else {
    echo "\nALL TESTS PASSED WITH 100% SUCCESS RATE!\n";
    exit(0);
}
