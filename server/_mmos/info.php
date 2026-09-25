<?php
// /_mmos/info — unauthenticated endpoint so the frontend can detect SSO mode

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/mmos.php';
require_once __DIR__ . '/../config/auth_middleware.php';

$cfg = mmos_config();

echo json_encode([
    'os_url'      => $cfg->os_url,
    'slug'        => $cfg->service_slug,
    'auth_mode'   => $cfg->service_key ? 'http' : 'standalone',
    // MM OS opens hand-off services from its Services page, which mints the token.
    'launch_url'  => rtrim($cfg->os_url, '/') . '/services',
    'local_login' => local_login_enabled(),
]);
