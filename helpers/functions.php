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
    $url = BASE_URL . '/' . $cleanPath;
    if (preg_match('/\.(css|js)$/i', $cleanPath)) {
        $fullPath = ROOT_PATH . '/' . $cleanPath;
        if (file_exists($fullPath)) {
            $url .= '?v=' . filemtime($fullPath);
        }
    }
    return $url;
}

/**
 * Resolve user avatar URL across root-served and subdirectory-served environments.
 */
function getAvatarUrl(?string $avatarUrl, string $fallback = 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=160&auto=format&fit=crop&q=80'): string {
    $raw = trim((string)($avatarUrl ?? ''));
    if ($raw === '') {
        return $fallback;
    }
    if (preg_match('#^((https?:)?//|data:image/)#i', $raw)) {
        return $raw;
    }
    return getAssetUrl(ltrim($raw, '/'));
}

/**
 * Validate and store an uploaded profile avatar file under uploads/avatars/.
 */
function processProfileAvatarUpload(array $file, int $userId, ?string $oldAvatarUrl = null): array {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'error' => 'Vui lòng chọn tệp hình ảnh để tải lên.'];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['success' => false, 'error' => 'Dung lượng ảnh vượt quá giới hạn cho phép (tối đa 5MB).'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Lỗi tải ảnh lên máy chủ (mã lỗi ' . (int)$file['error'] . ').'];
    }

    $maxSize = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Dung lượng hình ảnh không hợp lệ (tối đa 5MB).'];
    }

    $tmpPath = $file['tmp_name'] ?? '';
    if (!$tmpPath || !is_uploaded_file($tmpPath)) {
        return ['success' => false, 'error' => 'Tệp ảnh tải lên không hợp lệ.'];
    }

    $origName = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowedExts, true)) {
        return ['success' => false, 'error' => 'Định dạng ảnh không được hỗ trợ. Vui lòng chọn JPG, PNG, WebP hoặc GIF.'];
    }

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $mimeType = $finfo ? (string)finfo_file($finfo, $tmpPath) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if ($mimeType !== '' && !in_array($mimeType, $allowedMimes, true)) {
        return ['success' => false, 'error' => 'Loại tệp hình ảnh không hợp lệ.'];
    }

    $imgInfo = @getimagesize($tmpPath);
    if ($imgInfo === false || empty($imgInfo[0]) || empty($imgInfo[1])) {
        return ['success' => false, 'error' => 'Tệp tin bị hỏng hoặc không phải hình ảnh hợp lệ.'];
    }

    $uploadDir = ROOT_PATH . '/uploads/avatars';
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return ['success' => false, 'error' => 'Không thể khởi tạo thư mục lưu ảnh đại diện.'];
    }

    $htaccessPath = $uploadDir . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        @file_put_contents($htaccessPath, "<FilesMatch \"(?i)\\.(php|phtml|php3|php4|php5|php7|php8|phar|inc|pl|py|cgi|sh|bash|exe|bat|cmd)$\">\n    Order Deny,Allow\n    Deny from all\n</FilesMatch>\nOptions -Indexes\n");
    }

    $safeExt = ($ext === 'jpeg') ? 'jpg' : $ext;
    $fileName = 'avatar_' . $userId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $safeExt;
    $destPath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($tmpPath, $destPath)) {
        return ['success' => false, 'error' => 'Không thể lưu ảnh đại diện lên hệ thống.'];
    }

    if ($oldAvatarUrl) {
        $oldClean = ltrim(str_replace('\\', '/', trim($oldAvatarUrl)), '/');
        if (preg_match('#^uploads/avatars/[a-zA-Z0-9._-]+$#', $oldClean) && $oldClean !== ('uploads/avatars/' . $fileName)) {
            $oldFullPath = ROOT_PATH . '/' . $oldClean;
            if (is_file($oldFullPath)) {
                @unlink($oldFullPath);
            }
        }
    }

    $relativeUrl = 'uploads/avatars/' . $fileName;
    return [
        'success'      => true,
        'relative_url' => $relativeUrl,
        'full_url'     => getAssetUrl($relativeUrl),
        'file_name'    => $fileName,
    ];
}

function getFoodImageUrl($img) {
    $placeholder = BASE_URL . '/assets/images/foods/banh-mi-chao.jpg';
    if (empty($img)) {
        return $placeholder;
    }
    if (preg_match('#^(https?://|//)#i', $img)) {
        return $img;
    }
    $clean = ltrim($img, '/');
    if (strpos($clean, 'assets/') === 0 || strpos($clean, 'frontend/') === 0) {
        return BASE_URL . '/' . $clean;
    }
    return BASE_URL . '/assets/images/foods/' . $clean;
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
    $imgUrl = escapeHtml(getFoodImageUrl($rawImg));
    
    $restaurant = !empty($p['restaurant_name']) ? $p['restaurant_name'] : (!empty($p['restaurant']) ? $p['restaurant'] : 'FASTFOOD Partner');
    $category = !empty($p['category_name']) ? $p['category_name'] : (!empty($p['category']) ? $p['category'] : 'FASTFOOD');
    $ratingCount = (int)($p['rating_count'] ?? 0);
    $ratingAvgVal = (float)($p['rating_avg'] ?? 0);
    if ($ratingCount > 0 && $ratingAvgVal > 0) {
        $ratingDisplay = '★ ' . number_format($ratingAvgVal, 1) . ' <span style="color:var(--text-muted); font-weight:500;">(' . $ratingCount . ')</span>';
    } elseif (!empty($p['rating']) && (float)$p['rating'] > 0) {
        $ratingDisplay = '★ ' . number_format((float)$p['rating'], 1);
    } else {
        $ratingDisplay = '<span style="color:var(--text-muted); font-weight:600;">★ Mới</span>';
    }
    $prepTime = !empty($p['prep_time_minutes']) ? $p['prep_time_minutes'] . ' phút' : '15-20 phút';
    $detailUrl = getUrl('food-detail.php?id=' . $id);
    
    ob_start();
    ?>
    <div class="product-card" data-product-id="<?= $id ?>" data-category-id="<?= (int)($p['category_id'] ?? 0) ?>" data-category="<?= escapeHtml($category) ?>">
      <div class="product-thumb-wrap">
        <a href="<?= $detailUrl ?>" style="display:block; width:100%; height:100%;">
          <img src="<?= $imgUrl ?>" alt="<?= escapeHtml($name) ?>" class="product-thumb" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null; this.src='<?= getAssetUrl('assets/images/foods/banh-mi-chao.jpg') ?>';">
        </a>
        <?php if ($hasDiscount): ?>
          <span class="product-tag-discount">-<?= $discountPct ?>%</span>
        <?php endif; ?>
        <span class="product-tag-time">⏱️ <?= $prepTime ?></span>
      </div>
      <div class="product-info">
        <div class="product-meta-row">
          <span class="product-category-name" title="<?= escapeHtml($category) ?>"><?= escapeHtml($category) ?></span>
          <span class="product-rating"><?= $ratingDisplay ?></span>
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
            <button type="button" class="favorite-btn" data-id="<?= $id ?>" onclick="toggleFavorite(<?= $id ?>, this)" title="Yêu thích món này">♡</button>
            <button type="button" class="btn-add-cart" onclick="openFoodModal(<?= $id ?>)" title="Tùy chọn món & Topping">＋</button>
          </div>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}


