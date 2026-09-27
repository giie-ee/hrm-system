import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import apiClient from '../api/client'
import DashboardInsights from '../components/DashboardInsights'
import WorkspaceDashboard from '../components/WorkspaceDashboard'

const employeeModules = [
  {
    title: 'My Payslips',
    description: 'Review payslips from your available payroll records.',
    href: '/payslip',
    internal: true,
    availability: 'Available',
    icon: 'payslip',
  },
  {
    title: 'Leave',
    description: 'Submit and review your leave requests and balances.',
    href: '/leave',
    internal: true,
    availability: 'Available',
    icon: 'calendar',
  },
  {
    title: 'Attendance',
    description: 'Review attendance and use supported daily controls.',
    href: '/attendance',
    internal: true,
    availability: 'Available',
    icon: 'clock',
  },
  {
    title: 'Documents',
    description: 'Open your employee and onboarding documents.',
    href: '/documents',
    internal: true,
    availability: 'Available',
    icon: 'document',
  },
  {
    title: 'Benefits',
    description: 'Review available benefit plans and enrollment details.',
    href: '/benefits',
    internal: true,
    availability: 'Available',
    icon: 'heart',
  },
  {
    title: 'Onboarding',
    description: 'Complete onboarding information and required steps.',
    href: '/onboarding',
    internal: true,
    availability: 'Available',
    icon: 'onboarding',
  },
  {
    title: 'My Progress',
    description: 'View your onboarding and employee lifecycle progress.',
    href: '/progress-tracker',
    internal: true,
    availability: 'Available',
    icon: 'progress',
  },
  {
    title: 'Employee Access Check',
    description: 'Validate your current authenticated employee session.',
    href: '/api/test-employee.php',
    availability: 'Available',
    icon: 'shield',
  },
]

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

function employeeInsights(data, year) {
  const leave = data.leave
  const attendance = data.attendance
  const payroll = data.payroll
  const balances = Array.isArray(leave?.balances) ? leave.balances : null
  const requests = Array.isArray(leave?.requests) ? leave.requests : null
  const attendanceRows = Array.isArray(attendance) ? attendance : null
  const payrollRows = Array.isArray(payroll?.payrolls) ? payroll.payrolls : null
  const remainingDays = sumValues(balances, 'remaining_days')
  const netPayroll = numericValue(payroll?.summary?.total_net_salary)
  const attendanceHours = sumValues(attendanceRows, 'hours_worked')
  const statusCount = (rows, status) => rows?.filter((row) => row?.status === status).length ?? null
  const attendanceStatuses = ['Present', 'Late', 'Absent', 'Half-Day', 'On Leave']
    .map((status) => ({ name: status, records: statusCount(attendanceRows, status) }))
    .filter((row) => row.records !== null && row.records > 0)
  const payrollChartRows = (payrollRows || [])
    .map((record) => ({
      period: record?.pay_period?.end || record?.pay_period?.start || 'Unknown',
      net: numericValue(record?.net_salary),
    }))
    .filter((record) => record.net !== null)
    .reverse()

  return {
    kpis: [
      {
        label: `Remaining leave days (${year})`,
        value: remainingDays === null ? 'Unavailable' : formatCount(remainingDays),
        detail: balances ? `${balances.length} leave types` : 'Personal leave balances.',
      },
      {
        label: `Pending leave requests (${year})`,
        value: formatCount(leave?.summary?.pending),
        detail: `Approved days: ${formatCount(leave?.summary?.total_approved_days)}`,
      },
      {
        label: 'Attendance records',
        value: attendanceRows ? formatCount(attendanceRows.length) : 'Unavailable',
        detail: attendanceRows ? `Showing ${attendanceRows.length} of the latest 100 personal records · ${attendanceHours === null ? 'hours unavailable' : `${formatAmount(attendanceHours)} hours`}` : 'Personal attendance only.',
      },
      {
        label: `Processed payroll (${year})`,
        value: formatCount(payroll?.summary?.total_payroll_records),
        detail: `Net pay total: ${netPayroll === null ? 'Unavailable' : formatAmount(netPayroll)}`,
      },
    ],
    charts: [
      {
        title: `Leave requests by status (${year})`,
        type: 'bar',
        xKey: 'name',
        data: requests
          ? ['Pending', 'Approved', 'Rejected', 'Cancelled'].map((status) => ({ name: status, requests: statusCount(requests, status) || 0 }))
          : [],
        series: [{ key: 'requests', label: 'Requests' }],
      },
      {
        title: 'Attendance status · latest 100 records',
        type: 'pie',
        data: attendanceStatuses.map((row) => ({ name: row.name, value: row.records })),
      },
      {
        title: `Processed net pay by period (${year})`,
        type: 'line',
        xKey: 'period',
        data: payrollChartRows,
        series: [{ key: 'net', label: 'Net pay' }],
      },
    ],
  }
}

function EmployeeDashboard() {
  const navigate = useNavigate()
  const [user] = useState(readStoredUser)
  const [sessionStatus, setSessionStatus] = useState({ loading: true, ok: false, message: '' })
  const currentYear = String(new Date().getFullYear())
  const [insightsYear, setInsightsYear] = useState(currentYear)
  const [insightsState, setInsightsState] = useState({ loading: true, year: '', leave: null, attendance: null, payroll: null, error: '' })

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
    if (!user) {
      navigate('/login', { replace: true })
      return
    }

    if (user.role_name !== 'Employee') navigate('/dashboard', { replace: true })
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

  useEffect(() => {
    if (!user || user.role_name !== 'Employee') return undefined

    let isActive = true

    const loadPersonalInsights = async () => {
      const results = await Promise.allSettled([
        apiClient.get('/api/self-service/leave.php', { params: { year: Number(insightsYear) } }),
        apiClient.get('/api/self-service/attendance.php', { params: { page: 1, limit: 100 } }),
        apiClient.get('/api/self-service/payroll.php', { params: { year: Number(insightsYear) } }),
      ])

      if (!isActive) return

      const [leaveResult, attendanceResult, payrollResult] = results
      const errors = []
      const readObjectData = (result, label) => {
        if (result.status !== 'fulfilled' || result.value.data?.success === false) {
          errors.push(`${label} data is unavailable.`)
          return null
        }
        const value = result.value.data?.data
        if (!value || typeof value !== 'object' || Array.isArray(value)) {
          errors.push(`${label} response was unavailable.`)
          return null
        }
        return value
      }
      const leave = readObjectData(leaveResult, 'Leave')
      const payroll = readObjectData(payrollResult, 'Payroll')
      let attendance = null

      if (attendanceResult.status === 'fulfilled' && attendanceResult.value.data?.success !== false
        && Array.isArray(attendanceResult.value.data?.data)) {
        attendance = attendanceResult.value.data.data
      } else {
        errors.push('Attendance data is unavailable.')
      }

      setInsightsState({ loading: false, year: insightsYear, leave, attendance, payroll, error: errors.join(' ') })
    }

    loadPersonalInsights().catch(() => {
      if (isActive) setInsightsState({ loading: false, year: insightsYear, leave: null, attendance: null, payroll: null, error: 'Personal dashboard data could not be loaded.' })
    })

    return () => {
      isActive = false
    }
  }, [user, insightsYear])

  if (!user) return null

  const insights = employeeInsights(insightsState, insightsYear)
  const yearOptions = Array.from({ length: 5 }, (_, index) => {
    const year = new Date().getFullYear() - index
    return { value: String(year), label: String(year) }
  })
  const resetYearFilter = () => setInsightsYear(currentYear)

  return (
    <WorkspaceDashboard
      user={user}
      sessionStatus={sessionStatus}
      title="Employee Dashboard"
      summary="Keep your payslips, leave, attendance, benefits, documents, and progress within easy reach."
      statusLabel="Employee self-service"
      modules={employeeModules}
      insights={<DashboardInsights
        title="My HR summary"
        description="Personal leave and processed payroll use the selected year. Attendance charts use your latest 100 records."
        loading={insightsState.loading || insightsState.year !== insightsYear}
        error={insightsState.year === insightsYear ? insightsState.error : ''}
        kpis={insights.kpis}
        charts={insights.charts}
        filters={[
          { id: 'personal-year', label: 'Leave and payroll year', type: 'select', value: insightsYear, onChange: (event) => setInsightsYear(event.target.value), options: yearOptions },
        ]}
        filterSummary={`Showing personal leave and processed payroll for ${insightsYear}; attendance remains the latest 100 records.`}
        onResetFilters={resetYearFilter}
      />}
      onSignOut={handleSignOut}
    />
  )
}

export default EmployeeDashboard
