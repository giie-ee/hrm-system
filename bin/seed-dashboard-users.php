<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This command can only be run from the command line.');
}

if (getenv('ALLOW_DASHBOARD_TEST_SEED') !== '1') {
    fwrite(STDERR, "Dashboard test-user seeding is disabled. Set ALLOW_DASHBOARD_TEST_SEED=1 for this one command.\n");
    exit(1);
}

$environment = strtolower((string) (getenv('APP_ENV') ?: 'local'));
if ($environment === 'production' && getenv('ALLOW_PRODUCTION_SEED') !== '1') {
    fwrite(STDERR, "Production seeding is disabled. Set ALLOW_PRODUCTION_SEED=1 only for this one command.\n");
    exit(1);
}

$accounts = [
    [
        'role' => 'Admin',
        'employee_number' => 'TEST-ADMIN-001',
        'first_name' => 'Test',
        'last_name' => 'Administrator',
        'username' => 'admin',
        'email' => 'admin@example.test',
        'password_env' => 'SEED_ADMIN_PASSWORD',
    ],
    [
        'role' => 'HR',
        'employee_number' => 'TEST-HR-001',
        'first_name' => 'Test',
        'last_name' => 'HR Officer',
        'username' => 'hruser',
        'email' => 'hruser@example.test',
        'password_env' => 'SEED_HR_PASSWORD',
    ],
    [
        'role' => 'Manager',
        'employee_number' => 'TEST-MANAGER-001',
        'first_name' => 'Test',
        'last_name' => 'Manager',
        'username' => 'manager',
        'email' => 'manager@example.test',
        'password_env' => 'SEED_MANAGER_PASSWORD',
    ],
    [
        'role' => 'Employee',
        'employee_number' => 'TEST-EMPLOYEE-001',
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'username' => 'employee',
        'email' => 'employee@example.test',
        'password_env' => 'SEED_EMPLOYEE_PASSWORD',
    ],
];

function validateSeedPassword(string $password, string $variable): void
{
    $valid = strlen($password) >= 12
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^a-zA-Z0-9]/', $password);

    if (!$valid) {
        fwrite(
            STDERR,
            "{$variable} must contain at least 12 characters, including uppercase, lowercase, a number and a symbol.\n"
        );
        exit(1);
    }
}

foreach ($accounts as &$account) {
    $password = (string) getenv($account['password_env']);
    if ($password === '') {
        fwrite(STDERR, "Missing required environment variable: {$account['password_env']}\n");
        exit(1);
    }

    validateSeedPassword($password, $account['password_env']);
    $account['password'] = $password;
}
unset($account);

$projectRoot = dirname(__DIR__);
$backendRoot = (string) (getenv('HRMS_BACKEND_ROOT') ?: $projectRoot . '/hrms');
require_once $backendRoot . '/config/database.php';

try {
    $pdo->beginTransaction();

    $roleStatement = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = ? LIMIT 1');
    $existingUserStatement = $pdo->prepare(
        'SELECT user_id, username FROM users WHERE username = ? OR email = ? LIMIT 1'
    );
    $employeeStatement = $pdo->prepare(
        'SELECT employee_id FROM employees WHERE employee_number = ? OR email = ? LIMIT 1'
    );
    $insertEmployeeStatement = $pdo->prepare(
        "INSERT INTO employees
            (employee_number, first_name, last_name, email, employment_type, employment_status)
         VALUES (?, ?, ?, ?, 'Full-Time', 'Active')
         RETURNING employee_id"
    );
    $insertUserStatement = $pdo->prepare(
        "INSERT INTO users
            (employee_id, role_id, username, email, password_hash, account_status)
         VALUES (?, ?, ?, ?, ?, 'Active')"
    );

    $messages = [];

    foreach ($accounts as $account) {
        $roleStatement->execute([$account['role']]);
        $roleId = $roleStatement->fetchColumn();
        if ($roleId === false) {
            throw new RuntimeException("The {$account['role']} role is missing; run migrations first.");
        }

        $existingUserStatement->execute([$account['username'], $account['email']]);
        $existingUser = $existingUserStatement->fetch();
        if ($existingUser) {
            $messages[] = "Skipped {$account['username']}: an account with that username or email already exists.";
            continue;
        }

        $employeeStatement->execute([$account['employee_number'], $account['email']]);
        $employeeId = $employeeStatement->fetchColumn();

        if ($employeeId === false) {
            $insertEmployeeStatement->execute([
                $account['employee_number'],
                $account['first_name'],
                $account['last_name'],
                $account['email'],
            ]);
            $employeeId = $insertEmployeeStatement->fetchColumn();
        }

        $insertUserStatement->execute([
            $employeeId,
            $roleId,
            $account['username'],
            $account['email'],
            password_hash($account['password'], PASSWORD_DEFAULT),
        ]);
        $messages[] = "Created {$account['username']} with the {$account['role']} role.";
    }

    $pdo->commit();
    foreach ($messages as $message) {
        echo $message . PHP_EOL;
    }
    echo "Dashboard test-user seed completed. Remove the seed environment variables now.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Dashboard test-user seed failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
