-- Add software-development career levels for the university demonstration.
-- IMPORTANT: every amount in this migration is an editable demonstration
-- guideline. It is not a statutory scale, employment contract, payroll result,
-- or mandatory salary policy. Existing employee salaries are not changed.

INSERT INTO positions (position_name, department_id, description)
SELECT level.position_name, d.department_id, level.description
FROM (VALUES
    (
        'Junior Software Developer',
        'Entry-level developer working with supervision on scoped implementation and support tasks.'
    ),
    (
        'Senior Software Developer',
        'Experienced developer responsible for system design, complex delivery and technical mentoring.'
    ),
    (
        'Lead Software Developer',
        'Technical lead responsible for architecture, delivery coordination and developer guidance.'
    )
) AS level(position_name, description)
JOIN departments d ON d.department_name = 'Information Technology'
ON CONFLICT (position_name) DO NOTHING;

-- Preserve any guideline already configured for a position. The migration only
-- supplies a default where no current active guideline exists.
INSERT INTO position_salary_guidelines (
    position_id,
    recommended_basic_salary,
    currency,
    effective_from,
    notes
)
SELECT p.position_id,
       level.monthly_salary,
       'ZMW',
       DATE '2026-10-08',
       'EDITABLE DEMONSTRATION GUIDELINE ONLY. Admin/HR can change or deactivate it; it is not a statutory, contractual or mandatory salary.'
FROM (VALUES
    ('Junior Software Developer', 9000.00::NUMERIC),
    ('Senior Software Developer', 20000.00::NUMERIC),
    ('Lead Software Developer', 25000.00::NUMERIC)
) AS level(position_name, monthly_salary)
JOIN positions p ON p.position_name = level.position_name
WHERE NOT EXISTS (
    SELECT 1
    FROM position_salary_guidelines existing
    WHERE existing.position_id = p.position_id
      AND existing.status = 'Active'
      AND existing.effective_to IS NULL
)
ON CONFLICT (position_id, effective_from) DO NOTHING;

-- Strengthen the disclaimer on earlier seeded guidelines, but do not replace
-- notes that an organisation has already edited.
UPDATE position_salary_guidelines g
SET notes = 'EDITABLE DEMONSTRATION GUIDELINE ONLY. Admin/HR can change or deactivate it; it is not a statutory, contractual or mandatory salary.'
FROM positions p
WHERE p.position_id = g.position_id
  AND p.position_name IN ('Software Developer', 'HR Officer', 'HR Manager')
  AND g.notes = 'Demonstration monthly basic-salary guideline; HR must approve the employee salary record.';
