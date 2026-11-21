<?php
// Tự động nạp danh sách món ăn từ CSDL để Chatbot AI luôn sẵn sàng dữ liệu phản hồi thông minh
$chatbotInitProducts = [];
try {
    if (class_exists('FastFood\Config\Database')) {
        $cbDb = \FastFood\Config\Database::getConnection();
        $cbStmt = $cbDb->query("
            SELECT id, name, base_price, discount_price, image_url, description, is_featured 
            FROM products 
            WHERE is_available = 1 
            ORDER BY is_featured DESC, id ASC 
            LIMIT 12
        ");
        $chatbotInitProducts = $cbStmt ? $cbStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
} catch (\Throwable $e) {}
?>
<script>
window.FASTFOOD_DATA = window.FASTFOOD_DATA || {};
window.FASTFOOD_DATA.products = <?= json_encode(array_map(function($p) {
    $bp = (float)($p['base_price'] ?? 0);
    $dp = (float)($p['discount_price'] ?? 0);
    $price = ($dp > 0 && $dp < $bp) ? $dp : $bp;
    return [
        'id'             => (string)$p['id'],
        'name'           => $p['name'],
        'price'          => $price,
        'oldPrice'       => $bp,
        'image'          => !empty($p['image_url']) ? $p['image_url'] : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80',
        'restaurantName' => 'FASTFOOD Buôn Ma Thuột',
        'isBestSeller'   => !empty($p['is_featured']),
    ];
}, $chatbotInitProducts), JSON_UNESCAPED_UNICODE) ?>;
</script>

<!-- FASTFOOD UNIFIED SUPPORT CHATBOX COMPONENT -->
<div class="ff-chatbot-wrapper" id="ff-chatbot-wrapper">
  <!-- 1. Floating Chat Trigger Button -->
  <button type="button" class="ff-chat-trigger" id="ff-chat-trigger" aria-label="Mở khung chat Nhân viên hỗ trợ" title="Chat với Nhân viên hỗ trợ FASTFOOD">
    <span class="ff-trigger-glow"></span>
    <span class="ff-trigger-icon-wrap">
      <!-- Support Headset / Chat Icon -->
      <svg class="ff-icon-bot" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
        <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
      </svg>
      <!-- Close Icon (when chat is open) -->
      <svg class="ff-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
      </svg>
    </span>
    <span class="ff-trigger-badge">24/7</span>
    <span class="ff-trigger-tooltip">Nhắn với Nhân viên hỗ trợ FASTFOOD!</span>
  </button>

  <!-- 2. Chat Window Container -->
  <section class="ff-chat-window" id="ff-chat-window" role="dialog" aria-modal="true" aria-label="Khung trò chuyện với Nhân viên hỗ trợ" hidden>
    <!-- Chat Header -->
    <header class="ff-chat-header">
      <div class="ff-header-identity">
        <div class="ff-avatar-wrap">
          <span class="ff-avatar-icon" id="ff-header-avatar">🤖</span>
          <span class="ff-status-dot online" id="ff-header-status-dot" title="Sẵn sàng hỗ trợ"></span>
        </div>
        <div class="ff-header-info">
          <div class="ff-header-title-row">
            <h3 class="ff-chat-name" id="ff-chat-title">Trợ lý AI FASTFOOD</h3>
          </div>
          <p class="ff-chat-status" id="ff-chat-status-text">Hỏi AI món ngon, giá cả & khuyến mãi 24/7</p>
        </div>
      </div>

      <!-- Window Control Action Buttons -->
      <div class="ff-header-actions">
        <button type="button" class="ff-btn-icon" id="ff-btn-new-chat" title="Cuộc trò chuyện mới" aria-label="Bắt đầu cuộc trò chuyện mới">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M8 16H3v5"></path></svg>
        </button>
        <button type="button" class="ff-btn-icon" id="ff-btn-expand" title="Mở rộng khung chat" aria-label="Phóng to hoặc thu nhỏ">
          <svg class="ff-icon-maximize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"></path><path d="M9 21H3v-6"></path><path d="M21 3l-7 7"></path><path d="M3 21l7-7"></path></svg>
          <svg class="ff-icon-restore" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M4 14h6v6"></path><path d="M20 10h-6V4"></path><path d="M14 10l7-7"></path><path d="M10 14L3 21"></path></svg>
        </button>
        <button type="button" class="ff-btn-icon" id="ff-btn-minimize" title="Thu nhỏ" aria-label="Thu nhỏ">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        </button>
        <button type="button" class="ff-btn-icon ff-btn-close" id="ff-btn-close" title="Đóng chat" aria-label="Đóng cửa sổ chat">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
      </div>
    </header>

    <!-- 2 TÁCH BIỆT: Tab chọn Trợ lý AI vs Chat trực tiếp với Admin -->
    <div class="ff-chat-tabs-bar" id="ff-chat-tabs-bar" role="tablist">
      <button type="button" class="ff-chat-tab active" id="ff-tab-ai" data-channel="ai" role="tab" aria-selected="true">
        <span class="ff-tab-emoji">🤖</span>
        <span class="ff-tab-title">Trợ lý AI 24/7</span>
      </button>
      <button type="button" class="ff-chat-tab" id="ff-tab-admin" data-channel="admin" role="tab" aria-selected="false">
        <span class="ff-tab-emoji">👨‍💼</span>
        <span class="ff-tab-title">Liên hệ với cửa hàng</span>
        <span class="ff-tab-unread-dot" id="ff-tab-admin-dot" style="display: none;"></span>
      </button>
    </div>

    <!-- Khung tin nhắn riêng cho Kênh AI -->
    <div class="ff-chat-body" id="ff-chat-body-ai" aria-live="polite"></div>

    <!-- Khung tin nhắn riêng cho Kênh Chat tay với Cửa hàng -->
    <div class="ff-chat-body" id="ff-chat-body-admin" aria-live="polite" style="display: none;">
      <div class="ff-direct-notice">
        <span class="ff-direct-notice-icon">👨‍💼</span>
        <div class="ff-direct-notice-text">
          <strong>Kênh Liên Hệ Với Cửa Hàng</strong>
          <p>Tin nhắn tại đây được gửi trực tiếp đến Cửa hàng FASTFOOD (Chat tay 100%, không qua AI).</p>
        </div>
      </div>
    </div>

    <!-- Quick Question Suggestions Bar (Chips) -->
    <div class="ff-quick-chips-wrap" id="ff-quick-chips-wrap">
      <div class="ff-quick-chips" id="ff-quick-chips">
        <button type="button" class="ff-chip-item" data-query="Hôm nay ăn gì?">🍜 Hôm nay ăn gì?</button>
        <button type="button" class="ff-chip-item" data-query="Món nào dưới 50.000đ?">💰 Món nào dưới 50.000đ?</button>
        <button type="button" class="ff-chip-item" data-query="Có combo nào tiết kiệm không?">🎁 Có combo nào tiết kiệm không?</button>
        <button type="button" class="ff-chip-item" data-query="Món nào bán chạy nhất?">🔥 Món nào bán chạy nhất?</button>
        <button type="button" class="ff-chip-item" data-query="Tôi muốn đặt đồ ăn">🛒 Tôi muốn đặt đồ ăn</button>
        <button type="button" class="ff-chip-item" data-query="Kiểm tra đơn hàng của tôi">📦 Kiểm tra đơn hàng của tôi</button>
      </div>
    </div>

    <!-- Chat Input Form -->
    <footer class="ff-chat-footer">
      <!-- Image Upload Preview Strip -->
      <div class="ff-chat-upload-preview" id="ff-chat-upload-preview" style="display: none;">
        <div class="ff-preview-thumb-wrap">
          <img id="ff-preview-thumb" src="" alt="Xem trước ảnh" />
          <button type="button" class="ff-btn-remove-preview" id="ff-btn-remove-preview" title="Hủy ảnh" aria-label="Hủy ảnh">&times;</button>
        </div>
        <div class="ff-preview-meta">
          <span class="ff-preview-filename" id="ff-preview-filename"></span>
          <span class="ff-preview-filesize" id="ff-preview-filesize"></span>
        </div>
      </div>

      <form class="ff-chat-form" id="ff-chat-form" autocomplete="off">
        <input type="file" id="ff-chat-file-input" accept="image/jpeg,image/png,image/webp" style="display: none;" />
        <button type="button" class="ff-chat-attach-btn" id="ff-chat-attach-btn" title="Gửi ảnh (JPG, PNG, WEBP tối đa 5MB)" aria-label="Đính kèm ảnh">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
            <circle cx="12" cy="13" r="4"></circle>
          </svg>
        </button>
        <input type="text" class="ff-chat-input" id="ff-chat-input" name="message" placeholder="Hỏi AI món ngon, gửi ảnh hoặc liên hệ cửa hàng..." maxlength="1000">
        <button type="submit" class="ff-chat-send-btn" id="ff-chat-send-btn" aria-label="Gửi tin nhắn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
          </svg>
        </button>
      </form>
      <div class="ff-chat-disclaimer" id="ff-chat-disclaimer">
        <span>⚡ Trợ lý AI FASTFOOD phản hồi tự động tức thì</span>
      </div>
    </footer>
  </section>

  <!-- Lightbox Zoom Modal for Images -->
  <div class="ff-chat-lightbox" id="ff-chat-lightbox" style="display: none;" role="dialog" aria-modal="true" aria-label="Xem ảnh phóng to">
    <div class="ff-lightbox-backdrop" id="ff-lightbox-backdrop"></div>
    <div class="ff-lightbox-content">
      <img id="ff-lightbox-img" src="" alt="Ảnh phóng to" />
      <button type="button" class="ff-lightbox-close" id="ff-lightbox-close" aria-label="Đóng (Esc)">&times;</button>
    </div>
  </div>
</div>

