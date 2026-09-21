<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This command can only be run from the command line.');
}

require_once __DIR__ . '/../config/database.php';

$employeeId = filter_var(getenv('SEED_ADMIN_EMPLOYEE_ID'), FILTER_VALIDATE_INT);
$username = getenv('SEED_ADMIN_USERNAME');
$email = getenv('SEED_ADMIN_EMAIL');
$password = getenv('SEED_ADMIN_PASSWORD');

if (!$employeeId || !$username || !$email || !$password) {
    fwrite(STDERR, "Set SEED_ADMIN_EMPLOYEE_ID, SEED_ADMIN_USERNAME, SEED_ADMIN_EMAIL, and SEED_ADMIN_PASSWORD before running this command.\n");
    exit(1);
}

$employee = $conn->prepare('SELECT employee_id FROM employees WHERE employee_id = ? LIMIT 1');
$employee->bind_param('i', $employeeId);
$employee->execute();

if ($employee->get_result()->num_rows === 0) {
    fwrite(STDERR, "The requested employee does not exist; create the employee before seeding an account.\n");
    exit(1);
}

$role = $conn->prepare("SELECT role_id FROM roles WHERE role_name = 'Admin' LIMIT 1");
$role->execute();
$adminRole = $role->get_result()->fetch_assoc();

if (!$adminRole) {
    fwrite(STDERR, "The Admin role is missing; run php scripts/migrate.php first.\n");
    exit(1);
}

$existing = $conn->prepare('SELECT user_id FROM users WHERE username = ? OR email = ? OR employee_id = ? LIMIT 1');
$existing->bind_param('ssi', $username, $email, $employeeId);
$existing->execute();

if ($existing->get_result()->num_rows > 0) {
    echo "An account with this username, email, or employee already exists; nothing was changed.\n";
    exit(0);
}

$roleId = (int) $adminRole['role_id'];
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$insert = $conn->prepare("INSERT INTO users (employee_id, role_id, username, email, password_hash, account_status) VALUES (?, ?, ?, ?, ?, 'Active')");
$insert->bind_param('iisss', $employeeId, $roleId, $username, $email, $passwordHash);

if (!$insert->execute()) {
    fwrite(STDERR, "Unable to create the administrator account: {$insert->error}\n");
    exit(1);
}

echo "Administrator account created.\n";
