<?php
// api/categories.php
require_once '../config/cors.php';
setup_cors();

include_once '../config/db.php';
$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $dept_id = isset($_GET['department_id']) ? $_GET['department_id'] : null;
        if ($dept_id) {
            $query = "SELECT * FROM ticket_categories WHERE department_id = :did ORDER BY name ASC";
            $stmt = $db->prepare($query);
            $stmt->execute([':did' => $dept_id]);
        } else {
            $query = "SELECT c.*, d.name as department_name FROM ticket_categories c LEFT JOIN departments d ON c.department_id = d.id ORDER BY d.name, c.name ASC";
            $stmt = $db->prepare($query);
            $stmt->execute();
        }
        
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["success" => true, "data" => $categories]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!empty($data['name']) && !empty($data['department_id'])) {
        try {
            $query = "INSERT INTO ticket_categories (department_id, name) VALUES (:did, :n)";
            $stmt = $db->prepare($query);
            $stmt->execute([":did" => $data['department_id'], ":n" => $data['name']]);
            echo json_encode(["success" => true, "message" => "Category created successfully"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Name and Department ID are required."]);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    if ($id) {
        try {
            $query = "DELETE FROM ticket_categories WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([":id" => $id]);
            echo json_encode(["success" => true, "message" => "Category deleted"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
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
