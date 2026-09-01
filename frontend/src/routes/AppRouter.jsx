import { Navigate, Route, Routes } from 'react-router-dom'
import EmployeeDashboard from '../pages/EmployeeDashboard'
import EmployeeDirectory from '../pages/EmployeeDirectory'
import LoginPage from '../pages/LoginPage'

function AppRouter() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/dashboard" element={<EmployeeDashboard />} />
      <Route path="/employees" element={<EmployeeDirectory />} />
    </Routes>
  )
}

export default AppRouter
