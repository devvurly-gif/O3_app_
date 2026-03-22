import { ref, reactive } from 'vue'
import http from '@/services/http'
import type { PaginationMeta, PaginationParams, PaginatedResponse } from '@/types'

export function usePaginatedApi<T>(endpoint: string) {
  const items = ref<T[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const meta: PaginationMeta = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: null,
    to: null,
  })

  const params: PaginationParams = reactive({
    page: 1,
    per_page: 15,
    sort: null,
    order: null,
    search: null,
  })

  async function fetchPage(page: number | null = null): Promise<void> {
    if (page !== null) params.page = page
    loading.value = true
    error.value = null

    try {
      const query: Record<string, unknown> = {}
      for (const [k, v] of Object.entries(params)) {
        if (v !== null && v !== '' && v !== undefined) query[k] = v
      }

      const { data } = await http.get<PaginatedResponse<T>>(endpoint, { params: query })

      items.value = data.data as T[]
      meta.current_page = data.current_page
      meta.last_page = data.last_page
      meta.per_page = data.per_page
      meta.total = data.total
      meta.from = data.from
      meta.to = data.to
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } }
      error.value = err.response?.data?.message ?? 'Erreur de chargement.'
    } finally {
      loading.value = false
    }
  }

  function goToPage(page: number): void {
    fetchPage(page)
  }

  return { items, meta, loading, error, params, fetchPage, goToPage }
}
