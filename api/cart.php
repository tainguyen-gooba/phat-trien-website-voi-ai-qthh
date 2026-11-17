<?php
/**
 * FASTFOOD - API Quản lý giỏ hàng (Cart Session)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/helpers/response.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Tính toán tóm tắt giỏ hàng
function getCartSummary() {
    $items = array_values($_SESSION['cart']);
    $totalCount = 0;
    $totalAmount = 0;

    foreach ($items as $item) {
        $qty = (int)($item['qty'] ?? 1);
        $price = (float)($item['price'] ?? 0);
        $totalCount += $qty;
        $totalAmount += $qty * $price;
    }

    return [
        'items'       => $items,
        'totalCount'  => $totalCount,
        'totalAmount' => $totalAmount,
    ];
}

if ($method === 'GET') {
    jsonSuccess('Lấy giỏ hàng thành công', getCartSummary());
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$action && isset($input['action'])) {
        $action = $input['action'];
    }

    $productId = (string)($input['productId'] ?? $_POST['productId'] ?? '');
    $qty       = max(1, (int)($input['qty'] ?? $_POST['qty'] ?? 1));

    switch ($action) {
        case 'add':
            if (!$productId) {
                jsonError('Thiếu mã sản phẩm (productId).');
            }

            $db = getDB();
            $stmt = $db->prepare("SELECT id, name, base_price, discount_price, image_url, is_available FROM products WHERE id = ? OR product_code = ? LIMIT 1");
            $stmt->execute([$productId, $productId]);
            $product = $stmt->fetch();

            if (!$product) {
                // Thử tìm theo id số
                $numericId = (int)preg_replace('/\D/', '', $productId);
                if ($numericId > 0) {
                    $stmt = $db->prepare("SELECT id, name, base_price, discount_price, image_url, is_available FROM products WHERE id = ? LIMIT 1");
                    $stmt->execute([$numericId]);
                    $product = $stmt->fetch();
                }
            }

            if (!$product) {
                jsonError('Không tìm thấy sản phẩm trong cơ sở dữ liệu.');
            }

            $idKey = (string)$product['id'];
            $price = !empty($product['discount_price']) && (float)$product['discount_price'] > 0
                ? (float)$product['discount_price']
                : (float)$product['base_price'];

            if (isset($_SESSION['cart'][$idKey])) {
                $_SESSION['cart'][$idKey]['qty'] += $qty;
            } else {
                $_SESSION['cart'][$idKey] = [
                    'id'       => $idKey,
                    'name'     => $product['name'],
                    'price'    => $price,
                    'image'    => $product['image_url'] ?? '',
                    'qty'      => $qty,
                ];
            }

            jsonSuccess('Đã thêm món vào giỏ hàng thành công!', getCartSummary());
            break;

        case 'update':
            if (!$productId || !isset($_SESSION['cart'][$productId])) {
                jsonError('Không tìm thấy món ăn trong giỏ hàng.');
            }
            $newQty = max(1, (int)($input['qty'] ?? $_POST['qty'] ?? 1));
            $_SESSION['cart'][$productId]['qty'] = $newQty;
            jsonSuccess('Đã cập nhật số lượng món.', getCartSummary());
            break;

        case 'remove':
            if (!$productId) {
                jsonError('Thiếu mã sản phẩm cần xóa.');
            }
            unset($_SESSION['cart'][$productId]);
            jsonSuccess('Đã xóa món khỏi giỏ hàng.', getCartSummary());
            break;

        case 'clear':
            $_SESSION['cart'] = [];
            jsonSuccess('Đã làm trống giỏ hàng.', getCartSummary());
            break;

        default:
            jsonError('Hành động không hợp lệ.');
    }
}

