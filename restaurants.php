<?php
/**
 * FASTFOOD - Đã chuyển hướng sang thực đơn món ăn (foods.php)
 */
require_once __DIR__ . '/config/config.php';
header('Location: ' . getUrl('foods.php'), true, 301);
exit;
