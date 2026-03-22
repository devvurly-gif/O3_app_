import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import http from '@/services/http'
import type { StockMouvement, PaginationMeta, PaginatedResponse } from '@/types'

interface StockOperationResult {
  success: boolean
  data?: StockMouvement
}

export const useStockOperationStore = defineStore('stockOperation', () => {
  const mouvements = ref<StockMouvement[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const success = ref<string | null>(null)

  const meta: PaginationMeta = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
    from: null,
    to: null,
  })

  async function fetchMouvements(page: number = 1, filters: Record<string, unknown> = {}): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const params = { page, per_page: 20, ...filters }
      const { data } = await http.get<PaginatedResponse<StockMouvement>>('/stock-mouvements', { params })
      mouvements.value = data.data
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

  async function entree(payload: Record<string, unknown>): Promise<StockOperationResult> {
    loading.value = true
    error.value = null
    success.value = null
    try {
      const { data } = await http.post<{ data: StockMouvement; message: string }>('/stock/entree', payload)
      mouvements.value.unshift(data.data)
      success.value = data.message
      return { success: true, data: data.data }
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } }
      error.value = err.response?.data?.message ?? "Erreur lors de l'entrée."
      return { success: false }
    } finally {
      loading.value = false
    }
  }

  async function sortie(payload: Record<string, unknown>): Promise<StockOperationResult> {
    loading.value = true
    error.value = null
    success.value = null
    try {
      const { data } = await http.post<{ data: StockMouvement; message: string }>('/stock/sortie', payload)
      mouvements.value.unshift(data.data)
      success.value = data.message
      return { success: true, data: data.data }
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } }
      error.value = err.response?.data?.message ?? 'Erreur lors de la sortie.'
      return { success: false }
    } finally {
      loading.value = false
    }
  }

  async function ajustement(payload: Record<string, unknown>): Promise<StockOperationResult> {
    loading.value = true
    error.value = null
    success.value = null
    try {
      const { data } = await http.post<{ data: StockMouvement; message: string }>('/stock/ajustement', payload)
      mouvements.value.unshift(data.data)
      success.value = data.message
      return { success: true, data: data.data }
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } }
      error.value = err.response?.data?.message ?? "Erreur lors de l'ajustement."
      return { success: false }
    } finally {
      loading.value = false
    }
  }

  function clearMessages(): void {
    error.value = null
    success.value = null
  }

  return {
    mouvements,
    meta,
    loading,
    error,
    success,
    fetchMouvements,
    entree,
    sortie,
    ajustement,
    clearMessages,
  }
})
