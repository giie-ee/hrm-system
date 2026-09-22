import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

function readStoredUser() {
  try { return JSON.parse(localStorage.getItem('hrms_user') || 'null') } catch { return null }
}

function OnboardingPage() {
  const user = readStoredUser()
  const role = user?.role_name || 'Employee'
  const [records, setRecords] = useState([])
  const [state, setState] = useState('loading')
  const [message, setMessage] = useState('')

  useEffect(() => {
    let active = true
    apiClient.get('/api/onboarding/get.php').then((response) => {
      if (!active) return
      setRecords(response.data.data || [])
      setState('ready')
    }).catch((error) => {
      if (!active) return
      setMessage(error.response?.data?.message || 'Unable to load onboarding records.')
      setState('error')
    })
    return () => { active = false }
  }, [])

  const latest = records[0]
  const documents = latest?.documents || []
  const verified = documents.filter((document) => document.document_status === 'Verified').length

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-6xl">
        <header className="panel-surface mb-6 p-4 sm:p-6">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div><p className="section-label">HRMS / Employee lifecycle</p><h1 className="mt-2 text-3xl font-bold text-slate-900">Onboarding</h1><p className="mt-2 text-sm text-slate-600">Track assigned onboarding work and document verification from the database.</p></div>
            <div className="flex items-center gap-3"><span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{role}</span><Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link></div>
          </div>
        </header>

        <section className="mb-6 grid gap-4 md:grid-cols-4">
          <div className="panel-surface p-5"><p className="section-label">Records</p><p className="mt-2 text-2xl font-bold text-slate-900">{state === 'ready' ? records.length : '—'}</p></div>
          <div className="panel-surface p-5"><p className="section-label">Current status</p><p className="mt-2 text-lg font-bold text-slate-900">{latest?.onboarding_status || (state === 'ready' ? 'Not started' : '—')}</p></div>
          <div className="panel-surface p-5"><p className="section-label">Documents</p><p className="mt-2 text-2xl font-bold text-slate-900">{documents.length}</p></div>
          <div className="panel-surface p-5"><p className="section-label">Verified</p><p className="mt-2 text-2xl font-bold text-emerald-600">{latest ? `${latest.verification_percentage || 0}%` : '—'}</p></div>
        </section>

        {state === 'loading' && <div className="panel-surface p-6 text-sm text-slate-600">Loading onboarding information...</div>}
        {state === 'error' && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">{message}</div>}
        {state === 'ready' && records.length === 0 && <div className="panel-surface p-7 text-center"><h2 className="font-semibold text-slate-900">No onboarding record yet</h2><p className="mt-2 text-sm text-slate-600">HR can create an onboarding record and request the documents needed for this employee.</p></div>}

        {latest && (
          <section className="grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
            <div className="panel-surface p-5 sm:p-6">
              <p className="section-label">Current onboarding</p>
              <h2 className="mt-2 text-xl font-bold text-slate-900">Employee #{latest.employee_id}</h2>
              <dl className="mt-5 space-y-3 text-sm">
                <div className="flex justify-between gap-4 border-b border-slate-100 pb-3"><dt className="text-slate-500">Start date</dt><dd className="font-semibold text-slate-900">{latest.start_date}</dd></div>
                <div className="flex justify-between gap-4 border-b border-slate-100 pb-3"><dt className="text-slate-500">Assigned HR user</dt><dd className="font-semibold text-slate-900">#{latest.assigned_hr_id || '—'}</dd></div>
                <div className="flex justify-between gap-4"><dt className="text-slate-500">Verified documents</dt><dd className="font-semibold text-slate-900">{verified} of {documents.length}</dd></div>
              </dl>
              {latest.notes && <p className="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">{latest.notes}</p>}
            </div>
            <div className="panel-surface p-5 sm:p-6">
              <div className="flex items-center justify-between gap-3"><div><p className="section-label">Required records</p><h2 className="mt-2 text-lg font-bold text-slate-900">Document checklist</h2></div><Link to="/documents" className="action-button-secondary">Open documents</Link></div>
              <div className="mt-5 space-y-3">
                {documents.length === 0 && <p className="rounded-xl bg-slate-50 p-5 text-sm text-slate-600">No documents have been requested.</p>}
                {documents.map((document) => <div key={document.document_id} className="flex items-center justify-between gap-4 rounded-xl border border-slate-200 p-4"><div><p className="font-semibold text-slate-900">{document.document_name}</p><p className="mt-1 text-xs text-slate-500">{document.document_type || 'General document'}</p></div><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${document.document_status === 'Verified' ? 'bg-emerald-100 text-emerald-700' : document.document_status === 'Rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'}`}>{document.document_status}</span></div>)}
              </div>
            </div>
          </section>
        )}
      </div>
    </main>
  )
}

export default OnboardingPage
