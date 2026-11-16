<?php
/**
 * Test AI Chat API (PHP)
 */
require_once dirname(__DIR__) . '/src/services/AiService.php';
require_once dirname(__DIR__) . '/src/config/Database.php';

use FastFood\Services\AiService;

$tests = [
    'Cho mình xem món nào dưới 50k',
    'Giờ mở cửa của quán thế nào',
    'Pizza Hut Italia có món gì'
];

foreach ($tests as $t) {
    echo "Prompt: $t\n";
    $res = AiService::processPrompt($t);
    echo "Reply: " . $res['reply'] . "\n";
    echo "----------------------------------------\n";
}

