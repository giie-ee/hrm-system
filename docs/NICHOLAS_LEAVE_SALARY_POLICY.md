# Nicholas weekly delivery: leave and salary demonstration policy

## Purpose and boundary

This delivery gives the HRMS clear numbers for demonstrations and testing. The
figures are proposals, not legal, tax, payroll or employment advice. Before a
real organisation uses them, Admin/HR must approve the policy and verify it
against current Zambian requirements and the organisation's contracts.

Migration `007_nicholas_leave_salary_policy.sql` does not overwrite active
employee salary records. It creates separate position guidelines so the Payroll
screen can compare the current employee salary with the proposal. HR still owns
the decision to create or change an employee salary record.

## Proposed leave policy

| Leave type | Annual days | Requests per year | Maximum consecutive days |
|---|---:|---:|---:|
| Annual Leave | 24 | 3 | 10 |
| Sick Leave | 14 | 6 | 7 |
| Compassionate Leave | 5 | 2 | 5 |
| Maternity Leave | 90 | 1 | 90 |
| Paternity Leave | 7 | 1 | 7 |
| Study Leave | 10 | 2 | 5 |
| Unpaid Leave | 30 | 2 | 15 |

Only Pending and Approved requests consume the annual request limit. Rejected
and Cancelled requests do not. A request must still have sufficient remaining
days and must not overlap another Pending or Approved request.

The Leave page displays the policy for the selected leave type. The backend
rejects a request that exceeds its consecutive-day or annual request limit.

## Proposed monthly position salaries

All amounts are monthly basic salary guidelines in ZMW.

| Department | Position | Proposed monthly salary |
|---|---|---:|
| Human Resources | HR Manager | 18,500.00 |
| Human Resources | HR Officer | 11,500.00 |
| Finance | Finance Manager | 21,000.00 |
| Finance | Accountant | 13,500.00 |
| Information Technology | IT Manager | 23,000.00 |
| Information Technology | Software Developer | 16,500.00 |
| Information Technology | Systems Administrator | 14,500.00 |
| Operations | Operations Manager | 19,000.00 |
| Operations | Operations Officer | 10,500.00 |
| Sales & Marketing | Sales Manager | 18,000.00 |
| Sales & Marketing | Sales Executive | 10,000.00 |
| General | General Staff | 7,500.00 |

Fallback positions created during early migrations also receive guidelines:

| Position | Proposed monthly salary |
|---|---:|
| Human Resources Staff | 9,500.00 |
| Finance Staff | 10,500.00 |
| Information Technology Staff | 12,500.00 |
| Operations Staff | 9,000.00 |
| Sales & Marketing Staff | 8,500.00 |

The Payroll page shows both tables:

1. Position recommendations and the number of active employees in each.
2. Each active employee's current salary beside the position recommendation.

This exposes missing or outdated placeholder salaries without silently changing
payroll history.

## Bonus decision

A new bonus subsystem is not necessary yet. The existing Payroll Items workflow
already supports a one-time bonus safely:

- Type: `Allowance`
- Item name: `Performance Bonus`
- Amount: the approved ZMW amount
- Description: approval reference or performance period

Because the item belongs to one Draft payroll, it does not accidentally repeat
every month. A permanent recurring-bonus feature should wait until the team has
approved eligibility, authorization, taxation and reversal rules.

## Team integration notes

- Agatha's optional salary input should create the employee's actual salary; it
  must not modify the position guideline.
- Kamuti's employee-create transaction should insert that actual salary only
  when a value is supplied.
- Existing employee salaries should be corrected through the Payroll page so
  overlapping salary periods remain controlled.
- Admin/HR may adjust individual leave balances when a contract differs from the
  demonstration default.

## Retest checklist

1. Select every leave type and confirm its days, annual request limit and
   consecutive-day limit appear.
2. Submit a request at the consecutive limit and confirm it is accepted when
   the balance and overlap rules also pass.
3. Submit one above the limit and confirm it is rejected.
4. Reach the request-frequency limit using Pending or Approved requests and
   confirm the next request is rejected.
5. Cancel or reject a request and confirm it no longer consumes a request slot.
6. As Admin/HR, open Payroll and confirm all configured positions appear.
7. Confirm current employee salaries are shown separately from recommendations.
8. Confirm changing an employee salary through Payroll does not modify the
   position guideline.
