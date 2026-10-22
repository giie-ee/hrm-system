-- Manual rollback for 010_account_password_change.sql. Not in database/postgresql
-- on purpose (the runner applies every .sql file there). Redeploy the previous
-- code first; the old code ignores the column.
BEGIN;
ALTER TABLE users DROP COLUMN IF EXISTS must_change_password;
DELETE FROM schema_migrations WHERE version = '010_account_password_change.sql';
COMMIT;
