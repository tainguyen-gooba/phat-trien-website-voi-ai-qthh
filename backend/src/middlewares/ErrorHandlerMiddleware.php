<?php
namespace FastFood\Middlewares;

use FastFood\Utils\ApiResponse;
use FastFood\Constants\HttpStatusCodes;
use Throwable;

/**
 * Global Exception and Error Handler
 */
class ErrorHandlerMiddleware {
    public static function register(): void {
        set_exception_handler(function(Throwable $e) {
            ApiResponse::error('Lỗi máy chủ nội bộ: ' . $e->getMessage(), HttpStatusCodes::INTERNAL_SERVER_ERROR);
        });

        set_error_handler(function($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) return false;
            // Chỉ ngắt script với lỗi nghiêm trọng (Fatal, Parse, User Error)
            // Bỏ qua Warning/Notice về headers/session để không làm gián đoạn API
            if (!in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR])) {
                return false;
            }
            ApiResponse::error("Lỗi PHP: {$message} tại dòng {$line}", HttpStatusCodes::INTERNAL_SERVER_ERROR);
        });
    }
}

