<?php
// config/auth_middleware.php — Unified auth: accepts both minihelp_token (legacy) and MM OS SSO cookie

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mmos.php';
require_once __DIR__ . '/jwt.php';

class AuthResult {
    public int $user_id;
    public string $name;
    public string $email;
    public string $role;
    public ?int $department_id;
    public string $auth_source; // 'local' or 'mmos'
    public ?string $mmos_sub;

    public function __construct(array $data) {
        $this->user_id       = (int)($data['id'] ?? 0);
        $this->name          = $data['name'] ?? '';
        $this->email         = $data['email'] ?? '';
        $this->role          = $data['role'] ?? 'employee';
        $this->department_id = isset($data['department_id']) ? (int)$data['department_id'] : null;
        $this->auth_source   = $data['auth_source'] ?? 'local';
        $this->mmos_sub      = $data['mmos_sub'] ?? null;
    }
}

function get_authenticated_user(PDO $db): ?AuthResult {
    // Try MM OS SSO cookie first
    $cfg = mmos_config();
    $sso_token = $_COOKIE[$cfg->cookie_name] ?? null;

    if ($sso_token) {
        try {
            $verifier = mmos_jwt_verifier();
            $claims = $verifier->verify($sso_token);

            $user = ensure_mmos_user($db, $claims);
            if ($user) {
                return new AuthResult(array_merge($user, [
                    'auth_source' => 'mmos',
                    'mmos_sub'    => $claims['sub'] ?? null,
                ]));
            }
        } catch (JWTVerificationError $e) {
            // SSO token invalid — fall through to local auth
        }
    }

    // Try local Bearer token from Authorization header
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $auth_header, $matches)) {
        $token = $matches[1];
        $decoded = json_decode(base64_decode($token), true);

        if ($decoded && isset($decoded['id'], $decoded['exp'])) {
            if ($decoded['exp'] >= time()) {
                $stmt = $db->prepare("SELECT id, name, email, role, department_id FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $decoded['id']]);
                $user = $stmt->fetch();
                if ($user) {
                    return new AuthResult(array_merge($user, ['auth_source' => 'local']));
                }
            }
        }
    }

    return null;
}

function require_auth(PDO $db): AuthResult {
    $user = get_authenticated_user($db);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit();
    }
    return $user;
}

// Map MM OS token roles to MiniHelp roles
function map_mmos_role(array $roles): string {
    if (in_array('admin', $roles, true))     return 'admin';
    if (in_array('agent', $roles, true))     return 'agent';
    if (in_array('dept_head', $roles, true)) return 'dept_head';
    if (in_array('requester', $roles, true)) return 'employee';
    return 'employee';
}

function ensure_mmos_sub_column(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    try {
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'mmos_sub'");
        if ($stmt->rowCount() === 0) {
            $db->exec("ALTER TABLE users ADD COLUMN mmos_sub VARCHAR(128) NULL");
            $db->exec("ALTER TABLE users ADD INDEX idx_mmos_sub (mmos_sub)");
        }
    } catch (PDOException $e) {
        // ignore — column may already exist or DB doesn't support SHOW COLUMNS
    }
    $checked = true;
}

// Auto-provision or update a MiniHelp user from MM OS JWT claims
function ensure_mmos_user(PDO $db, array $claims): ?array {
    ensure_mmos_sub_column($db);

    $email = $claims['email'] ?? null;
    $sub   = $claims['sub']   ?? null;
    if (!$email && !$sub) return null;

    $name  = $claims['name'] ?? 'MM OS User';
    $roles = $claims['roles'] ?? [];
    $role  = map_mmos_role($roles);
    $dept  = $claims['dept'] ?? null;

    // Look up by mmos_sub first, then by email
    $user = null;
    if ($sub) {
        $stmt = $db->prepare("SELECT * FROM users WHERE mmos_sub = :sub LIMIT 1");
        $stmt->execute([':sub' => $sub]);
        $user = $stmt->fetch();
    }
    if (!$user && $email) {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
    }

    // Resolve department_id from name
    $department_id = null;
    if ($dept) {
        $stmt = $db->prepare("SELECT id FROM departments WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $dept]);
        $row = $stmt->fetch();
        if ($row) $department_id = (int)$row['id'];
    }

    if ($user) {
        // Update existing user with latest claims
        $stmt = $db->prepare(
            "UPDATE users SET name = :name, role = :role, department_id = :dept, mmos_sub = :sub WHERE id = :id"
        );
        $stmt->execute([
            ':name' => $name,
            ':role' => $role,
            ':dept' => $department_id,
            ':sub'  => $sub,
            ':id'   => $user['id'],
        ]);
        $user['name'] = $name;
        $user['role'] = $role;
        $user['department_id'] = $department_id;
        $user['mmos_sub'] = $sub;
    } else {
        // Create new user from SSO claims
        $stmt = $db->prepare(
            "INSERT INTO users (name, email, password_hash, role, department_id, mmos_sub) VALUES (:name, :email, :ph, :role, :dept, :sub)"
        );
        $placeholder_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $stmt->execute([
            ':name'  => $name,
            ':email' => $email,
            ':ph'    => $placeholder_hash,
            ':role'  => $role,
            ':dept'  => $department_id,
            ':sub'   => $sub,
        ]);
        $user = [
            'id'            => (int)$db->lastInsertId(),
            'name'          => $name,
            'email'         => $email,
            'role'          => $role,
            'department_id' => $department_id,
            'mmos_sub'      => $sub,
        ];
    }

    return $user;
}
