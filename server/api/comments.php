<?php
// api/comments.php
require_once '../config/cors.php';
setup_cors();

include_once '../config/db.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $ticket_id = isset($_GET['ticket_id']) ? $_GET['ticket_id'] : null;
    if ($ticket_id) {
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
    $data = json_decode(file_get_contents("php://input"));
    
    if(!empty($data->ticket_id) && !empty($data->user_id) && !empty($data->content)) {
        try {
            $query = "INSERT INTO comments SET ticket_id=:tid, user_id=:uid, content=:content";
            $stmt = $db->prepare($query);
            
            $stmt->bindParam(":tid", $data->ticket_id);
            $stmt->bindParam(":uid", $data->user_id);
            $stmt->bindParam(":content", $data->content);
            
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
                    require_once '../vendor/autoload.php';
                    if (class_exists('\Minishlink\WebPush\WebPush')) {
                        $auth = [
                            'VAPID' => [
                                'subject' => 'mailto:admin@minimines.com',
                                'publicKey' => 'BINJS1-br47yD9q-ytF4CQKB8m_0jmFlI0lKFdeVklUjwaJsqPNA7MsiJh-Wpj7gq-NRuHq-J0laTTf2MCrDFDI',
                                'privateKey' => 'pESv5fwAWR-Cxf5-l8y5DiSTGsI4aHEUJarBUaIIuyM',
                            ]
                        ];
                        $webPush = new \Minishlink\WebPush\WebPush($auth);

                        // Notify assignee, creator, or department head
                        $sQuery = "
                            SELECT p.* FROM push_subscriptions p 
                            JOIN users u ON p.user_id = u.id 
                            WHERE p.user_id IN (:creator_id, :assignee_id) 
                            AND p.user_id != :uid
                        ";
                        $sStmt = $db->prepare($sQuery);
                        $sStmt->execute([
                            ":creator_id" => $ticket['creator_id'],
                            ":assignee_id" => $ticket['assignee_id'] ? $ticket['assignee_id'] : 0,
                            ":uid" => $data->user_id
                        ]);
                        $subs = $sStmt->fetchAll(PDO::FETCH_ASSOC);

                        // Get Department name
                        $dStmt = $db->prepare("SELECT name FROM departments WHERE id = ?");
                        $dStmt->execute([$ticket['department_id']]);
                        $deptName = $dStmt->fetchColumn() ?: 'System';
                        
                        $payload = json_encode([
                            "title" => "New Comment: " . $ticket['ticket_number'],
                            "body" => "Dept: $deptName\nFrom: " . $data->user_id . "\n" . substr($data->content, 0, 100),
                            "url" => "/tickets/" . $ticket_id,
                            "priority" => $ticket['priority']
                        ]);

                        foreach($subs as $sub) {
                            $subscription = \Minishlink\WebPush\Subscription::create([
                                "endpoint" => $sub['endpoint'],
                                "keys" => ['p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                            ]);
                            $webPush->queueNotification($subscription, $payload);
                        }
                        foreach ($webPush->flush() as $report) {}
                    }
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
