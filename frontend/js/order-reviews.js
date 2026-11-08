(function () {
  const activeOrders = new Set();

  function startOrderReview(order, { apiUrl, token, onSaved } = {}) {
    if (!order?.id || !apiUrl || !token || activeOrders.has(String(order.id))) return;
    if (order.driverReview && order.restaurantReview) return;
    const deferKey = `fastfood-review-deferred-${order.id}`;
    if (sessionStorage.getItem(deferKey)) return;

    activeOrders.add(String(order.id));
    let target = order.driverReview ? 'restaurant' : 'driver';
    const dialog = document.createElement('dialog');
    dialog.className = 'order-review-dialog';
    dialog.setAttribute('aria-labelledby', 'order-review-title');
    dialog.innerHTML = `
      <form class="order-review-form">
        <button class="order-review-close" type="button" aria-label="Để đánh giá sau">×</button>
        <span class="order-review-icon" aria-hidden="true">★</span>
        <p class="order-review-eyebrow">Đơn hàng đã giao thành công</p>
        <h2 id="order-review-title"></h2>
        <p class="order-review-description"></p>
        <div class="order-review-stars" role="group" aria-label="Chọn số sao"></div>
        <label class="order-review-label" for="order-review-comment">Nhận xét</label>
        <textarea id="order-review-comment" name="comment" maxlength="1000" rows="3" placeholder="Chia sẻ trải nghiệm của bạn..."></textarea>
        <p class="order-review-error" role="alert"></p>
        <div class="order-review-actions">
          <button class="order-review-later" type="button">Để sau</button>
          <button class="order-review-submit" type="submit">Gửi đánh giá</button>
        </div>
      </form>
    `;

    const form = dialog.querySelector('.order-review-form');
    const title = dialog.querySelector('#order-review-title');
    const description = dialog.querySelector('.order-review-description');
    const stars = dialog.querySelector('.order-review-stars');
    const comment = dialog.querySelector('#order-review-comment');
    const error = dialog.querySelector('.order-review-error');
    const submit = dialog.querySelector('.order-review-submit');
    let rating = 0;
    let showing = false;

    function renderStep() {
      rating = 0;
      error.textContent = '';
      comment.value = '';
      if (target === 'driver') {
        title.textContent = 'Đánh giá tài xế';
        description.textContent = `Tài xế ${order.shipper?.name || 'FASTFOOD Express'} giao hàng thế nào?`;
      } else {
        title.textContent = 'Đánh giá quán ăn';
        description.textContent = `Bạn hài lòng với món ăn từ ${order.restaurantName || 'quán'} chứ?`;
      }
      stars.replaceChildren();
      [1, 2, 3, 4, 5].forEach((value) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'order-review-star';
        button.dataset.rating = String(value);
        button.setAttribute('aria-label', `${value} sao`);
        button.setAttribute('aria-pressed', 'false');
        button.textContent = '★';
        stars.appendChild(button);
      });
      submit.textContent = target === 'driver' && !order.restaurantReview
        ? 'Tiếp tục đánh giá quán'
        : 'Gửi đánh giá';
    }

    function defer() {
      sessionStorage.setItem(deferKey, 'true');
      if (dialog.open) dialog.close();
      activeOrders.delete(String(order.id));
      dialog.remove();
    }

    dialog.querySelector('.order-review-close').addEventListener('click', defer);
    dialog.querySelector('.order-review-later').addEventListener('click', defer);
    dialog.addEventListener('cancel', (event) => {
      event.preventDefault();
      defer();
    });
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) defer();
    });
    stars.addEventListener('click', (event) => {
      const selected = event.target.closest('[data-rating]');
      if (!selected) return;
      rating = Number(selected.dataset.rating);
      stars.querySelectorAll('[data-rating]').forEach((button) => {
        button.classList.toggle('selected', Number(button.dataset.rating) <= rating);
        button.setAttribute('aria-pressed', String(button === selected));
      });
    });
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!rating) {
        error.textContent = 'Vui lòng chọn số sao trước khi gửi.';
        return;
      }

      submit.disabled = true;
      error.textContent = '';
      try {
        const response = await fetch(
          `${apiUrl}/realtime/orders/${encodeURIComponent(order.id)}/reviews/${target}`,
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json',
              Authorization: `Bearer ${token}`,
            },
            body: JSON.stringify({ rating, comment: comment.value.trim() }),
          }
        );
        const result = await response.json();
        if (!response.ok || !result.success || !result.data) {
          throw new Error(result.message || 'Không thể lưu đánh giá.');
        }

        Object.assign(order, result.data);
        onSaved?.(order);
        if (target === 'driver' && !order.restaurantReview) {
          target = 'restaurant';
          renderStep();
          showing = false;
          dialog.close();
          window.setTimeout(() => {
            if (!showing && dialog.isConnected) {
              showing = true;
              dialog.showModal();
            }
          }, 180);
          return;
        }

        dialog.close();
        activeOrders.delete(String(order.id));
        dialog.remove();
        window.UIComponents?.showToast('Cảm ơn bạn đã gửi đánh giá!', 'success');
      } catch (requestError) {
        error.textContent = requestError.message || 'Không thể lưu đánh giá.';
      } finally {
        submit.disabled = false;
      }
    });

    if (!document.getElementById('order-review-styles')) {
      const style = document.createElement('style');
      style.id = 'order-review-styles';
      style.textContent = `
      .order-review-dialog{position:fixed;inset:0;width:min(470px,calc(100% - 32px));max-width:none;max-height:calc(100% - 32px);margin:auto;padding:0;overflow:auto;border:0;border-radius:20px;background:#fff;box-shadow:0 24px 80px #0004;color:#292929}
      .order-review-dialog::backdrop{background:#21180f88;backdrop-filter:blur(3px)}
      .order-review-form{position:relative;padding:30px}
      .order-review-close{position:absolute;top:14px;right:17px;border:0;background:transparent;color:#888;font-size:26px}
      .order-review-icon{display:grid;width:44px;height:44px;place-items:center;border-radius:14px;background:#fff3e5;color:#f28a24;font-size:24px}
      .order-review-eyebrow{margin:17px 0 6px;color:#ea760e;font-size:11px;font-weight:700}
      .order-review-form h2{margin:0;font-size:22px}
      .order-review-description{margin:8px 0 15px;color:#777;font-size:13px;line-height:1.55}
      .order-review-stars{display:flex;gap:8px;margin-bottom:18px}
      .order-review-star{padding:0;border:0;background:transparent;color:#d4d4d4;font-size:36px;line-height:1}
      .order-review-star.selected{color:#f5a623}
      .order-review-label{display:block;margin-bottom:7px;font-size:12px;font-weight:700}
      .order-review-form textarea{display:block;width:100%;resize:vertical;padding:11px;border:1px solid #ddd;border-radius:10px;font:inherit;font-size:13px}
      .order-review-error{min-height:18px;margin:6px 0;color:#d33;font-size:11px}
      .order-review-actions{display:flex;justify-content:flex-end;gap:9px}
      .order-review-actions button{min-height:40px;padding:0 14px;border:0;border-radius:9px;font-weight:700}
      .order-review-later{background:#f5f5f5;color:#666}
      .order-review-submit{background:#f28a24;color:#fff}
      .order-review-submit:disabled{opacity:.6}
      @media(max-width:480px){.order-review-form{padding:25px 20px}.order-review-actions button{padding:0 11px;font-size:12px}}
      `;
      document.head.appendChild(style);
    }
    document.body.appendChild(dialog);
    renderStep();
    dialog.showModal();
    showing = true;
  }

  window.OrderReviewFlow = { start: startOrderReview };
}());
