<?php
// config/session_token.php — MiniHelp's own signed session tokens (HMAC-SHA256).
// Issued by /api/auth.php (password login) and /_mmos/session (MM OS SSO). Format:
// base64url(payload JSON) . "." . base64url(HMAC-SHA256(secret, payload segment))

const MINIHELP_SESSION_COOKIE = 'minihelp_session';
const MINIHELP_SESSION_TTL    = 8 * 3600;

function session_b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function session_b64url_decode(string $data): string|false {
    return base64_decode(strtr($data, '-_', '+/'), true);
}

// The signing secret comes from MINIHELP_SESSION_SECRET when set. Otherwise one random secret
// is generated once and kept in the database, so every container and restart shares it.
function session_secret(PDO $db): string {
    static $secret = null;
    if ($secret !== null) return $secret;

    $env = getenv('MINIHELP_SESSION_SECRET');
    if ($env && strlen($env) >= 32) {
        return $secret = $env;
    }

    $db->exec("CREATE TABLE IF NOT EXISTS app_secrets (
        name VARCHAR(64) PRIMARY KEY,
        value VARCHAR(255) NOT NULL
    )");
    $stmt = $db->prepare("INSERT IGNORE INTO app_secrets (name, value) VALUES ('session', :v)");
    $stmt->execute([':v' => bin2hex(random_bytes(32))]);
    $secret = $db->query("SELECT value FROM app_secrets WHERE name = 'session'")->fetchColumn();
    if (!$secret) {
        throw new RuntimeException('session secret unavailable');
    }
    return $secret;
}

function issue_session_token(PDO $db, int $user_id, string $source): array {
    $exp = time() + MINIHELP_SESSION_TTL;
    $payload = session_b64url_encode(json_encode(['uid' => $user_id, 'src' => $source, 'exp' => $exp]));
    $sig = session_b64url_encode(hash_hmac('sha256', $payload, session_secret($db), true));
    return ['token' => $payload . '.' . $sig, 'exp' => $exp];
}

// Returns the user id for a valid, unexpired token, or null.
function verify_session_token(PDO $db, string $token): ?int {
    $parts = explode('.', $token);
    if (count($parts) !== 2) return null;
    [$payload, $sig] = $parts;

    $expected = session_b64url_encode(hash_hmac('sha256', $payload, session_secret($db), true));
    if (!hash_equals($expected, $sig)) return null;

    $json = session_b64url_decode($payload);
    $data = $json === false ? null : json_decode($json, true);
    if (!is_array($data) || !isset($data['uid'], $data['exp'])) return null;
    if ((int)$data['exp'] < time()) return null;

    return (int)$data['uid'];
}

function set_session_cookie(string $token, int $exp): void {
    setcookie(MINIHELP_SESSION_COOKIE, $token, [
        'expires'  => $exp,
        'path'     => '/',
        'secure'   => session_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_session_cookie(): void {
    setcookie(MINIHELP_SESSION_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => session_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// TLS ends at Coolify's proxy, so $_SERVER['HTTPS'] is never set here; trust the environment.
function session_cookie_secure(): bool {
    $env = getenv('MMOS_ENVIRONMENT') ?: 'production';
    return $env === 'production' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}
