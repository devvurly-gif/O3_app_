<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import http from '@/services/http'
import BaseSkeleton from '@/components/BaseSkeleton.vue'
import BaseModal from '@/components/BaseModal.vue'
import { useFormat } from '@/composables/useFormat'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip)

const { date: fmtDate } = useFormat()

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
  loadWidgetPrefs()
  refreshInterval = setInterval(() => fetchDashboard(true), REFRESH_INTERVAL)
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})

// ── Widget manager ──────────────────────────────────────
// Hidden keys are stored server-side per user; anything not listed is shown,
// so widgets added in a later release appear by default.
const PANEL_WIDGETS: Array<{ key: string; label: string }> = [
  { key: 'pos_today', label: "POS aujourd'hui" },
  { key: 'revenue_chart', label: "Chiffre d'affaires — 6 derniers mois" },
  { key: 'payment_methods', label: 'Paiements du mois' },
  { key: 'sales_purchases_chart', label: 'Ventes vs Achats — 6 derniers mois' },
  { key: 'top_products', label: 'Top produits du mois' },
  { key: 'low_stock', label: 'Stock bas' },
  { key: 'credit_clients', label: 'Clients En Compte' },
  { key: 'top_clients', label: 'Top clients du mois' },
  { key: 'bl_to_invoice', label: 'BL à facturer' },
  { key: 'overdue_invoices', label: 'Factures en retard' },
  { key: 'pending_orders', label: 'Factures en attente' },
  { key: 'recent_documents', label: 'Derniers documents' },
]

const hiddenWidgets = ref<string[]>([])
const showWidgetManager = ref(false)
const savingWidgets = ref(false)

function isVisible(key: string): boolean {
  return !hiddenWidgets.value.includes(key)
}

/** Keeps a row from leaving an empty gap once all its panels are hidden. */
function anyVisible(...keys: string[]): boolean {
  return keys.some(isVisible)
}

function toggleWidget(key: string) {
  hiddenWidgets.value = isVisible(key)
    ? [...hiddenWidgets.value, key]
    : hiddenWidgets.value.filter((k) => k !== key)
}

// Card labels come from the payload itself, so the manager never drifts from
// what the dashboard actually renders.
const cardWidgets = computed(() =>
  (data.value?.cards ?? []).map((c: any) => ({ key: c.key, label: c.label })),
)

const hiddenCount = computed(() => hiddenWidgets.value.length)

const widgetSections = computed(() => [
  { title: 'Indicateurs', items: cardWidgets.value },
  { title: 'Graphiques et listes', items: PANEL_WIDGETS },
])

async function loadWidgetPrefs() {
  try {
    const { data: prefs } = await http.get('/dashboard/widgets')
    hiddenWidgets.value = Array.isArray(prefs?.hidden) ? prefs.hidden : []
  } catch {
    /* a failed preference load must not blank the dashboard — show everything */
  }
}

async function saveWidgetPrefs() {
  savingWidgets.value = true
  try {
    await http.put('/dashboard/widgets', { hidden: hiddenWidgets.value })
    showWidgetManager.value = false
  } catch {
    error.value = "Impossible d'enregistrer la disposition des widgets."
  } finally {
    savingWidgets.value = false
  }
}

function showAllWidgets() {
  hiddenWidgets.value = []
}

// ── Computed ────────────────────────────────────────────
function cardsInGroup(group: string) {
  return (data.value?.cards ?? []).filter((c: any) => (c.group ?? 'pill') === group && isVisible(c.key))
}

const mainCards = computed(() => cardsInGroup('main'))
const secondaryCards = computed(() => cardsInGroup('secondary'))
const pillCards = computed(() => cardsInGroup('pill'))

// ── Chart.js revenue line config ───────────────────────
const revenueChartKey = ref(0)
const revenueChartData = computed(() => {
  const chart = data.value?.revenue_chart ?? []
  return {
    labels: chart.map((r: any) => r.month?.slice(5) ?? ''),
    datasets: [
      {
        label: 'Chiffre d\'affaires',
        data: chart.map((r: any) => r.total),
        borderColor: '#F97316',
        backgroundColor: (ctx: any) => {
          const canvas = ctx.chart?.ctx
          if (!canvas) return 'rgba(249,115,22,0.1)'
          const gradient = canvas.createLinearGradient(0, 0, 0, ctx.chart.height)
          gradient.addColorStop(0, 'rgba(249,115,22,0.25)')
          gradient.addColorStop(1, 'rgba(249,115,22,0)')
          return gradient
        },
        fill: true,
        tension: 0.4,
        borderWidth: 3,
        pointRadius: 5,
        pointBackgroundColor: '#F97316',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointHoverRadius: 7,
      },
    ],
  }
})

const revenueChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    tooltip: {
      callbacks: {
        label: (ctx: any) => fmtCurrency(ctx.parsed.y),
      },
    },
    legend: { display: false },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: '#9CA3AF', font: { size: 11 } },
    },
    y: {
      grid: { color: 'rgba(156,163,175,0.15)' },
      ticks: {
        color: '#9CA3AF',
        font: { size: 10 },
        callback: (v: any) => v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v,
      },
      beginAtZero: true,
    },
  },
  interaction: { intersect: false, mode: 'index' as const },
}))

watch(() => data.value?.revenue_chart, () => { revenueChartKey.value++ })

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
  margin_today: '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.306a11.95 11.95 0 015.814-5.518l2.74-1.22m0 0l-5.94-2.281m5.94 2.28l-2.28 5.941"/>',
  invoices_month: '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>',
  products: '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0v10l-8 4m0-14L4 17m8 4V10"/>',
}

const cardColors: Record<string, { bg: string; text: string }> = {
  ca_month: { bg: 'bg-emerald-50 dark:bg-emerald-900/30', text: 'text-emerald-600 dark:text-emerald-400' },
  purchases_month: { bg: 'bg-orange-50 dark:bg-orange-900/30', text: 'text-orange-600 dark:text-orange-400' },
  payments_month: { bg: 'bg-sky-50 dark:bg-sky-900/30', text: 'text-sky-600 dark:text-sky-400' },
  outstanding: { bg: 'bg-red-50 dark:bg-red-900/30', text: 'text-red-600 dark:text-red-400' },
  today_sales: { bg: 'bg-cyan-50 dark:bg-cyan-900/30', text: 'text-cyan-600 dark:text-cyan-400' },
  margin_today: { bg: 'bg-teal-50 dark:bg-teal-900/30', text: 'text-teal-600 dark:text-teal-400' },
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
  confirmed: 'bg-orange-100 dark:bg-orange-900/40 text-orange-600 dark:text-orange-300',
  converted: 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300',
  delivered: 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
  pending: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
  partial: 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300',
  paid: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
  cancelled: 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300',
}

const paymentColors: Record<string, string> = {
  cash: 'bg-green-500',
  card: 'bg-sky-500',
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
        <svg v-if="refreshing" class="w-4 h-4 text-orange-500 animate-spin" fill="none" viewBox="0 0 24 24">
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
        <button
          class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
          title="Choisir les widgets affichés"
          @click="showWidgetManager = true"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" />
            <rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" />
          </svg>
          <span class="hidden sm:inline">Widgets</span>
          <span
            v-if="hiddenCount"
            class="px-1.5 rounded-full bg-orange-100 dark:bg-orange-900/40 text-orange-600 dark:text-orange-300 text-[10px] font-bold"
          >
            {{ hiddenCount }} masqué{{ hiddenCount > 1 ? 's' : '' }}
          </span>
        </button>
      </div>
    </div>

    <!-- Widget manager -->
    <BaseModal v-model="showWidgetManager" title="Gérer les widgets" size="xl">
      <div class="space-y-5 max-h-[60vh] overflow-y-auto pr-1">
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Décochez ce que vous ne voulez pas voir. Le choix est enregistré sur votre compte et vous suit
          d'un appareil à l'autre.
        </p>

        <div v-for="section in widgetSections" :key="section.title">
          <h4 class="text-xs font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">
            {{ section.title }}
          </h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
            <label
              v-for="w in section.items"
              :key="w.key"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg border cursor-pointer transition text-sm"
              :class="isVisible(w.key)
                ? 'border-orange-200 dark:border-orange-800 bg-orange-50/60 dark:bg-orange-900/20 text-gray-800 dark:text-gray-200'
                : 'border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500'"
            >
              <input
                type="checkbox"
                class="w-4 h-4 rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                :checked="isVisible(w.key)"
                @change="toggleWidget(w.key)"
              />
              <span class="truncate">{{ w.label }}</span>
            </label>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-between gap-3 w-full">
          <button
            class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition"
            @click="showAllWidgets"
          >
            Tout afficher
          </button>
          <div class="flex items-center gap-2">
            <button
              class="px-4 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
              @click="showWidgetManager = false"
            >
              Annuler
            </button>
            <button
              class="px-4 py-2 text-sm font-semibold rounded-lg bg-orange-700 hover:bg-orange-800 text-white transition disabled:opacity-50"
              :disabled="savingWidgets"
              @click="saveWidgetPrefs"
            >
              {{ savingWidgets ? 'Enregistrement...' : 'Enregistrer' }}
            </button>
          </div>
        </div>
      </template>
    </BaseModal>

    <BaseSkeleton v-if="loading" type="dashboard" />

    <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg p-4 text-sm">
      {{ error }}
    </div>

    <template v-else-if="data">
      <!-- ══ ROW 1: Main KPI cards (4) ══ -->
      <div v-if="mainCards.length" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
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
      <div v-if="secondaryCards.length" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
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
            <p v-if="card.meta?.rate != null" class="text-[10px] text-gray-400 dark:text-gray-500 truncate">
              {{ card.meta.rate }} % du CA HT
              <span v-if="card.meta.uncosted_lines" class="text-amber-500" :title="`${card.meta.uncosted_lines} ligne(s) sans coût de revient`">
                · {{ card.meta.uncosted_lines }} sans coût
              </span>
            </p>
            <!--
              Ventes en compte : accordées sur la période, pas encaissées.
              Affichées sous le montant encaissé pour que le total vendu reste
              lisible sans confondre les deux.
            -->
            <p
              v-if="card.meta?.credit_granted"
              class="text-[10px] text-amber-600 dark:text-amber-500 truncate"
              title="Ventes en compte accordées sur la période — non encaissées"
            >
              + {{ fmtCurrency(card.meta.credit_granted) }} en compte
            </p>
          </div>
        </div>
      </div>

      <!-- Pill counters -->
      <div v-if="pillCards.length || (isVisible('pos_today') && data?.pos_today)" class="flex flex-wrap gap-3">
        <div v-for="card in pillCards" :key="card.key" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2.5 flex items-center gap-2 shadow-sm">
          <span class="text-xl font-bold text-gray-900 dark:text-white">{{ fmtNumber(card.value) }}</span>
          <span class="text-sm text-gray-500 dark:text-gray-400">{{ card.label }}</span>
        </div>
        <!-- POS today inline -->
        <div v-if="data?.pos_today && isVisible('pos_today')" class="bg-cyan-50 dark:bg-cyan-900/20 border border-cyan-200 dark:border-cyan-800 rounded-lg px-4 py-2.5 flex items-center gap-3 shadow-sm">
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
      <div v-if="anyVisible('revenue_chart', 'payment_methods')" class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
        <!-- Revenue chart (12 months) -->
        <div v-if="isVisible('revenue_chart')" class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Chiffre d'affaires — 6 derniers mois</h3>
          </div>
          <div class="px-5 py-4">
            <div v-if="data?.revenue_chart?.length" class="h-52">
              <Line :key="revenueChartKey" :data="revenueChartData" :options="revenueChartOptions" />
            </div>
          </div>
        </div>

        <!-- Payment methods donut -->
        <div v-if="isVisible('payment_methods')" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
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
      <div v-if="anyVisible('sales_purchases_chart', 'top_products')" class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
        <!-- Sales vs Purchases (6 months) -->
        <div v-if="isVisible('sales_purchases_chart')" class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-4">
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Ventes vs Achats — 6 derniers mois</h3>
            <div class="flex items-center gap-3 ml-auto text-xs">
              <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-orange-500"></span> Ventes</span>
              <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-teal-500"></span> Achats</span>
            </div>
          </div>
          <div class="px-5 py-4">
            <div class="flex items-end gap-3 h-40">
              <div v-for="bar in (data?.sales_purchases_chart ?? [])" :key="bar.label" class="flex-1 h-full flex gap-1 items-end">
                <div class="flex-1 h-full group relative flex items-end">
                  <div
                    class="bg-orange-500 dark:bg-orange-400 rounded-t w-full hover:opacity-80 transition"
                    :style="{ height: (bar.sales / spChartMax) * 100 + '%', minHeight: bar.sales > 0 ? '4px' : '1px', maxWidth: '28px', margin: '0 auto' }"
                  />
                  <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
                    Ventes: {{ fmtCurrency(bar.sales) }}
                  </div>
                </div>
                <div class="flex-1 h-full group relative flex items-end">
                  <div
                    class="bg-teal-500 dark:bg-teal-400 rounded-t w-full hover:opacity-80 transition"
                    :style="{ height: (bar.purchases / spChartMax) * 100 + '%', minHeight: bar.purchases > 0 ? '4px' : '1px', maxWidth: '28px', margin: '0 auto' }"
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
        <div v-if="isVisible('top_products')" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Top produits du mois</h3>
          </div>
          <ul v-if="data?.top_products?.length" class="divide-y divide-gray-100 dark:divide-gray-700 max-h-64 overflow-y-auto">
            <li v-for="(p, idx) in (data?.top_products ?? [])" :key="p.product_id" class="flex items-center gap-3 px-5 py-2.5">
              <span class="w-6 h-6 rounded-full bg-orange-50 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 text-xs font-bold flex items-center justify-center shrink-0">
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
      <div v-if="anyVisible('low_stock', 'credit_clients', 'top_clients')" class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
        <!-- Low stock alerts -->
        <div v-if="isVisible('low_stock')" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
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
        <div v-if="isVisible('credit_clients')" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
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
        <div v-if="isVisible('top_clients')" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
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
      <div v-if="anyVisible('bl_to_invoice', 'overdue_invoices', 'pending_orders')" class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
        <!-- BL à facturer -->
        <div v-if="isVisible('bl_to_invoice')" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
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
        <div v-if="isVisible('overdue_invoices')" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
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
                  <span class="text-red-500 dark:text-red-400 font-medium ml-1">Échue le {{ fmtDate(inv.due_at) }}</span>
                </p>
              </div>
              <span class="text-sm font-bold text-red-600 dark:text-red-400 shrink-0">{{ fmtCurrency(inv.footer?.amount_due ?? 0) }}</span>
            </div>
          </div>
          <div v-else class="px-5 py-8 text-center text-sm text-emerald-500 dark:text-emerald-400">✓ Aucun retard</div>
        </div>

        <!-- Pending invoices -->
        <div v-if="isVisible('pending_orders')" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
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
      <div v-if="isVisible('recent_documents')" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
          <h3 class="font-semibold text-gray-800 dark:text-gray-200">Derniers documents</h3>
        </div>
        <template v-if="data?.recent_documents?.length">
          <!-- Desktop table -->
          <div class="hidden sm:block overflow-x-auto">
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
          <!-- Mobile cards -->
          <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-700">
            <div v-for="doc in (data?.recent_documents ?? [])" :key="doc.id" class="px-4 py-3 space-y-1.5">
              <div class="flex items-center justify-between gap-2">
                <span class="font-mono text-sm font-medium text-gray-800 dark:text-gray-200">{{ doc.reference }}</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-medium" :class="statusStyles[doc.status] ?? 'bg-gray-100 text-gray-700'">
                  {{ statusLabels[doc.status] ?? doc.status }}
                </span>
              </div>
              <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{{ docTypeLabels[doc.document_type] ?? doc.document_type }} · {{ doc.third_partner?.tp_title ?? '—' }}</span>
                <span>{{ timeAgo(doc.created_at) }}</span>
              </div>
              <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                {{ doc.footer ? fmtCurrency(doc.footer.total_ttc) : '—' }}
              </div>
            </div>
          </div>
        </template>
        <div v-else class="px-5 py-8 text-center text-sm text-gray-400 dark:text-gray-500">Aucun document</div>
      </div>
    </template>
  </div>
</template>
