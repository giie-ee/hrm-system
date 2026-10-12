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
