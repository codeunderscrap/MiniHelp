<?php
// api/badge.php — Badge count for MM OS portal (GET /api/badge?sub=...)
// Returns open ticket count for a given user's mmos_sub, shown on the OS bar.
// No auth required — this is a service-to-service call from MM OS itself.
require_once '../config/cors.php';
setup_cors();

include_once '../config/db.php';

$database = new Database();
$db = $database->getConnection();

$sub = isset($_GET['sub']) ? $_GET['sub'] : null;

if (!$sub) {
    echo json_encode(['open' => 0, 'approvals_waiting' => 0]);
    exit();
}

try {
    // Find the user by mmos_sub
    $stmt = $db->prepare("SELECT id FROM users WHERE mmos_sub = :sub LIMIT 1");
    $stmt->execute([':sub' => $sub]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['open' => 0, 'approvals_waiting' => 0]);
        exit();
    }

    $user_id = $user['id'];

    // Count open tickets created by this user
    $stmt = $db->prepare(
        "SELECT COUNT(*) as cnt FROM tickets WHERE creator_id = :uid AND status NOT IN ('closed', 'resolved')"
    );
    $stmt->execute([':uid' => $user_id]);
    $open = (int)$stmt->fetch()['cnt'];

    echo json_encode(['open' => $open, 'approvals_waiting' => 0]);

} catch (PDOException $e) {
    echo json_encode(['open' => 0, 'approvals_waiting' => 0]);
}
