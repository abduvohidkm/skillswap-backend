<?php

namespace SkillSwap\Models;

use SkillSwap\Utils\Database;

/**
 * Category Model
 * Manages course categories
 */
class Category {
    /**
     * Get all categories with course count
     */
    public static function getAll(): array {
        $sql = "SELECT cat.*, COUNT(c.id) as course_count 
                FROM categories cat 
                LEFT JOIN courses c ON cat.id = c.category_id 
                GROUP BY cat.id 
                ORDER BY cat.name ASC";
        
        return Database::query($sql);
    }
    
    /**
     * Find category by ID
     */
    public static function findById(int $id): ?array {
        $sql = "SELECT cat.*, COUNT(c.id) as course_count 
                FROM categories cat 
                LEFT JOIN courses c ON cat.id = c.category_id 
                WHERE cat.id = :id 
                GROUP BY cat.id";
        
        return Database::queryOne($sql, [':id' => $id]);
    }
    
    /**
     * Find category by slug
     */
    public static function findBySlug(string $slug): ?array {
        $sql = "SELECT * FROM categories WHERE slug = :slug";
        return Database::queryOne($sql, [':slug' => $slug]);
    }
    
    /**
     * Find category by name
     */
    public static function findByName(string $name): ?array {
        $sql = "SELECT * FROM categories WHERE name = :name";
        return Database::queryOne($sql, [':name' => $name]);
    }
    
    /**
     * Create a new category
     */
    public static function create(array $data): ?int {
        // Validate name
        if (empty($data['name']) || strlen($data['name']) < 3 || strlen($data['name']) > 50) {
            return null;
        }
        
        // Create slug from name
        $slug = self::createSlug($data['name']);
        
        // Check for duplicates
        if (self::findByName($data['name']) || self::findBySlug($slug)) {
            return null;
        }
        
        $sql = "INSERT INTO categories (name, slug) VALUES (:name, :slug)";
        
        $params = [
            ':name' => trim($data['name']),
            ':slug' => $slug
        ];
        
        if (Database::execute($sql, $params)) {
            return Database::lastInsertId();
        }
        
        return null;
    }
    
    /**
     * Update category
     */
    public static function update(int $id, array $data): bool {
        // Check if category exists
        if (!self::findById($id)) {
            return false;
        }
        
        // Validate name
        if (empty($data['name']) || strlen($data['name']) < 3 || strlen($data['name']) > 50) {
            return false;
        }
        
        // Create new slug
        $slug = self::createSlug($data['name']);
        
        // Check for duplicate name (excluding current category)
        $existing = self::findByName($data['name']);
        if ($existing && $existing['id'] != $id) {
            return false;
        }
        
        $sql = "UPDATE categories SET name = :name, slug = :slug, updated_at = NOW() WHERE id = :id";
        
        $params = [
            ':id' => $id,
            ':name' => trim($data['name']),
            ':slug' => $slug
        ];
        
        return Database::execute($sql, $params);
    }
    
    /**
     * Delete category
     */
    public static function delete(int $id): bool {
        // Check if category has courses
        $category = self::findById($id);
        if (!$category) {
            return false;
        }
        
        if ($category['course_count'] > 0) {
            // Cannot delete category with courses
            return false;
        }
        
        $sql = "DELETE FROM categories WHERE id = :id";
        return Database::execute($sql, [':id' => $id]);
    }
    
    /**
     * Get category count
     */
    public static function getCount(): int {
        $sql = "SELECT COUNT(*) as count FROM categories";
        $result = Database::queryOne($sql);
        return (int)$result['count'];
    }
    
    /**
     * Get categories with pagination
     */
    public static function paginate(int $page = 1, int $limit = 20): array {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT cat.*, COUNT(c.id) as course_count 
                FROM categories cat 
                LEFT JOIN courses c ON cat.id = c.category_id 
                GROUP BY cat.id 
                ORDER BY cat.name ASC 
                LIMIT :limit OFFSET :offset";
        
        $params = [
            ':limit' => $limit,
            ':offset' => $offset
        ];
        
        return Database::query($sql, $params);
    }
    
    /**
     * Create URL-friendly slug from name
     */
    private static function createSlug(string $name): string {
        // Convert to lowercase
        $slug = strtolower($name);
        
        // Replace spaces and special characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        
        // Remove leading and trailing hyphens
        $slug = trim($slug, '-');
        
        return $slug;
    }
    
    /**
     * Get popular categories (by course count)
     */
    public static function getPopular(int $limit = 5): array {
        $sql = "SELECT cat.*, COUNT(c.id) as course_count 
                FROM categories cat 
                LEFT JOIN courses c ON cat.id = c.category_id 
                GROUP BY cat.id 
                HAVING course_count > 0
                ORDER BY course_count DESC 
                LIMIT :limit";
        
        return Database::query($sql, [':limit' => $limit]);
    }
    
    /**
     * Check if category can be deleted
     */
    public static function canDelete(int $id): array {
        $category = self::findById($id);
        
        if (!$category) {
            return [
                'can_delete' => false,
                'reason' => 'Category not found'
            ];
        }
        
        if ($category['course_count'] > 0) {
            return [
                'can_delete' => false,
                'reason' => "Category has {$category['course_count']} course(s)",
                'course_count' => $category['course_count']
            ];
        }
        
        return [
            'can_delete' => true,
            'reason' => null
        ];
    }
}
