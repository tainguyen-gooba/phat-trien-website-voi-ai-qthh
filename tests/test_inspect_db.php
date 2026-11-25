<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$rows = $db->query("SELECT id, email, full_name, role_id, status FROM users LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";



