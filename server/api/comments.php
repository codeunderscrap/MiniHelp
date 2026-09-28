<?php
// api/comments.php
require_once '../config/cors.php';
setup_cors();

include_once '../config/db.php';
require_once '../config/auth_middleware.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$me = require_auth($db);

if ($method === 'GET') {
    $ticket_id = isset($_GET['ticket_id']) ? $_GET['ticket_id'] : null;
    if ($ticket_id) {
        $visible = load_ticket($db, $ticket_id);
        if (!$visible || !can_view_ticket($me, $visible)) deny(404, 'Ticket not found');
        try {
            $query = "SELECT c.*, u.name as user_name, u.role as user_role 
                      FROM comments c 
                      LEFT JOIN users u ON c.user_id = u.id 
                      WHERE c.ticket_id = :tid 
                      ORDER BY c.created_at ASC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":tid", $ticket_id);
            $stmt->execute();
            $comments = $stmt->fetchAll();
            echo json_encode(["success" => true, "data" => $comments]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Missing ticket_id"]);
    }
} 
else if ($method === 'POST') {
    $isMultipart = !empty($_POST['ticket_id']);
    if ($isMultipart) {
        $data = (object)[
            'ticket_id' => $_POST['ticket_id'],
            'content' => isset($_POST['content']) ? $_POST['content'] : '',
            'user_id' => $me->user_id
        ];
    } else {
        $data = json_decode(file_get_contents("php://input"));
        if (is_object($data)) {
            $data->user_id = $me->user_id;
        }
    }
    
    if(!empty($data->ticket_id) && !empty($data->user_id) && (!empty($data->content) || !empty($_FILES['attachment']))) {
        $visible = load_ticket($db, $data->ticket_id);
        if (!$visible || !can_view_ticket($me, $visible)) deny(404, 'Ticket not found');
        try {
            // Handle file upload
            $attachmentMarkdown = '';
            if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'txt', 'csv', 'xlsx'];
                if (in_array($ext, $allowed)) {
                    $uploadDir = 'uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $fileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $targetPath = $uploadDir . $fileName;
                    move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath);
                    
                    $dbPath = '/api/uploads/' . $fileName;
                    
                    // Add to ticket_attachments
                    $aStmt = $db->prepare("INSERT INTO ticket_attachments (ticket_id, user_id, file_name, file_path) VALUES (:tid, :uid, :fn, :fp)");
                    $aStmt->execute([":tid" => $data->ticket_id, ":uid" => $data->user_id, ":fn" => $_FILES['attachment']['name'], ":fp" => $dbPath]);
                    
                    $attachmentMarkdown = "\n\n[FILE://" . $dbPath . "|" . $_FILES['attachment']['name'] . "]";
                }
            }
            
            $finalContent = $data->content . $attachmentMarkdown;

            $query = "INSERT INTO comments SET ticket_id=:tid, user_id=:uid, content=:content";
            $stmt = $db->prepare($query);
            
            $stmt->bindParam(":tid", $data->ticket_id);
            $stmt->bindParam(":uid", $data->user_id);
            $stmt->bindParam(":content", $finalContent);
            
            if($stmt->execute()) {
                $ticket_id = $data->ticket_id;
                // Fetch ticket details for notification
                $tStmt = $db->prepare("SELECT t.ticket_number, t.title, t.priority, t.creator_id, t.department_id, t.assignee_id FROM tickets t WHERE t.id = :tid");
                $tStmt->execute([":tid" => $ticket_id]);
                $ticket = $tStmt->fetch(PDO::FETCH_ASSOC);

                // --- IN-APP NOTIFICATIONS ---
                try {
                    $inAppQuery = "SELECT id FROM users WHERE id IN (:creator_id, :assignee_id) AND id != :uid";
                    $inAppStmt = $db->prepare($inAppQuery);
                    $inAppStmt->execute([
                        ":creator_id" => $ticket['creator_id'],
                        ":assignee_id" => $ticket['assignee_id'] ? $ticket['assignee_id'] : 0,
                        ":uid" => $data->user_id
                    ]);
                    $inAppUsers = $inAppStmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $nStmt = $db->prepare("INSERT INTO notifications (user_id, ticket_id, title, message) VALUES (?, ?, ?, ?)");
                    $nTitle = "New Comment on " . $ticket['ticket_number'];
                    $nMessage = "Update on: " . $ticket['title'];
                    foreach($inAppUsers as $uid) {
                        $nStmt->execute([$uid, $ticket_id, $nTitle, $nMessage]);
                    }
                } catch (\Throwable $e) {
                    error_log("In-App Notification Error (Comments): " . $e->getMessage());
                }

                // --- PUSH NOTIFICATION (BEST EFFORT) ---
                try {
                    require_once '../config/push.php';
                    // Notify the creator and the assignee, never the commenter
                    $sStmt = $db->prepare("SELECT p.* FROM push_subscriptions p WHERE p.user_id IN (:creator_id, :assignee_id) AND p.user_id != :uid");
                    $sStmt->execute([
                        ":creator_id" => $ticket['creator_id'],
                        ":assignee_id" => $ticket['assignee_id'] ? $ticket['assignee_id'] : 0,
                        ":uid" => $data->user_id
                    ]);
                    $dStmt = $db->prepare("SELECT name FROM departments WHERE id = ?");
                    $dStmt->execute([$ticket['department_id']]);
                    $deptName = $dStmt->fetchColumn() ?: 'System';
                    send_push($db, $sStmt->fetchAll(PDO::FETCH_ASSOC), json_encode([
                        "title" => "New Comment: " . $ticket['ticket_number'],
                        "body" => "Dept: $deptName\nFrom: " . $me->name . "\n" . substr($data->content, 0, 100),
                        "url" => "/tickets/" . $ticket_id,
                        "priority" => $ticket['priority']
                    ]));
                } catch (\Throwable $e) {
                    error_log("Push Notification Error (Comments): " . $e->getMessage());
                }

                echo json_encode(["success" => true, "message" => "Comment added"]);
            } else {
                http_response_code(503);
                echo json_encode(["success" => false, "error" => "Unable to add comment"]);
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

