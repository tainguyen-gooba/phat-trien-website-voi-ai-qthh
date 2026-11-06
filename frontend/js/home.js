/**
 * FASTFOOD HOME PAGE LOGIC
 */

document.addEventListener('DOMContentLoaded', () => {
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

});

// Đồng hồ Flash Deals
function startFlashDealsTimer() {
  let seconds = 2 * 3600 + 45 * 60 + 15; // 2h 45m 15s

  setInterval(() => {
    seconds--;
    if (seconds <= 0) seconds = 86400;

    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;

    const hEl = document.getElementById('timer-hours');
    const mEl = document.getElementById('timer-minutes');
    const sEl = document.getElementById('timer-seconds');

    if (hEl) hEl.innerText = String(hrs).padStart(2, '0');
    if (mEl) mEl.innerText = String(mins).padStart(2, '0');
    if (sEl) sEl.innerText = String(secs).padStart(2, '0');
  }, 1000);
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

  const deals = FASTFOOD_DATA.products.filter(p => p.isFlashDeal);
  container.innerHTML = deals.map(product => createProductCardHtml(product)).join('');
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
        <img src="${product.image}" alt="${UIComponents.escapeHtml(product.name)}" class="product-thumb" loading="lazy" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=600&auto=format&fit=crop&q=80';">
        ${discountPercent > 0 ? `<span class="product-tag-discount">-${discountPercent}%</span>` : ''}
        <span class="product-tag-time">⏱️ ${product.time}</span>
      </div>

      <div class="product-info">
        <div class="product-meta-row">
          <span class="product-category-name">${UIComponents.escapeHtml(product.restaurantName)}</span>
          <span class="product-rating">★ ${product.rating}</span>
        </div>

        <a href="./food-detail.html?id=${encodeURIComponent(product.id)}" class="product-title" title="${UIComponents.escapeHtml(product.name)}">
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
}

// Render Featured Restaurants
function renderFeaturedRestaurants() {
  const container = document.getElementById('featured-restaurants-container');
  if (!container) return;

  container.innerHTML = FASTFOOD_DATA.restaurants.map(res => `
    <div class="restaurant-card" onclick="window.location.href='./foods.html?restaurant=${res.id}'" style="cursor:pointer;">
      <div class="restaurant-banner-wrap">
        <img src="${res.banner}" alt="${res.name}" class="restaurant-banner" loading="lazy">
        <span class="restaurant-badge-promo">${res.promo}</span>
      </div>

      <div class="restaurant-body">
        <h4 class="restaurant-name">${res.name}</h4>
        <div class="restaurant-tags">${res.tags.join(' • ')}</div>

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
