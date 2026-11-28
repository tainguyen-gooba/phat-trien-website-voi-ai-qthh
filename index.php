<?php
/**
 * FASTFOOD - Trang chủ (Home Page)
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'FASTFOOD - Đặt Đồ Ăn Trực Tuyến Nhanh Chóng & Chuẩn Vị';
$activeNav = 'home';
$extraCss  = ['css/home.css'];

$db = getDB();

// 1. Lấy cấu hình Best Seller & danh sách món (Bảng cam duy nhất, ít nhất 5 món, thời gian 1 tiếng)
$bsContent = $db->query("SELECT content FROM website_content WHERE collection_name = 'bestseller' LIMIT 1")->fetchColumn();
$bsConfig = $bsContent ? json_decode($bsContent, true) : null;
if (!$bsConfig) {
    $bsConfig = [
        'title'         => 'Món Ăn Bán Chạy Nhất (Best Sellers)',
        'subtitle'      => 'Được khách hàng đặt nhiều nhất trong tuần qua - Ưu đãi giờ vàng giới hạn!',
        'badge'         => '🔥 GIỜ VÀNG BEST SELLER',
        'timer_enabled' => 1,
        'end_time'      => date('Y-m-d H:i:s', strtotime('+1 hour')),
        'discount_text' => 'Ưu đãi có hạn',
        'product_ids'   => [1, 2, 3, 5, 6] // Ít nhất 5 món
    ];
} else {
    // Đảm bảo danh sách product_ids có ít nhất 5 món
    if (empty($bsConfig['product_ids']) || count($bsConfig['product_ids']) < 5) {
        $bsConfig['product_ids'] = array_unique(array_merge($bsConfig['product_ids'] ?? [], [1, 2, 3, 5, 6]));
    }
}

$bestSellers = [];
if (!empty($bsConfig['product_ids']) && is_array($bsConfig['product_ids'])) {
    $ids = array_filter(array_map('intval', $bsConfig['product_ids']), function($id) { return $id > 0; });
    if (!empty($ids)) {
        $inClause = implode(',', $ids);
        $bestSellers = $db->query("SELECT p.*, r.name AS restaurant_name, c.name AS category_name 
                                   FROM products p 
                                   LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                                   LEFT JOIN categories c ON p.category_id = c.id 
                                   WHERE p.id IN ($inClause) AND p.is_available = 1 
                                   ORDER BY FIELD(p.id, $inClause)")->fetchAll();
    }
}
if (count($bestSellers) < 5) {
    $neededBs = 5 - count($bestSellers);
    $existingBsIds = array_column($bestSellers, 'id');
    $placeholdersBs = !empty($existingBsIds) ? 'AND p.id NOT IN (' . implode(',', array_map('intval', $existingBsIds)) . ')' : '';
    $moreBs = $db->query("SELECT p.*, r.name AS restaurant_name, c.name AS category_name 
                          FROM products p 
                          LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.is_available = 1 $placeholdersBs 
                          ORDER BY p.is_featured DESC, p.id ASC LIMIT $neededBs")->fetchAll();
    $bestSellers = array_merge($bestSellers, $moreBs);
}
// Đảm bảo lấy ít nhất 5 món cho Best Sellers
$bestSellers = array_slice($bestSellers, 0, 5);

// 3. Lấy danh sách 24 món ăn trong Menu (Khoảng 20-25 món, đúng tên & đúng hình ảnh chuẩn vị)
$menuCats = ['Burger & Gà Rán', 'Cơm & Món Việt', 'Pizza & Mỳ Ý', 'Sushi & Món Nhật', 'Trà Sữa & Đồ Uống', 'Ăn Vặt & Bánh'];
$menuProducts = [];
foreach ($menuCats as $mCat) {
    $items = $db->query("SELECT p.*, r.name AS restaurant_name, c.name AS category_name, c.slug AS category_slug 
                         FROM products p 
                         LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         WHERE c.name = " . $db->quote($mCat) . " AND p.is_available = 1 
                         ORDER BY p.is_featured DESC, p.id ASC LIMIT 4")->fetchAll();
    $menuProducts = array_merge($menuProducts, $items);
}
if (count($menuProducts) < 24) {
    $haveIds = array_column($menuProducts, 'id');
    $notIn = !empty($haveIds) ? "AND p.id NOT IN (" . implode(',', array_map('intval', $haveIds)) . ")" : "";
    $remain = 24 - count($menuProducts);
    $more = $db->query("SELECT p.*, r.name AS restaurant_name, c.name AS category_name, c.slug AS category_slug 
                        FROM products p 
                        LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        WHERE p.is_available = 1 $notIn 
                        ORDER BY p.is_featured DESC, p.id ASC LIMIT $remain")->fetchAll();
    $menuProducts = array_merge($menuProducts, $more);
}

// 4. Lấy danh sách Banner động từ website_content (hoặc fallback mặc định)
$bannerData = null;
try {
    $bannerRow = $db->query("SELECT content FROM website_content WHERE collection_name = 'banners' LIMIT 1")->fetchColumn();
    if ($bannerRow) {
        $bannerData = json_decode($bannerRow, true);
    }
} catch (Exception $e) {}

$slideInterval = (int)($bannerData['interval'] ?? 1800);
if ($slideInterval < 500) $slideInterval = 500;
if ($slideInterval > 60000) $slideInterval = 60000;

$rawBanners = !empty($bannerData['banners']) && is_array($bannerData['banners']) ? $bannerData['banners'] : [];
$banners = [];
$nowTs = time();
foreach ($rawBanners as $b) {
    if (isset($b['active']) && !$b['active']) continue;
    $startTs = !empty($b['startAt']) ? strtotime($b['startAt']) : false;
    $endTs   = !empty($b['endAt'])   ? strtotime($b['endAt'])   : false;
    if ($startTs !== false && $startTs > $nowTs) continue;
    if ($endTs !== false && $endTs < $nowTs) continue;
    $banners[] = $b;
}
usort($banners, function($a, $b) {
    return ((int)($a['order'] ?? 0)) <=> ((int)($b['order'] ?? 0));
});

if (empty($banners)) {
    $banners = [
        [
            'id' => 'banner-1',
            'title' => 'Burger Bò Nướng Phô Mai',
            'subtitle' => 'Burger King Station • Nóng hổi giao 15 phút',
            'badge' => '🔥 ƯU ĐÃI GIẢM 50%',
            'image' => 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=1600&auto=format&fit=crop&q=85',
            'fallback_image' => 'assets/images/foods/bo-xao.jpg',
            'link' => 'foods.php?search=Burger'
        ],
        [
            'id' => 'banner-2',
            'title' => 'Pizza Hải Sản Pesto Ý',
            'subtitle' => 'Pizza Hut Italia • Phô mai kéo sợi thơm lừng',
            'badge' => '🍕 MUA 1 TẶNG 1 HÔM NAY',
            'image' => 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=1600&auto=format&fit=crop&q=85',
            'fallback_image' => 'assets/images/foods/calzone-pizza.jpg',
            'link' => 'foods.php?search=Pizza'
        ],
        [
            'id' => 'banner-3',
            'title' => 'Cơm Tấm Sườn Bì Chả',
            'subtitle' => 'Cơm Tấm Sài Gòn Xưa • Chuẩn vị truyền thống',
            'badge' => '🍚 FREESHIP 0Đ ĐƠN TỪ 50K',
            'image' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=1600&auto=format&fit=crop&q=85',
            'fallback_image' => 'assets/images/foods/com-tam-dac-biet.jpg',
            'link' => 'foods.php?search=Cơm'
        ],
        [
            'id' => 'banner-4',
            'title' => 'Trà Sữa Trân Châu Hoàng Gia',
            'subtitle' => 'GongCha Tea • Trân châu đường đen dẻo dai',
            'badge' => '🧋 GIẢM 30K GIỜ VÀNG',
            'image' => 'https://images.unsplash.com/photo-1541658016709-82535e94bc69?w=1600&auto=format&fit=crop&q=85',
            'fallback_image' => 'assets/images/foods/brown-sugar-boba.jpg',
            'link' => 'foods.php?search=Trà sữa'
        ]
    ];
}

// 5. Lấy đánh giá khách hàng & thống kê điểm sao thực tế
$reviews = $db->query("SELECT r.*, u.full_name, u.avatar_url 
                       FROM reviews r 
                       LEFT JOIN users u ON r.user_id = u.id 
                       ORDER BY r.id DESC LIMIT 3")->fetchAll();

$reviewStats = $db->query("SELECT COUNT(*) AS total_reviews, COALESCE(AVG(rating), 5) AS avg_rating FROM reviews")->fetch();
$totalReviewsCount = (int)($reviewStats['total_reviews'] ?? 0);
$avgReviewRating = $totalReviewsCount > 0 ? round((float)$reviewStats['avg_rating'], 1) : 5.0;
$avgReviewRatingFormatted = number_format($avgReviewRating, 1);
$reviewSatisfactionPercent = (int)round(($avgReviewRating / 5) * 100);

require_once ROOT_PATH . '/includes/header.php';
?>

<main class="container">
  <!-- 1. HERO SLIDER & BANNER (TỰ ĐỘNG CHUYỂN ẢNH SAU 1-2S) -->
  <section class="hero-section">
    <div class="hero-slider-container" aria-label="Món ăn nổi bật">
      <div class="hero-slider-track" id="hero-slider-track">
        <?php foreach ($banners as $idx => $b): ?>
          <?php
            $bLink = !empty($b['link']) ? (strpos($b['link'], 'http') === 0 ? $b['link'] : getUrl($b['link'])) : getUrl('foods.php');
            $bImg = $b['image'] ?? '';
            if ($bImg && !preg_match('#^(https?://|//)#i', $bImg)) {
                $bImg = getAssetUrl($bImg);
            }
            $bFallback = !empty($b['fallback_image']) ? getAssetUrl($b['fallback_image']) : 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=1600&auto=format&fit=crop&q=85';
          ?>
          <div class="hero-slide" onclick="window.location.href='<?= escapeHtml($bLink) ?>'">
            <img src="<?= escapeHtml($bImg) ?>" alt="<?= escapeHtml($b['title'] ?? 'FASTFOOD') ?>" class="hero-slide-img" loading="<?= $idx === 0 ? 'eager' : 'lazy' ?>" onerror="this.onerror=null; this.src='<?= escapeHtml($bFallback) ?>';">
            <div class="hero-slide-overlay">
              <?php if (!empty($b['badge'])): ?>
                <span class="hero-slide-badge"><?= escapeHtml($b['badge']) ?></span>
              <?php endif; ?>
              <h3 class="hero-slide-title"><?= escapeHtml($b['title'] ?? '') ?></h3>
              <?php if (!empty($b['subtitle'])): ?>
                <p class="hero-slide-sub"><?= escapeHtml($b['subtitle']) ?></p>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <button type="button" class="hero-slider-btn prev" id="slider-prev-btn" aria-label="Slide trước">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="15 18 9 12 15 6"></polyline>
      </svg>
    </button>
    <button type="button" class="hero-slider-btn next" id="slider-next-btn" aria-label="Slide tiếp">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="9 18 15 12 9 6"></polyline>
      </svg>
    </button>
    <div class="hero-slider-dots" id="hero-slider-dots">
      <?php foreach ($banners as $idx => $b): ?>
        <span class="dot <?= $idx === 0 ? 'active' : '' ?>" data-index="<?= $idx ?>" aria-label="Slide <?= $idx + 1 ?>"></span>
      <?php endforeach; ?>
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

  <!-- 2. MÓN ĂN BÁN CHẠY NHẤT (BẢNG CAM GIỜ VÀNG BEST SELLERS DUY NHẤT) -->
  <section id="best-sellers" class="bestseller-orange-board" style="
      background: linear-gradient(135deg, #f97316 0%, #ea580c 50%, #c2410c 100%);
      background-image: radial-gradient(circle at 10% 20%, rgba(255,255,255,0.15) 0%, transparent 40%), radial-gradient(circle at 90% 80%, rgba(255,255,255,0.18) 0%, transparent 45%), linear-gradient(135deg, #f97316 0%, #ea580c 50%, #c2410c 100%);
      border-radius: 24px;
      padding: 2.2rem 2rem 2.5rem;
      margin-bottom: 4rem;
      box-shadow: 0 16px 40px rgba(234, 88, 12, 0.28);
      position: relative;
      color: #ffffff;
  ">
    <div class="section-title-wrap" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem; margin-bottom:1.75rem;">
      <div>
        <div style="display:flex; align-items:center; gap:0.65rem; margin-bottom:0.4rem; flex-wrap:wrap;">
          <h2 class="section-title" id="bs-display-title" style="margin:0; color:#ffffff; font-size:1.85rem; font-weight:900; text-shadow:0 2px 8px rgba(0,0,0,0.15);">
            <?= escapeHtml($bsConfig['title']) ?>
          </h2>
          <?php if (!empty($bsConfig['badge'])): ?>
            <span id="bs-display-badge" style="background:rgba(255,255,255,0.25); border:1.5px solid rgba(255,255,255,0.45); color:#ffffff; font-size:0.75rem; font-weight:800; padding:0.35rem 0.75rem; border-radius:9999px; text-transform:uppercase; letter-spacing:0.5px; backdrop-filter:blur(6px); display:inline-flex; align-items:center; gap:4px; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
              <?= escapeHtml($bsConfig['badge']) ?>
            </span>
          <?php endif; ?>
        </div>
        <p class="section-subtitle" id="bs-display-subtitle" style="margin:0; color:rgba(255,255,255,0.92); font-size:0.92rem; font-weight:500;">
          <?= escapeHtml($bsConfig['subtitle']) ?>
        </p>
      </div>

      <div style="display:flex; align-items:center; gap:0.85rem; flex-wrap:wrap;">
        <?php if (!empty($bsConfig['timer_enabled'])): ?>
          <!-- Đồng hồ đếm ngược Giờ Vàng Best Seller trên bảng cam (1 tiếng) -->
          <div class="bestseller-timer-box" style="display:flex; align-items:center; gap:0.65rem; background:rgba(0,0,0,0.22); backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,0.3); padding:0.55rem 1.15rem; border-radius:14px; box-shadow:0 4px 15px rgba(0,0,0,0.12);">
            <span style="font-size:0.88rem; font-weight:700; color:#ffffff; display:flex; align-items:center; gap:5px;">
              <span>⏳</span> Ưu đãi kết thúc:
            </span>
            <div class="bestseller-countdown" data-end="<?= escapeHtml($bsConfig['end_time'] ?? '') ?>" style="display:flex; align-items:center; gap:5px; font-weight:900; color:#fff; font-size:0.95rem;">
              <span class="bs-timer-unit bs-hours" style="background:#ffffff; color:#ea580c; font-weight:900; padding:3px 8px; border-radius:7px; min-width:30px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,0.15);">01</span> :
              <span class="bs-timer-unit bs-minutes" style="background:#ffffff; color:#ea580c; font-weight:900; padding:3px 8px; border-radius:7px; min-width:30px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,0.15);">00</span> :
              <span class="bs-timer-unit bs-seconds" style="background:#ffffff; color:#ea580c; font-weight:900; padding:3px 8px; border-radius:7px; min-width:30px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,0.15);">00</span>
            </div>
          </div>
        <?php endif; ?>

        <a href="<?= getUrl('foods.php?sort=popular') ?>" class="section-link" style="background:#ffffff; color:#ea580c; font-weight:800; font-size:0.88rem; padding:0.6rem 1.35rem; border-radius:9999px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; box-shadow:0 4px 15px rgba(0,0,0,0.15); transition:all .2s;" onmouseover="this.style.background='#fff7ed'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#ffffff'; this.style.transform='none'">
          Xem thực đơn ➔
        </a>
      </div>
    </div>

    <div class="grid flash-deals-products" id="best-sellers-container" style="display:grid; grid-template-columns:repeat(5, minmax(0, 1fr)); gap:0.95rem;">
      <?php foreach ($bestSellers as $p): ?>
        <?= renderProductCard($p) ?>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- 4. THỰC ĐƠN MÓN ĂN TRONG MENU (24 MÓN ĐÚNG TÊN & ĐÚNG HÌNH ẢNH) -->
  <section id="menu-section" style="margin-bottom: 4rem;">
    <div class="section-title-wrap" style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:1.25rem; margin-bottom:1.75rem;">
      <div>
        <span style="display:inline-block; font-size:0.8rem; font-weight:800; color:var(--primary-color); text-transform:uppercase; letter-spacing:0.8px; margin-bottom:0.35rem; background:var(--primary-light); padding:0.25rem 0.75rem; border-radius:9999px;">
          🍽️ Thực đơn tuyển chọn
        </span>
        <h2 class="section-title" style="margin:0 0 0.4rem; font-size:1.85rem; font-weight:800;">
          Danh Sách Món Ăn Trong Menu
        </h2>
        <p class="section-subtitle" style="margin:0; color:var(--text-muted); font-size:0.95rem;">
          Khám phá <?= count($menuProducts) ?> món ăn thơm ngon chuẩn vị với hình ảnh sắc nét, đầy đủ thông tin để bạn dễ dàng lựa chọn
        </p>
      </div>
      <div>
        <a href="<?= getUrl('foods.php') ?>" class="section-link" style="display:inline-flex; align-items:center; gap:6px; font-weight:700; color:var(--primary-color); text-decoration:none; padding:0.55rem 1.25rem; border:1.5px solid var(--primary-color); border-radius:9999px; transition:all .2s;" onmouseover="this.style.background='var(--primary-color)'; this.style.color='#fff';" onmouseout="this.style.background='transparent'; this.style.color='var(--primary-color)';">
          Xem tất cả 260+ món ➔
        </a>
      </div>
    </div>

    <!-- Bộ lọc danh mục nhanh cho Menu -->
    <div class="menu-tabs-wrap">
      <button type="button" class="menu-tab-btn active" data-cat="all" onclick="filterMenuCat('all', this)">
        <span>Tất cả</span>
        <span style="font-size:0.75rem; opacity:0.85; background:rgba(0,0,0,0.1); padding:1px 6px; border-radius:9999px;"><?= count($menuProducts) ?></span>
      </button>
      <button type="button" class="menu-tab-btn" data-cat="Burger & Gà Rán" onclick="filterMenuCat('Burger & Gà Rán', this)">
        🍔 Burger & Gà Rán
      </button>
      <button type="button" class="menu-tab-btn" data-cat="Cơm & Món Việt" onclick="filterMenuCat('Cơm & Món Việt', this)">
        🍚 Cơm & Món Việt
      </button>
      <button type="button" class="menu-tab-btn" data-cat="Pizza & Mỳ Ý" onclick="filterMenuCat('Pizza & Mỳ Ý', this)">
        🍕 Pizza & Mỳ Ý
      </button>
      <button type="button" class="menu-tab-btn" data-cat="Sushi & Món Nhật" onclick="filterMenuCat('Sushi & Món Nhật', this)">
        🍣 Sushi & Món Nhật
      </button>
      <button type="button" class="menu-tab-btn" data-cat="Trà Sữa & Đồ Uống" onclick="filterMenuCat('Trà Sữa & Đồ Uống', this)">
        🧋 Trà Sữa & Đồ Uống
      </button>
      <button type="button" class="menu-tab-btn" data-cat="Ăn Vặt & Bánh" onclick="filterMenuCat('Ăn Vặt & Bánh', this)">
        🥐 Ăn Vặt & Bánh
      </button>
    </div>

    <!-- Lưới 24 món ăn với hình ảnh và thông tin chi tiết -->
    <div class="menu-products-grid" id="menu-products-grid">
      <?php foreach ($menuProducts as $p): ?>
        <?= renderProductCard($p) ?>
      <?php endforeach; ?>
    </div>

    <div style="text-align:center; margin-top:2.75rem;">
      <a href="<?= getUrl('foods.php') ?>" class="btn btn-primary" style="padding:0.85rem 2.25rem; font-size:1rem; font-weight:800; border-radius:9999px; box-shadow:0 8px 25px rgba(242,138,36,0.35); text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
        <span>🍽️ Xem toàn bộ thực đơn FASTFOOD (260+ món)</span>
        <span>➔</span>
      </a>
    </div>
  </section>

  <script>
    // =====================================================================
    // 1. ĐỒNG HỒ ĐẾM NGƯỢC GIỜ VÀNG BEST SELLER (1 TIẾNG - 01:00:00)
    // =====================================================================
    (function() {
      const STORAGE_KEY = 'fastfood_flash_sale_end_v1';
      const ONE_HOUR_MS = 60 * 60 * 1000; // 1 tiếng = 3600 giây

      let endTime = parseInt(localStorage.getItem(STORAGE_KEY), 10);
      const now = Date.now();

      // Nếu chưa có mốc hoặc đã hết thời gian, khởi tạo 1 tiếng mới
      if (!endTime || isNaN(endTime) || endTime <= now || (endTime - now > ONE_HOUR_MS + 60000)) {
        endTime = now + ONE_HOUR_MS;
        localStorage.setItem(STORAGE_KEY, String(endTime));
      }

      function updateRealtimeTimer() {
        const current = Date.now();
        let diff = Math.floor((endTime - current) / 1000);

        if (diff <= 0) {
          // Khi hết 1 tiếng, tự động reset thêm 1 tiếng để luôn có ưu đãi giờ vàng
          endTime = Date.now() + ONE_HOUR_MS;
          localStorage.setItem(STORAGE_KEY, String(endTime));
          diff = 3600;
        }

        const hours = Math.floor(diff / 3600);
        const minutes = Math.floor((diff % 3600) / 60);
        const seconds = diff % 60;

        const strH = String(hours).padStart(2, '0');
        const strM = String(minutes).padStart(2, '0');
        const strS = String(seconds).padStart(2, '0');

        // Cập nhật các ô số đếm ngược Best Seller
        document.querySelectorAll('.bestseller-countdown').forEach((box) => {
          const bH = box.querySelector('.bs-hours');
          const bM = box.querySelector('.bs-minutes');
          const bS = box.querySelector('.bs-seconds');
          if (bH) bH.textContent = strH;
          if (bM) bM.textContent = strM;
          if (bS) bS.textContent = strS;
        });
      }

      updateRealtimeTimer();
      setInterval(updateRealtimeTimer, 1000);
    })();

    // =====================================================================
    // 2. BỘ LỌC DANH MỤC THỰC ĐƠN MENU (CHUYỂN TAB TỨC THÌ)
    // =====================================================================
    function filterMenuCat(catName, btn) {
      document.querySelectorAll('.menu-tab-btn').forEach(b => {
        b.classList.remove('active');
      });
      if (btn) btn.classList.add('active');

      const cards = document.querySelectorAll('#menu-products-grid .product-card');
      cards.forEach(card => {
        const cat = card.getAttribute('data-category') || '';
        if (catName === 'all' || cat.toLowerCase().includes(catName.toLowerCase())) {
          card.style.display = 'flex';
        } else {
          card.style.display = 'none';
        }
      });
    }
  </script>

  <!-- 6. ĐÁNH GIÁ CỦA KHÁCH HÀNG -->
  <?php if (!empty($reviews)): ?>
  <section class="reviews-showcase" style="margin-bottom: 4.5rem;">
    <div class="section-title-wrap reviews-section-header">
      <div>
        <span class="reviews-eyebrow">ĐÁNH GIÁ THỰC TẾ</span>
        <h2 class="section-title">Đánh Giá Từ Khách Hàng</h2>
        <p class="section-subtitle">Những trải nghiệm chân thực nhất từ thực khách đã tin dùng FASTFOOD</p>
      </div>
      <div class="reviews-summary-pill" aria-label="Đánh giá trung bình <?= escapeHtml($avgReviewRatingFormatted) ?> trên 5 sao (<?= $reviewSatisfactionPercent ?>%)">
        <span class="reviews-summary-stars">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <?= escapeHtml($avgReviewRatingFormatted) ?>
        </span>
        <span class="reviews-summary-divider"></span>
        <span class="reviews-summary-label"><?= $reviewSatisfactionPercent ?>% Khách hàng hài lòng<?= $totalReviewsCount > 0 ? ' (' . $totalReviewsCount . ' đánh giá)' : '' ?></span>
      </div>
    </div>

    <div class="review-grid">
      <?php foreach ($reviews as $rev): 
        $avatar = getAvatarUrl($rev['avatar_url'] ?? '', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=160&auto=format&fit=crop&q=80');
        $rating = max(1, min(5, (int)($rev['rating'] ?? 5)));
        $comment = trim((string)($rev['comment'] ?? ''));
        if ($comment === '') {
            $comment = 'Món ăn rất ngon, nóng hổi và giao hàng siêu tốc!';
        }
      ?>
        <article class="review-card">
          <div class="review-quote-mark" aria-hidden="true">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="currentColor"><path d="M9.983 3v7.391c0 5.704-3.731 9.57-8.983 10.609l-.995-2.151c2.432-.917 3.995-3.638 3.995-5.849h-4v-10h9.983zm14.017 0v7.391c0 5.704-3.748 9.571-9 10.609l-.996-2.151c2.433-.917 3.996-3.638 3.996-5.849h-3.983v-10h9.983z"/></svg>
          </div>

          <div class="review-header">
            <div class="review-avatar-ring">
              <img src="<?= escapeHtml($avatar) ?>" alt="<?= escapeHtml($rev['full_name'] ?: 'Khách hàng') ?>" class="review-avatar-img">
            </div>
            <div class="review-author-meta">
              <h4 class="review-author-name"><?= escapeHtml($rev['full_name'] ?: 'Khách hàng') ?></h4>
              <span class="review-verified-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Khách hàng đã đặt món
              </span>
            </div>
          </div>

          <div class="review-rating-row">
            <div class="review-stars" aria-label="<?= $rating ?> trên 5 sao">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <svg class="review-star-icon <?= $i <= $rating ? 'is-filled' : 'is-empty' ?>" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                  <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
              <?php endfor; ?>
            </div>
            <span class="review-score-chip"><?= number_format($rating, 1) ?></span>
          </div>

          <p class="review-text"><?= escapeHtml($comment) ?></p>

          <div class="review-order-tag">
            <span class="review-date">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <?= formatDateTimeVN($rev['created_at']) ?>
            </span>
            <span class="review-status-pill">Đã xác thực</span>
          </div>
        </article>
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
  if (window.FavoritesManager) {
    window.FavoritesManager.toggle(productId, btn);
  } else {
    const isLiked = btn.classList.toggle('is-liked');
    btn.textContent = isLiked ? '♥' : '♡';
  }
}

// =====================================================================
// HERO BANNER SLIDER - TỰ ĐỘNG CHUYỂN ẢNH SAU 1-2 GIÂY (MẶC ĐỊNH 1.8s)
// =====================================================================
(function() {
  const track = document.getElementById('hero-slider-track');
  if (!track) return;

  const slides = track.querySelectorAll('.hero-slide');
  const dots = document.querySelectorAll('#hero-slider-dots .dot');
  const prevBtn = document.getElementById('slider-prev-btn');
  const nextBtn = document.getElementById('slider-next-btn');
  const totalSlides = slides.length;
  if (totalSlides <= 1) return;

  let currentIndex = 0;
  let slideTimer = null;
  const INTERVAL_MS = <?= $slideInterval ?>; // Tự chuyển ảnh sau 1-2s (1800ms)

  function updateSlider(index) {
    currentIndex = (index + totalSlides) % totalSlides;
    track.style.transform = `translateX(-${currentIndex * 100}%)`;
    dots.forEach((dot, idx) => {
      dot.classList.toggle('active', idx === currentIndex);
    });
  }

  function nextSlide() {
    updateSlider(currentIndex + 1);
  }

  function prevSlide() {
    updateSlider(currentIndex - 1);
  }

  function startAutoSlide() {
    stopAutoSlide();
    slideTimer = setInterval(nextSlide, INTERVAL_MS);
  }

  function stopAutoSlide() {
    if (slideTimer) {
      clearInterval(slideTimer);
      slideTimer = null;
    }
  }

  const heroSectionEl = track.closest('.hero-section');
  let controlsHideTimer = null;
  function revealSliderControls() {
    if (!heroSectionEl) return;
    heroSectionEl.classList.add('controls-visible');
    if (controlsHideTimer) clearTimeout(controlsHideTimer);
    controlsHideTimer = setTimeout(() => {
      heroSectionEl.classList.remove('controls-visible');
    }, 3500);
  }
  if (heroSectionEl) {
    heroSectionEl.addEventListener('pointerdown', revealSliderControls);
    heroSectionEl.addEventListener('touchstart', revealSliderControls, { passive: true });
    heroSectionEl.addEventListener('mouseleave', () => {
      heroSectionEl.classList.remove('controls-visible');
      if (controlsHideTimer) {
        clearTimeout(controlsHideTimer);
        controlsHideTimer = null;
      }
      prevBtn?.blur();
      nextBtn?.blur();
    });
  }

  // Sự kiện nút Tiếp / Lùi
  if (nextBtn) {
    nextBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      revealSliderControls();
      nextSlide();
      startAutoSlide();
    });
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      revealSliderControls();
      prevSlide();
      startAutoSlide();
    });
  }

  // Sự kiện bấm chọn Dot chỉ số
  dots.forEach((dot) => {
    dot.addEventListener('click', (e) => {
      e.stopPropagation();
      const idx = parseInt(dot.getAttribute('data-index'), 10) || 0;
      updateSlider(idx);
      startAutoSlide();
    });
  });

  // Tạm dừng khi di chuột vào banner để người dùng đọc thông tin ưu đãi
  const container = document.querySelector('.hero-slider-container');
  if (container) {
    container.addEventListener('mouseenter', stopAutoSlide);
    container.addEventListener('mouseleave', startAutoSlide);

    // Hỗ trợ vuốt tay chuyển slide trên điện thoại / tablet
    let touchStartX = 0;
    let touchEndX = 0;
    container.addEventListener('touchstart', (e) => {
      touchStartX = e.changedTouches[0].screenX;
      stopAutoSlide();
    }, { passive: true });

    container.addEventListener('touchend', (e) => {
      touchEndX = e.changedTouches[0].screenX;
      if (touchStartX - touchEndX > 45) {
        nextSlide();
      } else if (touchEndX - touchStartX > 45) {
        prevSlide();
      }
      startAutoSlide();
    }, { passive: true });
  }

  // Kích hoạt chạy tự động
  startAutoSlide();
})();
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

