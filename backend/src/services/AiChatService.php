<?php
namespace FastFood\Services;

use FastFood\Config\Database;
use PDO;

/**
 * AI Chat Message History Service
 */
class AiChatService {
    public static function saveMessage(int $userId, string $sender, string $message): int {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("INSERT INTO chat_messages (user_id, sender, message, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$userId, $sender, $message]);
            return (int)$db->lastInsertId();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public static function getHistory(int $userId, int $limit = 30): array {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("SELECT * FROM chat_messages WHERE user_id = ? ORDER BY id DESC LIMIT ?");
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            return array_reverse($rows);
        } catch (\Exception $e) {
            return [];
        }
    }
}

