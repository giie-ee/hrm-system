# HRMS

HRMS is a React and PHP human-resource management application backed by
PostgreSQL. The existing login, employees, attendance, leave and payroll API
paths are preserved while the deployment stack now serves the frontend and API
from one Render service.

## Project structure

```text
bin/                    CLI migration and safe administrator commands
database/               PostgreSQL schema and database handoff notes
docker/                 Apache and container startup configuration
docs/                   Deployment and team-facing documentation
frontend/               React/Vite application
hrms/api/               Existing PHP JSON endpoints
hrms/config/            Runtime database configuration
hrms/includes/          Authentication, session and CORS helpers
hrms/lib/               PDO database layer and legacy endpoint adapter
```

PostgreSQL is the source of truth. The old MySQL migration remains available
only to avoid suddenly breaking a teammate's existing local XAMPP setup.

## Local setup

1. Create an empty PostgreSQL database named `hrms`.
2. Copy `.env.example` to `.env` and enter your local credentials in the shell
   or development environment you use to start PHP.
3. Enable PHP extensions `pdo` and `pdo_pgsql`.
4. Run migrations from the repository root:

```sh
php bin/migrate.php
```

5. Start the PHP API from the backend directory and the Vite frontend in a
   second terminal:

```sh
php -S 127.0.0.1:8000 -t hrms
cd frontend
npm install
npm run dev
```

Vite proxies `/api` to the local PHP server. Production uses same-origin API
URLs, so no hosted URL is hardcoded into the frontend.

## Database configuration

Render supplies a single `DATABASE_URL`. Local development can instead use the
individual `DB_*` variables documented in `.env.example`. To temporarily use
the old MySQL development setup, set `DB_CONNECTION=mysql`; its fallback values
remain host `127.0.0.1`, port `3306`, user `root`, and an empty password.

## Deployment

`render.yaml` defines the Render Docker web service and prompts for the Neon
`DATABASE_URL`. The image builds React, installs `pdo_pgsql`, runs pending
migrations, serves both frontend and API through Apache, and checks database
readiness at `/api/health.php`.

Follow [the Render deployment guide](docs/RENDER_DEPLOYMENT.md), especially the
warning about transferring any existing MySQL data before switching a live
service. No database password or administrator seed password belongs in Git.

## Current scope

Login, employees, attendance, leave and payroll have PHP endpoints. Benefits,
documents, onboarding and progress tracking currently remain frontend-first
modules and still need database/API implementation before they can be described
as complete end-to-end features.
