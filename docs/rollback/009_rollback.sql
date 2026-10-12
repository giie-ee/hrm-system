-- Manual rollback for 009_onboarding_access_gate.sql. Not in database/postgresql
-- on purpose (the runner applies every .sql file there). Redeploy the previous
-- code first; the old code ignores the column, so this is only for tidiness.
BEGIN;
DROP INDEX IF EXISTS idx_onboarding_restricting;
ALTER TABLE onboarding DROP COLUMN IF EXISTS restricts_access;
DELETE FROM schema_migrations WHERE version = '009_onboarding_access_gate.sql';
COMMIT;
