<?php
/**
 * FASTFOOD - Product Reviews Helper
 * Xử lý đánh giá khách hàng cho từng sản phẩm, tính điểm trung bình,
 * xác thực đã mua hàng, đồng bộ đánh giá đơn hàng và tự động đảm bảo schema DB.
 */

if (!function_exists('ensureProductReviewsSchema')) {
    /**
     * Đảm bảo bảng product_reviews, product_review_helpful và cột rating trên products tồn tại.
     * Nếu vừa khởi tạo lần đầu, tự động đồng bộ đánh giá đơn hàng và tạo dữ liệu mẫu ban đầu.
     */
    function ensureProductReviewsSchema(PDO $db, bool $forceSync = false): void
    {
        static $alreadyChecked = false;
        if ($alreadyChecked && !$forceSync) {
            return;
        }
        $alreadyChecked = true;

        try {
            $hasPr = (bool)$db->query("SHOW TABLES LIKE 'product_reviews'")->fetchColumn();
            $hasPrh = (bool)$db->query("SHOW TABLES LIKE 'product_review_helpful'")->fetchColumn();
            $prodCols = $db->query("DESCRIBE products")->fetchAll(PDO::FETCH_COLUMN);
            $hasRatingCols = in_array('rating_avg', $prodCols, true) && in_array('rating_count', $prodCols, true);

            if ($hasPr && $hasPrh && $hasRatingCols && !$forceSync) {
                return;
            }

            if (!$hasPr) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `product_reviews` (
                      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `product_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID sản phẩm được đánh giá',
                      `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID khách hàng đánh giá',
                      `order_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'ID đơn hàng đã mua (nếu có)',
                      `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Điểm đánh giá từ 1 đến 5 sao',
                      `comment` TEXT NULL COMMENT 'Nội dung nhận xét của khách hàng',
                      `variant_info` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Kích cỡ / Topping đã đặt',
                      `reply_content` TEXT NULL COMMENT 'Phản hồi từ quán / Admin',
                      `replied_by` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'ID quản trị viên/nhân viên phản hồi',
                      `replied_at` DATETIME NULL DEFAULT NULL COMMENT 'Thời điểm phản hồi',
                      `is_verified_purchase` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Đã mua món ăn thành công',
                      `helpful_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Số lượt thấy hữu ích',
                      `is_approved` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Hiển thị, 0 = Ẩn',
                      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      INDEX `idx_pr_product_approved` (`product_id`, `is_approved`, `created_at`),
                      INDEX `idx_pr_rating` (`product_id`, `rating`),
                      INDEX `idx_pr_user_product` (`user_id`, `product_id`),
                      INDEX `idx_pr_order` (`order_id`),
                      CONSTRAINT `fk_pr_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                      CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                      CONSTRAINT `fk_pr_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                      CONSTRAINT `chk_pr_rating` CHECK (`rating` >= 1 AND `rating` <= 5)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            if (!$hasPrh) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `product_review_helpful` (
                      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `review_id` BIGINT UNSIGNED NOT NULL,
                      `voter_key` VARCHAR(100) NOT NULL COMMENT 'u_<user_id> hoặc s_<session_hash>',
                      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `uq_review_voter` (`review_id`, `voter_key`),
                      CONSTRAINT `fk_prh_review` FOREIGN KEY (`review_id`) REFERENCES `product_reviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            if (!in_array('rating_avg', $prodCols, true)) {
                $db->exec("ALTER TABLE `products` ADD COLUMN `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 0.00 AFTER `prep_time_minutes`");
            }
            if (!in_array('rating_count', $prodCols, true)) {
                $db->exec("ALTER TABLE `products` ADD COLUMN `rating_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `rating_avg`");
            }

            // Sửa order_items cũ bị lưu nhầm product_id = 1 khi có biến thể
            $db->exec("
                UPDATE order_items oi
                INNER JOIN products p ON p.name = oi.product_name
                SET oi.product_id = p.id
                WHERE oi.product_id != p.id
            ");

            // Đồng bộ các đánh giá đơn hàng sẵn có sang product_reviews
            $existingOrderReviews = $db->query("
                SELECT r.order_id, r.user_id, r.rating, r.comment
                FROM reviews r
                INNER JOIN orders o ON o.id = r.order_id
                ORDER BY r.id ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($existingOrderReviews as $orv) {
                syncOrderReviewToProducts(
                    $db,
                    (int)$orv['order_id'],
                    (int)$orv['user_id'],
                    (int)$orv['rating'],
                    (string)($orv['comment'] ?? '')
                );
            }

            // Tạo dữ liệu đánh giá mẫu tự nhiên cho các món nổi bật nếu bảng đang ít dữ liệu
            $currentPrCount = (int)$db->query("SELECT COUNT(*) FROM product_reviews")->fetchColumn();
            if ($currentPrCount < 20) {
                seedInitialProductReviews($db);
            }

            // Đồng bộ lại rating_avg và rating_count cho toàn bộ sản phẩm
            $allProdIds = $db->query("SELECT id FROM products")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($allProdIds as $pid) {
                recalculateProductRating($db, (int)$pid);
            }
        } catch (Throwable $e) {
            // Không làm gián đoạn luồng chính nếu DB đang bảo trì
        }
    }
}

if (!function_exists('seedInitialProductReviews')) {
    /**
     * Khởi tạo đánh giá mẫu chân thực từ các tài khoản khách hàng cho nhóm món nổi bật ở mỗi danh mục
     */
    function seedInitialProductReviews(PDO $db): int
    {
        $customerIds = $db->query("SELECT id FROM users WHERE role_id = 2 ORDER BY id ASC LIMIT 12")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($customerIds)) {
            return 0;
        }
        $adminId = (int)($db->query("SELECT id FROM users WHERE role_id = 1 ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1);

        $targetProds = $db->query("
            SELECT p.id, p.name, c.slug AS cat_slug
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.is_available = 1
            ORDER BY (p.id IN (1,2,3,5,6)) DESC, p.is_featured DESC, p.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $pickedProds = [];
        $catCounts = [];
        foreach ($targetProds as $tp) {
            $pid = (int)$tp['id'];
            $slug = $tp['cat_slug'] ?: 'other';
            if (in_array($pid, [1, 2, 3, 5, 6], true)) {
                $pickedProds[$pid] = $tp;
                continue;
            }
            $catCounts[$slug] = $catCounts[$slug] ?? 0;
            if ($catCounts[$slug] < 4) {
                $pickedProds[$pid] = $tp;
                $catCounts[$slug]++;
            }
        }

        $commentPools = [
            'burger' => [
                [5, 'Thịt nướng mọng nước, phô mai tan chảy béo ngậy ăn cực cuốn. Giao tới nơi vẫn còn nóng hổi!', 'Cỡ Vừa (Regular) • Thêm phô mai lát Cheddar', 1, 6, 'FASTFOOD cảm ơn bạn đã ủng hộ! Hẹn gặp lại bạn ở các đơn hàng tiếp theo nhé 🍔🧡'],
                [5, 'Vỏ bánh mềm thơm, sốt đậm đà vừa miệng. Đóng gói hộp giấy gọn gàng sạch sẽ.', 'Cỡ Lớn (Up-size)', 1, 4, null],
                [4, 'Món ngon, đầy đặn, shipper giao hơi chậm 5 phút giờ cao điểm nhưng chất lượng rất ổn.', 'Cỡ Vừa (Regular)', 1, 2, 'Cảm ơn góp ý của bạn! FASTFOOD sẽ tối ưu điều phối giao hàng giờ cao điểm để phục vụ nhanh hơn nữa ạ.'],
            ],
            'com' => [
                [5, 'Cơm dẻo thơm, sườn nướng ướp đậm vị và rất mềm, chả trứng béo ngon chuẩn vị!', 'Suất Lớn • Trứng ốp la lòng đào', 1, 8, 'Cảm ơn bạn nhiều ạ! Rất vui vì bạn hợp khẩu vị với món cơm của FASTFOOD 🍚✨'],
                [5, 'Phần ăn cực kỳ chất lượng, canh nóng đi kèm đóng riêng không bị đổ. Rất đáng tiền!', 'Hộp Tiêu Chuẩn', 1, 5, null],
                [4, 'Đồ ăn ngon, nước mắm pha vừa vị, rau đồ chua tươi giòn.', 'Hộp Tiêu Chuẩn • Ít mỡ hành', 0, 2, null],
            ],
            'tra-sua' => [
                [5, 'Trà đậm vị, thơm sữa mà không bị gắt ngọt. Trân châu đường đen dẻo dai ngon xuất sắc!', 'Size L (700ml) • 70% đường • 50% đá', 1, 7, 'Cảm ơn bạn đã yêu thích trà sữa nhà FASTFOOD! Chúc bạn một ngày thật ngọt ngào 🧋💛'],
                [5, 'Kem cheese béo mặn uống cực đã, giao nhanh đá chưa kịp tan luôn.', 'Size M (500ml) • Kem cheese mặn', 1, 3, null],
                [4, 'Vị thanh mát giải nhiệt tốt, đóng seal chắc chắn.', 'Size L (700ml) • Thạch nha đam', 1, 1, null],
            ],
            'pizza' => [
                [5, 'Đế bánh giòn rụm bên ngoài mềm bên trong, phô mai kéo sợi siêu nhiều và thơm!', 'Cỡ Vừa • Gấp đôi phô mai Mozzarella', 1, 9, 'FASTFOOD cảm ơn bạn! Rất hân hạnh được phục vụ chiếc pizza nóng hổi cho bữa tiệc của bạn 🍕🔥'],
                [5, 'Topping hải sản / thịt ngập tràn, ăn 2 người no căng luôn.', 'Cỡ Lớn', 1, 4, null],
                [4, 'Nước sốt chuẩn vị Ý, giao đến vẫn ấm nóng.', null, 0, 2, null],
            ],
            'sushi' => [
                [5, 'Nguyên liệu tươi rói, cơm trộn giấm vừa ăn, đóng gói khay xịn kèm đủ nước tương wasabi.', 'Phần Tiêu Chuẩn', 1, 5, 'Cảm ơn quý khách đã tin tưởng lựa chọn món Nhật tại FASTFOOD 🍣✨'],
                [5, 'Cuộn chắc tay, vị thanh tươi rất dễ ăn, sẽ đặt lại nhiều lần.', null, 1, 3, null],
            ],
            'an-vat' => [
                [5, 'Ăn vặt buổi chiều quá hợp luôn, giòn tan đậm vị, nóng hổi vừa thổi vừa ăn!', null, 1, 5, 'Cảm ơn bạn đã ủng hộ! FASTFOOD luôn chiên/nướng mới khi nhận đơn để giữ độ giòn ngon nhất ạ 🥐'],
                [4, 'Ngon, đóng gói cẩn thận sạch sẽ, giá cả hợp lý.', null, 1, 2, null],
            ],
            'default' => [
                [5, 'Món ăn chế biến rất ngon, đúng như mô tả và giao hàng cực nhanh!', null, 1, 4, 'Cảm ơn bạn đã dành lời khen cho FASTFOOD!'],
                [5, 'Chất lượng tuyệt vời, khẩu phần đầy đặn, đóng gói cẩn thận.', null, 1, 3, null],
                [4, 'Hương vị ổn áp, giao nhanh đúng giờ.', null, 0, 1, null],
            ],
        ];

        $checkUserProd = $db->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ? LIMIT 1");
        $insSeed = $db->prepare("
            INSERT INTO product_reviews
                (product_id, user_id, order_id, rating, comment, variant_info, reply_content, replied_by, replied_at, is_verified_purchase, helpful_count, is_approved, created_at)
            VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");

        $seededCount = 0;
        $cLen = count($customerIds);
        $idxCounter = 0;

        foreach ($pickedProds as $pid => $prodInfo) {
            $slug = $prodInfo['cat_slug'] ?? '';
            $poolKey = 'default';
            if (strpos($slug, 'burger') !== false) $poolKey = 'burger';
            elseif (strpos($slug, 'com') !== false) $poolKey = 'com';
            elseif (strpos($slug, 'tra-sua') !== false || strpos($slug, 'ca-phe') !== false) $poolKey = 'tra-sua';
            elseif (strpos($slug, 'pizza') !== false) $poolKey = 'pizza';
            elseif (strpos($slug, 'sushi') !== false) $poolKey = 'sushi';
            elseif (strpos($slug, 'an-vat') !== false || strpos($slug, 'salad') !== false) $poolKey = 'an-vat';

            $pool = $commentPools[$poolKey];
            foreach ($pool as $offset => $revData) {
                $uid = (int)$customerIds[($idxCounter + $offset) % $cLen];
                $checkUserProd->execute([$pid, $uid]);
                if ($checkUserProd->fetchColumn()) {
                    continue;
                }

                [$rating, $comment, $variant, $verified, $helpful, $reply] = $revData;
                $daysAgo = ($offset * 2) + ($idxCounter % 7) + 1;
                $createdAt = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days -" . (($offset + 1) * 3) . " hours"));
                $repliedAt = $reply ? date('Y-m-d H:i:s', strtotime($createdAt . ' +45 minutes')) : null;
                $repliedBy = $reply ? $adminId : null;

                $insSeed->execute([
                    $pid, $uid, $rating, $comment, $variant,
                    $reply, $repliedBy, $repliedAt,
                    $verified, $helpful, $createdAt
                ]);
                $seededCount++;
            }
            $idxCounter++;
        }

        return $seededCount;
    }
}

if (!function_exists('recalculateProductRating')) {
    /**
     * Tính toán lại rating_avg và rating_count cho 1 sản phẩm từ bảng product_reviews
     */
    function recalculateProductRating(PDO $db, int $productId): array
    {
        if ($productId <= 0) {
            return ['rating_avg' => 0.0, 'rating_count' => 0];
        }

        $stmt = $db->prepare("
            SELECT COUNT(*) AS cnt, COALESCE(AVG(rating), 0) AS avg_rating
            FROM product_reviews
            WHERE product_id = ? AND is_approved = 1
        ");
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $count = (int)($row['cnt'] ?? 0);
        $avg   = $count > 0 ? round((float)$row['avg_rating'], 2) : 0.00;

        try {
            $upd = $db->prepare("UPDATE products SET rating_avg = ?, rating_count = ? WHERE id = ?");
            $upd->execute([$avg, $count, $productId]);
        } catch (Throwable $e) {
            // Bỏ qua nếu cột chưa tồn tại
        }

        return [
            'rating_avg'   => $avg,
            'rating_count' => $count,
        ];
    }
}

if (!function_exists('getProductReviewsSummary')) {
    /**
     * Lấy thống kê đánh giá chi tiết của 1 sản phẩm (điểm TB, tổng lượt, phân bổ 1-5 sao)
     */
    function getProductReviewsSummary(PDO $db, int $productId): array
    {
        $breakdown = [];
        for ($s = 5; $s >= 1; $s--) {
            $breakdown[$s] = ['star' => $s, 'count' => 0, 'percent' => 0];
        }

        $total = 0;
        $sumRating = 0;
        $verifiedCount = 0;
        $withCommentCount = 0;

        try {
            $stmt = $db->prepare("
                SELECT
                    rating,
                    COUNT(*) AS cnt,
                    SUM(CASE WHEN is_verified_purchase = 1 THEN 1 ELSE 0 END) AS verified_cnt,
                    SUM(CASE WHEN comment IS NOT NULL AND TRIM(comment) != '' THEN 1 ELSE 0 END) AS comment_cnt
                FROM product_reviews
                WHERE product_id = ? AND is_approved = 1
                GROUP BY rating
            ");
            $stmt->execute([$productId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                $star = (int)$r['rating'];
                $cnt  = (int)$r['cnt'];
                if ($star >= 1 && $star <= 5) {
                    $breakdown[$star]['count'] = $cnt;
                    $total += $cnt;
                    $sumRating += ($star * $cnt);
                    $verifiedCount += (int)$r['verified_cnt'];
                    $withCommentCount += (int)$r['comment_cnt'];
                }
            }

            if ($total > 0) {
                for ($s = 5; $s >= 1; $s--) {
                    $breakdown[$s]['percent'] = (int)round(($breakdown[$s]['count'] / $total) * 100);
                }
            }
        } catch (Throwable $e) {}

        $avg = $total > 0 ? round($sumRating / $total, 1) : 0.0;

        return [
            'avg'                => $avg,
            'total'              => $total,
            'verified_count'     => $verifiedCount,
            'with_comment_count' => $withCommentCount,
            'breakdown'          => array_values($breakdown),
        ];
    }
}

if (!function_exists('getProductReviewsList')) {
    /**
     * Lấy danh sách đánh giá sản phẩm kèm lọc & sắp xếp
     */
    function getProductReviewsList(PDO $db, int $productId, array $filters = [], string $voterKey = ''): array
    {
        $where = ['pr.product_id = ?'];
        $params = [$productId];

        if (empty($filters['include_hidden'])) {
            $where[] = 'pr.is_approved = 1';
        }

        $ratingFilter = isset($filters['rating']) ? (int)$filters['rating'] : 0;
        if ($ratingFilter >= 1 && $ratingFilter <= 5) {
            $where[] = 'pr.rating = ?';
            $params[] = $ratingFilter;
        }

        if (!empty($filters['verified'])) {
            $where[] = 'pr.is_verified_purchase = 1';
        }

        if (!empty($filters['has_comment'])) {
            $where[] = "pr.comment IS NOT NULL AND TRIM(pr.comment) != ''";
        }

        $sort = $filters['sort'] ?? 'newest';
        $orderBy = match ($sort) {
            'helpful' => 'pr.helpful_count DESC, pr.created_at DESC',
            'highest' => 'pr.rating DESC, pr.created_at DESC',
            'lowest'  => 'pr.rating ASC, pr.created_at DESC',
            default   => 'pr.created_at DESC, pr.id DESC',
        };

        $limit  = max(1, min(100, (int)($filters['limit'] ?? 20)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $whereSql = implode(' AND ', $where);
        $helpfulSelect = $voterKey !== ''
            ? ", EXISTS(SELECT 1 FROM product_review_helpful prh WHERE prh.review_id = pr.id AND prh.voter_key = ?) AS is_helpful_by_me"
            : ", 0 AS is_helpful_by_me";

        if ($voterKey !== '') {
            array_unshift($params, $voterKey);
        }

        $sql = "
            SELECT
                pr.*,
                u.full_name AS user_name,
                u.avatar_url AS user_avatar
                $helpfulSelect
            FROM product_reviews pr
            LEFT JOIN users u ON pr.user_id = u.id
            WHERE $whereSql
            ORDER BY $orderBy
            LIMIT $limit OFFSET $offset
        ";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }

        foreach ($rows as &$r) {
            $r['id']                   = (int)$r['id'];
            $r['product_id']           = (int)$r['product_id'];
            $r['user_id']              = (int)$r['user_id'];
            $r['order_id']             = $r['order_id'] ? (int)$r['order_id'] : null;
            $r['rating']               = (int)$r['rating'];
            $r['is_verified_purchase'] = (int)$r['is_verified_purchase'] === 1;
            $r['helpful_count']        = (int)$r['helpful_count'];
            $r['is_approved']          = (int)$r['is_approved'] === 1;
            $r['is_helpful_by_me']     = (int)($r['is_helpful_by_me'] ?? 0) === 1;
            $r['user_name']            = $r['user_name'] ?: 'Thực khách FASTFOOD';
            $r['created_at_formatted'] = formatDateTimeVN($r['created_at']);
            $r['replied_at_formatted'] = !empty($r['replied_at']) ? formatDateTimeVN($r['replied_at']) : null;
        }
        unset($r);

        return $rows;
    }
}

if (!function_exists('hasUserPurchasedProduct')) {
    /**
     * Kiểm tra khách hàng đã mua thành công món ăn này chưa
     */
    function hasUserPurchasedProduct(PDO $db, int $userId, int $productId): array
    {
        if ($userId <= 0 || $productId <= 0) {
            return ['purchased' => false, 'order_id' => null, 'variant_info' => null];
        }

        try {
            $stmt = $db->prepare("
                SELECT o.id AS order_id, oi.special_instructions
                FROM orders o
                INNER JOIN order_items oi ON oi.order_id = o.id
                WHERE o.customer_id = ?
                  AND oi.product_id = ?
                  AND LOWER(o.order_status) IN ('delivered', 'completed')
                ORDER BY o.id DESC
                LIMIT 1
            ");
            $stmt->execute([$userId, $productId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'purchased'    => true,
                    'order_id'     => (int)$row['order_id'],
                    'variant_info' => !empty($row['special_instructions']) ? trim($row['special_instructions']) : null,
                ];
            }

            $prodNameStmt = $db->prepare("SELECT name FROM products WHERE id = ? LIMIT 1");
            $prodNameStmt->execute([$productId]);
            $prodName = $prodNameStmt->fetchColumn();
            if ($prodName) {
                $stmt2 = $db->prepare("
                    SELECT o.id AS order_id, oi.special_instructions
                    FROM orders o
                    INNER JOIN order_items oi ON oi.order_id = o.id
                    WHERE o.customer_id = ?
                      AND oi.product_name = ?
                      AND LOWER(o.order_status) IN ('delivered', 'completed')
                    ORDER BY o.id DESC
                    LIMIT 1
                ");
                $stmt2->execute([$userId, $prodName]);
                $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($row2) {
                    return [
                        'purchased'    => true,
                        'order_id'     => (int)$row2['order_id'],
                        'variant_info' => !empty($row2['special_instructions']) ? trim($row2['special_instructions']) : null,
                    ];
                }
            }
        } catch (Throwable $e) {}

        return ['purchased' => false, 'order_id' => null, 'variant_info' => null];
    }
}

if (!function_exists('getReviewVoterKey')) {
    /**
     * Tạo khóa định danh người bấm "Hữu ích"
     */
    function getReviewVoterKey(?array $user): string
    {
        if (!empty($user['id'])) {
            return 'u_' . (int)$user['id'];
        }
        $sid = session_id();
        if (!$sid) {
            $sid = $_SERVER['REMOTE_ADDR'] ?? 'guest';
        }
        return 's_' . substr(md5($sid), 0, 24);
    }
}

if (!function_exists('syncOrderReviewToProducts')) {
    /**
     * Khi khách hàng đánh giá đơn hàng (order-tracking.php), đồng bộ sang đánh giá của từng món trong đơn
     */
    function syncOrderReviewToProducts(PDO $db, int $orderId, int $userId, int $rating, string $comment): void
    {
        if ($orderId <= 0 || $userId <= 0) return;

        try {
            $itemStmt = $db->prepare("
                SELECT DISTINCT
                    COALESCE(NULLIF(p.id, 0), oi.product_id) AS real_product_id,
                    oi.special_instructions
                FROM order_items oi
                LEFT JOIN products p ON (p.id = oi.product_id AND p.name = oi.product_name) OR (p.name = oi.product_name)
                WHERE oi.order_id = ?
            ");
            $itemStmt->execute([$orderId]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) return;

            $checkStmt = $db->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ? LIMIT 1");
            $updStmt   = $db->prepare("
                UPDATE product_reviews
                SET rating = ?, comment = ?, order_id = ?, is_verified_purchase = 1,
                    variant_info = COALESCE(?, variant_info), updated_at = NOW()
                WHERE id = ?
            ");
            $insStmt   = $db->prepare("
                INSERT INTO product_reviews (product_id, user_id, order_id, rating, comment, variant_info, is_verified_purchase, is_approved, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, 1, NOW(), NOW())
            ");

            $touchedProductIds = [];
            foreach ($items as $it) {
                $pid = (int)($it['real_product_id'] ?? 0);
                if ($pid <= 0 || isset($touchedProductIds[$pid])) continue;

                $existsProd = $db->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
                $existsProd->execute([$pid]);
                if (!$existsProd->fetchColumn()) continue;

                $variantInfo = !empty($it['special_instructions']) ? trim($it['special_instructions']) : null;

                $checkStmt->execute([$userId, $pid]);
                $existingRevId = $checkStmt->fetchColumn();

                if ($existingRevId) {
                    $updStmt->execute([$rating, $comment, $orderId, $variantInfo, (int)$existingRevId]);
                } else {
                    $insStmt->execute([$pid, $userId, $orderId, $rating, $comment, $variantInfo]);
                }

                $touchedProductIds[$pid] = true;
            }

            foreach (array_keys($touchedProductIds) as $pid) {
                recalculateProductRating($db, (int)$pid);
            }
        } catch (Throwable $e) {}
    }
}
