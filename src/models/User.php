<?php

namespace SkillSwap\Models;

use SkillSwap\Utils\Database;

class User {
    /**
     * Create a new user
     */
    public static function create(array $data): ?string {
        $id = Database::generateUUID();
        
        $sql = "INSERT INTO users (id, first_name, last_name, email, phone, password, role, created_at) 
                VALUES (:id, :first_name, :last_name, :email, :phone, :password, :role, NOW())";
        
        $params = [
            ':id' => $id,
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
            ':role' => $data['role'] ?? 'user'
        ];
        
        return Database::execute($sql, $params) ? $id : null;
    }
    
    /**
     * Find user by ID
     */
    public static function findById(string $id): ?array {
        $sql = "SELECT id, first_name, last_name, email, phone, role, avatar, balance, 
                       is_active, is_banned, theme, last_login_at, created_at, updated_at 
                FROM users WHERE id = :id";
        
        return Database::queryOne($sql, [':id' => $id]);
    }
    
    /**
     * Find user by email
     */
    public static function findByEmail(string $email): ?array {
        $sql = "SELECT * FROM users WHERE email = :email";
        return Database::queryOne($sql, [':email' => $email]);
    }
    
    /**
     * Get all users with pagination and filters
     */
    public static function getAll(int $page = 1, int $limit = 20, ?string $role = null, ?string $search = null): array {
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT id, first_name, last_name, email, phone, role, avatar, balance, 
                       is_active, is_banned, last_login_at, created_at 
                FROM users WHERE 1=1";
        
        if ($role) {
            $sql .= " AND role = :role";
            $params[':role'] = $role;
        }
        
        if ($search) {
            $sql .= " AND (first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)";
            $params[':search'] = "%{$search}%";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        return Database::query($sql, $params);
    }
    
    /**
     * Get total users count
     */
    public static function getCount(?string $role = null): int {
        $sql = "SELECT COUNT(*) as count FROM users WHERE 1=1";
        $params = [];
        
        if ($role) {
            $sql .= " AND role = :role";
            $params[':role'] = $role;
        }
        
        $result = Database::queryOne($sql, $params);
        return (int)$result['count'];
    }
    
    /**
     * Update user
     */
    public static function update(string $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = ['first_name', 'last_name', 'email', 'phone', 'role', 'avatar', 'balance', 'theme'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";
        
        return Database::execute($sql, $params);
    }
    
    /**
     * Update password
     */
    public static function updatePassword(string $id, string $newPassword): bool {
        $sql = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id";
        
        return Database::execute($sql, [
            ':id' => $id,
            ':password' => password_hash($newPassword, PASSWORD_BCRYPT)
        ]);
    }
    
    /**
     * Ban user
     */
    public static function ban(string $id): bool {
        $sql = "UPDATE users SET is_banned = TRUE, is_active = FALSE WHERE id = :id";
        return Database::execute($sql, [':id' => $id]);
    }
    
    /**
     * Unban user
     */
    public static function unban(string $id): bool {
        $sql = "UPDATE users SET is_banned = FALSE, is_active = TRUE WHERE id = :id";
        return Database::execute($sql, [':id' => $id]);
    }
    
    /**
     * Delete user
     */
    public static function delete(string $id): bool {
        $sql = "DELETE FROM users WHERE id = :id";
        return Database::execute($sql, [':id' => $id]);
    }
    
    /**
     * Update last login timestamp
     */
    public static function updateLastLogin(string $id): bool {
        $sql = "UPDATE users SET last_login_at = NOW() WHERE id = :id";
        return Database::execute($sql, [':id' => $id]);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword(string $email, string $password): ?array {
        $user = self::findByEmail($email);
        
        if (!$user) {
            return null;
        }
        
        if (password_verify($password, $user['password'])) {
            return $user;
        }
        
        return null;
    }
    
    /**
     * Get user statistics
     */
    public static function getStats(string $userId): array {
        $sql = "SELECT * FROM user_stats WHERE id = :id";
        $result = Database::queryOne($sql, [':id' => $userId]);
        
        return $result ?? [
            'courses_enrolled' => 0,
            'total_purchases' => 0,
            'total_spent' => 0
        ];
    }
}
