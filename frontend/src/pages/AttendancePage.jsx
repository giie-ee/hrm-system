import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

function getErrorMessage(error, fallback) {
  return error.response?.data?.message || fallback
}

const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
const attendanceStatuses = ['Present', 'Absent', 'Late', 'Present (Half Day)', 'Late (Half Day)', 'Half-Day', 'Early Checkout', 'On Leave', 'Off Schedule']

function displayTime(value) {
  return value ? String(value).slice(0, 5) : '—'
}

function AttendancePage() {
  const storedUser = localStorage.getItem('hrms_user')
  const user = storedUser ? JSON.parse(storedUser) : null
  const isEmployee = user?.role_name === 'Employee'
  const isAdmin = user?.role_name === 'Admin'
  const [records, setRecords] = useState([])
  const [employeeId, setEmployeeId] = useState('')
  const [status, setStatus] = useState('')
  const [date, setDate] = useState('')
  const [loading, setLoading] = useState(true)
  const [action, setAction] = useState('')
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [policy, setPolicy] = useState(null)
  const [policyLoading, setPolicyLoading] = useState(true)
  const [policySaving, setPolicySaving] = useState('')
  const [policyForm, setPolicyForm] = useState(null)

  const loadPolicy = useCallback(async () => {
    setPolicyLoading(true)
    try {
      const response = await apiClient.get('/api/configuration/work-policy.php')
      const nextPolicy = response.data.data
      setPolicy(nextPolicy)
      setPolicyForm({
        policy_name: nextPolicy.policy_name,
        timezone: nextPolicy.timezone,
        grace_minutes: nextPolicy.grace_minutes,
        early_departure_grace_minutes: nextPolicy.early_departure_grace_minutes,
        minimum_half_day_hours: nextPolicy.minimum_half_day_hours,
      })
    } catch (requestError) {
      setError(getErrorMessage(requestError, 'The working-hour policy could not be loaded.'))
    } finally {
      setPolicyLoading(false)
    }
  }, [])

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

  useEffect(() => {
    const load = async () => {
      await loadPolicy()
    }

    load()
  }, [loadPolicy])

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

  const savePolicySettings = async (event) => {
    event.preventDefault()
    if (!policy || !policyForm) return
    setPolicySaving('settings')
    setError('')
    setSuccess('')
    try {
      await apiClient.post('/api/configuration/work-policy-settings.php', {
        work_policy_id: policy.work_policy_id,
        ...policyForm,
      })
      setSuccess('Working-hour settings updated.')
      await loadPolicy()
    } catch (requestError) {
      setError(getErrorMessage(requestError, 'Working-hour settings could not be updated.'))
    } finally {
      setPolicySaving('')
    }
  }

  const updatePolicyDay = (dayOfWeek, field, value) => {
    setPolicy((previous) => ({
      ...previous,
      days: previous.days.map((day) => day.day_of_week === dayOfWeek ? { ...day, [field]: value } : day),
    }))
  }

  const savePolicyDay = async (day) => {
    setPolicySaving(`day-${day.day_of_week}`)
    setError('')
    setSuccess('')
    try {
      await apiClient.post('/api/configuration/work-policy-day.php', {
        work_policy_id: policy.work_policy_id,
        day_of_week: day.day_of_week,
        is_working_day: day.is_working_day,
        start_time: day.is_working_day ? displayTime(day.start_time) : null,
        end_time: day.is_working_day ? displayTime(day.end_time) : null,
        expected_hours: day.is_working_day ? Number(day.expected_hours) : 0,
      })
      setSuccess(`${dayNames[day.day_of_week]} schedule updated.`)
      await loadPolicy()
    } catch (requestError) {
      setError(getErrorMessage(requestError, 'The working day could not be updated.'))
    } finally {
      setPolicySaving('')
    }
  }

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6"><div><p className="section-label">Nexa People / Time</p><h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Attendance & work hours</h1><p className="mt-2 text-sm text-slate-600">Check-in records are classified from the organisation&apos;s active working-hour policy.</p></div><div className="flex flex-wrap items-center gap-3"><nav aria-label="Leave and attendance" className="flex rounded-lg bg-slate-100 p-1"><Link to="/leave" className="rounded-md px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900">Leave Management</Link><Link to="/attendance" className="rounded-md bg-white px-3 py-2 text-sm font-medium text-sky-700 shadow-sm">Attendance</Link></nav><Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link></div></header>

        {error && <div role="alert" className="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
        {success && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{success}</div>}

        <section className="panel-surface mb-6 p-5 sm:p-6">
          <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p className="section-label">Company policy</p>
              <h2 className="mt-2 text-lg font-bold text-slate-900">Working schedule</h2>
              <p className="mt-1 text-sm text-slate-600">Only Admin can change these rules. HR and managers can review them.</p>
            </div>
            {policy && <span className="rounded-full bg-[#dcffd1] px-3 py-1 text-xs font-semibold text-slate-700">{policy.policy_name}</span>}
          </div>

          {policyLoading && <p className="text-sm text-slate-600">Loading working-hour policy...</p>}

          {!policyLoading && policy && !isAdmin && (
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <PolicyValue label="Timezone" value={policy.timezone} />
              <PolicyValue label="Late grace" value={`${policy.grace_minutes} minutes`} />
              <PolicyValue label="Early checkout grace" value={`${policy.early_departure_grace_minutes} minutes`} />
              <PolicyValue label="Half-day threshold" value={`${policy.minimum_half_day_hours} hours`} />
            </div>
          )}

          {!policyLoading && policy && isAdmin && policyForm && (
            <>
              <form onSubmit={savePolicySettings} className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <label><span className="mb-2 block text-sm font-medium text-slate-700">Policy name</span><input className="field-input" value={policyForm.policy_name} onChange={(event) => setPolicyForm((previous) => ({ ...previous, policy_name: event.target.value }))} /></label>
                <label><span className="mb-2 block text-sm font-medium text-slate-700">Timezone</span><input className="field-input" value={policyForm.timezone} onChange={(event) => setPolicyForm((previous) => ({ ...previous, timezone: event.target.value }))} /></label>
                <label><span className="mb-2 block text-sm font-medium text-slate-700">Late grace (minutes)</span><input className="field-input" type="number" min="0" max="180" value={policyForm.grace_minutes} onChange={(event) => setPolicyForm((previous) => ({ ...previous, grace_minutes: Number(event.target.value) }))} /></label>
                <label><span className="mb-2 block text-sm font-medium text-slate-700">Early grace (minutes)</span><input className="field-input" type="number" min="0" max="180" value={policyForm.early_departure_grace_minutes} onChange={(event) => setPolicyForm((previous) => ({ ...previous, early_departure_grace_minutes: Number(event.target.value) }))} /></label>
                <label><span className="mb-2 block text-sm font-medium text-slate-700">Half-day hours</span><input className="field-input" type="number" min="0" max="24" step="0.25" value={policyForm.minimum_half_day_hours} onChange={(event) => setPolicyForm((previous) => ({ ...previous, minimum_half_day_hours: Number(event.target.value) }))} /></label>
                <button type="submit" disabled={Boolean(policySaving)} className="action-button-primary md:col-span-2 xl:col-span-5">{policySaving === 'settings' ? 'Saving settings...' : 'Save policy settings'}</button>
              </form>

              <div className="mt-6 overflow-x-auto rounded-xl border border-slate-200">
                <table className="min-w-full text-left text-sm">
                  <thead className="bg-slate-50 text-xs uppercase tracking-[0.1em] text-slate-500"><tr><th className="px-4 py-3">Day</th><th className="px-4 py-3">Working day</th><th className="px-4 py-3">Start</th><th className="px-4 py-3">End</th><th className="px-4 py-3">Expected hours</th><th className="px-4 py-3">Action</th></tr></thead>
                  <tbody className="divide-y divide-slate-200">
                    {policy.days.map((day) => (
                      <tr key={day.day_of_week} className="bg-white">
                        <td className="px-4 py-3 font-medium text-slate-900">{dayNames[day.day_of_week]}</td>
                        <td className="px-4 py-3"><input type="checkbox" checked={day.is_working_day} onChange={(event) => updatePolicyDay(day.day_of_week, 'is_working_day', event.target.checked)} /></td>
                        <td className="px-4 py-3"><input type="time" disabled={!day.is_working_day} value={day.start_time ? String(day.start_time).slice(0, 5) : ''} onChange={(event) => updatePolicyDay(day.day_of_week, 'start_time', event.target.value)} className="field-input min-w-32" /></td>
                        <td className="px-4 py-3"><input type="time" disabled={!day.is_working_day} value={day.end_time ? String(day.end_time).slice(0, 5) : ''} onChange={(event) => updatePolicyDay(day.day_of_week, 'end_time', event.target.value)} className="field-input min-w-32" /></td>
                        <td className="px-4 py-3"><input type="number" min="0.25" max="24" step="0.25" disabled={!day.is_working_day} value={day.expected_hours} onChange={(event) => updatePolicyDay(day.day_of_week, 'expected_hours', Number(event.target.value))} className="field-input min-w-28" /></td>
                        <td className="px-4 py-3"><button type="button" disabled={Boolean(policySaving)} onClick={() => savePolicyDay(day)} className="action-button-secondary">{policySaving === `day-${day.day_of_week}` ? 'Saving...' : 'Save'}</button></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          )}
        </section>

        <section className="panel-surface mb-6 p-5 sm:p-6">
          <div className="grid gap-4 md:grid-cols-4 md:items-end">
            {!isEmployee && <label><span className="mb-2 block text-sm font-medium text-slate-700">Employee ID</span><input className="field-input" type="number" min="1" value={employeeId} onChange={(event) => setEmployeeId(event.target.value)} placeholder="All employees" /></label>}
            <label><span className="mb-2 block text-sm font-medium text-slate-700">Date</span><input className="field-input" type="date" value={date} onChange={(event) => setDate(event.target.value)} /></label>
            <label><span className="mb-2 block text-sm font-medium text-slate-700">Status</span><select className="field-input" value={status} onChange={(event) => setStatus(event.target.value)}><option value="">All statuses</option>{attendanceStatuses.map((option) => <option key={option} value={option}>{option}</option>)}</select></label>
            {isEmployee && <div className="flex flex-wrap gap-3"><button type="button" disabled={Boolean(action) || loading} onClick={() => runAction('check-in', '/api/attendance/check-in.php')} className="action-button-primary">{action === 'check-in' ? 'Checking in...' : 'Check in'}</button><button type="button" disabled={Boolean(action) || loading} onClick={() => runAction('check-out', '/api/attendance/check-out.php')} className="action-button-secondary">{action === 'check-out' ? 'Checking out...' : 'Check out'}</button></div>}
          </div>
          {isEmployee ? <p className="mt-4 rounded-xl border border-[#ffd1dc] bg-[#fff7f9] px-4 py-3 text-sm text-slate-700">Check-in confirms the account action, but location is currently marked <strong>Unverified</strong>. The system does not claim that the employee is physically at work.</p> : <p className="mt-4 text-sm text-slate-600">Managers see assigned employees only. Admin and HR can review organisation-wide attendance.</p>}
        </section>

        <section className="panel-surface p-5 sm:p-6"><div className="mb-4 flex items-center justify-between gap-3"><h2 className="text-lg font-bold text-slate-900">Attendance records</h2><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{loading ? 'Loading' : `${records.length} records`}</span></div>{loading ? <p className="py-6 text-center text-sm text-slate-600">Loading attendance records...</p> : records.length === 0 ? <p className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-600">No attendance records found.</p> : <div className="overflow-x-auto rounded-xl border border-slate-200"><table className="min-w-full text-left text-sm"><thead className="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500"><tr><th className="px-4 py-3">Employee</th><th className="px-4 py-3">Date</th><th className="px-4 py-3">Check in</th><th className="px-4 py-3">Check out</th><th className="px-4 py-3">Worked / expected</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Verification</th></tr></thead><tbody className="divide-y divide-slate-200">{records.map((record) => <tr key={record.attendance_id} className="bg-white"><td className="px-4 py-3 text-slate-700"><span className="font-medium text-slate-900">{record.employee_name || record.employee_id}</span><span className="mt-1 block text-xs text-slate-500">{record.department_name || record.employee_number || ''}</span></td><td className="px-4 py-3 text-slate-700">{record.attendance_date}</td><td className="px-4 py-3 text-slate-700">{displayTime(record.check_in)}</td><td className="px-4 py-3 text-slate-700">{displayTime(record.check_out)}</td><td className="px-4 py-3 text-slate-700">{Number(record.hours_worked).toFixed(2)} / {Number(record.expected_hours).toFixed(2)}</td><td className="px-4 py-3 text-slate-700"><span className="font-medium">{record.status}</span>{record.late_minutes > 0 && <span className="mt-1 block text-xs text-slate-500">{record.late_minutes} min late</span>}{record.early_departure_minutes > 0 && <span className="mt-1 block text-xs text-slate-500">{record.early_departure_minutes} min early</span>}</td><td className="px-4 py-3 text-slate-700">{record.verification_status || 'Unverified'}</td></tr>)}</tbody></table></div>}</section>
      </div>
    </main>
  )
}

function PolicyValue({ label, value }) {
  return (
    <div className="rounded-xl bg-[#f7f9ff] p-4 ring-1 ring-[#d1dcff]">
      <p className="section-label">{label}</p>
      <p className="mt-2 font-semibold text-slate-900">{value}</p>
    </div>
  )
}

export default AttendancePage
