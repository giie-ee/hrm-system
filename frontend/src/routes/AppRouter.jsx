import { useCallback, useEffect, useState } from 'react'
import { Link, Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { isLockedByOnboarding, readStoredUser, refreshSession } from '../api/session'
import ChangePasswordPage from '../pages/ChangePasswordPage'
import EmployeeDashboard from '../pages/EmployeeDashboard'
import EmployeeDirectory from '../pages/EmployeeDirectory'
import DocumentsPage from '../pages/DocumentsPage'
import BenefitsPage from '../pages/BenefitsPage'
import OnboardingPage from '../pages/OnboardingPage'
import ProgressTrackerPage from '../pages/ProgressTrackerPage'
import AttendancePage from '../pages/AttendancePage'
import LeavePage from '../pages/LeavePage'
import LoginPage from '../pages/LoginPage'
import PayrollPage from '../pages/PayrollPage'
import PayslipPage from '../pages/PayslipPage'
import ReportsPage from '../pages/ReportsPage'
import RoleDashboard from '../pages/RoleDashboard'

// The stored onboarding flag can be stale (HR may have completed the onboarding
// since sign-in), so confirm with the server before turning someone away.
function RecheckAccess({ pathname, children }) {
  const [verdict, setVerdict] = useState('checking')

  useEffect(() => {
    let active = true
    refreshSession()
      .then(({ user }) => { if (active) setVerdict(isLockedByOnboarding(user, pathname) ? 'locked' : 'allowed') })
      .catch(() => { if (active) setVerdict('locked') })
    return () => { active = false }
  }, [pathname])

  if (verdict === 'checking') return <p className="p-6 text-sm text-slate-600">Checking your access...</p>
  if (verdict === 'locked') return <Navigate to="/onboarding" replace />
  return children
}

function ProtectedRoute({ children }) {
  const location = useLocation()
  const storedUser = localStorage.getItem('hrms_user')

  if (!storedUser) {
    return <Navigate to="/login" replace />
  }

  let redirectTo = null
  let recheck = false

  try {
    const user = JSON.parse(storedUser)

    if (!user || typeof user !== 'object' || !user.role_name) {
      throw new Error('Invalid stored user.')
    }

    if (user.must_change_password && location.pathname !== '/change-password') {
      redirectTo = '/change-password'
    } else if (isLockedByOnboarding(user, location.pathname)) {
      recheck = true
    }
  } catch {
    localStorage.removeItem('hrms_user')
    return <Navigate to="/login" replace />
  }

  if (redirectTo) {
    return <Navigate to={redirectTo} replace />
  }

  if (recheck) {
    return <RecheckAccess pathname={location.pathname}>{children}</RecheckAccess>
  }

  return children
}

// Keeps onboarding_required / must_change_password current on every navigation,
// so unlocking (or locking) takes effect without signing in again.
function SessionSync({ onChange }) {
  const location = useLocation()

  useEffect(() => {
    if (location.pathname === '/login' || !readStoredUser()) return undefined
    let active = true
    refreshSession().then(({ changed }) => { if (active && changed) onChange() }).catch(() => {})
    return () => { active = false }
  }, [location.pathname, onChange])

  return null
}

function OnboardingBanner() {
  const location = useLocation()
  const user = readStoredUser()
  if (!user?.onboarding_required || location.pathname === '/login' || location.pathname === '/change-password') return null

  return (
    <div className="sticky top-0 z-50 border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900" role="status">
      <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2">
        <span>Finish your onboarding to unlock leave, payroll, benefits and the rest of your workspace.</span>
        {location.pathname !== '/onboarding' && <Link to="/onboarding" className="font-semibold underline">Go to onboarding</Link>}
      </div>
    </div>
  )
}

function RoleProtectedRoute({ children, allowedRoles }) {
  const storedUser = localStorage.getItem('hrms_user')
  let isUnauthorized

  if (!storedUser) {
    return <Navigate to="/login" replace />
  }

  try {
    const user = JSON.parse(storedUser)

    if (!user || typeof user !== 'object' || !user.role_name) {
      throw new Error('Invalid stored user.')
    }

    isUnauthorized = !allowedRoles.includes(user.role_name)
  } catch {
    localStorage.removeItem('hrms_user')
    return <Navigate to="/login" replace />
  }

  if (isUnauthorized) {
    return <Navigate to="/dashboard" replace />
  }

  return children
}

function AppRouter() {
  const [, setSessionVersion] = useState(0)
  const handleSessionChange = useCallback(() => setSessionVersion((version) => version + 1), [])

  return (
    <>
    <SessionSync onChange={handleSessionChange} />
    <OnboardingBanner />
    <Routes>
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/change-password" element={<ProtectedRoute><ChangePasswordPage /></ProtectedRoute>} />
      <Route path="/dashboard" element={<ProtectedRoute><RoleProtectedRoute allowedRoles={['Admin', 'HR', 'Manager', 'Employee']}><RoleDashboard /></RoleProtectedRoute></ProtectedRoute>} />
      <Route path="/dashboard/employee" element={<ProtectedRoute><RoleProtectedRoute allowedRoles={['Employee']}><EmployeeDashboard /></RoleProtectedRoute></ProtectedRoute>} />
      <Route path="/employees" element={<ProtectedRoute><RoleProtectedRoute allowedRoles={['Admin', 'HR', 'Manager']}><EmployeeDirectory /></RoleProtectedRoute></ProtectedRoute>} />
      <Route path="/documents" element={<ProtectedRoute><DocumentsPage /></ProtectedRoute>} />
      <Route path="/benefits" element={<ProtectedRoute><BenefitsPage /></ProtectedRoute>} />
      <Route path="/onboarding" element={<ProtectedRoute><OnboardingPage /></ProtectedRoute>} />
      <Route path="/progress-tracker" element={<ProtectedRoute><ProgressTrackerPage /></ProtectedRoute>} />
      <Route path="/leave" element={<ProtectedRoute><RoleProtectedRoute allowedRoles={['Admin', 'HR', 'Manager', 'Employee']}><LeavePage /></RoleProtectedRoute></ProtectedRoute>} />
      <Route path="/attendance" element={<ProtectedRoute><RoleProtectedRoute allowedRoles={['Admin', 'HR', 'Manager', 'Employee']}><AttendancePage /></RoleProtectedRoute></ProtectedRoute>} />
      <Route path="/payroll" element={<ProtectedRoute><PayrollPage /></ProtectedRoute>} />
      <Route path="/payslip" element={<ProtectedRoute><PayslipPage /></ProtectedRoute>} />
      <Route path="/reports" element={<ProtectedRoute><RoleProtectedRoute allowedRoles={['Admin', 'HR']}><ReportsPage /></RoleProtectedRoute></ProtectedRoute>} />
    </Routes>
    </>
  )
}

export default AppRouter
