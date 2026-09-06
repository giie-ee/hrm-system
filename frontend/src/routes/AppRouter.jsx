import { Navigate, Route, Routes } from 'react-router-dom'
import EmployeeDashboard from '../pages/EmployeeDashboard'
import EmployeeDirectory from '../pages/EmployeeDirectory'
import DocumentsPage from '../pages/DocumentsPage'
import LoginPage from '../pages/LoginPage'
import PayrollPage from '../pages/PayrollPage'
import RoleDashboard from '../pages/RoleDashboard'

function AppRouter() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/dashboard" element={<RoleDashboard />} />
      <Route path="/dashboard/employee" element={<EmployeeDashboard />} />
      <Route path="/employees" element={<EmployeeDirectory />} />
      <Route path="/documents" element={<DocumentsPage />} />
      <Route path="/payroll" element={<PayrollPage />} />
    </Routes>
  )
}

export default AppRouter
