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

export default apiClient
