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
        $rawOrderCode = trim($body['orderCode'] ?? $body['order_code'] ?? $_POST['orderCode'] ?? $_POST['order_code'] ?? $_GET['orderCode'] ?? '');
        $rawStatus = trim($body['status'] ?? $body['order_status'] ?? $_POST['status'] ?? $_POST['order_status'] ?? $_GET['status'] ?? '');

        if (!$rawOrderCode || !$rawStatus) {
            jsonError('Thiếu mã đơn hàng hoặc trạng thái mới.');
        }

        $orderCode = preg_replace('/^#/', '', $rawOrderCode);
        $newStatus = strtolower($rawStatus);

        // Map status for orders vs realtime_orders ENUM differences
        $orderDbStatus = ($newStatus === 'delivered') ? 'completed' : $newStatus;
        $rtDbStatus    = ($newStatus === 'completed') ? 'delivered' : $newStatus;

        // Query existing order
        $ordStmt = $db->prepare("SELECT id, order_code, customer_id, total_amount, order_status, points_earned, points_used FROM orders WHERE order_code = ? LIMIT 1");
        $ordStmt->execute([$orderCode]);
        $order = $ordStmt->fetch();

        if (!$order) {
            jsonError('Không tìm thấy đơn hàng: ' . $orderCode, 404);
        }

        $orderId = (int)$order['id'];
        $previousStatus = strtolower($order['order_status'] ?? '');

        // Update orders table with timestamp fields
        if ($orderDbStatus === 'completed') {
            $upStmt = $db->prepare("UPDATE orders SET order_status = ?, payment_status = CASE WHEN LOWER(payment_method) = 'cod' THEN 'completed' ELSE payment_status END, completed_at = IFNULL(completed_at, NOW()), updated_at = NOW() WHERE id = ?");
        } elseif ($orderDbStatus === 'cancelled') {
            $upStmt = $db->prepare("UPDATE orders SET order_status = ?, cancelled_at = IFNULL(cancelled_at, NOW()), cancellation_reason = IFNULL(cancellation_reason, 'Quản trị viên hủy đơn hàng'), updated_at = NOW() WHERE id = ?");
        } else {
            $upStmt = $db->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?");
        }
        $upStmt->execute([$orderDbStatus, $orderId]);

        // Sync realtime_orders
        $rtStmt = $db->prepare("UPDATE realtime_orders SET status = ?, updated_at = NOW() WHERE order_code = ?");
        $rtStmt->execute([$rtDbStatus, $orderCode]);

        // Invoke Loyalty hooks
        require_once ROOT_PATH . '/helpers/loyalty.php';
        $loyaltyResult = null;

        if (!empty($order['customer_id']) && (int)$order['customer_id'] > 0) {
            if ($orderDbStatus === 'completed') {
                $loyaltyResult = processOrderEarnPoints($orderId);
            } elseif ($orderDbStatus === 'cancelled') {
                $loyaltyResult = handleOrderCancellation($orderId);
            }
        }

        jsonSuccess('Cập nhật trạng thái đơn hàng thành công!', [
            'order_id'        => $orderId,
            'order_code'      => $orderCode,
            'previous_status' => $previousStatus,
            'order_status'    => $orderDbStatus,
            'loyalty'         => $loyaltyResult
        ]);
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
            if (!empty($imageUrl)) {
                $hasPi = $db->query("SELECT id FROM product_images WHERE product_id = $id AND is_primary = 1 LIMIT 1")->fetchColumn();
                if ($hasPi) {
                    $db->prepare("UPDATE product_images SET image_url = ? WHERE product_id = ? AND is_primary = 1")->execute([$imageUrl, $id]);
                } else {
                    $db->prepare("INSERT INTO product_images (product_id, image_url, is_primary) VALUES (?, ?, 1)")->execute([$id, $imageUrl]);
                }
            }
            jsonSuccess('Cập nhật món ăn và biến thể tùy chọn thành công!');
        } else {
            $stmt = $db->prepare("INSERT INTO products (name, slug, restaurant_id, category_id, base_price, discount_price, image_url, is_available, options, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $slug, $restaurantId, $categoryId, $basePrice, $discountPrice, $imageUrl, $isAvailable, $optionsJson]);
            $newId = (int)$db->lastInsertId();
            if ($newId > 0 && !empty($imageUrl)) {
                $db->prepare("INSERT INTO product_images (product_id, image_url, is_primary) VALUES (?, ?, 1)")->execute([$newId, $imageUrl]);
            }
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

    // =========================================================================
    // MILESTONE 4: MEMBERSHIP & LOYALTY REWARD SYSTEM ADMIN API ACTIONS
    // =========================================================================

    case 'get_loyalty_members':
        // RBAC: Admin (role 1) and Staff (role 5) allowed (guarded by requireAdminOrStaff() at line 8)
        $tier = trim($_GET['tier'] ?? $body['tier'] ?? $_POST['tier'] ?? '');
        $search = trim($_GET['search'] ?? $body['search'] ?? $_POST['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? $body['page'] ?? $_POST['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? $body['limit'] ?? $_POST['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $conditions = ["1=1"];
        $params = [];

        // Tier filter
        if ($tier !== '' && strtoupper($tier) !== 'ALL') {
            $conditions[] = "UPPER(member_tier) = ?";
            $params[] = strtoupper($tier);
        }

        // Search filter: keyword in full_name, email, or phone
        if ($search !== '') {
            $conditions[] = "(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $kw = '%' . $search . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereSql = implode(' AND ', $conditions);

        // Count total matching records
        $countStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalMembers = (int)$countStmt->fetchColumn();

        // Load tiers metadata
        require_once ROOT_PATH . '/helpers/loyalty.php';
        $tierMeta = getMembershipTiers();

        // Fetch paginated member rows
        $querySql = "SELECT id, full_name, email, phone, avatar_url, role_id, status, member_tier, points_balance, total_spent, created_at 
                     FROM users 
                     WHERE {$whereSql} 
                     ORDER BY id ASC 
                     LIMIT ? OFFSET ?";
        
        $fetchStmt = $db->prepare($querySql);
        $execParams = $params;
        $execParams[] = $limit;
        $execParams[] = $offset;
        
        foreach ($execParams as $i => $val) {
            $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $fetchStmt->bindValue($i + 1, $val, $type);
        }
        $fetchStmt->execute();
        $rows = $fetchStmt->fetchAll();

        $members = [];
        foreach ($rows as $u) {
            $tierCode = strtoupper($u['member_tier'] ?? 'BRONZE');
            $t = $tierMeta[$tierCode] ?? [];
            $members[] = [
                'id'             => (int)$u['id'],
                'name'           => $u['full_name'],
                'full_name'      => $u['full_name'],
                'email'          => $u['email'],
                'phone'          => $u['phone'] ?? '',
                'avatar'         => $u['avatar_url'] ?? '',
                'avatar_url'     => $u['avatar_url'] ?? '',
                'role_id'        => (int)$u['role_id'],
                'status'         => $u['status'] ?? 'active',
                'member_tier'    => $tierCode,
                'points_balance' => (int)($u['points_balance'] ?? 0),
                'total_spent'    => (float)($u['total_spent'] ?? 0),
                'created_at'     => $u['created_at'],
                'tier_name'      => $t['tier_name'] ?? $tierCode,
                'badge_color'    => $t['badge_color'] ?? '#cd7f32',
                'badge_icon'     => $t['badge_icon'] ?? '🥉'
            ];
        }

        // Summary statistics across entire membership
        $statsRows = $db->query("SELECT member_tier, COUNT(*) as cnt, SUM(points_balance) as total_pts, SUM(total_spent) as total_vnd FROM users GROUP BY member_tier")->fetchAll();
        $tierCounts = ['BRONZE' => 0, 'SILVER' => 0, 'GOLD' => 0, 'DIAMOND' => 0];
        $totalAll = 0;
        $totalSystemPoints = 0;
        $totalSystemSpent = 0.0;
        foreach ($statsRows as $sr) {
            $code = strtoupper($sr['member_tier'] ?? 'BRONZE');
            $cnt = (int)$sr['cnt'];
            $tierCounts[$code] = $cnt;
            $totalAll += $cnt;
            $totalSystemPoints += (int)($sr['total_pts'] ?? 0);
            $totalSystemSpent += (float)($sr['total_vnd'] ?? 0);
        }

        $totalPages = $limit > 0 ? (int)ceil($totalMembers / $limit) : 1;
        $vipCount = ($tierCounts['SILVER'] ?? 0) + ($tierCounts['GOLD'] ?? 0) + ($tierCounts['DIAMOND'] ?? 0);

        jsonSuccess('Lấy danh sách thành viên thành công', [
            'members'    => $members,
            'pagination' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $totalMembers,
                'total_pages' => $totalPages,
                'totalPages'  => $totalPages,
            ],
            'summary'    => [
                'totalMembers' => $totalAll,
                'totalPoints'  => $totalSystemPoints,
                'vipCount'     => $vipCount
            ],
            'stats'      => [
                'total_members'        => $totalAll,
                'bronze_count'         => $tierCounts['BRONZE'] ?? 0,
                'silver_count'         => $tierCounts['SILVER'] ?? 0,
                'gold_count'           => $tierCounts['GOLD'] ?? 0,
                'diamond_count'        => $tierCounts['DIAMOND'] ?? 0,
                'total_system_points'  => $totalSystemPoints,
                'total_system_spent'   => $totalSystemSpent,
            ]
        ]);
        break;

    case 'get_member_loyalty_history':
        // RBAC: Admin (role 1) and Staff (role 5) allowed
        $userId = (int)($_GET['user_id'] ?? $body['user_id'] ?? $_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            jsonError('Thiếu ID thành viên hợp lệ.', 400);
        }

        $userStmt = $db->prepare("SELECT id, full_name, email, phone, avatar_url, role_id, status, member_tier, points_balance, total_spent, created_at FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        if (!$user) {
            jsonError('Không tìm thấy người dùng.', 404);
        }

        // Fetch transaction ledger
        $txStmt = $db->prepare("SELECT pt.id, pt.user_id, pt.order_id, pt.points, pt.balance_after, pt.transaction_type, pt.description, pt.created_at, o.order_code 
                                FROM point_transactions pt 
                                LEFT JOIN orders o ON pt.order_id = o.id 
                                WHERE pt.user_id = ? 
                                ORDER BY pt.created_at DESC, pt.id DESC");
        $txStmt->execute([$userId]);
        $rawTxs = $txStmt->fetchAll();

        $history = [];
        foreach ($rawTxs as $row) {
            $history[] = [
                'id'               => (int)$row['id'],
                'user_id'          => (int)$row['user_id'],
                'order_id'         => $row['order_id'] !== null ? (int)$row['order_id'] : null,
                'order_code'       => $row['order_code'] ?? null,
                'points'           => (int)$row['points'],
                'points_change'    => (int)$row['points'], // Alias for contract compatibility
                'balance_after'    => (int)$row['balance_after'],
                'transaction_type' => $row['transaction_type'],
                'description'      => $row['description'] ?? '',
                'created_at'       => $row['created_at'],
            ];
        }

        require_once ROOT_PATH . '/helpers/loyalty.php';
        $tierMeta = getMembershipTiers();
        $tierCode = strtoupper($user['member_tier'] ?? 'BRONZE');
        $t = $tierMeta[$tierCode] ?? [];

        jsonSuccess('Lấy lịch sử giao dịch điểm thành công', [
            'user' => [
                'id'             => (int)$user['id'],
                'name'           => $user['full_name'],
                'full_name'      => $user['full_name'],
                'email'          => $user['email'],
                'phone'          => $user['phone'] ?? '',
                'avatar'         => $user['avatar_url'] ?? '',
                'tier'           => $tierCode,
                'member_tier'    => $tierCode,
                'tier_name'      => $t['tier_name'] ?? $tierCode,
                'badge_color'    => $t['badge_color'] ?? '#cd7f32',
                'badge_icon'     => $t['badge_icon'] ?? '🥉',
                'points'         => (int)($user['points_balance'] ?? 0),
                'points_balance' => (int)($user['points_balance'] ?? 0),
                'total_spent'    => (float)($user['total_spent'] ?? 0),
                'created_at'     => $user['created_at'],
            ],
            'history'      => $history,
            'transactions' => $history
        ]);
        break;

    case 'update_member_loyalty':
        // RBAC: Strictly Admin only (role_id === 1)
        if (!isAdmin()) {
            jsonError('Chỉ Quản trị viên cấp cao (Admin) mới có quyền sửa hoặc thăng hạng thành viên.', 403);
        }

        require_once ROOT_PATH . '/helpers/loyalty.php';

        $targetUserId = (int)($body['user_id'] ?? $_POST['user_id'] ?? 0);
        if ($targetUserId <= 0) {
            jsonError('Thiếu ID thành viên hợp lệ.', 400);
        }

        $payload = [
            'member_tier'    => trim((string)($body['member_tier'] ?? $_POST['member_tier'] ?? '')),
            'points_balance' => isset($body['points_balance']) ? $body['points_balance'] : ($_POST['points_balance'] ?? null),
            'total_spent'    => isset($body['total_spent']) ? $body['total_spent'] : ($_POST['total_spent'] ?? null),
            'sync_min_spent' => !empty($body['sync_min_spent'] ?? $_POST['sync_min_spent'] ?? false),
            'reason'         => trim((string)($body['reason'] ?? $_POST['reason'] ?? '')),
        ];

        $res = adminUpdateMemberLoyalty($targetUserId, $payload);
        if (empty($res['success'])) {
            jsonError($res['error'] ?? 'Không thể cập nhật thông tin thành viên.', (int)($res['status'] ?? 400));
        }

        jsonSuccess($res['message'] ?? 'Cập nhật thành viên thành công!', $res['member'] ?? []);
        break;

    case 'save_loyalty_config':
        // RBAC: Strictly Admin only (role_id === 1)
        if (!isAdmin()) {
            jsonError('Chỉ quản trị viên cấp cao mới có quyền thay đổi cấu hình.', 403);
        }

        require_once ROOT_PATH . '/helpers/loyalty.php';

        // Extract settings (supports nested settings object or top-level keys)
        $settingsInput = $body['settings'] ?? [];
        if (!is_array($settingsInput)) $settingsInput = [];

        foreach (['point_to_vnd_rate', 'spending_step_vnd', 'min_redeem_points', 'max_redeem_percentage'] as $key) {
            if (isset($body[$key])) {
                $settingsInput[$key] = $body[$key];
            } elseif (isset($_POST[$key])) {
                $settingsInput[$key] = $_POST[$key];
            }
        }

        // Extract tiers (supports thresholds/ratios objects, tiers array, or flat fields)
        $tiersInput = $body['tiers'] ?? $_POST['tiers'] ?? null;

        if (empty($tiersInput) || !is_array($tiersInput)) {
            $builtTiers = [];
            $thresholds = $body['thresholds'] ?? $_POST['thresholds'] ?? [];
            $ratios = $body['ratios'] ?? $_POST['ratios'] ?? [];

            foreach (['BRONZE', 'SILVER', 'GOLD', 'DIAMOND'] as $tc) {
                $lCode = strtolower($tc);
                $spentVal = $thresholds[$tc] ?? $thresholds[$lCode] ?? $body["tier_{$lCode}_min_spent"] ?? $_POST["tier_{$lCode}_min_spent"] ?? $body["{$lCode}_min_spent"] ?? $_POST["{$lCode}_min_spent"] ?? null;
                $ratioVal = $ratios[$tc] ?? $ratios[$lCode] ?? $body["tier_{$lCode}_point_ratio"] ?? $_POST["tier_{$lCode}_point_ratio"] ?? $body["{$lCode}_point_ratio"] ?? $_POST["{$lCode}_point_ratio"] ?? $body["{$lCode}_ratio"] ?? $_POST["{$lCode}_ratio"] ?? null;

                if ($spentVal !== null || $ratioVal !== null) {
                    $item = ['tier_code' => $tc];
                    if ($spentVal !== null) $item['min_spent'] = $spentVal;
                    if ($ratioVal !== null) $item['point_ratio'] = $ratioVal;
                    $builtTiers[] = $item;
                }
            }
            if (!empty($builtTiers)) {
                $tiersInput = $builtTiers;
            }
        }

        // Validation 1: Settings fields
        if (isset($settingsInput['point_to_vnd_rate'])) {
            $val = $settingsInput['point_to_vnd_rate'];
            if ($val === '' || $val === null || !is_numeric($val) || (float)$val <= 0) {
                jsonError('Tỷ lệ quy đổi điểm (point_to_vnd_rate) phải là số dương lớn hơn 0.', 400);
            }
        }
        if (isset($settingsInput['spending_step_vnd'])) {
            $val = $settingsInput['spending_step_vnd'];
            if ($val === '' || $val === null || !is_numeric($val) || (float)$val <= 0) {
                jsonError('Mốc chi tiêu cơ sở (spending_step_vnd) phải là số dương lớn hơn 0.', 400);
            }
        }
        if (isset($settingsInput['min_redeem_points'])) {
            $val = $settingsInput['min_redeem_points'];
            if ($val === '' || $val === null || !is_numeric($val) || (int)$val < 0) {
                jsonError('Điểm đổi tối thiểu (min_redeem_points) không được là số âm.', 400);
            }
        }

        // Validation 2: Tiers fields & Inverted thresholds check
        if (!empty($tiersInput) && is_array($tiersInput)) {
            $effectiveTiers = getMembershipTiers();
            foreach ($tiersInput as $t) {
                $tc = strtoupper($t['tier_code'] ?? '');
                if (!$tc || !isset($effectiveTiers[$tc])) {
                    jsonError("Mã hạng thành viên không hợp lệ: {$tc}", 400);
                }
                if (isset($t['min_spent'])) {
                    if ($t['min_spent'] === '' || $t['min_spent'] === null || !is_numeric($t['min_spent']) || (float)$t['min_spent'] < 0) {
                        jsonError("Mốc chi tiêu hạng {$tc} không được là số âm.", 400);
                    }
                    $effectiveTiers[$tc]['min_spent'] = (float)$t['min_spent'];
                }
                if (isset($t['point_ratio'])) {
                    if ($t['point_ratio'] === '' || $t['point_ratio'] === null || !is_numeric($t['point_ratio']) || (float)$t['point_ratio'] <= 0) {
                        jsonError("Tỷ lệ tích điểm hạng {$tc} phải lớn hơn 0.", 400);
                    }
                    $effectiveTiers[$tc]['point_ratio'] = (float)$t['point_ratio'];
                }
            }

            $silverSpent  = (float)$effectiveTiers['SILVER']['min_spent'];
            $goldSpent    = (float)$effectiveTiers['GOLD']['min_spent'];
            $diamondSpent = (float)$effectiveTiers['DIAMOND']['min_spent'];

            if ($silverSpent <= 0) {
                jsonError("Mốc chi tiêu hạng Bạc (Silver) phải lớn hơn 0.", 400);
            }
            if ($silverSpent >= $goldSpent) {
                jsonError("Mốc chi tiêu hạng Bạc ({$silverSpent}) phải nhỏ hơn hạng Vàng ({$goldSpent}).", 400);
            }
            if ($goldSpent >= $diamondSpent) {
                jsonError("Mốc chi tiêu hạng Vàng ({$goldSpent}) phải nhỏ hơn hạng Kim Cương ({$diamondSpent}).", 400);
            }
        }

        // Atomic DB Update
        $ownsTx = false;
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $ownsTx = true;
        }

        try {
            // Update loyalty_settings
            if (!empty($settingsInput)) {
                $setStmt = $db->prepare("INSERT INTO loyalty_settings (setting_key, setting_value, updated_at) 
                                         VALUES (?, ?, NOW()) 
                                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
                foreach ($settingsInput as $k => $v) {
                    $setStmt->execute([$k, (string)$v]);
                }
            }

            // Update membership_tiers
            if (!empty($tiersInput) && is_array($tiersInput)) {
                $tierStmt = $db->prepare("UPDATE membership_tiers 
                                          SET min_spent = COALESCE(?, min_spent), 
                                              point_ratio = COALESCE(?, point_ratio), 
                                              updated_at = NOW() 
                                          WHERE tier_code = ?");
                foreach ($tiersInput as $t) {
                    $tc = strtoupper($t['tier_code']);
                    $minSpent = isset($t['min_spent']) ? (float)$t['min_spent'] : null;
                    $ratio    = isset($t['point_ratio']) ? (float)$t['point_ratio'] : null;
                    $tierStmt->execute([$minSpent, $ratio, $tc]);
                }
            }

            if ($ownsTx) {
                $db->commit();
            }

            // Invalidate cache immediately
            clearLoyaltyCache();

            jsonSuccess('Cập nhật cấu hình tích điểm và hạng thành viên thành công!', [
                'settings' => getLoyaltySettings(),
                'tiers'    => getMembershipTiers()
            ]);
        } catch (Exception $e) {
            if ($ownsTx) {
                $db->rollBack();
            }
            jsonError('Lỗi cập nhật cấu hình: ' . $e->getMessage(), 500);
        }
        break;

    case 'get_loyalty_config':
        // RBAC: Admin & Staff allowed
        require_once ROOT_PATH . '/helpers/loyalty.php';
        jsonSuccess('Lấy cấu hình thành công', [
            'settings' => getLoyaltySettings(),
            'tiers'    => getMembershipTiers()
        ]);
        break;

    // =========================================================================
    // VOUCHER MANAGEMENT ACTIONS (ADMIN & STAFF)
    // =========================================================================
    case 'get_admin_vouchers':
        require_once ROOT_PATH . '/helpers/voucher.php';
        $filters = [
            'search'          => trim($body['search'] ?? $_GET['search'] ?? ''),
            'voucher_type'    => trim($body['voucher_type'] ?? $_GET['voucher_type'] ?? ''),
            'min_member_tier' => trim($body['min_member_tier'] ?? $_GET['min_member_tier'] ?? ''),
            'is_active'       => isset($body['is_active']) ? $body['is_active'] : ($_GET['is_active'] ?? '')
        ];
        $vouchers = getAdminVouchers($filters);
        $stats = getVoucherStats();
        jsonSuccess('Lấy danh sách voucher thành công!', [
            'vouchers' => $vouchers,
            'stats'    => $stats
        ]);
        break;

    case 'save_admin_voucher':
        require_once ROOT_PATH . '/helpers/voucher.php';
        $vData = is_array($body['voucher'] ?? null) ? $body['voucher'] : $_POST;
        $vId = !empty($vData['id']) ? (int)$vData['id'] : null;
        $res = saveAdminVoucher($vData, $vId);
        if (!$res['success']) {
            jsonError($res['error'] ?? 'Không thể lưu voucher.');
        }
        jsonSuccess($res['message'], ['voucher_id' => $res['voucher_id']]);
        break;

    case 'delete_admin_voucher':
        require_once ROOT_PATH . '/helpers/voucher.php';
        $vId = (int)($body['id'] ?? $_POST['id'] ?? 0);
        if ($vId <= 0) {
            jsonError('ID voucher không hợp lệ.');
        }
        // Kiểm tra xem đã phát sinh đơn hàng chưa
        $uCount = (int)$db->query("SELECT COUNT(*) FROM voucher_usages WHERE voucher_id = " . $vId)->fetchColumn();
        if ($uCount > 0) {
            // Khóa voucher thay vì xóa cứng để bảo toàn lịch sử hóa đơn
            $db->prepare("UPDATE vouchers SET is_active = 0 WHERE id = ?")->execute([$vId]);
            jsonSuccess('Voucher đã phát sinh đơn hàng nên đã được chuyển sang trạng thái KHÓA để bảo toàn dữ liệu.');
        } else {
            $db->prepare("DELETE FROM user_vouchers WHERE voucher_id = ?")->execute([$vId]);
            $db->prepare("DELETE FROM vouchers WHERE id = ?")->execute([$vId]);
            try { $db->prepare("DELETE FROM coupons WHERE id = ?")->execute([$vId]); } catch (Exception $e) {}
            jsonSuccess('Xóa voucher thành công!');
        }
        break;

    case 'toggle_voucher_status':
        $vId = (int)($body['id'] ?? $_POST['id'] ?? 0);
        if ($vId <= 0) {
            jsonError('ID voucher không hợp lệ.');
        }
        $curr = (int)$db->query("SELECT is_active FROM vouchers WHERE id = " . $vId)->fetchColumn();
        $newStatus = $curr ? 0 : 1;
        $db->prepare("UPDATE vouchers SET is_active = ? WHERE id = ?")->execute([$newStatus, $vId]);
        try { $db->prepare("UPDATE coupons SET is_active = ? WHERE id = ?")->execute([$newStatus, $vId]); } catch (Exception $e) {}
        jsonSuccess($newStatus ? 'Đã kích hoạt voucher.' : 'Đã khóa voucher.', ['is_active' => $newStatus]);
        break;

    case 'trigger_birthday_cron':
        require_once ROOT_PATH . '/helpers/voucher.php';
        $cronRes = runDailyBirthdayVouchers();
        jsonSuccess("Đã quét và tặng thành công {$cronRes['total_gifted']}/{$cronRes['total_candidates']} voucher sinh nhật.", $cronRes);
        break;

    // =========================================================================
    // PRODUCT REVIEWS MANAGEMENT ACTIONS (ADMIN & STAFF)
    // =========================================================================
    case 'get_product_reviews':
        require_once ROOT_PATH . '/helpers/reviews.php';
        ensureProductReviewsSchema($db);
        $pReviews = $db->query("
            SELECT pr.*, p.name AS product_name, p.image_url AS product_image, u.full_name AS customer_name
            FROM product_reviews pr
            LEFT JOIN products p ON pr.product_id = p.id
            LEFT JOIN users u ON pr.user_id = u.id
            ORDER BY pr.created_at DESC, pr.id DESC
            LIMIT 200
        ")->fetchAll(PDO::FETCH_ASSOC);
        jsonSuccess('Lấy danh sách đánh giá sản phẩm thành công!', ['product_reviews' => $pReviews]);
        break;

    case 'reply_product_review':
        require_once ROOT_PATH . '/helpers/reviews.php';
        ensureProductReviewsSchema($db);
        $revId = (int)($body['id'] ?? $body['review_id'] ?? 0);
        $reply = trim((string)($body['reply_content'] ?? ''));
        if ($revId <= 0 || $reply === '') {
            jsonError('Thiếu ID đánh giá hoặc nội dung phản hồi.');
        }
        $stmt = $db->prepare("UPDATE product_reviews SET reply_content = ?, replied_by = ?, replied_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$reply, (int)$currentUser['id'], $revId]);
        jsonSuccess('Đã gửi phản hồi tới khách hàng!', ['reply_content' => $reply]);
        break;

    case 'toggle_product_review':
        require_once ROOT_PATH . '/helpers/reviews.php';
        ensureProductReviewsSchema($db);
        $revId = (int)($body['id'] ?? $body['review_id'] ?? 0);
        if ($revId <= 0) jsonError('Thiếu ID đánh giá.');
        $row = $db->query("SELECT product_id, is_approved FROM product_reviews WHERE id = $revId LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$row) jsonError('Không tìm thấy đánh giá.', 404);
        $newVal = ((int)$row['is_approved'] === 1) ? 0 : 1;
        $db->prepare("UPDATE product_reviews SET is_approved = ? WHERE id = ?")->execute([$newVal, $revId]);
        recalculateProductRating($db, (int)$row['product_id']);
        jsonSuccess($newVal ? 'Đã hiển thị đánh giá.' : 'Đã ẩn đánh giá.', ['is_approved' => $newVal]);
        break;

    case 'delete_product_review':
        require_once ROOT_PATH . '/helpers/reviews.php';
        ensureProductReviewsSchema($db);
        $revId = (int)($body['id'] ?? $body['review_id'] ?? 0);
        if ($revId <= 0) jsonError('Thiếu ID đánh giá.');
        $prodId = (int)$db->query("SELECT product_id FROM product_reviews WHERE id = $revId LIMIT 1")->fetchColumn();
        $db->prepare("DELETE FROM product_reviews WHERE id = ?")->execute([$revId]);
        if ($prodId > 0) recalculateProductRating($db, $prodId);
        jsonSuccess('Đã xóa đánh giá sản phẩm!');
        break;

    case 'get_conversations':
    case 'admin_conversations':
        // Fallback an toàn cho hệ thống chat admin
        try {
            spl_autoload_register(function ($class) {
                $prefix = 'FastFood\\';
                $baseDir = ROOT_PATH . '/backend/src/';
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) return;
                $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
                if (file_exists($file)) require_once $file;
            });
            $convData = \FastFood\Services\AiChatService::getAdminConversations();
            $settings = \FastFood\Services\AiChatService::getChatSettings();
            $conversations = $convData['conversations'] ?? $convData;
            $totalUnread = isset($convData['totalUnread']) ? (int)$convData['totalUnread'] : \FastFood\Services\AiChatService::getTotalUnreadCount();
            jsonSuccess('Lấy danh sách hội thoại thành công!', [
                'conversations' => $conversations,
                'totalUnread'   => $totalUnread,
                'supportStatus' => $settings
            ]);
        } catch (Exception $e) {
            jsonSuccess('Danh sách hội thoại trống', ['conversations' => [], 'totalUnread' => 0]);
        }
        break;

    default:
        jsonError('Hành động không hợp lệ.');
}
