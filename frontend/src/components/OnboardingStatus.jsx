import { useState } from 'react'
import apiClient from '../api/client'

function formLabel(status) {
  return { Pending: 'Not submitted yet', Submitted: 'Awaiting HR review', Approved: 'Approved', Rejected: 'Changes requested' }[status] || status
}

// Employee-facing checklist of what is still needed before full access unlocks.
export function OnboardingProgressCard({ record, restricted }) {
  const requirements = record?.requirements
  if (!requirements) return null

  const documentsDone = requirements.documents_total > 0 && requirements.documents_verified === requirements.documents_total
  const formDone = !requirements.form_required || requirements.form_status === 'Approved'
  const items = [
    { label: `Documents verified (${requirements.documents_verified} of ${requirements.documents_total})`, done: documentsDone },
    ...(requirements.form_required ? [{ label: `Information form: ${formLabel(requirements.form_status)}`, done: formDone }] : []),
    { label: 'HR final sign-off', done: record.onboarding_status === 'Completed' },
  ]

  return (
    <section className="panel-surface mb-6 p-5 sm:p-6">
      <p className="section-label">Your onboarding checklist</p>
      <h2 className="mt-2 text-lg font-bold text-slate-900">
        {record.onboarding_status === 'Completed' ? 'Onboarding complete' : 'What is left to do'}
      </h2>
      {restricted && record.onboarding_status !== 'Completed' && (
        <p className="mt-1 text-sm text-slate-600">Leave, payroll, benefits and other areas unlock as soon as HR completes your onboarding.</p>
      )}
      <ul className="mt-4 space-y-2">
        {items.map((item) => (
          <li key={item.label} className="flex items-center gap-3 text-sm text-slate-700">
            <span aria-hidden="true" className={`inline-flex h-5 w-5 items-center justify-center rounded-full text-xs ${item.done ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>{item.done ? '✓' : '•'}</span>
            <span>{item.label}</span>
          </li>
        ))}
      </ul>
    </section>
  )
}

// HR/Admin: who is ready to be signed off, with a one-click completion.
export function OnboardingCompletionPanel({ records, onChanged }) {
  const [busyId, setBusyId] = useState(null)
  const [message, setMessage] = useState({ kind: '', text: '' })
  const open = records.filter((record) => record.onboarding_status !== 'Completed' && record.requirements)
  if (open.length === 0) return null

  const complete = async (record) => {
    setBusyId(record.onboarding_id)
    setMessage({ kind: '', text: '' })
    try {
      await apiClient.post('/api/onboarding/update.php', { onboarding_id: record.onboarding_id, onboarding_status: 'Completed' })
      setMessage({ kind: 'success', text: `Onboarding for employee #${record.employee_id} is complete. Their access is now unlocked.` })
      onChanged()
    } catch (error) {
      setMessage({ kind: 'error', text: error.response?.data?.message || 'Onboarding could not be completed.' })
    } finally {
      setBusyId(null)
    }
  }

  return (
    <section className="panel-surface mb-6 p-5 sm:p-6">
      <p className="section-label">HR action</p>
      <h2 className="mt-2 text-lg font-bold text-slate-900">Complete onboarding</h2>
      <p className="mt-1 text-sm text-slate-600">Completing onboarding unlocks the employee's full access. It needs every document verified{open.some((r) => r.requirements.form_required) ? ' and the information form approved' : ''}.</p>
      {message.text && <div className={`mt-4 rounded-xl border p-3 text-sm ${message.kind === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'}`} role={message.kind === 'error' ? 'alert' : 'status'}>{message.text}</div>}
      <div className="mt-4 space-y-3">
        {open.map((record) => {
          const requirements = record.requirements
          return (
            <article key={record.onboarding_id} className="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p className="font-semibold text-slate-900">Employee #{record.employee_id} <span className="text-xs font-normal text-slate-500">({record.onboarding_status}{record.restricts_access ? ', access restricted' : ''})</span></p>
                <p className="mt-1 text-xs text-slate-500">Documents {requirements.documents_verified}/{requirements.documents_total} verified{requirements.form_required ? ` · Form: ${formLabel(requirements.form_status)}` : ''}</p>
              </div>
              <button type="button" disabled={!requirements.ready_to_complete || busyId === record.onboarding_id} onClick={() => complete(record)} className="action-button-primary disabled:cursor-not-allowed disabled:opacity-50">
                {busyId === record.onboarding_id ? 'Completing...' : requirements.ready_to_complete ? 'Complete onboarding' : 'Not ready'}
              </button>
            </article>
          )
        })}
      </div>
    </section>
  )
}
