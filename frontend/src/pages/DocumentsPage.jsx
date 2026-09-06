import { Link } from 'react-router-dom'

const plannedFields = [
  'Employee document records',
  'Document type or name',
  'Expiry date and status',
  'Visibility and version information',
]

function DocumentsPage() {
  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 p-4 sm:p-6">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">HRMS</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Documents</h1>
              <p className="mt-2 max-w-2xl text-sm text-slate-600">
                The document workspace is ready for the backend document service.
              </p>
            </div>

            <Link to="/dashboard" className="action-button-secondary self-start">
              Back to Dashboard
            </Link>
          </div>
        </header>

        <section className="mb-6 grid gap-4 md:grid-cols-3">
          <div className="panel-surface p-5">
            <p className="section-label">Data source</p>
            <p className="mt-2 text-lg font-bold text-slate-900">Not available</p>
          </div>
          <div className="panel-surface p-5">
            <p className="section-label">Current status</p>
            <p className="mt-2 text-lg font-bold text-amber-600">Backend dependency</p>
          </div>
          <div className="panel-surface p-5">
            <p className="section-label">Frontend status</p>
            <p className="mt-2 text-lg font-bold text-emerald-600">UI shell ready</p>
          </div>
        </section>

        <section className="panel-surface mb-6 p-5 sm:p-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <p className="section-label">Documents workspace</p>
              <h2 className="mt-2 text-lg font-bold text-slate-900">Document records are not exposed by the backend yet</h2>
              <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                No structured document API or document-related PHP endpoint is currently available in this project. This page does not load, invent, or scrape document records.
              </p>
            </div>
            <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
              Waiting on Kay&apos;s backend API
            </span>
          </div>
        </section>

        <section className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
          <div className="panel-surface p-5 sm:p-6">
            <div className="mb-5">
              <p className="section-label">Planned data</p>
              <h2 className="mt-2 text-lg font-bold text-slate-900">Fields this UI can display once available</h2>
            </div>
            <ul className="grid gap-3 sm:grid-cols-2" aria-label="Planned document data fields">
              {plannedFields.map((field) => (
                <li key={field} className="rounded-xl bg-slate-50 p-4 text-sm font-medium text-slate-700 ring-1 ring-slate-200">
                  {field}
                </li>
              ))}
            </ul>
          </div>

          <div className="panel-surface p-5 sm:p-6">
            <div className="mb-5">
              <p className="section-label">Unavailable actions</p>
              <h2 className="mt-2 text-lg font-bold text-slate-900">No document actions are active</h2>
            </div>
            <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm leading-6 text-slate-600">
              Upload, download, preview, expiry management, visibility controls, and version history will remain unavailable until the backend provides the corresponding document data and endpoints.
            </div>
          </div>
        </section>
      </div>
    </main>
  )
}

export default DocumentsPage
