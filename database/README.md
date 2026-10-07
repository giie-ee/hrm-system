# Database

PostgreSQL is the deployment database and the source of truth for new schema
changes. `postgresql/001_initial_schema.sql` creates the original deployed core.
`postgresql/002_complete_hrms_workflows.sql` additively ports the confirmed
Kamuti backend and ERD relationships without dropping existing Neon records.
`postgresql/003_nicholas_payroll_work_hours.sql` adds the configurable work
schedule, attendance snapshots, reusable payroll components, department-safe
employee defaults and baseline leave-type names identified during role testing.
`postgresql/004_seed_salaries_and_form_options.sql` seeds demo leave entitlements,
current-year leave balances, departments, positions, benefits and placeholder
salaries so forms such as leave requests can be submitted. Replace the figures
with approved values before production use.
`postgresql/005_review_fixes_onboarding_progress.sql` applies the testing-review
fixes: attendance schedule (09:15 cut-off, half-day Fri-Sun), default onboarding
document requests, and tables for the onboarding form and progress tracker. See
`docs/TESTING_REVIEW_FIXES.md`.
`postgresql/006_encrypt_onboarding_banking.sql` adds the encrypted banking
envelope and a database constraint that prevents ordinary onboarding JSON from
containing plaintext bank names or account numbers. The startup backfill command
converts any legacy rows before Apache starts.
`postgresql/007_nicholas_leave_salary_policy.sql` adds editable leave request
limits and separate position-based salary guidelines without overwriting active
employee salary records. See `docs/NICHOLAS_LEAVE_SALARY_POLICY.md`.

The second migration expands the core employee model and adds departments,
positions, manager assignments, benefits, onboarding/documents, performance,
recruitment, training, notifications, announcements and audit history. Future
changes must continue as `004_...sql`, `005_...sql`, and so on; never rewrite a
migration already recorded on Neon.

Run all pending migrations from the repository root:

```sh
php bin/migrate.php
```

To create the four optional dashboard test accounts after migration, use the
CLI-only `bin/seed-dashboard-users.php` command. It requires the explicit
`ALLOW_DASHBOARD_TEST_SEED=1` flag and four strong passwords supplied through
environment variables. It does not expose a browser-accessible setup route.

The runner records applied filenames in `schema_migrations`, applies each new
PostgreSQL migration in a transaction, and is safe to run again. Add later
changes as new numbered files; do not rewrite an applied migration on a live
database.

After running the migrations, confirm that the newest file was recorded:

```sql
SELECT version, applied_at
FROM schema_migrations
ORDER BY applied_at;
```

The baseline leave types deliberately have zero entitlement. Admin/HR must set
the organisation's approved leave policy and allocate balances before employees
can submit leave successfully. The default Monday-Friday work schedule is also
a starting value only; an Admin must confirm it against company policy.

The old MySQL schema remains under `hrms/migrations` only as a local
compatibility path. Set `DB_CONNECTION=mysql` if the team temporarily needs to
run the old XAMPP setup. Production and all new development should use
PostgreSQL.

## Data migration boundary

These migrations create the HRMS structure but do not copy rows from an
existing MySQL database. If the current hosted service contains real data,
export and validate that data before switching its `DATABASE_URL`. Do not point
the deployed service at an empty PostgreSQL database and assume MySQL records
were transferred.
