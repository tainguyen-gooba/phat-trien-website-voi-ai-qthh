/**
 * FASTFOOD HOME PAGE LOGIC
 */

document.addEventListener('DOMContentLoaded', () => {
  // Đồng bộ trạng thái yêu thích từ localStorage trước khi render
  if (typeof window.loadFavoritesFromStorage === 'function') {
    window.loadFavoritesFromStorage();
  }

  // Render Header & Footer
  UIComponents.renderHeader('home');
  UIComponents.renderFooter();

  // 1. Khởi tạo đồng hồ đếm ngược Flash Deals (02:45:30)
  startFlashDealsTimer();

  // 1b. Khởi tạo Slider Banner tự động chuyển ngang
  initHeroSlider();

  // 2. Render danh mục món ăn
  renderCategories();

  // 3. Render Flash Deals
  renderFlashDeals();

  // 4. Render món ăn bán chạy
  renderBestSellers();

  // 5. Render nhà hàng nổi bật
  renderFeaturedRestaurants();

  // 6. Render mã giảm giá
  renderCoupons();

  // 7. Render đánh giá khách hàng
  renderReviews();

  // Favorite button event delegation (products & restaurants)
  document.body.addEventListener('click', (e) => {
    const favBtn = e.target.closest('.favorite-btn');
    if (!favBtn) return;

    e.preventDefault();
    e.stopPropagation();

    if (favBtn.dataset.productId) {
      const productId = favBtn.dataset.productId;
      const product = FASTFOOD_DATA.products.find(p => String(p.id) === String(productId));
      if (!product) return;
      product.isLiked = !product.isLiked;

      // Đồng bộ trạng thái trên tất cả các nút của cùng món ăn trên trang (ví dụ vừa ở Flash Deals vừa ở Best Sellers)
      document.querySelectorAll(`.favorite-btn[data-product-id="${productId}"]`).forEach(btn => {
        btn.classList.toggle('is-liked', product.isLiked);
        btn.textContent = product.isLiked ? '♥' : '♡';
        btn.setAttribute('title', product.isLiked ? 'Bỏ yêu thích' : 'Yêu thích món này');
        btn.setAttribute('aria-label', product.isLiked ? 'Đã yêu thích món này' : 'Yêu thích món này');
      });

      if (typeof window.saveFavoritesToStorage === 'function') {
        window.saveFavoritesToStorage();
      }
      UIComponents.showToast(product.isLiked ? 'Đã thêm món vào yêu thích!' : 'Đã bỏ yêu thích món!', 'info', 2500);
      UIComponents.updateFavoriteBadge();
      window.saveFavoritesToStorage?.();
    } else if (favBtn.dataset.resId) {
      const resId = favBtn.dataset.resId;
      const res = FASTFOOD_DATA.restaurants.find(r => String(r.id) === String(resId));
      if (!res) return;
      res.isLiked = !res.isLiked;

      // Đồng bộ trạng thái trên tất cả các nút của cùng quán ăn trên trang
      document.querySelectorAll(`.favorite-btn[data-res-id="${resId}"]`).forEach(btn => {
        btn.classList.toggle('is-liked', res.isLiked);
        btn.textContent = res.isLiked ? '♥' : '♡';
        btn.setAttribute('title', res.isLiked ? 'Bỏ yêu thích' : 'Yêu thích quán này');
        btn.setAttribute('aria-label', res.isLiked ? 'Đã yêu thích quán này' : 'Yêu thích quán này');
      });

      if (typeof window.saveFavoritesToStorage === 'function') {
        window.saveFavoritesToStorage();
      }
      UIComponents.showToast(res.isLiked ? 'Đã thêm quán vào yêu thích!' : 'Đã bỏ yêu thích quán!', 'info', 2500);
      UIComponents.updateFavoriteBadge();
      window.saveFavoritesToStorage?.();
    }
  });

  // Restaurant card click - show detail view on home page too
  const featuredRestaurantsContainer = document.getElementById('featured-restaurants-container');
  if (featuredRestaurantsContainer) {
    featuredRestaurantsContainer.addEventListener('click', (event) => {
      const favoriteBtn = event.target.closest('.favorite-btn');
      if (favoriteBtn) return;

      const card = event.target.closest('.restaurant-card[data-res-id]');
      if (card) {
        const resId = card.getAttribute('data-res-id');
        // Navigate to restaurants page with the restaurant highlighted
        window.location.href = `./restaurants.html?res=${resId}`;
      }
    });
  }

});

function startFlashDealsTimer() {
  let activeDealIds = window.getActiveFlashDeals().products.map((product) => product.id);
  const updateTimer = () => {
    const activeDeals = window.getActiveFlashDeals();
    const dealIds = activeDeals.products.map((product) => product.id);
    if (dealIds.join(',') !== activeDealIds.join(',')) {
      activeDealIds = dealIds;
      renderFlashDeals(activeDeals.products);
    }

    const seconds = Math.max(0, Math.ceil((activeDeals.endsAt - Date.now()) / 1000));
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    const hEl = document.getElementById('timer-hours');
    const mEl = document.getElementById('timer-minutes');
    const sEl = document.getElementById('timer-seconds');
    if (hEl) hEl.innerText = String(hrs).padStart(2, '0');
    if (mEl) mEl.innerText = String(mins).padStart(2, '0');
    if (sEl) sEl.innerText = String(secs).padStart(2, '0');
  };

  updateTimer();
  window.setInterval(updateTimer, 1000);
  window.addEventListener('storage', (event) => {
    if (event.key === 'fastfood-flash-deals-v1') updateTimer();
  });
}

// Render Categories
function renderCategories() {
  const container = document.getElementById('category-grid-container');
  if (!container) return;

  const cats = FASTFOOD_DATA.categories;
  container.innerHTML = cats.map(cat => `
    <div class="category-item ${cat.id === 'all' ? 'active' : ''}" onclick="window.location.href='./foods.html?category=${cat.id}'">
      <div class="category-icon-circle">
        <img src="${cat.image}" alt="${UIComponents.escapeHtml(cat.name)}" class="category-icon-img" loading="lazy" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=240&auto=format&fit=crop&q=80';">
      </div>
      <span class="category-label">${UIComponents.escapeHtml(cat.name)}</span>
    </div>
  `).join('');
}

// Render Flash Deals
function renderFlashDeals() {
  const container = document.getElementById('flash-deals-container');
  if (!container) return;

  const activeDeals = window.getActiveFlashDeals();
  container.innerHTML = activeDeals.products.map(product => createProductCardHtml(product)).join('');
}

// Render Best Sellers
function renderBestSellers() {
  const container = document.getElementById('best-sellers-container');
  if (!container) return;

  const items = FASTFOOD_DATA.products.filter(p => p.isBestSeller);
  container.innerHTML = items.map(product => createProductCardHtml(product)).join('');
}

// Helper sinh thẻ Product Card HTML
function createProductCardHtml(product) {
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

          <button class="favorite-btn ${product.isLiked ? 'is-liked' : ''}" data-product-id="${product.id}" title="${product.isLiked ? 'Bỏ yêu thích' : 'Yêu thích món này'}" aria-label="${product.isLiked ? 'Đã yêu thích món này' : 'Yêu thích món này'}">${product.isLiked ? '♥' : '♡'}</button>
          <button class="btn-add-cart" title="Thêm vào giỏ" onclick="UIComponents.openFoodModal('${product.id}')">+</button>
        </div>
      </div>
    </div>
  `;
}

// Render Featured Restaurants
function renderFeaturedRestaurants() {
  const container = document.getElementById('featured-restaurants-container');
  if (!container) return;

  container.innerHTML = FASTFOOD_DATA.restaurants.map(res => `
    <div class="restaurant-card" data-res-id="${res.id}" style="cursor:pointer;">
      <div class="restaurant-banner-wrap">
        <img src="${res.banner}" alt="${res.name}" class="restaurant-banner" loading="lazy">
        <span class="restaurant-badge-promo">${res.promo}</span>
        <button class="favorite-btn restaurant-favorite ${res.isLiked ? 'is-liked' : ''}" data-res-id="${res.id}" title="${res.isLiked ? 'Bỏ yêu thích' : 'Yêu thích quán này'}" aria-label="${res.isLiked ? 'Đã yêu thích quán này' : 'Yêu thích quán này'}">${res.isLiked ? '♥' : '♡'}</button>
      </div>

      <div class="restaurant-body">
        <h4 class="restaurant-name">${UIComponents.escapeHtml(res.name)}</h4>
        <div class="restaurant-tags">${res.tags.join(' • ')}</div>
        <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.75rem;">📍 ${UIComponents.escapeHtml(res.address)}</div>

        <div class="restaurant-footer">
          <span class="restaurant-rating-pill">★ ${res.rating} (${res.reviewCount}+)</span>
          <span>🛵 ${res.deliveryTime}</span>
          <span>📍 ${res.distance}</span>
        </div>
      </div>
    </div>
  `).join('');
}

// Render Coupons
function renderCoupons() {
  const container = document.getElementById('coupons-container');
  if (!container) return;

  container.innerHTML = FASTFOOD_DATA.coupons.map(c => `
    <div class="promo-card">
      <img src="${c.icon}" alt="${c.title}" style="width:56px; height:56px; border-radius:var(--radius-md); object-fit:cover; box-shadow:var(--shadow-sm); flex-shrink:0;">
      <div class="promo-info">
        <strong style="display:block; font-size:0.95rem; color:var(--text-main); margin-bottom:0.25rem;">${c.title}</strong>
        <div style="display:flex; align-items:center; gap:0.5rem;">
          <span class="promo-code-pill">${c.code}</span>
          <button class="btn btn-secondary btn-sm" onclick="copyCouponCode('${c.code}')">Sao chép</button>
        </div>
      </div>
    </div>
  `).join('');
}

function renderReviews() {
  const container = document.getElementById('reviews-container');
  if (!container) return;

  container.innerHTML = FASTFOOD_DATA.reviews.map(review => `
    <article class="review-card">
      <div class="review-header">
        <img src="${review.avatar}" alt="${review.author}" class="review-avatar-img" loading="lazy">
        <strong>${review.author}</strong>
      </div>
      <div class="review-stars" aria-label="${review.rating} trên 5 sao">
        ${'★'.repeat(Math.max(0, Math.min(5, Number(review.rating) || 0)))}
      </div>
      <p class="review-text">“${review.comment}”</p>
      <span class="review-order-tag">${review.orderInfo} · ${review.date}</span>
    </article>
  `).join('');
}

function copyCouponCode(code) {
  navigator.clipboard?.writeText(code);
  UIComponents.showToast(`Đã sao chép mã ưu đãi "${code}"!`, 'success');
}

// Khởi tạo Carousel Banner trượt ngang tự động
function initHeroSlider() {
  const track = document.getElementById('hero-slider-track');
  const slides = document.querySelectorAll('.hero-slide');
  const dots = document.querySelectorAll('.hero-slider-dots .dot');
  const prevBtn = document.getElementById('slider-prev-btn');
  const nextBtn = document.getElementById('slider-next-btn');
  const sliderContainer = document.querySelector('.hero-slider-container');

  if (!track || slides.length === 0) return;

  let currentIndex = 0;
  const totalSlides = slides.length;
  let autoSlideTimer = null;

  function updateSlide(index) {
    if (index < 0) {
      currentIndex = totalSlides - 1;
    } else if (index >= totalSlides) {
      currentIndex = 0;
    } else {
      currentIndex = index;
    }

    // Di chuyển thanh trượt theo chiều ngang mượt mà
    track.style.transform = `translateX(-${currentIndex * 100}%)`;

    // Cập nhật trạng thái active cho dấu chấm tròn
    dots.forEach((dot, i) => {
      dot.classList.toggle('active', i === currentIndex);
    });
  }

  function startAutoSlide() {
    stopAutoSlide();
    autoSlideTimer = setInterval(() => {
      updateSlide(currentIndex + 1);
    }, 3200); // Tự động trượt sang ảnh tiếp theo sau mỗi 3.2 giây
  }

  function stopAutoSlide() {
    if (autoSlideTimer) {
      clearInterval(autoSlideTimer);
      autoSlideTimer = null;
    }
  }

  // Nút mũi tên chuyển tới
  if (nextBtn) {
    nextBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      updateSlide(currentIndex + 1);
      startAutoSlide();
    });
  }

  // Nút mũi tên lùi lại
  if (prevBtn) {
    prevBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      updateSlide(currentIndex - 1);
      startAutoSlide();
    });
  }

  // Bấm vào từng chấm tròn để nhảy trực tiếp tới slide đó
  dots.forEach(dot => {
    dot.addEventListener('click', (e) => {
      e.stopPropagation();
      const idx = parseInt(dot.dataset.index) || 0;
      updateSlide(idx);
      startAutoSlide();
    });
  });

  // Tạm dừng chạy tự động khi rê chuột vào để người dùng tiện xem
  if (sliderContainer) {
    sliderContainer.addEventListener('mouseenter', stopAutoSlide);
    sliderContainer.addEventListener('mouseleave', startAutoSlide);
  }

  // Bắt đầu chu trình tự động trượt
  startAutoSlide();
}
