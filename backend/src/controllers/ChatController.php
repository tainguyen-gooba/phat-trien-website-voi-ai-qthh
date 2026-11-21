<?php
namespace FastFood\Controllers;

use FastFood\Services\AiService;
use FastFood\Services\AiChatService;
use FastFood\Middlewares\ChatRateLimit;
use FastFood\Middlewares\AuthMiddleware;
use FastFood\Config\JwtConfig;
use FastFood\Constants\HttpStatusCodes;

/**
 * AI Chat & Support Inbox Controller
 * Hợp nhất tư vấn tự động AI và hộp thư chat trực tiếp của Admin
 */
class ChatController extends BaseController {
    /**
     * Nhận và xử lý tin nhắn từ khách hàng (hỗ trợ cả khách đăng nhập và khách vãng lai)
     */
    public function handleChat(): void {
        ChatRateLimit::check(60);

        $body = $this->getJsonBody();
        $message = trim($body['message'] ?? ($body['prompt'] ?? ''));
        $sessionId = trim($body['sessionId'] ?? '');
        $customerName = trim($body['customerName'] ?? '');
        $messageType = trim($body['messageType'] ?? 'text');
        $metadata = $body['metadata'] ?? null;

        // Cho phép gửi hình ảnh mà không có text chú thích
        $isImage = ($messageType === 'image') || !empty($metadata['imageUrl']) || !empty($body['imageUrl']);
        if ($isImage) {
            $messageType = 'image';
            if (empty($metadata)) {
                $metadata = [
                    'type'     => 'image',
                    'imageUrl' => $body['imageUrl'] ?? '',
                    'caption'  => $message
                ];
            }
            if (!$message) {
                $message = '[Hình ảnh]';
            }
        } elseif (!$message) {
            $this->sendError('Tin nhắn không được để trống.', HttpStatusCodes::BAD_REQUEST);
        }

        if (!$sessionId) {
            $sessionId = 'ff_chat_' . bin2hex(random_bytes(6)) . time();
        }

        $userId = null;
        if (!empty($body['userId'])) {
            $userId = (int)$body['userId'];
        }

        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            $payload = JwtConfig::decode($matches[1]);
            if ($payload && !empty($payload['id'])) {
                $userId = (int)$payload['id'];
            }
        }

        // Hỗ trợ phiên PHP Session khách hàng (hỗ trợ cả FASTFOOD_SESSID, FF_SESS_ADMIN và tab độc lập FF_SESS_*)
        if (session_status() === PHP_SESSION_NONE) {
            if (!empty($_COOKIE['FASTFOOD_SESSID'])) {
                session_name('FASTFOOD_SESSID');
            } elseif (!empty($_COOKIE['FF_SESS_ADMIN'])) {
                session_name('FF_SESS_ADMIN');
            } else {
                foreach ($_COOKIE as $k => $v) {
                    if (str_starts_with($k, 'FF_SESS_')) {
                        session_name($k);
                        break;
                    }
                }
            }
            @session_start();
        }

        $sessUserId = $_SESSION['user_id'] ?? null;
        $sessUserName = $_SESSION['user_name'] ?? null;
        // Mở khóa file session ngay lập tức để giải phóng tiến trình PHP đơn luồng
        session_write_close();

        if (!$userId && $sessUserId) {
            $userId = (int)$sessUserId;
        }

        // Tự động phân giải tên thật và thông tin tài khoản từ Database
        if ($userId) {
            try {
                $db = \FastFood\Config\Database::getConnection();
                $stmt = $db->prepare("SELECT id, full_name, email, phone FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $dbUser = $stmt->fetch();
                if (!empty($dbUser['full_name'])) {
                    $customerName = $dbUser['full_name'];
                }
            } catch (\Exception $e) {}
        }

        if (!$customerName || $customerName === 'Khách hàng' || $customerName === 'Khách vãng lai') {
            if (!empty($sessUserName)) {
                $customerName = $sessUserName;
            } elseif (!$customerName) {
                $customerName = 'Khách hàng';
            }
        }

        // Kênh chat tách biệt: 'ai' (Khách hỏi riêng AI) vs 'admin' (Khách chat tay riêng với Admin)
        $channel = trim($body['channel'] ?? '');
        if (!$channel) {
            $channel = str_starts_with($sessionId, 'admin_sess_') ? 'admin' : (str_starts_with($sessionId, 'ai_sess_') ? 'ai' : 'auto');
        }

        // Lưu thông tin phiên
        AiChatService::getOrCreateSession($sessionId, $userId, $customerName);

        // Lưu tin nhắn của khách hàng
        $userMsgId = AiChatService::saveMessage($sessionId, $userId, 'user', $message, $metadata, $messageType);

        // 1. KÊNH CHAT TAY LIÊN HỆ VỚI CỬA HÀNG
        if ($channel === 'admin' || str_starts_with($sessionId, 'admin_sess_')) {
            // Kiểm tra xem tin nhắn có phải là câu hỏi tư vấn món ăn / thực đơn / giá / khuyến mãi / địa chỉ không
            $cleanMsg = mb_strtolower($message, 'UTF-8');
            $isAiConsultation = (
                str_contains($cleanMsg, '50k') || str_contains($cleanMsg, 'dưới 50') ||
                str_contains($cleanMsg, 'hôm nay ăn gì') || str_contains($cleanMsg, 'combo') ||
                str_contains($cleanMsg, 'bán chạy') || str_contains($cleanMsg, 'khuyến mãi') ||
                str_contains($cleanMsg, 'thực đơn') || str_contains($cleanMsg, 'menu') ||
                str_contains($cleanMsg, 'quán ở đâu') || str_contains($cleanMsg, 'giờ mở cửa') ||
                str_contains($cleanMsg, 'địa chỉ') || str_contains($cleanMsg, 'phí ship') ||
                str_contains($cleanMsg, 'đặt đồ ăn') || str_contains($cleanMsg, 'có món') ||
                preg_match('/(?:dưới|giá rẻ|sinh viên|tiết kiệm|\b[1-9]\d{1,2}k\b)/iu', $cleanMsg)
            );

            // Nếu là câu hỏi tư vấn hoặc Admin đang bật chế độ AI tự động:
            if ($isAiConsultation || AiChatService::shouldAiAutoReply($sessionId)) {
                $aiResponse = AiService::processPrompt($message, $userId);
                $replyText = $aiResponse['reply'] ?? '';
                $products = $aiResponse['products'] ?? [];
                $order = $aiResponse['order'] ?? null;
                $suggestions = $aiResponse['suggestions'] ?? [];

                if ($replyText) {
                    $botReply = "🤖 *Trợ lý ảo FASTFOOD xin hỗ trợ gợi ý nhanh cho bạn trong khi chờ nhân viên cửa hàng phản hồi:*\n\n" . $replyText;
                    $botMsgId = AiChatService::saveMessage(
                        $sessionId,
                        $userId,
                        'assistant',
                        $botReply,
                        ['products' => $products, 'order' => $order]
                    );

                    $this->sendSuccess('Trợ lý ảo đã gợi ý món ăn cho khách!', [
                        'channel'            => 'admin',
                        'autoReplied'        => true,
                        'reply'              => $botReply,
                        'products'           => $products,
                        'order'              => $order,
                        'suggestions'        => $suggestions,
                        'userMessageId'      => $userMsgId,
                        'assistantMessageId' => $botMsgId ?: time(),
                        'messageId'          => $botMsgId ?: time(),
                        'adminOnline'        => true,
                    ]);
                    return;
                }
            }

            $this->sendSuccess('Tin nhắn đã được chuyển trực tiếp đến cửa hàng!', [
                'channel'       => 'admin',
                'autoReplied'   => false,
                'reply'         => null,
                'userMessageId' => $userMsgId,
                'messageId'     => $userMsgId,
                'messageType'   => $messageType,
                'metadata'      => $metadata,
                'adminBusy'     => false,
                'adminOnline'   => true,
            ]);
            return;
        }

        // 2. KÊNH HỎI RIÊNG TRỢ LÝ AI: Tự động phân tích và phản hồi 24/7
        if ($channel === 'ai' || str_starts_with($sessionId, 'ai_sess_')) {
            $aiResponse = AiService::processPrompt($message, $userId);
            $replyText = $aiResponse['reply'] ?? '';
            $products = $aiResponse['products'] ?? [];
            $order = $aiResponse['order'] ?? null;
            $suggestions = $aiResponse['suggestions'] ?? [];

            $botMsgId = AiChatService::saveMessage(
                $sessionId,
                $userId,
                'assistant',
                $replyText,
                ['products' => $products, 'order' => $order]
            );

            $this->sendSuccess('Trợ lý AI phản hồi thành công!', [
                'channel'            => 'ai',
                'autoReplied'        => true,
                'reply'              => $replyText,
                'products'           => $products,
                'order'              => $order,
                'suggestions'        => $suggestions,
                'userMessageId'      => $userMsgId,
                'assistantMessageId' => $botMsgId ?: time(),
                'messageId'          => $botMsgId ?: time(),
                'adminOnline'        => true,
            ]);
            return;
        }

        // Kiểm tra xem AI có được phép tự động trả lời (Chế độ legacy)
        $shouldReply = AiChatService::shouldAiAutoReply($sessionId);
        $settings = AiChatService::getChatSettings();

        if (!$shouldReply) {
            $this->sendSuccess('Tin nhắn đã được chuyển đến nhân viên hỗ trợ!', [
                'channel'       => 'admin',
                'autoReplied'   => false,
                'reply'         => null,
                'messageId'     => $userMsgId,
                'adminBusy'     => ($settings['mode'] ?? 'auto') === 'auto',
                'adminOnline'   => true,
                'supportStatus' => $settings
            ]);
            return;
        }

        // Trợ lý AI phân tích và trả lời tự động
        $aiResponse = AiService::processPrompt($message, $userId);
        $replyText = $aiResponse['reply'] ?? '';
        $products = $aiResponse['products'] ?? [];
        $order = $aiResponse['order'] ?? null;
        $suggestions = $aiResponse['suggestions'] ?? [];

        // Lưu phản hồi của trợ lý AI
        $botMsgId = AiChatService::saveMessage(
            $sessionId,
            $userId,
            'assistant',
            $replyText,
            ['products' => $products, 'order' => $order]
        );

        $this->sendSuccess('Phản hồi thành công!', [
            'autoReplied'   => true,
            'reply'         => $replyText,
            'products'      => $products,
            'order'         => $order,
            'suggestions'   => $suggestions,
            'messageId'     => $botMsgId ?: time(),
            'adminBusy'     => ($settings['mode'] ?? 'auto') === 'auto',
            'adminOnline'   => true,
            'supportStatus' => $settings
        ]);
    }

    /**
     * Lấy lịch sử tin nhắn của phiên chat (hỗ trợ cả khách vãng lai và đã đăng nhập)
     */
    public function getHistory(): void {
        $sessionId = trim($_GET['sessionId'] ?? '');

        $userId = null;
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            $payload = JwtConfig::decode($matches[1]);
            if ($payload) $userId = (int)($payload['id'] ?? null);
        }

        if (!$userId && session_status() === PHP_SESSION_NONE) {
            if (!empty($_COOKIE['FASTFOOD_SESSID'])) {
                session_name('FASTFOOD_SESSID');
            } elseif (!empty($_COOKIE['FF_SESS_ADMIN'])) {
                session_name('FF_SESS_ADMIN');
            } else {
                foreach ($_COOKIE as $k => $v) {
                    if (str_starts_with($k, 'FF_SESS_')) {
                        session_name($k);
                        break;
                    }
                }
            }
            @session_start();
            if (!empty($_SESSION['user_id'])) {
                $userId = (int)$_SESSION['user_id'];
            }
            session_write_close();
        }

        if (!$sessionId && !$userId) {
            $this->sendSuccess('Lịch sử trống', [
                'messages'      => [],
                'supportStatus' => ['adminOnline' => true, 'effectiveBusy' => false, 'aiPaused' => false]
            ]);
            return;
        }

        $messages = AiChatService::getHistory($sessionId, $userId);
        $isPaused = $sessionId ? AiChatService::isAiPaused($sessionId) : false;
        $settings = AiChatService::getChatSettings();
        $settings['aiPaused'] = $isPaused;

        $this->sendSuccess('Lấy lịch sử thành công!', [
            'messages'      => $messages,
            'supportStatus' => $settings
        ]);
    }

    /**
     * Khởi tạo phiên trò chuyện mới cho khách hàng
     */
    public function newSession(): void {
        $newSessionId = 'ff_chat_' . bin2hex(random_bytes(6)) . time();
        AiChatService::getOrCreateSession($newSessionId);
        $settings = AiChatService::getChatSettings();

        $this->sendSuccess('Tạo phiên mới thành công!', [
            'sessionId'      => $newSessionId,
            'welcomeMessage' => "Dạ chào bạn! Tôi là Nhân viên hỗ trợ FASTFOOD. Bạn cần tìm món ngon, săn khuyến mãi hay kiểm tra đơn hàng nào hôm nay?",
            'supportStatus'  => $settings
        ]);
    }

    /**
     * Danh sách hội thoại dành cho Admin
     */
    public function getAdminConversations(): void {
        AuthMiddleware::requireAdminOrStaff();
        $convData = AiChatService::getAdminConversations();
        $settings = AiChatService::getChatSettings();

        $conversations = $convData['conversations'] ?? $convData;
        $totalUnread = isset($convData['totalUnread']) ? (int)$convData['totalUnread'] : AiChatService::getTotalUnreadCount();

        $this->sendSuccess('Lấy danh sách hội thoại thành công!', [
            'conversations' => $conversations,
            'totalUnread'   => $totalUnread,
            'supportStatus' => $settings
        ]);
    }

    /**
     * Đánh dấu toàn bộ tin nhắn từ khách hàng trong phiên là đã đọc
     */
    public function markSessionAsRead(string $sessionId): void {
        AuthMiddleware::requireAdminOrStaff();
        $sessionId = trim($sessionId);
        if (!$sessionId) {
            $this->sendError('Session ID không hợp lệ.', HttpStatusCodes::BAD_REQUEST);
        }

        $affected = AiChatService::markSessionAsRead($sessionId);
        $totalUnread = AiChatService::getTotalUnreadCount();

        $this->sendSuccess('Đánh dấu đã đọc thành công!', [
            'sessionId'        => $sessionId,
            'affectedMessages' => $affected,
            'totalUnread'      => $totalUnread
        ]);
    }

    /**
     * Lấy tổng số tin nhắn chưa đọc cho Admin
     */
    public function getUnreadCount(): void {
        AuthMiddleware::requireAdminOrStaff();
        $totalUnread = AiChatService::getTotalUnreadCount();

        $this->sendSuccess('Lấy số lượng tin nhắn chưa đọc thành công!', [
            'totalUnread' => $totalUnread
        ]);
    }

    /**
     * Lấy lịch sử tin nhắn của một phiên hội thoại dành cho Admin
     */
    public function getSessionMessages(string $sessionId): void {
        AuthMiddleware::requireAdminOrStaff();
        $messages = AiChatService::getSessionMessages($sessionId);

        $this->sendSuccess('Lấy lịch sử tin nhắn thành công!', $messages);
    }

    /**
     * Admin gửi tin nhắn phản hồi trực tiếp tới khách hàng (hỗ trợ text, ảnh và thẻ món ăn 1-click)
     */
    public function sendAdminReply(string $sessionId): void {
        $admin = AuthMiddleware::requireAdminOrStaff();
        $body = $this->getJsonBody();
        $message = trim($body['message'] ?? '');
        $messageType = trim($body['messageType'] ?? 'text');
        $metadata = $body['metadata'] ?? null;
        $productId = isset($body['productId']) ? (int)$body['productId'] : null;

        // Nếu gửi thẻ món ăn (product_card) hoặc có productId
        if ($productId || $messageType === 'product_card') {
            $messageType = 'product_card';
            if ($productId) {
                try {
                    $db = \FastFood\Config\Database::getConnection();
                    $stmt = $db->prepare("
                        SELECT p.*, c.name AS category_name, r.name AS restaurant_name
                        FROM products p
                        LEFT JOIN categories c ON p.category_id = c.id
                        LEFT JOIN restaurants r ON p.restaurant_id = r.id
                        WHERE p.id = ? LIMIT 1
                    ");
                    $stmt->execute([$productId]);
                    $prod = $stmt->fetch(\PDO::FETCH_ASSOC);

                    if ($prod) {
                        $basePrice = (float)$prod['base_price'];
                        $discountPrice = (float)($prod['discount_price'] ?? 0);
                        $finalPrice = ($discountPrice > 0 && $discountPrice < $basePrice) ? $discountPrice : $basePrice;
                        $discountPercent = ($discountPrice > 0 && $basePrice > 0) ? (int)round((($basePrice - $discountPrice) / $basePrice) * 100) : 0;
                        
                        $status = $prod['status'] ?? ($prod['is_available'] ? 'in_stock' : 'out_of_stock');
                        $statusText = match ($status) {
                            'in_stock' => 'Còn hàng',
                            'out_of_stock' => 'Tạm hết',
                            'unavailable_today' => 'Hôm nay không bán',
                            default => $prod['is_available'] ? 'Còn hàng' : 'Tạm hết'
                        };

                        $productData = [
                            'id'              => (int)$prod['id'],
                            'productCode'     => $prod['product_code'] ?? 'prod-' . $prod['id'],
                            'name'            => $prod['name'],
                            'slug'            => $prod['slug'] ?? '',
                            'price'           => $finalPrice,
                            'originalPrice'   => $basePrice,
                            'discountPercent' => $discountPercent,
                            'image'           => $prod['image_url'] ?: '/assets/images/hero-burger.svg',
                            'categoryName'    => $prod['category_name'] ?? 'Món ngon',
                            'restaurantName'  => $prod['restaurant_name'] ?? 'FASTFOOD',
                            'status'          => $status,
                            'statusText'      => $statusText,
                            'isAvailable'     => (int)$prod['is_available'],
                            'description'     => $prod['description'] ?? ''
                        ];

                        $metadata = [
                            'type'     => 'product_card',
                            'product'  => $productData,
                            'products' => [$productData]
                        ];

                        if (!$message) {
                            $message = "FASTFOOD xin gửi bạn thông tin món ngon: {$productData['name']} - Giá: " . number_format($productData['price'], 0, ',', '.') . "đ";
                        }
                    }
                } catch (\Exception $e) {
                    error_log('[ChatController::sendAdminReply] Product lookup error: ' . $e->getMessage());
                }
            }
        } elseif ($messageType === 'image') {
            if (!$message) {
                $message = '[Hình ảnh]';
            }
        }

        if (!$message) {
            $this->sendError('Tin nhắn không được để trống.', HttpStatusCodes::BAD_REQUEST);
        }

        $adminId = (int)($admin['id'] ?? 1);
        $replyRecord = AiChatService::sendAdminReply($sessionId, $message, $adminId, $metadata, $messageType);

        $this->sendSuccess('Đã gửi phản hồi thành công!', [
            'message'       => $replyRecord,
            'aiPausedUntil' => $replyRecord['aiPausedUntil']
        ]);
    }

    /**
     * Bật/Tắt chế độ chat toàn cục (AI Tự Chat vs Admin Trực Tiếp Chat)
     */
    public function updateAdminStatus(): void {
        AuthMiddleware::requireAdminOrStaff();
        $body = $this->getJsonBody();
        $mode = trim($body['mode'] ?? '');
        if (!$mode) {
            $busy = !empty($body['busy']);
            $mode = $busy ? 'auto' : 'admin';
        }

        $settings = AiChatService::updateChatSettings(['mode' => $mode]);
        $this->sendSuccess('Cập nhật chế độ chat thành công!', $settings);
    }

    /**
     * Bật/Tắt tự động trả lời AI cho một phiên
     */
    public function updateSessionAutomation(string $sessionId): void {
        AuthMiddleware::requireAdminOrStaff();
        $body = $this->getJsonBody();
        $enabled = !empty($body['enabled']);

        AiChatService::updateSessionAutomation($sessionId, $enabled);

        $this->sendSuccess('Cập nhật tự động thành công!', [
            'enabled' => $enabled
        ]);
    }

    /**
     * Gợi ý câu trả lời AI cho Admin trong hộp thư chat (AI Copilot)
     */
    public function suggestReply(): void {
        AuthMiddleware::requireAdminOrStaff();
        $body = $this->getJsonBody();
        $customerMessage = trim($body['customerMessage'] ?? ($body['message'] ?? ($body['prompt'] ?? '')));
        $customerName = trim($body['customerName'] ?? 'Khách hàng');

        if (!$customerMessage) {
            $this->sendError('Tin nhắn khách hàng không được để trống.', HttpStatusCodes::BAD_REQUEST);
        }

        $suggestion = AiService::suggestAdminReply($customerMessage, $customerName);
        if (!isset($suggestion['reply']) && isset($suggestion['suggestedReply'])) {
            $suggestion['reply'] = $suggestion['suggestedReply'];
        }
        $this->sendSuccess('Gợi ý câu trả lời thành công!', $suggestion);
    }

    /**
     * Trợ lý AI Co-pilot chuyên sâu dành riêng cho Quản trị viên
     */
    public function adminAiCopilot(): void {
        $adminUser = AuthMiddleware::requireAdminOrStaff();
        $body = $this->getJsonBody();
        $prompt = trim($body['prompt'] ?? ($body['message'] ?? ''));

        if (!$prompt) {
            $this->sendError('Nội dung câu hỏi không được để trống.', HttpStatusCodes::BAD_REQUEST);
        }

        $admin = [
            'id'   => $adminUser['id'] ?? ($_SESSION['user_id'] ?? null),
            'name' => $adminUser['fullName'] ?? ($_SESSION['user_name'] ?? 'Quản trị viên'),
            'role' => $adminUser['role'] ?? ($_SESSION['user_role'] ?? 'admin'),
        ];

        $response = AiService::processAdminPrompt($prompt, $admin);
        $this->sendSuccess('Phản hồi thành công!', $response);
    }

    /**
     * Xóa một cuộc trò chuyện dành cho Admin
     */
    public function deleteSession(string $sessionId): void {
        AuthMiddleware::requireAdminOrStaff();
        if (!$sessionId) {
            $this->sendError('Mã phiên không hợp lệ.', HttpStatusCodes::BAD_REQUEST);
        }
        $success = AiChatService::deleteSession($sessionId);
        if ($success) {
            $this->sendSuccess('Đã xóa cuộc trò chuyện thành công!');
        } else {
            $this->sendError('Không thể xóa cuộc trò chuyện.', HttpStatusCodes::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Xóa toàn bộ lịch sử trò chuyện dành cho Admin
     */
    public function clearAllHistory(): void {
        AuthMiddleware::requireAdminOrStaff();
        $success = AiChatService::clearAllHistory();
        if ($success) {
            $this->sendSuccess('Đã xóa toàn bộ lịch sử trò chuyện thành công!');
        } else {
            $this->sendError('Không thể xóa lịch sử.', HttpStatusCodes::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Tải hình ảnh trong phiên trò chuyện (User hoặc Admin) với 6 lớp bảo mật
     */
    public function uploadChatImage(): void {
        ChatRateLimit::check(30);

        if (empty($_FILES['image']) || !isset($_FILES['image']['error'])) {
            $this->sendError('Vui lòng chọn tệp hình ảnh để tải lên.', HttpStatusCodes::BAD_REQUEST);
        }

        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->sendError('Lỗi tải tệp lên máy chủ. Mã lỗi: ' . $file['error'], HttpStatusCodes::BAD_REQUEST);
        }

        // Lớp 1: Giới hạn dung lượng tối đa 5MB
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize || $file['size'] <= 0) {
            $this->sendError('Dung lượng hình ảnh vượt quá giới hạn 5MB.', HttpStatusCodes::BAD_REQUEST);
        }

        $tmpPath = $file['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            $this->sendError('Tệp tải lên không hợp lệ.', HttpStatusCodes::BAD_REQUEST);
        }

        // Lớp 2: Kiểm tra phần mở rộng (Extension Whitelist)
        $origName = $file['name'] ?? '';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowedExts, true)) {
            $this->sendError('Định dạng tệp không được hỗ trợ. Chỉ chấp nhận JPG, JPEG, PNG, WEBP.', HttpStatusCodes::BAD_REQUEST);
        }

        // Lớp 3: Kiểm tra MIME type thực tế bằng Fileinfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            $this->sendError('Loại MIME của tệp không hợp lệ (' . htmlspecialchars($mimeType) . ').', HttpStatusCodes::BAD_REQUEST);
        }

        // Lớp 4: Kiểm tra cấu trúc nhị phân ảnh bằng getimagesize()
        $imgInfo = @getimagesize($tmpPath);
        if ($imgInfo === false || empty($imgInfo[0]) || empty($imgInfo[1])) {
            $this->sendError('Tệp tin bị lỗi hoặc không phải là hình ảnh nhị phân hợp lệ.', HttpStatusCodes::BAD_REQUEST);
        }

        // Lớp 5: Đặt tên tệp ngẫu nhiên an toàn chống ghi đè và thực thi mã
        $safeExt = ($ext === 'jpeg') ? 'jpg' : $ext;
        $randomName = 'chat_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $safeExt;

        // Lớp 6: Lưu trữ an toàn trong thư mục uploads/chat/
        $uploadDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'chat';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $destPath = $uploadDir . DIRECTORY_SEPARATOR . $randomName;
        if (!move_uploaded_file($tmpPath, $destPath)) {
            $this->sendError('Không thể lưu tệp hình ảnh vào hệ thống lưu trữ.', HttpStatusCodes::INTERNAL_SERVER_ERROR);
        }

        $url = '/uploads/chat/' . $randomName;

        $this->sendSuccess('Tải ảnh lên thành công!', [
            'url'      => $url,
            'fileName' => $randomName,
            'fileSize' => $file['size'],
            'mimeType' => $mimeType,
            'width'    => $imgInfo[0],
            'height'   => $imgInfo[1],
        ]);
    }

    /**
     * Tra cứu danh mục toàn bộ món ăn trong shop cho Admin (Kho Món Ăn FASTFOOD)
     */
    public function getAdminProducts(): void {
        AuthMiddleware::requireAdminOrStaff();

        $keyword = trim($_GET['keyword'] ?? ($_GET['q'] ?? ''));
        $status = trim($_GET['status'] ?? 'all');
        $categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $db = \FastFood\Config\Database::getConnection();

        // 1. Lấy thống kê số lượng theo từng trạng thái
        $statusCounts = [
            'all'               => 0,
            'in_stock'          => 0,
            'out_of_stock'      => 0,
            'unavailable_today' => 0,
        ];

        try {
            $stmtCounts = $db->query("SELECT status, COUNT(*) as cnt FROM products GROUP BY status");
            $rowsCounts = $stmtCounts->fetchAll(\PDO::FETCH_ASSOC);
            $totalAll = 0;
            foreach ($rowsCounts as $rc) {
                $st = $rc['status'];
                $c = (int)$rc['cnt'];
                $totalAll += $c;
                if (isset($statusCounts[$st])) {
                    $statusCounts[$st] = $c;
                }
            }
            $statusCounts['all'] = $totalAll;
        } catch (\Exception $e) {
            error_log('[ChatController::getAdminProducts] Error counts: ' . $e->getMessage());
        }

        // 2. Xây dựng điều kiện lọc
        $where = ["1=1"];
        $params = [];

        if ($status && $status !== 'all') {
            if (in_array($status, ['in_stock', 'out_of_stock', 'unavailable_today'], true)) {
                $where[] = "p.status = ?";
                $params[] = $status;
            }
        }

        if ($categoryId) {
            $where[] = "p.category_id = ?";
            $params[] = $categoryId;
        }

        if ($keyword !== '') {
            $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.product_code LIKE ? OR c.name LIKE ? OR p.id = ?)";
            $kwLike = '%' . $keyword . '%';
            $kwId = is_numeric($keyword) ? (int)$keyword : 0;
            $params[] = $kwLike;
            $params[] = $kwLike;
            $params[] = $kwLike;
            $params[] = $kwLike;
            $params[] = $kwId;
        }

        $whereClause = implode(' AND ', $where);

        // Đếm tổng số món thỏa điều kiện lọc
        $countStmt = $db->prepare("
            SELECT COUNT(*) 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $filteredTotal = (int)$countStmt->fetchColumn();

        // Lấy danh sách món ăn
        $queryStmt = $db->prepare("
            SELECT p.*, c.name AS category_name, r.name AS restaurant_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN restaurants r ON p.restaurant_id = r.id
            WHERE {$whereClause}
            ORDER BY (CASE WHEN p.status = 'in_stock' THEN 0 WHEN p.status = 'out_of_stock' THEN 1 ELSE 2 END), p.id DESC
            LIMIT ? OFFSET ?
        ");

        $paramIdx = 1;
        foreach ($params as $pVal) {
            $queryStmt->bindValue($paramIdx++, $pVal);
        }
        $queryStmt->bindValue($paramIdx++, $limit, \PDO::PARAM_INT);
        $queryStmt->bindValue($paramIdx++, $offset, \PDO::PARAM_INT);
        $queryStmt->execute();
        $productsRows = $queryStmt->fetchAll(\PDO::FETCH_ASSOC);

        $products = [];
        foreach ($productsRows as $p) {
            $basePrice = (float)$p['base_price'];
            $discountPrice = (float)($p['discount_price'] ?? 0);
            $finalPrice = ($discountPrice > 0 && $discountPrice < $basePrice) ? $discountPrice : $basePrice;
            $discountPercent = ($discountPrice > 0 && $basePrice > 0) ? (int)round((($basePrice - $discountPrice) / $basePrice) * 100) : 0;
            $st = $p['status'] ?? ($p['is_available'] ? 'in_stock' : 'out_of_stock');
            $stText = match ($st) {
                'in_stock' => 'Còn hàng',
                'out_of_stock' => 'Tạm hết',
                'unavailable_today' => 'Hôm nay không bán',
                default => $p['is_available'] ? 'Còn hàng' : 'Tạm hết'
            };

            $products[] = [
                'id'              => (int)$p['id'],
                'productCode'     => $p['product_code'] ?? 'prod-' . $p['id'],
                'name'            => $p['name'],
                'slug'            => $p['slug'] ?? '',
                'price'           => $finalPrice,
                'originalPrice'   => $basePrice,
                'discountPercent' => $discountPercent,
                'image'           => $p['image_url'] ?: '/assets/images/hero-burger.svg',
                'categoryName'    => $p['category_name'] ?? 'Món ngon',
                'restaurantName'  => $p['restaurant_name'] ?? 'FASTFOOD',
                'status'          => $st,
                'statusText'      => $stText,
                'isAvailable'     => (int)$p['is_available'],
                'description'     => $p['description'] ?? '',
                'prepTimeMinutes' => (int)($p['prep_time_minutes'] ?? 15),
            ];
        }

        // Lấy danh mục để client hiển thị dropdown lọc
        $categories = [];
        try {
            $catStmt = $db->query("SELECT id, name FROM categories ORDER BY name ASC");
            $categories = $catStmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {}

        $this->sendSuccess('Lấy danh sách món ăn thành công!', [
            'products'   => $products,
            'categories' => $categories,
            'pagination' => [
                'page'       => $page,
                'limit'      => $limit,
                'total'      => $filteredTotal,
                'totalPages' => (int)ceil($filteredTotal / $limit)
            ],
            'statuses'   => [
                ['key' => 'all', 'label' => 'Tất cả', 'count' => $statusCounts['all']],
                ['key' => 'in_stock', 'label' => 'Còn hàng', 'count' => $statusCounts['in_stock']],
                ['key' => 'out_of_stock', 'label' => 'Tạm hết', 'count' => $statusCounts['out_of_stock']],
                ['key' => 'unavailable_today', 'label' => 'Hôm nay không bán', 'count' => $statusCounts['unavailable_today']],
            ]
        ]);
    }

    /**
     * Cập nhật trạng thái kinh doanh của món ăn (Còn hàng / Tạm hết / Hôm nay không bán)
     */
    public function updateProductStatus(string $productId): void {
        AuthMiddleware::requireAdminOrStaff();
        $id = (int)$productId;
        if ($id <= 0) {
            $this->sendError('ID món ăn không hợp lệ.', HttpStatusCodes::BAD_REQUEST);
        }

        $body = $this->getJsonBody();
        $status = trim($body['status'] ?? '');
        $allowedStatuses = ['in_stock', 'out_of_stock', 'unavailable_today'];

        if (!in_array($status, $allowedStatuses, true)) {
            $this->sendError('Trạng thái không hợp lệ. Chỉ chấp nhận in_stock, out_of_stock, unavailable_today.', HttpStatusCodes::BAD_REQUEST);
        }

        $isAvailable = ($status === 'in_stock') ? 1 : 0;
        $db = \FastFood\Config\Database::getConnection();

        try {
            $stmt = $db->prepare("UPDATE products SET status = ?, is_available = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $isAvailable, $id]);

            if ($stmt->rowCount() === 0) {
                // Kiểm tra xem món có tồn tại không
                $check = $db->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
                $check->execute([$id]);
                if (!$check->fetch()) {
                    $this->sendError('Không tìm thấy món ăn yêu cầu.', HttpStatusCodes::NOT_FOUND);
                }
            }

            $stText = match ($status) {
                'in_stock' => 'Còn hàng',
                'out_of_stock' => 'Tạm hết',
                'unavailable_today' => 'Hôm nay không bán',
            };

            $this->sendSuccess('Cập nhật trạng thái món ăn thành công!', [
                'id'          => $id,
                'status'      => $status,
                'statusText'  => $stText,
                'isAvailable' => $isAvailable
            ]);
        } catch (\Exception $e) {
            error_log('[ChatController::updateProductStatus] Error: ' . $e->getMessage());
            $this->sendError('Lỗi cập nhật trạng thái món ăn.', HttpStatusCodes::INTERNAL_SERVER_ERROR);
        }
    }
}
