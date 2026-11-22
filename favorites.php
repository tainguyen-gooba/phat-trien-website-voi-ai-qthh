<?php
/**
 * FASTFOOD - Món ăn yêu thích (Favorites)
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Món Ăn Yêu Thích - FASTFOOD';
$activeNav = 'favorites';
$extraCss  = ['css/pages.css'];

$db = getDB();
// Lấy fallback gợi ý nếu người dùng chưa lưu món nào
$fallbackProducts = $db->query("SELECT p.*, c.name AS category_name, r.name AS restaurant_name 
                                FROM products p 
                                LEFT JOIN categories c ON p.category_id = c.id 
                                LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                                WHERE p.is_available = 1 
                                ORDER BY p.is_featured DESC, p.id DESC LIMIT 8")->fetchAll();

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= getUrl('index.php') ?>">Trang chủ</a> <span>/</span> <span>Món ăn yêu thích</span>
    </div>
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div>
        <h1 class="page-title">Món Ăn Yêu Thích Của Bạn</h1>
        <p class="restaurants-page-intro" style="color:var(--text-muted); margin-top:0.35rem;">
          Danh sách các món ăn hấp dẫn bạn đã lưu để nhanh chóng đặt lại bất kỳ lúc nào.
        </p>
      </div>
      <div id="favorites-count-badge" style="background:var(--primary-light); color:var(--primary-color); border:1px solid rgba(242,138,36,0.3); padding:0.4rem 1rem; border-radius:var(--radius-full); font-weight:700; font-size:0.9rem;">
        Đang tải danh sách...
      </div>
    </div>
  </div>
</div>

<main class="container" style="margin-top: 2rem; margin-bottom: 5rem;">
  <section class="favorites-section" aria-label="Danh sách món ăn yêu thích">
    
    <!-- Khung hiển thị khi chưa có món ăn yêu thích -->
    <div class="favorites-empty-box" id="favorites-empty" style="display:none; text-align:center; padding: 4.5rem 1.5rem; background:#FFFFFF; border:1px dashed var(--border-color); border-radius:var(--radius-xl); box-shadow:var(--shadow-sm); margin-bottom:3rem;">
      <div style="width:80px; height:80px; border-radius:50%; background:var(--primary-light); display:inline-flex; align-items:center; justify-content:center; font-size:2.5rem; margin-bottom:1.25rem;">
        🤍
      </div>
      <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem;">
        Bạn Chưa Lưu Món Ăn Nào Vào Yêu Thích
      </h2>
      <p style="color: var(--text-muted); max-width: 480px; margin: 0 auto 1.75rem; line-height: 1.6; font-size:0.95rem;">
        Hãy khám phá thực đơn thơm ngon và bấm vào biểu tượng trái tim (♡) ở các món ăn để lưu lại vào đây nhé!
      </p>
      <a href="<?= getUrl('foods.php') ?>" class="btn-primary" style="display:inline-flex; align-items:center; gap:0.5rem; padding: 0.8rem 2rem; border-radius: var(--radius-full); font-weight:700; text-decoration:none;">
        Khám Phá Thực Đơn Ngay ➔
      </a>
    </div>

    <!-- Lưới hiển thị các món ăn yêu thích -->
    <div class="grid grid-cols-4" id="favorite-products-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:1.25rem;">
      <!-- JavaScript sẽ tải các món ăn yêu thích từ localStorage và render vào đây -->
    </div>

    <!-- Phần gợi ý món ăn khi danh sách trống -->
    <div id="favorites-suggestions" style="display:none; margin-top:3.5rem;">
      <div class="section-title-wrap" style="margin-bottom:1.25rem;">
        <div>
          <h3 class="section-title" style="font-size:1.35rem;">Gợi Ý Món Ngon Nổi Bật</h3>
          <p class="section-subtitle">Có thể bạn sẽ thích những món ăn bán chạy nhất tại FASTFOOD</p>
        </div>
      </div>
      <div class="grid grid-cols-4" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:1.25rem;">
        <?php foreach ($fallbackProducts as $prod): ?>
          <?= renderProductCard($prod) ?>
        <?php endforeach; ?>
      </div>
    </div>

  </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  const grid = document.getElementById('favorite-products-grid');
  const emptyBox = document.getElementById('favorites-empty');
  const countBadge = document.getElementById('favorites-count-badge');
  const suggestions = document.getElementById('favorites-suggestions');

  async function loadFavorites() {
    const ids = window.FavoritesManager ? window.FavoritesManager.getIds() : [];

    if (!ids || ids.length === 0) {
      if (grid) grid.style.display = 'none';
      if (emptyBox) emptyBox.style.display = 'block';
      if (suggestions) suggestions.style.display = 'block';
      if (countBadge) countBadge.textContent = '0 món ăn yêu thích';
      return;
    }

    if (emptyBox) emptyBox.style.display = 'none';
    if (suggestions) suggestions.style.display = 'none';
    if (grid) grid.style.display = 'grid';
    if (countBadge) countBadge.textContent = `${ids.length} món ăn yêu thích`;

    try {
      const res = await fetch('<?= getUrl('api/favorites.php') ?>?ids=' + ids.join(','));
      const result = await res.json();

      if (result.success && result.data && result.data.products && result.data.products.length > 0) {
        renderProductList(result.data.products);
      } else {
        if (grid) grid.style.display = 'none';
        if (emptyBox) emptyBox.style.display = 'block';
        if (suggestions) suggestions.style.display = 'block';
        if (countBadge) countBadge.textContent = '0 món ăn yêu thích';
      }
    } catch (err) {
      console.error('Lỗi tải danh sách yêu thích:', err);
    }
  }

  function renderProductList(products) {
    if (!grid) return;
    grid.innerHTML = products.map((p) => {
      const hasDiscount = Number(p.discount_price) > 0 && Number(p.discount_price) < Number(p.base_price);
      const discountPercent = hasDiscount ? Math.round((1 - (p.discount_price / p.base_price)) * 100) : 0;
      const displayPrice = p.price_formatted || (Number(hasDiscount ? p.discount_price : p.base_price).toLocaleString('vi-VN') + 'đ');
      const oldPrice = p.original_price_formatted || (Number(p.base_price).toLocaleString('vi-VN') + 'đ');

      return `
        <div class="product-card" id="fav-card-${p.id}" style="transition:all 0.3s ease;">
          <div class="product-thumb-wrap">
            <a href="${p.detail_url}">
              <img src="${p.image_url_full || p.image_url}" alt="${p.name}" class="product-thumb" loading="lazy" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format&fit=crop&q=80';">
            </a>
            ${hasDiscount ? `<span class="product-badge badge-sale">-${discountPercent}%</span>` : ''}
            <div class="product-prep-time">⏱ 15 - 20 phút</div>
          </div>
          <div class="product-info">
            <div class="product-restaurant">${p.restaurant_name || 'FASTFOOD Partner'}</div>
            <h3 class="product-title">
              <a href="${p.detail_url}">${p.name}</a>
            </h3>
            <div class="product-meta-row">
              <span class="product-rating">★ ${Number(p.rating_avg || 4.9).toFixed(1)}</span>
              <span class="product-category">${p.category_name || 'Món ngon'}</span>
            </div>
            <div class="product-bottom-row">
              <div class="product-price-box">
                <span class="product-price">${displayPrice}</span>
                ${hasDiscount ? `<span class="product-old-price">${oldPrice}</span>` : ''}
              </div>
              <div class="product-actions-group">
                <button type="button" class="favorite-btn is-liked" data-id="${p.id}" onclick="removeFavoriteCard(${p.id})" title="Bỏ yêu thích món này">♥</button>
                <button type="button" class="btn-add-cart" onclick="openFoodModal(${p.id})" title="Tùy chọn món & Topping">＋</button>
              </div>
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

  window.removeFavoriteCard = function(productId) {
    if (window.FavoritesManager) {
      window.FavoritesManager.toggle(productId);
    }
    const card = document.getElementById(`fav-card-${productId}`);
    if (card) {
      card.style.opacity = '0';
      card.style.transform = 'scale(0.85)';
      setTimeout(() => {
        card.remove();
        const remaining = grid.querySelectorAll('.product-card').length;
        if (countBadge) countBadge.textContent = `${remaining} món ăn yêu thích`;
        if (remaining === 0) {
          grid.style.display = 'none';
          emptyBox.style.display = 'block';
          suggestions.style.display = 'block';
        }
      }, 300);
    }
  };

  // Khởi chạy nạp danh sách
  loadFavorites();
});
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
