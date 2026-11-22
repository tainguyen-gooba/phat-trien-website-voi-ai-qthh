<?php
/**
 * FASTFOOD - Cấu hình kết nối MySQL Database (PDO)
 */
date_default_timezone_set('Asia/Ho_Chi_Minh');

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'fastfood_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') ?: ''));

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $ports = array_unique(array_filter([DB_PORT, '3306', '3301']));
        $lastException = null;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        foreach ($ports as $port) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                return $pdo;
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }
        die("Lỗi kết nối cơ sở dữ liệu: " . ($lastException ? $lastException->getMessage() : "Unknown error"));
    }
    return $pdo;
}

