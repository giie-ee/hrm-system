<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/core.php';

function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required.']);
        exit;
    }

    static $checked = false;
    if ($checked) return;

    global $conn;
    if (!isset($conn)) require_once __DIR__ . '/../config/database.php';

    $user = one(
        'SELECT u.user_id,u.employee_id,u.role_id,u.username,u.account_status,u.auth_version,r.role_name,e.employment_status '
        . 'FROM users u JOIN roles r ON r.role_id=u.role_id JOIN employees e ON e.employee_id=u.employee_id WHERE u.user_id=?',
        [(int) $_SESSION['user_id']]
    );
    if (!$user || $user['account_status'] !== 'Active' || $user['employment_status'] !== 'Active'
        || (int) $user['auth_version'] !== (int) ($_SESSION['auth_version'] ?? 1)) {
        $_SESSION = [];
        session_regenerate_id(true);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session is no longer valid. Please log in again.']);
        exit;
    }

    foreach (['employee_id', 'role_id', 'role_name', 'username'] as $key) $_SESSION[$key] = $user[$key];
    $checked = true;
}

function requireRole(array $allowedRoles): void
{
    requireLogin();
    if (!in_array($_SESSION['role_name'] ?? '', $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }
}

function getCurrentEmployeeId(): ?int
{
    requireLogin();
    return isset($_SESSION['employee_id']) ? (int) $_SESSION['employee_id'] : null;
}

function isAdminOrHR(): bool
{
    requireLogin();
    return in_array($_SESSION['role_name'] ?? '', ['Admin', 'HR'], true);
}

function requireEmployeeAccess(mixed $targetEmployeeId): bool
{
    requireLogin();
    $target = id($targetEmployeeId, 'employee_id');
    $role = $_SESSION['role_name'] ?? '';
    $current = getCurrentEmployeeId();
    if (in_array($role, ['Admin', 'HR'], true) || $current === $target) return true;

    if ($role === 'Manager' && $current !== null) {
        $assignment = one(
            "SELECT assignment_id FROM manager_assignments WHERE manager_employee_id=? AND employee_id=? AND status='Active' LIMIT 1",
            [$current, $target]
        );
        if ($assignment) return true;
    }

    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not authorized to access this employee record.']);
    exit;
}
