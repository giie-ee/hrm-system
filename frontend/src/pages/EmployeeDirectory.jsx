import { Link } from 'react-router-dom'

function EmployeeDirectory() {
  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 p-4 sm:p-6">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">HRMS</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Employee Directory</h1>
            </div>

            <div className="flex flex-wrap items-center gap-3">
              <Link to="/dashboard" className="action-button-secondary">
                Back to Dashboard
              </Link>
              <a
                href="http://localhost:8000/employees/list.php"
                target="_blank"
                rel="noreferrer"
                className="action-button-primary"
              >
                Open PHP Employee List
              </a>
            </div>
          </div>
        </header>

        <section className="mb-6 grid gap-4 lg:grid-cols-3">
          <div className="panel-surface p-5">
            <p className="section-label">Data source</p>
            <p className="mt-2 text-lg font-bold text-slate-900">PHP employee page</p>
          </div>

          <div className="panel-surface p-5">
            <p className="section-label">Status</p>
            <p className="mt-2 text-lg font-bold text-amber-600">JSON data unavailable</p>
          </div>

          <div className="panel-surface p-5">
            <p className="section-label">Frontend readiness</p>
            <p className="mt-2 text-lg font-bold text-slate-900">JSON employee API pending</p>
          </div>
        </section>

        <section className="panel-surface mb-6 p-5 sm:p-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h2 className="text-lg font-bold text-slate-900">Employee search and filters</h2>
              <p className="mt-1 text-sm text-slate-600">
                Search and filtering will become available when the backend exposes the employee records as JSON.
              </p>
            </div>

            <fieldset disabled className="flex flex-col gap-3 sm:flex-row">
              <legend className="sr-only">Employee search and filter controls</legend>
              <div>
                <label htmlFor="employee-search" className="sr-only">Search by employee ID, number, or name</label>
                <input
                  id="employee-search"
                  type="search"
                  placeholder="Search employees"
                  aria-describedby="employee-filter-status"
                  className="field-input w-full cursor-not-allowed border-slate-200 bg-slate-100 text-slate-500 placeholder:text-slate-400 sm:w-64"
                />
              </div>
              <div>
                <label htmlFor="employment-status" className="sr-only">Filter by employment status</label>
                <select
                  id="employment-status"
                  aria-describedby="employee-filter-status"
                  className="field-input w-full cursor-not-allowed border-slate-200 bg-slate-100 text-slate-500 sm:w-48"
                >
                  <option>All statuses</option>
                </select>
              </div>
            </fieldset>
          </div>
          <p id="employee-filter-status" role="status" className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Filters are unavailable until Kay&apos;s JSON employee endpoint is added. The current PHP source returns an HTML page only.
          </p>
        </section>

        <section className="panel-surface p-5 sm:p-6">
          <div className="mb-4 flex items-center justify-between gap-3">
            <h2 className="text-lg font-bold text-slate-900">Employee roster</h2>
            <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
              Waiting on Kay&apos;s employee API
            </span>
          </div>

          <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
            <p className="text-base font-semibold text-slate-800">Real employee records are not exposed as JSON yet.</p>
            <p className="mt-2 text-sm text-slate-600">
              The current backend data source is the HTML employee listing page at{' '}
              <a href="http://localhost:8000/employees/list.php" target="_blank" rel="noreferrer" className="font-medium text-sky-600 underline underline-offset-2">
                http://localhost:8000/employees/list.php
              </a>
              .
            </p>
            <p className="mt-3 text-sm text-slate-600">
              Once Kay&apos;s employee API is available, this screen can be connected to live employee records and full filtering/searching.
            </p>
          </div>
        </section>
      </div>
    </main>
  )
}

export default EmployeeDirectory
