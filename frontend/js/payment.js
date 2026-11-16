document.addEventListener('DOMContentLoaded', async () => {
  const token = sessionStorage.getItem('fastfood_auth_token');
  const orderCode = new URLSearchParams(window.location.search).get('orderCode');
  const statusElement = document.getElementById('payment-status');
  const countdownElement = document.getElementById('payment-countdown');
  const content = document.getElementById('payment-content');
  const actions = document.getElementById('payment-actions');
  const orderElement = document.getElementById('payment-order');
  const amountElement = document.getElementById('payment-amount');
  const apiUrl = window.location.port === '5000'
    ? `${window.location.origin}/api/v1`
    : 'http://localhost:5000/api/v1';

  if (!token || !orderCode) {
    statusElement.textContent = 'Đường dẫn thanh toán không hợp lệ hoặc phiên đăng nhập đã hết hạn.';
    return;
  }
  orderElement.textContent = `Mã đơn: ${orderCode}`;
  window.UIComponents?.renderHeader('orders');
  window.UIComponents?.renderFooter();

  let currentPayment = null;
  let polling = false;
  let retryButton;

  const remainingTime = (value) => value ? new Date(value).getTime() - Date.now() : 0;
  const formatCountdown = (milliseconds) => {
    const seconds = Math.max(0, Math.ceil(milliseconds / 1000));
    const minutes = Math.floor(seconds / 60);
    return `${String(minutes).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
  };

  function appendAction(label, handler, className = 'btn btn-primary') {
    const button = document.createElement('button');
    button.className = className;
    button.type = 'button';
    button.textContent = label;
    button.addEventListener('click', handler);
    actions.appendChild(button);
    return button;
  }

  function updateCountdown() {
    if (!currentPayment) return;
    if (currentPayment.paymentStatus === 'pending' && currentPayment.expiresAt) {
      const remaining = remainingTime(currentPayment.expiresAt);
      countdownElement.textContent = remaining > 0
        ? `Thời gian thanh toán còn ${formatCountdown(remaining)}.`
        : 'Đang xác nhận giao dịch hết hạn...';
      if (remaining <= 0) refreshStatus();
      return;
    }

    if (currentPayment.paymentStatus === 'failed') {
      const retryRemaining = remainingTime(currentPayment.retryUntil);
      countdownElement.textContent = retryRemaining > 0
        ? `Bạn còn ${formatCountdown(retryRemaining)} để thanh toán lại hoặc đổi sang COD.`
        : 'Đã hết thời gian thanh toán lại. Đơn vẫn được giữ ở trạng thái không thành công.';
      return;
    }
    countdownElement.textContent = '';
  }

  async function switchToCod(button) {
    button.disabled = true;
    statusElement.textContent = 'Đang đổi đơn hàng sang thanh toán COD...';
    try {
      const response = await fetch(`${apiUrl}/payments/${encodeURIComponent(orderCode)}/switch-to-cod`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Không thể đổi sang COD.');
      }
      window.location.assign(`./order-tracking.html?id=${encodeURIComponent(orderCode)}`);
    } catch (error) {
      statusElement.textContent = error.message || 'Không thể đổi sang COD.';
      button.disabled = false;
    }
  }

  async function checkPaymentManually(button) {
    if (button) button.disabled = true;
    statusElement.textContent = 'Đang kiểm tra giao dịch từ hệ thống...';
    try {
      const response = await fetch(`${apiUrl}/payments/${encodeURIComponent(orderCode)}/status`, {
        headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      });
      const result = await response.json();
      if (!response.ok || !result.success || !result.data) {
        throw new Error(result.message || 'Không thể kiểm tra giao dịch.');
      }
      renderPayment(result.data);
      if (result.data.paymentStatus === 'completed') {
        statusElement.textContent = 'Thanh toán thành công! Đang chuyển hướng đến trang theo dõi...';
        window.setTimeout(() => {
          window.location.assign(`./order-tracking.html?id=${encodeURIComponent(orderCode)}`);
        }, 1200);
      } else {
        statusElement.textContent = 'Hệ thống chưa nhận được tiền từ ngân hàng. Nếu bạn vừa chuyển khoản, vui lòng đợi 5 - 30 giây để SePay tự động xử lý biến động số dư.';
      }
    } catch (error) {
      statusElement.textContent = error.message || 'Lỗi khi kiểm tra giao dịch.';
    } finally {
      if (button) button.disabled = false;
    }
  }

  async function initiatePayment(button) {
    if (!currentPayment) return;
    const method = currentPayment.paymentMethod === 'banking' ? 'vietqr' : currentPayment.paymentMethod;
    statusElement.textContent = 'Đang khởi tạo lượt thanh toán mới...';
    if (button) button.disabled = true;
    try {
      const response = await fetch(`${apiUrl}/payments/${method}/initiate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ orderCode }),
      });
      const result = await response.json();
      if (!response.ok || !result.success || !result.data) {
        throw new Error(result.message || 'Không thể khởi tạo giao dịch.');
      }
      if (['momo', 'vnpay'].includes(currentPayment.paymentMethod) && result.data.paymentUrl) {
        window.location.assign(result.data.paymentUrl);
        return;
      }
      currentPayment = { ...currentPayment, ...result.data, paymentStatus: 'pending' };
      renderPayment(currentPayment);
      window.setTimeout(refreshStatus, 5000);
    } catch (error) {
      statusElement.textContent = error.message || 'Không thể kết nối cổng thanh toán.';
      if (button) button.disabled = false;
      else appendAction('Thử khởi tạo lại', (event) => initiatePayment(event.currentTarget), 'btn btn-outline');
    }
  }

  function renderPayment(payment) {
    currentPayment = payment;
    amountElement.textContent = window.UIComponents?.formatVND(payment.amount) || `${payment.amount} đ`;
    content.replaceChildren();
    actions.replaceChildren();
    retryButton = null;

    if (payment.paymentMethod === 'cod') {
      statusElement.textContent = 'Đơn hàng đã chuyển sang thanh toán COD.';
      appendAction('Theo dõi đơn hàng', () => {
        window.location.assign(`./order-tracking.html?id=${encodeURIComponent(orderCode)}`);
      });
      updateCountdown();
      return;
    }

    if (payment.paymentStatus === 'completed') {
      statusElement.textContent = 'Đã xác nhận thanh toán thành công.';
      const message = document.createElement('p');
      message.textContent = 'Đơn hàng đã được đồng bộ. Đang chuyển đến trang theo dõi...';
      content.appendChild(message);
      window.setTimeout(() => {
        window.location.assign(`./order-tracking.html?id=${encodeURIComponent(orderCode)}`);
      }, 1800);
      updateCountdown();
      return;
    }

    if (payment.paymentStatus === 'failed') {
      statusElement.textContent = 'Thanh toán không thành công.';
      const canRetry = payment.canRetry || remainingTime(payment.retryUntil) > 0;
      const canSwitchToCod = payment.canSwitchToCod || remainingTime(payment.retryUntil) > 0;
      if (canRetry) {
        const label = payment.paymentMethod === 'momo' ? 'Thanh toán lại qua MoMo'
          : payment.paymentMethod === 'banking' ? 'Thanh toán lại qua VietQR' : 'Thử lại thanh toán';
        retryButton = appendAction(label, (event) => initiatePayment(event.currentTarget));
      }
      if (canSwitchToCod) {
        appendAction('Đổi sang thanh toán COD', (event) => switchToCod(event.currentTarget), 'btn btn-outline');
      }
      updateCountdown();
      return;
    }

    statusElement.textContent = 'Chờ thanh toán. Đơn chỉ được chuyển đến quán sau khi thanh toán thành công.';
    if (payment.paymentMethod === 'banking' && payment.qrUrl) {
      const image = document.createElement('img');
      image.className = 'payment-qr';
      image.alt = 'Mã VietQR thanh toán đơn hàng';
      image.src = payment.qrUrl;
      content.appendChild(image);

      const bankCode = payment.bankCode || 'BIDV';
      const accountNumber = payment.accountNumber || '8855033140';
      const accountName = payment.accountName || 'NGUYEN HIEN TAI';

      const detailsBox = document.createElement('div');
      detailsBox.className = 'payment-details-box';
      detailsBox.style.cssText = 'background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.25rem; margin:1rem 0; text-align:left; font-size:0.92rem; box-shadow:0 2px 8px rgba(0,0,0,0.03);';
      detailsBox.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed #e2e8f0;">
          <span style="color:#64748b;">Ngân hàng:</span>
          <strong style="color:#0f172a; font-weight:700;">${bankCode}</strong>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed #e2e8f0;">
          <span style="color:#64748b;">Số tài khoản:</span>
          <strong style="color:#0f172a; font-size:1.05rem; font-weight:700; letter-spacing:0.5px;">${accountNumber}</strong>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed #e2e8f0;">
          <span style="color:#64748b;">Chủ tài khoản:</span>
          <strong style="color:#0f172a; font-weight:700;">${accountName}</strong>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed #e2e8f0;">
          <span style="color:#64748b;">Số tiền:</span>
          <strong style="color:#ea580c; font-size:1.15rem; font-weight:800;">${window.UIComponents?.formatVND(payment.amount)}</strong>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0;">
          <span style="color:#64748b;">Nội dung CK:</span>
          <strong style="color:#ea580c; font-size:1.15rem; font-weight:800; background:#ffedd5; padding:2px 8px; border-radius:6px; letter-spacing:0.5px;">${orderCode}</strong>
        </div>
      `;
      content.appendChild(detailsBox);

      const autoNote = document.createElement('div');
      autoNote.style.cssText = 'padding:0.75rem 1rem; border-radius:10px; background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; font-size:0.85rem; text-align:left; line-height:1.5; margin-bottom:0.75rem;';
      autoNote.innerHTML = `
        <div style="font-weight:700; margin-bottom:3px; display:flex; align-items:center; gap:5px;">
          <span>⚡</span> Tự động duyệt đơn 24/7 qua SePay / VietQR
        </div>
        <div>Hệ thống sẽ <strong>tự động chuyển trạng thái ngay khi nhận được tiền</strong> (thường mất 5 - 30 giây). Vui lòng không thay đổi số tiền hoặc nội dung chuyển khoản.</div>
      `;
      content.appendChild(autoNote);
    } else if (payment.paymentMethod === 'momo' && payment.qrUrl) {
      const badge = document.createElement('div');
      badge.style.cssText = 'display:inline-flex; align-items:center; gap:0.4rem; background:#D82D8B; color:#fff; font-weight:700; padding:0.35rem 0.95rem; border-radius:999px; margin-bottom:0.75rem; font-size:0.85rem; box-shadow:0 4px 12px rgba(216,45,139,0.25);';
      badge.innerHTML = '<span>Ví Điện Tử MoMo</span>';
      content.appendChild(badge);

      const image = document.createElement('img');
      image.className = 'payment-qr';
      image.alt = 'Mã QR MoMo thanh toán đơn hàng';
      image.src = payment.qrUrl;
      content.appendChild(image);

      const phone = payment.momoPhone || '0911000001';
      const name = payment.momoName || 'FASTFOOD VIET NAM';

      const detailsBox = document.createElement('div');
      detailsBox.className = 'payment-details-box';
      detailsBox.style.cssText = 'background:#fdf2f8; border:1px solid #fbcfe8; border-radius:12px; padding:1rem 1.25rem; margin:1rem 0; text-align:left; font-size:0.92rem;';
      detailsBox.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed #fbcfe8;">
          <span style="color:#831843;">Số ví MoMo:</span>
          <strong style="color:#831843; font-weight:700;">${phone}</strong>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed #fbcfe8;">
          <span style="color:#831843;">Tên người nhận:</span>
          <strong style="color:#831843; font-weight:700;">${name}</strong>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed #fbcfe8;">
          <span style="color:#831843;">Số tiền:</span>
          <strong style="color:#db2777; font-size:1.15rem; font-weight:800;">${window.UIComponents?.formatVND(payment.amount)}</strong>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0;">
          <span style="color:#831843;">Lời nhắn chuyển:</span>
          <strong style="color:#db2777; font-size:1.15rem; font-weight:800; background:#fce7f3; padding:2px 8px; border-radius:6px;">${orderCode}</strong>
        </div>
      `;
      content.appendChild(detailsBox);

      const autoNote = document.createElement('div');
      autoNote.style.cssText = 'padding:0.75rem 1rem; border-radius:10px; background:#fdf2f8; border:1px solid #fbcfe8; color:#9d174d; font-size:0.85rem; text-align:left; line-height:1.5; margin-bottom:0.75rem;';
      autoNote.innerHTML = `
        <div style="font-weight:700; margin-bottom:3px;">⚡ Duyệt đơn tự động qua MoMo</div>
        <div>Vui lòng điền đúng <strong>Lời nhắn chuyển</strong> là <strong>${orderCode}</strong> để hệ thống tự động xác nhận đơn hàng của bạn.</div>
      `;
      content.appendChild(autoNote);
    } else if (['momo', 'vnpay'].includes(payment.paymentMethod) && payment.paymentUrl) {
      const link = document.createElement('a');
      link.className = 'btn btn-primary';
      link.href = payment.paymentUrl;
      link.textContent = payment.paymentMethod === 'momo' ? 'Mở MoMo để thanh toán' : 'Thanh toán';
      actions.appendChild(link);
      const note = document.createElement('p');
      note.textContent = 'Sau khi thanh toán, hệ thống sẽ tự cập nhật trạng thái giao dịch.';
      content.appendChild(note);
    } else {
      retryButton = appendAction('Tiếp tục thanh toán', (event) => initiatePayment(event.currentTarget));
    }

    if (['banking', 'momo', 'vietqr'].includes(payment.paymentMethod) && payment.paymentStatus === 'pending') {
      appendAction('🔄 Kiểm tra giao dịch', (event) => checkPaymentManually(event.currentTarget), 'btn btn-primary');
    }

    if (payment.canSwitchToCod) {
      appendAction('Đổi sang thanh toán COD', (event) => switchToCod(event.currentTarget), 'btn btn-outline');
    }
    updateCountdown();
  }

  async function refreshStatus() {
    if (polling) return;
    polling = true;
    try {
      const response = await fetch(`${apiUrl}/payments/${encodeURIComponent(orderCode)}/status`, {
        headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      });
      const result = await response.json();
      if (!response.ok || !result.success || !result.data) {
        throw new Error(result.message || 'Không thể tải trạng thái thanh toán.');
      }
      renderPayment(result.data);
      if (result.data.paymentStatus === 'completed' || result.data.paymentMethod === 'cod') return;
      if (result.data.paymentStatus === 'failed') return;
      if (!result.data.qrUrl && !result.data.paymentUrl && !result.data.paymentMethod) return;
      if (!result.data.qrUrl && !result.data.paymentUrl) {
        await initiatePayment();
        return;
      }
      window.setTimeout(refreshStatus, 5000);
    } catch (error) {
      statusElement.textContent = error.message || 'Không thể kiểm tra trạng thái thanh toán.';
      appendAction('Kiểm tra lại', refreshStatus, 'btn btn-outline');
      window.setTimeout(refreshStatus, 10000);
    } finally {
      polling = false;
    }
  }

  window.setInterval(updateCountdown, 1000);
  await refreshStatus();
});
