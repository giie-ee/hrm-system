import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

const initialPayrollForm = {
  employee_id: '',
  pay_period_start: '',
  pay_period_end: '',
}

const initialItemForm = {
  item_type: 'Allowance',
  item_name: '',
  amount: '',
  description: '',
}

function getErrorMessage(error, fallback) {
  return error.response?.data?.message || fallback
}

function formatAmount(amount) {
  const numericAmount = Number(amount)
  return Number.isFinite(numericAmount) ? numericAmount.toFixed(2) : 'Unavailable'
}

function PayrollPage() {
  const [payrollForm, setPayrollForm] = useState(initialPayrollForm)
  const [itemForm, setItemForm] = useState(initialItemForm)
  const [payroll, setPayroll] = useState(null)
  const [payrollRecords, setPayrollRecords] = useState([])
  const [payrollRecordsLoading, setPayrollRecordsLoading] = useState(true)
  const [payrollRecordsError, setPayrollRecordsError] = useState('')
  const [items, setItems] = useState([])
  const [loadingAction, setLoadingAction] = useState('')
  const [error, setError] = useState('')
  const [successMessage, setSuccessMessage] = useState('')

  useEffect(() => {
    let isActive = true

    const loadPayrollRecords = async () => {
      setPayrollRecordsLoading(true)
      setPayrollRecordsError('')

      try {
        const response = await apiClient.get('/api/payroll/get.php')
        const records = response.data.data || []

        if (!isActive) return

        setPayrollRecords(records)
        setPayroll((currentPayroll) => currentPayroll || records[0] || null)
      } catch (requestError) {
        if (!isActive) return

        setPayrollRecordsError(getErrorMessage(requestError, 'Existing payroll records could not be loaded.'))
      } finally {
        if (isActive) {
          setPayrollRecordsLoading(false)
        }
      }
    }

    loadPayrollRecords()

    return () => {
      isActive = false
    }
  }, [])

  const handlePayrollChange = (event) => {
    const { name, value } = event.target
    setPayrollForm((previous) => ({ ...previous, [name]: value }))
  }

  const handleItemChange = (event) => {
    const { name, value } = event.target
    setItemForm((previous) => ({ ...previous, [name]: value }))
  }

  const runAction = async (action, request, successMessage) => {
    setLoadingAction(action)
    setError('')
    setSuccessMessage('')

    try {
      await request()
      setSuccessMessage(successMessage)
    } catch (requestError) {
      setError(getErrorMessage(requestError, 'The payroll request could not be completed.'))
    } finally {
      setLoadingAction('')
    }
  }

  const createPayroll = async (event) => {
    event.preventDefault()

    await runAction(
      'create',
      async () => {
        const response = await apiClient.post('/api/payroll/create.php', payrollForm)
        setPayroll(response.data)
        setPayrollRecords((records) => [response.data, ...records])
        setItems([])
        setItemForm(initialItemForm)
      },
      'Draft payroll created successfully.',
    )
  }

  const addItem = async (event) => {
    event.preventDefault()
    if (!payroll?.payroll_id) return

    await runAction(
      'add-item',
      async () => {
        await apiClient.post('/api/payroll/add-item.php', {
          payroll_id: payroll.payroll_id,
          ...itemForm,
          amount: Number(itemForm.amount),
        })
        setItemForm(initialItemForm)
        await loadItems(payroll.payroll_id)
      },
      'Payroll item added successfully.',
    )
  }

  const loadItems = async (payrollId = payroll?.payroll_id) => {
    if (!payrollId) return

    await runAction(
      'items',
      async () => {
        const response = await apiClient.get('/api/payroll/get-items.php', {
          params: { payroll_id: payrollId },
        })
        setItems(response.data.items || [])
      },
      'Payroll items loaded successfully.',
    )
  }

  const calculatePayroll = async () => {
    if (!payroll?.payroll_id) return

    await runAction(
      'calculate',
      async () => {
        const response = await apiClient.post('/api/payroll/calculate.php', {
          payroll_id: payroll.payroll_id,
        })
        setPayroll(response.data)
        setItems(response.data.payroll_items || [])
      },
      'Payroll calculated successfully.',
    )
  }

  const processPayroll = async () => {
    if (!payroll?.payroll_id) return

    await runAction(
      'process',
      async () => {
        const response = await apiClient.post('/api/payroll/process.php', {
          payroll_id: payroll.payroll_id,
        })
        setPayroll((previous) => ({ ...previous, ...response.data }))
      },
      'Payroll processed successfully.',
    )
  }

  const isBusy = Boolean(loadingAction)
  const isProcessed = payroll?.payroll_status === 'Processed'
  const currentRole = JSON.parse(localStorage.getItem('hrms_user') || 'null')?.role_name
  const canManagePayroll = ['Admin', 'HR'].includes(currentRole)
  const processedPayrolls = payrollRecords.filter((record) => record.payroll_status === 'Processed').length
  const draftPayrolls = payrollRecords.filter((record) => record.payroll_status === 'Draft').length
  const latestNetSalary = payrollRecords[0]?.net_salary

  const handlePayrollSelect = (event) => {
    const selectedPayroll = payrollRecords.find(
      (record) => String(record.payroll_id) === event.target.value,
    )

    setPayroll(selectedPayroll || null)
    setItems([])
    setError('')
    setSuccessMessage('')
  }

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 p-4 sm:p-6">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">HRMS</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Payroll</h1>
              <p className="mt-2 max-w-2xl text-sm text-slate-600">
                Create and complete a payroll record using the existing PHP payroll workflow.
              </p>
            </div>
            <div className="flex flex-wrap gap-3">
              <Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link>
            </div>
          </div>
        </header>

        {error && (
          <div aria-live="polite" className="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        )}

        {successMessage && (
          <div aria-live="polite" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {successMessage}
          </div>
        )}

        <section className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <SummaryCard label="Total payroll records" value={payrollRecordsLoading ? 'Loading...' : payrollRecordsError ? 'Unavailable' : payrollRecords.length} />
          <SummaryCard label="Processed payrolls" value={payrollRecordsLoading ? 'Loading...' : payrollRecordsError ? 'Unavailable' : processedPayrolls} />
          <SummaryCard label="Draft payrolls" value={payrollRecordsLoading ? 'Loading...' : payrollRecordsError ? 'Unavailable' : draftPayrolls} />
          <SummaryCard label="Latest net salary" value={payrollRecordsLoading ? 'Loading...' : payrollRecordsError ? 'Unavailable' : formatAmount(latestNetSalary)} />
        </section>

        <section className="panel-surface mb-6 p-5 sm:p-6">
          <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p className="section-label">Existing records</p>
              <h2 className="mt-2 text-lg font-bold text-slate-900">Payroll history</h2>
              <p className="mt-1 text-sm text-slate-600">Select a payroll record to view its summary and work with its items.</p>
            </div>
            <label className="w-full sm:max-w-sm">
              <span className="mb-2 block text-sm font-medium text-slate-700">Payroll record</span>
              <select
                value={payroll?.payroll_id || ''}
                onChange={handlePayrollSelect}
                disabled={payrollRecordsLoading || payrollRecords.length === 0 || isBusy}
                className="field-input"
              >
                <option value="">
                  {payrollRecordsLoading ? 'Loading payroll records...' : payrollRecords.length === 0 ? 'No payroll records found' : 'Select a payroll record'}
                </option>
                {payrollRecords.map((record) => (
                  <option key={record.payroll_id} value={record.payroll_id}>
                    #{record.payroll_id} · {record.employee_name || `Employee ${record.employee_id}`} · {record.payroll_status}
                  </option>
                ))}
              </select>
            </label>
          </div>

          {payrollRecordsLoading && (
            <p className="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-700">Loading existing payroll records...</p>
          )}

          {!payrollRecordsLoading && payrollRecordsError && (
            <p role="alert" className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{payrollRecordsError}</p>
          )}

          {!payrollRecordsLoading && !payrollRecordsError && payrollRecords.length === 0 && (
            <p className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-600">No payroll records are available.</p>
          )}

          {!payrollRecordsLoading && !payrollRecordsError && payrollRecords.length > 0 && (
            <div className="overflow-x-auto rounded-xl border border-slate-200">
              <table className="min-w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                  <tr>
                    <th className="px-4 py-3 font-semibold">Payroll</th>
                    <th className="px-4 py-3 font-semibold">Employee</th>
                    <th className="px-4 py-3 font-semibold">Period</th>
                    <th className="px-4 py-3 font-semibold">Status</th>
                    <th className="px-4 py-3 font-semibold">Net salary</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {payrollRecords.map((record) => (
                    <tr
                      key={record.payroll_id}
                      className={`cursor-pointer ${record.payroll_id === payroll?.payroll_id ? 'bg-sky-50' : 'bg-white hover:bg-slate-50'}`}
                      onClick={() => handlePayrollSelect({ target: { value: String(record.payroll_id) } })}
                    >
                      <td className="px-4 py-3 font-medium text-slate-900">#{record.payroll_id}</td>
                      <td className="px-4 py-3 text-slate-700">{record.employee_name || `Employee ${record.employee_id}`}</td>
                      <td className="px-4 py-3 text-slate-700">{record.pay_period_start} to {record.pay_period_end}</td>
                      <td className="px-4 py-3 text-slate-700">{record.payroll_status}</td>
                      <td className="px-4 py-3 text-slate-700">{formatAmount(record.net_salary)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        <section className="mb-6 grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
          <div className="panel-surface p-5 sm:p-6">
            <div className="mb-5">
              <p className="section-label">Step 1</p>
              <h2 className="mt-2 text-lg font-bold text-slate-900">Create Draft Payroll</h2>
              <p className="mt-1 text-sm text-slate-600">The backend looks up the employee&apos;s active salary.</p>
            </div>

            {!canManagePayroll ? (
              <p className="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-700">Your payroll records are view-only.</p>
            ) : (
            <form onSubmit={createPayroll} className="grid gap-4 sm:grid-cols-2">
              <label className="sm:col-span-2">
                <span className="mb-2 block text-sm font-medium text-slate-700">Employee ID</span>
                <input
                  required
                  min="1"
                  name="employee_id"
                  type="number"
                  value={payrollForm.employee_id}
                  onChange={handlePayrollChange}
                  placeholder="Enter an existing employee ID"
                  className="field-input"
                />
              </label>
              <label>
                <span className="mb-2 block text-sm font-medium text-slate-700">Period start</span>
                <input required name="pay_period_start" type="date" value={payrollForm.pay_period_start} onChange={handlePayrollChange} className="field-input" />
              </label>
              <label>
                <span className="mb-2 block text-sm font-medium text-slate-700">Period end</span>
                <input required name="pay_period_end" type="date" value={payrollForm.pay_period_end} onChange={handlePayrollChange} className="field-input" />
              </label>
              <button type="submit" disabled={isBusy} className="action-button-primary sm:col-span-2">
                {loadingAction === 'create' ? 'Creating draft...' : 'Create Draft Payroll'}
              </button>
            </form>
            )}
          </div>

          <div className="panel-surface p-5 sm:p-6">
            <div className="mb-5 flex items-center justify-between gap-3">
              <div>
                <p className="section-label">Current record</p>
                <h2 className="mt-2 text-lg font-bold text-slate-900">Payroll Summary</h2>
              </div>
              <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${payroll ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600'}`}>
                {payroll?.payroll_status || 'No payroll selected'}
              </span>
            </div>

            {payroll ? (
              <dl className="grid gap-4 sm:grid-cols-2">
                <SummaryValue label="Payroll ID" value={payroll.payroll_id} />
                <SummaryValue label="Employee ID" value={payroll.employee_id} />
                <SummaryValue label="Basic salary" value={formatAmount(payroll.basic_salary)} />
                <SummaryValue label="Allowances" value={formatAmount(payroll.total_allowances)} />
                <SummaryValue label="Deductions" value={formatAmount(payroll.total_deductions)} />
                <SummaryValue label="Gross salary" value={formatAmount(payroll.gross_salary)} />
                <SummaryValue label="Net salary" value={formatAmount(payroll.net_salary)} />
                <SummaryValue label="Payment date" value={payroll.payment_date || 'Not processed'} />
              </dl>
            ) : (
              <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-600">
                Create a Draft payroll to view the backend calculation summary.
              </div>
            )}
          </div>
        </section>

        <section className="panel-surface mb-6 p-5 sm:p-6">
          <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p className="section-label">Step 2</p>
              <h2 className="mt-2 text-lg font-bold text-slate-900">Payroll Items</h2>
              <p className="mt-1 text-sm text-slate-600">Add real allowances or deductions to the selected Draft payroll.</p>
            </div>
            <button type="button" disabled={!payroll || isBusy} onClick={() => loadItems()} className="action-button-secondary">
              {loadingAction === 'items' ? 'Loading items...' : 'Load Items'}
            </button>
          </div>

          {canManagePayroll && <form onSubmit={addItem} className="grid gap-4 lg:grid-cols-[0.8fr_1.2fr_0.8fr_1.5fr_auto] lg:items-end">
            <label>
              <span className="mb-2 block text-sm font-medium text-slate-700">Type</span>
              <select name="item_type" value={itemForm.item_type} onChange={handleItemChange} disabled={!payroll || isProcessed || isBusy} className="field-input">
                <option value="Allowance">Allowance</option>
                <option value="Deduction">Deduction</option>
              </select>
            </label>
            <label>
              <span className="mb-2 block text-sm font-medium text-slate-700">Item name</span>
              <input required name="item_name" value={itemForm.item_name} onChange={handleItemChange} disabled={!payroll || isProcessed || isBusy} placeholder="Actual item name" className="field-input" />
            </label>
            <label>
              <span className="mb-2 block text-sm font-medium text-slate-700">Amount</span>
              <input required min="0" step="0.01" name="amount" type="number" value={itemForm.amount} onChange={handleItemChange} disabled={!payroll || isProcessed || isBusy} placeholder="0.00" className="field-input" />
            </label>
            <label>
              <span className="mb-2 block text-sm font-medium text-slate-700">Description</span>
              <input name="description" value={itemForm.description} onChange={handleItemChange} disabled={!payroll || isProcessed || isBusy} placeholder="Optional" className="field-input" />
            </label>
            <button type="submit" disabled={!payroll || isProcessed || isBusy} className="action-button-primary">
              {loadingAction === 'add-item' ? 'Adding...' : 'Add Item'}
            </button>
          </form>}

          <div className="mt-6 overflow-x-auto rounded-xl border border-slate-200">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                <tr>
                  <th className="px-4 py-3 font-semibold">Type</th>
                  <th className="px-4 py-3 font-semibold">Name</th>
                  <th className="px-4 py-3 font-semibold">Amount</th>
                  <th className="px-4 py-3 font-semibold">Description</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {items.length > 0 ? items.map((item, index) => (
                  <tr key={item.payroll_item_id || `${item.item_name}-${index}`} className="bg-white">
                    <td className="px-4 py-3 text-slate-700">{item.item_type}</td>
                    <td className="px-4 py-3 font-medium text-slate-900">{item.item_name}</td>
                    <td className="px-4 py-3 text-slate-700">{formatAmount(item.amount)}</td>
                    <td className="px-4 py-3 text-slate-600">{item.description || 'None'}</td>
                  </tr>
                )) : (
                  <tr>
                    <td colSpan="4" className="px-4 py-6 text-center text-slate-500">No payroll items loaded.</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </section>

        <section className="panel-surface p-5 sm:p-6">
          <div className="mb-5">
            <p className="section-label">Steps 3 and 4</p>
            <h2 className="mt-2 text-lg font-bold text-slate-900">Calculate and Process</h2>
            <p className="mt-1 text-sm text-slate-600">Calculation updates the totals. Processing changes a Draft to Processed.</p>
          </div>
          {canManagePayroll && <div className="flex flex-col gap-3 sm:flex-row">
            <button type="button" disabled={!payroll || isProcessed || isBusy} onClick={calculatePayroll} className="action-button-primary">
              {loadingAction === 'calculate' ? 'Calculating...' : 'Calculate Payroll'}
            </button>
            <button type="button" disabled={!payroll || isProcessed || isBusy} onClick={processPayroll} className="action-button-secondary">
              {loadingAction === 'process' ? 'Processing...' : 'Process Payroll'}
            </button>
          </div>}
        </section>
      </div>
    </main>
  )
}

function SummaryValue({ label, value }) {
  return (
    <div className="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
      <dt className="section-label">{label}</dt>
      <dd className="mt-2 text-base font-semibold text-slate-900">{value}</dd>
    </div>
  )
}

function SummaryCard({ label, value }) {
  return (
    <div className="panel-surface p-5">
      <p className="section-label">{label}</p>
      <p className="mt-2 text-xl font-bold tracking-tight text-slate-900">{value}</p>
    </div>
  )
}

export default PayrollPage
