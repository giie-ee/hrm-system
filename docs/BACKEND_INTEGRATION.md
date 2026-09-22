# Kamuti backend integration

## Evidence used

This integration compared three separate sources:

1. the public `del-gie/hrm-system` `main` branch at `f4ba202`;
2. the supplied `HRMS_Backend_Project` source, migration, tests and project
   defense report; and
3. the supplied ERD and use-case diagrams.

Code is treated as implementation evidence. The report describes Kamuti's
claimed scope and local MySQL results. The diagrams describe intended scope;
they are not treated as proof that a route or rule was implemented.

## What was confirmed in Kamuti's delivery

The package contains PHP endpoints and shared workflow modules for accounts,
employee administration, manager assignments, salaries, benefits, onboarding,
private documents, performance, recruitment, training, analytics,
notifications, announcements and audit history. It also hardens authentication,
leave, attendance and payroll behavior.

Its recorded evidence reports 554 local integration checks and 235 supplemental
checks against a copied MariaDB database. Those results are useful evidence for
the original MySQL package, but they do not prove this PostgreSQL/Render merge
works against Neon. Live Neon workflow testing remains required after deploy.

## PostgreSQL schema

Migration `001_initial_schema.sql` remains unchanged because it may already be
recorded in Neon. Migration `002_complete_hrms_workflows.sql` is additive and
brings the database to 35 application tables.

### Core and organization

- `roles`, `users`, `employees`
- `departments`, `positions`, `manager_assignments`
- `employee_salaries`

Existing employee rows are preserved. Newly added department, position and
profile columns are nullable for those rows; new employee creation validates
the complete organizational fields.

### Time, leave and payroll

- `attendance`
- `leave_types`, `leave_balances`, `leave_requests`
- `payroll`, `payroll_items`, `payroll_attendance`

### Employee lifecycle

- `benefits`, `employee_benefits`, `benefit_history`
- `onboarding`, `onboarding_documents`, `document_history`
- `training_courses`, `training_enrollments`
- `performance_cycles`, `performance_goals`, `performance_reviews`
- `performance_goal_ratings`, `performance_feedback`

### Recruitment and communication

- `job_vacancies`, `applicants`, `job_applications`, `interviews`
- `notifications`, `announcements`, `audit_logs`, `login_attempts`

This maps the diagrams' Department, Position, Employee, Applicant, Application,
Attendance, Leave, Payroll, Benefit, Training and Performance concepts into the
actual API contracts. Extra history, security and workflow tables come from the
backend code rather than the diagrams.

## PostgreSQL adaptations

- MySQL `AUTO_INCREMENT` became PostgreSQL identity columns.
- `ENUM` rules became constrained text fields.
- `INSERT IGNORE` and `ON DUPLICATE KEY` became `ON CONFLICT` operations.
- MySQL date functions and boolean sums became PostgreSQL `CURRENT_DATE`,
  `EXTRACT`, `TO_CHAR` and `CASE` expressions.
- Insert identity handling was expanded in the PDO compatibility adapter.
- Login now returns a CSRF token; the React client sends it on mutations.
- The publicly reachable `setup/create_users.php` was not imported. The only
  administrator seed remains the CLI-only `php bin/seed-admin.php` command.

## Frontend integration

Benefits, Onboarding, Documents and Progress Tracker no longer claim that the
backend is unavailable. They read authenticated data from the new endpoints.
The Documents screen can submit requested PDF/PNG/JPEG files and download
verified records. Progress is derived from database records and does not invent
completion values for missing data.

The package contains more administrative APIs than the current React navigation
exposes. Recruitment, training, performance-cycle administration, user/role
management, configuration and analytics still need dedicated management forms
if the team wants every endpoint available through the browser.

## Render and Neon behavior

The existing Render startup command runs `php bin/migrate.php`, so a deployment
of this branch applies migration 002 once and records it in
`schema_migrations`. Do not paste the MySQL migrations into Neon.

The `DATABASE_URL` remains the only required Neon secret. Document files are
stored outside Apache's public directory, but Render's ordinary container
filesystem is ephemeral. Use a persistent disk or object storage before relying
on uploads as durable production records.

## Verification performed locally

- PHP syntax: 128 files passed.
- React lint: passed.
- React production build: passed.
- MySQL-only SQL patterns in the imported paths were converted to PostgreSQL.

No live Neon password was present in the local environment, so migration 002
was not executed against the hosted database during this integration. The next
acceptance step is to deploy, confirm the migration log, then test one workflow
for each role against a disposable or backed-up Neon database.
