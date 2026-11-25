<?php
require_once __DIR__ . '/../config/database.php';
$email = $argv[1] ?? 'customer@fastfood.vn';
$hash = password_hash('123456', PASSWORD_DEFAULT);
getDB()->prepare("UPDATE users SET password_hash = ? WHERE email = ?")->execute([$hash, $email]);
echo "RESTORED";
