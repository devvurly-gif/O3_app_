import { computed, reactive, ref, toValue } from 'vue'
import type { MaybeRefOrGetter, Ref } from 'vue'
import http from '@/services/http'

/**
 * Les paliers tarifaires d'un produit : un prix par liste de prix et par
 * quantite minimale — « tarif revendeur a partir de 10 ».
 *
 * L'etat du petit formulaire d'ajout vivait a un bout de Products.vue et les
 * fonctions qui s'en servent a l'autre, cent-trente lignes plus loin.
 */
export interface ProductPriceTiersOptions {
  /** Le produit en cours d'edition, ou null en creation. */
  product: MaybeRefOrGetter<any>
  /** Taux de TVA courant du formulaire, pour afficher le TTC en regard du HT. */
  taxRate: MaybeRefOrGetter<number | string>
  /** Remonte un message a l'utilisateur. */
  notify: (message: string, level: 'success' | 'error') => void
}

export function useProductPriceTiers(options: ProductPriceTiersOptions) {
  const items: Ref<any[]> = ref([])

  const adding = ref(false)
  const saving = ref(false)
  const deletingId: Ref<number | null> = ref(null)

  const newTier = reactive({
    price_list_id: null as number | null,
    min_qty: 1,
    price_ht: 0,
  })

  const productId = (): number | null => toValue(options.product)?.id ?? null

  const newTierTtc = computed(() => {
    const ht = Number(newTier.price_ht) || 0
    const rate = Number(toValue(options.taxRate)) || 0
    return (ht * (1 + rate / 100)).toFixed(2)
  })

  const canAdd = computed(
    () => !!newTier.price_list_id && Number(newTier.min_qty) >= 1 && Number(newTier.price_ht) > 0,
  )

  /** Un palier existe deja pour ce couple liste / quantite minimale. */
  function isListAlreadyUsed(listId: number, minQty: number): boolean {
    return items.value.some(
      (i: any) => Number(i.price_list_id) === listId && Number(i.min_qty) === Number(minQty),
    )
  }

  async function reload(): Promise<void> {
    const id = productId()
    if (!id) return
    try {
      const { data } = await http.get(`/products/${id}/price-lists`)
      items.value = Array.isArray(data) ? data : data.data ?? []
    } catch (e) {
      console.error('Failed to reload price tiers', e)
    }
  }

  async function add(): Promise<void> {
    const id = productId()
    if (!canAdd.value || !id) return
    saving.value = true
    try {
      await http.post(`/price-lists/${newTier.price_list_id}/items`, {
        items: [
          {
            product_id: id,
            price_ht: Number(newTier.price_ht),
            min_qty: Number(newTier.min_qty) || 1,
          },
        ],
      })
      await reload()
      // Reset form
      newTier.price_list_id = null
      newTier.min_qty = 1
      newTier.price_ht = 0
      adding.value = false
      options.notify('Tarif ajouté', 'success')
    } catch (e: any) {
      const msg = e?.response?.data?.message ?? "Échec de l'ajout du tarif"
      options.notify(msg, 'error')
    } finally {
      saving.value = false
    }
  }

  async function remove(item: any): Promise<void> {
    if (!confirm('Supprimer ce tarif ?')) return
    deletingId.value = item.id
    try {
      await http.delete(`/price-lists/${item.price_list_id}/items/${item.id}`)
      items.value = items.value.filter((i: any) => i.id !== item.id)
      options.notify('Tarif supprimé', 'success')
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
    newTier,
    newTierTtc,
    canAdd,
    isListAlreadyUsed,
    reload,
    add,
    remove,
  }
}
