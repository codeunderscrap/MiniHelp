<?php
// /_mmos/health — healthcheck endpoint for MM OS service monitoring

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mmos.php';

$cfg = mmos_config();
$db_ok = false;

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->query("SELECT 1");
    $db_ok = true;
} catch (Exception $e) {
    $db_ok = false;
}

echo json_encode([
    'ok'      => $db_ok,
    'service' => $cfg->service_slug,
    'version' => '1.0.0',
    'db'      => $db_ok ? 'up' : 'down',
]);
