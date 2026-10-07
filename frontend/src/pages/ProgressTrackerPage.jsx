import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

function readStoredUser() {
  try { return JSON.parse(localStorage.getItem('hrms_user') || 'null') } catch { return null }
}

function isComplete(value) {
  return value === true || value === 1 || value === '1' || value === 't'
}

function ProgressTrackerPage() {
  const user = readStoredUser()
  const role = user?.role_name || 'Employee'
  const canManage = ['Admin', 'HR', 'Manager'].includes(role)
  const [onboarding, setOnboarding] = useState([])
  const [goals, setGoals] = useState([])
  const [training, setTraining] = useState([])
  const [records, setRecords] = useState([])
  const [employees, setEmployees] = useState([])
  const [state, setState] = useState('loading')
  const [message, setMessage] = useState('')
  const [actionMessage, setActionMessage] = useState({ kind: '', text: '' })
  const [busy, setBusy] = useState('')
  const [refreshKey, setRefreshKey] = useState(0)
  const [milestoneDrafts, setMilestoneDrafts] = useState({})
  const [createForm, setCreateForm] = useState({ employee_id: '', title: '', description: '', due_date: '', milestones: '' })

  useEffect(() => {
    let active = true
    const requests = [
      apiClient.get('/api/onboarding/get.php'),
      apiClient.get('/api/performance/goals/get.php'),
      apiClient.get('/api/training/enrollments.php'),
      apiClient.get('/api/progress/get.php'),
    ]
    if (canManage) requests.push(apiClient.get('/api/employees/get.php', { params: { status: 'Active', limit: 200 } }))

    Promise.all(requests).then((responses) => {
      if (!active) return
      setOnboarding(responses[0].data.data || [])
      setGoals(responses[1].data.data || [])
      setTraining(responses[2].data.data || [])
      setRecords(responses[3].data.data || [])
      setEmployees(canManage
        ? (responses[4].data.data || []).filter((employee) => Number(employee.employee_id) !== Number(user?.employee_id))
        : [])
      setState('ready')
    }).catch((error) => {
      if (!active) return
      setMessage(error.response?.data?.message || 'Unable to load progress records.')
      setState('error')
    })
    return () => { active = false }
  }, [canManage, refreshKey, user?.employee_id])

  const summaryMilestones = useMemo(() => {
    const latest = onboarding[0]
    const documents = latest?.documents || []
    const completedGoals = goals.filter((goal) => goal.status === 'Completed').length
    const completedTraining = training.filter((item) => item.status === 'Completed').length
    return [
      { name: 'Onboarding workflow', progress: latest?.onboarding_status === 'Completed' ? 100 : Number(latest?.verification_percentage || 0), detail: latest?.onboarding_status || 'Not started' },
      { name: 'Document verification', progress: documents.length ? Math.round(100 * documents.filter((item) => item.document_status === 'Verified').length / documents.length) : 0, detail: `${documents.filter((item) => item.document_status === 'Verified').length} of ${documents.length} verified` },
      { name: 'Performance goals', progress: goals.length ? Math.round(goals.reduce((sum, goal) => sum + Number(goal.progress || 0), 0) / goals.length) : 0, detail: `${completedGoals} of ${goals.length} completed` },
      { name: 'Training assignments', progress: training.length ? Math.round(100 * completedTraining / training.length) : 0, detail: `${completedTraining} of ${training.length} completed` },
    ]
  }, [goals, onboarding, training])

  const overall = Math.round(summaryMilestones.reduce((sum, milestone) => sum + milestone.progress, 0) / summaryMilestones.length)

  const runAction = async (key, endpoint, payload, successText) => {
    setBusy(key)
    setActionMessage({ kind: '', text: '' })
    try {
      await apiClient.post(endpoint, payload)
      setActionMessage({ kind: 'success', text: successText })
      setRefreshKey((value) => value + 1)
      return true
    } catch (error) {
      setActionMessage({ kind: 'error', text: error.response?.data?.message || 'The progress action could not be completed.' })
      return false
    } finally {
      setBusy('')
    }
  }

  const createProgressRecord = async (event) => {
    event.preventDefault()
    const milestones = createForm.milestones.split('\n').map((title) => title.trim()).filter(Boolean)
    const saved = await runAction('create', '/api/progress/create.php', {
      employee_id: Number(createForm.employee_id),
      title: createForm.title,
      description: createForm.description,
      due_date: createForm.due_date || null,
      milestones,
    }, 'Progress record created.')
    if (saved) setCreateForm({ employee_id: '', title: '', description: '', due_date: '', milestones: '' })
  }

  const addMilestone = async (recordId) => {
    const title = (milestoneDrafts[recordId] || '').trim()
    if (!title) return
    const saved = await runAction(`add-${recordId}`, '/api/progress/add-milestone.php', { record_id: recordId, title }, 'Milestone added.')
    if (saved) setMilestoneDrafts((current) => ({ ...current, [recordId]: '' }))
  }

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-6xl">
        <header className="panel-surface mb-6 p-4 sm:p-6"><div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div><p className="section-label">Nexa People / Development</p><h1 className="mt-2 text-3xl font-bold text-slate-900">Progress tracker</h1><p className="mt-2 text-sm text-slate-600">Review live development summaries and assigned milestone records.</p></div><div className="flex items-center gap-3"><span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{role}</span><Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link></div></div></header>

        {message && <div className="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">{message}</div>}
        {actionMessage.text && <div className={`mb-6 rounded-xl border p-4 text-sm ${actionMessage.kind === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'}`} role={actionMessage.kind === 'error' ? 'alert' : 'status'}>{actionMessage.text}</div>}

        <section className="mb-6 grid gap-4 md:grid-cols-3"><div className="panel-surface p-5"><p className="section-label">Overall progress</p><p className="mt-2 text-3xl font-bold text-sky-700">{state === 'ready' ? `${overall}%` : '—'}</p></div><div className="panel-surface p-5"><p className="section-label">Assigned records</p><p className="mt-2 text-3xl font-bold text-slate-900">{state === 'ready' ? records.length : '—'}</p></div><div className="panel-surface p-5"><p className="section-label">Completed records</p><p className="mt-2 text-3xl font-bold text-emerald-600">{state === 'ready' ? records.filter((record) => record.status === 'Completed').length : '—'}</p></div></section>

        {state === 'loading' && <div className="panel-surface mb-6 p-6 text-sm text-slate-600">Loading progress records...</div>}
        {state === 'ready' && <section className="panel-surface mb-6 p-5 sm:p-6"><p className="section-label">Calculated summary</p><h2 className="mt-2 text-lg font-bold text-slate-900">Onboarding, goals and training</h2><div className="mt-6 space-y-5">{summaryMilestones.map((milestone) => <article key={milestone.name}><div className="mb-2 flex items-center justify-between gap-3"><div><p className="font-semibold text-slate-900">{milestone.name}</p><p className="text-xs text-slate-500">{milestone.detail}</p></div><span className="text-sm font-bold text-sky-700">{milestone.progress}%</span></div><div className="h-2.5 overflow-hidden rounded-full bg-slate-200"><div className="h-full rounded-full bg-sky-500 transition-all" style={{ width: `${milestone.progress}%` }} /></div></article>)}</div></section>}

        {state === 'ready' && canManage && (
          <section className="panel-surface mb-6 p-5 sm:p-6">
            <p className="section-label">Reviewer action</p><h2 className="mt-2 text-lg font-bold text-slate-900">Assign a progress record</h2><p className="mt-1 text-sm text-slate-600">Managers can select assigned employees only. Admin and HR can select any active employee.</p>
            <form onSubmit={createProgressRecord} className="mt-5 grid gap-4 md:grid-cols-2">
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Employee</span><select required className="field-input" value={createForm.employee_id} onChange={(event) => setCreateForm((current) => ({ ...current, employee_id: event.target.value }))}><option value="">Select employee</option>{employees.map((employee) => <option key={employee.employee_id} value={employee.employee_id}>{employee.employee_number ? `${employee.employee_number} · ` : ''}{employee.first_name} {employee.last_name}</option>)}</select></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Title</span><input required maxLength="150" className="field-input" value={createForm.title} onChange={(event) => setCreateForm((current) => ({ ...current, title: event.target.value }))} /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Due date</span><input type="date" className="field-input" value={createForm.due_date} onChange={(event) => setCreateForm((current) => ({ ...current, due_date: event.target.value }))} /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Description</span><input maxLength="5000" className="field-input" value={createForm.description} onChange={(event) => setCreateForm((current) => ({ ...current, description: event.target.value }))} /></label>
              <label className="md:col-span-2"><span className="mb-2 block text-sm font-medium text-slate-700">Milestones</span><textarea required rows="4" className="field-input" value={createForm.milestones} onChange={(event) => setCreateForm((current) => ({ ...current, milestones: event.target.value }))} placeholder="Enter at least two milestones, one per line" /><span className="mt-1 block text-xs text-slate-500">Between 2 and 20 milestones.</span></label>
              <button type="submit" disabled={Boolean(busy) || employees.length === 0} className="action-button-primary md:col-span-2">{busy === 'create' ? 'Creating...' : 'Create progress record'}</button>
            </form>
          </section>
        )}

        {state === 'ready' && <section className="space-y-4"><div><p className="section-label">Assigned work</p><h2 className="mt-2 text-xl font-bold text-slate-900">Milestone records</h2></div>{records.length === 0 && <div className="panel-surface p-6 text-center text-sm text-slate-600">No milestone records have been assigned yet.</div>}{records.map((record) => (
          <article key={record.record_id} className="panel-surface p-5 sm:p-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><div className="flex flex-wrap items-center gap-2"><h3 className="text-lg font-bold text-slate-900">{record.title}</h3><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${record.status === 'Completed' ? 'bg-emerald-100 text-emerald-700' : record.status === 'Cancelled' ? 'bg-slate-200 text-slate-700' : 'bg-sky-100 text-sky-700'}`}>{record.status}</span></div><p className="mt-1 text-sm text-slate-600">{record.employee_name}{record.due_date ? ` · Due ${record.due_date}` : ''}</p>{record.description && <p className="mt-3 text-sm text-slate-700">{record.description}</p>}</div><div className="text-right"><p className="text-2xl font-bold text-sky-700">{Number(record.progress_percentage || 0)}%</p><p className="text-xs text-slate-500">{record.milestones_completed} of {record.milestones_total}</p></div></div>
            <div className="mt-5 h-2.5 overflow-hidden rounded-full bg-slate-200"><div className="h-full rounded-full bg-sky-500" style={{ width: `${Number(record.progress_percentage || 0)}%` }} /></div>
            <div className="mt-5 space-y-2">{(record.milestones || []).map((milestone) => <label key={milestone.milestone_id} className="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3"><input type="checkbox" checked={isComplete(milestone.is_complete)} disabled={!canManage || record.status === 'Cancelled' || Boolean(busy)} onChange={() => runAction(`milestone-${milestone.milestone_id}`, '/api/progress/complete-milestone.php', { record_id: record.record_id, milestone_id: milestone.milestone_id, is_complete: !isComplete(milestone.is_complete) }, isComplete(milestone.is_complete) ? 'Milestone reopened.' : 'Milestone completed.')} /><span className={`text-sm ${isComplete(milestone.is_complete) ? 'text-slate-500 line-through' : 'font-medium text-slate-800'}`}>{milestone.title}</span></label>)}</div>
            {canManage && record.status === 'In Progress' && <div className="mt-4 flex flex-col gap-3 sm:flex-row"><input className="field-input" maxLength="150" value={milestoneDrafts[record.record_id] || ''} onChange={(event) => setMilestoneDrafts((current) => ({ ...current, [record.record_id]: event.target.value }))} placeholder="Add another milestone" /><button type="button" disabled={Boolean(busy) || !(milestoneDrafts[record.record_id] || '').trim()} onClick={() => addMilestone(record.record_id)} className="action-button-secondary">{busy === `add-${record.record_id}` ? 'Adding...' : 'Add milestone'}</button></div>}
            {canManage && record.status !== 'Completed' && <div className="mt-4 flex justify-end"><button type="button" disabled={Boolean(busy)} onClick={() => runAction(`status-${record.record_id}`, '/api/progress/update.php', { record_id: record.record_id, status: record.status === 'Cancelled' ? 'In Progress' : 'Cancelled' }, record.status === 'Cancelled' ? 'Progress record reopened.' : 'Progress record cancelled.')} className="action-button-secondary">{record.status === 'Cancelled' ? 'Reopen record' : 'Cancel record'}</button></div>}
          </article>
        ))}</section>}

        <div className="mt-6 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">Percentages come only from stored milestones. Employees can view their own records; reviewers can update only the employees within their permitted scope.</div>
      </div>
    </main>
  )
}

export default ProgressTrackerPage
