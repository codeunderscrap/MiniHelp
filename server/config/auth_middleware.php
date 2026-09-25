<?php
// config/auth_middleware.php — Every API request is authenticated with a MiniHelp session token
// (config/session_token.php), sent as the HttpOnly `minihelp_session` cookie or as a Bearer header.
// Password login (/api/auth.php) and MM OS SSO (/_mmos/session) are the only ways to get one.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mmos.php';
require_once __DIR__ . '/session_token.php';

const ROLE_RANK = ['employee' => 0, 'user' => 0, 'agent' => 1, 'dept_head' => 2, 'admin' => 3];
const STAFF_ROLES = ['agent', 'dept_head', 'admin'];
const MANAGER_ROLES = ['dept_head', 'admin'];

class AuthResult {
    public int $user_id;
    public string $name;
    public string $email;
    public string $role;
    public ?int $department_id;

    public function __construct(array $data) {
        $this->user_id       = (int)($data['id'] ?? 0);
        $this->name          = $data['name'] ?? '';
        $this->email         = $data['email'] ?? '';
        $this->role          = $data['role'] ?? 'employee';
        $this->department_id = isset($data['department_id']) ? (int)$data['department_id'] : null;
    }

    public function is_admin(): bool   { return $this->role === 'admin'; }
    public function is_staff(): bool   { return in_array($this->role, STAFF_ROLES, true); }
    public function is_manager(): bool { return in_array($this->role, MANAGER_ROLES, true); }
}

function request_session_token(): ?string {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(\S+)$/i', $auth_header, $m)) {
        return $m[1];
    }
    return $_COOKIE[MINIHELP_SESSION_COOKIE] ?? null;
}

function get_authenticated_user(PDO $db): ?AuthResult {
    $token = request_session_token();
    if (!$token) return null;

    $uid = verify_session_token($db, $token);
    if ($uid === null) return null;

    // Read the role fresh on every request so a demotion takes effect immediately.
    $stmt = $db->prepare("SELECT id, name, email, role, department_id FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ? new AuthResult($user) : null;
}

function deny(int $status, string $error): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $error]);
    exit();
}

function require_auth(PDO $db): AuthResult {
    $user = get_authenticated_user($db);
    if (!$user) deny(401, 'Authentication required');
    return $user;
}

function require_staff(AuthResult $user): void {
    if (!$user->is_staff()) deny(403, 'Not allowed');
}

function require_manager(AuthResult $user): void {
    if (!$user->is_manager()) deny(403, 'Not allowed');
}

// Admins see every ticket; agents and department heads see their department's queue and
// anything assigned to them; everyone sees tickets they raised.
function can_view_ticket(AuthResult $user, array $ticket): bool {
    if ($user->is_admin()) return true;
    if ((int)$ticket['creator_id'] === $user->user_id) return true;
    if ($user->is_staff()) {
        if (isset($ticket['assignee_id']) && (int)$ticket['assignee_id'] === $user->user_id) return true;
        if ($user->department_id !== null && (int)$ticket['department_id'] === $user->department_id) return true;
    }
    return false;
}

// Changing status or assignee is staff work, limited to the tickets that staff member can see.
function can_work_ticket(AuthResult $user, array $ticket): bool {
    return $user->is_staff() && can_view_ticket($user, $ticket);
}

function load_ticket(PDO $db, $id): ?array {
    $stmt = $db->prepare("SELECT id, creator_id, assignee_id, department_id FROM tickets WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    return $ticket ?: null;
}

// Password login is for standalone installs. Once MM OS SSO is configured it is off unless
// MINIHELP_ALLOW_LOCAL_LOGIN=1 keeps a break-glass admin account usable.
function local_login_enabled(): bool {
    if (getenv('MINIHELP_ALLOW_LOCAL_LOGIN') === '1') return true;
    return mmos_config()->service_key === '';
}

// Map MM OS token roles to MiniHelp roles
function map_mmos_role(array $roles): string {
    if (in_array('admin', $roles, true))     return 'admin';
    if (in_array('agent', $roles, true))     return 'agent';
    if (in_array('dept_head', $roles, true)) return 'dept_head';
    return 'employee';
}

function ensure_mmos_sub_column(PDO $db): void {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'mmos_sub'");
        if ($stmt->rowCount() === 0) {
            $db->exec("ALTER TABLE users ADD COLUMN mmos_sub VARCHAR(128) NULL");
            $db->exec("ALTER TABLE users ADD INDEX idx_mmos_sub (mmos_sub)");
        }
    } catch (PDOException $e) {
        // ignore — column may already exist
    }
}

// Auto-provision or update a MiniHelp user from MM OS JWT claims.
// MM OS can raise a role but never lowers one: agents and department heads are set up inside
// MiniHelp (Settings → Users) and a sign-in must not undo that. The department is only filled
// in when the user has none, because MM OS's HR department names differ from MiniHelp's queues.
function ensure_mmos_user(PDO $db, array $claims): ?array {
    ensure_mmos_sub_column($db);

    $email = $claims['email'] ?? null;
    $sub   = $claims['sub']   ?? null;
    if (!$email || !$sub) return null;

    $name  = $claims['name'] ?? $email;
    $role  = map_mmos_role($claims['roles'] ?? []);
    $dept  = $claims['dept'] ?? null;

    $stmt = $db->prepare("SELECT * FROM users WHERE mmos_sub = :sub LIMIT 1");
    $stmt->execute([':sub' => $sub]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $department_id = null;
    if ($dept) {
        $stmt = $db->prepare("SELECT id FROM departments WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $dept]);
        $found = $stmt->fetchColumn();
        if ($found) $department_id = (int)$found;
    }

    if ($user) {
        $current_rank = ROLE_RANK[$user['role']] ?? 0;
        $new_role = (ROLE_RANK[$role] > $current_rank) ? $role : $user['role'];
        $new_dept = $user['department_id'] ?? $department_id;

        if ($user['name'] !== $name || $user['role'] !== $new_role
            || $user['department_id'] != $new_dept || $user['mmos_sub'] !== $sub) {
            $stmt = $db->prepare(
                "UPDATE users SET name = :name, role = :role, department_id = :dept, mmos_sub = :sub WHERE id = :id"
            );
            $stmt->execute([
                ':name' => $name, ':role' => $new_role, ':dept' => $new_dept,
                ':sub' => $sub, ':id' => $user['id'],
            ]);
        }
        $user['name'] = $name;
        $user['role'] = $new_role;
        $user['department_id'] = $new_dept;
        $user['mmos_sub'] = $sub;
        return $user;
    }

    $stmt = $db->prepare(
        "INSERT INTO users (name, email, password_hash, role, department_id, mmos_sub) VALUES (:name, :email, :ph, :role, :dept, :sub)"
    );
    $stmt->execute([
        ':name'  => $name,
        ':email' => $email,
        ':ph'    => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
        ':role'  => $role,
        ':dept'  => $department_id,
        ':sub'   => $sub,
    ]);
    return [
        'id'            => (int)$db->lastInsertId(),
        'name'          => $name,
        'email'         => $email,
        'role'          => $role,
        'department_id' => $department_id,
        'mmos_sub'      => $sub,
    ];
}
