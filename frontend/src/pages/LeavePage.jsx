import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

const initialForm = {
  leave_type_id: '',
  start_date: '',
  end_date: '',
  reason: '',
}

function getErrorMessage(error, fallback) {
  return error.response?.data?.message || fallback
}

function LeavePage() {
  const storedUser = localStorage.getItem('hrms_user')
  const user = storedUser ? JSON.parse(storedUser) : null
  const isEmployee = user?.role_name === 'Employee'
  const canReview = ['Admin', 'HR', 'Manager'].includes(user?.role_name)
  const [leaveTypes, setLeaveTypes] = useState([])
  const [balances, setBalances] = useState([])
  const [requests, setRequests] = useState([])
  const [form, setForm] = useState(initialForm)
  const [rejectionReason, setRejectionReason] = useState('')
  const [employeeId, setEmployeeId] = useState('')
  const [status, setStatus] = useState('')
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const loadData = async () => {
    setLoading(true)
    setError('')

    try {
      const requestParams = {}
      if (!isEmployee && employeeId) requestParams.employee_id = employeeId
      if (status) requestParams.status = status

      const balanceParams = {}
      if (!isEmployee && employeeId) balanceParams.employee_id = employeeId

      const [typesResponse, requestsResponse, balancesResponse] = await Promise.all([
        apiClient.get('/api/Leave/types.php'),
        apiClient.get('/api/Leave/get.php', { params: requestParams }),
        isEmployee || employeeId
          ? apiClient.get('/api/Leave/balance.php', { params: balanceParams })
          : Promise.resolve({ data: { success: true, data: [] } }),
      ])

      setLeaveTypes(typesResponse.data.data || [])
      setRequests(requestsResponse.data.data || [])
      setBalances(balancesResponse.data.data || [])
    } catch (requestError) {
      setError(getErrorMessage(requestError, 'Unable to load leave data.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    const load = async () => {
      await loadData()
    }

    load()
  }, [employeeId, status, isEmployee])

  const handleChange = (event) => {
    const { name, value } = event.target
    setForm((previous) => ({ ...previous, [name]: value }))
  }

  const runAction = async (action, message) => {
    setSubmitting(true)
    setError('')
    setSuccess('')

    try {
      await action()
      setSuccess(message)
      await loadData()
    } catch (requestError) {
      setError(getErrorMessage(requestError, 'The leave request could not be completed.'))
    } finally {
      setSubmitting(false)
    }
  }

  const submitRequest = async (event) => {
    event.preventDefault()
    await runAction(
      () => apiClient.post('/api/Leave/request.php', form),
      'Leave request submitted successfully.',
    )
    setForm(initialForm)
  }

  const cancelRequest = (leaveRequestId) => runAction(
    () => apiClient.post('/api/Leave/cancel.php', { leave_request_id: leaveRequestId }),
    'Leave request cancelled successfully.',
  )

  const reviewRequest = (leaveRequestId, approved) => {
    if (!approved && rejectionReason.trim() === '') {
      setError('Enter a rejection reason before rejecting a leave request.')
      return Promise.resolve()
    }

    const action = approved
      ? apiClient.post('/api/Leave/approve.php', { leave_request_id: leaveRequestId })
      : apiClient.post('/api/Leave/reject.php', { leave_request_id: leaveRequestId, rejection_reason: rejectionReason.trim() })

    return runAction(() => action, approved ? 'Leave request approved successfully.' : 'Leave request rejected successfully.')
  }

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">HRMS</p>
            <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Leave Management</h1>
            <p className="mt-2 text-sm text-slate-600">Live leave balances, requests, and approvals.</p>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            <nav aria-label="Leave and attendance" className="flex rounded-lg bg-slate-100 p-1">
              <Link to="/leave" className="rounded-md bg-white px-3 py-2 text-sm font-medium text-sky-700 shadow-sm">Leave Management</Link>
              <Link to="/attendance" className="rounded-md px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900">Attendance</Link>
            </nav>
            <Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link>
          </div>
        </header>

        {error && <div role="alert" className="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
        {success && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{success}</div>}

        {canReview && (
          <section className="panel-surface mb-6 p-5 sm:p-6">
            <div className="grid gap-4 sm:grid-cols-2">
              <label>
                <span className="mb-2 block text-sm font-medium text-slate-700">Employee ID</span>
                <input className="field-input" type="number" min="1" value={employeeId} onChange={(event) => setEmployeeId(event.target.value)} placeholder="All employees" />
              </label>
              <label>
                <span className="mb-2 block text-sm font-medium text-slate-700">Request status</span>
                <select className="field-input" value={status} onChange={(event) => setStatus(event.target.value)}>
                  <option value="">All statuses</option>
                  <option value="Pending">Pending</option>
                  <option value="Approved">Approved</option>
                  <option value="Rejected">Rejected</option>
                  <option value="Cancelled">Cancelled</option>
                </select>
              </label>
              <label className="sm:col-span-2"><span className="mb-2 block text-sm font-medium text-slate-700">Rejection reason</span><input className="field-input" value={rejectionReason} onChange={(event) => setRejectionReason(event.target.value)} placeholder="Required when rejecting a request" maxLength="5000" /></label>
            </div>
          </section>
        )}

        <section className="mb-6 grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
          <div className="panel-surface p-5 sm:p-6">
            <h2 className="text-lg font-bold text-slate-900">Leave balance</h2>
            {loading ? <p className="mt-4 text-sm text-slate-600">Loading balances...</p> : balances.length === 0 ? <p className="mt-4 text-sm text-slate-600">No leave balances are available.</p> : (
              <div className="mt-4 space-y-3">
                {balances.map((balance) => (
                  <div key={balance.leave_balance_id} className="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <div className="flex items-center justify-between gap-3"><p className="font-semibold text-slate-900">{balance.leave_name}</p><span className="text-sm font-semibold text-sky-700">{balance.remaining_days} remaining</span></div>
                    <p className="mt-2 text-sm text-slate-600">{balance.used_days} used of {balance.total_days} days</p>
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="panel-surface p-5 sm:p-6">
            <h2 className="text-lg font-bold text-slate-900">Request leave</h2>
            <form onSubmit={submitRequest} className="mt-4 grid gap-4 sm:grid-cols-2">
              <label className="sm:col-span-2"><span className="mb-2 block text-sm font-medium text-slate-700">Leave type</span><select required name="leave_type_id" value={form.leave_type_id} onChange={handleChange} className="field-input"><option value="">Select leave type</option>{leaveTypes.map((type) => <option key={type.leave_type_id} value={type.leave_type_id}>{type.leave_name}</option>)}</select></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Start date</span><input required name="start_date" type="date" value={form.start_date} onChange={handleChange} className="field-input" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">End date</span><input required name="end_date" type="date" value={form.end_date} onChange={handleChange} className="field-input" /></label>
              <label className="sm:col-span-2"><span className="mb-2 block text-sm font-medium text-slate-700">Reason</span><textarea name="reason" value={form.reason} onChange={handleChange} maxLength="5000" rows="3" className="field-input" /></label>
              <button type="submit" disabled={submitting || loading || leaveTypes.length === 0} className="action-button-primary sm:col-span-2">{submitting ? 'Submitting...' : 'Submit leave request'}</button>
            </form>
          </div>
        </section>

        <section className="panel-surface p-5 sm:p-6">
          <div className="mb-4 flex items-center justify-between gap-3"><h2 className="text-lg font-bold text-slate-900">Leave requests</h2><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{loading ? 'Loading' : `${requests.length} records`}</span></div>
          {loading ? <p className="py-6 text-center text-sm text-slate-600">Loading leave requests...</p> : requests.length === 0 ? <p className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-600">No leave requests found.</p> : (
            <div className="overflow-x-auto rounded-xl border border-slate-200"><table className="min-w-full text-left text-sm"><thead className="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500"><tr><th className="px-4 py-3">Employee</th><th className="px-4 py-3">Leave</th><th className="px-4 py-3">Period</th><th className="px-4 py-3">Days</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Actions</th></tr></thead><tbody className="divide-y divide-slate-200">{requests.map((request) => <tr key={request.leave_request_id} className="bg-white"><td className="px-4 py-3 text-slate-700">{request.employee_name}</td><td className="px-4 py-3 font-medium text-slate-900">{request.leave_name}</td><td className="px-4 py-3 text-slate-700">{request.start_date} to {request.end_date}</td><td className="px-4 py-3 text-slate-700">{request.number_of_days}</td><td className="px-4 py-3 text-slate-700">{request.status}</td><td className="px-4 py-3"><div className="flex flex-wrap gap-2">{isEmployee && request.status === 'Pending' && <button type="button" disabled={submitting} onClick={() => cancelRequest(request.leave_request_id)} className="action-button-secondary">Cancel</button>}{canReview && request.status === 'Pending' && <><button type="button" disabled={submitting} onClick={() => reviewRequest(request.leave_request_id, true)} className="action-button-primary">Approve</button><button type="button" disabled={submitting} onClick={() => reviewRequest(request.leave_request_id, false)} className="action-button-secondary">Reject</button></>}</div></td></tr>)}</tbody></table></div>
          )}
        </section>
      </div>
    </main>
  )
}

export default LeavePage
