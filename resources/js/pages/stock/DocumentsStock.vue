<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDocumentStockStore } from '@/stores/stock/useDocumentStockStore'
import { useExcelExport } from '@/composables/useExcelExport'
import BaseTable from '@/components/BaseTable.vue'
import BaseSkeleton from '@/components/BaseSkeleton.vue'
import BasePagination from '@/components/BasePagination.vue'

const router = useRouter()
const store = useDocumentStockStore()

const search = ref('')
const typeFilter = ref('')
const statFilter = ref('')
let searchTimer: ReturnType<typeof setTimeout> | null = null

const stockTypes = ['StockEntry', 'StockExit', 'StockAdjustmentNote', 'StockTransfer']

const typeLabels: Record<string, string> = {
  StockEntry: 'Entrée de Stock',
  StockExit: 'Sortie de Stock',
  StockAdjustmentNote: 'Ajustement',
  StockTransfer: 'Transfert',
}

const typeColors: Record<string, string> = {
  StockEntry: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  StockExit: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  StockAdjustmentNote: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
  StockTransfer: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
}

const statusLabels: Record<string, string> = {
  draft: 'Brouillon',
  confirmed: 'Confirmé',
  applied: 'Appliqué',
  cancelled: 'Annulé',
}

const statusColors: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
  confirmed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  applied: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
  cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
}

const { exporting, exportExcel } = useExcelExport()

function onExport() {
  exportExcel('/export/documents', buildFilters())
}

function buildFilters(): Record<string, string> {
  const f: Record<string, string> = {}
  if (search.value.trim()) f.search = search.value.trim()
  if (typeFilter.value) f.document_type = typeFilter.value
  if (statFilter.value) f.status = statFilter.value
  return f
}

function loadPage(page = 1) {
  store.fetchAll(page, buildFilters())
}

watch([search, typeFilter, statFilter], () => {
  clearTimeout(searchTimer!)
  searchTimer = setTimeout(() => loadPage(1), 350)
})

onMounted(() => loadPage())

const columns = [
  { key: 'reference', label: 'Référence' },
  { key: 'document_type', label: 'Type' },
  { key: 'status', label: 'Statut' },
  { key: 'warehouse', label: 'Dépôt' },
  { key: 'issued_at', label: 'Date' },
]

function viewDoc(row: Record<string, unknown>) {
  router.push(`/stock/documents/${row.id}`)
}
</script>

<template>
  <div class="max-w-9xl mx-auto py-4 sm:py-6 px-3 sm:px-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
      <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">Documents de Stock</h1>
      <div class="flex items-center gap-3">
        <button
          class="flex items-center gap-2 px-3 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition disabled:opacity-50"
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
          to="/stock/documents/create"
          class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-lg hover:bg-violet-700 transition"
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
        class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-gray-200 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-violet-500 w-full sm:w-64"
      />
      <select
        v-model="typeFilter"
        class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-violet-500"
      >
        <option value="">Tous types</option>
        <option v-for="t in stockTypes" :key="t" :value="t">{{ typeLabels[t] }}</option>
      </select>
      <select
        v-model="statFilter"
        class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-violet-500"
      >
        <option value="">Tous statuts</option>
        <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
      </select>
    </div>

    <BaseSkeleton v-if="store.loading && !store.documents.length" type="table" :rows="8" />

    <BaseTable v-else :columns="columns" :rows="store.documents">
      <template #cell-document_type="{ row }">
        <span
          :class="typeColors[row.document_type] ?? 'bg-gray-100 text-gray-600'"
          class="px-2 py-0.5 rounded-full text-xs font-medium"
        >
          {{ typeLabels[row.document_type] ?? row.document_type }}
        </span>
      </template>

      <template #cell-status="{ row }">
        <span
          :class="statusColors[row.status] ?? 'bg-gray-100 text-gray-600'"
          class="px-2 py-0.5 rounded-full text-xs font-medium"
        >
          {{ statusLabels[row.status] ?? row.status }}
        </span>
      </template>

      <template #cell-warehouse="{ row }">
        <span class="text-sm">{{ row.warehouse?.wh_title ?? '—' }}</span>
      </template>

      <template #actions="{ row }">
        <button
          class="text-violet-600 dark:text-violet-400 hover:text-violet-800 dark:hover:text-violet-300 text-sm font-medium"
          @click="viewDoc(row)"
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
      @change="(p) => loadPage(p)"
    />
  </div>
</template>
