<?php
/**
 * FASTFOOD - Chi tiết món ăn (Food Detail)
 * Hỗ trợ đầy đủ biến thể: Kích cỡ (Size), Toppings, Khẩu vị & Ghi chú
 */
require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/helpers/reviews.php';

$db = getDB();
ensureProductReviewsSchema($db);

$id = trim($_GET['id'] ?? '');

$product = null;
if ($id) {
    $stmt = $db->prepare("SELECT p.*, r.name AS restaurant_name, r.address AS restaurant_address, 
                                 r.rating_avg AS restaurant_rating_avg, r.rating_count AS restaurant_rating_count, c.name AS category_name
                          FROM products p
                          LEFT JOIN restaurants r ON p.restaurant_id = r.id
                          LEFT JOIN categories c ON p.category_id = c.id
                          WHERE p.id = ? OR p.product_code = ? LIMIT 1");
    $stmt->execute([$id, $id]);
    $product = $stmt->fetch();

    if (!$product && is_numeric($id)) {
        $stmt = $db->prepare("SELECT p.*, r.name AS restaurant_name, r.address AS restaurant_address, 
                                     r.rating_avg AS restaurant_rating_avg, r.rating_count AS restaurant_rating_count, c.name AS category_name
                              FROM products p
                              LEFT JOIN restaurants r ON p.restaurant_id = r.id
                              LEFT JOIN categories c ON p.category_id = c.id
                              WHERE p.id = ? LIMIT 1");
        $stmt->execute([(int)$id]);
        $product = $stmt->fetch();
    }
}

if (!$product) {
    header('Location: ' . getUrl('foods.php'));
    exit;
}

$pageTitle = $product['name'] . ' - FASTFOOD';
$activeNav = 'foods';
$extraCss  = ['css/pages.css'];

$hasDiscount = !empty($product['discount_price']) && (float)$product['discount_price'] > 0 && (float)$product['discount_price'] < (float)$product['base_price'];
$currentPrice = $hasDiscount ? (float)$product['discount_price'] : (float)$product['base_price'];

// 1. Phân tích biến thể & tùy chọn (Options: sizes, toppings, attributes)
$productOptions = [];
if (!empty($product['options'])) {
    $productOptions = is_array($product['options']) ? $product['options'] : json_decode($product['options'], true);
}

// Fallback thông minh nếu sản phẩm chưa có options
if (empty($productOptions) || !is_array($productOptions)) {
    $catName = mb_strtolower($product['category_name'] ?? '', 'UTF-8');
    if (strpos($catName, 'trà sữa') !== false || strpos($catName, 'uống') !== false) {
        $productOptions = [
            'sizes' => [
                ['name' => 'Size M (500ml)', 'price' => 0, 'is_default' => true],
                ['name' => 'Size L (700ml)', 'price' => 8000, 'is_default' => false]
            ],
            'toppings' => [
                ['name' => 'Trân châu đường đen dẻo', 'price' => 6000],
                ['name' => 'Kem cheese mặn béo ngậy', 'price' => 10000],
                ['name' => 'Thạch nha đam thanh mát', 'price' => 5000],
                ['name' => 'Pudding trứng mịn màng', 'price' => 7000]
            ],
            'attributes' => [
                ['name' => 'Lượng đường', 'options' => ['100% đường (Chuẩn)', '70% đường', '50% đường', '30% đường', 'Không đường']],
                ['name' => 'Lượng đá', 'options' => ['100% đá (Chuẩn)', '50% đá (Ít đá)', 'Không đá']]
            ]
        ];
    } elseif (strpos($catName, 'burger') !== false || strpos($catName, 'gà') !== false) {
        $productOptions = [
            'sizes' => [
                ['name' => 'Cỡ Vừa (Regular)', 'price' => 0, 'is_default' => true],
                ['name' => 'Cỡ Lớn (Up-size)', 'price' => 15000, 'is_default' => false],
                ['name' => 'Double Nhân Đôi (Siêu To)', 'price' => 25000, 'is_default' => false]
            ],
            'toppings' => [
                ['name' => 'Thêm phô mai lát Cheddar', 'price' => 10000],
                ['name' => 'Thịt ba chỉ xông khói Bacon', 'price' => 15000],
                ['name' => 'Khoai tây chiên nhỏ đi kèm', 'price' => 15000]
            ],
            'attributes' => [
                ['name' => 'Hương vị', 'options' => ['Giòn truyền thống', 'Sốt cay Hàn Quốc', 'Lắc phô mai (+0đ)']]
            ]
        ];
    } else {
        $productOptions = [
            'sizes' => [
                ['name' => 'Hộp Tiêu Chuẩn', 'price' => 0, 'is_default' => true],
                ['name' => 'Suất Lớn (+ Cơm & Thịt)', 'price' => 10000, 'is_default' => false],
                ['name' => 'Suất Đặc Biệt Siêu No', 'price' => 20000, 'is_default' => false]
            ],
            'toppings' => [
                ['name' => 'Trứng ốp la lòng đào', 'price' => 8000],
                ['name' => 'Chả trứng / Chả lụa thơm', 'price' => 10000],
                ['name' => 'Canh rong biển súp ấm', 'price' => 12000],
                ['name' => 'Thêm cơm trắng dẻo thơm', 'price' => 5000]
            ],
            'attributes' => [
                ['name' => 'Mức độ cay', 'options' => ['Không cay', 'Cay vừa', 'Cay nhiều']],
                ['name' => 'Ghi chú chế biến', 'options' => ['Đầy đủ mỡ hành', 'Ít mỡ hành', 'Không hành lá']]
            ]
        ];
    }
}

$sizes = $productOptions['sizes'] ?? [];
$toppings = $productOptions['toppings'] ?? [];
$attributes = $productOptions['attributes'] ?? [];

// Món ăn liên quan
$relatedStmt = $db->prepare("SELECT p.*, r.name AS restaurant_name, c.name AS category_name 
                             FROM products p 
                             LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                             LEFT JOIN categories c ON p.category_id = c.id 
                             WHERE p.id != ? AND (p.category_id = ? OR p.restaurant_id = ?) AND p.is_available = 1 
                             ORDER BY p.id DESC LIMIT 4");
$relatedStmt->execute([$product['id'], $product['category_id'] ?? 0, $product['restaurant_id'] ?? 0]);
$relatedFoods = $relatedStmt->fetchAll();

$currentUser     = currentUser();
$isManager       = isAdmin();
$isStaffOrAdmin  = isAdminOrStaff();
$voterKey        = getReviewVoterKey($currentUser);
$productIdInt    = (int)$product['id'];
$reviewSummary   = getProductReviewsSummary($db, $productIdInt);
$initialLimit    = 8;
$initialReviews  = getProductReviewsList($db, $productIdInt, [
    'limit'          => $initialLimit,
    'offset'         => 0,
    'include_hidden' => $isStaffOrAdmin,
], $voterKey);

$totalVisibleQuery = $db->prepare('SELECT COUNT(*) FROM product_reviews WHERE product_id = ?' . ($isStaffOrAdmin ? '' : ' AND is_approved = 1'));
$totalVisibleQuery->execute([$productIdInt]);
$initialFilteredTotal = (int)$totalVisibleQuery->fetchColumn();
$initialHasMore = count($initialReviews) < $initialFilteredTotal;

$myReview = null;
$purchaseInfo = ['purchased' => false, 'order_id' => null, 'variant_info' => null];
if (!empty($currentUser['id'])) {
    $uid = (int)$currentUser['id'];
    $myStmt = $db->prepare('SELECT id, rating, comment, variant_info FROM product_reviews WHERE product_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1');
    $myStmt->execute([$productIdInt, $uid]);
    $myRow = $myStmt->fetch(PDO::FETCH_ASSOC);
    if ($myRow) {
        $myReview = [
            'id'           => (int)$myRow['id'],
            'rating'       => (int)$myRow['rating'],
            'comment'      => $myRow['comment'] ?? '',
            'variant_info' => $myRow['variant_info'] ?? '',
        ];
    }
    $purchaseInfo = hasUserPurchasedProduct($db, $uid, $productIdInt);
}

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Scoped Styles for Food Variants -->
<style>
.fd-variant-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 1.15rem 1.25rem;
  margin-bottom: 1.25rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.fd-variant-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.85rem;
}
.fd-variant-title {
  font-size: 0.96rem;
  font-weight: 800;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.45rem;
}
.fd-variant-badge {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.18rem 0.55rem;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
}
.fd-variant-badge.required {
  background: #fee2e2;
  color: #ef4444;
}
.fd-variant-badge.optional {
  background: #ecfdf5;
  color: #059669;
}
.fd-size-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 0.65rem;
}
.fd-size-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 0.9rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
  transition: all 0.16s ease;
  user-select: none;
}
.fd-size-item:hover {
  border-color: #fdba74;
  background: #fffaf5;
}
.fd-size-item.selected {
  border-color: #ea580c;
  background: #fff7ed;
  box-shadow: 0 0 0 2px rgba(234, 88, 12, 0.12);
}
.fd-size-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.fd-size-radio {
  width: 17px;
  height: 17px;
  accent-color: #ea580c;
  cursor: pointer;
}
.fd-size-name {
  font-size: 0.88rem;
  font-weight: 700;
  color: #1e293b;
}
.fd-size-price {
  font-size: 0.85rem;
  font-weight: 800;
  color: #ea580c;
}

.fd-toppings-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 0.65rem;
}
.fd-topping-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.7rem 0.85rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
  transition: all 0.16s ease;
  user-select: none;
}
.fd-topping-item:hover {
  border-color: #fdba74;
  background: #fffaf5;
}
.fd-topping-item.selected {
  border-color: #ea580c;
  background: #fff7ed;
}
.fd-topping-left {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}
.fd-topping-cb {
  width: 17px;
  height: 17px;
  accent-color: #ea580c;
  cursor: pointer;
}
.fd-topping-name {
  font-size: 0.88rem;
  font-weight: 600;
  color: #334155;
}
.fd-topping-price {
  font-size: 0.85rem;
  font-weight: 800;
  color: #ea580c;
}

.fd-attr-group {
  margin-bottom: 0.85rem;
}
.fd-attr-group:last-child {
  margin-bottom: 0;
}
.fd-attr-name {
  font-size: 0.85rem;
  font-weight: 750;
  color: #475569;
  margin-bottom: 0.45rem;
}
.fd-attr-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}
.fd-chip-btn {
  padding: 0.4rem 0.8rem;
  border-radius: 999px;
  border: 1.5px solid #e2e8f0;
  background: #fff;
  color: #475569;
  font-size: 0.82rem;
  font-weight: 650;
  cursor: pointer;
  transition: all 0.15s ease;
}
.fd-chip-btn:hover {
  border-color: #cbd5e1;
  background: #f8fafc;
}
.fd-chip-btn.active {
  border-color: #ea580c;
  background: #ea580c;
  color: #ffffff;
}
.fd-note-input {
  width: 100%;
  padding: 0.65rem 0.85rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.88rem;
  color: #334155;
  outline: none;
  font-family: inherit;
  transition: border-color 0.16s ease;
}
.fd-note-input:focus {
  border-color: #ea580c;
  box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.1);
}
.fd-summary-strip {
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 10px;
  padding: 0.75rem 1rem;
  margin-bottom: 1.25rem;
  font-size: 0.86rem;
  color: #475569;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}

/* --- PRODUCT REVIEWS SECTION --- */
.pr-section {
  margin-top: 2.75rem;
  margin-bottom: 3.5rem;
  background: #ffffff;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-xl);
  padding: 2rem 2.25rem;
  box-shadow: var(--shadow-sm);
}
.pr-header-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}
.pr-section-title {
  font-size: 1.45rem;
  font-weight: 800;
  color: var(--text-main);
  margin: 0 0 0.25rem;
}
.pr-section-sub {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.9rem;
}
.pr-overview-box {
  display: grid;
  grid-template-columns: 220px 1fr 260px;
  gap: 1.75rem;
  align-items: center;
  background: linear-gradient(135deg, #fffaf5 0%, #fff7ed 100%);
  border: 1px solid #fed7aa;
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}
.pr-score-col {
  text-align: center;
  border-right: 1px solid #fed7aa;
  padding-right: 1.25rem;
}
.pr-score-num {
  font-size: 2.75rem;
  font-weight: 900;
  color: #ea580c;
  line-height: 1.05;
}
.pr-score-max {
  font-size: 1rem;
  font-weight: 700;
  color: #9a3412;
}
.pr-score-stars {
  color: var(--accent-gold);
  font-size: 1.25rem;
  letter-spacing: 2px;
  margin: 0.35rem 0;
}
.pr-score-count {
  font-size: 0.85rem;
  color: #7c2d12;
  font-weight: 600;
}
.pr-bars-col {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}
.pr-bar-row {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  cursor: pointer;
  padding: 0.15rem 0.4rem;
  border-radius: 8px;
  transition: background 0.15s;
}
.pr-bar-row:hover {
  background: rgba(234, 88, 12, 0.08);
}
.pr-bar-label {
  font-size: 0.82rem;
  font-weight: 700;
  color: #334155;
  min-width: 44px;
}
.pr-bar-track {
  flex: 1;
  height: 9px;
  background: #fde68a;
  background: rgba(251, 146, 60, 0.18);
  border-radius: 999px;
  overflow: hidden;
}
.pr-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #f59e0b, #ea580c);
  border-radius: 999px;
  transition: width 0.35s ease;
}
.pr-bar-pct {
  font-size: 0.78rem;
  font-weight: 600;
  color: #64748b;
  min-width: 68px;
  text-align: right;
}
.pr-cta-col {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.6rem;
}
.pr-verified-note {
  font-size: 0.78rem;
  font-weight: 700;
  color: #059669;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  padding: 0.25rem 0.7rem;
  border-radius: 999px;
}
.pr-form-card {
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 1.75rem;
  animation: fadeIn 0.2s ease;
}
.pr-form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}
.pr-star-picker {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 2rem;
  cursor: pointer;
  user-select: none;
}
.pr-star-btn {
  background: none;
  border: none;
  padding: 0 2px;
  font-size: 2rem;
  line-height: 1;
  cursor: pointer;
  color: #cbd5e1;
  transition: transform 0.14s ease, color 0.14s ease;
}
.pr-star-btn:hover {
  transform: scale(1.16);
}
.pr-star-btn.active {
  color: #f59e0b;
}
.pr-quick-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin: 0.75rem 0 1rem;
}
.pr-quick-chip {
  font-size: 0.78rem;
  font-weight: 600;
  padding: 0.32rem 0.75rem;
  border-radius: 999px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
}
.pr-quick-chip:hover {
  border-color: #ea580c;
  color: #ea580c;
  background: #fff7ed;
}
.pr-textarea {
  width: 100%;
  min-height: 96px;
  padding: 0.8rem 1rem;
  border: 1.5px solid #cbd5e1;
  border-radius: 12px;
  font-family: inherit;
  font-size: 0.92rem;
  color: #1e293b;
  resize: vertical;
  outline: none;
  background: #ffffff;
}
.pr-textarea:focus {
  border-color: #ea580c;
  box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.12);
}
.pr-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.25rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #f1f5f9;
}
.pr-filter-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}
.pr-filter-btn {
  padding: 0.4rem 0.9rem;
  border-radius: 999px;
  border: 1.5px solid #e2e8f0;
  background: #ffffff;
  color: #475569;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}
.pr-filter-btn:hover {
  border-color: #fdba74;
  color: #ea580c;
}
.pr-filter-btn.active {
  background: #ea580c;
  border-color: #ea580c;
  color: #ffffff;
}
.pr-sort-select {
  padding: 0.45rem 0.85rem;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
  background: #ffffff;
  color: #334155;
  font-size: 0.84rem;
  font-weight: 600;
  outline: none;
  cursor: pointer;
}
.pr-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.pr-card {
  border: 1px solid #f1f5f9;
  background: #ffffff;
  border-radius: 14px;
  padding: 1.25rem;
  transition: box-shadow 0.18s ease, border-color 0.18s ease;
}
.pr-card:hover {
  border-color: #fed7aa;
  box-shadow: 0 4px 16px rgba(234, 88, 12, 0.06);
}
.pr-card.is-hidden-review {
  opacity: 0.62;
  background: #f8fafc;
  border-style: dashed;
}
.pr-card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.85rem;
  margin-bottom: 0.6rem;
}
.pr-user-wrap {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.pr-avatar {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  border: 2px solid #ffedd5;
}
.pr-avatar-fallback {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: linear-gradient(135deg, #f97316, #ea580c);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 1rem;
  flex-shrink: 0;
}
.pr-user-name-row {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  flex-wrap: wrap;
}
.pr-user-name {
  font-weight: 800;
  font-size: 0.95rem;
  color: #0f172a;
}
.pr-verified-badge {
  font-size: 0.72rem;
  font-weight: 700;
  color: #059669;
  background: #ecfdf5;
  padding: 0.12rem 0.5rem;
  border-radius: 999px;
  border: 1px solid #a7f3d0;
}
.pr-mine-badge {
  font-size: 0.72rem;
  font-weight: 700;
  color: #c2410c;
  background: #ffedd5;
  padding: 0.12rem 0.5rem;
  border-radius: 999px;
}
.pr-meta-sub {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  font-size: 0.78rem;
  color: #64748b;
  margin-top: 0.2rem;
  flex-wrap: wrap;
}
.pr-card-stars {
  color: #f59e0b;
  font-size: 0.95rem;
  letter-spacing: 1px;
}
.pr-variant-chip {
  display: inline-block;
  margin: 0.25rem 0 0.5rem;
  font-size: 0.78rem;
  color: #475569;
  background: #f1f5f9;
  padding: 0.22rem 0.65rem;
  border-radius: 8px;
}
.pr-comment-body {
  margin: 0.35rem 0 0.75rem;
  font-size: 0.94rem;
  line-height: 1.6;
  color: #1e293b;
  white-space: pre-line;
}
.pr-reply-box {
  background: #fff7ed;
  border-left: 3px solid #ea580c;
  border-radius: 0 10px 10px 0;
  padding: 0.75rem 1rem;
  margin: 0.65rem 0 0.75rem;
}
.pr-reply-head {
  font-size: 0.8rem;
  font-weight: 800;
  color: #c2410c;
  margin-bottom: 0.25rem;
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
}
.pr-reply-text {
  margin: 0;
  font-size: 0.88rem;
  color: #431407;
  line-height: 1.5;
}
.pr-card-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.65rem;
  flex-wrap: wrap;
}
.pr-helpful-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.32rem 0.75rem;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #475569;
  font-size: 0.79rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}
.pr-helpful-btn:hover,
.pr-helpful-btn.active {
  border-color: #ea580c;
  background: #fff7ed;
  color: #ea580c;
}
.pr-owner-actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  flex-wrap: wrap;
}
.pr-mini-btn {
  padding: 0.28rem 0.65rem;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #334155;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
}
.pr-mini-btn:hover {
  background: #f1f5f9;
}
.pr-mini-btn.danger:hover {
  background: #fee2e2;
  border-color: #fecaca;
  color: #dc2626;
}
.pr-inline-reply-form {
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px dashed #e2e8f0;
  display: flex;
  gap: 0.5rem;
}
.pr-empty-state {
  text-align: center;
  padding: 2.75rem 1rem;
  background: #f8fafc;
  border-radius: 14px;
  border: 1px dashed #cbd5e1;
  color: #64748b;
}
@media (max-width: 900px) {
  .pr-overview-box {
    grid-template-columns: 1fr;
    text-align: center;
  }
  .pr-score-col {
    border-right: none;
    border-bottom: 1px solid #fed7aa;
    padding-right: 0;
    padding-bottom: 1rem;
  }
  .pr-section {
    padding: 1.25rem;
  }
}
</style>

<!-- Page Header Bar -->
<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= getUrl('index.php') ?>">Trang chủ</a> <span>/</span> 
      <a href="<?= getUrl('foods.php') ?>">Món ăn</a> <span>/</span> 
      <span><?= escapeHtml($product['name']) ?></span>
    </div>
    <h1 class="page-title">Thông Tin Món Ăn</h1>
  </div>
</div>

<main class="container">
  <div id="food-detail-container" style="margin-top: 2rem;">
    <div class="food-detail-grid">
      <!-- Ảnh món ăn -->
      <div class="food-gallery-main">
        <img src="<?= escapeHtml(getFoodImageUrl($product['image_url'] ?? '')) ?>" 
             alt="<?= escapeHtml($product['name']) ?>" class="food-gallery-img"
             referrerpolicy="no-referrer"
             onerror="this.onerror=null; this.src='<?= BASE_URL ?>/assets/images/foods/banh-mi-chao.jpg';">
      </div>

      <!-- Chi tiết thông tin & Biến thể tùy chọn -->
      <div>
        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem; flex-wrap:wrap;">
          <span class="badge badge-primary">FASTFOOD Chính Hãng</span>
          <a href="#product-reviews-section" id="fd-top-rating-link" style="color:var(--accent-gold); font-weight:700; font-size:0.9rem; text-decoration:none;">
            <?php if ($reviewSummary['total'] > 0): ?>
              ★ <?= number_format($reviewSummary['avg'], 1) ?> (<?= (int)$reviewSummary['total'] ?> đánh giá)
            <?php else: ?>
              ★ Chưa có đánh giá
            <?php endif; ?>
          </a>
          <span style="color:var(--text-muted); font-size:0.85rem;">• ⏱️ <?= (int)($product['prep_time_minutes'] ?: 15) ?> phút</span>
        </div>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:0.65rem;">
          🛵 Giao hàng tận nơi siêu tốc trong 20 phút tại Buôn Ma Thuột
        </p>

        <h1 style="font-size:2rem; font-weight:800; color:var(--text-main); line-height:1.25; margin-bottom:0.5rem;">
          <?= escapeHtml($product['name']) ?>
        </h1>
        
        <div class="detail-price-box" style="margin-bottom:0.85rem;">
          <span id="detail-total-price" class="detail-current-price"><?= formatVND($currentPrice) ?></span>
          <?php if ($hasDiscount): ?>
            <span class="detail-old-price"><?= formatVND($product['base_price']) ?></span>
          <?php endif; ?>
        </div>

        <p style="color:var(--text-secondary); line-height:1.7; font-size:0.95rem; margin-bottom:1.5rem;">
          <?= nl2br(escapeHtml($product['description'] ?: 'Món ăn thơm ngon, nóng hổi, chuẩn vị chế biến từ nguyên liệu tươi sạch hàng ngày.')) ?>
        </p>

        <!-- ========================================== -->
        <!-- 1. BIẾN THỂ KÍCH CỠ / SIZE (Chọn 1)       -->
        <!-- ========================================== -->
        <?php if (!empty($sizes)): ?>
          <div class="fd-variant-card">
            <div class="fd-variant-head">
              <span class="fd-variant-title">📏 1. Chọn kích cỡ / Khẩu phần:</span>
              <span class="fd-variant-badge required">Bắt buộc</span>
            </div>
            <div class="fd-size-grid">
              <?php foreach ($sizes as $idx => $s): 
                $isDef = !empty($s['is_default']) || $idx === 0;
                $sPrice = (float)($s['price'] ?? 0);
              ?>
                <label class="fd-size-item <?= $isDef ? 'selected' : '' ?>" id="fd-size-item-<?= $idx ?>" 
                       onclick="selectSize(<?= $idx ?>, '<?= escapeHtml($s['name']) ?>', <?= $sPrice ?>)">
                  <div class="fd-size-info">
                    <input type="radio" name="product_size" class="fd-size-radio" value="<?= escapeHtml($s['name']) ?>" 
                           <?= $isDef ? 'checked' : '' ?> data-price="<?= $sPrice ?>" data-name="<?= escapeHtml($s['name']) ?>">
                    <span class="fd-size-name"><?= escapeHtml($s['name']) ?></span>
                  </div>
                  <span class="fd-size-price">
                    <?= $sPrice > 0 ? '+' . formatVND($sPrice) : 'Gốc' ?>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- ========================================== -->
        <!-- 2. TÙY CHỌN TOPPING & MÓN KÈM (Chọn nhiều) -->
        <!-- ========================================== -->
        <?php if (!empty($toppings)): ?>
          <div class="fd-variant-card">
            <div class="fd-variant-head">
              <span class="fd-variant-title">✨ 2. Món ăn kèm / Toppings:</span>
              <span class="fd-variant-badge optional">Tùy chọn (<?= count($toppings) ?> món)</span>
            </div>
            <div class="fd-toppings-list">
              <?php foreach ($toppings as $tIdx => $t): 
                $tPrice = (float)($t['price'] ?? 0);
              ?>
                <label class="fd-topping-item" id="fd-topping-item-<?= $tIdx ?>">
                  <span class="fd-topping-left">
                    <input type="checkbox" class="fd-topping-cb" data-name="<?= escapeHtml($t['name']) ?>" 
                           data-price="<?= $tPrice ?>" onchange="toggleTopping(this, 'fd-topping-item-<?= $tIdx ?>')">
                    <span class="fd-topping-name"><?= escapeHtml($t['name']) ?></span>
                  </span>
                  <span class="fd-topping-price">+<?= formatVND($tPrice) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- ========================================== -->
        <!-- 3. TÙY CHỌN KHẨU VỊ / THUỘC TÍNH (Chọn 1) -->
        <!-- ========================================== -->
        <?php if (!empty($attributes)): ?>
          <div class="fd-variant-card">
            <div class="fd-variant-head">
              <span class="fd-variant-title">🌶️ 3. Khẩu vị & Cách chế biến:</span>
              <span class="fd-variant-badge optional">Tùy chọn</span>
            </div>
            <?php foreach ($attributes as $aIdx => $attr): 
              $attrName = $attr['name'] ?? 'Tùy chọn';
              $attrOpts = $attr['options'] ?? [];
            ?>
              <div class="fd-attr-group">
                <div class="fd-attr-name"><?= escapeHtml($attrName) ?>:</div>
                <div class="fd-attr-chips" data-attr-name="<?= escapeHtml($attrName) ?>">
                  <?php foreach ($attrOpts as $oIdx => $optVal): ?>
                    <button type="button" class="fd-chip-btn <?= $oIdx === 0 ? 'active' : '' ?>" 
                            data-attr="<?= escapeHtml($attrName) ?>" data-val="<?= escapeHtml($optVal) ?>"
                            onclick="selectAttributeChip(this)">
                      <?= escapeHtml($optVal) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- ========================================== -->
        <!-- 4. GHI CHÚ CHO BẾP                         -->
        <!-- ========================================== -->
        <div class="fd-variant-card">
          <div class="fd-variant-head">
            <span class="fd-variant-title">📝 Ghi chú cho quán (nếu có):</span>
          </div>
          <input type="text" id="fd-order-note" class="fd-note-input" 
                 placeholder="Ví dụ: Để riêng sốt, không hành, làm cay ít...">
        </div>

        <!-- Tóm tắt chi tiết giá lựa chọn -->
        <div class="fd-summary-strip" id="fd-summary-strip">
          <span>Đơn giá phần ăn: <b id="fd-unit-calc"><?= formatVND($currentPrice) ?></b></span>
          <span>Số lượng: <b id="fd-qty-calc">1</b></span>
        </div>

        <!-- Thao tác mua hàng & Số lượng -->
        <div style="display:flex; align-items:center; gap:1.25rem; margin-top:1.5rem; flex-wrap:wrap;">
          <?php if ($isManager): ?>
            <div style="flex:1; padding:1rem 1.25rem; background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; border-radius:var(--radius-md); font-weight:700; font-size:0.95rem;">
              👑 <span>Tài khoản Quản trị viên (Admin) chỉ dùng để quản trị hệ thống.</span>
            </div>
          <?php else: ?>
            <div class="quantity-control">
              <button type="button" class="qty-btn" id="btn-qty-minus" onclick="updateQty(-1)">-</button>
              <input type="text" id="detail-qty-input" class="qty-input" value="1" readonly>
              <button type="button" class="qty-btn" id="btn-qty-plus" onclick="updateQty(1)">+</button>
            </div>

            <button type="button" class="btn btn-outline btn-lg" id="btn-add-to-cart" onclick="handleAddToCart(false)">
              🛒 Thêm Vào Giỏ Hàng
            </button>

            <button type="button" class="btn btn-primary btn-lg" id="btn-buy-now" onclick="handleAddToCart(true)">
              ⚡ Mua Ngay
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ======================================================== -->
  <!-- ĐÁNH GIÁ KHÁCH HÀNG CHO MÓN ĂN NÀY                      -->
  <!-- ======================================================== -->
  <section id="product-reviews-section" class="pr-section" aria-label="Đánh giá khách hàng">
    <div class="pr-header-top">
      <div>
        <h2 class="pr-section-title">⭐ Đánh Giá Từ Khách Hàng</h2>
        <p class="pr-section-sub">Cảm nhận thực tế từ khách hàng đã thưởng thức <strong><?= escapeHtml($product['name']) ?></strong></p>
      </div>
    </div>

    <!-- Tổng quan điểm đánh giá & phân bổ sao -->
    <div class="pr-overview-box">
      <div class="pr-score-col">
        <div>
          <span class="pr-score-num" id="pr-avg-score"><?= $reviewSummary['total'] > 0 ? number_format($reviewSummary['avg'], 1) : '0.0' ?></span>
          <span class="pr-score-max">/ 5</span>
        </div>
        <div class="pr-score-stars" id="pr-avg-stars">
          <?php
            $fullStars = (int)round($reviewSummary['avg']);
            echo str_repeat('★', $fullStars) . str_repeat('☆', max(0, 5 - $fullStars));
          ?>
        </div>
        <div class="pr-score-count" id="pr-total-count">
          <?= $reviewSummary['total'] > 0 ? ($reviewSummary['total'] . ' lượt đánh giá') : 'Chưa có đánh giá nào' ?>
        </div>
        <div style="font-size:0.78rem; color:#059669; font-weight:700; margin-top:0.25rem;" id="pr-verified-count">
          <?= $reviewSummary['verified_count'] > 0 ? ('✓ ' . $reviewSummary['verified_count'] . ' đã mua hàng') : '' ?>
        </div>
      </div>

      <div class="pr-bars-col" id="pr-breakdown-bars">
        <?php foreach ($reviewSummary['breakdown'] as $b): ?>
          <div class="pr-bar-row" onclick="prSetRatingFilter(<?= (int)$b['star'] ?>)" title="Lọc theo <?= (int)$b['star'] ?> sao">
            <span class="pr-bar-label"><?= (int)$b['star'] ?> ★</span>
            <div class="pr-bar-track">
              <div class="pr-bar-fill" style="width: <?= (int)$b['percent'] ?>%;"></div>
            </div>
            <span class="pr-bar-pct"><?= (int)$b['percent'] ?>% (<?= (int)$b['count'] ?>)</span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="pr-cta-col">
        <?php if ($isStaffOrAdmin): ?>
          <div style="font-size:0.86rem; color:#9a3412; font-weight:700; line-height:1.5;">
            👑 Bạn đang xem với vai trò Quản trị viên / Nhân viên.<br>
            <span style="font-weight:500; color:#7c2d12;">Bạn có thể phản hồi hoặc ẩn/hiện đánh giá trực tiếp ở danh sách bên dưới.</span>
          </div>
        <?php elseif (!empty($currentUser['id'])): ?>
          <?php if ($purchaseInfo['purchased']): ?>
            <span class="pr-verified-note">✓ Bạn đã mua món ăn này</span>
          <?php endif; ?>
          <button type="button" class="btn btn-primary" id="btn-toggle-pr-form" onclick="prToggleForm()" style="width:100%; font-weight:800; border-radius:12px;">
            <?= $myReview ? '✏️ Chỉnh Sửa Đánh Giá' : '✍️ Viết Đánh Giá Ngay' ?>
          </button>
          <span style="font-size:0.78rem; color:#7c2d12;">Chia sẻ trải nghiệm giúp thực khách khác dễ lựa chọn</span>
        <?php else: ?>
          <p style="margin:0; font-size:0.86rem; color:#7c2d12; font-weight:600;">Bạn đã thưởng thức món này?</p>
          <a href="<?= getUrl('login.php?redirect=' . urlencode(getUrl('food-detail.php?id=' . $productIdInt . '#product-reviews-section'))) ?>" class="btn btn-primary" style="width:100%; font-weight:800; border-radius:12px; text-decoration:none;">
            🔑 Đăng Nhập Để Đánh Giá
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Form viết / sửa đánh giá -->
    <?php if (!empty($currentUser['id']) && !$isStaffOrAdmin): ?>
      <div class="pr-form-card" id="pr-form-card" style="display:none;">
        <div class="pr-form-header">
          <h3 style="margin:0; font-size:1.1rem; font-weight:800; color:#0f172a;" id="pr-form-heading">
            <?= $myReview ? 'Cập nhật đánh giá của bạn' : 'Đánh giá món: ' . escapeHtml($product['name']) ?>
          </h3>
          <button type="button" onclick="prToggleForm(false)" style="background:none; border:none; font-size:1.25rem; color:#64748b; cursor:pointer;" aria-label="Đóng">✕</button>
        </div>

        <form id="pr-submit-form" onsubmit="prHandleSubmit(event)">
          <div style="margin-bottom:0.85rem;">
            <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">Chọn mức độ hài lòng của bạn:</label>
            <div style="display:flex; align-items:center; gap:0.85rem; flex-wrap:wrap;">
              <div class="pr-star-picker" id="pr-star-picker">
                <?php $initStar = $myReview ? (int)$myReview['rating'] : 5; ?>
                <?php for ($s = 1; $s <= 5; $s++): ?>
                  <button type="button" class="pr-star-btn <?= $s <= $initStar ? 'active' : '' ?>" data-star="<?= $s ?>" onclick="prSelectStar(<?= $s ?>)" onmouseover="prHoverStar(<?= $s ?>)" onmouseout="prHoverStar(0)">★</button>
                <?php endfor; ?>
              </div>
              <span id="pr-star-label" style="font-size:0.9rem; font-weight:800; color:#ea580c;">Tuyệt vời (5/5 sao)</span>
            </div>
            <input type="hidden" id="pr-rating-val" value="<?= $initStar ?>">
          </div>

          <div class="pr-quick-tags">
            <button type="button" class="pr-quick-chip" onclick="prAppendQuickTag('Món ăn nóng hổi, rất ngon!')">🔥 Nóng hổi, rất ngon</button>
            <button type="button" class="pr-quick-chip" onclick="prAppendQuickTag('Giao hàng siêu nhanh, đóng gói cẩn thận.')">🛵 Giao nhanh, gói kỹ</button>
            <button type="button" class="pr-quick-chip" onclick="prAppendQuickTag('Khẩu phần đầy đặn, vừa miệng.')">🍽️ Đầy đặn, vừa miệng</button>
            <button type="button" class="pr-quick-chip" onclick="prAppendQuickTag('Giá cả hợp lý, sẽ tiếp tục ủng hộ!')">💯 Đáng tiền, sẽ ủng hộ</button>
          </div>

          <div style="margin-bottom:0.85rem;">
            <label for="pr-variant-input" style="display:block; font-size:0.82rem; font-weight:700; color:#475569; margin-bottom:0.3rem;">Khẩu vị / Kích cỡ đã dùng (tùy chọn):</label>
            <input type="text" id="pr-variant-input" class="fd-note-input" maxlength="250"
                   value="<?= escapeHtml($myReview['variant_info'] ?? ($purchaseInfo['variant_info'] ?? '')) ?>"
                   placeholder="Ví dụ: Cỡ Lớn, thêm phô mai, ít cay...">
          </div>

          <div style="margin-bottom:1rem;">
            <label for="pr-comment-input" style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">Nhận xét chi tiết:</label>
            <textarea id="pr-comment-input" class="pr-textarea" maxlength="2000" placeholder="Chia sẻ hương vị món ăn, chất lượng đóng gói, thái độ giao hàng..."><?= escapeHtml($myReview['comment'] ?? '') ?></textarea>
          </div>

          <div style="display:flex; justify-content:flex-end; gap:0.65rem;">
            <button type="button" class="btn btn-outline btn-sm" onclick="prToggleForm(false)">Hủy bỏ</button>
            <button type="submit" class="btn btn-primary btn-sm" id="pr-submit-btn">
              <?= $myReview ? '💾 Lưu Cập Nhật' : '🚀 Gửi Đánh Giá' ?>
            </button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <!-- Thanh lọc & sắp xếp -->
    <div class="pr-toolbar">
      <div class="pr-filter-pills" id="pr-filter-pills">
        <button type="button" class="pr-filter-btn active" data-filter="all" onclick="prChangeFilter('all', this)">Tất cả (<span id="pr-cnt-all"><?= (int)$reviewSummary['total'] ?></span>)</button>
        <button type="button" class="pr-filter-btn" data-filter="has_comment" onclick="prChangeFilter('has_comment', this)">Có bình luận (<span id="pr-cnt-comment"><?= (int)$reviewSummary['with_comment_count'] ?></span>)</button>
        <button type="button" class="pr-filter-btn" data-filter="verified" onclick="prChangeFilter('verified', this)">✓ Đã mua (<span id="pr-cnt-verified"><?= (int)$reviewSummary['verified_count'] ?></span>)</button>
        <button type="button" class="pr-filter-btn" data-filter="5" onclick="prChangeFilter('5', this)">5 ★</button>
        <button type="button" class="pr-filter-btn" data-filter="4" onclick="prChangeFilter('4', this)">4 ★</button>
        <button type="button" class="pr-filter-btn" data-filter="3" onclick="prChangeFilter('3', this)">3 ★</button>
        <button type="button" class="pr-filter-btn" data-filter="2" onclick="prChangeFilter('2', this)">2 ★</button>
        <button type="button" class="pr-filter-btn" data-filter="1" onclick="prChangeFilter('1', this)">1 ★</button>
      </div>

      <select id="pr-sort-select" class="pr-sort-select" onchange="prChangeSort(this.value)" aria-label="Sắp xếp đánh giá">
        <option value="newest">Mới nhất</option>
        <option value="helpful">Hữu ích nhất</option>
        <option value="highest">Sao cao đến thấp</option>
        <option value="lowest">Sao thấp đến cao</option>
      </select>
    </div>

    <!-- Danh sách nhận xét -->
    <div class="pr-list" id="pr-reviews-list"></div>

    <div id="pr-load-more-wrap" style="text-align:center; margin-top:1.25rem; <?= $initialHasMore ? '' : 'display:none;' ?>">
      <button type="button" class="btn btn-outline btn-sm" id="pr-load-more-btn" onclick="prLoadMore()">Xem thêm đánh giá ▾</button>
    </div>
  </section>

  <!-- Món ăn liên quan -->
  <?php if (!empty($relatedFoods)): ?>
  <section style="margin-top:4rem; margin-bottom:4rem;">
    <h3 style="font-size:1.35rem; font-weight:800; color:var(--text-main); margin-bottom:1.5rem;">
      Món Ăn Liên Quan Được Yêu Thích
    </h3>
    <div class="grid grid-cols-4" id="related-foods-container">
      <?php foreach ($relatedFoods as $rf): ?>
        <?= renderProductCard($rf) ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<script>
let qty = 1;
const basePrice = <?= (float)$currentPrice ?>;
const productId = <?= (int)$product['id'] ?>;

// Trạng thái biến thể đã chọn
let selectedSize = null;
<?php if (!empty($sizes)): ?>
  // Khởi tạo size mặc định
  <?php 
    $defSize = $sizes[0];
    foreach ($sizes as $s) {
      if (!empty($s['is_default'])) { $defSize = $s; break; }
    }
  ?>
  selectedSize = {
    name: <?= json_encode($defSize['name']) ?>,
    price: <?= (float)($defSize['price'] ?? 0) ?>
  };
<?php endif; ?>

function selectSize(idx, name, price) {
  selectedSize = { name, price: parseFloat(price) || 0 };
  
  // Update UI classes
  document.querySelectorAll('.fd-size-item').forEach((el, i) => {
    el.classList.toggle('selected', i === idx);
    const radio = el.querySelector('.fd-size-radio');
    if (radio) radio.checked = (i === idx);
  });

  calculateDetailTotal();
}

function toggleTopping(checkbox, itemId) {
  const itemEl = document.getElementById(itemId);
  if (itemEl) {
    itemEl.classList.toggle('selected', checkbox.checked);
  }
  calculateDetailTotal();
}

function selectAttributeChip(btn) {
  const container = btn.closest('.fd-attr-chips');
  if (container) {
    container.querySelectorAll('.fd-chip-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  }
}

function updateQty(delta) {
  qty = Math.max(1, qty + delta);
  document.getElementById('detail-qty-input').value = qty;
  calculateDetailTotal();
}

function calculateDetailTotal() {
  const sizeExtra = selectedSize ? (selectedSize.price || 0) : 0;
  
  let toppingsTotal = 0;
  document.querySelectorAll('.fd-topping-cb:checked').forEach(cb => {
    toppingsTotal += (parseFloat(cb.dataset.price) || 0);
  });

  const unitPrice = basePrice + sizeExtra + toppingsTotal;
  const grandTotal = unitPrice * qty;

  const display = document.getElementById('detail-total-price');
  if (display) {
    display.textContent = grandTotal.toLocaleString('vi-VN') + ' đ';
  }

  const unitCalc = document.getElementById('fd-unit-calc');
  if (unitCalc) {
    let breakdown = unitPrice.toLocaleString('vi-VN') + ' đ';
    if (sizeExtra > 0 || toppingsTotal > 0) {
      breakdown += ` (${basePrice.toLocaleString('vi-VN')}đ`;
      if (sizeExtra > 0) breakdown += ` + Cỡ ${sizeExtra.toLocaleString('vi-VN')}đ`;
      if (toppingsTotal > 0) breakdown += ` + Topping ${toppingsTotal.toLocaleString('vi-VN')}đ`;
      breakdown += ')';
    }
    unitCalc.textContent = breakdown;
  }

  const qtyCalc = document.getElementById('fd-qty-calc');
  if (qtyCalc) {
    qtyCalc.textContent = qty;
  }
}

async function handleAddToCart(isBuyNow = false) {
  try {
    // 1. Toppings
    const selectedToppings = [];
    document.querySelectorAll('.fd-topping-cb:checked').forEach(cb => {
      selectedToppings.push({
        name: cb.dataset.name,
        price: parseFloat(cb.dataset.price) || 0
      });
    });

    // 2. Attributes (Khẩu vị, đường, đá, v.v.)
    const selectedAttributes = [];
    document.querySelectorAll('.fd-attr-chips').forEach(group => {
      const activeBtn = group.querySelector('.fd-chip-btn.active');
      if (activeBtn) {
        selectedAttributes.push({
          name: activeBtn.dataset.attr,
          value: activeBtn.dataset.val
        });
      }
    });

    // 3. Ghi chú
    const note = document.getElementById('fd-order-note')?.value.trim() || '';

    const payload = {
      action: 'add',
      productId: productId,
      qty: qty,
      size: selectedSize,
      toppings: selectedToppings,
      attributes: selectedAttributes,
      note: note
    };

    const res = await fetch('<?= getUrl('api/cart.php') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await res.json();
    if (result.success) {
      if (isBuyNow) {
        window.location.href = '<?= getUrl('cart.php') ?>';
      } else {
        let topText = '';
        if (selectedSize && selectedSize.price > 0) topText += ` (Cỡ ${selectedSize.name})`;
        if (selectedToppings.length) topText += ` + ${selectedToppings.length} topping`;

        if (window.UIComponents?.showToast) {
          window.UIComponents.showToast(`Đã thêm ${qty} phần ${topText} vào giỏ hàng!`, 'success');
        } else {
          alert(`Đã thêm ${qty} phần ${topText} vào giỏ hàng!`);
        }

        const badge = document.getElementById('nav-cart-badge');
        if (badge) {
          badge.textContent = result.data.totalCount;
          badge.style.display = result.data.totalCount > 0 ? 'flex' : 'none';
        }
      }
    } else {
      alert(result.message || 'Không thể thêm vào giỏ hàng.');
    }
  } catch (err) {
    console.error(err);
    alert('Có lỗi xảy ra khi thêm vào giỏ hàng.');
  }
}

// Khởi chạy tính giá lần đầu
calculateDetailTotal();

// ==========================================================
// HỆ THỐNG ĐÁNH GIÁ KHÁCH HÀNG CHO MỖI SẢN PHẨM
// ==========================================================
const PR_API_URL = '<?= getUrl('api/product-reviews.php') ?>';
const PR_CURRENT_USER_ID = <?= (int)($currentUser['id'] ?? 0) ?>;
const PR_IS_ADMIN_OR_STAFF = <?= $isStaffOrAdmin ? 'true' : 'false' ?>;
const PR_STAR_LABELS = {
  1: 'Rất tệ (1/5 sao)',
  2: 'Không hài lòng (2/5 sao)',
  3: 'Bình thường (3/5 sao)',
  4: 'Hài lòng (4/5 sao)',
  5: 'Tuyệt vời (5/5 sao)'
};

let prReviewsData = <?= json_encode($initialReviews, JSON_UNESCAPED_UNICODE) ?>;
let prSummaryData = <?= json_encode($reviewSummary, JSON_UNESCAPED_UNICODE) ?>;
let prMyReview    = <?= json_encode($myReview, JSON_UNESCAPED_UNICODE) ?>;
let prFilter      = 'all';
let prSort        = 'newest';
let prOffset      = 0;
const prLimit     = <?= (int)$initialLimit ?>;

function prEsc(str) {
  return String(str ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[c]);
}

function prNotify(msg, type = 'success') {
  if (window.UIComponents?.showToast) {
    window.UIComponents.showToast(msg, type);
  } else {
    alert(msg);
  }
}

function prUpdateSummaryUI(summary) {
  if (!summary) return;
  prSummaryData = summary;
  const total = Number(summary.total || 0);
  const avg = Number(summary.avg || 0);

  const avgEl = document.getElementById('pr-avg-score');
  if (avgEl) avgEl.textContent = total > 0 ? avg.toFixed(1) : '0.0';

  const starsEl = document.getElementById('pr-avg-stars');
  if (starsEl) {
    const rounded = Math.round(avg);
    starsEl.textContent = '★'.repeat(rounded) + '☆'.repeat(Math.max(0, 5 - rounded));
  }

  const countEl = document.getElementById('pr-total-count');
  if (countEl) countEl.textContent = total > 0 ? `${total} lượt đánh giá` : 'Chưa có đánh giá nào';

  const verEl = document.getElementById('pr-verified-count');
  if (verEl) verEl.textContent = summary.verified_count > 0 ? `✓ ${summary.verified_count} đã mua hàng` : '';

  const topLink = document.getElementById('fd-top-rating-link');
  if (topLink) {
    topLink.textContent = total > 0 ? `★ ${avg.toFixed(1)} (${total} đánh giá)` : '★ Chưa có đánh giá';
  }

  const cntAll = document.getElementById('pr-cnt-all');
  const cntComment = document.getElementById('pr-cnt-comment');
  const cntVerified = document.getElementById('pr-cnt-verified');
  if (cntAll) cntAll.textContent = total;
  if (cntComment) cntComment.textContent = summary.with_comment_count || 0;
  if (cntVerified) cntVerified.textContent = summary.verified_count || 0;

  const barsEl = document.getElementById('pr-breakdown-bars');
  if (barsEl && Array.isArray(summary.breakdown)) {
    barsEl.innerHTML = summary.breakdown.map(b => `
      <div class="pr-bar-row" onclick="prSetRatingFilter(${b.star})" title="Lọc theo ${b.star} sao">
        <span class="pr-bar-label">${b.star} ★</span>
        <div class="pr-bar-track">
          <div class="pr-bar-fill" style="width: ${Number(b.percent || 0)}%;"></div>
        </div>
        <span class="pr-bar-pct">${Number(b.percent || 0)}% (${Number(b.count || 0)})</span>
      </div>
    `).join('');
  }
}

function prRenderList(append = false) {
  const container = document.getElementById('pr-reviews-list');
  if (!container) return;

  if (!append && (!prReviewsData || prReviewsData.length === 0)) {
    container.innerHTML = `
      <div class="pr-empty-state">
        <div style="font-size:2.3rem; margin-bottom:0.4rem;">💬</div>
        <div style="font-weight:700; color:#334155; font-size:1rem; margin-bottom:0.25rem;">
          ${prFilter === 'all' ? 'Chưa có đánh giá nào cho món ăn này' : 'Không tìm thấy đánh giá phù hợp bộ lọc'}
        </div>
        <div style="font-size:0.86rem;">
          ${prFilter === 'all' ? 'Hãy thưởng thức và là người đầu tiên chia sẻ cảm nhận của bạn nhé!' : 'Thử chuyển sang bộ lọc "Tất cả" để xem các nhận xét khác.'}
        </div>
      </div>
    `;
    return;
  }

  const html = prReviewsData.map(r => {
    const initial = (r.user_name || 'K').trim().charAt(0).toUpperCase();
    const avatarHtml = r.user_avatar
      ? `<img src="${prEsc(r.user_avatar)}" alt="${prEsc(r.user_name)}" class="pr-avatar" onerror="this.style.display='none';">`
      : `<div class="pr-avatar-fallback">${prEsc(initial)}</div>`;

    const isMine = PR_CURRENT_USER_ID > 0 && Number(r.user_id) === PR_CURRENT_USER_ID;
    const canDelete = isMine || PR_IS_ADMIN_OR_STAFF;
    const starsStr = '★'.repeat(Math.max(1, Math.min(5, Number(r.rating || 5)))) + '☆'.repeat(Math.max(0, 5 - Number(r.rating || 5)));

    return `
      <article class="pr-card ${r.is_approved === false ? 'is-hidden-review' : ''}" id="pr-card-${r.id}">
        <div class="pr-card-top">
          <div class="pr-user-wrap">
            ${avatarHtml}
            <div>
              <div class="pr-user-name-row">
                <span class="pr-user-name">${prEsc(r.user_name)}</span>
                ${r.is_verified_purchase ? '<span class="pr-verified-badge">✓ Đã mua hàng</span>' : ''}
                ${isMine ? '<span class="pr-mine-badge">Đánh giá của bạn</span>' : ''}
                ${r.is_approved === false ? '<span class="pr-mine-badge" style="background:#fee2e2;color:#dc2626;">Đang ẩn</span>' : ''}
              </div>
              <div class="pr-meta-sub">
                <span class="pr-card-stars">${starsStr}</span>
                <span>•</span>
                <span>${prEsc(r.created_at_formatted || '')}</span>
              </div>
            </div>
          </div>
        </div>

        ${r.variant_info ? `<div class="pr-variant-chip">🍽️ Đã chọn: ${prEsc(r.variant_info)}</div>` : ''}

        <p class="pr-comment-body">${r.comment ? prEsc(r.comment) : '<em style="color:#94a3b8;">Khách hàng đã chấm sao mà không để lại bình luận.</em>'}</p>

        ${r.reply_content ? `
          <div class="pr-reply-box">
            <div class="pr-reply-head">
              <span>🏪 Phản hồi từ FASTFOOD</span>
              ${r.replied_at_formatted ? `<span style="font-weight:500; color:#9a3412;">${prEsc(r.replied_at_formatted)}</span>` : ''}
            </div>
            <p class="pr-reply-text">${prEsc(r.reply_content)}</p>
          </div>
        ` : ''}

        <div class="pr-card-actions">
          <button type="button" class="pr-helpful-btn ${r.is_helpful_by_me ? 'active' : ''}" onclick="prToggleHelpful(${r.id}, this)">
            <span>👍</span>
            <span>Hữu ích (<span class="pr-helpful-num">${Number(r.helpful_count || 0)}</span>)</span>
          </button>

          <div class="pr-owner-actions">
            ${isMine && !PR_IS_ADMIN_OR_STAFF ? `
              <button type="button" class="pr-mini-btn" onclick="prOpenEditMyReview()">✏️ Sửa</button>
            ` : ''}
            ${PR_IS_ADMIN_OR_STAFF ? `
              <button type="button" class="pr-mini-btn" onclick="prToggleReplyBox(${r.id})">💬 Phản hồi</button>
              <button type="button" class="pr-mini-btn" onclick="prToggleApprove(${r.id})">${r.is_approved === false ? '👁️ Hiện' : '🙈 Ẩn'}</button>
            ` : ''}
            ${canDelete ? `
              <button type="button" class="pr-mini-btn danger" onclick="prDeleteReview(${r.id})">🗑️ Xóa</button>
            ` : ''}
          </div>
        </div>

        ${PR_IS_ADMIN_OR_STAFF ? `
          <div class="pr-inline-reply-form" id="pr-reply-form-${r.id}" style="display:none;">
            <input type="text" class="fd-note-input" id="pr-reply-input-${r.id}" value="${prEsc(r.reply_content || '')}" placeholder="Nhập phản hồi của quán tới khách hàng...">
            <button type="button" class="btn btn-primary btn-sm" onclick="prSendReply(${r.id})">Gửi</button>
          </div>
        ` : ''}
      </article>
    `;
  }).join('');

  container.innerHTML = html;
}

async function prFetchReviews(append = false) {
  if (!append) prOffset = 0;
  const params = new URLSearchParams({
    product_id: String(productId),
    sort: prSort,
    limit: String(prLimit),
    offset: String(prOffset),
    include_hidden: PR_IS_ADMIN_OR_STAFF ? '1' : '0'
  });

  if (['1', '2', '3', '4', '5'].includes(prFilter)) {
    params.set('rating', prFilter);
  } else if (prFilter === 'verified') {
    params.set('verified', '1');
  } else if (prFilter === 'has_comment') {
    params.set('has_comment', '1');
  }

  try {
    const res = await fetch(`${PR_API_URL}?${params.toString()}`);
    const json = await res.json();
    if (!json.success || !json.data) return;

    if (append) {
      prReviewsData = prReviewsData.concat(json.data.reviews || []);
    } else {
      prReviewsData = json.data.reviews || [];
    }
    prMyReview = json.data.my_review || null;
    prUpdateSummaryUI(json.data.summary);
    prRenderList(false);

    const loadMoreWrap = document.getElementById('pr-load-more-wrap');
    if (loadMoreWrap) {
      loadMoreWrap.style.display = json.data.has_more ? 'block' : 'none';
    }
  } catch (err) {
    console.error('Lỗi tải đánh giá:', err);
  }
}

function prChangeFilter(filterVal, btnEl) {
  prFilter = filterVal;
  document.querySelectorAll('.pr-filter-btn').forEach(b => b.classList.remove('active'));
  if (btnEl) {
    btnEl.classList.add('active');
  } else {
    const targetBtn = document.querySelector(`.pr-filter-btn[data-filter="${filterVal}"]`);
    if (targetBtn) targetBtn.classList.add('active');
  }
  prFetchReviews(false);
}

function prSetRatingFilter(star) {
  prChangeFilter(String(star), null);
  document.getElementById('pr-filter-pills')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function prChangeSort(sortVal) {
  prSort = sortVal;
  prFetchReviews(false);
}

function prLoadMore() {
  prOffset += prLimit;
  prFetchReviews(true);
}

function prToggleForm(forceState) {
  const card = document.getElementById('pr-form-card');
  if (!card) return;
  const shouldOpen = typeof forceState === 'boolean' ? forceState : (card.style.display === 'none' || !card.style.display);
  card.style.display = shouldOpen ? 'block' : 'none';
  if (shouldOpen) {
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}

function prOpenEditMyReview() {
  if (prMyReview) {
    prSelectStar(prMyReview.rating || 5);
    const commentEl = document.getElementById('pr-comment-input');
    const varEl = document.getElementById('pr-variant-input');
    if (commentEl) commentEl.value = prMyReview.comment || '';
    if (varEl && prMyReview.variant_info) varEl.value = prMyReview.variant_info;
  }
  prToggleForm(true);
}

function prSelectStar(star) {
  const val = Math.max(1, Math.min(5, Number(star) || 5));
  const input = document.getElementById('pr-rating-val');
  const label = document.getElementById('pr-star-label');
  if (input) input.value = String(val);
  if (label) label.textContent = PR_STAR_LABELS[val] || '';
  document.querySelectorAll('#pr-star-picker .pr-star-btn').forEach(btn => {
    btn.classList.toggle('active', Number(btn.dataset.star) <= val);
  });
}

function prHoverStar(star) {
  const activeVal = Number(document.getElementById('pr-rating-val')?.value || 5);
  const showVal = star > 0 ? star : activeVal;
  document.querySelectorAll('#pr-star-picker .pr-star-btn').forEach(btn => {
    btn.classList.toggle('active', Number(btn.dataset.star) <= showVal);
  });
}

function prAppendQuickTag(text) {
  const ta = document.getElementById('pr-comment-input');
  if (!ta) return;
  const cur = ta.value.trim();
  if (!cur) {
    ta.value = text;
  } else if (!cur.includes(text)) {
    ta.value = cur + ' ' + text;
  }
  ta.focus();
}

async function prHandleSubmit(e) {
  e.preventDefault();
  const btn = document.getElementById('pr-submit-btn');
  const rating = Number(document.getElementById('pr-rating-val')?.value || 5);
  const comment = document.getElementById('pr-comment-input')?.value.trim() || '';
  const variantInfo = document.getElementById('pr-variant-input')?.value.trim() || '';

  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Đang gửi...';
  }

  try {
    const res = await fetch(PR_API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'submit',
        product_id: productId,
        rating,
        comment,
        variant_info: variantInfo
      })
    });
    const json = await res.json();
    if (json.success) {
      prNotify(json.message || 'Gửi đánh giá thành công!', 'success');
      prToggleForm(false);
      const toggleBtn = document.getElementById('btn-toggle-pr-form');
      if (toggleBtn) toggleBtn.textContent = '✏️ Chỉnh Sửa Đánh Giá';
      const head = document.getElementById('pr-form-heading');
      if (head) head.textContent = 'Cập nhật đánh giá của bạn';
      if (btn) btn.textContent = '💾 Lưu Cập Nhật';
      prChangeFilter('all');
    } else {
      prNotify(json.message || 'Không thể gửi đánh giá.', 'error');
    }
  } catch (err) {
    prNotify('Lỗi kết nối: ' + err.message, 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      if (btn.textContent === 'Đang gửi...') {
        btn.textContent = prMyReview ? '💾 Lưu Cập Nhật' : '🚀 Gửi Đánh Giá';
      }
    }
  }
}

async function prDeleteReview(reviewId) {
  if (!confirm('Bạn có chắc chắn muốn xóa đánh giá này không?')) return;
  try {
    const res = await fetch(PR_API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', review_id: reviewId })
    });
    const json = await res.json();
    if (json.success) {
      prNotify(json.message || 'Đã xóa đánh giá.', 'success');
      const toggleBtn = document.getElementById('btn-toggle-pr-form');
      if (toggleBtn && !PR_IS_ADMIN_OR_STAFF) toggleBtn.textContent = '✍️ Viết Đánh Giá Ngay';
      prFetchReviews(false);
    } else {
      prNotify(json.message || 'Không thể xóa đánh giá.', 'error');
    }
  } catch (err) {
    prNotify('Lỗi kết nối: ' + err.message, 'error');
  }
}

async function prToggleHelpful(reviewId, btnEl) {
  try {
    const res = await fetch(PR_API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'toggle_helpful', review_id: reviewId })
    });
    const json = await res.json();
    if (json.success && json.data && btnEl) {
      btnEl.classList.toggle('active', Boolean(json.data.is_helpful_by_me));
      const numEl = btnEl.querySelector('.pr-helpful-num');
      if (numEl) numEl.textContent = json.data.helpful_count;
    }
  } catch (err) {}
}

function prToggleReplyBox(reviewId) {
  const box = document.getElementById(`pr-reply-form-${reviewId}`);
  if (box) {
    box.style.display = (box.style.display === 'none' || !box.style.display) ? 'flex' : 'none';
  }
}

async function prSendReply(reviewId) {
  const input = document.getElementById(`pr-reply-input-${reviewId}`);
  const replyContent = input?.value.trim() || '';
  if (!replyContent) {
    prNotify('Vui lòng nhập nội dung phản hồi.', 'error');
    return;
  }
  try {
    const res = await fetch(PR_API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'reply', review_id: reviewId, reply_content: replyContent })
    });
    const json = await res.json();
    if (json.success) {
      prNotify(json.message || 'Đã gửi phản hồi!', 'success');
      prFetchReviews(false);
    } else {
      prNotify(json.message || 'Không thể phản hồi.', 'error');
    }
  } catch (err) {
    prNotify('Lỗi kết nối: ' + err.message, 'error');
  }
}

async function prToggleApprove(reviewId) {
  try {
    const res = await fetch(PR_API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'toggle_approve', review_id: reviewId })
    });
    const json = await res.json();
    if (json.success) {
      prNotify(json.message || 'Đã cập nhật hiển thị.', 'success');
      prFetchReviews(false);
    } else {
      prNotify(json.message || 'Không thể cập nhật.', 'error');
    }
  } catch (err) {
    prNotify('Lỗi kết nối: ' + err.message, 'error');
  }
}

// Render danh sách đánh giá ban đầu ngay khi tải trang
prRenderList(false);
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
