<?php
namespace FastFood\Services;

use FastFood\Config\Database;
use PDO;

/**
 * FastFood Intelligent AI Engine
 */
class AiService {
    public static function processPrompt(string $prompt, ?int $userId = null): array {
        $cleanPrompt = mb_strtolower(trim($prompt));
        $db = Database::getConnection();

        // 1. Trợ giúp / Giờ mở cửa / Liên hệ
        if (str_contains($cleanPrompt, 'giờ') || str_contains($cleanPrompt, 'mở cửa') || str_contains($cleanPrompt, 'liên hệ') || str_contains($cleanPrompt, 'hotline')) {
            return [
                'reply' => "FASTFOOD hoạt động từ 08:00 đến 23:00 tất cả các ngày trong tuần. Hotline hỗ trợ 24/7: 1900 6868 hoặc 0903 000 003. Bạn cần đặt món ăn nào hôm nay?",
                'suggestions' => ['Xem thực đơn', 'Combo tiết kiệm', 'Món dưới 50k']
            ];
        }

        // 2. Món dưới 50k
        if (str_contains($cleanPrompt, '50k') || str_contains($cleanPrompt, 'dưới 50') || str_contains($cleanPrompt, 'giá rẻ') || str_contains($cleanPrompt, 'tiết kiệm')) {
            $stmt = $db->query("SELECT name, base_price, discount_price FROM products WHERE base_price <= 50000 AND is_available = 1 ORDER BY base_price ASC LIMIT 4");
            $items = $stmt->fetchAll();
            $reply = "Dưới đây là một số món ngon giá dưới 50.000đ dành cho bạn:\n";
            foreach ($items as $it) {
                $p = number_format($it['discount_price'] ?: $it['base_price'], 0, ',', '.') . 'đ';
                $reply .= "• {$it['name']} - {$p}\n";
            }
            $reply .= "\nBạn muốn thêm món nào vào giỏ hàng?";
            return [
                'reply' => $reply,
                'suggestions' => ['Trà sữa', 'Burger', 'Cơm tấm']
            ];
        }

        // 3. Tra cứu tình trạng đơn hàng
        if (str_contains($cleanPrompt, 'đơn') || str_contains($cleanPrompt, 'giao hàng') || str_contains($cleanPrompt, 'vận chuyển')) {
            if ($userId) {
                $stmt = $db->prepare("SELECT order_code, total_amount, order_status FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$userId]);
                $lastOrder = $stmt->fetch();
                if ($lastOrder) {
                    $stVN = [
                        'placed' => 'Đang chờ xác nhận',
                        'preparing' => 'Đang chuẩn bị món',
                        'delivering' => 'Đang trên đường giao',
                        'completed' => 'Đã giao thành công'
                    ][$lastOrder['order_status']] ?? 'Đang xử lý';
                    return [
                        'reply' => "Đơn hàng gần nhất của bạn là #{$lastOrder['order_code']}, trị giá " . number_format($lastOrder['total_amount'], 0, ',', '.') . "đ. Trạng thái hiện tại: {$stVN}.",
                        'suggestions' => ['Xem chi tiết đơn', 'Đặt thêm món khác']
                    ];
                }
            }
            return [
                'reply' => "Bạn có thể kiểm tra trạng thái đơn hàng bất kỳ lúc nào tại mục 'Đơn Hàng' trên thanh điều hướng.",
                'suggestions' => ['Lịch sử đơn hàng', 'Khám phá món mới']
            ];
        }

        // 4. Tìm kiếm từ khóa món ăn
        $stmt = $db->prepare("SELECT name, base_price, discount_price FROM products WHERE is_available = 1 AND name LIKE ? ORDER BY id DESC LIMIT 3");
        $stmt->execute(['%' . $prompt . '%']);
        $matched = $stmt->fetchAll();
        if (!empty($matched)) {
            $reply = "FASTFOOD tìm thấy các món phù hợp với yêu cầu của bạn:\n";
            foreach ($matched as $it) {
                $p = number_format($it['discount_price'] ?: $it['base_price'], 0, ',', '.') . 'đ';
                $reply .= "• {$it['name']} ({$p})\n";
            }
            return [
                'reply' => $reply,
                'suggestions' => ['Xem giỏ hàng', 'Khuyến mãi hôm nay']
            ];
        }

        // 5. Mặc định
        return [
            'reply' => "Chào bạn! Tôi là Trợ lý AI FASTFOOD. Tôi có thể giúp bạn tìm món ăn ngon, gợi ý combo tiết kiệm hoặc tra cứu trạng thái đơn hàng. Bạn đang muốn ăn gì hôm nay?",
            'suggestions' => ['Gợi ý món ăn', 'Món dưới 50k', 'Xem ưu đãi']
        ];
    }
}

