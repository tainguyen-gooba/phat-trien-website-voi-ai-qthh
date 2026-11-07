/**
 * FASTFOOD FOOD DETAIL PAGE CONTROLLER
 */

document.addEventListener('DOMContentLoaded', () => {
  UIComponents.renderHeader('foods');
  UIComponents.renderFooter();

  const urlParams = new URLSearchParams(window.location.search);
  const productId = urlParams.get('id') || 'prod-1';

  const product = FASTFOOD_DATA.products.find(p => p.id === productId) || FASTFOOD_DATA.products[0];
  const restaurant = window.getFastfoodRestaurantForProduct(product);
  const restaurantAddress = window.getFastfoodRestaurantAddressForProduct(product);

  let currentQty = 1;
  const selectedToppings = [];

  // Render Product Information
  const container = document.getElementById('food-detail-container');
  if (container) {
    container.innerHTML = `
      <div class="food-detail-grid">
        <!-- Ảnh món ăn -->
        <div class="food-gallery-main">
          <img src="${product.image}" alt="${UIComponents.escapeHtml(product.name)}" class="food-gallery-img">
        </div>

        <!-- Chi tiết thông tin -->
        <div>
          <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
            ${window.getFastfoodRestaurantUrlForProduct(product)
              ? `<a class="badge badge-secondary" href="${UIComponents.escapeHtml(window.getFastfoodRestaurantUrl({ id: product.restaurantId, url: window.getFastfoodRestaurantUrlForProduct(product) }))}" ${window.isFastfoodExternalUrl(window.getFastfoodRestaurantUrlForProduct(product)) ? 'target="_blank" rel="noopener noreferrer"' : ''}>${UIComponents.escapeHtml(product.restaurantName)}</a>`
              : `<span class="badge badge-secondary">${UIComponents.escapeHtml(product.restaurantName)}</span>`}
            <span style="color:var(--accent-gold); font-weight:700; font-size:0.9rem;">★ ${product.rating} (${product.reviews} đánh giá)</span>
            <span style="color:var(--text-muted); font-size:0.85rem;">• ⏱️ ${product.time}</span>
          </div>
          <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:0.65rem;">📍 ${UIComponents.escapeHtml(restaurantAddress)}${restaurant ? '' : ' (khu vực giao hàng)'}</p>

          <h1 style="font-size:2rem; font-weight:800; color:var(--text-main); line-height:1.25;">${UIComponents.escapeHtml(product.name)}</h1>
          
          <div class="detail-price-box">
            <span id="detail-total-price" class="detail-current-price">${UIComponents.formatVND(product.price)}</span>
            ${product.oldPrice ? `<span class="detail-old-price">${UIComponents.formatVND(product.oldPrice)}</span>` : ''}
          </div>

          <p style="color:var(--text-secondary); line-height:1.7; font-size:0.95rem; margin-bottom:1.5rem;">
            ${UIComponents.escapeHtml(product.description)}
          </p>

          <!-- Toppings selection -->
          ${product.toppings && product.toppings.length ? `
            <div class="topping-section">
              <h4 style="font-size:0.95rem; font-weight:700; color:var(--text-main); margin-bottom:0.75rem;">
                Tùy chọn thêm (Toppings):
              </h4>
              ${product.toppings.map((t, i) => `
                <div class="topping-item">
                  <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer;">
                    <input type="checkbox" class="detail-topping-cb" data-name="${UIComponents.escapeHtml(t.name)}" data-price="${t.price}">
                    <span style="font-size:0.9rem; font-weight:500;">${UIComponents.escapeHtml(t.name)}</span>
                  </label>
                  <span style="font-weight:700; color:var(--primary-color); font-size:0.9rem;">+${UIComponents.formatVND(t.price)}</span>
                </div>
              `).join('')}
            </div>
          ` : ''}

          <!-- Quantity and Action Buttons -->
          <div style="display:flex; align-items:center; gap:1.25rem; margin-top:2rem; flex-wrap:wrap;">
            <div class="quantity-control">
              <button class="qty-btn" id="btn-qty-minus">-</button>
              <input type="text" id="detail-qty-input" class="qty-input" value="1" readonly>
              <button class="qty-btn" id="btn-qty-plus">+</button>
            </div>

            <button class="btn btn-outline btn-lg" id="btn-add-cart-detail">
              🛒 Thêm Vào Giỏ Hàng
            </button>

            <button class="btn btn-primary btn-lg" id="btn-buy-now">
              ⚡ Mua Ngay
            </button>
          </div>
        </div>
      </div>
    `;

    // Recalculate price on topping check
    function updateTotalPrice() {
      const toppingTotal = selectedToppings.reduce((sum, t) => sum + t.price, 0);
      const unitPrice = product.price + toppingTotal;
      const total = unitPrice * currentQty;

      const priceEl = document.getElementById('detail-total-price');
      if (priceEl) priceEl.innerText = UIComponents.formatVND(total);
    }

    // Topping checkboxes
    document.querySelectorAll('.detail-topping-cb').forEach(cb => {
      cb.addEventListener('change', (e) => {
        const name = e.target.dataset.name;
        const price = parseInt(e.target.dataset.price) || 0;

        if (e.target.checked) {
          selectedToppings.push({ name, price });
        } else {
          const idx = selectedToppings.findIndex(t => t.name === name);
          if (idx > -1) selectedToppings.splice(idx, 1);
        }
        updateTotalPrice();
      });
    });

    // Quantity buttons
    const minusBtn = document.getElementById('btn-qty-minus');
    const plusBtn = document.getElementById('btn-qty-plus');
    const qtyInput = document.getElementById('detail-qty-input');

    if (minusBtn) {
      minusBtn.addEventListener('click', () => {
        if (currentQty > 1) {
          currentQty--;
          qtyInput.value = currentQty;
          updateTotalPrice();
        }
      });
    }

    if (plusBtn) {
      plusBtn.addEventListener('click', () => {
        currentQty++;
        qtyInput.value = currentQty;
        updateTotalPrice();
      });
    }

    // Add to cart
    const addCartBtn = document.getElementById('btn-add-cart-detail');
    if (addCartBtn) {
      addCartBtn.addEventListener('click', () => {
        CartController.addItem(product, currentQty, selectedToppings);
      });
    }

    // Buy now
    const buyNowBtn = document.getElementById('btn-buy-now');
    if (buyNowBtn) {
      buyNowBtn.addEventListener('click', () => {
        CartController.addItem(product, currentQty, selectedToppings);
        window.location.href = './checkout.html';
      });
    }
  }

  // Render related products
  const relatedContainer = document.getElementById('related-foods-container');
  if (relatedContainer) {
    const related = FASTFOOD_DATA.products.filter(p => p.id !== product.id).slice(0, 4);
    relatedContainer.innerHTML = related.map(p => `
      <div class="product-card">
        <div class="product-thumb-wrap">
          <img src="${p.image}" alt="${UIComponents.escapeHtml(p.name)}" class="product-thumb" loading="lazy">
          <span class="product-tag-time">⏱️ ${p.time}</span>
        </div>
        <div class="product-info">
          <a href="./food-detail.html?id=${encodeURIComponent(p.id)}" class="product-title">${UIComponents.escapeHtml(p.name)}</a>
          <div class="product-bottom-row">
            <span class="product-price">${UIComponents.formatVND(p.price)}</span>
            <button class="btn-add-cart" onclick="UIComponents.openFoodModal('${p.id}')">+</button>
          </div>
        </div>
      </div>
    `).join('');
  }
});
