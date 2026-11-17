<?php
/**
 * FASTFOOD Standalone Database Connection Tester (PHP)
 * Chạy lệnh: php backend/scripts/test-db.php
 */
echo "====================================================\n";
echo "      KIỂM TRA KẾT NỐI CƠ SỞ DỮ LIỆU MYSQL          \n";
echo "====================================================\n";

try {
    $host = '127.0.0.1';
    $db   = 'fastfood_db';
    $user = 'root';
    $pass = '';
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "[INFO] Trạng thái: KẾT NỐI THÀNH CÔNG!\n";
    echo "[INFO] Máy chủ: $host\n";
    echo "[INFO] Cơ sở dữ liệu: $db\n";
    echo "[INFO] Phiên bản MySQL: $version\n";
    exit(0);
} catch (PDOException $e) {
    echo "[ERROR] Trạng thái: KẾT NỐI THẤT BẠI!\n";
    echo "[ERROR] Lỗi: " . $e->getMessage() . "\n";
    echo "[WARN] Gợi ý: Kiểm tra MySQL trong XAMPP Control Panel đã được bật (Start) chưa.\n";
    exit(1);
}

