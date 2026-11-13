/**
 * FASTFOOD CHECKOUT PAGE CONTROLLER
 */

document.addEventListener('DOMContentLoaded', () => {
  const user = window.StorageManager ? window.StorageManager.getUser() : null;
  if (user && user.isLoggedIn && user.role === 'admin') {
    window.UIComponents?.showToast('Tài khoản Quản trị viên (Admin) chỉ dùng để quản lý hệ thống, không thể thanh toán hoặc mua hàng!', 'warning', 4000);
    window.location.replace('./admin/dashboard.html');
    return;
  }

  if (!UIComponents.requireLogin()) return;

  UIComponents.renderHeader('checkout');
  UIComponents.renderFooter();

  // Sổ thông tin của user đã đăng nhập vào form thông tin nhận hàng
  const customerNameInput = document.getElementById('customer-name');
  const customerPhoneInput = document.getElementById('customer-phone');
  const customerAddressInput = document.getElementById('customer-address');

  function populateUserInfo(currentUser) {
    if (!currentUser) return;
    if (customerNameInput && (currentUser.fullName || currentUser.name)) {
      customerNameInput.value = currentUser.fullName || currentUser.name;
    }
    if (customerPhoneInput && currentUser.phone) {
      customerPhoneInput.value = currentUser.phone;
    }
    if (customerAddressInput && currentUser.address) {
      customerAddressInput.value = currentUser.address;
    }
  }

  if (user) {
    populateUserInfo(user);
  }

  // Đồng thời kiểm tra dữ liệu tài khoản mới nhất qua API nếu có token
  const authToken = sessionStorage.getItem('fastfood_auth_token');
  const apiUrl = window.location.port === '5000'
    ? `${window.location.origin}/api/v1`
    : 'http://localhost:5000/api/v1';
  if (authToken) {
    fetch(`${apiUrl}/auth/me`, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${authToken}`,
      },
    })
      .then((res) => res.json())
      .then((resData) => {
        if (resData?.success && resData?.data?.user) {
          const freshUser = { ...(user || {}), ...resData.data.user, isLoggedIn: true };
          StorageManager.setUser(freshUser);
          populateUserInfo(freshUser);
        }
      })
      .catch(() => {});
  }

  const cart = StorageManager.getCart();
  if (cart.length === 0) {
    window.location.href = './cart.html';
    return;
  }

  const appliedCoupon = StorageManager.get(StorageManager.KEYS.COUPON, null);
  const discountAmount = appliedCoupon ? appliedCoupon.discount : 0;
  const summary = CartController.getBillSummary(discountAmount);
  const token = sessionStorage.getItem('fastfood_auth_token');
  const cancellationNotice = document.getElementById('checkout-cancellation-notice');
  const shippingPolicyPromise = CartController.getShippingPolicy()
    .then((policy) => {
      const billSummary = CartController.applyCancellationSurcharge(summary, policy.cancellationCount);
      if (cancellationNotice && policy.cancellationCount > 0) {
        cancellationNotice.hidden = false;
        cancellationNotice.textContent = `Bạn đã hủy ${policy.cancellationCount} đơn. Phí giao hàng hiện tăng ${policy.increasePercent}% cho các đơn tiếp theo.`;
      }
      if (shipEl) shipEl.innerText = billSummary.shippingFee === 0 ? 'Miễn phí' : UIComponents.formatVND(billSummary.shippingFee);
      if (totalEl) totalEl.innerText = UIComponents.formatVND(billSummary.total);
      return billSummary;
    })
    .catch((error) => {
      UIComponents.showToast(error.message || 'Không thể tải chính sách phí giao hàng.', 'error');
      return null;
    });

  // Render Mini Order Items List
  const itemsContainer = document.getElementById('checkout-items-summary');
  if (itemsContainer) {
    itemsContainer.innerHTML = cart.map(item => `
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; font-size:0.9rem;">
        <div style="display:flex; align-items:center; gap:0.6rem;">
          <span style="font-weight:700; color:var(--primary-color);">${item.qty}x</span>
          <span style="color:var(--text-main); font-weight:500;">${UIComponents.escapeHtml(item.name)}</span>
        </div>
        <span style="font-weight:700;">${UIComponents.formatVND(item.price * item.qty)}</span>
      </div>
    `).join('');
  }

  // Update Bill Numbers
  const subtotalEl = document.getElementById('checkout-subtotal');
  const shipEl = document.getElementById('checkout-shipping');
  const discountEl = document.getElementById('checkout-discount');
  const totalEl = document.getElementById('checkout-total');

  if (subtotalEl) subtotalEl.innerText = UIComponents.formatVND(summary.subtotal);
  if (shipEl) shipEl.innerText = summary.shippingFee === 0 ? 'Miễn phí' : UIComponents.formatVND(summary.shippingFee);
  if (discountEl) discountEl.innerText = `-${UIComponents.formatVND(summary.discount)}`;
  if (totalEl) totalEl.innerText = UIComponents.formatVND(summary.total);

  // Payment Method selection
  let selectedPayment = 'cod';
  document.querySelectorAll('.payment-method-card').forEach(card => {
    card.addEventListener('click', () => {
      document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('active'));
      card.classList.add('active');
      const radio = card.querySelector('input[type="radio"]');
      if (radio) {
        radio.checked = true;
        selectedPayment = radio.value;
      }
    });
    card.querySelector('input[type="radio"]')?.addEventListener('change', (event) => {
      document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('active'));
      event.currentTarget.closest('.payment-method-card')?.classList.add('active');
      selectedPayment = event.currentTarget.value;
    });
  });

  // Submit Order Form
  const checkoutForm = document.getElementById('checkout-form');
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const name = document.getElementById('customer-name')?.value.trim();
      const phone = document.getElementById('customer-phone')?.value.trim();
      const address = document.getElementById('customer-address')?.value.trim();
      const notes = document.getElementById('customer-notes')?.value.trim();

      const customerDetails = UIComponents.validateCustomerDetails({ fullName: name, phone, address });
      if (!customerDetails.valid) {
        UIComponents.showToast(Object.values(customerDetails.errors).filter(Boolean).join(' '), 'warning');
        return;
      }

      const currentBill = await shippingPolicyPromise;
      if (!currentBill) {
        UIComponents.showToast('Chưa thể xác nhận phí giao hàng. Vui lòng tải lại trang trước khi đặt hàng.', 'error');
        return;
      }

      const orderCode = UIComponents.createOrderCode();

      const newOrder = {
        id: orderCode,
        createdAt: new Date().toISOString(),
        restaurantName: cart[0]?.restaurantName || 'FASTFOOD Partner',
        recipient: {
          name: customerDetails.fullName,
          phone: customerDetails.phone,
          address: customerDetails.address,
          notes,
        },
        items: cart.map((item) => {
          const selectedToppings = Array.isArray(item.selectedToppings) ? item.selectedToppings : [];
          const toppingTotal = selectedToppings.reduce((sum, topping) => sum + (Number(topping.price) || 0), 0);
          return {
            productId: String(item.productId || ''),
            name: item.name,
            qty: item.qty,
            basePrice: Number.isSafeInteger(item.basePrice) ? item.basePrice : item.price - toppingTotal,
            price: item.price,
            selectedToppings,
          };
        }),
        couponCode: appliedCoupon?.code || '',
        paymentMethod: selectedPayment === 'vietqr' ? 'BANKING' : selectedPayment.toUpperCase(),
        subtotal: currentBill.subtotal,
        shippingFee: currentBill.shippingFee,
        discount: currentBill.discount,
        total: currentBill.total,
        status: selectedPayment === 'cod' ? 'pending' : 'awaiting_payment',
        statusText: selectedPayment === 'cod' ? 'Chờ xác nhận' : 'Chờ thanh toán',
        statusStep: 2, // 1: Đặt đơn, 2: Quán nhận, 3: Đang làm, 4: Đang giao, 5: Đã giao
        estimatedTime: '20 - 25 phút',
        shipper: {
          name: 'Nguyễn Văn Giao Hàng',
          phone: '0909 123 456',
          vehicle: 'Honda Wave • 59-P1 888.88',
        },
      };

      const submitButton = checkoutForm.querySelector('[type="submit"]');
      if (submitButton) submitButton.disabled = true;
      try {
        const response = await fetch(`${apiUrl}/realtime/orders`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify({ order: newOrder }),
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.message || 'Không thể lưu đơn hàng.');
        }
        Object.assign(newOrder, result.data);
      } catch (error) {
        UIComponents.showToast(error.message || 'Không thể kết nối máy chủ để đặt hàng.', 'error');
        if (submitButton) submitButton.disabled = false;
        return;
      }

      if (user) {
        const updatedUser = {
          ...user,
          fullName: name,
          phone,
          address,
        };
        StorageManager.setUser(updatedUser);
        if (token) {
          fetch(`${apiUrl}/auth/me`, {
            method: 'PATCH',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json',
              Authorization: `Bearer ${token}`,
            },
            body: JSON.stringify({ fullName: name, phone, address }),
          }).catch(() => {});
        }
      }

      StorageManager.addOrder(newOrder);
      CartController.clearCart();
      StorageManager.remove(StorageManager.KEYS.COUPON);

      if (selectedPayment !== 'cod') {
        const gateway = selectedPayment === 'vietqr' ? 'vietqr' : selectedPayment;
        try {
          const paymentResponse = await fetch(`${apiUrl}/payments/${gateway}/initiate`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json',
              Authorization: `Bearer ${token}`,
            },
            body: JSON.stringify({ orderCode }),
          });
          const paymentResult = await paymentResponse.json();
          if (!paymentResponse.ok || !paymentResult.success || !paymentResult.data) {
            throw new Error(paymentResult.message || 'Không thể khởi tạo thanh toán.');
          }
          if (selectedPayment === 'momo' && paymentResult.data.paymentUrl) {
            window.location.assign(paymentResult.data.paymentUrl);
          } else {
            window.location.assign(`./payment.html?orderCode=${encodeURIComponent(orderCode)}`);
          }
        } catch (error) {
          UIComponents.showToast(`Đơn ${orderCode} đã lưu. ${error.message}`, 'error', 5000);
          window.setTimeout(() => {
            window.location.assign(`./payment.html?orderCode=${encodeURIComponent(orderCode)}`);
          }, 1200);
        }
        return;
      }

      UIComponents.showToast('Đặt hàng thành công! Đang chuyển đến trang theo dõi...', 'success');

      setTimeout(() => {
        window.location.href = `./order-tracking.html?id=${orderCode}`;
      }, 1000);
    });
  }
});
