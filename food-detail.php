<?php
/**
 * FASTFOOD - Chi tiết món ăn (Food Detail)
 * Hỗ trợ đầy đủ biến thể: Kích cỡ (Size), Toppings, Khẩu vị & Ghi chú
 */
require_once __DIR__ . '/config/config.php';

$db = getDB();
$id = trim($_GET['id'] ?? '');

$product = null;
if ($id) {
    $stmt = $db->prepare("SELECT p.*, r.name AS restaurant_name, r.address AS restaurant_address, 
                                 r.rating_avg, r.rating_count, c.name AS category_name
                          FROM products p
                          LEFT JOIN restaurants r ON p.restaurant_id = r.id
                          LEFT JOIN categories c ON p.category_id = c.id
                          WHERE p.id = ? OR p.product_code = ? LIMIT 1");
    $stmt->execute([$id, $id]);
    $product = $stmt->fetch();

    if (!$product && is_numeric($id)) {
        $stmt = $db->prepare("SELECT p.*, r.name AS restaurant_name, r.address AS restaurant_address, 
                                     r.rating_avg, r.rating_count, c.name AS category_name
                              FROM products p
                              LEFT JOIN restaurants r ON p.restaurant_id = r.id
                              LEFT JOIN categories c ON p.category_id = c.id
                              WHERE p.id = ? LIMIT 1");
        $stmt->execute([(int)$id]);
        $product = $stmt->fetch();
    }
}

if (!$product) {
    header('Location: ' . getUrl('foods.php'));
    exit;
}

$pageTitle = $product['name'] . ' - FASTFOOD';
$activeNav = 'foods';
$extraCss  = ['css/pages.css'];

$hasDiscount = !empty($product['discount_price']) && (float)$product['discount_price'] > 0 && (float)$product['discount_price'] < (float)$product['base_price'];
$currentPrice = $hasDiscount ? (float)$product['discount_price'] : (float)$product['base_price'];

// 1. Phân tích biến thể & tùy chọn (Options: sizes, toppings, attributes)
$productOptions = [];
if (!empty($product['options'])) {
    $productOptions = is_array($product['options']) ? $product['options'] : json_decode($product['options'], true);
}

// Fallback thông minh nếu sản phẩm chưa có options
if (empty($productOptions) || !is_array($productOptions)) {
    $catName = mb_strtolower($product['category_name'] ?? '', 'UTF-8');
    if (strpos($catName, 'trà sữa') !== false || strpos($catName, 'uống') !== false) {
        $productOptions = [
            'sizes' => [
                ['name' => 'Size M (500ml)', 'price' => 0, 'is_default' => true],
                ['name' => 'Size L (700ml)', 'price' => 8000, 'is_default' => false]
            ],
            'toppings' => [
                ['name' => 'Trân châu đường đen dẻo', 'price' => 6000],
                ['name' => 'Kem cheese mặn béo ngậy', 'price' => 10000],
                ['name' => 'Thạch nha đam thanh mát', 'price' => 5000],
                ['name' => 'Pudding trứng mịn màng', 'price' => 7000]
            ],
            'attributes' => [
                ['name' => 'Lượng đường', 'options' => ['100% đường (Chuẩn)', '70% đường', '50% đường', '30% đường', 'Không đường']],
                ['name' => 'Lượng đá', 'options' => ['100% đá (Chuẩn)', '50% đá (Ít đá)', 'Không đá']]
            ]
        ];
    } elseif (strpos($catName, 'burger') !== false || strpos($catName, 'gà') !== false) {
        $productOptions = [
            'sizes' => [
                ['name' => 'Cỡ Vừa (Regular)', 'price' => 0, 'is_default' => true],
                ['name' => 'Cỡ Lớn (Up-size)', 'price' => 15000, 'is_default' => false],
                ['name' => 'Double Nhân Đôi (Siêu To)', 'price' => 25000, 'is_default' => false]
            ],
            'toppings' => [
                ['name' => 'Thêm phô mai lát Cheddar', 'price' => 10000],
                ['name' => 'Thịt ba chỉ xông khói Bacon', 'price' => 15000],
                ['name' => 'Khoai tây chiên nhỏ đi kèm', 'price' => 15000]
            ],
            'attributes' => [
                ['name' => 'Hương vị', 'options' => ['Giòn truyền thống', 'Sốt cay Hàn Quốc', 'Lắc phô mai (+0đ)']]
            ]
        ];
    } else {
        $productOptions = [
            'sizes' => [
                ['name' => 'Hộp Tiêu Chuẩn', 'price' => 0, 'is_default' => true],
                ['name' => 'Suất Lớn (+ Cơm & Thịt)', 'price' => 10000, 'is_default' => false],
                ['name' => 'Suất Đặc Biệt Siêu No', 'price' => 20000, 'is_default' => false]
            ],
            'toppings' => [
                ['name' => 'Trứng ốp la lòng đào', 'price' => 8000],
                ['name' => 'Chả trứng / Chả lụa thơm', 'price' => 10000],
                ['name' => 'Canh rong biển súp ấm', 'price' => 12000],
                ['name' => 'Thêm cơm trắng dẻo thơm', 'price' => 5000]
            ],
            'attributes' => [
                ['name' => 'Mức độ cay', 'options' => ['Không cay', 'Cay vừa', 'Cay nhiều']],
                ['name' => 'Ghi chú chế biến', 'options' => ['Đầy đủ mỡ hành', 'Ít mỡ hành', 'Không hành lá']]
            ]
        ];
    }
}

$sizes = $productOptions['sizes'] ?? [];
$toppings = $productOptions['toppings'] ?? [];
$attributes = $productOptions['attributes'] ?? [];

// Món ăn liên quan
$relatedStmt = $db->prepare("SELECT p.*, r.name AS restaurant_name 
                             FROM products p 
                             LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                             WHERE p.id != ? AND (p.category_id = ? OR p.restaurant_id = ?) AND p.is_available = 1 
                             ORDER BY p.id DESC LIMIT 4");
$relatedStmt->execute([$product['id'], $product['category_id'] ?? 0, $product['restaurant_id'] ?? 0]);
$relatedFoods = $relatedStmt->fetchAll();

$isManager = isAdmin();

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Scoped Styles for Food Variants -->
<style>
.fd-variant-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 1.15rem 1.25rem;
  margin-bottom: 1.25rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.fd-variant-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.85rem;
}
.fd-variant-title {
  font-size: 0.96rem;
  font-weight: 800;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.45rem;
}
.fd-variant-badge {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.18rem 0.55rem;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
}
.fd-variant-badge.required {
  background: #fee2e2;
  color: #ef4444;
}
.fd-variant-badge.optional {
  background: #ecfdf5;
  color: #059669;
}
.fd-size-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 0.65rem;
}
.fd-size-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 0.9rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
  transition: all 0.16s ease;
  user-select: none;
}
.fd-size-item:hover {
  border-color: #fdba74;
  background: #fffaf5;
}
.fd-size-item.selected {
  border-color: #ea580c;
  background: #fff7ed;
  box-shadow: 0 0 0 2px rgba(234, 88, 12, 0.12);
}
.fd-size-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.fd-size-radio {
  width: 17px;
  height: 17px;
  accent-color: #ea580c;
  cursor: pointer;
}
.fd-size-name {
  font-size: 0.88rem;
  font-weight: 700;
  color: #1e293b;
}
.fd-size-price {
  font-size: 0.85rem;
  font-weight: 800;
  color: #ea580c;
}

.fd-toppings-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 0.65rem;
}
.fd-topping-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.7rem 0.85rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
  transition: all 0.16s ease;
  user-select: none;
}
.fd-topping-item:hover {
  border-color: #fdba74;
  background: #fffaf5;
}
.fd-topping-item.selected {
  border-color: #ea580c;
  background: #fff7ed;
}
.fd-topping-left {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}
.fd-topping-cb {
  width: 17px;
  height: 17px;
  accent-color: #ea580c;
  cursor: pointer;
}
.fd-topping-name {
  font-size: 0.88rem;
  font-weight: 600;
  color: #334155;
}
.fd-topping-price {
  font-size: 0.85rem;
  font-weight: 800;
  color: #ea580c;
}

.fd-attr-group {
  margin-bottom: 0.85rem;
}
.fd-attr-group:last-child {
  margin-bottom: 0;
}
.fd-attr-name {
  font-size: 0.85rem;
  font-weight: 750;
  color: #475569;
  margin-bottom: 0.45rem;
}
.fd-attr-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}
.fd-chip-btn {
  padding: 0.4rem 0.8rem;
  border-radius: 999px;
  border: 1.5px solid #e2e8f0;
  background: #fff;
  color: #475569;
  font-size: 0.82rem;
  font-weight: 650;
  cursor: pointer;
  transition: all 0.15s ease;
}
.fd-chip-btn:hover {
  border-color: #cbd5e1;
  background: #f8fafc;
}
.fd-chip-btn.active {
  border-color: #ea580c;
  background: #ea580c;
  color: #ffffff;
}
.fd-note-input {
  width: 100%;
  padding: 0.65rem 0.85rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.88rem;
  color: #334155;
  outline: none;
  font-family: inherit;
  transition: border-color 0.16s ease;
}
.fd-note-input:focus {
  border-color: #ea580c;
  box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.1);
}
.fd-summary-strip {
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 10px;
  padding: 0.75rem 1rem;
  margin-bottom: 1.25rem;
  font-size: 0.86rem;
  color: #475569;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}
</style>

<!-- Page Header Bar -->
<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= getUrl('index.php') ?>">Trang chủ</a> <span>/</span> 
      <a href="<?= getUrl('foods.php') ?>">Món ăn</a> <span>/</span> 
      <span><?= escapeHtml($product['name']) ?></span>
    </div>
    <h1 class="page-title">Thông Tin Món Ăn</h1>
  </div>
</div>

<main class="container">
  <div id="food-detail-container" style="margin-top: 2rem;">
    <div class="food-detail-grid">
      <!-- Ảnh món ăn -->
      <div class="food-gallery-main">
        <img src="<?= escapeHtml($product['image_url'] ?: getAssetUrl('assets/images/logo.svg')) ?>" 
             alt="<?= escapeHtml($product['name']) ?>" class="food-gallery-img">
      </div>

      <!-- Chi tiết thông tin & Biến thể tùy chọn -->
      <div>
        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem; flex-wrap:wrap;">
          <span class="badge badge-primary">FASTFOOD Chính Hãng</span>
          <span style="color:var(--accent-gold); font-weight:700; font-size:0.9rem;">
            ★ <?= number_format((float)($product['rating_avg'] ?: 4.9), 1) ?> (<?= (int)($product['rating_count'] ?: 28) ?> đánh giá)
          </span>
          <span style="color:var(--text-muted); font-size:0.85rem;">• ⏱️ <?= (int)($product['prep_time_minutes'] ?: 15) ?> phút</span>
        </div>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:0.65rem;">
          🛵 Giao hàng tận nơi siêu tốc trong 20 phút tại Buôn Ma Thuột
        </p>

        <h1 style="font-size:2rem; font-weight:800; color:var(--text-main); line-height:1.25; margin-bottom:0.5rem;">
          <?= escapeHtml($product['name']) ?>
        </h1>
        
        <div class="detail-price-box" style="margin-bottom:0.85rem;">
          <span id="detail-total-price" class="detail-current-price"><?= formatVND($currentPrice) ?></span>
          <?php if ($hasDiscount): ?>
            <span class="detail-old-price"><?= formatVND($product['base_price']) ?></span>
          <?php endif; ?>
        </div>

        <p style="color:var(--text-secondary); line-height:1.7; font-size:0.95rem; margin-bottom:1.5rem;">
          <?= nl2br(escapeHtml($product['description'] ?: 'Món ăn thơm ngon, nóng hổi, chuẩn vị chế biến từ nguyên liệu tươi sạch hàng ngày.')) ?>
        </p>

        <!-- ========================================== -->
        <!-- 1. BIẾN THỂ KÍCH CỠ / SIZE (Chọn 1)       -->
        <!-- ========================================== -->
        <?php if (!empty($sizes)): ?>
          <div class="fd-variant-card">
            <div class="fd-variant-head">
              <span class="fd-variant-title">📏 1. Chọn kích cỡ / Khẩu phần:</span>
              <span class="fd-variant-badge required">Bắt buộc</span>
            </div>
            <div class="fd-size-grid">
              <?php foreach ($sizes as $idx => $s): 
                $isDef = !empty($s['is_default']) || $idx === 0;
                $sPrice = (float)($s['price'] ?? 0);
              ?>
                <label class="fd-size-item <?= $isDef ? 'selected' : '' ?>" id="fd-size-item-<?= $idx ?>" 
                       onclick="selectSize(<?= $idx ?>, '<?= escapeHtml($s['name']) ?>', <?= $sPrice ?>)">
                  <div class="fd-size-info">
                    <input type="radio" name="product_size" class="fd-size-radio" value="<?= escapeHtml($s['name']) ?>" 
                           <?= $isDef ? 'checked' : '' ?> data-price="<?= $sPrice ?>" data-name="<?= escapeHtml($s['name']) ?>">
                    <span class="fd-size-name"><?= escapeHtml($s['name']) ?></span>
                  </div>
                  <span class="fd-size-price">
                    <?= $sPrice > 0 ? '+' . formatVND($sPrice) : 'Gốc' ?>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- ========================================== -->
        <!-- 2. TÙY CHỌN TOPPING & MÓN KÈM (Chọn nhiều) -->
        <!-- ========================================== -->
        <?php if (!empty($toppings)): ?>
          <div class="fd-variant-card">
            <div class="fd-variant-head">
              <span class="fd-variant-title">✨ 2. Món ăn kèm / Toppings:</span>
              <span class="fd-variant-badge optional">Tùy chọn (<?= count($toppings) ?> món)</span>
            </div>
            <div class="fd-toppings-list">
              <?php foreach ($toppings as $tIdx => $t): 
                $tPrice = (float)($t['price'] ?? 0);
              ?>
                <label class="fd-topping-item" id="fd-topping-item-<?= $tIdx ?>">
                  <span class="fd-topping-left">
                    <input type="checkbox" class="fd-topping-cb" data-name="<?= escapeHtml($t['name']) ?>" 
                           data-price="<?= $tPrice ?>" onchange="toggleTopping(this, 'fd-topping-item-<?= $tIdx ?>')">
                    <span class="fd-topping-name"><?= escapeHtml($t['name']) ?></span>
                  </span>
                  <span class="fd-topping-price">+<?= formatVND($tPrice) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- ========================================== -->
        <!-- 3. TÙY CHỌN KHẨU VỊ / THUỘC TÍNH (Chọn 1) -->
        <!-- ========================================== -->
        <?php if (!empty($attributes)): ?>
          <div class="fd-variant-card">
            <div class="fd-variant-head">
              <span class="fd-variant-title">🌶️ 3. Khẩu vị & Cách chế biến:</span>
              <span class="fd-variant-badge optional">Tùy chọn</span>
            </div>
            <?php foreach ($attributes as $aIdx => $attr): 
              $attrName = $attr['name'] ?? 'Tùy chọn';
              $attrOpts = $attr['options'] ?? [];
            ?>
              <div class="fd-attr-group">
                <div class="fd-attr-name"><?= escapeHtml($attrName) ?>:</div>
                <div class="fd-attr-chips" data-attr-name="<?= escapeHtml($attrName) ?>">
                  <?php foreach ($attrOpts as $oIdx => $optVal): ?>
                    <button type="button" class="fd-chip-btn <?= $oIdx === 0 ? 'active' : '' ?>" 
                            data-attr="<?= escapeHtml($attrName) ?>" data-val="<?= escapeHtml($optVal) ?>"
                            onclick="selectAttributeChip(this)">
                      <?= escapeHtml($optVal) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- ========================================== -->
        <!-- 4. GHI CHÚ CHO BẾP                         -->
        <!-- ========================================== -->
        <div class="fd-variant-card">
          <div class="fd-variant-head">
            <span class="fd-variant-title">📝 Ghi chú cho quán (nếu có):</span>
          </div>
          <input type="text" id="fd-order-note" class="fd-note-input" 
                 placeholder="Ví dụ: Để riêng sốt, không hành, làm cay ít...">
        </div>

        <!-- Tóm tắt chi tiết giá lựa chọn -->
        <div class="fd-summary-strip" id="fd-summary-strip">
          <span>Đơn giá phần ăn: <b id="fd-unit-calc"><?= formatVND($currentPrice) ?></b></span>
          <span>Số lượng: <b id="fd-qty-calc">1</b></span>
        </div>

        <!-- Thao tác mua hàng & Số lượng -->
        <div style="display:flex; align-items:center; gap:1.25rem; margin-top:1.5rem; flex-wrap:wrap;">
          <?php if ($isManager): ?>
            <div style="flex:1; padding:1rem 1.25rem; background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; border-radius:var(--radius-md); font-weight:700; font-size:0.95rem;">
              👑 <span>Tài khoản Quản trị viên (Admin) chỉ dùng để quản trị hệ thống.</span>
            </div>
          <?php else: ?>
            <div class="quantity-control">
              <button type="button" class="qty-btn" id="btn-qty-minus" onclick="updateQty(-1)">-</button>
              <input type="text" id="detail-qty-input" class="qty-input" value="1" readonly>
              <button type="button" class="qty-btn" id="btn-qty-plus" onclick="updateQty(1)">+</button>
            </div>

            <button type="button" class="btn btn-outline btn-lg" id="btn-add-to-cart" onclick="handleAddToCart(false)">
              🛒 Thêm Vào Giỏ Hàng
            </button>

            <button type="button" class="btn btn-primary btn-lg" id="btn-buy-now" onclick="handleAddToCart(true)">
              ⚡ Mua Ngay
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Món ăn liên quan -->
  <?php if (!empty($relatedFoods)): ?>
  <section style="margin-top:4rem; margin-bottom:4rem;">
    <h3 style="font-size:1.35rem; font-weight:800; color:var(--text-main); margin-bottom:1.5rem;">
      Món Ăn Liên Quan Được Yêu Thích
    </h3>
    <div class="grid grid-cols-4" id="related-foods-container">
      <?php foreach ($relatedFoods as $rf): ?>
        <?= renderProductCard($rf) ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<script>
let qty = 1;
const basePrice = <?= (float)$currentPrice ?>;
const productId = <?= (int)$product['id'] ?>;

// Trạng thái biến thể đã chọn
let selectedSize = null;
<?php if (!empty($sizes)): ?>
  // Khởi tạo size mặc định
  <?php 
    $defSize = $sizes[0];
    foreach ($sizes as $s) {
      if (!empty($s['is_default'])) { $defSize = $s; break; }
    }
  ?>
  selectedSize = {
    name: <?= json_encode($defSize['name']) ?>,
    price: <?= (float)($defSize['price'] ?? 0) ?>
  };
<?php endif; ?>

function selectSize(idx, name, price) {
  selectedSize = { name, price: parseFloat(price) || 0 };
  
  // Update UI classes
  document.querySelectorAll('.fd-size-item').forEach((el, i) => {
    el.classList.toggle('selected', i === idx);
    const radio = el.querySelector('.fd-size-radio');
    if (radio) radio.checked = (i === idx);
  });

  calculateDetailTotal();
}

function toggleTopping(checkbox, itemId) {
  const itemEl = document.getElementById(itemId);
  if (itemEl) {
    itemEl.classList.toggle('selected', checkbox.checked);
  }
  calculateDetailTotal();
}

function selectAttributeChip(btn) {
  const container = btn.closest('.fd-attr-chips');
  if (container) {
    container.querySelectorAll('.fd-chip-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  }
}

function updateQty(delta) {
  qty = Math.max(1, qty + delta);
  document.getElementById('detail-qty-input').value = qty;
  calculateDetailTotal();
}

function calculateDetailTotal() {
  const sizeExtra = selectedSize ? (selectedSize.price || 0) : 0;
  
  let toppingsTotal = 0;
  document.querySelectorAll('.fd-topping-cb:checked').forEach(cb => {
    toppingsTotal += (parseFloat(cb.dataset.price) || 0);
  });

  const unitPrice = basePrice + sizeExtra + toppingsTotal;
  const grandTotal = unitPrice * qty;

  const display = document.getElementById('detail-total-price');
  if (display) {
    display.textContent = grandTotal.toLocaleString('vi-VN') + ' đ';
  }

  const unitCalc = document.getElementById('fd-unit-calc');
  if (unitCalc) {
    let breakdown = unitPrice.toLocaleString('vi-VN') + ' đ';
    if (sizeExtra > 0 || toppingsTotal > 0) {
      breakdown += ` (${basePrice.toLocaleString('vi-VN')}đ`;
      if (sizeExtra > 0) breakdown += ` + Cỡ ${sizeExtra.toLocaleString('vi-VN')}đ`;
      if (toppingsTotal > 0) breakdown += ` + Topping ${toppingsTotal.toLocaleString('vi-VN')}đ`;
      breakdown += ')';
    }
    unitCalc.textContent = breakdown;
  }

  const qtyCalc = document.getElementById('fd-qty-calc');
  if (qtyCalc) {
    qtyCalc.textContent = qty;
  }
}

async function handleAddToCart(isBuyNow = false) {
  try {
    // 1. Toppings
    const selectedToppings = [];
    document.querySelectorAll('.fd-topping-cb:checked').forEach(cb => {
      selectedToppings.push({
        name: cb.dataset.name,
        price: parseFloat(cb.dataset.price) || 0
      });
    });

    // 2. Attributes (Khẩu vị, đường, đá, v.v.)
    const selectedAttributes = [];
    document.querySelectorAll('.fd-attr-chips').forEach(group => {
      const activeBtn = group.querySelector('.fd-chip-btn.active');
      if (activeBtn) {
        selectedAttributes.push({
          name: activeBtn.dataset.attr,
          value: activeBtn.dataset.val
        });
      }
    });

    // 3. Ghi chú
    const note = document.getElementById('fd-order-note')?.value.trim() || '';

    const payload = {
      action: 'add',
      productId: productId,
      qty: qty,
      size: selectedSize,
      toppings: selectedToppings,
      attributes: selectedAttributes,
      note: note
    };

    const res = await fetch('<?= getUrl('api/cart.php') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await res.json();
    if (result.success) {
      if (isBuyNow) {
        window.location.href = '<?= getUrl('cart.php') ?>';
      } else {
        let topText = '';
        if (selectedSize && selectedSize.price > 0) topText += ` (Cỡ ${selectedSize.name})`;
        if (selectedToppings.length) topText += ` + ${selectedToppings.length} topping`;

        if (window.UIComponents?.showToast) {
          window.UIComponents.showToast(`Đã thêm ${qty} phần ${topText} vào giỏ hàng!`, 'success');
        } else {
          alert(`Đã thêm ${qty} phần ${topText} vào giỏ hàng!`);
        }

        const badge = document.getElementById('nav-cart-badge');
        if (badge) {
          badge.textContent = result.data.totalCount;
          badge.style.display = result.data.totalCount > 0 ? 'flex' : 'none';
        }
      }
    } else {
      alert(result.message || 'Không thể thêm vào giỏ hàng.');
    }
  } catch (err) {
    console.error(err);
    alert('Có lỗi xảy ra khi thêm vào giỏ hàng.');
  }
}

// Khởi chạy tính giá lần đầu
calculateDetailTotal();
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
