/**
 * FASTFOOD AI CHATBOT CONTROLLER
 * Xử lý tương tác giao diện người dùng, kết nối Backend RESTful API (/api/v1/chat),
 * hiển thị danh thiếp món ăn, thẻ tra cứu đơn hàng và lưu trữ lịch sử
 */

(function (window, document) {
  'use strict';

  const STORAGE_SESSION_KEY = 'fastfood_ai_session_id';
  const API_BASE = '/api/v1/chat';

  const FASTFOODChatbot = {
    isOpen: false,
    isExpanded: false,
    sessionId: null,
    isSubmitting: false,
    initialized: false,

    // Cấu hình selectors DOM
    elements: {},

    /**
     * Khởi tạo chatbot
     */
    async init() {
      if (this.initialized) return;

      // Đảm bảo CSS đã được nạp
      this.ensureStylesheet();

      // Kiểm tra và tiêm HTML nếu chưa có
      if (!document.getElementById('ff-chatbot-wrapper')) {
        await this.injectChatbotHtml();
      }

      this.cacheElements();
      this.initSession();
      this.bindEvents();
      this.initialized = true;

      // Tải lịch sử tin nhắn ban đầu nếu đã có session
      await this.loadHistory();
    },

    /**
     * Đảm bảo stylesheet chatbot.css được nhúng vào trang
     */
    ensureStylesheet() {
      if (document.querySelector('link[href*="chatbot.css"]')) return;
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = '/css/chatbot.css';
      document.head.appendChild(link);
    },

    /**
     * Tiêm mã HTML của chatbot vào trang
     */
    async injectChatbotHtml() {
      try {
        const res = await fetch('/components/chatbot.html');
        if (res.ok) {
          const html = await res.text();
          const div = document.createElement('div');
          div.innerHTML = html;
          document.body.appendChild(div.firstElementChild);
        } else {
          console.warn('[FASTFOOD AI] Could not load /components/chatbot.html directly, using inline markup.');
          this.injectFallbackHtml();
        }
      } catch (_) {
        this.injectFallbackHtml();
      }
    },

    injectFallbackHtml() {
      // Fallback HTML nếu fetch components bị chặn
      const wrapper = document.createElement('div');
      wrapper.id = 'ff-chatbot-wrapper';
      wrapper.className = 'ff-chatbot-wrapper';
      wrapper.innerHTML = `
        <button type="button" class="ff-chat-trigger" id="ff-chat-trigger" aria-label="Mở Trợ lý AI FASTFOOD" title="Chat với FASTFOOD AI">
          <div class="ff-trigger-glow"></div>
          <div class="ff-trigger-icon-wrap">
            <svg class="ff-icon-bot" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8V4H8"></path><rect width="16" height="12" x="4" y="8" rx="2"></rect><path d="M2 14h2"></path><path d="M20 14h2"></path><path d="M15 13v2"></path><path d="M9 13v2"></path></svg>
            <svg class="ff-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </div>
          <span class="ff-trigger-badge">AI</span>
          <span class="ff-trigger-tooltip">Hỏi FASTFOOD AI món ngon &amp; ưu đãi!</span>
        </button>
        <section class="ff-chat-window" id="ff-chat-window" role="dialog" aria-modal="true" aria-label="Khung trò chuyện với FASTFOOD AI" hidden>
          <header class="ff-chat-header">
            <div class="ff-header-identity">
              <div class="ff-avatar-wrap">
                <span class="ff-avatar-icon">🤖</span>
                <span class="ff-status-dot online"></span>
              </div>
              <div class="ff-header-info">
                <div class="ff-header-title-row">
                  <h3 class="ff-chat-name">FASTFOOD AI</h3>
                  <span class="ff-verified-badge">✓</span>
                </div>
                <p class="ff-chat-status">Trợ lý ảo thông minh 24/7</p>
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
            <div class="ff-typing-avatar">🤖</div>
            <div class="ff-typing-bubble">
              <span>FASTFOOD AI đang tra cứu dữ liệu</span>
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
              <input type="text" class="ff-chat-input" id="ff-chat-input" name="message" placeholder="Hỏi món ngon, giá tiền, khuyến mãi..." maxlength="1000" required />
              <button type="submit" class="ff-chat-send-btn" id="ff-chat-send-btn" aria-label="Gửi tin nhắn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
              </button>
            </form>
            <div class="ff-chat-disclaimer"><span>⚡ Trợ lý AI FASTFOOD kết nối dữ liệu thực tế từ MySQL</span></div>
          </footer>
        </section>
      `;
      document.body.appendChild(wrapper);
    },

    /**
     * Cache các element DOM
     */
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
      };
    },

    /**
     * Khởi tạo hoặc lấy Session ID từ localStorage
     */
    initSession() {
      let saved = localStorage.getItem(STORAGE_SESSION_KEY);
      if (!saved) {
        saved = 'ff_' + Math.random().toString(36).substring(2, 12) + Date.now().toString(36);
        localStorage.setItem(STORAGE_SESSION_KEY, saved);
      }
      this.sessionId = saved;
    },

    /**
     * Lấy JWT token nếu người dùng đã đăng nhập
     */
    getAuthToken() {
      return sessionStorage.getItem('fastfood_auth_token') 
        || localStorage.getItem('fastfood_auth_token')
        || null;
    },

    /**
     * Đăng ký các sự kiện tương tác
     */
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

      // Lắng nghe phím ESC để đóng
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.isOpen) {
          this.toggleChat(false);
        }
      });
    },

    /**
     * Mở / Đóng cửa sổ chat
     */
    toggleChat(forceState) {
      this.isOpen = typeof forceState === 'boolean' ? forceState : !this.isOpen;
      const { trigger, window } = this.elements;

      if (this.isOpen) {
        // Đóng panel chat CSKH live nếu đang mở để tránh đè giao diện
        const liveChatPanel = document.getElementById('live-chat-panel');
        const liveChatLauncher = document.getElementById('live-chat-launcher');
        if (liveChatPanel && liveChatPanel.classList.contains('open')) {
          liveChatPanel.classList.remove('open');
          if (liveChatLauncher) liveChatLauncher.hidden = false;
        }

        window.removeAttribute('hidden');
        trigger.classList.add('is-active');
        this.scrollToBottom();
        setTimeout(() => this.elements.input?.focus(), 150);
      } else {
        window.setAttribute('hidden', '');
        trigger.classList.remove('is-active');
      }
    },

    /**
     * Mở rộng / Thu nhỏ kích thước cửa sổ chat
     */
    toggleExpand() {
      this.isExpanded = !this.isExpanded;
      const { window, btnExpand } = this.elements;
      const maxIcon = btnExpand.querySelector('.ff-icon-maximize');
      const restoreIcon = btnExpand.querySelector('.ff-icon-restore');

      if (this.isExpanded) {
        window.classList.add('is-expanded');
        if (maxIcon) maxIcon.style.display = 'none';
        if (restoreIcon) restoreIcon.style.display = 'block';
        btnExpand.title = 'Thu gọn khung chat';
      } else {
        window.classList.remove('is-expanded');
        if (maxIcon) maxIcon.style.display = 'block';
        if (restoreIcon) restoreIcon.style.display = 'none';
        btnExpand.title = 'Mở rộng khung chat';
      }
      this.scrollToBottom();
    },

    /**
     * Bắt đầu phiên hội thoại mới
     */
    async startNewSession() {
      if (this.isSubmitting) return;

      const confirmed = window.confirm('Bắt đầu cuộc trò chuyện mới với FASTFOOD AI?');
      if (!confirmed) return;

      this.elements.body.innerHTML = '';
      this.showTyping(true);

      try {
        const token = this.getAuthToken();
        const headers = { 'Content-Type': 'application/json' };
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const res = await fetch(`${API_BASE}/new-session`, { method: 'POST', headers });
        const result = await res.json();

        if (result.success && result.data?.sessionId) {
          this.sessionId = result.data.sessionId;
          localStorage.setItem(STORAGE_SESSION_KEY, this.sessionId);
          this.showTyping(false);
          this.renderMessage({
            sender: 'assistant',
            message: result.data.welcomeMessage || 'Dạ chào bạn! Tôi là FASTFOOD AI. Bạn cần tìm món ngon, săn khuyến mãi hay tra cứu đơn hàng nào hôm nay?',
          });
        }
      } catch (err) {
        console.error('[FASTFOOD AI] Error starting new session:', err);
        this.showTyping(false);
        this.renderDefaultWelcome();
      }
    },

    /**
     * Tải lịch sử tin nhắn của phiên hiện tại
     */
    async loadHistory() {
      try {
        const token = this.getAuthToken();
        const headers = {};
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const res = await fetch(`${API_BASE}/history?sessionId=${encodeURIComponent(this.sessionId)}`, { headers });
        const result = await res.json();

        if (result.success && Array.isArray(result.data?.messages) && result.data.messages.length > 0) {
          this.elements.body.innerHTML = '';
          result.data.messages.forEach(m => this.renderMessage(m));
        } else {
          this.renderDefaultWelcome();
        }
      } catch (err) {
        console.warn('[FASTFOOD AI] Could not load chat history:', err);
        this.renderDefaultWelcome();
      }
    },

    /**
     * Hiển thị lời chào mặc định ban đầu
     */
    renderDefaultWelcome() {
      this.elements.body.innerHTML = '';
      const welcomeText = `Xin chào bạn! Tôi là **FASTFOOD AI** – Trợ lý ảo chính thức của nền tảng đặt đồ ăn FASTFOOD Buôn Ma Thuột 💖\n\n`
        + `Tôi có thể hỗ trợ bạn:\n`
        + `• Gợi ý món ăn ngon, bán chạy hôm nay 🔥\n`
        + `• Tìm món ăn giá sinh viên, combo tiết kiệm (dưới 50K) 💰\n`
        + `• Hướng dẫn đặt hàng, kiểm tra phí ship & mã giảm giá 🎁\n`
        + `• Tra cứu đơn hàng của bạn theo thời gian thực 📦\n\n`
        + `Bạn có thể bấm vào các gợi ý nhanh bên dưới hoặc đặt câu hỏi trực tiếp cho tôi nhé!`;

      this.renderMessage({
        sender: 'assistant',
        message: welcomeText,
      });
    },

    /**
     * Gửi tin nhắn đến Backend
     */
    async sendMessage(text) {
      if (!text || this.isSubmitting) return;

      const userText = text.trim();
      this.elements.input.value = '';
      this.isSubmitting = true;

      // 1. Render tin nhắn của User
      this.renderMessage({
        sender: 'user',
        message: userText,
        time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
      });

      // 2. Bật hiệu ứng Typing
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
          }),
        });

        const result = await res.json();
        this.showTyping(false);

        if (res.ok && result.success) {
          const { reply, products, order } = result.data;

          // Render tin nhắn phản hồi của AI
          this.renderMessage({
            sender: 'assistant',
            message: reply,
            metadata: { products, order },
            time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
          });
        } else {
          const errMsg = result.message || 'Hệ thống đang bận. Vui lòng thử lại sau giây lát!';
          this.renderMessage({
            sender: 'assistant',
            message: `⚠️ ${errMsg}`,
          });
        }
      } catch (err) {
        console.error('[FASTFOOD AI] Send message error:', err);
        this.showTyping(false);
        this.renderMessage({
          sender: 'assistant',
          message: 'Dạ mạng kết nối tới máy chủ AI đang chập chờn, bạn kiểm tra lại kết nối mạng và thử lại giúp mình nhé! 🛵',
        });
      } finally {
        this.isSubmitting = false;
        setTimeout(() => this.elements.input?.focus(), 100);
      }
    },

    /**
     * Hiển thị / Ẩn hiệu ứng đang nhập
     */
    showTyping(show) {
      if (show) {
        this.elements.typing.removeAttribute('hidden');
      } else {
        this.elements.typing.setAttribute('hidden', '');
      }
      this.scrollToBottom();
    },

    /**
     * Cuộn khung chat xuống đáy
     */
    scrollToBottom() {
      requestAnimationFrame(() => {
        if (this.elements.body) {
          this.elements.body.scrollTop = this.elements.body.scrollHeight;
        }
      });
    },

    /**
     * Render tin nhắn vào DOM
     */
    renderMessage(msgData) {
      const isUser = msgData.sender === 'user';
      const timeStr = msgData.time 
        || (msgData.createdAt ? new Date(msgData.createdAt).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) : '');

      const row = document.createElement('article');
      row.className = `ff-msg-row ${isUser ? 'user' : 'assistant'}`;

      let avatarHtml = '';
      if (!isUser) {
        avatarHtml = `<div class="ff-msg-avatar" title="FASTFOOD AI">🤖</div>`;
      }

      const formattedText = this.formatMarkdown(msgData.message || '');

      let cardsHtml = '';
      if (msgData.metadata?.products && msgData.metadata.products.length > 0) {
        cardsHtml = this.buildProductCardsHtml(msgData.metadata.products);
      }
      if (msgData.metadata?.order) {
        cardsHtml += this.buildOrderCardHtml(msgData.metadata.order);
      }

      row.innerHTML = `
        ${avatarHtml}
        <div class="ff-msg-bubble">
          <div class="ff-msg-content">${formattedText}</div>
          ${cardsHtml}
          ${timeStr ? `<span class="ff-msg-time">${timeStr}</span>` : ''}
        </div>
      `;

      // Gắn sự kiện thêm vào giỏ hàng trên các card
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

    /**
     * Tạo HTML danh thiếp sản phẩm gợi ý
     */
    buildProductCardsHtml(products) {
      if (!Array.isArray(products) || products.length === 0) return '';
      
      const cards = products.slice(0, 4).map(p => {
        const priceFmt = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
        const oldPriceFmt = p.originalPrice && p.originalPrice > p.price 
          ? new Intl.NumberFormat('vi-VN').format(p.originalPrice) + 'đ' : '';
        const discountBadge = p.discountPercent ? `<span class="ff-card-discount-tag">-${p.discountPercent}%</span>` : '';
        const detailUrl = `/pages/food-detail.html?id=${encodeURIComponent(p.id)}`;

        return `
          <div class="ff-product-card">
            <div class="ff-card-thumb-wrap">
              <img src="${p.image}" alt="${this.escapeHtml(p.name)}" class="ff-card-thumb" loading="lazy" />
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
                  data-id="${p.id}" 
                  data-name="${this.escapeHtml(p.name)}" 
                  data-price="${p.price}" 
                  data-image="${p.image}">
                  + Giỏ
                </button>
              </div>
            </div>
          </div>
        `;
      }).join('');

      return `<div class="ff-product-grid">${cards}</div>`;
    },

    /**
     * Tạo HTML thẻ tra cứu đơn hàng
     */
    buildOrderCardHtml(order) {
      if (!order || !order.orderCode) return '';
      const totalFmt = order.totalAmount ? new Intl.NumberFormat('vi-VN').format(order.totalAmount) + 'đ' : '—';
      const statusClass = order.status === 'delivered' ? 'delivered' : (order.status === 'cancelled' ? 'cancelled' : '');

      return `
        <div class="ff-order-card">
          <div class="ff-order-card-header">
            <span class="ff-order-code">Đơn #${order.orderCode}</span>
            <span class="ff-order-badge ${statusClass}">${order.displayStatus || 'Đang xử lý'}</span>
          </div>
          <div class="ff-order-detail-row">
            <span>Tổng tiền:</span>
            <span>${totalFmt}</span>
          </div>
          <div class="ff-order-detail-row">
            <span>Thanh toán:</span>
            <span>${order.paymentStatus === 'completed' ? 'Đã thanh toán ✅' : 'Chờ thanh toán ⏳'}</span>
          </div>
          <a href="/pages/order-tracking.html?orderCode=${encodeURIComponent(order.orderCode)}" class="ff-order-track-link">
            Xem lộ trình tài xế ➔
          </a>
        </div>
      `;
    },

    /**
     * Xử lý thêm nhanh món ăn vào giỏ hàng
     */
    handleQuickAddToCart(item) {
      try {
        if (window.Cart && typeof window.Cart.addItem === 'function') {
          window.Cart.addItem({
            id: item.id,
            name: item.name,
            price: item.price,
            image: item.image,
            quantity: 1,
            toppings: [],
          });
          if (typeof window.showToast === 'function') {
            window.showToast(`Đã thêm "${item.name}" vào giỏ hàng!`);
          } else if (window.UIComponents && typeof window.UIComponents.showToast === 'function') {
            window.UIComponents.showToast(`Đã thêm "${item.name}" vào giỏ hàng!`);
          } else {
            alert(`Đã thêm "${item.name}" vào giỏ hàng thành công!`);
          }
        } else {
          // Lưu vào storage mặc định nếu giỏ hàng dùng key riêng
          const cartKey = 'fastfood_cart_items';
          const current = JSON.parse(localStorage.getItem(cartKey) || '[]');
          const existing = current.find(x => x.id === item.id);
          if (existing) {
            existing.quantity = (existing.quantity || 1) + 1;
          } else {
            current.push({ ...item, quantity: 1, toppings: [] });
          }
          localStorage.setItem(cartKey, JSON.stringify(current));
          alert(`Đã thêm "${item.name}" vào giỏ hàng! Bạn có thể vào Giỏ hàng để thanh toán.`);
        }
      } catch (err) {
        console.error('[FASTFOOD AI] Quick add to cart error:', err);
      }
    },

    /**
     * Format Markdown đơn giản sang HTML an toàn
     */
    formatMarkdown(text) {
      if (!text) return '';
      let safe = this.escapeHtml(text);

      // In đậm **text**
      safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

      // In nghiêng _text_
      safe = safe.replace(/_(.*?)_/g, '<em>$1</em>');

      // Danh sách gạch đầu dòng • hoặc -
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

  // Tự động khởi chạy khi trang web tải xong
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => FASTFOODChatbot.init());
  } else {
    FASTFOODChatbot.init();
  }

  window.FASTFOODChatbot = FASTFOODChatbot;

})(window, document);

