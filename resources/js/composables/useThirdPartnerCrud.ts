import { computed, reactive, ref, watch } from 'vue'
import type { Ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useThirdPartnerStore } from '@/stores/thirdPartner'

/**
 * Le va-et-vient commun aux ecrans Clients et Fournisseurs.
 *
 * Les deux listent le meme modele derriere le meme endpoint, filtre par le
 * seul `role`. Ils portaient pourtant chacun leur recherche debattue, leur
 * pagination, leurs cartes de tete et leur formulaire — a la chaine de
 * caracteres « customer » / « supplier » pres.
 */

export type PartnerRole = 'customer' | 'supplier'

/** Le credit encore ouvert a ce tiers. */
export function creditAvailable(row: any): number {
  return (row.seuil_credit ?? 0) - (row.encours_actuel ?? 0)
}

/** Delai avant qu'une frappe ne parte au serveur. */
const SEARCH_DEBOUNCE_MS = 350

/**
 * Liste : recherche, filtre d'etat, pagination et cartes de tete.
 */
export function useThirdPartnerList(role: PartnerRole) {
  const store = useThirdPartnerStore()
  const { items } = storeToRefs(store)

  const search = ref('')
  const statusFilter = ref('')

  let searchTimer: ReturnType<typeof setTimeout> | null = null

  function buildParams(): Record<string, string> {
    const params: Record<string, string> = { role }
    if (search.value.trim()) params.search = search.value.trim()
    if (statusFilter.value !== '') params.status = statusFilter.value
    return params
  }

  function loadPage(page = 1): void {
    Object.assign(store.params, buildParams())
    store.fetchPage(page)
  }

  function onPageChange(page: number): void {
    loadPage(page)
  }

  watch([search, statusFilter], () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => loadPage(1), SEARCH_DEBOUNCE_MS)
  })

  // Cartes de tete : faute d'un endpoint d'agregat, elles ne portent que sur
  // la page chargee. C'est un ordre de grandeur, pas un total.
  const statActive = computed(() => items.value.filter((r: any) => r.tp_status).length)

  const statOverLimit = computed(
    () => items.value.filter((r: any) => (r.seuil_credit ?? 0) > 0 && creditAvailable(r) < 0).length,
  )

  const statEncours = computed(() =>
    items.value.reduce((sum: number, r: any) => sum + Number(r.encours_actuel ?? 0), 0),
  )

  return {
    store,
    items,
    search,
    statusFilter,
    buildParams,
    loadPage,
    onPageChange,
    creditAvailable,
    statActive,
    statOverLimit,
    statEncours,
  }
}

export interface ThirdPartnerFormOptions<T> {
  /** Prefixe i18n des messages de confirmation : « customers » ou « suppliers ». */
  scope: string
  /** Le formulaire vide — c'est lui qui declare les champs de l'ecran. */
  blank: () => T
  /** Remonte un message a l'utilisateur. */
  notify: (message: string, level: 'success' | 'error') => void
  /**
   * Ouverture de la modale, en creation (`null`) ou en edition. L'ecran y
   * remet ses onglets a zero et va chercher le detail de la fiche.
   */
  onOpen?: (row: any | null) => void
}

/**
 * Formulaire : ouverture, enregistrement, suppression.
 *
 * `blank()` fait office de schema. Une fiche client declare trois champs de
 * plus qu'une fiche fournisseur ; l'hydratation les suit sans rien savoir
 * d'eux.
 */
export function useThirdPartnerForm<T extends Record<string, any>>(options: ThirdPartnerFormOptions<T>) {
  const { t } = useI18n()
  const store = useThirdPartnerStore()

  const showModal = ref(false)
  const showDelete = ref(false)
  const saving = ref(false)
  const deleting = ref(false)
  const editTarget: Ref<any> = ref(null)
  const deleteTarget: Ref<any> = ref(null)

  const form = reactive(options.blank()) as T

  /** Reprend la ligne champ par champ, en retombant sur le formulaire vide. */
  function hydrate(row: any): void {
    const blank = options.blank()
    for (const key of Object.keys(blank) as (keyof T)[]) {
      form[key] = row[key] ?? blank[key]
    }
  }

  function openCreate(): void {
    editTarget.value = null
    Object.assign(form, options.blank())
    showModal.value = true
    options.onOpen?.(null)
  }

  function openEdit(row: any): void {
    editTarget.value = row
    hydrate(row)
    showModal.value = true
    options.onOpen?.(row)
  }

  async function submit(): Promise<void> {
    if (!form.tp_title.trim()) return
    saving.value = true
    try {
      // L'encours n'est jamais pilote depuis l'ecran : il se recalcule a
      // partir des documents. On l'ecarte de ce qui part au serveur.
      const { encours_actuel: _ignored, ...payload } = form as Record<string, any>
      if (editTarget.value) {
        await store.update(editTarget.value.id, payload)
        options.notify(t(`${options.scope}.updated`), 'success')
      } else {
        await store.create(payload)
        options.notify(t(`${options.scope}.created`), 'success')
      }
      showModal.value = false
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      options.notify(e.response?.data?.message ?? t('common.failedSave'), 'error')
    } finally {
      saving.value = false
    }
  }

  function confirmDelete(row: any): void {
    deleteTarget.value = row
    showDelete.value = true
  }

  async function doDelete(): Promise<void> {
    deleting.value = true
    try {
      await store.remove(deleteTarget.value.id)
      options.notify(t(`${options.scope}.deleted`), 'success')
      showDelete.value = false
    } catch {
      options.notify(t('common.failedDelete'), 'error')
    } finally {
      deleting.value = false
    }
  }

  return {
    form,
    showModal,
    showDelete,
    saving,
    deleting,
    editTarget,
    deleteTarget,
    openCreate,
    openEdit,
    submit,
    confirmDelete,
    doDelete,
  }
}
