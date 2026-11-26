<?php
/**
 * Idempotent migration & seed: Phạm vi món ăn / danh mục áp dụng cho Voucher.
 * - Freeship & các mã giảm giá toàn shop: applicable_scope = 'all'
 * - Voucher gắn món cụ thể (BURGER30, BOGOPIZZA, COMBO15, GARANBOGO, GOLDCHICKEN, SNACK15K):
 *   applicable_scope = 'specific' kèm category_ids / keywords / label.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';

$db = getDB();

$columnsToAdd = [
    'applicable_scope'        => "ALTER TABLE vouchers ADD COLUMN applicable_scope VARCHAR(20) NOT NULL DEFAULT 'all' AFTER min_member_tier",
    'applicable_category_ids' => "ALTER TABLE vouchers ADD COLUMN applicable_category_ids VARCHAR(255) NULL DEFAULT NULL AFTER applicable_scope",
    'applicable_keywords'     => "ALTER TABLE vouchers ADD COLUMN applicable_keywords VARCHAR(255) NULL DEFAULT NULL AFTER applicable_category_ids",
    'applicable_label'        => "ALTER TABLE vouchers ADD COLUMN applicable_label VARCHAR(150) NULL DEFAULT NULL AFTER applicable_keywords",
];

foreach ($columnsToAdd as $col => $ddl) {
    $chk = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vouchers' AND COLUMN_NAME = ?");
    $chk->execute([$col]);
    if ((int)$chk->fetchColumn() === 0) {
        $db->exec($ddl);
        echo "[DDL] Added column vouchers.{$col}\n";
    } else {
        echo "[DDL] Column vouchers.{$col} already exists\n";
    }
}

$specificRules = [
    'BURGER30' => [
        'scope'        => 'specific',
        'category_ids' => null,
        'keywords'     => 'burger',
        'label'        => 'Các món Burger',
    ],
    'BOGOPIZZA' => [
        'scope'        => 'specific',
        'category_ids' => null,
        'keywords'     => 'pizza',
        'label'        => 'Các món Pizza',
    ],
    'COMBO15' => [
        'scope'        => 'specific',
        'category_ids' => null,
        'keywords'     => 'combo',
        'label'        => 'Các món Combo',
    ],
    'GARANBOGO' => [
        'scope'        => 'specific',
        'category_ids' => null,
        'keywords'     => 'gà rán,cánh gà,đùi gà rán,gà giòn,gà popcorn,gà miếng,da gà,combo gà,burger gà',
        'label'        => 'Các món Gà Rán',
    ],
    'GOLDCHICKEN' => [
        'scope'        => 'specific',
        'category_ids' => null,
        'keywords'     => 'gà rán,cánh gà,đùi gà rán,gà giòn,gà popcorn,gà miếng,da gà,combo gà,burger gà',
        'label'        => 'Gà Rán & Combo Gà',
    ],
    'SNACK15K' => [
        'scope'        => 'specific',
        'category_ids' => '32,34,36',
        'keywords'     => 'khoai tây,khoai lang,vòng hành tây,phô mai que,phô mai viên,gà rán,cánh gà,đùi gà rán,gà giòn,gà popcorn,gà viên,trà sữa,trà chanh,trà đào,kem tươi,ăn vặt,snack',
        'label'        => 'Đồ Ăn Vặt, Trà Sữa & Gà Rán',
    ],
];

$updStmt = $db->prepare("
    UPDATE vouchers
    SET applicable_scope = ?,
        applicable_category_ids = ?,
        applicable_keywords = ?,
        applicable_label = ?
    WHERE UPPER(code) = UPPER(?)
");

foreach ($specificRules as $code => $rule) {
    $updStmt->execute([
        $rule['scope'],
        $rule['category_ids'],
        $rule['keywords'],
        $rule['label'],
        $code,
    ]);
    echo "[SEED] {$code} -> {$rule['label']}\n";
}

// Đảm bảo freeship và các mã giảm giá chung ở chế độ toàn đơn ('all')
$db->exec("UPDATE vouchers SET applicable_scope = 'all', applicable_category_ids = NULL, applicable_keywords = NULL, applicable_label = NULL WHERE voucher_type = 'free_shipping'");
echo "[DONE] Voucher food scope migration completed.\n";
