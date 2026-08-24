<?php
// api/auth.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/db.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if(!empty($data->email) && !empty($data->password)) {
        try {
            $query = "SELECT * FROM users WHERE email = :email LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":email", $data->email);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $user = $stmt->fetch();
                if(password_verify($data->password, $user['password_hash'])) {
                    
                    // Remove hash from response
                    unset($user['password_hash']);
                    
                    // Get department info if any
                    if($user['department_id']) {
                        $dQuery = "SELECT name, code FROM departments WHERE id = :did";
                        $dStmt = $db->prepare($dQuery);
                        $dStmt->bindParam(":did", $user['department_id']);
                        $dStmt->execute();
                        $user['department'] = $dStmt->fetch();
                    }
                    
                    // Minimal token system (for demo purposes)
                    $token = base64_encode(json_encode(["id" => $user['id'], "email" => $user['email'], "exp" => time() + 3600]));
                    
                    echo json_encode([
                        "success" => true,
                        "token" => $token,
                        "user" => $user
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode(["success" => false, "error" => "Invalid credentials."]);
                }
            } else {
                http_response_code(401);
                echo json_encode(["success" => false, "error" => "Invalid credentials."]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Incomplete data."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>
