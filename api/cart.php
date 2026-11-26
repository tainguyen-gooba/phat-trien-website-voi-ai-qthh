<?php
/**
 * FASTFOOD - API Quản lý giỏ hàng (Cart Session)
 * Hỗ trợ lưu trữ biến thể: Size, Toppings, Khẩu vị & Ghi chú
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
    $rawCart = $_SESSION['cart'] ?? [];
    $items = [];
    $totalCount = 0;
    $subtotal = 0;

    foreach ($rawCart as $key => $item) {
        $qty = max(1, (int)($item['qty'] ?? 1));
        $price = (float)($item['price'] ?? 0);
        $itemTotal = $qty * $price;
        $totalCount += $qty;
        $subtotal += $itemTotal;

        $itemCopy = $item;
        $itemCopy['cartKey'] = (string)$key;
        $itemCopy['qty'] = $qty;
        $itemCopy['itemTotal'] = $itemTotal;

        // Chuẩn hóa và tự động phục hồi ảnh món ăn nếu session chưa có
        if (empty($itemCopy['image'])) {
            $numId = (int)preg_replace('/\D/', '', $itemCopy['productId'] ?? $key);
            if ($numId > 0) {
                try {
                    $imgDb = getDB()->query("SELECT image_url FROM products WHERE id = $numId LIMIT 1")->fetchColumn();
                    if ($imgDb) {
                        $itemCopy['image'] = $imgDb;
                        $_SESSION['cart'][$key]['image'] = $imgDb;
                    }
                } catch (\Exception $e) {}
            }
        }
        if (!empty($itemCopy['image']) && strpos($itemCopy['image'], 'http') !== 0) {
            $itemCopy['image'] = getAssetUrl($itemCopy['image']);
        }
        if (empty($itemCopy['image'])) {
            $itemCopy['image'] = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=160&auto=format&fit=crop&q=80';
        }

        $items[] = $itemCopy;
    }

    $uid = (int)($_SESSION['user_id'] ?? ($_SESSION['customer_user']['id'] ?? 0));
    if ($uid > 0) {
        if (!isset($_SESSION['user_carts'])) $_SESSION['user_carts'] = [];
        $_SESSION['user_carts'][$uid] = $_SESSION['cart'] ?? [];
    }

    $shippingFee = ($subtotal > 0 && $subtotal < 100000) ? 18000 : 0;

    // Xử lý giảm giá từ Coupon / Voucher (Tích hợp Hệ thống Voucher Shop 4 loại: %, Tiền, Freeship, Mua 1 tặng 1)
    $appliedCoupon = $_SESSION['applied_coupon'] ?? null;
    $discount = 0;
    if ($appliedCoupon && $subtotal > 0) {
        require_once __DIR__ . '/../helpers/voucher.php';
        $vRes = validateAndCalculateVoucher($appliedCoupon, $subtotal, $uid > 0 ? $uid : null, (float)$shippingFee, array_values($rawCart));
        if (!empty($vRes['valid'])) {
            $discount = (float)$vRes['discount_amount'];
        } else {
            // Nếu không đạt do sai món / hết lượt / đã sử dụng / hết hạn / sai hạng -> tự động gỡ khỏi session
            if (empty($vRes['missing_amount']) || !empty($vRes['scope_mismatch'])) {
                unset($_SESSION['applied_coupon']);
                $appliedCoupon = null;
            }
            $discount = 0;
        }
    }
    $total = max(0, $subtotal + $shippingFee - $discount);

    return [
        'items'         => $items,
        'totalCount'    => $totalCount,
        'subtotal'      => $subtotal,
        'shippingFee'   => $shippingFee,
        'discount'      => $discount,
        'total'         => $total,
        'appliedCoupon' => $appliedCoupon,
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
            $stmt = $db->prepare("SELECT id, category_id, name, base_price, discount_price, image_url, is_available FROM products WHERE id = ? OR product_code = ? LIMIT 1");
            $stmt->execute([$productId, $productId]);
            $product = $stmt->fetch();

            if (!$product) {
                // Thử tìm theo id số
                $numericId = (int)preg_replace('/\D/', '', $productId);
                if ($numericId > 0) {
                    $stmt = $db->prepare("SELECT id, category_id, name, base_price, discount_price, image_url, is_available FROM products WHERE id = ? LIMIT 1");
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

            // 1. Xử lý Size / Kích cỡ
            $size = !empty($input['size']) && is_array($input['size']) ? $input['size'] : null;
            $sizePrice = $size ? (float)($size['price'] ?? 0) : 0;
            $sizeName  = $size ? trim($size['name'] ?? '') : '';

            // 2. Xử lý toppings đính kèm
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

            // 3. Xử lý Khẩu vị / Thuộc tính (Attributes)
            $attributes = !empty($input['attributes']) && is_array($input['attributes']) ? $input['attributes'] : [];
            $cleanAttributes = [];
            foreach ($attributes as $a) {
                $aName = trim($a['name'] ?? '');
                $aVal  = trim($a['value'] ?? '');
                if ($aName && $aVal) {
                    $cleanAttributes[] = ['name' => $aName, 'value' => $aVal];
                }
            }
            $note = trim($input['note'] ?? '');

            // Đơn giá cuối cùng cho 1 phần ăn
            $unitPrice = $basePrice + $sizePrice + $toppingsExtra;

            // Khóa giỏ hàng phân biệt theo món + size + topping + attributes + note
            $variantPayload = [
                'size'     => $sizeName,
                'toppings' => $cleanToppings,
                'attrs'    => $cleanAttributes,
                'note'     => $note
            ];
            $hasVariants = (!empty($sizeName) || !empty($cleanToppings) || !empty($cleanAttributes) || !empty($note));
            $variantSignature = $hasVariants ? '_' . substr(md5(json_encode($variantPayload)), 0, 8) : '';
            $cartKey = $idKey . $variantSignature;

            $itemImg = !empty($product['image_url']) ? $product['image_url'] : '';
            if (!$itemImg) {
                try {
                    $itemImg = $db->query("SELECT image_url FROM product_images WHERE product_id = " . (int)$product['id'] . " ORDER BY is_primary DESC LIMIT 1")->fetchColumn() ?: '';
                } catch (\Exception $e) {}
            }
            if (!$itemImg) {
                $itemImg = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80';
            } elseif (strpos($itemImg, 'http') !== 0) {
                $itemImg = getAssetUrl($itemImg);
            }

            if (isset($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey]['qty'] += $qty;
                if (empty($_SESSION['cart'][$cartKey]['image'])) {
                    $_SESSION['cart'][$cartKey]['image'] = $itemImg;
                }
            } else {
                $_SESSION['cart'][$cartKey] = [
                    'id'            => $cartKey,
                    'productId'     => $idKey,
                    'category_id'   => (int)($product['category_id'] ?? 0),
                    'name'          => $product['name'],
                    'basePrice'     => $basePrice,
                    'size'          => $sizeName ? ['name' => $sizeName, 'price' => $sizePrice] : null,
                    'toppings'      => $cleanToppings,
                    'toppingsExtra' => $toppingsExtra,
                    'attributes'    => $cleanAttributes,
                    'note'          => $note,
                    'price'         => $unitPrice,
                    'image'         => $itemImg,
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
            $couponCode = strtoupper(trim($input['code'] ?? $input['couponCode'] ?? $_POST['code'] ?? $_POST['couponCode'] ?? ''));
            if (!$couponCode) {
                jsonError('Vui lòng nhập mã giảm giá.');
            }
            require_once __DIR__ . '/../helpers/voucher.php';
            $db = getDB();
            $cp = null;

            // 1. Kiểm tra bảng vouchers trước
            try {
                $stmt = $db->prepare("SELECT * FROM vouchers WHERE UPPER(code) = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$couponCode]);
                $cp = $stmt->fetch();
            } catch (Exception $e) {}

            // 2. Fallback sang bảng coupons cũ nếu chưa có
            if (!$cp) {
                try {
                    $stmt = $db->prepare("SELECT * FROM coupons WHERE UPPER(code) = ? AND is_active = 1 LIMIT 1");
                    $stmt->execute([$couponCode]);
                    $cp = $stmt->fetch();
                } catch (Exception $e) {}
            }

            if (!$cp) {
                jsonError('Mã giảm giá "' . $couponCode . '" không hợp lệ hoặc đã hết hạn.');
            }

            $cUser = currentUser();
            $currentUserId = $cUser ? (int)$cUser['id'] : null;

            // Kiểm tra voucher chào mừng khách hàng mới
            if ($couponCode === 'NEW50' || $couponCode === 'NEW50K' || strpos($couponCode, 'NEW50K-') === 0) {
                if (!$cUser) {
                    jsonError('Vui lòng đăng nhập và xác thực thông tin khách hàng mới để sử dụng Voucher 50.000đ.');
                }
                $claimChk = $db->prepare("SELECT id FROM customer_voucher_claims WHERE user_id = ? LIMIT 1");
                $claimChk->execute([$cUser['id']]);
                if (!$claimChk->fetch()) {
                    jsonError('Bạn chưa nhập thông tin xác thực bạn mới nên chưa nhận được Voucher 50.000đ này.');
                }
                $ordCheck = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND order_status != 'cancelled'");
                $ordCheck->execute([$cUser['id']]);
                if ((int)$ordCheck->fetchColumn() > 0) {
                    jsonError('Voucher chào mừng ' . $couponCode . ' chỉ áp dụng cho đơn hàng đầu tiên của khách hàng mới.');
                }
            }

            // Kiểm tra voucher cấp riêng cho user
            if (!empty($cp['user_id'])) {
                if (!$cUser) {
                    jsonError('Mã giảm giá này dành riêng cho tài khoản được cấp. Vui lòng đăng nhập để sử dụng.');
                } elseif ((int)$cp['user_id'] !== (int)$cUser['id']) {
                    jsonError('Mã giảm giá này chỉ dành riêng cho chủ sở hữu tài khoản được cấp.');
                }
            }

            // Tính toán tạm tính giỏ hàng hiện tại
            $cartSummary = getCartSummary();
            $subtotal = (float)$cartSummary['subtotal'];
            $shippingFee = (float)$cartSummary['shippingFee'];

            if ($subtotal <= 0) {
                jsonError('Giỏ hàng đang trống, vui lòng chọn món trước khi áp dụng voucher.');
            }

            // Kiểm tra điều kiện & tính toán chiết khấu bằng helper voucher chung (kèm kiểm tra đúng món ăn)
            $sessionCartItems = !empty($_SESSION['cart']) && is_array($_SESSION['cart']) ? array_values($_SESSION['cart']) : [];
            $calc = validateAndCalculateVoucher($cp, $subtotal, $currentUserId, $shippingFee, $sessionCartItems);
            if (empty($calc['valid'])) {
                jsonError($calc['reason'] ?? 'Voucher không thỏa mãn điều kiện áp dụng.');
            }

            $voucherType = $cp['voucher_type'] ?? ($cp['discount_type'] ?? 'percentage');
            $_SESSION['applied_coupon'] = [
                'id'                  => (int)$cp['id'],
                'code'                => $cp['code'],
                'title'               => $cp['title'],
                'voucher_type'        => $voucherType,
                'discount_type'       => $voucherType,
                'discount_value'      => (float)$cp['discount_value'],
                'min_order_value'     => (float)$cp['min_order_value'],
                'max_discount_amount' => (float)$cp['max_discount_amount'],
                'min_member_tier'     => $cp['min_member_tier'] ?? 'ALL',
                'applicable_scope'    => $calc['applicable_scope'] ?? ($cp['applicable_scope'] ?? 'all'),
                'applicable_label'    => $calc['applicable_label'] ?? ($cp['applicable_label'] ?? ''),
                'discount_amount'     => (float)$calc['discount_amount']
            ];

            $savedMsg = 'Áp dụng mã ' . $cp['code'] . ' thành công! Tiết kiệm ' . number_format($calc['discount_amount'], 0, ',', '.') . 'đ';
            jsonSuccess($savedMsg, getCartSummary());
            break;

        case 'remove_coupon':
            unset($_SESSION['applied_coupon']);
            jsonSuccess('Đã hủy áp dụng mã giảm giá.', getCartSummary());
            break;

        case 'get':
        case 'summary':
            jsonSuccess('Lấy thông tin giỏ hàng thành công.', getCartSummary());
            break;

        default:
            jsonError('Hành động không hợp lệ.');
    }
}
