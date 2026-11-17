<?php
namespace FastFood\Models;

use FastFood\Config\Database;
use PDO;

/**
 * Base Model with PDO Connection
 */
abstract class BaseModel {
    protected PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }
}

