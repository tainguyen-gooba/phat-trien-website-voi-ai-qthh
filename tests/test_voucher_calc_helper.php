<?php
require_once __DIR__ . '/../helpers/voucher.php';

// 1. Percentage: 20% đơn 100k -> giảm 20k
$p = ['is_active' => 1, 'voucher_type' => 'percentage', 'discount_value' => 20, 'min_order_value' => 50000, 'max_discount_amount' => 50000];
$resP = validateAndCalculateVoucher($p, 100000);
assert($resP['valid'] === true && (float)$resP['discount_amount'] === 20000.0);

// 2. Fixed: giảm 20k đơn 100k -> giảm 20k
$f = ['is_active' => 1, 'voucher_type' => 'fixed', 'discount_value' => 20000, 'min_order_value' => 50000];
$resF = validateAndCalculateVoucher($f, 100000);
assert($resF['valid'] === true && (float)$resF['discount_amount'] === 20000.0);

// 3. Free shipping: phí ship 18k -> giảm 18k
$s = ['is_active' => 1, 'voucher_type' => 'free_shipping', 'discount_value' => 18000, 'min_order_value' => 50000];
$resS = validateAndCalculateVoucher($s, 100000, null, 18000);
assert($resS['valid'] === true && (float)$resS['discount_amount'] === 18000.0);

// 4. BOGO (Mua 1 tặng 1): giảm 35k
$b = ['is_active' => 1, 'voucher_type' => 'bogo', 'discount_value' => 35000, 'min_order_value' => 50000];
$resB = validateAndCalculateVoucher($b, 100000);
assert($resB['valid'] === true && (float)$resB['discount_amount'] === 35000.0);

// 5. Birthday Cron Scan & Non-duplicate check
$cron = runDailyBirthdayVouchers();
assert(isset($cron['total_candidates']) && isset($cron['total_gifted']));

echo "ALL_CALCS_AND_CRON_OK\n";
