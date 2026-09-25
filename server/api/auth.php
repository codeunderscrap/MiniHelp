<?php
// api/auth.php — POST: password login. DELETE: sign out.
require_once '../config/cors.php';
setup_cors();

include_once '../config/db.php';
require_once '../config/auth_middleware.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'DELETE') {
    clear_session_cookie();
    echo json_encode(["success" => true]);
    exit();
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit();
}

if (!local_login_enabled()) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Password sign-in is off. Sign in through MM OS."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->email) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Incomplete data."]);
    exit();
}

try {
    $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([":email" => $data->email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($data->password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Invalid credentials."]);
        exit();
    }

    unset($user['password_hash']);

    if ($user['department_id']) {
        $dStmt = $db->prepare("SELECT name, code FROM departments WHERE id = :did");
        $dStmt->execute([":did" => $user['department_id']]);
        $user['department'] = $dStmt->fetch(PDO::FETCH_ASSOC);
    }

    $session = issue_session_token($db, (int)$user['id'], 'local');
    set_session_cookie($session['token'], $session['exp']);

    echo json_encode([
        "success" => true,
        "token" => $session['token'],
        "user" => $user
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Sign-in failed."]);
}
