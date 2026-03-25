import { defineStore } from 'pinia'
import { ref, reactive, computed } from 'vue'
import http from '@/services/http'
import type { DocumentHeader, PaginationMeta, PaginatedResponse } from '@/types'

export const useDocumentVenteStore = defineStore('documentVente', () => {
  // ── State ─────────────────────────────────────────────────────────────
  const documents = ref<DocumentHeader[]>([])
  const current = ref<DocumentHeader | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const creditError = ref<string | null>(null)

  const meta: PaginationMeta = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: null,
    to: null,
  })

  // ── Getters ───────────────────────────────────────────────────────────
  const devis = computed(() => documents.value.filter((d) => d.document_type === 'QuoteSale'))
  const bonsLivraison = computed(() => documents.value.filter((d) => d.document_type === 'DeliveryNote'))
  const factures = computed(() => documents.value.filter((d) => d.document_type === 'InvoiceSale'))

  // ── Actions ───────────────────────────────────────────────────────────

  async function fetchAll(page: number = 1, filters: Record<string, unknown> = {}): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const params = { page, per_page: 15, domain: 'sales', ...filters }
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

  /**
   * Convert a Quote into a Delivery Note (BL).
   * If credit is exceeded, the server returns 422 with a credit error.
   */
  async function genererBC(devisId: number): Promise<{ success: boolean; bc?: DocumentHeader; error?: string }> {
    loading.value = true
    try {
      const { data } = await http.post<{ data: DocumentHeader }>(`/ventes/documents/${devisId}/generer-bc`)
      return { success: true, bc: data.data }
    } catch (e: unknown) {
      const err = e as { response?: { status?: number; data?: { message?: string } } }
      if (err.response?.status === 422) {
        return { success: false, error: err.response.data?.message ?? 'Erreur de conversion.' }
      }
      error.value = 'Erreur serveur. Veuillez réessayer.'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function genererBL(bcId: number): Promise<{ success: boolean; bl?: DocumentHeader; creditError?: string }> {
    creditError.value = null
    loading.value = true

    try {
      const { data } = await http.post<{ data: DocumentHeader }>(`/ventes/documents/${bcId}/generer-bl`)
      return { success: true, bl: data.data }
    } catch (e: unknown) {
      const err = e as { response?: { status?: number; data?: { message?: string; errors?: { credit?: string[] } } } }
      if (err.response?.status === 422) {
        creditError.value = err.response.data?.errors?.credit?.[0] ?? err.response.data?.message ?? null
        return { success: false, creditError: creditError.value ?? undefined }
      }

      error.value = 'Erreur serveur. Veuillez réessayer.'
      throw e
    } finally {
      loading.value = false
    }
  }

  /**
   * Confirm delivery reception — creates the Invoice.
   */
  async function confirmerReception(
    blId: number,
    paymentMethod = 'credit',
  ): Promise<{ success: boolean; facture?: DocumentHeader }> {
    loading.value = true

    try {
      const { data } = await http.put<{ data: DocumentHeader }>(`/ventes/documents/${blId}/confirmer`, {
        payment_method: paymentMethod,
      })
      return { success: true, facture: data.data }
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } }
      error.value = err.response?.data?.message ?? 'Erreur de confirmation.'
      return { success: false }
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

  async function updateStatus(id: number, status: string): Promise<DocumentHeader> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.patch<DocumentHeader>(`/documents/${id}`, { status })
      current.value = data
      return data
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } }
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
    } finally {
      loading.value = false
    }
  }

  async function addPayment(payload: Record<string, unknown>): Promise<unknown> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.post('/payments', payload)
      return data
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } }
      error.value = err.response?.data?.message ?? 'Erreur de paiement.'
      throw e
    } finally {
      loading.value = false
    }
  }

  return {
    documents,
    current,
    loading,
    error,
    creditError,
    meta,
    devis,
    bonsLivraison,
    factures,
    fetchAll,
    fetchOne,
    create,
    update,
    updateStatus,
    remove,
    addPayment,
    genererBC,
    genererBL,
    confirmerReception,
  }
})
