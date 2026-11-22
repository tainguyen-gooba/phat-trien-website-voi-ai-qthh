<?php
/**
 * FASTFOOD - API Lưu & Lấy Món Ăn Yêu Thích (Favorites)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
$action = $body['action'] ?? $_GET['action'] ?? 'get_items';

// Lấy danh sách ID sản phẩm
$rawIds = $body['ids'] ?? $_GET['ids'] ?? '';
$ids = [];
if (is_array($rawIds)) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $rawIds))));
} elseif (is_string($rawIds) && trim($rawIds) !== '') {
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $rawIds)))));
}

switch ($action) {
    case 'get_items':
        if (empty($ids)) {
            jsonSuccess('Danh sách yêu thích trống', ['products' => [], 'count' => 0]);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT p.*, c.name AS category_name, r.name AS restaurant_name 
                              FROM products p 
                              LEFT JOIN categories c ON p.category_id = c.id 
                              LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                              WHERE p.id IN ($placeholders) AND p.is_available = 1");
        $stmt->execute($ids);
        $products = $stmt->fetchAll();

        foreach ($products as &$item) {
            $curPrice = (float)($item['discount_price'] > 0 ? $item['discount_price'] : $item['base_price']);
            $item['current_price'] = $curPrice;
            $item['price_formatted'] = formatVND($curPrice);
            $item['original_price_formatted'] = formatVND((float)$item['base_price']);
            $rawImg = $item['image_url'] ?? '';
            if ($rawImg && !preg_match('#^(https?://|//)#i', $rawImg)) {
                $rawImg = getAssetUrl($rawImg);
            }
            $item['image_url_full'] = $rawImg;
            $item['detail_url'] = getUrl('food-detail.php?id=' . $item['id']);
        }
        unset($item);

        jsonSuccess('Lấy danh sách món ăn yêu thích thành công', [
            'products' => $products,
            'count'    => count($products)
        ]);
        break;

    case 'toggle':
        $prodId = (int)($body['productId'] ?? $_POST['productId'] ?? 0);
        if ($prodId <= 0) {
            jsonError('Thiếu ID món ăn');
        }

        if (!isset($_SESSION['user_favorites']) || !is_array($_SESSION['user_favorites'])) {
            $_SESSION['user_favorites'] = [];
        }

        $key = array_search($prodId, $_SESSION['user_favorites']);
        $isLiked = false;
        if ($key !== false) {
            unset($_SESSION['user_favorites'][$key]);
            $_SESSION['user_favorites'] = array_values($_SESSION['user_favorites']);
            $isLiked = false;
        } else {
            $_SESSION['user_favorites'][] = $prodId;
            $isLiked = true;
        }

        jsonSuccess($isLiked ? 'Đã thêm vào yêu thích' : 'Đã xóa khỏi yêu thích', [
            'productId' => $prodId,
            'isLiked'   => $isLiked,
            'count'     => count($_SESSION['user_favorites'])
        ]);
        break;

    default:
        jsonError('Hành động không hợp lệ');
}

