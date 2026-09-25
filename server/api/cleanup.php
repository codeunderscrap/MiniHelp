<?php
require_once '../config/cors.php';
setup_cors();
require_once '../config/db.php';
require_once '../config/auth_middleware.php';

$method = $_SERVER['REQUEST_METHOD'];

$database = new Database();
$db = $database->getConnection();

// Deletes tickets, so it is POST-only and limited to admins and department heads.
if ($method !== 'POST') deny(405, 'Method not allowed');
require_manager(require_auth($db));

try {
    // Optional: Get days parameter, default to 30
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;

    // Delete tickets that have been closed/resolved and haven't been updated in X days
    $query = "DELETE FROM tickets WHERE status IN ('resolved', 'closed') AND updated_at < NOW() - INTERVAL :days DAY";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":days", $days, PDO::PARAM_INT);
    $stmt->execute();
    
    $deletedCount = $stmt->rowCount();
    
    // DB cascading takes care of attachments, history, custom_values, comments!
    // But wait, physical files need deletion too.
    
    // Actually to delete physical files we must SELECT first
    $selQuery = "SELECT id FROM tickets WHERE status IN ('resolved', 'closed') AND updated_at < NOW() - INTERVAL :days DAY";
    $selStmt = $db->prepare($selQuery);
    $selStmt->bindParam(":days", $days, PDO::PARAM_INT);
    $selStmt->execute();
    $ticketsToDelete = $selStmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($ticketsToDelete) > 0) {
        $inQuery = implode(',', array_fill(0, count($ticketsToDelete), '?'));
        
        // Find physical files
        $attStmt = $db->prepare("SELECT file_path FROM ticket_attachments WHERE ticket_id IN ($inQuery)");
        $attStmt->execute($ticketsToDelete);
        $files = $attStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($files as $file) {
            $fullPath = str_replace('/api/', '', $file); // /api/uploads/file.jpg -> uploads/file.jpg
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        
        // Now delete tickets
        $delStmt = $db->prepare("DELETE FROM tickets WHERE id IN ($inQuery)");
        $delStmt->execute($ticketsToDelete);
    }
    
    echo json_encode(["success" => true, "message" => "Cleaned up $deletedCount tickets older than $days days."]);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
