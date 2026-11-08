document.addEventListener('DOMContentLoaded', async () => {
  const token = sessionStorage.getItem('fastfood_auth_token');
  const orderCode = new URLSearchParams(window.location.search).get('orderCode');
  const statusElement = document.getElementById('payment-status');
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

  function showRetry() {
    if (retryButton) return;
    retryButton = document.createElement('button');
    retryButton.className = 'btn btn-primary';
    retryButton.type = 'button';
    retryButton.textContent = 'Thử lại thanh toán';
    retryButton.addEventListener('click', () => (
      currentPayment ? initiatePayment(true) : refreshStatus()
    ));
    actions.appendChild(retryButton);
  }

  async function initiatePayment(isRetry = false) {
    if (!currentPayment) return;
    statusElement.textContent = 'Đang kết nối cổng thanh toán...';
    if (retryButton) retryButton.disabled = true;
    try {
      const method = currentPayment.paymentMethod === 'momo' ? 'momo' : 'bank';
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
      if (currentPayment.paymentMethod === 'momo' && result.data.paymentUrl) {
        window.location.assign(result.data.paymentUrl);
        return;
      }
      currentPayment = { ...currentPayment, ...result.data, paymentStatus: 'pending' };
      renderPayment(currentPayment);
      window.setTimeout(refreshStatus, 5000);
    } catch (error) {
      statusElement.textContent = error.message || 'Không thể kết nối cổng thanh toán.';
      if (retryButton) retryButton.disabled = false;
      else showRetry();
    }
  }

  function renderPayment(payment) {
    currentPayment = payment;
    amountElement.textContent = window.UIComponents?.formatVND(payment.amount) || `${payment.amount} đ`;
    content.replaceChildren();
    actions.replaceChildren();
    retryButton = null;

    if (payment.paymentStatus === 'completed') {
      statusElement.textContent = 'Đã xác nhận thanh toán thành công.';
      const message = document.createElement('p');
      message.textContent = 'Đơn hàng đã được đồng bộ. Đang chuyển đến trang theo dõi...';
      content.appendChild(message);
      window.setTimeout(() => {
        window.location.assign(`./order-tracking.html?id=${encodeURIComponent(orderCode)}`);
      }, 1800);
      return;
    }

    if (payment.paymentStatus === 'failed') {
      statusElement.textContent = 'Giao dịch chưa thành công. Bạn có thể thử thanh toán lại.';
      showRetry();
      return;
    }

    statusElement.textContent = 'Đang chờ xác nhận thanh toán. Không đóng trang cho đến khi giao dịch được cập nhật.';
    if (payment.paymentMethod === 'banking' && payment.qrUrl) {
      const image = document.createElement('img');
      image.className = 'payment-qr';
      image.alt = 'Mã QR chuyển khoản cho đơn hàng';
      image.src = payment.qrUrl;
      content.appendChild(image);
      const details = document.createElement('p');
      details.className = 'payment-details';
      details.textContent = `Ngân hàng: ${payment.bankCode} · STK: ${payment.accountNumber}`
        + `${payment.accountName ? ` · Chủ TK: ${payment.accountName}` : ''}`
        + ` · Nội dung: ${orderCode}`;
      content.appendChild(details);
    } else if (payment.paymentMethod === 'momo' && payment.paymentUrl) {
      const link = document.createElement('a');
      link.className = 'btn btn-primary';
      link.href = payment.paymentUrl;
      link.textContent = 'Mở MoMo để thanh toán';
      actions.appendChild(link);
      const note = document.createElement('p');
      note.textContent = 'Nếu đã thanh toán trong ứng dụng, trạng thái sẽ tự cập nhật sau khi MoMo xác nhận.';
      content.appendChild(note);
    } else {
      statusElement.textContent = 'Chưa có thông tin giao dịch. Hãy thử khởi tạo lại.';
      showRetry();
    }
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
      if (result.data.paymentStatus === 'completed' || result.data.paymentStatus === 'failed') return;
      if (!result.data.qrUrl && !result.data.paymentUrl && !result.data.paymentMethod) return;
      if (!result.data.qrUrl && !result.data.paymentUrl) {
        await initiatePayment();
        return;
      }
      window.setTimeout(refreshStatus, 5000);
    } catch (error) {
      statusElement.textContent = error.message || 'Không thể kiểm tra trạng thái thanh toán.';
      showRetry();
      window.setTimeout(refreshStatus, 10000);
    } finally {
      polling = false;
    }
  }

  await refreshStatus();
});
