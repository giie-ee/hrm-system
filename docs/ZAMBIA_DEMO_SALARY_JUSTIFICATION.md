# Nexa People HRMS: Zambian demonstration salary justification

> **IMPORTANT: EVERY AMOUNT IN THIS DOCUMENT CAN CHANGE.** The figures are
> editable demonstration guidelines for a fictional small-to-medium Zambian
> software company. They are not mandatory salaries, verified company payroll,
> statutory rates, employment offers or compensation consultancy.

## Evidence boundary

There is no official source identified here that publishes national averages
for each of the five named occupations. The sources therefore have different
evidence levels and must not be blended into a claim of a verified market
average.

| Source | What it verifies or estimates | How it is used |
|---|---|---|
| Zambia Statistics Agency, *2022-2023 Employment and Earnings Inquiry* (published February 2025) | Verified official establishment-survey results. The 2023 formal-sector average was ZMW 7,731; Information and Communication was ZMW 6,854; Professional, Scientific and Technical Activities was ZMW 12,262; Administrative and Support Services was ZMW 6,032. These are industry averages, not occupation salaries. | Economic context only. It does not prove what a developer or HR professional should earn. |
| Go Zambia Jobs, *Computer Software Engineer Salary in Zambia* (15 September 2025) | Identifiable local salary-guide estimates: junior ZMW 5,000-10,000, mid-level ZMW 10,000-20,000, and senior ZMW 20,000-30,000+ monthly. The page does not publish a sample or statistical methodology. | A practical estimate for choosing fictional developer values, clearly labelled as an estimate. |
| Jobs Mu Zambia, *Human Resources Officer Salary in Zambia* (2026) | Explicit estimates: HR Officer ZMW 6,500-12,000, experienced HR Officer ZMW 9,000-15,000, and HR Manager ZMW 18,000-30,000+ monthly. The source states that these are not an official national salary scale. | A practical estimate for the fictional HR roles, clearly labelled as an estimate. |
| Paylab, Lead Developer salary in Zambia | Anonymous, self-reported survey data cleaned by the provider. Its displayed lead-developer range is broad and the page says fewer than 20 verified respondents were available. | A weak cross-check only; it is not treated as an official or precise market average. |

Sources accessed 8 October 2026:

- https://www.zamstats.gov.zm/wp-content/uploads/2025/02/2022-2023-Employment-and-Earnings-Inquiry.pdf
- https://gozambiajobs.com/blog/computer-software-engineer-salary-in-zambia
- https://jobsmuzambia.com/human-resources-officer-salary-in-zambia/
- https://www.paylab.com/zm/salarios/tecnologias-de-la-informacion/desarrollador-jefe?lang=es

## Demonstration recommendations

| Position | Reference range used | Nexa People demo guideline | Reason for the demonstration value |
|---|---:|---:|---|
| Junior Software Developer | ZMW 5,000-10,000 estimate | **ZMW 9,000** | Near the upper end of the local junior estimate to represent a qualified early-career developer in a software company, without treating the highest value as automatic. |
| Software Developer (mid-level) | ZMW 10,000-20,000 estimate | **ZMW 16,500** | Inside the estimated mid-level band for an employee who can deliver features independently but does not yet carry senior design or mentoring responsibility. |
| Senior Software Developer | ZMW 20,000-30,000+ estimate | **ZMW 20,000** | At the lower boundary of the estimated senior band, proportionate to a fictional small-to-medium local company rather than a multinational or overseas remote employer. |
| Lead Software Developer | No verified national occupation average; senior estimate and Paylab survey used as cross-checks | **ZMW 25,000** | Above the senior demonstration value to reflect architecture, coordination and mentoring responsibility, while staying moderate for the fictional company size. This is the most assumption-dependent figure. |
| HR Officer | ZMW 6,500-12,000 estimate; experienced range ZMW 9,000-15,000 | **ZMW 11,500** | Within both cited HR estimates, suitable for a generalist handling recruitment, records, leave, onboarding and payroll support. |
| HR Manager | ZMW 18,000-30,000+ estimate | **ZMW 18,500** | Near the lower end of the cited management estimate, consistent with responsibility for the HR function in a small-to-medium organisation. |

These choices are deliberately simple demo data. They are not assertions that
the listed values are the Zambian market average. Location, employer type,
experience, qualifications, specialist skills, benefits, remote work and the
size and budget of an organisation can all justify different amounts.

## Why the HRMS must keep salaries configurable

A real HRMS serves organisations with different budgets, job structures,
collective arrangements, locations and conditions of service. Market conditions
also change. Fixed amounts in application code would become inaccurate, could
conflict with contracts, and would prevent legitimate differences between two
employees in the same position.

Nexa People therefore keeps the following concepts separate:

1. **Position salary guideline** - a non-binding reference used by Admin/HR for
   comparison and planning. It can be changed or deactivated.
2. **Employee basic salary** - the employee's approved, effective-dated salary
   record. It is individual and must retain history.
3. **Payroll calculation** - the result for a pay period, starting from the
   applicable employee salary and then applying allowances, bonuses, deductions,
   attendance adjustments and other approved payroll items.

The current Payroll screen reads and compares position guidelines but does not
yet contain an Admin/HR editing form for them. For now, an approved migration or
database-administration step can change a guideline. A future UI should add the
same effective-date, authorisation and audit controls used for salary changes;
the values must not become constants in PHP or React code.

Changing a guideline must not rewrite an employee salary or an old payroll.
Likewise, changing one employee's salary must not redefine the position
guideline for everyone else.

## Short project-documentation justification

Nexa People HRMS uses editable ZMW salary guidelines to provide realistic data
for a university demonstration. The selected software-development figures sit
within identifiable Zambian salary-guide estimates, while the HR figures sit
within explicitly labelled local estimates. Official ZamStats industry earnings
are used only as economic context because they are not occupation-specific.
The figures are not fixed policies: an organisation must configure its own
guidelines and approve each employee's effective-dated basic salary. Actual
payroll remains a separate calculation that applies earnings and deductions to
the approved employee salary for a particular period.

## Possible questions from Mr Mwanza

### Are these official Zambian salary scales?

No. ZamStats provides official industry-level earnings, not the occupation-level
rates used here. The occupation ranges are identifiable estimates, and the
chosen figures are documented demonstration assumptions.

### Why is the junior developer amount above the overall formal-sector average?

The ZamStats figure covers all formal-sector occupations and industries. A
software role requires specialised technical skills, and the chosen ZMW 9,000
still sits inside the cited junior developer estimate.

### Why does the lead developer earn more than the senior developer?

The lead role adds architecture, delivery coordination, mentoring and technical
decision responsibility. ZMW 25,000 is a project assumption for that additional
scope, not a verified national average.

### Why is the senior guideline at the bottom of the cited range?

The demonstration organisation is a small-to-medium local company. A
multinational, mining company, specialist employer or overseas remote role may
reasonably pay more.

### Can HR simply change everybody's salary by changing a guideline?

No. A guideline is only a comparison value. Employee salaries are separate,
effective-dated records, and the system should require an authorised salary
change for each employee.

### What happens when salaries change next year?

Admin/HR can end-date or deactivate a guideline and add a new effective-dated
value. Employee salary history and completed payroll records remain intact.

### Does basic salary equal take-home pay?

No. Basic salary is the contractual starting amount. Payroll combines it with
approved allowances or bonuses and subtracts deductions or adjustments to
calculate the period's gross and net pay.

### Why not hard-code one salary for each role?

Hard-coding would confuse demo assumptions with policy, ignore organisation and
employee differences, and destroy the purpose of salary history. Configuration
is both more realistic and technically safer.
