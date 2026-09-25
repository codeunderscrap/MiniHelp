<?php
// api/tickets.php
require_once '../config/cors.php';
setup_cors();

include_once '../config/db.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        
        if ($id) {
            // Get single ticket
            $query = "SELECT t.*, d.name as department_name, d.code as department_code, 
                      u1.name as creator_name, u2.name as assignee_name,
                      u1.avatar_url as creator_avatar, u2.avatar_url as assignee_avatar
                      FROM tickets t 
                      LEFT JOIN departments d ON t.department_id = d.id 
                      LEFT JOIN users u1 ON t.creator_id = u1.id 
                      LEFT JOIN users u2 ON t.assignee_id = u2.id 
                      WHERE t.id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ticket) {
                // Get dynamic custom values
                $fQuery = "SELECT tcv.field_value, ff.field_label, ff.field_type 
                           FROM ticket_custom_values tcv 
                           JOIN form_fields ff ON tcv.field_id = ff.id 
                           WHERE tcv.ticket_id = :tid";
                $fStmt = $db->prepare($fQuery);
                $fStmt->bindParam(":tid", $id);
                $fStmt->execute();
                $ticket['custom_fields'] = $fStmt->fetchAll(PDO::FETCH_ASSOC);

                // Get Attachments
                $aQuery = "SELECT * FROM ticket_attachments WHERE ticket_id = :tid";
                $aStmt = $db->prepare($aQuery);
                $aStmt->bindParam(":tid", $id);
                $aStmt->execute();
                $ticket['attachments'] = $aStmt->fetchAll(PDO::FETCH_ASSOC);

                // Get Comments
                $cQuery = "SELECT c.*, u.name as user_name, u.avatar_url FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.ticket_id = :tid ORDER BY c.created_at ASC";
                $cStmt = $db->prepare($cQuery);
                $cStmt->bindParam(":tid", $id);
                $cStmt->execute();
                $ticket['comments'] = $cStmt->fetchAll(PDO::FETCH_ASSOC);

                // Get History
                $hQuery = "SELECT h.*, u.name as user_name FROM ticket_history h LEFT JOIN users u ON h.user_id = u.id WHERE h.ticket_id = :tid ORDER BY h.created_at DESC";
                $hStmt = $db->prepare($hQuery);
                $hStmt->bindParam(":tid", $id);
                $hStmt->execute();
                $ticket['history'] = $hStmt->fetchAll(PDO::FETCH_ASSOC);

                // Queue Position
                if (in_array($ticket['status'], ['open', 'assigned', 'waiting'])) {
                    $qQuery = "SELECT count(*) as ahead FROM tickets 
                               WHERE department_id = :did AND status IN ('open', 'assigned', 'waiting') 
                               AND created_at < :cat";
                    $qStmt = $db->prepare($qQuery);
                    $qStmt->bindParam(":did", $ticket['department_id']);
                    $qStmt->bindParam(":cat", $ticket['created_at']);
                    $qStmt->execute();
                    $ahead = $qStmt->fetch(PDO::FETCH_ASSOC)['ahead'];
                    $ticket['queue_position'] = $ahead + 1; // You are number X
                } else {
                    $ticket['queue_position'] = null; // Resolved/in_progress
                }

                echo json_encode(["success" => true, "data" => $ticket]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "error" => "Ticket not found"]);
            }
        } else {
            // List all tickets
            $user_role = isset($_GET['role']) ? $_GET['role'] : 'admin';
            $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
            $dept_id = isset($_GET['department_id']) ? $_GET['department_id'] : null;
            
            $query = "SELECT t.*, d.name as department_name, d.code as department_code, 
                      u1.name as creator_name, u2.name as assignee_name,
                      u1.avatar_url as creator_avatar, u2.avatar_url as assignee_avatar
                      FROM tickets t 
                      LEFT JOIN departments d ON t.department_id = d.id 
                      LEFT JOIN users u1 ON t.creator_id = u1.id 
                      LEFT JOIN users u2 ON t.assignee_id = u2.id ";
                      
            if ($user_role === 'employee' && $user_id) {
                $query .= "WHERE t.creator_id = :uid ";
            } else if ($user_role === 'agent' && $dept_id) {
                $query .= "WHERE t.department_id = :did OR t.creator_id = :uid ";
            } else if ($user_role === 'dept_head' && $dept_id) {
                $query .= "WHERE t.department_id = :did ";
            }
            $query .= "ORDER BY t.created_at DESC";
            
            $stmt = $db->prepare($query);
            if ($user_role === 'employee' && $user_id) {
                $stmt->bindParam(":uid", $user_id);
            } else if ($user_role === 'agent' && $dept_id) {
                $stmt->bindParam(":did", $dept_id);
                $stmt->bindParam(":uid", $user_id);
            } else if ($user_role === 'dept_head' && $dept_id) {
                $stmt->bindParam(":did", $dept_id);
            }
            $stmt->execute();
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "data" => $tickets]);
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
} 
else if ($method === 'POST') {
    // Check if multipart form data (with file) or raw JSON
    $isMultipart = !empty($_POST['data']);
    $data = $isMultipart ? json_decode($_POST['data'], true) : json_decode(file_get_contents("php://input"), true);
    
        if(!empty($data['title']) && !empty($data['description']) && !empty($data['department_id']) && !empty($data['creator_id'])) {
        try {
            $db->beginTransaction();

            // 0. Validate Creator Exists (prevent foreign key constraint failure on wiped DB)
            $cStmt = $db->prepare("SELECT email FROM users WHERE id = :id");
            $cStmt->execute([':id' => $data['creator_id']]);
            $creator_email = $cStmt->fetchColumn();

            if (!$creator_email) {
                $db->rollBack();
                http_response_code(401);
                echo json_encode(["success" => false, "error" => "Session expired or user deleted. Please log out and log in again."]);
                exit();
            }

            // AUTO-PRIORITY RULES ENGINE
            $priority = isset($data['priority']) ? $data['priority'] : 'medium';
            if (file_exists('settings.json')) {
                $settings = json_decode(file_get_contents('settings.json'), true);
                if (!empty($settings['rules'])) {
                    foreach ($settings['rules'] as $rule) {
                        if (strtolower($rule['email']) === strtolower($creator_email)) {
                            $priority = $rule['priority']; // Override priority!
                            break;
                        }
                    }
                }
            }
            
            // AUTO-ASSIGN LOGIC: Find agent in this department with the least active tickets
            $agentQuery = "SELECT u.id FROM users u 
                           LEFT JOIN tickets t ON u.id = t.assignee_id AND t.status IN ('open', 'in_progress', 'assigned')
                           WHERE u.department_id = :did AND u.role IN ('agent', 'dept_head')
                           GROUP BY u.id
                           ORDER BY COUNT(t.id) ASC LIMIT 1";
            $agentStmt = $db->prepare($agentQuery);
            $agentStmt->execute([":did" => $data['department_id']]);
            $auto_assignee_id = $agentStmt->fetchColumn();
            
            $ticket_number = 'MM-' . date('Ymd') . '-' . rand(1000, 9999);
            
            if ($auto_assignee_id) {
                $query = "INSERT INTO tickets SET ticket_number=:tn, title=:title, description=:desc, 
                          priority=:priority, department_id=:dept_id, creator_id=:creator_id, 
                          assignee_id=:assignee, status='assigned'";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ":tn" => $ticket_number, ":title" => $data['title'], ":desc" => $data['description'],
                    ":priority" => $priority, ":dept_id" => $data['department_id'], ":creator_id" => $data['creator_id'],
                    ":assignee" => $auto_assignee_id
                ]);
            } else {
                $query = "INSERT INTO tickets SET ticket_number=:tn, title=:title, description=:desc, 
                          priority=:priority, department_id=:dept_id, creator_id=:creator_id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ":tn" => $ticket_number, ":title" => $data['title'], ":desc" => $data['description'],
                    ":priority" => $priority, ":dept_id" => $data['department_id'], ":creator_id" => $data['creator_id']
                ]);
            }
            
            $last_id = $db->lastInsertId();
            
            // Insert Custom Values
            if (!empty($data['custom_values']) && is_array($data['custom_values'])) {
                $cvQuery = "INSERT INTO ticket_custom_values (ticket_id, field_id, field_value) VALUES (:tid, :fid, :val)";
                $cvStmt = $db->prepare($cvQuery);
                foreach($data['custom_values'] as $field_id => $val) {
                    $cvStmt->execute([":tid" => $last_id, ":fid" => $field_id, ":val" => $val]);
                }
            }

            // Log History
            $hStmt = $db->prepare("INSERT INTO ticket_history (ticket_id, user_id, action) VALUES (:tid, :uid, 'Ticket created')");
            $hStmt->execute([":tid" => $last_id, ":uid" => $data['creator_id']]);

            // Handle Attachments
            if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = time() . '_' . basename($_FILES['attachment']['name']);
                $targetPath = $uploadDir . $fileName;
                
                // Compress Image safely
                try {
                    $fileType = isset($_FILES['attachment']['type']) ? $_FILES['attachment']['type'] : 'application/octet-stream';
                    if (function_exists('mime_content_type')) {
                        $fileType = mime_content_type($_FILES['attachment']['tmp_name']);
                    }
                    
                    if (extension_loaded('gd') && in_array($fileType, ['image/jpeg', 'image/png'])) {
                        $image = $fileType === 'image/jpeg' ? imagecreatefromjpeg($_FILES['attachment']['tmp_name']) : imagecreatefrompng($_FILES['attachment']['tmp_name']);
                        if ($image !== false) {
                            // Save compressed jpeg (70% quality)
                            imagejpeg($image, $targetPath, 70);
                            imagedestroy($image);
                        } else {
                            move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath);
                        }
                    } else {
                        move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath);
                    }
                } catch (\Throwable $e) {
                    // Fallback if anything fails
                    move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath);
                }
                
                $dbPath = '/api/uploads/' . $fileName;
                $aStmt = $db->prepare("INSERT INTO ticket_attachments (ticket_id, user_id, file_name, file_path) VALUES (:tid, :uid, :fn, :fp)");
                $aStmt->execute([":tid" => $last_id, ":uid" => $data['creator_id'], ":fn" => $_FILES['attachment']['name'], ":fp" => $dbPath]);
            }

            $db->commit();
            
            // --- IN-APP NOTIFICATIONS ---
            try {
                $inAppQuery = "SELECT id FROM users WHERE department_id = :did OR role = 'admin' OR id = :creator";
                $inAppStmt = $db->prepare($inAppQuery);
                $inAppStmt->execute([":did" => $data['department_id'], ":creator" => $data['creator_id']]);
                $inAppUsers = $inAppStmt->fetchAll(PDO::FETCH_COLUMN);
                
                $nStmt = $db->prepare("INSERT INTO notifications (user_id, ticket_id, title, message) VALUES (?, ?, ?, ?)");
                $nTitle = "New Ticket: " . $ticket_number;
                $nMessage = "Priority: " . ucfirst($priority) . " - " . $data['title'];
                foreach($inAppUsers as $uid) {
                    $nStmt->execute([$uid, $last_id, $nTitle, $nMessage]);
                }
            } catch (\Throwable $e) {
                error_log("In-App Notification Error: " . $e->getMessage());
            }

            // --- TRIGGER WEB PUSH NOTIFICATION ---
            try {
                if (file_exists('../vendor/autoload.php')) {
                    require_once '../vendor/autoload.php';
                    $auth = [
                        'VAPID' => [
                            'subject' => 'mailto:admin@minimines.com',
                            'publicKey' => 'BINJS1-br47yD9q-ytF4CQKB8m_0jmFlI0lKFdeVklUjwaJsqPNA7MsiJh-Wpj7gq-NRuHq-J0laTTf2MCrDFDI',
                            'privateKey' => 'pESv5fwAWR-Cxf5-l8y5DiSTGsI4aHEUJarBUaIIuyM',
                        ]
                    ];
                    
                    if (class_exists('\Minishlink\WebPush\WebPush')) {
                        $webPush = new \Minishlink\WebPush\WebPush($auth);
                        
                        // Ignore errors if table is missing or query fails
                        $sQuery = "SELECT p.* FROM push_subscriptions p JOIN users u ON p.user_id = u.id WHERE u.department_id = :did";
                        $sStmt = $db->prepare($sQuery);
                        $sStmt->execute([":did" => $data['department_id']]);
                        $subs = $sStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Get Department name
                        $dStmt = $db->prepare("SELECT name FROM departments WHERE id = ?");
                        $dStmt->execute([$data['department_id']]);
                        $deptName = $dStmt->fetchColumn() ?: 'System';
                        
                        $payload = json_encode([
                            "title" => "New Ticket: " . $ticket_number,
                            "body" => "Dept: $deptName\nPriority: " . ucfirst($priority) . "\n" . $data['title'],
                            "url" => "/tickets/" . $last_id,
                            "priority" => $priority // Will be used by frontend for specific sounds
                        ]);
                        
                        foreach ($subs as $sub) {
                            $subscription = \Minishlink\WebPush\Subscription::create([
                                'endpoint' => $sub['endpoint'],
                                'keys' => [
                                    'p256dh' => $sub['p256dh'],
                                    'auth' => $sub['auth']
                                ],
                            ]);
                            $webPush->queueNotification($subscription, $payload);
                        }
                        foreach ($webPush->flush() as $report) {}
                    }
                }
            } catch (\Throwable $e) {
                // Silently ignore push notification errors (e.g. table not found or library missing)
                // The ticket was already successfully created and committed.
                error_log("Push Notification Error: " . $e->getMessage());
            }
            // -------------------------------------
            
            echo json_encode(["success" => true, "data" => ["id" => $last_id, "ticket_number" => $ticket_number]]);
        } catch(PDOException $e) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Incomplete data."]);
    }
}
else if ($method === 'PATCH') {
    // Update ticket status
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    $data = json_decode(file_get_contents("php://input"), true);
    
    if($id && !empty($data['status'])) {
        try {
            $query = "UPDATE tickets SET status=:status";
            $params = [":status" => $data['status'], ":id" => $id];
            if(isset($data['assignee_id'])) {
                $query .= ", assignee_id=:assignee_id";
                $params[":assignee_id"] = $data['assignee_id'];
            }
            $query .= " WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            
            echo json_encode(["success" => true, "message" => "Ticket updated."]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Incomplete data."]);
    }
}
?>
