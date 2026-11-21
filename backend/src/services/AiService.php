<?php
namespace FastFood\Services;

use FastFood\Config\Database;
use PDO;

/**
 * FastFood Intelligent AI Engine & Customer Support Bot
 * Tự động phân tích câu hỏi khách hàng bằng xử lý ngôn ngữ tự nhiên (NLP tiếng Việt),
 * tra cứu thực đơn CSDL theo khoảng giá, thể loại, món ăn bán chạy, đơn hàng & chính sách
 */
class AiService {
    /**
     * Xử lý câu hỏi của khách hàng và sinh câu trả lời thông minh
     */
    public static function processPrompt(string $prompt, ?int $userId = null): array {
        $rawPrompt = trim($prompt);
        $clean = mb_strtolower($rawPrompt, 'UTF-8');
        $db = Database::getConnection();

        // 0. CHÀO HỎI / XÃ GIAO / LỜI CẢM ƠN (Ngắn gọn, thân thiện, lịch sự)
        $greetingWords = ['hello', 'hi', 'hey', 'alo', 'alô', 'chào', 'chào bạn', 'chào shop', 'chào ad', 'chào admin', 'shop ơi', 'ad ơi', 'admin ơi', 'hé lô', 'halo', 'hế nhô', 'bạn ơi'];
        if (in_array($clean, $greetingWords, true) ||
            preg_match('/^(?:hello|hi|hey|alo|alô|chào|chào bạn|chào shop|hé lô|halo|hế nhô|shop ơi|ad ơi|admin ơi|bạn ơi)\b/iu', $clean)) {
            return [
                'reply'       => "Dạ FASTFOOD xin chào bạn! Rất vui được hỗ trợ bạn hôm nay 💖\nBạn đang cần tìm món ăn ngon, xem khuyến mãi hay tra cứu đơn hàng nào ạ?",
                'products'    => [],
                'suggestions' => ['🍜 Hôm nay ăn gì?', '💰 Món nào dưới 50.000đ?', '🎁 Có combo nào tiết kiệm không?', '📦 Tra cứu đơn hàng']
            ];
        }

        // Lời cảm ơn / Tạm biệt
        if (preg_match('/^(?:cảm ơn|cam on|thanks|thank you|cảm ơn shop|cám ơn|tks|dạ cảm ơn)\b/iu', $clean)) {
            return [
                'reply'       => "Dạ không có gì ạ! Chúc bạn có một bữa ăn thật ngon miệng và một ngày ngập tràn niềm vui cùng FASTFOOD nhé! ❤️ Cần hỗ trợ thêm bất cứ món gì, bạn cứ nhắn mình ngay nhé!",
                'products'    => [],
                'suggestions' => ['🍜 Hôm nay ăn gì?', '💰 Món nào dưới 50.000đ?', '📦 Tra cứu đơn hàng']
            ];
        }

        // 1. TRA CỨU ĐƠN HÀNG (Qua mã đơn hàng hoặc lịch sử đặt gần nhất)
        if (preg_match('/(?:FF[-_]?[A-Z0-9]+|#\d{3,})/i', $rawPrompt, $matches) ||
            str_contains($clean, 'kiểm tra đơn') || str_contains($clean, 'đơn hàng của tôi') ||
            str_contains($clean, 'tình trạng đơn') || str_contains($clean, 'tra cứu đơn') ||
            str_contains($clean, 'đơn hàng') || str_contains($clean, 'shipper đến đâu')) {

            $orderCode = null;
            if (!empty($matches[0])) {
                $orderCode = ltrim($matches[0], '#');
            }

            $order = null;
            if ($orderCode) {
                $stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ? OR order_code LIKE ? LIMIT 1");
                $stmt->execute([$orderCode, "%{$orderCode}%"]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif ($userId) {
                $stmt = $db->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$userId]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($order) {
                $statusMap = [
                    'placed'     => 'Đang chờ xác nhận',
                    'confirmed'  => 'Đã xác nhận đơn',
                    'preparing'  => 'Nhà bếp đang chế biến nóng sốt',
                    'delivering' => 'Shipper đang trên đường giao (15-20 phút)',
                    'completed'  => 'Đã giao thành công',
                    'delivered'  => 'Đã giao thành công',
                    'cancelled'  => 'Đã hủy'
                ];
                $stName = $statusMap[$order['order_status']] ?? 'Đang xử lý';
                $totalFmt = number_format((float)$order['total_amount'], 0, ',', '.') . 'đ';
                $orderDate = !empty($order['created_at']) ? date('H:i d/m/Y', strtotime($order['created_at'])) : 'Gần đây';

                $reply = "📦 **Thông tin đơn hàng #{$order['order_code']}:**\n"
                    . "• Trạng thái hiện tại: **{$stName}**\n"
                    . "• Tổng thanh toán: **{$totalFmt}**\n"
                    . "• Địa chỉ giao hàng: {$order['delivery_address']}\n"
                    . "• Người nhận: {$order['recipient_name']} ({$order['recipient_phone']})\n"
                    . "• Thời gian đặt: {$orderDate}\n\n"
                    . "Bạn bấm vào thẻ đơn hàng bên dưới để xem chi tiết lộ trình và hóa đơn nhé!";

                return [
                    'reply'       => $reply,
                    'order'       => [
                        'orderCode'     => $order['order_code'],
                        'status'        => $order['order_status'],
                        'displayStatus' => $stName,
                        'totalAmount'   => (float)$order['total_amount'],
                        'isAuthorized'  => true,
                        'found'         => true
                    ],
                    'products'    => [],
                    'suggestions' => ['Hôm nay ăn gì?', 'Món nào dưới 50.000đ?', 'Hotline hỗ trợ']
                ];
            } else {
                if ($userId) {
                    return [
                        'reply'       => "Dạ hiện tại bạn chưa có đơn hàng nào phát sinh trên hệ thống FASTFOOD. Bạn hãy dạo quanh thực đơn và chọn món ăn ngon để chúng mình phục vụ bạn ngay nhé! 💖",
                        'products'    => self::getFeaturedProducts($db, 4),
                        'suggestions' => ['🍜 Hôm nay ăn gì?', '💰 Món nào dưới 50.000đ?', '🎁 Có combo nào tiết kiệm không?']
                    ];
                } else {
                    return [
                        'reply'       => "Để kiểm tra đơn hàng, bạn vui lòng nhập **Mã đơn hàng** (Ví dụ: `FF-123456`) hoặc [Đăng nhập](login.php) tài khoản để theo dõi đơn trực tiếp nhé!",
                        'suggestions' => ['Đăng nhập tài khoản', 'Hôm nay ăn gì?', 'Liên hệ hỗ trợ']
                    ];
                }
            }
        }

        // 2. COMBO & KHUYẾN MÃI / VOUCHER GIẢM GIÁ
        if (str_contains($clean, 'combo') || str_contains($clean, 'khuyến mãi') || str_contains($clean, 'khuyen mai') ||
            str_contains($clean, 'voucher') || str_contains($clean, 'ưu đãi') || str_contains($clean, 'uu dai') ||
            str_contains($clean, 'giảm giá') || str_contains($clean, 'giam gia') ||
            str_contains($clean, 'mã giảm') || str_contains($clean, 'ma giam') || str_contains($clean, 'quà tặng') || str_contains($clean, 'flash sale')) {

            $stmt = $db->query("
                SELECT id, name, base_price, discount_price, image_url, description, is_featured 
                FROM products 
                WHERE is_available = 1 AND (
                    LOWER(name) LIKE '%combo%' OR 
                    LOWER(name) LIKE '%set%' OR 
                    (discount_price > 0 AND discount_price < base_price)
                )
                ORDER BY id DESC LIMIT 4
            ");
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($items)) {
                $items = self::getFeaturedProductsRaw($db, 4);
            }
            $formatted = self::formatProducts($items);

            $reply = "🎁 **Chương trình ưu đãi bùng nổ hôm nay tại FASTFOOD:**\n\n"
                . "• **Voucher Bạn Mới:** Giảm trực tiếp **50%** cho đơn đầu tiên (Mã: **NEW50**) tự động áp dụng tại giỏ hàng!\n"
                . "• **Freeship 0đ:** Miễn phí giao hàng toàn thành phố cho đơn từ 100.000đ (bán kính 5km).\n"
                . "• **Combo tiết kiệm:** Mua món chính kèm nước hoặc khoai tây chiên giảm sâu đến 25%!\n\n"
                . "Dưới đây là một số Combo & Món ưu đãi đặc sắc dành riêng cho bạn:";

            return [
                'reply'       => $reply,
                'products'    => $formatted,
                'suggestions' => ['💰 Món nào dưới 50.000đ?', '🛒 Tôi muốn đặt đồ ăn', '📦 Kiểm tra đơn hàng']
            ];
        }

        // 3. TÌM MÓN THEO NGÂN SÁCH / GIÁ TIỀN (Ví dụ: "Món nào dưới 50.000đ?", "dưới 30k", "sinh viên", "giá rẻ")
        $hasBudgetIntent = (
            preg_match('/(?:dưới|duoi|tầm|tam|khoảng|khoang|giá|gia|tối đa|toi da|không quá|khong qua|<)\s*(\d+(?:[.,]\d+)?)\s*(k|kđ|đ|d|vnd|nghìn|ngan|ngàn|000)?/iu', $clean, $priceMatches) ||
            preg_match('/(\d+)\s*(?:k|000)\s*(?:trở xuống|tro xuong|đổ lại|do lai)/iu', $clean, $priceMatches) ||
            str_contains($clean, '50k') || str_contains($clean, 'dưới 50') || str_contains($clean, 'duoi 50') ||
            str_contains($clean, 'giá rẻ') || str_contains($clean, 'gia re') ||
            str_contains($clean, 'tiết kiệm') || str_contains($clean, 'tiet kiem') ||
            str_contains($clean, 'sinh viên') || str_contains($clean, 'sinh vien') ||
            str_contains($clean, 'hạt dẻ') || str_contains($clean, 'hat de') ||
            str_contains($clean, 'món rẻ') || str_contains($clean, 'mon re')
        );

        if ($hasBudgetIntent) {
            $maxPrice = 50000; // Mặc định 50.000đ nếu không ghi rõ số
            if (!empty($priceMatches[1])) {
                $rawVal = str_replace(['.', ','], '', $priceMatches[1]);
                $num = (float)$rawVal;
                $unit = mb_strtolower($priceMatches[2] ?? '', 'UTF-8');
                if ($unit === 'k' || $unit === 'kđ' || $unit === 'nghìn' || $unit === 'ngàn') {
                    $maxPrice = (int)($num * 1000);
                } elseif ($num < 1000) {
                    $maxPrice = (int)($num * 1000); // Vd: nhập "dưới 50" -> 50.000đ
                } else {
                    $maxPrice = (int)$num;
                }
            }

            // Truy vấn các món ăn có giá hữu hiệu <= maxPrice
            // Ưu tiên các món ngon, món chính (Burger, Gà, Cơm, Bánh mì cuộn, Trà sữa)
            $stmt = $db->prepare("
                SELECT id, name, base_price, discount_price, image_url, description, is_featured 
                FROM products 
                WHERE is_available = 1 AND (
                    (discount_price > 0 AND discount_price <= ?) OR 
                    ((discount_price IS NULL OR discount_price = 0) AND base_price <= ?)
                )
                ORDER BY is_featured DESC, base_price DESC 
                LIMIT 4
            ");
            $stmt->execute([$maxPrice, $maxPrice]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) {
                // Nếu khoảng giá quá thấp, tìm các món rẻ nhất hiện có trong quán
                $stmt = $db->query("
                    SELECT id, name, base_price, discount_price, image_url, description, is_featured 
                    FROM products 
                    WHERE is_available = 1 
                    ORDER BY (CASE WHEN discount_price > 0 AND discount_price < base_price THEN discount_price ELSE base_price END) ASC 
                    LIMIT 4
                ");
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $formatted = self::formatProducts($items);
            $maxPriceFmt = number_format($maxPrice, 0, ',', '.') . 'đ';

            $reply = "💰 FASTFOOD gợi ý các món ngon **giá sinh viên dưới {$maxPriceFmt}** siêu ngon dành cho bạn đây ạ:\n\n";
            foreach ($formatted as $i => $it) {
                $reply .= ($i + 1) . ". **{$it['name']}** – " . number_format($it['price'], 0, ',', '.') . "đ\n";
            }
            $reply .= "\nĐặc biệt đơn từ 100K được **Freeship 0đ** toàn thành phố! Bạn bấm **+ Giỏ** để thêm món ngay nhé! 🛵";

            return [
                'reply'       => $reply,
                'products'    => $formatted,
                'suggestions' => ['🍜 Hôm nay ăn gì?', '🎁 Có combo nào tiết kiệm không?', '🛒 Tôi muốn đặt đồ ăn']
            ];
        }

        // 4. MÓN BÁN CHẠY / HÔM NAY ĂN GÌ / GỢI Ý MÓN NGON
        if (str_contains($clean, 'hôm nay ăn gì') || str_contains($clean, 'hom nay an gi') ||
            str_contains($clean, 'bán chạy') || str_contains($clean, 'ban chay') ||
            str_contains($clean, 'món gì ngon') || str_contains($clean, 'gợi ý') || str_contains($clean, 'goi y') ||
            str_contains($clean, 'món hot') || str_contains($clean, 'mon hot') ||
            str_contains($clean, 'món nào ngon') || str_contains($clean, 'best seller') ||
            str_contains($clean, 'món đặc sắc') || str_contains($clean, 'recommend')) {

            $formatted = self::getFeaturedProducts($db, 4);
            $reply = "🔥 Top món **bán chạy & được yêu thích nhất** tại FASTFOOD Buôn Ma Thuột hôm nay:\n\n";
            foreach ($formatted as $i => $it) {
                $reply .= ($i + 1) . ". **{$it['name']}** – " . number_format($it['price'], 0, ',', '.') . "đ\n";
            }
            $reply .= "\nTất cả món đều được nhà bếp chế biến nóng giòn ngay khi bạn đặt. Bạn muốn thưởng thức món nào hôm nay?";

            return [
                'reply'       => $reply,
                'products'    => $formatted,
                'suggestions' => ['💰 Món nào dưới 50.000đ?', '🎁 Có combo nào tiết kiệm không?', '🛒 Tôi muốn đặt đồ ăn']
            ];
        }

        // 5. GIỜ HOẠT ĐỘNG / ĐỊA CHỈ / HOTLINE / LIÊN HỆ
        if (str_contains($clean, 'giờ') || str_contains($clean, 'mở cửa') || str_contains($clean, 'đóng cửa') ||
            str_contains($clean, 'địa chỉ') || str_contains($clean, 'ở đâu') || str_contains($clean, 'hotline') ||
            str_contains($clean, 'số điện thoại') || str_contains($clean, 'sdt') || str_contains($clean, 'liên hệ') ||
            str_contains($clean, 'chi nhánh') || str_contains($clean, 'quán ở đâu')) {
            return [
                'reply' => "🏠 **FASTFOOD Buôn Ma Thuột** luôn sẵn sàng phục vụ bạn:\n\n"
                    . "• **Địa chỉ quán:** 16 Hoàng Diệu, Phường Thắng Lợi, TP. Buôn Ma Thuột, Đắk Lắk\n"
                    . "• **Giờ mở cửa:** **06:00 – 23:30** hàng ngày (kể cả Thứ 7, CN và ngày Lễ)\n"
                    . "• **Tổng đài đặt món 24/7:** **1900 8888** hoặc **0903 000 003**\n"
                    . "• **Cam kết giao nhanh:** Đồ ăn luôn nóng hổi đến tay bạn chỉ trong 15 - 20 phút!",
                'products'    => [],
                'suggestions' => ['🍜 Hôm nay ăn gì?', '💰 Món nào dưới 50.000đ?', '🛒 Tôi muốn đặt đồ ăn']
            ];
        }

        // 6. GIAO HÀNG / PHÍ SHIP / TỐC ĐỘ GIAO
        if (str_contains($clean, 'phí ship') || str_contains($clean, 'phi ship') ||
            str_contains($clean, 'freeship') || str_contains($clean, 'ship bao nhiêu') ||
            str_contains($clean, 'giao hàng trong bao lâu') || str_contains($clean, 'bao lâu nhận được') ||
            str_contains($clean, 'có giao tận nơi') || str_contains($clean, 'giao tận nhà')) {
            return [
                'reply' => "🛵 **Chính sách giao hàng siêu tốc tại FASTFOOD:**\n\n"
                    . "• **Thời gian giao hàng:** Chỉ từ **15 – 20 phút** kể từ lúc bấm đặt hàng.\n"
                    . "• **Biểu phí vận chuyển:**\n"
                    . "  - Bán kính dưới 3km: **15.000đ** (cố định).\n"
                    . "  - Bán kính 3km – 10km: 15.000đ + 5.000đ/km tiếp theo.\n"
                    . "  - **Freeship 0đ:** Miễn phí giao hàng cho mọi đơn từ **100.000đ**!\n"
                    . "• **Bảo quản nhiệt:** Đồ ăn được đóng gói trong túi giữ nhiệt chuyên dụng, đảm bảo giòn rụm và nóng sốt khi nhận!",
                'products'    => [],
                'suggestions' => ['🍜 Hôm nay ăn gì?', '💰 Món nào dưới 50.000đ?', '🛒 Tôi muốn đặt đồ ăn']
            ];
        }

        // 7. HƯỚNG DẪN ĐẶT HÀNG / THANH TOÁN (COD, VIETQR SEPAY)
        if (str_contains($clean, 'đặt đồ ăn') || str_contains($clean, 'đặt món') || str_contains($clean, 'cách đặt') ||
            str_contains($clean, 'thanh toán') || str_contains($clean, 'chuyển khoản') || str_contains($clean, 'quét mã') ||
            str_contains($clean, 'vietqr') || str_contains($clean, 'tiền mặt') || str_contains($clean, 'cod') ||
            str_contains($clean, 'sepay') || str_contains($clean, 'ngân hàng')) {
            return [
                'reply' => "🛒 **Hướng dẫn đặt hàng siêu tốc tại FASTFOOD:**\n\n"
                    . "1. Chọn món ăn yêu thích từ Thực Đơn hoặc Khung Chat và nhấn **+ Giỏ**.\n"
                    . "2. Nhấp vào biểu tượng **Giỏ Hàng** ở góc trên, kiểm tra món và nhấn **Thanh Toán**.\n"
                    . "3. Nhập địa chỉ giao hàng và chọn phương thức thanh toán:\n"
                    . "   • **Tiền mặt khi nhận hàng (COD)**\n"
                    . "   • **Chuyển khoản Quét mã VietQR SePay tự động** (xác nhận đơn trong 3 giây!)\n"
                    . "4. Bấm **Đặt Hàng** và theo dõi lộ trình shipper giao nóng hổi trong 20 phút!",
                'products'    => [],
                'suggestions' => ['🍜 Hôm nay ăn gì?', '💰 Món nào dưới 50.000đ?', 'Xem thực đơn']
            ];
        }

        // 8. TÌM KIẾM THEO LOẠI MÓN / THỰC ĐƠN CỤ THỂ (Cơm, Gà, Burger, Pizza, Mì Ý, Trà sữa, Ăn vặt, v.v.)
        $categoriesMap = [
            'Cơm & Món Việt' => [
                'keywords' => ['cơm', 'com', 'cơm tấm', 'sườn', 'cơm gà', 'gạo lứt', 'cơm chiên', 'phở'],
                'category_slugs' => ['com', 'com-mon-viet']
            ],
            'Burger & Gà Rán' => [
                'keywords' => ['burger', 'gà', 'ga', 'gà rán', 'cánh gà', 'đùi gà', 'hamburger', 'bánh mì cuộn', 'wrap', 'bò nướng'],
                'category_slugs' => ['burger', 'burger-ga-ran']
            ],
            'Pizza & Mỳ Ý' => [
                'keywords' => ['pizza', 'mì ý', 'mỳ ý', 'spaghetti', 'pasta', 'bò bằm'],
                'category_slugs' => ['pizza', 'pizza-my-y']
            ],
            'Trà Sữa & Đồ Uống' => [
                'keywords' => ['trà', 'tra', 'trà sữa', 'trà đào', 'trà chanh', 'trà vải', 'cà phê', 'cafe', 'nước ép', 'nước ngọt', 'coca', 'pepsi', 'đồ uống', 'thức uống'],
                'category_slugs' => ['tra-sua', 'tra-sua-do-uong', 'cafe']
            ],
            'Ăn Vặt & Tráng Miệng' => [
                'keywords' => ['khoai', 'khoai tây', 'phô mai que', 'bánh', 'tráng miệng', 'chè', 'kem', 'tàu hũ', 'ăn vặt', 'xúc xích', 'quẩy'],
                'category_slugs' => ['an-vat', 'an-vat-trang-mieng']
            ],
            'Salad & Healthy' => [
                'keywords' => ['salad', 'healthy', 'ăn kiêng', 'eat clean', 'rau'],
                'category_slugs' => ['healthy']
            ],
            'Sushi & Món Nhật' => [
                'keywords' => ['sushi', 'sashimi', 'teriyaki', 'món nhật'],
                'category_slugs' => ['sushi']
            ]
        ];

        foreach ($categoriesMap as $catTitle => $config) {
            foreach ($config['keywords'] as $kw) {
                if (str_contains($clean, $kw)) {
                    $stmt = $db->prepare("
                        SELECT id, name, base_price, discount_price, image_url, description, is_featured 
                        FROM products 
                        WHERE is_available = 1 AND (LOWER(name) LIKE ? OR LOWER(description) LIKE ?)
                        ORDER BY is_featured DESC, id DESC LIMIT 4
                    ");
                    $stmt->execute(["%{$kw}%", "%{$kw}%"]);
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($items)) {
                        $formatted = self::formatProducts($items);
                        $reply = "🍽️ FASTFOOD tìm thấy các món **{$catTitle}** tuyệt ngon cho bạn:\n\n";
                        foreach ($formatted as $i => $it) {
                            $reply .= ($i + 1) . ". **{$it['name']}** – " . number_format($it['price'], 0, ',', '.') . "đ\n";
                        }
                        $reply .= "\nBạn bấm **+ Giỏ** để thêm ngay vào giỏ hàng nhé!";

                        return [
                            'reply'       => $reply,
                            'products'    => $formatted,
                            'suggestions' => ['💰 Món nào dưới 50.000đ?', '🍜 Hôm nay ăn gì?', '🛒 Tôi muốn đặt đồ ăn']
                        ];
                    }
                }
            }
        }

        // 9. TÌM KIẾM THEO TỪ KHÓA TỰ DO TRONG BẢNG PRODUCTS
        $words = array_filter(explode(' ', $clean), fn($w) => mb_strlen($w, 'UTF-8') >= 2);
        if (!empty($words)) {
            $likeClauses = [];
            $params = [];
            foreach (array_slice($words, 0, 4) as $w) {
                $likeClauses[] = '(LOWER(name) LIKE ? OR LOWER(description) LIKE ?)';
                $params[] = "%{$w}%";
                $params[] = "%{$w}%";
            }
            $whereSql = implode(' OR ', $likeClauses);

            $stmt = $db->prepare("
                SELECT id, name, base_price, discount_price, image_url, description, is_featured 
                FROM products 
                WHERE is_available = 1 AND ({$whereSql})
                ORDER BY is_featured DESC, id DESC LIMIT 4
            ");
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($items)) {
                $formatted = self::formatProducts($items);
                $reply = "FASTFOOD tìm thấy các món ăn phù hợp với tìm kiếm của bạn:\n\n";
                foreach ($formatted as $i => $it) {
                    $reply .= ($i + 1) . ". **{$it['name']}** – " . number_format($it['price'], 0, ',', '.') . "đ\n";
                }
                $reply .= "\nBạn bấm **+ Giỏ** để thêm món yêu thích nhé!";

                return [
                    'reply'       => $reply,
                    'products'    => $formatted,
                    'suggestions' => ['🍜 Hôm nay ăn gì?', '💰 Món nào dưới 50.000đ?', '🎁 Có combo nào tiết kiệm không?']
                ];
            }
        }

        // 10. PHẢN HỒI THÔNG MINH MẶC ĐỊNH
        return [
            'reply'       => "Dạ FASTFOOD luôn sẵn sàng hỗ trợ bạn! Bạn muốn tìm món ăn ngon theo sở thích, săn ưu đãi giảm giá hay tra cứu đơn hàng vừa đặt ạ? Bạn có thể bấm chọn gợi ý bên dưới hoặc nhắn tên món bạn muốn tìm nhé 💖",
            'products'    => self::getFeaturedProducts($db, 4),
            'suggestions' => ['🍜 Hôm nay ăn gì?', '💰 Món nào dưới 50.000đ?', '🎁 Có combo nào tiết kiệm không?', '📦 Tra cứu đơn hàng']
        ];
    }

    /**
     * Gợi ý câu trả lời AI dành cho Admin Copilot
     */
    public static function suggestAdminReply(string $customerMessage, string $customerName = 'Khách hàng'): array {
        $aiRes = self::processPrompt($customerMessage);
        $reply = $aiRes['reply'] ?? '';
        $intent = 'Tư vấn thông tin món ăn';

        $clean = mb_strtolower($customerMessage, 'UTF-8');
        if (str_contains($clean, 'đơn') || str_contains($clean, 'giao')) {
            $intent = 'Tra cứu đơn hàng';
        } elseif (str_contains($clean, '50k') || str_contains($clean, 'rẻ') || str_contains($clean, 'combo') || str_contains($clean, 'giá')) {
            $intent = 'Tư vấn giá & khuyến mãi';
        } elseif (str_contains($clean, 'giờ') || str_contains($clean, 'địa chỉ') || str_contains($clean, 'hotline')) {
            $intent = 'Thông tin quán & liên hệ';
        }

        return [
            'suggestedReply' => $reply,
            'suggestion'     => $reply,
            'intentLabel'    => $intent,
            'intent'         => $intent,
            'suggestions'    => $aiRes['suggestions'] ?? []
        ];
    }

    /**
     * Xử lý câu hỏi và phân tích dữ liệu nghiệp vụ dành riêng cho Admin AI Copilot
     */
    public static function processAdminPrompt(string $prompt, array $admin = []): array {
        $clean = mb_strtolower(trim($prompt), 'UTF-8');
        $db = Database::getConnection();

        // 1. Phân tích doanh thu & vận hành & đơn hàng
        if (str_contains($clean, 'doanh thu') || str_contains($clean, 'báo cáo') || str_contains($clean, 'thống kê') || str_contains($clean, 'giờ cao điểm') || str_contains($clean, 'đơn hôm nay') || str_contains($clean, 'tình hình')) {
            $todayStats = ['total' => 0, 'revenue' => 0, 'pending' => 0, 'delivered' => 0];
            try {
                $stmt = $db->query("
                    SELECT 
                        COUNT(*) AS total_orders,
                        COALESCE(SUM(CASE WHEN order_status != 'cancelled' THEN total_amount ELSE 0 END), 0) AS revenue,
                        SUM(CASE WHEN order_status = 'placed' THEN 1 ELSE 0 END) AS pending_orders,
                        SUM(CASE WHEN order_status = 'delivered' OR order_status = 'completed' THEN 1 ELSE 0 END) AS delivered_orders
                    FROM orders 
                    WHERE DATE(created_at) = CURDATE()
                ");
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($r) {
                    $todayStats['total'] = (int)($r['total_orders'] ?? 0);
                    $todayStats['revenue'] = (float)($r['revenue'] ?? 0);
                    $todayStats['pending'] = (int)($r['pending_orders'] ?? 0);
                    $todayStats['delivered'] = (int)($r['delivered_orders'] ?? 0);
                }
            } catch (\Exception $e) {}

            $revText = number_format($todayStats['revenue'], 0, ',', '.') . 'đ';
            $reply = "📊 **BÁO CÁO DOANH THU & PHÂN TÍCH VẬN HÀNH HÔM NAY:**\n\n"
                   . "• **Tổng đơn hàng phát sinh hôm nay:** **{$todayStats['total']} đơn**\n"
                   . "• **Doanh thu thực nhận hôm nay:** **{$revText}**\n"
                   . "• **Đơn đang chờ xử lý:** **{$todayStats['pending']} đơn**\n"
                   . "• **Đơn đã giao thành công:** **{$todayStats['delivered']} đơn**\n\n"
                   . "⏰ **Khung giờ cao điểm tại Buôn Ma Thuột:**\n"
                   . "1. **Trưa (11:00 – 13:00):** Tỉ trọng 45% lượng đơn, nhóm văn phòng & sinh viên chọn các combo cơm, mì cay, burger.\n"
                   . "2. **Chiều tối (17:30 – 20:30):** Tỉ trọng 40% lượng đơn, nhóm gia đình & giới trẻ đặt gà rán giòn rụm, pizza, trà sữa.\n\n"
                   . "💡 **Khuyến nghị AI cho Quản lý:** Hãy chuẩn bị nguyên liệu trước 10:30 sáng và bố trí tối thiểu 2 shipper trực vùng trung tâm để giữ thời gian giao dưới 20 phút.";

            return [
                'reply' => $reply,
                'suggestions' => [
                    'Chính sách phí giao hàng & hủy đơn',
                    'Top 5 món bán chạy nhất tuần',
                    'Soạn thông báo ưu đãi cuối tuần'
                ]
            ];
        }

        // 2. Chính sách phí ship & hủy đơn (quy tắc 3%)
        if (str_contains($clean, 'phí') || str_contains($clean, 'hủy') || str_contains($clean, 'ship') || str_contains($clean, '3%') || str_contains($clean, 'chính sách')) {
            $reply = "📋 **QUY TẮC TÍNH PHÍ GIAO HÀNG & CHÍNH SÁCH HỦY ĐƠN FASTFOOD:**\n\n"
                   . "1. **Biểu phí vận chuyển tiêu chuẩn:**\n"
                   . "   • Dưới 3km: **15.000đ** (cố định).\n"
                   . "   • Từ 3km – 10km: **15.000đ + 5.000đ/km tiếp theo**.\n"
                   . "   • Miễn phí giao hàng (Freeship) cho đơn từ 100.000đ hoặc áp mã voucher FREESHIP.\n\n"
                   . "2. **Quy tắc điều chỉnh 3% khi khách hủy đơn:**\n"
                   . "   • Khi khách hàng hủy đơn sau khi nhà hàng đã xác nhận bếp nấu, hệ thống tự động ghi nhận **phí phạt cam kết 3%** trên tổng giá trị đơn để bù đắp chi phí nguyên liệu và công tài xế đã điều phối.\n"
                   . "   • Trường hợp lỗi do nhà hàng (hết món) hoặc shipper không thể giao: Khách hàng được hoàn tiền 100% kèm voucher bù đắp 20K.\n\n"
                   . "3. **Thời gian xử lý hoàn tiền:**\n"
                   . "   • Ví điện tử / Thẻ ATM: Trong vòng 24h – 48h làm việc.";

            return [
                'reply' => $reply,
                'suggestions' => [
                    'Cách xử lý khiếu nại khách hàng 1 sao',
                    'Phân tích tình hình doanh thu hôm nay',
                    'Soạn tin nhắn khuyến mãi 50K'
                ]
            ];
        }

        // 3. Soạn tin nhắn khuyến mãi / Marketing
        if (str_contains($clean, 'soạn') || str_contains($clean, 'khuyến mãi') || str_contains($clean, 'voucher') || str_contains($clean, 'giảm giá') || str_contains($clean, '50k') || str_contains($clean, 'marketing')) {
            $reply = "✍️ **MẪU TIN NHẮN MARKETING & KHUYẾN MÃI CUỐI TUẦN CHUYÊN NGHIỆP:**\n\n"
                   . "🎉 **BÙNG NỔ TIỆC NGON CUỐI TUẦN – GIẢM NGAY 50K CÙNG FASTFOOD!** 🍔🔥\n\n"
                   . "Cuối tuần thảnh thơi, không cần vào bếp vì đã có FASTFOOD lo trọn từ A-Z! Hàng loạt món ngon nóng hổi đang chờ bạn:\n"
                   . "👉 Nhập mã: **CUOITUAN50** – Giảm ngay **50.000đ** cho đơn từ 150.000đ.\n"
                   . "👉 Miễn phí ship 3km đầu tiên cho tất cả combo Burger & Gà Rán Giòn Rụm.\n"
                   . "⚡ Giao siêu tốc chỉ 15 - 20 phút, đồ ăn luôn nóng hổi trên tay!\n\n"
                   . "📲 Mở app đặt ngay hôm nay: [fastfood.vn](http://localhost:5000)\n"
                   . "Chúc quý khách có bữa ăn thật ngon miệng bên người thân yêu! ❤️";

            return [
                'reply' => $reply,
                'suggestions' => [
                    'Phân tích doanh thu hôm nay',
                    'Top 5 món bán chạy nhất tuần',
                    'Cách xử lý khiếu nại khách hàng 1 sao'
                ]
            ];
        }

        // 4. Món bán chạy / Thực đơn / Best sellers
        if (str_contains($clean, 'bán chạy') || str_contains($clean, 'món') || str_contains($clean, 'thực đơn') || str_contains($clean, 'best seller') || str_contains($clean, 'top')) {
            $topItems = [];
            try {
                $stmt = $db->query("
                    SELECT name, base_price, discount_price 
                    FROM products 
                    WHERE is_available = 1 
                    ORDER BY is_featured DESC, id ASC 
                    LIMIT 5
                ");
                $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {}

            $reply = "🍔 **DANH SÁCH MÓN ĂN BÁN CHẠY NHẤT HỆ THỐNG FASTFOOD:**\n\n";
            if (!empty($topItems)) {
                foreach ($topItems as $i => $item) {
                    $p = number_format((float)$item['base_price'], 0, ',', '.') . 'đ';
                    $reply .= ($i + 1) . ". **{$item['name']}** – Giá: {$p}\n";
                }
            }

            return [
                'reply' => $reply,
                'suggestions' => [
                    'Phân tích doanh thu hôm nay',
                    'Soạn tin nhắn khuyến mãi cuối tuần',
                    'Chính sách phí giao hàng & hủy đơn'
                ]
            ];
        }

        // 5. Phản hồi chung hỗ trợ nghiệp vụ quản trị
        return [
            'reply' => "Xin chào **Quản trị viên**! Tôi là **FASTFOOD AI Co-pilot** luôn sẵn sàng hỗ trợ bạn.\n\n"
                     . "Tôi có thể phân tích số liệu kinh doanh, tra cứu chính sách hệ thống, kiểm tra các món bán chạy hoặc soạn thảo tin nhắn chăm sóc khách hàng và khuyến mãi.\n\n"
                     . "Bạn hãy bấm chọn một trong các gợi ý bên dưới hoặc đặt câu hỏi trực tiếp nhé! 🚀",
            'suggestions' => [
                'Phân tích doanh thu & Giờ cao điểm',
                'Top 5 món bán chạy nhất tuần',
                'Chính sách phí giao hàng & hủy đơn',
                'Soạn tin nhắn khuyến mãi 50K'
            ]
        ];
    }

    /**
     * Lấy các món ăn nổi bật định dạng chuẩn frontend
     */
    private static function getFeaturedProducts(PDO $db, int $limit = 4): array {
        $items = self::getFeaturedProductsRaw($db, $limit);
        return self::formatProducts($items);
    }

    private static function getFeaturedProductsRaw(PDO $db, int $limit = 4): array {
        $stmt = $db->query("
            SELECT id, name, base_price, discount_price, image_url, description, is_featured 
            FROM products 
            WHERE is_available = 1 
            ORDER BY is_featured DESC, id ASC 
            LIMIT {$limit}
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Format danh sách sản phẩm khớp với JS Chatbot
     */
    private static function formatProducts(array $items): array {
        $defaultImg = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80';
        $formatted = [];

        foreach ($items as $p) {
            $basePrice = (float)($p['base_price'] ?? 0);
            $discPrice = (float)($p['discount_price'] ?? 0);
            $price = ($discPrice > 0 && $discPrice < $basePrice) ? $discPrice : $basePrice;
            $discountPercent = ($discPrice > 0 && $discPrice < $basePrice)
                ? (int)round((($basePrice - $discPrice) / $basePrice) * 100)
                : 0;

            $img = trim($p['image_url'] ?? '');
            if (!$img) $img = $defaultImg;

            $formatted[] = [
                'id'              => (string)$p['id'],
                'name'            => $p['name'],
                'price'           => $price,
                'originalPrice'   => $basePrice,
                'discountPercent' => $discountPercent,
                'restaurantName'  => 'FASTFOOD Buôn Ma Thuột',
                'image'           => $img,
            ];
        }

        return $formatted;
    }
}
