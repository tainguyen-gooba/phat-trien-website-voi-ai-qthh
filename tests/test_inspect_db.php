<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$u = $db->query("SELECT id, email, password_hash FROM users WHERE id = 1")->fetch();
$candidates = ['123456', 'password', 'admin123', 'admin', 'fastfood'];
foreach ($candidates as $c) {
    if (password_verify($c, $u['password_hash'])) {
        echo "MATCH: $c\n";
    }
}
