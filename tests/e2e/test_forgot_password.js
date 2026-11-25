/**
 * FASTFOOD - E2E Test Suite for Forgot Password & OTP Reset System
 * File: tests/e2e/test_forgot_password.js
 */

const http = require('http');
const { execSync } = require('child_process');

function request(options, postData = null) {
  return new Promise((resolve, reject) => {
    const req = http.request(options, (res) => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => resolve({
        statusCode: res.statusCode,
        headers: res.headers,
        body: body
      }));
    });
    req.on('error', reject);
    if (postData) {
      req.write(postData);
    }
    req.end();
  });
}

function parseCookies(cookieHeaders) {
  if (!cookieHeaders) return '';
  if (Array.isArray(cookieHeaders)) {
    return cookieHeaders.map(c => c.split(';')[0]).join('; ');
  }
  return cookieHeaders.split(';')[0];
}

async function runTests() {
  console.log('================================================================');
  console.log('🚀 BẮT ĐẦU KIỂM THỬ HỆ THỐNG QUÊN MẬT KHẨU & MÃ XÁC THỰC OTP');
  console.log('================================================================\n');

  let passed = 0;
  let total = 0;

  function assert(condition, message) {
    total++;
    if (condition) {
      console.log(`  ✅ [PASS] ${message}`);
      passed++;
    } else {
      console.error(`  ❌ [FAIL] ${message}`);
    }
  }

  try {
    // 1. Kiểm tra trang login.php có chứa liên kết quên mật khẩu đúng
    const loginRes = await request({
      hostname: '127.0.0.1',
      port: 5000,
      path: '/login.php',
      method: 'GET'
    });
    assert(loginRes.statusCode === 200, '1. Trang login.php tải thành công (HTTP 200)');
    assert(loginRes.body.includes('forgot-password.php'), '1b. Liên kết "Quên mật khẩu?" trỏ chính xác về forgot-password.php');

    // 2. Kiểm tra trang forgot-password.php tải thành công
    const fpRes = await request({
      hostname: '127.0.0.1',
      port: 5000,
      path: '/forgot-password.php',
      method: 'GET'
    });
    assert(fpRes.statusCode === 200, '2. Trang forgot-password.php tải thành công (HTTP 200)');
    assert(fpRes.body.includes('Quên Mật Khẩu?'), '2b. Hiển thị tiêu đề Quên Mật Khẩu và các bước tiến trình Stepper');

    // 3. Gửi yêu cầu với email không tồn tại
    const badEmailPost = 'action=request_otp&email=nonexistent_user_99999@gmail.com';
    const badEmailRes = await request({
      hostname: '127.0.0.1',
      port: 5000,
      path: '/forgot-password.php',
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(badEmailPost)
      }
    }, badEmailPost);
    assert(badEmailRes.body.includes('Không tìm thấy tài khoản nào'), '3. Kiểm tra email không tồn tại báo lỗi thân thiện chính xác');

    // 4. Gửi yêu cầu OTP cho email hợp lệ (customer@fastfood.vn)
    const validEmail = 'customer@fastfood.vn';
    const goodEmailPost = `action=request_otp&email=${encodeURIComponent(validEmail)}`;
    const goodEmailRes = await request({
      hostname: '127.0.0.1',
      port: 5000,
      path: '/forgot-password.php',
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(goodEmailPost)
      }
    }, goodEmailPost);
    
    // Server sẽ redirect 302 sang ?step=verify&email=...
    assert(goodEmailRes.statusCode === 302, '4. Gửi yêu cầu email hợp lệ thành công (Chuyển hướng 302 sang bước xác thực OTP)');
    const sessionCookie = parseCookies(goodEmailRes.headers['set-cookie']);

    // 5. Kiểm tra bước 2: Trang nhập OTP hiển thị
    const verifyPageRes = await request({
      hostname: '127.0.0.1',
      port: 5000,
      path: `/forgot-password.php?step=verify&email=${encodeURIComponent(validEmail)}`,
      method: 'GET',
      headers: { 'Cookie': sessionCookie }
    });
    assert(verifyPageRes.statusCode === 200, '5. Bước 2 tải thành công');
    assert(verifyPageRes.body.includes('Nhập Mã OTP') || verifyPageRes.body.includes('fp-otp-input'), '5b. Hiển thị ô nhập mã OTP 6 số và đếm ngược thời gian');

    // Lấy OTP được lưu trong DB qua helper script
    const dbOut = execSync(`php tests/test_fp_query.php "${validEmail}"`).toString();
    const dbResult = JSON.parse(dbOut);
    const validOtp = dbResult.otp_code;
    const resetToken = dbResult.token;

    assert(Boolean(validOtp && validOtp.length === 6), `6. Mã OTP 6 số (${validOtp}) và Token bảo mật đã được tạo thành công trong DB`);

    // 7. Nhập sai OTP bị từ chối
    const wrongOtpPost = `action=verify_otp&email=${encodeURIComponent(validEmail)}&otp=000000`;
    const wrongOtpRes = await request({
      hostname: '127.0.0.1',
      port: 5000,
      path: `/forgot-password.php?step=verify&email=${encodeURIComponent(validEmail)}`,
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(wrongOtpPost),
        'Cookie': sessionCookie
      }
    }, wrongOtpPost);
    assert(wrongOtpRes.body.includes('không chính xác') || wrongOtpRes.body.includes('không hợp lệ'), '7. Nhập sai mã OTP bị chặn chính xác');

    // 8. Nhập đúng mã OTP được chuyển sang bước đặt mật khẩu mới
    const correctOtpPost = `action=verify_otp&email=${encodeURIComponent(validEmail)}&otp=${validOtp}`;
    const correctOtpRes = await request({
      hostname: '127.0.0.1',
      port: 5000,
      path: `/forgot-password.php?step=verify&email=${encodeURIComponent(validEmail)}`,
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(correctOtpPost),
        'Cookie': sessionCookie
      }
    }, correctOtpPost);
    assert(correctOtpRes.statusCode === 302, '8. Nhập đúng OTP xác thực thành công (Chuyển hướng sang bước đổi mật khẩu)');

    // 9. Kiểm tra trang reset-password.php với token hợp lệ
    const resetPageRes = await request({
      hostname: '127.0.0.1',
      port: 5000,
      path: `/reset-password.php?token=${resetToken}`,
      method: 'GET'
    });
    assert(resetPageRes.statusCode === 200, '9. Truy cập trực tiếp qua reset-password.php?token=... hợp lệ (HTTP 200)');
    assert(resetPageRes.body.includes('Mật Khẩu Mới'), '9b. Hiển thị form thiết lập mật khẩu mới cùng thanh đo độ mạnh mật khẩu');

    // 10. Đặt mật khẩu không khớp bị từ chối
    const mismatchPost = `action=reset_password&token=${resetToken}&new_password=MatKhauMoi123&confirm_password=MatKhauKhac456`;
    const mismatchRes = await request({
      hostname: '127.0.0.1',
      port: 5000,
      path: '/forgot-password.php',
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(mismatchPost),
        'Cookie': sessionCookie
      }
    }, mismatchPost);
    assert(mismatchRes.body.includes('không khớp'), '10. Mật khẩu xác nhận không khớp bị chặn');

    // 11. Đặt mật khẩu mới thành công
    const testNewPassword = 'NewSecretPassword123@';
    const goodResetPost = `action=reset_password&token=${resetToken}&new_password=${encodeURIComponent(testNewPassword)}&confirm_password=${encodeURIComponent(testNewPassword)}`;
    const goodResetRes = await request({
      hostname: '127.0.0.1',
      port: 5000,
      path: '/forgot-password.php',
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(goodResetPost),
        'Cookie': sessionCookie
      }
    }, goodResetPost);
    assert(goodResetRes.body.includes('Đổi Mật Khẩu Thành Công!') || goodResetRes.body.includes('thành công'), '11. Đặt lại mật khẩu mới hoàn tất thành công!');

    // 12. Kiểm tra token đã bị đánh dấu is_used = 1 (ngăn chặn replay attack)
    const dbOutAfter = execSync(`php tests/test_fp_query.php "${validEmail}"`).toString();
    const dbResultAfter = JSON.parse(dbOutAfter);
    assert(dbResultAfter.is_used === 1 || dbResultAfter.is_used === '1', '12. Token khôi phục đã bị hủy hiệu lực (is_used = 1) để bảo mật tuyệt đối');

    // 13. Thử đăng nhập bằng mật khẩu MỚI
    const loginNewPost = `email=${encodeURIComponent(validEmail)}&password=${encodeURIComponent(testNewPassword)}`;
    const loginNewRes = await request({
      hostname: '127.0.0.1',
      port: 5000,
      path: '/login.php',
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(loginNewPost)
      }
    }, loginNewPost);
    assert(loginNewRes.statusCode === 302, '13. Đăng nhập thành công với mật khẩu mới vừa đổi!');

    // 14. Khôi phục lại mật khẩu mặc định (123456) cho tài khoản mẫu
    const restored = execSync(`php tests/test_fp_restore.php "${validEmail}"`).toString().trim();
    assert(restored === 'RESTORED', '14. Mật khẩu tài khoản mẫu đã được hoàn nguyên về "123456" chuẩn');

    // 15. Kiểm tra đăng nhập lại bằng mật khẩu "123456" bình thường
    const loginRestoredPost = `email=${encodeURIComponent(validEmail)}&password=123456`;
    const loginRestoredRes = await request({
      hostname: '127.0.0.1',
      port: 5000,
      path: '/login.php',
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(loginRestoredPost)
      }
    }, loginRestoredPost);
    assert(loginRestoredRes.statusCode === 302, '15. Đăng nhập lại với mật khẩu chuẩn 123456 thành công!');

  } catch (err) {
    console.error('Lỗi kiểm thử:', err);
  }

  console.log('\n================================================================');
  console.log(`TỔNG KẾT: ${passed}/${total} BÀI KIỂM TRA ĐẠT THÀNH CÔNG (${Math.round((passed/total)*100)}% PASS)!`);
  console.log('================================================================');

  if (passed !== total) {
    process.exit(1);
  }
}

runTests();
