import axios, { type AxiosInstance, type InternalAxiosRequestConfig, type AxiosError } from 'axios'
import { API_BASE_URL } from '@/config/api'
import { useToastStore } from '@/stores/toastStore'

const http: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// ── Request: attach Bearer token ────────────────────────────────
http.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// ── Response: global error handling ─────────────────────────────
http.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
    const status = error.response?.status
    const data = error.response?.data

    // Toast store — lazy-init to avoid Pinia "before app" error
    let toast: ReturnType<typeof useToastStore> | null = null
    try {
      toast = useToastStore()
    } catch {
      // Pinia not yet initialized (edge case on app boot)
    }

    switch (status) {
      // ── 401 Unauthenticated ─────────────────────────────────
      case 401:
        localStorage.removeItem('token')
        if (!window.location.pathname.startsWith('/login')) {
          window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname)
        }
        break

      // ── 403 Forbidden ───────────────────────────────────────
      case 403:
        toast?.error(data?.message || 'Accès refusé. Vous n\'avez pas les permissions nécessaires.')
        break

      // ── 404 Not Found ───────────────────────────────────────
      case 404:
        // Silent for API calls — pages handle their own 404
        break

      // ── 422 Validation Error ────────────────────────────────
      case 422: {
        if (data?.errors) {
          const firstError = Object.values(data.errors)[0]?.[0]
          if (firstError) {
            toast?.error(firstError)
            break
          }
        }
        if (data?.message) {
          toast?.error(data.message)
        }
        break
      }

      // ── 429 Too Many Requests ───────────────────────────────
      case 429:
        toast?.warning('Trop de requêtes. Veuillez patienter quelques instants.')
        break

      // ── 500+ Server Error ───────────────────────────────────
      case 500:
      case 502:
      case 503:
        toast?.error('Erreur serveur. Veuillez réessayer ou contacter l\'administrateur.')
        break

      // ── Network Error (no response) ─────────────────────────
      default:
        if (!error.response) {
          toast?.error('Erreur réseau. Vérifiez votre connexion internet.')
        }
        break
    }

    return Promise.reject(error)
  },
)

export default http
