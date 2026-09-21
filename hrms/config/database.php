<?php

declare(strict_types=1);

date_default_timezone_set('Africa/Lusaka');

require_once __DIR__ . '/../lib/Database.php';

try {
    $pdo = Database::connect();
    $conn = new MysqliCompatConnection($pdo);
} catch (Throwable $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());

    if (PHP_SAPI === 'cli') {
        throw $exception;
    }

    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'The database is temporarily unavailable.',
    ]);
    exit;
}
