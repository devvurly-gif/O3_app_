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
        <!-- Date range: sales, purchases -->
        <template v-if="activeTab === 'sales' || activeTab === 'purchases'">
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

        <!-- Warehouse: stock -->
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

        <!-- Single date: POS -->
        <template v-if="activeTab === 'pos'">
          <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Date</label>
            <input
              v-model="filters.pos_date"
              type="date"
              class="block w-44 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </template>

        <!-- Credit: no filters needed -->

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
          v-if="reportData && activeTab !== 'credit'"
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

      <!-- POS Daily Report -->
      <template v-if="activeTab === 'pos'">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          <KpiCard label="Sessions" :value="String(reportData.sessions_count)" />
          <KpiCard label="Tickets" :value="String(reportData.total_tickets)" />
          <KpiCard label="CA TTC" :value="fmt(reportData.total_ttc)" currency />
          <KpiCard label="Annulés" :value="String(reportData.cancelled_tickets)" />
          <KpiCard label="Écart Caisse" :value="fmt(reportData.total_difference)" :currency="true" />
        </div>

        <!-- Sessions du jour -->
        <ReportTable
          title="Sessions du jour"
          :columns="['#', 'Terminal', 'Caissier', 'Ouverture', 'Fermeture', 'Tickets', 'CA TTC', 'Écart']"
          :rows="reportData.sessions.map((s: any) => [
            s.id,
            s.terminal,
            s.user,
            formatTime(s.opened_at),
            formatTime(s.closed_at),
            s.tickets,
            fmt(s.total_ttc),
            fmt(s.cash_difference)
          ])"
        />

        <!-- Réconciliation caisse -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Réconciliation Caisse</h3>
          <div class="space-y-2">
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">Total fonds de caisse ouverture</span>
              <span class="font-medium text-gray-900 dark:text-white">{{ fmt(reportData.total_opening_cash) }} MAD</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">Total caisses fermées</span>
              <span class="font-medium text-gray-900 dark:text-white">{{ fmt(reportData.total_closing_cash) }} MAD</span>
            </div>
            <div class="flex justify-between text-sm pt-2 border-t border-gray-200 dark:border-gray-700">
              <span class="font-semibold text-gray-900 dark:text-white">Écart total</span>
              <span class="font-bold text-lg" :class="reportData.total_difference >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ fmt(reportData.total_difference) }} MAD
              </span>
            </div>
          </div>
        </div>

        <!-- Paiements par méthode -->
        <ReportTable
          title="Résumé des Paiements"
          :columns="['Méthode', 'Montant']"
          :rows="Object.entries(reportData.payments_by_method).map(([method, amount]: [string, any]) => [ucfirst(method), fmt(amount)])"
        />
      </template>

      <!-- Credit / Créances Report -->
      <template v-if="activeTab === 'credit'">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <KpiCard label="Encours Total" :value="fmt(reportData.total_encours)" currency />
          <KpiCard label="Clients en Compte" :value="String(reportData.clients_count)" />
          <KpiCard label="Clients en Alerte" :value="String(reportData.clients_alerte)" />
        </div>

        <!-- Clients list -->
        <div class="space-y-4">
          <div
            v-for="client in reportData.clients"
            :key="client.id"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm"
          >
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ client.name }}</h3>
                  <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="{
                      'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': client.status === 'normal',
                      'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400': client.status === 'alerte',
                      'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': client.status === 'depasse',
                    }"
                  >
                    {{ client.status === 'normal' ? 'Normal' : client.status === 'alerte' ? 'Alerte' : 'Dépassé' }}
                  </span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  {{ client.code }} <span v-if="client.phone">· {{ client.phone }}</span>
                </p>
              </div>
              <div class="text-right">
                <p class="text-lg font-bold" :class="client.status === 'depasse' ? 'text-red-600' : 'text-gray-900 dark:text-white'">
                  {{ fmt(client.encours) }} MAD
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  Seuil : {{ client.seuil > 0 ? fmt(client.seuil) + ' MAD' : 'Non défini' }}
                  <span v-if="client.seuil > 0"> · {{ client.usage_pct }}%</span>
                </p>
              </div>
            </div>

            <!-- Progress bar -->
            <div v-if="client.seuil > 0" class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-4">
              <div
                class="h-2 rounded-full transition-all"
                :class="{
                  'bg-green-500': client.usage_pct < 80,
                  'bg-yellow-500': client.usage_pct >= 80 && client.usage_pct < 100,
                  'bg-red-500': client.usage_pct >= 100,
                }"
                :style="{ width: Math.min(client.usage_pct, 100) + '%' }"
              />
            </div>

            <!-- Unpaid documents -->
            <div v-if="client.unpaid_docs && client.unpaid_docs.length" class="mt-3">
              <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Documents impayés ({{ client.unpaid_docs.length }})</p>
              <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                  <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                      <th class="pb-1 pr-3 font-medium">Réf.</th>
                      <th class="pb-1 pr-3 font-medium">Type</th>
                      <th class="pb-1 pr-3 font-medium">Date</th>
                      <th class="pb-1 pr-3 font-medium text-right">Total</th>
                      <th class="pb-1 pr-3 font-medium text-right">Restant dû</th>
                      <th class="pb-1 font-medium text-right">Ancienneté</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="doc in client.unpaid_docs"
                      :key="doc.id"
                      class="border-b border-gray-100 dark:border-gray-700/50"
                    >
                      <td class="py-1.5 pr-3 font-medium text-gray-900 dark:text-white">{{ doc.reference }}</td>
                      <td class="py-1.5 pr-3 text-gray-500 dark:text-gray-400">{{ formatDocType(doc.type) }}</td>
                      <td class="py-1.5 pr-3 text-gray-500 dark:text-gray-400">{{ doc.date }}</td>
                      <td class="py-1.5 pr-3 text-right text-gray-900 dark:text-white">{{ fmt(doc.total) }}</td>
                      <td class="py-1.5 pr-3 text-right font-medium text-red-600">{{ fmt(doc.due) }}</td>
                      <td class="py-1.5 text-right">
                        <span
                          class="inline-flex items-center rounded-full px-1.5 py-0.5 text-xs font-medium"
                          :class="doc.age_days > 30 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : doc.age_days > 15 ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                        >
                          {{ doc.age_days }}j
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
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
  { key: 'pos', label: 'POS Journalier' },
  { key: 'credit', label: 'Créances' },
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
  pos_date: now.toISOString().split('T')[0],
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
    let endpoint: string
    let params: Record<string, string> = {}

    switch (activeTab.value) {
      case 'stock':
        endpoint = '/reports/stock'
        if (filters.warehouse_id) params.warehouse_id = filters.warehouse_id
        break
      case 'pos':
        endpoint = '/pos/report/daily'
        params.date = filters.pos_date
        break
      case 'credit':
        endpoint = '/reports/credit-clients'
        break
      default:
        endpoint = `/reports/${activeTab.value}`
        params = { from: filters.from, to: filters.to }
    }

    const resp = await http.get(endpoint, { params })
    // POS daily report returns { data: null } when no sessions found
    if (activeTab.value === 'pos' && resp.data?.data === null) {
      toast.error('Aucune session POS fermée pour cette date.')
      return
    }
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
    let endpoint: string
    let params: Record<string, string> = {}
    let filename: string

    switch (activeTab.value) {
      case 'stock':
        endpoint = '/reports/stock/pdf'
        if (filters.warehouse_id) params.warehouse_id = filters.warehouse_id
        filename = `Rapport_Stock_${new Date().toISOString().split('T')[0]}.pdf`
        break
      case 'pos':
        endpoint = '/pos/report/daily'
        params = { date: filters.pos_date, format: 'pdf' }
        filename = `Rapport_POS_${filters.pos_date}.pdf`
        break
      default:
        endpoint = `/reports/${activeTab.value}/pdf`
        params = { from: filters.from, to: filters.to }
        filename = `Rapport_${ucfirst(activeTab.value)}_${filters.from}_${filters.to}.pdf`
    }

    const resp = await http.get(endpoint, { params, responseType: 'blob' })
    const blob = new Blob([resp.data], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
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

function formatTime(dt: string): string {
  if (!dt) return '—'
  const d = new Date(dt)
  return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

const docTypeLabels: Record<string, string> = {
  InvoiceSale: 'Facture',
  DeliveryNote: 'Bon de livraison',
  TicketSale: 'Ticket POS',
  QuoteSale: 'Devis',
}

function formatDocType(type: string): string {
  return docTypeLabels[type] || type
}
</script>
