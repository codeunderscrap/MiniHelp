<?php
require_once '../config/cors.php';
setup_cors();
require_once '../config/db.php';
require_once '../config/auth_middleware.php';
$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();
$me = get_authenticated_user($db);
if ($method !== 'GET') {
    if (!$me) deny(401, 'Authentication required');
    require_manager($me);
}

if ($method === 'GET') {
    try {
        $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // The app shell reads notification sounds before sign-in; everything else
            // (SLA targets, priority rules) is for signed-in managers only.
            if (!($me && $me->is_manager()) && strpos($row['setting_key'], 'sound_') !== 0) continue;
            
            $val = json_decode($row['setting_value'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $val = $row['setting_value'];
            }
            $settings[$row['setting_key']] = $val;
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
                $valToSave = is_array($val) ? json_encode($val) : (string)$val;
                $stmt->execute([':k' => $key, ':v' => $valToSave]);
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
