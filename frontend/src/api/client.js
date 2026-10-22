import axios from 'axios'

const apiClient = axios.create({
  // Production uses the same Render origin. Set VITE_API_BASE_URL only when
  // the frontend and PHP API are intentionally hosted on different origins.
  baseURL: import.meta.env.VITE_API_BASE_URL || '',
  timeout: 10000,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
  },
})

apiClient.interceptors.request.use((config) => {
  const csrfToken = sessionStorage.getItem('hrms_csrf_token')
  if (csrfToken && config.method?.toLowerCase() !== 'get') {
    config.headers['X-CSRF-Token'] = csrfToken
  }
  return config
})

// Backend gates: a temporary password or an unfinished onboarding limits what an
// account may reach. Send the person to the page that fixes it instead of
// showing a generic error. Only redirect from pages that are not already open.
const openDuringOnboarding = ['/dashboard', '/onboarding', '/documents', '/attendance', '/change-password']

function flagStoredUser(flag) {
  try {
    const user = JSON.parse(localStorage.getItem('hrms_user') || 'null')
    if (user) localStorage.setItem('hrms_user', JSON.stringify({ ...user, [flag]: true }))
  } catch {
    // The redirect below still happens when stored data is unreadable.
  }
}

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const code = error.response?.status === 403 ? error.response?.data?.code : null
    const path = window.location.pathname

    if (code === 'PASSWORD_CHANGE_REQUIRED' && path !== '/change-password') {
      flagStoredUser('must_change_password')
      window.location.assign('/change-password')
    } else if (code === 'ONBOARDING_REQUIRED') {
      flagStoredUser('onboarding_required')
      if (!openDuringOnboarding.some((route) => path === route || path.startsWith(`${route}/`))) {
        window.location.assign('/onboarding')
      }
    }

    return Promise.reject(error)
  },
)

export default apiClient
