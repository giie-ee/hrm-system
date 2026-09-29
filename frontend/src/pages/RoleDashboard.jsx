import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import apiClient from '../api/client'
import DashboardInsights from '../components/DashboardInsights'
import WorkspaceDashboard from '../components/WorkspaceDashboard'

const sharedModules = {
  directory: {
    title: 'Employee Directory',
    description: 'Open the connected employee records workspace.',
    href: '/employees',
    internal: true,
    availability: 'Available',
    icon: 'people',
  },
  reports: {
    title: 'Reports',
    description: 'Review live workforce and HR operations summaries.',
    href: '/reports',
    internal: true,
    availability: 'Available',
    icon: 'progress',
  },
  payroll: {
    title: 'Payroll',
    description: 'Create, calculate, and process payroll records.',
    href: '/payroll',
    internal: true,
    availability: 'Available',
    icon: 'payroll',
  },
  payslip: {
    title: 'Payslips',
    description: 'Review payslips from available payroll records.',
    href: '/payslip',
    internal: true,
    availability: 'Available',
    icon: 'payslip',
  },
  leave: {
    title: 'Leave',
    description: 'Review leave requests, balances, and approvals.',
    href: '/leave',
    internal: true,
    availability: 'Available',
    icon: 'calendar',
  },
  attendance: {
    title: 'Attendance',
    description: 'Review attendance records and daily activity.',
    href: '/attendance',
    internal: true,
    availability: 'Available',
    icon: 'clock',
  },
  documents: {
    title: 'Documents',
    description: 'Open the connected employee document workspace.',
    href: '/documents',
    internal: true,
    availability: 'Available',
    icon: 'document',
  },
  benefits: {
    title: 'Benefits',
    description: 'Review benefit plans and enrollment records.',
    href: '/benefits',
    internal: true,
    availability: 'Available',
    icon: 'heart',
  },
  onboarding: {
    title: 'Onboarding',
    description: 'Review onboarding forms and document progress.',
    href: '/onboarding',
    internal: true,
    availability: 'Available',
    icon: 'onboarding',
  },
  progress: {
    title: 'Progress Tracker',
    description: 'Review onboarding, goal, and training progress.',
    href: '/progress-tracker',
    internal: true,
    availability: 'Available',
    icon: 'progress',
  },
}

const roleDashboardContent = {
  Admin: {
    title: 'Admin Dashboard',
    summary: 'Manage people records, access checks, payroll, and connected HR workflows from one place.',
    statusLabel: 'Administrator workspace',
    insightsScope: 'organization',
    modules: [
      sharedModules.directory,
      sharedModules.reports,
      {
        title: 'Admin Access Check',
        description: 'Verify administrator access against the secure backend.',
        href: '/api/test-admin.php',
        availability: 'Available',
        icon: 'shield',
      },
      sharedModules.payroll,
      sharedModules.payslip,
      sharedModules.leave,
      sharedModules.attendance,
      sharedModules.documents,
      sharedModules.benefits,
      sharedModules.onboarding,
      sharedModules.progress,
    ],
  },
  HR: {
    title: 'HR Dashboard',
    summary: 'Keep employee information, attendance, payroll, benefits, and onboarding easy to find and act on.',
    statusLabel: 'HR workspace',
    insightsScope: 'organization',
    modules: [
      sharedModules.directory,
      sharedModules.reports,
      {
        title: 'HR Management Tools',
        description: 'Advanced HR-only workflows will appear when their protected API is connected.',
        availability: 'Backend dependency',
        icon: 'shield',
      },
      sharedModules.payroll,
      sharedModules.payslip,
      sharedModules.leave,
      sharedModules.attendance,
      sharedModules.documents,
      sharedModules.benefits,
      sharedModules.onboarding,
      sharedModules.progress,
    ],
  },
  Manager: {
    title: 'Manager Dashboard',
    summary: 'Review team activity and move quickly between leave, attendance, payroll, and employee progress.',
    statusLabel: 'Manager workspace',
    insightsScope: 'team',
    modules: [
      sharedModules.directory,
      {
        title: 'Team Overview',
        description: 'Team reporting will appear when the manager-specific API is connected.',
        availability: 'Backend dependency',
        icon: 'team',
      },
      sharedModules.leave,
      sharedModules.attendance,
      sharedModules.payroll,
      sharedModules.payslip,
      sharedModules.documents,
      sharedModules.benefits,
      sharedModules.onboarding,
      sharedModules.progress,
    ],
  },
}

function readStoredUser() {
  const storedUser = localStorage.getItem('hrms_user')
  if (!storedUser) return null

  try {
    return JSON.parse(storedUser)
  } catch {
    localStorage.removeItem('hrms_user')
    return null
  }
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
  return number === null ? 'Unavailable' : new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(number)
}

function sumValues(rows, key) {
  if (!Array.isArray(rows)) return null
  if (rows.length === 0) return 0
  const values = rows.map((row) => numericValue(row?.[key])).filter((value) => value !== null)
  return values.length > 0 ? values.reduce((total, value) => total + value, 0) : null
}

function organizationInsights(data) {
  const employees = data?.employees || {}
  const attendance = data?.attendance || {}
  const leaveRows = Array.isArray(data?.leave) ? data.leave : []
  const departmentRows = Array.isArray(data?.departments) ? data.departments : []
  const payrollRows = Array.isArray(data?.payroll) ? data.payroll : []
  const payrollTotal = sumValues(payrollRows, 'net')
  const leaveTotal = sumValues(leaveRows, 'requests')
  const pendingLeave = leaveRows.find((row) => row?.status === 'Pending')
  const attendanceRate = numericValue(attendance.rate_recorded_days_percent)
  const attended = numericValue(attendance.attended)
  const attendanceRecords = numericValue(attendance.records)

  return {
    kpis: [
      { label: 'Total employees', value: formatCount(employees.total) },
      { label: 'Active employees', value: formatCount(employees.active) },
      {
        label: 'Attendance rate',
        value: attendanceRate === null ? 'Unavailable' : `${attendanceRate.toFixed(1)}%`,
        detail: attended !== null && attendanceRecords !== null
          ? `${formatCount(attended)} attended of ${formatCount(attendanceRecords)} recorded days in this period.`
          : 'Based on recorded attendance days, not scheduled workdays.',
      },
      {
        label: 'Leave requests',
        value: formatCount(leaveTotal),
        detail: `Pending: ${formatCount(pendingLeave?.requests)}`,
      },
      {
        label: 'Finalized net payroll',
        value: payrollTotal === null ? 'Unavailable' : formatAmount(payrollTotal),
        detail: `Available period: ${data?.period?.start_date || '—'} to ${data?.period?.end_date || '—'}`,
      },
    ],
    charts: [
      {
        title: 'Employees by department',
        type: 'bar',
        xKey: 'name',
        data: departmentRows
          .map((row) => ({ name: row?.department_name || 'Unassigned', employees: numericValue(row?.employees) }))
          .filter((row) => row.employees !== null),
        series: [{ key: 'employees', label: 'Employees' }],
      },
      {
        title: 'Leave requests by status',
        type: 'bar',
        xKey: 'name',
        data: leaveRows
          .map((row) => ({ name: row?.status || 'Unknown', requests: numericValue(row?.requests) }))
          .filter((row) => row.requests !== null),
        series: [{ key: 'requests', label: 'Requests' }],
      },
      {
        title: 'Monthly finalized net payroll',
        type: 'line',
        xKey: 'period',
        data: payrollRows
          .map((row) => ({ period: row?.period || 'Unknown', net: numericValue(row?.net) }))
          .filter((row) => row.net !== null),
        series: [{ key: 'net', label: 'Net payroll' }],
      },
    ],
  }
}

function managerInsights(data, selectedPeriod) {
  const team = data?.summary?.team || data?.team?.summary || {}
  const attendance = data?.attendance?.summary || {}
  const leave = data?.leave?.summary || {}
  const attendanceTotal = numericValue(attendance.total_records)
  const present = numericValue(attendance.present)
  const attendanceRate = attendanceTotal !== null && attendanceTotal > 0 && present !== null
    ? (present / attendanceTotal) * 100
    : null
  const leaveStatusKey = selectedPeriod.leaveStatus?.toLowerCase()
  const leaveCountKey = leaveStatusKey === 'half-day' ? 'half_day' : leaveStatusKey === 'on leave' ? 'on_leave' : leaveStatusKey
  const leaveCount = leaveCountKey ? leave[leaveCountKey] : leave.pending
  const attendanceStatuses = [
    ['Present', 'present'],
    ['Absent', 'absent'],
    ['Late', 'late'],
    ['Half-day', 'half_day'],
    ['On leave', 'on_leave'],
  ].map(([name, key]) => ({ name, records: numericValue(attendance[key]) }))

  return {
    kpis: [
      { label: 'Assigned team', value: formatCount(team.total_team_members) },
      { label: 'Active team members', value: formatCount(team.active_team_members) },
      {
        label: 'Present in selected period',
        value: formatCount(present),
        detail: `${formatCount(attendanceTotal)} scoped attendance records for ${selectedPeriod.attendanceMonth || 'all dates'}.`,
      },
      {
        label: 'Recorded attendance rate',
        value: attendanceRate === null ? 'Unavailable' : `${attendanceRate.toFixed(1)}%`,
        detail: 'Present records divided by recorded team attendance; not a scheduled-workday rate.',
      },
      {
        label: selectedPeriod.leaveStatus ? `${selectedPeriod.leaveStatus} leave requests` : 'Pending leave requests',
        value: formatCount(leaveCount),
        detail: `${formatCount(leave.total_requests)} team requests for ${selectedPeriod.leaveYear || 'all years'}.`,
      },
    ],
    charts: [
      {
        title: `Team attendance status · ${selectedPeriod.attendanceMonth || 'all dates'}`,
        description: 'Filtered to currently assigned team members; counts include recorded attendance only.',
        type: 'bar',
        xKey: 'name',
        data: attendanceTotal === 0 ? [] : attendanceStatuses.filter((row) => row.records !== null && row.records > 0),
        series: [{ key: 'records', label: 'Records' }],
      },
      {
        title: `Team leave requests · ${selectedPeriod.leaveYear || 'all years'}`,
        description: `Currently assigned team members${selectedPeriod.leaveStatus ? ` · ${selectedPeriod.leaveStatus}` : ''}.`,
        type: 'pie',
        data: [
          ['Pending', 'pending'],
          ['Approved', 'approved'],
          ['Rejected', 'rejected'],
          ['Cancelled', 'cancelled'],
        ].map(([name, key]) => ({ name, value: numericValue(leave[key]) }))
          .filter((row) => row.value !== null && row.value > 0),
      },
    ],
  }
}

function RoleDashboard() {
  const navigate = useNavigate()
  const [user] = useState(readStoredUser)
  const [sessionStatus, setSessionStatus] = useState({ loading: true, ok: false, message: '' })
  const [insightsState, setInsightsState] = useState({ loading: true, data: null, error: '' })
  const today = new Date().toISOString().slice(0, 10)
  const currentMonth = today.slice(0, 7)
  const currentYear = today.slice(0, 4)
  const [rangeStart, setRangeStart] = useState(`${today.slice(0, 4)}-01-01`)
  const [rangeEnd, setRangeEnd] = useState(today)
  const [managerEmployeeId, setManagerEmployeeId] = useState('')
  const [managerAttendanceMonth, setManagerAttendanceMonth] = useState(currentMonth)
  const [managerLeaveYear, setManagerLeaveYear] = useState(currentYear)
  const [managerLeaveStatus, setManagerLeaveStatus] = useState('')

  const handleSignOut = async () => {
    try {
      await apiClient.post('/api/auth/logout.php')
    } catch {
      // Local credentials are still cleared when the backend is unreachable.
    } finally {
      localStorage.removeItem('hrms_user')
      sessionStorage.removeItem('hrms_csrf_token')
      navigate('/login', { replace: true })
    }
  }

  useEffect(() => {
    if (!user) navigate('/login', { replace: true })
  }, [navigate, user])

  useEffect(() => {
    if (!user) return

    apiClient
      .get('/api/auth/me.php')
      .then((response) => {
        sessionStorage.setItem('hrms_csrf_token', response.data.data?.csrf_token || '')
        setSessionStatus({
          loading: false,
          ok: true,
          message: response.data.message || 'Your secure session is verified.',
        })
      })
      .catch((error) => {
        setSessionStatus({
          loading: false,
          ok: false,
          message: error.response?.data?.message || 'Session verification is unavailable.',
        })
      })
  }, [user])

  const content = user ? roleDashboardContent[user.role_name] : null
  const insightsScope = content?.insightsScope

  useEffect(() => {
    if (!user || !insightsScope) return undefined

    let isActive = true

    const loadInsights = async () => {
      if (insightsScope === 'organization'
        && (!rangeStart || !rangeEnd || rangeStart < '2000-01-01' || rangeStart > rangeEnd || rangeEnd > today)) {
        setInsightsState({ loading: false, data: null, error: 'Choose a valid range from 2000-01-01 through today, with the start on or before the end.' })
        return
      }

      setInsightsState({ loading: true, data: null, error: '' })

      try {
        if (insightsScope === 'organization') {
          const response = await apiClient.get('/api/analytics/summary.php', {
            params: { start_date: rangeStart, end_date: rangeEnd },
          })
          const data = response.data?.data
          if (response.data?.success !== true || !data || typeof data !== 'object') {
            throw new Error(response.data?.message || 'Dashboard insights are unavailable.')
          }
          if (isActive) setInsightsState({ loading: false, data, error: '' })
          return
        }

        const results = await Promise.allSettled([
          apiClient.get('/api/manager/dashboard.php'),
          apiClient.get('/api/manager/team.php'),
          apiClient.get('/api/manager/attendance.php', {
            params: { employee_id: managerEmployeeId || undefined, month: managerAttendanceMonth || undefined },
          }),
          apiClient.get('/api/manager/leave.php', {
            params: {
              employee_id: managerEmployeeId || undefined,
              year: managerLeaveYear || undefined,
              status: managerLeaveStatus || undefined,
            },
          }),
        ])
        const labels = ['Team summary', 'Team list', 'Team attendance', 'Team leave']
        const errors = []
        const [summary, team, attendance, leave] = results.map((result, index) => {
          if (result.status !== 'fulfilled' || result.value.data?.success !== true
            || !result.value.data?.data || typeof result.value.data.data !== 'object') {
            errors.push(`${labels[index]} data is unavailable.`)
            return null
          }
          return result.value.data.data
        })
        if (isActive) {
          setInsightsState({
            loading: false,
            data: { summary, team, attendance, leave },
            error: errors.join(' '),
          })
        }
      } catch (requestError) {
        if (isActive) {
          setInsightsState({
            loading: false,
            data: null,
            error: requestError.response?.data?.message || requestError.message || 'Dashboard insights could not be loaded.',
          })
        }
      }
    }

    loadInsights()

    return () => {
      isActive = false
    }
  }, [user, insightsScope, rangeStart, rangeEnd, today, managerEmployeeId, managerAttendanceMonth, managerLeaveYear, managerLeaveStatus])

  if (!user) return null

  if (user.role_name === 'Employee') return <NavigateToEmployeeDashboard />

  if (!content) {
    return (
      <main className="dashboard-unavailable">
        <section className="panel-surface dashboard-unavailable__card">
          <p className="section-label">Nexa People</p>
          <h1>Dashboard unavailable</h1>
          <p>The role “{user.role_name}” does not have a dashboard configured yet.</p>
          <button type="button" onClick={() => navigate('/login', { replace: true })} className="action-button-secondary">
            Return to login
          </button>
        </section>
      </main>
    )
  }

  const teamMembers = Array.isArray(insightsState.data?.team?.employees) ? insightsState.data.team.employees : []
  const selectedTeamMember = teamMembers.find((item) => String(item?.employee?.employee_id) === managerEmployeeId)
  const managerYearOptions = [
    { value: '', label: 'All years' },
    ...Array.from({ length: 5 }, (_, index) => {
      const year = Number(currentYear) - index
      return { value: String(year), label: String(year) }
    }),
  ]
  const insights = insightsScope === 'organization'
    ? organizationInsights(insightsState.data)
    : managerInsights(insightsState.data, {
        attendanceMonth: managerAttendanceMonth,
        leaveYear: managerLeaveYear,
        leaveStatus: managerLeaveStatus,
      })
  const insightFilters = insightsScope === 'organization'
    ? [
        { id: 'start-date', label: 'From', type: 'date', value: rangeStart, onChange: (event) => setRangeStart(event.target.value), min: '2000-01-01', max: rangeEnd, required: true },
        { id: 'end-date', label: 'To', type: 'date', value: rangeEnd, onChange: (event) => setRangeEnd(event.target.value), min: rangeStart, max: today, required: true },
      ]
    : [
        {
          id: 'manager-employee',
          label: 'Team member',
          type: 'select',
          value: managerEmployeeId,
          onChange: (event) => setManagerEmployeeId(event.target.value),
          options: [
            { value: '', label: 'All assigned team members' },
            ...teamMembers.map((item) => ({
              value: String(item.employee.employee_id),
              label: item.employee.full_name || `Employee ${item.employee.employee_id}`,
            })),
          ],
        },
        { id: 'manager-attendance-month', label: 'Attendance month', type: 'month', value: managerAttendanceMonth, onChange: (event) => setManagerAttendanceMonth(event.target.value) },
        { id: 'manager-leave-year', label: 'Leave request year', type: 'select', value: managerLeaveYear, onChange: (event) => setManagerLeaveYear(event.target.value), options: managerYearOptions },
        {
          id: 'manager-leave-status',
          label: 'Leave status',
          type: 'select',
          value: managerLeaveStatus,
          onChange: (event) => setManagerLeaveStatus(event.target.value),
          options: [
            { value: '', label: 'All statuses' },
            ...['Pending', 'Approved', 'Rejected', 'Cancelled'].map((status) => ({ value: status, label: status })),
          ],
        },
      ]
  const filterSummary = insightsScope === 'organization'
    ? `Showing ${rangeStart || '—'} through ${rangeEnd || '—'}.`
    : `Attendance: ${managerAttendanceMonth || 'all dates'} · Leave: ${managerLeaveYear || 'all years'}, ${managerLeaveStatus || 'all statuses'} · ${selectedTeamMember?.employee?.full_name || 'all assigned team members'}.`
  const resetFilters = () => {
    if (insightsScope === 'organization') {
      setRangeStart(`${currentYear}-01-01`)
      setRangeEnd(today)
      return
    }
    setManagerEmployeeId('')
    setManagerAttendanceMonth(currentMonth)
    setManagerLeaveYear(currentYear)
    setManagerLeaveStatus('')
  }

  return (
    <WorkspaceDashboard
      user={user}
      sessionStatus={sessionStatus}
      title={content.title}
      summary={content.summary}
      statusLabel={content.statusLabel}
      modules={content.modules}
      insights={<DashboardInsights
        title={insightsScope === 'organization' ? 'Organization overview' : 'Team overview'}
        description={insightsScope === 'organization'
          ? 'Employee totals are current; attendance, leave, and payroll follow the selected date range.'
          : 'All data is limited to currently assigned team members. Attendance follows the selected month; leave follows the selected year and status.'}
        loading={insightsState.loading}
        error={insightsState.error}
        kpis={insights.kpis}
        charts={insights.charts}
        filters={insightFilters}
        filterSummary={filterSummary}
        onResetFilters={resetFilters}
      />}
      onSignOut={handleSignOut}
    />
  )
}

function NavigateToEmployeeDashboard() {
  const navigate = useNavigate()

  useEffect(() => {
    navigate('/dashboard/employee', { replace: true })
  }, [navigate])

  return null
}

export default RoleDashboard
