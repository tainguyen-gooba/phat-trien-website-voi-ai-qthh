<?php
/**
 * Idempotent seed script: Thêm 10 voucher shop mới vào hệ thống (vouchers + coupons).
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/helpers/voucher.php';

$vouchersToAdd = [
    [
        'code'                => 'TRUALUNCH10',
        'title'               => 'Giảm 10% Bữa Trưa Năng Lượng (Tối đa 25.000đ)',
        'description'         => 'Ưu đãi đặt món buổi trưa áp dụng cho mọi thành viên FASTFOOD',
        'voucher_type'        => 'percentage',
        'discount_value'      => 10,
        'min_order_value'     => 50000,
        'max_discount_amount' => 25000,
        'usage_limit'         => 1000,
        'user_limit'          => 1,
        'min_member_tier'     => 'ALL',
        'days'                => 90,
    ],
    [
        'code'                => 'SNACK15K',
        'title'               => 'Giảm 15.000đ cho đơn ăn vặt từ 45.000đ',
        'description'         => 'Thỏa thích trà sữa, khoai tây chiên, gà rán với ưu đãi giảm ngay 15K',
        'voucher_type'        => 'fixed',
        'discount_value'      => 15000,
        'min_order_value'     => 45000,
        'max_discount_amount' => 15000,
        'usage_limit'         => 1200,
        'user_limit'          => 1,
        'min_member_tier'         => 'ALL',
        'applicable_scope'        => 'specific',
        'applicable_category_ids' => '32,34,36',
        'applicable_keywords'     => 'khoai tây,khoai lang,vòng hành tây,phô mai que,phô mai viên,gà rán,cánh gà,đùi gà rán,gà giòn,gà popcorn,gà viên,trà sữa,trà chanh,trà đào,kem tươi,ăn vặt,snack',
        'applicable_label'        => 'Đồ Ăn Vặt, Trà Sữa & Gà Rán',
        'days'                    => 90,
    ],
    [
        'code'                => 'FREESHIP25',
        'title'               => 'Freeship Siêu Tốc (Tối đa 25.000đ)',
        'description'         => 'Miễn phí giao hàng cho đơn hàng từ 70.000đ trên toàn khu vực',
        'voucher_type'        => 'free_shipping',
        'discount_value'      => 25000,
        'min_order_value'     => 70000,
        'max_discount_amount' => 25000,
        'usage_limit'         => 1500,
        'user_limit'          => 1,
        'min_member_tier'     => 'ALL',
        'days'                => 90,
    ],
    [
        'code'                => 'GARANBOGO',
        'title'               => 'Mua 1 Tặng 1 - Gà Rán Giòn Cay Kèm Nước',
        'description'         => 'Tặng ngay phần ăn phụ hoặc nước mát trị giá 30.000đ cho đơn từ 120.000đ',
        'voucher_type'        => 'bogo',
        'discount_value'      => 30000,
        'min_order_value'     => 120000,
        'max_discount_amount' => 30000,
        'usage_limit'         => 400,
        'user_limit'          => 1,
        'min_member_tier'         => 'ALL',
        'applicable_scope'        => 'specific',
        'applicable_keywords'     => 'gà rán,cánh gà,đùi gà rán,gà giòn,gà popcorn,gà miếng,da gà,combo gà,burger gà',
        'applicable_label'        => 'Các món Gà Rán',
        'days'                    => 60,
    ],
    [
        'code'                => 'BRONZE12',
        'title'               => 'Chào Hạng Đồng - Giảm 12% tối đa 30.000đ',
        'description'         => 'Ưu đãi dành cho thành viên từ Hạng Đồng (Bronze) trở lên',
        'voucher_type'        => 'percentage',
        'discount_value'      => 12,
        'min_order_value'     => 60000,
        'max_discount_amount' => 30000,
        'usage_limit'         => 800,
        'user_limit'          => 1,
        'min_member_tier'     => 'BRONZE',
        'days'                => 90,
    ],
    [
        'code'                => 'SILVER25K',
        'title'               => 'Đặc quyền Hạng Bạc - Giảm ngay 25.000đ',
        'description'         => 'Ưu đãi tiền mặt dành riêng cho thành viên từ Hạng Bạc (Silver) trở lên',
        'voucher_type'        => 'fixed',
        'discount_value'      => 25000,
        'min_order_value'     => 90000,
        'max_discount_amount' => 25000,
        'usage_limit'         => 600,
        'user_limit'          => 1,
        'min_member_tier'     => 'SILVER',
        'days'                => 90,
    ],
    [
        'code'                => 'SHIPBAC0D',
        'title'               => 'Freeship Đặc Quyền Bạc (Tối đa 20.000đ)',
        'description'         => 'Hỗ trợ phí giao hàng 0đ cho đơn hàng từ 50.000đ từ Hạng Bạc+',
        'voucher_type'        => 'free_shipping',
        'discount_value'      => 20000,
        'min_order_value'     => 50000,
        'max_discount_amount' => 20000,
        'usage_limit'         => 700,
        'user_limit'          => 1,
        'min_member_tier'     => 'SILVER',
        'days'                => 90,
    ],
    [
        'code'                => 'GOLDCHICKEN',
        'title'               => 'Đặc quyền Vàng - Mua 1 Tặng 1 Combo Gà',
        'description'         => 'Ưu đãi Mua 1 Tặng 1 trị giá 45.000đ cho thành viên Hạng Vàng trở lên',
        'voucher_type'        => 'bogo',
        'discount_value'      => 45000,
        'min_order_value'     => 140000,
        'max_discount_amount' => 45000,
        'usage_limit'         => 350,
        'user_limit'          => 1,
        'min_member_tier'         => 'GOLD',
        'applicable_scope'        => 'specific',
        'applicable_keywords'     => 'gà rán,cánh gà,đùi gà rán,gà giòn,gà popcorn,gà miếng,da gà,combo gà,burger gà',
        'applicable_label'        => 'Gà Rán & Combo Gà',
        'days'                    => 75,
    ],
    [
        'code'                => 'TIECTOI25',
        'title'               => 'Đại Tiệc Cuối Tuần - Giảm 25% tối đa 70.000đ',
        'description'         => 'Giảm sâu cho hóa đơn nhóm & gia đình từ 180.000đ (Hạng Vàng+)',
        'voucher_type'        => 'percentage',
        'discount_value'      => 25,
        'min_order_value'     => 180000,
        'max_discount_amount' => 70000,
        'usage_limit'         => 400,
        'user_limit'          => 1,
        'min_member_tier'     => 'GOLD',
        'days'                => 90,
    ],
    [
        'code'                => 'DIAMOND30P',
        'title'               => 'VIP Kim Cương - Giảm 30% tối đa 100.000đ',
        'description'         => 'Siêu đặc quyền giảm giá lớn nhất dành riêng cho thành viên Hạng Kim Cương',
        'voucher_type'        => 'percentage',
        'discount_value'      => 30,
        'min_order_value'     => 200000,
        'max_discount_amount' => 100000,
        'usage_limit'         => 200,
        'user_limit'          => 1,
        'min_member_tier'     => 'DIAMOND',
        'days'                => 90,
    ],
];

$db = getDB();
$now = date('Y-m-d H:i:s');
$created = 0;
$updated = 0;

foreach ($vouchersToAdd as $item) {
    $days = (int)$item['days'];
    $payload = [
        'code'                => $item['code'],
        'title'               => $item['title'],
        'description'         => $item['description'],
        'voucher_type'        => $item['voucher_type'],
        'discount_value'      => $item['discount_value'],
        'min_order_value'     => $item['min_order_value'],
        'max_discount_amount' => $item['max_discount_amount'],
        'usage_limit'         => $item['usage_limit'],
        'user_limit'          => $item['user_limit'],
        'min_member_tier'         => $item['min_member_tier'],
        'applicable_scope'        => $item['applicable_scope'] ?? 'all',
        'applicable_category_ids' => $item['applicable_category_ids'] ?? null,
        'applicable_keywords'     => $item['applicable_keywords'] ?? null,
        'applicable_label'        => $item['applicable_label'] ?? null,
        'is_public'               => 1,
        'is_active'           => 1,
        'start_date'          => $now,
        'end_date'            => date('Y-m-d H:i:s', strtotime("+{$days} days")),
    ];

    $chk = $db->prepare("SELECT id FROM vouchers WHERE UPPER(code) = UPPER(?) LIMIT 1");
    $chk->execute([$item['code']]);
    $existingId = $chk->fetchColumn();

    $res = saveAdminVoucher($payload, $existingId ? (int)$existingId : null);
    if (empty($res['success'])) {
        fwrite(STDERR, "FAILED {$item['code']}: " . ($res['error'] ?? 'unknown') . "\n");
        exit(1);
    }

    if ($existingId) {
        $updated++;
    } else {
        $created++;
    }
    echo "[OK] {$item['code']} (id={$res['voucher_id']}, type={$item['voucher_type']}, tier={$item['min_member_tier']})\n";
}

echo "SUMMARY: created={$created}, updated={$updated}, total_public=" .
    (int)$db->query("SELECT COUNT(*) FROM vouchers WHERE is_public = 1 AND is_active = 1")->fetchColumn() . "\n";
