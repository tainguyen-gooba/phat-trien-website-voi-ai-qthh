/**
 * FASTFOOD RESTAURANTS PAGE CONTROLLER
 */

document.addEventListener('DOMContentLoaded', () => {
  UIComponents.renderHeader('restaurants');
  UIComponents.renderFooter();

  let selectedCategory = 'all';
  let searchTerm = '';
  let sortBy = 'popular';

  const container = document.getElementById('restaurants-grid-container');
  const searchInput = document.getElementById('restaurant-search-input');
  const sortSelect = document.getElementById('restaurant-sort-select');
  const resultsCount = document.getElementById('restaurant-results-count');

  function renderList() {
    if (!container) return;

    let list = [...FASTFOOD_DATA.restaurants];

    // Filter by search
    if (searchTerm) {
      list = list.filter(r => r.name.toLowerCase().includes(searchTerm.toLowerCase()) || r.tags.some(t => t.toLowerCase().includes(searchTerm.toLowerCase())));
    }

    // Filter by category
    if (selectedCategory !== 'all') {
      list = list.filter(r => r.category === selectedCategory);
    }

    // Sort
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
      <a class="restaurant-card" href="${UIComponents.escapeHtml(window.getFastfoodRestaurantUrl(res))}" ${window.isFastfoodExternalUrl(res.url) ? 'target="_blank" rel="noopener noreferrer"' : ''}>
        <div class="restaurant-banner-wrap">
          <img src="${UIComponents.escapeHtml(res.banner)}" alt="${UIComponents.escapeHtml(res.name)}" class="restaurant-banner" loading="lazy">
          ${res.promo ? `<span class="restaurant-badge-promo">${UIComponents.escapeHtml(res.promo)}</span>` : ''}
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
      </a>
    `).join('');
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
});
