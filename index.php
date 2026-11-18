<?php
/**
 * FASTFOOD - Trang chủ (Home Page)
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'FASTFOOD - Đặt Đồ Ăn Trực Tuyến Nhanh Chóng & Chuẩn Vị';
$activeNav = 'home';
$extraCss  = ['css/home.css'];

$db = getDB();

// 1. Lấy danh mục món ăn
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order ASC, id ASC LIMIT 8")->fetchAll();

// 2. Lấy món Flash Sale (có giảm giá)
$flashDeals = $db->query("SELECT p.*, r.name AS restaurant_name 
                          FROM products p 
                          LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                          WHERE p.is_available = 1 AND p.discount_price > 0 AND p.discount_price < p.base_price 
                          ORDER BY p.id DESC LIMIT 4")->fetchAll();

// 3. Lấy món Bán chạy nhất
$bestSellers = $db->query("SELECT p.*, r.name AS restaurant_name, c.name AS category_name 
                           FROM products p 
                           LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                           LEFT JOIN categories c ON p.category_id = c.id 
                           WHERE p.is_available = 1 
                           ORDER BY p.is_featured DESC, p.id ASC LIMIT 8")->fetchAll();

// 5. Lấy quán ăn nổi bật
$featuredRestaurants = $db->query("SELECT * FROM restaurants WHERE is_active = 1 ORDER BY rating_avg DESC, id ASC LIMIT 4")->fetchAll();

// 6. Lấy đánh giá khách hàng
$reviews = $db->query("SELECT r.*, u.full_name, u.avatar_url, res.name AS restaurant_name 
                       FROM reviews r 
                       LEFT JOIN users u ON r.user_id = u.id 
                       LEFT JOIN restaurants res ON r.restaurant_id = res.id 
                       ORDER BY r.id DESC LIMIT 3")->fetchAll();

// 6. Kiểm tra quà tặng thành viên mới
$showWelcomeVoucher = false;
if (!empty($_SESSION['welcome_discount_applied'])) {
    $showWelcomeVoucher = true;
    unset($_SESSION['welcome_discount_applied']);
}

require_once ROOT_PATH . '/includes/header.php';
?>

<main class="container">
  <?php if ($showWelcomeVoucher): ?>
    <div style="background: linear-gradient(135deg, #ea580c 0%, #f97316 100%); color: #fff; padding: 1.25rem 1.75rem; border-radius: 16px; margin: 1.5rem 0 2rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 25px rgba(234, 88, 12, 0.25); position: relative; flex-wrap: wrap; gap: 1rem;">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <span style="font-size: 2.5rem; flex-shrink: 0;">🎉</span>
        <div>
          <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0 0 0.25rem; color: #fff;">
            Chào mừng bạn mới! Nhận ngay Voucher Giảm 50%
          </h3>
          <p style="font-size: 0.92rem; margin: 0; opacity: 0.95;">
            Mã <strong>NEW50</strong> (Giảm 50% giá trị món ăn) đã được tự động áp dụng vào giỏ hàng của bạn. Hãy chọn món và thưởng thức ngay!
          </p>
        </div>
      </div>
      <a href="<?= getUrl('foods.php') ?>" class="btn btn-light" style="white-space: nowrap; font-weight: 700; color: #ea580c; background: #fff; border-radius: 9999px; padding: 0.6rem 1.4rem;">
        Đặt Món Ngay ➔
      </a>
    </div>
  <?php endif; ?>
  <!-- 1. HERO SLIDER & BANNER -->
  <section class="hero-section">
    <div class="hero-slider-container" aria-label="Món ăn nổi bật">
      <div class="hero-slider-track" id="hero-slider-track">
        <div class="hero-slide" onclick="window.location.href='<?= getUrl('foods.php?search=Burger') ?>'">
          <img src="https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=1600&auto=format&fit=crop&q=85" alt="Burger Bò Nướng Phô Mai" class="hero-slide-img">
          <div class="hero-slide-overlay">
            <span class="hero-slide-badge">🔥 ƯU ĐÃI GIẢM 50%</span>
            <h3 class="hero-slide-title">Burger Bò Nướng Phô Mai</h3>
            <p class="hero-slide-sub">Burger King Station • Nóng hổi giao 15 phút</p>
          </div>
        </div>
        <div class="hero-slide" onclick="window.location.href='<?= getUrl('foods.php?search=Pizza') ?>'">
          <img src="https://images.unsplash.com/photo-1513104890138-7c749659a591?w=1600&auto=format&fit=crop&q=85" alt="Pizza Hải Sản Pesto" class="hero-slide-img">
          <div class="hero-slide-overlay">
            <span class="hero-slide-badge">🍕 MUA 1 TẶNG 1 HÔM NAY</span>
            <h3 class="hero-slide-title">Pizza Hải Sản Pesto Ý</h3>
            <p class="hero-slide-sub">Pizza Hut Italia • Phô mai kéo sợi thơm lừng</p>
          </div>
        </div>
        <div class="hero-slide" onclick="window.location.href='<?= getUrl('foods.php?search=Cơm') ?>'">
          <img src="<?= getAssetUrl('assets/images/foods/com-tam-suon-bi-cha.jpg') ?>" alt="Cơm Tấm Sườn Bì Chả" class="hero-slide-img">
          <div class="hero-slide-overlay">
            <span class="hero-slide-badge">🍚 FREESHIP 0Đ ĐƠN TỪ 50K</span>
            <h3 class="hero-slide-title">Cơm Tấm Sườn Bì Chả</h3>
            <p class="hero-slide-sub">Cơm Tấm Sài Gòn Xưa • Chuẩn vị truyền thống</p>
          </div>
        </div>
        <div class="hero-slide" onclick="window.location.href='<?= getUrl('foods.php?search=Trà sữa') ?>'">
          <img src="https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=1600&auto=format&fit=crop&q=85" alt="Trà Sữa GongCha Hoàng Gia" class="hero-slide-img">
          <div class="hero-slide-overlay">
            <span class="hero-slide-badge">🧋 GIẢM 30K GIỜ VÀNG</span>
            <h3 class="hero-slide-title">Trà Sữa Trân Châu Hoàng Gia</h3>
            <p class="hero-slide-sub">GongCha Tea • Trân châu đường đen dẻo dai</p>
          </div>
        </div>
      </div>

      <button class="hero-slider-btn prev" id="slider-prev-btn" aria-label="Slide trước">‹</button>
      <button class="hero-slider-btn next" id="slider-next-btn" aria-label="Slide tiếp">›</button>
      <div class="hero-slider-dots" id="hero-slider-dots">
        <span class="dot active" data-index="0"></span>
        <span class="dot" data-index="1"></span>
        <span class="dot" data-index="2"></span>
        <span class="dot" data-index="3"></span>
      </div>
    </div>

    <div class="hero-grid">
      <div class="hero-copy">
        <span class="hero-badge-tag">🛵 GIAO HÀNG HỎA TỐC 20 PHÚT</span>
        <h1 class="hero-title">Thèm Món Gì, <br><span>FASTFOOD</span> Giao Đó!</h1>
        <p class="hero-desc">Khám phá hàng ngàn món ăn nóng hổi từ các thương hiệu hàng đầu với ưu đãi freeship ngập tràn mỗi ngày.</p>
        <div class="hero-tags">
          <a href="<?= getUrl('foods.php?search=Burger') ?>" class="hero-tags-pill">Burger Bò</a>
          <a href="<?= getUrl('foods.php?search=Cơm tấm') ?>" class="hero-tags-pill">Cơm Tấm</a>
          <a href="<?= getUrl('foods.php?search=Trà sữa') ?>" class="hero-tags-pill">Trà Sữa</a>
          <a href="<?= getUrl('foods.php?search=Gà rán') ?>" class="hero-tags-pill">Gà Rán</a>
          <a href="<?= getUrl('foods.php?search=Pizza') ?>" class="hero-tags-pill">Pizza Ý</a>
        </div>
      </div>
    </div>
  </section>

  <!-- 2. DANH MỤC MÓN ĂN -->
  <section class="category-section">
    <div class="section-title-wrap">
      <div>
        <h2 class="section-title">Danh Mục Món Ăn</h2>
        <p class="section-subtitle">Lựa chọn món ăn theo khẩu vị yêu thích của bạn</p>
      </div>
      <a href="<?= getUrl('foods.php') ?>" class="section-link">Xem tất cả ➔</a>
    </div>

    <div class="category-grid" id="category-grid-container">
      <?php foreach ($categories as $cat): ?>
        <?php
          $catUrl = getUrl('foods.php?category=' . urlencode($cat['slug'] ?: $cat['id']));
          $icon = trim($cat['icon_url'] ?? '');
          $isImgUrl = preg_match('/^(https?:\/\/|\/|\.\/)/i', $icon);
        ?>
        <a href="<?= $catUrl ?>" class="category-item">
          <div class="category-icon-circle">
            <?php if ($isImgUrl): ?>
              <img src="<?= escapeHtml($icon) ?>" alt="<?= escapeHtml($cat['name']) ?>" class="category-icon-img" loading="lazy" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=240&auto=format&fit=crop&q=80';">
            <?php else: ?>
              <span style="font-size: 2rem;"><?= !empty($icon) ? escapeHtml($icon) : '🍔' ?></span>
            <?php endif; ?>
          </div>
          <span class="category-label"><?= escapeHtml($cat['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- 3. FLASH DEALS / GIẢM GIÁ HÔM NAY -->
  <?php if (!empty($flashDeals)): ?>
  <section class="flash-deals-banner" id="flash-deals">
    <div class="flash-deals-header">
      <div>
        <span style="background:rgba(255,255,255,0.25); padding:0.25rem 0.65rem; border-radius:var(--radius-sm); font-size:0.75rem; font-weight:800; text-transform:uppercase;">
          ⚡ CHỚP MẮT LÀ HẾT
        </span>
        <h2 style="font-size:1.85rem; font-weight:800; margin-top:0.4rem;">Món Ăn Flash Sale</h2>
      </div>

      <div class="flash-timer-box">
        <span>Kết thúc trong:</span>
        <span class="timer-digit" id="timer-hours">02</span> :
        <span class="timer-digit" id="timer-minutes">45</span> :
        <span class="timer-digit" id="timer-seconds">30</span>
      </div>
    </div>

    <div class="grid flash-deals-products" id="flash-deals-container">
      <?php foreach ($flashDeals as $p): ?>
        <?= renderProductCard($p) ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- 4. MÓN ĂN BÁN CHẠY NHẤT -->
  <section id="best-sellers" style="margin-bottom: 4rem;">
    <div class="section-title-wrap">
      <div>
        <h2 class="section-title">Món Ăn Bán Chạy Nhất (Best Sellers)</h2>
        <p class="section-subtitle">Được khách hàng đặt nhiều nhất trong tuần qua</p>
      </div>
      <a href="<?= getUrl('foods.php?sort=popular') ?>" class="section-link">Xem thực đơn ➔</a>
    </div>

    <div class="grid grid-cols-4" id="best-sellers-container">
      <?php foreach ($bestSellers as $p): ?>
        <?= renderProductCard($p) ?>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- 6. NHÀ HÀNG NỔI BẬT -->
  <?php if (!empty($featuredRestaurants)): ?>
  <section style="margin-bottom: 4rem;">
    <div class="section-title-wrap">
      <div>
        <h2 class="section-title">Nhà Hàng & Quán Ăn Nổi Bật</h2>
        <p class="section-subtitle">Đối tác chính hãng, đảm bảo vệ sinh ATTP và chế biến chuẩn vị</p>
      </div>
      <a href="<?= getUrl('restaurants.php') ?>" class="section-link">Xem tất cả quán ➔</a>
    </div>

    <div class="grid grid-cols-2" id="featured-restaurants-container">
      <?php foreach ($featuredRestaurants as $r): 
        $bannerUrl = escapeHtml($r['banner_url'] ?: 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=800&auto=format&fit=crop&q=80');
      ?>
        <a href="<?= getUrl('foods.php?restaurant=' . $r['id']) ?>" class="restaurant-card">
          <div class="restaurant-banner-wrap">
            <img src="<?= $bannerUrl ?>" alt="<?= escapeHtml($r['name']) ?>" class="restaurant-banner" loading="lazy" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=800&auto=format&fit=crop&q=80';">
            <span class="restaurant-badge-promo">⚡ Giao 15-20p</span>
          </div>
          <div class="restaurant-body">
            <h3 class="restaurant-name"><?= escapeHtml($r['name']) ?></h3>
            <p class="restaurant-tags">📍 <?= escapeHtml($r['address']) ?></p>
            <div class="restaurant-footer">
              <span class="restaurant-rating-pill">⭐ <?= number_format((float)$r['rating_avg'], 1) ?> (<?= (int)$r['rating_count'] ?>+)</span>
              <span style="color:var(--primary-color); font-weight:700;">Xem menu quán ➔</span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- 7. ĐÁNH GIÁ CỦA KHÁCH HÀNG -->
  <?php if (!empty($reviews)): ?>
  <section style="margin-bottom: 4rem;">
    <div class="section-title-wrap">
      <div>
        <h2 class="section-title">Đánh Giá Từ Khách Hàng</h2>
        <p class="section-subtitle">Những trải nghiệm chân thực nhất từ thực khách đã tin dùng FASTFOOD</p>
      </div>
    </div>

    <div class="review-grid">
      <?php foreach ($reviews as $rev): 
        $avatar = $rev['avatar_url'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=160&auto=format&fit=crop&q=80';
        $rating = (int)($rev['rating'] ?? 5);
      ?>
        <div class="review-card">
          <div class="review-header">
            <img src="<?= escapeHtml($avatar) ?>" alt="<?= escapeHtml($rev['full_name'] ?: 'Khách hàng') ?>" class="review-avatar-img">
            <div>
              <h4 style="font-size:1rem; font-weight:700; margin:0 0 0.2rem;"><?= escapeHtml($rev['full_name'] ?: 'Khách hàng') ?></h4>
              <span style="font-size:0.75rem; color:var(--text-muted);"><?= escapeHtml($rev['restaurant_name'] ?: 'FASTFOOD Partner') ?></span>
            </div>
          </div>
          <div class="review-stars">
            <?= str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) ?>
          </div>
          <p class="review-text">“<?= escapeHtml($rev['comment'] ?: 'Món ăn rất ngon, nóng hổi và giao hàng siêu tốc!') ?>”</p>
          <div class="review-order-tag">
            <span>📅 <?= formatDateTimeVN($rev['created_at']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

</main>

<script>
// Thêm món vào giỏ hàng qua AJAX PHP
async function addToCart(productId) {
  try {
    const res = await fetch('<?= getUrl('api/cart.php') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'add', productId, qty: 1 })
    });
    const result = await res.json();
    if (result.success) {
      if (window.UIComponents?.showToast) {
        window.UIComponents.showToast('Đã thêm món vào giỏ hàng!', 'success');
      } else {
        alert('Đã thêm món vào giỏ hàng!');
      }
      const badge = document.getElementById('nav-cart-badge');
      if (badge) {
        badge.textContent = result.data.totalCount;
        badge.style.display = result.data.totalCount > 0 ? 'flex' : 'none';
      }
    } else {
      alert(result.message || 'Không thể thêm món.');
    }
  } catch (err) {
    console.error(err);
  }
}

function copyCoupon(code) {
  navigator.clipboard?.writeText(code).then(() => {
    if (window.UIComponents?.showToast) {
      window.UIComponents.showToast(`Đã sao chép mã: ${code}`, 'success');
    } else {
      alert(`Đã sao chép mã: ${code}`);
    }
  });
}

function toggleFavorite(productId, btn) {
  const isLiked = btn.classList.toggle('is-liked');
  btn.textContent = isLiked ? '♥' : '♡';
  if (window.UIComponents?.showToast) {
    window.UIComponents.showToast(isLiked ? 'Đã thêm vào danh sách yêu thích!' : 'Đã xóa khỏi danh sách yêu thích.', 'info');
  }
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

