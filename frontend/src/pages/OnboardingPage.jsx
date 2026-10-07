import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

const emptyOnboardingForm = {
  phone: '', address: '', emergency_contact_name: '', emergency_contact_phone: '',
  emergency_contact_relationship: '', bank_name: '', bank_account_number: '',
  tax_number: '', national_id: '', next_of_kin_name: '',
}

const fieldLabels = {
  phone: 'Phone', address: 'Home address', emergency_contact_name: 'Emergency contact name',
  emergency_contact_phone: 'Emergency contact phone', emergency_contact_relationship: 'Emergency contact relationship',
  bank_name: 'Bank name', bank_account_number: 'Bank account number', tax_number: 'Tax / TPIN number',
  national_id: 'National ID', next_of_kin_name: 'Next of kin',
}

function readStoredUser() {
  try { return JSON.parse(localStorage.getItem('hrms_user') || 'null') } catch { return null }
}

function humanValue(value) {
  if (value === true) return 'Received and encrypted'
  if (value === false || value === null || value === '') return 'Not provided'
  return String(value)
}

function OnboardingPage() {
  const user = readStoredUser()
  const role = user?.role_name || 'Employee'
  const canRequestDocuments = ['Admin', 'HR'].includes(role)
  const canReviewForms = ['Admin', 'HR'].includes(role)
  const canLoadForms = ['Admin', 'HR', 'Manager', 'Employee'].includes(role)
  const [records, setRecords] = useState([])
  const [forms, setForms] = useState([])
  const [state, setState] = useState('loading')
  const [message, setMessage] = useState('')
  const [refreshKey, setRefreshKey] = useState(0)
  const [catalog, setCatalog] = useState({ types: [], documents: [] })
  const [requestForm, setRequestForm] = useState({ onboarding_id: '', document_name: '', custom_name: '', document_type: '' })
  const [employeeForm, setEmployeeForm] = useState(emptyOnboardingForm)
  const [reviewReasons, setReviewReasons] = useState({})
  const [bankAccessReasons, setBankAccessReasons] = useState({})
  const [bankingViews, setBankingViews] = useState({})
  const [requesting, setRequesting] = useState(false)
  const [formBusy, setFormBusy] = useState('')
  const [requestMessage, setRequestMessage] = useState({ kind: '', text: '' })
  const [formMessage, setFormMessage] = useState({ kind: '', text: '' })

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
  }, [refreshKey])

  useEffect(() => {
    if (!canLoadForms) return undefined
    let active = true
    apiClient.get('/api/onboarding/form-get.php').then((response) => {
      if (!active) return
      const nextForms = response.data.data?.forms || []
      setForms(nextForms)
      const own = nextForms.find((form) => Number(form.employee_id) === Number(user?.employee_id))
      if (own?.form_data) {
        const data = own.form_data
        setEmployeeForm({ ...emptyOnboardingForm, ...Object.fromEntries(Object.keys(emptyOnboardingForm).filter((key) => !['bank_name', 'bank_account_number'].includes(key)).map((key) => [key, data[key] || ''])) })
      }
    }).catch((error) => {
      if (active) setFormMessage({ kind: 'error', text: error.response?.data?.message || 'Onboarding forms could not be loaded.' })
    })
    return () => { active = false }
  }, [canLoadForms, refreshKey, user?.employee_id])

  useEffect(() => {
    if (!canRequestDocuments) return undefined
    let active = true
    apiClient.get('/api/onboarding/document-types.php').then((response) => {
      if (active) setCatalog(response.data.data || { types: [], documents: [] })
    }).catch(() => {
      if (active) setRequestMessage({ kind: 'error', text: 'Document types could not be loaded.' })
    })
    return () => { active = false }
  }, [canRequestDocuments])

  const handleRequestChange = (event) => {
    const { name, value } = event.target
    setRequestForm((current) => {
      const next = { ...current, [name]: value }
      if (name === 'document_name') {
        const match = catalog.documents.find((item) => item.document_name === value)
        next.document_type = match ? match.document_type : (value === '__other' ? 'Other' : '')
      }
      return next
    })
  }

  const submitDocumentRequest = async (event) => {
    event.preventDefault()
    const documentName = requestForm.document_name === '__other' ? requestForm.custom_name.trim() : requestForm.document_name
    if (!documentName) { setRequestMessage({ kind: 'error', text: 'Choose a document to request.' }); return }
    setRequesting(true); setRequestMessage({ kind: '', text: '' })
    try {
      await apiClient.post('/api/onboarding/request-document.php', { onboarding_id: Number(requestForm.onboarding_id), document_name: documentName, document_type: requestForm.document_type || 'Other' })
      setRequestMessage({ kind: 'success', text: 'Document requested.' })
      setRequestForm((current) => ({ ...current, document_name: '', custom_name: '', document_type: '' }))
      setRefreshKey((key) => key + 1)
    } catch (error) {
      setRequestMessage({ kind: 'error', text: error.response?.data?.message || 'The document could not be requested.' })
    } finally { setRequesting(false) }
  }

  const submitEmployeeForm = async (event) => {
    event.preventDefault(); setFormBusy('submit'); setFormMessage({ kind: '', text: '' })
    try {
      await apiClient.post('/api/onboarding/form-submit.php', employeeForm)
      setFormMessage({ kind: 'success', text: 'Your onboarding form was encrypted and submitted to HR.' })
      setEmployeeForm(emptyOnboardingForm)
      setRefreshKey((key) => key + 1)
    } catch (error) {
      setFormMessage({ kind: 'error', text: error.response?.data?.message || 'The onboarding form could not be submitted.' })
    } finally { setFormBusy('') }
  }

  const reviewForm = async (formId, status) => {
    const reason = reviewReasons[formId] || ''
    if (status === 'Rejected' && !reason.trim()) { setFormMessage({ kind: 'error', text: 'Add a reason before rejecting the form.' }); return }
    setFormBusy(`review-${formId}`); setFormMessage({ kind: '', text: '' })
    try {
      await apiClient.post('/api/onboarding/form-review.php', { form_id: formId, status, reason })
      setFormMessage({ kind: 'success', text: `Onboarding form ${status.toLowerCase()}.` })
      setRefreshKey((key) => key + 1)
    } catch (error) {
      setFormMessage({ kind: 'error', text: error.response?.data?.message || 'The onboarding form could not be reviewed.' })
    } finally { setFormBusy('') }
  }

  const revealBanking = async (formId) => {
    const reason = (bankAccessReasons[formId] || '').trim()
    if (!reason) { setFormMessage({ kind: 'error', text: 'Enter a reason before accessing protected banking information.' }); return }
    setFormBusy(`banking-${formId}`); setFormMessage({ kind: '', text: '' })
    try {
      const response = await apiClient.post('/api/onboarding/form-banking.php', { form_id: formId, reason })
      setBankingViews((current) => ({ ...current, [formId]: response.data.data }))
      window.setTimeout(() => setBankingViews((current) => {
        const next = { ...current }; delete next[formId]; return next
      }), 60000)
      setFormMessage({ kind: 'success', text: 'Banking information revealed temporarily. This access was recorded in the audit log.' })
    } catch (error) {
      setFormMessage({ kind: 'error', text: error.response?.data?.message || 'Protected banking information could not be accessed.' })
    } finally { setFormBusy('') }
  }

  const latest = records[0]
  const documents = latest?.documents || []
  const verified = documents.filter((document) => document.document_status === 'Verified').length
  const ownForm = forms.find((form) => Number(form.employee_id) === Number(user?.employee_id))
  const maySubmitOwnForm = Boolean(ownForm) && (!ownForm.form_id || ['Pending', 'Rejected'].includes(ownForm.form_status))

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8"><div className="mx-auto max-w-6xl">
      <header className="panel-surface mb-6 p-4 sm:p-6"><div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div><p className="section-label">Nexa People / Employee lifecycle</p><h1 className="mt-2 text-3xl font-bold text-slate-900">Onboarding</h1><p className="mt-2 text-sm text-slate-600">Complete employee information, documents and HR verification in one protected workflow.</p></div><div className="flex items-center gap-3"><span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{role}</span><Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link></div></div></header>

      <section className="mb-6 grid gap-4 md:grid-cols-4"><div className="panel-surface p-5"><p className="section-label">Records</p><p className="mt-2 text-2xl font-bold text-slate-900">{state === 'ready' ? records.length : '—'}</p></div><div className="panel-surface p-5"><p className="section-label">Current status</p><p className="mt-2 text-lg font-bold text-slate-900">{latest?.onboarding_status || (state === 'ready' ? 'Not started' : '—')}</p></div><div className="panel-surface p-5"><p className="section-label">Documents</p><p className="mt-2 text-2xl font-bold text-slate-900">{documents.length}</p></div><div className="panel-surface p-5"><p className="section-label">Verified</p><p className="mt-2 text-2xl font-bold text-emerald-600">{latest ? `${latest.verification_percentage || 0}%` : '—'}</p></div></section>

      {state === 'loading' && <div className="panel-surface p-6 text-sm text-slate-600">Loading onboarding information...</div>}
      {state === 'error' && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">{message}</div>}
      {formMessage.text && <div className={`mb-6 rounded-xl border p-4 text-sm ${formMessage.kind === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'}`} role={formMessage.kind === 'error' ? 'alert' : 'status'}>{formMessage.text}</div>}
      {state === 'ready' && records.length === 0 && <div className="panel-surface p-7 text-center"><h2 className="font-semibold text-slate-900">No onboarding record yet</h2><p className="mt-2 text-sm text-slate-600">HR can create an onboarding record and request the documents needed for this employee.</p></div>}

      {ownForm && (
        <section className="panel-surface mb-6 p-5 sm:p-6">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><p className="section-label">Employee form</p><h2 className="mt-2 text-lg font-bold text-slate-900">Your onboarding information</h2><p className="mt-1 text-sm text-slate-600">Banking values are encrypted before storage and are never returned by this screen.</p></div><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{ownForm.form_status}</span></div>
          {ownForm.form_status === 'Rejected' && <p className="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">HR requested changes: {ownForm.rejection_reason}. Re-enter the banking fields because protected values cannot be displayed.</p>}
          {maySubmitOwnForm ? <form onSubmit={submitEmployeeForm} className="mt-5 grid gap-4 md:grid-cols-2">
            {Object.keys(emptyOnboardingForm).map((field) => <label key={field} className={field === 'address' ? 'md:col-span-2' : ''}><span className="mb-2 block text-sm font-medium text-slate-700">{fieldLabels[field]}{!['tax_number', 'national_id', 'next_of_kin_name'].includes(field) ? ' *' : ''}</span>{field === 'address' ? <textarea required rows="3" maxLength="300" className="field-input" value={employeeForm[field]} onChange={(event) => setEmployeeForm((current) => ({ ...current, [field]: event.target.value }))} /> : <input required={!['tax_number', 'national_id', 'next_of_kin_name'].includes(field)} type={field === 'bank_account_number' ? 'password' : 'text'} autoComplete="off" maxLength={field === 'bank_name' ? 100 : field === 'bank_account_number' ? 40 : 100} className="field-input" value={employeeForm[field]} onChange={(event) => setEmployeeForm((current) => ({ ...current, [field]: event.target.value }))} />}</label>)}
            <button type="submit" disabled={Boolean(formBusy)} className="action-button-primary md:col-span-2">{formBusy === 'submit' ? 'Encrypting and submitting...' : 'Submit onboarding form'}</button>
          </form> : <div className="mt-5 rounded-xl bg-slate-50 p-5 text-sm text-slate-700">{ownForm.form_status === 'Approved' ? 'HR has approved this form.' : 'This form is awaiting HR review.'} {ownForm.form_data?.banking_submitted && 'Protected banking information was received.'}</div>}
        </section>
      )}

      {canReviewForms && (
        <section className="panel-surface mb-6 p-5 sm:p-6"><p className="section-label">HR review</p><h2 className="mt-2 text-lg font-bold text-slate-900">Submitted onboarding forms</h2><p className="mt-1 text-sm text-slate-600">Only HR/Admin can review these forms. Banking values remain encrypted and are not displayed.</p><div className="mt-5 space-y-4">
          {forms.filter((form) => form.form_id).length === 0 && <p className="rounded-xl bg-slate-50 p-5 text-sm text-slate-600">No employee forms have been submitted.</p>}
          {forms.filter((form) => form.form_id).map((form) => <article key={form.form_id} className="rounded-xl border border-slate-200 p-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><h3 className="font-bold text-slate-900">{form.employee_name}</h3><p className="text-xs text-slate-500">Employee #{form.employee_id}</p></div><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{form.form_status}</span></div><dl className="mt-4 grid gap-3 sm:grid-cols-2">{Object.entries(form.form_data || {}).map(([key, value]) => <div key={key} className="rounded-lg bg-slate-50 p-3"><dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{key === 'banking_submitted' ? 'Banking information' : (fieldLabels[key] || key.replaceAll('_', ' '))}</dt><dd className="mt-1 break-words text-sm text-slate-800">{humanValue(value)}</dd></div>)}</dl>{form.form_data?.banking_submitted && Number(form.employee_id) !== Number(user?.employee_id) && <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4"><p className="text-sm font-semibold text-amber-900">Protected banking access</p><p className="mt-1 text-xs text-amber-800">Use only for a legitimate HR or payroll task. Every reveal is audited and automatically hidden after 60 seconds.</p><div className="mt-3 flex flex-col gap-3 sm:flex-row"><input className="field-input" maxLength="250" placeholder="Reason, e.g. payroll verification" value={bankAccessReasons[form.form_id] || ''} onChange={(event) => setBankAccessReasons((current) => ({ ...current, [form.form_id]: event.target.value }))} /><button type="button" disabled={Boolean(formBusy)} onClick={() => revealBanking(form.form_id)} className="action-button-secondary">{formBusy === `banking-${form.form_id}` ? 'Accessing...' : 'Reveal for 60 seconds'}</button></div>{bankingViews[form.form_id] && <div className="mt-3 rounded-lg bg-white p-3 text-sm text-slate-800"><p><span className="font-semibold">Bank:</span> {bankingViews[form.form_id].bank_name}</p><p className="mt-1"><span className="font-semibold">Account:</span> {bankingViews[form.form_id].bank_account_number}</p><button type="button" className="mt-2 text-xs font-semibold text-sky-700" onClick={() => setBankingViews((current) => { const next = { ...current }; delete next[form.form_id]; return next })}>Hide now</button></div>}</div>}{form.form_status === 'Submitted' && Number(form.employee_id) !== Number(user?.employee_id) && <div className="mt-4"><textarea className="field-input" rows="2" placeholder="Reason required only when rejecting" value={reviewReasons[form.form_id] || ''} onChange={(event) => setReviewReasons((current) => ({ ...current, [form.form_id]: event.target.value }))} /><div className="mt-3 flex flex-wrap gap-3"><button type="button" disabled={Boolean(formBusy)} onClick={() => reviewForm(form.form_id, 'Approved')} className="action-button-primary">Approve</button><button type="button" disabled={Boolean(formBusy)} onClick={() => reviewForm(form.form_id, 'Rejected')} className="action-button-secondary">Reject</button></div></div>}{form.form_status === 'Submitted' && Number(form.employee_id) === Number(user?.employee_id) && <p className="mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-600">Another HR/Admin user must review your form.</p>}{form.rejection_reason && <p className="mt-3 text-sm text-red-700">Reason: {form.rejection_reason}</p>}</article>)}
        </div></section>
      )}

      {role === 'Manager' && <div className="mb-6 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">Managers can monitor assigned onboarding and document progress. They can view and submit only their own personal onboarding form; employee forms under their team remain restricted to HR/Admin.</div>}

      {canRequestDocuments && records.length > 0 && <section className="panel-surface mb-6 p-5 sm:p-6"><p className="section-label">HR action</p><h2 className="mt-2 text-lg font-bold text-slate-900">Request a document</h2><form onSubmit={submitDocumentRequest} className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4"><label><span className="mb-2 block text-sm font-medium text-slate-700">Employee onboarding</span><select required name="onboarding_id" value={requestForm.onboarding_id} onChange={handleRequestChange} className="field-input"><option value="">Select employee</option>{records.map((record) => <option key={record.onboarding_id} value={record.onboarding_id}>Employee #{record.employee_id} ({record.onboarding_status})</option>)}</select></label><label><span className="mb-2 block text-sm font-medium text-slate-700">Document</span><select required name="document_name" value={requestForm.document_name} onChange={handleRequestChange} className="field-input"><option value="">Select document</option>{catalog.documents.map((item) => <option key={item.document_name} value={item.document_name}>{item.document_name}</option>)}<option value="__other">Other (specify)</option></select></label><label><span className="mb-2 block text-sm font-medium text-slate-700">Document type</span><select required name="document_type" value={requestForm.document_type} onChange={handleRequestChange} className="field-input"><option value="">Select type</option>{catalog.types.map((type) => <option key={type} value={type}>{type}</option>)}</select></label>{requestForm.document_name === '__other' && <label><span className="mb-2 block text-sm font-medium text-slate-700">Document name</span><input required name="custom_name" value={requestForm.custom_name} onChange={handleRequestChange} maxLength="150" className="field-input" /></label>}<button type="submit" disabled={requesting || catalog.documents.length === 0} className="action-button-primary md:col-span-2 xl:col-span-4">{requesting ? 'Requesting...' : 'Request document'}</button></form>{requestMessage.text && <p role={requestMessage.kind === 'error' ? 'alert' : 'status'} className={`mt-3 text-sm ${requestMessage.kind === 'error' ? 'text-red-700' : 'text-emerald-700'}`}>{requestMessage.text}</p>}</section>}

      {latest && <section className="grid gap-6 lg:grid-cols-[0.85fr_1.15fr]"><div className="panel-surface p-5 sm:p-6"><p className="section-label">Current onboarding</p><h2 className="mt-2 text-xl font-bold text-slate-900">Employee #{latest.employee_id}</h2><dl className="mt-5 space-y-3 text-sm"><div className="flex justify-between gap-4 border-b border-slate-100 pb-3"><dt className="text-slate-500">Start date</dt><dd className="font-semibold text-slate-900">{latest.start_date}</dd></div><div className="flex justify-between gap-4 border-b border-slate-100 pb-3"><dt className="text-slate-500">Assigned HR user</dt><dd className="font-semibold text-slate-900">#{latest.assigned_hr_id || '—'}</dd></div><div className="flex justify-between gap-4"><dt className="text-slate-500">Verified documents</dt><dd className="font-semibold text-slate-900">{verified} of {documents.length}</dd></div></dl>{latest.notes && <p className="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">{latest.notes}</p>}</div><div className="panel-surface p-5 sm:p-6"><div className="flex items-center justify-between gap-3"><div><p className="section-label">Required records</p><h2 className="mt-2 text-lg font-bold text-slate-900">Document checklist</h2></div><Link to="/documents" className="action-button-secondary">Open documents</Link></div><div className="mt-5 space-y-3">{documents.length === 0 && <p className="rounded-xl bg-slate-50 p-5 text-sm text-slate-600">No documents have been requested.</p>}{documents.map((document) => <div key={document.document_id} className="flex items-center justify-between gap-4 rounded-xl border border-slate-200 p-4"><div><p className="font-semibold text-slate-900">{document.document_name}</p><p className="mt-1 text-xs text-slate-500">{document.document_type || 'General document'}</p></div><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${document.document_status === 'Verified' ? 'bg-emerald-100 text-emerald-700' : document.document_status === 'Rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'}`}>{document.document_status}</span></div>)}</div></div></section>}
    </div></main>
  )
}

export default OnboardingPage
