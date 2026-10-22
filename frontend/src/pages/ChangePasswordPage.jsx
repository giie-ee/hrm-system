import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import apiClient from '../api/client'
import BrandMark from '../components/BrandMark'
import { readStoredUser, storeUser } from '../api/session'

function passwordProblem(password) {
  if (password.length < 10) return 'Use at least 10 characters.'
  if (!/[A-Z]/.test(password) || !/[a-z]/.test(password)) return 'Include both uppercase and lowercase letters.'
  if (!/[0-9]/.test(password)) return 'Include at least one number.'
  if (!/[^a-zA-Z0-9]/.test(password)) return 'Include at least one symbol.'
  return ''
}

function ChangePasswordPage() {
  const navigate = useNavigate()
  const user = readStoredUser()
  const [form, setForm] = useState({ current_password: '', new_password: '', confirm_password: '' })
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  const forced = Boolean(user?.must_change_password)

  const handleChange = (event) => {
    const { name, value } = event.target
    setForm((current) => ({ ...current, [name]: value }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')

    const problem = passwordProblem(form.new_password)
    if (problem) { setError(problem); return }
    if (form.new_password !== form.confirm_password) { setError('The new passwords do not match.'); return }
    if (form.new_password === form.current_password) { setError('Choose a password different from the current one.'); return }

    setSaving(true)
    try {
      await apiClient.post('/api/auth/change-password.php', {
        current_password: form.current_password,
        new_password: form.new_password,
      })
      if (user) storeUser({ ...user, must_change_password: false })
      navigate(user?.onboarding_required ? '/onboarding' : '/dashboard', { replace: true })
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'The password could not be changed.')
    } finally {
      setSaving(false)
    }
  }

  const handleSignOut = async () => {
    try {
      await apiClient.post('/api/auth/logout.php')
    } catch {
      // Local credentials are cleared even when the backend is unreachable.
    } finally {
      localStorage.removeItem('hrms_user')
      sessionStorage.removeItem('hrms_csrf_token')
      navigate('/login', { replace: true })
    }
  }

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-md">
        <div className="mb-6"><BrandMark /></div>
        <section className="panel-surface p-5 sm:p-6">
          <p className="section-label">Account security</p>
          <h1 className="mt-2 text-2xl font-bold text-slate-900">{forced ? 'Set your own password' : 'Change password'}</h1>
          <p className="mt-2 text-sm text-slate-600">
            {forced
              ? 'You are signed in with a temporary password. Choose a new one to continue.'
              : 'Changing your password signs you out of your other sessions.'}
          </p>
          {error && <div className="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">{error}</div>}
          <form onSubmit={handleSubmit} className="mt-5 grid gap-4">
            <label><span className="mb-2 block text-sm font-medium text-slate-700">{forced ? 'Temporary password' : 'Current password'}</span>
              <input required type="password" name="current_password" value={form.current_password} onChange={handleChange} autoComplete="current-password" className="field-input" /></label>
            <label><span className="mb-2 block text-sm font-medium text-slate-700">New password</span>
              <input required type="password" name="new_password" value={form.new_password} onChange={handleChange} autoComplete="new-password" className="field-input" /></label>
            <label><span className="mb-2 block text-sm font-medium text-slate-700">Confirm new password</span>
              <input required type="password" name="confirm_password" value={form.confirm_password} onChange={handleChange} autoComplete="new-password" className="field-input" /></label>
            <p className="text-xs text-slate-500">At least 10 characters with uppercase, lowercase, a number and a symbol.</p>
            <button type="submit" disabled={saving} className="action-button-primary">{saving ? 'Saving...' : 'Change password'}</button>
          </form>
          <div className="mt-4 flex justify-between text-sm">
            {!forced && <button type="button" onClick={() => navigate(-1)} className="text-sky-700 hover:underline">Cancel</button>}
            <button type="button" onClick={handleSignOut} className="ml-auto text-slate-600 hover:underline">Sign out</button>
          </div>
        </section>
      </div>
    </main>
  )
}

export default ChangePasswordPage
