<?php
namespace FastFood\Models;

use PDO;
use Exception;

/**
 * Health & Diagnostics Model
 */
class HealthModel extends BaseModel {
    public function pingDatabase(): array {
        try {
            $start = microtime(true);
            $stmt = $this->db->query("SELECT 1 AS alive");
            $alive = $stmt->fetchColumn();
            $latencyMs = round((microtime(true) - $start) * 1000, 2);

            $tables = ['categories', 'products', 'orders', 'users', 'restaurants'];
            $counts = [];
            foreach ($tables as $table) {
                try {
                    $counts[$table] = (int)$this->db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
                } catch (Exception $e) {
                    $counts[$table] = null;
                }
            }

            return [
                'databaseConnected' => (bool)$alive,
                'latencyMs' => $latencyMs,
                'tables' => $counts
            ];
        } catch (Exception $e) {
            return [
                'databaseConnected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

