<?php
require_once '../config/cors.php';
setup_cors();
require_once '../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();

if ($method === 'GET') {
    $dept_id = isset($_GET['department_id']) ? $_GET['department_id'] : null;
    if ($dept_id) {
        try {
            $stmt = $db->prepare("SELECT * FROM form_fields WHERE department_id = :did");
            $stmt->bindParam(":did", $dept_id);
            $stmt->execute();
            echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "department_id is required"]);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    if(!empty($data->department_id) && !empty($data->field_label) && !empty($data->field_type)) {
        try {
            $stmt = $db->prepare("INSERT INTO form_fields (department_id, field_label, field_type, is_required, options) VALUES (:did, :label, :type, :req, :opt)");
            $stmt->execute([
                ':did' => $data->department_id,
                ':label' => $data->field_label,
                ':type' => $data->field_type,
                ':req' => isset($data->is_required) ? $data->is_required : 0,
                ':opt' => isset($data->options) ? $data->options : null
            ]);
            echo json_encode(["success" => true, "message" => "Field added"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Incomplete data"]);
    }
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents("php://input"));
    if(!empty($data->id)) {
        try {
            $stmt = $db->prepare("UPDATE form_fields SET field_label = :label, field_type = :type, is_required = :req, options = :opt WHERE id = :id");
            $stmt->execute([
                ':label' => $data->field_label,
                ':type' => $data->field_type,
                ':req' => isset($data->is_required) ? $data->is_required : 0,
                ':opt' => isset($data->options) ? $data->options : null,
                ':id' => $data->id
            ]);
            echo json_encode(["success" => true, "message" => "Field updated"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Missing ID"]);
    }
} elseif ($method === 'DELETE') {
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    if ($id) {
        try {
            $stmt = $db->prepare("DELETE FROM form_fields WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(["success" => true, "message" => "Field deleted"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Missing ID"]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>
