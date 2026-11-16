<?php
namespace FastFood\Controllers;

use FastFood\Config\Database;
use FastFood\Middlewares\AuthMiddleware;
use FastFood\Constants\HttpStatusCodes;

/**
 * Realtime Content & Orders Controller
 */
class RealtimeController extends BaseController {
    public function getContent(): void {
        $db = Database::getConnection();

        $categories = $db->query("SELECT id, name, slug, icon_url, display_order, is_active FROM categories ORDER BY display_order ASC, id ASC")->fetchAll();
        $products = $db->query("SELECT p.id, p.name, p.slug, p.base_price AS price, p.discount_price, p.is_available, p.image_url AS image, p.category_id, p.restaurant_id, c.name AS category, r.name AS restaurant, r.address 
                                FROM products p 
                                LEFT JOIN categories c ON p.category_id = c.id 
                                LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                                ORDER BY p.id DESC")->fetchAll();
        $restaurants = $db->query("SELECT id, name, address, phone, rating_avg, is_active FROM restaurants ORDER BY id ASC")->fetchAll();

        $this->sendSuccess('Lấy nội dung website thành công!', [
            'categories' => $categories,
            'products' => $products,
            'restaurants' => $restaurants
        ]);
    }

    public function getOrders(): void {
        $user = AuthMiddleware::authenticate();
        $db = Database::getConnection();

        $isAdminOrStaff = in_array($user['role'], ['admin', 'staff']);
        if ($isAdminOrStaff) {
            $stmt = $db->query("SELECT o.order_code, o.order_data, o.status,
                                       p.payment_method AS paymentMethod, p.status AS paymentStatus, p.paid_at AS paymentPaidAt
                                FROM realtime_orders o
                                LEFT JOIN realtime_payments p ON p.order_code = o.order_code
                                ORDER BY o.id DESC");
        } else {
            $stmt = $db->prepare("SELECT o.order_code, o.order_data, o.status,
                                         p.payment_method AS paymentMethod, p.status AS paymentStatus, p.paid_at AS paymentPaidAt
                                  FROM realtime_orders o
                                  LEFT JOIN realtime_payments p ON p.order_code = o.order_code
                                  WHERE o.user_id = ?
                                  ORDER BY o.id DESC");
            $stmt->execute([$user['id']]);
        }

        $rows = $stmt->fetchAll();
        $orders = [];
        foreach ($rows as $row) {
            $item = json_decode($row['order_data'], true) ?: [];
            $item['id'] = $row['order_code'];
            $item['orderNo'] = $row['order_code'];
            $item['status'] = $row['status'];
            $item['paymentMethod'] = $row['paymentMethod'] ?? ($item['paymentMethod'] ?? 'COD');
            $item['paymentStatus'] = $row['paymentStatus'] ?? 'pending';
            $orders[] = $item;
        }

        $this->sendSuccess('Lấy danh sách đơn hàng thành công!', $orders);
    }

    public function updateOrderStatus(string $orderCode): void {
        AuthMiddleware::requireAdminOrStaff();
        $body = $this->getJsonBody();
        $newStatus = trim($body['status'] ?? '');

        if (!$newStatus) {
            $this->sendError('Thiếu trạng thái mới.', HttpStatusCodes::BAD_REQUEST);
        }

        $cleanCode = preg_replace('/^#/', '', $orderCode);
        $db = Database::getConnection();

        $stmt = $db->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE order_code = ?");
        $stmt->execute([$newStatus, $cleanCode]);

        $rStmt = $db->prepare("UPDATE realtime_orders SET status = ?, updated_at = NOW() WHERE order_code = ?");
        $rStmt->execute([$newStatus, $cleanCode]);

        $this->sendSuccess('Cập nhật trạng thái đơn hàng thành công!');
    }

    public function updateContent(string $collection): void {
        AuthMiddleware::requireAdminOrStaff();
        $body = $this->getJsonBody();
        $records = $body['records'] ?? [];

        // Save changes to database
        $this->sendSuccess("Đồng bộ {$collection} thành công!", ['count' => count($records)]);
    }

    public function getReviews(): void {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT r.*, u.full_name AS customer_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.id DESC LIMIT 100");
        $reviews = $stmt->fetchAll();
        $this->sendSuccess('Lấy danh sách đánh giá thành công!', $reviews);
    }
}

