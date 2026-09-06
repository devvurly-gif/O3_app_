import { ref } from 'vue'
import type { Ref } from 'vue'
import http from '@/services/http'
import { useVariantOptionsStore } from '@/stores/useVariantOptionsStore'

/**
 * Les declinaisons d'un produit — couleur, taille, et ce que le tenant a
 * defini d'autre dans les options de variantes.
 *
 * La liste n'est envoyee au serveur qu'a l'enregistrement du produit, et
 * seulement si elle a bouge : `dirty` evite un POST de synchronisation a
 * chaque sauvegarde d'un produit dont personne n'a touche les variantes.
 *
 * Tout est inerte quand le module « variantes » n'est pas actif chez le
 * tenant : c'est l'ecran qui le dit, via `enabled`.
 */
export function useProductVariants(enabled: () => boolean) {
  const variantStore = useVariantOptionsStore()

  const variants: Ref<any[]> = ref([])
  const dirty = ref(false)

  async function load(productId: number | null | undefined): Promise<void> {
    if (!enabled() || !productId) return
    try {
      const { data } = await http.get('/products/' + productId + '/variants')
      variants.value = data
    } catch {
      variants.value = []
    }
  }

  async function save(productId: number | null | undefined): Promise<void> {
    if (!enabled() || !dirty.value) return
    await http.post('/products/' + productId + '/variants/sync', { variants: variants.value })
    dirty.value = false
  }

  function addRow(): void {
    variants.value.push({ label: '', sku: '', price: null, is_active: true })
    dirty.value = true
  }

  function remove(idx: number): void {
    variants.value.splice(idx, 1)
    dirty.value = true
  }

  /**
   * Compose toutes les combinaisons des options du tenant — « Blanc / 1/2" »,
   * « Chrome / 3/4" » — et n'ajoute que celles qui manquent, pour ne pas
   * ecraser un SKU ou un prix deja saisi.
   */
  function applyGenerated(): void {
    if (!variantStore.items.length) return
    const combos = variantStore.items.reduce((acc: string[], type: any) => {
      if (!type.values || !type.values.length) return acc
      if (!acc.length) return type.values.map((v: any) => v.key)
      return acc.flatMap((a: string) => type.values.map((v: any) => a + ' / ' + v.key))
    }, [])
    const existing = new Set(variants.value.map((v: any) => v.label))
    const newOnes = combos
      .filter((c: string) => !existing.has(c))
      .map((label: string) => ({ label, sku: '', price: null, is_active: true }))
    variants.value = [...variants.value, ...newOnes]
    dirty.value = true
  }

  return { variants, dirty, load, save, addRow, remove, applyGenerated }
}
