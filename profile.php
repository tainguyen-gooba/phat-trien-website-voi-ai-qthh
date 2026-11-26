<?php
/**
 * FASTFOOD - Hồ sơ người dùng & Trung tâm Khách hàng Thân thiết VIP
 * File: profile.php
 */
require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/helpers/loyalty.php';

// 1. Enforce Authentication
requireLogin();

$currentUser = currentUser();
$userId = (int)$currentUser['id'];
$db = getDB();

$message = '';
$error = '';

// 2. Handle Profile Info Update (Full Name, Phone, Address)
$isUpdateProfile = ($_SERVER['REQUEST_METHOD'] === 'POST' && (
    (isset($_POST['action']) && $_POST['action'] === 'update_profile') ||
    isset($_POST['fullName'])
));

if ($isUpdateProfile) {
    $fullName = trim($_POST['fullName'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $dob      = trim($_POST['date_of_birth'] ?? '');

    if (mb_strlen($fullName) < 3) {
        $error = 'Họ và tên không hợp lệ (tối thiểu 3 ký tự).';
    } elseif (!preg_match('/^[0-9+() -]{10,15}$/', $phone)) {
        $error = 'Số điện thoại không hợp lệ.';
    } elseif ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        $error = 'Định dạng ngày sinh không hợp lệ (YYYY-MM-DD).';
    } elseif ($dob !== '' && strtotime($dob) > strtotime(date('Y-m-d'))) {
        $error = 'Ngày sinh không thể là một ngày trong tương lai.';
    } elseif ($dob !== '' && strtotime($dob) < strtotime('1920-01-01')) {
        $error = 'Năm sinh không hợp lệ (tối thiểu từ năm 1920).';
    } else {
        $cleanDob = ($dob !== '') ? $dob : null;
        $upStmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, date_of_birth = ?, updated_at = NOW() WHERE id = ?");
        $upStmt->execute([$fullName, $phone, $address, $cleanDob, $userId]);

        $_SESSION['user_name']  = $fullName;
        $_SESSION['user_phone'] = $phone;
        $currentUser = currentUser();
        $message = 'Cập nhật thông tin thành công!';

        // Tự động kiểm tra và tặng voucher sinh nhật nếu hôm nay là sinh nhật
        if ($cleanDob) {
            require_once ROOT_PATH . '/helpers/voucher.php';
            $gift = @checkAndGiftBirthdayVoucher($userId);
            if ($gift) {
                $message .= " 🎂 Chúc mừng sinh nhật! Bạn vừa nhận được voucher {$gift['code']} giảm {$gift['discount_percent']}%!";
            }
        }
    }
}

// 3. Fetch fresh user details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("Không tìm thấy thông tin tài khoản.");
}

// 5. Fetch Membership Tiers and Settings
$tiers = getMembershipTiers();
$settings = getLoyaltySettings();
$pointRate = (float)($settings['point_to_vnd_rate'] ?? 100.0);

$currentTierCode = strtoupper($user['member_tier'] ?? 'BRONZE');
$currentTier = $tiers[$currentTierCode] ?? $tiers['BRONZE'];
$totalSpent = (float)$user['total_spent'];
$pointsBalance = (int)$user['points_balance'];
$pointsVndValue = $pointsBalance * $pointRate;

// 6. Calculate Next Tier Threshold & Progress
$tierOrder = ['BRONZE', 'SILVER', 'GOLD', 'DIAMOND'];
$currentIndex = array_search($currentTierCode, $tierOrder, true);
if ($currentIndex === false) $currentIndex = 0;
$isMaxTier = ($currentIndex >= count($tierOrder) - 1);

if (!$isMaxTier) {
    $nextTierCode = $tierOrder[$currentIndex + 1];
    $nextTier = $tiers[$nextTierCode] ?? null;
    $currentMin = (float)$currentTier['min_spent'];
    $nextMin = (float)($nextTier['min_spent'] ?? 0);
    $remainingSpent = max(0.0, $nextMin - $totalSpent);
    $tierSpan = max(1.0, $nextMin - $currentMin);
    $progressPercent = min(100.0, max(0.0, round((($totalSpent - $currentMin) / $tierSpan) * 100, 1)));
} else {
    $nextTierCode = null;
    $nextTier = null;
    $remainingSpent = 0.0;
    $progressPercent = 100.0;
}

// Overall 0 to 5,000,000 timeline position for visual nodes
$overallPercent = min(100.0, max(0.0, round(($totalSpent / 5000000.0) * 100, 1)));

// 7. Fetch Voucher Packages & Wallet Vouchers
require_once ROOT_PATH . '/helpers/voucher.php';
$voucherPackages = getVoucherPackages();
$birthdayStatus  = getUserBirthdayStatus($userId);

// 8. Fetch Active User Coupons & Wallet Vouchers
$walletVouchers = getUserWalletVouchers($userId);
$userCoupons = $walletVouchers;
$couponCount = count($userCoupons);

// 9. Fetch Point Transactions Ledger History
$pointHistory = getUserPointHistory($userId, 50);

$pageTitle = 'Hồ Sơ & Khách Hàng VIP - FASTFOOD';
$activeNav = 'profile';
$cssVer = @filemtime(__DIR__ . '/css/profile.css') ?: '20261126';
$jsVer  = @filemtime(__DIR__ . '/js/profile.js') ?: '20261126';
$extraCss  = ['css/pages.css', 'css/profile.css?v=' . $cssVer];
$extraJs   = ['js/profile.js?v=' . $jsVer];

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= getUrl('index.php') ?>">Trang chủ</a> <span>/</span> 
      <a href="<?= getUrl('profile.php') ?>">Tài khoản</a> <span>/</span> 
      <span>Hồ sơ & Khách hàng VIP</span>
    </div>
    <h1 class="page-title">Hồ Sơ Khách Hàng VIP</h1>
  </div>
</div>

<main class="profile-dashboard-wrap">

  <!-- =========================================================================
       HERO GRID: VIP Pass Card (F7) & Tier Progress Tracker (F8)
       ========================================================================= -->
  <section class="profile-hero-grid">

    <!-- F7: Modern FastFood VIP Member Pass Card -->
    <?php
    $tierCardClass = match ($currentTierCode) {
        'SILVER'  => 'vip-card-silver',
        'GOLD'    => 'vip-card-gold',
        'DIAMOND' => 'vip-card-diamond',
        default   => 'vip-card-bronze',
    };
    $tierPrivilegeHighlight = match ($currentTierCode) {
        'DIAMOND' => 'Freeship 0đ • VIP 24/7',
        'GOLD'    => 'Freeship đơn từ 150K',
        'SILVER'  => 'Ưu tiên xử lý đơn',
        default   => 'Tích điểm đổi Voucher',
    };
    $memberSerialCode = '#FF-' . str_pad((string)$userId, 5, '0', STR_PAD_LEFT);
    ?>
    <div class="vip-pass-card vip-card vip-member-card membership-card <?= $tierCardClass ?> js-vip-tilt-card" id="vipCard" data-component="vip-card">
      <!-- Precision Engraved Vector Foil & Ambient Glare -->
      <div class="vip-card-bg-art" aria-hidden="true">
        <div class="vip-card-dotmatrix"></div>
        <div class="vip-card-inner-frame"></div>
        <div class="vip-card-glare"></div>
        <svg class="vip-guilloche-svg" viewBox="0 0 420 260" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="365" cy="65" r="145" stroke="rgba(255,255,255,0.09)" stroke-width="1"/>
          <circle cx="365" cy="65" r="112" stroke="rgba(255,255,255,0.07)" stroke-width="1" stroke-dasharray="5 5"/>
          <circle cx="365" cy="65" r="78" stroke="rgba(255,255,255,0.09)" stroke-width="0.9"/>
          <path d="M295 190 L345 128 L395 190 L345 252 Z" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
          <path d="M312 190 L345 148 L378 190 L345 232 Z" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
          <line x1="25" y1="225" x2="140" y2="225" stroke="rgba(255,255,255,0.08)" stroke-width="0.8"/>
        </svg>
      </div>

      <!-- Header -->
      <div class="vip-card-header">
        <div class="vip-brand-badge">
          <div class="vip-brand-crest">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5m14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"/>
            </svg>
          </div>
          <div class="vip-brand-text">
            <span class="vip-brand-title">FASTFOOD VIP</span>
            <span class="vip-brand-sub">PRIORITY LOYALTY PASS</span>
          </div>
        </div>
        <div class="vip-tier-pill vip-tier tier-badge badge-tier" data-tier="<?= $currentTierCode ?>">
          <span class="vip-tier-icon"><?= escapeHtml($currentTier['badge_icon']) ?></span>
          <span><?= escapeHtml($currentTier['tier_name']) ?></span>
        </div>
      </div>

      <!-- Middle: Gold EMV Chip, NFC & Engraved Member ID -->
      <div class="vip-card-middle">
        <div class="vip-chip-wrap">
          <div class="vip-chip-svg" aria-hidden="true">
            <svg viewBox="0 0 48 34" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="0.75" y="0.75" width="46.5" height="32.5" rx="5.25" stroke="rgba(255,255,255,0.38)" stroke-width="1"/>
              <path d="M1 12.5H15.5C17 12.5 18 13.5 18 15V19C18 20.5 17 21.5 15.5 21.5H1" stroke="rgba(120,53,15,0.45)" stroke-width="1.2"/>
              <path d="M47 12.5H32.5C31 12.5 30 13.5 30 15V19C30 20.5 31 21.5 32.5 21.5H47" stroke="rgba(120,53,15,0.45)" stroke-width="1.2"/>
              <path d="M18 17H30" stroke="rgba(120,53,15,0.45)" stroke-width="1.2"/>
              <path d="M24 1V12.5M24 21.5V33" stroke="rgba(120,53,15,0.45)" stroke-width="1.2"/>
              <rect x="17" y="11" width="14" height="12" rx="3" stroke="rgba(120,53,15,0.42)" stroke-width="1.15"/>
            </svg>
          </div>
          <svg class="vip-nfc-icon" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M8.5 15.5a5 5 0 0 1 0-7"/>
            <path d="M12 18a8.5 8.5 0 0 0 0-12"/>
            <path d="M15.5 20.5a12 12 0 0 0 0-17"/>
          </svg>
        </div>

        <div class="vip-id-block">
          <span class="vip-id-eyebrow">MÃ THÀNH VIÊN</span>
          <button type="button"
                  class="vip-cust-id-btn js-copy-code"
                  data-code="<?= escapeHtml($memberSerialCode) ?>"
                  title="Bấm để sao chép mã thẻ thành viên">
            <span class="vip-cust-id copy-btn-text"><?= escapeHtml($memberSerialCode) ?></span>
            <svg class="vip-id-copy-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- Luxury Privilege Highlight Ribbon -->
      <div class="vip-perks-strip">
        <div class="vip-perk-cell">
          <span class="vip-perk-kicker">TỶ LỆ TÍCH ĐIỂM</span>
          <span class="vip-perk-value">x<?= number_format((float)$currentTier['point_ratio'], 1) ?> / 10.000đ</span>
        </div>
        <div class="vip-perk-divider" aria-hidden="true"></div>
        <div class="vip-perk-cell vip-perk-cell-right">
          <span class="vip-perk-kicker">ĐẶC QUYỀN HẠNG</span>
          <span class="vip-perk-value"><?= escapeHtml($tierPrivilegeHighlight) ?></span>
        </div>
      </div>

      <!-- Footer: User Info & Points -->
      <div class="vip-card-footer">
        <div class="vip-user-info">
          <div class="vip-avatar-ring">
            <img src="<?= escapeHtml($user['avatar_url'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=160&auto=format&fit=crop&q=80') ?>" 
                 alt="Avatar" class="vip-avatar-img">
          </div>
          <div class="vip-user-meta">
            <span class="vip-holder-kicker">CHỦ THẺ</span>
            <h3 class="vip-user-name"><?= escapeHtml($user['full_name']) ?></h3>
            <span class="vip-spent-label">Đã chi: <?= number_format($totalSpent, 0, ',', '.') ?> đ</span>
          </div>
        </div>

        <div class="vip-points-box vip-points points-balance" id="pointsBalance" data-points="<?= $pointsBalance ?>">
          <div class="vip-points-kicker">ĐIỂM TÍCH LŨY</div>
          <div class="vip-points-val">
            <span class="js-user-points-balance" data-raw-points="<?= $pointsBalance ?>"><?= number_format($pointsBalance, 0, ',', '.') ?></span>
            <span class="vip-points-unit">Điểm</span>
          </div>
          <div class="vip-points-vnd js-user-points-vnd">
            ~ <?= number_format($pointsVndValue, 0, ',', '.') ?> đ
          </div>
        </div>
      </div>
    </div>

    <!-- F8: Tier Progress Tracker & Next Tier Gap -->
    <?php
    if ($totalSpent >= 5000000.0) {
        $trackFillPercent = 100.0;
    } elseif ($totalSpent >= 3000000.0) {
        $trackFillPercent = 66.67 + (($totalSpent - 3000000.0) / 2000000.0) * 33.33;
    } elseif ($totalSpent >= 1000000.0) {
        $trackFillPercent = 33.33 + (($totalSpent - 1000000.0) / 2000000.0) * 33.34;
    } else {
        $trackFillPercent = ($totalSpent / 1000000.0) * 33.33;
    }
    $trackFillPercent = round(min(100.0, max(0.0, $trackFillPercent)), 2);
    ?>
    <div class="tier-tracker-card">
      <div class="tracker-main-body">
        <div class="tracker-title-row">
          <div class="tracker-heading-group">
            <div class="tracker-icon-badge" aria-hidden="true">🏅</div>
            <div>
              <h2 class="tracker-title">Tiến Trình Nâng Hạng</h2>
              <p class="tracker-subtitle">Lộ trình tích lũy chi tiêu &amp; mở khóa đặc quyền</p>
            </div>
          </div>
          <span class="tracker-badge-pill">
            <span class="tracker-pill-dot" aria-hidden="true"></span>
            Tỷ lệ tích: x<?= number_format((float)$currentTier['point_ratio'], 1) ?> điểm/10k
          </span>
        </div>

        <!-- Quick Stats Strip -->
        <div class="tracker-quick-stats">
          <div class="stat-item">
            <div class="stat-item-label">Hạng Hiện Tại</div>
            <div class="stat-item-val"><?= escapeHtml($currentTier['badge_icon']) ?> <?= escapeHtml($currentTier['tier_name']) ?></div>
          </div>
          <div class="stat-item">
            <div class="stat-item-label">Điểm Tích Lũy</div>
            <div class="stat-item-val text-orange">
              <span class="js-user-points-balance" data-raw-points="<?= $pointsBalance ?>"><?= number_format($pointsBalance, 0, ',', '.') ?></span> pts
            </div>
          </div>
          <div class="stat-item">
            <div class="stat-item-label">Tổng Chi Tiêu</div>
            <div class="stat-item-val"><?= number_format($totalSpent, 0, ',', '.') ?> đ</div>
          </div>
          <div class="stat-item">
            <div class="stat-item-label">Voucher Đang Có</div>
            <div class="stat-item-val"><span class="js-voucher-count"><?= $couponCount ?></span> mã</div>
          </div>
        </div>

        <!-- Milestones Track -->
        <div class="tier-progress-panel <?= $isMaxTier ? 'is-diamond-max' : '' ?>">
          <div class="tier-progress-panel-head">
            <span class="tier-progress-kicker">Cột mốc chi tiêu</span>
            <span class="tier-progress-status <?= $isMaxTier ? 'is-max' : '' ?>">
              <?= $isMaxTier ? '100% • Hoàn tất' : $progressPercent . '% đến ' . escapeHtml($nextTier['tier_name'] ?? '') ?>
            </span>
          </div>

          <div class="tier-milestones-track <?= $isMaxTier ? 'is-diamond-max' : '' ?>" data-component="progress-tracker">
            <div class="milestones-bar-bg">
              <div class="milestones-bar-fill progress-bar tier-progress-fill <?= $isMaxTier ? 'diamond-max' : '' ?>" 
                   id="tierProgressBar"
                   role="progressbar"
                   aria-valuenow="<?= $isMaxTier ? '100' : $progressPercent ?>"
                   aria-valuemin="0"
                   aria-valuemax="100"
                   style="width: <?= $isMaxTier ? '100' : $trackFillPercent ?>%;"></div>
            </div>

            <div class="milestone-nodes">
              <?php
              $tierNodes = [
                  ['code' => 'BRONZE',  'name' => 'Đồng',      'spent' => '0đ',   'threshold' => 0],
                  ['code' => 'SILVER',  'name' => 'Bạc',       'spent' => '1.0M', 'threshold' => 1000000],
                  ['code' => 'GOLD',    'name' => 'Vàng',      'spent' => '3.0M', 'threshold' => 3000000],
                  ['code' => 'DIAMOND', 'name' => 'Kim Cương', 'spent' => '5.0M', 'threshold' => 5000000],
              ];
              foreach ($tierNodes as $idx => $tNode):
                  $isReached = ($totalSpent >= $tNode['threshold']);
                  $isCurr = ($currentTierCode === $tNode['code']);
                  $nodeClass = $isCurr ? 'is-reached is-current' : ($isReached ? 'is-reached' : '');
              ?>
                <div class="milestone-node <?= $nodeClass ?>">
                  <div class="node-dot">
                    <?= $isReached ? '✓' : ($idx + 1) ?>
                  </div>
                  <div class="node-name"><?= $tNode['name'] ?></div>
                  <div class="node-spent"><?= $tNode['spent'] ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Gap Banner / Max Status -->
      <?php if (!$isMaxTier && $nextTier): ?>
        <div class="tracker-alert-box">
          <div class="tracker-alert-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
          </div>
          <div class="tracker-banner-copy">
            <div class="tracker-banner-kicker">TIẾN ĐỘ THĂNG HẠNG TIẾP THEO</div>
            <div class="tracker-banner-desc">
              Chi tiêu thêm <span class="remaining-gap next-tier-gap" id="remainingGap" data-gap="<?= $remainingSpent ?>"><strong><?= number_format($remainingSpent, 0, ',', '.') ?> đ</strong></span> để thăng hạng 
              <strong><?= escapeHtml($nextTier['tier_name']) ?></strong>!
            </div>
          </div>
          <span class="tracker-banner-chip"><?= $progressPercent ?>%</span>
        </div>
      <?php else: ?>
        <div class="tracker-max-badge">
          <div class="tracker-max-icon" aria-hidden="true">💎</div>
          <div class="tracker-banner-copy">
            <div class="tracker-max-kicker">ĐẠT HẠNG TỐI ĐA!</div>
            <div class="tracker-max-desc">Bạn đang sở hữu cấp bậc Kim Cương cao cấp nhất cùng trọn vẹn đặc quyền VIP tối thượng!</div>
          </div>
          <span class="tracker-max-chip">VIP MAX</span>
        </div>
      <?php endif; ?>
    </div>

  </section>

  <!-- =========================================================================
       INTERACTIVE SEGMENTED TABS NAVIGATION
       ========================================================================= -->
  <nav class="profile-tabs-nav" role="tablist">
    <button type="button" class="profile-tab-btn js-profile-tab active" data-target="tab-vouchers" role="tab" aria-selected="true">
      <span>🎁</span> Đổi Thưởng & Kho Voucher
    </button>
    <button type="button" class="profile-tab-btn js-profile-tab" data-target="tab-tiers" role="tab" aria-selected="false">
      <span>👑</span> Đặc Quyền Cấp Bậc
    </button>
    <button type="button" class="profile-tab-btn js-profile-tab" data-target="tab-history" role="tab" aria-selected="false">
      <span>📜</span> Lịch Sử Điểm Thưởng
    </button>
    <button type="button" class="profile-tab-btn js-profile-tab" data-target="tab-profile" role="tab" aria-selected="false">
      <span>👤</span> Thông Tin Cá Nhân
    </button>
  </nav>

  <!-- =========================================================================
       TAB 1: REWARDS CENTER & VOUCHER HUB (F10)
       ========================================================================= -->
  <div id="tab-vouchers" class="profile-tab-pane js-profile-panel active" role="tabpanel">

    <!-- Part A: Sàn Đổi Điểm Lấy Voucher -->
    <div class="reward-exchange-wrap">
      <div class="tab-section-header">
        <h2 class="tab-section-title">
          <span>🪙</span> Sàn Đổi Điểm Thưởng Lấy Voucher
        </h2>
        <p class="tab-section-subtitle">
          Quy đổi điểm thưởng tích lũy thành các mã giảm giá trực tiếp cho các đơn hàng tiếp theo.
          Số dư khả dụng: <strong class="text-orange"><span class="js-user-points-balance" data-raw-points="<?= $pointsBalance ?>"><?= number_format($pointsBalance, 0, ',', '.') ?></span> Điểm</strong> 
          (~ <span class="js-user-points-vnd"><?= number_format($pointsVndValue, 0, ',', '.') ?> đ</span>).
        </p>
      </div>

      <div class="voucher-exchange-grid">
        <?php foreach ($voucherPackages as $pkgId => $pkg): 
            $canAfford = ($pointsBalance >= $pkg['points_cost']);
            $pointsNeeded = max(0, $pkg['points_cost'] - $pointsBalance);
        ?>
          <div class="voucher-pkg-card <?= !$canAfford ? 'package-locked' : '' ?>">
            <div class="voucher-pkg-badge"><?= escapeHtml($pkg['badge']) ?></div>
            <div class="voucher-pkg-val"><?= number_format($pkg['discount_amount'], 0, ',', '.') ?> đ</div>
            <div class="voucher-pkg-price">Chi phí: <?= number_format($pkg['points_cost']) ?> điểm</div>
            <div class="voucher-pkg-desc"><?= escapeHtml($pkg['description']) ?></div>
            
            <button type="button" 
                    class="btn-exchange js-exchange-voucher-btn <?= !$canAfford ? 'btn-disabled' : '' ?>"
                    data-package-id="<?= $pkgId ?>"
                    data-points-cost="<?= $pkg['points_cost'] ?>"
                    data-package-name="<?= escapeHtml($pkg['name']) ?>"
                    <?= !$canAfford ? 'disabled' : '' ?>>
              <span>🎁</span>
              <span class="btn-text"><?= $canAfford ? 'Đổi Ngay' : 'Cần thêm ' . number_format($pointsNeeded) . ' điểm' ?></span>
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Part B: Kho Voucher Của Tôi -->
    <div class="my-vouchers-wrap">
      <div class="tab-section-header">
        <h2 class="tab-section-title">
          <span>🎟️</span> Kho Voucher Của Tôi (<span class="js-voucher-count"><?= $couponCount ?></span>)
        </h2>
        <p class="tab-section-subtitle">
          Danh sách mã ưu đãi độc quyền dành riêng cho bạn. Nhấn sao chép và áp dụng tại bước thanh toán.
        </p>
      </div>

      <div id="user-vouchers-container" class="active-vouchers-grid">
        <?php if (!empty($userCoupons)): ?>
          <?php foreach ($userCoupons as $coupon): 
              $discountVal = (float)$coupon['discount_value'];
              $vType = $coupon['voucher_type'] ?? ($coupon['discount_type'] ?? 'percentage');
              $discountDisplay = $coupon['discount_display'] ?? (
                  $vType === 'percentage' 
                      ? rtrim(rtrim(number_format($discountVal, 1), '0'), '.') . '%'
                      : ($vType === 'free_shipping' ? 'FREESHIP' : number_format($discountVal, 0, ',', '.') . 'đ')
              );
              $minOrder = (float)($coupon['min_order_value'] ?? 0);
              $endDate = !empty($coupon['effective_end_date']) ? date('d/m/Y', strtotime($coupon['effective_end_date'])) : (!empty($coupon['end_date']) ? date('d/m/Y', strtotime($coupon['end_date'])) : 'Không thời hạn');
              $typeBadge = $coupon['type_badge'] ?? 'Ưu Đãi';
              $isBirthday = !empty($coupon['is_birthday_voucher']) || (!empty($coupon['source']) && $coupon['source'] === 'birthday');
          ?>
            <div class="voucher-ticket">
              <div class="voucher-ticket-left">
                <div class="ticket-discount-tag"><?= $discountDisplay ?></div>
                <div class="ticket-discount-sub"><?= $typeBadge ?></div>
              </div>

              <div class="voucher-ticket-right">
                <div>
                  <div style="display:flex; align-items:center; gap:0.4rem; margin-bottom:0.25rem;">
                    <?php if ($isBirthday): ?>
                      <span style="background:#fef3c7; color:#b45309; font-weight:800; font-size:0.7rem; padding:0.15rem 0.45rem; border-radius:4px;">🎂 Sinh Nhật</span>
                    <?php endif; ?>
                    <span style="background:#ffedd5; color:#ea580c; font-weight:750; font-size:0.7rem; padding:0.15rem 0.45rem; border-radius:4px;"><?= $typeBadge ?></span>
                  </div>
                  <h4 class="ticket-title"><?= escapeHtml($coupon['title'] ?: 'Mã Ưu Đãi VIP FASTFOOD') ?></h4>
                  <div class="ticket-condition">
                    <?= $minOrder > 0 ? 'Đơn tối thiểu ' . number_format($minOrder, 0, ',', '.') . 'đ' : 'Áp dụng cho mọi đơn hàng' ?>
                  </div>
                </div>

                <div>
                  <?php
                    $isCouponUsed = !empty($coupon['is_used']);
                    $displayCouponCode = $isCouponUsed
                      ? str_repeat('x', max(8, strlen((string)$coupon['code'])))
                      : $coupon['code'];
                  ?>
                  <div class="ticket-code-row">
                    <span class="ticket-code-badge" <?= $isCouponUsed ? 'style="user-select:none; -webkit-user-select:none; background:#f1f5f9; color:#94a3b8; border-color:#cbd5e1;"' : '' ?>>
                      <?= $isCouponUsed ? '🔒 ' : '' ?><?= escapeHtml($displayCouponCode) ?>
                    </span>
                    <?php if (!$isCouponUsed): ?>
                      <button type="button" class="ticket-copy-btn js-copy-code" data-code="<?= escapeHtml($coupon['code']) ?>" title="Sao chép mã vào clipboard">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                          <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                        <span class="copy-btn-text">Sao Chép</span>
                      </button>
                    <?php else: ?>
                      <span style="font-size:0.75rem; font-weight:750; color:#64748b; background:#f1f5f9; padding:0.25rem 0.55rem; border-radius:8px;">Đã dùng</span>
                    <?php endif; ?>
                  </div>
                  <div class="ticket-expiry-row">
                    <span>HSD: <?= $endDate ?></span>
                    <?php if (!$isCouponUsed): ?>
                      <a href="<?= getUrl('foods.php') ?>" style="color: var(--ff-primary); font-weight: 700; text-decoration: none;">Dùng ngay →</a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-vouchers-card js-no-vouchers" style="grid-column: 1 / -1;">
            <div class="empty-vouchers-icon">🎟️</div>
            <h3 style="margin: 0 0 0.5rem; color: var(--ff-dark-slate); font-weight: 800;">Bạn chưa có mã voucher nào</h3>
            <p style="margin: 0 0 1.25rem;">Hãy dùng điểm thưởng tích lũy ở phía trên để đổi lấy các ưu đãi giảm giá hấp dẫn ngay hôm nay!</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- =========================================================================
       TAB 2: TIER PRIVILEGES GUIDE & COMPARISON (F9)
       ========================================================================= -->
  <div id="tab-tiers" class="profile-tab-pane js-profile-panel" role="tabpanel">
    <div class="tab-section-header">
      <h2 class="tab-section-title">
        <span>👑</span> Bảng Đặc Quyền Các Cấp Bậc Thành Viên
      </h2>
      <p class="tab-section-subtitle">
        Càng tích lũy chi tiêu nhiều, bạn càng nhận được tỷ lệ nhân điểm thưởng cao và nhiều đặc quyền vượt trội.
      </p>
    </div>

    <div class="privileges-grid">
      <?php foreach ($tiers as $tCode => $tData): 
          $isCurrent = ($currentTierCode === $tCode);
          $benefits = is_array($tData['benefits']) ? $tData['benefits'] : (json_decode($tData['benefits'] ?? '[]', true) ?: []);
          $minSpentFmt = (float)$tData['min_spent'] > 0 
              ? 'Chi tiêu từ ' . number_format((float)$tData['min_spent'], 0, ',', '.') . 'đ' 
              : 'Mặc định khi đăng ký';
      ?>
        <div class="privilege-card tier-card <?= $isCurrent ? 'active is-current current-tier tier-active' : '' ?>" data-tier-item data-is-current="<?= $isCurrent ? 'true' : 'false' ?>">
          <?php if ($isCurrent): ?>
            <div class="privilege-ribbon badge-current-tier">★ Hạng hiện tại</div>
          <?php endif; ?>

          <div class="privilege-card-top">
            <div class="privilege-icon"><?= escapeHtml($tData['badge_icon']) ?></div>
            <h3 class="privilege-name"><?= escapeHtml($tData['tier_name']) ?></h3>
            <div class="privilege-spent"><?= $minSpentFmt ?></div>
            <div class="privilege-multiplier-badge">
              Tích lũy x<?= number_format((float)$tData['point_ratio'], 1) ?> điểm/10k
            </div>
          </div>

          <ul class="privilege-list">
            <?php foreach ($benefits as $bText): ?>
              <li>
                <span class="privilege-check-icon">✓</span>
                <span><?= escapeHtml($bText) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- =========================================================================
       TAB 3: POINTS ACTIVITY HISTORY TIMELINE (F11)
       ========================================================================= -->
  <div id="tab-history" class="profile-tab-pane js-profile-panel" role="tabpanel">
    <div class="tab-section-header">
      <h2 class="tab-section-title">
        <span>📜</span> Lịch Sử Tích Lũy & Sử Dụng Điểm Thưởng
      </h2>
      <p class="tab-section-subtitle">
        Theo dõi minh bạch toàn bộ các giao dịch biến động điểm thưởng gắn liền với đơn hàng và đổi voucher.
      </p>
    </div>

    <div class="points-history-card">
      <?php if (!empty($pointHistory)): ?>
        <!-- Desktop Table -->
        <div class="points-table-wrap">
          <table class="points-table">
            <thead>
              <tr>
                <th>Thời Gian</th>
                <th>Loại Giao Dịch</th>
                <th>Biến Động Điểm</th>
                <th>Nội Dung & Ghi Chú</th>
                <th>Số Dư Sau</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pointHistory as $tx): 
                  $pts = (int)$tx['points'];
                  $isPlus = ($pts > 0);
                  $type = $tx['transaction_type'];
                  
                  $badgeMeta = match ($type) {
                      'EARN'            => ['class' => 'badge-earn',     'label' => '+ Tích điểm đơn hàng'],
                      'REDEEM_CHECKOUT' => ['class' => 'badge-checkout', 'label' => '- Trừ điểm thanh toán'],
                      'REDEEM_VOUCHER'  => ['class' => 'badge-voucher',  'label' => '- Đổi mã voucher'],
                      'REFUND_RESTORE'  => ['class' => 'badge-restore',  'label' => '+ Hoàn điểm đơn hủy'],
                      'REFUND_REVOKE'   => ['class' => 'badge-revoke',   'label' => '- Thu hồi điểm hoàn trả'],
                      default           => ['class' => 'badge-adjust',   'label' => '± Điều chỉnh'],
                  };
              ?>
                <tr>
                  <td><?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?></td>
                  <td>
                    <span class="points-badge <?= $badgeMeta['class'] ?>"><?= $badgeMeta['label'] ?></span>
                  </td>
                  <td>
                    <span class="<?= $isPlus ? 'pts-val-plus' : 'pts-val-minus' ?>">
                      <?= $isPlus ? '+' . number_format($pts) : number_format($pts) ?>
                    </span>
                  </td>
                  <td>
                    <?= escapeHtml($tx['description']) ?>
                    <?php if (!empty($tx['order_code'])): ?>
                      <strong style="color: var(--ff-primary);">(#<?= escapeHtml($tx['order_code']) ?>)</strong>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="pts-balance-pill"><?= number_format((int)$tx['balance_after']) ?> pts</span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Mobile Card Stream -->
        <div class="points-mobile-stream" style="padding: 1rem;">
          <?php foreach ($pointHistory as $tx): 
              $pts = (int)$tx['points'];
              $isPlus = ($pts > 0);
              $type = $tx['transaction_type'];
              $badgeMeta = match ($type) {
                  'EARN'            => ['class' => 'badge-earn',     'label' => '+ Tích điểm'],
                  'REDEEM_CHECKOUT' => ['class' => 'badge-checkout', 'label' => '- Thanh toán'],
                  'REDEEM_VOUCHER'  => ['class' => 'badge-voucher',  'label' => '- Đổi voucher'],
                  'REFUND_RESTORE'  => ['class' => 'badge-restore',  'label' => '+ Hoàn trả'],
                  'REFUND_REVOKE'   => ['class' => 'badge-revoke',   'label' => '- Thu hồi'],
                  default           => ['class' => 'badge-adjust',   'label' => '± Điều chỉnh'],
              };
          ?>
            <div class="points-mobile-card">
              <div class="points-mobile-top">
                <span class="points-badge <?= $badgeMeta['class'] ?>"><?= $badgeMeta['label'] ?></span>
                <span class="<?= $isPlus ? 'pts-val-plus' : 'pts-val-minus' ?>">
                  <?= $isPlus ? '+' . number_format($pts) : number_format($pts) ?>
                </span>
              </div>
              <div class="points-mobile-desc">
                <?= escapeHtml($tx['description']) ?>
              </div>
              <div class="points-mobile-bottom">
                <span><?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?></span>
                <span>Số dư: <strong><?= number_format((int)$tx['balance_after']) ?> pts</strong></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

      <?php else: ?>
        <div style="padding: 3rem 1.5rem; text-align: center; color: var(--ff-muted-slate);">
          <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📜</div>
          <h3 style="color: var(--ff-dark-slate); font-weight: 700; margin: 0 0 0.5rem;">Chưa có giao dịch điểm nào</h3>
          <p style="margin: 0;">Các biến động tích lũy và đổi điểm của bạn sẽ được ghi nhận chi tiết tại đây.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- =========================================================================
       TAB 4: PROFILE EDIT FORM (F12)
       ========================================================================= -->
  <div id="tab-profile" class="profile-tab-pane js-profile-panel" role="tabpanel">
    <div class="profile-edit-card">
      <div class="profile-avatar-row">
        <img src="<?= escapeHtml($user['avatar_url'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=160&auto=format&fit=crop&q=80') ?>" 
             alt="Avatar" class="profile-avatar-img">
        <div>
          <h3 style="font-size: 1.3rem; font-weight: 800; margin: 0 0 0.25rem; color: var(--ff-dark-slate);">
            <?= escapeHtml($user['full_name']) ?>
          </h3>
          <span class="badge badge-secondary"><?= strtoupper($currentUser['role']) ?></span>
          <span style="color: var(--ff-muted-slate); font-size: 0.85rem; margin-left: 0.5rem;">
            <?= escapeHtml($user['email']) ?>
          </span>
          <div style="margin-top:0.35rem; font-size:0.85rem; color:#475569;">
            🎂 <strong>Ngày sinh:</strong> 
            <?= !empty($user['date_of_birth']) ? date('d/m/Y', strtotime($user['date_of_birth'])) : '<span style="color:#94a3b8; font-style:italic;">Chưa cập nhật ngày sinh</span>' ?>
          </div>
        </div>
      </div>

      <?php if (!empty($birthdayStatus['is_birthday_today'])): ?>
        <div style="margin-bottom:1.25rem; background:linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); border:1.5px solid #fb923c; border-radius:12px; padding:1rem 1.25rem; display:flex; align-items:center; gap:0.85rem;">
          <div style="font-size:2rem;">🎂</div>
          <div>
            <strong style="color:#9a3412; font-size:1rem; display:block;">Chúc Mừng Sinh Nhật <?= escapeHtml($user['full_name']) ?>! 🎉</strong>
            <p style="margin:0.2rem 0 0; font-size:0.85rem; color:#c2410c;">
              FASTFOOD đã gửi tặng bạn voucher ưu đãi sinh nhật độc quyền trong mục <strong>Ví Voucher</strong>. Hãy thưởng thức tiệc ngon hôm nay nhé!
            </p>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($message): ?>
        <div class="profile-alert-success alert alert-success">
          <span>✅</span> <?= escapeHtml($message) ?>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="profile-alert-danger alert alert-danger">
          <span>❌</span> <?= escapeHtml($error) ?>
        </div>
      <?php endif; ?>

      <form action="<?= getUrl('profile.php#tab-profile') ?>" method="POST">
        <input type="hidden" name="action" value="update_profile">

        <div class="profile-form-group">
          <label class="profile-form-label" for="profile-name">Họ và Tên *</label>
          <input type="text" name="fullName" id="profile-name" class="profile-form-control" 
                 value="<?= escapeHtml($user['full_name']) ?>" required>
        </div>

        <div class="profile-form-group">
          <label class="profile-form-label" for="profile-email">Địa chỉ Email (Không thể thay đổi)</label>
          <input type="email" id="profile-email" class="profile-form-control" 
                 value="<?= escapeHtml($user['email']) ?>" readonly>
        </div>

        <div class="profile-form-group">
          <label class="profile-form-label" for="profile-phone">Số điện thoại *</label>
          <input type="tel" name="phone" id="profile-phone" class="profile-form-control" 
                 value="<?= escapeHtml($user['phone']) ?>" required>
        </div>

        <div class="profile-form-group">
          <label class="profile-form-label" for="profile-dob">🎂 Ngày Sinh (Nhận quà ưu đãi sinh nhật)</label>
          <input type="date" name="date_of_birth" id="profile-dob" class="profile-form-control" 
                 value="<?= escapeHtml($user['date_of_birth'] ?? '') ?>" 
                 max="<?= date('Y-m-d') ?>" min="1920-01-01">
          <small style="color: var(--ff-muted-slate); font-size: 0.8rem; margin-top: 4px; display: block;">
            💡 FASTFOOD sẽ tự động gửi tặng bạn voucher ưu đãi độc quyền lên tới 25% vào đúng ngày sinh nhật mỗi năm!
          </small>
        </div>

        <div class="profile-form-group">
          <label class="profile-form-label" for="profile-address">Địa chỉ mặc định</label>
          <input type="text" name="address" id="profile-address" class="profile-form-control" 
                 value="<?= escapeHtml($user['address'] ?? '') ?>" placeholder="Nhập địa chỉ nhà riêng hoặc cơ quan để giao hàng nhanh...">
        </div>

        <div class="profile-actions-row">
          <button type="submit" class="btn btn-primary btn-lg" style="min-width: 170px;">
            Lưu Thay Đổi
          </button>
          <a href="<?= getUrl('order-history.php') ?>" class="btn btn-outline btn-lg">
            Xem Đơn Hàng Của Tôi
          </a>
        </div>
      </form>
    </div>
  </div>

</main>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
