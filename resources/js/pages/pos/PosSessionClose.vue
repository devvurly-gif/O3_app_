<template>
  <div class="h-full flex items-center justify-center bg-gray-100 dark:bg-gray-700">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 w-full max-w-lg">
      <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Fermeture de session</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Saisissez le fond de caisse de fermeture</p>
      </div>

      <!-- Session summary -->
      <div v-if="posStore.currentSession" class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 mb-4 space-y-2 text-sm">
        <div class="flex justify-between">
          <span class="text-gray-500 dark:text-gray-400">Terminal</span>
          <span class="font-medium text-gray-900 dark:text-white">{{ posStore.currentTerminal?.name }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-500 dark:text-gray-400">Ouverture</span>
          <span class="font-medium text-gray-900 dark:text-white">
            {{ new Date(posStore.currentSession.opened_at).toLocaleString('fr-FR') }}
          </span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-500 dark:text-gray-400">Fond de caisse initial</span>
          <span class="font-medium text-gray-900 dark:text-white">{{ Number(posStore.currentSession.opening_cash).toFixed(2) }} MAD</span>
        </div>
      </div>

      <!-- Pre-close: ventes par mode de paiement -->
      <div v-if="liveStats" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 mb-6 space-y-2">
        <div class="flex items-center justify-between text-sm">
          <span class="font-semibold text-gray-900 dark:text-white">Ventes de la session</span>
          <span class="text-xs text-gray-500 dark:text-gray-400">
            {{ liveStats.total_tickets }} ticket{{ liveStats.total_tickets > 1 ? 's' : '' }}
            <span v-if="liveStats.cancelled_tickets" class="ml-1 text-red-500">
              · {{ liveStats.cancelled_tickets }} annulé{{ liveStats.cancelled_tickets > 1 ? 's' : '' }}
            </span>
          </span>
        </div>

        <div
          v-for="row in paymentMethodRows"
          :key="row.method"
          class="flex items-center justify-between text-xs px-2 py-1.5 rounded-lg"
          :class="row.bg"
        >
          <span class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full" :class="row.dot"></span>
            <span class="font-medium" :class="row.label">{{ row.title }}</span>
            <span class="text-[10px]" :class="row.muted">
              {{ row.count }} ticket{{ row.count > 1 ? 's' : '' }}
            </span>
          </span>
          <span class="font-semibold tabular-nums" :class="row.label">{{ Number(row.amount).toFixed(2) }} MAD</span>
        </div>

        <div class="flex justify-between text-xs pt-1 border-t border-gray-200 dark:border-gray-700">
          <span class="text-gray-600 dark:text-gray-400">Total TTC</span>
          <span class="font-semibold text-gray-900 dark:text-white tabular-nums">
            {{ Number(liveStats.total_ttc).toFixed(2) }} MAD
          </span>
        </div>
        <div class="flex justify-between text-xs">
          <span class="text-gray-600 dark:text-gray-400">Total encaissé</span>
          <span class="font-semibold text-gray-900 dark:text-white tabular-nums">
            {{ Number(liveStats.total_paid).toFixed(2) }} MAD
          </span>
        </div>
        <div v-if="Number(liveStats.total_credit) > 0" class="flex justify-between text-xs">
          <span class="text-amber-600 dark:text-amber-400">Dont en compte</span>
          <span class="font-semibold text-amber-700 dark:text-amber-400 tabular-nums">
            {{ Number(liveStats.total_credit).toFixed(2) }} MAD
          </span>
        </div>
      </div>

      <form class="space-y-5" @submit.prevent="close">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Fond de caisse de fermeture (MAD)</label>
          <input
            v-model.number="closingCash"
            type="number"
            step="0.01"
            min="0"
            required
            placeholder="0.00"
            class="w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes (optionnel)</label>
          <textarea
            v-model="notes"
            rows="3"
            class="w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
            placeholder="Notes de fermeture..."
          />
        </div>

        <!-- Result (after closing) -->
        <div v-if="result" class="bg-blue-50 dark:bg-blue-900/30 rounded-xl p-4 space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">Espèces attendues</span>
            <span class="font-semibold text-gray-900 dark:text-white">{{ Number(result.expected_cash).toFixed(2) }} MAD</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">Espèces comptées</span>
            <span class="font-semibold text-gray-900 dark:text-white">{{ Number(result.closing_cash).toFixed(2) }} MAD</span>
          </div>
          <div class="flex justify-between border-t border-blue-200 dark:border-blue-700 pt-2">
            <span class="text-gray-700 dark:text-gray-300 font-medium">Différence</span>
            <span
              class="font-bold"
              :class="Number(result.cash_difference) >= 0 ? 'text-green-600' : 'text-red-600'"
            >
              {{ Number(result.cash_difference) >= 0 ? '+' : '' }}{{ Number(result.cash_difference).toFixed(2) }} MAD
            </span>
          </div>
        </div>

        <!-- Email sent notice -->
        <div v-if="result" class="bg-green-50 dark:bg-green-900/20 rounded-xl p-3 flex items-center gap-2 text-sm text-green-700 dark:text-green-400">
          <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
          </svg>
          <span>Le rapport de fermeture a été envoyé par email à l'administrateur.</span>
        </div>

        <div class="flex gap-3">
          <router-link
            v-if="!result"
            to="/pos/main"
            class="flex-1 py-3 text-center rounded-xl text-sm font-medium text-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
          >
            Retour
          </router-link>
          <button
            v-if="!result"
            type="submit"
            :disabled="closing"
            class="flex-1 py-3 rounded-xl text-sm font-semibold text-white bg-red-600 hover:bg-red-700 transition disabled:opacity-50"
          >
            {{ closing ? 'Fermeture...' : 'Fermer la session' }}
          </button>

          <!-- After closing: Download report + Go to dashboard -->
          <template v-else>
            <button
              class="flex-1 py-3 rounded-xl text-sm font-semibold text-white bg-green-600 hover:bg-green-700 transition flex items-center justify-center gap-2"
              :disabled="downloading"
              @click="downloadReport"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
              </svg>
              {{ downloading ? 'Téléchargement...' : 'Rapport PDF' }}
            </button>
            <router-link
              to="/dashboard"
              class="flex-1 py-3 text-center rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition"
            >
              Tableau de bord
            </router-link>
          </template>
        </div>
      </form>

      <p v-if="error" class="mt-4 text-sm text-red-600 text-center">{{ error }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePosStore, type PosSessionData } from '@/stores/pos/posStore'
import http from '@/services/http'

const router = useRouter()
const posStore = usePosStore()

const closingCash = ref(0)
const notes = ref('')
const closing = ref(false)
const downloading = ref(false)
const error = ref('')
const result = ref<PosSessionData | null>(null)

// Live stats — fetched once on mount so the cashier sees the
// "ventes par mode de paiement" breakdown before confirming closure.
interface SessionStats {
  total_tickets: number
  cancelled_tickets: number
  total_ttc: number
  total_ht: number
  total_tax: number
  total_paid: number
  total_credit: number
  payments_by_method: Record<string, number>
  tickets_count_by_method: Record<string, number>
}
const liveStats = ref<SessionStats | null>(null)

const PAYMENT_METHOD_META: Record<string, { title: string; bg: string; dot: string; label: string; muted: string }> = {
  cash:   { title: 'Espèces',   bg: 'bg-green-50 dark:bg-green-900/20',   dot: 'bg-green-500',   label: 'text-green-700 dark:text-green-400',   muted: 'text-green-600/70 dark:text-green-400/60' },
  card:   { title: 'Carte',     bg: 'bg-blue-50 dark:bg-blue-900/20',     dot: 'bg-blue-500',    label: 'text-blue-700 dark:text-blue-400',     muted: 'text-blue-600/70 dark:text-blue-400/60' },
  credit: { title: 'En compte', bg: 'bg-amber-50 dark:bg-amber-900/20',   dot: 'bg-amber-500',   label: 'text-amber-700 dark:text-amber-400',   muted: 'text-amber-600/70 dark:text-amber-400/60' },
  cheque: { title: 'Chèque',    bg: 'bg-purple-50 dark:bg-purple-900/20', dot: 'bg-purple-500',  label: 'text-purple-700 dark:text-purple-400', muted: 'text-purple-600/70 dark:text-purple-400/60' },
  virement: { title: 'Virement', bg: 'bg-indigo-50 dark:bg-indigo-900/20', dot: 'bg-indigo-500', label: 'text-indigo-700 dark:text-indigo-400', muted: 'text-indigo-600/70 dark:text-indigo-400/60' },
}

const paymentMethodRows = computed(() => {
  const amounts = liveStats.value?.payments_by_method ?? {}
  const counts = liveStats.value?.tickets_count_by_method ?? {}
  const keys = new Set<string>(['cash', 'card', 'credit'])
  Object.keys(amounts).forEach((k) => keys.add(k))
  return Array.from(keys).map((method) => {
    const meta = PAYMENT_METHOD_META[method] ?? {
      title: method, bg: 'bg-gray-50 dark:bg-gray-900/40', dot: 'bg-gray-400',
      label: 'text-gray-700 dark:text-gray-300', muted: 'text-gray-500',
    }
    return {
      method,
      ...meta,
      amount: amounts[method] ?? 0,
      count: counts[method] ?? 0,
    }
  })
})

async function fetchLiveStats(): Promise<void> {
  const sid = posStore.currentSession?.id
  if (!sid) return
  try {
    const { data } = await http.get<{ stats: SessionStats }>(`/pos/sessions/${sid}/live-stats`)
    liveStats.value = data?.stats ?? null
  } catch {
    liveStats.value = null
  }
}

onMounted(async () => {
  if (!posStore.hasOpenSession) {
    await posStore.fetchCurrentSession()
    if (!posStore.hasOpenSession) {
      router.replace('/pos')
      return
    }
  }
  await fetchLiveStats()
})

async function close() {
  closing.value = true
  error.value = ''
  try {
    result.value = await posStore.closeSession(closingCash.value, notes.value || undefined)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    error.value = e.response?.data?.message ?? 'Erreur lors de la fermeture'
  } finally {
    closing.value = false
  }
}

async function downloadReport() {
  if (!result.value) return
  downloading.value = true
  try {
    const response = await http.get(`/pos/sessions/${result.value.id}/report`, {
      responseType: 'blob',
    })
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `rapport-fermeture-session-${result.value.id}.pdf`
    document.body.appendChild(a)
    a.click()
    window.URL.revokeObjectURL(url)
    document.body.removeChild(a)
  } catch {
    alert('Erreur lors du téléchargement du rapport')
  } finally {
    downloading.value = false
  }
}
</script>
