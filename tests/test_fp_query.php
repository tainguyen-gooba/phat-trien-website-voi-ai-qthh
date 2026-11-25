<?php
require_once __DIR__ . '/../config/database.php';
$email = $argv[1] ?? 'customer@fastfood.vn';
$stmt = getDB()->prepare("SELECT otp_code, token, is_used FROM password_resets WHERE email = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$email]);
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
