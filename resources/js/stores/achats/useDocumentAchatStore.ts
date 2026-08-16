import { defineStore } from 'pinia'
import { ref, reactive, computed } from 'vue'
import http from '@/services/http'
import type { DocumentHeader, PaginationMeta, PaginatedResponse } from '@/types'

export const useDocumentAchatStore = defineStore('documentAchat', () => {
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
  const bonsCommande = computed(() => documents.value.filter((d) => d.document_type === 'PurchaseOrder'))
  const bonsReception = computed(() => documents.value.filter((d) => d.document_type === 'ReceiptNotePurchase'))
  const facturesAchat = computed(() => documents.value.filter((d) => d.document_type === 'InvoicePurchase'))

  // ── Actions ───────────────────────────────────────────────────────────

  async function fetchAll(page: number = 1, filters: Record<string, unknown> = {}): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const params = { page, per_page: 15, domain: 'purchases', ...filters }
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
   * Convert a PurchaseOrder into a ReceiptNote (Bon de Réception).
   * Observer fires automatically → stock entry (IN).
   */
  async function genererReception(commandeId: number): Promise<{ success: boolean; br?: DocumentHeader }> {
    loading.value = true
    error.value = null

    try {
      const { data } = await http.post<{ data: DocumentHeader }>(`/achats/documents/${commandeId}/generer-reception`)
      return { success: true, br: data.data }
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } }
      error.value = err.response?.data?.message ?? 'Erreur de génération du Bon de Réception.'
      return { success: false }
    } finally {
      loading.value = false
    }
  }

  /**
   * Confirm a receipt note (BR) — applies stock movements.
   */
  async function confirmerBR(brId: number): Promise<DocumentHeader> {
    loading.value = true
    error.value = null
    try {
      // L'endpoint repond { message, data: <document> }, comme ses voisins
      // generer-* / confirmer-facture : sans deballer l'enveloppe, la page
      // de detail se lie a un objet sans aucun champ et s'affiche vide.
      const { data } = await http.put<{ message: string; data: DocumentHeader }>(`/achats/documents/${brId}/confirmer-br`)
      current.value = data.data
      return data.data
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } }
      error.value = err.response?.data?.message ?? 'Erreur de confirmation.'
      throw e
    } finally {
      loading.value = false
    }
  }

  /**
   * Create a supplier return (Bon de Retour) from a committed BR or purchase
   * invoice — the way to undo a confirmed document, since cancelling one is
   * refused. Without `lines` the whole document is returned.
   */
  async function retourFournisseur(
    documentId: number,
    payload: { lines?: Array<{ product_id: number; quantity: number }>; notes?: string } = {},
  ): Promise<{ success: boolean; retour?: DocumentHeader }> {
    loading.value = true
    error.value = null

    try {
      const { data } = await http.post<{ data: DocumentHeader }>(
        `/achats/documents/${documentId}/retour-fournisseur`,
        payload,
      )
      return { success: true, retour: data.data }
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } }
      error.value = err.response?.data?.message ?? 'Erreur lors du retour fournisseur.'
      return { success: false }
    } finally {
      loading.value = false
    }
  }

  /**
   * Confirm receipt and create the Purchase Invoice (Facture Achat).
   */
  async function confirmerFacture(
    brId: number,
    paymentMethod = 'credit',
  ): Promise<{ success: boolean; facture?: DocumentHeader }> {
    loading.value = true
    error.value = null

    try {
      const { data } = await http.put<{ data: DocumentHeader }>(`/achats/documents/${brId}/confirmer-facture`, {
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
    meta,
    bonsCommande,
    bonsReception,
    facturesAchat,
    fetchAll,
    fetchOne,
    create,
    update,
    updateStatus,
    remove,
    addPayment,
    genererReception,
    confirmerBR,
    confirmerFacture,
    retourFournisseur,
  }
})
