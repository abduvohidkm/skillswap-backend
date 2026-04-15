<?php

namespace SkillSwap\Utils;

use PDO;
use PDOException;

class Database {
    private static ?PDO $connection = null;
    
    /**
     * Get database connection (singleton pattern)
     */
    public static function getConnection(): PDO {
        if (self::$connection === null) {
            try {
                $host = $_ENV['DB_HOST'] ?? $_ENV['MYSQLHOST'] ?? 'mysql.railway.internal';
                $port = $_ENV['DB_PORT'] ?? $_ENV['MYSQLPORT'] ?? '3306';
                $dbname = $_ENV['DB_NAME'] ?? $_ENV['MYSQLDATABASE'] ?? 'railway';
                $user = $_ENV['DB_USER'] ?? $_ENV['MYSQLUSER'] ?? 'root';
                $pass = $_ENV['DB_PASS'] ?? $_ENV['MYSQLPASSWORD'] ?? 'QKfxegOUsmciItrOQhFKPNAXXjxfYeOg';
                
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                
                self::$connection = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new \Exception("Database connection failed");
            }
        }
        
        return self::$connection;
    }
    
    /**
     * Execute a SELECT query
     */
    public static function query(string $sql, array $params = []): array {
        $conn = self::getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Execute a SELECT query and return single row
     */
    public static function queryOne(string $sql, array $params = []): ?array {
        $conn = self::getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Execute INSERT/UPDATE/DELETE query
     */
    public static function execute(string $sql, array $params = []): bool {
        $conn = self::getConnection();
        $stmt = $conn->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Get last inserted ID
     */
    public static function lastInsertId(): string {
        return self::getConnection()->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public static function beginTransaction(): bool {
        return self::getConnection()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public static function commit(): bool {
        return self::getConnection()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public static function rollback(): bool {
        return self::getConnection()->rollBack();
    }
    
    /**
     * Generate UUID v4
     */
    public static function generateUUID(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
