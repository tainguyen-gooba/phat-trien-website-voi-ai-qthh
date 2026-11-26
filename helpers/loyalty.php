<?php
/**
 * FASTFOOD - Core Loyalty & Membership Service
 * helpers/loyalty.php
 * 
 * Provides atomic, transaction-safe business logic for:
 * - Dynamic membership tiers & system loyalty settings
 * - Tier calculations & automatic upgrades
 * - Points earning on order completion (idempotent)
 * - Points redemption at checkout & voucher exchange
 * - Order cancellation & refund rollback
 * - Point transaction history ledger
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
require_once ROOT_PATH . '/config/database.php';

// In-memory static cache stores
class LoyaltyCache {
    public static ?array $settings = null;
    public static ?array $tiers = null;
}

/**
 * Reset in-memory static caches (useful after updates or in unit testing)
 */
function clearLoyaltyCache(): void {
    LoyaltyCache::$settings = null;
    LoyaltyCache::$tiers = null;
}

/**
 * Standard Voucher Packages definition for points exchange
 */
function getVoucherPackages(): array {
    return [
        1 => [
            'id'              => 1,
            'name'            => 'Voucher FASTFOOD 10.000đ',
            'package_name'    => 'Voucher FASTFOOD 10.000đ',
            'points_cost'     => 100,
            'discount_amount' => 10000.0,
            'voucher_value'   => 10000.0,
            'min_order_value' => 50000.0,
            'prefix'          => 'FF10K',
            'badge'           => '10K',
            'description'     => 'Giảm 10.000đ cho đơn hàng từ 50.000đ'
        ],
        2 => [
            'id'              => 2,
            'name'            => 'Voucher FASTFOOD 20.000đ',
            'package_name'    => 'Voucher FASTFOOD 20.000đ',
            'points_cost'     => 200,
            'discount_amount' => 20000.0,
            'voucher_value'   => 20000.0,
            'min_order_value' => 80000.0,
            'prefix'          => 'FF20K',
            'badge'           => '20K',
            'description'     => 'Giảm 20.000đ cho đơn hàng từ 80.000đ'
        ],
        3 => [
            'id'              => 3,
            'name'            => 'Voucher FASTFOOD 50.000đ',
            'package_name'    => 'Voucher FASTFOOD 50.000đ',
            'points_cost'     => 500,
            'discount_amount' => 50000.0,
            'voucher_value'   => 50000.0,
            'min_order_value' => 150000.0,
            'prefix'          => 'FF50K',
            'badge'           => '50K',
            'description'     => 'Giảm 50.000đ cho đơn hàng từ 150.000đ'
        ]
    ];
}

/**
 * 1. Retrieve loyalty settings with fallback defaults
 */
function getLoyaltySettings(): array {
    if (LoyaltyCache::$settings !== null) {
        return LoyaltyCache::$settings;
    }

    $defaults = [
        'point_to_vnd_rate'     => 100.0,   // 1 pt = 100 VND (100 pts = 10,000 VND)
        'spending_step_vnd'     => 10000.0, // 10,000 VND spending per step
        'min_redeem_points'     => 100,     // Minimum 100 pts to redeem at checkout
        'max_redeem_percentage' => 100,     // Up to 100% of order total
    ];

    try {
        $db = getDB();
        $stmt = $db->query("SELECT setting_key, setting_value FROM loyalty_settings");
        $settings = $defaults;
        while ($row = $stmt->fetch()) {
            $k = $row['setting_key'];
            $v = $row['setting_value'];
            $settings[$k] = is_numeric($v) ? (float)$v : $v;
        }
        LoyaltyCache::$settings = $settings;
        return $settings;
    } catch (Exception $e) {
        return $defaults;
    }
}

/**
 * 2. Retrieve membership tiers ordered by min_spent ASC
 */
function getMembershipTiers(): array {
    if (LoyaltyCache::$tiers !== null) {
        return LoyaltyCache::$tiers;
    }

    $defaultTiers = [
        'BRONZE' => [
            'id'           => 1,
            'tier_code'    => 'BRONZE',
            'tier_name'    => 'Đồng',
            'min_spent'    => 0.0,
            'point_ratio'  => 1.0,
            'points_ratio' => 1.0,
            'badge_color'  => '#cd7f32',
            'badge_icon'   => '🥉',
            'benefits'     => [
                'Tích lũy 1 điểm cho mỗi 10.000đ chi tiêu',
                'Quà tặng chào mừng thành viên mới',
                'Tích lũy điểm đổi voucher ưu đãi'
            ]
        ],
        'SILVER' => [
            'id'           => 2,
            'tier_code'    => 'SILVER',
            'tier_name'    => 'Bạc',
            'min_spent'    => 1000000.0,
            'point_ratio'  => 1.5,
            'points_ratio' => 1.5,
            'badge_color'  => '#94a3b8',
            'badge_icon'   => '🥈',
            'benefits'     => [
                'Tích lũy 1.5 điểm cho mỗi 10.000đ (+50%)',
                'Ưu tiên xử lý đơn hàng',
                'Quà tặng sinh nhật thành viên'
            ]
        ],
        'GOLD' => [
            'id'           => 3,
            'tier_code'    => 'GOLD',
            'tier_name'    => 'Vàng',
            'min_spent'    => 3000000.0,
            'point_ratio'  => 2.0,
            'points_ratio' => 2.0,
            'badge_color'  => '#f59e0b',
            'badge_icon'   => '🥇',
            'benefits'     => [
                'Tích lũy 2 điểm cho mỗi 10.000đ (x2 điểm)',
                'Miễn phí vận chuyển cho đơn từ 150.000đ',
                'Quà tặng sinh nhật độc quyền'
            ]
        ],
        'DIAMOND' => [
            'id'           => 4,
            'tier_code'    => 'DIAMOND',
            'tier_name'    => 'Kim Cương',
            'min_spent'    => 5000000.0,
            'point_ratio'  => 3.0,
            'points_ratio' => 3.0,
            'badge_color'  => '#06b6d4',
            'badge_icon'   => '💎',
            'benefits'     => [
                'Tích lũy 3 điểm cho mỗi 10.000đ (x3 điểm)',
                'Miễn phí vận chuyển mọi đơn hàng',
                'Đường dây nóng hỗ trợ ưu tiên VIP 24/7'
            ]
        ]
    ];

    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM membership_tiers ORDER BY min_spent ASC");
        $tiers = [];
        while ($row = $stmt->fetch()) {
            $code = strtoupper($row['tier_code']);
            $benefits = [];
            if (!empty($row['benefits_json'])) {
                $decoded = json_decode($row['benefits_json'], true);
                if (is_array($decoded)) {
                    $benefits = $decoded;
                }
            }
            $ratio = (float)($row['point_ratio'] ?? $row['points_ratio'] ?? 1.0);
            $icon = $row['badge_icon'] ?? '';
            if (empty($icon) || strpos($icon, 'fa-') !== false) {
                $emojiMap = ['BRONZE' => '🥉', 'SILVER' => '🥈', 'GOLD' => '🥇', 'DIAMOND' => '💎'];
                $icon = $emojiMap[$code] ?? '🥉';
            }
            $tiers[$code] = [
                'id'           => (int)$row['id'],
                'tier_code'    => $code,
                'tier_name'    => $row['tier_name'],
                'min_spent'    => (float)$row['min_spent'],
                'point_ratio'  => $ratio,
                'points_ratio' => $ratio,
                'badge_color'  => $row['badge_color'] ?? '#cd7f32',
                'badge_icon'   => $icon,
                'benefits'     => $benefits
            ];
        }
        if (!empty($tiers)) {
            LoyaltyCache::$tiers = $tiers;
            return $tiers;
        }
    } catch (Exception $e) {}

    LoyaltyCache::$tiers = $defaultTiers;
    return $defaultTiers;
}

/**
 * Compare two membership tier codes and return the higher rank
 */
function getHigherMembershipTier(string $tierA, string $tierB): string {
    $rank = ['BRONZE' => 1, 'SILVER' => 2, 'GOLD' => 3, 'DIAMOND' => 4];
    $a = strtoupper(trim($tierA));
    $b = strtoupper(trim($tierB));
    $rankA = $rank[$a] ?? 1;
    $rankB = $rank[$b] ?? 1;
    if ($rankA >= $rankB) {
        return isset($rank[$a]) ? $a : 'BRONZE';
    }
    return isset($rank[$b]) ? $b : 'BRONZE';
}

/**
 * Tự động tặng riêng 1 mã voucher dùng 1 lần duy nhất khi khách hàng được thăng hạng thành viên
 * (Áp dụng cho cả thăng hạng tự động qua đơn hàng lẫn Admin nâng hạng thủ công).
 * Mỗi hạng (SILVER, GOLD, DIAMOND) chỉ tặng tối đa 1 lần cho mỗi tài khoản.
 */
function giftTierUpgradeVoucher(int $userId, string $oldTier, string $newTier, ?PDO $db = null): ?array {
    if ($userId <= 0) {
        return null;
    }

    $rank = ['BRONZE' => 1, 'SILVER' => 2, 'GOLD' => 3, 'DIAMOND' => 4];
    $oldCode = strtoupper(trim($oldTier ?: 'BRONZE'));
    $newCode = strtoupper(trim($newTier ?: 'BRONZE'));

    if (($rank[$newCode] ?? 1) <= ($rank[$oldCode] ?? 1)) {
        return null; // Không phải thăng hạng
    }

    $rewardMap = [
        'SILVER'  => ['label' => 'Bạc',       'discount' => 30000.0,  'min_order' => 80000.0],
        'GOLD'    => ['label' => 'Vàng',      'discount' => 50000.0,  'min_order' => 120000.0],
        'DIAMOND' => ['label' => 'Kim Cương', 'discount' => 100000.0, 'min_order' => 200000.0],
    ];
    if (!isset($rewardMap[$newCode])) {
        return null;
    }

    $db = $db ?: getDB();
    $codePrefix = "UP-{$newCode}-{$userId}-";

    // Chống cấp trùng nếu khách từng nhận quà thăng hạng cho mốc này rồi
    $chkC = $db->prepare("SELECT id FROM coupons WHERE user_id = ? AND code LIKE ? LIMIT 1");
    $chkC->execute([$userId, $codePrefix . '%']);
    if ($chkC->fetch()) {
        return null;
    }

    try {
        $chkUV = $db->prepare("SELECT uv.id FROM user_vouchers uv JOIN vouchers v ON uv.voucher_id = v.id WHERE uv.user_id = ? AND v.code LIKE ? LIMIT 1");
        $chkUV->execute([$userId, $codePrefix . '%']);
        if ($chkUV->fetch()) {
            return null;
        }
    } catch (Exception $e) {}

    $cfg = $rewardMap[$newCode];
    $label = $cfg['label'];
    $discount = (float)$cfg['discount'];
    $minOrder = (float)$cfg['min_order'];

    // Sinh mã độc nhất không trùng ở cả bảng coupons lẫn vouchers
    $code = '';
    while (true) {
        $candidate = $codePrefix . strtoupper(bin2hex(random_bytes(2)));
        $dupC = $db->prepare("SELECT id FROM coupons WHERE code = ? LIMIT 1");
        $dupC->execute([$candidate]);
        $dupV = $db->prepare("SELECT id FROM vouchers WHERE code = ? LIMIT 1");
        $dupV->execute([$candidate]);
        if (!$dupC->fetch() && !$dupV->fetch()) {
            $code = $candidate;
            break;
        }
    }

    $title = "Quà Thăng Hạng {$label} - Giảm " . number_format($discount, 0, ',', '.') . "đ";
    $desc  = "Chúc mừng bạn đã thăng hạng {$label}! Tặng riêng 1 mã voucher chỉ sử dụng 1 lần duy nhất cho tài khoản của bạn (đơn từ " . number_format($minOrder, 0, ',', '.') . "đ).";
    $startDate = date('Y-m-d H:i:s');
    $endDate   = date('Y-m-d 23:59:59', strtotime('+30 days'));

    // 1. Tạo bản ghi trong bảng vouchers (usage_limit = 1, user_limit = 1, không công khai)
    $insV = $db->prepare("INSERT INTO vouchers
        (code, title, description, voucher_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, user_limit, min_member_tier, is_birthday_voucher, is_public, start_date, end_date, is_active)
        VALUES (?, ?, ?, 'fixed', ?, ?, ?, 1, 0, 1, ?, 0, 0, ?, ?, 1)");
    $insV->execute([$code, $title, $desc, $discount, $minOrder, $discount, $newCode, $startDate, $endDate]);
    $voucherId = (int)$db->lastInsertId();

    // 2. Tạo bản ghi tương thích bảng coupons gắn user_id
    $insC = $db->prepare("INSERT INTO coupons
        (user_id, code, title, discount_type, voucher_type, discount_value, min_order_value, max_discount_amount, min_member_tier, usage_limit, usage_count, start_date, end_date, is_active, created_at)
        VALUES (?, ?, ?, 'fixed', 'fixed', ?, ?, ?, ?, 1, 0, ?, ?, 1, NOW())");
    $insC->execute([$userId, $code, $title, $discount, $minOrder, $discount, $newCode, $startDate, $endDate]);
    $couponId = (int)$db->lastInsertId();

    // 3. Lưu thẳng vào Ví Voucher của khách hàng (user_vouchers)
    $insUV = $db->prepare("INSERT INTO user_vouchers (user_id, voucher_id, source, is_used, assigned_at, expires_at)
                           VALUES (?, ?, 'loyalty', 0, NOW(), ?)");
    $insUV->execute([$userId, $voucherId, $endDate]);

    // 4. Thông báo (nếu bảng notifications tồn tại)
    try {
        $db->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'loyalty', NOW())")
           ->execute([
               $userId,
               "👑 Chúc mừng bạn thăng hạng {$label}!",
               "FASTFOOD tặng riêng bạn mã voucher {$code} giảm " . number_format($discount, 0, ',', '.') . "đ (sử dụng 1 lần duy nhất, HSD 30 ngày). Hãy vào Ví Voucher để sử dụng nhé!"
           ]);
    } catch (Exception $e) {}

    return [
        'voucher_id'      => $voucherId,
        'coupon_id'       => $couponId,
        'code'            => $code,
        'title'           => $title,
        'tier'            => $newCode,
        'discount_amount' => $discount,
        'min_order_value' => $minOrder,
        'usage_limit'     => 1,
        'user_limit'      => 1,
        'expires_at'      => $endDate,
    ];
}

/**
 * 3. Calculate tier code from total spent amount
 */
function calculateTier(float $totalSpent): string {
    $tiers = getMembershipTiers();
    // Sort descending by min_spent
    uasort($tiers, function ($a, $b) {
        return $b['min_spent'] <=> $a['min_spent'];
    });

    foreach ($tiers as $code => $tier) {
        if ($totalSpent >= (float)$tier['min_spent']) {
            return $code;
        }
    }
    return 'BRONZE';
}

/**
 * 4. Synchronize user's tier and total_spent from completed orders
 */
function syncUserTier(int $userId): array {
    $db = getDB();

    $spentStmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE customer_id = ? AND order_status = 'completed'");
    $spentStmt->execute([$userId]);
    $totalSpent = (float)$spentStmt->fetchColumn();

    $newTier = calculateTier($totalSpent);

    $uStmt = $db->prepare("SELECT member_tier, total_spent FROM users WHERE id = ?");
    $uStmt->execute([$userId]);
    $currentUser = $uStmt->fetch();

    $prevTier = $currentUser ? ($currentUser['member_tier'] ?? 'BRONZE') : 'BRONZE';

    $upStmt = $db->prepare("UPDATE users SET total_spent = ?, member_tier = ? WHERE id = ?");
    $upStmt->execute([$totalSpent, $newTier, $userId]);

    $tierUpgradeVoucher = giftTierUpgradeVoucher($userId, (string)$prevTier, (string)$newTier, $db);

    return [
        'success'              => true,
        'user_id'              => $userId,
        'total_spent'          => $totalSpent,
        'tier'                 => $newTier,
        'previous_tier'        => $prevTier,
        'upgraded'             => ($newTier !== $prevTier),
        'tier_upgrade_voucher' => $tierUpgradeVoucher
    ];
}

/**
 * 5. Calculate points earned for given order total and tier code
 */
function calculateEarnPoints(float $orderTotal, string $tierCode): int {
    if ($orderTotal <= 0) {
        return 0;
    }

    $tiers = getMembershipTiers();
    $tierKey = strtoupper($tierCode);
    $ratio = isset($tiers[$tierKey]) ? (float)$tiers[$tierKey]['point_ratio'] : 1.0;

    $settings = getLoyaltySettings();
    $spendingStep = !empty($settings['spending_step_vnd']) ? (float)$settings['spending_step_vnd'] : 10000.0;
    if ($spendingStep <= 0) {
        $spendingStep = 10000.0;
    }

    $steps = floor($orderTotal / $spendingStep);
    return (int)floor($steps * $ratio);
}

/**
 * 6. Process points earning on order completion with idempotency protection
 */
function processOrderEarnPoints(int $orderId): array {
    $db = getDB();
    $ownsTx = false;
    if (!$db->inTransaction()) {
        $db->beginTransaction();
        $ownsTx = true;
    }

    try {
        $ordStmt = $db->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
        $ordStmt->execute([$orderId]);
        $order = $ordStmt->fetch();

        if (!$order) {
            if ($ownsTx) $db->rollBack();
            return ['success' => false, 'error' => 'Order not found'];
        }

        if ($order['order_status'] !== 'completed') {
            if ($ownsTx) $db->commit();
            return ['success' => false, 'error' => 'Order is not completed yet'];
        }

        // Idempotency check 1: orders.points_earned > 0
        if (!empty($order['points_earned']) && (int)$order['points_earned'] > 0) {
            if ($ownsTx) $db->commit();
            return [
                'success'         => false,
                'already_awarded' => true,
                'points_earned'   => (int)$order['points_earned'],
                'message'         => 'Points already awarded for this order'
            ];
        }

        // Idempotency check 2: point_transactions ledger
        $chkTx = $db->prepare("SELECT id, points FROM point_transactions WHERE order_id = ? AND transaction_type = 'EARN' LIMIT 1 FOR UPDATE");
        $chkTx->execute([$orderId]);
        $existingTx = $chkTx->fetch();
        if ($existingTx) {
            if ($ownsTx) $db->commit();
            return [
                'success'         => false,
                'already_awarded' => true,
                'points_earned'   => (int)$existingTx['points'],
                'message'         => 'Points already awarded in transaction ledger'
            ];
        }

        $userId = (int)$order['customer_id'];
        $userStmt = $db->prepare("SELECT id, member_tier, points_balance, total_spent FROM users WHERE id = ? FOR UPDATE");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        if (!$user) {
            if ($ownsTx) $db->rollBack();
            return ['success' => false, 'error' => 'Customer account not found'];
        }

        $currentTier = $user['member_tier'] ?? 'BRONZE';
        $orderTotal = (float)$order['total_amount'];
        $pointsEarned = calculateEarnPoints($orderTotal, $currentTier);

        // Update user: balance, total_spent, upgrade tier (preserve manually promoted higher tier)
        $newBalance = (int)$user['points_balance'] + $pointsEarned;
        $newTotalSpent = (float)$user['total_spent'] + $orderTotal;
        $calculatedTier = calculateTier($newTotalSpent);
        $newTier = getHigherMembershipTier((string)$currentTier, $calculatedTier);

        $upUser = $db->prepare("UPDATE users SET points_balance = ?, total_spent = ?, member_tier = ? WHERE id = ?");
        $upUser->execute([$newBalance, $newTotalSpent, $newTier, $userId]);

        // Update orders.points_earned
        $upOrd = $db->prepare("UPDATE orders SET points_earned = ? WHERE id = ?");
        $upOrd->execute([$pointsEarned, $orderId]);

        // Record point_transactions
        $orderCode = $order['order_code'] ?? ('#' . $orderId);
        $desc = "Tích lũy điểm từ đơn hàng " . $orderCode;
        $insTx = $db->prepare("INSERT INTO point_transactions 
            (user_id, order_id, points, balance_after, transaction_type, description, created_at)
            VALUES (?, ?, ?, ?, 'EARN', ?, NOW())");
        $insTx->execute([$userId, $orderId, $pointsEarned, $newBalance, $desc]);

        // Tặng riêng 1 mã voucher dùng 1 lần nếu đơn hàng giúp khách thăng hạng
        $tierUpgradeVoucher = giftTierUpgradeVoucher($userId, (string)$currentTier, (string)$newTier, $db);

        if ($ownsTx) {
            $db->commit();
        }

        return [
            'success'              => true,
            'points_earned'        => $pointsEarned,
            'new_balance'          => $newBalance,
            'new_tier'             => $newTier,
            'previous_tier'        => $currentTier,
            'total_spent'          => $newTotalSpent,
            'tier_upgrade_voucher' => $tierUpgradeVoucher
        ];
    } catch (Exception $e) {
        if ($ownsTx) $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * 7. Redeem points directly at checkout for order discount
 */
function redeemPointsAtCheckout(int $userId, int $orderId, int $pointsToUse, string $orderCode): array {
    if ($pointsToUse <= 0) {
        return ['success' => true, 'points_used' => 0, 'discount_amount' => 0.0];
    }

    $settings = getLoyaltySettings();
    $rate = !empty($settings['point_to_vnd_rate']) ? (float)$settings['point_to_vnd_rate'] : 100.0;
    $minRedeem = !empty($settings['min_redeem_points']) ? (int)$settings['min_redeem_points'] : 100;

    if ($pointsToUse < $minRedeem) {
        return [
            'success' => false,
            'error'   => "Số điểm tối thiểu để đổi là {$minRedeem} điểm."
        ];
    }

    $db = getDB();
    $ownsTx = false;
    if (!$db->inTransaction()) {
        $db->beginTransaction();
        $ownsTx = true;
    }

    try {
        $userStmt = $db->prepare("SELECT id, points_balance FROM users WHERE id = ? FOR UPDATE");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        if (!$user) {
            if ($ownsTx) $db->rollBack();
            return ['success' => false, 'error' => 'Người dùng không tồn tại.'];
        }

        $currentBalance = (int)$user['points_balance'];
        if ($pointsToUse > $currentBalance) {
            if ($ownsTx) $db->rollBack();
            return [
                'success' => false,
                'error'   => "Số dư điểm không đủ. Bạn hiện có {$currentBalance} điểm."
            ];
        }

        // 1 pt = rate VND (100 pts = 10,000 VND when rate = 100)
        $discountAmount = $pointsToUse * $rate;
        $newBalance = $currentBalance - $pointsToUse;

        // Deduct user balance
        $upUser = $db->prepare("UPDATE users SET points_balance = points_balance - ? WHERE id = ? AND points_balance >= ?");
        $upUser->execute([$pointsToUse, $userId, $pointsToUse]);
        if ($upUser->rowCount() === 0) {
            if ($ownsTx) $db->rollBack();
            return ['success' => false, 'error' => 'Không thể trừ điểm do số dư đã thay đổi.'];
        }

        // Update order if orderId provided
        if ($orderId > 0) {
            $upOrd = $db->prepare("UPDATE orders SET points_used = ?, points_discount = ? WHERE id = ?");
            $upOrd->execute([$pointsToUse, $discountAmount, $orderId]);
        }

        // Record in ledger
        $desc = "Sử dụng {$pointsToUse} điểm giảm giá cho đơn hàng " . ($orderCode ?: ('#' . $orderId));
        $insTx = $db->prepare("INSERT INTO point_transactions 
            (user_id, order_id, points, balance_after, transaction_type, description, created_at)
            VALUES (?, ?, ?, ?, 'REDEEM_CHECKOUT', ?, NOW())");
        $insTx->execute([$userId, $orderId > 0 ? $orderId : null, -$pointsToUse, $newBalance, $desc]);

        if ($ownsTx) {
            $db->commit();
        }

        return [
            'success'         => true,
            'points_used'     => $pointsToUse,
            'discount_amount' => $discountAmount,
            'new_balance'     => $newBalance
        ];
    } catch (Exception $e) {
        if ($ownsTx) $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * 8. Exchange points for a unique discount voucher
 */
function exchangePointsForVoucher(int $userId, int $packageId): array {
    $packages = getVoucherPackages();
    if (!isset($packages[$packageId])) {
        return ['success' => false, 'error' => 'Gói voucher không hợp lệ.'];
    }

    $pkg = $packages[$packageId];
    $pointsRequired = (int)$pkg['points_cost'];

    $db = getDB();
    $ownsTx = false;
    if (!$db->inTransaction()) {
        $db->beginTransaction();
        $ownsTx = true;
    }

    try {
        $userStmt = $db->prepare("SELECT id, full_name, points_balance FROM users WHERE id = ? FOR UPDATE");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        if (!$user) {
            if ($ownsTx) $db->rollBack();
            return ['success' => false, 'error' => 'Người dùng không tồn tại.'];
        }

        $currentBalance = (int)$user['points_balance'];
        if ($currentBalance < $pointsRequired) {
            if ($ownsTx) $db->rollBack();
            return [
                'success' => false,
                'error'   => "Số dư điểm không đủ để đổi voucher này. Cần {$pointsRequired} điểm, bạn có {$currentBalance} điểm."
            ];
        }

        // Deduct points
        $upUser = $db->prepare("UPDATE users SET points_balance = points_balance - ? WHERE id = ? AND points_balance >= ?");
        $upUser->execute([$pointsRequired, $userId, $pointsRequired]);
        if ($upUser->rowCount() === 0) {
            if ($ownsTx) $db->rollBack();
            return ['success' => false, 'error' => 'Không thể trừ điểm do số dư đã thay đổi.'];
        }
        $newBalance = $currentBalance - $pointsRequired;

        // Generate collision-free coupon code across coupons & vouchers
        $prefix = $pkg['prefix'] ?? 'REW';
        $couponCode = $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        while (true) {
            $chk = $db->prepare("SELECT id FROM coupons WHERE code = ? LIMIT 1");
            $chk->execute([$couponCode]);
            $chkV = $db->prepare("SELECT id FROM vouchers WHERE code = ? LIMIT 1");
            $chkV->execute([$couponCode]);
            if (!$chk->fetch() && !$chkV->fetch()) {
                break;
            }
            $couponCode = $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        }

        // Insert coupon with user_id linkage
        $insCoupon = $db->prepare("INSERT INTO coupons 
            (user_id, code, title, discount_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, start_date, end_date, is_active, created_at)
            VALUES (?, ?, ?, 'fixed', ?, ?, ?, 1, 0, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1, NOW())");
        $insCoupon->execute([
            $userId,
            $couponCode,
            $pkg['name'],
            $pkg['discount_amount'],
            $pkg['min_order_value'],
            $pkg['discount_amount']
        ]);
        $couponId = (int)$db->lastInsertId();

        // Đồng bộ vào bảng vouchers & user_vouchers để hiển thị và cập nhật tức thì trong Ví Voucher Của Tôi
        $insVoucher = $db->prepare("INSERT INTO vouchers
            (code, title, description, voucher_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, user_limit, min_member_tier, is_birthday_voucher, is_public, start_date, end_date, is_active)
            VALUES (?, ?, ?, 'fixed', ?, ?, ?, 1, 0, 1, 'ALL', 0, 0, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1)");
        $insVoucher->execute([
            $couponCode,
            $pkg['name'],
            $pkg['description'] ?? ('Đổi điểm thưởng lấy ưu đãi giảm ' . number_format((float)$pkg['discount_amount'], 0, ',', '.') . 'đ'),
            $pkg['discount_amount'],
            $pkg['min_order_value'],
            $pkg['discount_amount']
        ]);
        $newVoucherId = (int)$db->lastInsertId();

        $insUserVoucher = $db->prepare("INSERT INTO user_vouchers
            (user_id, voucher_id, source, is_used, assigned_at, expires_at)
            VALUES (?, ?, 'loyalty', 0, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))");
        $insUserVoucher->execute([$userId, $newVoucherId]);

        // Record in ledger
        $desc = "Đổi {$pointsRequired} điểm lấy " . $pkg['name'] . " (Mã: {$couponCode})";
        $insTx = $db->prepare("INSERT INTO point_transactions 
            (user_id, order_id, points, balance_after, transaction_type, description, created_at)
            VALUES (?, NULL, ?, ?, 'REDEEM_VOUCHER', ?, NOW())");
        $insTx->execute([$userId, -$pointsRequired, $newBalance, $desc]);

        if ($ownsTx) {
            $db->commit();
        }

        return [
            'success'         => true,
            'coupon_id'       => $couponId,
            'coupon_code'     => $couponCode,
            'discount_amount' => $pkg['discount_amount'],
            'points_deducted' => $pointsRequired,
            'new_balance'     => $newBalance
        ];
    } catch (Exception $e) {
        if ($ownsTx) $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * 9. Handle points restoration and revocation on order cancellation or refund
 */
function handleOrderCancellation(int $orderId): array {
    $db = getDB();
    $ownsTx = false;
    if (!$db->inTransaction()) {
        $db->beginTransaction();
        $ownsTx = true;
    }

    try {
        $ordStmt = $db->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
        $ordStmt->execute([$orderId]);
        $order = $ordStmt->fetch();

        if (!$order) {
            if ($ownsTx) $db->rollBack();
            return ['success' => false, 'error' => 'Order not found'];
        }

        $userId = (int)$order['customer_id'];
        $userStmt = $db->prepare("SELECT id, points_balance, total_spent, member_tier FROM users WHERE id = ? FOR UPDATE");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        $pointsRestored = 0;
        $pointsRevoked = 0;
        $currentBalance = (int)($user['points_balance'] ?? 0);
        $currentSpent = (float)($user['total_spent'] ?? 0.0);
        $orderCode = $order['order_code'] ?? ('#' . $orderId);

        // 1. Rollback points used at checkout (if any)
        $pointsUsed = (int)($order['points_used'] ?? 0);
        if ($pointsUsed > 0) {
            $chkRestore = $db->prepare("SELECT id FROM point_transactions WHERE order_id = ? AND transaction_type = 'REFUND_RESTORE' LIMIT 1 FOR UPDATE");
            $chkRestore->execute([$orderId]);
            if (!$chkRestore->fetch()) {
                $currentBalance += $pointsUsed;
                $pointsRestored = $pointsUsed;

                $desc = "Hoàn lại {$pointsUsed} điểm đã sử dụng từ đơn hàng hủy " . $orderCode;
                $insTx = $db->prepare("INSERT INTO point_transactions 
                    (user_id, order_id, points, balance_after, transaction_type, description, created_at)
                    VALUES (?, ?, ?, ?, 'REFUND_RESTORE', ?, NOW())");
                $insTx->execute([$userId, $orderId, $pointsUsed, $currentBalance, $desc]);
            }
        }

        // 2. Revoke points earned and spending if previously completed
        $pointsEarned = (int)($order['points_earned'] ?? 0);
        if ($pointsEarned > 0) {
            $chkRevoke = $db->prepare("SELECT id FROM point_transactions WHERE order_id = ? AND transaction_type = 'REFUND_REVOKE' LIMIT 1 FOR UPDATE");
            $chkRevoke->execute([$orderId]);
            if (!$chkRevoke->fetch()) {
                $currentBalance = max(0, $currentBalance - $pointsEarned);
                $pointsRevoked = $pointsEarned;
                $currentSpent = max(0.0, $currentSpent - (float)$order['total_amount']);

                $desc = "Thu hồi {$pointsEarned} điểm tích lũy do hủy đơn hàng " . $orderCode;
                $insTx = $db->prepare("INSERT INTO point_transactions 
                    (user_id, order_id, points, balance_after, transaction_type, description, created_at)
                    VALUES (?, ?, ?, ?, 'REFUND_REVOKE', ?, NOW())");
                $insTx->execute([$userId, $orderId, -$pointsEarned, $currentBalance, $desc]);
            }
        }

        // Recalculate tier
        $newTier = calculateTier($currentSpent);

        // Update user
        $upUser = $db->prepare("UPDATE users SET points_balance = ?, total_spent = ?, member_tier = ? WHERE id = ?");
        $upUser->execute([$currentBalance, $currentSpent, $newTier, $userId]);

        if ($ownsTx) {
            $db->commit();
        }

        return [
            'success'         => true,
            'points_restored' => $pointsRestored,
            'points_revoked'  => $pointsRevoked,
            'new_balance'     => $currentBalance,
            'new_spent'       => $currentSpent,
            'new_tier'        => $newTier
        ];
    } catch (Exception $e) {
        if ($ownsTx) $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * 10. Get transaction history ledger for user
 */
function getUserPointHistory(int $userId, int $limit = 50): array {
    $db = getDB();
    $limit = max(1, min(200, (int)$limit));
    $stmt = $db->prepare("SELECT pt.*, o.order_code 
        FROM point_transactions pt 
        LEFT JOIN orders o ON pt.order_id = o.id 
        WHERE pt.user_id = ? 
        ORDER BY pt.created_at DESC, pt.id DESC 
        LIMIT " . (int)$limit);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * 11. Update system loyalty settings
 */
function updateLoyaltySettings(array $settings): bool {
    $allowedKeys = [
        'point_to_vnd_rate',
        'spending_step_vnd',
        'min_redeem_points',
        'max_redeem_percentage'
    ];

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO loyalty_settings (setting_key, setting_value, updated_at) 
        VALUES (?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");

    foreach ($settings as $key => $val) {
        if (in_array($key, $allowedKeys, true)) {
            $stmt->execute([$key, (string)$val]);
        }
    }

    clearLoyaltyCache();
    return true;
}

/**
 * 12. Admin manual update / tier promotion for a member
 */
function adminUpdateMemberLoyalty(int $userId, array $input): array {
    $allowedTiers = ['BRONZE', 'SILVER', 'GOLD', 'DIAMOND'];
    $newTier = strtoupper(trim((string)($input['member_tier'] ?? '')));
    if (!in_array($newTier, $allowedTiers, true)) {
        return ['success' => false, 'status' => 400, 'error' => 'Hạng thành viên không hợp lệ (chỉ hỗ trợ BRONZE, SILVER, GOLD, DIAMOND).'];
    }

    if (isset($input['points_balance']) && $input['points_balance'] !== '' && $input['points_balance'] !== null) {
        if (!is_numeric($input['points_balance']) || (int)$input['points_balance'] < 0) {
            return ['success' => false, 'status' => 400, 'error' => 'Điểm khả dụng phải là số nguyên không âm (>= 0).'];
        }
    }

    if (isset($input['total_spent']) && $input['total_spent'] !== '' && $input['total_spent'] !== null) {
        if (!is_numeric($input['total_spent']) || (float)$input['total_spent'] < 0) {
            return ['success' => false, 'status' => 400, 'error' => 'Tổng chi tiêu không được là số âm (>= 0).'];
        }
    }

    $tierMeta = getMembershipTiers();
    $syncMinSpent = !empty($input['sync_min_spent']);
    $reason = mb_substr(trim((string)($input['reason'] ?? '')), 0, 200);

    $db = getDB();
    $ownsTx = false;
    if (!$db->inTransaction()) {
        $db->beginTransaction();
        $ownsTx = true;
    }

    try {
        $uStmt = $db->prepare("SELECT id, full_name, email, phone, member_tier, points_balance, total_spent FROM users WHERE id = ? FOR UPDATE");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch();

        if (!$user) {
            if ($ownsTx) $db->rollBack();
            return ['success' => false, 'status' => 404, 'error' => 'Không tìm thấy thành viên này.'];
        }

        $oldTier = strtoupper($user['member_tier'] ?? 'BRONZE');
        $oldPoints = (int)($user['points_balance'] ?? 0);
        $oldSpent = (float)($user['total_spent'] ?? 0.0);

        $newPoints = (isset($input['points_balance']) && $input['points_balance'] !== '' && $input['points_balance'] !== null)
            ? (int)$input['points_balance']
            : $oldPoints;

        $newSpent = (isset($input['total_spent']) && $input['total_spent'] !== '' && $input['total_spent'] !== null)
            ? (float)$input['total_spent']
            : $oldSpent;

        if ($syncMinSpent) {
            $tierMin = (float)($tierMeta[$newTier]['min_spent'] ?? 0.0);
            if ($newSpent < $tierMin) {
                $newSpent = $tierMin;
            }
        }

        $upStmt = $db->prepare("UPDATE users SET member_tier = ?, points_balance = ?, total_spent = ? WHERE id = ?");
        $upStmt->execute([$newTier, $newPoints, $newSpent, $userId]);

        $pointsDelta = $newPoints - $oldPoints;
        $tierChanged = ($newTier !== $oldTier);
        $spentChanged = (abs($newSpent - $oldSpent) >= 0.01);

        if ($tierChanged || $pointsDelta !== 0 || $spentChanged) {
            $oldLabel = $tierMeta[$oldTier]['tier_name'] ?? $oldTier;
            $newLabel = $tierMeta[$newTier]['tier_name'] ?? $newTier;
            $parts = [];
            if ($tierChanged) {
                $parts[] = "Hạng: {$oldLabel} -> {$newLabel}";
            }
            if ($pointsDelta !== 0) {
                $sign = $pointsDelta > 0 ? '+' : '';
                $parts[] = "Điểm: {$oldPoints} -> {$newPoints} ({$sign}{$pointsDelta})";
            }
            if ($spentChanged) {
                $parts[] = 'Chi tiêu: ' . number_format($newSpent, 0, ',', '.') . 'đ';
            }
            $desc = 'Admin điều chỉnh (' . implode('; ', $parts) . ')';
            if ($reason !== '') {
                $desc .= ' - ' . $reason;
            }
            $desc = mb_substr($desc, 0, 255);

            $txStmt = $db->prepare("INSERT INTO point_transactions (user_id, order_id, points, balance_after, transaction_type, description, created_at)
                                    VALUES (?, NULL, ?, ?, 'ADMIN_ADJUST', ?, NOW())");
            $txStmt->execute([$userId, $pointsDelta, $newPoints, $desc]);
        }

        // Tặng riêng 1 voucher dùng 1 lần duy nhất nếu Admin thăng hạng thành viên lên cao hơn
        $tierUpgradeVoucher = $tierChanged ? giftTierUpgradeVoucher($userId, $oldTier, $newTier, $db) : null;

        if ($ownsTx) {
            $db->commit();
        }

        $tInfo = $tierMeta[$newTier] ?? [];
        $msg = $tierChanged
            ? "Đã cập nhật hạng thành viên {$user['full_name']} sang {$tInfo['badge_icon']} {$tInfo['tier_name']}!"
            : "Đã cập nhật thông tin thành viên {$user['full_name']} thành công!";
        if ($tierUpgradeVoucher) {
            $msg .= " Đã tự động tặng riêng 1 voucher thăng hạng {$tierUpgradeVoucher['code']} (chỉ dùng 1 lần).";
        }

        return [
            'success'              => true,
            'message'              => $msg,
            'tier_upgrade_voucher' => $tierUpgradeVoucher,
            'member'               => [
                'id'             => (int)$user['id'],
                'full_name'      => $user['full_name'],
                'email'          => $user['email'],
                'member_tier'    => $newTier,
                'tier_name'      => $tInfo['tier_name'] ?? $newTier,
                'badge_icon'     => $tInfo['badge_icon'] ?? '🥉',
                'points_balance' => $newPoints,
                'total_spent'    => $newSpent,
            ]
        ];
    } catch (Exception $e) {
        if ($ownsTx) $db->rollBack();
        return ['success' => false, 'status' => 500, 'error' => 'Lỗi cập nhật thành viên: ' . $e->getMessage()];
    }
}
