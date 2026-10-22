import apiClient from './client'

// Routes a not-yet-onboarded employee may open. The backend is the real gate
// (see docs/ONBOARDING_ACCESS.md); this list only keeps the UI honest.
export const ONBOARDING_OPEN_ROUTES = [
  '/dashboard',
  '/dashboard/employee',
  '/onboarding',
  '/documents',
  '/attendance',
  '/change-password',
]

export function readStoredUser() {
  try {
    return JSON.parse(localStorage.getItem('hrms_user') || 'null')
  } catch {
    return null
  }
}

export function storeUser(user) {
  localStorage.setItem('hrms_user', JSON.stringify(user))
}

export function isOpenDuringOnboarding(path) {
  return ONBOARDING_OPEN_ROUTES.some((route) => path === route || path.startsWith(`${route}/`))
}

export function isLockedByOnboarding(user, path) {
  return Boolean(user?.onboarding_required) && typeof path === 'string' && path.startsWith('/') && !isOpenDuringOnboarding(path)
}

// Re-reads the signed-in user so flags such as onboarding_required stay current
// after HR completes an onboarding. Returns the merged user and whether a flag changed.
export async function refreshSession() {
  const stored = readStoredUser()
  const response = await apiClient.get('/api/auth/me.php')
  const fresh = response.data?.data?.user
  const csrfToken = response.data?.data?.csrf_token
  if (csrfToken) sessionStorage.setItem('hrms_csrf_token', csrfToken)
  if (!stored || !fresh) return { user: stored, changed: false }

  const merged = { ...stored, ...fresh }
  const changed = Boolean(stored.onboarding_required) !== Boolean(merged.onboarding_required)
    || Boolean(stored.must_change_password) !== Boolean(merged.must_change_password)
  if (changed) storeUser(merged)
  return { user: merged, changed }
}
