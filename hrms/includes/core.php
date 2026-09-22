<?php

declare(strict_types=1);

class ApiError extends RuntimeException
{
    public function __construct(public int $status, string $message)
    {
        parent::__construct($message);
    }
}

function fail(int $status, string $message): never
{
    throw new ApiError($status, $message);
}

function reply(mixed $data = [], string $message = 'Operation completed successfully.', int $status = 200, array $legacy = []): never
{
    http_response_code($status);
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data] + $legacy, JSON_THROW_ON_ERROR);
    exit;
}

function input(): array
{
    static $body;
    if ($body !== null) return $body;
    if (str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
        $raw = file_get_contents('php://input');
        try { $object = json_decode((string) $raw, false, 32, JSON_THROW_ON_ERROR); }
        catch (JsonException) { fail(400, 'Invalid JSON request body.'); }
        if (!is_object($object)) fail(400, 'Request body must be a JSON object.');
        $body = json_decode((string) $raw, true, 32, JSON_THROW_ON_ERROR);
    } else {
        $body = $_POST;
    }
    return $body;
}

function id(mixed $value, string $name = 'ID'): int
{
    if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,18}$/D', (string) $value)) {
        fail(400, "{$name} must be a positive integer.");
    }
    return (int) $value;
}

function textValue(mixed $value, string $name, int $max = 5000, bool $required = true): string
{
    if (!is_string($value) || strlen(trim($value)) > $max || ($required && trim($value) === '')) {
        fail(400, "{$name} must be valid text (maximum {$max} bytes).");
    }
    return trim($value);
}

function choice(mixed $value, array $values, string $name = 'status'): string
{
    if (!is_string($value) || !in_array($value, $values, true)) fail(400, "Invalid {$name}.");
    return $value;
}

function number(mixed $value, float $min, float $max, string $name): float
{
    if ((!is_string($value) && !is_int($value) && !is_float($value)) || !is_numeric($value)
        || !is_finite((float) $value) || (float) $value < $min || (float) $value > $max) {
        fail(400, "{$name} is outside the permitted range.");
    }
    return (float) $value;
}

function money(mixed $value, string $name = 'amount'): string
{
    if ((!is_int($value) && !is_float($value) && !is_string($value))
        || !preg_match('/^\d{1,10}(\.\d{1,2})?$/D', (string) $value)) {
        fail(400, "{$name} must be a non-negative amount with at most two decimal places.");
    }
    return number_format(number($value, 0, 9999999999.99, $name), 2, '.', '');
}

function dateValue(mixed $value, string $name = 'date'): string
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) fail(400, "{$name} must use YYYY-MM-DD.");
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $value < '2000-01-01' || $value > '2100-12-31') {
        fail(400, "Invalid {$name} (2000-2100 required).");
    }
    return $value;
}

function dates(array $body, string $start = 'start_date', string $end = 'end_date'): array
{
    $first = dateValue($body[$start] ?? null, $start);
    $last = dateValue($body[$end] ?? null, $end);
    if ($first > $last) fail(400, 'Start date cannot be later than end date.');
    return [$first, $last];
}

function query(string $sql, array $params = []): MysqliCompatStatement
{
    global $conn;
    if (!isset($conn) || !$conn instanceof MysqliCompatConnection) fail(503, 'Database connection unavailable.');
    $statement = $conn->prepare($sql);
    if ($params !== []) {
        $types = '';
        foreach ($params as $param) $types .= is_int($param) ? 'i' : (is_float($param) ? 'd' : 's');
        $statement->bind_param($types, ...$params);
    }
    $statement->execute();
    $GLOBALS['hrms_last_insert_id'] = $statement->insert_id;
    return $statement;
}

function rows(string $sql, array $params = []): array
{
    $statement = query($sql, $params);
    $records = $statement->get_result()->fetch_all();
    $statement->close();
    return $records;
}

function one(string $sql, array $params = []): ?array { return rows($sql, $params)[0] ?? null; }

function record(string $table, string $key, int $value, bool $lock = false): array
{
    if (!preg_match('/^[a-z_][a-z0-9_]*$/D', $table) || !preg_match('/^[a-z_][a-z0-9_]*$/D', $key)) {
        throw new LogicException('Invalid developer-defined database identifier.');
    }
    return one("SELECT * FROM {$table} WHERE {$key}=?" . ($lock ? ' FOR UPDATE' : ''), [$value])
        ?? fail(404, 'Record not found.');
}

function inserted(): int { return (int) ($GLOBALS['hrms_last_insert_id'] ?? 0); }

function transaction(callable $work): mixed
{
    global $conn;
    $conn->begin_transaction();
    try {
        $result = $work();
        $conn->commit();
        return $result;
    } catch (Throwable $exception) {
        if ($conn->pdo()->inTransaction()) $conn->rollback();
        throw $exception;
    }
}

function audit(string $action, string $entity, ?int $entityId = null, array $metadata = []): void
{
    query('INSERT INTO audit_logs (user_id,action,entity,entity_id,metadata,ip_address) VALUES (?,?,?,?,?,?)',
        [$_SESSION['user_id'] ?? null, $action, $entity, $entityId, json_encode($metadata, JSON_THROW_ON_ERROR), substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45)]);
}

function notifyEmployee(int $employee, string $title, string $entity, int $entityId): void
{
    query("INSERT INTO notifications (user_id,title,entity,entity_id) SELECT user_id,?,?,? FROM users WHERE employee_id=? AND account_status='Active'",
        [$title, $entity, $entityId, $employee]);
}

function notifyHR(string $title, string $entity, int $entityId): void
{
    query("INSERT INTO notifications (user_id,title,entity,entity_id) SELECT u.user_id,?,?,? FROM users u JOIN roles r ON r.role_id=u.role_id WHERE r.role_name IN ('Admin','HR') AND u.account_status='Active'",
        [$title, $entity, $entityId]);
}

function ownEmployee(): int { return getCurrentEmployeeId() ?: fail(403, 'Employee profile required.'); }

function scope(string $column, array &$params): string
{
    if (isAdminOrHR()) return '1=1';
    $params[] = ownEmployee();
    if (($_SESSION['role_name'] ?? '') === 'Manager') {
        return "EXISTS (SELECT 1 FROM manager_assignments ma WHERE ma.employee_id={$column} AND ma.manager_employee_id=? AND ma.status='Active')";
    }
    return "{$column}=?";
}

function pageLimit(): string
{
    $limit = isset($_GET['limit']) ? id($_GET['limit'], 'limit') : 100;
    $page = isset($_GET['page']) ? id($_GET['page'], 'page') : 1;
    if ($limit > 200 || $page > 100000) fail(400, 'Pagination exceeds permitted range.');
    return ' LIMIT ' . $limit . ' OFFSET ' . (($page - 1) * $limit);
}

function requireReviewer(int $employee): void
{
    requireRole(['Admin', 'HR', 'Manager']);
    requireEmployeeAccess($employee);
    if (ownEmployee() === $employee) fail(403, 'A reviewer cannot approve their own record.');
}
