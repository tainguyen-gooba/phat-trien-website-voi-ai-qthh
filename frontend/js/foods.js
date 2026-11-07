/**
 * FASTFOOD FOODS LIST PAGE CONTROLLER
 */

document.addEventListener('DOMContentLoaded', () => {
  UIComponents.renderHeader('foods');
  UIComponents.renderFooter();

  // Read URL search params
  const urlParams = new URLSearchParams(window.location.search);
  let selectedCategory = urlParams.get('category') || 'all';
  let searchTerm = urlParams.get('search') || '';
  let sortBy = 'default';
  let priceFilter = 'all';

  const container = document.getElementById('foods-grid-container');
  const searchInput = document.getElementById('food-search-input');
  const sortSelect = document.getElementById('food-sort-select');

  if (searchInput && searchTerm) {
    searchInput.value = searchTerm;
  }

  function renderList() {
    if (!container) return;

    let list = [...FASTFOOD_DATA.products];

    // Filter by search
    if (searchTerm) {
      list = list.filter(p => 
        p.name.toLowerCase().includes(searchTerm.toLowerCase()) || 
        p.restaurantName.toLowerCase().includes(searchTerm.toLowerCase()) ||
        p.description.toLowerCase().includes(searchTerm.toLowerCase())
      );
    }

    // Filter by category
    if (selectedCategory !== 'all') {
      list = list.filter(p => p.category === selectedCategory);
    }

    // Filter by price range
    if (priceFilter === 'under-50') {
      list = list.filter(p => p.price < 50000);
    } else if (priceFilter === '50-100') {
      list = list.filter(p => p.price >= 50000 && p.price <= 100000);
    } else if (priceFilter === 'over-100') {
      list = list.filter(p => p.price > 100000);
    }

    // Sort
    if (sortBy === 'price-asc') {
      list.sort((a, b) => a.price - b.price);
    } else if (sortBy === 'price-desc') {
      list.sort((a, b) => b.price - a.price);
    } else if (sortBy === 'rating') {
      list.sort((a, b) => b.rating - a.rating);
    }

    // Update result count
    const countEl = document.getElementById('food-results-count');
    if (countEl) countEl.innerText = `${list.length} món ăn`;

    if (list.length === 0) {
      container.innerHTML = `
        <div class="state-box" style="grid-column: 1 / -1;">
          <img src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=160&auto=format&fit=crop&q=80" alt="Not found" style="width:100px; height:100px; border-radius:50%; object-fit:cover; margin:0 auto 1rem; box-shadow:var(--shadow-md);">
          <h3 class="state-title">Không tìm thấy món ăn nào</h3>
          <p class="state-desc">Rất tiếc, không có món ăn nào phù hợp với tìm kiếm của bạn. Hãy thử thay đổi từ khóa hoặc bộ lọc.</p>
          <button class="btn btn-primary" onclick="window.location.href='./foods.html'">Xem Toàn Bộ Món Ăn</button>
        </div>
      `;
      return;
    }

    container.innerHTML = list.map(product => {
      const discountPercent = product.oldPrice 
        ? Math.round(((product.oldPrice - product.price) / product.oldPrice) * 100) 
        : 0;

      return `
        <div class="product-card">
          <div class="product-thumb-wrap">
            <img src="${product.image}" alt="${UIComponents.escapeHtml(product.name)}" class="product-thumb" loading="lazy" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=600&auto=format&fit=crop&q=80';">
            ${discountPercent > 0 ? `<span class="product-tag-discount">-${discountPercent}%</span>` : ''}
            <span class="product-tag-time">⏱️ ${product.time}</span>
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

              <button class="btn-add-cart" title="Thêm vào giỏ" onclick="UIComponents.openFoodModal('${product.id}')">
                +
              </button>
            </div>
          </div>
        </div>
      `;
    }).join('');
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

  // Category Radio Filter
  document.querySelectorAll('.food-cat-filter').forEach(radio => {
    if (radio.value === selectedCategory) radio.checked = true;
    radio.addEventListener('change', (e) => {
      selectedCategory = e.target.value;
      renderList();
    });
  });

  // Price Radio Filter
  document.querySelectorAll('.food-price-filter').forEach(radio => {
    radio.addEventListener('change', (e) => {
      priceFilter = e.target.value;
      renderList();
    });
  });

  renderList();
});
