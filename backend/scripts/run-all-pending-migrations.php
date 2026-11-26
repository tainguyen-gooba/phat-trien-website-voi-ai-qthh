<?php
require_once __DIR__ . '/../../config/config.php';
$db = getDB();

echo "========================================================\n";
echo "RUNNING ALL PENDING DATABASE MIGRATIONS\n";
echo "========================================================\n\n";

$migrationFiles = [
    'migrate_membership_loyalty.sql',
    'migrate_customer_vouchers.sql',
    'migrate_voucher_shop_system.sql',
    'migrate_welcome_voucher.sql',
    'migrate_password_resets.sql',
    'migrate_product_status_and_chat.sql',
    'migrate_unread_chat_badge.sql',
    'chat_tables.sql',
];

$mysqlExe = 'C:\\xampp\\mysql\\bin\\mysql.exe';
$dbDir = ROOT_PATH . '/database';

foreach ($migrationFiles as $file) {
    $filePath = $dbDir . '/' . $file;
    if (!file_exists($filePath)) {
        echo "[SKIP] File not found: $file\n";
        continue;
    }
    echo "[RUNNING] $file ...\n";
    $cmd = "\"$mysqlExe\" -u root fastfood_db < \"$filePath\" 2>&1";
    exec($cmd, $output, $returnCode);
    if ($returnCode === 0) {
        echo "   -> [OK] Successfully applied $file\n";
    } else {
        echo "   -> [WARN/ERROR] Code $returnCode: " . implode("\n   ", $output) . "\n";
    }
}

echo "\n--------------------------------------------------------\n";
echo "VERIFYING CRITICAL COLUMNS AND TABLES:\n";
echo "--------------------------------------------------------\n";

// Verify users columns
$userCols = $db->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
$neededUserCols = ['points_balance', 'member_tier', 'total_spent', 'cccd', 'date_of_birth'];
foreach ($neededUserCols as $col) {
    $exists = in_array($col, $userCols) ? "EXISTS" : "MISSING!";
    echo "users.$col: $exists\n";
}

// Verify orders columns
$orderCols = $db->query("DESCRIBE orders")->fetchAll(PDO::FETCH_COLUMN);
$neededOrderCols = ['points_earned', 'points_used', 'points_discount'];
foreach ($neededOrderCols as $col) {
    $exists = in_array($col, $orderCols) ? "EXISTS" : "MISSING!";
    echo "orders.$col: $exists\n";
}

// Verify tables
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$neededTables = [
    'point_transactions', 
    'membership_tiers', 
    'loyalty_settings', 
    'vouchers', 
    'user_vouchers', 
    'user_saved_vouchers',
    'customer_voucher_claims', 
    'password_resets', 
    'ai_chat_sessions', 
    'ai_chat_messages'
];
foreach ($neededTables as $table) {
    $exists = in_array($table, $tables) ? "EXISTS" : "MISSING!";
    echo "table `$table`: $exists\n";
}

echo "\n========================================================\n";
echo "MIGRATION COMPLETE\n";
echo "========================================================\n";
