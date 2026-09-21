<?php

declare(strict_types=1);

$origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
$configuredOrigins = trim((string) getenv('CORS_ORIGINS'));
$allowedOrigins = $configuredOrigins !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $configuredOrigins))))
    : ['http://localhost:5173', 'http://127.0.0.1:5173'];

if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    if ($origin !== '' && !in_array($origin, $allowedOrigins, true)) {
        http_response_code(403);
        exit;
    }

    http_response_code(204);
    exit;
}
