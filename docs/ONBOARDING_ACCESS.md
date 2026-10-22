# Two-level employee access (onboarding gate)

New hires can sign in straight away, but until HR/Admin marks their onboarding
**Completed** they only reach the onboarding-related parts of the system. Once it
is completed the account is unlocked immediately (no re-login needed).

## How it works
- `onboarding.restricts_access` (migration `009`) marks a record as access-gating.
  Employees created through the employee form get it automatically. Existing
  employees are **not** restricted. HR can set it either way with
  `restrict_access` on `onboarding/create.php` / `onboarding/update.php`
  (`false` lifts the restriction early, e.g. for an urgent start).
- The gate applies to the **Employee** role only. Admin, HR and Manager are never gated.
- It is enforced centrally in `requireLogin()` (`hrms/includes/auth.php`) with a
  default-deny allowlist in `hrms/lib/OnboardingAccess.php`, so new endpoints are
  restricted automatically.

## What a not-yet-onboarded employee can use
`auth/*`, `onboarding/*` (record, documents list, information form), `documents/*`
(upload/download requested files), `notifications/*`, `attendance/*` (day-one
check-in and their own history), `self-service/profile|update-profile|onboarding|attendance`,
`announcements/get`, `configuration/get`, `configuration/work-policy`, `health`.

Everything else returns **403** with
`{"success":false,"message":"Complete your onboarding to access this feature.","code":"ONBOARDING_REQUIRED"}`
- leave, payroll/payslips/salary, benefits, performance, training, progress tracker,
the employee directory, manager/analytics/recruitment/user admin, etc.

## Completing onboarding
HR/Admin can set `Completed` once every document request is **Verified** and, for
restricting records, the information form is **Approved**. The first document or
form submission moves `Not Started` to `In Progress` automatically, and HR gets an
"Onboarding ready for completion" notification when the last requirement is met.
`onboarding/get.php` now returns `restricts_access` and a `requirements` object
(`documents_total`, `documents_verified`, `form_required`, `form_status`,
`ready_to_complete`).

## For the frontend
- `auth/login.php` and `auth/me.php` return `user.onboarding_required` (boolean).
  Re-read `me.php` on dashboard load / after onboarding changes, because the value
  in `localStorage` goes stale once HR completes the onboarding.
- When `onboarding_required` is true: show a "finish your onboarding" banner linking
  to the Onboarding page, and hide or disable the restricted dashboard tiles/routes.
- Any API call can still return 403 with `code: "ONBOARDING_REQUIRED"`; treat it as
  "redirect to the Onboarding page", not as a generic error.

## Rollback
`docs/rollback/009_rollback.sql` (manual). The old code ignores the column.

## Accounts created at hire (temporary password)
`POST /api/employees/create.php` accepts, in addition to the employee fields:
- `basic_salary` (optional, e.g. `"7500.00"`) and `salary_effective_from` (defaults to the hire date) - creates the first salary row.
- `create_account: true` and optional `username` (defaults to `first.last`, a number is appended if taken) - creates an Employee login with a generated 14 character **temporary password** and `must_change_password = true`. The response contains `account: {user_id, username, temporary_password}`; **this is the only time the password is shown** and it is never written to the audit log. Everything happens in one transaction, so a failure (for example a taken username) creates nothing.

Until the employee sets their own password, every endpoint except `api/auth/*` returns
`403 {"code": "PASSWORD_CHANGE_REQUIRED"}`. `auth/login.php` and `auth/me.php` return
`user.must_change_password`. `POST /api/auth/change-password.php`
`{current_password, new_password}` clears the flag, ends the user's other sessions
(`auth_version`) and keeps the current one; it is limited to 5 wrong attempts per 15 minutes.
`users/create.php` and `users/reset-password.php` accept an optional
`must_change_password` (default `false`, so existing admin flows are unchanged).
Migration `010`, rollback `docs/rollback/010_rollback.sql`.

Frontend: login goes to `/change-password` when the flag is set; the router, onboarding
banner, locked dashboard tiles, completion panel for HR and the "create account" /
salary fields on the employee form are in `frontend/src` (`api/session.js`,
`pages/ChangePasswordPage.jsx`, `components/OnboardingStatus.jsx`).
