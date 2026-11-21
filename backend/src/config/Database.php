<?php
namespace FastFood\Config;

use PDO;
use PDOException;

/**
 * Database Singleton Connection
 */
class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            date_default_timezone_set('Asia/Ho_Chi_Minh');
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $defaultPort = getenv('DB_PORT') ?: '3301';
            $ports = array_unique(array_filter([$defaultPort, '3301', '3306']));
            $name = getenv('DB_NAME') ?: 'fastfood_db';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') ?: '');
            $charset = 'utf8mb4';

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $lastException = null;
            foreach ($ports as $port) {
                try {
                    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
                    self::$instance = new PDO($dsn, $user, $pass, $options);
                    return self::$instance;
                } catch (PDOException $e) {
                    $lastException = $e;
                }
            }

            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi kết nối MySQL Database: ' . ($lastException ? $lastException->getMessage() : 'Unknown error')
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        return self::$instance;
    }
}

