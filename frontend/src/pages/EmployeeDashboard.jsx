import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import apiClient from '../api/client'

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
  const [sessionStatus, setSessionStatus] = useState({
    loading: true,
    ok: false,
    message: '',
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
      return
    }

    if (user.role_name !== 'Employee') {
      navigate('/dashboard', { replace: true })
    }
  }, [navigate, user])

  useEffect(() => {
    if (!user) return

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

  if (!user) {
    return null
  }

  const quickLinks = [
    {
      title: 'Employee Directory',
      description: 'Open the HRMS employee directory screen.',
      to: '/employees',
      tone: 'sky',
    },
    {
      title: 'Payroll',
      description: 'Create and process a payroll record through the existing PHP workflow.',
      to: '/payroll',
      tone: 'amber',
    },
    {
      title: 'Payslip',
      description: 'View a payslip from your available payroll records.',
      to: '/payslip',
      tone: 'sky',
    },
    {
      title: 'Documents',
      description: 'Open the documents workspace and backend availability status.',
      to: '/documents',
      tone: 'emerald',
    },
    {
      title: 'Benefits',
      description: 'Review your benefits workspace and enrollment availability.',
      to: '/benefits',
      tone: 'amber',
    },
    {
      title: 'Onboarding Forms',
      description: 'Complete your onboarding information and review required fields.',
      to: '/onboarding',
      tone: 'sky',
    },
    {
      title: 'Progress Tracker',
      description: 'View your onboarding and employee lifecycle progress.',
      to: '/progress-tracker',
      tone: 'emerald',
    },
    {
      title: 'Leave',
      description: 'Submit and review your leave requests and balances.',
      to: '/leave',
      tone: 'sky',
    },
    {
      title: 'Attendance',
      description: 'Review your attendance and use daily check-in controls.',
      to: '/attendance',
      tone: 'emerald',
    },
    {
      title: user.role_name === 'Admin' ? 'Admin Access Check' : 'Employee Access Check',
      description: 'Validate the current authenticated session.',
      href: user.role_name === 'Admin' ? '/api/test-admin.php' : '/api/test-employee.php',
      target: '_blank',
      rel: 'noreferrer',
      tone: 'emerald',
    },
  ]

  const quickLinkStyles = {
    sky: 'border-sky-200 bg-sky-50 text-sky-700',
    amber: 'border-amber-200 bg-amber-50 text-amber-700',
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  }

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 p-4 sm:p-6">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">HRMS</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Employee Dashboard</h1>
            </div>

            <div className="flex items-center gap-3">
              <span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-medium text-sky-700">
                {user.role_name}
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

        <section className="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <div className="panel-surface p-5">
            <p className="text-sm text-slate-500">Welcome</p>
            <p className="mt-2 text-xl font-bold tracking-tight text-slate-900">{user.username}</p>
          </div>

          <div className="panel-surface p-5">
            <p className="text-sm text-slate-500">Employee ID</p>
            <p className="mt-2 text-xl font-bold tracking-tight text-slate-900">{user.employee_id}</p>
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
              <h2 className="text-lg font-bold text-slate-900">My account</h2>
              <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Active session</span>
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
                <p className="section-label">Authentication</p>
                <p className="mt-2 text-base font-semibold text-slate-900">{sessionStatus.loading ? 'Checking...' : sessionStatus.ok ? 'Authenticated' : 'Not confirmed'}</p>
              </div>
            </div>
          </div>

          <div className="panel-surface p-5 sm:p-6">
            <h2 className="text-lg font-bold text-slate-900">Access status</h2>
            <div
              aria-live="polite"
              className={`mt-4 rounded-xl border p-4 text-sm ${sessionStatus.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-600'}`}
            >
              {sessionStatus.loading
                ? 'Checking your current PHP session...'
                : sessionStatus.message || 'No session response yet.'}
            </div>
          </div>
        </section>

        <section className="mb-6">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="text-lg font-bold text-slate-900">Quick links</h2>
          </div>

          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {quickLinks.map((link) =>
              link.to ? (
                <Link
                  key={link.title}
                  to={link.to}
                  className={`panel-surface block p-5 transition duration-200 hover:-translate-y-0.5 hover:shadow-md ${link.tone === 'amber' ? 'hover:border-amber-300' : link.tone === 'emerald' ? 'hover:border-emerald-300' : 'hover:border-sky-300'}`}
                >
                  <div className={`mb-3 inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${quickLinkStyles[link.tone]}`}>
                    Available
                  </div>
                  <h3 className="text-lg font-semibold text-slate-900">{link.title}</h3>
                  <p className="mt-2 text-sm text-slate-600">{link.description}</p>
                </Link>
              ) : (
                <a
                  key={link.title}
                  href={link.href}
                  target={link.target}
                  rel={link.rel}
                  className="panel-surface block p-5 transition duration-200 hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md"
                >
                  <div className={`mb-3 inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${quickLinkStyles[link.tone]}`}>
                    Available
                  </div>
                  <h3 className="text-lg font-semibold text-slate-900">{link.title}</h3>
                  <p className="mt-2 text-sm text-slate-600">{link.description}</p>
                </a>
              ),
            )}
          </div>
        </section>

        <section className="grid gap-4 lg:grid-cols-3">
          <div className="panel-surface p-5">
            <h3 className="text-base font-semibold text-slate-900">Employee Directory</h3>
            <p className="mt-3 text-sm text-slate-600">
              Existing backend listing is available at the PHP employee page, but it is not yet connected to a JSON API for the React UI.
            </p>
          </div>

          <div className="panel-surface p-5">
            <h3 className="text-base font-semibold text-slate-900">Payroll</h3>
            <p className="mt-3 text-sm text-slate-600">
              Payroll endpoints already exist in the backend, but the payroll UI and data binding are still pending implementation.
            </p>
          </div>

          <div className="panel-surface p-5">
            <h3 className="text-base font-semibold text-slate-900">Leave & Attendance</h3>
            <p className="mt-3 text-sm text-slate-600">
              Leave and attendance workflows are connected to the authenticated backend APIs.
            </p>
          </div>
        </section>
      </div>
    </main>
  )
}

export default EmployeeDashboard
