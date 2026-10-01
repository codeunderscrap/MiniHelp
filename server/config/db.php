<?php
// config/db.php

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'minimines_helpdesk';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Return associative arrays by default
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo json_encode(["success" => false, "message" => "Database Connection error: " . $exception->getMessage()]);
            exit;
        }
            // Auto-migrate form_fields to support category-specific questions
            try {
                $this->conn->exec("ALTER TABLE form_fields ADD COLUMN category_id INT NULL DEFAULT NULL AFTER department_id");
            } catch(PDOException $e) {}

            // Auto-cleanup unwanted fields for the user
            try {
                $this->conn->exec("DELETE FROM form_fields WHERE field_label LIKE '%Operating System%' OR field_label LIKE '%Error Message%'");
            } catch(PDOException $e) {}
        return $this->conn;
    }
}
?>


