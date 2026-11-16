<?php
namespace FastFood\Controllers;

use FastFood\Services\HealthService;

/**
 * Health Controller
 */
class HealthController extends BaseController {
    private HealthService $healthService;

    public function __construct() {
        $this->healthService = new HealthService();
    }

    public function getSystemHealth(): void {
        $status = $this->healthService->getFullHealthStatus();
        $isDbOk = $status['database']['databaseConnected'] ?? false;
        $message = $isDbOk 
            ? 'Hệ thống FASTFOOD API và Database đang hoạt động bình thường' 
            : 'Backend API đang chạy, nhưng chưa kết nối được MySQL';

        $this->sendSuccess($message, $status);
    }

    public function getDatabaseHealth(): void {
        $status = $this->healthService->getDatabaseHealth();
        $isDbOk = $status['databaseConnected'] ?? false;
        $message = $isDbOk ? 'Kết nối MySQL hoạt động bình thường' : 'Cơ sở dữ liệu MySQL chưa kết nối';

        $this->sendSuccess($message, $status);
    }
}

