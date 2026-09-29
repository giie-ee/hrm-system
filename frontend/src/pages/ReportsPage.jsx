import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import apiClient from '../api/client'

const minimumReportDate = '2000-01-01'

function getToday() {
  return new Date().toISOString().slice(0, 10)
}

function getDefaultPeriod() {
  const today = getToday()
  return { start: `${today.slice(0, 4)}-01-01`, end: today }
}

function isValidDate(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false
  const parsedDate = new Date(`${value}T00:00:00.000Z`)
  return !Number.isNaN(parsedDate.getTime()) && parsedDate.toISOString().slice(0, 10) === value
}

function getDateRangeError(start, end, today) {
  if (!isValidDate(start) || !isValidDate(end)) return 'Enter both dates using a valid calendar date.'
  if (start < minimumReportDate || end > today) return `Dates must be between ${minimumReportDate} and today.`
  if (start > end) return 'Start date must be on or before end date.'
  return ''
}

function numericValue(value) {
  if (value === null || value === undefined || value === '') return null
  const number = Number(value)
  return Number.isFinite(number) ? number : null
}

function formatCount(value) {
  const number = numericValue(value)
  return number === null ? 'Unavailable' : new Intl.NumberFormat().format(number)
}

function formatAmount(value) {
  const number = numericValue(value)
  return number === null
    ? 'Unavailable'
    : new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(number)
}

function formatRate(value) {
  const number = numericValue(value)
  return number === null ? 'Unavailable' : `${number.toFixed(2)}%`
}

function getRows(data, key) {
  return Array.isArray(data?.[key]) ? data[key] : []
}

function MetricCard({ label, value, detail }) {
  return (
    <article className="panel-surface min-w-0 p-4 sm:p-5">
      <p className="section-label">{label}</p>
      <p className="mt-2 wrap-break-word text-2xl font-bold tracking-tight text-slate-900">{value ?? 'Unavailable'}</p>
      {detail && <p className="mt-1 text-xs leading-5 text-slate-500">{detail}</p>}
    </article>
  )
}

function ReportSection({ title, scope, description, children }) {
  return (
    <section className="mb-8">
      <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="text-xl font-bold text-slate-900">{title}</h2>
          {description && <p className="mt-1 max-w-3xl text-sm text-slate-600">{description}</p>}
        </div>
        <span className="self-start rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{scope}</span>
      </div>
      {children}
    </section>
  )
}

function ReportTable({ label, rows, columns, rowKey }) {
  const records = Array.isArray(rows) ? rows : []

  return (
    <div className="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white">
      <div className="border-b border-slate-200 px-4 py-3">
        <h3 className="text-sm font-semibold text-slate-800">{label}</h3>
      </div>
      {records.length === 0 ? (
        <p className="px-4 py-6 text-center text-sm text-slate-500" role="status">No records are available for this report.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="bg-slate-50 text-xs uppercase text-slate-500">
              <tr>{columns.map((column) => <th key={column.label} className="whitespace-nowrap px-4 py-3 font-semibold">{column.label}</th>)}</tr>
            </thead>
            <tbody className="divide-y divide-slate-200">
              {records.map((record, index) => (
                <tr key={rowKey ? rowKey(record) : `${label}-${index}`} className="bg-white">
                  {columns.map((column) => (
                    <td key={column.label} className="whitespace-nowrap px-4 py-3 text-slate-700">
                      {column.render ? column.render(record) : record?.[column.key] ?? 'Unavailable'}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

function ChartEmptyState() {
  return <p className="flex h-64 items-center justify-center rounded-lg bg-slate-50 px-4 text-center text-sm text-slate-500" role="status">No chart data is available.</p>
}

function DepartmentChart({ rows }) {
  const chartRows = rows
    .map((row) => ({ name: row?.department_name || 'Department unavailable', employees: numericValue(row?.employees) }))
    .filter((row) => row.employees !== null)

  return (
    <article className="panel-surface min-w-0 p-4 sm:p-5">
      <h3 className="mb-3 font-semibold text-slate-900">Employees by department</h3>
      {chartRows.length === 0 ? <ChartEmptyState /> : (
        <ResponsiveContainer width="100%" height={Math.max(260, chartRows.length * 36)}>
          <BarChart data={chartRows} layout="vertical" margin={{ top: 4, right: 16, bottom: 4, left: 8 }}>
            <CartesianGrid stroke="#e2e8f0" strokeDasharray="3 3" />
            <XAxis type="number" allowDecimals={false} tick={{ fontSize: 12 }} />
            <YAxis type="category" dataKey="name" width={130} tick={{ fontSize: 12 }} />
            <Tooltip />
            <Bar dataKey="employees" name="Employees" fill="#0284c7" radius={[0, 3, 3, 0]} />
          </BarChart>
        </ResponsiveContainer>
      )}
    </article>
  )
}

function LeaveChart({ rows }) {
  const chartRows = rows
    .map((row) => ({ status: row?.status || 'Status unavailable', requests: numericValue(row?.requests) }))
    .filter((row) => row.requests !== null)

  return (
    <article className="panel-surface min-w-0 p-4 sm:p-5">
      <h3 className="mb-3 font-semibold text-slate-900">Leave requests by status</h3>
      {chartRows.length === 0 ? <ChartEmptyState /> : (
        <ResponsiveContainer width="100%" height={260}>
          <BarChart data={chartRows} margin={{ top: 8, right: 12, bottom: 4, left: 0 }}>
            <CartesianGrid stroke="#e2e8f0" strokeDasharray="3 3" />
            <XAxis dataKey="status" tick={{ fontSize: 12 }} />
            <YAxis allowDecimals={false} tick={{ fontSize: 12 }} width={48} />
            <Tooltip />
            <Bar dataKey="requests" name="Requests" fill="#059669" radius={[3, 3, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      )}
    </article>
  )
}

function PayrollChart({ rows }) {
  const chartRows = rows
    .map((row) => ({
      period: row?.period || 'Period unavailable',
      gross: numericValue(row?.gross),
      deductions: numericValue(row?.deductions),
      net: numericValue(row?.net),
    }))
    .filter((row) => row.gross !== null || row.deductions !== null || row.net !== null)

  return (
    <article className="panel-surface min-w-0 p-4 sm:p-5">
      <h3 className="mb-3 font-semibold text-slate-900">Monthly payroll trend</h3>
      {chartRows.length === 0 ? <ChartEmptyState /> : (
        <ResponsiveContainer width="100%" height={280}>
          <LineChart data={chartRows} margin={{ top: 8, right: 12, bottom: 4, left: 0 }}>
            <CartesianGrid stroke="#e2e8f0" strokeDasharray="3 3" />
            <XAxis dataKey="period" tick={{ fontSize: 12 }} />
            <YAxis tick={{ fontSize: 12 }} width={76} />
            <Tooltip />
            <Legend />
            <Line type="monotone" dataKey="gross" name="Gross" stroke="#0284c7" strokeWidth={2} dot={{ r: 3 }} connectNulls />
            <Line type="monotone" dataKey="deductions" name="Deductions" stroke="#d97706" strokeWidth={2} dot={{ r: 3 }} connectNulls />
            <Line type="monotone" dataKey="net" name="Net" stroke="#059669" strokeWidth={2} dot={{ r: 3 }} connectNulls />
          </LineChart>
        </ResponsiveContainer>
      )}
    </article>
  )
}

function ReportsPage() {
  const today = getToday()
  const initialPeriod = getDefaultPeriod()
  const [startDate, setStartDate] = useState(initialPeriod.start)
  const [endDate, setEndDate] = useState(initialPeriod.end)
  const [activePeriod, setActivePeriod] = useState(initialPeriod)
  const [requestVersion, setRequestVersion] = useState(0)
  const [validationError, setValidationError] = useState('')
  const [reportState, setReportState] = useState({ loading: true, data: null, error: '' })

  useEffect(() => {
    let isActive = true

    apiClient.get('/api/analytics/summary.php', {
      params: { start_date: activePeriod.start, end_date: activePeriod.end },
    })
      .then((response) => {
        const payload = response.data
        if (payload?.success !== true || !payload.data || typeof payload.data !== 'object') {
          throw new Error(payload?.message || 'The reporting response is unavailable.')
        }
        if (isActive) setReportState({ loading: false, data: payload.data, error: '' })
      })
      .catch((requestError) => {
        if (isActive) {
          setReportState({
            loading: false,
            data: null,
            error: requestError.response?.data?.message || requestError.message || 'Reports could not be loaded.',
          })
        }
      })

    return () => {
      isActive = false
    }
  }, [activePeriod.start, activePeriod.end, requestVersion])

  const applyDateRange = (event) => {
    event.preventDefault()
    const error = getDateRangeError(startDate, endDate, today)
    setValidationError(error)
    if (error) return

    setReportState({ loading: true, data: null, error: '' })
    setActivePeriod({ start: startDate, end: endDate })
    setRequestVersion((version) => version + 1)
  }

  const resetDateRange = () => {
    const defaultPeriod = getDefaultPeriod()
    setStartDate(defaultPeriod.start)
    setEndDate(defaultPeriod.end)
    setValidationError('')
    setReportState({ loading: true, data: null, error: '' })
    setActivePeriod(defaultPeriod)
    setRequestVersion((version) => version + 1)
  }

  const refreshReports = () => {
    setReportState({ loading: true, data: null, error: '' })
    setRequestVersion((version) => version + 1)
  }

  const data = reportState.data
  const departments = getRows(data, 'departments')
  const positions = getRows(data, 'positions')
  const leaveRows = getRows(data, 'leave')
  const payrollRows = getRows(data, 'payroll')
  const benefitsRows = getRows(data, 'benefits')
  const onboardingRows = getRows(data, 'onboarding')
  const recruitmentRows = getRows(data, 'recruitment')
  const trainingRows = getRows(data, 'training')
  const performanceRows = getRows(data, 'performance')
  const reportPeriod = data?.period || activePeriod
  const attendance = data?.attendance || {}
  const employees = data?.employees || {}

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 p-4 sm:p-6">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">HRMS / Analytics</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Reports</h1>
              <p className="mt-2 max-w-2xl text-sm text-slate-600">Review workforce, attendance, leave, payroll, and HR workflow summaries from live reporting data.</p>
              <p className="mt-3 text-sm font-medium text-slate-700">
                Selected reporting period: {reportPeriod.start_date || reportPeriod.start || 'Unavailable'} to {reportPeriod.end_date || reportPeriod.end || 'Unavailable'}
              </p>
            </div>
            <div className="flex flex-wrap gap-3">
              <button type="button" onClick={refreshReports} disabled={reportState.loading} className="action-button-secondary">
                {reportState.loading ? 'Refreshing...' : 'Refresh reports'}
              </button>
              <Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link>
            </div>
          </div>
        </header>

        <form onSubmit={applyDateRange} className="panel-surface mb-6 p-5 sm:p-6">
          <div className="mb-4">
            <p className="section-label">Reporting period</p>
            <p className="mt-1 text-sm text-slate-600">The selected period applies to attendance, overlapping leave requests, and processed/paid payroll.</p>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
            <label>
              <span className="mb-2 block text-sm font-medium text-slate-700">Start date</span>
              <input required type="date" min={minimumReportDate} max={endDate || today} value={startDate} onChange={(event) => setStartDate(event.target.value)} className="field-input" />
            </label>
            <label>
              <span className="mb-2 block text-sm font-medium text-slate-700">End date</span>
              <input required type="date" min={startDate || minimumReportDate} max={today} value={endDate} onChange={(event) => setEndDate(event.target.value)} className="field-input" />
            </label>
            <div className="flex flex-wrap gap-3 sm:col-span-2 lg:col-span-1">
              <button type="submit" disabled={reportState.loading} className="action-button-primary">Apply period</button>
              <button type="button" disabled={reportState.loading} onClick={resetDateRange} className="action-button-secondary">Reset</button>
            </div>
          </div>
          {validationError && <p className="mt-3 text-sm text-red-700" role="alert">{validationError}</p>}
          <p className="mt-3 text-xs text-slate-500">Employee, department, position, benefits, onboarding, recruitment, training, and performance summaries are all-time/current grouped data.</p>
        </form>

        {reportState.loading && (
          <div className="panel-surface mb-6 p-6 text-center text-sm text-sky-800" role="status">Loading report data for {activePeriod.start} to {activePeriod.end}...</div>
        )}

        {!reportState.loading && reportState.error && (
          <div className="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{reportState.error}</div>
        )}

        {!reportState.loading && !reportState.error && data && (
          <>
            {data.scope_note && <p className="mb-6 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">{data.scope_note}</p>}

            <ReportSection title="Workforce" scope="All-time/current grouped data" description="Employee totals and organization breakdowns are not filtered by the selected period.">
              <div className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard label="Total employees" value={formatCount(employees.total)} />
                <MetricCard label="Active employees" value={formatCount(employees.active)} />
              </div>
              <div className="grid gap-4 xl:grid-cols-2">
                <DepartmentChart rows={departments} />
                <ReportTable
                  label="Position breakdown"
                  rows={positions}
                  rowKey={(row) => row?.position_id ?? row?.position_name}
                  columns={[
                    { key: 'position_name', label: 'Position' },
                    { label: 'Employees', render: (row) => formatCount(row?.employees) },
                  ]}
                />
              </div>
            </ReportSection>

            <ReportSection title="Attendance" scope="Selected reporting period" description="Attendance records between the selected start and end dates.">
              <div className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard label="Attendance records" value={formatCount(attendance.records)} />
                <MetricCard label="Attended records" value={formatCount(attendance.attended)} />
                <MetricCard label="Recorded hours" value={formatAmount(attendance.hours)} />
                <MetricCard label="Recorded-day attendance rate" value={formatRate(attendance.rate_recorded_days_percent)} />
              </div>
              <p className="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                {attendance.definition || 'Rate definition unavailable.'} This is not a scheduled-workday attendance rate.
              </p>
            </ReportSection>

            <ReportSection title="Leave" scope="Selected reporting period" description="Requests whose date ranges overlap the selected period; leave days are the request totals as stored, not prorated.">
              <div className="grid gap-4 xl:grid-cols-2">
                <LeaveChart rows={leaveRows} />
                <ReportTable
                  label="Requests and leave days by status"
                  rows={leaveRows}
                  rowKey={(row) => row?.status}
                  columns={[
                    { key: 'status', label: 'Status' },
                    { label: 'Requests', render: (row) => formatCount(row?.requests) },
                    { label: 'Leave days', render: (row) => formatCount(row?.days) },
                  ]}
                />
              </div>
            </ReportSection>

            <ReportSection title="Payroll" scope="Selected period · processed/paid only" description="Monthly totals are grouped by pay-period start month and exclude Draft payroll records.">
              <div className="mb-4 grid gap-4 xl:grid-cols-2">
                <PayrollChart rows={payrollRows} />
                <ReportTable
                  label="Monthly payroll totals"
                  rows={payrollRows}
                  rowKey={(row) => row?.period}
                  columns={[
                    { key: 'period', label: 'Month' },
                    { label: 'Records', render: (row) => formatCount(row?.records) },
                    { label: 'Gross', render: (row) => formatAmount(row?.gross) },
                    { label: 'Deductions', render: (row) => formatAmount(row?.deductions) },
                    { label: 'Net', render: (row) => formatAmount(row?.net) },
                  ]}
                />
              </div>
            </ReportSection>

            <ReportSection title="Benefits" scope="All-time/current grouped data" description="Assignment counts and amounts are grouped by status and are not date-filtered by this endpoint.">
              <ReportTable
                label="Benefit assignments by status"
                rows={benefitsRows}
                rowKey={(row) => row?.status}
                columns={[
                  { key: 'status', label: 'Status' },
                  { label: 'Assignments', render: (row) => formatCount(row?.assignments) },
                  { label: 'Amount', render: (row) => formatAmount(row?.amount) },
                ]}
              />
            </ReportSection>

            <ReportSection title="Onboarding" scope="All-time/current grouped data" description="Onboarding records grouped by current status; not date-filtered by this endpoint.">
              <ReportTable
                label="Onboarding records by status"
                rows={onboardingRows}
                rowKey={(row) => row?.status}
                columns={[
                  { key: 'status', label: 'Status' },
                  { label: 'Records', render: (row) => formatCount(row?.records) },
                ]}
              />
            </ReportSection>

            <ReportSection title="Recruitment" scope="All-time/current grouped data" description="Applications grouped by current status; not date-filtered by this endpoint.">
              <ReportTable
                label="Applications by status"
                rows={recruitmentRows}
                rowKey={(row) => row?.status}
                columns={[
                  { key: 'status', label: 'Status' },
                  { label: 'Applications', render: (row) => formatCount(row?.applications) },
                ]}
              />
            </ReportSection>

            <ReportSection title="Training" scope="All-time/current grouped data" description="Training enrollments grouped by current status; not date-filtered by this endpoint.">
              <ReportTable
                label="Enrollments by status"
                rows={trainingRows}
                rowKey={(row) => row?.status}
                columns={[
                  { key: 'status', label: 'Status' },
                  { label: 'Enrollments', render: (row) => formatCount(row?.enrollments) },
                ]}
              />
            </ReportSection>

            <ReportSection title="Performance" scope="All-time/current grouped data" description="Review counts and average final ratings grouped by review status; not date-filtered by this endpoint.">
              <ReportTable
                label="Performance reviews by status"
                rows={performanceRows}
                rowKey={(row) => row?.status}
                columns={[
                  { key: 'status', label: 'Review status' },
                  { label: 'Reviews', render: (row) => formatCount(row?.reviews) },
                  { label: 'Average final rating', render: (row) => formatAmount(row?.average_final_rating) },
                ]}
              />
            </ReportSection>
          </>
        )}
      </div>
    </main>
  )
}

export default ReportsPage