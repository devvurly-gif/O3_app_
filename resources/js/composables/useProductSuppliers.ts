import { reactive, ref, toValue } from 'vue'
import type { MaybeRefOrGetter, Ref } from 'vue'
import http from '@/services/http'

/**
 * Les fournisseurs pouvant livrer un produit — un produit s'achete souvent
 * chez plusieurs fournisseurs, a des prix et delais differents. La priorite
 * la plus basse (1 = prefere) est celle qu'utilise achats:draft-daily-po.
 *
 * Meme patron que useProductPriceTiers : pas d'edition en ligne, pour
 * changer un prix/une priorite on retire le lien et on le rajoute.
 */
export interface ProductSuppliersOptions {
  /** Le produit en cours d'edition, ou null en creation. */
  product: MaybeRefOrGetter<any>
  /** Remonte un message a l'utilisateur. */
  notify: (message: string, level: 'success' | 'error') => void
}

export function useProductSuppliers(options: ProductSuppliersOptions) {
  const items: Ref<any[]> = ref([])

  const adding = ref(false)
  const saving = ref(false)
  const deletingId: Ref<number | null> = ref(null)

  const newLink = reactive({
    third_partner_id: null as number | null,
    supplier_sku: '',
    purchase_price: null as number | null,
    priority: 1,
    lead_time_days: null as number | null,
  })

  const productId = (): number | null => toValue(options.product)?.id ?? null

  function isSupplierAlreadyLinked(thirdPartnerId: number): boolean {
    return items.value.some((i: any) => Number(i.id ?? i.third_partner_id) === thirdPartnerId)
  }

  async function reload(): Promise<void> {
    const id = productId()
    if (!id) return
    try {
      const { data } = await http.get(`/products/${id}/suppliers`)
      items.value = Array.isArray(data) ? data : data.data ?? []
    } catch (e) {
      console.error('Failed to reload product suppliers', e)
    }
  }

  function resetForm(): void {
    newLink.third_partner_id = null
    newLink.supplier_sku = ''
    newLink.purchase_price = null
    newLink.priority = 1
    newLink.lead_time_days = null
  }

  async function add(): Promise<void> {
    const id = productId()
    if (!newLink.third_partner_id || !id) return
    saving.value = true
    try {
      await http.post(`/products/${id}/suppliers`, {
        third_partner_id: newLink.third_partner_id,
        supplier_sku: newLink.supplier_sku || null,
        purchase_price: newLink.purchase_price,
        priority: newLink.priority || 1,
        lead_time_days: newLink.lead_time_days,
      })
      await reload()
      resetForm()
      adding.value = false
      options.notify('Fournisseur lié', 'success')
    } catch (e: any) {
      const msg = e?.response?.data?.message ?? "Échec de l'ajout du fournisseur"
      options.notify(msg, 'error')
    } finally {
      saving.value = false
    }
  }

  async function remove(item: any): Promise<void> {
    const id = productId()
    if (!id) return
    if (!confirm('Retirer ce fournisseur du produit ?')) return
    deletingId.value = item.id
    try {
      await http.delete(`/products/${id}/suppliers/${item.id}`)
      items.value = items.value.filter((i: any) => i.id !== item.id)
      options.notify('Fournisseur retiré', 'success')
    } catch (e: any) {
      const msg = e?.response?.data?.message ?? 'Échec de la suppression'
      options.notify(msg, 'error')
    } finally {
      deletingId.value = null
    }
  }

  return {
    items,
    adding,
    saving,
    deletingId,
    newLink,
    isSupplierAlreadyLinked,
    reload,
    add,
    remove,
  }
}
