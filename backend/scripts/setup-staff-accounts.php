<?php
/**
 * Setup Admin & 4 Staff Accounts (PHP)
 * Chạy lệnh: php backend/scripts/setup-staff-accounts.php
 */
echo "====================================================\n";
echo "      CÀI ĐẶT TÀI KHOẢN ADMIN & 4 THÀNH VIÊN (STAFF)\n";
echo "====================================================\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=fastfood_db;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $pdo->beginTransaction();

    // 1. Role staff
    $pdo->exec("INSERT INTO roles (id, name, description) VALUES (5, 'staff', 'Thành viên ban quản trị / Nhân viên vận hành') ON DUPLICATE KEY UPDATE name = 'staff', description = 'Thành viên ban quản trị / Nhân viên vận hành'");

    $passwordHash = password_hash('123456', PASSWORD_BCRYPT);

    // 2. Admin
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, status = 'active' WHERE email = 'admin@fastfood.vn'");
    $stmt->execute([$passwordHash]);

    // 3. 4 Staff
    $staffAccounts = [
        ['fullName' => 'Nguyễn Văn Thành (Thành viên 1)', 'email' => 'staff1@fastfood.vn', 'phone' => '0911000001'],
        ['fullName' => 'Trần Thị Mai (Thành viên 2)', 'email' => 'staff2@fastfood.vn', 'phone' => '0911000002'],
        ['fullName' => 'Lê Hoàng Long (Thành viên 3)', 'email' => 'staff3@fastfood.vn', 'phone' => '0911000003'],
        ['fullName' => 'Phạm Thu Hương (Thành viên 4)', 'email' => 'staff4@fastfood.vn', 'phone' => '0911000004'],
    ];

    $insStmt = $pdo->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, default_address, status)
                              VALUES (5, ?, ?, ?, ?, 'Buôn Ma Thuột, Đắk Lắk', 'active')
                              ON DUPLICATE KEY UPDATE role_id = 5, full_name = VALUES(full_name), phone = VALUES(phone), password_hash = VALUES(password_hash), status = 'active'");

    foreach ($staffAccounts as $acc) {
        $insStmt->execute([$acc['fullName'], $acc['email'], $acc['phone'], $passwordHash]);
        echo "[OK] Đã thiết lập tài khoản: {$acc['email']}\n";
    }

    $pdo->commit();
    echo "[SUCCESS] Hoàn tất thiết lập Admin & 4 Nhân viên (Mật khẩu: 123456)!\n";
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    echo "[ERROR] Thất bại: " . $e->getMessage() . "\n";
}

