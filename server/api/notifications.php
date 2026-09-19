<?php
// api/notifications.php
require_once '../config/cors.php';
setup_cors();

include_once '../config/db.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "User ID is required"]);
        exit;
    }

    try {
        $query = "SELECT n.*, t.department_id, d.name as department_name 
                  FROM notifications n 
                  LEFT JOIN tickets t ON n.ticket_id = t.id
                  LEFT JOIN departments d ON t.department_id = d.id
                  WHERE n.user_id = :uid 
                  ORDER BY n.created_at DESC LIMIT 20";
        $stmt = $db->prepare($query);
        $stmt->execute([":uid" => $user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $unreadQuery = "SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = FALSE";
        $uStmt = $db->prepare($unreadQuery);
        $uStmt->execute([":uid" => $user_id]);
        $unread_count = $uStmt->fetchColumn();

        echo json_encode(["success" => true, "data" => $notifications, "unread_count" => $unread_count]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
} else if ($method === 'PATCH') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!empty($data['user_id'])) {
        try {
            $query = "UPDATE notifications SET is_read = TRUE WHERE user_id = :uid";
            $stmt = $db->prepare($query);
            $stmt->execute([":uid" => $data['user_id']]);
            echo json_encode(["success" => true, "message" => "Notifications marked as read"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "User ID is required"]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>
