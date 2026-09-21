<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This command can only be run from the command line.');
}

$projectRoot = dirname(__DIR__);
$backendRoot = (string) (getenv('HRMS_BACKEND_ROOT') ?: $projectRoot . '/hrms');
require_once $backendRoot . '/config/database.php';

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$dialect = $driver === 'pgsql' ? 'postgresql' : $driver;
$migrationDirectory = $projectRoot . '/database/' . $dialect;

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )'
);

$files = is_dir($migrationDirectory) ? (glob($migrationDirectory . '/*.sql') ?: []) : [];
if ($driver === 'mysql' && $files === []) {
    $legacyMigration = $backendRoot . '/migrations/001_initial_schema.sql';
    if (is_file($legacyMigration)) {
        $files = [$legacyMigration];
    }
}

if ($files === []) {
    fwrite(STDERR, "No migrations are available for database driver '{$driver}'.\n");
    exit(1);
}
sort($files, SORT_STRING);

try {
    foreach ($files as $file) {
        $version = basename($file);
        $check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
        $check->execute([$version]);

        if ($check->fetchColumn()) {
            echo "Already applied: {$version}\n";
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Could not read migration: {$version}");
        }

        $transactional = $driver === 'pgsql';
        if ($transactional) {
            $pdo->beginTransaction();
        }

        try {
            $pdo->exec($sql);
            $record = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
            $record->execute([$version]);

            if ($transactional) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($transactional && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        echo "Applied: {$version}\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

echo "Database migrations are up to date.\n";
