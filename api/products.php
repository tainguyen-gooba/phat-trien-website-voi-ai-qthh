<?php
/**
 * FASTFOOD - RESTful API Sản Phẩm / Món Ăn Cho Frontend
 * Hỗ trợ tìm kiếm, phân trang, lọc theo danh mục, quán ăn, và lấy chi tiết món ăn
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/helpers/response.php';

try {
    $db = getDB();
} catch (Exception $e) {
    jsonError('Không thể kết nối cơ sở dữ liệu: ' . $e->getMessage(), 500);
}

// 1. Lấy chi tiết 1 món ăn nếu truyền ?id=
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
    $stmt = $db->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug, r.name AS restaurant_name, r.address AS restaurant_address 
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                          WHERE p.id = ? LIMIT 1");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) {
        jsonError('Không tìm thấy món ăn yêu cầu.', 404);
    }

    if (!empty($product['options']) && is_string($product['options'])) {
        $product['options'] = json_decode($product['options'], true);
    }

    $currentPrice = (float)($product['discount_price'] > 0 ? $product['discount_price'] : $product['base_price']);
    $product['current_price'] = $currentPrice;
    $product['price_formatted'] = formatVND($currentPrice);
    $product['original_price_formatted'] = formatVND((float)$product['base_price']);

    jsonSuccess('Lấy thông tin món ăn thành công', $product);
}

// 2. Lấy danh sách món ăn với bộ lọc tìm kiếm & phân trang
$search       = trim($_GET['search'] ?? $_GET['q'] ?? '');
$category     = trim($_GET['category'] ?? '');
$restaurantId = isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : 0;
$isFeatured   = isset($_GET['featured']) || isset($_GET['is_bestseller']) ? 1 : null;
$sort         = trim($_GET['sort'] ?? 'newest');
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = max(1, min(50, (int)($_GET['limit'] ?? 12)));
$offset       = ($page - 1) * $limit;

$whereClauses = ["p.is_available = 1"];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($category !== '') {
    if (is_numeric($category)) {
        $whereClauses[] = "p.category_id = ?";
        $params[] = (int)$category;
    } else {
        $whereClauses[] = "(c.slug = ? OR c.name LIKE ?)";
        $params[] = $category;
        $params[] = '%' . $category . '%';
    }
}

if ($restaurantId > 0) {
    $whereClauses[] = "p.restaurant_id = ?";
    $params[] = $restaurantId;
}

if ($isFeatured !== null) {
    $whereClauses[] = "p.is_featured = 1";
}

$whereSql = implode(' AND ', $whereClauses);

// Xử lý tiêu chí sắp xếp
$orderBy = "p.id DESC";
switch ($sort) {
    case 'price-asc':
    case 'price_asc':
        $orderBy = "COALESCE(NULLIF(p.discount_price, 0), p.base_price) ASC";
        break;
    case 'price-desc':
    case 'price_desc':
        $orderBy = "COALESCE(NULLIF(p.discount_price, 0), p.base_price) DESC";
        break;
    case 'rating':
    case 'rating_desc':
        $orderBy = "p.rating_avg DESC";
        break;
    case 'popular':
        $orderBy = "p.rating_count DESC, p.rating_avg DESC";
        break;
    case 'newest':
    default:
        $orderBy = "p.id DESC";
        break;
}

// Đếm tổng số lượng
$countStmt = $db->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Lấy danh sách sản phẩm
$sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, r.name AS restaurant_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN restaurants r ON p.restaurant_id = r.id 
        WHERE $whereSql 
        ORDER BY $orderBy 
        LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

foreach ($products as &$item) {
    if (!empty($item['options']) && is_string($item['options'])) {
        $item['options'] = json_decode($item['options'], true);
    }
    $curPrice = (float)($item['discount_price'] > 0 ? $item['discount_price'] : $item['base_price']);
    $item['current_price'] = $curPrice;
    $item['price_formatted'] = formatVND($curPrice);
    $item['original_price_formatted'] = formatVND((float)$item['base_price']);
}
unset($item);

jsonSuccess('Lấy danh sách sản phẩm thành công', [
    'products'    => $products,
    'total'       => $total,
    'page'        => $page,
    'limit'       => $limit,
    'totalPages'  => (int)ceil($total / $limit)
]);

