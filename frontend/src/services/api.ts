import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/authStore'
import { isTauriApp } from '@/lib/tauri'

// Detect if running in Tauri desktop app (works for Tauri v2 via __TAURI_INTERNALS__)
const isTauri = isTauriApp()

// '/pos' in web production build, '' in desktop/dev (BASE_URL is '/pos/' or '/')
const webBase = (import.meta.env.BASE_URL ?? '/').replace(/\/$/, '')

export const SERVER_URL_KEY = 'pos-server-url'

// Read at request time so changes made on the login page take effect immediately.
export function getBaseUrl(): string {
  if (isTauri) {
    const serverUrl = (localStorage.getItem(SERVER_URL_KEY) ?? import.meta.env.VITE_API_URL ?? 'http://localhost:8000').replace(/\/$/, '')
    return `${serverUrl}/api`
  }
  // Dev: Vite intercepts /pos/... before proxy can handle it, so call Laravel directly.
  // CORS already allows http://localhost:5173 (see backend/config/cors.php).
  if (import.meta.env.DEV) return 'http://localhost:8000/api'
  // Production: Apache routes /pos/api/... → Laravel (see backend/public/.htaccess)
  return `${webBase}/api`
}

export const api = axios.create({
  timeout: 30_000,
  // Desktop uses Bearer-token auth only — cookies not needed and cause CORS pre-flight friction
  withCredentials: !isTauri,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

// ─── Request interceptor — set baseURL dynamically + attach Bearer token + tenant code ───
api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  config.baseURL = getBaseUrl()
  const token = useAuthStore.getState().token
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`
  }
  // Read company code saved at login time so every request reaches the right tenant
  const tenantCode = localStorage.getItem('pos-company-code') ?? 'main'
  if (config.headers) {
    config.headers['X-Tenant-Code'] = tenantCode
  }
  return config
})

// ─── Response interceptor — handle 401 / errors ──────────────────────────────
api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    // Don't redirect when the 401 came from the login endpoint itself —
    // the form's catch block already shows the error toast and the user is already on /login
    const isLoginRequest = error.config?.url?.endsWith('/login')
    if (error.response?.status === 401 && !isLoginRequest) {
      useAuthStore.getState().logout()
      window.location.href = `${webBase}/login`
    }

    // Network error — queue for offline sync
    if (!error.response && error.code === 'ERR_NETWORK') {
      const offlineStore = await import('@/stores/offlineStore')
      offlineStore.useOfflineStore.getState().setOffline(true)
    }

    return Promise.reject(error)
  },
)

// ─── Typed helper wrappers ────────────────────────────────────────────────────
export const apiGet = <T>(url: string, params?: Record<string, unknown>) =>
  api.get<T>(url, { params }).then((r) => r.data)

export const apiPost = <T>(url: string, data?: unknown) =>
  api.post<T>(url, data).then((r) => r.data)

export const apiPut = <T>(url: string, data?: unknown) =>
  api.put<T>(url, data).then((r) => r.data)

export const apiPatch = <T>(url: string, data?: unknown) =>
  api.patch<T>(url, data).then((r) => r.data)

export const apiDelete = <T>(url: string) =>
  api.delete<T>(url).then((r) => r.data)

// ─── CSRF cookie (needed for Sanctum web guard) ───────────────────────────────
export const fetchCsrfCookie = () => {
  let base: string
  if (isTauri) {
    base = (localStorage.getItem(SERVER_URL_KEY) ?? import.meta.env.VITE_API_URL ?? 'http://localhost:8000').replace(/\/$/, '')
  } else if (import.meta.env.DEV) {
    base = 'http://localhost:8000'
  } else {
    base = webBase
  }
  return axios.get(`${base}/sanctum/csrf-cookie`, { withCredentials: true })
}
