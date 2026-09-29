import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

const emptyEmployeeForm = {
  employee_number: '',
  first_name: '',
  middle_name: '',
  last_name: '',
  gender: 'Female',
  date_of_birth: '',
  national_id: '',
  email: '',
  phone: '',
  address: '',
  department_id: '',
  position_id: '',
  employment_type: 'Full-Time',
  hire_date: '',
}

function EmployeeDirectory() {
  const currentRole = JSON.parse(localStorage.getItem('hrms_user') || 'null')?.role_name
  const canManageEmployees = ['Admin', 'HR'].includes(currentRole)
  const [employees, setEmployees] = useState([])
  const [search, setSearch] = useState('')
  const [employmentStatus, setEmploymentStatus] = useState('')
  const [employmentType, setEmploymentType] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [refreshKey, setRefreshKey] = useState(0)
  const [configuration, setConfiguration] = useState({ departments: [], positions: [] })
  const [employeeForm, setEmployeeForm] = useState(emptyEmployeeForm)
  const [editingEmployeeId, setEditingEmployeeId] = useState(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    let isActive = true
    apiClient.get('/api/configuration/get.php')
      .then((response) => {
        if (isActive) setConfiguration(response.data.data || { departments: [], positions: [] })
      })
      .catch((requestError) => {
        if (isActive) setError(requestError.response?.data?.message || 'Departments and positions could not be loaded.')
      })
    return () => {
      isActive = false
    }
  }, [])

  useEffect(() => {
    let isActive = true

    const loadEmployees = async () => {
      setLoading(true)
      setError('')

      try {
        const response = await apiClient.get('/api/employees/get.php', {
          params: {
            search: search || undefined,
            status: employmentStatus || undefined,
            employment_type: employmentType || undefined,
            limit: 200,
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
  }, [employmentStatus, employmentType, refreshKey, search])

  const availablePositions = configuration.positions.filter(
    (position) => String(position.department_id) === String(employeeForm.department_id),
  )

  const handleEmployeeFormChange = (event) => {
    const { name, value } = event.target
    setEmployeeForm((previous) => ({
      ...previous,
      [name]: value,
      ...(name === 'department_id' ? { position_id: '' } : {}),
    }))
  }

  const beginEdit = (employee) => {
    setEditingEmployeeId(employee.employee_id)
    setEmployeeForm({
      employee_number: employee.employee_number || '',
      first_name: employee.first_name || '',
      middle_name: employee.middle_name || '',
      last_name: employee.last_name || '',
      gender: employee.gender || 'Female',
      date_of_birth: employee.date_of_birth || '',
      national_id: employee.national_id || '',
      email: employee.email || '',
      phone: employee.phone || '',
      address: employee.address || '',
      department_id: employee.department_id || '',
      position_id: employee.position_id || '',
      employment_type: employee.employment_type || 'Full-Time',
      hire_date: employee.hire_date || '',
    })
    setError('')
    setSuccess('')
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const resetEmployeeForm = () => {
    setEditingEmployeeId(null)
    setEmployeeForm(emptyEmployeeForm)
  }

  const saveEmployee = async (event) => {
    event.preventDefault()
    setSaving(true)
    setError('')
    setSuccess('')
    try {
      const endpoint = editingEmployeeId ? '/api/employees/update.php' : '/api/employees/create.php'
      await apiClient.post(endpoint, {
        ...employeeForm,
        date_of_birth: employeeForm.date_of_birth || null,
        national_id: employeeForm.national_id || null,
        department_id: Number(employeeForm.department_id),
        position_id: Number(employeeForm.position_id),
        ...(editingEmployeeId ? { employee_id: editingEmployeeId } : {}),
      })
      setSuccess(editingEmployeeId ? 'Employee information updated.' : 'Employee record created.')
      resetEmployeeForm()
      setRefreshKey((value) => value + 1)
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'The employee record could not be saved.')
    } finally {
      setSaving(false)
    }
  }

  const changeEmployeeStatus = async (employee, nextStatus) => {
    setSaving(true)
    setError('')
    setSuccess('')
    try {
      await apiClient.post('/api/employees/status.php', {
        employee_id: employee.employee_id,
        employment_status: nextStatus,
      })
      setSuccess(`${employee.first_name} ${employee.last_name} is now ${nextStatus.toLowerCase()}.`)
      setRefreshKey((value) => value + 1)
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Employment status could not be changed.')
    } finally {
      setSaving(false)
    }
  }

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

        {error && <div role="alert" className="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
        {success && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{success}</div>}

        {canManageEmployees && (
          <section className="panel-surface mb-6 p-5 sm:p-6">
            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <p className="section-label">HR administration</p>
                <h2 className="mt-2 text-lg font-bold text-slate-900">{editingEmployeeId ? 'Edit employee' : 'Add employee'}</h2>
                <p className="mt-1 text-sm text-slate-600">The database keeps a global primary key. Employee number is the readable business identifier and may include a department prefix.</p>
              </div>
              {editingEmployeeId && <button type="button" onClick={resetEmployeeForm} className="action-button-secondary">Cancel editing</button>}
            </div>

            <form onSubmit={saveEmployee} className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Employee number</span><input required name="employee_number" value={employeeForm.employee_number} onChange={handleEmployeeFormChange} placeholder="e.g. FIN-001" className="field-input" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">First name</span><input required name="first_name" value={employeeForm.first_name} onChange={handleEmployeeFormChange} className="field-input" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Middle name</span><input name="middle_name" value={employeeForm.middle_name} onChange={handleEmployeeFormChange} className="field-input" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Last name</span><input required name="last_name" value={employeeForm.last_name} onChange={handleEmployeeFormChange} className="field-input" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Gender</span><select required name="gender" value={employeeForm.gender} onChange={handleEmployeeFormChange} className="field-input"><option value="Female">Female</option><option value="Male">Male</option><option value="Other">Other</option></select></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Date of birth</span><input type="date" name="date_of_birth" value={employeeForm.date_of_birth} onChange={handleEmployeeFormChange} className="field-input" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">National ID</span><input name="national_id" value={employeeForm.national_id} onChange={handleEmployeeFormChange} className="field-input" autoComplete="off" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Email</span><input required type="email" name="email" value={employeeForm.email} onChange={handleEmployeeFormChange} className="field-input" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Phone</span><input name="phone" value={employeeForm.phone} onChange={handleEmployeeFormChange} className="field-input" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Hire date</span><input required type="date" name="hire_date" value={employeeForm.hire_date} onChange={handleEmployeeFormChange} className="field-input" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Department</span><select required name="department_id" value={employeeForm.department_id} onChange={handleEmployeeFormChange} className="field-input"><option value="">Select department</option>{configuration.departments.map((department) => <option key={department.department_id} value={department.department_id}>{department.department_code ? `${department.department_code} · ` : ''}{department.department_name}</option>)}</select></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Position</span><select required name="position_id" value={employeeForm.position_id} onChange={handleEmployeeFormChange} disabled={!employeeForm.department_id} className="field-input"><option value="">Select position</option>{availablePositions.map((position) => <option key={position.position_id} value={position.position_id}>{position.position_name}</option>)}</select></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Employment type</span><select required name="employment_type" value={employeeForm.employment_type} onChange={handleEmployeeFormChange} className="field-input"><option value="Full-Time">Full-Time</option><option value="Part-Time">Part-Time</option><option value="Contract">Contract</option><option value="Temporary">Temporary</option></select></label>
              <label className="md:col-span-2 xl:col-span-1"><span className="mb-2 block text-sm font-medium text-slate-700">Address</span><input name="address" value={employeeForm.address} onChange={handleEmployeeFormChange} className="field-input" /></label>
              <button type="submit" disabled={saving || configuration.departments.length === 0} className="action-button-primary md:col-span-2 xl:col-span-4">{saving ? 'Saving employee...' : editingEmployeeId ? 'Save employee changes' : 'Add employee'}</button>
            </form>
          </section>
        )}

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
                    <th className="px-4 py-3 font-semibold">Department</th>
                    <th className="px-4 py-3 font-semibold">Position</th>
                    <th className="px-4 py-3 font-semibold">Employment type</th>
                    <th className="px-4 py-3 font-semibold">Status</th>
                    {canManageEmployees && <th className="px-4 py-3 font-semibold">Actions</th>}
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {employees.map((employee) => (
                    <tr key={employee.employee_id} className="bg-white">
                      <td className="px-4 py-3 text-slate-700">{employee.employee_id}</td>
                      <td className="px-4 py-3 text-slate-700">{employee.employee_number}</td>
                      <td className="px-4 py-3 font-medium text-slate-900">{employee.first_name} {employee.last_name}</td>
                      <td className="px-4 py-3 text-slate-700">{employee.department_name || 'Unassigned'}</td>
                      <td className="px-4 py-3 text-slate-700">{employee.position_name || 'Unassigned'}</td>
                      <td className="px-4 py-3 text-slate-700">{employee.employment_type}</td>
                      <td className="px-4 py-3 text-slate-700">{employee.employment_status}</td>
                      {canManageEmployees && (
                        <td className="px-4 py-3">
                          <div className="flex flex-wrap gap-2">
                            <button type="button" disabled={saving} onClick={() => beginEdit(employee)} className="action-button-secondary">Edit</button>
                            <button type="button" disabled={saving} onClick={() => changeEmployeeStatus(employee, employee.employment_status === 'Active' ? 'Inactive' : 'Active')} className="action-button-secondary">{employee.employment_status === 'Active' ? 'Deactivate' : 'Reactivate'}</button>
                          </div>
                        </td>
                      )}
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
