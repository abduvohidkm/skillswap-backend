<?php
/**
 * Database Configuration & Connection
 * Skill Swap Academy Backend
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        $this->host = $_ENV['DB_HOST'] ?? $_ENV['MYSQLHOST'] ?? 'mysql.railway.internal';
        $this->db_name = $_ENV['DB_NAME'] ?? $_ENV['MYSQLDATABASE'] ?? 'railway';
        $this->username = $_ENV['DB_USER'] ?? $_ENV['MYSQLUSER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? $_ENV['MYSQLPASSWORD'] ?? 'QKfxegOUsmciItrOQhFKPNAXXjxfYeOg';
    }

    public function connect() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
            die();
        }

        return $this->conn;
    }
}
