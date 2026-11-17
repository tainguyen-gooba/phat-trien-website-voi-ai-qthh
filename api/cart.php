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
            $basePrice = !empty($product['discount_price']) && (float)$product['discount_price'] > 0
                ? (float)$product['discount_price']
                : (float)$product['base_price'];

            // Xử lý toppings đính kèm
            $toppings = !empty($input['toppings']) && is_array($input['toppings']) ? $input['toppings'] : [];
            $toppingsExtra = 0;
            $cleanToppings = [];
            foreach ($toppings as $t) {
                $tPrice = (float)($t['price'] ?? 0);
                $tName = trim($t['name'] ?? '');
                if ($tName) {
                    $toppingsExtra += $tPrice;
                    $cleanToppings[] = ['name' => $tName, 'price' => $tPrice];
                }
            }
            $unitPrice = $basePrice + $toppingsExtra;

            // Khóa giỏ hàng phân biệt theo món + danh sách topping đã chọn
            $toppingSignature = !empty($cleanToppings) ? '_' . substr(md5(json_encode($cleanToppings)), 0, 8) : '';
            $cartKey = $idKey . $toppingSignature;

            if (isset($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey]['qty'] += $qty;
            } else {
                $_SESSION['cart'][$cartKey] = [
                    'id'            => $cartKey,
                    'productId'     => $idKey,
                    'name'          => $product['name'],
                    'basePrice'     => $basePrice,
                    'toppings'      => $cleanToppings,
                    'toppingsExtra' => $toppingsExtra,
                    'price'         => $unitPrice,
                    'image'         => $product['image_url'] ?? '',
                    'qty'           => $qty,
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

        case 'apply_coupon':
            $couponCode = strtoupper(trim($input['code'] ?? $_POST['code'] ?? ''));
            if (!$couponCode) {
                jsonError('Vui lòng nhập mã giảm giá.');
            }
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM coupons WHERE UPPER(code) = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$couponCode]);
            $cp = $stmt->fetch();
            if (!$cp) {
                jsonError('Mã giảm giá "' . $couponCode . '" không hợp lệ hoặc đã hết hạn.');
            }
            $now = date('Y-m-d H:i:s');
            if (!empty($cp['start_date']) && $cp['start_date'] > $now) {
                jsonError('Mã giảm giá chưa đến ngày áp dụng.');
            }
            if (!empty($cp['end_date']) && $cp['end_date'] < $now) {
                jsonError('Mã giảm giá đã hết hạn sử dụng.');
            }
            if ($cp['usage_limit'] > 0 && $cp['usage_count'] >= $cp['usage_limit']) {
                jsonError('Mã giảm giá đã hết lượt sử dụng.');
            }
            if ($couponCode === 'NEW50') {
                $cUser = currentUser();
                if ($cUser) {
                    $ordCheck = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND order_status != 'cancelled'");
                    $ordCheck->execute([$cUser['id']]);
                    if ((int)$ordCheck->fetchColumn() > 0) {
                        jsonError('Mã NEW50 chỉ áp dụng cho đơn hàng đầu tiên của khách hàng mới.');
                    }
                }
            }
            $_SESSION['applied_coupon'] = [
                'id'                  => (int)$cp['id'],
                'code'                => $cp['code'],
                'title'               => $cp['title'],
                'discount_type'       => $cp['discount_type'],
                'discount_value'      => (float)$cp['discount_value'],
                'min_order_value'     => (float)$cp['min_order_value'],
                'max_discount_amount' => (float)$cp['max_discount_amount']
            ];
            jsonSuccess('Áp dụng mã giảm giá ' . $cp['code'] . ' thành công!', [
                'coupon' => $_SESSION['applied_coupon']
            ]);
            break;

        case 'remove_coupon':
            unset($_SESSION['applied_coupon']);
            jsonSuccess('Đã hủy áp dụng mã giảm giá.');
            break;

        default:
            jsonError('Hành động không hợp lệ.');
    }
}

