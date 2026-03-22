<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Rapports</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Générez des rapports PDF pour les ventes, achats et stock</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-700">
      <nav class="flex -mb-px space-x-8">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="py-3 px-1 border-b-2 font-medium text-sm transition-colors whitespace-nowrap"
          :class="
            activeTab === tab.key
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
          "
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
      <div class="flex flex-wrap items-end gap-4">
        <template v-if="activeTab !== 'stock'">
          <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Du</label>
            <input
              v-model="filters.from"
              type="date"
              class="block w-44 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Au</label>
            <input
              v-model="filters.to"
              type="date"
              class="block w-44 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </template>
        <template v-if="activeTab === 'stock'">
          <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Entrepôt</label>
            <select
              v-model="filters.warehouse_id"
              class="block w-52 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Tous les entrepôts</option>
              <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.wh_title }}</option>
            </select>
          </div>
        </template>

        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition"
          :disabled="loading"
          @click="fetchReport"
        >
          <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          Générer
        </button>

        <button
          v-if="reportData"
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 transition"
          :disabled="downloadingPdf"
          @click="downloadPdf"
        >
          <svg v-if="downloadingPdf" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Télécharger PDF
        </button>
      </div>
    </div>

    <!-- Report data -->
    <BaseSkeleton v-if="loading" type="table" :rows="6" />

    <template v-else-if="reportData">
      <!-- Sales Report -->
      <template v-if="activeTab === 'sales'">
        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <KpiCard label="CA TTC" :value="fmt(reportData.totals.revenue_ttc)" currency />
          <KpiCard label="CA HT" :value="fmt(reportData.totals.revenue_ht)" currency />
          <KpiCard label="Total TVA" :value="fmt(reportData.totals.total_tax)" currency />
          <KpiCard label="Factures" :value="String(reportData.totals.invoice_count)" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <ReportTable title="Top 10 Produits" :columns="['Produit', 'Qté', 'CA TTC']" :rows="reportData.top_products.map((r: any) => [r.designation, fmtQty(r.total_qty), fmt(r.total_revenue)])" />
          <ReportTable title="Top 10 Clients" :columns="['Client', 'Factures', 'CA TTC']" :rows="reportData.top_clients.map((r: any) => [r.tp_title, r.invoice_count, fmt(r.total_revenue)])" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <ReportTable title="Par Type" :columns="['Type', 'Nombre']" :rows="reportData.by_type.map((r: any) => [r.label, r.count])" />
          <ReportTable title="Paiements par Méthode" :columns="['Méthode', 'Nombre', 'Montant']" :rows="reportData.payments_by_method.map((r: any) => [ucfirst(r.method), r.count, fmt(r.total)])" />
        </div>
      </template>

      <!-- Purchases Report -->
      <template v-if="activeTab === 'purchases'">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <KpiCard label="Achats TTC" :value="fmt(reportData.totals.spending_ttc)" currency />
          <KpiCard label="Achats HT" :value="fmt(reportData.totals.spending_ht)" currency />
          <KpiCard label="Total TVA" :value="fmt(reportData.totals.total_tax)" currency />
          <KpiCard label="Factures" :value="String(reportData.totals.invoice_count)" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <ReportTable title="Top 10 Produits Achetés" :columns="['Produit', 'Qté', 'Coût TTC']" :rows="reportData.top_products.map((r: any) => [r.designation, fmtQty(r.total_qty), fmt(r.total_cost)])" />
          <ReportTable title="Top 10 Fournisseurs" :columns="['Fournisseur', 'Factures', 'Montant']" :rows="reportData.top_suppliers.map((r: any) => [r.tp_title, r.invoice_count, fmt(r.total_amount)])" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <ReportTable title="Par Type" :columns="['Type', 'Nombre']" :rows="reportData.by_type.map((r: any) => [r.label, r.count])" />
          <ReportTable title="Paiements par Méthode" :columns="['Méthode', 'Nombre', 'Montant']" :rows="reportData.payments_by_method.map((r: any) => [ucfirst(r.method), r.count, fmt(r.total)])" />
        </div>
      </template>

      <!-- Stock Report -->
      <template v-if="activeTab === 'stock'">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <KpiCard label="Produits en Stock" :value="String(reportData.total_value.product_count)" />
          <KpiCard label="Quantité Totale" :value="fmtQty(reportData.total_value.total_qty)" />
          <KpiCard label="Valeur du Stock" :value="fmt(reportData.total_value.total_value)" currency />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <ReportTable
            v-if="reportData.out_of_stock.length"
            title="Ruptures de Stock"
            highlight="red"
            :columns="['SKU', 'Produit', 'Entrepôt', 'Stock']"
            :rows="reportData.out_of_stock.map((r: any) => [r.sku, r.product, r.warehouse, fmtQty(r.stockLevel)])"
          />
          <ReportTable
            v-if="reportData.low_stock.length"
            title="Stock Faible"
            highlight="orange"
            :columns="['SKU', 'Produit', 'Entrepôt', 'Stock']"
            :rows="reportData.low_stock.map((r: any) => [r.sku, r.product, r.warehouse, fmtQty(r.stockLevel)])"
          />
        </div>

        <ReportTable
          title="État Complet du Stock"
          :columns="['SKU', 'Produit', 'Entrepôt', 'Stock', 'Coût Unit.', 'Valeur']"
          :rows="reportData.current_stock.map((r: any) => [r.sku, r.product, r.warehouse, fmtQty(r.stockLevel), fmt(r.cost_price), fmt(r.value)])"
        />
      </template>
    </template>

    <!-- Empty state -->
    <div v-else class="flex flex-col items-center justify-center py-20 text-gray-400 dark:text-gray-500">
      <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <p class="text-lg font-medium">Sélectionnez vos filtres et cliquez sur Générer</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import http from '@/services/http'
import { useToastStore } from '@/stores/toastStore'
import KpiCard from '@/components/reports/KpiCard.vue'
import ReportTable from '@/components/reports/ReportTable.vue'
import BaseSkeleton from '@/components/BaseSkeleton.vue'

const toast = useToastStore()

const tabs = [
  { key: 'sales', label: 'Ventes' },
  { key: 'purchases', label: 'Achats' },
  { key: 'stock', label: 'Stock' },
]

const activeTab = ref('sales')
const loading = ref(false)
const downloadingPdf = ref(false)
const reportData = ref<any>(null)
const warehouses = ref<any[]>([])

const now = new Date()
const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)

const filters = reactive({
  from: firstOfMonth.toISOString().split('T')[0],
  to: now.toISOString().split('T')[0],
  warehouse_id: '',
})

// Reset data when tab changes
watch(activeTab, () => {
  reportData.value = null
})

onMounted(async () => {
  try {
    const resp = await http.get('/warehouses')
    warehouses.value = resp.data.data ?? resp.data
  } catch {
    // silent
  }
})

async function fetchReport() {
  loading.value = true
  reportData.value = null
  try {
    const endpoint = `/reports/${activeTab.value}`
    const params: Record<string, string> =
      activeTab.value === 'stock'
        ? filters.warehouse_id
          ? { warehouse_id: filters.warehouse_id }
          : {}
        : { from: filters.from, to: filters.to }

    const resp = await http.get(endpoint, { params })
    reportData.value = resp.data
  } catch {
    toast.error('Erreur lors de la génération du rapport.')
  } finally {
    loading.value = false
  }
}

async function downloadPdf() {
  downloadingPdf.value = true
  try {
    const endpoint = `/reports/${activeTab.value}/pdf`
    const params: Record<string, string> =
      activeTab.value === 'stock'
        ? filters.warehouse_id
          ? { warehouse_id: filters.warehouse_id }
          : {}
        : { from: filters.from, to: filters.to }

    const resp = await http.get(endpoint, { params, responseType: 'blob' })
    const blob = new Blob([resp.data], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download =
      activeTab.value === 'stock'
        ? `Rapport_Stock_${new Date().toISOString().split('T')[0]}.pdf`
        : `Rapport_${ucfirst(activeTab.value)}_${filters.from}_${filters.to}.pdf`
    document.body.appendChild(a)
    a.click()
    window.URL.revokeObjectURL(url)
    document.body.removeChild(a)
    toast.success('PDF téléchargé avec succès.')
  } catch {
    toast.error('Erreur lors du téléchargement du PDF.')
  } finally {
    downloadingPdf.value = false
  }
}

function fmt(val: number | string): string {
  return Number(val).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function fmtQty(val: number | string): string {
  return Number(val).toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function ucfirst(s: string): string {
  if (!s) return ''
  return s.charAt(0).toUpperCase() + s.slice(1)
}
</script>
