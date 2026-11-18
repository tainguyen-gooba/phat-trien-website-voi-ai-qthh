<?php
/**
 * FASTFOOD - Backend API Entry Point (PHP Native)
 */

// Autoload FastFood namespace
spl_autoload_register(function ($class) {
    $prefix = 'FastFood\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use FastFood\Middlewares\ErrorHandlerMiddleware;
use FastFood\Routes\ApiRouter;

ErrorHandlerMiddleware::register();
ApiRouter::dispatch();

