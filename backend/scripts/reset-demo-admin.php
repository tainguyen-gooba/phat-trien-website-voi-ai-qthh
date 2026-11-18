<?php
/**
 * Reset Demo Admin (PHP)
 */
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=fastfood_db;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $hash = password_hash('123456', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, status = 'active' WHERE email = 'admin@fastfood.vn'");
    $stmt->execute([$hash]);
    echo "[OK] Reset admin password successfully to 123456.\n";
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}

