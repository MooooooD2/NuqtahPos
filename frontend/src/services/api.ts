import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/authStore'

// Detect if running in Tauri desktop app
const isTauri = typeof window !== 'undefined' && '__TAURI__' in window

const BASE_URL = isTauri
  ? import.meta.env.VITE_API_URL ?? 'http://localhost:8000'
  : '/api'  // proxied via Vite dev server; relative in production

export const api = axios.create({
  baseURL: BASE_URL,
  timeout: 30_000,
  withCredentials: true,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-Tenant-Code': 'main',
  },
})

// ─── Request interceptor — attach Bearer token ────────────────────────────────
api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = useAuthStore.getState().token
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// ─── Response interceptor — handle 401 / errors ──────────────────────────────
api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    if (error.response?.status === 401) {
      useAuthStore.getState().logout()
      window.location.href = '/login'
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

export const apiDelete = <T>(url: string) =>
  api.delete<T>(url).then((r) => r.data)

// ─── CSRF cookie (needed for Sanctum web guard) ───────────────────────────────
export const fetchCsrfCookie = () =>
  axios.get(`${isTauri ? (import.meta.env.VITE_API_URL ?? 'http://localhost:8000') : ''}/sanctum/csrf-cookie`, {
    withCredentials: true,
  })
