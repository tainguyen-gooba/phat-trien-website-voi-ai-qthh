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
    return BASE_URL . ($cleanPath ? '/' . $cleanPath : '');
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

