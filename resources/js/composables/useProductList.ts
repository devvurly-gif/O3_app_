import { computed, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useProductStore } from '@/stores/product'

/**
 * Le catalogue : recherche, cinq filtres, pagination, et les quatre cartes de
 * tete.
 *
 * Pendant du `useThirdPartnerList` des fiches tiers, avec ses propres filtres
 * — stock, boutique, promotion — que les tiers n'ont pas.
 */

/** Delai avant qu'une frappe ne parte au serveur. */
const SEARCH_DEBOUNCE_MS = 350

export function useProductList() {
  const store = useProductStore()
  const { items } = storeToRefs(store)

  const search = ref('')
  const statusFilter = ref('')
  const stockFilter = ref('')
  const ecomFilter = ref('')
  const promoFilter = ref('')

  let searchTimer: ReturnType<typeof setTimeout> | null = null

  function buildParams(): Record<string, string> {
    const p: Record<string, string> = {}
    if (search.value.trim()) p.search = search.value.trim()
    if (statusFilter.value !== '') p.status = statusFilter.value
    if (stockFilter.value !== '') p.in_stock = stockFilter.value
    if (ecomFilter.value !== '') p.is_ecom = ecomFilter.value
    if (promoFilter.value !== '') p.on_promo = promoFilter.value
    return p
  }

  function loadPage(page = 1): void {
    const p = buildParams()
    store.params.page = page
    // Assign every filter explicitly (not just the ones present in `p`) so a
    // cleared field actually clears the stored param instead of leaving a
    // stale value behind — usePaginatedApi drops null/'' before the request.
    store.params.search = p.search ?? null
    store.params.status = p.status ?? null
    store.params.in_stock = p.in_stock ?? null
    store.params.is_ecom = p.is_ecom ?? null
    store.params.on_promo = p.on_promo ?? null
    store.fetchPage(page)
  }

  function onPageChange(page: number): void {
    loadPage(page)
  }

  watch([search, statusFilter, stockFilter, ecomFilter, promoFilter], () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => loadPage(1), SEARCH_DEBOUNCE_MS)
  })

  // ── Cartes de tete ────────────────────────────────────────────────────────
  // Faute d'un endpoint d'agregat, elles ne portent que sur la page chargee —
  // sauf le total, que la pagination connait vraiment.
  const statTotal = computed(() => store.meta?.total ?? items.value.length)

  const statOutOfStock = computed(
    () => items.value.filter((p: any) => Number(p.total_stock ?? 0) <= 0).length,
  )

  const statStockValue = computed(() => {
    const total = items.value.reduce(
      (sum: number, p: any) => sum + Number(p.p_salePrice ?? 0) * Math.max(Number(p.total_stock ?? 0), 0),
      0,
    )
    return `${new Intl.NumberFormat('fr-MA').format(Math.round(total))} MAD`
  })

  const statActivePercent = computed(() => {
    if (!items.value.length) return 0
    const active = items.value.filter((p: any) => p.p_status).length
    return Math.round((active / items.value.length) * 100)
  })

  return {
    store,
    items,
    search,
    statusFilter,
    stockFilter,
    ecomFilter,
    promoFilter,
    buildParams,
    loadPage,
    onPageChange,
    statTotal,
    statOutOfStock,
    statStockValue,
    statActivePercent,
  }
}
