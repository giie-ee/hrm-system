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
  const [refreshKey, setRefreshKey] = useState(0)
  const [catalog, setCatalog] = useState({ types: [], documents: [] })
  const [requestForm, setRequestForm] = useState({ onboarding_id: '', document_name: '', custom_name: '', document_type: '' })
  const [requesting, setRequesting] = useState(false)
  const [requestMessage, setRequestMessage] = useState({ kind: '', text: '' })
  const canRequestDocuments = ['Admin', 'HR'].includes(role)

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
    setRequesting(true)
    setRequestMessage({ kind: '', text: '' })
    try {
      await apiClient.post('/api/onboarding/request-document.php', {
        onboarding_id: Number(requestForm.onboarding_id),
        document_name: documentName,
        document_type: requestForm.document_type || 'Other',
      })
      setRequestMessage({ kind: 'success', text: 'Document requested.' })
      setRequestForm((current) => ({ ...current, document_name: '', custom_name: '', document_type: '' }))
      setRefreshKey((key) => key + 1)
    } catch (error) {
      setRequestMessage({ kind: 'error', text: error.response?.data?.message || 'The document could not be requested.' })
    } finally {
      setRequesting(false)
    }
  }

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

        {canRequestDocuments && records.length > 0 && (
          <section className="panel-surface mb-6 p-5 sm:p-6">
            <p className="section-label">HR action</p>
            <h2 className="mt-2 text-lg font-bold text-slate-900">Request a document</h2>
            <form onSubmit={submitDocumentRequest} className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Employee onboarding</span>
                <select required name="onboarding_id" value={requestForm.onboarding_id} onChange={handleRequestChange} className="field-input">
                  <option value="">Select employee</option>
                  {records.map((record) => <option key={record.onboarding_id} value={record.onboarding_id}>Employee #{record.employee_id} ({record.onboarding_status})</option>)}
                </select>
              </label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Document</span>
                <select required name="document_name" value={requestForm.document_name} onChange={handleRequestChange} className="field-input">
                  <option value="">Select document</option>
                  {catalog.documents.map((item) => <option key={item.document_name} value={item.document_name}>{item.document_name}</option>)}
                  <option value="__other">Other (specify)</option>
                </select>
              </label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Document type</span>
                <select required name="document_type" value={requestForm.document_type} onChange={handleRequestChange} className="field-input">
                  <option value="">Select type</option>
                  {catalog.types.map((type) => <option key={type} value={type}>{type}</option>)}
                </select>
              </label>
              {requestForm.document_name === '__other' && (
                <label><span className="mb-2 block text-sm font-medium text-slate-700">Document name</span>
                  <input required name="custom_name" value={requestForm.custom_name} onChange={handleRequestChange} maxLength={150} className="field-input" />
                </label>
              )}
              <button type="submit" disabled={requesting || catalog.documents.length === 0} className="action-button-primary md:col-span-2 xl:col-span-4">{requesting ? 'Requesting...' : 'Request document'}</button>
            </form>
            {requestMessage.text && <p role={requestMessage.kind === 'error' ? 'alert' : 'status'} className={`mt-3 text-sm ${requestMessage.kind === 'error' ? 'text-red-700' : 'text-emerald-700'}`}>{requestMessage.text}</p>}
          </section>
        )}

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
