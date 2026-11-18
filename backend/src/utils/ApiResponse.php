<?php
namespace FastFood\Utils;

use FastFood\Constants\HttpStatusCodes;

/**
 * Standard API Response Formatter
 */
class ApiResponse {
    public static function send(bool $success, string $message, $data = null, int $statusCode = HttpStatusCodes::OK): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        $payload = [
            'success' => $success,
            'message' => $message,
        ];
        if ($data !== null) {
            $payload['data'] = $data;
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(string $message, $data = null, int $statusCode = HttpStatusCodes::OK): void {
        self::send(true, $message, $data, $statusCode);
    }

    public static function created(string $message, $data = null): void {
        self::send(true, $message, $data, HttpStatusCodes::CREATED);
    }

    public static function error(string $message, int $statusCode = HttpStatusCodes::BAD_REQUEST, $data = null): void {
        self::send(false, $message, $data, $statusCode);
    }

    public static function paginate(string $message, array $items, int $total, int $page, int $limit): void {
        $data = [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => (int)ceil($total / max(1, $limit))
            ]
        ];
        self::send(true, $message, $data, HttpStatusCodes::OK);
    }
}

