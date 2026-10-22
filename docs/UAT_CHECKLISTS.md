# User acceptance checklists

Run against the deployed site with test accounts. Tick each line; note the date, the tester and anything unexpected.

## New hire (end to end)
- [ ] HR/Admin adds an employee with a salary and "Create a login account"; a username and a temporary password are shown once.
- [ ] The new hire signs in with the temporary password and is sent to "Set your own password".
- [ ] A weak password is refused with a clear message; a valid one works and lands on Onboarding.
- [ ] A banner says onboarding must be finished; Leave, Payslips, Benefits and Progress are greyed out; Attendance, Documents and Onboarding work.
- [ ] Typing a locked address (e.g. /leave) returns to Onboarding.
- [ ] The checklist shows documents 0 of 4 and the form as not submitted.
- [ ] The new hire submits the information form and uploads the four documents.
- [ ] HR sees the form and documents, verifies/approves them, gets an "ready for completion" notification, and the Complete onboarding button enables.
- [ ] After HR completes onboarding, the new hire (without signing in again) reaches Leave, Payroll and Benefits and the banner is gone.

## Employee (existing)
- [ ] Leave: dropdown lists all leave types; a request goes through; an overlapping or too-long one shows a clear message.
- [ ] Attendance: check-in before 09:15 is "Present", after is "Late"; Fri/Sat/Sun show "(Half Day)" variants; approved leave shows "On Leave".
- [ ] Documents: "Choose a request" lists pending requests; upload works.
- [ ] Payslips, benefits and progress records open and show their own data only.

## Manager
- [ ] Sees only assigned team members (attendance, leave, dashboard).
- [ ] Can create a progress record with 2+ milestones and tick them off; cannot do so for someone not assigned.

## HR
- [ ] Employee directory: add/edit employee, department then position dropdowns work, NRC with slashes saves.
- [ ] Onboarding: request documents from the dropdowns, verify/reject, approve/reject forms, complete onboarding.
- [ ] Payroll: components, salaries and a payroll run for a period with half-day attendance.
- [ ] Reports open without errors.

## Admin
- [ ] Everything HR can do, plus user accounts (create, reset password, role change) and work-policy settings.
- [ ] Cannot deactivate or demote themselves or the last admin.

## Security spot checks
- [ ] Wrong password five times for change-password is rate limited.
- [ ] After a password change, other open sessions are signed out.
- [ ] An Employee cannot open HR pages by typing the address; the API returns 403.
