<?php
/**
 * FASTFOOD - Admin AJAX API Controller
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/helpers/response.php';

requireAdminOrStaff();
$currentUser = currentUser();
$db = getDB();

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
$action = $body['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_all_data':
        $categories = $db->query("SELECT id, name, slug, icon_url, display_order, is_active FROM categories ORDER BY display_order ASC, id ASC")->fetchAll();
        $products = $db->query("SELECT p.id, p.name, p.slug, p.base_price, p.discount_price, p.is_available, p.image_url, p.category_id, p.restaurant_id, p.options, c.name AS category_name, r.name AS restaurant_name 
                                FROM products p 
                                LEFT JOIN categories c ON p.category_id = c.id 
                                LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                                ORDER BY p.id DESC")->fetchAll();
        
        foreach ($products as &$prod) {
            if (!empty($prod['options']) && is_string($prod['options'])) {
                $prod['options'] = json_decode($prod['options'], true);
            }
        }
        unset($prod);

        $ordersRaw = $db->query("SELECT * FROM realtime_orders ORDER BY id DESC LIMIT 200")->fetchAll();
        $orders = [];
        foreach ($ordersRaw as $row) {
            $data = json_decode($row['order_data'], true) ?: [];
            $data['id'] = $row['order_code'];
            $data['status'] = $row['status'];
            $data['createdAt'] = $row['created_at'];
            $orders[] = $data;
        }

        $users = [];
        if (isAdmin()) {
            $users = $db->query("SELECT id, full_name, email, phone, role_id, status, created_at FROM users ORDER BY id ASC")->fetchAll();
        } else {
            // Staff chỉ thấy danh sách tóm tắt
            $users = $db->query("SELECT id, full_name, email, role_id, status FROM users ORDER BY id ASC")->fetchAll();
        }

        $restaurants = $db->query("SELECT id, name, address, phone, rating_avg, is_active FROM restaurants ORDER BY id ASC")->fetchAll();
        $reviews = $db->query("SELECT r.*, u.full_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.id DESC LIMIT 100")->fetchAll();

        jsonSuccess('Lấy dữ liệu thành công', [
            'categories'  => $categories,
            'products'    => $products,
            'orders'      => $orders,
            'users'       => $users,
            'restaurants' => $restaurants,
            'reviews'     => $reviews,
            'currentUser' => $currentUser,
        ]);
        break;

    case 'update_order_status':
        $orderCode = trim($body['orderCode'] ?? '');
        $newStatus = trim($body['status'] ?? '');
        if (!$orderCode || !$newStatus) {
            jsonError('Thiếu mã đơn hàng hoặc trạng thái mới.');
        }

        $stmt = $db->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE order_code = ?");
        $stmt->execute([$newStatus, $orderCode]);

        $rtStmt = $db->prepare("UPDATE realtime_orders SET status = ?, updated_at = NOW() WHERE order_code = ?");
        $rtStmt->execute([$newStatus, $orderCode]);

        jsonSuccess('Cập nhật trạng thái đơn hàng thành công!');
        break;

    case 'save_product':
        $id          = (int)($body['id'] ?? 0);
        $name        = trim($body['name'] ?? '');
        $basePrice   = (float)($body['basePrice'] ?? $body['price'] ?? 0);
        $discountPrice = (float)($body['discountPrice'] ?? 0);
        $categoryId  = (int)($body['categoryId'] ?? 1);
        $restaurantId = !empty($body['restaurantId']) ? (int)$body['restaurantId'] : 1;
        $imageUrl    = trim($body['imageUrl'] ?? $body['image'] ?? '');
        $isAvailable = isset($body['isAvailable']) ? (int)$body['isAvailable'] : 1;

        if (!$name || $basePrice <= 0) {
            jsonError('Vui lòng nhập tên món và giá tiền hợp lệ.');
        }

        // Xử lý biến thể & tùy chọn (Options: sizes, toppings, attributes)
        $optionsRaw = $body['options'] ?? null;
        $optionsJson = null;
        if (!empty($optionsRaw)) {
            if (is_string($optionsRaw)) {
                $optionsJson = $optionsRaw;
            } elseif (is_array($optionsRaw)) {
                $optionsJson = json_encode($optionsRaw, JSON_UNESCAPED_UNICODE);
            }
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        if (!$slug) $slug = 'mon-' . time();

        if ($id > 0) {
            if ($optionsJson !== null) {
                $stmt = $db->prepare("UPDATE products SET name = ?, base_price = ?, discount_price = ?, category_id = ?, image_url = ?, is_available = ?, options = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $basePrice, $discountPrice, $categoryId, $imageUrl, $isAvailable, $optionsJson, $id]);
            } else {
                $stmt = $db->prepare("UPDATE products SET name = ?, base_price = ?, discount_price = ?, category_id = ?, image_url = ?, is_available = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $basePrice, $discountPrice, $categoryId, $imageUrl, $isAvailable, $id]);
            }
            jsonSuccess('Cập nhật món ăn và biến thể tùy chọn thành công!');
        } else {
            $stmt = $db->prepare("INSERT INTO products (name, slug, restaurant_id, category_id, base_price, discount_price, image_url, is_available, options, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $slug, $restaurantId, $categoryId, $basePrice, $discountPrice, $imageUrl, $isAvailable, $optionsJson]);
            jsonSuccess('Thêm món ăn mới kèm biến thể thành công!');
        }
        break;

    case 'delete_product':
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) jsonError('Thiếu ID sản phẩm cần xóa.');
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        jsonSuccess('Đã xóa món ăn thành công!');
        break;

    case 'save_category':
        $id   = (int)($body['id'] ?? 0);
        $name = trim($body['name'] ?? '');
        $icon = trim($body['icon'] ?? '🍔');
        if (!$name) jsonError('Tên danh mục không được để trống.');

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        if (!$slug) $slug = 'cat-' . time();

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE categories SET name = ?, icon_url = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $icon, $id]);
            jsonSuccess('Cập nhật danh mục thành công!');
        } else {
            $stmt = $db->prepare("INSERT INTO categories (name, slug, icon_url, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
            $stmt->execute([$name, $slug, $icon]);
            jsonSuccess('Thêm danh mục mới thành công!');
        }
        break;

    case 'delete_category':
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) jsonError('Thiếu ID danh mục.');
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        jsonSuccess('Đã xóa danh mục!');
        break;

    case 'save_user':
        requireAdmin(); // Chỉ Admin mới có quyền sửa người dùng
        $id     = (int)($body['id'] ?? 0);
        $roleId = (int)($body['roleId'] ?? 2);
        $status = trim($body['status'] ?? 'active');

        if ($id <= 0) jsonError('Thiếu ID người dùng.');
        if ($id === $currentUser['id']) jsonError('Không thể tự hạ quyền của chính bạn.');

        $stmt = $db->prepare("UPDATE users SET role_id = ?, status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$roleId, $status, $id]);
        jsonSuccess('Cập nhật phân quyền người dùng thành công!');
        break;

    case 'upload_image':
        if (empty($_FILES['image'])) {
            jsonError('Không tìm thấy tệp ảnh tải lên.');
        }
        $file = $_FILES['image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            jsonError('Định dạng ảnh không được hỗ trợ. Vui lòng chọn JPG, PNG hoặc WebP.');
        }
        $newFilename = 'prod_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $targetDir = ROOT_PATH . '/frontend/assets/uploads';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $destPath = $targetDir . '/' . $newFilename;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            jsonError('Không thể lưu tệp ảnh.');
        }
        $url = getAssetUrl('uploads/' . $newFilename);
        jsonSuccess('Tải ảnh thành công!', ['url' => $url]);
        break;

    case 'get_banners':
        $bannerRow = null;
        try {
            $bannerRow = $db->query("SELECT content FROM website_content WHERE collection_name = 'banners' LIMIT 1")->fetchColumn();
        } catch (Exception $e) {}

        $bannerData = $bannerRow ? json_decode($bannerRow, true) : null;
        if (!$bannerData || !is_array($bannerData)) {
            $bannerData = [
                'interval' => 1800,
                'banners' => [
                    [
                        'id' => 'banner-1',
                        'title' => 'Burger Bò Nướng Phô Mai',
                        'subtitle' => 'Burger King Station • Nóng hổi giao 15 phút',
                        'badge' => '🔥 ƯU ĐÃI GIẢM 50%',
                        'image' => 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=1600&auto=format&fit=crop&q=85',
                        'link' => 'foods.php?search=Burger',
                        'order' => 1,
                        'active' => true
                    ],
                    [
                        'id' => 'banner-2',
                        'title' => 'Pizza Hải Sản Pesto Ý',
                        'subtitle' => 'Pizza Hut Italia • Phô mai kéo sợi thơm lừng',
                        'badge' => '🍕 MUA 1 TẶNG 1 HÔM NAY',
                        'image' => 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=1600&auto=format&fit=crop&q=85',
                        'link' => 'foods.php?search=Pizza',
                        'order' => 2,
                        'active' => true
                    ],
                    [
                        'id' => 'banner-3',
                        'title' => 'Cơm Tấm Sườn Bì Chả',
                        'subtitle' => 'Cơm Tấm Sài Gòn Xưa • Chuẩn vị truyền thống',
                        'badge' => '🍚 FREESHIP 0Đ ĐƠN TỪ 50K',
                        'image' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=1600&auto=format&fit=crop&q=85',
                        'link' => 'foods.php?search=Cơm',
                        'order' => 3,
                        'active' => true
                    ],
                    [
                        'id' => 'banner-4',
                        'title' => 'Trà Sữa Trân Châu Hoàng Gia',
                        'subtitle' => 'GongCha Tea • Trân châu đường đen dẻo dai',
                        'badge' => '🧋 GIẢM 30K GIỜ VÀNG',
                        'image' => 'https://images.unsplash.com/photo-1541658016709-82535e94bc69?w=1600&auto=format&fit=crop&q=85',
                        'link' => 'foods.php?search=Trà sữa',
                        'order' => 4,
                        'active' => true
                    ]
                ]
            ];
        }
        jsonSuccess('Lấy danh sách banner thành công!', $bannerData);
        break;

    case 'save_banners':
        $banners = $body['banners'] ?? [];
        $interval = (int)($body['interval'] ?? 1800);
        if ($interval < 1000) $interval = 1000;
        if ($interval > 10000) $interval = 10000;

        $saveData = [
            'interval' => $interval,
            'banners'  => $banners
        ];
        $jsonStr = json_encode($saveData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $stmt = $db->prepare("INSERT INTO website_content (collection_name, content, updated_at) 
                             VALUES ('banners', ?, NOW()) 
                             ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = NOW()");
        $stmt->execute([$jsonStr]);
        jsonSuccess('Đã lưu cấu hình banner và tốc độ chuyển slide thành công!');
        break;

    default:
        jsonError('Hành động không hợp lệ.');
}
