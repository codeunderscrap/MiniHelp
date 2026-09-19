<?php
require_once '../config/cors.php';
setup_cors();
require_once '../config/db.php';
$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();

if ($method === 'GET') {
    try {
        $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        echo json_encode(["success" => true, "data" => $settings]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
} else if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!empty($data)) {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v");
            foreach ($data as $key => $val) {
                $stmt->execute([':k' => $key, ':v' => $val]);
            }
            $db->commit();
            echo json_encode(["success" => true, "message" => "Settings updated"]);
        } catch(PDOException $e) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "No data"]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>
