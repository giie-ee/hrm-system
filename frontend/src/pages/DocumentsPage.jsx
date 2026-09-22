import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

const allowedFileTypes = '.pdf,.jpg,.jpeg,.png'
const maxFileSize = 5 * 1024 * 1024

function readStoredUser() {
  try { return JSON.parse(localStorage.getItem('hrms_user') || 'null') } catch { return null }
}

async function fetchDocuments() {
  const response = await apiClient.get('/api/onboarding/get.php')
  return (response.data.data || []).flatMap((onboarding) =>
    (onboarding.documents || []).map((document) => ({ ...document, employee_id: onboarding.employee_id })))
}

function DocumentsPage() {
  const user = readStoredUser()
  const role = user?.role_name || 'Employee'
  const fileInputRef = useRef(null)
  const [records, setRecords] = useState([])
  const [search, setSearch] = useState('')
  const [selectedDocumentId, setSelectedDocumentId] = useState('')
  const [selectedFile, setSelectedFile] = useState(null)
  const [state, setState] = useState('loading')
  const [message, setMessage] = useState('')

  const loadDocuments = useCallback(async () => {
    try {
      const documents = await fetchDocuments()
      setRecords(documents)
      setSelectedDocumentId((current) => current || String(documents.find((item) => ['Pending', 'Rejected'].includes(item.document_status))?.document_id || ''))
      setState('ready')
    } catch (error) {
      setMessage(error.response?.data?.message || 'Unable to load document records.')
      setState('error')
    }
  }, [])

  useEffect(() => {
    let active = true
    fetchDocuments().then((documents) => {
      if (!active) return
      setRecords(documents)
      setSelectedDocumentId(String(documents.find((item) => ['Pending', 'Rejected'].includes(item.document_status))?.document_id || ''))
      setState('ready')
    }).catch((error) => {
      if (!active) return
      setMessage(error.response?.data?.message || 'Unable to load document records.')
      setState('error')
    })
    return () => { active = false }
  }, [])

  const visibleRecords = useMemo(() => records.filter((document) =>
    document.document_name.toLowerCase().includes(search.trim().toLowerCase())), [records, search])
  const uploadable = records.filter((document) => ['Pending', 'Rejected'].includes(document.document_status))

  const handleFileChange = (event) => {
    const file = event.target.files?.[0]
    setMessage('')
    if (!file) return setSelectedFile(null)
    if (!['application/pdf', 'image/png', 'image/jpeg'].includes(file.type) || file.size > maxFileSize) {
      setSelectedFile(null)
      setMessage('Choose a PDF, PNG, or JPEG file no larger than 5 MB.')
      event.target.value = ''
      return
    }
    setSelectedFile(file)
  }

  const uploadDocument = async () => {
    if (!selectedDocumentId || !selectedFile) return
    setState('saving')
    setMessage('')
    const body = new FormData()
    body.append('document_id', selectedDocumentId)
    body.append('file', selectedFile)
    try {
      await apiClient.post('/api/documents/submit.php', body, { headers: { 'Content-Type': 'multipart/form-data' } })
      setSelectedFile(null)
      if (fileInputRef.current) fileInputRef.current.value = ''
      setMessage('Document submitted for verification.')
      await loadDocuments()
    } catch (error) {
      setMessage(error.response?.data?.message || 'Unable to upload the document.')
      setState('error')
    }
  }

  const downloadDocument = async (document) => {
    try {
      const response = await apiClient.get(`/api/documents/download.php?document_id=${document.document_id}`, { responseType: 'blob' })
      const url = URL.createObjectURL(response.data)
      const link = window.document.createElement('a')
      link.href = url
      link.download = document.document_name
      link.click()
      URL.revokeObjectURL(url)
    } catch (error) {
      setMessage(error.response?.data?.message || 'Unable to download the document.')
    }
  }

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 p-4 sm:p-6"><div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div><p className="section-label">HRMS / Employee records</p><h1 className="mt-2 text-3xl font-bold text-slate-900">Documents</h1><p className="mt-2 text-sm text-slate-600">Submit and retrieve onboarding files through the protected document service.</p></div><div className="flex items-center gap-3"><span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{role}</span><Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link></div></div></header>

        <section className="mb-6 grid gap-4 md:grid-cols-3"><div className="panel-surface p-5"><p className="section-label">Records in scope</p><p className="mt-2 text-2xl font-bold text-slate-900">{state === 'loading' ? '—' : records.length}</p></div><div className="panel-surface p-5"><p className="section-label">Verified</p><p className="mt-2 text-2xl font-bold text-emerald-600">{records.filter((item) => item.document_status === 'Verified').length}</p></div><div className="panel-surface p-5"><p className="section-label">Awaiting upload</p><p className="mt-2 text-2xl font-bold text-amber-600">{uploadable.length}</p></div></section>

        {message && <div className={`mb-6 rounded-xl border p-4 text-sm ${state === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'}`}>{message}</div>}

        <section className="mb-6 grid gap-6 lg:grid-cols-[1.35fr_0.65fr]">
          <div className="panel-surface p-5 sm:p-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p className="section-label">Document library</p><h2 className="mt-2 text-lg font-bold text-slate-900">Accessible files</h2></div><input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search document name" className="field-input sm:max-w-xs" /></div>
            <div className="mt-5 space-y-3">
              {state === 'loading' && <p className="text-sm text-slate-600">Loading documents...</p>}
              {state === 'ready' && visibleRecords.length === 0 && <p className="rounded-xl bg-slate-50 p-5 text-sm text-slate-600">No matching document records.</p>}
              {visibleRecords.map((document) => <article key={document.document_id} className="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between"><div><p className="font-semibold text-slate-900">{document.document_name}</p><p className="mt-1 text-xs text-slate-500">Employee #{document.employee_id} · {document.document_type || 'General'}</p></div><div className="flex items-center gap-3"><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{document.document_status}</span>{document.document_status === 'Verified' && <button type="button" onClick={() => downloadDocument(document)} className="action-button-secondary">Download</button>}</div></article>)}
            </div>
          </div>

          <div className="panel-surface p-5 sm:p-6">
            <p className="section-label">Secure upload</p><h2 className="mt-2 text-lg font-bold text-slate-900">Submit requested file</h2>
            <label className="mt-5 block text-sm font-medium text-slate-700">Document request<select value={selectedDocumentId} onChange={(event) => setSelectedDocumentId(event.target.value)} className="field-input mt-2"><option value="">Choose a request</option>{uploadable.map((document) => <option key={document.document_id} value={document.document_id}>{document.document_name}</option>)}</select></label>
            <label className="mt-4 block text-sm font-medium text-slate-700">PDF, PNG, or JPEG<input ref={fileInputRef} type="file" accept={allowedFileTypes} onChange={handleFileChange} className="field-input mt-2" /></label>
            {selectedFile && <p className="mt-3 text-xs text-slate-500">Selected: {selectedFile.name}</p>}
            <button type="button" onClick={uploadDocument} disabled={!selectedDocumentId || !selectedFile || state === 'saving'} className="action-button-primary mt-5 w-full disabled:cursor-not-allowed disabled:opacity-50">{state === 'saving' ? 'Uploading...' : 'Submit document'}</button>
            <p className="mt-4 text-xs leading-5 text-slate-500">Files are validated by type and size, stored outside the web root, and downloaded only through an authorized endpoint.</p>
          </div>
        </section>
      </div>
    </main>
  )
}

export default DocumentsPage
