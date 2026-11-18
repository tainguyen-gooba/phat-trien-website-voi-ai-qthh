<?php
namespace FastFood\Models;

use PDO;

/**
 * Auth & User Model
 */
class AuthModel extends BaseModel {
    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT id, full_name, email, phone, address, role_id, status, created_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO users (full_name, email, password_hash, phone, address, role_id, status, created_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $data['fullName'] ?? '',
            $data['email'],
            $data['passwordHash'],
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['roleId'] ?? 2,
            $data['status'] ?? 'active'
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateProfile(int $id, array $data): bool {
        $stmt = $this->db->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([
            $data['fullName'],
            $data['phone'],
            $data['address'],
            $id
        ]);
    }
}
