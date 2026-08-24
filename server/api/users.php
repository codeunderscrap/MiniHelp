<?php
// api/users.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/db.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Extremely basic auth check for demo (in production, validate Bearer token)
$headers = apache_request_headers();
// if(!isset($headers['Authorization'])) {
//     http_response_code(401);
//     echo json_encode(["success" => false, "error" => "Unauthorized"]);
//     exit;
// }

if ($method === 'GET') {
    try {
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        if($id) {
            $query = "SELECT u.id, u.name, u.email, u.role, u.department_id, d.name as department_name 
                      FROM users u 
                      LEFT JOIN departments d ON u.department_id = d.id 
                      WHERE u.id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if($user) {
                echo json_encode(["success" => true, "data" => $user]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "error" => "User not found"]);
            }
        } else {
            // Get all agents (for assignment)
            $role = isset($_GET['role']) ? $_GET['role'] : null;
            $query = "SELECT id, name, email, role, department_id FROM users";
            if($role) {
                $query .= " WHERE role = :role";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":role", $role);
            } else {
                $stmt = $db->prepare($query);
            }
            $stmt->execute();
            $users = $stmt->fetchAll();
            echo json_encode(["success" => true, "data" => $users]);
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
} 
else if ($method === 'PATCH') {
    // Update profile (Settings)
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    $data = json_decode(file_get_contents("php://input"));
    
    if($id && !empty($data->name)) {
        try {
            $query = "UPDATE users SET name = :name";
            if(!empty($data->password)) {
                $query .= ", password_hash = :pass";
            }
            $query .= " WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":name", $data->name);
            $stmt->bindParam(":id", $id);
            
            if(!empty($data->password)) {
                $hash = password_hash($data->password, PASSWORD_DEFAULT);
                $stmt->bindParam(":pass", $hash);
            }
            
            if($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Profile updated successfully."]);
            } else {
                http_response_code(503);
                echo json_encode(["success" => false, "error" => "Unable to update profile."]);
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
else if ($method === 'POST') {
    // Add new user
    $data = json_decode(file_get_contents("php://input"));
    
    if(!empty($data->name) && !empty($data->email) && !empty($data->password) && !empty($data->role)) {
        try {
            $query = "INSERT INTO users SET name=:name, email=:email, password_hash=:pass, role=:role, department_id=:did";
            $stmt = $db->prepare($query);
            
            $hash = password_hash($data->password, PASSWORD_DEFAULT);
            $did = !empty($data->department_id) ? $data->department_id : null;
            
            $stmt->bindParam(":name", $data->name);
            $stmt->bindParam(":email", $data->email);
            $stmt->bindParam(":pass", $hash);
            $stmt->bindParam(":role", $data->role);
            $stmt->bindParam(":did", $did);
            
            if($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "User added successfully"]);
            } else {
                http_response_code(503);
                echo json_encode(["success" => false, "error" => "Unable to add user"]);
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
