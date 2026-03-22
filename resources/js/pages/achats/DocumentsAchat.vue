<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDocumentAchatStore } from '@/stores/achats/useDocumentAchatStore'
import { useExcelExport } from '@/composables/useExcelExport'
import BaseTable from '@/components/BaseTable.vue'
import BaseSkeleton from '@/components/BaseSkeleton.vue'
import BasePagination from '@/components/BasePagination.vue'

const router = useRouter()
const store = useDocumentAchatStore()

const search = ref('')
const typeFilter = ref('')
let searchTimer: ReturnType<typeof setTimeout> | null = null

const purchaseTypes = [
  'PurchaseOrder',
  'ReceiptNotePurchase',
  'InvoicePurchase',
  'CreditNotePurchase',
  'ReturnPurchase',
]

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
  { key: 'third_partner', label: 'Fournisseur' },
  { key: 'issued_at', label: 'Date' },
]

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
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
      <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">Documents d'Achat</h1>
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
          to="/achats/documents/create"
          class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition"
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
        <option v-for="t in purchaseTypes" :key="t" :value="t">{{ typeLabels[t] }}</option>
      </select>
    </div>

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
        <button
          class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm font-medium"
          @click="viewDocument(row)"
        >
          Voir
        </button>
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
  </div>
</template>
