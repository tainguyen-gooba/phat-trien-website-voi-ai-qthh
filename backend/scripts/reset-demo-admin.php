<?php
/**
 * Reset Demo Admin (PHP)
 */
require_once dirname(__DIR__) . '/src/config/Database.php';

use FastFood\Config\Database;

try {
    $pdo = Database::getConnection();
    $hash = password_hash('123456', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, status = 'active' WHERE email = 'admin@fastfood.vn'");
    $stmt->execute([$hash]);
    echo "[OK] Reset admin password successfully to 123456.\n";
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}

