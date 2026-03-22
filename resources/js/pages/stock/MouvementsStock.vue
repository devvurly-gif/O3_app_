<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useStockOperationStore } from '@/stores/stock/useStockOperationStore'
import { useExcelExport } from '@/composables/useExcelExport'
import BaseTable from '@/components/BaseTable.vue'
import BasePagination from '@/components/BasePagination.vue'

const store = useStockOperationStore()

const dirFilter = ref('')
const reasonFilter = ref('')
const productSearch = ref('')

let filterTimer: ReturnType<typeof setTimeout> | null = null

const columns = [
  { key: 'product', label: 'Produit' },
  { key: 'warehouse', label: 'Dépôt' },
  { key: 'direction', label: 'Direction' },
  { key: 'reason', label: 'Motif' },
  { key: 'document', label: 'Document' },
  { key: 'quantity', label: 'Quantité' },
  { key: 'stock_before', label: 'Stock Avant' },
  { key: 'stock_after', label: 'Stock Après' },
  { key: 'created_at', label: 'Date' },
]

const directionLabels = { in: 'Entrée', out: 'Sortie' }
const directionColors = {
  in: 'bg-green-100 text-green-700',
  out: 'bg-red-100 text-red-700',
}

const reasonLabels: Record<string, string> = {
  sale_delivery: 'Livraison vente',
  purchase_receipt: 'Réception achat',
  transfer_out: 'Transfert sortant',
  transfer_in: 'Transfert entrant',
  manual_entry: 'Entrée manuelle',
  manual_exit: 'Sortie manuelle',
  inventory_adjustment: 'Ajustement inventaire',
  stock_entry: 'Entrée stock (doc)',
  stock_exit: 'Sortie stock (doc)',
  stock_adjustment: 'Ajustement stock (doc)',
  stock_transfer_in: 'Transfert entrant (doc)',
  stock_transfer_out: 'Transfert sortant (doc)',
}

const stockDocTypes = ['StockEntry', 'StockExit', 'StockAdjustmentNote', 'StockTransfer']

const reasonOptions = Object.entries(reasonLabels)

const { exporting, exportExcel } = useExcelExport()

function onExport() {
  exportExcel('/export/stock-mouvements', buildFilters())
}

function buildFilters(): Record<string, string> {
  const f: Record<string, string> = {}
  if (dirFilter.value) f.direction = dirFilter.value
  if (reasonFilter.value) f.reason = reasonFilter.value
  if (productSearch.value.trim()) f.product_search = productSearch.value.trim()
  return f
}

function loadPage(page = 1) {
  store.fetchMouvements(page, buildFilters())
}

function onPageChange(page: number) {
  loadPage(page)
}

watch([dirFilter, reasonFilter, productSearch], () => {
  clearTimeout(filterTimer)
  filterTimer = setTimeout(() => loadPage(1), 200)
})

onMounted(() => loadPage())
</script>

<template>
  <div class="max-w-9xl mx-auto py-6 px-4">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Mouvements de Stock</h1>
      <button
        class="flex items-center gap-2 px-3 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
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
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <div class="relative">
        <svg
          class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="productSearch"
          type="text"
          placeholder="Produit : nom, SKU, EAN…"
          class="pl-9 pr-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-violet-500 w-64"
        />
      </div>
      <select
        v-model="dirFilter"
        class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="">Toutes directions</option>
        <option value="in">Entrée</option>
        <option value="out">Sortie</option>
      </select>
      <select
        v-model="reasonFilter"
        class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="">Tous motifs</option>
        <option v-for="[key, label] in reasonOptions" :key="key" :value="key">{{ label }}</option>
      </select>
    </div>

    <div v-if="store.loading && !store.mouvements.length" class="text-center py-12 text-gray-500 dark:text-gray-400">Chargement...</div>

    <BaseTable v-else :columns="columns" :rows="store.mouvements">
      <template #cell-product="{ row }">
        <span class="text-sm font-medium">{{ row.product?.p_title ?? '—' }}</span>
      </template>
      <template #cell-warehouse="{ row }">
        <span class="text-sm">{{ row.warehouse?.wh_title ?? '—' }}</span>
      </template>
      <template #cell-direction="{ row }">
        <span :class="directionColors[row.direction]" class="px-2 py-0.5 rounded-full text-xs font-medium">
          {{ directionLabels[row.direction] ?? row.direction }}
        </span>
      </template>
      <template #cell-reason="{ row }">
        <span class="text-sm text-gray-600 dark:text-gray-400">{{ reasonLabels[row.reason] ?? row.reason ?? '—' }}</span>
      </template>
      <template #cell-document="{ row }">
        <router-link
          v-if="row.document_reference && row.document_header_id"
          :to="
            stockDocTypes.includes(row.document_type)
              ? `/stock/documents/${row.document_header_id}`
              : `/documents/${row.document_header_id}`
          "
          class="text-xs text-violet-600 hover:underline font-mono"
        >
          {{ row.document_reference }}
        </router-link>
        <span v-else class="text-xs text-gray-400 dark:text-gray-500">—</span>
      </template>
      <template #cell-quantity="{ row }">
        <span class="text-sm font-mono">{{ Number(row.quantity).toFixed(2) }}</span>
      </template>
      <template #cell-stock_before="{ row }">
        <span class="text-sm font-mono text-gray-500 dark:text-gray-400">{{ Number(row.stock_before).toFixed(2) }}</span>
      </template>
      <template #cell-stock_after="{ row }">
        <span class="text-sm font-mono font-medium">{{ Number(row.stock_after).toFixed(2) }}</span>
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
