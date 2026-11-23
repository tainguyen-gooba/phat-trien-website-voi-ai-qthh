/**
 * FASTFOOD SUPPORT CHATBOX CONTROLLER ("Nhân viên hỗ trợ")
 * Hợp nhất Chat trực tiếp với Admin & Trợ lý AI tự động khi Admin bận:
 * - Đồng bộ realtime qua Socket.IO + dự phòng polling HTTP
 * - Kết nối API /api/v1/chat (hỗ trợ cả port 5000, Live Server và dự phòng offline FASTFOOD_DATA)
 * - Thêm nhanh món vào giỏ hàng chuẩn CartController
 */

(function (window, document) {
  'use strict';

  const STORAGE_SESSION_KEY = 'fastfood_ai_session_id';
  const DEFAULT_FOOD_IMG = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80';

  function detectFrontendBaseUrl() {
    const scriptEl = document.currentScript
      || Array.from(document.scripts).find(s => /chatbot\.js/i.test(s.src || ''));
    if (scriptEl && scriptEl.src) {
      return scriptEl.src.replace(/js\/chatbot\.js(\?.*)?$/i, '');
    }
    return window.location.pathname.includes('/pages/') ? '../' : './';
  }

  const FRONTEND_BASE = detectFrontendBaseUrl();
  const API_BASE = (window.location.port === '5000' || window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost')
    ? (window.location.port === '5000' ? `${window.location.origin}/api/v1/chat` : 'http://127.0.0.1:5000/api/v1/chat')
    : `${window.location.origin}/api/v1/chat`;

  const FASTFOODChatbot = {
    isOpen: false,
    isExpanded: false,
    currentChannel: 'ai', // 'ai' (Trợ lý AI 24/7) hoặc 'admin' (Chat tay với Admin)
    aiSessionId: null,
    adminSessionId: null,
    isSubmitting: false,
    initialized: false,
    lastRenderedMsgId: 0,
    renderedMessageIds: new Set(),
    pollTimer: null,
    supportStatus: { adminOnline: true, effectiveBusy: false, aiPaused: false },
    elements: {},

    async init() {
      if (this.initialized) return;
      if (window.location.pathname.includes('/admin/')) return;

      this.ensureStylesheet();

      if (!document.getElementById('ff-chatbot-wrapper')) {
        await this.injectChatbotHtml();
      }

      this.cacheElements();
      this.initSession();
      this.bindEvents();
      this.bindRealtimeBridge();
      this.initialized = true;

      await this.loadHistory();
      this.joinSupportRoom();
    },

    resolveFrontendPath(relPath) {
      const clean = String(relPath || '').replace(/^\/+/, '');
      if (/^https?:\/\//i.test(clean)) return clean;
      return `${FRONTEND_BASE}${clean}`;
    },

    normalizeImageUrl(rawImg) {
      const img = String(rawImg || '').trim();
      if (!img) return DEFAULT_FOOD_IMG;
      if (/^https?:\/\//i.test(img) || img.startsWith('data:')) return img;
      if (img.startsWith('../')) return this.resolveFrontendPath(img.slice(3));
      if (img.startsWith('./')) return this.resolveFrontendPath(img.slice(2));
      if (img.startsWith('/')) return this.resolveFrontendPath(img.slice(1));
      return this.resolveFrontendPath(img);
    },

    ensureStylesheet() {
      if (document.querySelector('link[href*="chatbot.css"]')) return;
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = this.resolveFrontendPath('css/chatbot.css');
      document.head.appendChild(link);
    },

    async injectChatbotHtml() {
      try {
        const res = await fetch(this.resolveFrontendPath('components/chatbot.html'));
        if (res.ok) {
          const html = await res.text();
          const div = document.createElement('div');
          div.innerHTML = html;
          if (div.firstElementChild) {
            document.body.appendChild(div.firstElementChild);
            return;
          }
        }
      } catch (_) {}
      this.injectFallbackHtml();
    },

    injectFallbackHtml() {
      const wrapper = document.createElement('div');
      wrapper.id = 'ff-chatbot-wrapper';
      wrapper.className = 'ff-chatbot-wrapper';
      wrapper.innerHTML = `
        <button type="button" class="ff-chat-trigger" id="ff-chat-trigger" aria-label="Mở khung chat hỗ trợ" title="Hỗ trợ & Tư vấn FASTFOOD">
          <div class="ff-trigger-glow"></div>
          <div class="ff-trigger-icon-wrap">
            <svg class="ff-icon-bot" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
              <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
            </svg>
            <svg class="ff-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </div>
          <span class="ff-trigger-badge">24/7</span>
          <span class="ff-trigger-tooltip">Hỏi AI & Liên hệ với cửa hàng!</span>
        </button>
        <section class="ff-chat-window" id="ff-chat-window" role="dialog" aria-modal="true" aria-label="Khung trò chuyện hỗ trợ" hidden>
          <header class="ff-chat-header">
            <div class="ff-header-identity">
              <div class="ff-avatar-wrap">
                <span class="ff-avatar-icon" id="ff-header-avatar">🤖</span>
                <span class="ff-status-dot online" id="ff-header-status-dot"></span>
              </div>
              <div class="ff-header-info">
                <div class="ff-header-title-row">
                  <h3 class="ff-chat-name" id="ff-chat-title">Trợ lý AI FASTFOOD</h3>
                </div>
                <p class="ff-chat-status" id="ff-chat-status-text">Hỏi AI món ngon, giá cả & khuyến mãi 24/7</p>
              </div>
            </div>
            <div class="ff-header-actions">
              <button type="button" class="ff-btn-icon" id="ff-btn-new-chat" title="Cuộc trò chuyện mới"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M8 16H3v5"></path></svg></button>
              <button type="button" class="ff-btn-icon" id="ff-btn-expand" title="Mở rộng khung chat"><svg class="ff-icon-maximize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"></path><path d="M9 21H3v-6"></path><path d="M21 3l-7 7"></path><path d="M3 21l7-7"></path></svg><svg class="ff-icon-restore" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M4 14h6v6"></path><path d="M20 10h-6V4"></path><path d="M14 10l7-7"></path><path d="M10 14L3 21"></path></svg></button>
              <button type="button" class="ff-btn-icon" id="ff-btn-minimize" title="Thu nhỏ"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line></svg></button>
              <button type="button" class="ff-btn-icon ff-btn-close" id="ff-btn-close" title="Đóng chat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
            </div>
          </header>

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

          <div class="ff-chat-body" id="ff-chat-body-ai" aria-live="polite"></div>
          <div class="ff-chat-body" id="ff-chat-body-admin" aria-live="polite" style="display: none;">
            <div class="ff-direct-notice">
              <span class="ff-direct-notice-icon">👨‍💼</span>
              <div class="ff-direct-notice-text">
                <strong>Kênh Liên Hệ Với Cửa Hàng</strong>
                <p>Tin nhắn tại đây được gửi trực tiếp đến Cửa hàng FASTFOOD (Chat tay 100%, không qua AI).</p>
              </div>
            </div>
          </div>

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
          <footer class="ff-chat-footer">
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
              <input type="text" class="ff-chat-input" id="ff-chat-input" name="message" placeholder="Hỏi AI món ngon, gửi ảnh hoặc liên hệ cửa hàng..." maxlength="1000" />
              <button type="submit" class="ff-chat-send-btn" id="ff-chat-send-btn" aria-label="Gửi tin nhắn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
              </button>
            </form>
            <div class="ff-chat-disclaimer" id="ff-chat-disclaimer"><span>⚡ Trợ lý AI FASTFOOD phản hồi tự động tức thì</span></div>
          </footer>
        </section>

        <div class="ff-chat-lightbox" id="ff-chat-lightbox" style="display: none;" role="dialog" aria-modal="true" aria-label="Xem ảnh phóng to">
          <div class="ff-lightbox-backdrop" id="ff-lightbox-backdrop"></div>
          <div class="ff-lightbox-content">
            <img id="ff-lightbox-img" src="" alt="Ảnh phóng to" />
            <button type="button" class="ff-lightbox-close" id="ff-lightbox-close" aria-label="Đóng (Esc)">&times;</button>
          </div>
        </div>
      `;
      document.body.appendChild(wrapper);
    },

    cacheElements() {
      this.elements = {
        wrapper: document.getElementById('ff-chatbot-wrapper'),
        trigger: document.getElementById('ff-chat-trigger'),
        window: document.getElementById('ff-chat-window'),
        tabAi: document.getElementById('ff-tab-ai'),
        tabAdmin: document.getElementById('ff-tab-admin'),
        adminDot: document.getElementById('ff-tab-admin-dot'),
        bodyAi: document.getElementById('ff-chat-body-ai') || document.getElementById('ff-chat-body'),
        bodyAdmin: document.getElementById('ff-chat-body-admin'),
        title: document.getElementById('ff-chat-title') || document.querySelector('.ff-chat-name'),
        avatar: document.getElementById('ff-header-avatar'),
        statusText: document.getElementById('ff-chat-status-text'),
        statusDot: document.getElementById('ff-header-status-dot'),
        quickChipsWrap: document.getElementById('ff-quick-chips-wrap'),
        quickChips: document.getElementById('ff-quick-chips'),
        disclaimer: document.getElementById('ff-chat-disclaimer'),
        form: document.getElementById('ff-chat-form'),
        input: document.getElementById('ff-chat-input'),
        btnSend: document.getElementById('ff-chat-send-btn'),
        btnClose: document.getElementById('ff-btn-close'),
        btnMinimize: document.getElementById('ff-btn-minimize'),
        btnExpand: document.getElementById('ff-btn-expand'),
        btnNewChat: document.getElementById('ff-btn-new-chat'),
        attachBtn: document.getElementById('ff-chat-attach-btn'),
        fileInput: document.getElementById('ff-chat-file-input'),
        uploadPreview: document.getElementById('ff-chat-upload-preview'),
        previewThumb: document.getElementById('ff-preview-thumb'),
        previewFilename: document.getElementById('ff-preview-filename'),
        previewFilesize: document.getElementById('ff-preview-filesize'),
        btnRemovePreview: document.getElementById('ff-btn-remove-preview'),
        lightbox: document.getElementById('ff-chat-lightbox'),
        lightboxImg: document.getElementById('ff-lightbox-img'),
        lightboxClose: document.getElementById('ff-lightbox-close'),
        lightboxBackdrop: document.getElementById('ff-lightbox-backdrop'),
      };
    },

    initSession() {
      // 1. Khởi tạo phiên cho kênh AI
      let aiSaved = localStorage.getItem('fastfood_ai_session_id');
      if (!aiSaved || !aiSaved.startsWith('ai_sess_')) {
        aiSaved = 'ai_sess_' + Math.random().toString(36).substring(2, 10) + Date.now().toString(36);
        localStorage.setItem('fastfood_ai_session_id', aiSaved);
      }
      this.aiSessionId = aiSaved;

      // 2. Khởi tạo phiên cho kênh Admin chat tay
      let adminSaved = localStorage.getItem('fastfood_admin_session_id');
      const userId = this.getCurrentUserId();
      if (userId) {
        adminSaved = `admin_sess_u_${userId}`;
      } else if (!adminSaved || !adminSaved.startsWith('admin_sess_')) {
        adminSaved = 'admin_sess_' + Math.random().toString(36).substring(2, 10) + Date.now().toString(36);
      }
      localStorage.setItem('fastfood_admin_session_id', adminSaved);
      this.adminSessionId = adminSaved;

      // Kênh đang chọn (mặc định là 'ai')
      this.currentChannel = localStorage.getItem('fastfood_chat_active_channel') || 'ai';
    },

    switchChannel(channel) {
      if (channel !== 'ai' && channel !== 'admin') return;
      this.currentChannel = channel;
      localStorage.setItem('fastfood_chat_active_channel', channel);

      const isAi = (channel === 'ai');

      if (this.elements.tabAi) {
        this.elements.tabAi.classList.toggle('active', isAi);
        this.elements.tabAi.setAttribute('aria-selected', isAi ? 'true' : 'false');
      }
      if (this.elements.tabAdmin) {
        this.elements.tabAdmin.classList.toggle('active', !isAi);
        this.elements.tabAdmin.setAttribute('aria-selected', !isAi ? 'true' : 'false');
      }

      if (this.elements.bodyAi) {
        this.elements.bodyAi.style.display = isAi ? 'flex' : 'none';
      }
      if (this.elements.bodyAdmin) {
        this.elements.bodyAdmin.style.display = isAi ? 'none' : 'flex';
      }

      if (!isAi && this.elements.adminDot) {
        this.elements.adminDot.style.display = 'none';
      }

      if (this.elements.avatar) {
        this.elements.avatar.textContent = isAi ? '🤖' : '👨‍💼';
      }
      if (this.elements.title) {
        this.elements.title.textContent = isAi ? 'Trợ lý AI FASTFOOD' : 'Liên hệ với cửa hàng';
      }
      if (this.elements.statusText) {
        this.elements.statusText.textContent = isAi
          ? 'Hỏi AI món ngon, giá cả & khuyến mãi 24/7'
          : 'Trò chuyện trực tiếp với Cửa hàng FASTFOOD';
      }
      if (this.elements.input) {
        this.elements.input.placeholder = isAi
          ? 'Hỏi AI món ngon, giá cả, khuyến mãi hôm nay...'
          : 'Nhắn tin trực tiếp cho cửa hàng...';
      }
      if (this.elements.quickChipsWrap) {
        this.elements.quickChipsWrap.style.display = isAi ? 'block' : 'none';
      }
      if (this.elements.disclaimer) {
        this.elements.disclaimer.innerHTML = isAi
          ? '<span>⚡ Trợ lý AI FASTFOOD phản hồi tự động tức thì</span>'
          : '<span>👨‍💼 Liên hệ trực tiếp với Cửa hàng • Không có AI tự động</span>';
      }

      this.scrollToBottom();
      setTimeout(() => this.elements.input?.focus(), 80);
    },

    getAuthToken() {
      return sessionStorage.getItem('fastfood_auth_token')
        || localStorage.getItem('fastfood_auth_token')
        || null;
    },

    getCurrentCustomerName() {
      try {
        if (window.FASTFOOD_USER && window.FASTFOOD_USER.full_name) {
          return window.FASTFOOD_USER.full_name;
        }
        if (window.FASTFOOD_USER && window.FASTFOOD_USER.name) {
          return window.FASTFOOD_USER.name;
        }
        const u = window.StorageManager?.getUser?.();
        if (u?.full_name || u?.name) return u.full_name || u.name;
      } catch (_) {}
      return 'Khách hàng';
    },

    getCurrentUserId() {
      try {
        if (window.FASTFOOD_USER && window.FASTFOOD_USER.id) {
          return parseInt(window.FASTFOOD_USER.id, 10);
        }
        const u = window.StorageManager?.getUser?.();
        if (u?.id) return parseInt(u.id, 10);
      } catch (_) {}
      return null;
    },

    handleImageSelect(file) {
      if (!file) return;

      const allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
      const ext = (file.name.split('.').pop() || '').toLowerCase();
      const isAllowedExt = allowedExts.includes(ext);
      const isAllowedMime = /^image\/(jpeg|png|webp)$/i.test(file.type);

      if (!isAllowedExt || !isAllowedMime) {
        window.UIComponents?.showToast?.('Chỉ chấp nhận tệp hình ảnh JPG, PNG hoặc WEBP.', 'warning');
        if (this.elements.fileInput) this.elements.fileInput.value = '';
        return;
      }

      const maxSize = 5 * 1024 * 1024; // 5MB
      if (file.size > maxSize) {
        window.UIComponents?.showToast?.('Dung lượng ảnh vượt quá 5MB. Vui lòng chọn ảnh nhỏ hơn.', 'warning');
        if (this.elements.fileInput) this.elements.fileInput.value = '';
        return;
      }

      if (this.selectedImagePreviewUrl) {
        URL.revokeObjectURL(this.selectedImagePreviewUrl);
      }

      this.selectedImageFile = file;
      this.selectedImagePreviewUrl = URL.createObjectURL(file);

      if (this.elements.previewThumb) {
        this.elements.previewThumb.src = this.selectedImagePreviewUrl;
      }
      if (this.elements.previewFilename) {
        this.elements.previewFilename.textContent = file.name;
      }
      if (this.elements.previewFilesize) {
        const kb = Math.round(file.size / 1024);
        this.elements.previewFilesize.textContent = kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${kb} KB`;
      }
      if (this.elements.uploadPreview) {
        this.elements.uploadPreview.style.display = 'flex';
      }

      setTimeout(() => this.elements.input?.focus(), 50);
    },

    cancelImagePreview() {
      if (this.selectedImagePreviewUrl) {
        URL.revokeObjectURL(this.selectedImagePreviewUrl);
        this.selectedImagePreviewUrl = null;
      }
      this.selectedImageFile = null;
      if (this.elements.fileInput) {
        this.elements.fileInput.value = '';
      }
      if (this.elements.uploadPreview) {
        this.elements.uploadPreview.style.display = 'none';
      }
      if (this.elements.previewThumb) {
        this.elements.previewThumb.src = '';
      }
    },

    openLightbox(imgSrc, altText = 'Xem ảnh phóng to') {
      if (!this.elements.lightbox || !imgSrc) return;
      if (this.elements.lightboxImg) {
        this.elements.lightboxImg.src = imgSrc;
        this.elements.lightboxImg.alt = altText;
      }
      this.elements.lightbox.style.display = 'flex';
    },

    closeLightbox() {
      if (!this.elements.lightbox) return;
      this.elements.lightbox.style.display = 'none';
      if (this.elements.lightboxImg) {
        this.elements.lightboxImg.src = '';
      }
    },

    bindEvents() {
      const { trigger, btnClose, btnMinimize, btnExpand, btnNewChat, form, quickChips, tabAi, tabAdmin } = this.elements;

      trigger?.addEventListener('click', () => this.toggleChat());
      btnClose?.addEventListener('click', () => this.toggleChat(false));
      btnMinimize?.addEventListener('click', () => this.toggleChat(false));
      btnExpand?.addEventListener('click', () => this.toggleExpand());
      btnNewChat?.addEventListener('click', () => this.startNewSession());

      // Chuyển đổi giữa 2 kênh: Trợ lý AI và Chat với Admin
      tabAi?.addEventListener('click', () => this.switchChannel('ai'));
      tabAdmin?.addEventListener('click', () => this.switchChannel('admin'));

      // Nút đính kèm ảnh và chọn file
      this.elements.attachBtn?.addEventListener('click', () => {
        this.elements.fileInput?.click();
      });

      this.elements.fileInput?.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        if (file) {
          this.handleImageSelect(file);
        }
      });

      this.elements.btnRemovePreview?.addEventListener('click', () => {
        this.cancelImagePreview();
      });

      // Lightbox close events
      this.elements.lightboxClose?.addEventListener('click', () => this.closeLightbox());
      this.elements.lightboxBackdrop?.addEventListener('click', () => this.closeLightbox());

      // Kéo thả ảnh (Drag & Drop)
      const chatWin = this.elements.window;
      if (chatWin) {
        ['dragenter', 'dragover'].forEach(name => {
          chatWin.addEventListener(name, (e) => {
            e.preventDefault();
            e.stopPropagation();
          }, false);
        });
        chatWin.addEventListener('drop', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const file = e.dataTransfer?.files?.[0];
          if (file && file.type.startsWith('image/')) {
            this.handleImageSelect(file);
          }
        }, false);
      }

      // Dán ảnh từ clipboard (Paste)
      this.elements.input?.addEventListener('paste', (e) => {
        const items = e.clipboardData?.items;
        if (!items) return;
        for (let i = 0; i < items.length; i++) {
          if (items[i].type && items[i].type.indexOf('image') !== -1) {
            const file = items[i].getAsFile();
            if (file) {
              e.preventDefault();
              this.handleImageSelect(file);
              break;
            }
          }
        }
      });

      form?.addEventListener('submit', (e) => {
        e.preventDefault();
        const val = this.elements.input.value.trim();
        if (val || this.selectedImageFile) {
          this.sendMessage(val);
        }
      });

      quickChips?.addEventListener('click', (e) => {
        const btn = e.target.closest('.ff-chip-item');
        if (btn && !this.isSubmitting && !btn.disabled) {
          btn.disabled = true;
          setTimeout(() => { btn.disabled = false; }, 2000);
          const query = btn.dataset.query || btn.textContent.trim();
          // Khi khách bấm câu hỏi gợi ý, tự động chuyển sang kênh AI để nhận phân tích món ngon tức thời
          if (this.currentChannel !== 'ai') {
            this.switchChannel('ai');
          }
          this.sendMessage(query);
        }
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          if (this.elements.lightbox && this.elements.lightbox.style.display === 'flex') {
            this.closeLightbox();
            return;
          }
          if (this.isOpen) {
            this.toggleChat(false);
          }
        }
      });
    },

    bindRealtimeBridge() {
      // Khởi tạo socket nếu chưa chạy trên trang hiện tại
      if (typeof window.initFastfoodRealtime === 'function' && !window.FASTFOOD_SOCKET) {
        window.initFastfoodRealtime();
      }

      window.addEventListener('fastfood:socket-connected', () => {
        this.joinSupportRoom();
      });

      window.addEventListener('fastfood:support-status', (event) => {
        if (event.detail) {
          this.updateSupportStatusUI(event.detail);
        }
      });

      window.addEventListener('fastfood:support-message', (event) => {
        const payload = event.detail;
        if (!payload || !payload.message) return;
        const msg = payload.message;
        // Bỏ qua tin nhắn của chính khách hàng để tránh tạo bong bóng trùng lặp
        if (msg.sender === 'user' || msg.senderRole === 'customer') return;
        if (msg.sender === 'admin' || payload.sessionId === this.adminSessionId) {
          this.renderMessage(msg, 'admin');
          if (!this.isOpen && msg.sender === 'admin') {
            window.UIComponents?.showToast?.('Cửa hàng vừa phản hồi tin nhắn của bạn!', 'info');
          }
        }
      });

      // Đồng bộ liên tab siêu tốc (< 15ms) qua BroadcastChannel và localStorage
      try {
        if (typeof BroadcastChannel !== 'undefined') {
          this.broadcastChannel = new BroadcastChannel('fastfood_chat_realtime');
          this.broadcastChannel.onmessage = (event) => {
            const data = event.data;
            if (data && data.type === 'ADMIN_REPLIED') {
              if (!data.sessionId || data.sessionId === this.adminSessionId) {
                if (data.message && typeof data.message === 'object') {
                  this.renderMessage(data.message, 'admin');
                }
                this.syncLatestMessages();
              }
            }
          };
        }
      } catch (_) {}

      window.addEventListener('storage', (e) => {
        if (e.key === 'fastfood_chat_sync' && e.newValue) {
          try {
            const data = JSON.parse(e.newValue);
            if (data && data.type === 'ADMIN_REPLIED') {
              if (!data.sessionId || data.sessionId === this.adminSessionId) {
                if (data.message && typeof data.message === 'object') {
                  this.renderMessage(data.message, 'admin');
                }
                this.syncLatestMessages();
              }
            }
          } catch (_) {}
        }
      });
    },

    joinSupportRoom() {
      const socket = window.FASTFOOD_SOCKET;
      if (socket && socket.connected) {
        if (this.adminSessionId) socket.emit('support:join', { sessionId: this.adminSessionId });
        if (this.aiSessionId) socket.emit('support:join', { sessionId: this.aiSessionId });
      }
    },

    updateSupportStatusUI(status) {
      if (!status) return;
      this.supportStatus = { ...this.supportStatus, ...status };
      if (this.currentChannel === 'admin') {
        if (this.elements.statusText) {
          this.elements.statusText.textContent = 'Trò chuyện trực tiếp với Cửa hàng FASTFOOD';
        }
      }
    },

    startPolling() {
      this.stopPolling();
      this.pollTimer = setInterval(() => {
        if (this.isOpen && !this.isSubmitting) {
          this.syncLatestMessages();
        }
      }, 4500);
    },

    stopPolling() {
      if (this.pollTimer) {
        clearInterval(this.pollTimer);
        this.pollTimer = null;
      }
    },

    async syncLatestMessages() {
      try {
        const token = this.getAuthToken();
        const headers = {};
        if (token) headers['Authorization'] = `Bearer ${token}`;

        // 1. Luôn đồng bộ tin nhắn từ Kênh Admin
        if (this.adminSessionId) {
          const resAdmin = await fetch(`${API_BASE}/history?sessionId=${encodeURIComponent(this.adminSessionId)}`, { headers });
          if (resAdmin.ok) {
            const resultAdmin = await resAdmin.json();
            if (resultAdmin.success && Array.isArray(resultAdmin.data?.messages)) {
              resultAdmin.data.messages.forEach(m => {
                if (m.id && !this.renderedMessageIds.has(String(m.id))) {
                  this.renderMessage(m, 'admin');
                }
              });
            }
          }
        }

        // 2. Đồng bộ Kênh AI khi người dùng đang xem tab AI
        if (this.aiSessionId && this.currentChannel === 'ai') {
          const resAi = await fetch(`${API_BASE}/history?sessionId=${encodeURIComponent(this.aiSessionId)}`, { headers });
          if (resAi.ok) {
            const resultAi = await resAi.json();
            if (resultAi.success && Array.isArray(resultAi.data?.messages)) {
              resultAi.data.messages.forEach(m => {
                if (m.id && !this.renderedMessageIds.has(String(m.id))) {
                  this.renderMessage(m, 'ai');
                }
              });
            }
          }
        }
      } catch (_) {}
    },

    toggleChat(forceState) {
      this.isOpen = typeof forceState === 'boolean' ? forceState : !this.isOpen;
      const { trigger, window: chatWindow } = this.elements;

      if (this.isOpen) {
        chatWindow.removeAttribute('hidden');
        trigger.classList.add('is-active');
        this.joinSupportRoom();
        this.switchChannel(this.currentChannel || 'ai');
        this.syncLatestMessages();
        this.startPolling();
        this.scrollToBottom();
        setTimeout(() => this.elements.input?.focus(), 150);
      } else {
        chatWindow.setAttribute('hidden', '');
        trigger.classList.remove('is-active');
        this.stopPolling();
      }
    },

    toggleExpand() {
      this.isExpanded = !this.isExpanded;
      const { window: chatWindow, btnExpand } = this.elements;
      const maxIcon = btnExpand.querySelector('.ff-icon-maximize');
      const restoreIcon = btnExpand.querySelector('.ff-icon-restore');

      if (this.isExpanded) {
        chatWindow.classList.add('is-expanded');
        if (maxIcon) maxIcon.style.display = 'none';
        if (restoreIcon) restoreIcon.style.display = 'block';
        btnExpand.title = 'Thu gọn khung chat';
      } else {
        chatWindow.classList.remove('is-expanded');
        if (maxIcon) maxIcon.style.display = 'block';
        if (restoreIcon) restoreIcon.style.display = 'none';
        btnExpand.title = 'Mở rộng khung chat';
      }
      this.scrollToBottom();
    },

    async startNewSession() {
      if (this.isSubmitting) return;

      const isAi = (this.currentChannel === 'ai');
      const channelName = isAi ? 'Trợ lý AI' : 'Cửa hàng';
      const confirmed = window.confirm(`Bắt đầu cuộc trò chuyện mới với ${channelName}?`);
      if (!confirmed) return;

      if (isAi) {
        if (this.elements.bodyAi) this.elements.bodyAi.innerHTML = '';
        this.aiSessionId = 'ai_sess_' + Math.random().toString(36).substring(2, 10) + Date.now().toString(36);
        localStorage.setItem('fastfood_ai_session_id', this.aiSessionId);
        this.renderDefaultWelcome();
      } else {
        if (this.elements.bodyAdmin) {
          this.elements.bodyAdmin.innerHTML = `
            <div class="ff-direct-notice">
              <span class="ff-direct-notice-icon">👨‍💼</span>
              <div class="ff-direct-notice-text">
                <strong>Kênh Liên Hệ Với Cửa Hàng</strong>
                <p>Tin nhắn tại đây được gửi trực tiếp đến Cửa hàng FASTFOOD (Chat tay 100%, không qua AI).</p>
              </div>
            </div>
          `;
        }
        const userId = this.getCurrentUserId();
        this.adminSessionId = userId ? `admin_sess_u_${userId}` : ('admin_sess_' + Math.random().toString(36).substring(2, 10) + Date.now().toString(36));
        localStorage.setItem('fastfood_admin_session_id', this.adminSessionId);
      }
    },

    async loadHistory() {
      // 1. Nạp lịch sử Kênh AI
      await this.loadChannelHistory(this.aiSessionId, 'ai');
      // 2. Nạp lịch sử Kênh Admin
      await this.loadChannelHistory(this.adminSessionId, 'admin');
      // 3. Hiển thị tab đang chọn
      this.switchChannel(this.currentChannel || 'ai');
    },

    async loadChannelHistory(sessionId, channel) {
      if (!sessionId) return;
      try {
        const token = this.getAuthToken();
        const headers = {};
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const res = await fetch(`${API_BASE}/history?sessionId=${encodeURIComponent(sessionId)}`, { headers });
        const result = await res.json();

        if (result.success && Array.isArray(result.data?.messages) && result.data.messages.length > 0) {
          result.data.messages.forEach(m => this.renderMessage(m, channel));
        } else {
          if (channel === 'ai') {
            this.renderDefaultWelcome();
          }
        }
      } catch (_) {
        if (channel === 'ai') this.renderDefaultWelcome();
      }
    },

    renderDefaultWelcome() {
      if (this.elements.bodyAi) {
        this.elements.bodyAi.innerHTML = '';
      }
      const welcomeText = `Xin chào bạn! Tôi là **Trợ lý AI** của nền tảng đặt đồ ăn FASTFOOD Buôn Ma Thuột 💖\n\n`
        + `Mình có thể hỗ trợ bạn ngay lập tức:\n`
        + `• Gợi ý món ăn ngon, bán chạy hôm nay 🔥\n`
        + `• Tìm món giá sinh viên, combo tiết kiệm (dưới 50K) 💰\n`
        + `• Hướng dẫn đặt hàng, phí ship & mã giảm giá 🎁\n`
        + `• Tra cứu thực đơn & tình trạng đơn hàng 📦\n\n`
        + `*Cần trò chuyện trực tiếp với nhân viên, bạn chỉ cần bấm tab **"Liên hệ với cửa hàng"** ở phía trên nhé!*`;

      this.renderMessage({
        sender: 'assistant',
        message: welcomeText,
      }, 'ai');
    },

    /**
     * Dự phòng thông minh ngay tại trình duyệt nếu máy chủ tạm thời gián đoạn
     */
    buildLocalOfflineReply(userText) {
      const data = window.FASTFOOD_DATA;
      let products = Array.isArray(data?.products) && data.products.length > 0 ? data.products : [
        { id: '126', name: 'Burger Gà Teriyaki Kiểu Nhật Rong Biển', price: 49000, oldPrice: 59000, isBestSeller: true, image: 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&auto=format&fit=crop&q=80', restaurantName: 'FASTFOOD Buôn Ma Thuột' },
        { id: '138', name: 'Bánh Mì Cuộn Gà Giòn Sốt Caesar Phô Mai', price: 49000, oldPrice: 49000, isBestSeller: true, image: 'https://images.unsplash.com/photo-1626700051175-6818013e1d4f?w=600&auto=format&fit=crop&q=80', restaurantName: 'FASTFOOD Buôn Ma Thuột' },
        { id: '43', name: 'Trà Sữa Oolong Nướng Trân Châu Hoàng Kim (Size L)', price: 50000, oldPrice: 50000, isBestSeller: true, image: 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', restaurantName: 'FASTFOOD Buôn Ma Thuột' },
        { id: '254', name: 'Bắp Xào Bơ Tép Khô Mỡ Hành Thơm Lừng', price: 25000, oldPrice: 25000, isBestSeller: true, image: '/assets/images/foods/bap-xao.jpg', restaurantName: 'FASTFOOD Buôn Ma Thuột' }
      ];
      const norm = userText
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[đĐ]/g, 'd')
        .toLowerCase();

      if (products.length === 0) {
        return {
          reply: 'Dạ Trợ lý AI đã ghi nhận câu hỏi của bạn. Hiện kết nối tới máy chủ đang tạm gián đoạn, bạn duyệt thực đơn trên trang nhé!',
          products: [],
        };
      }

      let matched = [];
      let reply = '';
      const cleanNoDots = norm.replace(/\./g, '');
      const budgetMatch = cleanNoDots.match(/duoi\s*(\d+)\s*(k|000)?/);

      if (/(combo|set|khuyen mai|uu dai)/.test(norm)) {
        matched = products
          .filter(p => /combo|set/i.test(p.name) || p.isFlashDeal || (p.oldPrice && p.oldPrice > p.price))
          .slice(0, 4);
        reply = '🎁 Dưới đây là các **Combo & Món ưu đãi tiết kiệm** nổi bật tại FASTFOOD:\n\n';
      } else if (budgetMatch || /(duoi 50|gia re|tiet kiem)/.test(norm)) {
        let maxPrice = 50000;
        if (budgetMatch) {
          const val = Number(budgetMatch[1]);
          maxPrice = budgetMatch[2] === 'k' ? val * 1000 : (val < 1000 ? val * 1000 : val);
        }
        matched = products.filter(p => Number(p.price) <= maxPrice).slice(0, 5);
        reply = `🎉 Gợi ý các món ngon giá **dưới ${new Intl.NumberFormat('vi-VN').format(maxPrice)}đ** dành cho bạn:\n\n`;
      } else if (/(hom nay an gi|ban chay|best seller|mon hot|goi y)/.test(norm)) {
        matched = products.filter(p => p.isBestSeller).slice(0, 5);
        reply = '🔥 Top món **bán chạy nhất** tại FASTFOOD Buôn Ma Thuột hôm nay:\n\n';
      } else {
        matched = products.filter(p => {
          const pNorm = `${p.name} ${p.category} ${p.restaurantName}`
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[đĐ]/g, 'd').toLowerCase();
          return norm.split(/\s+/).some(tok => tok.length >= 2 && pNorm.includes(tok));
        }).slice(0, 5);
        if (matched.length === 0) matched = products.filter(p => p.isBestSeller).slice(0, 4);
        reply = 'Dạ Trợ lý AI tìm thấy các món phù hợp với nhu cầu của bạn:\n\n';
      }

      matched.forEach((p, i) => {
        reply += `${i + 1}. **${p.name}** – ${new Intl.NumberFormat('vi-VN').format(p.price)}đ (${p.restaurantName || 'FASTFOOD'})\n`;
      });

      const formattedProducts = matched.map(p => ({
        id: p.id,
        name: p.name,
        price: Number(p.price),
        originalPrice: Number(p.oldPrice || p.price),
        discountPercent: p.oldPrice && p.oldPrice > p.price ? Math.round(((p.oldPrice - p.price) / p.oldPrice) * 100) : 0,
        restaurantName: p.restaurantName || 'FASTFOOD',
        image: p.image,
        imageCredit: p.imageCredit,
      }));

      return { reply, products: formattedProducts };
    },

    async sendMessage(text) {
      if ((!text && !this.selectedImageFile) || this.isSubmitting) return;

      const userText = (text || '').trim();

      // Chống bấm liên tiếp gây trùng lặp tin nhắn
      const now = Date.now();
      if (!this.selectedImageFile && this._lastSentText === userText && (now - (this._lastSentTime || 0)) < 2500) {
        console.warn('[FASTFOOD Chat] Đang xử lý tin nhắn, vui lòng chờ trong giây lát...');
        return;
      }
      this._lastSentText = userText;
      this._lastSentTime = now;

      this.elements.input.value = '';
      this.isSubmitting = true;

      const channel = this.currentChannel; // 'ai' hoặc 'admin'
      const activeSessionId = (channel === 'ai') ? this.aiSessionId : this.adminSessionId;
      const customerName = this.getCurrentCustomerName();
      const userId = this.getCurrentUserId();
      const userEmail = window.FASTFOOD_USER?.email || window.StorageManager?.getUser?.()?.email || '';
      const userPhone = window.FASTFOOD_USER?.phone || window.StorageManager?.getUser?.()?.phone || '';

      const imageFileToUpload = this.selectedImageFile;
      const localPreviewUrl = this.selectedImagePreviewUrl;

      // Xóa preview trên input
      this.cancelImagePreview();

      const tempId = 'tmp_' + Date.now();
      let pendingRow = null;

      if (imageFileToUpload) {
        pendingRow = this.renderMessage({
          id: tempId,
          sender: 'user',
          customerName: customerName,
          message: userText || '[Hình ảnh]',
          messageType: 'image',
          metadata: {
            type: 'image',
            imageUrl: localPreviewUrl,
            fileName: imageFileToUpload.name,
            fileSize: imageFileToUpload.size,
            caption: userText
          },
          time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
        }, channel);
        if (pendingRow) pendingRow.dataset.imageUrl = localPreviewUrl;
      } else {
        pendingRow = this.renderMessage({
          id: tempId,
          sender: 'user',
          customerName: customerName,
          message: userText,
          messageType: 'text',
          time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
        }, channel);
      }

      try {
        const token = this.getAuthToken();
        let uploadedImageUrl = null;
        let uploadedMeta = null;

        // Nếu có tệp ảnh cần tải lên
        if (imageFileToUpload) {
          const formData = new FormData();
          formData.append('image', imageFileToUpload);
          formData.append('sessionId', activeSessionId);

          const upHeaders = {};
          if (token) upHeaders['Authorization'] = `Bearer ${token}`;

          const upRes = await fetch(`${API_BASE}/chat/upload-image`, {
            method: 'POST',
            headers: upHeaders,
            body: formData
          });

          const upResult = await upRes.json();
          if (!upRes.ok || !upResult.success || !upResult.data?.url) {
            throw new Error(upResult.message || 'Không thể tải ảnh lên máy chủ');
          }

          uploadedImageUrl = upResult.data.url;
          uploadedMeta = {
            type: 'image',
            imageUrl: uploadedImageUrl,
            fileName: upResult.data.fileName,
            fileSize: upResult.data.fileSize,
            mimeType: upResult.data.mimeType,
            caption: userText
          };

          if (pendingRow) {
            pendingRow.dataset.imageUrl = uploadedImageUrl;
            const imgEl = pendingRow.querySelector('.ff-msg-image');
            if (imgEl) imgEl.src = uploadedImageUrl;
            const wrapEl = pendingRow.querySelector('.ff-msg-image-wrap');
            if (wrapEl) wrapEl.dataset.imgUrl = uploadedImageUrl;
          }
        }

        // Bắn tín hiệu tức thời liên tab cho Admin Inbox (< 15ms)
        if (channel === 'admin') {
          try {
            const syncPayload = {
              type: 'CUSTOMER_SENT',
              channel: 'admin',
              sessionId: activeSessionId,
              customerName: customerName,
              userId: userId,
              text: userText || '[Hình ảnh]',
              messageType: imageFileToUpload ? 'image' : 'text',
              metadata: uploadedMeta || null,
              imageUrl: uploadedImageUrl || null,
              time: Date.now()
            };
            if (this.broadcastChannel) {
              this.broadcastChannel.postMessage(syncPayload);
            }
            localStorage.setItem('fastfood_chat_sync', JSON.stringify(syncPayload));
          } catch (_) {}
        }

        const headers = { 'Content-Type': 'application/json' };
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const postBody = {
          message: userText || (imageFileToUpload ? '[Hình ảnh]' : ''),
          messageType: imageFileToUpload ? 'image' : 'text',
          metadata: uploadedMeta || null,
          sessionId: activeSessionId,
          channel: channel,
          customerName: customerName,
          userId: userId,
          email: userEmail,
          phone: userPhone,
        };

        const res = await fetch(`${API_BASE}/message`, {
          method: 'POST',
          headers,
          body: JSON.stringify(postBody),
        });

        const result = await res.json();

        // 1. Cập nhật ID thật của TIN NHẮN NGƯỜI DÙNG để chống trùng lặp khi sync history
        const userRealId = result.data?.userMessageId || (result.data?.autoReplied === false ? result.data?.messageId : null);
        if (result.success && userRealId) {
          const userRealIdStr = String(userRealId);
          this.renderedMessageIds.add(userRealIdStr);
          if (pendingRow) {
            pendingRow.dataset.msgId = userRealIdStr;
          }
        }

        // 2. Kênh AI hoặc Trợ lý ảo tự động gợi ý phản hồi
        if (res.ok && result.success && result.data) {
          const { reply, products, order, autoReplied } = result.data;
          const botMessageId = result.data.assistantMessageId || (result.data.autoReplied ? result.data.messageId : null);
          if (reply && (channel === 'ai' || autoReplied)) {
            this.renderMessage({
              id: botMessageId,
              sender: 'assistant',
              message: reply,
              metadata: { products, order },
              time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
            }, channel);
          }
        } else if (channel === 'ai') {
          const fallback = this.buildLocalOfflineReply(userText);
          this.renderMessage({
            sender: 'assistant',
            message: fallback.reply,
            metadata: { products: fallback.products },
            time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
          }, 'ai');
        }
      } catch (err) {
        console.warn('[FASTFOOD Chat] Lỗi gửi tin nhắn:', err);
        if (channel === 'ai' && !imageFileToUpload) {
          const fallback = this.buildLocalOfflineReply(userText);
          this.renderMessage({
            sender: 'assistant',
            message: fallback.reply,
            metadata: { products: fallback.products },
            time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
          }, 'ai');
        } else {
          window.UIComponents?.showToast?.(err.message || 'Lỗi gửi tin nhắn. Vui lòng thử lại!', 'error');
        }
      } finally {
        this.isSubmitting = false;
        setTimeout(() => this.elements.input?.focus(), 100);
      }
    },

    showTyping(show) {
      if (this.elements.typing) {
        this.elements.typing.style.display = 'none';
        this.elements.typing.setAttribute('hidden', '');
      }
    },

    scrollToBottom() {
      requestAnimationFrame(() => {
        const targetBody = (this.currentChannel === 'admin') ? this.elements.bodyAdmin : this.elements.bodyAi;
        if (targetBody) {
          targetBody.scrollTop = targetBody.scrollHeight;
        }
      });
    },

    renderMessage(msgData, targetChannel) {
      if (!msgData) return;
      const msgIdStr = msgData.id ? String(msgData.id) : '';
      if (msgIdStr && this.renderedMessageIds.has(msgIdStr)) {
        return;
      }
      if (msgIdStr) {
        this.renderedMessageIds.add(msgIdStr);
        const numericId = Number(msgData.id);
        if (Number.isFinite(numericId) && numericId > this.lastRenderedMsgId) {
          this.lastRenderedMsgId = numericId;
        }
      }

      // Xác định kênh đích
      let channel = targetChannel;
      if (!channel) {
        if (msgData.sender === 'admin' || (msgData.sessionId && String(msgData.sessionId).startsWith('admin_sess_'))) {
          channel = 'admin';
        } else {
          channel = 'ai';
        }
      }

      const isUser = msgData.sender === 'user';
      const isAdmin = msgData.sender === 'admin';
      const targetBody = (channel === 'admin') ? this.elements.bodyAdmin : this.elements.bodyAi;

      const isImageMsg = msgData.messageType === 'image' ||
                         msgData.metadata?.type === 'image' ||
                         Boolean(msgData.metadata?.imageUrl) ||
                         Boolean(msgData.imageUrl);
      const imageUrl = msgData.metadata?.imageUrl || msgData.imageUrl || '';

      const isProductCard = msgData.messageType === 'product_card' ||
                            msgData.metadata?.type === 'product_card' ||
                            Boolean(msgData.metadata?.product);

      // Chống trùng lặp tin nhắn người dùng (deduplication giữa optimistic render và sync history)
      if (isUser && targetBody) {
        const cleanText = (msgData.message || '').trim().replace(/\s+/g, ' ');
        const normClean = cleanText.toLowerCase().replace(/[^\p{L}\p{N}]/gu, '');
        const existingUserRows = targetBody.querySelectorAll('.ff-msg-row.user');
        for (let i = existingUserRows.length - 1; i >= 0; i--) {
          const r = existingUserRows[i];
          const contentEl = r.querySelector('.ff-msg-content');
          const existingText = contentEl ? contentEl.textContent.trim().replace(/\s+/g, ' ') : '';
          const normExisting = (r.dataset.msgText || existingText).toLowerCase().replace(/[^\p{L}\p{N}]/gu, '');
          const textMatch = (cleanText && existingText === cleanText) || (normClean && normClean === normExisting) || (cleanText && existingText && (existingText.includes(cleanText) || cleanText.includes(existingText)));
          const imgMatch = imageUrl && (r.dataset.imageUrl === imageUrl || (r.dataset.imageUrl && imageUrl.includes(r.dataset.imageUrl)));
          if (textMatch || imgMatch) {
            // NẾU ĐÃ CÓ BONG BÓNG TIN NHẮN TRÙNG NỘI DUNG: CẬP NHẬT ID VÀ TUYỆT ĐỐI KHÔNG TẠO BUBBLE MỚI!
            if (msgIdStr) {
              r.dataset.msgId = msgIdStr;
              this.renderedMessageIds.add(msgIdStr);
            }
            if (imageUrl) r.dataset.imageUrl = imageUrl;
            return r;
          }
        }
      }
      // Chèn vạch phân cách ngày (Date Separator) nếu sang ngày mới
      if (targetBody) {
        const rawDate = msgData.createdAt ? new Date(msgData.createdAt.replace(' ', 'T')) : new Date();
        if (!isNaN(rawDate.getTime())) {
          const dateKey = rawDate.toLocaleDateString('vi-VN');
          const lastDateProp = (channel === 'admin') ? '_lastAdminDate' : '_lastAiDate';
          if (this[lastDateProp] !== dateKey) {
            this[lastDateProp] = dateKey;
            const todayKey = new Date().toLocaleDateString('vi-VN');
            const yesterdayKey = new Date(Date.now() - 86400000).toLocaleDateString('vi-VN');
            let dateLabel = dateKey;
            if (dateKey === todayKey) dateLabel = `Hôm nay, ${dateKey}`;
            else if (dateKey === yesterdayKey) dateLabel = `Hôm qua, ${dateKey}`;
            else {
              const dayName = rawDate.toLocaleDateString('vi-VN', { weekday: 'long' });
              dateLabel = `${dayName.charAt(0).toUpperCase() + dayName.slice(1)}, ${dateKey}`;
            }

            const dateDivider = document.createElement('div');
            dateDivider.className = 'ff-chat-date-divider';
            dateDivider.innerHTML = `<span>📅 ${dateLabel}</span>`;
            targetBody.appendChild(dateDivider);
          }
        }
      }

      // Chuẩn hóa giờ hiển thị chính xác theo giờ Việt Nam
      let timeStr = '';
      if (msgData.createdAt) {
        const d = new Date(msgData.createdAt.replace(' ', 'T'));
        if (!isNaN(d.getTime())) {
          timeStr = d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
        }
      }
      if (!timeStr && msgData.time && /^\d{1,2}:\d{2}$/.test(msgData.time)) {
        timeStr = msgData.time;
      }
      if (!timeStr) {
        timeStr = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
      }

      const row = document.createElement('article');
      row.className = `ff-msg-row ${isUser ? 'user' : 'assistant'}${isAdmin ? ' admin' : ''}`;
      if (msgIdStr) row.dataset.msgId = msgIdStr;
      if (imageUrl) row.dataset.imageUrl = imageUrl;
      if (isUser && msgData.message) row.dataset.msgText = (msgData.message || '').trim().replace(/\s+/g, ' ');

      let avatarHtml = '';
      let senderLabelHtml = '';
      if (!isUser) {
        avatarHtml = isAdmin
          ? `<div class="ff-msg-avatar" title="Cửa hàng FASTFOOD">👨‍💼</div>`
          : `<div class="ff-msg-avatar" title="Trợ lý AI FASTFOOD">🤖</div>`;
        senderLabelHtml = isAdmin
          ? `<div class="ff-sender-tag is-admin">👨‍💼 ${this.escapeHtml(msgData.metadata?.adminName || 'Cửa hàng FASTFOOD')}</div>`
          : `<div class="ff-sender-tag">🤖 Trợ lý AI FASTFOOD</div>`;
      }

      // Hiển thị khối ảnh nếu có
      let imageBubbleHtml = '';
      if (isImageMsg && imageUrl) {
        imageBubbleHtml = `
          <div class="ff-msg-image-wrap" data-img-url="${this.escapeHtml(imageUrl)}" title="Nhấp để xem ảnh phóng to">
            <img src="${this.escapeHtml(imageUrl)}"
                 alt="${this.escapeHtml(msgData.message || 'Hình ảnh chat')}"
                 class="ff-msg-image"
                 loading="lazy"
                 onerror="this.style.display='none'; if (this.nextElementSibling) this.nextElementSibling.style.display='flex';" />
            <div class="ff-msg-image-error" style="display: none;">
              <span>⚠️ Không thể tải hình ảnh</span>
            </div>
          </div>
        `;
      }

      const showText = (isImageMsg && (!msgData.message || msgData.message === '[Hình ảnh]'))
        ? ''
        : (msgData.message || '');
      const formattedText = showText ? `<div class="ff-msg-content">${this.formatMarkdown(showText)}</div>` : '';

      let cardsHtml = '';
      if (isProductCard && (msgData.metadata?.product || msgData.metadata?.products?.[0])) {
        cardsHtml = this.buildSingleProductCardHtml(msgData.metadata.product || msgData.metadata.products[0]);
      } else if (msgData.metadata?.products && msgData.metadata.products.length > 0) {
        cardsHtml = this.buildProductCardsHtml(msgData.metadata.products);
      }
      if (msgData.metadata?.order && msgData.metadata.order.isAuthorized !== false && msgData.metadata.order.found !== false) {
        cardsHtml += this.buildOrderCardHtml(msgData.metadata.order);
      }

      const bubbleClasses = `ff-msg-bubble ${isUser ? 'ff-msg-user chat-msg-user message-user' : (isAdmin ? 'ff-msg-admin chat-msg-admin message-admin' : 'ff-msg-ai ff-msg-assistant chat-msg-ai')}`;

      row.innerHTML = `
        ${avatarHtml}
        <div class="${bubbleClasses}">
          ${senderLabelHtml}
          ${imageBubbleHtml}
          ${formattedText}
          ${cardsHtml}
          ${timeStr ? `<span class="ff-msg-time">${timeStr}</span>` : ''}
        </div>
      `;

      // Gắn sự kiện phóng to ảnh (Lightbox Zoom)
      const imgWraps = row.querySelectorAll('.ff-msg-image-wrap');
      imgWraps.forEach(wrap => {
        wrap.addEventListener('click', (e) => {
          e.stopPropagation();
          const u = wrap.dataset.imgUrl;
          if (u) this.openLightbox(u);
        });
      });

      const addBtns = row.querySelectorAll('.ff-btn-card-add');
      addBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          this.handleQuickAddToCart({
            id: btn.dataset.id,
            name: btn.dataset.name,
            price: Number(btn.dataset.price),
            image: btn.dataset.image,
          });
        });
      });

      // Đưa vào đúng body của kênh
      if (targetBody) {
        targetBody.appendChild(row);
      }

      // Thông báo có tin nhắn Admin nếu đang ở tab AI
      if (channel === 'admin' && isAdmin && this.currentChannel !== 'admin' && this.elements.adminDot) {
        this.elements.adminDot.style.display = 'inline-block';
        window.UIComponents?.showToast?.('Cửa hàng vừa phản hồi trong mục "Liên hệ với cửa hàng"!', 'info');
      }

      this.scrollToBottom();
      return row;
    },

    buildSingleProductCardHtml(product) {
      if (!product) return '';
      const p = product;
      const priceFmt = new Intl.NumberFormat('vi-VN').format(p.price || 0) + 'đ';
      const oldPrice = Number(p.originalPrice || p.basePrice || 0);
      const curPrice = Number(p.price || 0);
      const oldPriceFmt = (oldPrice > curPrice) ? new Intl.NumberFormat('vi-VN').format(oldPrice) + 'đ' : '';
      const discountPercent = p.discountPercent || (oldPrice > curPrice ? Math.round(((oldPrice - curPrice) / oldPrice) * 100) : 0);
      const discountBadge = discountPercent > 0 ? `<span class="ff-card-discount-tag">-${discountPercent}%</span>` : '';
      
      const detailUrl = this.resolveFrontendPath(`food-detail.php?id=${encodeURIComponent(p.id)}`);
      const imgSrc = this.normalizeImageUrl(p.image);

      const status = p.status || (p.isAvailable === 0 || p.is_available === 0 ? 'out_of_stock' : 'in_stock');
      let statusBadge = '';
      let canOrder = true;

      if (status === 'out_of_stock') {
        statusBadge = '<span class="ff-card-status out-of-stock">🟠 Tạm hết</span>';
        canOrder = false;
      } else if (status === 'unavailable_today') {
        statusBadge = '<span class="ff-card-status unavailable">🔴 Hôm nay không bán</span>';
        canOrder = false;
      } else {
        statusBadge = '<span class="ff-card-status in-stock">🟢 Còn hàng</span>';
        canOrder = true;
      }

      const desc = p.description ? `<p class="ff-single-card-desc">${this.escapeHtml(p.description)}</p>` : '';

      const orderBtn = canOrder
        ? `<button type="button" class="ff-btn-card-add ff-btn-single-order"
             data-id="${this.escapeHtml(p.id)}"
             data-name="${this.escapeHtml(p.name)}"
             data-price="${curPrice}"
             data-image="${this.escapeHtml(imgSrc)}">
             🛒 Đặt món
           </button>`
        : `<button type="button" class="ff-btn-single-order disabled" disabled>
             ${status === 'unavailable_today' ? 'Hôm nay không bán' : 'Tạm hết món'}
           </button>`;

      return `
        <div class="ff-single-product-card" data-product-id="${this.escapeHtml(p.id)}">
          <div class="ff-single-card-top">
            <div class="ff-single-card-thumb-wrap">
              <img src="${this.escapeHtml(imgSrc)}" alt="${this.escapeHtml(p.name)}" class="ff-single-card-thumb" loading="lazy" onerror="this.onerror=null;this.src='${DEFAULT_FOOD_IMG}'" />
              ${discountBadge}
            </div>
            <div class="ff-single-card-meta">
              <span class="ff-single-card-badge-row">${statusBadge}</span>
              <h4 class="ff-single-card-title" title="${this.escapeHtml(p.name)}">${this.escapeHtml(p.name)}</h4>
              <span class="ff-single-card-cat">${this.escapeHtml(p.categoryName || p.restaurantName || 'FASTFOOD')}</span>
              <div class="ff-single-card-price-row">
                <span class="ff-single-card-price">${priceFmt}</span>
                ${oldPriceFmt ? `<span class="ff-single-card-old-price">${oldPriceFmt}</span>` : ''}
              </div>
            </div>
          </div>
          ${desc}
          <div class="ff-single-card-actions">
            <a href="${detailUrl}" class="ff-btn-single-view">Xem chi tiết</a>
            ${orderBtn}
          </div>
        </div>
      `;
    },

    buildProductCardsHtml(products) {
      if (!Array.isArray(products) || products.length === 0) return '';

      const cards = products.slice(0, 4).map(p => {
        const priceFmt = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
        const oldPriceFmt = p.originalPrice && p.originalPrice > p.price
          ? new Intl.NumberFormat('vi-VN').format(p.originalPrice) + 'đ' : '';
        const discountBadge = p.discountPercent ? `<span class="ff-card-discount-tag">-${p.discountPercent}%</span>` : '';
        const detailUrl = this.resolveFrontendPath(`pages/food-detail.html?id=${encodeURIComponent(p.id)}`);
        const imgSrc = this.normalizeImageUrl(p.image);

        return `
          <div class="ff-product-card">
            <div class="ff-card-thumb-wrap">
              <img src="${this.escapeHtml(imgSrc)}" alt="${this.escapeHtml(p.name)}" class="ff-card-thumb" loading="lazy" onerror="this.onerror=null;this.src='${DEFAULT_FOOD_IMG}'" />
              ${window.UIComponents?.renderImageCredit(p) || ''}
              ${discountBadge}
            </div>
            <div class="ff-card-info">
              <h4 class="ff-card-title" title="${this.escapeHtml(p.name)}">${this.escapeHtml(p.name)}</h4>
              <p class="ff-card-restaurant">${this.escapeHtml(p.restaurantName || 'FASTFOOD')}</p>
              <div class="ff-card-pricing">
                <span class="ff-card-price">${priceFmt}</span>
                ${oldPriceFmt ? `<span class="ff-card-old-price">${oldPriceFmt}</span>` : ''}
              </div>
              <div class="ff-card-actions">
                <a href="${detailUrl}" class="ff-btn-card-view">Xem</a>
                <button type="button" class="ff-btn-card-add"
                  data-id="${this.escapeHtml(p.id)}"
                  data-name="${this.escapeHtml(p.name)}"
                  data-price="${Number(p.price || 0)}"
                  data-image="${this.escapeHtml(imgSrc)}">
                  + Giỏ
                </button>
              </div>
            </div>
          </div>
        `;
      }).join('');

      return `<div class="ff-product-grid">${cards}</div>`;
    },

    buildOrderCardHtml(order) {
      if (!order || !order.orderCode) return '';
      const totalFmt = order.totalAmount ? new Intl.NumberFormat('vi-VN').format(order.totalAmount) + 'đ' : '—';
      const statusClass = order.status === 'delivered' || order.status === 'completed'
        ? 'delivered'
        : (order.status === 'cancelled' ? 'cancelled' : '');
      const trackUrl = this.resolveFrontendPath(`pages/order-tracking.html?orderCode=${encodeURIComponent(order.orderCode)}`);

      return `
        <div class="ff-order-card">
          <div class="ff-order-card-header">
            <span class="ff-order-code">Đơn #${this.escapeHtml(order.orderCode)}</span>
            <span class="ff-order-badge ${statusClass}">${this.escapeHtml(order.displayStatus || 'Đang xử lý')}</span>
          </div>
          <div class="ff-order-detail-row">
            <span>Tổng tiền:</span>
            <span>${totalFmt}</span>
          </div>
          <a href="${trackUrl}" class="ff-order-track-link">
            Xem lộ trình đơn hàng ➔
          </a>
        </div>
      `;
    },

    handleQuickAddToCart(item) {
      try {
        const catalogProduct = window.FASTFOOD_DATA?.products?.find(p => String(p.id) === String(item.id));
        const productToAdd = catalogProduct || {
          id: item.id,
          name: item.name,
          price: Number(item.price || 0),
          image: item.image,
          restaurantId: 'res-1',
          restaurantName: 'FASTFOOD',
          toppings: [],
        };

        if (window.CartController && typeof window.CartController.addItem === 'function') {
          window.CartController.addItem(productToAdd, 1, []);
          return;
        }

        const cartKey = 'fastfood_cart';
        const current = JSON.parse(localStorage.getItem(cartKey) || '[]');
        const existing = current.find(x => String(x.id) === String(productToAdd.id));
        if (existing) {
          existing.qty = (existing.qty || existing.quantity || 1) + 1;
        } else {
          current.push({
            key: `${productToAdd.id}::`,
            productId: productToAdd.id,
            id: productToAdd.id,
            name: productToAdd.name,
            basePrice: productToAdd.price,
            price: productToAdd.price,
            image: productToAdd.image,
            restaurantId: productToAdd.restaurantId || 'res-1',
            restaurantName: productToAdd.restaurantName || 'FASTFOOD',
            selectedToppings: [],
            qty: 1,
          });
        }
        localStorage.setItem(cartKey, JSON.stringify(current));
        window.UIComponents?.updateCartBadge?.();
        if (window.UIComponents?.showToast) {
          window.UIComponents.showToast(`Đã thêm "${productToAdd.name}" vào giỏ hàng!`, 'success');
        } else {
          alert(`Đã thêm "${productToAdd.name}" vào giỏ hàng!`);
        }
      } catch (err) {
        console.error('[Nhân viên hỗ trợ] Quick add to cart error:', err);
      }
    },

    formatMarkdown(text) {
      if (!text) return '';
      let safe = this.escapeHtml(text);
      safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
      safe = safe.replace(/_(.*?)_/g, '<em>$1</em>');

      const lines = safe.split('\n');
      const output = [];
      let inList = false;

      lines.forEach(line => {
        const trimmed = line.trim();
        if (trimmed.startsWith('• ') || trimmed.startsWith('- ')) {
          if (!inList) {
            output.push('<ul>');
            inList = true;
          }
          output.push(`<li>${trimmed.substring(2)}</li>`);
        } else {
          if (inList) {
            output.push('</ul>');
            inList = false;
          }
          if (trimmed) {
            output.push(`<p>${trimmed}</p>`);
          }
        }
      });

      if (inList) output.push('</ul>');
      return output.join('');
    },

    escapeHtml(str) {
      return String(str ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
      })[char]);
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => FASTFOODChatbot.init());
  } else {
    FASTFOODChatbot.init();
  }

  window.FASTFOODChatbot = FASTFOODChatbot;
})(window, document);
