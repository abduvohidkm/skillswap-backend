<?php

namespace SkillSwap\Models;

use SkillSwap\Utils\Database;

class Application {
    public static function create(string $studentId, string $courseId): ?string {
        $id = Database::generateUUID();
        
        $sql = "INSERT INTO applications (id, student_id, course_id, status, applied_at) 
                VALUES (:id, :student_id, :course_id, 'approved', NOW())";
        
        $params = [
            ':id' => $id,
            ':student_id' => $studentId,
            ':course_id' => $courseId
        ];
        
        return Database::execute($sql, $params) ? $id : null;
    }
    
    public static function findById(string $id): ?array {
        $sql = "SELECT a.*, c.title as course_title, c.teacher, c.category, c.price,
                       u.first_name, u.last_name, u.email 
                FROM applications a 
                LEFT JOIN courses c ON a.course_id = c.id 
                LEFT JOIN users u ON a.student_id = u.id 
                WHERE a.id = :id";
        
        return Database::queryOne($sql, [':id' => $id]);
    }
    
    public static function getAll(int $page = 1, int $limit = 20, ?string $status = null): array {
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT a.*, c.title as course_title, c.teacher, c.price,
                       u.first_name, u.last_name, u.email 
                FROM applications a 
                LEFT JOIN courses c ON a.course_id = c.id 
                LEFT JOIN users u ON a.student_id = u.id 
                WHERE 1=1";
        
        if ($status) {
            $sql .= " AND a.status = :status";
            $params[':status'] = $status;
        }
        
        $sql .= " ORDER BY a.applied_at DESC LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        return Database::query($sql, $params);
    }
    
    public static function getByStudent(string $studentId): array {
        $sql = "SELECT a.*, c.title, c.description, c.teacher, c.price, c.image_url 
                FROM applications a 
                LEFT JOIN courses c ON a.course_id = c.id 
                WHERE a.student_id = :student_id AND a.status = 'approved' 
                ORDER BY a.applied_at DESC";
        
        return Database::query($sql, [':student_id' => $studentId]);
    }
    
    public static function approve(string $id, string $adminId): bool {
        $sql = "UPDATE applications SET status = 'approved', processed_at = NOW(), processed_by = :admin_id WHERE id = :id";
        return Database::execute($sql, [':id' => $id, ':admin_id' => $adminId]);
    }
    
    public static function reject(string $id, string $adminId, ?string $notes = null): bool {
        $sql = "UPDATE applications SET status = 'rejected', processed_at = NOW(), processed_by = :admin_id, notes = :notes WHERE id = :id";
        return Database::execute($sql, [':id' => $id, ':admin_id' => $adminId, ':notes' => $notes]);
    }
    
    public static function getCount(?string $status = null): int {
        $sql = "SELECT COUNT(*) as count FROM applications WHERE 1=1";
        $params = [];
        
        if ($status) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        
        $result = Database::queryOne($sql, $params);
        return (int)$result['count'];
    }
    
    public static function exists(string $studentId, string $courseId): bool {
        $sql = "SELECT COUNT(*) as count FROM applications WHERE student_id = :student_id AND course_id = :course_id";
        $result = Database::queryOne($sql, [':student_id' => $studentId, ':course_id' => $courseId]);
        return (int)$result['count'] > 0;
    }
}
