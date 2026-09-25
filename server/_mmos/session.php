<?php
// /_mmos/session — accepts the MM OS JWT, verifies it, provisions the user, and starts a
// MiniHelp session (config/session_token.php). The MM OS token itself is not kept: it is
// short-lived and only proves who the user is at hand-off.

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mmos.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/auth_middleware.php';

$data = json_decode(file_get_contents('php://input'), true);
$token = is_array($data) ? ($data['token'] ?? null) : null;

if (!$token || !is_string($token)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_token']);
    exit();
}

try {
    $claims = mmos_jwt_verifier()->verify($token);
} catch (JWTVerificationError $e) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit();
}

$db = (new Database())->getConnection();
$user = ensure_mmos_user($db, $claims);

if (!$user) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'user_provision_failed']);
    exit();
}

$session = issue_session_token($db, (int)$user['id'], 'mmos');
set_session_cookie($session['token'], $session['exp']);

echo json_encode([
    'ok'    => true,
    'sub'   => $claims['sub'] ?? '',
    'token' => $session['token'],
    'user'  => [
        'id'            => (int)$user['id'],
        'name'          => $user['name'],
        'email'         => $user['email'],
        'role'          => $user['role'],
        'department_id' => $user['department_id'] ?? null,
        'avatar_url'    => $user['avatar_url'] ?? null,
    ],
]);
