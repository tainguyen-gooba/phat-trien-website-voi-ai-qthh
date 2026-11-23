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
            'points_cost'     => 100,
            'discount_amount' => 10000.0,
            'min_order_value' => 50000.0,
            'prefix'          => 'FF10K',
            'badge'           => '10K',
            'description'     => 'Giảm 10.000đ cho đơn hàng từ 50.000đ'
        ],
        2 => [
            'id'              => 2,
            'name'            => 'Voucher FASTFOOD 20.000đ',
            'points_cost'     => 200,
            'discount_amount' => 20000.0,
            'min_order_value' => 80000.0,
            'prefix'          => 'FF20K',
            'badge'           => '20K',
            'description'     => 'Giảm 20.000đ cho đơn hàng từ 80.000đ'
        ],
        3 => [
            'id'              => 3,
            'name'            => 'Voucher FASTFOOD 50.000đ',
            'points_cost'     => 500,
            'discount_amount' => 50000.0,
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

    return [
        'success'       => true,
        'user_id'       => $userId,
        'total_spent'   => $totalSpent,
        'tier'          => $newTier,
        'previous_tier' => $prevTier,
        'upgraded'      => ($newTier !== $prevTier)
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

        // Update user: balance, total_spent, auto-upgrade tier
        $newBalance = (int)$user['points_balance'] + $pointsEarned;
        $newTotalSpent = (float)$user['total_spent'] + $orderTotal;
        $newTier = calculateTier($newTotalSpent);

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

        if ($ownsTx) {
            $db->commit();
        }

        return [
            'success'        => true,
            'points_earned'  => $pointsEarned,
            'new_balance'    => $newBalance,
            'new_tier'       => $newTier,
            'previous_tier'  => $currentTier,
            'total_spent'    => $newTotalSpent
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

        // Generate collision-free coupon code
        $prefix = $pkg['prefix'] ?? 'REW';
        $couponCode = $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        while (true) {
            $chk = $db->prepare("SELECT id FROM coupons WHERE code = ? LIMIT 1");
            $chk->execute([$couponCode]);
            if (!$chk->fetch()) {
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
