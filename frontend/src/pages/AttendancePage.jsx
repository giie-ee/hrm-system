import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

function getErrorMessage(error, fallback) {
  return error.response?.data?.message || fallback
}

function AttendancePage() {
  const storedUser = localStorage.getItem('hrms_user')
  const user = storedUser ? JSON.parse(storedUser) : null
  const isEmployee = user?.role_name === 'Employee'
  const [records, setRecords] = useState([])
  const [employeeId, setEmployeeId] = useState('')
  const [status, setStatus] = useState('')
  const [date, setDate] = useState('')
  const [loading, setLoading] = useState(true)
  const [action, setAction] = useState('')
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const loadRecords = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      const params = { date: date || undefined, status: status || undefined }
      if (!isEmployee && employeeId) params.employee_id = employeeId
      const response = await apiClient.get('/api/attendance/get.php', { params })
      setRecords(response.data.data || [])
    } catch (requestError) {
      setRecords([])
      setError(getErrorMessage(requestError, 'Unable to load attendance records.'))
    } finally {
      setLoading(false)
    }
  }, [date, status, employeeId, isEmployee])

  useEffect(() => {
    const load = async () => {
      await loadRecords()
    }

    load()
  }, [loadRecords])

  const runAction = async (name, endpoint) => {
    setAction(name)
    setError('')
    setSuccess('')

    try {
      const response = await apiClient.post(endpoint, {})
      setSuccess(response.data.message || 'Attendance action completed successfully.')
      await loadRecords()
    } catch (requestError) {
      setError(getErrorMessage(requestError, 'The attendance action could not be completed.'))
    } finally {
      setAction('')
    }
  }

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6"><div><p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">HRMS</p><h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Attendance</h1><p className="mt-2 text-sm text-slate-600">Live attendance records and daily check-in controls.</p></div><div className="flex flex-wrap items-center gap-3"><nav aria-label="Leave and attendance" className="flex rounded-lg bg-slate-100 p-1"><Link to="/leave" className="rounded-md px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900">Leave Management</Link><Link to="/attendance" className="rounded-md bg-white px-3 py-2 text-sm font-medium text-sky-700 shadow-sm">Attendance</Link></nav><Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link></div></header>

        {error && <div role="alert" className="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
        {success && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{success}</div>}

        <section className="panel-surface mb-6 p-5 sm:p-6">
          <div className="grid gap-4 md:grid-cols-4 md:items-end">
            {!isEmployee && <label><span className="mb-2 block text-sm font-medium text-slate-700">Employee ID</span><input className="field-input" type="number" min="1" value={employeeId} onChange={(event) => setEmployeeId(event.target.value)} placeholder="All employees" /></label>}
            <label><span className="mb-2 block text-sm font-medium text-slate-700">Date</span><input className="field-input" type="date" value={date} onChange={(event) => setDate(event.target.value)} /></label>
            <label><span className="mb-2 block text-sm font-medium text-slate-700">Status</span><select className="field-input" value={status} onChange={(event) => setStatus(event.target.value)}><option value="">All statuses</option><option value="Present">Present</option><option value="Absent">Absent</option><option value="Late">Late</option><option value="Half-Day">Half-Day</option><option value="On Leave">On Leave</option></select></label>
            {isEmployee && <div className="flex flex-wrap gap-3"><button type="button" disabled={Boolean(action) || loading} onClick={() => runAction('check-in', '/api/attendance/check-in.php')} className="action-button-primary">{action === 'check-in' ? 'Checking in...' : 'Check in'}</button><button type="button" disabled={Boolean(action) || loading} onClick={() => runAction('check-out', '/api/attendance/check-out.php')} className="action-button-secondary">{action === 'check-out' ? 'Checking out...' : 'Check out'}</button></div>}
          </div>
          {!isEmployee && <p className="mt-4 text-sm text-slate-600">Administrative attendance creation is not exposed in the current UI; use the existing API when a manual-entry workflow is needed.</p>}
        </section>

        <section className="panel-surface p-5 sm:p-6"><div className="mb-4 flex items-center justify-between gap-3"><h2 className="text-lg font-bold text-slate-900">Attendance records</h2><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{loading ? 'Loading' : `${records.length} records`}</span></div>{loading ? <p className="py-6 text-center text-sm text-slate-600">Loading attendance records...</p> : records.length === 0 ? <p className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-600">No attendance records found.</p> : <div className="overflow-x-auto rounded-xl border border-slate-200"><table className="min-w-full text-left text-sm"><thead className="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500"><tr><th className="px-4 py-3">Employee</th><th className="px-4 py-3">Date</th><th className="px-4 py-3">Check in</th><th className="px-4 py-3">Check out</th><th className="px-4 py-3">Hours</th><th className="px-4 py-3">Status</th></tr></thead><tbody className="divide-y divide-slate-200">{records.map((record) => <tr key={record.attendance_id} className="bg-white"><td className="px-4 py-3 text-slate-700">{record.employee_name || record.employee_id}</td><td className="px-4 py-3 text-slate-700">{record.attendance_date}</td><td className="px-4 py-3 text-slate-700">{record.check_in || 'Not recorded'}</td><td className="px-4 py-3 text-slate-700">{record.check_out || 'Not recorded'}</td><td className="px-4 py-3 text-slate-700">{record.hours_worked}</td><td className="px-4 py-3 text-slate-700">{record.status}</td></tr>)}</tbody></table></div>}</section>
      </div>
    </main>
  )
}

export default AttendancePage
