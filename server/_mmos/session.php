<?php
// /_mmos/session — accepts the MM OS JWT, verifies it, provisions the user, sets session cookie

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true");

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header("Access-Control-Allow-Origin: $origin");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
$token = $data['token'] ?? null;

if (!$token) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_token']);
    exit();
}

$cfg = mmos_config();

try {
    $verifier = mmos_jwt_verifier();
    $claims = $verifier->verify($token);
} catch (JWTVerificationError $e) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit();
}

// Auto-provision user from JWT claims
$database = new Database();
$db = $database->getConnection();
$user = ensure_mmos_user($db, $claims);

if (!$user) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'user_provision_failed']);
    exit();
}

// Set the session cookie — HttpOnly, Secure, SameSite=Lax for top-level handoff
// TLS ends at Coolify's proxy, so $_SERVER['HTTPS'] is never set here; trust the environment.
$secure = $cfg->environment === 'production'
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$cookie_opts = [
    'expires'  => $claims['exp'] ?? time() + 900,
    'path'     => '/',
    'secure'   => $secure,
    'httponly'  => true,
    'samesite' => 'Lax',
];
setcookie($cfg->cookie_name, $token, $cookie_opts);

// Return user info (minus password_hash) for the SPA to bootstrap
unset($user['password_hash']);
echo json_encode([
    'ok'   => true,
    'sub'  => $claims['sub'] ?? '',
    'user' => [
        'id'     => (int)$user['id'],
        'name'   => $user['name'],
        'email'  => $user['email'],
        'role'   => $user['role'],
        'avatar' => $user['avatar_url'] ?? null,
    ],
]);
