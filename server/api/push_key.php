<?php
// api/push_key.php — the VAPID public key the browser subscribes with.
require_once '../config/cors.php';
setup_cors();

require_once '../config/db.php';
require_once '../config/auth_middleware.php';
require_once '../config/push.php';

$db = (new Database())->getConnection();
require_auth($db);

try {
    echo json_encode(["success" => true, "publicKey" => vapid_keys($db)['publicKey']]);
} catch (\Throwable $e) {
    error_log("VAPID key error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Push notifications are unavailable"]);
}
