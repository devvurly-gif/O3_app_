<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import http from '@/services/http'
import BaseSkeleton from '@/components/BaseSkeleton.vue'

const data = ref<Record<string, any> | null>(null)
const loading = ref(true)
const refreshing = ref(false)
const error = ref<string | null>(null)
const lastRefresh = ref<Date | null>(null)
let refreshInterval: ReturnType<typeof setInterval> | null = null

const REFRESH_INTERVAL = 30_000 // 30 seconds

async function fetchDashboard(silent = false) {
  if (!silent) loading.value = true
  else refreshing.value = true
  error.value = null
  try {
    const res = await http.get('/dashboard', { params: { _nocache: Date.now() } })
    data.value = res.data
    lastRefresh.value = new Date()
  } catch {
    if (!silent) error.value = 'Impossible de charger le tableau de bord.'
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

onMounted(() => {
  fetchDashboard()
  refreshInterval = setInterval(() => fetchDashboard(true), REFRESH_INTERVAL)
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})

// ── Computed ────────────────────────────────────────────
const mainCards = computed(() => data.value?.cards?.slice(0, 4) ?? [])
const secondaryCards = computed(() => data.value?.cards?.slice(4, 8) ?? [])
const pillCards = computed(() => data.value?.cards?.slice(8) ?? [])

const chartMax = computed(() => {
  if (!data.value?.revenue_chart) return 1
  return Math.max(...data.value.revenue_chart.map((r: any) => r.total), 1)
})

const spChartMax = computed(() => {
  if (!data.value?.sales_purchases_chart) return 1
  return Math.max(
    ...data.value.sales_purchases_chart.flatMap((r: any) => [r.sales, r.purchases]),
    1,
  )
})

const paymentTotal = computed(() =>
  (data.value?.payment_methods ?? []).reduce((s: number, p: any) => s + p.total, 0),
)

// ── Helpers ─────────────────────────────────────────────
function fmtCurrency(v: any) {
  return Number(v).toLocaleString('fr-MA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' MAD'
}
function fmtNumber(v: any) {
  return Number(v).toLocaleString('fr-MA')
}
function trendClass(trend: any) {
  if (trend === null || trend === undefined) return 'text-gray-400'
  return trend >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400'
}
function trendLabel(trend: any) {
  if (trend === null || trend === undefined) return ''
  const sign = trend >= 0 ? '+' : ''
  return `${sign}${trend}%`
}
function timeAgo(dateStr: string) {
  const d = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - d.getTime()
  const diffMin = Math.floor(diffMs / 60000)
  if (diffMin < 60) return `${diffMin}min`
  const diffH = Math.floor(diffMin / 60)
  if (diffH < 24) return `${diffH}h`
  return `${Math.floor(diffH / 24)}j`
}

const cardIcons: Record<string, string> = {
  ca_month: '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/>',
  purchases_month: '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>',
  payments_month: '<path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>',
  outstanding: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>',
  today_sales: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>',
  margin_month: '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>',
  invoices_month: '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>',
  products: '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0v10l-8 4m0-14L4 17m8 4V10"/>',
}

const cardColors: Record<string, { bg: string; text: string }> = {
  ca_month: { bg: 'bg-emerald-50 dark:bg-emerald-900/30', text: 'text-emerald-600 dark:text-emerald-400' },
  purchases_month: { bg: 'bg-orange-50 dark:bg-orange-900/30', text: 'text-orange-600 dark:text-orange-400' },
  payments_month: { bg: 'bg-blue-50 dark:bg-blue-900/30', text: 'text-blue-600 dark:text-blue-400' },
  outstanding: { bg: 'bg-red-50 dark:bg-red-900/30', text: 'text-red-600 dark:text-red-400' },
  today_sales: { bg: 'bg-cyan-50 dark:bg-cyan-900/30', text: 'text-cyan-600 dark:text-cyan-400' },
  margin_month: { bg: 'bg-emerald-50 dark:bg-emerald-900/30', text: 'text-emerald-600 dark:text-emerald-400' },
  invoices_month: { bg: 'bg-violet-50 dark:bg-violet-900/30', text: 'text-violet-600 dark:text-violet-400' },
  products: { bg: 'bg-gray-50 dark:bg-gray-700', text: 'text-gray-600 dark:text-gray-400' },
}

const docTypeLabels: Record<string, string> = {
  QuoteSale: 'Devis',
  CustomerOrder: 'BC Client',
  DeliveryNote: 'BL',
  InvoiceSale: 'Facture',
  CreditNoteSale: 'Avoir',
  ReturnSale: 'Retour',
  TicketSale: 'Ticket POS',
  PurchaseOrder: 'BC Achat',
  ReceiptNotePurchase: 'BR',
  InvoicePurchase: 'Fact. Achat',
  CreditNotePurchase: 'Avoir Fourn.',
  ReturnPurchase: 'Retour Fourn.',
  StockEntry: 'Entrée Stock',
  StockExit: 'Sortie Stock',
  StockTransfer: 'Transfert',
  StockAdjustmentNote: 'Ajustement',
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

const statusStyles: Record<string, string> = {
  draft: 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
  confirmed: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
  converted: 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300',
  delivered: 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
  pending: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
  partial: 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300',
  paid: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
  cancelled: 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300',
}

const paymentColors: Record<string, string> = {
  cash: 'bg-green-500',
  card: 'bg-blue-500',
  credit: 'bg-amber-500',
  cheque: 'bg-purple-500',
  bank_transfer: 'bg-cyan-500',
  effet: 'bg-pink-500',
}
</script>

<template>
  <div class="space-y-4 sm:space-y-6">
    <!-- Page header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">Tableau de bord</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Vue d'ensemble de votre activité</p>
      </div>
      <div class="flex items-center gap-3">
        <div class="flex items-center gap-1.5">
          <span class="relative flex h-2.5 w-2.5">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
          </span>
          <span class="text-xs text-gray-400 dark:text-gray-500">Live</span>
        </div>
        <svg v-if="refreshing" class="w-4 h-4 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <button
          class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
          title="Rafraîchir"
          :disabled="refreshing"
          @click="fetchDashboard(true)"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
          </svg>
        </button>
      </div>
    </div>

    <BaseSkeleton v-if="loading" type="dashboard" />

    <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg p-4 text-sm">
      {{ error }}
    </div>

    <template v-else-if="data">
      <!-- ══ ROW 1: Main KPI cards (4) ══ -->
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
        <div
          v-for="card in mainCards"
          :key="card.key"
          class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-3 py-3 sm:px-5 sm:py-4 flex items-center gap-3 sm:gap-4 shadow-sm"
        >
          <div class="w-11 h-11 rounded-lg flex items-center justify-center shrink-0" :class="cardColors[card.key]?.bg">
            <svg class="w-5 h-5" :class="cardColors[card.key]?.text" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" v-html="cardIcons[card.key] ?? ''" />
          </div>
          <div class="min-w-0">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">{{ card.label }}</p>
            <p class="text-base sm:text-xl font-bold text-gray-900 dark:text-white leading-tight truncate">
              {{ card.currency ? fmtCurrency(card.value) : fmtNumber(card.value) }}
            </p>
            <p v-if="card.trend != null" class="text-xs mt-0.5" :class="trendClass(card.trend)">
              {{ trendLabel(card.trend) }} vs mois précédent
            </p>
          </div>
        </div>
      </div>

      <!-- ══ ROW 2: Secondary KPI cards (4) ══ -->
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
        <div
          v-for="card in secondaryCards"
          :key="card.key"
          class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-3 py-3 sm:px-4 sm:py-3 flex items-center gap-3 shadow-sm"
        >
          <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" :class="cardColors[card.key]?.bg ?? 'bg-gray-50 dark:bg-gray-700'">
            <svg class="w-4 h-4" :class="cardColors[card.key]?.text ?? 'text-gray-500'" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" v-html="cardIcons[card.key] ?? ''" />
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400 truncate">{{ card.label }}</p>
            <p class="text-sm sm:text-lg font-bold text-gray-900 dark:text-white leading-tight truncate">
              {{ card.currency ? fmtCurrency(card.value) : fmtNumber(card.value) }}
            </p>
            <p v-if="card.trend != null" class="text-[10px]" :class="trendClass(card.trend)">
              {{ trendLabel(card.trend) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Pill counters -->
      <div v-if="pillCards.length" class="flex flex-wrap gap-3">
        <div v-for="card in pillCards" :key="card.key" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2.5 flex items-center gap-2 shadow-sm">
          <span class="text-xl font-bold text-gray-900 dark:text-white">{{ fmtNumber(card.value) }}</span>
          <span class="text-sm text-gray-500 dark:text-gray-400">{{ card.label }}</span>
        </div>
        <!-- POS today inline -->
        <div v-if="data?.pos_today" class="bg-cyan-50 dark:bg-cyan-900/20 border border-cyan-200 dark:border-cyan-800 rounded-lg px-4 py-2.5 flex items-center gap-3 shadow-sm">
          <svg class="w-5 h-5 text-cyan-600 dark:text-cyan-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.15c0 .415.336.75.75.75z"/>
          </svg>
          <div>
            <span class="text-sm font-bold text-cyan-700 dark:text-cyan-300">POS aujourd'hui:</span>
            <span class="text-sm text-cyan-600 dark:text-cyan-400 ml-1">{{ data.pos_today.ticket_count }} tickets</span>
            <span class="text-sm font-semibold text-cyan-700 dark:text-cyan-300 ml-2">{{ fmtCurrency(data.pos_today.total_ttc) }}</span>
          </div>
          <div v-if="data?.pos_today?.active_sessions?.length" class="flex items-center gap-1 ml-2">
            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
            <span class="text-xs text-cyan-600 dark:text-cyan-400">{{ data?.pos_today?.active_sessions?.length }} session(s) active(s)</span>
          </div>
        </div>
      </div>

      <!-- ══ ROW 3: Revenue chart + Sales vs Purchases ══ -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
        <!-- Revenue chart (12 months) -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Chiffre d'affaires — 6 derniers mois</h3>
          </div>
          <div class="px-5 py-4">
            <div class="flex items-end gap-1 h-48">
              <div v-for="bar in (data?.revenue_chart ?? [])" :key="bar.month" class="flex-1 group relative">
                <div
                  class="bg-blue-500 dark:bg-blue-400 hover:bg-blue-600 dark:hover:bg-blue-300 rounded-t transition-colors mx-auto"
                  :style="{ height: (bar.total / chartMax) * 100 + '%', minHeight: bar.total > 0 ? '4px' : '2px', maxWidth: '24px' }"
                />
                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block bg-gray-900 dark:bg-gray-600 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
                  {{ bar.label }}: {{ fmtCurrency(bar.total) }}
                </div>
              </div>
            </div>
            <div class="flex gap-1 mt-2">
              <div v-for="bar in (data?.revenue_chart ?? [])" :key="'l-' + bar.month" class="flex-1 text-center text-[9px] text-gray-400 dark:text-gray-500 truncate">
                {{ bar.month.slice(5) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Payment methods donut -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Paiements du mois</h3>
          </div>
          <div v-if="data?.payment_methods?.length" class="px-5 py-4 space-y-3">
            <!-- Stacked bar -->
            <div class="h-4 rounded-full overflow-hidden flex bg-gray-100 dark:bg-gray-700">
              <div
                v-for="pm in (data?.payment_methods ?? [])"
                :key="pm.method"
                :class="paymentColors[pm.method] ?? 'bg-gray-400'"
                :style="{ width: (pm.total / paymentTotal) * 100 + '%' }"
                class="h-full transition-all"
              />
            </div>
            <!-- Legend -->
            <div class="space-y-2">
              <div v-for="pm in (data?.payment_methods ?? [])" :key="'pm-' + pm.method" class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                  <span class="w-3 h-3 rounded-full shrink-0" :class="paymentColors[pm.method] ?? 'bg-gray-400'" />
                  <span class="text-gray-600 dark:text-gray-400">{{ pm.label }}</span>
                </div>
                <div class="text-right">
                  <span class="font-semibold text-gray-800 dark:text-gray-200">{{ fmtCurrency(pm.total) }}</span>
                  <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">({{ pm.count }})</span>
                </div>
              </div>
            </div>
            <div class="border-t border-gray-100 dark:border-gray-700 pt-2 flex justify-between text-sm font-semibold">
              <span class="text-gray-600 dark:text-gray-400">Total</span>
              <span class="text-gray-900 dark:text-white">{{ fmtCurrency(paymentTotal) }}</span>
            </div>
          </div>
          <div v-else class="px-5 py-8 text-center text-sm text-gray-400 dark:text-gray-500">Aucun paiement ce mois</div>
        </div>
      </div>

      <!-- ══ ROW 4: Sales vs Purchases + Top products ══ -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
        <!-- Sales vs Purchases (6 months) -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-4">
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Ventes vs Achats — 6 derniers mois</h3>
            <div class="flex items-center gap-3 ml-auto text-xs">
              <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-500"></span> Ventes</span>
              <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-orange-500"></span> Achats</span>
            </div>
          </div>
          <div class="px-5 py-4">
            <div class="flex items-end gap-3 h-40">
              <div v-for="bar in (data?.sales_purchases_chart ?? [])" :key="bar.label" class="flex-1 flex gap-1 items-end">
                <div class="flex-1 group relative">
                  <div
                    class="bg-blue-500 dark:bg-blue-400 rounded-t mx-auto hover:opacity-80 transition"
                    :style="{ height: (bar.sales / spChartMax) * 100 + '%', minHeight: bar.sales > 0 ? '3px' : '1px', maxWidth: '28px' }"
                  />
                  <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
                    Ventes: {{ fmtCurrency(bar.sales) }}
                  </div>
                </div>
                <div class="flex-1 group relative">
                  <div
                    class="bg-orange-500 dark:bg-orange-400 rounded-t mx-auto hover:opacity-80 transition"
                    :style="{ height: (bar.purchases / spChartMax) * 100 + '%', minHeight: bar.purchases > 0 ? '3px' : '1px', maxWidth: '28px' }"
                  />
                  <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
                    Achats: {{ fmtCurrency(bar.purchases) }}
                  </div>
                </div>
              </div>
            </div>
            <div class="flex gap-3 mt-2">
              <div v-for="bar in (data?.sales_purchases_chart ?? [])" :key="'sl-' + bar.label" class="flex-1 text-center text-[10px] text-gray-400 dark:text-gray-500 truncate">
                {{ bar.label }}
              </div>
            </div>
          </div>
        </div>

        <!-- Top products -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Top produits du mois</h3>
          </div>
          <ul v-if="data?.top_products?.length" class="divide-y divide-gray-100 dark:divide-gray-700 max-h-64 overflow-y-auto">
            <li v-for="(p, idx) in (data?.top_products ?? [])" :key="p.product_id" class="flex items-center gap-3 px-5 py-2.5">
              <span class="w-6 h-6 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-bold flex items-center justify-center shrink-0">
                {{ Number(idx) + 1 }}
              </span>
              <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-800 dark:text-gray-200 truncate">{{ p.designation }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ fmtNumber(p.total_qty) }} unités</p>
              </div>
              <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ fmtCurrency(p.total_revenue) }}</span>
            </li>
          </ul>
          <div v-else class="px-5 py-8 text-center text-sm text-gray-400 dark:text-gray-500">Aucune vente ce mois</div>
        </div>
      </div>

      <!-- ══ ROW 5: Low stock + Credit clients + Top clients ══ -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
        <!-- Low stock alerts -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Stock bas</h3>
            <span v-if="data?.low_stock?.length" class="ml-auto text-xs bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 px-2 py-0.5 rounded-full font-medium">
              {{ data.low_stock.length }}
            </span>
          </div>
          <div v-if="data?.low_stock?.length" class="max-h-64 overflow-y-auto">
            <div v-for="(item, i) in (data?.low_stock ?? [])" :key="i" class="flex items-center justify-between px-5 py-2 border-b border-gray-50 dark:border-gray-700/50 last:border-0">
              <div class="min-w-0">
                <p class="text-sm text-gray-800 dark:text-gray-200 truncate">{{ item.product }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ item.warehouse }}</p>
              </div>
              <span
                class="text-sm font-bold px-2 py-0.5 rounded shrink-0"
                :class="item.stockLevel <= 0 ? 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30' : 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30'"
              >
                {{ item.stockLevel }}
              </span>
            </div>
          </div>
          <div v-else class="px-5 py-8 text-center text-sm text-emerald-500 dark:text-emerald-400">✓ Tous les stocks OK</div>
        </div>

        <!-- Credit clients (en compte) -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>
            </svg>
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Clients En Compte</h3>
          </div>
          <div v-if="data?.credit_clients?.length" class="max-h-64 overflow-y-auto">
            <div v-for="client in (data?.credit_clients ?? [])" :key="client.id" class="px-5 py-2.5 border-b border-gray-50 dark:border-gray-700/50 last:border-0">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ client.tp_title }}</span>
                <span class="text-sm font-bold text-amber-600 dark:text-amber-400 shrink-0">{{ fmtCurrency(client.encours_actuel) }}</span>
              </div>
              <div v-if="client.seuil_credit > 0" class="flex items-center gap-2">
                <div class="flex-1 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="(client.usage_pct ?? 0) > 80 ? 'bg-red-500' : (client.usage_pct ?? 0) > 50 ? 'bg-amber-500' : 'bg-emerald-500'"
                    :style="{ width: Math.min(client.usage_pct ?? 0, 100) + '%' }"
                  />
                </div>
                <span class="text-[10px] text-gray-400 dark:text-gray-500 shrink-0">{{ client.usage_pct ?? 0 }}% / {{ fmtCurrency(client.seuil_credit) }}</span>
              </div>
            </div>
          </div>
          <div v-else class="px-5 py-8 text-center text-sm text-gray-400 dark:text-gray-500">Aucun encours</div>
        </div>

        <!-- Top clients -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Top clients du mois</h3>
          </div>
          <ul v-if="data?.top_clients?.length" class="divide-y divide-gray-100 dark:divide-gray-700">
            <li v-for="(client, idx) in (data?.top_clients ?? [])" :key="client.id" class="flex items-center gap-3 px-5 py-2.5">
              <span class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 text-xs font-bold flex items-center justify-center shrink-0">
                {{ Number(idx) + 1 }}
              </span>
              <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-800 dark:text-gray-200 font-medium truncate">{{ client.tp_title }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ client.invoice_count }} doc(s)</p>
              </div>
              <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ fmtCurrency(client.total_revenue) }}</span>
            </li>
          </ul>
          <div v-else class="px-5 py-8 text-center text-sm text-gray-400 dark:text-gray-500">Aucun client ce mois</div>
        </div>
      </div>

      <!-- ══ ROW 6: BL to invoice + Overdue + Pending invoices ══ -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
        <!-- BL à facturer -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
            <svg class="w-5 h-5 text-violet-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5A3.375 3.375 0 006.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0015 2.25h-1.5a2.251 2.251 0 00-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 00-9-9z"/>
            </svg>
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">BL à facturer</h3>
            <span v-if="data?.bl_to_invoice?.length" class="ml-auto text-xs bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300 px-2 py-0.5 rounded-full font-medium">
              {{ data.bl_to_invoice.length }}
            </span>
          </div>
          <div v-if="data?.bl_to_invoice?.length" class="max-h-64 overflow-y-auto divide-y divide-gray-50 dark:divide-gray-700/50">
            <div v-for="bl in (data?.bl_to_invoice ?? [])" :key="bl.id" class="flex items-center justify-between px-5 py-2.5">
              <div class="min-w-0">
                <p class="text-sm font-mono text-gray-800 dark:text-gray-200">{{ bl.reference }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ bl.third_partner?.tp_title ?? '—' }}</p>
              </div>
              <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 shrink-0">{{ bl.footer ? fmtCurrency(bl.footer.total_ttc) : '—' }}</span>
            </div>
          </div>
          <div v-else class="px-5 py-8 text-center text-sm text-emerald-500 dark:text-emerald-400">✓ Tout est facturé</div>
        </div>

        <!-- Overdue invoices -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Factures en retard</h3>
            <span v-if="data?.overdue_invoices?.length" class="ml-auto text-xs bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 px-2 py-0.5 rounded-full font-medium">
              {{ data.overdue_invoices.length }}
            </span>
          </div>
          <div v-if="data?.overdue_invoices?.length" class="max-h-64 overflow-y-auto divide-y divide-gray-50 dark:divide-gray-700/50">
            <div v-for="inv in (data?.overdue_invoices ?? [])" :key="inv.id" class="flex items-center justify-between px-5 py-2.5">
              <div class="min-w-0">
                <p class="text-sm text-gray-800 dark:text-gray-200">{{ inv.reference }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">
                  {{ inv.third_partner?.tp_title ?? '—' }}
                  <span class="text-red-500 dark:text-red-400 font-medium ml-1">Échue le {{ new Date(inv.due_at).toLocaleDateString('fr-FR') }}</span>
                </p>
              </div>
              <span class="text-sm font-bold text-red-600 dark:text-red-400 shrink-0">{{ fmtCurrency(inv.footer?.amount_due ?? 0) }}</span>
            </div>
          </div>
          <div v-else class="px-5 py-8 text-center text-sm text-emerald-500 dark:text-emerald-400">✓ Aucun retard</div>
        </div>

        <!-- Pending invoices -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Factures en attente</h3>
          </div>
          <ul v-if="data?.pending_orders?.length" class="divide-y divide-gray-100 dark:divide-gray-700 max-h-64 overflow-y-auto">
            <li v-for="inv in (data?.pending_orders ?? [])" :key="inv.id" class="flex items-center gap-3 px-5 py-2.5">
              <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-800 dark:text-gray-200 truncate">
                  {{ inv.reference }}
                  <span class="text-gray-400 dark:text-gray-500 ml-1">{{ inv.third_partner?.tp_title ?? '' }}</span>
                </p>
              </div>
              <div class="text-right shrink-0">
                <p class="text-sm font-semibold text-red-600 dark:text-red-400">{{ fmtCurrency(inv.footer?.amount_due ?? 0) }}</p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500">/ {{ fmtCurrency(inv.footer?.total_ttc ?? 0) }}</p>
              </div>
            </li>
          </ul>
          <div v-else class="px-5 py-8 text-center text-sm text-emerald-500 dark:text-emerald-400 font-medium">✓ Tout est réglé</div>
        </div>
      </div>

      <!-- ══ ROW 7: Recent documents ══ -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
          <h3 class="font-semibold text-gray-800 dark:text-gray-200">Derniers documents</h3>
        </div>
        <div v-if="data?.recent_documents?.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800/80 text-gray-500 dark:text-gray-400 uppercase text-xs">
              <tr>
                <th class="text-left px-5 py-2.5">Référence</th>
                <th class="text-left px-3 py-2.5">Type</th>
                <th class="text-left px-3 py-2.5">Tiers</th>
                <th class="text-right px-3 py-2.5">Montant</th>
                <th class="text-center px-3 py-2.5">Statut</th>
                <th class="text-right px-5 py-2.5">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="doc in (data?.recent_documents ?? [])" :key="doc.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                <td class="px-5 py-2.5 font-mono text-gray-800 dark:text-gray-200">{{ doc.reference }}</td>
                <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400">{{ docTypeLabels[doc.document_type] ?? doc.document_type }}</td>
                <td class="px-3 py-2.5 text-gray-600 dark:text-gray-300">{{ doc.third_partner?.tp_title ?? '—' }}</td>
                <td class="px-3 py-2.5 text-right font-semibold text-gray-700 dark:text-gray-300">{{ doc.footer ? fmtCurrency(doc.footer.total_ttc) : '—' }}</td>
                <td class="px-3 py-2.5 text-center">
                  <span class="px-2 py-0.5 rounded text-[10px] font-medium" :class="statusStyles[doc.status] ?? 'bg-gray-100 text-gray-700'">
                    {{ statusLabels[doc.status] ?? doc.status }}
                  </span>
                </td>
                <td class="px-5 py-2.5 text-right text-gray-400 dark:text-gray-500">{{ timeAgo(doc.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="px-5 py-8 text-center text-sm text-gray-400 dark:text-gray-500">Aucun document</div>
      </div>
    </template>
  </div>
</template>
