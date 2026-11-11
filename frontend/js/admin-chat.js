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

    const btnAdminBusyToggle = document.getElementById('btn-admin-busy-toggle');
    const btnSuggest = document.getElementById('btn-ai-suggest-reply');
    const btnToggleAuto = document.getElementById('btn-toggle-ai-automation');
    const intentTag = document.getElementById('ai-detected-intent');
    const quickChips = document.getElementById('ai-quick-chips');

    let conversations = [];
    let selectedSessionId = '';
    let active = false;
    let historyRequestId = 0;
    let supportStatus = { adminOnline: true, adminManualBusy: null, effectiveBusy: false };
    let pollTimer = null;

    async function chatRequest(path, options = {}) {
      const response = await fetch(`${apiUrl}/chat${path}`, {
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

    async function realtimeRequest(path, options = {}) {
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
      if (!message) return;
      if (message.id && messageList.querySelector(`[data-message-id="${message.id}"]`)) return;

      const role = message.sender || message.senderRole || 'user';
      const isAdminMsg = role === 'admin';
      const isAiMsg = role === 'assistant' || role === 'ai';

      const bubble = document.createElement('article');
      bubble.className = `admin-chat-message${isAdminMsg ? ' outgoing' : ''}${isAiMsg ? ' assistant' : ''}`;
      if (message.id) bubble.dataset.messageId = String(message.id);

      const sender = document.createElement('strong');
      sender.textContent = isAdminMsg
        ? 'Bạn (Admin - Nhân viên hỗ trợ)'
        : isAiMsg
          ? '🤖 Trợ lý tự động (Khi Admin bận)'
          : 'Khách hàng';

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

    function updateBusyToggleUI(status) {
      if (status) {
        supportStatus = { ...supportStatus, ...status };
      }
      if (!btnAdminBusyToggle) return;

      const isBusy = Boolean(supportStatus.effectiveBusy);
      if (isBusy) {
        btnAdminBusyToggle.textContent = '🟠 Đang bận (AI tự rep)';
        btnAdminBusyToggle.classList.remove('secondary');
        btnAdminBusyToggle.title = 'Hệ thống đang tự động trả lời khách thay bạn. Bấm để chuyển sang Đang trực.';
      } else {
        btnAdminBusyToggle.textContent = '🟢 Đang trực (Tự trả lời)';
        btnAdminBusyToggle.classList.add('secondary');
        btnAdminBusyToggle.title = 'Bạn đang trực tiếp nhận tin nhắn (AI không tự trả lời). Bấm nếu bạn đang bận.';
      }
    }

    function updateAutomationUI(conversation) {
      const paused = isAiPaused(conversation);
      const forced = Boolean(conversation?.aiForceAuto);

      if (btnToggleAuto) {
        if (paused || (!supportStatus.effectiveBusy && !forced)) {
          btnToggleAuto.textContent = '🤖 Bật AI tự động cho khách này';
          btnToggleAuto.classList.remove('secondary');
          btnToggleAuto.title = 'Cho phép trợ lý AI tự trả lời khách này ngay cả khi bạn đang trực.';
        } else {
          btnToggleAuto.textContent = '⏸️ Tạm dừng AI (30p)';
          btnToggleAuto.classList.add('secondary');
          btnToggleAuto.title = 'Tạm dừng trợ lý AI cho khách này trong 30 phút để bạn trực tiếp trao đổi.';
        }
      }

      if (paused) {
        const pauseTime = new Date(conversation.aiPausedUntil);
        assistantStatus.textContent = `Admin đang tiếp quản. AI tạm dừng đến ${pauseTime.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}.`;
      } else if (forced) {
        assistantStatus.textContent = 'Đang bật Trợ lý tự động riêng cho khách hàng này.';
      } else if (supportStatus.effectiveBusy) {
        assistantStatus.textContent = 'Admin đang bận: Trợ lý tự động sẽ trả lời tin nhắn mới của khách.';
      } else {
        assistantStatus.textContent = 'Admin đang trực: AI tắt tự động, bạn tự đọc và trả lời trực tiếp cho khách.';
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
        const sessionId = String(conversation.sessionId);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `chat-conversation${sessionId === selectedSessionId ? ' active' : ''}`;
        button.dataset.sessionId = sessionId;

        const name = document.createElement('strong');
        name.textContent = conversation.customerName || 'Khách hàng';

        const preview = document.createElement('span');
        const prefix = conversation.lastSender === 'user'
          ? 'Khách: '
          : conversation.lastSender === 'admin'
            ? 'Bạn: '
            : '🤖 ';
        preview.textContent = `${prefix}${conversation.lastMessage || '...'}`;

        const meta = document.createElement('small');
        meta.textContent = conversation.email || (conversation.userId ? `ID khách: ${conversation.userId}` : 'Khách vãng lai');

        button.append(name, preview, meta);
        conversationList.appendChild(button);
      });
    }

    async function loadHistory(sessionId) {
      const requestId = ++historyRequestId;
      messageList.replaceChildren();
      if (intentTag) intentTag.style.display = 'none';

      const conversation = conversations.find((item) => String(item.sessionId) === sessionId);
      threadTitle.textContent = conversation?.customerName || 'Khách hàng';
      updateAutomationUI(conversation);

      try {
        const messages = await chatRequest(`/admin/sessions/${encodeURIComponent(sessionId)}/messages`);
        if (requestId !== historyRequestId) return;
        messages.forEach((message) => renderMessage(message));
      } catch (error) {
        if (requestId === historyRequestId) showFeedback(error.message, true);
      }
    }

    async function refreshConversations() {
      try {
        const data = await chatRequest('/admin/conversations');
        conversations = Array.isArray(data?.conversations) ? data.conversations : [];
        if (data?.supportStatus) {
          updateBusyToggleUI(data.supportStatus);
        }
        renderConversationList();

        if (!selectedSessionId && conversations.length) {
          selectedSessionId = String(conversations[0].sessionId);
          renderConversationList();
          await loadHistory(selectedSessionId);
        } else {
          const currentConversation = conversations.find((item) => String(item.sessionId) === selectedSessionId);
          if (currentConversation) {
            threadTitle.textContent = currentConversation.customerName || 'Khách hàng';
            updateAutomationUI(currentConversation);
          }
        }
      } catch (error) {
        showFeedback(error.message, true);
      }
    }

    // --- Toggle Global Admin Busy / Online Status ---
    btnAdminBusyToggle?.addEventListener('click', async () => {
      const nextBusy = !supportStatus.effectiveBusy;
      btnAdminBusyToggle.disabled = true;
      try {
        const data = await chatRequest('/admin/status', {
          method: 'PATCH',
          body: JSON.stringify({ busy: nextBusy }),
        });
        updateBusyToggleUI(data);
        const currentConversation = conversations.find((item) => String(item.sessionId) === selectedSessionId);
        updateAutomationUI(currentConversation);
        showFeedback(
          nextBusy
            ? '🟠 Đã chuyển sang ĐANG BẬN: Trợ lý tự động sẽ trả lời khách thay bạn.'
            : '🟢 Đã chuyển sang ĐANG TRỰC: Tin nhắn khách sẽ chờ bạn trực tiếp phản hồi.'
        );
      } catch (err) {
        showFeedback(err.message || 'Không thể đổi trạng thái trực.', true);
      } finally {
        btnAdminBusyToggle.disabled = false;
      }
    });

    // --- AI Suggest Reply Button ---
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
          const p = b.querySelector('p');
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
        showFeedback('Chưa tìm thấy tin nhắn nào từ khách hàng để AI phân tích.', true);
        return;
      }

      const originalBtnText = btnSuggest.innerHTML;
      btnSuggest.disabled = true;
      btnSuggest.innerHTML = '✨ Đang phân tích...';
      showFeedback('Đang gợi ý câu trả lời phù hợp...');

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
          showFeedback('Không thể tạo gợi ý câu trả lời, vui lòng thử lại.', true);
        }
      } catch (err) {
        showFeedback(err.message || 'Lỗi khi gọi gợi ý AI.', true);
      } finally {
        btnSuggest.disabled = false;
        btnSuggest.innerHTML = originalBtnText;
      }
    });

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

    btnToggleAuto?.addEventListener('click', async () => {
      if (!selectedSessionId) {
        showFeedback('Vui lòng chọn khách hàng trước.', true);
        return;
      }

      const conversation = conversations.find((item) => String(item.sessionId) === selectedSessionId);
      const currentlyAutoActive = !isAiPaused(conversation) && (conversation?.aiForceAuto || supportStatus.effectiveBusy);
      const willEnable = !currentlyAutoActive;

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
            ? '✅ Đã bật tự động trả lời cho khách hàng này.'
            : '⏸️ Đã tạm dừng tự động trong 30 phút để bạn trực tiếp hỗ trợ.'
        );
      } catch (err) {
        showFeedback(err.message || 'Không thể thay đổi trạng thái AI.', true);
      } finally {
        btnToggleAuto.disabled = false;
      }
    });

    conversationList.addEventListener('click', (event) => {
      const button = event.target.closest('[data-session-id]');
      if (!button) return;
      selectedSessionId = button.dataset.sessionId;
      renderConversationList();
      showFeedback('');
      loadHistory(selectedSessionId);
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const message = input.value.trim();
      if (!message || !selectedSessionId) {
        showFeedback('Chọn một hội thoại trước khi trả lời.', true);
        return;
      }

      const sendButton = form.querySelector('button[type="submit"]');
      sendButton.disabled = true;
      try {
        const result = await chatRequest(`/admin/sessions/${encodeURIComponent(selectedSessionId)}/reply`, {
          method: 'POST',
          body: JSON.stringify({ message }),
        });
        input.value = '';
        if (intentTag) intentTag.style.display = 'none';
        if (result?.message) {
          renderMessage(result.message);
        }
        const conv = conversations.find((item) => String(item.sessionId) === selectedSessionId);
        if (conv) {
          conv.aiPausedUntil = result?.aiPausedUntil || new Date(Date.now() + 15 * 60 * 1000).toISOString();
          conv.aiForceAuto = false;
          conv.lastMessage = message;
          conv.lastSender = 'admin';
          updateAutomationUI(conv);
          renderConversationList();
        }
        showFeedback('Đã gửi tin nhắn trực tiếp cho khách hàng (Tạm dừng tự động 15 phút).');
      } catch (err) {
        showFeedback(err.message || 'Không thể gửi tin nhắn.', true);
      } finally {
        sendButton.disabled = false;
      }
    });

    socket?.on('support:new_message', (payload) => {
      if (!payload) return;
      if (active && String(payload.sessionId) === selectedSessionId && payload.message) {
        renderMessage(payload.message);
      }
      refreshConversations();
    });

    socket?.on('support:status', (status) => {
      updateBusyToggleUI(status);
      const currentConversation = conversations.find((item) => String(item.sessionId) === selectedSessionId);
      if (currentConversation) updateAutomationUI(currentConversation);
    });

    return {
      setActive(isActive) {
        if (active === isActive) return;
        active = isActive;
        if (pollTimer) {
          clearInterval(pollTimer);
          pollTimer = null;
        }
        if (active) {
          refreshConversations();
          pollTimer = setInterval(() => {
            if (active && selectedSessionId) {
              chatRequest(`/admin/sessions/${encodeURIComponent(selectedSessionId)}/messages`)
                .then((msgs) => Array.isArray(msgs) && msgs.forEach(renderMessage))
                .catch(() => {});
            }
          }, 5000);
        }
      },
    };
  }

  window.initAdminChatInbox = initAdminChatInbox;
}());
