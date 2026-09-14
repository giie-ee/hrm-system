import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

function EmployeeDirectory() {
  const [employees, setEmployees] = useState([])
  const [search, setSearch] = useState('')
  const [employmentStatus, setEmploymentStatus] = useState('')
  const [employmentType, setEmploymentType] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let isActive = true

    const loadEmployees = async () => {
      setLoading(true)
      setError('')

      try {
        const response = await apiClient.get('/api/employees/get.php', {
          params: {
            search: search || undefined,
            employment_status: employmentStatus || undefined,
            employment_type: employmentType || undefined,
          },
        })

        if (!isActive) return

        if (!response.data.success) {
          throw new Error(response.data.message || 'Unable to retrieve employees.')
        }

        setEmployees(response.data.data || [])
      } catch (requestError) {
        if (!isActive) return

        setEmployees([])
        setError(requestError.response?.data?.message || requestError.message || 'Unable to retrieve employees.')
      } finally {
        if (isActive) {
          setLoading(false)
        }
      }
    }

    loadEmployees()

    return () => {
      isActive = false
    }
  }, [employmentStatus, employmentType, search])

  const statusOptions = [...new Set(employees.map((employee) => employee.employment_status).filter(Boolean))]
  const typeOptions = [...new Set(employees.map((employee) => employee.employment_type).filter(Boolean))]

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 p-4 sm:p-6">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">HRMS</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Employee Directory</h1>
            </div>

            <Link to="/dashboard" className="action-button-secondary">
              Back to Dashboard
            </Link>
          </div>
        </header>

        <section className="mb-6 grid gap-4 lg:grid-cols-3">
          <div className="panel-surface p-5">
            <p className="section-label">Data source</p>
            <p className="mt-2 text-lg font-bold text-slate-900">Employee JSON API</p>
          </div>

          <div className="panel-surface p-5">
            <p className="section-label">Status</p>
            <p className={`mt-2 text-lg font-bold ${error ? 'text-red-600' : loading ? 'text-amber-600' : 'text-emerald-600'}`}>
              {error ? 'Unavailable' : loading ? 'Loading' : 'Connected'}
            </p>
          </div>

          <div className="panel-surface p-5">
            <p className="section-label">Frontend readiness</p>
            <p className="mt-2 text-lg font-bold text-slate-900">Live employee records</p>
          </div>
        </section>

        <section className="panel-surface mb-6 p-5 sm:p-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h2 className="text-lg font-bold text-slate-900">Employee search and filters</h2>
              <p className="mt-1 text-sm text-slate-600">
                Search by employee number or name and filter the live employee records.
              </p>
            </div>

            <fieldset className="flex flex-col gap-3 sm:flex-row">
              <legend className="sr-only">Employee search and filter controls</legend>
              <div>
                <label htmlFor="employee-search" className="sr-only">Search by employee ID, number, or name</label>
                <input
                  id="employee-search"
                  type="search"
                  placeholder="Search employees"
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  disabled={loading}
                  aria-describedby="employee-filter-status"
                  className="field-input w-full sm:w-64"
                />
              </div>
              <div>
                <label htmlFor="employment-status" className="sr-only">Filter by employment status</label>
                <select
                  id="employment-status"
                  value={employmentStatus}
                  onChange={(event) => setEmploymentStatus(event.target.value)}
                  disabled={loading}
                  aria-describedby="employee-filter-status"
                  className="field-input w-full sm:w-48"
                >
                  <option value="">All statuses</option>
                  {statusOptions.map((status) => <option key={status} value={status}>{status}</option>)}
                </select>
              </div>
              <div>
                <label htmlFor="employment-type" className="sr-only">Filter by employment type</label>
                <select
                  id="employment-type"
                  value={employmentType}
                  onChange={(event) => setEmploymentType(event.target.value)}
                  disabled={loading}
                  aria-describedby="employee-filter-status"
                  className="field-input w-full sm:w-48"
                >
                  <option value="">All types</option>
                  {typeOptions.map((type) => <option key={type} value={type}>{type}</option>)}
                </select>
              </div>
            </fieldset>
          </div>
          <p id="employee-filter-status" role="status" className="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
            Search uses the employee number, first name, and last name fields provided by the employee API.
          </p>
        </section>

        <section className="panel-surface p-5 sm:p-6">
          <div className="mb-4 flex items-center justify-between gap-3">
            <h2 className="text-lg font-bold text-slate-900">Employee roster</h2>
            <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
              {loading ? 'Loading records' : `${employees.length} records`}
            </span>
          </div>

          {loading && (
            <div className="rounded-xl border border-sky-200 bg-sky-50 p-6 text-center text-sm text-sky-700" role="status">
              Loading employee records...
            </div>
          )}

          {!loading && error && (
            <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-center" role="alert">
              <p className="text-base font-semibold text-red-800">Unable to load employee records.</p>
              <p className="mt-2 text-sm text-red-700">{error}</p>
            </div>
          )}

          {!loading && !error && employees.length === 0 && (
            <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
              <p className="text-base font-semibold text-slate-800">No employees match the current filters.</p>
              <p className="mt-2 text-sm text-slate-600">Try a different search or filter.</p>
            </div>
          )}

          {!loading && !error && employees.length > 0 && (
            <div className="overflow-x-auto rounded-xl border border-slate-200">
              <table className="min-w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                  <tr>
                    <th className="px-4 py-3 font-semibold">ID</th>
                    <th className="px-4 py-3 font-semibold">Employee number</th>
                    <th className="px-4 py-3 font-semibold">Name</th>
                    <th className="px-4 py-3 font-semibold">Employment type</th>
                    <th className="px-4 py-3 font-semibold">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {employees.map((employee) => (
                    <tr key={employee.employee_id} className="bg-white">
                      <td className="px-4 py-3 text-slate-700">{employee.employee_id}</td>
                      <td className="px-4 py-3 text-slate-700">{employee.employee_number}</td>
                      <td className="px-4 py-3 font-medium text-slate-900">{employee.first_name} {employee.last_name}</td>
                      <td className="px-4 py-3 text-slate-700">{employee.employment_type}</td>
                      <td className="px-4 py-3 text-slate-700">{employee.employment_status}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </div>
    </main>
  )
}

export default EmployeeDirectory
