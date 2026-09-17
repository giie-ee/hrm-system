import { useState } from 'react'
import { Link } from 'react-router-dom'

const plannedStages = [
  {
    title: 'Onboarding information',
    description: 'Personal, contact, employment, and required onboarding details.',
  },
  {
    title: 'Required documents',
    description: 'Documents needed to complete the employee record.',
  },
  {
    title: 'Manager review',
    description: 'Manager confirmation of role and initial objectives.',
  },
  {
    title: 'HR review',
    description: 'HR verification and completion of the employee setup.',
  },
]

function readStoredUser() {
  try {
    return JSON.parse(localStorage.getItem('hrms_user') || 'null')
  } catch {
    return null
  }
}

function ProgressTrackerPage() {
  const user = readStoredUser()
  const role = user?.role_name || 'Employee'
  const isEmployee = role === 'Employee'
  const [trackerState] = useState('unavailable')

  const heading = isEmployee ? 'Your progress tracker' : `${role} progress workspace`
  const description = isEmployee
    ? 'Follow your onboarding and employee lifecycle milestones as they are completed.'
    : `Review employee lifecycle progress when progress records and role permissions are connected.`

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 p-4 sm:p-6">
          <div className="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">HRMS / Employee lifecycle</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Progress Tracker</h1>
              <p className="mt-2 max-w-2xl text-sm text-slate-600">Track onboarding, review, and employee development milestones in one workspace.</p>
            </div>
            <div className="flex flex-wrap items-center gap-3">
              <span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{role}</span>
              <Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link>
            </div>
          </div>
        </header>

        <section className="mb-6 grid gap-4 md:grid-cols-3">
          <div className="panel-surface p-5">
            <p className="section-label">Progress status</p>
            <p className="mt-2 text-lg font-bold text-amber-600">Backend unavailable</p>
            <p className="mt-1 text-sm text-slate-500">No progress records are exposed yet.</p>
          </div>
          <div className="panel-surface p-5">
            <p className="section-label">Overall completion</p>
            <p className="mt-2 text-lg font-bold text-slate-900">Not available</p>
            <p className="mt-1 text-sm text-slate-500">A percentage will appear with real data.</p>
          </div>
          <div className="panel-surface p-5">
            <p className="section-label">Access scope</p>
            <p className="mt-2 text-lg font-bold text-emerald-600">{isEmployee ? 'My progress' : 'Review ready'}</p>
            <p className="mt-1 text-sm text-slate-500">Based on your current role.</p>
          </div>
        </section>

        <section className="panel-surface mb-6 p-5 sm:p-6">
          <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <p className="section-label">Progress overview</p>
              <h2 className="mt-2 text-lg font-bold text-slate-900">{heading}</h2>
              <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{description}</p>
            </div>
            <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Not connected</span>
          </div>

          {trackerState === 'loading' && (
            <div className="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-700" role="status">Loading progress information...</div>
          )}

          {trackerState === 'error' && (
            <div className="mt-5 rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-700" role="alert">The progress service could not be reached. Try again when the backend is available.</div>
          )}

          {trackerState === 'unavailable' && (
            <div className="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-7 text-center" role="status">
              <p className="text-base font-semibold text-slate-800">No progress data available</p>
              <p className="mx-auto mt-2 max-w-2xl text-sm leading-6 text-slate-600">The current PHP service does not provide progress tracker records. No percentage, employee progress, or completion status is shown until the backend is connected.</p>
            </div>
          )}
        </section>

        <section className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
          <div className="panel-surface p-5 sm:p-6">
            <div className="mb-5 flex items-start justify-between gap-3">
              <div>
                <p className="section-label">Milestone timeline</p>
                <h2 className="mt-2 text-lg font-bold text-slate-900">Progress stages</h2>
              </div>
              <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Awaiting data</span>
            </div>

            <ol className="space-y-4" aria-label="Planned progress stages">
              {plannedStages.map((stage, index) => (
                <li key={stage.title} className="flex gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                  <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-sm font-bold text-slate-400 ring-1 ring-slate-200">{index + 1}</div>
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                      <h3 className="font-semibold text-slate-800">{stage.title}</h3>
                      <span className="self-start rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600">Status unavailable</span>
                    </div>
                    <p className="mt-1 text-sm leading-6 text-slate-600">{stage.description}</p>
                  </div>
                </li>
              ))}
            </ol>
          </div>

          <div className="panel-surface p-5 sm:p-6">
            <p className="section-label">Status legend</p>
            <h2 className="mt-2 text-lg font-bold text-slate-900">Lifecycle status model</h2>
            <p className="mt-2 text-sm leading-6 text-slate-600">These statuses are ready for connected progress records. They are not assigned to any employee on this screen.</p>
            <div className="mt-5 space-y-3">
              {[
                ['Not Started', 'bg-slate-100 text-slate-600'],
                ['In Progress', 'bg-sky-100 text-sky-700'],
                ['Pending Review', 'bg-amber-100 text-amber-700'],
                ['Completed', 'bg-emerald-100 text-emerald-700'],
              ].map(([label, style]) => (
                <div key={label} className="flex items-center justify-between gap-3 rounded-xl border border-slate-100 p-3">
                  <span className="text-sm text-slate-700">{label}</span>
                  <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${style}`}>Available status</span>
                </div>
              ))}
            </div>
            <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800">
              <strong>Backend connection needed.</strong> Progress percentages, filtering, employee selection, and milestone updates require an authenticated progress API.
            </div>
          </div>
        </section>
      </div>
    </main>
  )
}

export default ProgressTrackerPage
