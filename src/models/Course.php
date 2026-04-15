<?php

namespace SkillSwap\Models;

use SkillSwap\Utils\Database;

/**
 * Course Model - UPDATED VERSION
 * Added support for: category_id, is_free, instructor_name, telegram_link
 */
class Course {
    /**
     * Create a new course
     */
    public static function create(array $data): ?string {
        $id = Database::generateUUID();
        
        $sql = "INSERT INTO courses (
                    id, title, description, teacher, category, category_id, 
                    price, is_free, image_url, instructor_name, telegram_link,
                    instructor_id, status, created_at
                ) VALUES (
                    :id, :title, :description, :teacher, :category, :category_id,
                    :price, :is_free, :image_url, :instructor_name, :telegram_link,
                    :instructor_id, 'pending', NOW()
                )";
        
        // Calculate is_free based on price
        $price = floatval($data['price'] ?? 0);
        $is_free = isset($data['is_free']) ? (bool)$data['is_free'] : ($price == 0);
        
        $params = [
            ':id' => $id,
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':teacher' => $data['teacher'] ?? $data['instructor_name'] ?? 'Unknown',
            ':category' => $data['category'] ?? 'Uncategorized',
            ':category_id' => $data['category_id'] ?? 1,
            ':price' => $price,
            ':is_free' => $is_free ? 1 : 0,
            ':image_url' => $data['image_url'] ?? null,
            ':instructor_name' => $data['instructor_name'] ?? null,
            ':telegram_link' => $data['telegram_link'] ?? null,
            ':instructor_id' => $data['instructor_id']
        ];
        
        return Database::execute($sql, $params) ? $id : null;
    }
    
    /**
     * Find course by ID with category info
     */
    public static function findById(string $id): ?array {
        $sql = "SELECT c.*, 
                       cat.name as category_name, 
                       cat.slug as category_slug,
                       u.first_name, 
                       u.last_name, 
                       u.email as instructor_email 
                FROM courses c 
                LEFT JOIN categories cat ON c.category_id = cat.id
                LEFT JOIN users u ON c.instructor_id = u.id 
                WHERE c.id = :id";
        
        return Database::queryOne($sql, [':id' => $id]);
    }
    
    /**
     * Get all courses with filters and pagination
     */
    public static function getAll(
        int $page = 1, 
        int $limit = 20, 
        ?int $category_id = null, 
        ?string $status = null, 
        ?string $search = null,
        ?bool $is_free = null
    ): array {
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT c.*, 
                       cat.name as category_name,
                       cat.slug as category_slug,
                       u.first_name, 
                       u.last_name 
                FROM courses c 
                LEFT JOIN categories cat ON c.category_id = cat.id
                LEFT JOIN users u ON c.instructor_id = u.id 
                WHERE 1=1";
        
        // Filter by category_id
        if ($category_id) {
            $sql .= " AND c.category_id = :category_id";
            $params[':category_id'] = $category_id;
        }
        
        // Filter by old category field (backward compatibility)
        if (isset($data['category']) && !$category_id) {
            $sql .= " AND c.category = :category";
            $params[':category'] = $data['category'];
        }
        
        // Filter by status
        if ($status) {
            $sql .= " AND c.status = :status";
            $params[':status'] = $status;
        }
        
        // Filter by is_free
        if ($is_free !== null) {
            $sql .= " AND c.is_free = :is_free";
            $params[':is_free'] = $is_free ? 1 : 0;
        }
        
        // Search
        if ($search) {
            $sql .= " AND (c.title LIKE :search OR c.description LIKE :search OR c.teacher LIKE :search OR c.instructor_name LIKE :search)";
            $params[':search'] = "%{$search}%";
        }
        
        $sql .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        return Database::query($sql, $params);
    }
    
    /**
     * Get courses by instructor
     */
    public static function getByInstructor(string $instructorId): array {
        $sql = "SELECT c.*, cat.name as category_name 
                FROM courses c 
                LEFT JOIN categories cat ON c.category_id = cat.id
                WHERE c.instructor_id = :instructor_id 
                ORDER BY c.created_at DESC";
        
        return Database::query($sql, [':instructor_id' => $instructorId]);
    }
    
    /**
     * Get courses by category
     */
    public static function getByCategory(int $categoryId, int $page = 1, int $limit = 20): array {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT c.*, 
                       cat.name as category_name,
                       u.first_name, 
                       u.last_name 
                FROM courses c 
                LEFT JOIN categories cat ON c.category_id = cat.id
                LEFT JOIN users u ON c.instructor_id = u.id 
                WHERE c.category_id = :category_id 
                  AND c.status = 'approved'
                ORDER BY c.created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $params = [
            ':category_id' => $categoryId,
            ':limit' => $limit,
            ':offset' => $offset
        ];
        
        return Database::query($sql, $params);
    }
    
    /**
     * Get free courses
     */
    public static function getFreeCourses(int $page = 1, int $limit = 20): array {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT c.*, 
                       cat.name as category_name,
                       u.first_name, 
                       u.last_name 
                FROM courses c 
                LEFT JOIN categories cat ON c.category_id = cat.id
                LEFT JOIN users u ON c.instructor_id = u.id 
                WHERE c.is_free = 1 
                  AND c.status = 'approved'
                ORDER BY c.created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $params = [
            ':limit' => $limit,
            ':offset' => $offset
        ];
        
        return Database::query($sql, $params);
    }
    
    /**
     * Update course
     */
    public static function update(string $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = [
            'title', 'description', 'teacher', 'category', 'category_id',
            'price', 'is_free', 'image_url', 'instructor_name', 'telegram_link'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        
        // Auto-calculate is_free if price is updated
        if (isset($data['price']) && !isset($data['is_free'])) {
            $fields[] = "is_free = :is_free";
            $params[':is_free'] = ($data['price'] == 0) ? 1 : 0;
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE courses SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";
        
        return Database::execute($sql, $params);
    }
    
    /**
     * Approve course
     */
    public static function approve(string $id, string $adminId): bool {
        $sql = "UPDATE courses SET status = 'approved', approved_at = NOW(), approved_by = :admin_id WHERE id = :id";
        
        return Database::execute($sql, [
            ':id' => $id,
            ':admin_id' => $adminId
        ]);
    }
    
    /**
     * Reject course
     */
    public static function reject(string $id, string $adminId, ?string $reason = null): bool {
        $sql = "UPDATE courses SET status = 'rejected', approved_by = :admin_id, rejection_reason = :reason WHERE id = :id";
        
        return Database::execute($sql, [
            ':id' => $id,
            ':admin_id' => $adminId,
            ':reason' => $reason
        ]);
    }
    
    /**
     * Delete course
     */
    public static function delete(string $id): bool {
        $sql = "DELETE FROM courses WHERE id = :id";
        return Database::execute($sql, [':id' => $id]);
    }
    
    /**
     * Bulk delete courses
     */
    public static function bulkDelete(array $courseIds): bool {
        if (empty($courseIds)) {
            return false;
        }
        
        $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
        $sql = "DELETE FROM courses WHERE id IN ({$placeholders})";
        
        return Database::execute($sql, $courseIds);
    }
    
    /**
     * Bulk update status (activate/deactivate)
     */
    public static function bulkUpdateStatus(array $courseIds, string $status): bool {
        if (empty($courseIds) || !in_array($status, ['approved', 'pending', 'rejected'])) {
            return false;
        }
        
        $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
        $sql = "UPDATE courses SET status = ? WHERE id IN ({$placeholders})";
        
        $params = array_merge([$status], $courseIds);
        
        return Database::execute($sql, $params);
    }
    
    /**
     * Get course count
     */
    public static function getCount(?string $status = null, ?int $categoryId = null, ?bool $isFree = null): int {
        $sql = "SELECT COUNT(*) as count FROM courses WHERE 1=1";
        $params = [];
        
        if ($status) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        
        if ($categoryId) {
            $sql .= " AND category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }
        
        if ($isFree !== null) {
            $sql .= " AND is_free = :is_free";
            $params[':is_free'] = $isFree ? 1 : 0;
        }
        
        $result = Database::queryOne($sql, $params);
        return (int)$result['count'];
    }
    
    /**
     * Get course statistics
     */
    public static function getStats(): array {
        $sql = "SELECT 
                    COUNT(*) as total_courses,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_courses,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_courses,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_courses,
                    SUM(CASE WHEN is_free = 1 THEN 1 ELSE 0 END) as free_courses,
                    SUM(CASE WHEN is_free = 0 THEN 1 ELSE 0 END) as paid_courses,
                    SUM(CASE WHEN is_free = 0 THEN price ELSE 0 END) as total_revenue,
                    AVG(CASE WHEN is_free = 0 THEN price ELSE NULL END) as avg_price
                FROM courses";
        
        return Database::queryOne($sql) ?? [];
    }
    
    /**
     * Get stats by category
     */
    public static function getStatsByCategory(): array {
        $sql = "SELECT 
                    cat.id,
                    cat.name as category_name,
                    cat.slug as category_slug,
                    COUNT(c.id) as course_count,
                    SUM(CASE WHEN c.is_free = 1 THEN 1 ELSE 0 END) as free_count,
                    SUM(CASE WHEN c.is_free = 0 THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN c.is_free = 0 THEN c.price ELSE 0 END) as total_revenue
                FROM categories cat
                LEFT JOIN courses c ON cat.id = c.category_id
                GROUP BY cat.id
                ORDER BY course_count DESC";
        
        return Database::query($sql);
    }
}
