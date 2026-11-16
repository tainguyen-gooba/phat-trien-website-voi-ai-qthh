<?php
namespace FastFood\Utils;

/**
 * Basic Application Logger
 */
class Logger {
    public static function info(string $message, array $context = []): void {
        self::log('INFO', $message, $context);
    }

    public static function warn(string $message, array $context = []): void {
        self::log('WARN', $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::log('ERROR', $message, $context);
    }

    private static function log(string $level, string $message, array $context = []): void {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        error_log("[{$timestamp}] [{$level}] {$message}{$contextStr}");
    }
}

