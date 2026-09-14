<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This command can only be run from the command line.');
}

require_once __DIR__ . '/../config/database.php';

$migration = __DIR__ . '/../migrations/001_initial_schema.sql';
$sql = file_get_contents($migration);

if ($sql === false) {
    fwrite(STDERR, "Could not read the schema migration.\n");
    exit(1);
}

if (!$conn->multi_query($sql)) {
    fwrite(STDERR, "Migration failed: {$conn->error}\n");
    exit(1);
}

do {
    if ($result = $conn->store_result()) {
        $result->free();
    }
} while ($conn->more_results() && $conn->next_result());

if ($conn->error) {
    fwrite(STDERR, "Migration failed: {$conn->error}\n");
    exit(1);
}

echo "Schema migration completed.\n";
