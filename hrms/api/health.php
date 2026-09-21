<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->query('SELECT 1');
    echo json_encode([
        'status' => 'ok',
        'service' => 'hrms',
        'database' => 'connected',
    ]);
} catch (Throwable $exception) {
    error_log('Health check failed: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'service' => 'hrms',
        'database' => 'unavailable',
    ]);
}
