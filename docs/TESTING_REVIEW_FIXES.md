# Testing review fixes - backend notes

Covers migration `005_review_fixes_onboarding_progress.sql` and the endpoints it
adds. All POST bodies are JSON and need the `X-CSRF-Token` header, as elsewhere.

## Attendance
- Default schedule: Mon-Thu 09:00-17:00, Fri/Sat/Sun 09:00-13:00 (half days),
  15 minute grace, so check-ins up to 09:15 are on time.
- New statuses: `Present (Half Day)` and `Late (Half Day)` (a day is a half day
  when its expected hours are not above `minimum_half_day_hours`). The policy
  response now includes `is_half_day`.
- Approved leave is marked `On Leave` after that employee's configured workday
  end time. Half days and later Admin schedule changes are respected. There is
  no scheduler on the host, so the sync runs when attendance is read
  (`attendance/get.php`) or someone checks in; it also back-fills 30 days.

## Documents
Every active employee has an onboarding record with four document requests
(National ID / NRC copy, Proof of address, Bank account details, Academic
certificates); new employees get them automatically. These fill the
"Choose a request" dropdown (`onboarding/get.php` -> `documents`).

## Onboarding form (employee submits, HR/Admin approves)
- `GET  /api/onboarding/form-get.php[?employee_id=&status=]` - returns `fields`
  (required + max length) and `forms[]` (`form_id`, `form_status` =
  Pending|Submitted|Approved|Rejected, redacted `form_data`,
  `rejection_reason`). Employees see their own and HR/Admin see everyone.
  Managers cannot read assigned employees' personal forms, but all account
  roles can read and submit their own form.
- `POST /api/onboarding/form-submit.php` - employee. Fields: `phone`, `address`,
  `emergency_contact_name`, `emergency_contact_phone`,
  `emergency_contact_relationship`, `bank_name`, `bank_account_number`
  (required); `tax_number`, `national_id`, `next_of_kin_name` (optional).
  Bank name and account number are encrypted with AES-256-GCM and removed from
  ordinary JSON before storage. They are never returned by the form-list API.
  Allowed when no form exists or it was Rejected.
- `POST /api/onboarding/form-review.php` - HR/Admin only:
  `{form_id, status: Approved|Rejected,
  reason}` (reason required when rejecting). Approval copies phone, address and
  national ID to the employee record. Reviewers cannot review their own form.
- `POST /api/onboarding/form-banking.php` - HR/Admin only: `{form_id, reason}`.
  This is the only authorized plaintext read path. It reveals one form at a
  time, rejects self-access and records a value-free audit entry. The frontend
  hides the response after 60 seconds.

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
- The frontend now creates records, lists milestones, completes/reopens
  milestones, adds milestones, and cancels/reopens records through these APIs.

## Banking encryption configuration
- Set `HRMS_BANKING_ACTIVE_KEY_ID` to the ID used for new writes.
- Set `HRMS_BANKING_KEYS_JSON` to a protected JSON keyring whose values are
  base64-encoded 32-byte keys. Do not commit or paste the real value into docs.
- `bin/encrypt-onboarding-bank-data.php` backfills legacy plaintext and validates
  the database constraint. It authenticates every stored envelope, so removing
  an old rotation key also stops startup before data becomes silently unreadable.
  Container startup fails closed if keys or legacy data are invalid.
- Scope note: form fields are protected; uploaded banking-document files remain
  outside the web root but need separate at-rest encryption/object-storage work.

## Other
- `national_id` is free text (e.g. `123456/78/1`); it is no longer rejected by
  the generic `*_id` integer check in `bootstrap.php`.
