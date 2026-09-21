# Database

PostgreSQL is the deployment database and the source of truth for new schema
changes. The initial schema is in `postgresql/001_initial_schema.sql`.

That first migration covers only tables referenced by the backend code present
in this repository. Missing teammate code and frontend-only modules are not a
sound basis for inventing production tables. Add each confirmed module through
a new migration (`002_...sql`, `003_...sql`, and so on) as its API contract is
agreed.

Run all pending migrations from the repository root:

```sh
php bin/migrate.php
```

The runner records applied filenames in `schema_migrations`, applies each new
PostgreSQL migration in a transaction, and is safe to run again. Add later
changes as new numbered files; do not rewrite an applied migration on a live
database.

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
