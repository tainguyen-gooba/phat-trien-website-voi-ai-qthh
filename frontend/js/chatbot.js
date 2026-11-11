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
  const API_BASE = window.location.port === '5000'
    ? `${window.location.origin}/api/v1/chat`
    : 'http://localhost:5000/api/v1/chat';

  const FASTFOODChatbot = {
    isOpen: false,
    isExpanded: false,
    sessionId: null,
    isSubmitting: false,
    initialized: false,
    lastRenderedMsgId: 0,
    renderedMessageIds: new Set(),
    pollTimer: null,
    supportStatus: { adminOnline: false, effectiveBusy: true, aiPaused: false },
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
        <button type="button" class="ff-chat-trigger" id="ff-chat-trigger" aria-label="Mở khung chat Nhân viên hỗ trợ" title="Chat với Nhân viên hỗ trợ FASTFOOD">
          <div class="ff-trigger-glow"></div>
          <div class="ff-trigger-icon-wrap">
            <svg class="ff-icon-bot" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
              <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
            </svg>
            <svg class="ff-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </div>
          <span class="ff-trigger-badge">24/7</span>
          <span class="ff-trigger-tooltip">Nhắn với Nhân viên hỗ trợ FASTFOOD!</span>
        </button>
        <section class="ff-chat-window" id="ff-chat-window" role="dialog" aria-modal="true" aria-label="Khung trò chuyện với Nhân viên hỗ trợ" hidden>
          <header class="ff-chat-header">
            <div class="ff-header-identity">
              <div class="ff-avatar-wrap">
                <span class="ff-avatar-icon" id="ff-header-avatar">👨‍💼</span>
                <span class="ff-status-dot online" id="ff-header-status-dot"></span>
              </div>
              <div class="ff-header-info">
                <div class="ff-header-title-row">
                  <h3 class="ff-chat-name">Nhân viên hỗ trợ</h3>
                  <span class="ff-verified-badge">✓</span>
                </div>
                <p class="ff-chat-status" id="ff-chat-status-text">Hỗ trợ khách hàng trực tuyến 24/7</p>
              </div>
            </div>
            <div class="ff-header-actions">
              <button type="button" class="ff-btn-icon" id="ff-btn-new-chat" title="Cuộc trò chuyện mới"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M8 16H3v5"></path></svg></button>
              <button type="button" class="ff-btn-icon" id="ff-btn-expand" title="Mở rộng khung chat"><svg class="ff-icon-maximize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"></path><path d="M9 21H3v-6"></path><path d="M21 3l-7 7"></path><path d="M3 21l7-7"></path></svg><svg class="ff-icon-restore" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M4 14h6v6"></path><path d="M20 10h-6V4"></path><path d="M14 10l7-7"></path><path d="M10 14L3 21"></path></svg></button>
              <button type="button" class="ff-btn-icon" id="ff-btn-minimize" title="Thu nhỏ"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line></svg></button>
              <button type="button" class="ff-btn-icon ff-btn-close" id="ff-btn-close" title="Đóng chat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
            </div>
          </header>
          <div class="ff-chat-body" id="ff-chat-body" aria-live="polite"></div>
          <div class="ff-chat-typing" id="ff-chat-typing" hidden>
            <div class="ff-typing-avatar">👨‍💼</div>
            <div class="ff-typing-bubble">
              <span>Nhân viên hỗ trợ đang phản hồi</span>
              <div class="ff-typing-dots"><span></span><span></span><span></span></div>
            </div>
          </div>
          <div class="ff-quick-chips-wrap">
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
            <form class="ff-chat-form" id="ff-chat-form" autocomplete="off">
              <input type="text" class="ff-chat-input" id="ff-chat-input" name="message" placeholder="Nhắn tin cho Nhân viên hỗ trợ..." maxlength="1000" required />
              <button type="submit" class="ff-chat-send-btn" id="ff-chat-send-btn" aria-label="Gửi tin nhắn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
              </button>
            </form>
            <div class="ff-chat-disclaimer"><span>⚡ Kết nối trực tiếp đội ngũ hỗ trợ FASTFOOD</span></div>
          </footer>
        </section>
      `;
      document.body.appendChild(wrapper);
    },

    cacheElements() {
      this.elements = {
        wrapper: document.getElementById('ff-chatbot-wrapper'),
        trigger: document.getElementById('ff-chat-trigger'),
        window: document.getElementById('ff-chat-window'),
        body: document.getElementById('ff-chat-body'),
        typing: document.getElementById('ff-chat-typing'),
        form: document.getElementById('ff-chat-form'),
        input: document.getElementById('ff-chat-input'),
        btnSend: document.getElementById('ff-chat-send-btn'),
        btnClose: document.getElementById('ff-btn-close'),
        btnMinimize: document.getElementById('ff-btn-minimize'),
        btnExpand: document.getElementById('ff-btn-expand'),
        btnNewChat: document.getElementById('ff-btn-new-chat'),
        quickChips: document.getElementById('ff-quick-chips'),
        statusText: document.getElementById('ff-chat-status-text'),
        statusDot: document.getElementById('ff-header-status-dot'),
      };
    },

    initSession() {
      let saved = localStorage.getItem(STORAGE_SESSION_KEY);
      if (!saved) {
        saved = 'ff_chat_' + Math.random().toString(36).substring(2, 12) + Date.now().toString(36);
        localStorage.setItem(STORAGE_SESSION_KEY, saved);
      }
      this.sessionId = saved;
    },

    getAuthToken() {
      return sessionStorage.getItem('fastfood_auth_token')
        || localStorage.getItem('fastfood_auth_token')
        || null;
    },

    getCurrentCustomerName() {
      try {
        const u = window.StorageManager?.getUser?.();
        if (u?.full_name) return u.full_name;
      } catch (_) {}
      return 'Khách hàng';
    },

    bindEvents() {
      const { trigger, btnClose, btnMinimize, btnExpand, btnNewChat, form, quickChips } = this.elements;

      trigger?.addEventListener('click', () => this.toggleChat());
      btnClose?.addEventListener('click', () => this.toggleChat(false));
      btnMinimize?.addEventListener('click', () => this.toggleChat(false));
      btnExpand?.addEventListener('click', () => this.toggleExpand());
      btnNewChat?.addEventListener('click', () => this.startNewSession());

      form?.addEventListener('submit', (e) => {
        e.preventDefault();
        const val = this.elements.input.value.trim();
        if (val) this.sendMessage(val);
      });

      quickChips?.addEventListener('click', (e) => {
        const btn = e.target.closest('.ff-chip-item');
        if (btn) {
          const query = btn.dataset.query || btn.textContent.trim();
          this.sendMessage(query);
        }
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.isOpen) {
          this.toggleChat(false);
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
        if (!payload || payload.sessionId !== this.sessionId || !payload.message) return;
        const msg = payload.message;
        if (msg.sender === 'admin' || msg.sender === 'assistant') {
          this.renderMessage(msg);
          if (!this.isOpen && msg.sender === 'admin') {
            window.UIComponents?.showToast('Nhân viên hỗ trợ vừa phản hồi tin nhắn của bạn!', 'info');
          }
        }
      });
    },

    joinSupportRoom() {
      const socket = window.FASTFOOD_SOCKET;
      if (socket && socket.connected && this.sessionId) {
        socket.emit('support:join', { sessionId: this.sessionId });
      }
    },

    updateSupportStatusUI(status) {
      if (!status) return;
      this.supportStatus = { ...this.supportStatus, ...status };
      const { statusText, statusDot } = this.elements;
      if (!statusText) return;

      statusText.textContent = 'Hỗ trợ khách hàng trực tuyến 24/7';
      statusDot?.classList.add('online');
      statusDot?.classList.remove('busy');
    },

    startPolling() {
      this.stopPolling();
      this.pollTimer = setInterval(() => {
        if (this.isOpen && !this.isSubmitting) {
          this.syncLatestMessages();
        }
      }, 3000);
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
        const res = await fetch(`${API_BASE}/history?sessionId=${encodeURIComponent(this.sessionId)}`, { headers });
        if (!res.ok) return;
        const result = await res.json();
        if (!result.success) return;

        if (result.data?.supportStatus) {
          this.updateSupportStatusUI(result.data.supportStatus);
        }

        const messages = result.data?.messages;
        if (!Array.isArray(messages)) return;
        messages.forEach(m => {
          if (m.id && !this.renderedMessageIds.has(String(m.id))) {
            this.renderMessage(m);
          }
        });
      } catch (_) {}
    },

    toggleChat(forceState) {
      this.isOpen = typeof forceState === 'boolean' ? forceState : !this.isOpen;
      const { trigger, window: chatWindow } = this.elements;

      if (this.isOpen) {
        chatWindow.removeAttribute('hidden');
        trigger.classList.add('is-active');
        this.joinSupportRoom();
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

      const confirmed = window.confirm('Bắt đầu cuộc trò chuyện mới với Nhân viên hỗ trợ?');
      if (!confirmed) return;

      this.elements.body.innerHTML = '';
      this.renderedMessageIds.clear();
      this.lastRenderedMsgId = 0;
      this.showTyping(true);

      try {
        const token = this.getAuthToken();
        const headers = { 'Content-Type': 'application/json' };
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const res = await fetch(`${API_BASE}/new-session`, { method: 'POST', headers });
        const result = await res.json();

        if (res.ok && result.success && result.data?.sessionId) {
          this.sessionId = result.data.sessionId;
          localStorage.setItem(STORAGE_SESSION_KEY, this.sessionId);
          this.joinSupportRoom();
          if (result.data.supportStatus) {
            this.updateSupportStatusUI(result.data.supportStatus);
          }
          this.renderMessage({
            sender: 'assistant',
            message: result.data.welcomeMessage || 'Dạ chào bạn! Tôi là Nhân viên hỗ trợ FASTFOOD. Bạn cần tìm món ngon, săn khuyến mãi hay kiểm tra đơn hàng nào hôm nay?',
          });
        } else {
          this.renderDefaultWelcome();
        }
      } catch (err) {
        console.warn('[Nhân viên hỗ trợ] Tạo phiên offline:', err);
        this.renderDefaultWelcome();
      } finally {
        this.showTyping(false);
      }
    },

    async loadHistory() {
      try {
        const token = this.getAuthToken();
        const headers = {};
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const res = await fetch(`${API_BASE}/history?sessionId=${encodeURIComponent(this.sessionId)}`, { headers });
        const result = await res.json();

        if (result.success && result.data?.supportStatus) {
          this.updateSupportStatusUI(result.data.supportStatus);
        }

        if (result.success && Array.isArray(result.data?.messages) && result.data.messages.length > 0) {
          this.elements.body.innerHTML = '';
          this.renderedMessageIds.clear();
          result.data.messages.forEach(m => this.renderMessage(m));
        } else {
          this.renderDefaultWelcome();
        }
      } catch (_) {
        this.renderDefaultWelcome();
      }
    },

    renderDefaultWelcome() {
      this.elements.body.innerHTML = '';
      this.renderedMessageIds.clear();
      const welcomeText = `Xin chào bạn! Tôi là **Nhân viên hỗ trợ** của nền tảng đặt đồ ăn FASTFOOD Buôn Ma Thuột 💖\n\n`
        + `Mình có thể hỗ trợ bạn ngay lập tức:\n`
        + `• Gợi ý món ăn ngon, bán chạy hôm nay 🔥\n`
        + `• Tìm món giá sinh viên, combo tiết kiệm (dưới 50K) 💰\n`
        + `• Hướng dẫn đặt hàng, phí ship & mã giảm giá 🎁\n`
        + `• Tra cứu đơn hàng & kết nối trực tiếp với Admin 📦\n\n`
        + `Bạn bấm vào gợi ý bên dưới hoặc nhắn câu hỏi cho mình nhé!`;

      this.renderMessage({
        sender: 'assistant',
        message: welcomeText,
      });
    },

    /**
     * Dự phòng ngay tại trình duyệt nếu máy đồng đội chưa bật Backend Node.js
     */
    buildLocalOfflineReply(userText) {
      const data = window.FASTFOOD_DATA;
      const products = Array.isArray(data?.products) ? data.products : [];
      const norm = userText
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[đĐ]/g, 'd')
        .toLowerCase();

      if (products.length === 0) {
        return {
          reply: 'Dạ Nhân viên hỗ trợ đã ghi nhận câu hỏi của bạn. Hiện kết nối tới máy chủ đang tạm gián đoạn, bạn thử kiểm tra lại backend (cổng 5000) hoặc duyệt thực đơn trên trang nhé!',
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
        reply = 'Dạ Nhân viên hỗ trợ tìm thấy các món phù hợp với nhu cầu của bạn:\n\n';
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
      }));

      return { reply, products: formattedProducts };
    },

    async sendMessage(text) {
      if (!text || this.isSubmitting) return;

      const userText = text.trim();
      this.elements.input.value = '';
      this.isSubmitting = true;

      this.renderMessage({
        sender: 'user',
        message: userText,
        time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
      });

      this.showTyping(true);

      try {
        const token = this.getAuthToken();
        const headers = { 'Content-Type': 'application/json' };
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const res = await fetch(`${API_BASE}/message`, {
          method: 'POST',
          headers,
          body: JSON.stringify({
            message: userText,
            sessionId: this.sessionId,
            customerName: this.getCurrentCustomerName(),
          }),
        });

        const result = await res.json();
        this.showTyping(false);

        if (res.ok && result.success && result.data) {
          const { autoReplied, reply, products, order, messageId, adminBusy, adminOnline } = result.data;
          this.updateSupportStatusUI({ adminBusy, effectiveBusy: adminBusy, adminOnline });

          if (autoReplied && reply) {
            this.renderMessage({
              id: messageId,
              sender: 'assistant',
              message: reply,
              metadata: { products, order },
              time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
            });
          }
        } else {
          const fallback = this.buildLocalOfflineReply(userText);
          this.renderMessage({
            sender: 'assistant',
            message: fallback.reply,
            metadata: { products: fallback.products },
          });
        }
      } catch (err) {
        console.warn('[Nhân viên hỗ trợ] Dùng chế độ dự phòng cục bộ:', err);
        this.showTyping(false);
        const fallback = this.buildLocalOfflineReply(userText);
        this.renderMessage({
          sender: 'assistant',
          message: fallback.reply,
          metadata: { products: fallback.products },
          time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
        });
      } finally {
        this.isSubmitting = false;
        setTimeout(() => this.elements.input?.focus(), 100);
      }
    },

    showTyping(show) {
      if (show) {
        this.elements.typing.removeAttribute('hidden');
      } else {
        this.elements.typing.setAttribute('hidden', '');
      }
      this.scrollToBottom();
    },

    scrollToBottom() {
      requestAnimationFrame(() => {
        if (this.elements.body) {
          this.elements.body.scrollTop = this.elements.body.scrollHeight;
        }
      });
    },

    renderMessage(msgData) {
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

      const isUser = msgData.sender === 'user';
      const isAdmin = msgData.sender === 'admin';
      const timeStr = msgData.time
        || (msgData.createdAt ? new Date(msgData.createdAt).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) : '');

      const row = document.createElement('article');
      row.className = `ff-msg-row ${isUser ? 'user' : 'assistant'}${isAdmin ? ' admin' : ''}`;
      if (msgIdStr) row.dataset.msgId = msgIdStr;

      let avatarHtml = '';
      let senderLabelHtml = '';
      if (!isUser) {
        avatarHtml = isAdmin
          ? `<div class="ff-msg-avatar" title="Nhân viên hỗ trợ trực tiếp">👨‍💼</div>`
          : `<div class="ff-msg-avatar" title="Nhân viên hỗ trợ tự động">🤖</div>`;
        senderLabelHtml = isAdmin
          ? `<div class="ff-sender-tag is-admin">👨‍💼 ${this.escapeHtml(msgData.metadata?.adminName || 'Nhân viên hỗ trợ trực tiếp')}</div>`
          : `<div class="ff-sender-tag">🤖 Trợ lý tự động</div>`;
      }

      const formattedText = this.formatMarkdown(msgData.message || '');

      let cardsHtml = '';
      if (msgData.metadata?.products && msgData.metadata.products.length > 0) {
        cardsHtml = this.buildProductCardsHtml(msgData.metadata.products);
      }
      if (msgData.metadata?.order && msgData.metadata.order.isAuthorized !== false && msgData.metadata.order.found !== false) {
        cardsHtml += this.buildOrderCardHtml(msgData.metadata.order);
      }

      row.innerHTML = `
        ${avatarHtml}
        <div class="ff-msg-bubble">
          ${senderLabelHtml}
          <div class="ff-msg-content">${formattedText}</div>
          ${cardsHtml}
          ${timeStr ? `<span class="ff-msg-time">${timeStr}</span>` : ''}
        </div>
      `;

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

      this.elements.body.appendChild(row);
      this.scrollToBottom();
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
