<?php
/**
 * CategoryController.php
 * 
 * Location: src/controllers/CategoryController.php
 * 
 * Handles all category-related API endpoints:
 * - GET /api/categories (public)
 * - GET /api/admin/categories (admin)
 * - POST /api/admin/categories (admin)
 * - PUT /api/admin/categories/:id (admin)
 * - DELETE /api/admin/categories/:id (admin)
 */

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Response.php';

class CategoryController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * GET /api/categories
     * Public endpoint - Get all categories
     */
    public function getAllCategories() {
        try {
            $query = "SELECT id, name, slug, created_at FROM categories ORDER BY name ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success([
                'categories' => $categories
            ]);
        } catch (Exception $e) {
            Response::error('Failed to fetch categories', 500);
        }
    }
    
    /**
     * GET /api/admin/categories
     * Admin endpoint - Get all categories with course count
     */
    public function getAdminCategories() {
        try {
            $query = "
                SELECT 
                    c.id,
                    c.name,
                    c.slug,
                    c.created_at,
                    COUNT(co.id) as course_count
                FROM categories c
                LEFT JOIN courses co ON c.id = co.category_id
                GROUP BY c.id
                ORDER BY c.name ASC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success([
                'categories' => $categories
            ]);
        } catch (Exception $e) {
            Response::error('Failed to fetch categories', 500);
        }
    }
    
    /**
     * POST /api/admin/categories
     * Admin endpoint - Create new category
     */
    public function createCategory() {
        try {
            // Get input
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validation
            if (!isset($data['name']) || empty(trim($data['name']))) {
                Response::error('Category name is required', 400);
                return;
            }
            
            $name = trim($data['name']);
            
            if (strlen($name) > 100) {
                Response::error('Category name must be less than 100 characters', 400);
                return;
            }
            
            // Generate slug
            $slug = $this->generateSlug($name);
            
            // Check if category already exists
            $checkQuery = "SELECT id FROM categories WHERE name = ? OR slug = ?";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([$name, $slug]);
            
            if ($checkStmt->fetch()) {
                Response::error('Category already exists', 400);
                return;
            }
            
            // Insert category
            $insertQuery = "INSERT INTO categories (name, slug, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
            $insertStmt = $this->db->prepare($insertQuery);
            $insertStmt->execute([$name, $slug]);
            
            $categoryId = $this->db->lastInsertId();
            
            Response::success([
                'id' => $categoryId,
                'name' => $name,
                'slug' => $slug
            ], 'Category created successfully', 201);
            
        } catch (Exception $e) {
            Response::error('Failed to create category: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /api/admin/categories/:id
     * Admin endpoint - Update category
     */
    public function updateCategory($id) {
        try {
            // Get input
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validation
            if (!isset($data['name']) || empty(trim($data['name']))) {
                Response::error('Category name is required', 400);
                return;
            }
            
            $name = trim($data['name']);
            
            if (strlen($name) > 100) {
                Response::error('Category name must be less than 100 characters', 400);
                return;
            }
            
            // Check if category exists
            $checkQuery = "SELECT id FROM categories WHERE id = ?";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([$id]);
            
            if (!$checkStmt->fetch()) {
                Response::error('Category not found', 404);
                return;
            }
            
            // Generate new slug
            $slug = $this->generateSlug($name);
            
            // Check if name/slug already used by another category
            $dupQuery = "SELECT id FROM categories WHERE (name = ? OR slug = ?) AND id != ?";
            $dupStmt = $this->db->prepare($dupQuery);
            $dupStmt->execute([$name, $slug, $id]);
            
            if ($dupStmt->fetch()) {
                Response::error('Category name already exists', 400);
                return;
            }
            
            // Update category
            $updateQuery = "UPDATE categories SET name = ?, slug = ?, updated_at = NOW() WHERE id = ?";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute([$name, $slug, $id]);
            
            Response::success([
                'id' => $id,
                'name' => $name,
                'slug' => $slug
            ], 'Category updated successfully');
            
        } catch (Exception $e) {
            Response::error('Failed to update category: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /api/admin/categories/:id
     * Admin endpoint - Delete category
     */
    public function deleteCategory($id) {
        try {
            // Check if category exists
            $checkQuery = "SELECT id FROM categories WHERE id = ?";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([$id]);
            
            if (!$checkStmt->fetch()) {
                Response::error('Category not found', 404);
                return;
            }
            
            // Check if category has courses
            $courseQuery = "SELECT COUNT(*) as count FROM courses WHERE category_id = ?";
            $courseStmt = $this->db->prepare($courseQuery);
            $courseStmt->execute([$id]);
            $result = $courseStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                Response::error('Cannot delete category with existing courses. Please reassign or delete courses first.', 400);
                return;
            }
            
            // Delete category
            $deleteQuery = "DELETE FROM categories WHERE id = ?";
            $deleteStmt = $this->db->prepare($deleteQuery);
            $deleteStmt->execute([$id]);
            
            Response::success(null, 'Category deleted successfully');
            
        } catch (Exception $e) {
            Response::error('Failed to delete category: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Helper: Generate URL-friendly slug from name
     */
    private function generateSlug($name) {
        // Convert to lowercase
        $slug = strtolower($name);
        
        // Replace spaces with hyphens
        $slug = preg_replace('/\s+/', '-', $slug);
        
        // Remove special characters
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        
        // Remove multiple consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);
        
        // Trim hyphens from start and end
        $slug = trim($slug, '-');
        
        return $slug;
    }
}
