import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import apiClient from '../api/client'

const roleDashboardContent = {
  Admin: {
    title: 'Admin Dashboard',
    summary: 'Administration access is active. Use the available backend checks and existing employee listing from here.',
    statusLabel: 'Administrator access',
    accent: 'sky',
    cards: [
      {
        title: 'Employee Directory',
        description: 'Open the existing backend employee listing.',
        href: '/employees',
        internal: true,
        availability: 'Available',
      },
      {
        title: 'Admin Access Check',
        description: 'Verify the authenticated administrator session against the PHP backend.',
        href: 'http://localhost:8000/api/test-admin.php',
        availability: 'Available',
      },
      {
        title: 'Payroll',
        description: 'Create, calculate, and process payroll through the existing authenticated PHP workflow.',
        href: '/payroll',
        internal: true,
        availability: 'Available',
      },
      {
        title: 'Leave and Attendance',
        description: 'Review leave requests, balances, and attendance records.',
        href: '/leave',
        internal: true,
        availability: 'Available',
      },
      {
        title: 'Documents',
        description: 'Open the documents workspace. Document data is waiting on a backend API.',
        href: '/documents',
        internal: true,
        availability: 'Frontend shell',
      },
      {
        title: 'Onboarding Forms',
        description: 'Open the onboarding workspace. Save and review actions are waiting on a backend API.',
        href: '/onboarding',
        internal: true,
        availability: 'Frontend shell',
      },
    ],
  },
  HR: {
    title: 'HR Dashboard',
    summary: 'Your HR workspace is ready for HR-specific modules as the backend permissions and APIs become available.',
    statusLabel: 'HR role identified',
    accent: 'emerald',
    cards: [
      {
        title: 'Employee Directory',
        description: 'Open the existing backend employee listing.',
        href: '/employees',
        internal: true,
        availability: 'Available',
      },
      {
        title: 'HR tools',
        description: 'HR-specific employee management workflows require a backend role-protected API.',
        availability: 'Backend dependency',
      },
      {
        title: 'Payroll',
        description: 'Create, calculate, and process payroll through the existing authenticated PHP workflow.',
        href: '/payroll',
        internal: true,
        availability: 'Available',
      },
      {
        title: 'Leave',
        description: 'Review leave requests, balances, and approvals.',
        href: '/leave',
        internal: true,
        availability: 'Available',
      },
      {
        title: 'Attendance',
        description: 'Review attendance records and supported daily actions.',
        href: '/attendance',
        internal: true,
        availability: 'Available',
      },
      {
        title: 'Documents',
        description: 'Open the documents workspace. Document data is waiting on a backend API.',
        href: '/documents',
        internal: true,
        availability: 'Frontend shell',
      },
      {
        title: 'Onboarding Forms',
        description: 'Open the onboarding workspace. Review workflows are waiting on a backend API.',
        href: '/onboarding',
        internal: true,
        availability: 'Frontend shell',
      },
    ],
  },
  Manager: {
    title: 'Manager Dashboard',
    summary: 'Your manager workspace is ready for team-focused modules as manager permissions and APIs become available.',
    statusLabel: 'Manager role identified',
    accent: 'amber',
    cards: [
      {
        title: 'Employee Directory',
        description: 'Open the existing backend employee listing.',
        href: '/employees',
        internal: true,
        availability: 'Available',
      },
      {
        title: 'Team overview',
        description: 'Manager-specific team data is waiting on a backend endpoint and permission contract.',
        availability: 'Backend dependency',
      },
      {
        title: 'Leave and attendance',
        description: 'Review leave requests and team attendance records.',
        href: '/leave',
        internal: true,
        availability: 'Available',
      },
      {
        title: 'Payroll',
        description: 'Create, calculate, and process payroll through the existing authenticated PHP workflow.',
        href: '/payroll',
        internal: true,
        availability: 'Available',
      },
      {
        title: 'Documents',
        description: 'Open the documents workspace. Document data is waiting on a backend API.',
        href: '/documents',
        internal: true,
        availability: 'Frontend shell',
      },
      {
        title: 'Onboarding Forms',
        description: 'Open the onboarding workspace. Review workflows are waiting on a backend API.',
        href: '/onboarding',
        internal: true,
        availability: 'Frontend shell',
      },
      {
        title: 'Attendance',
        description: 'Review attendance records for the team.',
        href: '/attendance',
        internal: true,
        availability: 'Available',
      },
    ],
  },
}

const accentStyles = {
  sky: 'bg-sky-100 text-sky-700',
  emerald: 'bg-emerald-100 text-emerald-700',
  amber: 'bg-amber-100 text-amber-700',
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
  const [sessionStatus, setSessionStatus] = useState(() => {
    if (user?.role_name === 'HR' || user?.role_name === 'Manager') {
      return {
        loading: false,
        ok: false,
        message: 'No role-specific session check endpoint is available yet.',
      }
    }

    return { loading: true, ok: false, message: '' }
  })

  const handleSignOut = async () => {
    try {
      await apiClient.post('/api/auth/logout.php')
    } catch {
      return
    } finally {
      localStorage.removeItem('hrms_user')
      localStorage.removeItem('hrms_token')
      navigate('/login', { replace: true })
    }
  }

  useEffect(() => {
    if (!user) {
      navigate('/login', { replace: true })
    }
  }, [navigate, user])

  useEffect(() => {
    if (!user || user.role_name === 'HR' || user.role_name === 'Manager') return

    const endpoint = user.role_name === 'Admin' ? '/api/test-admin.php' : '/api/test-employee.php'

    apiClient
      .get(endpoint)
      .then((response) => {
        setSessionStatus({
          loading: false,
          ok: true,
          message: response.data.message || 'Session verified.',
        })
      })
      .catch((error) => {
        setSessionStatus({
          loading: false,
          ok: false,
          message: error.response?.data?.message || 'Session verification unavailable.',
        })
      })
  }, [user])

  if (!user) return null

  if (user.role_name === 'Employee') {
    return <NavigateToEmployeeDashboard />
  }

  const content = roleDashboardContent[user.role_name]

  if (!content) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-slate-100 p-6">
        <section className="panel-surface w-full max-w-lg p-6 text-center sm:p-8">
          <p className="section-label">HRMS</p>
          <h1 className="mt-3 text-2xl font-bold tracking-tight text-slate-900">Dashboard unavailable</h1>
          <p className="mt-3 text-sm text-slate-600">
            The backend returned the role “{user.role_name}”, but no React dashboard has been defined for it yet.
          </p>
          <button type="button" onClick={() => navigate('/login', { replace: true })} className="action-button-secondary mt-6">
            Return to login
          </button>
        </section>
      </main>
    )
  }

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 p-4 sm:p-6">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">HRMS</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{content.title}</h1>
              <p className="mt-2 max-w-2xl text-sm text-slate-600">{content.summary}</p>
            </div>

            <div className="flex items-center gap-3">
              <span className={`rounded-full px-3 py-1 text-xs font-medium ${accentStyles[content.accent]}`}>
                {content.statusLabel}
              </span>
              <button
                type="button"
                onClick={handleSignOut}
                className="action-button-secondary"
              >
                Sign out
              </button>
            </div>
          </div>
        </header>

        <section className="mb-6 grid gap-4 md:grid-cols-3">
          <div className="panel-surface p-5">
            <p className="text-sm text-slate-500">Welcome</p>
            <p className="mt-2 text-xl font-bold tracking-tight text-slate-900">{user.username}</p>
          </div>
          <div className="panel-surface p-5">
            <p className="text-sm text-slate-500">Role</p>
            <p className="mt-2 text-xl font-bold tracking-tight text-slate-900">{user.role_name}</p>
          </div>
          <div className="panel-surface p-5">
            <p className="text-sm text-slate-500">Session status</p>
            <p className={`mt-2 text-lg font-bold ${sessionStatus.ok ? 'text-emerald-600' : 'text-amber-600'}`}>
              {sessionStatus.loading ? 'Checking...' : sessionStatus.ok ? 'Verified' : 'Pending'}
            </p>
          </div>
        </section>

        <section className="mb-6 grid gap-6 lg:grid-cols-[1.5fr_1fr]">
          <div className="panel-surface p-5 sm:p-6">
            <div className="mb-4 flex items-center justify-between gap-3">
              <h2 className="text-lg font-bold text-slate-900">Role workspace</h2>
              <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Role-aware shell</span>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                <p className="section-label">Username</p>
                <p className="mt-2 text-base font-semibold text-slate-900">{user.username}</p>
              </div>
              <div className="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                <p className="section-label">Email</p>
                <p className="mt-2 text-base font-semibold text-slate-900">{user.email || 'Unavailable'}</p>
              </div>
              <div className="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                <p className="section-label">User ID</p>
                <p className="mt-2 text-base font-semibold text-slate-900">{user.user_id}</p>
              </div>
              <div className="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                <p className="section-label">Role ID</p>
                <p className="mt-2 text-base font-semibold text-slate-900">{user.role_id}</p>
              </div>
            </div>
          </div>

          <div className="panel-surface p-5 sm:p-6">
            <h2 className="text-lg font-bold text-slate-900">Access status</h2>
            <div aria-live="polite" className={`mt-4 rounded-xl border p-4 text-sm ${sessionStatus.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-600'}`}>
              {sessionStatus.loading ? 'Checking the current PHP session...' : sessionStatus.message}
            </div>
          </div>
        </section>

        <section>
          <div className="mb-4">
            <h2 className="text-lg font-bold text-slate-900">Role capabilities</h2>
            <p className="mt-1 text-sm text-slate-600">Only currently exposed backend functionality is marked available.</p>
          </div>
          <div className="grid gap-4 lg:grid-cols-3">
            {content.cards.map((card) => {
              const cardClassName = `panel-surface block p-5 ${card.availability === 'Available' ? 'transition duration-200 hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md' : ''}`
              const cardContent = (
                <>
                  <span className={`mb-3 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${card.availability === 'Available' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>
                    {card.availability}
                  </span>
                  <h3 className="text-lg font-semibold text-slate-900">{card.title}</h3>
                  <p className="mt-2 text-sm text-slate-600">{card.description}</p>
                </>
              )

              return card.internal ? (
                <Link key={card.title} to={card.href} className={cardClassName}>
                  {cardContent}
                </Link>
              ) : card.href ? (
                <a key={card.title} href={card.href} target="_blank" rel="noreferrer" className={cardClassName}>
                  {cardContent}
                </a>
              ) : (
                <div key={card.title} className={cardClassName}>
                  {cardContent}
                </div>
              )
            })}
          </div>
        </section>
      </div>
    </main>
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
