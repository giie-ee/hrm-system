import { Navigate, Route, Routes } from 'react-router-dom'
import EmployeeDashboard from '../pages/EmployeeDashboard'
import EmployeeDirectory from '../pages/EmployeeDirectory'
import DocumentsPage from '../pages/DocumentsPage'
import OnboardingPage from '../pages/OnboardingPage'
import AttendancePage from '../pages/AttendancePage'
import LeavePage from '../pages/LeavePage'
import LoginPage from '../pages/LoginPage'
import PayrollPage from '../pages/PayrollPage'
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

function AppRouter() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/dashboard" element={<ProtectedRoute><RoleDashboard /></ProtectedRoute>} />
      <Route path="/dashboard/employee" element={<ProtectedRoute><EmployeeDashboard /></ProtectedRoute>} />
      <Route path="/employees" element={<ProtectedRoute><EmployeeDirectory /></ProtectedRoute>} />
      <Route path="/documents" element={<ProtectedRoute><DocumentsPage /></ProtectedRoute>} />
      <Route path="/onboarding" element={<ProtectedRoute><OnboardingPage /></ProtectedRoute>} />
      <Route path="/leave" element={<ProtectedRoute><LeavePage /></ProtectedRoute>} />
      <Route path="/attendance" element={<ProtectedRoute><AttendancePage /></ProtectedRoute>} />
      <Route path="/payroll" element={<ProtectedRoute><PayrollPage /></ProtectedRoute>} />
    </Routes>
  )
}

export default AppRouter
