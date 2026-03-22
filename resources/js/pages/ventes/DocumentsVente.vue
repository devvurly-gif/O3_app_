<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDocumentVenteStore } from '@/stores/ventes/useDocumentVenteStore'
import { useExcelExport } from '@/composables/useExcelExport'
import BaseTable from '@/components/BaseTable.vue'
import BaseSkeleton from '@/components/BaseSkeleton.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseModal from '@/components/BaseModal.vue'

const router = useRouter()
const store = useDocumentVenteStore()

const search = ref('')
const typeFilter = ref('')
let searchTimer: ReturnType<typeof setTimeout> | null = null

const saleTypes = ['QuoteSale', 'CustomerOrder', 'DeliveryNote', 'InvoiceSale', 'CreditNoteSale', 'ReturnSale']

const { exporting, exportExcel } = useExcelExport()

function onExport() {
  exportExcel('/export/documents', buildFilters())
}

function buildFilters(): Record<string, string> {
  const f: Record<string, string> = {}
  if (search.value.trim()) f.search = search.value.trim()
  if (typeFilter.value) f.document_type = typeFilter.value
  return f
}

function loadPage(page = 1) {
  store.fetchAll(page, buildFilters())
}

function onPageChange(page: number) {
  loadPage(page)
}

watch([search, typeFilter], () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadPage(1), 350)
})

onMounted(() => loadPage())

const columns = [
  { key: 'reference', label: 'Référence' },
  { key: 'document_type', label: 'Type' },
  { key: 'status', label: 'Statut' },
  { key: 'third_partner', label: 'Client' },
  { key: 'issued_at', label: 'Date' },
]

const typeLabels: Record<string, string> = {
  QuoteSale: 'Devis',
  CustomerOrder: 'Bon de Commande Client',
  DeliveryNote: 'Bon de Livraison',
  InvoiceSale: 'Facture',
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
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
      <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">Documents de Vente</h1>
      <div class="flex items-center gap-3">
        <button
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
          {{ exporting ? 'Export...' : 'Export Excel' }}
        </button>
        <router-link
          to="/ventes/documents/create"
          class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          Nouveau
        </router-link>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-3 mb-6">
      <input
        v-model="search"
        type="text"
        placeholder="Rechercher référence..."
        class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-gray-200 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 w-full sm:w-64"
      />
      <select
        v-model="typeFilter"
        class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="">Tous types</option>
        <option v-for="t in saleTypes" :key="t" :value="t">{{ typeLabels[t] }}</option>
      </select>
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

    <BaseTable v-else :columns="columns" :rows="store.documents">
      <template #cell-document_type="{ row }">
        <span class="text-sm">{{ typeLabels[row.document_type] ?? row.document_type }}</span>
      </template>

      <template #cell-status="{ row }">
        <span class="text-sm">{{ statusLabels[row.status] ?? row.status }}</span>
      </template>

      <template #cell-third_partner="{ row }">
        <span class="text-sm">{{ row.third_partner?.tp_title ?? '—' }}</span>
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
            class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm font-medium"
            @click="viewDocument(row)"
          >
            Voir
          </button>
        </div>
      </template>
    </BaseTable>

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
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Montant *</label>
          <input
            v-model.number="paymentForm.amount"
            type="number"
            step="0.01"
            min="0.01"
            required
            class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Méthode *</label>
          <select
            v-model="paymentForm.method"
            required
            class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="cash">Espèces</option>
            <option value="bank_transfer">Virement</option>
            <option value="cheque">Chèque</option>
            <option value="effet">Effet</option>
            <option value="credit">Crédit</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
          <input
            v-model="paymentForm.paid_at"
            type="date"
            class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Référence</label>
          <input
            v-model="paymentForm.reference"
            type="text"
            placeholder="N° chèque, virement..."
            class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
          <textarea
            v-model="paymentForm.notes"
            rows="2"
            placeholder="Notes..."
            class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
