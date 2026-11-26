<?php
/**
 * FASTFOOD - Customer & Admin Voucher System Helper
 * Quản lý Voucher Shop, Phân loại 4 loại voucher, Phân hạng thành viên,
 * Voucher sinh nhật tự động, Áp dụng và Thống kê.
 */
require_once dirname(__DIR__) . '/config/config.php';

/**
 * Lấy hạng thành viên của user
 */
function getUserMemberTier(int $userId): string {
    if ($userId <= 0) return 'BRONZE';
    $db = getDB();
    $stmt = $db->prepare("SELECT member_tier FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $tier = $stmt->fetchColumn();
    return strtoupper($tier ?: 'BRONZE');
}

/**
 * Tính phần trăm đã dùng & số lượt còn lại của voucher.
 * Đảm bảo khi có >= 1 lượt sử dụng (vd 1/300 = 0.3%), không bị làm tròn xuống 0%.
 */
function formatVoucherUsageMetrics(int $usageCount, int $usageLimit): array {
    $usageCount = max(0, $usageCount);
    $usageLimit = max(0, $usageLimit);
    $remaining  = $usageLimit > 0 ? max(0, $usageLimit - $usageCount) : null;

    if ($usageLimit <= 0 || $usageCount <= 0) {
        return [
            'usage_count'   => $usageCount,
            'usage_limit'   => $usageLimit,
            'remaining'     => $remaining,
            'usage_pct'     => '0',
            'bar_width_pct' => 0.0,
        ];
    }

    $rawPct = min(100.0, ($usageCount / $usageLimit) * 100.0);
    if ($rawPct >= 1.0) {
        $rounded = round($rawPct, 1);
        $pctText = rtrim(rtrim(number_format($rounded, 1, '.', ''), '0'), '.');
    } else {
        $roundedOne = round($rawPct, 1);
        if ($roundedOne >= 0.1) {
            $pctText = number_format($roundedOne, 1, '.', '');
        } else {
            $roundedTwo = max(0.01, round($rawPct, 2));
            $pctText = rtrim(rtrim(number_format($roundedTwo, 2, '.', ''), '0'), '.');
        }
    }

    return [
        'usage_count'   => $usageCount,
        'usage_limit'   => $usageLimit,
        'remaining'     => $remaining,
        'usage_pct'     => $pctText,
        'bar_width_pct' => min(100.0, max(2.0, $rawPct)),
    ];
}

/**
 * Ẩn mã voucher bằng ký tự 'x' khi tài khoản đã sử dụng / hết hạn để chống bôi đen sao chép.
 */
function maskVoucherCode(string $code): string {
    $len = max(8, strlen(trim($code)));
    return str_repeat('x', $len);
}

/**
 * Giới hạn lượt dùng / mỗi tài khoản:
 * Mọi voucher phân theo hạng thành viên (BRONZE, SILVER, GOLD, DIAMOND) chỉ được dùng tối đa 1 lần / tài khoản.
 */
function getEffectivePerUserLimit(array $voucher): int {
    $reqTier = strtoupper(trim((string)($voucher['min_member_tier'] ?? 'ALL')));
    if ($reqTier !== '' && $reqTier !== 'ALL') {
        return 1;
    }
    return max(1, (int)($voucher['user_limit'] ?? 1));
}

/**
 * Đếm số lần user đã sử dụng voucher (đồng bộ cả voucher_usages lẫn user_vouchers.is_used).
 */
function getUserVoucherUsedCount(int $voucherId, int $userId, ?PDO $db = null): int {
    if ($voucherId <= 0 || $userId <= 0) {
        return 0;
    }
    $db = $db ?: getDB();
    $vuCount = 0;
    $uvUsed = 0;
    try {
        $uStmt = $db->prepare("SELECT COUNT(*) FROM voucher_usages WHERE voucher_id = ? AND user_id = ?");
        $uStmt->execute([$voucherId, $userId]);
        $vuCount = (int)$uStmt->fetchColumn();
    } catch (Exception $e) {}
    try {
        $uvStmt = $db->prepare("SELECT COALESCE(MAX(is_used), 0) FROM user_vouchers WHERE voucher_id = ? AND user_id = ?");
        $uvStmt->execute([$voucherId, $userId]);
        $uvUsed = (int)$uvStmt->fetchColumn();
    } catch (Exception $e) {}

    return max($vuCount, $uvUsed > 0 ? 1 : 0);
}

/**
 * Chuẩn hóa danh sách category_id áp dụng cho voucher.
 */
function parseVoucherScopeCategoryIds($raw): array {
    if (is_array($raw)) {
        $parts = $raw;
    } else {
        $parts = explode(',', (string)($raw ?? ''));
    }
    $ids = [];
    foreach ($parts as $p) {
        $id = (int)trim((string)$p);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

/**
 * Chuẩn hóa danh sách từ khóa tên món áp dụng cho voucher (chữ thường UTF-8).
 */
function parseVoucherScopeKeywords($raw): array {
    if (is_array($raw)) {
        $parts = $raw;
    } else {
        $parts = explode(',', (string)($raw ?? ''));
    }
    $keywords = [];
    foreach ($parts as $p) {
        $kw = mb_strtolower(trim((string)$p), 'UTF-8');
        if ($kw !== '') {
            $keywords[$kw] = $kw;
        }
    }
    return array_values($keywords);
}

/**
 * Kiểm tra voucher có giới hạn theo món ăn / danh mục cụ thể hay không.
 * Freeship và các mã giảm giá toàn shop luôn trả về false.
 */
function isVoucherFoodRestricted(array $voucher): bool {
    $vType = strtolower(trim((string)($voucher['voucher_type'] ?? ($voucher['discount_type'] ?? 'percentage'))));
    if ($vType === 'free_shipping') {
        return false;
    }
    $scope = strtolower(trim((string)($voucher['applicable_scope'] ?? 'all')));
    if ($scope !== 'specific') {
        return false;
    }
    $catIds = parseVoucherScopeCategoryIds($voucher['applicable_category_ids'] ?? null);
    $keywords = parseVoucherScopeKeywords($voucher['applicable_keywords'] ?? null);
    return !empty($catIds) || !empty($keywords);
}

/**
 * Lấy nhãn hiển thị ngắn gọn của nhóm món mà voucher áp dụng.
 */
function normalizeVoucherScopeLabel(array $voucher, ?PDO $db = null): string {
    $explicit = trim((string)($voucher['applicable_label'] ?? ''));
    if ($explicit !== '') {
        return $explicit;
    }

    $catIds = parseVoucherScopeCategoryIds($voucher['applicable_category_ids'] ?? null);
    if (!empty($catIds)) {
        try {
            $db = $db ?: getDB();
            $placeholders = implode(',', array_fill(0, count($catIds), '?'));
            $stmt = $db->prepare("SELECT name FROM categories WHERE id IN ({$placeholders}) ORDER BY id");
            $stmt->execute($catIds);
            $catNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($catNames)) {
                return implode(', ', $catNames);
            }
        } catch (Exception $e) {}
    }

    $keywords = parseVoucherScopeKeywords($voucher['applicable_keywords'] ?? null);
    if (!empty($keywords)) {
        $pretty = array_map(static function (string $kw): string {
            return mb_convert_case($kw, MB_CASE_TITLE, 'UTF-8');
        }, array_slice($keywords, 0, 3));
        return implode(', ', $pretty);
    }

    return 'nhóm món chỉ định';
}

/**
 * Bổ sung thông tin phạm vi món áp dụng chuẩn hóa vào bản ghi voucher.
 */
function enrichVoucherScopeFields(array &$row, ?PDO $db = null): void {
    $restricted = isVoucherFoodRestricted($row);
    $row['applicable_scope'] = $restricted ? 'specific' : 'all';
    if ($restricted) {
        $label = normalizeVoucherScopeLabel($row, $db);
        $row['applicable_label'] = $label;
        $row['scope_badge'] = 'Chỉ áp: ' . $label;
    } else {
        $row['applicable_category_ids'] = null;
        $row['applicable_keywords'] = null;
        $row['applicable_label'] = '';
        $row['scope_badge'] = '';
    }
}

/**
 * Kiểm tra giỏ hàng có chứa món ăn khớp điều kiện của voucher hay không
 * và tính tổng tiền riêng của các món đủ điều kiện (eligible_subtotal).
 */
function evaluateVoucherCartScope(array $voucher, ?array $cartItems = null, ?PDO $db = null): array {
    if (!isVoucherFoodRestricted($voucher)) {
        return [
            'restricted'        => false,
            'matched'           => true,
            'eligible_subtotal' => null,
            'matched_count'     => 0,
            'label'             => 'Toàn thực đơn',
            'reason'            => '',
        ];
    }

    $label = normalizeVoucherScopeLabel($voucher, $db);

    // Cho phép API xem trước theo subtotal (get_eligible?subtotal=...) khi không truyền cartItems
    if ($cartItems === null) {
        return [
            'restricted'        => true,
            'matched'           => true,
            'eligible_subtotal' => null,
            'matched_count'     => 0,
            'label'             => $label,
            'reason'            => '',
        ];
    }

    if (empty($cartItems)) {
        return [
            'restricted'        => true,
            'matched'           => false,
            'eligible_subtotal' => 0.0,
            'matched_count'     => 0,
            'label'             => $label,
            'reason'            => "Voucher chỉ áp dụng cho {$label} — giỏ hàng của bạn chưa có món phù hợp.",
        ];
    }

    $productIds = [];
    foreach ($cartItems as $key => $item) {
        if (!is_array($item)) continue;
        $rawPid = (string)($item['product_id'] ?? $item['productId'] ?? $item['id'] ?? $key);
        $basePart = explode('_', $rawPid)[0];
        $pid = (int)preg_replace('/\D/', '', $basePart);
        if ($pid > 0) {
            $productIds[$pid] = $pid;
        }
    }

    $productMeta = [];
    if (!empty($productIds)) {
        try {
            $db = $db ?: getDB();
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $pStmt = $db->prepare("SELECT id, category_id, name FROM products WHERE id IN ({$placeholders})");
            $pStmt->execute(array_values($productIds));
            foreach ($pStmt->fetchAll() as $pRow) {
                $productMeta[(int)$pRow['id']] = [
                    'category_id' => (int)($pRow['category_id'] ?? 0),
                    'name'        => trim((string)($pRow['name'] ?? '')),
                ];
            }
        } catch (Exception $e) {}
    }

    $catIds = parseVoucherScopeCategoryIds($voucher['applicable_category_ids'] ?? null);
    $keywords = parseVoucherScopeKeywords($voucher['applicable_keywords'] ?? null);

    $eligibleSubtotal = 0.0;
    $matchedCount = 0;

    foreach ($cartItems as $key => $item) {
        if (!is_array($item)) continue;
        $rawPid = (string)($item['product_id'] ?? $item['productId'] ?? $item['id'] ?? $key);
        $basePart = explode('_', $rawPid)[0];
        $pid = (int)preg_replace('/\D/', '', $basePart);

        $catId = (int)($productMeta[$pid]['category_id'] ?? ($item['category_id'] ?? 0));
        $prodName = trim((string)($productMeta[$pid]['name'] ?? ($item['name'] ?? '')));

        $itemMatched = false;
        if ($catId > 0 && in_array($catId, $catIds, true)) {
            $itemMatched = true;
        }
        if (!$itemMatched && !empty($keywords) && $prodName !== '') {
            foreach ($keywords as $kw) {
                if (mb_stripos($prodName, $kw, 0, 'UTF-8') !== false) {
                    $itemMatched = true;
                    break;
                }
            }
        }

        if ($itemMatched) {
            $qty = max(1, (int)($item['qty'] ?? ($item['quantity'] ?? 1)));
            $price = max(0.0, (float)($item['price'] ?? 0));
            $eligibleSubtotal += ($price * $qty);
            $matchedCount += $qty;
        }
    }

    if ($matchedCount <= 0 || $eligibleSubtotal <= 0) {
        return [
            'restricted'        => true,
            'matched'           => false,
            'eligible_subtotal' => 0.0,
            'matched_count'     => 0,
            'label'             => $label,
            'reason'            => "Voucher chỉ áp dụng cho {$label} — giỏ hàng của bạn chưa có món phù hợp.",
        ];
    }

    return [
        'restricted'        => true,
        'matched'           => true,
        'eligible_subtotal' => $eligibleSubtotal,
        'matched_count'     => $matchedCount,
        'label'             => $label,
        'reason'            => '',
    ];
}

/**
 * 1. Lấy danh sách Voucher công khai trên Sàn Săn Voucher (Voucher Shop)
 * Hỗ trợ lọc theo loại voucher và kiểm tra điều kiện hạng thành viên của user
 */
function getAllPublicVouchers(?int $userId = null, ?string $typeFilter = null): array {
    $db = getDB();
    $now = date('Y-m-d H:i:s');

    $sql = "
        SELECT v.*,
               (SELECT COUNT(*) FROM voucher_usages vu WHERE vu.voucher_id = v.id) AS total_usages,
               " . ($userId ? "
               (SELECT COUNT(*) FROM user_vouchers uv WHERE uv.voucher_id = v.id AND uv.user_id = :uid) AS is_in_wallet,
               (SELECT COALESCE(MAX(uv2.is_used), 0) FROM user_vouchers uv2 WHERE uv2.voucher_id = v.id AND uv2.user_id = :uid3) AS uv_is_used,
               (SELECT COUNT(*) FROM voucher_usages vu2 WHERE vu2.voucher_id = v.id AND vu2.user_id = :uid2) AS user_used_count
               " : "0 AS is_in_wallet, 0 AS uv_is_used, 0 AS user_used_count") . "
        FROM vouchers v
        WHERE v.is_public = 1
          AND v.is_active = 1
          AND (v.start_date IS NULL OR v.start_date <= :now1)
          AND (v.end_date IS NULL OR v.end_date >= :now2)
    ";

    $params = [
        ':now1' => $now,
        ':now2' => $now,
    ];
    if ($userId) {
        $params[':uid'] = $userId;
        $params[':uid2'] = $userId;
        $params[':uid3'] = $userId;
    }

    if ($typeFilter && in_array($typeFilter, ['percentage', 'fixed', 'free_shipping', 'bogo'])) {
        $sql .= " AND v.voucher_type = :vtype";
        $params[':vtype'] = $typeFilter;
    }

    $sql .= " ORDER BY v.min_member_tier DESC, v.discount_value DESC, v.id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Hạng thành viên hiện tại của user
    $userTier = $userId ? getUserMemberTier($userId) : 'BRONZE';
    $tierRanks = ['ALL' => 0, 'BRONZE' => 1, 'SILVER' => 2, 'GOLD' => 3, 'DIAMOND' => 4];
    $userRank = $tierRanks[$userTier] ?? 1;

    foreach ($rows as &$r) {
        $perUserLimit = getEffectivePerUserLimit($r);
        $effectiveUsageCount = max((int)($r['usage_count'] ?? 0), (int)($r['total_usages'] ?? 0));
        $usageLimit = (int)($r['usage_limit'] ?? 0);
        $metrics = formatVoucherUsageMetrics($effectiveUsageCount, $usageLimit);

        $r['is_saved'] = (int)($r['is_in_wallet'] ?? 0) > 0;
        $r['user_used_count'] = max((int)($r['user_used_count'] ?? 0), (int)($r['uv_is_used'] ?? 0) > 0 ? 1 : 0);
        $r['effective_user_limit'] = $perUserLimit;
        $r['effective_usage_count'] = $effectiveUsageCount;
        $r['usage_count'] = $effectiveUsageCount;
        $r['usage_pct'] = $metrics['usage_pct'];
        $r['usage_bar_width'] = $metrics['bar_width_pct'];
        $r['remaining_uses'] = $metrics['remaining'];
        $r['is_used'] = (int)($r['uv_is_used'] ?? 0) === 1 || $r['user_used_count'] >= $perUserLimit;
        $r['is_available'] = ($usageLimit === 0 || $effectiveUsageCount < $usageLimit);

        // Kiểm tra đủ điều kiện hạng thành viên để thu thập
        $reqTier = strtoupper($r['min_member_tier'] ?? 'ALL');
        $reqRank = $tierRanks[$reqTier] ?? 0;
        $r['tier_eligible'] = ($userRank >= $reqRank);

        // Badge loại voucher
        $r['type_badge'] = match($r['voucher_type']) {
            'percentage'    => 'Giảm %',
            'fixed'         => 'Giảm Tiền',
            'free_shipping' => 'Freeship',
            'bogo'          => 'Mua 1 Tặng 1',
            default         => 'Ưu Đãi'
        };

        // Text giảm giá
        $discVal = (float)$r['discount_value'];
        $r['discount_display'] = match($r['voucher_type']) {
            'percentage'    => 'GIẢM ' . rtrim(rtrim(number_format($discVal, 1), '0'), '.') . '%',
            'free_shipping' => 'FREESHIP',
            'bogo'          => 'TẶNG MÓN',
            default         => 'GIẢM ' . number_format($discVal, 0, ',', '.') . 'đ'
        };

        enrichVoucherScopeFields($r, $db);
    }
    unset($r);

    return $rows;
}

/**
 * 2. Lấy danh sách Voucher trong Ví của khách hàng (My Voucher Wallet)
 * Bao gồm:
 * - Voucher lưu từ sàn (claim)
 * - Voucher sinh nhật (birthday)
 * - Voucher đổi điểm (loyalty)
 * - Voucher do Admin cấp (admin)
 */
function getUserWalletVouchers(int $userId, ?string $typeFilter = null): array {
    if ($userId <= 0) return [];
    $db = getDB();
    $now = date('Y-m-d H:i:s');

    $sql = "
        SELECT v.*,
               uv.id AS user_voucher_id,
               uv.source,
               uv.is_used AS uv_is_used,
               uv.used_at AS uv_used_at,
               uv.assigned_at,
               COALESCE(uv.expires_at, v.end_date) AS effective_end_date,
               (SELECT COUNT(*) FROM voucher_usages vu WHERE vu.voucher_id = v.id AND vu.user_id = :uid1) AS user_used_count
        FROM user_vouchers uv
        JOIN vouchers v ON uv.voucher_id = v.id
        WHERE uv.user_id = :uid2
          AND v.is_active = 1
    ";

    $params = [':uid1' => $userId, ':uid2' => $userId];

    if ($typeFilter && in_array($typeFilter, ['percentage', 'fixed', 'free_shipping', 'bogo'])) {
        $sql .= " AND v.voucher_type = :vtype";
        $params[':vtype'] = $typeFilter;
    }

    $sql .= " ORDER BY uv.is_used ASC, effective_end_date ASC, v.discount_value DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Hạng thành viên hiện tại
    $userTier = getUserMemberTier($userId);
    $tierRanks = ['ALL' => 0, 'BRONZE' => 1, 'SILVER' => 2, 'GOLD' => 3, 'DIAMOND' => 4];
    $userRank = $tierRanks[$userTier] ?? 1;

    foreach ($rows as &$r) {
        $perUserLimit = getEffectivePerUserLimit($r);
        $r['user_used_count'] = max((int)($r['user_used_count'] ?? 0), (int)($r['uv_is_used'] ?? 0) > 0 ? 1 : 0);
        $r['effective_user_limit'] = $perUserLimit;
        $r['is_used'] = (int)($r['uv_is_used'] ?? 0) === 1 || $r['user_used_count'] >= $perUserLimit;
        $r['is_expired'] = (!empty($r['effective_end_date']) && $r['effective_end_date'] < $now);
        $r['is_exhausted'] = ($r['usage_limit'] > 0 && (int)$r['usage_count'] >= (int)$r['usage_limit']);

        $reqTier = strtoupper($r['min_member_tier'] ?? 'ALL');
        $reqRank = $tierRanks[$reqTier] ?? 0;
        $r['tier_eligible'] = ($userRank >= $reqRank);

        $r['is_usable'] = !$r['is_used'] && !$r['is_expired'] && !$r['is_exhausted'] && (int)$r['is_active'] === 1 && $r['tier_eligible'];

        $r['type_badge'] = match($r['voucher_type']) {
            'percentage'    => 'Giảm %',
            'fixed'         => 'Giảm Tiền',
            'free_shipping' => 'Freeship',
            'bogo'          => 'Mua 1 Tặng 1',
            default         => 'Ưu Đãi'
        };

        $discVal = (float)$r['discount_value'];
        $r['discount_display'] = match($r['voucher_type']) {
            'percentage'    => 'GIẢM ' . rtrim(rtrim(number_format($discVal, 1), '0'), '.') . '%',
            'free_shipping' => 'FREESHIP',
            'bogo'          => 'TẶNG MÓN',
            default         => 'GIẢM ' . number_format($discVal, 0, ',', '.') . 'đ'
        };

        enrichVoucherScopeFields($r, $db);
    }
    unset($r);

    return $rows;
}

/**
 * Đếm số mã công khai trên Sàn mà user hiện tại vẫn còn có thể săn / sử dụng
 * (loại bỏ các mã user đã dùng rồi hoặc toàn sàn đã hết lượt).
 */
function getPublicHuntAvailableCount(array $publicVouchers): int {
    $count = 0;
    foreach ($publicVouchers as $v) {
        if (empty($v['is_used']) && !empty($v['is_available'])) {
            $count++;
        }
    }
    return $count;
}

/**
 * Chuẩn hóa & ẩn mã các voucher đã dùng / hết hạn trước khi trả về JSON cho frontend.
 */
function sanitizeVouchersForClient(array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        $isUsed = !empty($r['is_used']);
        $isExpired = !empty($r['is_expired']);
        $rawCode = (string)($r['code'] ?? '');
        $masked = maskVoucherCode($rawCode);
        $endDateRaw = $r['effective_end_date'] ?? ($r['end_date'] ?? null);

        $item = $r;
        $item['id'] = (int)($r['id'] ?? 0);
        $item['is_tier_upgrade'] = (strpos($rawCode, 'UP-') === 0);
        $item['is_code_hidden'] = ($isUsed || $isExpired);
        $item['display_code'] = ($isUsed || $isExpired) ? $masked : $rawCode;
        $item['code'] = ($isUsed || $isExpired) ? $masked : $rawCode;
        $item['end_date_formatted'] = !empty($endDateRaw) ? date('d/m/Y', strtotime((string)$endDateRaw)) : 'Không thời hạn';
        $item['min_order_value'] = (float)($r['min_order_value'] ?? 0);
        $item['max_discount_amount'] = !empty($r['max_discount_amount']) ? (float)$r['max_discount_amount'] : 0.0;
        $item['usage_limit'] = (int)($r['usage_limit'] ?? 0);
        $item['effective_usage_count'] = (int)($r['effective_usage_count'] ?? $r['usage_count'] ?? 0);
        $item['effective_user_limit'] = (int)($r['effective_user_limit'] ?? getEffectivePerUserLimit($r));
        enrichVoucherScopeFields($item);
        if (!isset($item['usage_pct'])) {
            $metrics = formatVoucherUsageMetrics($item['effective_usage_count'], $item['usage_limit']);
            $item['usage_pct'] = $metrics['usage_pct'];
            $item['usage_bar_width'] = $metrics['bar_width_pct'];
            $item['remaining_uses'] = $metrics['remaining'];
        }
        $out[] = $item;
    }
    return $out;
}

/**
 * 3. Đếm tổng số voucher khả dụng trong ví của user
 */
function getUserVoucherCount(int $userId): int {
    $vouchers = getUserWalletVouchers($userId);
    $count = 0;
    foreach ($vouchers as $v) {
        if (!empty($v['is_usable'])) {
            $count++;
        }
    }
    return $count;
}

/**
 * 4. Khách hàng 1-Click lưu voucher công khai vào ví cá nhân
 */
function claimPublicVoucher(int $userId, int $voucherId): array {
    if ($userId <= 0) {
        return ['success' => false, 'error' => 'Vui lòng đăng nhập để lưu voucher vào ví.'];
    }

    $db = getDB();
    $now = date('Y-m-d H:i:s');

    $stmt = $db->prepare("SELECT * FROM vouchers WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$voucherId]);
    $voucher = $stmt->fetch();

    if (!$voucher) {
        return ['success' => false, 'error' => 'Voucher không tồn tại hoặc đã ngừng áp dụng.'];
    }

    if (!empty($voucher['end_date']) && $voucher['end_date'] < $now) {
        return ['success' => false, 'error' => 'Voucher đã hết hạn sử dụng.'];
    }

    $totalUsages = 0;
    try {
        $tStmt = $db->prepare("SELECT COUNT(*) FROM voucher_usages WHERE voucher_id = ?");
        $tStmt->execute([$voucherId]);
        $totalUsages = (int)$tStmt->fetchColumn();
    } catch (Exception $e) {}
    $effectiveUsed = max((int)($voucher['usage_count'] ?? 0), $totalUsages);

    if ((int)$voucher['usage_limit'] > 0 && $effectiveUsed >= (int)$voucher['usage_limit']) {
        return ['success' => false, 'error' => 'Voucher đã hết số lượt sử dụng tối đa.'];
    }

    // Kiểm tra điều kiện hạng thành viên
    $reqTier = strtoupper($voucher['min_member_tier'] ?? 'ALL');
    if ($reqTier !== 'ALL') {
        $userTier = getUserMemberTier($userId);
        $tierRanks = ['ALL' => 0, 'BRONZE' => 1, 'SILVER' => 2, 'GOLD' => 3, 'DIAMOND' => 4];
        if (($tierRanks[$userTier] ?? 1) < ($tierRanks[$reqTier] ?? 0)) {
            return [
                'success' => false,
                'error' => "Voucher này dành riêng cho thành viên hạng {$reqTier} trở lên (Hạng hiện tại của bạn là {$userTier}). Hãy tích lũy thêm chi tiêu để nâng hạng nhé!"
            ];
        }
    }

    // Kiểm tra số lần đã dùng của user (chặn nếu đã dùng hết 1 lượt trên voucher_usages hoặc user_vouchers)
    $perUserLimit = getEffectivePerUserLimit($voucher);
    if (getUserVoucherUsedCount($voucherId, $userId, $db) >= $perUserLimit) {
        return ['success' => false, 'error' => "Bạn đã sử dụng hết {$perUserLimit} lượt cho voucher này rồi (chỉ áp dụng {$perUserLimit} lần/tài khoản)."];
    }

    // Kiểm tra đã có trong ví chưa
    $chk = $db->prepare("SELECT id, is_used FROM user_vouchers WHERE user_id = ? AND voucher_id = ? LIMIT 1");
    $chk->execute([$userId, $voucherId]);
    $existing = $chk->fetch();

    if ($existing) {
        if ((int)$existing['is_used'] === 1) {
            return ['success' => false, 'error' => 'Bạn đã sử dụng voucher này rồi.'];
        }
        return ['success' => true, 'message' => 'Voucher này đã có sẵn trong ví của bạn.', 'already_saved' => true, 'voucher' => $voucher];
    }

    // Thêm vào user_vouchers
    $ins = $db->prepare("INSERT INTO user_vouchers (user_id, voucher_id, source, is_used, expires_at) VALUES (?, ?, 'claim', 0, ?)");
    $ins->execute([$userId, $voucherId, $voucher['end_date']]);

    // Đồng bộ tương thích bảng user_saved_vouchers (nếu có coupon cùng id)
    try {
        $db->prepare("INSERT IGNORE INTO user_saved_vouchers (user_id, coupon_id, saved_at) VALUES (?, ?, NOW())")->execute([$userId, $voucherId]);
    } catch (Exception $e) {}

    return [
        'success' => true,
        'message' => "Đã lưu thành công voucher \"{$voucher['code']}\" vào ví của bạn!",
        'voucher' => $voucher
    ];
}

/**
 * 5. Tính toán chiết khấu và kiểm tra tính hợp lệ của voucher với giá trị giỏ hàng
 * Hỗ trợ: percentage, fixed, free_shipping, bogo
 */
function validateAndCalculateVoucher(array $voucher, float $subtotal, ?int $userId = null, float $shippingFee = 18000.0, ?array $cartItems = null): array {
    $now = date('Y-m-d H:i:s');
    $db = getDB();

    // Làm mới dữ liệu thực từ DB nếu voucher có ID & code (tránh cache session cũ vượt quota)
    $isVouchersTableRow = false;
    if (!empty($voucher['id']) && !empty($voucher['code'])) {
        try {
            $fStmt = $db->prepare("SELECT * FROM vouchers WHERE id = ? AND UPPER(code) = UPPER(?) LIMIT 1");
            $fStmt->execute([(int)$voucher['id'], (string)$voucher['code']]);
            $freshV = $fStmt->fetch();
            if ($freshV) {
                $voucher = $freshV;
                $isVouchersTableRow = true;
            } else {
                $cStmt = $db->prepare("SELECT * FROM coupons WHERE id = ? AND UPPER(code) = UPPER(?) LIMIT 1");
                $cStmt->execute([(int)$voucher['id'], (string)$voucher['code']]);
                $freshC = $cStmt->fetch();
                if ($freshC) {
                    $voucher = $freshC;
                }
            }
        } catch (Exception $e) {}
    }

    if (empty($voucher['is_active'])) {
        return ['valid' => false, 'reason' => 'Voucher hiện đang tạm dừng.'];
    }
    if (!empty($voucher['start_date']) && $voucher['start_date'] > $now) {
        return ['valid' => false, 'reason' => 'Voucher chưa đến thời điểm áp dụng.'];
    }
    if (!empty($voucher['end_date']) && $voucher['end_date'] < $now) {
        return ['valid' => false, 'reason' => 'Voucher đã hết hạn sử dụng.'];
    }

    // Kiểm tra tổng số lượt sử dụng của toàn sàn
    $effectivePoolUsed = (int)($voucher['usage_count'] ?? 0);
    if ($isVouchersTableRow && !empty($voucher['id'])) {
        try {
            $uAllStmt = $db->prepare("SELECT COUNT(*) FROM voucher_usages WHERE voucher_id = ?");
            $uAllStmt->execute([(int)$voucher['id']]);
            $effectivePoolUsed = max($effectivePoolUsed, (int)$uAllStmt->fetchColumn());
        } catch (Exception $e) {}
    }
    if (!empty($voucher['usage_limit']) && (int)$voucher['usage_limit'] > 0 && $effectivePoolUsed >= (int)$voucher['usage_limit']) {
        return ['valid' => false, 'reason' => 'Voucher đã hết lượt sử dụng.'];
    }

    // Kiểm tra hạng thành viên
    $reqTier = strtoupper($voucher['min_member_tier'] ?? 'ALL');
    if ($reqTier !== 'ALL') {
        if (!$userId) {
            return ['valid' => false, 'reason' => "Voucher yêu cầu đăng nhập tài khoản đạt hạng {$reqTier} trở lên."];
        }
        $userTier = getUserMemberTier($userId);
        $tierRanks = ['ALL' => 0, 'BRONZE' => 1, 'SILVER' => 2, 'GOLD' => 3, 'DIAMOND' => 4];
        if (($tierRanks[$userTier] ?? 1) < ($tierRanks[$reqTier] ?? 0)) {
            return ['valid' => false, 'reason' => "Voucher dành riêng cho hạng {$reqTier} trở lên (bạn đang là {$userTier})."];
        }
    }

    // Kiểm tra số lần dùng của user (tối đa 1 lần cho tất cả voucher phân hạng thành viên)
    if ($userId > 0 && !empty($voucher['id'])) {
        $usedCount = getUserVoucherUsedCount((int)$voucher['id'], $userId, $db);
        if (!$isVouchersTableRow && !empty($voucher['user_id']) && (int)$voucher['user_id'] === $userId) {
            $usedCount = max($usedCount, (int)($voucher['usage_count'] ?? 0));
        }

        $userLimit = getEffectivePerUserLimit($voucher);
        if ($usedCount >= $userLimit) {
            $reasonMsg = ($userLimit === 1)
                ? 'Mỗi tài khoản chỉ được sử dụng voucher này 1 lần (bạn đã sử dụng rồi).'
                : "Bạn đã sử dụng hết {$userLimit} lượt cho voucher này.";
            return ['valid' => false, 'reason' => $reasonMsg];
        }
    }

    // Kiểm tra đúng món ăn / danh mục áp dụng (trừ freeship & mã giảm giá toàn shop)
    $scopeEval = evaluateVoucherCartScope($voucher, $cartItems, $db);
    if (empty($scopeEval['matched'])) {
        return [
            'valid'            => false,
            'reason'           => $scopeEval['reason'],
            'scope_mismatch'   => true,
            'applicable_label' => $scopeEval['label'],
        ];
    }

    $calculationSubtotal = ($scopeEval['restricted'] && $scopeEval['eligible_subtotal'] !== null)
        ? (float)$scopeEval['eligible_subtotal']
        : $subtotal;

    // Kiểm tra điều kiện đơn hàng tối thiểu (tính trên nhóm món áp dụng nếu voucher giới hạn món)
    $minOrder = (float)($voucher['min_order_value'] ?? 0);
    if ($calculationSubtotal < $minOrder) {
        $diff = $minOrder - $calculationSubtotal;
        $reason = $scopeEval['restricted']
            ? 'Nhóm món áp dụng (' . $scopeEval['label'] . ') cần tối thiểu ' . number_format($minOrder, 0, ',', '.') . 'đ (còn thiếu ' . number_format($diff, 0, ',', '.') . 'đ).'
            : 'Đơn hàng chưa đạt giá trị tối thiểu ' . number_format($minOrder, 0, ',', '.') . 'đ (còn thiếu ' . number_format($diff, 0, ',', '.') . 'đ).';
        return [
            'valid'            => false,
            'reason'           => $reason,
            'min_order'        => $minOrder,
            'missing_amount'   => $diff,
            'applicable_label' => $scopeEval['restricted'] ? $scopeEval['label'] : '',
        ];
    }

    // Tính toán chiết khấu theo loại voucher trên giá trị món hợp lệ
    $discount = 0;
    $discVal = (float)$voucher['discount_value'];
    $vType = $voucher['voucher_type'] ?? ($voucher['discount_type'] ?? 'percentage');

    switch ($vType) {
        case 'percentage':
            $discount = ($calculationSubtotal * $discVal) / 100.0;
            if (!empty($voucher['max_discount_amount']) && (float)$voucher['max_discount_amount'] > 0) {
                $discount = min($discount, (float)$voucher['max_discount_amount']);
            }
            $discount = min($calculationSubtotal, $discount);
            break;

        case 'fixed':
            $discount = min($calculationSubtotal, $discVal);
            break;

        case 'free_shipping':
            // Miễn phí giao hàng: giảm theo mức phí ship hoặc giá trị discVal
            $maxShipDiscount = ($discVal > 0) ? $discVal : $shippingFee;
            $discount = min($shippingFee, $maxShipDiscount);
            break;

        case 'bogo':
            // Mua 1 tặng 1: giảm giá trị món tặng tương đương discVal
            $discount = min($calculationSubtotal, $discVal > 0 ? $discVal : 35000);
            break;

        default:
            $discount = min($calculationSubtotal, $discVal);
    }

    return [
        'valid'             => true,
        'discount_amount'   => $discount,
        'discount_text'     => number_format($discount, 0, ',', '.') . 'đ',
        'eligible_subtotal' => $calculationSubtotal,
        'applicable_scope'  => $scopeEval['restricted'] ? 'specific' : 'all',
        'applicable_label'  => $scopeEval['restricted'] ? $scopeEval['label'] : '',
        'voucher'           => $voucher
    ];
}

/**
 * 6. Ghi nhận sử dụng voucher khi đơn hàng tạo / thanh toán thành công
 */
function recordVoucherUsage(int $voucherId, int $userId, int $orderId, string $orderCode, float $discountAmount): bool {
    if ($voucherId <= 0) return false;
    $db = getDB();

    try {
        // Lấy thông tin voucher để đồng bộ bảng coupons theo mã code
        $vRowStmt = $db->prepare("SELECT code, end_date FROM vouchers WHERE id = ? LIMIT 1");
        $vRowStmt->execute([$voucherId]);
        $vRow = $vRowStmt->fetch();

        // 1. Thêm vào bảng voucher_usages
        $stmt = $db->prepare("INSERT INTO voucher_usages (voucher_id, user_id, order_id, order_code, discount_amount, used_at)
                              VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$voucherId, $userId, $orderId, $orderCode, $discountAmount]);

        // 2. Tăng usage_count trong vouchers (giảm số lượt còn lại & tăng %)
        $db->prepare("UPDATE vouchers SET usage_count = usage_count + 1 WHERE id = ?")->execute([$voucherId]);

        // 3. Đồng bộ usage_count trong coupons theo code (tránh lệch ID khác mã)
        if ($vRow && !empty($vRow['code'])) {
            try {
                $db->prepare("UPDATE coupons SET usage_count = usage_count + 1 WHERE UPPER(code) = UPPER(?)")
                   ->execute([$vRow['code']]);
            } catch (Exception $e) {}
        }

        // 4. Đánh dấu is_used trong user_vouchers (hoặc tạo mới nếu khách nhập mã trực tiếp ở giỏ hàng)
        if ($userId > 0) {
            $uvUpd = $db->prepare("UPDATE user_vouchers SET is_used = 1, used_at = NOW(), order_id = ?
                                   WHERE user_id = ? AND voucher_id = ? AND is_used = 0 LIMIT 1");
            $uvUpd->execute([$orderId, $userId, $voucherId]);

            if ($uvUpd->rowCount() === 0) {
                $uvChk = $db->prepare("SELECT id FROM user_vouchers WHERE user_id = ? AND voucher_id = ? AND is_used = 1 LIMIT 1");
                $uvChk->execute([$userId, $voucherId]);
                if (!$uvChk->fetch()) {
                    $db->prepare("INSERT INTO user_vouchers (user_id, voucher_id, source, is_used, used_at, order_id, expires_at)
                                  VALUES (?, ?, 'claim', 1, NOW(), ?, ?)")
                       ->execute([$userId, $voucherId, $orderId, $vRow['end_date'] ?? null]);
                }
            }
        }
        return true;
    } catch (Exception $e) {
        error_log('Lỗi recordVoucherUsage: ' . $e->getMessage());
        return false;
    }
}

/**
 * 7. Kiểm tra ngày sinh nhật và tự động tặng voucher sinh nhật cho 1 user
 * Mỗi khách hàng chỉ nhận 1 voucher sinh nhật mỗi năm. Thời hạn 7 ngày.
 * Hạng: Bronze (10%), Silver (15%), Gold (20%), Diamond (25%).
 */
function checkAndGiftBirthdayVoucher(int $userId): ?array {
    if ($userId <= 0) return null;
    $db = getDB();

    $stmt = $db->prepare("SELECT id, full_name, email, member_tier, date_of_birth FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || empty($user['date_of_birth'])) {
        return null;
    }

    $dob = $user['date_of_birth'];
    $birthMonth = (int)date('m', strtotime($dob));
    $birthDay   = (int)date('d', strtotime($dob));

    $currentMonth = (int)date('m');
    $currentDay   = (int)date('d');
    $currentYear  = (int)date('Y');

    // Chỉ tặng khi đúng ngày sinh nhật
    if ($birthMonth !== $currentMonth || $birthDay !== $currentDay) {
        return null;
    }

    // Kiểm tra đã nhận voucher sinh nhật năm nay chưa
    $chk = $db->prepare("SELECT id, voucher_id FROM birthday_voucher_logs WHERE user_id = ? AND birth_year = ? LIMIT 1");
    $chk->execute([$userId, $currentYear]);
    if ($chk->fetch()) {
        return null; // Đã nhận trong năm nay
    }

    // Xác định ưu đãi theo hạng thành viên
    $tier = strtoupper($user['member_tier'] ?: 'BRONZE');
    $discountPercent = match($tier) {
        'SILVER'  => 15.0,
        'GOLD'    => 20.0,
        'DIAMOND' => 25.0,
        default   => 10.0 // BRONZE
    };

    $maxDiscount = match($tier) {
        'SILVER'  => 80000.0,
        'GOLD'    => 120000.0,
        'DIAMOND' => 200000.0,
        default   => 50000.0
    };

    $minOrder = match($tier) {
        'SILVER'  => 60000.0,
        'GOLD'    => 80000.0,
        'DIAMOND' => 100000.0,
        default   => 50000.0
    };

    // Sinh mã voucher độc nhất: BD-2026-UID-XXXX
    $randCode = strtoupper(bin2hex(random_bytes(2)));
    $code = "BD-{$currentYear}-{$userId}-{$randCode}";
    $title = "Quà Sinh Nhật FASTFOOD - Giảm {$discountPercent}% (Hạng {$tier})";
    $desc = "Chúc mừng sinh nhật {$user['full_name']}! Món quà tri ân đặc biệt từ FASTFOOD giảm {$discountPercent}% tối đa " . number_format($maxDiscount, 0, ',', '.') . "đ cho bữa tiệc sinh nhật của bạn.";

    $startDate = date('Y-m-d H:i:s');
    $endDate = date('Y-m-d 23:59:59', strtotime('+7 days'));

    $db->beginTransaction();
    try {
        // 1. Thêm vào vouchers
        $insVoucher = $db->prepare("
            INSERT INTO vouchers (code, title, description, voucher_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, user_limit, min_member_tier, is_birthday_voucher, is_public, start_date, end_date, is_active)
            VALUES (?, ?, ?, 'percentage', ?, ?, ?, 1, 0, 1, 'ALL', 1, 0, ?, ?, 1)
        ");
        $insVoucher->execute([$code, $title, $desc, $discountPercent, $minOrder, $maxDiscount, $startDate, $endDate]);
        $voucherId = (int)$db->lastInsertId();

        // 2. Thêm vào coupons để tương thích
        try {
            $insCoupon = $db->prepare("
                INSERT INTO coupons (user_id, code, title, discount_type, voucher_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, start_date, end_date, is_active)
                VALUES (?, ?, ?, 'percentage', 'percentage', ?, ?, ?, 1, 0, ?, ?, 1)
            ");
            $insCoupon->execute([$userId, $code, $title, $discountPercent, $minOrder, $maxDiscount, $startDate, $endDate]);
        } catch (Exception $e) {}

        // 3. Tặng trực tiếp vào ví user_vouchers
        $insUserVoucher = $db->prepare("
            INSERT INTO user_vouchers (user_id, voucher_id, source, is_used, assigned_at, expires_at)
            VALUES (?, ?, 'birthday', 0, NOW(), ?)
        ");
        $insUserVoucher->execute([$userId, $voucherId, $endDate]);

        // 4. Ghi log sinh nhật
        $insLog = $db->prepare("
            INSERT INTO birthday_voucher_logs (user_id, birth_year, voucher_id, tier_at_birth, discount_percent, notification_sent, gifted_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        $insLog->execute([$userId, $currentYear, $voucherId, $tier, $discountPercent]);

        // 5. Gửi thông báo nếu có bảng notifications
        try {
            $db->prepare("INSERT INTO notifications (user_id, title, message, type, created_at)
                          VALUES (?, ?, ?, 'birthday', NOW())")
               ->execute([
                   $userId, 
                   '🎂 Chúc Mừng Sinh Nhật Bạn!', 
                   "FASTFOOD thân gửi tặng bạn mã voucher {$code} giảm {$discountPercent}% (HSD 7 ngày). Hãy tận hưởng bữa tiệc sinh nhật ngon miệng nhé!"
               ]);
        } catch (Exception $e) {}

        $db->commit();

        return [
            'voucher_id'       => $voucherId,
            'code'             => $code,
            'title'            => $title,
            'discount_percent' => $discountPercent,
            'max_discount'     => $maxDiscount,
            'expires_at'       => $endDate,
            'user'             => $user
        ];
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Lỗi checkAndGiftBirthdayVoucher: ' . $e->getMessage());
        return null;
    }
}

/**
 * 8. Quét tự động sinh nhật tất cả khách hàng (Dùng cho Cron Job hằng ngày)
 */
function runDailyBirthdayVouchers(): array {
    $db = getDB();
    $currentMonth = (int)date('m');
    $currentDay   = (int)date('d');
    $currentYear  = (int)date('Y');

    // Tìm tất cả khách hàng có sinh nhật hôm nay và chưa được tặng trong năm nay
    $sql = "
        SELECT u.id, u.full_name, u.email, u.member_tier, u.date_of_birth
        FROM users u
        WHERE u.date_of_birth IS NOT NULL
          AND MONTH(u.date_of_birth) = :m
          AND DAY(u.date_of_birth) = :d
          AND NOT EXISTS (
              SELECT 1 FROM birthday_voucher_logs bvl 
              WHERE bvl.user_id = u.id AND bvl.birth_year = :y
          )
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([':m' => $currentMonth, ':d' => $currentDay, ':y' => $currentYear]);
    $candidates = $stmt->fetchAll();

    $gifted = [];
    foreach ($candidates as $cand) {
        $res = checkAndGiftBirthdayVoucher((int)$cand['id']);
        if ($res) {
            $gifted[] = $res;
        }
    }

    return [
        'total_candidates' => count($candidates),
        'total_gifted'     => count($gifted),
        'gifted_list'      => $gifted,
        'executed_at'      => date('Y-m-d H:i:s')
    ];
}

/**
 * 9. Lấy trạng thái sinh nhật của khách hàng hiện tại
 */
function getUserBirthdayStatus(int $userId): array {
    if ($userId <= 0) {
        return ['has_dob' => false, 'is_birthday_today' => false, 'received_this_year' => false];
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT date_of_birth FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $dob = $stmt->fetchColumn();

    if (!$dob) {
        return ['has_dob' => false, 'is_birthday_today' => false, 'received_this_year' => false];
    }

    $bMonth = (int)date('m', strtotime($dob));
    $bDay   = (int)date('d', strtotime($dob));
    $isToday = ($bMonth === (int)date('m') && $bDay === (int)date('d'));

    $chk = $db->prepare("SELECT v.* FROM birthday_voucher_logs bvl JOIN vouchers v ON bvl.voucher_id = v.id WHERE bvl.user_id = ? AND bvl.birth_year = ? LIMIT 1");
    $chk->execute([$userId, (int)date('Y')]);
    $voucher = $chk->fetch();

    return [
        'has_dob'            => true,
        'date_of_birth'      => $dob,
        'formatted_dob'      => date('d/m/Y', strtotime($dob)),
        'is_birthday_today'  => $isToday,
        'received_this_year' => !empty($voucher),
        'birthday_voucher'   => $voucher ?: null
    ];
}

/**
 * 10. Admin: Lấy danh sách Voucher có phân trang, lọc và thống kê
 */
function getAdminVouchers(array $filters = []): array {
    $db = getDB();
    $sql = "
        SELECT v.*,
               (SELECT COUNT(*) FROM voucher_usages vu WHERE vu.voucher_id = v.id) AS actual_usages,
               (SELECT COALESCE(SUM(vu2.discount_amount), 0) FROM voucher_usages vu2 WHERE vu2.voucher_id = v.id) AS total_discount_given
        FROM vouchers v
        WHERE 1=1
    ";
    $params = [];

    if (!empty($filters['search'])) {
        $sql .= " AND (v.code LIKE :s OR v.title LIKE :s2)";
        $params[':s'] = '%' . $filters['search'] . '%';
        $params[':s2'] = '%' . $filters['search'] . '%';
    }

    if (!empty($filters['voucher_type'])) {
        $sql .= " AND v.voucher_type = :vt";
        $params[':vt'] = $filters['voucher_type'];
    }

    if (!empty($filters['min_member_tier'])) {
        $sql .= " AND v.min_member_tier = :tier";
        $params[':tier'] = $filters['min_member_tier'];
    }

    if (isset($filters['is_active']) && $filters['is_active'] !== '') {
        $sql .= " AND v.is_active = :act";
        $params[':act'] = (int)$filters['is_active'];
    }

    $sql .= " ORDER BY v.id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        enrichVoucherScopeFields($r, $db);
    }
    unset($r);
    return $rows;
}

/**
 * 11. Admin: Thống kê hiệu quả Voucher
 */
function getVoucherStats(): array {
    $db = getDB();
    $totalVouchers = (int)$db->query("SELECT COUNT(*) FROM vouchers")->fetchColumn();
    $activeVouchers = (int)$db->query("SELECT COUNT(*) FROM vouchers WHERE is_active = 1 AND (end_date IS NULL OR end_date >= NOW())")->fetchColumn();
    $totalUsages = (int)$db->query("SELECT COUNT(*) FROM voucher_usages")->fetchColumn();
    $totalDiscount = (float)$db->query("SELECT COALESCE(SUM(discount_amount), 0) FROM voucher_usages")->fetchColumn();
    $birthdayGifted = (int)$db->query("SELECT COUNT(*) FROM birthday_voucher_logs WHERE birth_year = YEAR(NOW())")->fetchColumn();

    return [
        'total_vouchers'  => $totalVouchers,
        'active_vouchers' => $activeVouchers,
        'total_usages'    => $totalUsages,
        'total_discount'  => $totalDiscount,
        'birthday_gifted' => $birthdayGifted
    ];
}

/**
 * 12. Admin: Tạo hoặc Cập nhật Voucher
 */
function saveAdminVoucher(array $data, ?int $voucherId = null): array {
    $db = getDB();
    $code = strtoupper(trim($data['code'] ?? ''));
    if (!$code) {
        $code = 'FAST' . strtoupper(bin2hex(random_bytes(3)));
    }
    $title = trim($data['title'] ?? '');
    if (!$title) {
        return ['success' => false, 'error' => 'Tiêu đề voucher không được để trống.'];
    }

    $desc = trim($data['description'] ?? '');
    $type = $data['voucher_type'] ?? 'percentage';
    if (!in_array($type, ['percentage', 'fixed', 'free_shipping', 'bogo'])) {
        $type = 'percentage';
    }

    $discVal = max(0, (float)($data['discount_value'] ?? 0));
    $minOrder = max(0, (float)($data['min_order_value'] ?? 0));
    $maxDisc = !empty($data['max_discount_amount']) ? max(0, (float)$data['max_discount_amount']) : null;
    $usageLimit = max(0, (int)($data['usage_limit'] ?? 0));
    $minTier = in_array($data['min_member_tier'] ?? '', ['ALL', 'BRONZE', 'SILVER', 'GOLD', 'DIAMOND']) ? $data['min_member_tier'] : 'ALL';
    $userLimit = ($minTier !== 'ALL') ? 1 : max(1, (int)($data['user_limit'] ?? 1));
    $scope = ($type === 'free_shipping')
        ? 'all'
        : (strtolower(trim((string)($data['applicable_scope'] ?? 'all'))) === 'specific' ? 'specific' : 'all');
    $catIdsArr = parseVoucherScopeCategoryIds($data['applicable_category_ids'] ?? null);
    $keywordsArr = parseVoucherScopeKeywords($data['applicable_keywords'] ?? null);
    $catIdsStr = ($scope === 'specific' && !empty($catIdsArr)) ? implode(',', $catIdsArr) : null;
    $keywordsStr = ($scope === 'specific' && !empty($keywordsArr)) ? implode(',', $keywordsArr) : null;
    $scopeLabel = ($scope === 'specific') ? trim((string)($data['applicable_label'] ?? '')) : null;
    if ($scope === 'specific') {
        if (!$catIdsStr && !$keywordsStr) {
            return ['success' => false, 'error' => 'Voucher giới hạn món cần chọn ít nhất 1 danh mục hoặc nhập từ khóa món ăn.'];
        }
        if ($scopeLabel === '') {
            $scopeLabel = normalizeVoucherScopeLabel([
                'applicable_scope'        => 'specific',
                'applicable_category_ids' => $catIdsStr,
                'applicable_keywords'     => $keywordsStr,
            ], $db);
        }
    } else {
        $catIdsStr = null;
        $keywordsStr = null;
        $scopeLabel = null;
    }

    $isPublic = !empty($data['is_public']) ? 1 : 0;
    $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    $startDate = !empty($data['start_date']) ? date('Y-m-d H:i:s', strtotime($data['start_date'])) : date('Y-m-d H:i:s');
    $endDate = !empty($data['end_date']) ? date('Y-m-d H:i:s', strtotime($data['end_date'])) : null;

    if ($voucherId && $voucherId > 0) {
        // Kiểm tra trùng code với voucher khác
        $chk = $db->prepare("SELECT id FROM vouchers WHERE code = ? AND id != ? LIMIT 1");
        $chk->execute([$code, $voucherId]);
        if ($chk->fetch()) {
            return ['success' => false, 'error' => "Mã voucher {$code} đã tồn tại ở bản ghi khác."];
        }

        $stmt = $db->prepare("
            UPDATE vouchers 
            SET code = ?, title = ?, description = ?, voucher_type = ?, discount_value = ?,
                min_order_value = ?, max_discount_amount = ?, usage_limit = ?, user_limit = ?,
                min_member_tier = ?, applicable_scope = ?, applicable_category_ids = ?, applicable_keywords = ?, applicable_label = ?,
                is_public = ?, is_active = ?, start_date = ?, end_date = ?
            WHERE id = ?
        ");
        $stmt->execute([$code, $title, $desc, $type, $discVal, $minOrder, $maxDisc, $usageLimit, $userLimit, $minTier, $scope, $catIdsStr, $keywordsStr, $scopeLabel, $isPublic, $isActive, $startDate, $endDate, $voucherId]);

        // Cập nhật tương thích bảng coupons
        try {
            $db->prepare("UPDATE coupons SET code = ?, title = ?, discount_type = ?, voucher_type = ?, discount_value = ?, min_order_value = ?, max_discount_amount = ?, usage_limit = ?, is_active = ?, start_date = ?, end_date = ? WHERE id = ?")
               ->execute([$code, $title, $type, $type, $discVal, $minOrder, $maxDisc, $usageLimit, $isActive, $startDate, $endDate, $voucherId]);
        } catch (Exception $e) {}

        return ['success' => true, 'message' => "Cập nhật voucher {$code} thành công!", 'voucher_id' => $voucherId];
    } else {
        // Kiểm tra trùng code
        $chk = $db->prepare("SELECT id FROM vouchers WHERE code = ? LIMIT 1");
        $chk->execute([$code]);
        if ($chk->fetch()) {
            return ['success' => false, 'error' => "Mã voucher {$code} đã tồn tại."];
        }

        $stmt = $db->prepare("
            INSERT INTO vouchers (code, title, description, voucher_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, user_limit, min_member_tier, applicable_scope, applicable_category_ids, applicable_keywords, applicable_label, is_public, is_active, start_date, end_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$code, $title, $desc, $type, $discVal, $minOrder, $maxDisc, $usageLimit, $userLimit, $minTier, $scope, $catIdsStr, $keywordsStr, $scopeLabel, $isPublic, $isActive, $startDate, $endDate]);
        $newId = (int)$db->lastInsertId();

        // Thêm tương thích bảng coupons
        try {
            $db->prepare("INSERT INTO coupons (id, code, title, discount_type, voucher_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, is_active, start_date, end_date)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)")
               ->execute([$newId, $code, $title, $type, $type, $discVal, $minOrder, $maxDisc, $usageLimit, $isActive, $startDate, $endDate]);
        } catch (Exception $e) {}

        return ['success' => true, 'message' => "Tạo mới voucher {$code} thành công!", 'voucher_id' => $newId];
    }
}
