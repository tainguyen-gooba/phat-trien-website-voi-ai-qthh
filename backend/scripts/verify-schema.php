<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
$db = getDB();
echo "--- users.cccd column ---\n";
print_r($db->query("SHOW COLUMNS FROM users LIKE 'cccd'")->fetch());

echo "\n--- customer_voucher_claims columns ---\n";
print_r($db->query("DESCRIBE customer_voucher_claims")->fetchAll(PDO::FETCH_ASSOC));
