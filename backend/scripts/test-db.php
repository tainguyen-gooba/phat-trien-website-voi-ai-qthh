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
    $ports = array_unique(array_filter([getenv('DB_PORT') ?: null, '3307', '3301', '3306']));
    $pdo = null;
    $connectedPort = null;
    $lastErr = null;

    foreach ($ports as $port) {
        try {
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $connectedPort = $port;
            break;
        } catch (PDOException $ex) {
            $lastErr = $ex;
        }
    }

    if (!$pdo) {
        throw $lastErr;
    }

    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "[INFO] Trạng thái: KẾT NỐI THÀNH CÔNG!\n";
    echo "[INFO] Máy chủ: $host (Cổng $connectedPort)\n";
    echo "[INFO] Cơ sở dữ liệu: $db\n";
    echo "[INFO] Phiên bản MySQL: $version\n";
    exit(0);
} catch (PDOException $e) {
    echo "[ERROR] Trạng thái: KẾT NỐI THẤT BẠI!\n";
    echo "[ERROR] Lỗi: " . $e->getMessage() . "\n";
    echo "[WARN] Gợi ý: Kiểm tra MySQL trong XAMPP Control Panel đã được bật (Start) chưa.\n";
    exit(1);
}

