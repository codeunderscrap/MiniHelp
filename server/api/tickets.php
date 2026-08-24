<?php
// api/tickets.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
                      u1.name as creator_name, u2.name as assignee_name 
                      FROM tickets t 
                      LEFT JOIN departments d ON t.department_id = d.id 
                      LEFT JOIN users u1 ON t.creator_id = u1.id 
                      LEFT JOIN users u2 ON t.assignee_id = u2.id 
                      WHERE t.id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            $ticket = $stmt->fetch();
            
            if ($ticket) {
                // Get comments
                $cQuery = "SELECT c.*, u.name as user_name FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.ticket_id = :tid ORDER BY c.created_at ASC";
                $cStmt = $db->prepare($cQuery);
                $cStmt->bindParam(":tid", $id);
                $cStmt->execute();
                $ticket['comments'] = $cStmt->fetchAll();
                
                echo json_encode(["success" => true, "data" => $ticket]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "error" => "Ticket not found"]);
            }
        } else {
            // List all tickets with Role-Based Scoping
            $user_role = isset($_GET['role']) ? $_GET['role'] : 'admin';
            $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
            $dept_id = isset($_GET['department_id']) ? $_GET['department_id'] : null;
            
            $query = "SELECT t.*, d.name as department_name, d.code as department_code, 
                      u1.name as creator_name, u2.name as assignee_name 
                      FROM tickets t 
                      LEFT JOIN departments d ON t.department_id = d.id 
                      LEFT JOIN users u1 ON t.creator_id = u1.id 
                      LEFT JOIN users u2 ON t.assignee_id = u2.id ";
                      
            // RBAC Filtering
            if ($user_role === 'employee' && $user_id) {
                // Employees only see their own created tickets
                $query .= "WHERE t.creator_id = :uid ";
            } else if ($user_role === 'agent' && $dept_id) {
                // Agents only see tickets in their department OR tickets they created
                $query .= "WHERE t.department_id = :did OR t.creator_id = :uid ";
            } else if ($user_role === 'dept_head' && $dept_id) {
                // Dept heads see their department tickets
                $query .= "WHERE t.department_id = :did ";
            }
            // Admin sees all (no WHERE clause needed)
            
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
            $tickets = $stmt->fetchAll();
            echo json_encode(["success" => true, "data" => $tickets]);
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
} 
else if ($method === 'POST') {
    // Create new ticket
    $data = json_decode(file_get_contents("php://input"));
    
    if(!empty($data->title) && !empty($data->description) && !empty($data->department_id) && !empty($data->creator_id)) {
        try {
            $ticket_number = 'MM-' . date('Ymd') . '-' . rand(1000, 9999);
            
            $query = "INSERT INTO tickets SET 
                      ticket_number=:tn, title=:title, description=:desc, 
                      priority=:priority, department_id=:dept_id, creator_id=:creator_id";
            
            $stmt = $db->prepare($query);
            
            $priority = isset($data->priority) ? $data->priority : 'medium';
            
            $stmt->bindParam(":tn", $ticket_number);
            $stmt->bindParam(":title", $data->title);
            $stmt->bindParam(":desc", $data->description);
            $stmt->bindParam(":priority", $priority);
            $stmt->bindParam(":dept_id", $data->department_id);
            $stmt->bindParam(":creator_id", $data->creator_id);
            
            if($stmt->execute()) {
                $last_id = $db->lastInsertId();
                
                // --- TRIGGER WEB PUSH NOTIFICATION ---
                require_once '../vendor/autoload.php';
                
                $auth = [
                    'VAPID' => [
                        'subject' => 'mailto:admin@minimines.com',
                        'publicKey' => 'BINJS1-br47yD9q-ytF4CQKB8m_0jmFlI0lKFdeVklUjwaJsqPNA7MsiJh-Wpj7gq-NRuHq-J0laTTf2MCrDFDI',
                        'privateKey' => 'pESv5fwAWR-Cxf5-l8y5DiSTGsI4aHEUJarBUaIIuyM',
                    ]
                ];
                $webPush = new \Minishlink\WebPush\WebPush($auth);
                
                // Fetch subscriptions for agents in this department
                $sQuery = "SELECT p.* FROM push_subscriptions p 
                           JOIN users u ON p.user_id = u.id 
                           WHERE u.department_id = :did";
                $sStmt = $db->prepare($sQuery);
                $sStmt->bindParam(":did", $data->department_id);
                $sStmt->execute();
                $subs = $sStmt->fetchAll();
                
                $payload = json_encode([
                    "title" => "New Ticket: " . $ticket_number,
                    "body" => "Priority: " . ucfirst($priority) . "\n" . $data->title,
                    "url" => "/tickets"
                ]);
                
                foreach($subs as $sub) {
                    $subscription = \Minishlink\WebPush\Subscription::create([
                        "endpoint" => $sub['endpoint'],
                        "keys" => [
                            'p256dh' => $sub['p256dh'],
                            'auth' => $sub['auth']
                        ],
                    ]);
                    $webPush->queueNotification($subscription, $payload);
                }
                
                foreach ($webPush->flush() as $report) {
                    // In production, delete expired subscriptions from DB if $report->isSuccess() is false
                }
                // -------------------------------------
                
                echo json_encode(["success" => true, "data" => ["id" => $last_id, "ticket_number" => $ticket_number]]);
            } else {
                http_response_code(503);
                echo json_encode(["success" => false, "error" => "Unable to create ticket."]);
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
else if ($method === 'PATCH') {
    // Update ticket status/assignee
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    $data = json_decode(file_get_contents("php://input"));
    
    if($id && !empty($data->status)) {
        try {
            $query = "UPDATE tickets SET status=:status";
            if(isset($data->assignee_id)) {
                $query .= ", assignee_id=:assignee_id";
            }
            $query .= " WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":status", $data->status);
            $stmt->bindParam(":id", $id);
            if(isset($data->assignee_id)) {
                $stmt->bindParam(":assignee_id", $data->assignee_id);
            }
            
            if($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Ticket updated."]);
            } else {
                http_response_code(503);
                echo json_encode(["success" => false, "error" => "Unable to update ticket."]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Incomplete data or missing ID."]);
    }
}
else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>
