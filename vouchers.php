<?php
/**
 * FASTFOOD - Customer Voucher Hub & Wallet Page
 * File: vouchers.php
 * Trung tâm Khuyến mãi, Săn Voucher và Quản lý Ví Voucher của Khách Hàng
 * Hỗ trợ 4 loại voucher: Giảm %, Giảm tiền, Miễn phí vận chuyển, Mua 1 tặng 1
 * Tích hợp Hạng thành viên & Voucher sinh nhật tự động
 */
$pageTitle = 'Kho Voucher Shop & Ưu Đãi Khách Hàng | FASTFOOD';
$activeNav = 'vouchers';

require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/helpers/auth.php';
require_once ROOT_PATH . '/helpers/voucher.php';
require_once ROOT_PATH . '/helpers/loyalty.php';

$currentUser = currentUser();
$userId = $currentUser ? (int)$currentUser['id'] : 0;

// Lấy dữ liệu voucher
$publicVouchers = getAllPublicVouchers($userId);
$walletVouchers = $userId > 0 ? getUserWalletVouchers($userId) : [];
$walletCount    = $userId > 0 ? getUserVoucherCount($userId) : 0;

// Lấy thông tin điểm thưởng & ngày sinh nếu đã đăng nhập
$userPoints = 0;
$userTier = 'BRONZE';
$birthdayStatus = null;
if ($userId > 0) {
    $db = getDB();
    $uStmt = $db->prepare("SELECT points_balance, member_tier, total_spent, date_of_birth FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $uData = $uStmt->fetch();
    $userPoints = (int)($uData['points_balance'] ?? 0);
    $userTier   = strtoupper($uData['member_tier'] ?? 'BRONZE');
    $birthdayStatus = getUserBirthdayStatus($userId);

    // Tự động kiểm tra và tặng voucher nếu hôm nay là sinh nhật
    if (!empty($birthdayStatus['is_birthday_today']) && empty($birthdayStatus['received_this_year'])) {
        $gift = checkAndGiftBirthdayVoucher($userId);
        if ($gift) {
            $walletVouchers = getUserWalletVouchers($userId);
            $walletCount = getUserVoucherCount($userId);
            $birthdayStatus = getUserBirthdayStatus($userId);
        }
    }
}
$voucherPackages = getVoucherPackages();

require_once ROOT_PATH . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= getAssetUrl('css/vouchers.css') ?>">

<main class="container my-4">
  <!-- 1. HERO BANNER -->
  <section class="voucher-hero">
    <div class="voucher-hero-content">
      <div class="voucher-hero-badge">
        <span>🎉</span> FASTFOOD VOUCHER SHOP &amp; KHUYẾN MÃI
      </div>
      <h1 class="voucher-hero-title">Săn Voucher Khủng - Thưởng Thức Món Ngon</h1>
      <p class="voucher-hero-desc">
        Thu thập mã giảm giá phần trăm, giảm tiền mặt, miễn phí vận chuyển và mua 1 tặng 1. Đặc quyền riêng cho từng hạng thành viên và quà sinh nhật độc quyền!
      </p>

      <div class="voucher-quick-stats-bar">
        <div class="voucher-stat-chip">
          <span>🎟️</span>
          <div>Ví của bạn: <strong><span id="stat-wallet-count"><?= $walletCount ?></span> mã</strong> khả dụng</div>
        </div>
        <?php if ($userId > 0): ?>
          <div class="voucher-stat-chip">
            <span>🪙</span>
            <div>Điểm tích lũy: <strong><span id="stat-user-points"><?= number_format($userPoints) ?></span> pts</strong> (Hạng <strong><?= escapeHtml($userTier) ?></strong>)</div>
          </div>
        <?php else: ?>
          <div class="voucher-stat-chip">
            <span>👤</span>
            <a href="<?= getUrl('login.php') ?>" style="color:#ffffff; font-weight:750; text-decoration:underline;">Đăng nhập để nhận thêm ưu đãi →</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- CELEBRATORY BIRTHDAY BANNER (NẾU HÔM NAY LÀ SINH NHẬT) -->
  <?php if (!empty($birthdayStatus['is_birthday_today'])): ?>
    <div style="margin: 1.5rem 0; background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); border: 2px solid #ea580c; border-radius: 16px; padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 4px 15px rgba(234,88,12,0.12);">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="font-size: 2.75rem; line-height: 1;">🎂</div>
        <div>
          <strong style="color: #9a3412; font-size: 1.15rem; display: block;">
            Chúc Mừng Sinh Nhật <?= escapeHtml($currentUser['full_name']) ?>! 🎉
          </strong>
          <p style="margin: 0.25rem 0 0; font-size: 0.88rem; color: #c2410c;">
            FASTFOOD thân gửi tặng bạn món quà sinh nhật đặc biệt theo hạng <strong><?= $userTier ?></strong> (Đã được chuyển vào Ví Voucher, HSD 7 ngày). Chúc bạn có một ngày sinh nhật thật ấm áp và ngon miệng!
          </p>
        </div>
      </div>
      <button type="button" class="btn btn-primary" onclick="document.querySelector('.voucher-tab-btn[data-target=\'tab-wallet\']').click()" style="white-space:nowrap; padding:0.6rem 1.25rem; font-weight:750;">
        Xem Quà Sinh Nhật 🎁
      </button>
    </div>
  <?php endif; ?>

  <!-- 2. TABS BAR -->
  <nav class="voucher-tabs-bar" role="tablist">
    <button type="button" class="voucher-tab-btn active" data-target="tab-hunt" role="tab" aria-selected="true">
      <span>🎁</span> Săn Voucher Hot
      <span class="voucher-tab-badge"><?= count($publicVouchers) ?></span>
    </button>
    <button type="button" class="voucher-tab-btn" data-target="tab-wallet" role="tab" aria-selected="false">
      <span>🎟️</span> Ví Voucher Của Tôi
      <span class="voucher-tab-badge" id="tab-wallet-badge"><?= $walletCount ?></span>
    </button>
    <button type="button" class="voucher-tab-btn" data-target="tab-loyalty" role="tab" aria-selected="false">
      <span>🪙</span> Đổi Điểm Lấy Voucher
      <span class="voucher-tab-badge"><?= count($voucherPackages) ?></span>
    </button>
  </nav>

  <!-- 3. TAB 1: SĂN VOUCHER HOT -->
  <div id="tab-hunt" class="voucher-tab-pane active" role="tabpanel">
    
    <!-- Toolbar: Filter Chips theo 4 loại voucher & Realtime Search -->
    <div class="voucher-toolbar-wrap">
      <div class="voucher-filter-group">
        <span style="font-size: 0.86rem; font-weight: 850; color: #334155; margin-right: 0.25rem;">Phân loại:</span>
        <button type="button" class="voucher-filter-pill active" data-type="all">Tất Cả (<?= count($publicVouchers) ?>)</button>
        <button type="button" class="voucher-filter-pill" data-type="percentage">🔥 Giảm %</button>
        <button type="button" class="voucher-filter-pill" data-type="fixed">💰 Giảm Tiền Mặt</button>
        <button type="button" class="voucher-filter-pill" data-type="free_shipping">🚚 Freeship 0đ</button>
        <button type="button" class="voucher-filter-pill" data-type="bogo">🍔 Mua 1 Tặng 1</button>
      </div>

      <div class="voucher-search-box">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="text" id="voucher-search-hunt" placeholder="Tìm theo mã hoặc tên ưu đãi..." autocomplete="off">
      </div>
    </div>

    <div class="voucher-grid" id="public-vouchers-grid">
      <?php if (!empty($publicVouchers)): ?>
        <?php foreach ($publicVouchers as $cp): 
          $discDisplay = $cp['discount_display'] ?? '';
          $minOrder = (float)($cp['min_order_value'] ?? 0);
          $endDate = $cp['end_date'] ? date('d/m/Y', strtotime($cp['end_date'])) : 'Không thời hạn';
          $isClaimed = !empty($cp['is_saved']);
          $isUsed = !empty($cp['is_used']);
          $tierEligible = isset($cp['tier_eligible']) ? $cp['tier_eligible'] : true;
          $vType = $cp['voucher_type'] ?? 'percentage';
          $typeBadge = $cp['type_badge'] ?? 'Ưu Đãi';
          $reqTier = strtoupper($cp['min_member_tier'] ?? 'ALL');
          $usageCount = (int)($cp['usage_count'] ?? 0);
          $usageLimit = (int)($cp['usage_limit'] ?? 0);
          $usagePct = $usageLimit > 0 ? min(100, round(($usageCount / $usageLimit) * 100)) : 0;
          $remaining = $usageLimit > 0 ? max(0, $usageLimit - $usageCount) : null;
        ?>
          <article class="voucher-ticket-card <?= $isClaimed ? 'is-claimed' : '' ?> <?= $isUsed ? 'is-used' : '' ?>" data-voucher-type="<?= escapeHtml($vType) ?>" data-code="<?= escapeHtml(strtolower($cp['code'])) ?>" data-title="<?= escapeHtml(strtolower($cp['title'] ?: '')) ?>">
            <div class="ticket-stub">
              <div class="ticket-discount-tag"><?= $discDisplay ?></div>
              <div class="ticket-discount-sub"><?= $typeBadge ?></div>
            </div>

            <div class="ticket-body">
              <div class="ticket-header-row">
                <div>
                  <div style="display:flex; align-items:center; gap:0.4rem; margin-bottom:0.35rem; flex-wrap:wrap;">
                    <span class="ticket-type-tag <?= escapeHtml($vType) ?>">
                      <?= $typeBadge ?>
                    </span>
                    <?php if ($reqTier !== 'ALL'): ?>
                      <span class="ticket-tier-badge">
                        👑 Hạng <?= $reqTier ?>+
                      </span>
                    <?php endif; ?>
                  </div>
                  <h3 class="ticket-title"><?= escapeHtml($cp['title'] ?: 'Ưu Đãi Đặc Biệt FASTFOOD') ?></h3>
                </div>

                <?php if ($isUsed): ?>
                  <span class="ticket-status-pill used">Đã dùng</span>
                <?php elseif ($isClaimed): ?>
                  <span class="ticket-status-pill saved">✓ Trong ví</span>
                <?php endif; ?>
              </div>

              <div class="ticket-meta-info">
                <div class="ticket-condition">
                  <span>📌</span>
                  <span><?= $minOrder > 0 ? 'Đơn tối thiểu ' . number_format($minOrder, 0, ',', '.') . 'đ' : 'Áp dụng cho mọi đơn hàng' ?>
                  <?php if (!empty($cp['max_discount_amount']) && (float)$cp['max_discount_amount'] > 0): ?>
                    (Tối đa <?= number_format($cp['max_discount_amount'], 0, ',', '.') ?>đ)
                  <?php endif; ?></span>
                </div>
                <div class="ticket-expiry">
                  <span>⏳</span>
                  <span>HSD: <?= $endDate ?></span>
                </div>

                <?php if ($usageLimit > 0): ?>
                  <div class="ticket-quota-wrap">
                    <div class="ticket-quota-head">
                      <span>Đã dùng <?= $usagePct ?>%</span>
                      <span>Còn <?= $remaining ?> lượt</span>
                    </div>
                    <div class="ticket-quota-bar">
                      <div class="ticket-quota-progress" style="width: <?= $usagePct ?>%;"></div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>

              <div class="ticket-actions-strip">
                <span class="ticket-code-pill" title="Mã voucher">
                  🎟️ <?= escapeHtml($cp['code']) ?>
                </span>

                <div style="display:flex; gap:0.4rem; align-items:center;">
                  <button type="button" class="ticket-btn-action ticket-btn-copy js-copy-voucher-code" 
                          data-code="<?= escapeHtml($cp['code']) ?>" title="Sao chép mã">
                    📋 Sao chép
                  </button>

                  <?php if (!$userId): ?>
                    <a href="<?= getUrl('login.php') ?>" class="ticket-btn-action ticket-btn-claim">
                      🎁 Lưu mã
                    </a>
                  <?php elseif (!$tierEligible): ?>
                    <button type="button" class="ticket-btn-action" style="background:#e2e8f0; color:#94a3b8; border:none; cursor:not-allowed;" title="Cần đạt hạng <?= $reqTier ?> để lưu voucher này" disabled>
                      Cần Hạng <?= $reqTier ?>
                    </button>
                  <?php elseif ($isUsed): ?>
                    <button type="button" class="ticket-btn-action ticket-btn-claimed" disabled>
                      Đã dùng
                    </button>
                  <?php elseif ($isClaimed): ?>
                    <a href="<?= getUrl('cart.php') ?>" class="ticket-btn-action ticket-btn-use">
                      Dùng ngay →
                    </a>
                  <?php else: ?>
                    <button type="button" class="ticket-btn-action ticket-btn-claim js-claim-voucher-btn" 
                            data-voucher-id="<?= (int)$cp['id'] ?>" data-code="<?= escapeHtml($cp['code']) ?>">
                      🎁 Lưu mã
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="grid-column: 1 / -1; text-align:center; padding:3.5rem 2rem; background:#fff; border-radius:20px; border:1px dashed #fed7aa;">
          <div style="font-size:3.5rem; margin-bottom:1rem;">🎟️</div>
          <h3 style="font-size:1.3rem; font-weight:800; color:#1e293b;">Hiện tại chưa có mã giảm giá công khai nào</h3>
          <p style="color:var(--text-muted);">Hãy quay lại sau để săn các mã ưu đãi độc quyền từ FASTFOOD nhé!</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 4. TAB 2: VÍ VOUCHER CỦA TÔI -->
  <div id="tab-wallet" class="voucher-tab-pane" style="display:none;" role="tabpanel">
    <?php if (!$userId): ?>
      <div style="text-align:center; padding:3.5rem 1.5rem; background:#fff; border-radius:18px; border:1px solid #fed7aa; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
        <div style="font-size:3.5rem; margin-bottom:1rem;">🔐</div>
        <h3 style="font-size:1.4rem; font-weight:800; color:#1e293b; margin-bottom:0.5rem;">Đăng Nhập Để Xem Ví Voucher</h3>
        <p style="color:var(--text-muted); max-width:480px; margin:0 auto 1.5rem;">
          Đăng nhập vào tài khoản FASTFOOD để quản lý các voucher đã lưu, mã quà tặng sinh nhật và voucher đổi từ điểm thưởng của bạn.
        </p>
        <a href="<?= getUrl('login.php') ?>" class="btn btn-primary" style="padding:0.75rem 2rem; font-weight:750; border-radius:12px;">
          Đăng Nhập Ngay
        </a>
      </div>
    <?php else: ?>
      <!-- Toolbar: Filter Chips cho Ví Voucher & Search -->
      <div class="voucher-toolbar-wrap">
        <div class="voucher-filter-group">
          <span style="font-size: 0.86rem; font-weight: 850; color: #334155; margin-right: 0.25rem;">Lọc ví:</span>
          <button type="button" class="wallet-filter-pill active" data-type="all">Tất Cả (<?= count($walletVouchers) ?>)</button>
          <button type="button" class="wallet-filter-pill" data-type="percentage">🔥 Giảm %</button>
          <button type="button" class="wallet-filter-pill" data-type="fixed">💰 Giảm Tiền</button>
          <button type="button" class="wallet-filter-pill" data-type="free_shipping">🚚 Freeship</button>
          <button type="button" class="wallet-filter-pill" data-type="bogo">🍔 Mua 1 Tặng 1</button>
        </div>

        <div class="voucher-search-box">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          <input type="text" id="voucher-search-wallet" placeholder="Tìm kiếm trong ví của bạn..." autocomplete="off">
        </div>
      </div>

      <div id="wallet-vouchers-container" class="voucher-grid">
        <?php if (!empty($walletVouchers)): ?>
          <?php foreach ($walletVouchers as $cp): 
            $discDisplay = $cp['discount_display'] ?? '';
            $minOrder = (float)($cp['min_order_value'] ?? 0);
            $endDate = !empty($cp['effective_end_date']) ? date('d/m/Y', strtotime($cp['effective_end_date'])) : (!empty($cp['end_date']) ? date('d/m/Y', strtotime($cp['end_date'])) : 'Không thời hạn');
            $isUsed = !empty($cp['is_used']);
            $isExpired = !empty($cp['is_expired']);
            $vType = $cp['voucher_type'] ?? 'percentage';
            $typeBadge = $cp['type_badge'] ?? 'Ưu Đãi';
            $isBirthday = !empty($cp['is_birthday_voucher']) || (!empty($cp['source']) && $cp['source'] === 'birthday');
          ?>
            <article class="voucher-ticket-card <?= $isBirthday ? 'is-birthday-exclusive' : '' ?> <?= $isUsed ? 'is-used' : ($isExpired ? 'is-expired' : 'is-claimed') ?>" data-voucher-type="<?= escapeHtml($vType) ?>" data-code="<?= escapeHtml(strtolower($cp['code'])) ?>" data-title="<?= escapeHtml(strtolower($cp['title'] ?: '')) ?>">
              <div class="ticket-stub">
                <div class="ticket-discount-tag"><?= $discDisplay ?></div>
                <div class="ticket-discount-sub"><?= $typeBadge ?></div>
              </div>

              <div class="voucher-ticket-body ticket-body">
                <div class="ticket-header-row">
                  <div>
                    <div style="display:flex; align-items:center; gap:0.4rem; margin-bottom:0.35rem; flex-wrap:wrap;">
                      <?php if ($isBirthday): ?>
                        <span style="font-size:0.72rem; font-weight:800; background:#fef3c7; color:#b45309; border:1px solid #fde68a; padding:0.15rem 0.5rem; border-radius:6px;">
                          🎂 Quà Sinh Nhật
                        </span>
                      <?php endif; ?>
                      <span class="ticket-type-tag <?= escapeHtml($vType) ?>">
                        <?= $typeBadge ?>
                      </span>
                    </div>
                    <h3 class="ticket-title"><?= escapeHtml($cp['title'] ?: 'Mã Ưu Đãi FASTFOOD') ?></h3>
                  </div>

                  <?php if ($isUsed): ?>
                    <span class="ticket-status-pill used">Đã sử dụng</span>
                  <?php elseif ($isExpired): ?>
                    <span class="ticket-status-pill used">Hết hạn</span>
                  <?php else: ?>
                    <span class="ticket-status-pill saved">✓ Sẵn sàng</span>
                  <?php endif; ?>
                </div>

                <div class="ticket-meta-info">
                  <div class="ticket-condition">
                    <span>📌</span>
                    <span><?= $minOrder > 0 ? 'Đơn tối thiểu ' . number_format($minOrder, 0, ',', '.') . 'đ' : 'Áp dụng cho mọi đơn hàng' ?></span>
                  </div>
                  <div class="ticket-expiry">
                    <span>⏳</span>
                    <span>HSD: <?= $endDate ?></span>
                  </div>
                </div>

                <div class="ticket-actions-strip">
                  <span class="ticket-code-pill">
                    🎟️ <?= escapeHtml($cp['code']) ?>
                  </span>

                  <div style="display:flex; gap:0.4rem; align-items:center;">
                    <button type="button" class="ticket-btn-action ticket-btn-copy js-copy-voucher-code" 
                            data-code="<?= escapeHtml($cp['code']) ?>" title="Sao chép mã">
                      📋 Sao chép
                    </button>
                    <?php if (!$isUsed && !$isExpired): ?>
                      <a href="<?= getUrl('cart.php') ?>" class="ticket-btn-action ticket-btn-use">
                        Dùng ngay →
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="grid-column: 1 / -1; text-align:center; padding:3.5rem 2rem; background:#fff; border-radius:20px; border:1px dashed #fed7aa;">
            <div style="font-size:3.5rem; margin-bottom:1rem;">📭</div>
            <h3 style="font-size:1.3rem; font-weight:800; color:#1e293b;">Ví voucher của bạn đang trống</h3>
            <p style="color:var(--text-muted); margin-bottom:1.5rem;">Hãy qua tab "Săn Voucher Hot" để lưu các mã ưu đãi hấp dẫn vào ví nhé!</p>
            <button type="button" class="btn btn-primary" onclick="document.querySelector('.voucher-tab-btn[data-target=\'tab-hunt\']').click()" style="padding:0.75rem 1.75rem; font-weight:750; border-radius:12px;">
              🎁 Khám Phá Sàn Voucher
            </button>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- 5. TAB 3: ĐỔI ĐIỂM LẤY VOUCHER -->
  <div id="tab-loyalty" class="voucher-tab-pane" style="display:none;" role="tabpanel">
    <div class="voucher-grid">
      <?php if (!empty($voucherPackages)): ?>
        <?php foreach ($voucherPackages as $pkg): 
          $canExchange = ($userId > 0 && $userPoints >= (int)$pkg['points_cost']);
        ?>
          <div class="loyalty-package-card">
            <div class="loyalty-pkg-header">
              <span class="loyalty-coin-badge">🪙 <?= number_format($pkg['points_cost']) ?> Điểm</span>
              <span style="font-size:0.75rem; font-weight:750; color:#15803d; background:#dcfce7; padding:0.2rem 0.5rem; border-radius:6px;">
                Tiền mặt
              </span>
            </div>

            <div class="loyalty-pkg-body">
              <div class="loyalty-pkg-value">
                <?= number_format($pkg['voucher_value'], 0, ',', '.') ?>đ
              </div>
              <h4 class="loyalty-pkg-name"><?= escapeHtml($pkg['package_name']) ?></h4>
              <p class="loyalty-pkg-desc"><?= escapeHtml($pkg['description']) ?></p>
            </div>

            <div class="loyalty-pkg-footer">
              <?php if (!$userId): ?>
                <a href="<?= getUrl('login.php') ?>" class="btn btn-outline" style="width:100%; font-size:0.85rem; font-weight:750;">
                  Đăng nhập để đổi
                </a>
              <?php elseif ($canExchange): ?>
                <button type="button" class="btn btn-primary js-exchange-voucher-btn" 
                        data-pkg-id="<?= (int)$pkg['id'] ?>" 
                        data-cost="<?= (int)$pkg['points_cost'] ?>"
                        data-name="<?= escapeHtml($pkg['package_name']) ?>"
                        style="width:100%; font-size:0.85rem; font-weight:750;">
                  🪙 Đổi Ngay
                </button>
              <?php else: ?>
                <button type="button" class="btn" style="width:100%; font-size:0.85rem; font-weight:750; background:#f1f5f9; color:#94a3b8; border:none; cursor:not-allowed;" disabled>
                  Thiếu <?= number_format($pkg['points_cost'] - $userPoints) ?> điểm
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</main>

<style>
.voucher-filter-pill, .wallet-filter-pill {
  padding: 0.4rem 0.85rem;
  border-radius: 20px;
  border: 1.5px solid #fed7aa;
  background: #ffffff;
  color: #9a3412;
  font-size: 0.82rem;
  font-weight: 750;
  cursor: pointer;
  transition: all 0.18s ease;
}
.voucher-filter-pill:hover, .wallet-filter-pill:hover {
  border-color: #ea580c;
  background: #fff7ed;
}
.voucher-filter-pill.active, .wallet-filter-pill.active {
  background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
  border-color: #ea580c;
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(234, 88, 12, 0.25);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // 1. Chuyển Tab (Săn Voucher / Ví Của Tôi / Đổi Điểm)
  const tabs = document.querySelectorAll('.voucher-tab-btn');
  const panes = document.querySelectorAll('.voucher-tab-pane');

  function activateTab(targetId) {
    tabs.forEach(btn => {
      const active = (btn.dataset.target === targetId);
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    panes.forEach(pane => {
      if (pane.id === targetId) {
        pane.style.display = 'block';
        pane.classList.add('active');
      } else {
        pane.style.display = 'none';
        pane.classList.remove('active');
      }
    });

    if (history.replaceState) {
      history.replaceState(null, '', '#' + targetId);
    }
  }

  tabs.forEach(btn => {
    btn.addEventListener('click', () => activateTab(btn.dataset.target));
  });

  // Tự động mở tab từ URL Hash
  const hash = window.location.hash ? window.location.hash.substring(1) : '';
  if (hash && document.getElementById(hash)) {
    activateTab(hash);
  }

  // Helper: Toast thông báo xịn xò
  function showVoucherToast(msg, icon = '🎉') {
    let container = document.getElementById('voucher-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'voucher-toast-container';
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = 'voucher-toast';
    toast.innerHTML = `<span style="font-size:1.25rem;">${icon}</span> <span>${escapeHtml(msg)}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 3200);
  }

  function escapeHtml(str) {
    return String(str || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
  }

  // 2. Lọc Voucher Sàn Săn Voucher (Filter Pills & Realtime Search)
  let activeHuntType = 'all';
  const huntSearch = document.getElementById('voucher-search-hunt');

  function filterHuntCards() {
    const query = (huntSearch?.value || '').trim().toLowerCase();
    document.querySelectorAll('#public-vouchers-grid .voucher-ticket-card').forEach(card => {
      const matchType = (activeHuntType === 'all' || card.dataset.voucherType === activeHuntType);
      const code = card.dataset.code || '';
      const title = card.dataset.title || '';
      const matchQuery = !query || code.includes(query) || title.includes(query);
      card.style.display = (matchType && matchQuery) ? 'flex' : 'none';
    });
  }

  document.querySelectorAll('.voucher-filter-pill').forEach(pill => {
    pill.addEventListener('click', () => {
      document.querySelectorAll('.voucher-filter-pill').forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      activeHuntType = pill.dataset.type;
      filterHuntCards();
    });
  });

  huntSearch?.addEventListener('input', filterHuntCards);

  // 3. Lọc Ví Voucher Của Tôi (Filter Pills & Realtime Search)
  let activeWalletType = 'all';
  const walletSearch = document.getElementById('voucher-search-wallet');

  function filterWalletCards() {
    const query = (walletSearch?.value || '').trim().toLowerCase();
    document.querySelectorAll('#wallet-vouchers-container .voucher-ticket-card').forEach(card => {
      const matchType = (activeWalletType === 'all' || card.dataset.voucherType === activeWalletType);
      const code = card.dataset.code || '';
      const title = card.dataset.title || '';
      const matchQuery = !query || code.includes(query) || title.includes(query);
      card.style.display = (matchType && matchQuery) ? 'flex' : 'none';
    });
  }

  document.querySelectorAll('.wallet-filter-pill').forEach(pill => {
    pill.addEventListener('click', () => {
      document.querySelectorAll('.wallet-filter-pill').forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      activeWalletType = pill.dataset.type;
      filterWalletCards();
    });
  });

  walletSearch?.addEventListener('input', filterWalletCards);

  // 4. Copy Voucher Code with Toast
  document.querySelectorAll('.js-copy-voucher-code').forEach(btn => {
    btn.addEventListener('click', async () => {
      const code = btn.dataset.code;
      if (!code) return;
      try {
        await navigator.clipboard.writeText(code);
        const originalText = btn.innerHTML;
        btn.innerHTML = '✓ Đã chép';
        btn.style.color = '#16a34a';
        btn.style.borderColor = '#16a34a';
        showVoucherToast(`Đã sao chép mã ${code} vào bộ nhớ tạm!`, '📋');
        setTimeout(() => {
          btn.innerHTML = originalText;
          btn.style.color = '';
          btn.style.borderColor = '';
        }, 2000);
      } catch (_) {
        prompt('Sao chép mã voucher:', code);
      }
    });
  });

  // 5. 1-Click Claim Voucher to Wallet
  document.querySelectorAll('.js-claim-voucher-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const voucherId = btn.dataset.voucherId;
      const code = btn.dataset.code;
      if (!voucherId) return;

      btn.disabled = true;
      btn.textContent = 'Đang lưu...';

      try {
        const res = await fetch('api/vouchers.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'claim', voucher_id: parseInt(voucherId, 10) })
        });
        const data = await res.json();

        if (data.success) {
          btn.className = 'ticket-btn-action ticket-btn-claimed';
          btn.innerHTML = '✓ Đã trong ví';
          
          const badge = document.getElementById('tab-wallet-badge');
          if (badge && typeof data.data?.wallet_count === 'number') {
            badge.textContent = data.data.wallet_count;
          }
          const statBadge = document.getElementById('stat-wallet-count');
          if (statBadge && typeof data.data?.wallet_count === 'number') {
            statBadge.textContent = data.data.wallet_count;
          }

          showVoucherToast(data.message || `Đã lưu voucher ${code} vào ví của bạn thành công!`, '🎉');
        } else {
          showVoucherToast(data.message || data.error || 'Không thể lưu voucher.', '⚠️');
          btn.disabled = false;
          btn.innerHTML = '🎁 Lưu mã';
        }
      } catch (err) {
        showVoucherToast('Lỗi kết nối máy chủ: ' + err.message, '⚠️');
        btn.disabled = false;
        btn.innerHTML = '🎁 Lưu mã';
      }
    });
  });

  // 6. Exchange Points for Voucher
  document.querySelectorAll('.js-exchange-voucher-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const pkgId = btn.dataset.pkgId;
      const cost = btn.dataset.cost;
      const name = btn.dataset.name;

      if (!confirm(`Bạn có chắc chắn muốn dùng ${cost} điểm để đổi ${name}?`)) return;

      btn.disabled = true;
      btn.textContent = 'Đang đổi...';

      try {
        const res = await fetch('api/loyalty.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'exchange_voucher', package_id: parseInt(pkgId, 10) })
        });
        const data = await res.json();

        if (data.success) {
          alert(`Đổi thành công! Mã ưu đãi của bạn là: ${data.data.coupon_code}. Mã đã được lưu vào Ví Voucher!`);
          window.location.reload();
        } else {
          alert(data.message || data.error || 'Đổi voucher thất bại.');
          btn.disabled = false;
          btn.textContent = '🪙 Đổi ngay';
        }
      } catch (err) {
        alert('Lỗi kết nối máy chủ: ' + err.message);
        btn.disabled = false;
        btn.textContent = '🪙 Đổi ngay';
      }
    });
  });
});
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
