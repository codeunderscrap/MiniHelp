<?php
// api/users.php
require_once '../config/cors.php';
setup_cors();

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
            // Get all users
            $role = isset($_GET['role']) ? $_GET['role'] : null;
            $query = "SELECT u.id, u.name, u.email, u.role, u.department_id, d.name as department 
                      FROM users u 
                      LEFT JOIN departments d ON u.department_id = d.id";
            if($role) {
                $query .= " WHERE u.role = :role";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":role", $role);
            } else {
                $query .= " ORDER BY u.created_at DESC";
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
else if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if(!empty($data->name) && !empty($data->email) && !empty($data->password) && !empty($data->role)) {
        try {
            $query = "INSERT INTO users (name, email, password_hash, role, department_id) VALUES (:name, :email, :pass, :role, :did)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ":name" => $data->name,
                ":email" => $data->email,
                ":pass" => password_hash($data->password, PASSWORD_DEFAULT),
                ":role" => $data->role,
                ":did" => empty($data->department_id) ? null : $data->department_id
            ]);
            echo json_encode(["success" => true, "message" => "User created successfully."]);
        } catch(PDOException $e) {
            http_response_code(503);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Incomplete data."]);
    }
} 
else if ($method === 'PATCH') {
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    $data = json_decode(file_get_contents("php://input"));
    
    if($id && (!empty($data->name) || !empty($data->role))) {
        try {
            $query = "UPDATE users SET ";
            $params = [":id" => $id];
            $updates = [];
            
            if(!empty($data->name)) { $updates[] = "name = :name"; $params[":name"] = $data->name; }
            if(!empty($data->email)) { $updates[] = "email = :email"; $params[":email"] = $data->email; }
            if(!empty($data->role)) { $updates[] = "role = :role"; $params[":role"] = $data->role; }
            if(isset($data->department_id)) { 
                $updates[] = "department_id = :did"; 
                $params[":did"] = empty($data->department_id) ? null : $data->department_id; 
            }
            if(!empty($data->password)) { 
                $updates[] = "password_hash = :pass"; 
                $params[":pass"] = password_hash($data->password, PASSWORD_DEFAULT); 
            }
            
            $query .= implode(", ", $updates) . " WHERE id = :id";
            $stmt = $db->prepare($query);
            
            if($stmt->execute($params)) {
                echo json_encode(["success" => true, "message" => "User updated successfully."]);
            } else {
                http_response_code(503);
                echo json_encode(["success" => false, "error" => "Unable to update user."]);
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
else if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    if ($id) {
        try {
            $query = "DELETE FROM users WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([":id" => $id]);
            echo json_encode(["success" => true, "message" => "User deleted"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Missing ID."]);
    }
}
else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>
