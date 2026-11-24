/**
 * FASTFOOD ADMIN CHAT INBOX CONTROLLER
 * Quản lý hộp thư tin nhắn khách hàng & Chuyển đổi linh hoạt giữa:
 * 1. AI Tự Động Trả Lời (24/7)
 * 2. Admin Trực Tiếp Chat (Tắt AI tự động)
 * Kèm theo AI Copilot hỗ trợ soạn tin siêu tốc cho nhân viên
 */

(function () {
  'use strict';

  function initAdminChatInbox({ socket, apiUrl, token }) {
    const inbox = document.getElementById('chat-inbox');
    if (!inbox) return null;

    // Chuẩn hoá API URL: nếu apiUrl là 'api.php' hoặc không hợp lệ, fallback về window.BASE_URL + '/api/v1' hoặc '/api/v1'
    let resolvedApiUrl = (apiUrl && apiUrl !== 'api.php') ? apiUrl.replace(/\/+$/, '') : '';
    if (!resolvedApiUrl) {
      const siteBase = (typeof window.BASE_URL === 'string' && window.BASE_URL.trim()) ? window.BASE_URL.replace(/\/+$/, '') : '';
      resolvedApiUrl = siteBase ? `${siteBase}/api/v1` : '/api/v1';
    }

    let activeToken = token || (typeof sessionStorage !== 'undefined' ? (sessionStorage.getItem('fastfood_auth_token') || sessionStorage.getItem('fastfood_admin_token')) : '');

    const conversationList = document.getElementById('chat-conversation-list');
    const messageList = document.getElementById('admin-chat-messages');
    const threadTitle = document.getElementById('chat-thread-title');
    const customerMeta = document.getElementById('chat-customer-meta');
    const customerAvatar = document.getElementById('chat-customer-avatar');
    const customerTypeBadge = document.getElementById('chat-customer-type-badge');
    const assistantStatus = document.getElementById('chat-assistant-status');
    const feedback = document.getElementById('admin-chat-feedback');
    const form = document.getElementById('admin-chat-form');
    const input = document.getElementById('admin-chat-input-field') || form.elements.message;

    // Mode Switcher Controls
    const btnModeAi = document.getElementById('btn-mode-ai');
    const btnModeAdmin = document.getElementById('btn-mode-admin');
    const btnAdminBusyToggle = document.getElementById('btn-admin-busy-toggle');
    const modeBadge = document.getElementById('chat-mode-current-badge');
    const modeDesc = document.getElementById('chat-mode-desc-text');

    // Thread AI & Copilot Controls
    const btnSuggest = document.getElementById('btn-ai-suggest-reply');
    const btnToggleAuto = document.getElementById('btn-toggle-ai-automation');
    const btnDeleteConv = document.getElementById('btn-delete-conv');
    const btnClearAll = document.getElementById('btn-clear-all-conversations');
    const btnRefresh = document.getElementById('btn-refresh-conversations');
    const searchInput = document.getElementById('chat-conv-search-input');
    const intentTag = document.getElementById('ai-detected-intent');
    const quickChips = document.getElementById('ai-quick-chips');

    // Product Picker (Kho Món Ăn FASTFOOD) Controls
    const pickerDrawer = document.getElementById('admin-product-picker-drawer');
    const btnOpenPicker = document.getElementById('btn-open-product-picker');
    const btnAiOpenPicker = document.getElementById('btn-ai-open-picker');
    const pickerBtnClose = document.getElementById('picker-btn-close');
    const pickerBtnRefresh = document.getElementById('picker-btn-refresh');
    const pickerSearchInput = document.getElementById('picker-search-input');
    const pickerSearchClear = document.getElementById('picker-search-clear');
    const pickerCatSelect = document.getElementById('picker-cat-select');
    const pickerProductList = document.getElementById('picker-product-list');
    const pickerStatusSummary = document.getElementById('picker-status-summary');
    const pickerCntAll = document.getElementById('picker-cnt-all');
    const pickerCntAvailable = document.getElementById('picker-cnt-available');
    const pickerCntOut = document.getElementById('picker-cnt-out');
    const pickerCntDisabled = document.getElementById('picker-cnt-disabled');

    // Admin Chat Lightbox Modal Controls
    const lightboxModal = document.getElementById('admin-chat-lightbox-modal');
    const lightboxImage = document.getElementById('admin-lightbox-image');
    const lightboxClose = document.getElementById('admin-lightbox-close');
    const lightboxBackdrop = document.getElementById('admin-lightbox-backdrop');

    // Image Attachment & Upload Controls
    const attachBtn = document.getElementById('admin-chat-attach-btn');
    const fileInput = document.getElementById('admin-chat-file-input');
    const uploadPreview = document.getElementById('admin-chat-upload-preview');
    const previewThumb = document.getElementById('admin-preview-thumb');
    const previewFilename = document.getElementById('admin-preview-filename');
    const previewFilesize = document.getElementById('admin-preview-filesize');
    const btnRemovePreview = document.getElementById('admin-btn-remove-preview');

    let selectedImageFile = null;
    let selectedImagePreviewUrl = null;

    function formatFileSize(bytes) {
      if (!bytes || bytes <= 0) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function clearSelectedImage() {
      selectedImageFile = null;
      if (selectedImagePreviewUrl) {
        try { URL.revokeObjectURL(selectedImagePreviewUrl); } catch (_) {}
        selectedImagePreviewUrl = null;
      }
      if (fileInput) fileInput.value = '';
      if (previewThumb) previewThumb.src = '';
      if (previewFilename) previewFilename.textContent = '';
      if (previewFilesize) previewFilesize.textContent = '';
      if (uploadPreview) uploadPreview.style.display = 'none';
    }

    async function uploadChatImageFile(file) {
      const formData = new FormData();
      formData.append('image', file);
      if (selectedSessionId) {
        formData.append('sessionId', selectedSessionId);
      }

      const uploadHeaders = {
        Accept: 'application/json',
      };
      const currentToken = activeToken || token || (typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('fastfood_auth_token') : '');
      if (currentToken) {
        uploadHeaders['Authorization'] = `Bearer ${currentToken}`;
      }

      let response = await fetch(`${resolvedApiUrl}/realtime/chat/upload-image`, {
        method: 'POST',
        headers: uploadHeaders,
        body: formData,
      });

      if (!response.ok && response.status === 404) {
        response = await fetch(`${resolvedApiUrl}/chat/upload-image`, {
          method: 'POST',
          headers: uploadHeaders,
          body: formData,
        });
      }

      if (!response.ok && response.status === 404) {
        response = await fetch('/api/realtime.php?action=upload_chat_image', {
          method: 'POST',
          headers: uploadHeaders,
          body: formData,
        });
      }

      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Không thể tải ảnh lên máy chủ.');
      }

      const data = result.data || {};
      const imgUrl = data.url || data.imageUrl;
      if (!imgUrl) {
        throw new Error('Máy chủ không trả về đường dẫn ảnh.');
      }

      return {
        imageUrl: imgUrl,
        fileName: data.fileName || file.name,
        fileSize: data.fileSize || file.size,
        mimeType: data.mimeType || file.type,
      };
    }

    let pickerProducts = [];
    let pickerStatusFilter = 'all';
    let pickerKeyword = '';
    let pickerCategoryId = 'all';

    let conversations = [];
    let filteredConversations = [];
    let selectedSessionId = '';
    let active = false;
    let historyRequestId = 0;
    let lastAdminChatDateStr = '';
    let supportStatus = { mode: 'auto', adminOnline: true, effectiveBusy: true };
    let pollTimer = null;

    // Kênh đồng bộ thời gian thực liên tab (< 30ms)
    let realtimeChannel = null;
    if (typeof document !== 'undefined' && !document.getElementById('conv-unread-badge-style')) {
      const style = document.createElement('style');
      style.id = 'conv-unread-badge-style';
      style.textContent = `
        .conv-unread-badge {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          background: linear-gradient(135deg, #ef4444, #dc2626);
          color: #fff;
          font-size: 0.72rem;
          font-weight: 700;
          min-width: 18px;
          height: 18px;
          line-height: 1;
          padding: 0 6px;
          border-radius: 999px;
          margin-left: 6px;
          box-shadow: 0 2px 6px rgba(239, 68, 68, 0.45);
        }
      `;
      document.head?.appendChild(style);
    }
    try {
      if (typeof BroadcastChannel !== 'undefined') {
        realtimeChannel = new BroadcastChannel('fastfood_chat_realtime');
        realtimeChannel.onmessage = (event) => {
          handleIncomingSync(event.data);
        };
      }
    } catch (_) {}

    window.addEventListener('storage', (e) => {
      if (e.key === 'fastfood_chat_sync' && e.newValue) {
        try {
          const data = JSON.parse(e.newValue);
          handleIncomingSync(data);
        } catch (_) {}
      }
    });

    async function handleIncomingSync(data) {
      if (!data) return;

      if (data.type === 'CUSTOMER_SENT') {
        const isViewingSession = Boolean(active && selectedSessionId && String(data.sessionId) === String(selectedSessionId));

        if (isViewingSession) {
          if (data.text || data.imageUrl || data.metadata) {
            renderMessage({
              id: 'cmsg_' + (data.time || Date.now()),
              sessionId: data.sessionId,
              sender: 'user',
              senderRole: 'user',
              customerName: data.customerName || 'Khách hàng',
              message: data.text || (data.messageType === 'image' ? '[Hình ảnh]' : ''),
              messageType: data.messageType || (data.imageUrl ? 'image' : 'text'),
              metadata: data.metadata || null,
              imageUrl: data.imageUrl || null,
              time: new Date(data.time || Date.now()).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
              createdAt: new Date(data.time || Date.now()).toISOString()
            });
          }
          // Immediately mark read via POST /api/v1/chat/admin/sessions/${sessionId}/read
          chatRequest(`/admin/sessions/${encodeURIComponent(data.sessionId)}/read`, { method: 'POST' })
            .then((resData) => {
              const totalUnread = resData && typeof resData.totalUnread === 'number'
                ? resData.totalUnread
                : (resData && resData.data && typeof resData.data.totalUnread === 'number' ? resData.data.totalUnread : undefined);
              if (typeof totalUnread === 'number' && typeof window.updateMessagesNavBadge === 'function') {
                window.updateMessagesNavBadge(totalUnread);
              }
            })
            .catch(() => {});
          // Do NOT increment badge
        } else {
          // If admin is on another tab/section or viewing another session:
          if (typeof window.updateMessagesNavBadge === 'function') {
            const currentBadgeCount = typeof window.getMessagesNavBadgeCount === 'function' ? window.getMessagesNavBadgeCount() : 0;
            window.updateMessagesNavBadge(currentBadgeCount + 1);
          }
          const conv = conversations.find(c => String(c.sessionId) === String(data.sessionId));
          if (conv) {
            conv.unreadCount = (parseInt(conv.unreadCount || 0, 10)) + 1;
            renderConversationList();
          }
        }
        refreshConversations(true);
      }

      if (data.type === 'SESSION_READ') {
        const readSessionId = String(data.sessionId);
        const conv = conversations.find(c => String(c.sessionId) === readSessionId);
        if (conv) {
          conv.unreadCount = 0;
          renderConversationList();
        }
        const totalUnread = data && typeof data.totalUnread === 'number'
          ? data.totalUnread
          : (data && data.data && typeof data.data.totalUnread === 'number' ? data.data.totalUnread : undefined);
        if (typeof totalUnread === 'number' && typeof window.updateMessagesNavBadge === 'function') {
          window.updateMessagesNavBadge(totalUnread);
        }
      }
    }

    async function chatRequest(path, options = {}) {
      const cleanPath = path.startsWith('/') ? path : `/${path}`;
      const authToken = activeToken || token || (typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('fastfood_auth_token') : '');
      const reqHeaders = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
        ...(options.headers || {}),
      };

      let response;
      try {
        response = await fetch(`${resolvedApiUrl}/chat${cleanPath}`, {
          ...options,
          headers: reqHeaders,
        });
      } catch (networkErr) {
        // Fallback sang backend/index.php nếu rewrite bị chặn
        const fallbackUrl = (window.BASE_URL || '').replace(/\/+$/, '') + `/backend/index.php/api/v1/chat${cleanPath}`;
        response = await fetch(fallbackUrl, {
          ...options,
          headers: reqHeaders,
        });
      }

      if (!response.ok && response.status === 404) {
        const fallbackUrl = (window.BASE_URL || '').replace(/\/+$/, '') + `/backend/index.php/api/v1/chat${cleanPath}`;
        try {
          const fbRes = await fetch(fallbackUrl, {
            ...options,
            headers: reqHeaders,
          });
          if (fbRes.ok) {
            response = fbRes;
          }
        } catch (_) {}
      }

      let result = null;
      try {
        result = await response.json();
      } catch (parseErr) {
        // Fallback sang admin/api.php nếu request là conversations
        if (cleanPath.includes('/admin/conversations')) {
          try {
            const apiRes = await fetch('api.php?action=admin_conversations');
            result = await apiRes.json();
          } catch (_) {}
        }
      }

      if (!result || !result.success) {
        throw new Error(result?.message || 'Không thể xử lý yêu cầu.');
      }
      return result.data;
    }

    async function realtimeRequest(path, options = {}) {
      const cleanPath = path.startsWith('/') ? path : `/${path}`;
      const authToken = activeToken || token || (typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('fastfood_auth_token') : '');
      const response = await fetch(`${resolvedApiUrl}/realtime${cleanPath}`, {
        ...options,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
          ...(options.headers || {}),
        },
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Không thể xử lý yêu cầu.');
      }
      return result.data;
    }

    function showFeedback(message, isError = false) {
      if (!feedback) return;
      feedback.textContent = message;
      feedback.classList.toggle('error', isError);
      if (message && !isError) {
        setTimeout(() => {
          if (feedback.textContent === message) feedback.textContent = '';
        }, 5000);
      }
    }

    function isAiPaused(conversation) {
      const pauseTime = conversation?.aiPausedUntil ? new Date(conversation.aiPausedUntil) : null;
      return Boolean(pauseTime && pauseTime.getTime() > Date.now());
    }

    /**
     * Cập nhật giao diện chuyển chế độ Chat toàn cục (AI Tự Chat vs Admin Trực Tiếp)
     */
    function updateChatModeUI(status) {
      if (status) {
        supportStatus = { ...supportStatus, ...status };
      }

      const mode = supportStatus.mode || (supportStatus.effectiveBusy ? 'auto' : 'admin');
      const isAuto = mode === 'auto';

      if (btnModeAi) {
        btnModeAi.classList.toggle('active', isAuto);
      }
      if (btnModeAdmin) {
        btnModeAdmin.classList.toggle('active', !isAuto);
      }
      if (modeBadge) {
        modeBadge.textContent = isAuto ? '🤖 AI Tự Chat (24/7)' : '👨‍💼 Admin Trực Tiếp Chat';
        modeBadge.className = `chat-mode-status-tag ${isAuto ? 'mode-auto' : 'mode-admin'}`;
      }
      if (modeDesc) {
        modeDesc.innerHTML = isAuto
          ? '✨ <strong>AI Tự Chat:</strong> Trợ lý AI đang tự động trả lời tin nhắn của khách hàng 24/7. Bạn có thể theo dõi và can thiệp khi cần.'
          : '⚡ <strong>Admin Trực Tiếp:</strong> AI đã tắt tự động. Bạn đọc và phản hồi trực tiếp cho khách hàng.';
      }

      // Hỗ trợ nút cũ (nếu có)
      if (btnAdminBusyToggle) {
        btnAdminBusyToggle.textContent = isAuto ? '🤖 AI Tự Chat (24/7)' : '🟢 Admin Trực Tiếp (Tự trả lời)';
        btnAdminBusyToggle.classList.toggle('secondary', !isAuto);
      }
    }

    /**
     * Cập nhật trạng thái AI cho riêng cuộc trò chuyện đang chọn
     */
    function updateAutomationUI(conversation) {
      const paused = isAiPaused(conversation);
      const forced = Boolean(conversation?.aiForceAuto);
      const isGlobalAuto = supportStatus.mode === 'auto' || supportStatus.effectiveBusy;

      const aiCurrentlyActive = !paused && (forced || isGlobalAuto);

      if (btnToggleAuto) {
        if (aiCurrentlyActive) {
          btnToggleAuto.innerHTML = '👨‍💼 Tắt AI (Admin tiếp quản)';
          btnToggleAuto.classList.add('secondary');
          btnToggleAuto.title = 'Tạm dừng AI cho khách này trong 30 phút để bạn trực tiếp trao đổi.';
        } else {
          btnToggleAuto.innerHTML = '🤖 Bật AI cho khách này';
          btnToggleAuto.classList.remove('secondary');
          btnToggleAuto.title = 'Bật Trợ lý AI tự động trả lời riêng cho khách hàng này.';
        }
      }

      if (assistantStatus) {
        if (paused) {
          const pauseTime = new Date(conversation.aiPausedUntil);
          assistantStatus.innerHTML = `👨‍💼 <strong>Admin đang tiếp quản</strong> (AI tạm dừng đến ${pauseTime.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })})`;
          assistantStatus.className = 'chat-assistant-status-pill paused';
        } else if (forced) {
          assistantStatus.innerHTML = '🤖 <strong>AI tự động riêng</strong> đang phục vụ khách này';
          assistantStatus.className = 'chat-assistant-status-pill auto';
        } else if (isGlobalAuto) {
          assistantStatus.innerHTML = '🤖 <strong>AI tự chat 24/7</strong> đang trả lời khách';
          assistantStatus.className = 'chat-assistant-status-pill auto';
        } else {
          assistantStatus.innerHTML = '👨‍💼 <strong>Admin đang trực</strong>: Bạn trả lời trực tiếp cho khách';
          assistantStatus.className = 'chat-assistant-status-pill direct';
        }
      }
    }

    function renderMessage(message) {
      if (!message) return;
      if (message.id && messageList.querySelector(`[data-message-id="${message.id}"]`)) return;

      const role = message.sender || message.senderRole || 'user';
      const isAdminMsg = role === 'admin';
      const isAiMsg = role === 'assistant' || role === 'ai';

      // Chống trùng lặp tin nhắn giữa Realtime Sync (cmsg_*) và Lịch sử Database
      const cleanMsg = (message.message || '').trim();
      const existingBubbles = messageList.querySelectorAll('.admin-chat-message');
      for (let i = existingBubbles.length - 1; i >= 0; i--) {
        const b = existingBubbles[i];
        const contentEl = b.querySelector('.admin-msg-content');
        if (contentEl && contentEl.textContent.trim() === cleanMsg) {
          const curId = b.dataset.messageId || '';
          if (!curId || curId.startsWith('cmsg_') || curId.startsWith('tmp_')) {
            if (message.id) b.dataset.messageId = String(message.id);
            return;
          }
        }
      }

      // Chèn vạch phân cách ngày (Date Separator) nếu sang ngày mới
      let rawDateStr = message.createdAt || '';
      let msgDateObj = null;
      if (rawDateStr) {
        msgDateObj = new Date(rawDateStr.replace(' ', 'T'));
      } else {
        msgDateObj = new Date();
      }

      if (!isNaN(msgDateObj.getTime())) {
        const dateKey = msgDateObj.toLocaleDateString('vi-VN');
        if (dateKey !== lastAdminChatDateStr) {
          lastAdminChatDateStr = dateKey;
          const todayKey = new Date().toLocaleDateString('vi-VN');
          const yesterdayObj = new Date(Date.now() - 86400000);
          const yesterdayKey = yesterdayObj.toLocaleDateString('vi-VN');

          let dateLabel = dateKey;
          if (dateKey === todayKey) {
            dateLabel = `Hôm nay, ${dateKey}`;
          } else if (dateKey === yesterdayKey) {
            dateLabel = `Hôm qua, ${dateKey}`;
          } else {
            const dayName = msgDateObj.toLocaleDateString('vi-VN', { weekday: 'long' });
            dateLabel = `${dayName.charAt(0).toUpperCase() + dayName.slice(1)}, ${dateKey}`;
          }

          const dateDiv = document.createElement('div');
          dateDiv.className = 'chat-date-separator';
          dateDiv.innerHTML = `<span>📅 ${dateLabel}</span>`;
          messageList.appendChild(dateDiv);
        }
      }

      const bubble = document.createElement('article');
      bubble.className = `admin-chat-message${isAdminMsg ? ' outgoing' : ''}${isAiMsg ? ' assistant' : ''}`;
      const senderRoleKey = isAdminMsg ? 'admin' : (isAiMsg ? 'assistant' : 'user');
      bubble.dataset.senderRole = senderRoleKey;
      if (message.id) bubble.dataset.messageId = String(message.id);

      // Nhận diện tin nhắn liên tiếp cùng người gửi để căn chỉnh khoảng cách gọn gàng (4px)
      const prevBubble = messageList.querySelector('.admin-chat-message:last-of-type');
      if (prevBubble && prevBubble.dataset.senderRole === senderRoleKey) {
        bubble.classList.add('same-sender-consecutive');
      }

      const header = document.createElement('div');
      header.className = 'admin-msg-header';

      // Xác định tên người gửi chính xác theo phiên hoặc tin nhắn
      const conv = conversations.find(c => String(c.sessionId) === String(selectedSessionId));
      const customerDisplayName = message.customerName || conv?.customerName || 'Khách hàng';

      const sender = document.createElement('strong');
      sender.className = `admin-msg-sender-badge ${isAdminMsg ? 'badge-admin' : (isAiMsg ? 'badge-ai' : 'badge-customer')}`;
      sender.textContent = isAdminMsg
        ? '👨‍💼 Bạn (Admin)'
        : isAiMsg
          ? '🤖 Trợ lý AI FASTFOOD'
          : `👤 ${customerDisplayName}`;

      const timeSpan = document.createElement('span');
      timeSpan.className = 'admin-msg-time';

      // Chuẩn hóa giờ hiển thị chính xác theo giờ địa phương Việt Nam (HH:mm)
      let timeText = '';
      if (rawDateStr) {
        const d = new Date(rawDateStr.replace(' ', 'T'));
        if (!isNaN(d.getTime())) {
          timeText = d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
        }
      }
      if (!timeText && message.time && /^\d{1,2}:\d{2}$/.test(message.time)) {
        timeText = message.time;
      }
      if (!timeText) {
        timeText = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
      }

      timeSpan.textContent = timeText;

      header.append(sender, timeSpan);

      const content = document.createElement('div');
      content.className = 'admin-msg-text';

      const isImage = message.messageType === 'image' ||
                      message.metadata?.type === 'image' ||
                      Boolean(message.metadata?.imageUrl) ||
                      Boolean(message.imageUrl) ||
                      (typeof message.message === 'string' && /\.(jpg|jpeg|png|webp)(\?.*)?$/i.test(message.message.trim()));
      const imgUrl = message.metadata?.imageUrl || message.imageUrl || (isImage && !message.metadata ? message.message.trim() : '');

      const isProductCard = message.messageType === 'product_card' ||
                            message.metadata?.type === 'product_card' ||
                            Boolean(message.metadata?.product) ||
                            Boolean(message.metadata?.products?.[0]);
      const productObj = message.metadata?.product || message.metadata?.products?.[0];

      // 1. Render hình ảnh nếu có
      if (isImage && imgUrl) {
        const imgWrap = document.createElement('div');
        imgWrap.className = 'admin-msg-image-bubble';
        imgWrap.dataset.imgUrl = imgUrl;
        imgWrap.title = 'Nhấp để xem ảnh phóng to';
        imgWrap.innerHTML = `
          <img src="${escapeHtml(imgUrl)}" alt="Hình ảnh chat" class="admin-chat-image-thumb" loading="lazy" onerror="this.style.display='none';" />
        `;
        imgWrap.addEventListener('click', (e) => {
          e.stopPropagation();
          openAdminLightbox(imgUrl);
        });
        content.appendChild(imgWrap);
      }

      // 2. Render thẻ món ăn nếu có
      if (isProductCard && productObj) {
        const cardEl = document.createElement('div');
        cardEl.className = 'admin-msg-product-card';
        const priceFmt = new Intl.NumberFormat('vi-VN').format(productObj.price || 0) + 'đ';
        const oldPrice = Number(productObj.originalPrice || productObj.basePrice || 0);
        const curPrice = Number(productObj.price || 0);
        const oldPriceFmt = (oldPrice > curPrice) ? new Intl.NumberFormat('vi-VN').format(oldPrice) + 'đ' : '';

        cardEl.innerHTML = `
          <img src="${escapeHtml(productObj.image || '/assets/images/hero-burger.svg')}" alt="${escapeHtml(productObj.name)}" class="admin-product-card-thumb" onerror="this.src='/assets/images/hero-burger.svg'" />
          <div class="admin-product-card-info">
            <div class="admin-product-card-tag">🍔 Thẻ Món Ăn FASTFOOD</div>
            <h4 class="admin-product-card-title">${escapeHtml(productObj.name)}</h4>
            <div class="admin-product-card-price-row">
              <span class="admin-product-card-price">${priceFmt}</span>
              ${oldPriceFmt ? `<span class="admin-product-card-old-price">${oldPriceFmt}</span>` : ''}
            </div>
            <span class="admin-product-card-preview-note">✓ Đã gửi thẻ kèm nút đặt món cho khách</span>
          </div>
        `;
        content.appendChild(cardEl);
      }

      // 3. Văn bản tin nhắn
      const textToShow = (isImage && (!message.message || message.message === '[Hình ảnh]'))
        ? ''
        : (message.message || '');
      if (textToShow) {
        const textDiv = document.createElement('div');
        textDiv.className = 'admin-msg-body-text';
        textDiv.innerHTML = formatChatText(textToShow);
        content.appendChild(textDiv);
      }

      bubble.append(header, content);
      messageList.appendChild(bubble);
      messageList.scrollTop = messageList.scrollHeight;
    }

    function formatChatText(text) {
      if (!text) return '';
      let safe = String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
      safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
      return safe.replace(/\n/g, '<br>');
    }

    function getAvatarForName(name) {
      const clean = String(name || '').trim();
      if (!clean) return '👤';
      const firstChar = clean.charAt(0).toUpperCase();
      return firstChar;
    }

    function renderConversationList() {
      conversationList.replaceChildren();

      const query = (searchInput?.value || '').trim().toLowerCase();
      filteredConversations = conversations.filter(c => {
        if (!query) return true;
        const name = (c.customerName || '').toLowerCase();
        const email = (c.email || '').toLowerCase();
        const phone = (c.phone || '').toLowerCase();
        const lastMsg = (c.lastMessage || '').toLowerCase();
        return name.includes(query) || email.includes(query) || phone.includes(query) || lastMsg.includes(query);
      });

      if (!filteredConversations.length) {
        const empty = document.createElement('div');
        empty.className = 'chat-empty-state';
        empty.innerHTML = `
          <span class="empty-icon">📭</span>
          <p>${query ? 'Không tìm thấy cuộc trò chuyện phù hợp.' : 'Chưa có tin nhắn nào từ khách hàng.'}</p>
        `;
        conversationList.appendChild(empty);
        return;
      }

      filteredConversations.forEach((conversation) => {
        const sessionId = String(conversation.sessionId);
        const isSelected = sessionId === selectedSessionId;

        const item = document.createElement('button');
        item.type = 'button';
        item.className = `chat-conversation-item${isSelected ? ' active' : ''}`;
        item.dataset.sessionId = sessionId;

        const avatarWrap = document.createElement('div');
        avatarWrap.className = 'conv-avatar';
        avatarWrap.textContent = getAvatarForName(conversation.customerName);

        const infoWrap = document.createElement('div');
        infoWrap.className = 'conv-info';

        const topRow = document.createElement('div');
        topRow.className = 'conv-top-row';

        const name = document.createElement('strong');
        name.className = 'conv-name';
        name.textContent = (conversation.customerName || 'Khách hàng') + ' ';

        const isAdminDirect = conversation.channel === 'admin' || sessionId.startsWith('admin_sess_');
        const channelBadge = document.createElement('span');
        channelBadge.className = `conv-channel-badge ${isAdminDirect ? 'admin-direct' : 'ai-query'}`;
        channelBadge.textContent = isAdminDirect ? '👨‍💼 Liên hệ cửa hàng' : '🤖 Hỏi AI';
        channelBadge.style.cssText = isAdminDirect
          ? 'font-size: 10px; font-weight: 750; padding: 2px 6px; border-radius: 6px; background: #dcfce7; color: #15803d; margin-left: 4px; display: inline-block;'
          : 'font-size: 10px; font-weight: 750; padding: 2px 6px; border-radius: 6px; background: #fff7ed; color: #c2410c; margin-left: 4px; display: inline-block;';
        name.appendChild(channelBadge);

        const time = document.createElement('span');
        time.className = 'conv-time';
        time.textContent = conversation.time || '';

        topRow.append(name, time);

        const convUnread = parseInt(conversation.unreadCount || 0, 10);
        if (convUnread > 0) {
          const unreadBadge = document.createElement('span');
          unreadBadge.className = 'conv-unread-badge';
          unreadBadge.textContent = convUnread > 99 ? '99+' : String(convUnread);
          topRow.appendChild(unreadBadge);
        }

        const bottomRow = document.createElement('div');
        bottomRow.className = 'conv-bottom-row';

        const preview = document.createElement('p');
        preview.className = 'conv-preview';
        const prefix = conversation.lastSender === 'user'
          ? '<span class="prefix-user">Khách: </span>'
          : conversation.lastSender === 'admin'
            ? '<span class="prefix-admin">Bạn: </span>'
            : '<span class="prefix-ai">🤖: </span>';
        preview.innerHTML = `${prefix}${escapeHtml(conversation.lastMessage || '...')}`;

        bottomRow.appendChild(preview);

        const metaRow = document.createElement('div');
        metaRow.className = 'conv-meta-row';
        const metaTag = document.createElement('span');
        metaTag.className = 'conv-tag';
        metaTag.textContent = conversation.email || (conversation.userId ? `ID #${conversation.userId}` : 'Khách vãng lai');
        metaRow.appendChild(metaTag);

        infoWrap.append(topRow, bottomRow, metaRow);
        item.append(avatarWrap, infoWrap);
        conversationList.appendChild(item);
      });
    }

    async function loadHistory(sessionId) {
      const requestId = ++historyRequestId;
      lastAdminChatDateStr = '';
      messageList.replaceChildren();
      if (intentTag) intentTag.style.display = 'none';

      const conversation = conversations.find((item) => String(item.sessionId) === sessionId);
      const prevUnreadCount = conversation && conversation.unreadCount ? parseInt(conversation.unreadCount, 10) : 0;

      // Clear conversation's local unread badge
      if (conversation) {
        conversation.unreadCount = 0;
      }
      renderConversationList();

      // Decrement sidebar badge by previous unread count of this conversation
      if (prevUnreadCount > 0 && typeof window.updateMessagesNavBadge === 'function') {
        const currentCount = typeof window.getMessagesNavBadgeCount === 'function' ? window.getMessagesNavBadgeCount() : 0;
        window.updateMessagesNavBadge(Math.max(0, currentCount - prevUnreadCount));
      }

      // Call POST /api/v1/chat/admin/sessions/${sessionId}/read
      chatRequest(`/admin/sessions/${encodeURIComponent(sessionId)}/read`, { method: 'POST' })
        .then((res) => {
          if (res && res.totalUnread !== undefined && !res.data) {
            res.data = { totalUnread: res.totalUnread, sessionId: res.sessionId, affectedMessages: res.affectedMessages };
          }
          if (typeof res.data?.totalUnread === 'number' && typeof window.updateMessagesNavBadge === 'function') {
            window.updateMessagesNavBadge(res.data.totalUnread);
          }
          try {
            const bc = new BroadcastChannel('fastfood_chat_realtime');
            bc.postMessage({ type: 'SESSION_READ', sessionId: sessionId, totalUnread: res.data.totalUnread });
          } catch (e) {}
          try {
            localStorage.setItem('fastfood_chat_sync', JSON.stringify({ type: 'SESSION_READ', sessionId: sessionId, totalUnread: res.data.totalUnread, timestamp: Date.now() }));
          } catch (e) {}
        })
        .catch(() => {});

      if (threadTitle) {
        threadTitle.textContent = conversation?.customerName || 'Khách hàng';
      }
      if (btnDeleteConv) {
        btnDeleteConv.style.display = 'inline-block';
      }
      if (customerAvatar) {
        customerAvatar.textContent = getAvatarForName(conversation?.customerName);
      }
      if (customerTypeBadge) {
        const isAdminDirect = conversation?.channel === 'admin' || sessionId.startsWith('admin_sess_');
        customerTypeBadge.textContent = isAdminDirect
          ? `👨‍💼 Chat trực tiếp • ${conversation?.userId ? `Thành viên #${conversation.userId}` : 'Khách vãng lai'}`
          : `🤖 Hỏi AI 24/7 • ${conversation?.userId ? `Thành viên #${conversation.userId}` : 'Khách vãng lai'}`;
      }
      if (customerMeta) {
        const contactArr = [];
        if (conversation?.email) contactArr.push(`✉️ ${conversation.email}`);
        if (conversation?.phone) contactArr.push(`📞 ${conversation.phone}`);
        customerMeta.textContent = contactArr.length ? contactArr.join(' • ') : 'Đang trực tuyến';
      }

      updateAutomationUI(conversation);

      try {
        const messages = await chatRequest(`/admin/sessions/${encodeURIComponent(sessionId)}/messages`);
        if (requestId !== historyRequestId) return;
        if (Array.isArray(messages) && messages.length > 0) {
          messages.forEach((message) => renderMessage(message));
        } else {
          const emptyThread = document.createElement('div');
          emptyThread.className = 'chat-thread-empty';
          emptyThread.innerHTML = `
            <span>👋</span>
            <p>Bắt đầu cuộc trò chuyện với <strong>${escapeHtml(conversation?.customerName || 'Khách hàng')}</strong></p>
          `;
          messageList.appendChild(emptyThread);
        }
      } catch (error) {
        if (requestId === historyRequestId) showFeedback(error.message, true);
      }
    }

    async function refreshConversations(silent = false) {
      try {
        const data = await chatRequest('/admin/conversations');
        conversations = Array.isArray(data?.conversations) ? data.conversations : [];

        if (typeof data?.totalUnread === 'number' && typeof window.updateMessagesNavBadge === 'function') {
          window.updateMessagesNavBadge(data.totalUnread);
        }

        if (data?.supportStatus) {
          updateChatModeUI(data.supportStatus);
        }

        renderConversationList();

        if (!selectedSessionId && conversations.length) {
          selectedSessionId = String(conversations[0].sessionId);
          renderConversationList();
          await loadHistory(selectedSessionId);
        } else if (selectedSessionId) {
          const current = conversations.find((item) => String(item.sessionId) === selectedSessionId);
          if (current) {
            updateAutomationUI(current);
          }
        }
        if (!silent) showFeedback('Đã đồng bộ danh sách tin nhắn mới.');
      } catch (error) {
        if (!silent) showFeedback(error.message, true);
      }
    }

    /**
     * Chuyển đổi chế độ Chat toàn cục (AI Tự Chat vs Admin Trực Tiếp Chat)
     */
    async function switchChatMode(targetMode) {
      if (supportStatus.mode === targetMode) return;
      showFeedback(`Đang chuyển sang chế độ ${targetMode === 'auto' ? 'AI Tự Chat' : 'Admin Trực Tiếp'}...`);

      try {
        const data = await chatRequest('/admin/status', {
          method: 'PATCH',
          body: JSON.stringify({ mode: targetMode }),
        });
        updateChatModeUI(data);

        const current = conversations.find((item) => String(item.sessionId) === selectedSessionId);
        if (current) updateAutomationUI(current);

        showFeedback(
          targetMode === 'auto'
            ? '🤖 ĐÃ BẬT AI TỰ ĐỘNG PHẢN HỒI: Trợ lý AI sẽ tự động phản hồi khách hàng 24/7.'
            : '👨‍💼 ĐÃ BẬT CHẾ ĐỘ ADMIN TRỰC TIẾP: AI đã tắt tự động. Bạn đọc tin nhắn và chat trực tiếp với khách hàng!'
        );
      } catch (err) {
        showFeedback(err.message || 'Không thể đổi chế độ chat.', true);
      }
    }

    // --- Mode Switch Button Listeners ---
    btnModeAi?.addEventListener('click', () => switchChatMode('auto'));
    btnModeAdmin?.addEventListener('click', () => switchChatMode('admin'));
    btnAdminBusyToggle?.addEventListener('click', () => {
      const nextMode = supportStatus.mode === 'auto' ? 'admin' : 'auto';
      switchChatMode(nextMode);
    });

    // --- Refresh Button ---
    btnRefresh?.addEventListener('click', () => {
      btnRefresh.classList.add('spinning');
      refreshConversations().finally(() => setTimeout(() => btnRefresh.classList.remove('spinning'), 500));
    });

    // --- Live Filter Search ---
    searchInput?.addEventListener('input', () => {
      renderConversationList();
    });

    // --- AI Suggest Reply (Copilot) ---
    btnSuggest?.addEventListener('click', async () => {
      if (!selectedSessionId) {
        showFeedback('Vui lòng chọn một cuộc trò chuyện từ danh sách trước.', true);
        return;
      }

      const conversation = conversations.find((item) => String(item.sessionId) === selectedSessionId);
      let lastCustomerMsg = '';
      const allBubbles = Array.from(messageList.querySelectorAll('.admin-chat-message'));
      for (let i = allBubbles.length - 1; i >= 0; i--) {
        const b = allBubbles[i];
        if (!b.classList.contains('outgoing') && !b.classList.contains('assistant')) {
          const p = b.querySelector('.admin-msg-text, p');
          if (p && p.textContent.trim()) {
            lastCustomerMsg = p.textContent.trim();
            break;
          }
        }
      }

      if (!lastCustomerMsg && conversation?.lastSender === 'user') {
        lastCustomerMsg = conversation.lastMessage;
      }

      if (!lastCustomerMsg) {
        showFeedback('Chưa tìm thấy tin nhắn nào từ khách để AI phân tích gợi ý.', true);
        return;
      }

      const originalBtnText = btnSuggest.innerHTML;
      btnSuggest.disabled = true;
      btnSuggest.innerHTML = '✨ Đang phân tích...';
      showFeedback('AI đang phân tích câu hỏi và tạo câu trả lời...');

      try {
        const data = await realtimeRequest('/chat/ai/suggest', {
          method: 'POST',
          body: JSON.stringify({
            customerMessage: lastCustomerMsg,
            customerName: conversation?.customerName || 'bạn',
          }),
        });

        const replyText = data?.suggestedReply || data?.suggestion;
        if (replyText) {
          input.value = replyText;
          input.focus();
          if (intentTag && (data.intentLabel || data.intent)) {
            intentTag.style.display = 'inline-block';
            intentTag.textContent = `🎯 ${data.intentLabel || data.intent}`;
          }
          showFeedback('✨ Đã điền câu trả lời gợi ý! Bạn có thể chỉnh sửa rồi bấm Gửi.');
        } else {
          showFeedback('Không thể tạo gợi ý, vui lòng thử lại.', true);
        }
      } catch (err) {
        showFeedback(err.message || 'Lỗi khi gọi gợi ý AI.', true);
      } finally {
        btnSuggest.disabled = false;
        btnSuggest.innerHTML = originalBtnText;
      }
    });

    // --- Quick Response Chips ---
    quickChips?.addEventListener('click', (event) => {
      const chip = event.target.closest('.ai-chip');
      if (!chip) return;
      const text = chip.dataset.text;
      if (text) {
        input.value = text;
        input.focus();
        showFeedback('Đã chọn câu trả lời mẫu.');
      }
    });

    // --- Toggle Per-Session Automation ---
    btnToggleAuto?.addEventListener('click', async () => {
      if (!selectedSessionId) {
        showFeedback('Vui lòng chọn khách hàng trước.', true);
        return;
      }

      const conversation = conversations.find((item) => String(item.sessionId) === selectedSessionId);
      const paused = isAiPaused(conversation);
      const isGlobalAuto = supportStatus.mode === 'auto' || supportStatus.effectiveBusy;
      const aiCurrentlyActive = !paused && (conversation?.aiForceAuto || isGlobalAuto);
      const willEnable = !aiCurrentlyActive;

      btnToggleAuto.disabled = true;
      try {
        await chatRequest(`/admin/sessions/${encodeURIComponent(selectedSessionId)}/automation`, {
          method: 'PATCH',
          body: JSON.stringify({
            enabled: willEnable,
            pauseMinutes: 30,
          }),
        });

        if (conversation) {
          if (willEnable) {
            conversation.aiPausedUntil = null;
            conversation.aiForceAuto = true;
          } else {
            conversation.aiPausedUntil = new Date(Date.now() + 30 * 60 * 1000).toISOString();
            conversation.aiForceAuto = false;
          }
          updateAutomationUI(conversation);
        }

        showFeedback(
          willEnable
            ? '🤖 Đã bật AI tự động trả lời riêng cho khách này.'
            : '👨‍💼 Đã tắt AI (Admin tiếp quản 30 phút).'
        );
      } catch (err) {
        showFeedback(err.message || 'Không thể thay đổi trạng thái AI.', true);
      } finally {
        btnToggleAuto.disabled = false;
      }
    });

    // --- Delete Selected Conversation ---
    btnDeleteConv?.addEventListener('click', async () => {
      if (!selectedSessionId) return;
      const conv = conversations.find((item) => String(item.sessionId) === selectedSessionId);
      const name = conv?.customerName || 'khách hàng này';
      if (!window.confirm(`Bạn có chắc chắn muốn xóa vĩnh viễn cuộc trò chuyện với ${name}?`)) {
        return;
      }
      try {
        btnDeleteConv.disabled = true;
        await chatRequest(`/admin/sessions/${encodeURIComponent(selectedSessionId)}`, {
          method: 'DELETE',
        });
        showFeedback('✅ Đã xóa cuộc trò chuyện thành công!');
        conversations = conversations.filter((c) => String(c.sessionId) !== String(selectedSessionId));
        selectedSessionId = '';
        messageList.replaceChildren();
        if (threadTitle) threadTitle.textContent = 'Chọn một khách hàng';
        if (customerMeta) customerMeta.textContent = 'Chọn một hội thoại ở danh sách bên trái để bắt đầu chat';
        if (btnDeleteConv) btnDeleteConv.style.display = 'none';
        renderConversationList();
      } catch (err) {
        showFeedback(err.message || 'Không thể xóa cuộc trò chuyện.', true);
      } finally {
        btnDeleteConv.disabled = false;
      }
    });

    // --- Clear All Chat History ---
    btnClearAll?.addEventListener('click', async () => {
      if (!conversations.length) {
        showFeedback('Danh sách cuộc trò chuyện đang trống.');
        return;
      }
      if (!window.confirm('CẢNH BÁO: Bạn có chắc chắn muốn xóa TOÀN BỘ lịch sử tin nhắn trong hệ thống?')) {
        return;
      }
      try {
        btnClearAll.disabled = true;
        await chatRequest('/admin/conversations/clear', {
          method: 'DELETE',
        });
        showFeedback('✅ Đã xóa sạch toàn bộ lịch sử tin nhắn!');
        conversations = [];
        selectedSessionId = '';
        messageList.replaceChildren();
        if (threadTitle) threadTitle.textContent = 'Chọn một khách hàng';
        if (customerMeta) customerMeta.textContent = 'Chọn một hội thoại ở danh sách bên trái để bắt đầu chat';
        if (btnDeleteConv) btnDeleteConv.style.display = 'none';
        renderConversationList();
      } catch (err) {
        showFeedback(err.message || 'Không thể xóa lịch sử.', true);
      } finally {
        btnClearAll.disabled = false;
      }
    });

    // --- Conversation Selection ---
    conversationList.addEventListener('click', (event) => {
      const button = event.target.closest('[data-session-id]');
      if (!button) return;
      selectedSessionId = button.dataset.sessionId;
      clearSelectedImage();
      renderConversationList();
      showFeedback('');
      loadHistory(selectedSessionId);
    });

    // --- Attachment Button & File Upload Listeners ---
    if (attachBtn && fileInput) {
      attachBtn.addEventListener('click', () => {
        fileInput.click();
      });
    }

    if (fileInput) {
      fileInput.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (!file) {
          clearSelectedImage();
          return;
        }

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
          showFeedback('Định dạng hình ảnh không hợp lệ. Chỉ chấp nhận JPG, PNG, WEBP.', true);
          clearSelectedImage();
          return;
        }

        const maxSizeBytes = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSizeBytes) {
          showFeedback('Dung lượng hình ảnh vượt quá 5MB. Vui lòng chọn ảnh nhỏ hơn.', true);
          clearSelectedImage();
          return;
        }

        selectedImageFile = file;
        if (selectedImagePreviewUrl) {
          try { URL.revokeObjectURL(selectedImagePreviewUrl); } catch (_) {}
        }
        selectedImagePreviewUrl = URL.createObjectURL(file);

        if (previewThumb) previewThumb.src = selectedImagePreviewUrl;
        if (previewFilename) previewFilename.textContent = file.name;
        if (previewFilesize) previewFilesize.textContent = formatFileSize(file.size);
        if (uploadPreview) uploadPreview.style.display = 'flex';
        showFeedback('');
      });
    }

    if (btnRemovePreview) {
      btnRemovePreview.addEventListener('click', () => {
        clearSelectedImage();
      });
    }

    // --- Send Message Form ---
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const rawMessage = input.value.trim();
      const hasImage = Boolean(selectedImageFile);

      if (!rawMessage && !hasImage) {
        showFeedback('Vui lòng nhập tin nhắn hoặc chọn hình ảnh để gửi.', true);
        return;
      }
      if (!selectedSessionId) {
        showFeedback('Vui lòng chọn một cuộc trò chuyện trước khi gửi.', true);
        return;
      }

      const sendButton = form.querySelector('button[type="submit"]') || document.getElementById('admin-chat-submit-btn');
      if (sendButton) sendButton.disabled = true;

      try {
        let replyPayload = {
          message: rawMessage,
        };

        if (hasImage) {
          showFeedback('Đang tải hình ảnh lên...');
          const uploaded = await uploadChatImageFile(selectedImageFile);
          replyPayload = {
            message: rawMessage || '[Hình ảnh]',
            messageType: 'image',
            metadata: {
              type: 'image',
              imageUrl: uploaded.imageUrl,
              fileName: uploaded.fileName,
              fileSize: uploaded.fileSize,
              mimeType: uploaded.mimeType,
            },
          };
        }

        const result = await chatRequest(`/admin/sessions/${encodeURIComponent(selectedSessionId)}/reply`, {
          method: 'POST',
          body: JSON.stringify(replyPayload),
        });

        input.value = '';
        clearSelectedImage();
        if (intentTag) intentTag.style.display = 'none';

        if (result?.message) {
          renderMessage(result.message);
        }
        const conv = conversations.find((item) => String(item.sessionId) === selectedSessionId);
        if (conv) {
          conv.aiPausedUntil = result?.aiPausedUntil || new Date(Date.now() + 15 * 60 * 1000).toISOString();
          conv.aiForceAuto = false;
          conv.lastMessage = replyPayload.message;
          conv.lastSender = 'admin';
          updateAutomationUI(conv);
          renderConversationList();
        }

        // Bắn tín hiệu tức thời liên tab cho widget Khách hàng (< 30ms)
        try {
          const sentMsg = result?.message || {
            id: 'admin_msg_' + Date.now(),
            sessionId: selectedSessionId,
            sender: 'admin',
            senderRole: 'admin',
            message: replyPayload.message,
            messageType: replyPayload.messageType || 'text',
            metadata: replyPayload.metadata || null,
            time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
            createdAt: new Date().toISOString()
          };
          if (realtimeChannel) {
            realtimeChannel.postMessage({
              type: 'ADMIN_REPLIED',
              sessionId: selectedSessionId,
              message: sentMsg,
              time: Date.now()
            });
          }
          localStorage.setItem('fastfood_chat_sync', JSON.stringify({
            type: 'ADMIN_REPLIED',
            sessionId: selectedSessionId,
            message: sentMsg,
            time: Date.now()
          }));
        } catch (_) {}

        showFeedback('✅ Đã gửi phản hồi trực tiếp cho khách hàng (AI tạm dừng 15 phút).');
      } catch (err) {
        showFeedback(err.message || 'Không thể gửi tin nhắn.', true);
      } finally {
        if (sendButton) sendButton.disabled = false;
        setTimeout(() => input.focus(), 100);
      }
    });

    // --- Realtime Socket Listeners ---
    socket?.on('support:new_message', (payload) => {
      if (!payload) return;
      const isViewing = Boolean(active && String(payload.sessionId) === selectedSessionId);
      if (isViewing && payload.message) {
        renderMessage(payload.message);
        chatRequest(`/admin/sessions/${encodeURIComponent(payload.sessionId)}/read`, { method: 'POST' })
          .then((resData) => {
            const totalUnread = resData && typeof resData.totalUnread === 'number'
              ? resData.totalUnread
              : (resData && resData.data && typeof resData.data.totalUnread === 'number' ? resData.data.totalUnread : undefined);
            if (typeof totalUnread === 'number' && typeof window.updateMessagesNavBadge === 'function') {
              window.updateMessagesNavBadge(totalUnread);
            }
          })
          .catch(() => {});
      } else {
        if (typeof window.updateMessagesNavBadge === 'function') {
          const currentBadge = typeof window.getMessagesNavBadgeCount === 'function' ? window.getMessagesNavBadgeCount() : 0;
          window.updateMessagesNavBadge(currentBadge + 1);
        }
        const conv = conversations.find(c => String(c.sessionId) === String(payload.sessionId));
        if (conv) {
          conv.unreadCount = (parseInt(conv.unreadCount || 0, 10)) + 1;
          renderConversationList();
        }
      }
      refreshConversations(true);
    });

    socket?.on('support:status', (status) => {
      updateChatModeUI(status);
      const current = conversations.find((item) => String(item.sessionId) === selectedSessionId);
      if (current) updateAutomationUI(current);
    });

    // =========================================================================
    // PRODUCT PICKER & LIGHTBOX LOGIC
    // =========================================================================

    function openProductPicker() {
      if (!pickerDrawer) return;
      pickerDrawer.removeAttribute('hidden');
      if (pickerProducts.length === 0) {
        loadPickerProducts();
      }
      setTimeout(() => pickerSearchInput?.focus(), 50);
    }

    function closeProductPicker() {
      if (!pickerDrawer) return;
      pickerDrawer.setAttribute('hidden', '');
    }

    function openAdminLightbox(url) {
      if (!lightboxModal || !lightboxImage || !url) return;
      lightboxImage.src = url;
      lightboxModal.removeAttribute('hidden');
    }

    function closeAdminLightbox() {
      if (!lightboxModal) return;
      lightboxModal.setAttribute('hidden', '');
      if (lightboxImage) lightboxImage.src = '';
    }

    async function loadPickerProducts() {
      if (!pickerProductList) return;
      pickerProductList.innerHTML = '<div style="text-align:center;padding:24px;color:#64748b;font-size:12px;">⏳ Đang tải kho món ăn...</div>';

      try {
        const queryParams = new URLSearchParams();
        if (pickerStatusFilter && pickerStatusFilter !== 'all') {
          queryParams.set('status', pickerStatusFilter);
        }
        if (pickerKeyword) {
          queryParams.set('keyword', pickerKeyword);
        }
        if (pickerCategoryId && pickerCategoryId !== 'all') {
          queryParams.set('category_id', pickerCategoryId);
        }
        queryParams.set('limit', '100');

        const data = await chatRequest(`/admin/products?${queryParams.toString()}`);
        pickerProducts = Array.isArray(data?.products) ? data.products : [];

        // Cập nhật số lượng trên các tab trạng thái
        if (Array.isArray(data?.statuses)) {
          data.statuses.forEach(st => {
            if (st.key === 'all' && pickerCntAll) pickerCntAll.textContent = st.count;
            if (st.key === 'in_stock' && pickerCntAvailable) pickerCntAvailable.textContent = st.count;
            if (st.key === 'out_of_stock' && pickerCntOut) pickerCntOut.textContent = st.count;
            if (st.key === 'unavailable_today' && pickerCntDisabled) pickerCntDisabled.textContent = st.count;
          });
        }

        // Cập nhật dropdown danh mục nếu chưa có
        if (pickerCatSelect && pickerCatSelect.options.length <= 1 && Array.isArray(data?.categories)) {
          data.categories.forEach(cat => {
            const opt = document.createElement('option');
            opt.value = cat.id;
            opt.textContent = cat.name;
            pickerCatSelect.appendChild(opt);
          });
        }

        renderPickerList();
      } catch (err) {
        pickerProductList.innerHTML = `<div style="text-align:center;padding:20px;color:#dc2626;font-size:12px;">⚠️ ${escapeHtml(err.message || 'Không thể tải danh sách món ăn.')}</div>`;
      }
    }

    function renderPickerList() {
      if (!pickerProductList) return;
      pickerProductList.innerHTML = '';

      if (pickerProducts.length === 0) {
        pickerProductList.innerHTML = `
          <div style="text-align:center;padding:36px 16px;color:#94a3b8;font-size:12px;">
            <span style="font-size:32px;display:block;margin-bottom:8px;">🔍</span>
            <p style="margin:0;font-weight:600;">Không tìm thấy món ăn nào phù hợp</p>
            <p style="margin:4px 0 0;font-size:11px;">Thử đổi từ khóa hoặc bộ lọc trạng thái</p>
          </div>
        `;
        if (pickerStatusSummary) pickerStatusSummary.textContent = 'Hiển thị 0 món ăn';
        return;
      }

      if (pickerStatusSummary) {
        pickerStatusSummary.textContent = `Hiển thị ${pickerProducts.length} món ăn`;
      }

      pickerProducts.forEach(p => {
        const card = document.createElement('article');
        card.className = 'picker-product-card';
        card.dataset.productId = p.id;
        card.dataset.status = p.status;

        const priceFmt = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
        const oldPriceFmt = p.originalPrice > p.price ? new Intl.NumberFormat('vi-VN').format(p.originalPrice) + 'đ' : '';
        const imgSrc = p.image || '/assets/images/hero-burger.svg';

        const statusTagClass = p.status === 'out_of_stock' ? 'out_of_stock' : (p.status === 'unavailable_today' ? 'unavailable_today' : 'in_stock');
        const statusTagText = p.statusText || (p.status === 'in_stock' ? 'Còn hàng' : (p.status === 'out_of_stock' ? 'Tạm hết' : 'Hôm nay không bán'));

        card.innerHTML = `
          <div class="picker-card-thumb-wrap">
            <img src="${escapeHtml(imgSrc)}" alt="${escapeHtml(p.name)}" class="picker-card-thumb" loading="lazy" onerror="this.src='/assets/images/hero-burger.svg'" />
          </div>
          <div class="picker-card-body">
            <div class="picker-card-title-row">
              <h4 class="picker-card-name" title="${escapeHtml(p.name)}">${escapeHtml(p.name)}</h4>
              <span class="picker-card-category">${escapeHtml(p.categoryName || '')}</span>
            </div>
            <div class="picker-card-price-row">
              <span class="picker-price-current">${priceFmt}</span>
              ${oldPriceFmt ? `<span class="picker-price-old">${oldPriceFmt}</span>` : ''}
            </div>
            ${p.description ? `<p class="picker-card-desc">${escapeHtml(p.description)}</p>` : ''}
            <div class="picker-card-footer">
              <span class="picker-status-tag ${statusTagClass}">${escapeHtml(statusTagText)}</span>
              <button type="button" class="btn-picker-send-chat" data-product-id="${p.id}" title="1-Click gửi thẻ món ăn này vào cuộc trò chuyện">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                <span>Gửi vào chat</span>
              </button>
            </div>
          </div>
        `;

        const sendBtn = card.querySelector('.btn-picker-send-chat');
        sendBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          handleSendProductCard(p.id, sendBtn);
        });

        pickerProductList.appendChild(card);
      });
    }

    async function handleSendProductCard(productId, btnElement) {
      if (!selectedSessionId) {
        showFeedback('Vui lòng chọn một cuộc trò chuyện từ danh sách bên trái trước khi gửi thẻ món!', true);
        const convListEl = document.getElementById('chat-conversation-list');
        if (convListEl) {
          convListEl.style.outline = '2px solid #f97316';
          setTimeout(() => { convListEl.style.outline = ''; }, 1200);
        }
        return;
      }

      const prod = pickerProducts.find(p => Number(p.id) === Number(productId));
      if (!prod) return;

      const originalBtnHtml = btnElement ? btnElement.innerHTML : '';
      if (btnElement) {
        btnElement.disabled = true;
        btnElement.innerHTML = '<span>Đang gửi...</span>';
      }

      try {
        const result = await chatRequest(`/admin/sessions/${encodeURIComponent(selectedSessionId)}/reply`, {
          method: 'POST',
          body: JSON.stringify({
            messageType: 'product_card',
            productId: Number(productId),
            message: `FASTFOOD xin gửi bạn thông tin món ngon: ${prod.name} - Giá: ${new Intl.NumberFormat('vi-VN').format(prod.price)}đ`
          })
        });

        if (result?.message) {
          renderMessage(result.message);
        }

        const conv = conversations.find((item) => String(item.sessionId) === selectedSessionId);
        if (conv) {
          conv.aiPausedUntil = result?.aiPausedUntil || new Date(Date.now() + 15 * 60 * 1000).toISOString();
          conv.aiForceAuto = false;
          conv.lastMessage = `[Thẻ món ăn] ${prod.name}`;
          conv.lastSender = 'admin';
          updateAutomationUI(conv);
          renderConversationList();
        }

        // Bắn tín hiệu tức thời liên tab cho widget Khách hàng (< 30ms)
        try {
          const sentMsg = result?.message || {
            id: 'admin_msg_' + Date.now(),
            sessionId: selectedSessionId,
            sender: 'admin',
            senderRole: 'admin',
            message: `FASTFOOD xin gửi bạn thông tin món ngon: ${prod.name} - Giá: ${new Intl.NumberFormat('vi-VN').format(prod.price)}đ`,
            messageType: 'product_card',
            metadata: {
              type: 'product_card',
              product: prod,
              products: [prod]
            },
            time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
            createdAt: new Date().toISOString()
          };
          if (realtimeChannel) {
            realtimeChannel.postMessage({
              type: 'ADMIN_REPLIED',
              sessionId: selectedSessionId,
              message: sentMsg,
              time: Date.now()
            });
          }
          localStorage.setItem('fastfood_chat_sync', JSON.stringify({
            type: 'ADMIN_REPLIED',
            sessionId: selectedSessionId,
            message: sentMsg,
            time: Date.now()
          }));
        } catch (_) {}

        showFeedback(`✅ Đã gửi thẻ món ăn [${prod.name}] vào cuộc trò chuyện!`);
      } catch (err) {
        showFeedback(err.message || 'Không thể gửi thẻ món ăn.', true);
      } finally {
        if (btnElement) {
          btnElement.disabled = false;
          btnElement.innerHTML = originalBtnHtml;
        }
      }
    }

    // Gắn sự kiện cho Drawer Kho Món Ăn
    btnOpenPicker?.addEventListener('click', openProductPicker);
    btnAiOpenPicker?.addEventListener('click', openProductPicker);
    pickerBtnClose?.addEventListener('click', closeProductPicker);
    pickerBtnRefresh?.addEventListener('click', () => {
      pickerBtnRefresh.classList.add('spinning');
      loadPickerProducts().finally(() => setTimeout(() => pickerBtnRefresh.classList.remove('spinning'), 500));
    });

    let searchDebounce = null;
    pickerSearchInput?.addEventListener('input', (e) => {
      pickerKeyword = e.target.value.trim();
      if (pickerSearchClear) {
        pickerSearchClear.hidden = !pickerKeyword;
      }
      clearTimeout(searchDebounce);
      searchDebounce = setTimeout(() => {
        loadPickerProducts();
      }, 250);
    });

    pickerSearchClear?.addEventListener('click', () => {
      if (pickerSearchInput) pickerSearchInput.value = '';
      pickerKeyword = '';
      pickerSearchClear.hidden = true;
      loadPickerProducts();
      pickerSearchInput?.focus();
    });

    // Lọc theo tabs trạng thái
    const statusTabs = document.querySelectorAll('.picker-status-tabs .picker-tab');
    statusTabs.forEach(tab => {
      tab.addEventListener('click', () => {
        statusTabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        pickerStatusFilter = tab.dataset.status || 'all';
        loadPickerProducts();
      });
    });

    pickerCatSelect?.addEventListener('change', (e) => {
      pickerCategoryId = e.target.value;
      loadPickerProducts();
    });

    // Sự kiện đóng Lightbox
    lightboxClose?.addEventListener('click', closeAdminLightbox);
    lightboxBackdrop?.addEventListener('click', closeAdminLightbox);

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (lightboxModal && !lightboxModal.hasAttribute('hidden')) {
          closeAdminLightbox();
          return;
        }
        if (pickerDrawer && !pickerDrawer.hasAttribute('hidden')) {
          closeProductPicker();
        }
      }
    });

    function escapeHtml(str) {
      return String(str ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
      })[char]);
    }

    // Global background poll for unread messages count so sidebar badge is always accurate across all tabs
    const backgroundUnreadPoll = async () => {
      try {
        const data = await chatRequest('/admin/conversations');
        if (typeof data?.totalUnread === 'number' && typeof window.updateMessagesNavBadge === 'function') {
          window.updateMessagesNavBadge(data.totalUnread);
        }
      } catch (_) {}
    };
    backgroundUnreadPoll();
    setInterval(backgroundUnreadPoll, 5000);

    // Global helper for unread badge clearing
    window.clearMessagesBadge = function() {
      if (typeof window.updateMessagesNavBadge === 'function') {
        window.updateMessagesNavBadge(0);
      }
    };

    return {
      setActive(isActive) {
        if (active === isActive) return;
        active = isActive;
        if (pollTimer) {
          clearInterval(pollTimer);
          pollTimer = null;
        }
        if (active) {
          refreshConversations(true);
          pollTimer = setInterval(async () => {
            if (!active) return;
            if (selectedSessionId) {
              try {
                const msgs = await chatRequest(`/admin/sessions/${encodeURIComponent(selectedSessionId)}/messages`);
                if (Array.isArray(msgs)) msgs.forEach(renderMessage);
              } catch (_) {}
            }
            await refreshConversations(true);
          }, 4500);
        }
      },
      refresh: refreshConversations,
    };
  }

  window.initAdminChatInbox = initAdminChatInbox;
}());
