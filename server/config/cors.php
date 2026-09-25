<?php
// config/cors.php — The SPA is served from the same origin as the API (nginx proxies /api),
// so no CORS headers are needed. Cross-origin callers must be listed in MINIHELP_ALLOWED_ORIGINS
// (comma-separated); any other origin gets no Access-Control-Allow-Origin and the browser blocks it.

function setup_cors() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = array_filter(array_map('trim', explode(',', getenv('MINIHELP_ALLOWED_ORIGINS') ?: '')));

    if ($origin && in_array($origin, $allowed, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
        header("Vary: Origin");
        header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    }

    header("Content-Type: application/json; charset=UTF-8");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit();
    }
}
