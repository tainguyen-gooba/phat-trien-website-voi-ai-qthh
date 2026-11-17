<?php
namespace FastFood\Services;

use FastFood\Models\HealthModel;

/**
 * Health Check & Diagnostics Service
 */
class HealthService {
    private HealthModel $healthModel;

    public function __construct() {
        $this->healthModel = new HealthModel();
    }

    public function getFullHealthStatus(): array {
        $dbStatus = $this->healthModel->pingDatabase();
        return [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'runtime' => 'PHP ' . PHP_VERSION,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP CLI Server',
            'database' => $dbStatus,
            'memoryUsage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
        ];
    }

    public function getDatabaseHealth(): array {
        return $this->healthModel->pingDatabase();
    }
}

