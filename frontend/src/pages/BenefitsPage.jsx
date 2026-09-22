import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

function readStoredUser() {
  try { return JSON.parse(localStorage.getItem('hrms_user') || 'null') } catch { return null }
}

function BenefitsPage() {
  const user = readStoredUser()
  const role = user?.role_name || 'Employee'
  const [plans, setPlans] = useState([])
  const [assignments, setAssignments] = useState([])
  const [state, setState] = useState('loading')
  const [message, setMessage] = useState('')

  useEffect(() => {
    let active = true
    Promise.all([
      apiClient.get('/api/benefits/get.php'),
      apiClient.get('/api/benefits/assignments.php'),
    ]).then(([plansResponse, assignmentsResponse]) => {
      if (!active) return
      setPlans(plansResponse.data.data || [])
      setAssignments(assignmentsResponse.data.data || [])
      setState('ready')
    }).catch((error) => {
      if (!active) return
      setMessage(error.response?.data?.message || 'Unable to load benefit records.')
      setState('error')
    })
    return () => { active = false }
  }, [])

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 p-4 sm:p-6">
          <div className="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="section-label">HRMS / Total rewards</p>
              <h1 className="mt-2 text-3xl font-bold text-slate-900">Benefits</h1>
              <p className="mt-2 text-sm text-slate-600">Live benefit plans and employee enrollment records from the HRMS database.</p>
            </div>
            <div className="flex items-center gap-3">
              <span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{role}</span>
              <Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link>
            </div>
          </div>
        </header>

        <section className="mb-6 grid gap-4 md:grid-cols-3">
          <div className="panel-surface p-5"><p className="section-label">Available plans</p><p className="mt-2 text-2xl font-bold text-slate-900">{state === 'ready' ? plans.length : '—'}</p></div>
          <div className="panel-surface p-5"><p className="section-label">Enrollments in scope</p><p className="mt-2 text-2xl font-bold text-slate-900">{state === 'ready' ? assignments.length : '—'}</p></div>
          <div className="panel-surface p-5"><p className="section-label">Connection</p><p className={`mt-2 text-lg font-bold ${state === 'error' ? 'text-red-600' : state === 'ready' ? 'text-emerald-600' : 'text-sky-600'}`}>{state === 'ready' ? 'Connected' : state === 'error' ? 'Needs attention' : 'Loading'}</p></div>
        </section>

        {state === 'error' && <div className="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">{message}</div>}

        <section className="mb-6 grid gap-6 lg:grid-cols-2">
          <div className="panel-surface p-5 sm:p-6">
            <p className="section-label">Benefit catalogue</p>
            <h2 className="mt-2 text-lg font-bold text-slate-900">Available plans</h2>
            <div className="mt-5 space-y-3">
              {state === 'loading' && <p className="text-sm text-slate-600">Loading benefit plans...</p>}
              {state === 'ready' && plans.length === 0 && <p className="rounded-xl bg-slate-50 p-5 text-sm text-slate-600">No plans have been created yet.</p>}
              {plans.map((plan) => (
                <article key={plan.benefit_id} className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div><h3 className="font-semibold text-slate-900">{plan.benefit_name}</h3><p className="mt-1 text-sm text-slate-600">{plan.description || 'No description provided.'}</p></div>
                    <span className="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">{plan.status}</span>
                  </div>
                  <p className="mt-3 text-xs text-slate-500">{plan.benefit_type} · {plan.provider || 'Provider not specified'} · Default K{Number(plan.default_amount || 0).toFixed(2)}</p>
                </article>
              ))}
            </div>
          </div>

          <div className="panel-surface p-5 sm:p-6">
            <p className="section-label">Enrollment records</p>
            <h2 className="mt-2 text-lg font-bold text-slate-900">{role === 'Employee' ? 'My benefits' : 'Accessible employee benefits'}</h2>
            <div className="mt-5 space-y-3">
              {state === 'loading' && <p className="text-sm text-slate-600">Loading enrollments...</p>}
              {state === 'ready' && assignments.length === 0 && <p className="rounded-xl bg-slate-50 p-5 text-sm text-slate-600">No enrollment records are available for this account.</p>}
              {assignments.map((assignment) => (
                <article key={assignment.employee_benefit_id} className="rounded-xl border border-slate-200 p-4">
                  <div className="flex items-center justify-between gap-3"><h3 className="font-semibold text-slate-900">{assignment.benefit_name}</h3><span className="text-xs font-semibold text-emerald-700">{assignment.status}</span></div>
                  <p className="mt-2 text-sm text-slate-600">Employee #{assignment.employee_id} · K{Number(assignment.amount || 0).toFixed(2)}</p>
                  <p className="mt-1 text-xs text-slate-500">From {assignment.enrollment_date}{assignment.end_date ? ` to ${assignment.end_date}` : ''}</p>
                </article>
              ))}
            </div>
          </div>
        </section>

        {role !== 'Employee' && <div className="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">Plan creation and employee assignment are available through the authenticated Benefits API; management forms can be added as the next UI increment.</div>}
      </div>
    </main>
  )
}

export default BenefitsPage
