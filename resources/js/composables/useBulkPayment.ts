import { computed, reactive, ref, toValue } from 'vue'
import type { MaybeRefOrGetter, Ref } from 'vue'
import http from '@/services/http'

/**
 * Le reglement groupe depuis une fiche tiers.
 *
 * Clients.vue et Fournisseurs.vue en portaient chacun une copie, au prefixe
 * pres (`payment*` d'un cote, `bulkPayment*` de l'autre) : meme etat, meme
 * selection, meme appel a `/third-partners/{id}/bulk-payment`.
 *
 * Ce qui les separait vraiment tient en deux points, devenus des options :
 * les types de documents qu'un reglement peut solder — la vente y ajoute le
 * bon de livraison quand le tenant a active « paiement sur BL » — et ce
 * qu'il faut rafraichir une fois l'argent encaisse.
 */
export interface BulkPaymentOptions {
  /** Types de documents qu'un reglement groupe peut solder. */
  payableDocTypes: MaybeRefOrGetter<string[]>
  /** Remonte un message a l'utilisateur. */
  notify: (message: string, level: 'success' | 'error') => void
  /**
   * Appele apres chaque encaissement, avec la fiche tiers rechargee.
   * C'est la que l'ecran rafraichit sa liste et ses autres panneaux ouverts.
   */
  onSettled?: (partner: any) => void
}

export interface BulkPaymentForm {
  amount: number
  method: string
  reference: string
  notes: string
}

const emptyForm = (): BulkPaymentForm => ({ amount: 0, method: 'cash', reference: '', notes: '' })

function amountDue(doc: any): number {
  return Number(doc.footer?.amount_due ?? 0)
}

export function useBulkPayment(options: BulkPaymentOptions) {
  const show = ref(false)
  const target = ref<any>(null)
  const loading = ref(false)
  const saving = ref(false)
  const detail = ref<any>(null)
  const result = ref<any>(null)

  const form = reactive<BulkPaymentForm>(emptyForm())

  function isPayable(doc: any): boolean {
    return toValue(options.payableDocTypes).includes(doc.document_type)
  }

  /** Les documents qui restent a solder, du plus ancien au plus recent. */
  const unpaidDocs = computed<any[]>(() => {
    const docs = detail.value?.document_headers
    if (!docs) return []
    return docs
      .filter((d: any) => isPayable(d) && amountDue(d) > 0)
      .sort((a: any, b: any) => new Date(a.issued_at).getTime() - new Date(b.issued_at).getTime())
  })

  /** Tous les documents soldables, soldes compris — repli quand rien n'est du. */
  const payableDocs = computed<any[]>(() => {
    const docs = detail.value?.document_headers
    if (!docs) return []
    return docs
      .filter(isPayable)
      .sort((a: any, b: any) => new Date(b.issued_at).getTime() - new Date(a.issued_at).getTime())
  })

  /** Les documents coches. Pre-remplis a l'ouverture avec tout ce qui est du. */
  const selectedIds: Ref<number[]> = ref([])

  /** Le document vise par un reglement a l'unite, distinct du groupe. */
  const selectedDocId: Ref<number | null> = ref(null)

  const selectedTotalDue = computed(() =>
    unpaidDocs.value.filter((d: any) => selectedIds.value.includes(d.id)).reduce((sum, d) => sum + amountDue(d), 0),
  )

  const allSelected = computed(
    () => unpaidDocs.value.length > 0 && selectedIds.value.length === unpaidDocs.value.length,
  )

  function toggleDoc(id: number): void {
    const idx = selectedIds.value.indexOf(id)
    if (idx >= 0) selectedIds.value.splice(idx, 1)
    else selectedIds.value.push(id)
  }

  function toggleSelectAll(): void {
    selectedIds.value = allSelected.value ? [] : unpaidDocs.value.map((d: any) => d.id)
  }

  /** Recharge la fiche et previent l'ecran hote. */
  async function reload(): Promise<void> {
    const { data } = await http.get(`/third-partners/${target.value.id}`)
    detail.value = data
    options.onSettled?.(data)
  }

  function failed(err: unknown): void {
    const e = err as { response?: { data?: { message?: string } } }
    options.notify(e.response?.data?.message ?? 'Erreur lors du paiement', 'error')
  }

  async function open(row: any): Promise<void> {
    target.value = row
    result.value = null
    Object.assign(form, emptyForm())
    detail.value = null
    selectedDocId.value = null
    selectedIds.value = []
    show.value = true
    loading.value = true
    try {
      const { data } = await http.get(`/third-partners/${row.id}`)
      detail.value = data
      // Tout ce qui est du est coche d'office, et le montant pre-rempli en face.
      selectedIds.value = unpaidDocs.value.map((d: any) => d.id)
      form.amount = Number(selectedTotalDue.value.toFixed(2))
    } catch {
      detail.value = null
    } finally {
      loading.value = false
    }
  }

  /** Encaisse un montant et le laisse s'imputer sur les documents coches. */
  async function submit(): Promise<void> {
    if (!target.value || !form.amount || form.amount <= 0) return
    saving.value = true
    result.value = null
    try {
      const { data } = await http.post(`/third-partners/${target.value.id}/bulk-payment`, {
        amount: form.amount,
        method: form.method,
        reference: form.reference || null,
        notes: form.notes || null,
        document_ids: selectedIds.value.length > 0 ? selectedIds.value : undefined,
      })
      result.value = data
      options.notify(data.message, 'success')
      await reload()
      form.amount = 0
    } catch (err: unknown) {
      failed(err)
    } finally {
      saving.value = false
    }
  }

  /** Encaisse sur un seul document, choisi dans la liste. */
  async function submitSingleDoc(): Promise<void> {
    if (!selectedDocId.value || !form.amount || form.amount <= 0) return
    saving.value = true
    result.value = null
    try {
      await http.post('/payments', {
        document_header_id: selectedDocId.value,
        amount: form.amount,
        method: form.method,
        paid_at: new Date().toISOString().slice(0, 10),
        reference: form.reference || null,
        notes: form.notes || null,
      })
      options.notify('Paiement enregistré.', 'success')
      await reload()
      form.amount = 0
      form.reference = ''
      form.notes = ''
      selectedDocId.value = null
    } catch (err: unknown) {
      failed(err)
    } finally {
      saving.value = false
    }
  }

  return {
    show,
    target,
    loading,
    saving,
    detail,
    result,
    form,
    unpaidDocs,
    payableDocs,
    selectedIds,
    selectedDocId,
    selectedTotalDue,
    allSelected,
    toggleDoc,
    toggleSelectAll,
    open,
    submit,
    submitSingleDoc,
  }
}
