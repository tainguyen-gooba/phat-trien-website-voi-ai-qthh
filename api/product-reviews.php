<?php
/**
 * FASTFOOD - API Đánh giá khách hàng theo từng sản phẩm
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/helpers/response.php';
require_once ROOT_PATH . '/helpers/reviews.php';

$db = getDB();
ensureProductReviewsSchema($db);

$currentUser = currentUser();
$voterKey    = getReviewVoterKey($currentUser);
$method      = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $productId = (int)($_GET['product_id'] ?? 0);
    if ($productId <= 0) {
        jsonError('Thiếu mã sản phẩm (product_id).', 400);
    }

    $includeHidden = isAdminOrStaff() && !empty($_GET['include_hidden']);
    $filters = [
        'rating'         => (int)($_GET['rating'] ?? 0),
        'verified'       => !empty($_GET['verified']),
        'has_comment'    => !empty($_GET['has_comment']),
        'sort'           => trim($_GET['sort'] ?? 'newest'),
        'limit'          => max(1, min(50, (int)($_GET['limit'] ?? 10))),
        'offset'         => max(0, (int)($_GET['offset'] ?? 0)),
        'include_hidden' => $includeHidden,
    ];

    $summary = getProductReviewsSummary($db, $productId);
    $reviews = getProductReviewsList($db, $productId, $filters, $voterKey);

    // Kiểm tra tổng số item khớp bộ lọc để hỗ trợ nút Xem thêm
    $cntWhere = ['product_id = ?'];
    $cntParams = [$productId];
    if (!$includeHidden) {
        $cntWhere[] = 'is_approved = 1';
    }
    if ($filters['rating'] >= 1 && $filters['rating'] <= 5) {
        $cntWhere[] = 'rating = ?';
        $cntParams[] = $filters['rating'];
    }
    if ($filters['verified']) {
        $cntWhere[] = 'is_verified_purchase = 1';
    }
    if ($filters['has_comment']) {
        $cntWhere[] = "comment IS NOT NULL AND TRIM(comment) != ''";
    }
    $cntStmt = $db->prepare('SELECT COUNT(*) FROM product_reviews WHERE ' . implode(' AND ', $cntWhere));
    $cntStmt->execute($cntParams);
    $filteredTotal = (int)$cntStmt->fetchColumn();

    $myReview = null;
    $purchaseInfo = ['purchased' => false, 'order_id' => null, 'variant_info' => null];
    if (!empty($currentUser['id'])) {
        $uid = (int)$currentUser['id'];
        $myStmt = $db->prepare('SELECT * FROM product_reviews WHERE product_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1');
        $myStmt->execute([$productId, $uid]);
        $myRow = $myStmt->fetch(PDO::FETCH_ASSOC);
        if ($myRow) {
            $myReview = [
                'id'           => (int)$myRow['id'],
                'rating'       => (int)$myRow['rating'],
                'comment'      => $myRow['comment'] ?? '',
                'variant_info' => $myRow['variant_info'] ?? '',
            ];
        }
        $purchaseInfo = hasUserPurchasedProduct($db, $uid, $productId);
    }

    jsonSuccess('Lấy danh sách đánh giá thành công.', [
        'summary'        => $summary,
        'reviews'        => $reviews,
        'filtered_total' => $filteredTotal,
        'has_more'       => ($filters['offset'] + count($reviews)) < $filteredTotal,
        'my_review'      => $myReview,
        'purchase_info'  => $purchaseInfo,
    ]);
}

if ($method !== 'POST') {
    jsonError('Phương thức không được hỗ trợ.', 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}
$action = trim($body['action'] ?? $_POST['action'] ?? 'submit');

switch ($action) {
    case 'submit':
        if (empty($currentUser['id'])) {
            jsonError('Vui lòng đăng nhập để gửi đánh giá món ăn.', 401);
        }

        $userId      = (int)$currentUser['id'];
        $productId   = (int)($body['product_id'] ?? 0);
        $rating      = max(1, min(5, (int)($body['rating'] ?? 5)));
        $comment     = trim((string)($body['comment'] ?? ''));
        $variantInfo = trim((string)($body['variant_info'] ?? ''));

        if ($productId <= 0) {
            jsonError('Mã sản phẩm không hợp lệ.');
        }

        // Xác minh sản phẩm tồn tại
        $prodStmt = $db->prepare('SELECT id, name FROM products WHERE id = ? LIMIT 1');
        $prodStmt->execute([$productId]);
        $prodRow = $prodStmt->fetch(PDO::FETCH_ASSOC);
        if (!$prodRow) {
            jsonError('Không tìm thấy món ăn này.', 404);
        }

        if (mb_strlen($comment, 'UTF-8') > 2000) {
            jsonError('Nội dung đánh giá tối đa 2000 ký tự.');
        }
        if (mb_strlen($variantInfo, 'UTF-8') > 250) {
            $variantInfo = mb_substr($variantInfo, 0, 250, 'UTF-8');
        }

        // Kiểm tra đã từng mua thành công món này chưa
        $purchase = hasUserPurchasedProduct($db, $userId, $productId);
        $isVerified = $purchase['purchased'] ? 1 : 0;
        $orderId    = $purchase['order_id'];
        if ($variantInfo === '' && !empty($purchase['variant_info'])) {
            $variantInfo = $purchase['variant_info'];
        }
        $variantDb = $variantInfo !== '' ? $variantInfo : null;

        // Kiểm tra xem user đã đánh giá sản phẩm này chưa -> cập nhật hoặc thêm mới
        $existStmt = $db->prepare('SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1');
        $existStmt->execute([$productId, $userId]);
        $existingId = (int)$existStmt->fetchColumn();

        if ($existingId > 0) {
            $upd = $db->prepare('
                UPDATE product_reviews
                SET rating = ?, comment = ?, variant_info = COALESCE(?, variant_info),
                    order_id = COALESCE(?, order_id),
                    is_verified_purchase = GREATEST(is_verified_purchase, ?),
                    updated_at = NOW()
                WHERE id = ?
            ');
            $upd->execute([$rating, $comment, $variantDb, $orderId, $isVerified, $existingId]);
            $reviewId = $existingId;
            $msg = 'Đã cập nhật đánh giá của bạn thành công!';
        } else {
            $ins = $db->prepare('
                INSERT INTO product_reviews
                    (product_id, user_id, order_id, rating, comment, variant_info, is_verified_purchase, is_approved, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ');
            $ins->execute([$productId, $userId, $orderId, $rating, $comment, $variantDb, $isVerified]);
            $reviewId = (int)$db->lastInsertId();
            $msg = 'Cảm ơn bạn đã gửi đánh giá cho món ăn!';
        }

        recalculateProductRating($db, $productId);
        $summary = getProductReviewsSummary($db, $productId);

        jsonSuccess($msg, [
            'review_id' => $reviewId,
            'summary'   => $summary,
        ]);
        break;

    case 'delete':
        if (empty($currentUser['id'])) {
            jsonError('Vui lòng đăng nhập để thực hiện thao tác này.', 401);
        }

        $reviewId = (int)($body['review_id'] ?? 0);
        if ($reviewId <= 0) {
            jsonError('Thiếu mã đánh giá.');
        }

        $revStmt = $db->prepare('SELECT id, product_id, user_id FROM product_reviews WHERE id = ? LIMIT 1');
        $revStmt->execute([$reviewId]);
        $revRow = $revStmt->fetch(PDO::FETCH_ASSOC);
        if (!$revRow) {
            jsonError('Không tìm thấy đánh giá.', 404);
        }

        $isOwner = ((int)$revRow['user_id'] === (int)$currentUser['id']);
        if (!$isOwner && !isAdminOrStaff()) {
            jsonError('Bạn không có quyền xóa đánh giá này.', 403);
        }

        $db->prepare('DELETE FROM product_reviews WHERE id = ?')->execute([$reviewId]);
        $productId = (int)$revRow['product_id'];
        recalculateProductRating($db, $productId);
        $summary = getProductReviewsSummary($db, $productId);

        jsonSuccess('Đã xóa đánh giá thành công.', [
            'summary' => $summary,
        ]);
        break;

    case 'toggle_helpful':
        $reviewId = (int)($body['review_id'] ?? 0);
        if ($reviewId <= 0) {
            jsonError('Thiếu mã đánh giá.');
        }

        $revExists = $db->prepare('SELECT id FROM product_reviews WHERE id = ? LIMIT 1');
        $revExists->execute([$reviewId]);
        if (!$revExists->fetchColumn()) {
            jsonError('Đánh giá không tồn tại.', 404);
        }

        $chkStmt = $db->prepare('SELECT id FROM product_review_helpful WHERE review_id = ? AND voter_key = ? LIMIT 1');
        $chkStmt->execute([$reviewId, $voterKey]);
        $voteId = $chkStmt->fetchColumn();

        if ($voteId) {
            $db->prepare('DELETE FROM product_review_helpful WHERE id = ?')->execute([(int)$voteId]);
            $isHelpful = false;
        } else {
            $db->prepare('INSERT IGNORE INTO product_review_helpful (review_id, voter_key) VALUES (?, ?)')->execute([$reviewId, $voterKey]);
            $isHelpful = true;
        }

        $cntStmt = $db->prepare('SELECT COUNT(*) FROM product_review_helpful WHERE review_id = ?');
        $cntStmt->execute([$reviewId]);
        $actualVotes = (int)$cntStmt->fetchColumn();

        // Cộng thêm baseline nếu đánh giá mẫu có helpful_count sẵn > số row trong helpful table
        $db->prepare('
            UPDATE product_reviews
            SET helpful_count = GREATEST(?, helpful_count + ?)
            WHERE id = ?
        ')->execute([$actualVotes, $isHelpful ? 1 : -1, $reviewId]);

        $finalCount = (int)$db->query("SELECT helpful_count FROM product_reviews WHERE id = $reviewId")->fetchColumn();

        jsonSuccess($isHelpful ? 'Đã đánh dấu hữu ích.' : 'Đã bỏ đánh dấu hữu ích.', [
            'helpful_count'    => max(0, $finalCount),
            'is_helpful_by_me' => $isHelpful,
        ]);
        break;

    case 'reply':
        if (!isAdminOrStaff()) {
            jsonError('Chỉ Quản trị viên hoặc Nhân viên mới có quyền phản hồi.', 403);
        }

        $reviewId = (int)($body['review_id'] ?? 0);
        $reply    = trim((string)($body['reply_content'] ?? ''));

        if ($reviewId <= 0) {
            jsonError('Thiếu mã đánh giá.');
        }
        if ($reply === '') {
            jsonError('Vui lòng nhập nội dung phản hồi.');
        }

        $upd = $db->prepare('UPDATE product_reviews SET reply_content = ?, replied_by = ?, replied_at = NOW(), updated_at = NOW() WHERE id = ?');
        $upd->execute([$reply, (int)$currentUser['id'], $reviewId]);

        jsonSuccess('Đã gửi phản hồi tới khách hàng!', [
            'reply_content'        => $reply,
            'replied_at_formatted' => formatDateTimeVN(date('Y-m-d H:i:s')),
        ]);
        break;

    case 'toggle_approve':
        if (!isAdminOrStaff()) {
            jsonError('Chỉ Quản trị viên hoặc Nhân viên mới có quyền ẩn/hiện đánh giá.', 403);
        }

        $reviewId = (int)($body['review_id'] ?? 0);
        if ($reviewId <= 0) {
            jsonError('Thiếu mã đánh giá.');
        }

        $rowStmt = $db->prepare('SELECT product_id, is_approved FROM product_reviews WHERE id = ? LIMIT 1');
        $rowStmt->execute([$reviewId]);
        $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            jsonError('Không tìm thấy đánh giá.', 404);
        }

        $newApproved = ((int)$row['is_approved'] === 1) ? 0 : 1;
        $db->prepare('UPDATE product_reviews SET is_approved = ? WHERE id = ?')->execute([$newApproved, $reviewId]);

        $productId = (int)$row['product_id'];
        recalculateProductRating($db, $productId);
        $summary = getProductReviewsSummary($db, $productId);

        jsonSuccess($newApproved ? 'Đã hiển thị đánh giá.' : 'Đã ẩn đánh giá khỏi trang sản phẩm.', [
            'is_approved' => ($newApproved === 1),
            'summary'     => $summary,
        ]);
        break;

    default:
        jsonError('Hành động không hợp lệ.', 400);
}
