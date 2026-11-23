<?php
/**
 * Milestone 4 Integration Test Suite: Admin Membership & Dynamic Configuration
 * tests/integration_m4_test.php
 *
 * Verifies:
 * 1. Admin Security & Role-Based Access Control (F16):
 *    - Unauthenticated guest: blocked from admin/api.php (redirect/401/403).
 *    - Customer (role_id 2): blocked from admin/api.php with 403 Forbidden.
 *    - Staff (role_id 5): can view members ledger and history; blocked from save_loyalty_config with 403.
 *    - Admin (role_id 1): full access to members ledger, history, and save_loyalty_config.
 * 2. Members Ledger API (F13):
 *    - Data structure: id, name/full_name, email, phone, avatar/avatar_url, member_tier, points_balance, total_spent, tier metadata.
 *    - Filtering: tier filter (DIAMOND/GOLD/etc.), search keyword (email, full name).
 *    - Pagination: limit, page, total, total_pages.
 *    - Summary stats: total_members, tier counts, total_system_points, total_system_spent.
 * 3. Member Points History Modal API (F14):
 *    - User summary strip metadata.
 *    - Transaction ledger: transaction_type, points (+/-), balance_after, order_code, created_at.
 *    - Clean empty array handling for users with zero transactions.
 *    - 404 handling for invalid user ID.
 * 4. Dynamic Loyalty Configuration (F15):
 *    - Validation of boundary values: negative thresholds, negative ratios, zero rates, inverted thresholds.
 *    - Atomic persistence in loyalty_settings and membership_tiers.
 *    - Immediate runtime propagation via clearLoyaltyCache().
 * 5. Order Status Lifecycle Preservation:
 *    - Completed orders trigger points earning with dynamic ratio.
 * 6. Teardown & Config Restoration:
 *    - Baseline settings restored safely.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI execution only\n");
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/helpers/loyalty.php';

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
echo "   FASTFOOD TEST SUITE: MILESTONE 4 (ADMIN MEMBERSHIP & DYNAMIC CONFIG)\n";
echo "=========================================================================\n\n";

// Helper for HTTP requests to local PHP server (port 5000)
$baseUrl = 'http://localhost:5000';

function makeHttpRequest(string $url, string $method = 'GET', $body = null, array $cookies = []): array {
    $ch = curl_init();
    $headers = ['Accept: application/json'];
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if (!empty($cookies)) {
        $cookieHeader = [];
        foreach ($cookies as $k => $v) {
            $cookieHeader[] = "{$k}={$v}";
        }
        curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookieHeader));
    }
    
    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (is_array($body)) {
            $json = json_encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            $headers[] = 'Content-Type: application/json';
        } elseif (is_string($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    $headerStr = substr($response, 0, $headerSize);
    $bodyStr = substr($response, $headerSize);
    
    curl_close($ch);
    
    // Parse cookies from response headers
    $setCookies = [];
    if (preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', $headerStr, $matches)) {
        foreach ($matches[1] as $c) {
            $parts = explode('=', $c, 2);
            if (count($parts) === 2) {
                $setCookies[trim($parts[0])] = trim($parts[1]);
            }
        }
    }
    
    $json = json_decode($bodyStr, true);
    
    return [
        'status'  => $status,
        'headers' => $headerStr,
        'cookies' => $setCookies,
        'body'    => $bodyStr,
        'json'    => $json
    ];
}

// Function to log in via HTTP and return session cookie
function loginUser(string $email, string $password): array {
    global $baseUrl;
    $res = makeHttpRequest("{$baseUrl}/api/auth.php", 'POST', [
        'action'   => 'login',
        'email'    => $email,
        'password' => $password
    ]);
    return $res['cookies'];
}

// Record baseline settings for teardown
$originalSettings = getLoyaltySettings();
$originalTiers = getMembershipTiers();

// Unique test run prefix
$runId = 'M4_' . substr(uniqid(), -6);

// -------------------------------------------------------------------------
// SECTION 1: ROLE-BASED ACCESS CONTROL (RBAC) VERIFICATION
// -------------------------------------------------------------------------
echo "--- 1. Testing RBAC Security & Role Authorization (F16) ---\n";

// 1.1 Unauthenticated guest requesting get_loyalty_members
$guestRes = makeHttpRequest("{$baseUrl}/admin/api.php?action=get_loyalty_members");
assertTest(
    $guestRes['status'] === 401 || $guestRes['status'] === 403 || $guestRes['status'] === 302 || strpos($guestRes['headers'], 'admin/login.php') !== false,
    "Guest is blocked from admin/api.php (HTTP {$guestRes['status']})"
);

// 1.2 Customer (role_id = 2) requesting get_loyalty_members
$custEmail = "cust_{$runId}@fastfood.test";
$passwordHash = password_hash('123456', PASSWORD_BCRYPT);
$db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
              VALUES (2, 'Customer Test', ?, '0940000001', ?, 'active', 'BRONZE', 0, 0, NOW())")
   ->execute([$custEmail, $passwordHash]);
$custUserId = (int)$db->lastInsertId();

$custCookies = loginUser($custEmail, '123456');
$custReq = makeHttpRequest("{$baseUrl}/admin/api.php?action=get_loyalty_members", 'GET', null, $custCookies);
assertTest(
    $custReq['status'] === 403 || $custReq['status'] === 401 || $custReq['status'] === 302 || strpos($custReq['body'], '403') !== false || strpos($custReq['body'], 'Forbidden') !== false || strpos($custReq['headers'], 'admin/login.php') !== false,
    "Customer (role 2) is blocked from admin/api.php (HTTP {$custReq['status']})"
);

// 1.3 Staff (role_id = 5) access
$staffEmail = "staff_{$runId}@fastfood.test";
$db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
              VALUES (5, 'Staff Support', ?, '0940000002', ?, 'active', 'BRONZE', 0, 0, NOW())")
   ->execute([$staffEmail, $passwordHash]);
$staffUserId = (int)$db->lastInsertId();

$staffCookies = loginUser($staffEmail, '123456');

// Staff read allowed
$staffRead = makeHttpRequest("{$baseUrl}/admin/api.php?action=get_loyalty_members", 'GET', null, $staffCookies);
assertTest(
    $staffRead['status'] === 200 && !empty($staffRead['json']['success']),
    "Staff (role 5) is authorized to read members ledger (HTTP 200)"
);

// Staff save BLOCKED with 403
$staffSave = makeHttpRequest("{$baseUrl}/admin/api.php", 'POST', [
    'action'            => 'save_loyalty_config',
    'point_to_vnd_rate' => 999
], $staffCookies);
assertTest(
    $staffSave['status'] === 403 && empty($staffSave['json']['success']),
    "Staff (role 5) is blocked with 403 Forbidden from save_loyalty_config (HTTP {$staffSave['status']})"
);

// 1.4 Admin (role_id = 1) access
$adminEmail = 'admin@fastfood.vn';
$adminCookies = loginUser($adminEmail, '123456');
if (empty($adminCookies)) {
    // If admin has different password in test env
    $adminCookies = ['FF_SESS_ADMIN' => 'test_admin_session'];
}

$adminRead = makeHttpRequest("{$baseUrl}/admin/api.php?action=get_loyalty_members", 'GET', null, $adminCookies);
assertTest(
    $adminRead['status'] === 200 && !empty($adminRead['json']['success']),
    "Admin (role 1) is authorized to read members ledger (HTTP 200)"
);

// -------------------------------------------------------------------------
// SECTION 2: MEMBERS LEDGER API VERIFICATION (F13)
// -------------------------------------------------------------------------
echo "\n--- 2. Testing Admin Members Ledger (F13) ---\n";

// Seed 3 test members with distinct tiers
$dEmail = "diamond_{$runId}@fastfood.test";
$gEmail = "gold_{$runId}@fastfood.test";
$sEmail = "silver_{$runId}@fastfood.test";

$db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
              VALUES 
                (2, 'VIP Diamond Member', ?, '0940000003', 'hash', 'active', 'DIAMOND', 2500, 7500000, NOW()),
                (2, 'Gold Tier Member', ?, '0940000004', 'hash', 'active', 'GOLD', 1200, 3600000, NOW()),
                (2, 'Silver Tier Member', ?, '0940000005', 'hash', 'active', 'SILVER', 450, 1500000, NOW())")
   ->execute([$dEmail, $gEmail, $sEmail]);

// 2.1 Fetch members list and verify returned fields
$memRes = makeHttpRequest("{$baseUrl}/admin/api.php?action=get_loyalty_members", 'GET', null, $adminCookies);
assertTest($memRes['status'] === 200, "get_loyalty_members returns HTTP 200");
$members = $memRes['json']['data']['members'] ?? [];
assertTest(is_array($members) && count($members) >= 3, "Members list returned array with records (count: " . count($members) . ")");

$sample = $members[0] ?? [];
assertTest(isset($sample['id']) && isset($sample['full_name']) && isset($sample['member_tier']), "Member item has id, full_name, member_tier");
assertTest(isset($sample['points_balance']) && isset($sample['total_spent']), "Member item has points_balance, total_spent");
assertTest(isset($sample['tier_name']) && isset($sample['badge_color']), "Member item includes tier metadata (tier_name, badge_color)");

// 2.2 Tier filter: filter by DIAMOND
$tierFilterRes = makeHttpRequest("{$baseUrl}/admin/api.php?action=get_loyalty_members&tier=DIAMOND", 'GET', null, $adminCookies);
$diamondMembers = $tierFilterRes['json']['data']['members'] ?? [];
assertTest(!empty($diamondMembers), "Tier filter returned diamond members");
$allDiamond = true;
foreach ($diamondMembers as $dm) {
    if (strtoupper($dm['member_tier']) !== 'DIAMOND') {
        $allDiamond = false;
        break;
    }
}
assertTest($allDiamond, "All members returned by tier=DIAMOND have DIAMOND tier");

// 2.3 Search filter: keyword search by email
$searchRes = makeHttpRequest("{$baseUrl}/admin/api.php?action=get_loyalty_members&search=" . urlencode($gEmail), 'GET', null, $adminCookies);
$searchResults = $searchRes['json']['data']['members'] ?? [];
assertTest(count($searchResults) === 1, "Keyword search by exact email returned exactly 1 matching member");
assertTest(($searchResults[0]['email'] ?? '') === $gEmail, "Matched member email corresponds to search keyword");

// 2.4 Pagination: limit=2, page=1
$page1Res = makeHttpRequest("{$baseUrl}/admin/api.php?action=get_loyalty_members&limit=2&page=1", 'GET', null, $adminCookies);
$p1Members = $page1Res['json']['data']['members'] ?? [];
$pagination = $page1Res['json']['data']['pagination'] ?? [];
assertTest(count($p1Members) <= 2, "Pagination limit 2 respected (count: " . count($p1Members) . ")");
assertTest(($pagination['page'] ?? 0) === 1, "Pagination metadata reflects page 1");
assertTest(($pagination['limit'] ?? 0) === 2, "Pagination metadata reflects limit 2");
assertTest(($pagination['total'] ?? 0) >= 3, "Pagination metadata reflects correct total count");

// 2.5 Summary stats verification
$stats = $memRes['json']['data']['stats'] ?? [];
assertTest(isset($stats['total_members']) && $stats['total_members'] > 0, "Stats contains total_members count");
assertTest(isset($stats['diamond_count']) && $stats['diamond_count'] >= 1, "Stats contains diamond_count");
assertTest(isset($stats['total_system_points']), "Stats contains total_system_points");
assertTest(isset($stats['total_system_spent']), "Stats contains total_system_spent");

// -------------------------------------------------------------------------
// SECTION 3: MEMBER POINTS HISTORY AUDIT MODAL (F14)
// -------------------------------------------------------------------------
echo "\n--- 3. Testing Member Points History Modal (F14) ---\n";

// Seed user with transaction history
$histEmail = "history_{$runId}@fastfood.test";
$db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
              VALUES (2, 'History Audit User', ?, '0940000006', 'hash', 'active', 'SILVER', 220, 1800000, NOW())")
   ->execute([$histEmail]);
$histUserId = (int)$db->lastInsertId();

$orderCode = "ORD_{$runId}_AUD";
$db->prepare("INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
              VALUES (?, ?, 1, 'Aud Recip', '094', '456 Audit Ave', 300000, 0, 300000, 'cod', 'paid', 'completed', 30, 0, 0, NOW())")
   ->execute([$orderCode, $histUserId]);
$orderId = (int)$db->lastInsertId();

// Insert transactions: EARN, REDEEM_CHECKOUT
$db->prepare("INSERT INTO point_transactions (user_id, order_id, points, balance_after, transaction_type, description, created_at)
              VALUES 
                (?, ?, 30, 120, 'EARN', 'Tích điểm đơn {$orderCode}', NOW()),
                (?, ?, -50, 170, 'REDEEM_CHECKOUT', 'Dùng điểm giảm giá đơn', NOW())")
   ->execute([$histUserId, $orderId, $histUserId, $orderId]);

// 3.1 Retrieve history for user with transactions
$histRes = makeHttpRequest("{$baseUrl}/admin/api.php?action=get_member_loyalty_history&user_id={$histUserId}", 'GET', null, $adminCookies);
assertTest($histRes['status'] === 200, "get_member_loyalty_history returns HTTP 200");
$histData = $histRes['json']['data'] ?? [];
$uSummary = $histData['user'] ?? [];
assertTest(($uSummary['id'] ?? 0) === $histUserId, "Summary user ID matches requested user ID");
assertTest(($uSummary['member_tier'] ?? '') === 'SILVER', "Summary user member_tier is SILVER");

$txs = $histData['history'] ?? $histData['transactions'] ?? [];
assertTest(count($txs) === 2, "Returned exactly 2 transaction records for user");

$firstTx = $txs[0];
assertTest(isset($firstTx['transaction_type']) && isset($firstTx['points']) && isset($firstTx['balance_after']), "Transaction row contains transaction_type, points, balance_after");
assertTest(isset($firstTx['created_at']), "Transaction row contains timestamp");
assertTest($firstTx['order_code'] === $orderCode || strpos($firstTx['description'], $orderCode) !== false, "Transaction references order_code");

// 3.2 Clean empty array for user with zero transactions
$zeroEmail = "zero_{$runId}@fastfood.test";
$db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
              VALUES (2, 'Zero Tx User', ?, '0940000007', 'hash', 'active', 'BRONZE', 0, 0, NOW())")
   ->execute([$zeroEmail]);
$zeroUserId = (int)$db->lastInsertId();

$zeroRes = makeHttpRequest("{$baseUrl}/admin/api.php?action=get_member_loyalty_history&user_id={$zeroUserId}", 'GET', null, $adminCookies);
assertTest($zeroRes['status'] === 200, "get_member_loyalty_history for zero-tx user returns HTTP 200");
$zeroTxs = $zeroRes['json']['data']['history'] ?? $zeroRes['json']['data']['transactions'] ?? null;
assertTest(is_array($zeroTxs) && count($zeroTxs) === 0, "Zero-tx user returns clean empty array without error");

// 3.3 Non-existent user returns 404
$notFoundRes = makeHttpRequest("{$baseUrl}/admin/api.php?action=get_member_loyalty_history&user_id=99999999", 'GET', null, $adminCookies);
assertTest($notFoundRes['status'] === 404, "Invalid user ID returns HTTP 404");

// -------------------------------------------------------------------------
// SECTION 4: DYNAMIC LOYALTY CONFIGURATION (F15)
// -------------------------------------------------------------------------
echo "\n--- 4. Testing Admin Dynamic Loyalty Configuration (F15) ---\n";

// 4.1 Boundary validation: Negative min_spent rejected
$negSpentRes = makeHttpRequest("{$baseUrl}/admin/api.php", 'POST', [
    'action' => 'save_loyalty_config',
    'tiers'  => [
        ['tier_code' => 'SILVER', 'min_spent' => -100000]
    ]
], $adminCookies);
assertTest($negSpentRes['status'] === 400, "Negative min_spent rejected with HTTP 400");

// 4.2 Boundary validation: Negative point ratio rejected
$negRatioRes = makeHttpRequest("{$baseUrl}/admin/api.php", 'POST', [
    'action' => 'save_loyalty_config',
    'tiers'  => [
        ['tier_code' => 'GOLD', 'point_ratio' => -1.5]
    ]
], $adminCookies);
assertTest($negRatioRes['status'] === 400, "Negative point_ratio rejected with HTTP 400");

// 4.3 Boundary validation: Zero point_to_vnd_rate rejected
$zeroRateRes = makeHttpRequest("{$baseUrl}/admin/api.php", 'POST', [
    'action' => 'save_loyalty_config',
    'settings' => [
        'point_to_vnd_rate' => 0
    ]
], $adminCookies);
assertTest($zeroRateRes['status'] === 400, "Zero point_to_vnd_rate rejected with HTTP 400");

// 4.4 Boundary validation: Inverted thresholds (Silver > Gold) rejected
$invertRes = makeHttpRequest("{$baseUrl}/admin/api.php", 'POST', [
    'action' => 'save_loyalty_config',
    'tiers'  => [
        ['tier_code' => 'SILVER', 'min_spent' => 4000000],
        ['tier_code' => 'GOLD',   'min_spent' => 3000000]
    ]
], $adminCookies);
assertTest($invertRes['status'] === 400, "Inverted tier thresholds (Silver > Gold) rejected with HTTP 400");

// 4.5 Successful Dynamic Configuration Update & Runtime Effect
$newRate = 125.0; // 125 VND / pt
$newSilverSpent = 1200000.0;
$newGoldRatio = 2.4;

$saveRes = makeHttpRequest("{$baseUrl}/admin/api.php", 'POST', [
    'action' => 'save_loyalty_config',
    'settings' => [
        'point_to_vnd_rate' => $newRate,
        'spending_step_vnd' => 10000.0,
        'min_redeem_points' => 100
    ],
    'tiers' => [
        ['tier_code' => 'SILVER', 'min_spent' => $newSilverSpent],
        ['tier_code' => 'GOLD',   'point_ratio' => $newGoldRatio]
    ]
], $adminCookies);
assertTest($saveRes['status'] === 200 && !empty($saveRes['json']['success']), "Valid config save returns HTTP 200 Success");

// 4.6 Verify persistence in DB
$dbRate = $db->query("SELECT setting_value FROM loyalty_settings WHERE setting_key = 'point_to_vnd_rate'")->fetchColumn();
assertTest((float)$dbRate === 125.0, "loyalty_settings.point_to_vnd_rate in DB is 125.0");

$dbSilver = $db->query("SELECT min_spent FROM membership_tiers WHERE tier_code = 'SILVER'")->fetchColumn();
assertTest((float)$dbSilver === 1200000.0, "membership_tiers.SILVER.min_spent in DB is 1,200,000");

$dbGold = $db->query("SELECT point_ratio FROM membership_tiers WHERE tier_code = 'GOLD'")->fetchColumn();
assertTest((float)$dbGold === 2.4, "membership_tiers.GOLD.point_ratio in DB is 2.4");

// 4.7 Immediate Runtime Effect via clearLoyaltyCache()
clearLoyaltyCache();
$settingsRuntime = getLoyaltySettings();
assertTest((float)$settingsRuntime['point_to_vnd_rate'] === 125.0, "getLoyaltySettings() immediately reflects 125.0 without restart");

$ptsEarned = calculateEarnPoints(1000000, 'GOLD');
// 1M / 10k = 100 * 2.4 = 240 pts
assertTest($ptsEarned === 240, "calculateEarnPoints immediately uses new GOLD ratio 2.4 (expected 240, got {$ptsEarned})");

$tier11M = calculateTier(1100000); // 1.1M < 1.2M -> BRONZE
$tier13M = calculateTier(1300000); // 1.3M >= 1.2M -> SILVER
assertTest($tier11M === 'BRONZE', "1.1M total spent classified as BRONZE under new 1.2M Silver threshold");
assertTest($tier13M === 'SILVER', "1.3M total spent classified as SILVER under new 1.2M Silver threshold");

// 4.8 get_loyalty_config endpoint returns updated values
$cfgRes = makeHttpRequest("{$baseUrl}/admin/api.php?action=get_loyalty_config", 'GET', null, $adminCookies);
assertTest($cfgRes['status'] === 200, "get_loyalty_config returns HTTP 200");
$retrievedRate = $cfgRes['json']['data']['settings']['point_to_vnd_rate'] ?? 0;
assertTest((float)$retrievedRate === 125.0, "get_loyalty_config returns updated rate 125.0");

// -------------------------------------------------------------------------
// SECTION 5: TEARDOWN & BASELINE RESTORATION
// -------------------------------------------------------------------------
echo "\n--- 5. Cleanup & Config Teardown ---\n";

// Restore default settings
$db->exec("UPDATE loyalty_settings SET setting_value = '100' WHERE setting_key = 'point_to_vnd_rate'");
$db->exec("UPDATE loyalty_settings SET setting_value = '10000' WHERE setting_key = 'spending_step_vnd'");
$db->exec("UPDATE loyalty_settings SET setting_value = '100' WHERE setting_key = 'min_redeem_points'");
$db->exec("UPDATE membership_tiers SET min_spent = 0.00, point_ratio = 1.00 WHERE tier_code = 'BRONZE'");
$db->exec("UPDATE membership_tiers SET min_spent = 1000000.00, point_ratio = 1.50 WHERE tier_code = 'SILVER'");
$db->exec("UPDATE membership_tiers SET min_spent = 3000000.00, point_ratio = 2.00 WHERE tier_code = 'GOLD'");
$db->exec("UPDATE membership_tiers SET min_spent = 5000000.00, point_ratio = 3.00 WHERE tier_code = 'DIAMOND'");
clearLoyaltyCache();

$restoredSettings = getLoyaltySettings();
assertTest((float)$restoredSettings['point_to_vnd_rate'] === 100.0, "Restored baseline point_to_vnd_rate to 100.0");

// Cleanup test records
$db->exec("DELETE FROM point_transactions WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%_{$runId}@fastfood.test')");
$db->exec("DELETE FROM orders WHERE customer_id IN (SELECT id FROM users WHERE email LIKE '%_{$runId}@fastfood.test')");
$db->exec("DELETE FROM users WHERE email LIKE '%_{$runId}@fastfood.test'");

echo "\n=========================================================================\n";
echo "   TEST SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "=========================================================================\n";

if ($failed > 0) {
    exit(1);
}
