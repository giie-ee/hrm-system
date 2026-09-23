import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import apiClient from '../api/client'
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
    modules: [
      sharedModules.directory,
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
    modules: [
      sharedModules.directory,
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

function RoleDashboard() {
  const navigate = useNavigate()
  const [user] = useState(readStoredUser)
  const [sessionStatus, setSessionStatus] = useState({ loading: true, ok: false, message: '' })

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

  if (!user) return null

  if (user.role_name === 'Employee') return <NavigateToEmployeeDashboard />

  const content = roleDashboardContent[user.role_name]

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

  return (
    <WorkspaceDashboard
      user={user}
      sessionStatus={sessionStatus}
      title={content.title}
      summary={content.summary}
      statusLabel={content.statusLabel}
      modules={content.modules}
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
