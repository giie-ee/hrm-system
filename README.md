# HRMS

PHP/MySQL human-resource management system.

## Database configuration

The application reads these environment variables. When none are set, it retains
the existing local defaults: `localhost`, port `3306`, username `root`, empty
password, and database `hrms_db`.

| Variable | Purpose |
| --- | --- |
| `DB_HOST` | MySQL host name (for a Render private MySQL service, its internal host) |
| `DB_PORT` | MySQL port; normally `3306` |
| `DB_USERNAME` | Application database user |
| `DB_PASSWORD` | Application database password |
| `DB_DATABASE` | Database name |

Create the database first if your provider does not create it for you, then run
the migration from the `hrms` directory:

```sh
php scripts/migrate.php
```

The migration is safe to re-run. It creates the tables used by the PHP code and
adds the four roles required by its authorization checks: Admin, HR, Manager,
and Employee. It does not invent employee accounts or production HR data.

## Creating the first administrator

The old browser-accessible account-creation page has been removed. Create an
employee first, then run this command from the `hrms` directory with secure,
temporary environment variables:

```sh
SEED_ADMIN_EMPLOYEE_ID=1 SEED_ADMIN_USERNAME=admin SEED_ADMIN_EMAIL=admin@example.com SEED_ADMIN_PASSWORD='choose-a-strong-password' php scripts/seed_admin.php
```

`seed_admin.php` only runs from the command line, stores a password hash, and
refuses to overwrite an existing account.

## Render deployment

`render.yaml` deploys this repository as a Docker web service. The container
uses Apache with PHP 8.3 and `mysqli`, and listens on Render's `PORT`.

1. Provision MySQL before deploying the application. A Render MySQL deployment
   should be a private service with a persistent disk mounted at
   `/var/lib/mysql`; set its `MYSQL_DATABASE`, `MYSQL_USER`,
   `MYSQL_PASSWORD`, and `MYSQL_ROOT_PASSWORD` values in Render.
2. Create the Blueprint from this repository, then enter the five `DB_*`
   values requested by `render.yaml`. For a Render-private MySQL service, use
   its internal hostname and port, not a public URL.
3. Open a Render Shell for the web service and run `php scripts/migrate.php`.
4. Create at least one employee row, then set the four `SEED_ADMIN_*` variables
   only for the shell command and run `php scripts/seed_admin.php`.

Do not place database passwords or seed passwords in Git or `render.yaml`.
