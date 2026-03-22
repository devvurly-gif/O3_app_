import { defineStore } from 'pinia'
import { ref, reactive, computed } from 'vue'
import http from '@/services/http'
import type { DocumentHeader, PaginationMeta, PaginatedResponse } from '@/types'

export const useDocumentStockStore = defineStore('documentStock', () => {
  // ── State ─────────────────────────────────────────────────────────────
  const documents = ref<DocumentHeader[]>([])
  const current = ref<DocumentHeader | null>(null)
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

  // ── Getters ───────────────────────────────────────────────────────────
  const entrees = computed(() => documents.value.filter((d) => d.document_type === 'StockEntry'))
  const sorties = computed(() => documents.value.filter((d) => d.document_type === 'StockExit'))
  const ajustements = computed(() => documents.value.filter((d) => d.document_type === 'StockAdjustmentNote'))
  const transferts = computed(() => documents.value.filter((d) => d.document_type === 'StockTransfer'))

  // ── Actions ───────────────────────────────────────────────────────────

  async function fetchAll(page: number = 1, filters: Record<string, unknown> = {}): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const params = { page, per_page: 15, domain: 'stock', ...filters }
      const { data } = await http.get<PaginatedResponse<DocumentHeader>>('/documents', { params })
      documents.value = data.data
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

  async function fetchOne(id: number): Promise<DocumentHeader | null> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get<DocumentHeader>(`/documents/${id}`)
      current.value = data
      return data
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } }
      error.value = err.response?.data?.message ?? 'Document introuvable.'
      return null
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Record<string, unknown>): Promise<DocumentHeader> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.post<DocumentHeader>('/documents', payload)
      return data
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      error.value = err.response?.data?.message ?? 'Erreur de création.'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function update(id: number, payload: Record<string, unknown>): Promise<DocumentHeader> {
    loading.value = true
    error.value = null
    try {
      await http.put(`/documents/${id}`, payload)
      const fresh = await fetchOne(id)
      return fresh!
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      error.value = err.response?.data?.message ?? 'Erreur de mise à jour.'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function remove(id: number): Promise<void> {
    loading.value = true
    try {
      await http.delete(`/documents/${id}`)
      documents.value = documents.value.filter((d) => d.id !== id)
    } finally {
      loading.value = false
    }
  }

  /**
   * Applique un document de stock : déclenche les mouvements de stock
   * et passe le statut à 'applied'.
   */
  async function appliquer(id: number): Promise<{ success: boolean; document?: DocumentHeader }> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.post<{ data: DocumentHeader; message: string }>(`/stock/documents/${id}/appliquer`)
      // Mettre à jour dans la liste si présent
      const idx = documents.value.findIndex((d) => d.id === id)
      if (idx !== -1) documents.value[idx] = data.data
      if (current.value?.id === id) current.value = data.data
      return { success: true, document: data.data }
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } }
      error.value = err.response?.data?.message ?? "Erreur lors de l'application."
      return { success: false }
    } finally {
      loading.value = false
    }
  }

  /**
   * Annule un document de stock (draft ou confirmed uniquement).
   */
  async function annuler(id: number): Promise<{ success: boolean; document?: DocumentHeader }> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.post<{ data: DocumentHeader; message: string }>(`/stock/documents/${id}/annuler`)
      const idx = documents.value.findIndex((d) => d.id === id)
      if (idx !== -1) documents.value[idx] = data.data
      if (current.value?.id === id) current.value = data.data
      return { success: true, document: data.data }
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } }
      error.value = err.response?.data?.message ?? "Erreur lors de l'annulation."
      return { success: false }
    } finally {
      loading.value = false
    }
  }

  return {
    documents,
    current,
    loading,
    error,
    meta,
    entrees,
    sorties,
    ajustements,
    transferts,
    fetchAll,
    fetchOne,
    create,
    update,
    remove,
    appliquer,
    annuler,
  }
})
