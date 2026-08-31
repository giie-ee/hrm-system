import { useState } from 'react'

function LoginPage() {
  const [formData, setFormData] = useState({
    email: '',
    password: '',
  })

  const handleChange = (event) => {
    const { name, value } = event.target
    setFormData((prev) => ({ ...prev, [name]: value }))
  }

  const handleSubmit = (event) => {
    event.preventDefault()
    console.log('Login form submitted', formData)
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-100 p-6">
      <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl ring-1 ring-slate-200">
        <div className="mb-6 text-center">
          <p className="text-sm font-semibold uppercase tracking-[0.18em] text-sky-600">
            HRMS
          </p>
          <h1 className="mt-3 text-3xl font-bold text-slate-900">Welcome back</h1>
          <p className="mt-2 text-sm text-slate-500">
            Sign in to access your employee dashboard.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-5">
          <div>
            <label htmlFor="email" className="mb-2 block text-sm font-medium text-slate-700">
              Email address
            </label>
            <input
              id="email"
              name="email"
              type="email"
              value={formData.email}
              onChange={handleChange}
              placeholder="name@company.com"
              className="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-slate-900 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200"
            />
          </div>

          <div>
            <label htmlFor="password" className="mb-2 block text-sm font-medium text-slate-700">
              Password
            </label>
            <input
              id="password"
              name="password"
              type="password"
              value={formData.password}
              onChange={handleChange}
              placeholder="Enter your password"
              className="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-slate-900 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200"
            />
          </div>

          <div className="flex items-center justify-between text-sm">
            <label className="flex items-center gap-2 text-slate-600">
              <input type="checkbox" className="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
              Remember me
            </label>
            <a href="#" className="font-medium text-sky-600 hover:text-sky-500">
              Forgot password?
            </a>
          </div>

          <button
            type="submit"
            className="w-full rounded-xl bg-sky-600 px-4 py-3 font-semibold text-white transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2"
          >
            Sign in
          </button>
        </form>
      </div>
    </main>
  )
}

export default LoginPage
