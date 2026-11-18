<?php
/**
 * FASTFOOD - Các hàm tiện ích hỗ trợ
 */

function formatVND($amount) {
    return number_format((float)($amount ?? 0), 0, ',', '.') . ' đ';
}

function escapeHtml($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function getAssetUrl($path) {
    $cleanPath = ltrim($path, '/');
    return BASE_URL . '/' . $cleanPath;
}

function getUrl($path = '') {
    $cleanPath = ltrim($path, '/');
    $url = BASE_URL . ($cleanPath ? '/' . $cleanPath : '');

    // Tự động duy trì tham số tab để phân lập phiên giữa các tab
    if (!empty($_GET['tab'])) {
        $sep = (strpos($url, '?') !== false) ? '&' : '?';
        $url .= $sep . 'tab=' . urlencode($_GET['tab']);
    }

    return $url;
}

/**
 * Format date time sang định dạng Việt Nam
 */
function formatDateTimeVN($datetimeStr) {
    if (!$datetimeStr) return '';
    try {
        $dt = new DateTime($datetimeStr);
        $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
        return $dt->format('d/m/Y H:i');
    } catch (Exception $e) {
        return (string)$datetimeStr;
    }
}

/**
 * Render thẻ món ăn chuẩn thiết kế đồng bộ chiều cao và giao diện
 */
function renderProductCard($p) {
    $id = (int)($p['id'] ?? 0);
    $name = $p['name'] ?? 'Món ăn ngon';
    $basePrice = (float)($p['base_price'] ?? ($p['price'] ?? 0));
    $discountPrice = !empty($p['discount_price']) ? (float)$p['discount_price'] : 0;
    $hasDiscount = $discountPrice > 0 && $discountPrice < $basePrice;
    $currentPrice = $hasDiscount ? $discountPrice : $basePrice;
    $discountPct = $hasDiscount ? round((($basePrice - $discountPrice) / $basePrice) * 100) : 0;
    
    $rawImg = !empty($p['image_url']) ? $p['image_url'] : (!empty($p['image']) ? $p['image'] : '');
    if (!$rawImg) {
        $rawImg = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80';
    } elseif (strpos($rawImg, 'http') !== 0 && strpos($rawImg, '/') !== 0) {
        $rawImg = getAssetUrl($rawImg);
    }
    $imgUrl = escapeHtml($rawImg);
    
    $restaurant = !empty($p['restaurant_name']) ? $p['restaurant_name'] : (!empty($p['restaurant']) ? $p['restaurant'] : 'FASTFOOD Partner');
    $category = !empty($p['category_name']) ? $p['category_name'] : (!empty($p['category']) ? $p['category'] : 'FASTFOOD');
    $rating = !empty($p['rating_avg']) ? number_format((float)$p['rating_avg'], 1) : (!empty($p['rating']) ? number_format((float)$p['rating'], 1) : '4.9');
    $prepTime = !empty($p['prep_time_minutes']) ? $p['prep_time_minutes'] . ' phút' : '15-20 phút';
    $detailUrl = getUrl('food-detail.php?id=' . $id);
    
    ob_start();
    ?>
    <div class="product-card" data-product-id="<?= $id ?>">
      <div class="product-thumb-wrap">
        <a href="<?= $detailUrl ?>" style="display:block; width:100%; height:100%;">
          <img src="<?= $imgUrl ?>" alt="<?= escapeHtml($name) ?>" class="product-thumb" loading="lazy" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80';">
        </a>
        <?php if ($hasDiscount): ?>
          <span class="product-tag-discount">-<?= $discountPct ?>%</span>
        <?php endif; ?>
        <span class="product-tag-time">⏱️ <?= $prepTime ?></span>
      </div>
      <div class="product-info">
        <div class="product-meta-row">
          <span class="product-category-name" title="<?= escapeHtml($category) ?>"><?= escapeHtml($category) ?></span>
          <span class="product-rating">★ <?= $rating ?></span>
        </div>
        <a href="<?= $detailUrl ?>" class="product-title" title="<?= escapeHtml($name) ?>">
          <?= escapeHtml($name) ?>
        </a>
        <div class="product-restaurant-name" style="color:var(--text-muted); font-size:0.8rem;">
          <span>🛵</span> FASTFOOD • Giao nhanh 15-20p
        </div>
        <div class="product-bottom-row">
          <div class="product-price-box">
            <span class="product-price"><?= formatVND($currentPrice) ?></span>
            <?php if ($hasDiscount): ?>
              <span class="product-old-price"><?= formatVND($basePrice) ?></span>
            <?php endif; ?>
          </div>
          <div class="product-actions-group">
            <button type="button" class="favorite-btn" onclick="toggleFavorite(<?= $id ?>, this)" title="Yêu thích món này">♡</button>
            <button type="button" class="btn-add-cart" onclick="openFoodModal(<?= $id ?>)" title="Tùy chọn món & Topping">＋</button>
          </div>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}


