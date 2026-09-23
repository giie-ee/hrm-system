import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import apiClient from '../api/client'
import BrandMark from '../components/BrandMark'

function EyeIcon({ hidden }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {hidden ? (
        <>
          <path d="M3 3l18 18" />
          <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
          <path d="M9.9 5.1A11 11 0 0 1 12 5c4.4 0 8.2 2.4 10 7a11.8 11.8 0 0 1-4.7 5.4M6.6 6.6A11.6 11.6 0 0 0 2 12c1.8 4.6 5.6 7 10 7 1.4 0 2.8-.2 4-.6" />
        </>
      ) : (
        <>
          <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
          <circle cx="12" cy="12" r="3" />
        </>
      )}
    </svg>
  )
}

function LoginPage() {
  const [formData, setFormData] = useState({ username: '', password: '' })
  const [showPassword, setShowPassword] = useState(false)
  const navigate = useNavigate()
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [successMessage, setSuccessMessage] = useState('')

  const handleChange = (event) => {
    const { name, value } = event.target
    setFormData((previous) => ({ ...previous, [name]: value }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setLoading(true)
    setError('')
    setSuccessMessage('')

    try {
      const response = await apiClient.post('/api/auth/login.php', formData)

      if (response.data.success) {
        setSuccessMessage(response.data.message || 'Login successful.')
        localStorage.setItem('hrms_user', JSON.stringify(response.data.user))
        sessionStorage.setItem('hrms_csrf_token', response.data.csrf_token || '')
        setTimeout(() => navigate('/dashboard', { replace: true }), 400)
      } else {
        setError(response.data.message || 'Login failed.')
      }
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Unable to connect to the server.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <main className="login-page">
      <div className="login-shell">
        <header className="login-shell__header">
          <BrandMark />
          <span className="login-security-note">
            <span className="login-security-note__dot" />
            Secure team access
          </span>
        </header>

        <div className="login-frame">
          <section className="login-story" aria-label="Nexa People overview">
            <div className="login-story__copy">
              <span className="login-story__eyebrow">A clearer workday starts here</span>
              <h1>People operations that feel simple.</h1>
              <p>One calm workspace for employee records, leave, attendance, payroll, and progress.</p>
            </div>

            <div className="people-visual" aria-hidden="true">
              <span className="people-visual__orb people-visual__orb--pink" />
              <span className="people-visual__orb people-visual__orb--green" />
              <span className="people-visual__orb people-visual__orb--blue" />
              <span className="people-visual__orbit people-visual__orbit--large" />
              <span className="people-visual__orbit people-visual__orbit--small" />
              <span className="people-visual__person people-visual__person--one" />
              <span className="people-visual__person people-visual__person--two" />
              <span className="people-visual__person people-visual__person--three" />
              <span className="people-visual__card people-visual__card--one"><i />Team ready</span>
              <span className="people-visual__card people-visual__card--two"><i />Work in sync</span>
            </div>

            <div className="login-story__features">
              <span>Role-based access</span>
              <span>Connected records</span>
              <span>Clear workflows</span>
            </div>
          </section>

          <section className="login-panel">
            <div className="login-panel__inner">
              <div className="login-panel__heading">
                <span className="login-panel__eyebrow">Welcome back</span>
                <h2>Sign in to Nexa People</h2>
                <p>Use the account provided for your Admin, HR, Manager, or Employee role.</p>
              </div>

              <form onSubmit={handleSubmit} className="login-form" noValidate>
                <div className="form-field">
                  <label htmlFor="username">Username</label>
                  <div className="form-control">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
                      <circle cx="12" cy="8" r="4" />
                      <path d="M4 21a8 8 0 0 1 16 0" />
                    </svg>
                    <input
                      id="username"
                      name="username"
                      type="text"
                      autoComplete="username"
                      value={formData.username}
                      onChange={handleChange}
                      placeholder="Enter your username"
                      aria-invalid={Boolean(error)}
                      required
                    />
                  </div>
                </div>

                <div className="form-field">
                  <label htmlFor="password">Password</label>
                  <div className="form-control">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
                      <rect x="4" y="10" width="16" height="11" rx="3" />
                      <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                    </svg>
                    <input
                      id="password"
                      name="password"
                      type={showPassword ? 'text' : 'password'}
                      autoComplete="current-password"
                      value={formData.password}
                      onChange={handleChange}
                      placeholder="Enter your password"
                      aria-invalid={Boolean(error)}
                      required
                    />
                    <button
                      type="button"
                      className="password-toggle"
                      aria-label={showPassword ? 'Hide password' : 'Show password'}
                      onClick={() => setShowPassword((previous) => !previous)}
                    >
                      <EyeIcon hidden={showPassword} />
                    </button>
                  </div>
                </div>

                <div className="login-form__support">
                  <span><i />Protected role-based workspace</span>
                </div>

                {error && <div className="form-message form-message--error" aria-live="polite">{error}</div>}
                {successMessage && <div className="form-message form-message--success" aria-live="polite">{successMessage}</div>}

                <button type="submit" disabled={loading} className="login-submit">
                  <span>{loading ? 'Signing you in…' : 'Sign in to workspace'}</span>
                  {!loading && (
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
                      <path d="M5 12h14m-5-5 5 5-5 5" />
                    </svg>
                  )}
                </button>
              </form>

              <p className="login-panel__footer">Authorized team members only · Access is recorded securely</p>
            </div>
          </section>
        </div>
      </div>
    </main>
  )
}

export default LoginPage
