<?php

namespace SkillSwap\Models;

use SkillSwap\Utils\Database;

class ExamMaterial {
    public static function getAll(int $page = 1, int $limit = 20): array {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM exam_materials ORDER BY exam_date DESC LIMIT :limit OFFSET :offset";
        
        return Database::query($sql, [':limit' => $limit, ':offset' => $offset]);
    }
    
    public static function findById(string $id): ?array {
        $sql = "SELECT * FROM exam_materials WHERE id = :id";
        return Database::queryOne($sql, [':id' => $id]);
    }
    
    public static function create(array $data): ?string {
        $id = Database::generateUUID();
        
        $sql = "INSERT INTO exam_materials (id, title, description, exam_date, price, image_url, proof_link, buy_link, created_at) 
                VALUES (:id, :title, :description, :exam_date, :price, :image_url, :proof_link, :buy_link, NOW())";
        
        $params = [
            ':id' => $id,
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':exam_date' => $data['exam_date'],
            ':price' => $data['price'],
            ':image_url' => $data['image_url'] ?? null,
            ':proof_link' => $data['proof_link'] ?? null,
            ':buy_link' => $data['buy_link'] ?? null
        ];
        
        return Database::execute($sql, $params) ? $id : null;
    }
}
