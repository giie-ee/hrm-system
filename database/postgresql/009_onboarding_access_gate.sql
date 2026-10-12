-- Two-level employee access: an Employee whose onboarding record restricts
-- access can only reach the onboarding-related endpoints until HR/Admin marks
-- the onboarding Completed. Existing records stay unrestricted so current
-- employees are not locked out; only new hires (created through the employee
-- form) or records HR explicitly restricts are gated.

ALTER TABLE onboarding
    ADD COLUMN IF NOT EXISTS restricts_access BOOLEAN NOT NULL DEFAULT FALSE;

CREATE INDEX IF NOT EXISTS idx_onboarding_restricting
    ON onboarding(employee_id)
    WHERE restricts_access AND onboarding_status <> 'Completed';
