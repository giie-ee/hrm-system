-- Development/demo reference data so employees have salaries and the form
-- dropdowns (leave types, departments, positions, benefits) offer real choices.
-- Additive and idempotent: only fills gaps and never overwrites configured values.
-- Amounts are placeholders in ZMW; Admin/HR must replace them with approved figures.

-- Leave types: sensible entitlements, only where still at the zero baseline.
INSERT INTO leave_types (leave_name, description, default_days, status)
VALUES
    ('Annual Leave', 'Planned paid leave.', 24, 'Active'),
    ('Sick Leave', 'Health-related absence.', 14, 'Active'),
    ('Compassionate Leave', 'Approved compassionate absence.', 5, 'Active'),
    ('Maternity Leave', 'Maternity absence.', 90, 'Active'),
    ('Paternity Leave', 'Paternity absence.', 7, 'Active'),
    ('Study Leave', 'Approved study or examination leave.', 10, 'Active'),
    ('Unpaid Leave', 'Approved leave without salary.', 30, 'Active')
ON CONFLICT (leave_name) DO NOTHING;

UPDATE leave_types
SET default_days = CASE leave_name
        WHEN 'Annual Leave' THEN 24
        WHEN 'Sick Leave' THEN 14
        WHEN 'Compassionate Leave' THEN 5
        WHEN 'Unpaid Leave' THEN 30
        ELSE default_days
    END
WHERE default_days = 0
  AND leave_name IN ('Annual Leave', 'Sick Leave', 'Compassionate Leave', 'Unpaid Leave');

-- Leave balances for the current year for every active employee.
INSERT INTO leave_balances (employee_id, leave_type_id, year, total_days, used_days, remaining_days)
SELECT e.employee_id, lt.leave_type_id, EXTRACT(YEAR FROM CURRENT_DATE)::SMALLINT,
       lt.default_days, 0, lt.default_days
FROM employees e
CROSS JOIN leave_types lt
WHERE e.employment_status = 'Active'
  AND lt.status = 'Active'
ON CONFLICT (employee_id, leave_type_id, year) DO NOTHING;

-- Departments and positions.
INSERT INTO departments (department_name, description, department_code)
VALUES
    ('Human Resources', 'People operations and administration.', 'HR'),
    ('Finance', 'Accounting, payroll and budgeting.', 'FIN'),
    ('Information Technology', 'Systems, support and development.', 'IT'),
    ('Operations', 'Day-to-day business operations.', 'OPS'),
    ('Sales & Marketing', 'Sales, marketing and customer relations.', 'SAL')
ON CONFLICT (department_name) DO NOTHING;

INSERT INTO positions (position_name, department_id, description)
SELECT v.position_name, d.department_id, v.description
FROM (VALUES
    ('HR Manager', 'Human Resources', 'Leads the HR function.'),
    ('HR Officer', 'Human Resources', 'Handles recruitment and employee records.'),
    ('Finance Manager', 'Finance', 'Leads the finance function.'),
    ('Accountant', 'Finance', 'Maintains accounts and payroll.'),
    ('IT Manager', 'Information Technology', 'Leads the IT function.'),
    ('Software Developer', 'Information Technology', 'Builds and maintains software.'),
    ('Systems Administrator', 'Information Technology', 'Maintains infrastructure.'),
    ('Operations Manager', 'Operations', 'Leads operations.'),
    ('Operations Officer', 'Operations', 'Supports daily operations.'),
    ('Sales Manager', 'Sales & Marketing', 'Leads the sales team.'),
    ('Sales Executive', 'Sales & Marketing', 'Manages client sales.')
) AS v(position_name, department_name, description)
JOIN departments d ON d.department_name = v.department_name
ON CONFLICT (position_name) DO NOTHING;

-- Benefits.
INSERT INTO benefits (benefit_name, description, benefit_type, provider, default_amount, status)
VALUES
    ('Medical Insurance', 'Employee health cover.', 'Health', NULL, 500, 'Active'),
    ('Life Insurance', 'Group life cover.', 'Insurance', NULL, 150, 'Active'),
    ('Pension Scheme', 'Retirement savings plan.', 'Retirement', NULL, 0, 'Active'),
    ('Lunch Allowance', 'Monthly meal support.', 'Allowance', NULL, 300, 'Active'),
    ('Airtime Allowance', 'Monthly communication support.', 'Allowance', NULL, 100, 'Active')
ON CONFLICT (benefit_name) DO NOTHING;

-- Salaries: one active salary per employee that has none, tiered by role.
INSERT INTO employee_salaries (employee_id, basic_salary, effective_from, salary_status, currency)
SELECT e.employee_id,
       CASE r.role_name
           WHEN 'Admin' THEN 15000
           WHEN 'HR' THEN 10000
           WHEN 'Manager' THEN 12000
           ELSE 6000
       END,
       COALESCE(e.hire_date, CURRENT_DATE),
       'Active',
       'ZMW'
FROM employees e
LEFT JOIN users u ON u.employee_id = e.employee_id
LEFT JOIN roles r ON r.role_id = u.role_id
WHERE e.employment_status = 'Active'
  AND NOT EXISTS (
      SELECT 1 FROM employee_salaries s
      WHERE s.employee_id = e.employee_id AND s.salary_status = 'Active'
  );
