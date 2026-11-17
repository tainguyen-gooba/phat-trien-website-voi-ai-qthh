<?php
/**
 * FASTFOOD - Chuẩn hóa phản hồi JSON cho AJAX / API
 */

function jsonSuccess($message = 'Thành công', $data = [], $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'   => true,
        'message'   => $message,
        'data'      => $data,
        'timestamp' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($message = 'Đã có lỗi xảy ra', $code = 400, $data = null) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $payload = [
        'success'   => false,
        'message'   => $message,
        'timestamp' => date('c'),
    ];
    if ($data !== null) {
        $payload['data'] = $data;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

