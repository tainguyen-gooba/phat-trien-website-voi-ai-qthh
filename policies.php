<?php
/**
 * FASTFOOD - Chính sách & Hướng dẫn (Policies)
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Chính Sách & Hỗ Trợ - FASTFOOD';
$activeNav = '';
$extraCss  = ['css/pages.css', 'css/policies.css'];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb"><a href="<?= getUrl('index.php') ?>">Trang chủ</a><span>/</span><span>Chính sách & hỗ trợ</span></div>
    <h1 class="page-title">Chính Sách & Hỗ Trợ</h1>
  </div>
</div>

<main class="container policy-content" style="max-width:860px; margin:2.5rem auto 5rem;">
  <p class="policy-intro" style="font-size:1.1rem; color:var(--text-secondary); margin-bottom:2rem;">
    Thông tin về quy chế vận hành, chính sách giao hàng và các kênh hỗ trợ khách hàng của FASTFOOD tại Buôn Ma Thuột.
  </p>

  <section class="policy-section" id="cancellation" style="background:#fff; border-radius:14px; padding:1.75rem; margin-bottom:1.5rem; box-shadow:0 3px 14px rgba(0,0,0,0.03);">
    <h2 style="font-size:1.3rem; font-weight:800; color:var(--text-main); margin-top:0;">Quy định hủy đơn & Phí vận chuyển</h2>
    <p>Để đảm bảo quyền lợi cho shipper và nhà hàng, khách hàng có thể hủy đơn khi đơn còn ở trạng thái <strong>Chờ xác nhận</strong> hoặc <strong>Chờ thanh toán</strong>.</p>
    <p><strong>Lưu ý:</strong> Mỗi lần khách hàng hủy đơn, phí giao hàng cho các đơn tiếp theo sẽ tự động tăng thêm 3% để bù đắp chi phí vận hành.</p>
  </section>

  <section class="policy-section" id="terms" style="background:#fff; border-radius:14px; padding:1.75rem; margin-bottom:1.5rem; box-shadow:0 3px 14px rgba(0,0,0,0.03);">
    <h2 style="font-size:1.3rem; font-weight:800; color:var(--text-main); margin-top:0;">Điều khoản dịch vụ</h2>
    <p>FASTFOOD hỗ trợ khách hàng tìm món, đặt món và theo dõi đơn hàng từ các quán đối tác. Khi đặt hàng, vui lòng cung cấp thông tin nhận hàng chính xác và kiểm tra món, địa chỉ, giá tiền trước khi xác nhận.</p>
    <p>Đơn hàng được xử lý theo tình trạng hoạt động và xác nhận của quán. Nếu cần thay đổi hoặc hỗ trợ, hãy liên hệ hotline <strong>1900 8888</strong>.</p>
  </section>

  <section class="policy-section" id="privacy" style="background:#fff; border-radius:14px; padding:1.75rem; margin-bottom:1.5rem; box-shadow:0 3px 14px rgba(0,0,0,0.03);">
    <h2 style="font-size:1.3rem; font-weight:800; color:var(--text-main); margin-top:0;">Chính sách bảo mật</h2>
    <p>Thông tin liên hệ, địa chỉ giao hàng và nội dung đơn hàng được sử dụng để xử lý giao dịch, giao món và hỗ trợ khách hàng. FASTFOOD cam kết bảo mật thông tin tài khoản và không chia sẻ cho bên thứ ba trái phép.</p>
    <p>Để yêu cầu hỗ trợ về thông tin tài khoản hoặc đơn hàng, liên hệ <a href="mailto:hotro@fastfood.vn">hotro@fastfood.vn</a>.</p>
  </section>

  <section class="policy-section" id="food-safety" style="background:#fff; border-radius:14px; padding:1.75rem; margin-bottom:1.5rem; box-shadow:0 3px 14px rgba(0,0,0,0.03);">
    <h2 style="font-size:1.3rem; font-weight:800; color:var(--text-main); margin-top:0;">Quy chuẩn vệ sinh an toàn thực phẩm</h2>
    <p>Các quán đối tác có trách nhiệm chế biến, đóng gói và bảo quản món ăn phù hợp với quy định an toàn thực phẩm. Khách hàng vui lòng kiểm tra tình trạng món khi nhận và báo ngay cho tổng đài nếu phát hiện vấn đề.</p>
  </section>

  <section class="policy-section" id="complaints" style="background:#fff; border-radius:14px; padding:1.75rem; box-shadow:0 3px 14px rgba(0,0,0,0.03);">
    <h2 style="font-size:1.3rem; font-weight:800; color:var(--text-main); margin-top:0;">Giải quyết khiếu nại</h2>
    <p>Khi cần phản ánh về đơn hàng, vui lòng chuẩn bị mã đơn và mô tả vấn đề để được hỗ trợ nhanh nhất.</p>
    <div style="display:flex; gap:1rem; margin-top:1.25rem;">
      <a class="btn btn-primary" href="mailto:hotro@fastfood.vn">Gửi email hỗ trợ</a>
      <a class="btn btn-outline" href="<?= getUrl('order-history.php') ?>">Xem đơn hàng của bạn</a>
    </div>
  </section>
</main>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

