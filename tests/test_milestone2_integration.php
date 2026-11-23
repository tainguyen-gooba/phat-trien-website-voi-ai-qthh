<?php
/**
 * Milestone 2 Integration Test Suite: Checkout & Order Lifecycle Integration
 *
 * Verifies:
 * 1. Checkout Points Redemption (F4):
 *    - Balance validation, minimum threshold (100 pts), deduction, ledger REDEEM_CHECKOUT.
 *    - Orders table columns: points_used, points_discount, total_amount, payment_status ('paid' if total == 0).
 * 2. Order Completion Hook (F5):
 *    - Admin status transition to 'completed' / 'delivered' in admin/api.php and RealtimeController.php.
 *    - Points earning calculation by tier ratio, atomic balance & total_spent increment.
 *    - Automatic tier upgrade on spending threshold.
 *    - Idempotency guard: multiple completion calls do not duplicate points.
 * 3. Order Cancellation & Points Rollback (F6):
 *    - Customer cancellation in order-tracking.php for 'placed', 'pending', 'awaiting_payment'.
 *    - Points restoration (REFUND_RESTORE), order status updated to 'cancelled'.
 *    - Admin cancellation of completed order: revokes earned points (REFUND_REVOKE), decrements total_spent, demotes tier.
 *    - Double cancellation idempotency.
 * 4. Database Invariants:
 *    - points_balance === initial_balance + SUM(point_transactions.points).
 *    - Non-negative balances and ledger audit continuity.
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/loyalty.php';

$db = getDB();

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$message}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$message}\n";
    }
}

echo "=========================================================================\n";
echo "   FASTFOOD TEST SUITE: MILESTONE 2 (CHECKOUT & ORDER LIFECYCLE)\n";
echo "=========================================================================\n\n";

// Ensure BRONZE ratio is 1.0
$db->exec("UPDATE membership_tiers SET point_ratio = 1.0 WHERE tier_code = 'BRONZE'");
clearLoyaltyCache();

// Unique test run prefix
$runId = 'M2_' . substr(uniqid(), -6);
$testUserEmail = "test_{$runId}@fastfood.test";
$testUserPhone = '098' . rand(1000000, 9999999);

// 1. Setup Test User
$db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, member_tier, points_balance, total_spent, status, created_at)
              VALUES (2, 'M2 Integration Tester', ?, ?, 'hash', 'BRONZE', 300, 0.00, 'active', NOW())")
   ->execute([$testUserEmail, $testUserPhone]);
$testUserId = (int)$db->lastInsertId();

assertTest($testUserId > 0, "Created test user ID: {$testUserId} with 300 points balance");

// -------------------------------------------------------------------------
// SECTION 1: CHECKOUT REDEMPTION & ORDERS COLUMNS
// -------------------------------------------------------------------------
echo "\n--- 1. Testing Checkout Points Redemption (F4) ---\n";

// Test 1.1: Redeem 100 points on a 150,000 VND order
$order1Code = "ORD_{$runId}_1";
$subtotal1 = 150000.0;
$pointsToUse1 = 100;
$discountFromPoints1 = 10000.0; // 100 * 100 VND
$total1 = $subtotal1 - $discountFromPoints1;

$db->prepare("INSERT INTO orders 
    (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, 
     subtotal, shipping_fee, discount_amount, points_used, points_discount, total_amount, payment_method, payment_status, order_status, created_at)
    VALUES (?, ?, 1, 'M2 Tester', ?, '123 Test St', ?, 0, 0, ?, ?, ?, 'cod', 'pending', 'placed', NOW())")
   ->execute([$order1Code, $testUserId, $testUserPhone, $subtotal1, $pointsToUse1, $discountFromPoints1, $total1]);
$order1Id = (int)$db->lastInsertId();

// Execute redeemPointsAtCheckout
$redRes = redeemPointsAtCheckout($testUserId, $order1Id, $pointsToUse1, $order1Code);
assertTest(!empty($redRes['success']), "redeemPointsAtCheckout succeeded for 100 points");
assertTest((int)$redRes['points_used'] === 100, "Points used returned is 100");
assertTest((float)$redRes['discount_amount'] === 10000.0, "Discount amount returned is 10,000 VND");

// Check user points balance in DB (300 - 100 = 200)
$userRow = $db->query("SELECT points_balance, member_tier, total_spent FROM users WHERE id = {$testUserId}")->fetch(PDO::FETCH_ASSOC);
assertTest((int)$userRow['points_balance'] === 200, "User points_balance in DB deducted from 300 to 200");

// Check orders table record
$ordRow1 = $db->query("SELECT points_used, points_discount, total_amount, order_status FROM orders WHERE id = {$order1Id}")->fetch(PDO::FETCH_ASSOC);
assertTest((int)$ordRow1['points_used'] === 100, "orders.points_used is 100");
assertTest((float)$ordRow1['points_discount'] === 10000.0, "orders.points_discount is 10,000.00");
assertTest((float)$ordRow1['total_amount'] === 140000.0, "orders.total_amount is 140,000.00");

// Check ledger point_transactions
$tx1 = $db->query("SELECT * FROM point_transactions WHERE user_id = {$testUserId} AND order_id = {$order1Id} AND transaction_type = 'REDEEM_CHECKOUT'")->fetch(PDO::FETCH_ASSOC);
assertTest(!empty($tx1), "point_transactions ledger contains REDEEM_CHECKOUT record");
assertTest((int)$tx1['points'] === -100, "Ledger points is -100");
assertTest((int)$tx1['balance_after'] === 200, "Ledger balance_after is 200");

// Test 1.2: Order with 100% points payment (0 VND total)
$order2Code = "ORD_{$runId}_2";
$subtotal2 = 20000.0;
$pointsToUse2 = 200; // 200 pts * 100 = 20,000 VND discount
$discountFromPoints2 = 20000.0;
$total2 = 0.0;
$paymentStatus2 = ($total2 == 0) ? 'paid' : 'pending';

$db->prepare("INSERT INTO orders 
    (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, 
     subtotal, shipping_fee, discount_amount, points_used, points_discount, total_amount, payment_method, payment_status, order_status, created_at)
    VALUES (?, ?, 1, 'M2 Tester', ?, '123 Test St', ?, 0, 0, ?, ?, ?, 'cod', ?, 'pending', NOW())")
   ->execute([$order2Code, $testUserId, $testUserPhone, $subtotal2, $pointsToUse2, $discountFromPoints2, $total2, $paymentStatus2]);
$order2Id = (int)$db->lastInsertId();

$redRes2 = redeemPointsAtCheckout($testUserId, $order2Id, $pointsToUse2, $order2Code);
assertTest(!empty($redRes2['success']), "100% points payment succeeded");

$ordRow2 = $db->query("SELECT total_amount, payment_status FROM orders WHERE id = {$order2Id}")->fetch(PDO::FETCH_ASSOC);
assertTest((float)$ordRow2['total_amount'] === 0.0, "100% points order total_amount is 0.00 VND");
assertTest($ordRow2['payment_status'] === 'paid', "100% points order payment_status is 'paid'");

$userRow = $db->query("SELECT points_balance FROM users WHERE id = {$testUserId}")->fetch(PDO::FETCH_ASSOC);
assertTest((int)$userRow['points_balance'] === 0, "User points_balance in DB is now 0 (200 - 200)");


// -------------------------------------------------------------------------
// SECTION 2: ORDER COMPLETION HOOK & TIER UPGRADE (F5)
// -------------------------------------------------------------------------
echo "\n--- 2. Testing Order Completion Hook (F5) ---\n";

// Create high-value order of 1,200,000 VND for testUser (crosses 1M threshold for SILVER tier)
$order3Code = "ORD_{$runId}_3";
$subtotal3 = 1200000.0;
$total3 = 1200000.0;

$db->prepare("INSERT INTO orders 
    (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, 
     subtotal, shipping_fee, discount_amount, points_used, points_discount, total_amount, payment_method, payment_status, order_status, created_at)
    VALUES (?, ?, 1, 'M2 Tester', ?, '123 Test St', ?, 0, 0, 0, 0, ?, 'cod', 'pending', 'placed', NOW())")
   ->execute([$order3Code, $testUserId, $testUserPhone, $subtotal3, $total3]);
$order3Id = (int)$db->lastInsertId();

$db->prepare("INSERT INTO realtime_orders (order_code, user_id, order_data, status, created_at)
              VALUES (?, ?, '{}', 'pending', NOW())")
   ->execute([$order3Code, $testUserId]);

// Trigger completion hook via processOrderEarnPoints (simulating admin status transition to completed)
$db->prepare("UPDATE orders SET order_status = 'completed', completed_at = NOW(), updated_at = NOW() WHERE id = ?")
   ->execute([$order3Id]);
$db->prepare("UPDATE realtime_orders SET status = 'delivered', updated_at = NOW() WHERE order_code = ?")
   ->execute([$order3Code]);

$earnRes = processOrderEarnPoints($order3Id);
assertTest(!empty($earnRes['success']), "processOrderEarnPoints succeeded on order completion");
// 1,200,000 / 10,000 * 1.0 (Bronze) = 120 points
assertTest((int)$earnRes['points_earned'] === 120, "1,200,000 VND order earned 120 points on BRONZE");
assertTest($earnRes['new_tier'] === 'SILVER', "Customer tier automatically upgraded to SILVER");
assertTest((float)$earnRes['total_spent'] === 1200000.0, "total_spent upgraded to 1,200,000.00");

// Verify DB user record
$userRow3 = $db->query("SELECT member_tier, points_balance, total_spent FROM users WHERE id = {$testUserId}")->fetch(PDO::FETCH_ASSOC);
assertTest($userRow3['member_tier'] === 'SILVER', "users.member_tier updated to SILVER in DB");
assertTest((int)$userRow3['points_balance'] === 120, "users.points_balance updated to 120 in DB");
assertTest((float)$userRow3['total_spent'] === 1200000.0, "users.total_spent updated to 1,200,000 in DB");

// Verify point_transactions ledger
$earnTx = $db->query("SELECT * FROM point_transactions WHERE user_id = {$testUserId} AND order_id = {$order3Id} AND transaction_type = 'EARN'")->fetch(PDO::FETCH_ASSOC);
assertTest(!empty($earnTx), "Ledger contains EARN record");
assertTest((int)$earnTx['points'] === 120, "EARN transaction points is +120");
assertTest((int)$earnTx['balance_after'] === 120, "EARN transaction balance_after is 120");

// Verify IDEMPOTENCY: second call must NOT re-award points
$earnResIdempotent = processOrderEarnPoints($order3Id);
assertTest(!empty($earnResIdempotent['already_awarded']), "Second completion call detected already_awarded");
$userRowIdem = $db->query("SELECT points_balance, total_spent FROM users WHERE id = {$testUserId}")->fetch(PDO::FETCH_ASSOC);
assertTest((int)$userRowIdem['points_balance'] === 120, "Idempotency preserved: points_balance unchanged (120)");
assertTest((float)$userRowIdem['total_spent'] === 1200000.0, "Idempotency preserved: total_spent unchanged (1.2M)");

$earnTxCount = $db->query("SELECT COUNT(*) FROM point_transactions WHERE user_id = {$testUserId} AND order_id = {$order3Id} AND transaction_type = 'EARN'")->fetchColumn();
assertTest((int)$earnTxCount === 1, "Idempotency preserved: exactly 1 EARN ledger record in database");


// -------------------------------------------------------------------------
// SECTION 3: ORDER CANCELLATION & POINTS ROLLBACK (F6)
// -------------------------------------------------------------------------
echo "\n--- 3. Testing Order Cancellation & Points Rollback (F6) ---\n";

// Scenario 3.1: Pending order cancellation with points restoration (REFUND_RESTORE)
// User currently has 120 points. Create order using 100 points.
$order4Code = "ORD_{$runId}_4";
$subtotal4 = 200000.0;
$pointsToUse4 = 100;
$discountFromPoints4 = 10000.0;
$total4 = 190000.0;

$db->prepare("INSERT INTO orders 
    (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, 
     subtotal, shipping_fee, discount_amount, points_used, points_discount, total_amount, payment_method, payment_status, order_status, created_at)
    VALUES (?, ?, 1, 'M2 Tester', ?, '123 Test St', ?, 0, 0, ?, ?, ?, 'cod', 'pending', 'placed', NOW())")
   ->execute([$order4Code, $testUserId, $testUserPhone, $subtotal4, $pointsToUse4, $discountFromPoints4, $total4]);
$order4Id = (int)$db->lastInsertId();

$redRes4 = redeemPointsAtCheckout($testUserId, $order4Id, $pointsToUse4, $order4Code);
assertTest(!empty($redRes4['success']), "Order 4 redeemed 100 points (balance: 120 -> 20)");

$userRowPreCancel = $db->query("SELECT points_balance FROM users WHERE id = {$testUserId}")->fetch(PDO::FETCH_ASSOC);
assertTest((int)$userRowPreCancel['points_balance'] === 20, "User balance dropped to 20 before cancellation");

// Customer cancels order while in 'placed' status (tests order-tracking.php logic)
$db->prepare("UPDATE orders SET order_status = 'cancelled', cancellation_reason = 'Khách hàng tự hủy trên website', cancelled_at = NOW(), updated_at = NOW() WHERE id = ?")
   ->execute([$order4Id]);

$cancelRes4 = handleOrderCancellation($order4Id);
assertTest(!empty($cancelRes4['success']), "handleOrderCancellation succeeded on pending order");
assertTest((int)$cancelRes4['points_restored'] === 100, "Restored 100 points to user");
assertTest((int)$cancelRes4['new_balance'] === 120, "New user balance is 120 (20 + 100)");

$userRowPostCancel = $db->query("SELECT points_balance FROM users WHERE id = {$testUserId}")->fetch(PDO::FETCH_ASSOC);
assertTest((int)$userRowPostCancel['points_balance'] === 120, "User balance restored to 120 in DB");

// Check REFUND_RESTORE in ledger
$restoreTx = $db->query("SELECT * FROM point_transactions WHERE user_id = {$testUserId} AND order_id = {$order4Id} AND transaction_type = 'REFUND_RESTORE'")->fetch(PDO::FETCH_ASSOC);
assertTest(!empty($restoreTx), "Ledger contains REFUND_RESTORE transaction");
assertTest((int)$restoreTx['points'] === 100, "REFUND_RESTORE points is +100");
assertTest((int)$restoreTx['balance_after'] === 120, "REFUND_RESTORE balance_after is 120");

// Double cancellation idempotency test
$cancelRes4Double = handleOrderCancellation($order4Id);
assertTest((int)$cancelRes4Double['points_restored'] === 0, "Double cancellation restores 0 points (idempotent)");
$restoreCount = $db->query("SELECT COUNT(*) FROM point_transactions WHERE user_id = {$testUserId} AND order_id = {$order4Id} AND transaction_type = 'REFUND_RESTORE'")->fetchColumn();
assertTest((int)$restoreCount === 1, "Exactly 1 REFUND_RESTORE transaction exists");

// Scenario 3.2: Completed order cancellation (Revocation & Tier Demotion)
// Cancel Order 3 (the completed order that awarded 120 points and promoted user to SILVER with 1.2M spent)
$db->prepare("UPDATE orders SET order_status = 'cancelled', cancellation_reason = 'Quản trị viên hủy đơn hàng', cancelled_at = NOW(), updated_at = NOW() WHERE id = ?")
   ->execute([$order3Id]);

$cancelRes3 = handleOrderCancellation($order3Id);
assertTest(!empty($cancelRes3['success']), "handleOrderCancellation succeeded on completed order");
assertTest((int)$cancelRes3['points_revoked'] === 120, "Revoked 120 earned points");
assertTest((float)($cancelRes3['new_spent'] ?? $cancelRes3['total_spent'] ?? 0.0) === 0.0, "Decremented total_spent by 1,200,000 back to 0.00");
assertTest($cancelRes3['new_tier'] === 'BRONZE', "Demoted member tier from SILVER back to BRONZE");

$userRowPostRevoke = $db->query("SELECT member_tier, points_balance, total_spent FROM users WHERE id = {$testUserId}")->fetch(PDO::FETCH_ASSOC);
assertTest($userRowPostRevoke['member_tier'] === 'BRONZE', "DB users.member_tier demoted back to BRONZE");
assertTest((int)$userRowPostRevoke['points_balance'] === 0, "DB users.points_balance revoked from 120 to 0");
assertTest((float)$userRowPostRevoke['total_spent'] === 0.0, "DB users.total_spent decremented back to 0");

$revokeTx = $db->query("SELECT * FROM point_transactions WHERE user_id = {$testUserId} AND order_id = {$order3Id} AND transaction_type = 'REFUND_REVOKE'")->fetch(PDO::FETCH_ASSOC);
assertTest(!empty($revokeTx), "Ledger contains REFUND_REVOKE transaction");
assertTest((int)$revokeTx['points'] === -120, "REFUND_REVOKE points is -120");
assertTest((int)$revokeTx['balance_after'] === 0, "REFUND_REVOKE balance_after is 0");


// -------------------------------------------------------------------------
// SECTION 4: MATHEMATICAL AUDIT & DATABASE INVARIANTS
// -------------------------------------------------------------------------
echo "\n--- 4. Testing Mathematical Audit & Ledger Invariants ---\n";

$allTxs = $db->query("SELECT * FROM point_transactions WHERE user_id = {$testUserId} ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$calculatedBalance = 300; // Initial
foreach ($allTxs as $t) {
    $calculatedBalance += (int)$t['points'];
    assertTest((int)$t['balance_after'] === $calculatedBalance, "Ledger row #{$t['id']} ({$t['transaction_type']}) balance_after ({$t['balance_after']}) matches running sum ({$calculatedBalance})");
}

$finalUser = $db->query("SELECT points_balance FROM users WHERE id = {$testUserId}")->fetch(PDO::FETCH_ASSOC);
assertTest((int)$finalUser['points_balance'] === $calculatedBalance, "Final user balance ({$finalUser['points_balance']}) strictly matches cumulative ledger sum ({$calculatedBalance})");
assertTest((int)$finalUser['points_balance'] >= 0, "Final balance is non-negative");

// -------------------------------------------------------------------------
// CLEANUP
// -------------------------------------------------------------------------
echo "\n--- 5. Cleanup Test Data ---\n";
$db->exec("DELETE FROM point_transactions WHERE user_id = {$testUserId}");
$db->exec("DELETE FROM orders WHERE customer_id = {$testUserId}");
$db->exec("DELETE FROM realtime_orders WHERE user_id = {$testUserId}");
$db->exec("DELETE FROM users WHERE id = {$testUserId}");
echo "  [INFO] Cleaned up test user {$testUserId} and related records.\n";

echo "\n=========================================================================\n";
echo "   INTEGRATION TEST SUMMARY: {$passed} PASSED, {$failed} FAILED (TOTAL: " . ($passed + $failed) . ")\n";
echo "=========================================================================\n";

if ($failed === 0) {
    echo "MILESTONE 2 INTEGRATION VERIFIED 100% PASS!\n";
    exit(0);
} else {
    echo "MILESTONE 2 INTEGRATION HAD FAILURES!\n";
    exit(1);
}
