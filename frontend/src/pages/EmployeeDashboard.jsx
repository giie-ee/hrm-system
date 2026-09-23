import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import apiClient from '../api/client'
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

function EmployeeDashboard() {
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

  if (!user) return null

  return (
    <WorkspaceDashboard
      user={user}
      sessionStatus={sessionStatus}
      title="Employee Dashboard"
      summary="Keep your payslips, leave, attendance, benefits, documents, and progress within easy reach."
      statusLabel="Employee self-service"
      modules={employeeModules}
      onSignOut={handleSignOut}
    />
  )
}

export default EmployeeDashboard
