# Render deployment

The repository now deploys as one Docker web service:

```text
Browser -> React application -> /api/*.php -> PostgreSQL
```

The Docker build compiles the React frontend, serves it through Apache, keeps
the existing PHP API paths, runs pending migrations at startup, and exposes a
database-aware health check at `/api/health.php`.

## Why the current deployment only says "HRMS is working!"

That sentence comes directly from `hrms/index.php`. The older Dockerfile copied
only the `hrms/` PHP directory into Apache, so that placeholder became the home
page and the React frontend was never built. Database configuration cannot
change that page.

The new multi-stage Dockerfile first builds `frontend/`, then copies the React
`dist/` files over the Apache document root while retaining `/api/*.php`. The
new code must be committed, pushed to the branch Render uses, and deployed
before the frontend can replace the placeholder. If the service was connected
using a public repository URL instead of the GitHub integration, manually
deploy the latest commit after each push.

## First Neon synchronization, step by step

### 1. Put the deployment changes on GitHub

1. Review the local changes with the team.
2. Have a repository collaborator, such as Agatha, commit and push them to the
   branch used by Render. The current local changes have not been pushed.
3. In Render, open the HRMS service's **Settings** page and confirm:
   - Branch is the branch that received the commit, normally `main`.
   - Runtime is Docker.
   - Root Directory is blank unless the whole repository is intentionally
     nested elsewhere.
   - Dockerfile Path is `./Dockerfile`.

### 2. Create the empty Neon database

1. Sign in to Neon and select **New project**.
2. Name it `hrms` or another clear team name.
3. Choose a Neon region reasonably close to the Render service's region.
4. Keep the default branch and database, or name the database `hrms`.
5. Open the project dashboard and select **Connect**.
6. Select the database and owner role, choose the direct connection for this
   first migration, and copy the PostgreSQL URL. It should resemble:

   ```text
   postgresql://USER:PASSWORD@HOST/DATABASE?sslmode=require
   ```

Never place the real URL in source code, screenshots, reports or team chat.

### 3. Connect Render to Neon

1. Open the existing HRMS web service in Render.
2. Select **Environment** and add or update:

   ```dotenv
   APP_ENV=production
   DB_CONNECTION=pgsql
   DATABASE_URL=<paste the private Neon URL here>
   DB_SSLMODE=require
   MIGRATE_ON_START=1
   SESSION_COOKIE_SECURE=1
   SEED_ADMIN_ON_START=0
   ```

3. Save the environment values. Do not add the Neon URL to `render.yaml`.
4. Leave the old MySQL variables in place until the first PostgreSQL deployment
   succeeds; the application will prefer `DATABASE_URL`. Remove the MySQL
   variables after verification to prevent future confusion.

### 4. Deploy the updated Docker image

1. Open **Deploys** in the Render service.
2. If Render is linked to Agatha's GitHub account, push/merge may trigger the
   deploy automatically.
3. If Render uses the public-repository URL, choose **Manual Deploy** and
   **Deploy latest commit**. For the first frontend-enabled build, **Clear build
   cache & deploy** is also reasonable.
4. Watch the logs. A successful first database startup should include:

   ```text
   Applied: 001_initial_schema.sql
   Applied: 002_complete_hrms_workflows.sql
   Database migrations are up to date.
   ```

   Later deploys should show both migrations as `Already applied` instead of
   creating duplicate tables.

### 5. Verify application and database synchronization

1. Open `https://<your-service>.onrender.com/api/health.php` and confirm:

   ```json
   {"status":"ok","service":"hrms","database":"connected"}
   ```

2. Open the service root URL. It should display Agatha's React login interface,
   not the PHP placeholder.
3. In Neon's **Tables** view or SQL editor, confirm 35 application tables plus
   the migration ledger. The added groups include departments/positions,
   benefits, onboarding/documents, performance, recruitment, training,
   notifications, announcements and audit history.

4. Run these read-only checks in the Neon SQL editor:

   ```sql
   SELECT version, applied_at FROM schema_migrations ORDER BY version;
   SELECT role_id, role_name FROM roles ORDER BY role_id;
   SELECT COUNT(*) AS application_tables
   FROM information_schema.tables
   WHERE table_schema = 'public'
     AND table_name <> 'schema_migrations';
   ```

   The first query should list migrations 001 and 002, the second should list
   Admin, HR, Manager and Employee, and the final count should be 35.

### 6. Create and test the first administrator

Follow the protected seed process below, then verify login. After login, test
one read from Employees, Attendance, Leave, Payroll, Benefits and Onboarding
before describing the hosted integration as complete.

## Blueprint alternative

The root `render.yaml` can create/configure the `hrms` web service and requests
the Neon connection URL without storing it in Git. In Render:

1. Open the Blueprint already connected to this repository, or create a new
   Blueprint from the repository.
2. Review the proposed resources before applying. Do not create a second web
   service if the existing `hrms` service is already managed by a different
   Blueprint.
3. In Neon, create an empty project/database and copy its direct PostgreSQL
   connection URL. Use the direct connection for the initial integration; the
   pooler can be tested separately later.
4. Apply the Blueprint. When Render asks for `DATABASE_URL`, paste the Neon URL
   there. Never add it to `render.yaml` or Git.
5. Deploy the commit containing these changes.
6. Open `https://<your-service>.onrender.com/api/health.php`. It should return
   `status: ok` and `database: connected`.

## Keep the existing manually-created Render service

If the current service was created manually and you do not want to convert it
to a Blueprint:

1. Create an empty Neon PostgreSQL project/database.
2. Copy its direct PostgreSQL connection URL.
3. In the web service's Environment page set:

   - `APP_ENV=production`
   - `DB_CONNECTION=pgsql`
   - `DATABASE_URL=<Neon direct connection URL>`
   - `DB_SSLMODE=require`
   - `MIGRATE_ON_START=1`
   - `SESSION_COOKIE_SECURE=1`

4. Remove the old MySQL `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, and
   `DB_DATABASE` variables after the PostgreSQL deployment is verified.
5. Redeploy and check `/api/health.php`.

Do not commit the Neon URL or password. Store it only in Render's Environment
page and in a private local environment when database administration is needed.

## Create the first administrator safely

There is no public account-creation endpoint. On a paid service you can run the
following through a Render Shell. On a free service, use the one-start workflow
below.

Set these temporary environment variables on the web service:

```dotenv
SEED_ADMIN_ON_START=1
ALLOW_PRODUCTION_SEED=1
SEED_ADMIN_EMPLOYEE_NUMBER=EMP-001
SEED_ADMIN_FIRST_NAME=YourFirstName
SEED_ADMIN_LAST_NAME=YourLastName
SEED_ADMIN_USERNAME=admin
SEED_ADMIN_EMAIL=you@example.com
SEED_ADMIN_PASSWORD=use-a-long-unique-password
```

Deploy once. The startup process creates the employee and administrator only if
they do not already exist. Immediately remove all `SEED_ADMIN_*` variables and
`ALLOW_PRODUCTION_SEED`, set `SEED_ADMIN_ON_START=0`, and deploy again.

The password is hashed, the script refuses passwords shorter than 12
characters, it does not overwrite an existing account, and production seeding
requires the explicit one-time opt-in.

## Optional four-role dashboard test accounts

For a controlled demonstration, `bin/seed-dashboard-users.php` can create one
Admin, HR, Manager and Employee account after both migrations have run. The
command is CLI-only, requires `ALLOW_DASHBOARD_TEST_SEED=1`, and reads all four
passwords from environment variables. Each password must have at least 12
characters with uppercase, lowercase, number and symbol characters.

When the command targets the production Neon database, it additionally requires
`ALLOW_PRODUCTION_SEED=1`. Remove both permission flags and all four password
variables immediately after the command. Do not leave shared demonstration
accounts enabled on a public service after testing is complete.

## Schema growth

`001_initial_schema.sql` is the deployed core and remains unchanged.
`002_complete_hrms_workflows.sql` additively ports Kamuti's confirmed backend
tables and the relationships supported by the supplied diagrams. Keep both
files immutable after Neon records them. Add future changes as migration 003 or
later; never rewrite an already-applied production migration.

Document files need separate persistence planning. The database stores document
metadata, but ordinary Render container storage is ephemeral. Use a Render
persistent disk on a supported plan or object storage before treating uploaded
files as durable production records.
