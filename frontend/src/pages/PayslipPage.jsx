import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

function getErrorMessage(error, fallback) {
  return error.response?.data?.message || fallback
}

function formatAmount(amount) {
  const numericAmount = Number(amount)
  return Number.isFinite(numericAmount) ? numericAmount.toFixed(2) : 'Unavailable'
}

function PayslipPage() {
  const [payrollRecords, setPayrollRecords] = useState([])
  const [selectedPayroll, setSelectedPayroll] = useState(null)
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [itemsLoading, setItemsLoading] = useState(false)
  const [error, setError] = useState('')
  const [itemsError, setItemsError] = useState('')

  useEffect(() => {
    let isActive = true

    const loadPayrollRecords = async () => {
      setLoading(true)
      setError('')

      try {
        const response = await apiClient.get('/api/payroll/get.php')
        const records = response.data.data || []

        if (!isActive) return

        setPayrollRecords(records)
        setSelectedPayroll(records[0] || null)
      } catch (requestError) {
        if (isActive) setError(getErrorMessage(requestError, 'Payroll records could not be loaded.'))
      } finally {
        if (isActive) setLoading(false)
      }
    }

    loadPayrollRecords()

    return () => {
      isActive = false
    }
  }, [])

  useEffect(() => {
    let isActive = true

    const loadItems = async () => {
      if (!selectedPayroll?.payroll_id) {
        setItems([])
        return
      }

      setItemsLoading(true)
      setItemsError('')

      try {
        const response = await apiClient.get('/api/payroll/get-items.php', {
          params: { payroll_id: selectedPayroll.payroll_id },
        })

        if (isActive) setItems(response.data.items || [])
      } catch (requestError) {
        if (isActive) {
          setItems([])
          setItemsError(getErrorMessage(requestError, 'Payroll items could not be loaded.'))
        }
      } finally {
        if (isActive) setItemsLoading(false)
      }
    }

    loadItems()

    return () => {
      isActive = false
    }
  }, [selectedPayroll])

  const allowances = items.filter((item) => item.item_type === 'Allowance')
  const deductions = items.filter((item) => item.item_type === 'Deduction')

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-5xl">
        <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">HRMS</p>
            <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Payslip</h1>
            <p className="mt-2 text-sm text-slate-600">View a payslip from an existing payroll record.</p>
          </div>
          <div className="flex flex-wrap gap-3 print:hidden">
            <Link to="/payroll" className="action-button-secondary">Back to Payroll</Link>
            <Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link>
          </div>
        </header>

        {loading && <div className="panel-surface p-6 text-sm text-sky-700" role="status">Loading payroll records...</div>}
        {!loading && error && <div className="panel-surface border-red-200 bg-red-50 p-6 text-sm text-red-700" role="alert">{error}</div>}
        {!loading && !error && payrollRecords.length === 0 && <div className="panel-surface p-6 text-center text-sm text-slate-600" role="status">No payroll records are available for a payslip.</div>}

        {!loading && !error && payrollRecords.length > 0 && (
          <>
            <section className="panel-surface mb-6 p-5 print:hidden sm:p-6">
              <label className="block max-w-md">
                <span className="mb-2 block text-sm font-medium text-slate-700">Payroll record</span>
                <select value={selectedPayroll?.payroll_id || ''} onChange={(event) => setSelectedPayroll(payrollRecords.find((record) => String(record.payroll_id) === event.target.value) || null)} className="field-input">
                  {payrollRecords.map((record) => <option key={record.payroll_id} value={record.payroll_id}>#{record.payroll_id} · {record.employee_name || `Employee ${record.employee_id}`} · {record.payroll_status}</option>)}
                </select>
              </label>
            </section>

            {!selectedPayroll && <div className="panel-surface p-6 text-sm text-slate-600" role="status">No payroll record is selected.</div>}

            {selectedPayroll && (
              <article className="panel-surface p-5 sm:p-8">
                <div className="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-start sm:justify-between">
                  <div>
                    <p className="text-sm font-semibold uppercase tracking-[0.18em] text-sky-600">HRMS</p>
                    <h2 className="mt-2 text-2xl font-bold text-slate-900">Payslip</h2>
                    <p className="mt-2 text-sm text-slate-600">Pay period: {selectedPayroll.pay_period_start} to {selectedPayroll.pay_period_end}</p>
                  </div>
                  <div className="flex items-start gap-3">
                    <div className="text-left text-sm sm:text-right"><p className="text-slate-500">Payroll status</p><p className="mt-1 font-semibold text-slate-900">{selectedPayroll.payroll_status}</p><p className="mt-3 text-slate-500">Payment date</p><p className="mt-1 font-semibold text-slate-900">{selectedPayroll.payment_date || 'Not processed'}</p></div>
                    <button type="button" onClick={() => window.print()} className="action-button-secondary print:hidden">Print Payslip</button>
                  </div>
                </div>

                <section className="border-b border-slate-200 py-6">
                  <p className="section-label">Employee</p>
                  <h3 className="mt-2 text-lg font-bold text-slate-900">{selectedPayroll.employee_name || `Employee ${selectedPayroll.employee_id}`}</h3>
                  <p className="mt-1 text-sm text-slate-600">Employee ID: {selectedPayroll.employee_id}</p>
                </section>

                <section className="grid gap-6 border-b border-slate-200 py-6 md:grid-cols-2">
                  <div>
                    <p className="section-label">Earnings</p>
                    <div className="mt-4 space-y-3"><LineItem label="Basic salary" amount={selectedPayroll.basic_salary} />{allowances.map((item) => <LineItem key={item.payroll_item_id || `${item.item_name}-${item.amount}`} label={item.item_name} amount={item.amount} />)}{!itemsLoading && allowances.length === 0 && <p className="text-sm text-slate-500">No allowance items loaded.</p>}</div>
                  </div>
                  <div>
                    <p className="section-label">Deductions</p>
                    <div className="mt-4 space-y-3">{deductions.map((item) => <LineItem key={item.payroll_item_id || `${item.item_name}-${item.amount}`} label={item.item_name} amount={item.amount} />)}{!itemsLoading && deductions.length === 0 && <p className="text-sm text-slate-500">No deduction items loaded.</p>}</div>
                  </div>
                </section>

                {itemsLoading && <p className="border-b border-slate-200 py-4 text-sm text-slate-600" role="status">Loading payroll items...</p>}
                {itemsError && <p className="border-b border-red-200 bg-red-50 py-4 text-sm text-red-700" role="alert">{itemsError}</p>}

                <section className="grid gap-3 pt-6 sm:grid-cols-4">
                  <SummaryValue label="Gross salary" value={formatAmount(selectedPayroll.gross_salary)} />
                  <SummaryValue label="Total allowances" value={formatAmount(selectedPayroll.total_allowances)} />
                  <SummaryValue label="Total deductions" value={formatAmount(selectedPayroll.total_deductions)} />
                  <SummaryValue label="Net salary" value={formatAmount(selectedPayroll.net_salary)} strong />
                </section>
              </article>
            )}
          </>
        )}
      </div>
    </main>
  )
}

function LineItem({ label, amount }) {
  return <div className="flex items-center justify-between gap-4 text-sm"><span className="text-slate-700">{label}</span><span className="font-semibold text-slate-900">{formatAmount(amount)}</span></div>
}

function SummaryValue({ label, value, strong = false }) {
  return <div className={`rounded-xl p-4 ${strong ? 'bg-sky-50 ring-1 ring-sky-200' : 'bg-slate-50 ring-1 ring-slate-200'}`}><p className="section-label">{label}</p><p className="mt-2 text-base font-bold text-slate-900">{value}</p></div>
}

export default PayslipPage
