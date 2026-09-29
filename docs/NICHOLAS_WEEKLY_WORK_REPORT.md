# Nicholas Weekly HRMS Work Report

## Reporting period and assigned responsibility

**Assigned work:** Add independent payroll information and work-hour data through SQL, and assist Agatha and Amani with frontend inconsistency resolution and testing evidence.

This delivery converts the team's all-role testing observations into an additive PostgreSQL migration, scoped APIs, and practical Admin/HR screens. It does not treat unresolved business policy questions as finished technical features.

## All-role testing findings

The team tested the Admin, HR, Manager, and Employee accounts and observed the following:

- The Employee Directory is appropriately hidden from the Employee role and available to the other roles.
- Admin and HR could view employees but could not add, edit, deactivate, or reactivate employee records.
- Managers need access only to employees assigned to them or their department, not the whole organisation.
- The leave-type selector had no options, preventing leave submission.
- Employee self-service check-in and check-out existed, but there was no proof that the employee was physically at work.
- Attendance status depended too much on manual input and was not derived from company working-hour policy.
- Payroll permissions and sensitive-data visibility need clearer separation between HR, customer Admin, and any future vendor/platform administrator.
- Employees need access to their own payslips and a printable/mobile-saveable copy.
- Document access should be scoped: Employee to own documents, Manager to assigned employees, and Admin/HR to all authorised employee records.
- The HR navigation appeared to be missing Benefits during the tested deployment.
- Notifications, an organisational calendar, and role-filtered communication are needed.
- The onboarding protocol is not complete. The proposed direction is an invitation/default-login flow, employee onboarding form, and HR verification.
- The progress tracker has no agreed data source or fairness rules. Streaks or bonuses should not be introduced until the team agrees on objective measures and an appeal/review process.
- There was not enough realistic sample data for full workflow testing.

The lecturer's feedback on the interface was positive: the design felt distinctive, clean, and less like an unmodified generated template.

## Work completed in this delivery

### 1. PostgreSQL payroll and work-hour migration

`database/postgresql/003_nicholas_payroll_work_hours.sql` adds:

- A department code and safe General department/position defaults for incomplete employee records.
- Configurable work policies and seven day-of-week schedule records.
- Employee-to-work-policy assignment.
- Attendance schedule snapshots: expected hours, late minutes, early departure minutes, source, and verification status.
- A reusable payroll-component catalogue and employee recurring component assignments.
- Payroll currency, creator/processor audit fields, processing timestamp, and payment reference.
- Payroll attendance totals for expected, worked, overtime, and shortfall hours.
- Baseline Annual, Sick, Compassionate, and Unpaid leave-type names.

Leave entitlements remain zero until Admin/HR enters the organisation's approved policy. This prevents the system from inventing legal or company entitlements.

### 2. Employee management and department allocation

- Admin/HR can now create employee records, edit details, and deactivate/reactivate employees from the Employee Directory.
- The form uses department and position selections instead of asking users to enter internal IDs.
- Manager directory and attendance data use the existing manager-assignment scope instead of exposing all employees.
- Employee records keep one global, stable database primary key. A human-readable `employee_number` can contain a department prefix such as `FIN-001` or `HR-001`.

**Decision:** Primary keys should not restart per department. Department-specific primary keys can collide and break relationships when an employee transfers. The correct relationship is a global employee ID plus a department foreign key and a readable employee number.

Creating an employee record does not yet create a login account or send an onboarding email. That should be completed as part of the agreed onboarding invitation workflow.

### 3. Work-hour policy and attendance classification

- Admin can configure the company timezone, late grace period, early-departure grace period, half-day threshold, working days, start/end times, and expected hours.
- Employees check themselves in and out.
- The API automatically classifies On Time/Present, Late, Half-Day, Early Checkout, or Off Schedule from the stored company policy.
- Check-in/out times use the configured work-policy timezone.
- HR/Admin can add a confirmed manual attendance record when a correction is required.
- Attendance views show worked versus expected hours, late minutes, early-departure minutes, source, and verification state.

Self-service check-ins are deliberately labelled **Unverified**. The system does not falsely claim that a timestamp proves physical presence.

Automatic daily absence creation still needs a scheduled job. Location verification also remains a policy decision. A future approach could use an organisation-defined geofence, an approved device/site kiosk, or manager confirmation, subject to employee consent and privacy requirements.

### 4. Independent payroll information

- Admin/HR can select an employee, create salary history, and assign recurring allowances or deductions.
- Payroll creation copies applicable recurring components into the payroll period.
- Processing captures scheduled working days and expected hours from the work policy, actual attendance hours, approved leave, missing scheduled days, late days, overtime, and shortfall.
- Attendance information is a recorded payroll reference; salary is not automatically reduced from shortfall because the organisation has not approved that policy.
- Employees can retrieve only their own payroll records; Managers can retrieve only assigned employees; Admin/HR can retrieve all authorised payroll records.
- The existing payslip Print action can be used to print or save a PDF on desktop or mobile.
- Payroll creator and processor details are recorded for auditability.

**Remaining control decision:** Audit history alone is not full fraud prevention. A recommended future approval flow is HR prepares payroll, Finance/customer Admin approves it, and the creator cannot approve their own payroll. The team must agree on this before implementation.

### 5. Notifications and interface consistency

- The role dashboard now displays a notification bell, unread count, and role/user-scoped notifications.
- Reading a notification updates its state through the existing protected API.
- Employee Directory, Attendance, and Payroll now use clearer labels and selectors rather than raw database IDs.
- Benefits already exists in the current role-dashboard configuration for HR. If it is absent after deployment, the hosted frontend is behind the local version and should be rebuilt/redeployed before adding duplicate navigation.

The organisational calendar is not included in this delivery and remains future work.

## Security and administration decision

For a normal customer installation, the real business **Admin** should be a trusted person appointed by the customer organisation. The development team should not routinely use that role or view the customer's HR records.

If the system later becomes a multi-organisation product, a separate platform/vendor support role and separate control plane will be needed. It should use explicit customer authorisation, least-privilege access, strong authentication, encryption, immutable audit records, and time-limited support access. It should not share the organisation Admin account.

Sensitive fields should be returned only when the user needs them. Notifications should also remain role/user targeted; payroll or document events must not become organisation-wide announcements.

## Team handoff

### Natalie — Leave, Onboarding, and Document Requests

- Use the seeded leave-type names, but create the UI/protocol for Admin/HR entitlement setup and annual employee balance allocation.
- Define the onboarding invitation, employee form fields, required documents, HR verification, rejection/correction, and completion steps.
- Keep document visibility aligned to Employee self, Manager assigned employees, and Admin/HR authorised organisation scope.

### Kamuti — Backend/API integration

- Integrate and retest the new work-policy, attendance, employee, payroll-component, and payroll endpoints.
- Add a safe scheduled absence job after the team agrees on holidays, approved leave, weekends, and cross-midnight shifts.
- Add physical-presence verification only after the organisation chooses a lawful, privacy-aware method.
- Design the two-person payroll approval state after the team chooses the approver role.

### Agatha — Frontend consistency

- Continue applying the Nexa People design system to the functional Employee Directory, Attendance, Payroll, Benefits, Onboarding, and Documents pages.
- Preserve the current role restrictions when improving layout.
- Complete the deeper mobile pass later, especially tables, forms, notification panels, and print views.

### Amani — Testing documentation

- Use the retest matrix below as the next test cycle.
- Record the test date, account/role, expected result, actual result, screenshot/error, and pass/fail status.
- Separate verified behaviour from proposed policy decisions.

## Required database/deployment step

The new tables and columns do not exist in Neon until the pending migration is run. From the repository root, with the production database environment variables loaded:

```powershell
& "C:\xampp\php\php.exe" bin\migrate.php
```

On Render, redeploy the service after the updated code is pushed. Check the migration record with:

```sql
SELECT version, applied_at
FROM schema_migrations
ORDER BY applied_at;
```

The expected newest filename is `003_nicholas_payroll_work_hours.sql`.

Do not paste the Neon password into chat, screenshots, documentation, or source control.

## Role retest matrix

### Admin

- Configure the active work schedule and timezone.
- Create, edit, deactivate, and reactivate an employee.
- Set salary history and recurring payroll components.
- Create/process a payroll period and confirm the work-hour snapshot.
- Confirm notifications appear and can be marked read.

### HR

- Create/edit/deactivate an employee.
- Confirm work policy is visible but not editable.
- Configure salary/payroll data and process the currently authorised workflow.
- Confirm Benefits appears after the latest build is deployed.
- Allocate a leave balance before testing leave submission.

### Manager

- Confirm only assigned employees appear in the directory, attendance, payroll, documents, and other employee-scoped screens.
- Confirm unrelated employee IDs cannot be retrieved directly through the API.
- Confirm company work policy is view-only.

### Employee

- Confirm the Employee Directory remains hidden.
- Check in and out and confirm automatic attendance status and Unverified location label.
- Confirm only the employee's own payroll/payslip is visible, then print or save it as PDF.
- Confirm leave types appear; then submit leave only after HR/Admin allocates a balance for the current year.
- Confirm notifications are limited to the employee and their role.

## Verification completed locally

- All changed PHP files passed syntax checks.
- Frontend ESLint passed.
- The frontend production build completed successfully.
- Git whitespace validation passed; line-ending notices are informational Windows CRLF notices.

## Known limitations and next decisions

- The migration was not applied to the live Neon database during this coding pass.
- Render and live browser role tests must be repeated after migration and redeployment.
- No automatic absent scheduler, holiday calendar, or cross-midnight shift support is included yet.
- No geofence, biometric, or device identity proof is claimed.
- No two-person payroll approval workflow is included yet.
- Employee creation does not yet issue a login or onboarding email.
- Calendar UI and complete onboarding protocol remain open.
- Progress tracking/streak rewards remain a concept only. They should not affect pay or benefits without objective measures, human review, and an appeal mechanism.
- No changes were committed or pushed to GitHub.
