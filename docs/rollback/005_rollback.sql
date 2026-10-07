-- Manual rollback for 005_review_fixes_onboarding_progress.sql.
-- NOT in database/postgresql on purpose: the migration runner applies every
-- .sql file in that folder. Run this by hand (e.g. Neon SQL editor) only if
-- migration 005 must be undone. Redeploy the previous code first (git tag
-- backup/pre-005) so nothing writes to the tables dropped below.

BEGIN;

-- New tables (discards all onboarding forms and progress records).
DROP TABLE IF EXISTS progress_milestones;
DROP TABLE IF EXISTS progress_records;
DROP TABLE IF EXISTS onboarding_forms;

-- Restore the original schedule: Mon-Fri 08:00-17:00, weekend off, 10 min grace.
UPDATE work_policies SET grace_minutes = 10 WHERE policy_name = 'Default Work Schedule';
UPDATE work_policy_days wpd
SET is_working_day = (wpd.day_of_week BETWEEN 1 AND 5),
    start_time = CASE WHEN wpd.day_of_week BETWEEN 1 AND 5 THEN '08:00'::TIME END,
    end_time = CASE WHEN wpd.day_of_week BETWEEN 1 AND 5 THEN '17:00'::TIME END,
    expected_hours = CASE WHEN wpd.day_of_week BETWEEN 1 AND 5 THEN 8 ELSE 0 END
FROM work_policies wp
WHERE wp.work_policy_id = wpd.work_policy_id AND wp.policy_name = 'Default Work Schedule';

-- Optional: remove rows the new attendance code auto-created from approved leave,
-- and half-day statuses the old code does not understand.
-- DELETE FROM attendance WHERE attendance_source = 'System' AND status = 'On Leave';
-- UPDATE attendance SET status = 'Present' WHERE status = 'Present (Half Day)';
-- UPDATE attendance SET status = 'Late' WHERE status = 'Late (Half Day)';

-- Allow the migration to be applied again later.
DELETE FROM schema_migrations WHERE version = '005_review_fixes_onboarding_progress.sql';

COMMIT;
-- Onboarding records and document requests added by 005 are harmless and are kept.
