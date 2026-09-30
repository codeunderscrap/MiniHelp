<?php
// api/email_log.php — admin-only, read-only view of email delivery attempts.
// GET /api/email_log.php[?ticket_id=N][&status=failed][&limit=100]
require_once '../config/cors.php';
setup_cors();

include_once '../config/db.php';
require_once '../config/auth_middleware.php';
require_once '../config/mailer.php';

$database = new Database();
$db = $database->getConnection();
$me = require_auth($db);
if (!$me->is_admin()) deny(403, 'Not allowed');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') deny(405, 'Method not allowed');

try {
    mail_ensure_log_table($db);
    $where = [];
    $params = [];
    if (!empty($_GET['ticket_id'])) {
        $where[] = 'ticket_id = :t';
        $params[':t'] = (int)$_GET['ticket_id'];
    }
    if (!empty($_GET['status'])) {
        $where[] = 'status = :s';
        $params[':s'] = (string)$_GET['status'];
    }
    $limit = max(1, min(500, (int)($_GET['limit'] ?? 100)));
    $sql = "SELECT id, ticket_id, recipient, event, status, error, created_at FROM email_log"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY id DESC LIMIT $limit";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(["success" => true, "configured" => mail_config($db)['enabled'], "data" => $stmt->fetchAll()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
