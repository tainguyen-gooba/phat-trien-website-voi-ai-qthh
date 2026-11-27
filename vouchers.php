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
$publicVouchers     = getAllPublicVouchers($userId);
$huntAvailableCount = getPublicHuntAvailableCount($publicVouchers);
$walletVouchers     = $userId > 0 ? getUserWalletVouchers($userId) : [];
$walletCount        = $userId > 0 ? getUserVoucherCount($userId) : 0;

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
            <div>Điểm tích lũy: <strong><span id="stat-user-points"><?= number_format($userPoints) ?></span> pts</strong> (Hạng <strong id="stat-user-tier"><?= escapeHtml($userTier) ?></strong>)</div>
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
      <svg class="voucher-ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="20 12 20 22 4 22 4 12"></polyline>
        <rect x="2" y="7" width="20" height="5"></rect>
        <line x1="12" y1="22" x2="12" y2="7"></line>
        <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path>
        <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path>
      </svg>
      <span>Săn Voucher Hot</span>
      <span class="voucher-tab-badge" id="tab-hunt-badge"><?= $huntAvailableCount ?></span>
    </button>
    <button type="button" class="voucher-tab-btn" data-target="tab-wallet" role="tab" aria-selected="false">
      <svg class="voucher-ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"></path>
        <path d="M13 5v2"></path>
        <path d="M13 17v2"></path>
        <path d="M13 11v2"></path>
      </svg>
      <span>Ví Voucher Của Tôi</span>
      <span class="voucher-tab-badge" id="tab-wallet-badge"><?= $walletCount ?></span>
    </button>
    <button type="button" class="voucher-tab-btn" data-target="tab-loyalty" role="tab" aria-selected="false">
      <svg class="voucher-ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="9"></circle>
        <path d="M12 7v10"></path>
        <path d="M15 9.5a2.5 2.5 0 0 0-5 0c0 1.5 5 1.5 5 3a2.5 2.5 0 0 1-5 0"></path>
      </svg>
      <span>Đổi Điểm Lấy Voucher</span>
      <span class="voucher-tab-badge"><?= count($voucherPackages) ?></span>
    </button>
  </nav>

  <!-- 3. TAB 1: SĂN VOUCHER HOT -->
  <div id="tab-hunt" class="voucher-tab-pane active" role="tabpanel">
    
    <!-- Toolbar: Filter Chips theo 4 loại voucher & Realtime Search -->
    <div class="voucher-toolbar-wrap">
      <div class="voucher-filter-group">
        <span style="font-size: 0.86rem; font-weight: 850; color: #334155; margin-right: 0.25rem;">Phân loại:</span>
        <button type="button" class="voucher-filter-pill active" id="hunt-filter-all-btn" data-type="all">Tất Cả (<?= count($publicVouchers) ?>)</button>
        <button type="button" class="voucher-filter-pill" data-type="percentage">
          <svg class="voucher-ui-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="5" x2="5" y2="19"></line><circle cx="6.5" cy="6.5" r="2.5"></circle><circle cx="17.5" cy="17.5" r="2.5"></circle></svg>
          <span>Giảm %</span>
        </button>
        <button type="button" class="voucher-filter-pill" data-type="fixed">
          <svg class="voucher-ui-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg>
          <span>Giảm Tiền Mặt</span>
        </button>
        <button type="button" class="voucher-filter-pill" data-type="free_shipping">
          <svg class="voucher-ui-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13" rx="1"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
          <span>Freeship 0đ</span>
        </button>
        <button type="button" class="voucher-filter-pill" data-type="bogo">
          <svg class="voucher-ui-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
          <span>Mua 1 Tặng 1</span>
        </button>
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
          $usageLimit = (int)($cp['usage_limit'] ?? 0);
          $usageCount = (int)($cp['effective_usage_count'] ?? $cp['usage_count'] ?? 0);
          $usageMetrics = formatVoucherUsageMetrics($usageCount, $usageLimit);
          $usagePct = $usageMetrics['usage_pct'];
          $usageBarWidth = $usageMetrics['bar_width_pct'];
          $remaining = $usageMetrics['remaining'];
          $isAvailable = isset($cp['is_available']) ? !empty($cp['is_available']) : ($usageLimit === 0 || $usageCount < $usageLimit);
          $perUserLimit = (int)($cp['effective_user_limit'] ?? getEffectivePerUserLimit($cp));
          $isHiddenCode = $isUsed;
          $displayCode = $isHiddenCode ? maskVoucherCode((string)$cp['code']) : (string)$cp['code'];
          $safeDataCode = $isHiddenCode ? strtolower($displayCode) : strtolower((string)$cp['code']);
        ?>
          <article class="voucher-ticket-card <?= $isClaimed ? 'is-claimed' : '' ?> <?= $isUsed ? 'is-used' : '' ?>" data-voucher-id="<?= (int)$cp['id'] ?>" data-voucher-type="<?= escapeHtml($vType) ?>" data-code="<?= escapeHtml($safeDataCode) ?>" data-title="<?= escapeHtml(strtolower($cp['title'] ?: '')) ?>">
            <div class="ticket-stub">
              <div class="ticket-discount-tag"><?= $discDisplay ?></div>
              <div class="ticket-discount-sub"><?= $typeBadge ?></div>
            </div>

            <div class="ticket-body">
              <div class="ticket-header-row">
                <div>
                  <div style="display:flex; align-items:center; gap:0.35rem; margin-bottom:0.35rem; flex-wrap:wrap;">
                    <span class="ticket-type-tag <?= escapeHtml($vType) ?>">
                      <?= $typeBadge ?>
                    </span>
                    <?php if ($reqTier !== 'ALL'): ?>
                      <span class="ticket-tier-badge">
                        👑 Hạng <?= $reqTier ?>+
                      </span>
                    <?php endif; ?>
                    <?php if ($perUserLimit === 1): ?>
                      <span class="ticket-tier-badge" style="background:#f8fafc; color:#475569; border-color:#cbd5e1;">
                        🔒 1 lần/tài khoản
                      </span>
                    <?php endif; ?>
                    <?php if (($cp['applicable_scope'] ?? 'all') === 'specific' && !empty($cp['applicable_label'])): ?>
                      <span class="ticket-tier-badge" style="background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe;">
                        🍽️ <?= escapeHtml($cp['applicable_label']) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                  <h3 class="ticket-title" title="<?= escapeHtml($cp['title'] ?: 'Ưu Đãi Đặc Biệt FASTFOOD') ?>"><?= escapeHtml($cp['title'] ?: 'Ưu Đãi Đặc Biệt FASTFOOD') ?></h3>
                </div>

                <?php if ($isUsed): ?>
                  <span class="ticket-status-pill used">Đã dùng</span>
                <?php elseif (!$isAvailable): ?>
                  <span class="ticket-status-pill used">Hết lượt</span>
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

                <div class="ticket-bottom-stack">
                  <?php if ($usageLimit > 0): ?>
                    <div class="ticket-quota-wrap">
                      <div class="ticket-quota-head">
                        <span>Đã dùng <?= escapeHtml($usagePct) ?>% (<?= number_format($usageCount) ?>/<?= number_format($usageLimit) ?>)</span>
                        <span><?= $remaining > 0 ? 'Còn ' . number_format($remaining) . ' lượt' : 'Đã hết lượt' ?></span>
                      </div>
                      <div class="ticket-quota-bar">
                        <div class="ticket-quota-progress" style="width: <?= $usageBarWidth ?>%;"></div>
                      </div>
                    </div>
                  <?php endif; ?>

                  <div class="ticket-code-row">
                    <?php if ($isHiddenCode): ?>
                      <span class="ticket-code-pill is-masked" aria-label="Mã voucher đã bị ẩn" title="Mã đã sử dụng không thể sao chép">
                        🔒 <?= escapeHtml($displayCode) ?>
                      </span>
                    <?php else: ?>
                      <span class="ticket-code-pill" title="Mã voucher">
                        🎟️ <?= escapeHtml($displayCode) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="ticket-actions-strip">
                <?php if ($isUsed): ?>
                  <button type="button" class="ticket-btn-action ticket-btn-used-disabled" disabled title="Bạn đã sử dụng mã này (tối đa 1 lần/tài khoản)">
                    ✓ Đã dùng
                  </button>
                <?php else: ?>
                  <button type="button" class="ticket-btn-action ticket-btn-copy js-copy-voucher-code" 
                          data-code="<?= escapeHtml($cp['code']) ?>" title="Sao chép mã">
                    📋 Sao chép
                  </button>

                  <?php if (!$userId): ?>
                    <a href="<?= getUrl('login.php') ?>" class="ticket-btn-action ticket-btn-claim">
                      🎁 Lưu mã
                    </a>
                  <?php elseif (!$tierEligible): ?>
                    <button type="button" class="ticket-btn-action ticket-btn-locked-tier" title="Cần đạt hạng <?= $reqTier ?> để lưu voucher này" disabled>
                      Cần Hạng <?= $reqTier ?>
                    </button>
                  <?php elseif (!$isAvailable): ?>
                    <button type="button" class="ticket-btn-action ticket-btn-used-disabled" style="flex:1.15 1 0;" disabled>
                      Hết lượt
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
                <?php endif; ?>
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
          <button type="button" class="wallet-filter-pill active" id="wallet-filter-all-btn" data-type="all">Tất Cả (<?= count($walletVouchers) ?>)</button>
          <button type="button" class="wallet-filter-pill" data-type="percentage">
            <svg class="voucher-ui-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="5" x2="5" y2="19"></line><circle cx="6.5" cy="6.5" r="2.5"></circle><circle cx="17.5" cy="17.5" r="2.5"></circle></svg>
            <span>Giảm %</span>
          </button>
          <button type="button" class="wallet-filter-pill" data-type="fixed">
            <svg class="voucher-ui-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg>
            <span>Giảm Tiền</span>
          </button>
          <button type="button" class="wallet-filter-pill" data-type="free_shipping">
            <svg class="voucher-ui-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13" rx="1"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
            <span>Freeship</span>
          </button>
          <button type="button" class="wallet-filter-pill" data-type="bogo">
            <svg class="voucher-ui-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
            <span>Mua 1 Tặng 1</span>
          </button>
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
            $isTierUpgrade = (strpos((string)($cp['code'] ?? ''), 'UP-') === 0);
            $isWalletHidden = $isUsed || $isExpired;
            $walletDisplayCode = $isWalletHidden ? maskVoucherCode((string)$cp['code']) : (string)$cp['code'];
            $walletDataCode = $isWalletHidden ? strtolower($walletDisplayCode) : strtolower((string)$cp['code']);
          ?>
            <article class="voucher-ticket-card <?= $isBirthday ? 'is-birthday-exclusive' : '' ?> <?= $isUsed ? 'is-used' : ($isExpired ? 'is-expired' : 'is-claimed') ?>" data-voucher-id="<?= (int)$cp['id'] ?>" data-voucher-type="<?= escapeHtml($vType) ?>" data-code="<?= escapeHtml($walletDataCode) ?>" data-title="<?= escapeHtml(strtolower($cp['title'] ?: '')) ?>">
              <div class="ticket-stub">
                <div class="ticket-discount-tag"><?= $discDisplay ?></div>
                <div class="ticket-discount-sub"><?= $typeBadge ?></div>
              </div>

              <div class="voucher-ticket-body ticket-body">
                <div class="ticket-header-row">
                  <div>
                    <div style="display:flex; align-items:center; gap:0.35rem; margin-bottom:0.35rem; flex-wrap:wrap;">
                      <?php if ($isBirthday): ?>
                        <span style="font-size:0.68rem; font-weight:800; background:#fef3c7; color:#b45309; border:1px solid #fde68a; padding:0.14rem 0.45rem; border-radius:6px;">
                          🎂 Quà Sinh Nhật
                        </span>
                      <?php endif; ?>
                      <?php if ($isTierUpgrade): ?>
                        <span class="ticket-tier-badge">
                          👑 Quà Thăng Hạng
                        </span>
                        <span class="ticket-tier-badge" style="background:#f8fafc; color:#475569; border-color:#cbd5e1;">
                          🔒 1 lần/tài khoản
                        </span>
                      <?php elseif (($cp['source'] ?? '') === 'loyalty'): ?>
                        <span style="font-size:0.68rem; font-weight:800; background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; padding:0.14rem 0.45rem; border-radius:6px;">
                          🪙 Đổi Điểm
                        </span>
                      <?php endif; ?>
                      <span class="ticket-type-tag <?= escapeHtml($vType) ?>">
                        <?= $typeBadge ?>
                      </span>
                      <?php if (($cp['applicable_scope'] ?? 'all') === 'specific' && !empty($cp['applicable_label'])): ?>
                        <span class="ticket-tier-badge" style="background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe;">
                          🍽️ <?= escapeHtml($cp['applicable_label']) ?>
                        </span>
                      <?php endif; ?>
                    </div>
                    <h3 class="ticket-title" title="<?= escapeHtml($cp['title'] ?: 'Mã Ưu Đãi FASTFOOD') ?>"><?= escapeHtml($cp['title'] ?: 'Mã Ưu Đãi FASTFOOD') ?></h3>
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

                  <div class="ticket-bottom-stack">
                    <div class="ticket-code-row">
                      <?php if ($isWalletHidden): ?>
                        <span class="ticket-code-pill is-masked" aria-label="Mã voucher đã bị ẩn">
                          🔒 <?= escapeHtml($walletDisplayCode) ?>
                        </span>
                      <?php else: ?>
                        <span class="ticket-code-pill">
                          🎟️ <?= escapeHtml($walletDisplayCode) ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <div class="ticket-actions-strip">
                  <?php if ($isUsed): ?>
                    <button type="button" class="ticket-btn-action ticket-btn-used-disabled" disabled>
                      ✓ Đã sử dụng
                    </button>
                  <?php elseif ($isExpired): ?>
                    <button type="button" class="ticket-btn-action ticket-btn-used-disabled" disabled>
                      Hết hạn
                    </button>
                  <?php else: ?>
                    <button type="button" class="ticket-btn-action ticket-btn-copy js-copy-voucher-code" 
                            data-code="<?= escapeHtml($cp['code']) ?>" title="Sao chép mã">
                      📋 Sao chép
                    </button>
                    <a href="<?= getUrl('cart.php') ?>" class="ticket-btn-action ticket-btn-use">
                      Dùng ngay →
                    </a>
                  <?php endif; ?>
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
    <div class="loyalty-exchange-banner">
      <div class="loyalty-exchange-banner-text">
        <h3>🪙 Đổi Điểm Thưởng Lấy Mã Giảm Giá</h3>
        <p>Sử dụng điểm tích lũy từ các đơn hàng đã đặt để đổi thành mã ưu đãi (tự động lưu vào Ví Voucher của bạn).</p>
      </div>
      <div class="loyalty-exchange-balance-chip">
        <span>Số dư hiện tại:</span>
        <strong id="loyalty-balance-display"><?= $userId > 0 ? number_format($userPoints) . ' điểm' : 'Chưa đăng nhập' ?></strong>
      </div>
    </div>

    <div class="loyalty-exchange-grid">
      <?php if (!empty($voucherPackages)): ?>
        <?php foreach ($voucherPackages as $pkg): 
          $cost = (int)($pkg['points_cost'] ?? 0);
          $minOrderPkg = (float)($pkg['min_order_value'] ?? 0);
          $canExchange = ($userId > 0 && $userPoints >= $cost);
          $missingPts = max(0, $cost - $userPoints);
          $progressPct = ($userId > 0 && $cost > 0) ? min(100, round(($userPoints / $cost) * 100)) : 0;
          $pkgName = $pkg['name'] ?? $pkg['package_name'] ?? 'Voucher FASTFOOD';
        ?>
          <div class="loyalty-package-card <?= $canExchange ? 'is-ready' : '' ?>" data-pkg-id="<?= (int)$pkg['id'] ?>" data-pkg-cost="<?= $cost ?>" data-pkg-name="<?= escapeHtml($pkgName) ?>">
            <div class="loyalty-pkg-header">
              <span class="loyalty-coin-badge">🪙 Cần <?= number_format($cost) ?> điểm</span>
              <span class="loyalty-pkg-status-tag <?= $canExchange ? 'ready' : '' ?>">
                <?= $canExchange ? '✓ Đủ điểm đổi' : 'Ưu đãi đơn hàng' ?>
              </span>
            </div>

            <div class="loyalty-pkg-body">
              <h4 class="loyalty-pkg-name"><?= escapeHtml($pkgName) ?></h4>

              <ul class="loyalty-pkg-info-list">
                <li>
                  <span>🎁</span>
                  <span><?= escapeHtml($pkg['description'] ?? 'Áp dụng giảm trực tiếp khi thanh toán') ?></span>
                </li>
                <?php if ($minOrderPkg > 0): ?>
                  <li>
                    <span>📌</span>
                    <span>Điều kiện: Đơn hàng từ <?= number_format($minOrderPkg, 0, ',', '.') ?>đ</span>
                  </li>
                <?php endif; ?>
                <li>
                  <span>⏳</span>
                  <span>Lưu ngay vào Ví Voucher sau khi đổi thành công</span>
                </li>
              </ul>

              <?php if ($userId > 0): ?>
                <div class="loyalty-pkg-progress-wrap">
                  <div class="loyalty-pkg-progress-head">
                    <span>Điểm của bạn: <?= number_format(min($userPoints, $cost)) ?>/<?= number_format($cost) ?></span>
                    <span><?= $canExchange ? 'Sẵn sàng 100%' : 'Còn thiếu ' . number_format($missingPts) . ' điểm' ?></span>
                  </div>
                  <div class="loyalty-pkg-progress-bar">
                    <div class="loyalty-pkg-progress-fill <?= $canExchange ? 'is-full' : '' ?>" style="width: <?= $progressPct ?>%;"></div>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <div class="loyalty-pkg-footer">
              <?php if (!$userId): ?>
                <a href="<?= getUrl('login.php') ?>" class="loyalty-pkg-btn loyalty-pkg-btn-login">
                  Đăng nhập để đổi điểm
                </a>
              <?php elseif ($canExchange): ?>
                <button type="button" class="loyalty-pkg-btn loyalty-pkg-btn-exchange js-exchange-voucher-btn" 
                        data-pkg-id="<?= (int)$pkg['id'] ?>" 
                        data-cost="<?= $cost ?>"
                        data-name="<?= escapeHtml($pkgName) ?>">
                  🪙 Đổi Ngay (-<?= number_format($cost) ?> điểm)
                </button>
              <?php else: ?>
                <button type="button" class="loyalty-pkg-btn loyalty-pkg-btn-disabled" disabled>
                  Cần thêm <?= number_format($missingPts) ?> điểm để đổi
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

  const IS_LOGGED_IN = <?= $userId > 0 ? 'true' : 'false' ?>;
  const CART_URL = <?= json_encode(getUrl('cart.php')) ?>;
  const LOGIN_URL = <?= json_encode(getUrl('login.php')) ?>;
  const API_VOUCHERS_URL = <?= json_encode(getUrl('api/vouchers.php')) ?>;
  const API_LOYALTY_URL = <?= json_encode(getUrl('api/loyalty.php')) ?>;

  function formatVnd(n) {
    return Number(n || 0).toLocaleString('vi-VN') + 'đ';
  }

  function renderCodeRowHtml(cp, isHidden) {
    const codeText = escapeHtml(cp.display_code || cp.code || '');
    return `
      <div class="ticket-code-row">
        ${isHidden
          ? `<span class="ticket-code-pill is-masked" aria-label="Mã voucher đã bị ẩn" title="Mã đã sử dụng không thể sao chép">🔒 ${codeText}</span>`
          : `<span class="ticket-code-pill" title="Mã voucher">🎟️ ${codeText}</span>`
        }
      </div>
    `;
  }

  function renderPublicVoucherCardHtml(cp) {
    const reqTier = String(cp.min_member_tier || 'ALL').toUpperCase();
    const isClaimed = !!cp.is_saved;
    const isUsed = !!cp.is_used;
    const isAvailable = cp.is_available !== undefined ? !!cp.is_available : true;
    const tierEligible = cp.tier_eligible !== undefined ? !!cp.tier_eligible : true;
    const vType = cp.voucher_type || 'percentage';
    const typeBadge = cp.type_badge || 'Ưu Đãi';
    const discDisplay = cp.discount_display || '';
    const minOrder = Number(cp.min_order_value || 0);
    const maxDisc = Number(cp.max_discount_amount || 0);
    const usageLimit = Number(cp.usage_limit || 0);
    const usageCount = Number(cp.effective_usage_count ?? cp.usage_count ?? 0);
    const usagePct = cp.usage_pct ?? '0';
    const usageBarWidth = Number(cp.usage_bar_width || 0);
    const remaining = cp.remaining_uses !== null && cp.remaining_uses !== undefined ? Number(cp.remaining_uses) : Math.max(0, usageLimit - usageCount);
    const perUserLimit = Number(cp.effective_user_limit || 1);
    const displayCode = String(cp.display_code || cp.code || '');
    const safeDataCode = displayCode.toLowerCase();
    const title = cp.title || 'Ưu Đãi Đặc Biệt FASTFOOD';
    const endDate = cp.end_date_formatted || 'Không thời hạn';

    let statusPill = '';
    if (isUsed) {
      statusPill = '<span class="ticket-status-pill used">Đã dùng</span>';
    } else if (!isAvailable) {
      statusPill = '<span class="ticket-status-pill used">Hết lượt</span>';
    } else if (isClaimed) {
      statusPill = '<span class="ticket-status-pill saved">✓ Trong ví</span>';
    }

    let actionStripHtml = '';
    if (isUsed) {
      actionStripHtml = `
        <button type="button" class="ticket-btn-action ticket-btn-used-disabled" disabled title="Bạn đã sử dụng mã này (tối đa 1 lần/tài khoản)">
          ✓ Đã dùng
        </button>
      `;
    } else {
      const copyBtnHtml = `
        <button type="button" class="ticket-btn-action ticket-btn-copy js-copy-voucher-code"
                data-code="${escapeHtml(displayCode)}" title="Sao chép mã">
          📋 Sao chép
        </button>
      `;
      let mainBtnHtml = '';
      if (!IS_LOGGED_IN) {
        mainBtnHtml = `<a href="${LOGIN_URL}" class="ticket-btn-action ticket-btn-claim">🎁 Lưu mã</a>`;
      } else if (!tierEligible) {
        mainBtnHtml = `<button type="button" class="ticket-btn-action ticket-btn-locked-tier" title="Cần đạt hạng ${escapeHtml(reqTier)} để lưu voucher này" disabled>Cần Hạng ${escapeHtml(reqTier)}</button>`;
      } else if (!isAvailable) {
        mainBtnHtml = `<button type="button" class="ticket-btn-action ticket-btn-used-disabled" style="flex:1.15 1 0;" disabled>Hết lượt</button>`;
      } else if (isClaimed) {
        mainBtnHtml = `<a href="${CART_URL}" class="ticket-btn-action ticket-btn-use">Dùng ngay →</a>`;
      } else {
        mainBtnHtml = `<button type="button" class="ticket-btn-action ticket-btn-claim js-claim-voucher-btn" data-voucher-id="${Number(cp.id)}" data-code="${escapeHtml(displayCode)}">🎁 Lưu mã</button>`;
      }
      actionStripHtml = copyBtnHtml + mainBtnHtml;
    }

    const condLine = (minOrder > 0 ? 'Đơn tối thiểu ' + formatVnd(minOrder) : 'Áp dụng cho mọi đơn hàng') +
      (maxDisc > 0 ? ' (Tối đa ' + formatVnd(maxDisc) + ')' : '');

    const quotaHtml = usageLimit > 0 ? `
      <div class="ticket-quota-wrap">
        <div class="ticket-quota-head">
          <span>Đã dùng ${escapeHtml(usagePct)}% (${usageCount.toLocaleString('vi-VN')}/${usageLimit.toLocaleString('vi-VN')})</span>
          <span>${remaining > 0 ? 'Còn ' + remaining.toLocaleString('vi-VN') + ' lượt' : 'Đã hết lượt'}</span>
        </div>
        <div class="ticket-quota-bar">
          <div class="ticket-quota-progress" style="width: ${usageBarWidth}%;"></div>
        </div>
      </div>
    ` : '';

    return `
      <article class="voucher-ticket-card ${isClaimed ? 'is-claimed' : ''} ${isUsed ? 'is-used' : ''}" data-voucher-id="${Number(cp.id)}" data-voucher-type="${escapeHtml(vType)}" data-code="${escapeHtml(safeDataCode)}" data-title="${escapeHtml(title.toLowerCase())}">
        <div class="ticket-stub">
          <div class="ticket-discount-tag">${discDisplay}</div>
          <div class="ticket-discount-sub">${escapeHtml(typeBadge)}</div>
        </div>
        <div class="ticket-body">
          <div class="ticket-header-row">
            <div>
              <div style="display:flex; align-items:center; gap:0.35rem; margin-bottom:0.35rem; flex-wrap:wrap;">
                <span class="ticket-type-tag ${escapeHtml(vType)}">${escapeHtml(typeBadge)}</span>
                ${reqTier !== 'ALL' ? `<span class="ticket-tier-badge">👑 Hạng ${escapeHtml(reqTier)}+</span>` : ''}
                ${perUserLimit === 1 ? '<span class="ticket-tier-badge" style="background:#f8fafc; color:#475569; border-color:#cbd5e1;">🔒 1 lần/tài khoản</span>' : ''}
                ${cp.applicable_scope === 'specific' && cp.applicable_label ? `<span class="ticket-tier-badge" style="background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe;">🍽️ ${escapeHtml(cp.applicable_label)}</span>` : ''}
              </div>
              <h3 class="ticket-title" title="${escapeHtml(title)}">${escapeHtml(title)}</h3>
            </div>
            ${statusPill}
          </div>
          <div class="ticket-meta-info">
            <div class="ticket-condition"><span>📌</span><span>${condLine}</span></div>
            <div class="ticket-expiry"><span>⏳</span><span>HSD: ${escapeHtml(endDate)}</span></div>
            <div class="ticket-bottom-stack">
              ${quotaHtml}
              ${renderCodeRowHtml(cp, isUsed)}
            </div>
          </div>
          <div class="ticket-actions-strip">${actionStripHtml}</div>
        </div>
      </article>
    `;
  }

  function renderWalletVoucherCardHtml(cp) {
    const isUsed = !!cp.is_used;
    const isExpired = !!cp.is_expired;
    const isHidden = isUsed || isExpired;
    const vType = cp.voucher_type || 'percentage';
    const typeBadge = cp.type_badge || 'Ưu Đãi';
    const discDisplay = cp.discount_display || '';
    const minOrder = Number(cp.min_order_value || 0);
    const isBirthday = !!cp.is_birthday_voucher || cp.source === 'birthday';
    const displayCode = String(cp.display_code || cp.code || '');
    const title = cp.title || 'Mã Ưu Đãi FASTFOOD';
    const endDate = cp.end_date_formatted || 'Không thời hạn';

    let statusPill = '<span class="ticket-status-pill saved">✓ Sẵn sàng</span>';
    if (isUsed) statusPill = '<span class="ticket-status-pill used">Đã sử dụng</span>';
    else if (isExpired) statusPill = '<span class="ticket-status-pill used">Hết hạn</span>';

    let actionStripHtml = '';
    if (isUsed) {
      actionStripHtml = '<button type="button" class="ticket-btn-action ticket-btn-used-disabled" disabled>✓ Đã sử dụng</button>';
    } else if (isExpired) {
      actionStripHtml = '<button type="button" class="ticket-btn-action ticket-btn-used-disabled" disabled>Hết hạn</button>';
    } else {
      actionStripHtml = `
        <button type="button" class="ticket-btn-action ticket-btn-copy js-copy-voucher-code"
                data-code="${escapeHtml(displayCode)}" title="Sao chép mã">
          📋 Sao chép
        </button>
        <a href="${CART_URL}" class="ticket-btn-action ticket-btn-use">Dùng ngay →</a>
      `;
    }

    return `
      <article class="voucher-ticket-card ${isBirthday ? 'is-birthday-exclusive' : ''} ${isUsed ? 'is-used' : (isExpired ? 'is-expired' : 'is-claimed')}" data-voucher-id="${Number(cp.id)}" data-voucher-type="${escapeHtml(vType)}" data-code="${escapeHtml(displayCode.toLowerCase())}" data-title="${escapeHtml(title.toLowerCase())}">
        <div class="ticket-stub">
          <div class="ticket-discount-tag">${discDisplay}</div>
          <div class="ticket-discount-sub">${escapeHtml(typeBadge)}</div>
        </div>
        <div class="voucher-ticket-body ticket-body">
          <div class="ticket-header-row">
            <div>
              <div style="display:flex; align-items:center; gap:0.35rem; margin-bottom:0.35rem; flex-wrap:wrap;">
                ${isBirthday ? '<span style="font-size:0.68rem; font-weight:800; background:#fef3c7; color:#b45309; border:1px solid #fde68a; padding:0.14rem 0.45rem; border-radius:6px;">🎂 Quà Sinh Nhật</span>' : ''}
                ${cp.is_tier_upgrade ? '<span class="ticket-tier-badge">👑 Quà Thăng Hạng</span><span class="ticket-tier-badge" style="background:#f8fafc; color:#475569; border-color:#cbd5e1;">🔒 1 lần/tài khoản</span>' : (cp.source === 'loyalty' ? '<span style="font-size:0.68rem; font-weight:800; background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; padding:0.14rem 0.45rem; border-radius:6px;">🪙 Đổi Điểm</span>' : '')}
                <span class="ticket-type-tag ${escapeHtml(vType)}">${escapeHtml(typeBadge)}</span>
                ${cp.applicable_scope === 'specific' && cp.applicable_label ? `<span class="ticket-tier-badge" style="background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe;">🍽️ ${escapeHtml(cp.applicable_label)}</span>` : ''}
              </div>
              <h3 class="ticket-title" title="${escapeHtml(title)}">${escapeHtml(title)}</h3>
            </div>
            ${statusPill}
          </div>
          <div class="ticket-meta-info">
            <div class="ticket-condition"><span>📌</span><span>${minOrder > 0 ? 'Đơn tối thiểu ' + formatVnd(minOrder) : 'Áp dụng cho mọi đơn hàng'}</span></div>
            <div class="ticket-expiry"><span>⏳</span><span>HSD: ${escapeHtml(endDate)}</span></div>
            <div class="ticket-bottom-stack">${renderCodeRowHtml(cp, isHidden)}</div>
          </div>
          <div class="ticket-actions-strip">${actionStripHtml}</div>
        </div>
      </article>
    `;
  }

  function updateLoyaltyCardsUi(points) {
    if (!IS_LOGGED_IN || typeof points !== 'number') return;
    const statPts = document.getElementById('stat-user-points');
    if (statPts) statPts.textContent = points.toLocaleString('vi-VN');
    const balEl = document.getElementById('loyalty-balance-display');
    if (balEl) balEl.textContent = `${points.toLocaleString('vi-VN')} điểm`;

    document.querySelectorAll('.loyalty-package-card').forEach(card => {
      const cost = parseInt(card.dataset.pkgCost || '0', 10);
      const pkgId = parseInt(card.dataset.pkgId || '0', 10);
      const pkgName = card.dataset.pkgName || 'Voucher FASTFOOD';
      if (!cost) return;

      const canExchange = points >= cost;
      const missingPts = Math.max(0, cost - points);
      const progressPct = Math.min(100, Math.round((points / cost) * 100));

      card.classList.toggle('is-ready', canExchange);

      const statusTag = card.querySelector('.loyalty-pkg-status-tag');
      if (statusTag) {
        statusTag.classList.toggle('ready', canExchange);
        statusTag.textContent = canExchange ? '✓ Đủ điểm đổi' : 'Ưu đãi đơn hàng';
      }

      const headSpans = card.querySelectorAll('.loyalty-pkg-progress-head span');
      if (headSpans.length >= 2) {
        headSpans[0].textContent = `Điểm của bạn: ${Math.min(points, cost).toLocaleString('vi-VN')}/${cost.toLocaleString('vi-VN')}`;
        headSpans[1].textContent = canExchange ? 'Sẵn sàng 100%' : `Còn thiếu ${missingPts.toLocaleString('vi-VN')} điểm`;
      }

      const fillEl = card.querySelector('.loyalty-pkg-progress-fill');
      if (fillEl) {
        fillEl.classList.toggle('is-full', canExchange);
        fillEl.style.width = `${progressPct}%`;
      }

      const footerEl = card.querySelector('.loyalty-pkg-footer');
      if (footerEl) {
        if (canExchange) {
          footerEl.innerHTML = `<button type="button" class="loyalty-pkg-btn loyalty-pkg-btn-exchange js-exchange-voucher-btn" data-pkg-id="${pkgId}" data-cost="${cost}" data-name="${escapeHtml(pkgName)}">🪙 Đổi Ngay (-${cost.toLocaleString('vi-VN')} điểm)</button>`;
        } else {
          footerEl.innerHTML = `<button type="button" class="loyalty-pkg-btn loyalty-pkg-btn-disabled" disabled>Cần thêm ${missingPts.toLocaleString('vi-VN')} điểm để đổi</button>`;
        }
      }
    });
  }

  function applyVoucherStateSnapshot(state) {
    if (!state || typeof state !== 'object') return;

    if (typeof state.hunt_available_count === 'number') {
      const huntBadge = document.getElementById('tab-hunt-badge');
      if (huntBadge) huntBadge.textContent = state.hunt_available_count;
    }

    if (typeof state.wallet_count === 'number') {
      const walletBadge = document.getElementById('tab-wallet-badge');
      if (walletBadge) walletBadge.textContent = state.wallet_count;
      const statBadge = document.getElementById('stat-wallet-count');
      if (statBadge) statBadge.textContent = state.wallet_count;
    }

    if (Array.isArray(state.public_vouchers)) {
      const huntAllBtn = document.getElementById('hunt-filter-all-btn');
      if (huntAllBtn) huntAllBtn.textContent = `Tất Cả (${state.public_vouchers.length})`;
      const huntGrid = document.getElementById('public-vouchers-grid');
      if (huntGrid) {
        huntGrid.innerHTML = state.public_vouchers.length
          ? state.public_vouchers.map(renderPublicVoucherCardHtml).join('')
          : '<div style="grid-column: 1 / -1; text-align:center; padding:3.5rem 2rem; background:#fff; border-radius:20px; border:1px dashed #fed7aa;"><div style="font-size:3.5rem; margin-bottom:1rem;">🎟️</div><h3 style="font-size:1.3rem; font-weight:800; color:#1e293b;">Hiện tại chưa có mã giảm giá công khai nào</h3><p style="color:var(--text-muted);">Hãy quay lại sau để săn các mã ưu đãi độc quyền từ FASTFOOD nhé!</p></div>';
        filterHuntCards();
      }
    }

    if (IS_LOGGED_IN && Array.isArray(state.wallet_vouchers)) {
      const walletAllBtn = document.getElementById('wallet-filter-all-btn');
      if (walletAllBtn) walletAllBtn.textContent = `Tất Cả (${state.wallet_vouchers.length})`;
      const walletGrid = document.getElementById('wallet-vouchers-container');
      if (walletGrid) {
        walletGrid.innerHTML = state.wallet_vouchers.length
          ? state.wallet_vouchers.map(renderWalletVoucherCardHtml).join('')
          : '<div style="grid-column: 1 / -1; text-align:center; padding:3.5rem 2rem; background:#fff; border-radius:20px; border:1px dashed #fed7aa;"><div style="font-size:3.5rem; margin-bottom:1rem;">📭</div><h3 style="font-size:1.3rem; font-weight:800; color:#1e293b;">Ví voucher của bạn đang trống</h3><p style="color:var(--text-muted); margin-bottom:1.5rem;">Hãy qua tab "Săn Voucher Hot" để lưu các mã ưu đãi hấp dẫn vào ví nhé!</p><button type="button" class="btn btn-primary" onclick="document.querySelector(\'.voucher-tab-btn[data-target=\\\'tab-hunt\\\']\').click()" style="padding:0.75rem 1.75rem; font-weight:750; border-radius:12px;">🎁 Khám Phá Sàn Voucher</button></div>';
        filterWalletCards();
      }
    }

    if (typeof state.user_points === 'number') {
      updateLoyaltyCardsUi(state.user_points);
    }
  }

  let syncInFlight = null;
  window.syncVouchersLiveState = async function() {
    if (syncInFlight) return syncInFlight;
    syncInFlight = (async () => {
      try {
        const res = await fetch(`${API_VOUCHERS_URL}?action=sync_state&_t=${Date.now()}`, {
          cache: 'no-store'
        });
        const json = await res.json();
        if (json.success && json.data) {
          applyVoucherStateSnapshot(json.data);
        }
      } catch (_) {}
      finally {
        syncInFlight = null;
      }
    })();
    return syncInFlight;
  };

  function broadcastVoucherSync() {
    try {
      new BroadcastChannel('fastfood_realtime').postMessage({ type: 'content:updated', collection: 'vouchers' });
    } catch (_) {}
    try {
      localStorage.setItem('ff_vouchers_dirty', String(Date.now()));
    } catch (_) {}
  }

  // Event delegation để toàn bộ nút Copy / Lưu mã / Đổi điểm hoạt động tức thì sau khi cập nhật DOM
  document.addEventListener('click', async (e) => {
    const copyBtn = e.target.closest('.js-copy-voucher-code');
    if (copyBtn) {
      if (copyBtn.disabled || copyBtn.closest('.voucher-ticket-card.is-used')) return;
      const code = copyBtn.dataset.code;
      if (!code) return;
      try {
        await navigator.clipboard.writeText(code);
        const originalText = copyBtn.innerHTML;
        copyBtn.innerHTML = '✓ Đã chép';
        copyBtn.style.color = '#16a34a';
        copyBtn.style.borderColor = '#16a34a';
        showVoucherToast(`Đã sao chép mã ${code} vào bộ nhớ tạm!`, '📋');
        setTimeout(() => {
          copyBtn.innerHTML = originalText;
          copyBtn.style.color = '';
          copyBtn.style.borderColor = '';
        }, 2000);
      } catch (_) {
        prompt('Sao chép mã voucher:', code);
      }
      return;
    }

    const claimBtn = e.target.closest('.js-claim-voucher-btn');
    if (claimBtn) {
      const voucherId = claimBtn.dataset.voucherId;
      const code = claimBtn.dataset.code;
      if (!voucherId || claimBtn.disabled) return;

      claimBtn.disabled = true;
      claimBtn.textContent = 'Đang lưu...';

      try {
        const res = await fetch(API_VOUCHERS_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'claim', voucher_id: parseInt(voucherId, 10) })
        });
        const data = await res.json();

        if (data.success) {
          applyVoucherStateSnapshot(data.data);
          broadcastVoucherSync();
          showVoucherToast(data.message || `Đã lưu voucher ${code} vào Ví Voucher của bạn!`, '🎉');
        } else {
          showVoucherToast(data.message || data.error || 'Không thể lưu voucher.', '⚠️');
          claimBtn.disabled = false;
          claimBtn.innerHTML = '🎁 Lưu mã';
        }
      } catch (err) {
        showVoucherToast('Lỗi kết nối máy chủ: ' + err.message, '⚠️');
        claimBtn.disabled = false;
        claimBtn.innerHTML = '🎁 Lưu mã';
      }
      return;
    }

    const exchBtn = e.target.closest('.js-exchange-voucher-btn');
    if (exchBtn) {
      const pkgId = exchBtn.dataset.pkgId;
      const cost = exchBtn.dataset.cost;
      const name = exchBtn.dataset.name;
      if (!pkgId || exchBtn.disabled) return;

      if (!confirm(`Bạn có chắc chắn muốn dùng ${cost} điểm để đổi ${name}?`)) return;

      exchBtn.disabled = true;
      exchBtn.textContent = 'Đang đổi...';

      try {
        const res = await fetch(API_LOYALTY_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'exchange_voucher', package_id: parseInt(pkgId, 10) })
        });
        const data = await res.json();

        if (data.success) {
          await window.syncVouchersLiveState();
          broadcastVoucherSync();
          showVoucherToast(`🎉 Đổi thành công mã ${data.data?.coupon_code || ''}! Đã tự động lưu vào Ví Voucher của bạn.`, '🎟️');
        } else {
          showVoucherToast(data.message || data.error || 'Đổi voucher thất bại.', '⚠️');
          exchBtn.disabled = false;
          exchBtn.textContent = `🪙 Đổi Ngay (-${Number(cost).toLocaleString('vi-VN')} điểm)`;
        }
      } catch (err) {
        showVoucherToast('Lỗi kết nối máy chủ: ' + err.message, '⚠️');
        exchBtn.disabled = false;
        exchBtn.textContent = `🪙 Đổi Ngay (-${Number(cost).toLocaleString('vi-VN')} điểm)`;
      }
    }
  });

  // Tự động đồng bộ khi quay lại từ thanh toán / đổi tab / tab khác đặt hàng xong
  window.addEventListener('pageshow', () => {
    window.syncVouchersLiveState();
  });
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) window.syncVouchersLiveState();
  });
  window.addEventListener('storage', (e) => {
    if (e.key === 'ff_vouchers_dirty') {
      window.syncVouchersLiveState();
    }
  });
  tabs.forEach(btn => {
    btn.addEventListener('click', () => window.syncVouchersLiveState());
  });
});
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
