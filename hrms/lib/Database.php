<?php

declare(strict_types=1);

/**
 * Database connection and a small mysqli-compatible facade.
 *
 * The facade keeps the existing endpoint code stable while the project moves
 * to PDO/PostgreSQL. New backend code should use Database::connect() directly.
 */
final class Database
{
    public static function connect(): PDO
    {
        $databaseUrl = trim((string) getenv('DATABASE_URL'));

        if ($databaseUrl !== '') {
            return self::connectFromUrl($databaseUrl);
        }

        $driver = strtolower((string) (getenv('DB_CONNECTION') ?: 'pgsql'));
        $database = (string) (getenv('DB_DATABASE') ?: ($driver === 'mysql' ? 'hrms_db' : 'hrms'));
        $host = (string) (getenv('DB_HOST') ?: '127.0.0.1');
        $username = (string) (getenv('DB_USERNAME') ?: ($driver === 'mysql' ? 'root' : 'postgres'));
        $password = (string) (getenv('DB_PASSWORD') ?: '');
        $defaultPort = $driver === 'mysql' ? '3306' : '5432';
        $port = (string) (getenv('DB_PORT') ?: $defaultPort);

        if ($driver === 'mysql') {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        } elseif (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
            $sslMode = (string) (getenv('DB_SSLMODE') ?: 'prefer');
            $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslMode}";
        } else {
            throw new RuntimeException("Unsupported DB_CONNECTION: {$driver}");
        }

        return self::createPdo($dsn, $username, $password);
    }

    private static function connectFromUrl(string $databaseUrl): PDO
    {
        $parts = parse_url($databaseUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('DATABASE_URL is not a valid database connection URL.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['postgres', 'postgresql', 'pgsql'], true)) {
            throw new RuntimeException('DATABASE_URL must use PostgreSQL.');
        }

        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? 5432);
        $database = ltrim((string) ($parts['path'] ?? ''), '/');
        $username = rawurldecode((string) ($parts['user'] ?? ''));
        $password = rawurldecode((string) ($parts['pass'] ?? ''));
        parse_str((string) ($parts['query'] ?? ''), $query);
        $sslMode = (string) ($query['sslmode'] ?? (getenv('DB_SSLMODE') ?: 'prefer'));

        if ($database === '' || $username === '') {
            throw new RuntimeException('DATABASE_URL must include a database name and username.');
        }

        $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslMode}";
        return self::createPdo($dsn, $username, $password);
    }

    private static function createPdo(
        string $dsn,
        string $username,
        #[\SensitiveParameter] string $password
    ): PDO
    {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $pdo->exec("SET TIME ZONE 'Africa/Lusaka'");
        }

        return $pdo;
    }
}

final class MysqliCompatConnection
{
    public string $error = '';

    public function __construct(private ?PDO $pdo)
    {
    }

    public function pdo(): PDO
    {
        if (!$this->pdo) {
            throw new RuntimeException('Database connection is closed.');
        }

        return $this->pdo;
    }

    public function prepare(string $sql): MysqliCompatStatement
    {
        return new MysqliCompatStatement($this->pdo(), $sql);
    }

    public function query(string $sql): MysqliCompatResult|bool
    {
        try {
            $statement = $this->pdo()->query($sql);
            if ($statement->columnCount() === 0) {
                return true;
            }

            return new MysqliCompatResult($statement->fetchAll());
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
            throw $exception;
        }
    }

    public function begin_transaction(): bool
    {
        return $this->pdo()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo()->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo()->rollBack();
    }

    public function close(): void
    {
        $this->pdo = null;
    }
}

final class MysqliCompatStatement
{
    private const PRIMARY_KEYS = [
        'announcements' => 'announcement_id',
        'applicants' => 'applicant_id',
        'attendance' => 'attendance_id',
        'audit_logs' => 'audit_id',
        'benefit_history' => 'history_id',
        'benefits' => 'benefit_id',
        'departments' => 'department_id',
        'document_history' => 'history_id',
        'employee_benefits' => 'employee_benefit_id',
        'employee_salaries' => 'salary_id',
        'employees' => 'employee_id',
        'interviews' => 'interview_id',
        'job_applications' => 'application_id',
        'job_vacancies' => 'vacancy_id',
        'leave_types' => 'leave_type_id',
        'leave_requests' => 'leave_request_id',
        'manager_assignments' => 'assignment_id',
        'notifications' => 'notification_id',
        'onboarding' => 'onboarding_id',
        'onboarding_documents' => 'document_id',
        'payroll' => 'payroll_id',
        'payroll_attendance' => 'payroll_attendance_id',
        'payroll_items' => 'payroll_item_id',
        'performance_cycles' => 'cycle_id',
        'performance_feedback' => 'feedback_id',
        'performance_goal_ratings' => 'rating_id',
        'performance_goals' => 'goal_id',
        'performance_reviews' => 'review_id',
        'positions' => 'position_id',
        'training_courses' => 'course_id',
        'training_enrollments' => 'enrollment_id',
        'users' => 'user_id',
    ];

    public int $affected_rows = 0;
    public int $insert_id = 0;
    public string $error = '';

    /** @var array<int, mixed> */
    private array $boundVariables = [];

    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    private ?PDOStatement $statement = null;

    public function __construct(private PDO $pdo, private string $sql)
    {
    }

    public function bind_param(string $types, &...$variables): bool
    {
        unset($types);
        $this->boundVariables = [];
        foreach ($variables as &$variable) {
            $this->boundVariables[] =& $variable;
        }

        return true;
    }

    public function execute(): bool
    {
        try {
            $sql = $this->sql;
            $returningColumn = null;

            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
                && preg_match('/^\s*INSERT\s+INTO\s+([a-z_][a-z0-9_]*)/i', $sql, $matches)
                && !preg_match('/\bRETURNING\b/i', $sql)) {
                $table = strtolower($matches[1]);
                $returningColumn = self::PRIMARY_KEYS[$table] ?? null;
                if ($returningColumn !== null) {
                    $sql .= " RETURNING {$returningColumn}";
                }
            } elseif ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
                && preg_match('/\bRETURNING\s+([a-z_][a-z0-9_]*)/i', $sql, $matches)) {
                $returningColumn = strtolower($matches[1]);
            }

            $this->statement = $this->pdo->prepare($sql);
            $parameters = array_map(static fn ($value) => $value, $this->boundVariables);
            $this->statement->execute($parameters);
            $this->affected_rows = $this->statement->rowCount();
            $this->rows = $this->statement->columnCount() > 0
                ? $this->statement->fetchAll()
                : [];

            if ($returningColumn !== null && isset($this->rows[0][$returningColumn])) {
                $this->insert_id = (int) $this->rows[0][$returningColumn];
            } elseif ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql'
                && preg_match('/^\s*INSERT\b/i', $sql)) {
                $lastId = $this->pdo->lastInsertId();
                $this->insert_id = ctype_digit((string) $lastId) ? (int) $lastId : 0;
            }

            return true;
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
            throw $exception;
        }
    }

    public function get_result(): MysqliCompatResult
    {
        return new MysqliCompatResult($this->rows);
    }

    public function close(): void
    {
        $this->statement = null;
        $this->rows = [];
    }
}

final class MysqliCompatResult
{
    public int $num_rows;
    private int $position = 0;

    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private array $rows)
    {
        $this->num_rows = count($rows);
    }

    /** @return array<string, mixed>|null */
    public function fetch_assoc(): ?array
    {
        if (!isset($this->rows[$this->position])) {
            return null;
        }

        return $this->rows[$this->position++];
    }

    /** @return array<int, array<string, mixed>> */
    public function fetch_all(int $mode = 0): array
    {
        unset($mode);
        return $this->rows;
    }

    public function free(): void
    {
        $this->rows = [];
        $this->num_rows = 0;
    }
}
