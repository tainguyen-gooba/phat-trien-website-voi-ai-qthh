<?php
require_once __DIR__ . '/../../config/config.php';
$db = getDB();

// Test the query from line 26 of checkout.php
$userId = 1;
$uStmt = $db->prepare("SELECT points_balance, member_tier, total_spent FROM users WHERE id = ? LIMIT 1");
$uStmt->execute([$userId]);
$userLoyalty = $uStmt->fetch(PDO::FETCH_ASSOC);

echo "Query succeeded! Result for user $userId:\n";
print_r($userLoyalty);

require_once ROOT_PATH . '/helpers/loyalty.php';
$settings = getLoyaltySettings();
echo "\nLoyalty settings:\n";
print_r($settings);

$tiers = getMembershipTiers();
echo "\nMembership tiers count: " . count($tiers) . "\n";
