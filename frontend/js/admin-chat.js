(function () {
  function initAdminChatInbox({ socket, apiUrl, token }) {
    const inbox = document.getElementById('chat-inbox');
    if (!inbox) return null;

    const conversationList = document.getElementById('chat-conversation-list');
    const messageList = document.getElementById('admin-chat-messages');
    const threadTitle = document.getElementById('chat-thread-title');
    const assistantStatus = document.getElementById('chat-assistant-status');
    const feedback = document.getElementById('admin-chat-feedback');
    const form = document.getElementById('admin-chat-form');
    const input = form.elements.message;

    // AI Copilot Controls
    const btnSuggest = document.getElementById('btn-ai-suggest-reply');
    const btnToggleAuto = document.getElementById('btn-toggle-ai-automation');
    const intentTag = document.getElementById('ai-detected-intent');
    const quickChips = document.getElementById('ai-quick-chips');

    let conversations = [];
    let selectedCustomerId = '';
    let active = false;
    let historyRequestId = 0;

    async function request(path, options = {}) {
      const response = await fetch(`${apiUrl}/realtime${path}`, {
        ...options,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
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
      feedback.textContent = message;
      feedback.classList.toggle('error', isError);
    }

    function renderMessage(message) {
      if (message.id && messageList.querySelector(`[data-message-id="${message.id}"]`)) return;
      const bubble = document.createElement('article');
      bubble.className = `admin-chat-message${message.senderRole === 'admin' ? ' outgoing' : ''}${message.senderRole === 'ai' ? ' assistant' : ''}`;
      if (message.id) bubble.dataset.messageId = String(message.id);

      const sender = document.createElement('strong');
      sender.textContent = message.senderRole === 'admin'
        ? 'Bạn (Admin)'
        : message.senderRole === 'ai' ? 'Trợ lý AI FASTFOOD' : 'Khách hàng';
      const content = document.createElement('p');
      content.textContent = message.message;
      bubble.append(sender, content);
      messageList.appendChild(bubble);
      messageList.scrollTop = messageList.scrollHeight;
    }

    function isAiPaused(conversation) {
      const pauseTime = conversation?.aiPausedUntil ? new Date(conversation.aiPausedUntil) : null;
      return Boolean(pauseTime && pauseTime.getTime() > Date.now());
    }

    function updateAutomationUI(conversation) {
      const paused = isAiPaused(conversation);
      if (btnToggleAuto) {
        if (paused) {
          btnToggleAuto.textContent = '🤖 Bật lại AI tự động';
          btnToggleAuto.classList.remove('secondary');
          btnToggleAuto.title = 'Hiện đang tạm dừng bot. Bấm để bật lại AI tự trả lời.';
        } else {
          btnToggleAuto.textContent = '⏸️ Tạm dừng AI (30p)';
          btnToggleAuto.classList.add('secondary');
          btnToggleAuto.title = 'Bấm để tạm dừng bot AI trả lời tự động cho khách này trong 30 phút.';
        }
      }

      if (paused) {
        const pauseTime = new Date(conversation.aiPausedUntil);
        assistantStatus.textContent = `Trợ lý AI đang tạm dừng đến ${pauseTime.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}.`;
      } else {
        assistantStatus.textContent = 'Trợ lý AI sẽ tự trả lời tin nhắn mới của khách.';
      }
    }

    function renderConversationList() {
      conversationList.replaceChildren();
      if (!conversations.length) {
        const empty = document.createElement('p');
        empty.className = 'chat-empty';
        empty.textContent = 'Chưa có tin nhắn khách hàng.';
        conversationList.appendChild(empty);
        return;
      }

      conversations.forEach((conversation) => {
        const customerId = String(conversation.customerId);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `chat-conversation${customerId === selectedCustomerId ? ' active' : ''}`;
        button.dataset.customerId = customerId;
        const name = document.createElement('strong');
        name.textContent = conversation.customerName || `Khách hàng #${customerId}`;
        const preview = document.createElement('span');
        preview.textContent = `${conversation.lastSenderRole === 'customer' ? 'Khách: ' : ''}${conversation.lastMessage || '...'}`;
        const email = document.createElement('small');
        email.textContent = conversation.email || `Mã khách hàng: ${customerId}`;
        button.append(name, preview, email);
        conversationList.appendChild(button);
      });
    }

    async function loadHistory(customerId) {
      const requestId = ++historyRequestId;
      messageList.replaceChildren();
      if (intentTag) intentTag.style.display = 'none';

      const conversation = conversations.find((item) => String(item.customerId) === customerId);
      threadTitle.textContent = conversation?.customerName || `Khách hàng #${customerId}`;
      updateAutomationUI(conversation);

      try {
        const messages = await request(`/chat/${encodeURIComponent(customerId)}`);
        if (requestId !== historyRequestId) return;
        messages.forEach((message) => renderMessage(message));
      } catch (error) {
        if (requestId === historyRequestId) showFeedback(error.message, true);
      }
    }

    async function refreshConversations() {
      try {
        conversations = await request('/chat/conversations');
        renderConversationList();
        if (!selectedCustomerId && conversations.length) {
          selectedCustomerId = String(conversations[0].customerId);
          renderConversationList();
          await loadHistory(selectedCustomerId);
        } else {
          const currentConversation = conversations.find((item) => String(item.customerId) === selectedCustomerId);
          if (currentConversation) {
            threadTitle.textContent = currentConversation.customerName || `Khách hàng #${selectedCustomerId}`;
            updateAutomationUI(currentConversation);
          }
        }
      } catch (error) {
        showFeedback(error.message, true);
      }
    }

    // --- AI Suggest Reply Button ---
    btnSuggest?.addEventListener('click', async () => {
      if (!selectedCustomerId) {
        showFeedback('Vui lòng chọn một cuộc trò chuyện từ danh sách trước.', true);
        return;
      }

      const conversation = conversations.find((item) => String(item.customerId) === selectedCustomerId);
      // Tìm tin nhắn gần nhất của khách hàng
      let lastCustomerMsg = '';
      const allBubbles = Array.from(messageList.querySelectorAll('.admin-chat-message'));
      for (let i = allBubbles.length - 1; i >= 0; i--) {
        const b = allBubbles[i];
        if (!b.classList.contains('outgoing') && !b.classList.contains('assistant')) {
          const p = b.querySelector('p');
          if (p && p.textContent.trim()) {
            lastCustomerMsg = p.textContent.trim();
            break;
          }
        }
      }

      if (!lastCustomerMsg && conversation?.lastSenderRole === 'customer') {
        lastCustomerMsg = conversation.lastMessage;
      }

      if (!lastCustomerMsg) {
        showFeedback('Chưa tìm thấy tin nhắn nào từ khách hàng để AI phân tích.', true);
        return;
      }

      const originalBtnText = btnSuggest.innerHTML;
      btnSuggest.disabled = true;
      btnSuggest.innerHTML = '✨ Đang phân tích...';
      showFeedback('Trợ lý AI đang phân tích ý định và chuẩn bị câu trả lời phù hợp...');

      try {
        const data = await request('/chat/ai/suggest', {
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
          showFeedback('✨ Đã điền câu trả lời gợi ý từ AI! Bạn có thể chỉnh sửa rồi bấm Gửi.');
        } else {
          showFeedback('Không thể tạo gợi ý câu trả lời, vui lòng thử lại.', true);
        }
      } catch (err) {
        showFeedback(err.message || 'Lỗi khi gọi Trợ lý AI.', true);
      } finally {
        btnSuggest.disabled = false;
        btnSuggest.innerHTML = originalBtnText;
      }
    });

    // --- Quick reply chips ---
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

    // --- Toggle AI Automation Button ---
    btnToggleAuto?.addEventListener('click', async () => {
      if (!selectedCustomerId) {
        showFeedback('Vui lòng chọn khách hàng trước.', true);
        return;
      }

      const conversation = conversations.find((item) => String(item.customerId) === selectedCustomerId);
      const currentlyPaused = isAiPaused(conversation);
      const willEnable = currentlyPaused; // Nếu đang pause thì bấm để enable

      btnToggleAuto.disabled = true;
      try {
        await request(`/chat/${encodeURIComponent(selectedCustomerId)}/automation`, {
          method: 'PATCH',
          body: JSON.stringify({
            enabled: willEnable,
            pauseMinutes: 30,
          }),
        });

        if (conversation) {
          if (willEnable) {
            conversation.aiPausedUntil = null;
          } else {
            conversation.aiPausedUntil = new Date(Date.now() + 30 * 60 * 1000).toISOString();
          }
          updateAutomationUI(conversation);
        }

        showFeedback(willEnable ? '✅ Đã kích hoạt lại AI tự động trả lời cho khách này.' : '⏸️ Đã tạm dừng AI tự động trong 30 phút.');
      } catch (err) {
        showFeedback(err.message || 'Không thể thay đổi trạng thái AI.', true);
      } finally {
        btnToggleAuto.disabled = false;
      }
    });

    conversationList.addEventListener('click', (event) => {
      const button = event.target.closest('[data-customer-id]');
      if (!button) return;
      selectedCustomerId = button.dataset.customerId;
      renderConversationList();
      showFeedback('');
      loadHistory(selectedCustomerId);
    });

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const message = input.value.trim();
      if (!message || !selectedCustomerId) {
        showFeedback('Chọn một hội thoại trước khi trả lời.', true);
        return;
      }
      if (!socket?.connected) {
        showFeedback('Kết nối chat chưa sẵn sàng. Vui lòng thử lại.', true);
        return;
      }

      const sendButton = form.querySelector('button[type="submit"]');
      sendButton.disabled = true;
      socket.emit('chat:send', { message, customerId: selectedCustomerId }, (result) => {
        sendButton.disabled = false;
        if (!result?.success) {
          showFeedback(result?.message || 'Không thể gửi tin nhắn.', true);
          return;
        }
        input.value = '';
        if (intentTag) intentTag.style.display = 'none';
        showFeedback('Đã gửi trả lời. Trợ lý AI sẽ tạm dừng 10 phút.');
      });
    });

    socket?.on('chat:new', (message) => {
      if (active && String(message.customerId) === selectedCustomerId) renderMessage(message);
      refreshConversations();
    });

    return {
      setActive(isActive) {
        if (active === isActive) return;
        active = isActive;
        if (active) refreshConversations();
      },
    };
  }

  window.initAdminChatInbox = initAdminChatInbox;
}());
