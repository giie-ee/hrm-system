# Render deployment

The repository now deploys as one Docker web service:

```text
Browser -> React application -> /api/*.php -> PostgreSQL
```

The Docker build compiles the React frontend, serves it through Apache, keeps
the existing PHP API paths, runs pending migrations at startup, and exposes a
database-aware health check at `/api/health.php`.

## Recommended setup: Render plus Neon

The root `render.yaml` defines the `hrms` web service and requests the Neon
connection URL without storing it in Git. In Render:

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

## Schema growth

`001_initial_schema.sql` creates only the tables required by the backend code
currently in this repository: roles, employees, users, attendance, leave and
payroll. It deliberately does not invent database structures for frontend-only
modules or Kamuti's unavailable code. When a module gains a confirmed API and
data model, add a new migration such as `002_documents.sql`; never rewrite an
already-applied production migration.
