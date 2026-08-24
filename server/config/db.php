<?php
// config/db.php

class Database {
    private $host = "localhost";
    private $db_name = "minimines_helpdesk";
    private $username = "root";
    private $password = ""; // Default XAMPP/WAMP empty password
    public $conn;

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
        return $this->conn;
    }
}
?>
