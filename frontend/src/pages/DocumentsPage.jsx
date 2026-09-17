import { useRef, useState } from 'react'
import { Link } from 'react-router-dom'

const documentCategories = [
  { name: 'Contracts', description: 'Employment agreements and amendments', tone: 'sky' },
  { name: 'Identification', description: 'Identity and right-to-work records', tone: 'emerald' },
  { name: 'Certificates', description: 'Training, education, and compliance', tone: 'amber' },
  { name: 'Payslips', description: 'Payroll statements and summaries', tone: 'sky' },
  { name: 'Leave documents', description: 'Supporting files for leave requests', tone: 'emerald' },
  { name: 'Other HR documents', description: 'Additional employee records', tone: 'amber' },
]

const allowedFileTypes = '.pdf,.doc,.docx,.jpg,.jpeg,.png'
const allowedFileExtensions = new Set(['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'])
const maxFileSize = 10 * 1024 * 1024

function readStoredUser() {
  try {
    return JSON.parse(localStorage.getItem('hrms_user') || 'null')
  } catch {
    return null
  }
}

function formatFileSize(bytes) {
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function getFileExtension(fileName) {
  return fileName.split('.').pop()?.toLowerCase() || ''
}

function formatFileType(file) {
  if (file.type) return file.type
  return `${getFileExtension(file.name).toUpperCase()} document`
}

function DocumentsPage() {
  const user = readStoredUser()
  const role = user?.role_name || 'Employee'
  const isEmployee = role === 'Employee'
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('')
  const [selectedFile, setSelectedFile] = useState(null)
  const [fileError, setFileError] = useState('')
  const fileInputRef = useRef(null)
  const documentState = 'unavailable'

  const handleFileChange = (event) => {
    const file = event.target.files?.[0]
    setFileError('')

    if (!file) {
      setSelectedFile(null)
      return
    }

    if (!allowedFileExtensions.has(getFileExtension(file.name))) {
      setSelectedFile(null)
      setFileError('Unsupported file type. Choose a PDF, DOC, DOCX, JPG, JPEG, or PNG file.')
      event.target.value = ''
      return
    }

    if (file.size > maxFileSize) {
      setSelectedFile(null)
      setFileError('This file is larger than 10 MB. Choose a smaller file.')
      event.target.value = ''
      return
    }

    setSelectedFile(file)
  }

  const clearSelectedFile = () => {
    setSelectedFile(null)
    setFileError('')
    if (fileInputRef.current) fileInputRef.current.value = ''
  }

  const roleDescription = isEmployee
    ? 'You can view your own HR documents and prepare files for upload when the document service is connected.'
    : `${role} access is ready for broader document management when employee document records are available.`

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 p-4 sm:p-6">
          <div className="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">HRMS / Employee records</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Documents</h1>
              <p className="mt-2 max-w-2xl text-sm text-slate-600">Keep contracts, certificates, payslips, and other HR records in one place.</p>
            </div>

            <div className="flex flex-wrap items-center gap-3">
              <span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{role}</span>
              <Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link>
            </div>
          </div>
        </header>

        <section className="mb-6 grid gap-4 md:grid-cols-3">
          <div className="panel-surface p-5">
            <p className="section-label">Document records</p>
            <p className="mt-2 text-lg font-bold text-amber-600">Backend unavailable</p>
            <p className="mt-1 text-sm text-slate-500">No document endpoint is exposed yet.</p>
          </div>
          <div className="panel-surface p-5">
            <p className="section-label">Categories</p>
            <p className="mt-2 text-lg font-bold text-slate-900">{documentCategories.length} HR categories</p>
            <p className="mt-1 text-sm text-slate-500">Ready for connected records.</p>
          </div>
          <div className="panel-surface p-5">
            <p className="section-label">Access scope</p>
            <p className="mt-2 text-lg font-bold text-emerald-600">{isEmployee ? 'My documents' : 'Team documents'}</p>
            <p className="mt-1 text-sm text-slate-500">Based on your current role.</p>
          </div>
        </section>

        <section className="panel-surface mb-6 p-5 sm:p-6">
          <div className="mb-5 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p className="section-label">Document library</p>
              <h2 className="mt-2 text-lg font-bold text-slate-900">Your document records</h2>
              <p className="mt-1 text-sm text-slate-600">Search and filter controls are ready for the document API.</p>
            </div>
            <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Not connected</span>
          </div>

          <div className="mb-5 grid gap-3 md:grid-cols-[1fr_220px]">
            <label>
              <span className="mb-2 block text-sm font-medium text-slate-700">Search documents</span>
              <input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search by document name"
                className="field-input"
                disabled
                aria-describedby="document-search-note"
              />
            </label>
            <label>
              <span className="mb-2 block text-sm font-medium text-slate-700">Document category</span>
              <select value={category} onChange={(event) => setCategory(event.target.value)} className="field-input" disabled>
                <option value="">All categories</option>
                {documentCategories.map((item) => <option key={item.name} value={item.name}>{item.name}</option>)}
              </select>
            </label>
          </div>
          <p id="document-search-note" className="mb-5 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">Search and filtering will activate when the backend returns document records.</p>

          {documentState === 'loading' && (
            <div className="rounded-xl border border-sky-200 bg-sky-50 p-7 text-center text-sm text-sky-700" role="status">Loading document records...</div>
          )}

          {documentState === 'error' && (
            <div className="rounded-xl border border-red-200 bg-red-50 p-7 text-center text-sm text-red-700" role="alert">The document service could not be reached. Try again when the backend is available.</div>
          )}

          {documentState === 'unavailable' && (
            <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-7 text-center" role="status">
              <p className="text-base font-semibold text-slate-800">No document records available</p>
              <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-600">The current PHP service does not expose a documents list endpoint, so there are no records to display. View and download actions will appear here once records are connected.</p>
              <div className="mt-5 flex flex-wrap justify-center gap-2 text-xs font-medium text-slate-500">
                <span className="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">View unavailable</span>
                <span className="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">Download unavailable</span>
              </div>
            </div>
          )}
        </section>

        <section className="mb-6">
          <div className="mb-4">
            <p className="section-label">Document categories</p>
            <h2 className="mt-2 text-lg font-bold text-slate-900">Organise HR records by type</h2>
          </div>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {documentCategories.map((item) => (
              <div key={item.name} className="panel-surface p-4">
                <div className={`mb-3 h-2 w-10 rounded-full ${item.tone === 'sky' ? 'bg-sky-400' : item.tone === 'emerald' ? 'bg-emerald-400' : 'bg-amber-400'}`} />
                <h3 className="font-semibold text-slate-900">{item.name}</h3>
                <p className="mt-1 text-sm text-slate-600">{item.description}</p>
              </div>
            ))}
          </div>
        </section>

        <section className="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
          <div className="panel-surface p-5 sm:p-6">
            <div className="mb-5">
              <p className="section-label">Add a document</p>
              <h2 className="mt-2 text-lg font-bold text-slate-900">Prepare an upload</h2>
              <p className="mt-1 text-sm text-slate-600">Select a file now; submission will be enabled after the upload endpoint is added.</p>
            </div>

            <label htmlFor="document-file" className="flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-sky-300 bg-sky-50 p-6 text-center transition hover:border-sky-500 hover:bg-sky-100">
              <span className="text-sm font-semibold text-sky-800">Choose a document file</span>
              <span className="mt-1 text-xs text-sky-700">PDF, DOC, DOCX, JPG, JPEG, or PNG up to 10 MB</span>
              <input ref={fileInputRef} id="document-file" type="file" accept={allowedFileTypes} onChange={handleFileChange} className="sr-only" />
            </label>

            {fileError && <p className="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{fileError}</p>}

            {selectedFile && (
              <div className="mt-4 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                  <p className="truncate text-sm font-semibold text-slate-900">{selectedFile.name}</p>
                  <p className="mt-1 text-xs text-slate-500">{formatFileSize(selectedFile.size)} · {formatFileType(selectedFile)}</p>
                </div>
                <button type="button" className="action-button-secondary self-start sm:self-auto" onClick={clearSelectedFile}>Remove file</button>
              </div>
            )}

            <div className="mt-5 flex flex-wrap gap-3">
              <button type="button" className="action-button-primary" disabled title="Document upload will be available once the Documents API is connected.">Upload file</button>
              <span className="self-center text-xs text-amber-700">Document upload will be available once the Documents API is connected.</span>
            </div>
          </div>

          <div className="panel-surface p-5 sm:p-6">
            <p className="section-label">Role access</p>
            <h2 className="mt-2 text-lg font-bold text-slate-900">{isEmployee ? 'Personal document access' : `${role} document workspace`}</h2>
            <p className="mt-3 text-sm leading-6 text-slate-600">{roleDescription}</p>
            <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800">
              <strong>Backend connection needed.</strong> The UI does not claim that a file was uploaded, viewed, or downloaded until the corresponding authenticated API exists.
            </div>
            <dl className="mt-5 space-y-3 text-sm">
              <div className="flex items-center justify-between gap-4 border-b border-slate-100 pb-3"><dt className="text-slate-500">Current role</dt><dd className="font-semibold text-slate-900">{role}</dd></div>
              <div className="flex items-center justify-between gap-4"><dt className="text-slate-500">Document scope</dt><dd className="font-semibold text-slate-900">{isEmployee ? 'Own records' : 'Managed records'}</dd></div>
            </dl>
          </div>
        </section>
      </div>
    </main>
  )
}

export default DocumentsPage
