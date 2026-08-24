<?php
// api/sla.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/db.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Ensure sla_configs table exists
$initQuery = "CREATE TABLE IF NOT EXISTS sla_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    response_time_minutes INT NOT NULL,
    resolution_time_minutes INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY dept_priority (department_id, priority),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
)";
$db->exec($initQuery);

if ($method === 'GET') {
    try {
        $query = "SELECT s.*, d.name as department_name 
                  FROM sla_configs s 
                  JOIN departments d ON s.department_id = d.id 
                  ORDER BY d.name, s.priority";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $configs = $stmt->fetchAll();
        echo json_encode(["success" => true, "data" => $configs]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
} 
else if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if(!empty($data->department_id) && !empty($data->priority) && isset($data->response_time) && isset($data->resolution_time)) {
        try {
            // Upsert configuration
            $query = "INSERT INTO sla_configs (department_id, priority, response_time_minutes, resolution_time_minutes) 
                      VALUES (:did, :prio, :resp, :res) 
                      ON DUPLICATE KEY UPDATE 
                      response_time_minutes = VALUES(response_time_minutes), 
                      resolution_time_minutes = VALUES(resolution_time_minutes)";
            $stmt = $db->prepare($query);
            
            $stmt->bindParam(":did", $data->department_id);
            $stmt->bindParam(":prio", $data->priority);
            $stmt->bindParam(":resp", $data->response_time);
            $stmt->bindParam(":res", $data->resolution_time);
            
            if($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "SLA Configuration saved successfully"]);
            } else {
                http_response_code(503);
                echo json_encode(["success" => false, "error" => "Unable to save SLA configuration"]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Incomplete data."]);
    }
}
else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>
