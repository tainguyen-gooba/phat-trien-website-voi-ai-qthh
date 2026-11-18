<?php
namespace FastFood\Controllers;

use FastFood\Utils\ApiResponse;

/**
 * Base Controller
 */
abstract class BaseController {
    protected function sendSuccess(string $message, $data = null, int $statusCode = 200): void {
        ApiResponse::send(true, $message, $data, $statusCode);
    }

    protected function sendCreated(string $message, $data = null): void {
        ApiResponse::created($message, $data);
    }

    protected function sendError(string $message, int $statusCode = 400, $data = null): void {
        ApiResponse::error($message, $statusCode, $data);
    }

    protected function getJsonBody(): array {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (is_array($data) && !empty($data)) {
            return $data;
        }
        return !empty($_POST) ? $_POST : [];
    }
}
