<?php
namespace FastFood\Services;

use FastFood\Config\Database;

/**
 * Product Recommendation Engine
 */
class ProductRecommendationService {
    public static function getRecommendations(?int $categoryId = null, int $limit = 6): array {
        $db = Database::getConnection();
        if ($categoryId) {
            $stmt = $db->prepare("SELECT p.*, c.name AS category_name, r.name AS restaurant_name 
                                  FROM products p 
                                  LEFT JOIN categories c ON p.category_id = c.id 
                                  LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                                  WHERE p.category_id = ? AND p.is_available = 1 
                                  ORDER BY p.rating_avg DESC, p.order_count DESC LIMIT ?");
            $stmt->bindValue(1, $categoryId, \PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $db->prepare("SELECT p.*, c.name AS category_name, r.name AS restaurant_name 
                              FROM products p 
                              LEFT JOIN categories c ON p.category_id = c.id 
                              LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                              WHERE p.is_available = 1 
                              ORDER BY p.order_count DESC LIMIT ?");
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

