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

    if (list.length === 0) {
      container.innerHTML = `
        <div class="state-box" style="grid-column: 1 / -1;">
          <img src="https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=160&auto=format&fit=crop&q=80" alt="Not found" style="width:100px; height:100px; border-radius:50%; object-fit:cover; margin:0 auto 1rem; box-shadow:var(--shadow-md);">
          <h3 class="state-title">Không tìm thấy quán ăn phù hợp</h3>
          <p class="state-desc">Hãy thử tìm kiếm với từ khóa khác hoặc xóa bộ lọc danh mục.</p>
          <button class="btn btn-primary" onclick="window.location.reload()">Đặt Lại Bộ Lọc</button>
        </div>
      `;
      return;
    }

    container.innerHTML = list.map(res => `
      <div class="restaurant-card" onclick="window.location.href='./foods.html?restaurant=${res.id}'" style="cursor:pointer;">
        <div class="restaurant-banner-wrap">
          <img src="${res.banner}" alt="${res.name}" class="restaurant-banner" loading="lazy">
          <span class="restaurant-badge-promo">${res.promo}</span>
        </div>

        <div class="restaurant-body">
          <h4 class="restaurant-name">${res.name}</h4>
          <div class="restaurant-tags">${res.tags.join(' • ')}</div>
          <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.75rem;">📍 ${res.address}</div>

          <div class="restaurant-footer">
            <span class="restaurant-rating-pill">★ ${res.rating} (${res.reviewCount}+)</span>
            <span>🛵 ${res.deliveryTime}</span>
            <span>📍 ${res.distance}</span>
          </div>
        </div>
      </div>
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

  renderList();
});

