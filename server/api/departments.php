<?php
// api/departments.php
require_once '../config/cors.php';
setup_cors();

include_once '../config/db.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "SELECT * FROM departments ORDER BY name ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $departments = $stmt->fetchAll();
        
        echo json_encode([
            "success" => true,
            "data" => $departments
        ]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!empty($data['name']) && !empty($data['code'])) {
        try {
            $query = "INSERT INTO departments (name, code, description) VALUES (:n, :c, :d)";
            $stmt = $db->prepare($query);
            $desc = isset($data['description']) ? $data['description'] : '';
            $stmt->execute([":n" => $data['name'], ":c" => $data['code'], ":d" => $desc]);
            echo json_encode(["success" => true, "message" => "Department created successfully"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Name and code are required."]);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    $data = json_decode(file_get_contents("php://input"), true);
    if ($id && !empty($data['name']) && !empty($data['code'])) {
        try {
            $query = "UPDATE departments SET name = :n, code = :c, description = :d WHERE id = :id";
            $stmt = $db->prepare($query);
            $desc = isset($data['description']) ? $data['description'] : '';
            $stmt->execute([":n" => $data['name'], ":c" => $data['code'], ":d" => $desc, ":id" => $id]);
            echo json_encode(["success" => true, "message" => "Department updated"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Incomplete data for update."]);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    if ($id) {
        try {
            // Check if there are tickets for this department
            $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM tickets WHERE department_id = :id");
            $checkStmt->execute([":id" => $id]);
            $ticketCount = $checkStmt->fetchColumn();
            
            if ($ticketCount > 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Cannot delete department: It has $ticketCount ticket(s) assigned to it. Please reassign or delete them first."]);
                exit;
            }

            // Unassign users from this department
            $db->prepare("UPDATE users SET department_id = NULL WHERE department_id = :id")->execute([":id" => $id]);
            
            // Delete associated categories if any
            try {
                $db->prepare("DELETE FROM ticket_categories WHERE department_id = :id")->execute([":id" => $id]);
            } catch(PDOException $e) {
                // Ignore if table doesn't exist yet
            }

            // Delete associated custom fields if ON DELETE CASCADE wasn't set historically
            try {
                $db->prepare("DELETE FROM ticket_custom_fields WHERE department_id = :id")->execute([":id" => $id]);
            } catch(PDOException $e) {}

            $query = "DELETE FROM departments WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([":id" => $id]);
            echo json_encode(["success" => true, "message" => "Department deleted"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Database Error: " . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Missing ID."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>
