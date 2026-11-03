# Operations notes (Render)

## Environment variables the app needs
| Variable | Purpose | If missing |
|---|---|---|
| `DATABASE_URL`, `DB_CONNECTION=pgsql`, `DB_SSLMODE=require` | Database | App cannot start |
| `HRMS_BANKING_ACTIVE_KEY_ID` + `HRMS_BANKING_KEYS_JSON` | Encrypts onboarding bank details (`{"k1":"<base64 of 32 random bytes>"}`) | **Onboarding form submission fails** |
| `HRMS_CRON_TOKEN` (24+ random characters) | Authorises the scheduled leave sync below | Sync endpoint answers 503 |
| `SESSION_COOKIE_SECURE=1`, `CORS_ORIGINS` | Production cookie/CORS settings | Insecure cookies / blocked origins |

Generate a banking key with `php -r "echo base64_encode(random_bytes(32));"`. Keep the keys out of the repo and chat.

## "On Leave" attendance (no scheduler on the free tier)
Approved leave is marked `On Leave` automatically whenever attendance is read or an employee checks in,
so the data is correct when anyone looks at it. To also have it run on a timer, pick one:
1. **Free:** an external pinger (for example cron-job.org) calling, daily after closing time,
   `POST https://<service>/api/attendance/sync-leave.php` with header `X-Cron-Token: <HRMS_CRON_TOKEN>`.
   It returns `{"days_marked": n}` and is idempotent.
2. **Render Cron Job** (paid): same Docker image, command `php /app/bin/sync-leave-attendance.php`, same database environment variables.

## Deploying
- Auto-deploy only works if the service's repository is `giie-ee/hrm-system` (the repo was renamed from `del-gie`),
  the branch is `main`, and Auto-Deploy is *On Commit*. If pushes do not deploy, check
  Settings -> Build & Deploy, the GitHub webhook's Recent Deliveries, and the Render GitHub App's repository access;
  meanwhile use *Manual Deploy -> Deploy latest commit*.
- A redeploy restarts the container and signs everyone out.
- Migrations run at container start inside a transaction; a failing migration stops the start and Render keeps the previous version live.

## Rollback
Backup tags on GitHub mark the code before each change: `backup/pre-seed-004`, `backup/pre-005`, `backup/pre-009`, `backup/pre-010`.
`git revert <commit>` is the safe way to undo a push. Matching manual database rollbacks are in `docs/rollback/` (they are not run automatically).

## Is the latest version live?
Render keeps serving the old build until a deploy finishes, so check before testing. An endpoint that only exists in
the new build answers with JSON once deployed, and with the web app's HTML page before that:
`POST /api/auth/change-password.php` (added in commit d1e84a1) returns `{"success":false,"message":"Authentication required."}`
when deployed. Anything starting with `<!doctype html>` means the old build is still running.

If a push to `main` does not deploy, try merging through a GitHub pull request instead and note how long Render takes
to react; record the result here so the team knows which route works.
