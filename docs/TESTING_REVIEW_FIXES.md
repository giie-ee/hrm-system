# Testing review fixes - backend notes

Covers migration `005_review_fixes_onboarding_progress.sql` and the endpoints it
adds. All POST bodies are JSON and need the `X-CSRF-Token` header, as elsewhere.

## Attendance
- Default schedule: Mon-Thu 09:00-17:00, Fri/Sat/Sun 09:00-13:00 (half days),
  15 minute grace, so check-ins up to 09:15 are on time.
- New statuses: `Present (Half Day)` and `Late (Half Day)` (a day is a half day
  when its expected hours are not above `minimum_half_day_hours`). The policy
  response now includes `is_half_day`.
- Approved leave is marked `On Leave` automatically after 17:00 (policy time).
  There is no scheduler on the host, so the sync runs when attendance is read
  (`attendance/get.php`) or someone checks in; it also back-fills 30 days.

## Documents
Every active employee has an onboarding record with four document requests
(National ID / NRC copy, Proof of address, Bank account details, Academic
certificates); new employees get them automatically. These fill the
"Choose a request" dropdown (`onboarding/get.php` -> `documents`).

## Onboarding form (employee submits, HR/Admin/Manager approves)
- `GET  /api/onboarding/form-get.php[?employee_id=&status=]` - returns `fields`
  (required + max length) and `forms[]` (`form_id`, `form_status` =
  Pending|Submitted|Approved|Rejected, `form_data`, `rejection_reason`). Employees
  see their own, Managers their assigned team, HR/Admin everyone.
- `POST /api/onboarding/form-submit.php` - employee. Fields: `phone`, `address`,
  `emergency_contact_name`, `emergency_contact_phone`,
  `emergency_contact_relationship`, `bank_name`, `bank_account_number`
  (required); `tax_number`, `national_id`, `next_of_kin_name` (optional).
  Allowed when no form exists or it was Rejected.
- `POST /api/onboarding/form-review.php` - `{form_id, status: Approved|Rejected,
  reason}` (reason required when rejecting). Approval copies phone, address and
  national ID to the employee record. Reviewers cannot review their own form.

## Progress tracker
- `GET  /api/progress/get.php[?employee_id=&status=]` - records with
  `milestones[]`, `milestones_completed`, `milestones_total`,
  `progress_percentage`. Employees see their own (read-only).
- `POST /api/progress/create.php` - Admin/HR/Manager: `{employee_id, title,
  description?, due_date?, milestones: ["a","b",...]}` (2-20 milestones).
- `POST /api/progress/update.php` - `{record_id, title?, description?, due_date?,
  status?: "Cancelled"|"In Progress"}`.
- `POST /api/progress/add-milestone.php` - `{record_id, title}`.
- `POST /api/progress/complete-milestone.php` - `{record_id, milestone_id,
  is_complete?: true|false}`. The record becomes `Completed` when every
  milestone is complete and returns to `In Progress` if one is reopened.
- Managers can only manage employees assigned to them (`manager_assignments`).

## Other
- `national_id` is free text (e.g. `123456/78/1`); it is no longer rejected by
  the generic `*_id` integer check in `bootstrap.php`.
