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
        $host = $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?? $_ENV['DB_HOST'] ?? 'mysql.railway.internal';
        if ($host === 'localhost' || $host === '127.0.0.1') {
            $host = 'mysql.railway.internal';
        }
        $this->host = $host;
        $this->db_name = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?? $_ENV['DB_NAME'] ?? 'railway';
        $this->username = $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?? $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?? $_ENV['DB_PASS'] ?? 'QKfxegOUsmciItrOQhFKPNAXXjxfYeOg';
        if ($pass === '' || $pass === 'root') {
            $pass = 'QKfxegOUsmciItrOQhFKPNAXXjxfYeOg';
        }
        $this->password = $pass;
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
