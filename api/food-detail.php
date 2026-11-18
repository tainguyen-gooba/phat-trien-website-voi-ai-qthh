<?php
/**
 * FASTFOOD - API Chi tiết món ăn & Toppings
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/helpers/response.php';

$idParam = trim($_GET['id'] ?? '');
if (!$idParam) {
    jsonError('Thiếu mã món ăn (id).', 400);
}

$numId = (int)preg_replace('/\D/', '', $idParam);

$db = getDB();
$stmt = $db->prepare("SELECT p.*, r.name AS restaurant_name, c.name AS category_name, c.slug AS category_slug 
                      FROM products p 
                      LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      WHERE p.id = ? OR p.product_code = ? LIMIT 1");
$stmt->execute([$numId, $idParam]);
$p = $stmt->fetch();

if (!$p) {
    jsonError('Không tìm thấy món ăn.', 404);
}

// Lấy toppings từ website_content nếu có
$toppings = [];
$wcRow = $db->query("SELECT content FROM website_content WHERE collection_name = 'products'")->fetch();
if ($wcRow && !empty($wcRow['content'])) {
    $wcProds = json_decode($wcRow['content'], true);
    if (is_array($wcProds)) {
        foreach ($wcProds as $item) {
            $itemNum = (int)preg_replace('/\D/', '', $item['id'] ?? '');
            if ($itemNum === (int)$p['id'] || (!empty($item['name']) && trim($item['name']) === trim($p['name']))) {
                if (!empty($item['toppings']) && is_array($item['toppings'])) {
                    $toppings = $item['toppings'];
                }
                break;
            }
        }
    }
}

// Fallback topping theo danh mục nếu chưa có topping
if (empty($toppings)) {
    $catSlug = strtolower($p['category_slug'] ?? '');
    if (strpos($catSlug, 'burger') !== false || strpos($catSlug, 'ga-ran') !== false) {
        $toppings = [
            ['name' => 'Thêm phô mai lát Cheddar', 'price' => 10000],
            ['name' => 'Thịt ba chỉ xông khói (Bacon)', 'price' => 15000],
            ['name' => 'Trứng ốp la lòng đào', 'price' => 8000],
        ];
    } elseif (strpos($catSlug, 'tra-sua') !== false || strpos($catSlug, 'do-uong') !== false || strpos($catSlug, 'ca-phe') !== false) {
        $toppings = [
            ['name' => 'Trân châu đường đen dẻo', 'price' => 6000],
            ['name' => 'Thạch nha đam thanh mát', 'price' => 5000],
            ['name' => 'Kem cheese béo mặn thơm ngậy', 'price' => 10000],
            ['name' => 'Pudding trứng béo mịn', 'price' => 7000],
        ];
    } elseif (strpos($catSlug, 'pizza') !== false || strpos($catSlug, 'my-y') !== false) {
        $toppings = [
            ['name' => 'Gấp đôi phô mai Mozzarella', 'price' => 15000],
            ['name' => 'Thêm xúc xích Pepperoni cay', 'price' => 20000],
            ['name' => 'Viền phô mai xúc xích', 'price' => 25000],
        ];
    } else {
        $toppings = [
            ['name' => 'Thêm trứng ốp la lòng đào', 'price' => 8000],
            ['name' => 'Thêm chả lụa / chả trứng', 'price' => 10000],
            ['name' => 'Canh rong biển súp ấm', 'price' => 12000],
        ];
    }
}

$hasDiscount = !empty($p['discount_price']) && (float)$p['discount_price'] > 0 && (float)$p['discount_price'] < (float)$p['base_price'];
$price = $hasDiscount ? (float)$p['discount_price'] : (float)$p['base_price'];
$oldPrice = $hasDiscount ? (float)$p['base_price'] : 0;

$rawImg = $p['image_url'] ?: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80';
if (strpos($rawImg, 'http') !== 0 && strpos($rawImg, '/') !== 0) {
    $rawImg = getAssetUrl($rawImg);
}

jsonSuccess('Lấy thông tin món thành công', [
    'id'             => (int)$p['id'],
    'name'           => $p['name'],
    'description'    => $p['description'] ?: 'Món ăn chuẩn vị thơm ngon, chế biến nóng hổi ngay khi nhận đơn.',
    'price'          => $price,
    'oldPrice'       => $oldPrice,
    'image'          => $rawImg,
    'restaurantName' => $p['restaurant_name'] ?: 'FASTFOOD Partner',
    'categoryName'   => $p['category_name'] ?: 'Món ngon',
    'prepTime'       => $p['prep_time_minutes'] ? $p['prep_time_minutes'] . ' phút' : '15-20 phút',
    'toppings'       => $toppings,
]);
