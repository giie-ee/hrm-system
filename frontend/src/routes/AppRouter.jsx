import { Navigate, Route, Routes } from 'react-router-dom'
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
import RoleDashboard from '../pages/RoleDashboard'

function ProtectedRoute({ children }) {
  const storedUser = localStorage.getItem('hrms_user')

  if (!storedUser) {
    return <Navigate to="/login" replace />
  }

  try {
    const user = JSON.parse(storedUser)

    if (!user || typeof user !== 'object' || !user.role_name) {
      throw new Error('Invalid stored user.')
    }
  } catch {
    localStorage.removeItem('hrms_user')
    return <Navigate to="/login" replace />
  }

  return children
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
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<LoginPage />} />
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
    </Routes>
  )
}

export default AppRouter
