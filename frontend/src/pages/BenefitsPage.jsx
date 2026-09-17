import { useState } from 'react'
import { Link } from 'react-router-dom'

const benefitCategories = [
  { name: 'Health and wellness', description: 'Health insurance, wellness support, and related coverage.' },
  { name: 'Retirement and pension', description: 'Pension and long-term retirement provisions.' },
  { name: 'Allowances', description: 'Eligible work-related allowances and support.' },
  { name: 'Leave benefits', description: 'Benefits connected to leave and employee wellbeing.' },
  { name: 'Employee perks', description: 'Optional programs and employee discounts.' },
]

function readStoredUser() {
  try {
    return JSON.parse(localStorage.getItem('hrms_user') || 'null')
  } catch {
    return null
  }
}

function BenefitsPage() {
  const user = readStoredUser()
  const role = user?.role_name || 'Employee'
  const isEmployee = role === 'Employee'
  const [benefitsState] = useState('unavailable')

  const heading = isEmployee ? 'Your benefits overview' : `${role} benefits workspace`
  const description = isEmployee
    ? 'Review benefits available to you and your enrollment status when benefit records are connected.'
    : `Review employee benefits and enrollment information when the benefits service and role permissions are connected.`

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 p-4 sm:p-6">
          <div className="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">HRMS / Total rewards</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Benefits</h1>
              <p className="mt-2 max-w-2xl text-sm text-slate-600">Understand benefit programs, coverage, and enrollment in one workspace.</p>
            </div>
            <div className="flex flex-wrap items-center gap-3">
              <span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{role}</span>
              <Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link>
            </div>
          </div>
        </header>

        <section className="mb-6 grid gap-4 md:grid-cols-3">
          <div className="panel-surface p-5">
            <p className="section-label">Benefits records</p>
            <p className="mt-2 text-lg font-bold text-amber-600">Backend unavailable</p>
            <p className="mt-1 text-sm text-slate-500">No benefits endpoint is exposed yet.</p>
          </div>
          <div className="panel-surface p-5">
            <p className="section-label">Available plans</p>
            <p className="mt-2 text-lg font-bold text-slate-900">Not available</p>
            <p className="mt-1 text-sm text-slate-500">Plans will appear with real backend data.</p>
          </div>
          <div className="panel-surface p-5">
            <p className="section-label">Access scope</p>
            <p className="mt-2 text-lg font-bold text-emerald-600">{isEmployee ? 'My benefits' : 'Review ready'}</p>
            <p className="mt-1 text-sm text-slate-500">Based on your current role.</p>
          </div>
        </section>

        <section className="panel-surface mb-6 p-5 sm:p-6">
          <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <p className="section-label">Benefits overview</p>
              <h2 className="mt-2 text-lg font-bold text-slate-900">{heading}</h2>
              <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{description}</p>
            </div>
            <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Not connected</span>
          </div>

          {benefitsState === 'loading' && (
            <div className="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-700" role="status">Loading benefits information...</div>
          )}

          {benefitsState === 'error' && (
            <div className="mt-5 rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-700" role="alert">The benefits service could not be reached. Try again when the backend is available.</div>
          )}

          {benefitsState === 'unavailable' && (
            <div className="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-7 text-center" role="status">
              <p className="text-base font-semibold text-slate-800">No benefits data available</p>
              <p className="mx-auto mt-2 max-w-2xl text-sm leading-6 text-slate-600">The current PHP service does not provide benefits plans, employee benefit records, or enrollment data. No prices, coverage, deductions, or statuses are shown until the backend is connected.</p>
            </div>
          )}
        </section>

        <section className="mb-6 grid gap-6 lg:grid-cols-2">
          <div className="panel-surface p-5 sm:p-6">
            <div className="mb-5 flex items-start justify-between gap-3">
              <div>
                <p className="section-label">Available benefits</p>
                <h2 className="mt-2 text-lg font-bold text-slate-900">Benefit plans</h2>
              </div>
              <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Awaiting data</span>
            </div>
            <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center" role="status">
              <p className="text-sm font-semibold text-slate-800">No benefit plans to display</p>
              <p className="mt-2 text-sm leading-6 text-slate-600">Plan names, eligibility, coverage, and costs will be supplied by the Benefits API.</p>
            </div>
          </div>

          <div className="panel-surface p-5 sm:p-6">
            <div className="mb-5 flex items-start justify-between gap-3">
              <div>
                <p className="section-label">Current benefits</p>
                <h2 className="mt-2 text-lg font-bold text-slate-900">Enrollment status</h2>
              </div>
              <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Awaiting data</span>
            </div>
            <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center" role="status">
              <p className="text-sm font-semibold text-slate-800">No enrollment records to display</p>
              <p className="mt-2 text-sm leading-6 text-slate-600">Enrollment dates, coverage status, and benefit deductions will appear here once connected.</p>
            </div>
          </div>
        </section>

        <section className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
          <div className="panel-surface p-5 sm:p-6">
            <p className="section-label">Benefit categories</p>
            <h2 className="mt-2 text-lg font-bold text-slate-900">Benefits data model</h2>
            <p className="mt-2 text-sm leading-6 text-slate-600">These categories describe the future benefits workspace. They are not benefit plans or employee records.</p>
            <div className="mt-5 grid gap-3 sm:grid-cols-2">
              {benefitCategories.map((category) => (
                <div key={category.name} className="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                  <h3 className="font-semibold text-slate-800">{category.name}</h3>
                  <p className="mt-1 text-sm leading-6 text-slate-600">{category.description}</p>
                </div>
              ))}
            </div>
          </div>

          <div className="panel-surface p-5 sm:p-6">
            <p className="section-label">Role access</p>
            <h2 className="mt-2 text-lg font-bold text-slate-900">{isEmployee ? 'Personal benefits access' : `${role} benefits workspace`}</h2>
            <p className="mt-3 text-sm leading-6 text-slate-600">{isEmployee ? 'Employees will see their own benefits and enrollment details when those records are available.' : `${role} access is structured for future benefits review, without claiming administrative controls that the backend does not currently support.`}</p>
            <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800">
              <strong>Backend connection needed.</strong> Benefit enrollment, plan administration, eligibility, and deductions require authenticated benefits endpoints.
            </div>
            <dl className="mt-5 space-y-3 text-sm">
              <div className="flex items-center justify-between gap-4 border-b border-slate-100 pb-3"><dt className="text-slate-500">Current role</dt><dd className="font-semibold text-slate-900">{role}</dd></div>
              <div className="flex items-center justify-between gap-4"><dt className="text-slate-500">Benefit scope</dt><dd className="font-semibold text-slate-900">{isEmployee ? 'Own records' : 'Review ready'}</dd></div>
            </dl>
          </div>
        </section>
      </div>
    </main>
  )
}

export default BenefitsPage
