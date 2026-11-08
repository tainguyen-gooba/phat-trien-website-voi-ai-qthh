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
    let conversations = [];
    let selectedCustomerId = '';
    let active = false;
    let historyRequestId = 0;

    async function request(path) {
      const response = await fetch(`${apiUrl}/realtime${path}`, {
        headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Không thể tải dữ liệu tin nhắn.');
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
        ? 'Bạn'
        : message.senderRole === 'ai' ? 'Trợ lý AI' : 'Khách hàng';
      const content = document.createElement('p');
      content.textContent = message.message;
      bubble.append(sender, content);
      messageList.appendChild(bubble);
      messageList.scrollTop = messageList.scrollHeight;
    }

    function showAssistantStatus(conversation) {
      const pauseTime = conversation?.aiPausedUntil ? new Date(conversation.aiPausedUntil) : null;
      if (pauseTime && pauseTime.getTime() > Date.now()) {
        assistantStatus.textContent = `Trợ lý AI tạm dừng đến ${pauseTime.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}.`;
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
        preview.textContent = `${conversation.lastSenderRole === 'customer' ? 'Khách: ' : ''}${conversation.lastMessage}`;
        const email = document.createElement('small');
        email.textContent = conversation.email || `Mã khách hàng: ${customerId}`;
        button.append(name, preview, email);
        conversationList.appendChild(button);
      });
    }

    async function loadHistory(customerId) {
      const requestId = ++historyRequestId;
      messageList.replaceChildren();
      const conversation = conversations.find((item) => String(item.customerId) === customerId);
      threadTitle.textContent = conversation?.customerName || `Khách hàng #${customerId}`;
      showAssistantStatus(conversation);
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
            showAssistantStatus(currentConversation);
          }
        }
      } catch (error) {
        showFeedback(error.message, true);
      }
    }

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
