<?php
/**
 * FASTFOOD - Cron Job Tự Động Tặng Voucher Sinh Nhật Khách Hàng
 * File: cron/birthday_vouchers.php
 * Chạy hằng ngày (00:05 mỗi ngày) qua Task Scheduler / Cron:
 * php D:\phat-trien-website-voi-ai-qthh\cron\birthday_vouchers.php
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/helpers/voucher.php';

$isCli = (php_sapi_name() === 'cli' || defined('STDIN'));

// Nếu gọi qua HTTP Web, yêu cầu đăng nhập Admin hoặc kèm bí mật token
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $secret = $_GET['secret'] ?? '';
    $cronSecret = defined('CRON_SECRET') ? CRON_SECRET : 'fastfood_cron_secret_2026';
    if ($secret !== $cronSecret && !isAdminOrStaff()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Truy cập bị từ chối. Cần quyền Admin hoặc Secret key hợp lệ.']);
        exit;
    }
}

$startTime = microtime(true);
$result = runDailyBirthdayVouchers();
$executionTime = round((microtime(true) - $startTime) * 1000, 2);

$response = [
    'success'          => true,
    'message'          => "Đã quét và tặng thành công {$result['total_gifted']}/{$result['total_candidates']} voucher sinh nhật.",
    'total_candidates' => $result['total_candidates'],
    'total_gifted'     => $result['total_gifted'],
    'gifted_list'      => $result['gifted_list'],
    'executed_at'      => $result['executed_at'],
    'execution_time_ms'=> $executionTime
];

if ($isCli) {
    echo "=======================================================\n";
    echo "🎂 FASTFOOD BIRTHDAY VOUCHER CRON JOB EXECUTED\n";
    echo "Thời gian: " . $result['executed_at'] . "\n";
    echo "Ứng viên sinh nhật hôm nay: " . $result['total_candidates'] . "\n";
    echo "Đã tặng thành công: " . $result['total_gifted'] . "\n";
    if (!empty($result['gifted_list'])) {
        foreach ($result['gifted_list'] as $g) {
            echo " - Tặng user #{$g['user']['id']} ({$g['user']['full_name']}): Mã {$g['code']} giảm {$g['discount_percent']}%\n";
        }
    }
    echo "Thời gian xử lý: {$executionTime} ms\n";
    echo "=======================================================\n";
} else {
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
