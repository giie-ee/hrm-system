<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This command can only be run from the command line.');
}

$environment = strtolower((string) (getenv('APP_ENV') ?: 'local'));
if ($environment === 'production' && getenv('ALLOW_PRODUCTION_SEED') !== '1') {
    fwrite(STDERR, "Production seeding is disabled. Set ALLOW_PRODUCTION_SEED=1 only for this one command.\n");
    exit(1);
}

$values = [
    'employee_number' => trim((string) getenv('SEED_ADMIN_EMPLOYEE_NUMBER')),
    'first_name' => trim((string) getenv('SEED_ADMIN_FIRST_NAME')),
    'last_name' => trim((string) getenv('SEED_ADMIN_LAST_NAME')),
    'username' => trim((string) getenv('SEED_ADMIN_USERNAME')),
    'email' => trim((string) getenv('SEED_ADMIN_EMAIL')),
    'password' => (string) getenv('SEED_ADMIN_PASSWORD'),
];

foreach ($values as $name => $value) {
    if ($value === '') {
        fwrite(STDERR, 'Missing required environment variable: SEED_ADMIN_' . strtoupper($name) . "\n");
        exit(1);
    }
}

if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "SEED_ADMIN_EMAIL must be a valid email address.\n");
    exit(1);
}

if (strlen($values['password']) < 12) {
    fwrite(STDERR, "SEED_ADMIN_PASSWORD must contain at least 12 characters.\n");
    exit(1);
}

$projectRoot = dirname(__DIR__);
$backendRoot = (string) (getenv('HRMS_BACKEND_ROOT') ?: $projectRoot . '/hrms');
require_once $backendRoot . '/config/database.php';

try {
    $pdo->beginTransaction();

    $role = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'Admin' LIMIT 1");
    $role->execute();
    $roleId = $role->fetchColumn();
    if ($roleId === false) {
        throw new RuntimeException('The Admin role is missing; run migrations first.');
    }

    $existingUser = $pdo->prepare('SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $existingUser->execute([$values['username'], $values['email']]);
    if ($existingUser->fetchColumn()) {
        $pdo->rollBack();
        echo "An administrator with this username or email already exists; nothing was changed.\n";
        exit(0);
    }

    $employee = $pdo->prepare('SELECT employee_id FROM employees WHERE employee_number = ? OR email = ? LIMIT 1');
    $employee->execute([$values['employee_number'], $values['email']]);
    $employeeId = $employee->fetchColumn();

    if ($employeeId === false) {
        $insertSql = 'INSERT INTO employees
            (employee_number, first_name, last_name, email, employment_type, employment_status)
            VALUES (?, ?, ?, ?, ?, ?)';
        $parameters = [
            $values['employee_number'],
            $values['first_name'],
            $values['last_name'],
            $values['email'],
            (string) (getenv('SEED_ADMIN_EMPLOYMENT_TYPE') ?: 'Permanent'),
            'Active',
        ];

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $insertSql .= ' RETURNING employee_id';
            $insertEmployee = $pdo->prepare($insertSql);
            $insertEmployee->execute($parameters);
            $employeeId = $insertEmployee->fetchColumn();
        } else {
            $insertEmployee = $pdo->prepare($insertSql);
            $insertEmployee->execute($parameters);
            $employeeId = $pdo->lastInsertId();
        }
    }

    $insertUser = $pdo->prepare(
        "INSERT INTO users
            (employee_id, role_id, username, email, password_hash, account_status)
         VALUES (?, ?, ?, ?, ?, 'Active')"
    );
    $insertUser->execute([
        $employeeId,
        $roleId,
        $values['username'],
        $values['email'],
        password_hash($values['password'], PASSWORD_DEFAULT),
    ]);

    $pdo->commit();
    echo "Administrator employee and account are ready.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Administrator seed failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
