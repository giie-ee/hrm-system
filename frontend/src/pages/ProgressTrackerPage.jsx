import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

function readStoredUser() {
  try { return JSON.parse(localStorage.getItem('hrms_user') || 'null') } catch { return null }
}

function ProgressTrackerPage() {
  const user = readStoredUser()
  const role = user?.role_name || 'Employee'
  const [onboarding, setOnboarding] = useState([])
  const [goals, setGoals] = useState([])
  const [training, setTraining] = useState([])
  const [state, setState] = useState('loading')
  const [message, setMessage] = useState('')

  useEffect(() => {
    let active = true
    Promise.all([
      apiClient.get('/api/onboarding/get.php'),
      apiClient.get('/api/performance/goals/get.php'),
      apiClient.get('/api/training/enrollments.php'),
    ]).then(([onboardingResponse, goalsResponse, trainingResponse]) => {
      if (!active) return
      setOnboarding(onboardingResponse.data.data || [])
      setGoals(goalsResponse.data.data || [])
      setTraining(trainingResponse.data.data || [])
      setState('ready')
    }).catch((error) => {
      if (!active) return
      setMessage(error.response?.data?.message || 'Unable to load progress records.')
      setState('error')
    })
    return () => { active = false }
  }, [])

  const milestones = useMemo(() => {
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

  const overall = milestones.length ? Math.round(milestones.reduce((sum, milestone) => sum + milestone.progress, 0) / milestones.length) : 0

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-6xl">
        <header className="panel-surface mb-6 p-4 sm:p-6"><div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div><p className="section-label">HRMS / Development</p><h1 className="mt-2 text-3xl font-bold text-slate-900">Progress tracker</h1><p className="mt-2 text-sm text-slate-600">A live summary calculated from onboarding, performance, and training records.</p></div><div className="flex items-center gap-3"><span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{role}</span><Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link></div></div></header>

        <section className="mb-6 grid gap-4 md:grid-cols-3"><div className="panel-surface p-5"><p className="section-label">Overall progress</p><p className="mt-2 text-3xl font-bold text-sky-700">{state === 'ready' ? `${overall}%` : '—'}</p></div><div className="panel-surface p-5"><p className="section-label">Active goals</p><p className="mt-2 text-3xl font-bold text-slate-900">{goals.filter((goal) => goal.status !== 'Cancelled').length}</p></div><div className="panel-surface p-5"><p className="section-label">Training records</p><p className="mt-2 text-3xl font-bold text-slate-900">{training.length}</p></div></section>

        {state === 'loading' && <div className="panel-surface p-6 text-sm text-slate-600">Loading progress records...</div>}
        {state === 'error' && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">{message}</div>}
        {state === 'ready' && <section className="panel-surface p-5 sm:p-6"><p className="section-label">Milestones</p><h2 className="mt-2 text-lg font-bold text-slate-900">{role === 'Employee' ? 'My development progress' : 'Accessible employee progress'}</h2><div className="mt-6 space-y-5">{milestones.map((milestone) => <article key={milestone.name}><div className="mb-2 flex items-center justify-between gap-3"><div><p className="font-semibold text-slate-900">{milestone.name}</p><p className="text-xs text-slate-500">{milestone.detail}</p></div><span className="text-sm font-bold text-sky-700">{milestone.progress}%</span></div><div className="h-2.5 overflow-hidden rounded-full bg-slate-200"><div className="h-full rounded-full bg-sky-500 transition-all" style={{ width: `${milestone.progress}%` }} /></div></article>)}</div></section>}

        <div className="mt-6 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">Progress is calculated only from records currently stored in the database. Missing records remain at zero rather than being estimated.</div>
      </div>
    </main>
  )
}

export default ProgressTrackerPage
