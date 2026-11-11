/**
 * FASTFOOD RESTAURANTS PAGE CONTROLLER
 */

document.addEventListener('DOMContentLoaded', () => {
  // Đồng bộ trạng thái yêu thích từ localStorage trước khi render
  if (typeof window.loadFavoritesFromStorage === 'function') {
    window.loadFavoritesFromStorage();
  }

  UIComponents.renderHeader('restaurants');
  UIComponents.renderFooter();

  let selectedCategory = 'all';
  let searchTerm = '';
  let sortBy = 'popular';
  let currentRestaurantId = null;

  // Auto-open restaurant detail if navigated with ?res=XXX parameter
  const urlParams = new URLSearchParams(window.location.search);
  const autoOpenResId = urlParams.get('res');
  if (autoOpenResId) {
    currentRestaurantId = autoOpenResId;
  }

  const listView = document.getElementById('restaurant-list-view');
  const detailView = document.getElementById('restaurant-detail-view');
  const backBtn = document.getElementById('back-to-list-btn');
  const container = document.getElementById('restaurants-grid-container');
  const searchInput = document.getElementById('restaurant-search-input');
  const sortSelect = document.getElementById('restaurant-sort-select');
  const resultsCount = document.getElementById('restaurant-results-count');

  const detailName = document.getElementById('detail-restaurant-name');
  const detailAddress = document.getElementById('detail-restaurant-address');
  const detailRating = document.getElementById('detail-restaurant-rating');
  const detailReviewCount = document.getElementById('detail-restaurant-review-count');
  const detailDelivery = document.getElementById('detail-restaurant-delivery');
  const detailTags = document.getElementById('detail-restaurant-tags');
  const detailFavorite = document.getElementById('detail-restaurant-favorite');
  const detailProductsGrid = document.getElementById('restaurant-detail-products-grid');

  function renderList() {
    if (!container) return;

    let list = [...FASTFOOD_DATA.restaurants];

    if (searchTerm) {
      list = list.filter(r => r.name.toLowerCase().includes(searchTerm.toLowerCase()) || r.tags.some(t => t.toLowerCase().includes(searchTerm.toLowerCase())));
    }

    if (selectedCategory !== 'all') {
      list = list.filter(r => r.category === selectedCategory);
    }

    if (sortBy === 'rating') {
      list.sort((a, b) => b.rating - a.rating);
    } else if (sortBy === 'reviews') {
      list.sort((a, b) => b.reviewCount - a.reviewCount);
    }

    if (resultsCount) {
      resultsCount.textContent = `Tìm thấy ${list.length} quán ăn`;
    }

    if (list.length === 0) {
      container.innerHTML = `
        <div class="restaurant-empty-state">
          <span class="restaurant-empty-icon" aria-hidden="true">⌕</span>
          <h3>Không tìm thấy quán ăn phù hợp</h3>
          <p>Thử đổi từ khóa tìm kiếm hoặc chọn danh mục khác.</p>
          <button class="btn btn-primary" type="button" data-reset-filters>Đặt lại bộ lọc</button>
        </div>
      `;
      return;
    }

    container.innerHTML = list.map(res => `
      <div class="restaurant-card" data-res-id="${res.id}" style="cursor:pointer;">
        <div class="restaurant-banner-wrap">
          <img src="${UIComponents.escapeHtml(res.banner)}" alt="${UIComponents.escapeHtml(res.name)}" class="restaurant-banner" loading="lazy">
          ${res.promo ? `<span class="restaurant-badge-promo">${UIComponents.escapeHtml(res.promo)}</span>` : ''}
          <button class="favorite-btn restaurant-favorite ${res.isLiked ? 'is-liked' : ''}" data-res-id="${res.id}" title="${res.isLiked ? 'Bỏ yêu thích' : 'Yêu thích quán này'}" aria-label="${res.isLiked ? 'Đã yêu thích quán này' : 'Yêu thích quán này'}">${res.isLiked ? '♥' : '♡'}</button>
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
  }

  function renderRestaurantDetail(resId) {
    const res = FASTFOOD_DATA.restaurants.find(r => r.id === resId);
    if (!res) return;

    currentRestaurantId = resId;

    detailName.textContent = res.name;
    detailAddress.textContent = res.address;
    detailRating.textContent = res.rating;
    detailReviewCount.textContent = res.reviewCount;
    detailDelivery.textContent = res.deliveryTime;
    detailFavorite.className = `favorite-btn ${res.isLiked ? 'is-liked' : ''}`;
    detailFavorite.textContent = res.isLiked ? '♥' : '♡';
    detailFavorite.setAttribute('title', res.isLiked ? 'Bỏ yêu thích quán này' : 'Yêu thích quán này');
    detailFavorite.setAttribute('aria-label', res.isLiked ? 'Đã yêu thích quán này' : 'Yêu thích quán này');

    detailTags.innerHTML = (res.tags || []).map(tag => `<span class="restaurant-detail-tag">${UIComponents.escapeHtml(tag)}</span>`).join('');

    const products = FASTFOOD_DATA.products.filter(p => p.restaurantId === resId);
    detailProductsGrid.innerHTML = products.map(product => {
      const discountPercent = product.oldPrice
        ? Math.round(((product.oldPrice - product.price) / product.oldPrice) * 100)
        : 0;
      return `
        <div class="product-card">
          <div class="product-thumb-wrap">
            <img src="${product.image}" alt="${UIComponents.escapeHtml(product.name)}" class="product-thumb" loading="lazy" onerror="this.onerror=null; this.style.visibility='hidden';">
            ${UIComponents.renderImageCredit(product)}
            ${discountPercent > 0 ? `<span class="product-tag-discount">-${discountPercent}%</span>` : ''}
            <span class="product-tag-time">⏱️ ${product.time}</span>
            <button class="favorite-btn ${product.isLiked ? 'is-liked' : ''}" data-product-id="${product.id}" title="${product.isLiked ? 'Bỏ yêu thích' : 'Yêu thích món này'}" aria-label="${product.isLiked ? 'Đã yêu thích món này' : 'Yêu thích món này'}">${product.isLiked ? '♥' : '♡'}</button>
          </div>
          <div class="product-info">
            <div class="product-meta-row">
              <span class="product-rating">★ ${product.rating}</span>
            </div>
            <a href="${UIComponents.escapeHtml(window.getFastfoodProductUrl(product))}" ${window.isFastfoodExternalUrl(product.url) ? 'target="_blank" rel="noopener noreferrer"' : ''} class="product-title" title="${UIComponents.escapeHtml(product.name)}">
              ${UIComponents.escapeHtml(product.name)}
            </a>
            <div class="product-bottom-row">
              <div class="product-price-box">
                <span class="product-price">${UIComponents.formatVND(product.price)}</span>
                ${product.oldPrice ? `<span class="product-old-price">${UIComponents.formatVND(product.oldPrice)}</span>` : ''}
              </div>
              <button class="favorite-btn product-card-favorite ${product.isLiked ? 'is-liked' : ''}" data-product-id="${product.id}" title="${product.isLiked ? 'Bỏ yêu thích' : 'Yêu thích món này'}" aria-label="${product.isLiked ? 'Đã yêu thích món này' : 'Yêu thích món này'}">${product.isLiked ? '♥' : '♡'}</button>
              <button class="btn-add-cart" title="Thêm vào giỏ" onclick="UIComponents.openFoodModal('${product.id}')">+</button>
            </div>
          </div>
        </div>
      `;
    }).join('');

    if (listView) listView.style.display = 'none';
    if (detailView) detailView.classList.add('active');
  }

  function showListView() {
    currentRestaurantId = null;
    if (listView) listView.style.display = 'block';
    if (detailView) detailView.classList.remove('active');
  }

  // Event handlers
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      searchTerm = e.target.value.trim();
      renderList();
    });
  }

  if (sortSelect) {
    sortSelect.addEventListener('change', (e) => {
      sortBy = e.target.value;
      renderList();
    });
  }

  // Category filter clicks
  document.querySelectorAll('.filter-category-radio').forEach(radio => {
    radio.addEventListener('change', (e) => {
      selectedCategory = e.target.value;
      renderList();
    });
  });

  // Back to list
  if (backBtn) {
    backBtn.addEventListener('click', () => {
      showListView();
    });
  }

  // Restaurant card click - show detail view
  container.addEventListener('click', (event) => {
    const favoriteBtn = event.target.closest('.favorite-btn');
    if (favoriteBtn) return;

    const card = event.target.closest('.restaurant-card[data-res-id]');
    if (card) {
      const resId = card.getAttribute('data-res-id');
      renderRestaurantDetail(resId);
    }
  });

  // Favorite button click for restaurants
  container.addEventListener('click', (event) => {
    const favoriteBtn = event.target.closest('.favorite-btn[data-res-id]');
    if (!favoriteBtn) return;

    event.preventDefault();
    event.stopPropagation();

    const resId = favoriteBtn.getAttribute('data-res-id');
    const res = FASTFOOD_DATA.restaurants.find(r => r.id === resId);
    if (!res) return;

    res.isLiked = !res.isLiked;
    favoriteBtn.classList.toggle('is-liked', res.isLiked);
    favoriteBtn.textContent = res.isLiked ? '♥' : '♡';
    favoriteBtn.setAttribute('title', res.isLiked ? 'Bỏ yêu thích' : 'Yêu thích quán này');
    favoriteBtn.setAttribute('aria-label', res.isLiked ? 'Đã yêu thích quán này' : 'Yêu thích quán này');

    if (currentRestaurantId === resId && detailFavorite) {
      detailFavorite.classList.toggle('is-liked', res.isLiked);
      detailFavorite.textContent = res.isLiked ? '♥' : '♡';
      detailFavorite.setAttribute('title', res.isLiked ? 'Bỏ yêu thích quán này' : 'Yêu thích quán này');
      detailFavorite.setAttribute('aria-label', res.isLiked ? 'Đã yêu thích quán này' : 'Yêu thích quán này');
    }

    if (typeof window.saveFavoritesToStorage === 'function') {
      window.saveFavoritesToStorage();
    }
    UIComponents.showToast(res.isLiked ? 'Đã thêm vào yêu thích!' : 'Đã bỏ yêu thích!', 'info', 2500);
    UIComponents.updateFavoriteBadge();
    window.saveFavoritesToStorage?.();
  });

  // Detail view favorite button click
  if (detailFavorite) {
    detailFavorite.addEventListener('click', (event) => {
      event.stopPropagation();
      if (!currentRestaurantId) return;

      const res = FASTFOOD_DATA.restaurants.find(r => r.id === currentRestaurantId);
      if (!res) return;

      res.isLiked = !res.isLiked;
      detailFavorite.classList.toggle('is-liked', res.isLiked);
      detailFavorite.textContent = res.isLiked ? '♥' : '♡';
      detailFavorite.setAttribute('title', res.isLiked ? 'Bỏ yêu thích quán này' : 'Yêu thích quán này');
      detailFavorite.setAttribute('aria-label', res.isLiked ? 'Đã yêu thích quán này' : 'Yêu thích quán này');

      const cardFavorite = document.querySelector(`.restaurant-favorite[data-res-id="${currentRestaurantId}"]`);
      if (cardFavorite) {
        cardFavorite.classList.toggle('is-liked', res.isLiked);
        cardFavorite.textContent = res.isLiked ? '♥' : '♡';
      }

      if (typeof window.saveFavoritesToStorage === 'function') {
        window.saveFavoritesToStorage();
      }
      UIComponents.showToast(res.isLiked ? 'Đã thêm vào yêu thích!' : 'Đã bỏ yêu thích!', 'info', 2500);
      UIComponents.updateFavoriteBadge();
      window.saveFavoritesToStorage?.();
    });
  }

  // Product favorite button click in detail view
  detailProductsGrid.addEventListener('click', (event) => {
    const favoriteBtn = event.target.closest('.favorite-btn[data-product-id]');
    if (!favoriteBtn) return;

    event.preventDefault();
    event.stopPropagation();

    const productId = favoriteBtn.getAttribute('data-product-id');
    const product = FASTFOOD_DATA.products.find(p => p.id === productId);
    if (!product) return;

    product.isLiked = !product.isLiked;
    favoriteBtn.classList.toggle('is-liked', product.isLiked);
    favoriteBtn.textContent = product.isLiked ? '♥' : '♡';
    favoriteBtn.setAttribute('title', product.isLiked ? 'Bỏ yêu thích' : 'Yêu thích món này');
    favoriteBtn.setAttribute('aria-label', product.isLiked ? 'Đã yêu thích món này' : 'Yêu thích món này');

    if (typeof window.saveFavoritesToStorage === 'function') {
      window.saveFavoritesToStorage();
    }
    UIComponents.showToast(product.isLiked ? 'Đã thêm món vào yêu thích!' : 'Đã bỏ yêu thích món!', 'info', 2500);
    UIComponents.updateFavoriteBadge();
    window.saveFavoritesToStorage?.();
  });

  container?.addEventListener('click', (event) => {
    if (!event.target.closest('[data-reset-filters]')) return;
    searchTerm = '';
    selectedCategory = 'all';
    sortBy = 'popular';
    if (searchInput) searchInput.value = '';
    if (sortSelect) sortSelect.value = 'popular';
    const allCategories = document.querySelector('.filter-category-radio[value="all"]');
    if (allCategories) allCategories.checked = true;
    renderList();
  });

  renderList();

  // Auto-open restaurant detail if navigated from home page
  if (autoOpenResId) {
    setTimeout(() => renderRestaurantDetail(autoOpenResId), 100);
  }
});
