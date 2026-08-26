<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDocumentVenteStore } from '@/stores/ventes/useDocumentVenteStore'
import { useToastStore } from '@/stores/toastStore'
import { useExcelExport } from '@/composables/useExcelExport'
import http from '@/services/http'
import BaseTable from '@/components/BaseTable.vue'
import BaseSkeleton from '@/components/BaseSkeleton.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseModal from '@/components/BaseModal.vue'
import { useFormat } from '@/composables/useFormat'

const router = useRouter()
const store = useDocumentVenteStore()
const toast = useToastStore()
const { date: fmtDate, fmt } = useFormat()

const search = ref('')
const typeFilter = ref('')
const customerFilter = ref('')
const customers = ref<Array<{ id: number; tp_title: string }>>([])
let searchTimer: ReturnType<typeof setTimeout> | null = null

const saleTypes = [
  'QuoteSale',
  'CustomerOrder',
  'DeliveryNote',
  'InvoiceSale',
  'TicketSale',
  'CreditNoteSale',
  'ReturnSale',
]

const { exporting, exportExcel, canExport } = useExcelExport()

function onExport() {
  exportExcel('/export/documents', buildFilters())
}

function buildFilters(): Record<string, string> {
  const f: Record<string, string> = {}
  if (search.value.trim()) f.search = search.value.trim()
  if (typeFilter.value) f.document_type = typeFilter.value
  if (customerFilter.value) f.thirdPartner_id = customerFilter.value
  return f
}

function loadPage(page = 1) {
  store.fetchAll(page, buildFilters())
}

function onPageChange(page: number) {
  loadPage(page)
}

watch([search, typeFilter, customerFilter], () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadPage(1), 350)
})

onMounted(() => {
  loadPage()
  http
    .get('/third-partners', { params: { role: 'customer', per_page: 200 } })
    .then(({ data }) => {
      customers.value = data.data ?? data
    })
    .catch(() => {
      customers.value = []
    })
})

// ── Facturation groupee ───────────────────────────────────────────────────

/**
 * Un bon facturable : livre, confirme ou deja regle, et pas encore facture.
 *
 * 'converted' et 'delivered' sont poses par la facturation : ils sortent donc
 * de la selection, ce qui empeche de facturer deux fois le meme bon.
 */
function isBillable(row: Record<string, unknown>): boolean {
  return row.document_type === 'DeliveryNote' && ['confirmed', 'partial', 'paid'].includes(row.status as string)
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
 * Regrouper les bons de deux clients fabriquerait une creance attribuee au
 * mauvais tiers. Le serveur le refuse ; l'ecran le dit avant d'y aller.
 */
const selectedCustomers = computed(() => [
  ...new Set(selectedRows.value.map((d: Record<string, unknown>) => (d.third_partner as { id?: number })?.id)),
])

const mixedCustomers = computed(() => selectedCustomers.value.length > 1)

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
watch([search, typeFilter, customerFilter], () => {
  selectedIds.value = []
})

const showGroupModal = ref(false)
const grouping = ref(false)
const groupError = ref('')
const groupForm = ref({ issued_at: '', customer_ref: '' })

function openGroupModal(): void {
  groupError.value = ''
  const dates = selectedRows.value
    .map((d: Record<string, unknown>) => String(d.issued_at ?? '').slice(0, 10))
    .filter(Boolean)
    .sort()
  groupForm.value = { issued_at: dates[dates.length - 1] ?? '', customer_ref: '' }
  showGroupModal.value = true
}

async function submitGroup(): Promise<void> {
  groupError.value = ''
  grouping.value = true

  const result = await store.regrouperBls({
    delivery_note_ids: selectedIds.value,
    issued_at: groupForm.value.issued_at || null,
    customer_ref: groupForm.value.customer_ref || null,
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
    router.push(`/ventes/documents/${result.facture.id}`)
  }
}

const columns = [
  { key: 'select', label: '' },
  { key: 'reference', label: 'Référence' },
  { key: 'document_type', label: 'Type', hideOnMobile: true },
  { key: 'status', label: 'Statut', hideOnMobile: true },
  { key: 'third_partner', label: 'Client' },
  { key: 'issued_at', label: 'Date' },
]

function mobileRowClass(row: Record<string, unknown>): string {
  const isPaid = row.status === 'paid'
  return isPaid ? 'border-gray-200 dark:border-gray-700' : 'border-red-400 dark:border-red-600 border-l-4'
}

const typeLabels: Record<string, string> = {
  QuoteSale: 'Devis',
  CustomerOrder: 'Bon de Commande Client',
  DeliveryNote: 'Bon de Livraison',
  InvoiceSale: 'Facture',
  TicketSale: 'Ticket POS',
  CreditNoteSale: 'Avoir',
  ReturnSale: 'Bon de Retour',
}

const statusLabels: Record<string, string> = {
  draft: 'Brouillon',
  confirmed: 'Confirmé',
  converted: 'Converti',
  delivered: 'Livré',
  pending: 'En attente',
  partial: 'Partiel',
  paid: 'Payé',
  cancelled: 'Annulé',
}

function statusLabel(status: string, documentType?: string): string {
  // BL (DeliveryNote) — "confirmed" = livré, pas payé.
  if (documentType === 'DeliveryNote' && status === 'confirmed') {
    return 'Livré'
  }
  return statusLabels[status] ?? status
}

function viewDocument(doc: Record<string, unknown>) {
  router.push(`/ventes/documents/${doc.id}`)
}

/* ── Payment modal ──────────────────────────────────────────────── */
const showPaymentModal = ref(false)
const paymentLoading = ref(false)
const paymentSuccess = ref('')
const paymentError = ref('')
const paymentTarget = ref<Record<string, unknown> | null>(null)

const paymentForm = ref({
  amount: 0,
  method: 'cash' as string,
  paid_at: new Date().toISOString().split('T')[0],
  reference: '',
  notes: '',
})

function canPay(row: Record<string, unknown>): boolean {
  return (
    row.document_type === 'InvoiceSale' &&
    ['confirmed', 'pending', 'partial'].includes(row.status as string) &&
    Number((row as any).footer?.amount_due ?? 0) > 0
  )
}

function openPayment(row: Record<string, unknown>) {
  paymentTarget.value = row
  paymentForm.value = {
    amount: Number((row as any).footer?.amount_due ?? 0),
    method: 'cash',
    paid_at: new Date().toISOString().split('T')[0],
    reference: '',
    notes: '',
  }
  showPaymentModal.value = true
}

async function submitPayment() {
  if (!paymentTarget.value) return
  paymentLoading.value = true
  paymentError.value = ''
  try {
    await store.addPayment({
      document_header_id: paymentTarget.value.id,
      amount: paymentForm.value.amount,
      method: paymentForm.value.method,
      paid_at: paymentForm.value.paid_at,
      reference: paymentForm.value.reference || null,
      notes: paymentForm.value.notes || null,
    })
    showPaymentModal.value = false
    paymentSuccess.value = 'Paiement enregistré avec succès.'
    setTimeout(() => {
      paymentSuccess.value = ''
    }, 4000)
    loadPage(store.meta.current_page)
  } catch {
    paymentError.value = "Erreur lors de l'enregistrement du paiement."
  }
  paymentLoading.value = false
}
</script>

<template>
  <div class="max-w-9xl mx-auto py-4 sm:py-6 px-3 sm:px-4">
    <div class="flex items-center justify-between gap-3 mb-6">
      <h1 class="text-lg sm:text-2xl font-bold text-gray-900 dark:text-white">Documents de Vente</h1>
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
          to="/ventes/documents/create"
          class="inline-flex items-center gap-2 px-3 sm:px-4 py-2 bg-orange-700 text-white text-sm font-medium rounded-lg hover:bg-orange-800 transition"
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
        aria-label="Rechercher une vente"
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
        <option v-for="t in saleTypes" :key="t" :value="t">{{ typeLabels[t] }}</option>
      </select>
      <select
        v-model="customerFilter"
        aria-label="Filtrer par client"
        class="px-3.5 py-2 text-input rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-orange-500"
      >
        <option value="">Tous clients</option>
        <option v-for="c in customers" :key="c.id" :value="String(c.id)">{{ c.tp_title }}</option>
      </select>
      <button
        v-if="billableRows.length"
        class="px-3.5 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition"
        @click="toggleAll"
      >
        {{ allBillableSelected ? 'Tout décocher' : 'Cocher tous les BL' }}
      </button>
    </div>

    <!-- Barre d'action : n'apparaît qu'une fois des BL cochés -->
    <div
      v-if="selectedIds.length"
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 px-4 py-3 rounded-lg border border-teal-300 dark:border-teal-700 bg-teal-50 dark:bg-teal-900/20"
    >
      <div class="text-sm text-gray-700 dark:text-gray-200">
        <strong>{{ selectedIds.length }}</strong> bon(s) de livraison sélectionné(s) —
        <strong class="font-mono">{{ fmt(selectedTotal) }} DH</strong>
        <span v-if="mixedCustomers" class="block sm:inline text-red-600 dark:text-red-400 sm:ml-2">
          Clients différents : un seul client par facture.
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
          :disabled="mixedCustomers"
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

    <!-- Flash messages -->
    <Transition
      enter-active-class="transition duration-300"
      enter-from-class="opacity-0 -translate-y-2"
      leave-active-class="transition duration-200"
      leave-to-class="opacity-0"
    >
      <div
        v-if="paymentSuccess"
        class="mb-4 flex items-center gap-2 px-4 py-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-300 rounded-xl text-sm"
      >
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        {{ paymentSuccess }}
      </div>
    </Transition>

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
        <span class="text-sm">{{ statusLabel(row.status, row.document_type) }}</span>
      </template>

      <template #cell-third_partner="{ row }">
        <span class="text-sm">{{ row.third_partner?.tp_title ?? '—' }}</span>
      </template>

      <template #cell-issued_at="{ row }">
        <span class="text-sm">{{ fmtDate(row.issued_at) }}</span>
      </template>

      <template #actions="{ row }">
        <div class="flex items-center gap-2">
          <button
            v-if="canPay(row)"
            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg transition bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 border border-emerald-200 dark:border-emerald-800"
            @click.stop="openPayment(row)"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            Payer
          </button>
          <button
            class="text-orange-700 dark:text-orange-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm font-medium"
            @click="viewDocument(row)"
          >
            Voir
          </button>
        </div>
      </template>
    </BaseTable>

    <BaseModal v-model="showGroupModal" title="Facturer les BL sélectionnés" size="md">
      <div class="space-y-4">
        <div class="rounded-lg bg-gray-50 dark:bg-gray-900 px-4 py-3 text-sm">
          <div class="flex justify-between py-1">
            <span class="text-gray-500 dark:text-gray-400">Client</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">
              {{ selectedRows[0]?.third_partner?.tp_title ?? '—' }}
            </span>
          </div>
          <div class="flex justify-between py-1">
            <span class="text-gray-500 dark:text-gray-400">Bons de livraison</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">{{ selectedIds.length }}</span>
          </div>
          <div class="flex justify-between py-1 border-t border-gray-200 dark:border-gray-700 mt-1 pt-2">
            <span class="text-gray-700 dark:text-gray-300 font-semibold">Total facture</span>
            <span class="font-mono font-bold text-gray-900 dark:text-white">{{ fmt(selectedTotal) }} DH</span>
          </div>
        </div>

        <div>
          <label for="group-date-vente" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            Date de la facture
          </label>
          <input
            id="group-date-vente"
            v-model="groupForm.issued_at"
            type="date"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
          />
          <p class="text-xs text-gray-400 mt-1">Par défaut, la date du dernier BL sélectionné.</p>
        </div>

        <div>
          <label for="group-ref-vente" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            Référence client
          </label>
          <input
            id="group-ref-vente"
            v-model="groupForm.customer_ref"
            type="text"
            placeholder="Facultatif — n° de commande du client"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
          />
          <p class="text-xs text-gray-400 mt-1">Conservée dans les notes, pour le rapprochement.</p>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
          Les BL restent consultables et passent en « Converti ». Le stock n'est pas remouvementé : la marchandise est
          sortie à la livraison. Les règlements déjà encaissés sur ces BL suivent la facture.
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

    <!-- Payment Modal -->
    <BaseModal v-model="showPaymentModal" title="Enregistrer un paiement" size="md">
      <div v-if="paymentTarget" class="mb-4 px-3 py-2.5 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm">
        <span class="text-gray-500 dark:text-gray-400">Facture :</span>
        <span class="font-semibold text-gray-800 dark:text-gray-200 ml-1">{{ (paymentTarget as any).reference }}</span>
        <span class="text-gray-400 dark:text-gray-500 mx-1">—</span>
        <span class="text-gray-600 dark:text-gray-300">{{
          (paymentTarget as any).third_partner?.tp_title ?? '—'
        }}</span>
      </div>
      <div
        v-if="paymentError"
        class="mb-4 px-3 py-2.5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg text-sm"
      >
        {{ paymentError }}
      </div>
      <form class="space-y-4" @submit.prevent="submitPayment">
        <div>
          <label
            for="documentsvente-paymentform-amount"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >Montant *</label
          >
          <input
            id="documentsvente-paymentform-amount"
            v-model.number="paymentForm.amount"
            type="number"
            step="0.01"
            min="0.01"
            required
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
          />
        </div>
        <div>
          <label
            for="documentsvente-paymentform-method"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >Méthode *</label
          >
          <select
            id="documentsvente-paymentform-method"
            v-model="paymentForm.method"
            required
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
          >
            <option value="cash">Espèces</option>
            <option value="bank_transfer">Virement</option>
            <option value="cheque">Chèque</option>
            <option value="effet">Effet</option>
            <option value="credit">Crédit</option>
          </select>
        </div>
        <div>
          <label
            for="documentsvente-paymentform-paid-at"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >Date</label
          >
          <input
            id="documentsvente-paymentform-paid-at"
            v-model="paymentForm.paid_at"
            type="date"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
          />
        </div>
        <div>
          <label
            for="documentsvente-paymentform-reference"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >Référence</label
          >
          <input
            id="documentsvente-paymentform-reference"
            v-model="paymentForm.reference"
            type="text"
            placeholder="N° chèque, virement..."
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500 focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
          />
        </div>
        <div>
          <label
            for="documentsvente-paymentform-notes"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >Notes</label
          >
          <textarea
            id="documentsvente-paymentform-notes"
            v-model="paymentForm.notes"
            rows="2"
            placeholder="Notes..."
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500 focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
          ></textarea>
        </div>
      </form>
      <template #footer>
        <button
          class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
          @click="showPaymentModal = false"
        >
          Annuler
        </button>
        <button
          :disabled="paymentLoading || paymentForm.amount <= 0"
          class="px-5 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50"
          @click="submitPayment"
        >
          {{ paymentLoading ? 'Enregistrement...' : 'Enregistrer' }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>
