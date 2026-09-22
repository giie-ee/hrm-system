<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

set_exception_handler(static function (Throwable $exception): void {
    $status = $exception instanceof ApiError ? $exception->status : 500;
    $message = $exception instanceof ApiError ? $exception->getMessage() : 'Unable to complete request. Contact the administrator.';
    if ($exception instanceof PDOException
        && in_array((string) $exception->getCode(), ['23503', '23505', '40001', '40P01'], true)) {
        $status = 409;
        $message = 'The operation conflicts with an existing record or concurrent change.';
    }
    if ($status === 500) {
        error_log(sprintf(
            'HRMS failure %s code %s at %s:%d: %s',
            get_class($exception),
            (string) $exception->getCode(),
            basename($exception->getFile()),
            $exception->getLine(),
            $exception->getMessage()
        ));
    }
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
});

require_once __DIR__ . '/session.php';
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$configuredOrigins = getenv('CORS_ORIGINS') ?: getenv('HRMS_ALLOWED_ORIGINS') ?: '';
$allowedOrigins = $configuredOrigins !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $configuredOrigins))))
    : ['http://localhost:5173', 'http://127.0.0.1:5173'];
$forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$scheme = $forwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$ownOrigin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
if ($origin !== '' && $origin !== $ownOrigin && !in_array($origin, $allowedOrigins, true)) fail(403, 'Origin is not permitted.');
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
    http_response_code(204);
    exit;
}

$expectedMethod = defined('HRMS_METHOD') ? HRMS_METHOD : 'GET';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== $expectedMethod) {
    header('Allow: ' . $expectedMethod . ', OPTIONS');
    fail(405, 'Method not allowed.');
}
foreach ($_GET as $key => $value) {
    if (!is_string($value)) fail(400, 'Query parameters must be scalar values.');
    if (str_ends_with((string) $key, '_id') && $value !== '') id($value, (string) $key);
}

$public = defined('HRMS_PUBLIC') && HRMS_PUBLIC;
if (!$public && !isset($_SESSION['user_id'])) fail(401, 'Authentication required.');
if (!$public && time() - (int) ($_SESSION['last_activity'] ?? time()) > 1800) {
    $_SESSION = [];
    session_regenerate_id(true);
    fail(401, 'Session expired. Please log in again.');
}
if ($expectedMethod === 'POST') {
    $body = input();
    foreach ($body as $key => $value) {
        if (str_ends_with((string) $key, '_id') && $value !== null) id($value, (string) $key);
        if (is_array($value) && !in_array($key, ['ratings'], true)) fail(400, 'Unexpected structured input.');
    }
    if (!$public && (!isset($_SERVER['HTTP_X_CSRF_TOKEN'], $_SESSION['csrf_token'])
        || !hash_equals((string) $_SESSION['csrf_token'], (string) $_SERVER['HTTP_X_CSRF_TOKEN']))) {
        fail(403, 'A valid X-CSRF-Token header is required.');
    }
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
if (!$public) {
    requireLogin();
    $_SESSION['last_activity'] = time();
}
