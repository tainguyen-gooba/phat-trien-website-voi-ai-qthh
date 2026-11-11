/**
 * FASTFOOD FAVORITES PAGE CONTROLLER
 * Hiển thị các món ăn và quán ăn mà khách hàng đã yêu thích
 */

document.addEventListener('DOMContentLoaded', () => {
  // Đồng bộ trạng thái yêu thích từ localStorage trước khi render
  if (typeof window.loadFavoritesFromStorage === 'function') {
    window.loadFavoritesFromStorage();
  }

  UIComponents.renderHeader('favorites');
  UIComponents.renderFooter();

  const productsGrid = document.getElementById('favorite-products-grid');
  const restaurantsGrid = document.getElementById('favorite-restaurants-grid');
  const emptyState = document.getElementById('favorites-empty');
  const content = document.getElementById('favorites-content');
  const productsCount = document.getElementById('fav-products-count');
  const restaurantsCount = document.getElementById('fav-restaurants-count');

  // Tabs
  const tabs = document.querySelectorAll('.fav-tab');
  const panels = document.querySelectorAll('.fav-tab-panel');

  function getLikedProducts() {
    return FASTFOOD_DATA.products.filter(p => p.isLiked);
  }

  function getLikedRestaurants() {
    return FASTFOOD_DATA.restaurants.filter(r => r.isLiked);
  }

  function renderFavorites() {
    const likedProducts = getLikedProducts();
    const likedRestaurants = getLikedRestaurants();
    const totalLiked = likedProducts.length + likedRestaurants.length;

    const badge = document.getElementById('nav-favorite-badge');
    if (badge) {
      badge.textContent = totalLiked;
      badge.style.display = totalLiked > 0 ? 'flex' : 'none';
    }

    const favBtn = document.querySelector('.favorite-nav-btn');
    if (favBtn) {
      if (totalLiked > 0) {
        favBtn.classList.add('is-liked');
        favBtn.textContent = '♥';
      } else {
        favBtn.classList.remove('is-liked');
        favBtn.textContent = '♡';
      }
    }

    if (totalLiked === 0) {
      emptyState.style.display = 'flex';
      content.style.display = 'none';
      return;
    } else {
      emptyState.style.display = 'none';
      content.style.display = 'block';
    }

    productsCount.textContent = `${likedProducts.length} món ăn`;
    if (likedProducts.length > 0) {
      productsGrid.innerHTML = likedProducts.map(product => {
        const discountPercent = product.oldPrice
          ? Math.round(((product.oldPrice - product.price) / product.oldPrice) * 100)
          : 0;
        return `
          <div class="product-card">
            <div class="product-thumb-wrap">
              <img src="${product.image}" alt="${UIComponents.escapeHtml(product.name)}" class="product-thumb" loading="lazy" onerror="this.onerror=null; this.style.visibility='hidden';">
              ${discountPercent > 0 ? `<span class="product-tag-discount">-${discountPercent}%</span>` : ''}
              <span class="product-tag-time">⏱️ ${product.time}</span>
              <button class="favorite-btn is-liked" data-product-id="${product.id}" title="Bỏ yêu thích" aria-label="Đã yêu thích món này">♥</button>
            </div>
            <div class="product-info">
              <div class="product-meta-row">
                ${window.getFastfoodRestaurantUrlForProduct(product)
                  ? `<a class="product-category-name" href="${UIComponents.escapeHtml(window.getFastfoodRestaurantUrl({ id: product.restaurantId, url: window.getFastfoodRestaurantUrlForProduct(product) }))}" ${window.isFastfoodExternalUrl(window.getFastfoodRestaurantUrlForProduct(product)) ? 'target="_blank" rel="noopener noreferrer"' : ''}>${UIComponents.escapeHtml(product.restaurantName)}</a>`
                  : `<span class="product-category-name">${UIComponents.escapeHtml(product.restaurantName)}</span>`}
                <span class="product-rating">★ ${product.rating}</span>
              </div>
              <div style="font-size:0.78rem; color:var(--text-muted); margin-bottom:0.55rem;">📍 ${UIComponents.escapeHtml(window.getFastfoodRestaurantAddressForProduct(product))}</div>
              <a href="${UIComponents.escapeHtml(window.getFastfoodProductUrl(product))}" ${window.isFastfoodExternalUrl(product.url) ? 'target="_blank" rel="noopener noreferrer"' : ''} class="product-title" title="${UIComponents.escapeHtml(product.name)}">
                ${UIComponents.escapeHtml(product.name)}
              </a>
              <div class="product-bottom-row">
                <div class="product-price-box">
                  <span class="product-price">${UIComponents.formatVND(product.price)}</span>
                  ${product.oldPrice ? `<span class="product-old-price">${UIComponents.formatVND(product.oldPrice)}</span>` : ''}
                </div>
                <button class="favorite-btn is-liked" data-product-id="${product.id}" title="Bỏ yêu thích" aria-label="Đã yêu thích món này">♥</button>
                <button class="btn-add-cart" title="Thêm vào giỏ" onclick="UIComponents.openFoodModal('${product.id}')">+</button>
              </div>
            </div>
          </div>
        `;
      }).join('');
    } else {
      productsGrid.innerHTML = `<div class="state-box" style="grid-column: 1 / -1; text-align:center; padding:2rem;"><p>Không có món ăn yêu thích nào.</p></div>`;
    }

    restaurantsCount.textContent = `${likedRestaurants.length} quán ăn`;
    if (likedRestaurants.length > 0) {
      restaurantsGrid.innerHTML = likedRestaurants.map(res => `
        <div class="restaurant-card" data-res-id="${res.id}" style="cursor:pointer;">
          <div class="restaurant-banner-wrap">
            <img src="${UIComponents.escapeHtml(res.banner)}" alt="${UIComponents.escapeHtml(res.name)}" class="restaurant-banner" loading="lazy">
            ${res.promo ? `<span class="restaurant-badge-promo">${UIComponents.escapeHtml(res.promo)}</span>` : ''}
            <button class="favorite-btn restaurant-favorite is-liked" data-res-id="${res.id}" title="Bỏ yêu thích quán này" aria-label="Đã yêu thích quán này">♥</button>
          </div>
          <div class="restaurant-body">
            <h4 class="restaurant-name">${UIComponents.escapeHtml(res.name)}</h4>
            <div class="restaurant-tags">${(res.tags || []).map((tag) => UIComponents.escapeHtml(tag)).join(' <span aria-hidden="true">•</span> ')}</div>
            <p class="restaurant-address">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
              <span>${UIComponents.escapeHtml(res.address)}</span>
            </p>
            <div class="restaurant-footer">
              <span class="restaurant-rating-pill">★ ${res.rating} (${res.reviewCount}+)</span>
              <span class="restaurant-delivery">🛵 ${UIComponents.escapeHtml(res.deliveryTime)}</span>
              <span class="restaurant-distance">${UIComponents.escapeHtml(res.distance)}</span>
            </div>
          </div>
        </div>
      `).join('');
    } else {
      restaurantsGrid.innerHTML = `<div style="grid-column: 1 / -1; text-align:center; padding:2rem;"><p>Không có quán ăn yêu thích nào.</p></div>`;
    }
  }

  // Tab switching
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const tabId = tab.getAttribute('data-tab');
      tabs.forEach(t => t.classList.remove('active'));
      panels.forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(`fav-${tabId}`).classList.add('active');
    });
  });

  // Remove favorite from product card
  productsGrid.addEventListener('click', (e) => {
    const favBtn = e.target.closest('.favorite-btn[data-product-id]');
    if (!favBtn) return;

    e.preventDefault();
    e.stopPropagation();

    const productId = favBtn.getAttribute('data-product-id');
    const product = FASTFOOD_DATA.products.find(p => String(p.id) === String(productId));
    if (!product) return;

    product.isLiked = false;
    if (typeof window.saveFavoritesToStorage === 'function') {
      window.saveFavoritesToStorage();
    }
    renderFavorites();
    UIComponents.showToast('Đã bỏ yêu thích món ăn!', 'info', 2500);
    window.saveFavoritesToStorage?.();
  });

  // Remove favorite from restaurant card
  restaurantsGrid.addEventListener('click', (e) => {
    const favBtn = e.target.closest('.favorite-btn[data-res-id]');
    if (!favBtn) return;

    e.preventDefault();
    e.stopPropagation();

    const resId = favBtn.getAttribute('data-res-id');
    const res = FASTFOOD_DATA.restaurants.find(r => String(r.id) === String(resId));
    if (!res) return;

    res.isLiked = false;
    if (typeof window.saveFavoritesToStorage === 'function') {
      window.saveFavoritesToStorage();
    }
    renderFavorites();
    UIComponents.showToast('Đã bỏ yêu thích quán ăn!', 'info', 2500);
    window.saveFavoritesToStorage?.();
  });

  renderFavorites();
});