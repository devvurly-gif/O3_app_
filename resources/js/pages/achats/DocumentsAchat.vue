<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDocumentAchatStore } from '@/stores/achats/useDocumentAchatStore'
import { useToastStore } from '@/stores/toastStore'
import { useAuthStore } from '@/stores/authStore'
import { useExcelExport } from '@/composables/useExcelExport'
import http from '@/services/http'
import BaseTable from '@/components/BaseTable.vue'
import BaseSkeleton from '@/components/BaseSkeleton.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseModal from '@/components/BaseModal.vue'
import { useFormat } from '@/composables/useFormat'

const router = useRouter()
const store = useDocumentAchatStore()
const toast = useToastStore()
const auth = useAuthStore()
const { date: fmtDate, fmt } = useFormat()

// Suppression définitive (admin) : le document, ses règlements et ses mouvements de stock disparaissent.
interface DeletableDoc {
  id: number
  reference: string
  document_type: string
  issued_at: string
  third_partner?: { tp_title: string } | null
}
const docToDelete = ref<DeletableDoc | null>(null)
const deleting = ref(false)
const deleteError = ref('')
const showDeleteModal = computed({
  get: () => docToDelete.value !== null,
  set: (v: boolean) => {
    if (!v) docToDelete.value = null
  },
})

function askDelete(doc: Record<string, unknown>) {
  deleteError.value = ''
  docToDelete.value = doc as unknown as DeletableDoc
}

async function confirmDelete() {
  if (!docToDelete.value) return
  deleting.value = true
  deleteError.value = ''
  try {
    await http.delete(`/documents/${docToDelete.value.id}/definitif`)
    toast.success(`Document ${docToDelete.value.reference} supprimé définitivement.`)
    docToDelete.value = null
    selectedIds.value = []
    loadPage(store.meta.current_page || 1)
  } catch (e) {
    deleteError.value =
      (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'La suppression a échoué.'
  } finally {
    deleting.value = false
  }
}

const search = ref('')
const typeFilter = ref('')
const supplierFilter = ref('')
const suppliers = ref<Array<{ id: number; tp_title: string }>>([])
let searchTimer: ReturnType<typeof setTimeout> | null = null

const purchaseTypes = [
  'PurchaseOrder',
  'ReceiptNotePurchase',
  'InvoicePurchase',
  'CreditNotePurchase',
  'ReturnPurchase',
]

const { exporting, exportExcel, canExport } = useExcelExport()

function onExport() {
  exportExcel('/export/documents', buildFilters())
}

function buildFilters(): Record<string, string> {
  const f: Record<string, string> = {}
  if (search.value.trim()) f.search = search.value.trim()
  if (typeFilter.value) f.document_type = typeFilter.value
  if (supplierFilter.value) f.thirdPartner_id = supplierFilter.value
  return f
}

function loadPage(page = 1) {
  store.fetchAll(page, buildFilters())
}

function onPageChange(page: number) {
  loadPage(page)
}

watch([search, typeFilter, supplierFilter], () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadPage(1), 350)
})

onMounted(() => {
  loadPage()
  http
    .get('/third-partners', { params: { role: 'supplier', per_page: 200 } })
    .then(({ data }) => {
      suppliers.value = data.data ?? data
    })
    .catch(() => {
      suppliers.value = []
    })
})

// ── Facturation groupee ───────────────────────────────────────────────────

/**
 * Un bon facturable : recu, confirme, et pas encore facture.
 *
 * Le statut 'converted' est precisement ce que pose la facturation groupee ;
 * il sort donc de la selection, ce qui empeche de facturer deux fois le meme
 * bon depuis deux onglets.
 */
function isBillable(row: Record<string, unknown>): boolean {
  return row.document_type === 'ReceiptNotePurchase' && ['confirmed', 'received'].includes(row.status as string)
}

const selectedIds = ref<number[]>([])

const billableRows = computed(() => store.documents.filter(isBillable))

const selectedRows = computed(() =>
  store.documents.filter((d: Record<string, unknown>) => selectedIds.value.includes(d.id as number)),
)

const selectedTotal = computed(() =>
  selectedRows.value.reduce(
    (sum: number, d: Record<string, unknown>) => sum + Number((d.footer as { total_ttc?: number })?.total_ttc ?? 0),
    0,
  ),
)

/**
 * Regrouper des bons de deux fournisseurs fabriquerait une dette attribuee au
 * mauvais tiers. Le serveur le refuse ; l'ecran le dit avant d'y aller.
 */
const selectedSuppliers = computed(() => [
  ...new Set(selectedRows.value.map((d: Record<string, unknown>) => (d.third_partner as { id?: number })?.id)),
])

const mixedSuppliers = computed(() => selectedSuppliers.value.length > 1)

const allBillableSelected = computed(
  () => billableRows.value.length > 0 && billableRows.value.every((d) => selectedIds.value.includes(d.id as number)),
)

function toggleRow(id: number): void {
  selectedIds.value = selectedIds.value.includes(id)
    ? selectedIds.value.filter((x) => x !== id)
    : [...selectedIds.value, id]
}

function toggleAll(): void {
  selectedIds.value = allBillableSelected.value
    ? []
    : billableRows.value.map((d: Record<string, unknown>) => d.id as number)
}

// Une selection ne survit pas a un changement de page ou de filtre : les
// lignes cochees ne seraient plus a l'ecran, et on facturerait a l'aveugle.
watch([search, typeFilter, supplierFilter], () => {
  selectedIds.value = []
})

const showGroupModal = ref(false)
const grouping = ref(false)
const groupError = ref('')
const groupForm = ref({ issued_at: '', supplier_ref: '' })

function openGroupModal(): void {
  groupError.value = ''
  // Par defaut la facture porte la date du dernier bon : c'est la date a
  // laquelle le fournisseur arrete son recapitulatif.
  const dates = selectedRows.value
    .map((d: Record<string, unknown>) => String(d.issued_at ?? '').slice(0, 10))
    .filter(Boolean)
    .sort()
  groupForm.value = { issued_at: dates[dates.length - 1] ?? '', supplier_ref: '' }
  showGroupModal.value = true
}

async function submitGroup(): Promise<void> {
  groupError.value = ''
  grouping.value = true

  const result = await store.regrouperBons({
    receipt_ids: selectedIds.value,
    issued_at: groupForm.value.issued_at || null,
    supplier_ref: groupForm.value.supplier_ref || null,
  })

  grouping.value = false

  if (!result.success) {
    groupError.value = result.message ?? 'Erreur lors du regroupement.'
    return
  }

  showGroupModal.value = false
  selectedIds.value = []
  toast.success(result.message ?? 'Facture créée.')

  if (result.facture) {
    router.push(`/achats/documents/${result.facture.id}`)
  }
}

const columns = [
  { key: 'select', label: '' },
  { key: 'reference', label: 'Référence' },
  { key: 'document_type', label: 'Type', hideOnMobile: true },
  { key: 'status', label: 'Statut', hideOnMobile: true },
  { key: 'third_partner', label: 'Fournisseur' },
  { key: 'issued_at', label: 'Date' },
]

function mobileRowClass(row: Record<string, unknown>): string {
  const isPaid = row.status === 'paid'
  return isPaid ? 'border-gray-200 dark:border-gray-700' : 'border-red-400 dark:border-red-600 border-l-4'
}

const typeLabels = {
  PurchaseOrder: 'Bon de Commande',
  ReceiptNotePurchase: 'Bon de Réception',
  InvoicePurchase: 'Facture Achat',
  CreditNotePurchase: 'Avoir Fournisseur',
  ReturnPurchase: 'Bon de Retour',
}

const statusLabels = {
  draft: 'Brouillon',
  confirmed: 'Confirmé',
  converted: 'Converti',
  received: 'Reçu',
  pending: 'En attente',
  paid: 'Payé',
  cancelled: 'Annulé',
}

function viewDocument(doc: Record<string, unknown>) {
  router.push(`/achats/documents/${doc.id}`)
}
</script>

<template>
  <div class="max-w-9xl mx-auto py-4 sm:py-6 px-3 sm:px-4">
    <div class="flex items-center justify-between gap-3 mb-6">
      <h1 class="text-lg sm:text-2xl font-bold text-gray-900 dark:text-white">Documents d'Achat</h1>
      <div class="flex items-center gap-3">
        <button
          v-if="canExport"
          class="flex items-center gap-2 px-3 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition"
          :disabled="exporting"
          @click="onExport"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
          </svg>
          <span class="hidden sm:inline">{{ exporting ? 'Export...' : 'Export Excel' }}</span>
        </button>
        <router-link
          to="/achats/ocr-import"
          class="inline-flex items-center gap-2 px-3 py-2 border border-teal-600 text-teal-600 dark:text-teal-400 dark:border-teal-400 text-sm font-medium rounded-lg hover:bg-teal-50 dark:hover:bg-teal-900/20 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
          </svg>
          <span class="hidden sm:inline">Import OCR</span>
        </router-link>
        <router-link
          to="/achats/documents/create"
          class="inline-flex items-center gap-2 px-3 sm:px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          <span class="hidden sm:inline">Nouveau</span>
        </router-link>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-3 mb-6">
      <input
        v-model="search"
        aria-label="Rechercher un achat"
        type="text"
        placeholder="Rechercher référence..."
        class="px-3.5 py-2 text-input rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-gray-200 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-orange-500 w-full sm:w-64"
      />
      <select
        v-model="typeFilter"
        aria-label="Filtrer par type de document"
        class="px-3.5 py-2 text-input rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-orange-500"
      >
        <option value="">Tous types</option>
        <option v-for="t in purchaseTypes" :key="t" :value="t">{{ typeLabels[t] }}</option>
      </select>
      <select
        v-model="supplierFilter"
        aria-label="Filtrer par fournisseur"
        class="px-3.5 py-2 text-input rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-orange-500"
      >
        <option value="">Tous fournisseurs</option>
        <option v-for="f in suppliers" :key="f.id" :value="String(f.id)">{{ f.tp_title }}</option>
      </select>
      <button
        v-if="billableRows.length"
        class="px-3.5 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition"
        @click="toggleAll"
      >
        {{ allBillableSelected ? 'Tout décocher' : 'Cocher tous les bons' }}
      </button>
    </div>

    <!-- Barre d'action : n'apparaît qu'une fois des bons cochés -->
    <div
      v-if="selectedIds.length"
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 px-4 py-3 rounded-lg border border-teal-300 dark:border-teal-700 bg-teal-50 dark:bg-teal-900/20"
    >
      <div class="text-sm text-gray-700 dark:text-gray-200">
        <strong>{{ selectedIds.length }}</strong> bon(s) de réception sélectionné(s) —
        <strong class="font-mono">{{ fmt(selectedTotal) }} DH</strong>
        <span v-if="mixedSuppliers" class="block sm:inline text-red-600 dark:text-red-400 sm:ml-2">
          Fournisseurs différents : un seul fournisseur par facture.
        </span>
      </div>
      <div class="flex items-center gap-2">
        <button
          class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-white/60 dark:hover:bg-gray-800 rounded-lg transition"
          @click="selectedIds = []"
        >
          Annuler
        </button>
        <button
          class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition disabled:opacity-50"
          :disabled="mixedSuppliers"
          @click="openGroupModal"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
          </svg>
          Facturer en une seule facture
        </button>
      </div>
    </div>

    <BaseSkeleton v-if="store.loading && !store.documents.length" type="table" :rows="8" />

    <BaseTable v-else :columns="columns" :rows="store.documents" :mobile-row-class="mobileRowClass">
      <template #cell-select="{ row }">
        <input
          v-if="isBillable(row)"
          type="checkbox"
          class="rounded border-gray-300 text-teal-600 focus:ring-teal-500"
          :checked="selectedIds.includes(row.id)"
          :aria-label="'Sélectionner le bon ' + row.reference"
          @change="toggleRow(row.id)"
        />
        <span v-else class="text-gray-300 dark:text-gray-600">—</span>
      </template>

      <template #cell-document_type="{ row }">
        <span class="text-sm">{{ typeLabels[row.document_type] ?? row.document_type }}</span>
      </template>

      <template #cell-status="{ row }">
        <span class="text-sm">{{ statusLabels[row.status] ?? row.status }}</span>
      </template>

      <template #cell-third_partner="{ row }">
        <span class="text-sm">{{ row.third_partner?.tp_title ?? '—' }}</span>
      </template>

      <template #cell-issued_at="{ row }">
        <span class="text-sm">{{ fmtDate(row.issued_at) }}</span>
      </template>

      <template #actions="{ row }">
        <div class="inline-flex items-center gap-3">
          <button
            class="text-orange-700 dark:text-orange-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm font-medium"
            @click="viewDocument(row)"
          >
            Voir
          </button>
          <button
            v-if="auth.isAdmin"
            type="button"
            class="text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition"
            :title="'Supprimer définitivement ' + row.reference"
            :aria-label="'Supprimer définitivement ' + row.reference"
            @click="askDelete(row)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
              />
            </svg>
          </button>
        </div>
      </template>
    </BaseTable>

    <BaseModal v-model="showDeleteModal" title="Supprimer définitivement ce document ?" size="md">
      <div v-if="docToDelete" class="space-y-3 text-sm text-gray-700 dark:text-gray-200">
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3">
          <div class="font-semibold font-mono">{{ docToDelete.reference }}</div>
          <div class="text-gray-600 dark:text-gray-300">
            {{ typeLabels[docToDelete.document_type] ?? docToDelete.document_type }} —
            {{ docToDelete.third_partner?.tp_title ?? '—' }} — {{ fmtDate(docToDelete.issued_at) }}
          </div>
        </div>
        <p>
          Le document, ses lignes, ses règlements et ses mouvements de stock sont effacés, sans corbeille : il ne pourra
          pas être récupéré. Le stock déjà reçu est retiré.
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          Refusé si un document en dérive (facture, retour) ou si la marchandise reçue a déjà été consommée. L'opération
          reste tracée dans le journal d'activité.
        </p>
        <p v-if="deleteError" class="text-red-600 dark:text-red-400 font-medium">{{ deleteError }}</p>
      </div>

      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
          @click="showDeleteModal = false"
        >
          Annuler
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg transition disabled:opacity-60"
          :disabled="deleting"
          @click="confirmDelete"
        >
          {{ deleting ? 'Suppression…' : 'Supprimer définitivement' }}
        </button>
      </template>
    </BaseModal>

    <BaseModal v-model="showGroupModal" title="Facturer les bons sélectionnés" size="md">
      <div class="space-y-4">
        <div class="rounded-lg bg-gray-50 dark:bg-gray-900 px-4 py-3 text-sm">
          <div class="flex justify-between py-1">
            <span class="text-gray-500 dark:text-gray-400">Fournisseur</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">
              {{ selectedRows[0]?.third_partner?.tp_title ?? '—' }}
            </span>
          </div>
          <div class="flex justify-between py-1">
            <span class="text-gray-500 dark:text-gray-400">Bons</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">{{ selectedIds.length }}</span>
          </div>
          <div class="flex justify-between py-1 border-t border-gray-200 dark:border-gray-700 mt-1 pt-2">
            <span class="text-gray-700 dark:text-gray-300 font-semibold">Total facture</span>
            <span class="font-mono font-bold text-gray-900 dark:text-white">{{ fmt(selectedTotal) }} DH</span>
          </div>
        </div>

        <div>
          <label for="group-date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            Date de la facture
          </label>
          <input
            id="group-date"
            v-model="groupForm.issued_at"
            type="date"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
          />
          <p class="text-xs text-gray-400 mt-1">Par défaut, la date du dernier bon sélectionné.</p>
        </div>

        <div>
          <label for="group-ref" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            N° de facture du fournisseur
          </label>
          <input
            id="group-ref"
            v-model="groupForm.supplier_ref"
            type="text"
            placeholder="Facultatif"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
          />
          <p class="text-xs text-gray-400 mt-1">Conservé dans les notes, pour le rapprochement.</p>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
          Les bons restent consultables et passent en « Converti ». Le stock n'est pas remouvementé : la marchandise est
          entrée à la réception.
        </p>

        <p v-if="groupError" class="text-sm text-red-600 dark:text-red-400">{{ groupError }}</p>
      </div>

      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
          @click="showGroupModal = false"
        >
          Annuler
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-teal-600 hover:bg-teal-700 text-white rounded-lg transition disabled:opacity-60"
          :disabled="grouping"
          @click="submitGroup"
        >
          {{ grouping ? 'Création…' : 'Créer la facture' }}
        </button>
      </template>
    </BaseModal>

    <BasePagination
      v-if="store.meta.last_page > 1"
      :current-page="store.meta.current_page"
      :last-page="store.meta.last_page"
      :total="store.meta.total"
      :per-page="store.meta.per_page"
      class="mt-4"
      @change="onPageChange"
    />
  </div>
</template>
