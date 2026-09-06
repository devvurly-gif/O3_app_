import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/authStore'

/**
 * Le choix des colonnes du catalogue produits.
 *
 * Trois choses tenaient ensemble dans Products.vue sans jamais etre nommees :
 * le catalogue des colonnes possibles, le filtrage par droits et par modules
 * du tenant, et la selection de l'utilisateur — propre a chacun, conservee
 * d'une session a l'autre dans ce navigateur.
 *
 * Rien ici ne depend du reste de l'ecran, ce qui rend l'ensemble testable a
 * part : la page de 2198 lignes ne le permettait pas.
 */

// Un attribut du modele Product = une colonne. `permission` reserve les
// champs sensibles (prix d'achat, cout) aux roles autorises ; `module`
// masque les colonnes des fonctionnalites non activees chez le tenant.
// `menuLabel` sert au menu « Colonnes » quand l'en-tete est trop court.
// Alias de type (et non interface) : BaseTable attend une signature
// d'index, que seuls les alias satisfont implicitement.
export type ColumnDef = {
  key: string
  label: string
  menuLabel?: string
  permission?: string
  module?: string
  hideOnMobile?: boolean
}

/** Colonnes affichees tant que l'utilisateur n'a rien choisi. */
export const DEFAULT_VISIBLE_COLUMNS = [
  'primary_image',
  'p_code',
  'p_title',
  'category',
  'brand',
  'p_salePrice',
  'p_status',
  'total_stock',
]

export function useProductColumns() {
  const { t } = useI18n()
  const auth = useAuthStore()

  const catalogColumns = computed<ColumnDef[]>(() => [
    { key: 'id', label: 'ID', hideOnMobile: true },
    { key: 'primary_image', label: '#', menuLabel: t('products.image') },
    { key: 'p_code', label: t('common.code') },
    { key: 'p_title', label: t('common.name') },
    { key: 'p_sku', label: 'SKU' },
    { key: 'p_ean13', label: t('products.ean'), hideOnMobile: true },
    { key: 'p_imei', label: 'IMEI', module: 'imei', hideOnMobile: true },
    { key: 'category', label: t('products.category') },
    { key: 'brand', label: t('products.brand') },
    { key: 'p_purchasePrice', label: t('products.purchasePrice'), permission: 'products.view_cost' },
    { key: 'p_salePrice', label: t('products.salePrice') },
    { key: 'p_cost', label: t('products.cost'), permission: 'products.view_cost' },
    { key: 'p_taxRate', label: t('products.taxRate') },
    { key: 'p_unit', label: t('products.unit') },
    { key: 'total_stock', label: 'Stock' },
    { key: 'p_status', label: t('common.status') },
    { key: 'is_ecom', label: t('products.columnEcom'), module: 'ecom' },
    { key: 'p_slug', label: t('products.slug'), module: 'ecom', hideOnMobile: true },
    { key: 'p_description', label: t('products.description'), hideOnMobile: true },
    { key: 'p_long_description', label: t('products.longDescription'), hideOnMobile: true },
    { key: 'p_notes', label: t('products.notes'), hideOnMobile: true },
    { key: 'created_at', label: t('common.createdAt'), hideOnMobile: true },
    { key: 'updated_at', label: t('common.updatedAt'), hideOnMobile: true },
  ])

  // Colonnes que cet utilisateur a le droit de voir. Le serveur retire de
  // toute facon p_purchasePrice / p_cost du payload sans products.view_cost —
  // ce filtre evite d'afficher (et de proposer) des colonnes vides.
  const allColumns = computed(() =>
    catalogColumns.value.filter((col) => {
      if (col.permission && !auth.hasPermission(col.permission)) return false
      if (col.module && !auth.hasModule(col.module)) return false
      return true
    }),
  )

  // ── Colonnes affichees / masquees ────────────────────────────────────────
  // Le choix est propre a chaque utilisateur (cle prefixee par son id) et
  // conserve d'une session a l'autre sur ce navigateur.
  const columnsMenuOpen = ref(false)
  const columnsStorageKey = computed(() => `products.visibleColumns.v1:${auth.user?.id ?? 'anon'}`)
  const visibleColumns = ref<string[]>([...DEFAULT_VISIBLE_COLUMNS])

  function loadVisibleColumns() {
    try {
      const raw = localStorage.getItem(columnsStorageKey.value)
      const parsed = raw ? JSON.parse(raw) : null
      const keys = Array.isArray(parsed) ? parsed.filter((k: unknown) => typeof k === 'string') : []
      visibleColumns.value = keys.length ? keys : [...DEFAULT_VISIBLE_COLUMNS]
    } catch {
      visibleColumns.value = [...DEFAULT_VISIBLE_COLUMNS]
    }
  }

  // Recharge a la connexion / au changement d'utilisateur.
  watch(() => auth.user?.id, loadVisibleColumns, { immediate: true })

  watch(
    visibleColumns,
    (val) => {
      try {
        localStorage.setItem(columnsStorageKey.value, JSON.stringify(val))
      } catch {
        /* stockage indisponible (mode prive / quota) — on ignore */
      }
    },
    { deep: true },
  )

  // L'ordre d'affichage suit le catalogue, pas l'ordre des clics.
  const columns = computed(() => allColumns.value.filter((c) => visibleColumns.value.includes(c.key)))

  function isColumnVisible(key: string) {
    return visibleColumns.value.includes(key)
  }

  function toggleColumn(key: string) {
    if (isColumnVisible(key)) {
      // On garde toujours au moins une colonne visible.
      if (columns.value.length <= 1) return
      visibleColumns.value = visibleColumns.value.filter((k) => k !== key)
    } else {
      visibleColumns.value = [...visibleColumns.value, key]
    }
  }

  function showAllColumns() {
    visibleColumns.value = allColumns.value.map((c) => c.key)
  }

  function resetColumns() {
    visibleColumns.value = [...DEFAULT_VISIBLE_COLUMNS]
  }

  return {
    allColumns,
    columns,
    columnsMenuOpen,
    visibleColumns,
    isColumnVisible,
    toggleColumn,
    showAllColumns,
    resetColumns,
  }
}
