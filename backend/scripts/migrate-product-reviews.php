<?php
/**
 * Migrate Product Reviews System & Backfill Initial Reviews
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/helpers/reviews.php';

$db = getDB();
ensureProductReviewsSchema($db, true);

$total = (int)$db->query("SELECT COUNT(*) FROM product_reviews")->fetchColumn();
echo "=== PRODUCT REVIEWS MIGRATION COMPLETED ($total reviews ready) ===\n";
