<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Sessions POS</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Gérer et suivre les sessions de caisse</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
      <select
        v-model="filterStatus"
        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        @change="fetchSessions"
      >
        <option value="">Toutes les sessions</option>
        <option value="open">Ouvertes</option>
        <option value="closed">Fermées</option>
      </select>

      <select
        v-model="filterTerminal"
        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        @change="fetchSessions"
      >
        <option value="">Tous les terminaux</option>
        <option v-for="t in terminals" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>

      <div class="ml-auto flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <span
          class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 font-medium"
        >
          <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
          {{ openCount }} ouverte{{ openCount !== 1 ? 's' : '' }}
        </span>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <tr>
              <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-gray-400">Terminal</th>
              <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-gray-400">Utilisateur</th>
              <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-gray-400">Ouverture</th>
              <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-gray-400">Fermeture</th>
              <th class="text-right px-4 py-3 font-semibold text-gray-600 dark:text-gray-400">Fond ouvert</th>
              <th class="text-right px-4 py-3 font-semibold text-gray-600 dark:text-gray-400">Fond fermé</th>
              <th class="text-right px-4 py-3 font-semibold text-gray-600 dark:text-gray-400">Écart</th>
              <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-gray-400">Statut</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading" class="border-b">
              <td colspan="9" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">Chargement...</td>
            </tr>
            <tr v-else-if="!sessions.length" class="border-b">
              <td colspan="9" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">Aucune session trouvée</td>
            </tr>
            <tr
              v-for="s in sessions"
              :key="s.id"
              class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
            >
              <td class="px-4 py-3">
                <span class="font-mono text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">
                  {{ s.terminal?.name ?? '—' }}
                </span>
              </td>
              <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ s.user?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ formatDate(s.opened_at) }}</td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ s.closed_at ? formatDate(s.closed_at) : '—' }}</td>
              <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-300">{{ formatMoney(s.opening_cash) }}</td>
              <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-300">
                {{ s.closing_cash != null ? formatMoney(s.closing_cash) : '—' }}
              </td>
              <td class="px-4 py-3 text-right font-mono" :class="diffClass(s.cash_difference)">
                {{ s.cash_difference != null ? formatMoney(s.cash_difference) : '—' }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="s.closed_at
                    ? 'bg-gray-100 text-gray-500'
                    : 'bg-green-100 text-green-700'"
                >
                  <span
                    v-if="!s.closed_at"
                    class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"
                  ></span>
                  {{ s.closed_at ? 'Fermée' : 'Ouverte' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <!-- Force close -->
                  <button
                    v-if="!s.closed_at"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-red-600 hover:bg-red-50 transition"
                    title="Forcer la fermeture"
                    @click="confirmForceClose(s)"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    Fermer
                  </button>

                  <!-- Details -->
                  <button
                    class="p-1.5 rounded-lg text-gray-400 dark:text-gray-500 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition"
                    title="Détails"
                    @click="openDetail(s)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="totalPages > 1"
        class="flex items-center justify-between px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900"
      >
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Page {{ currentPage }} / {{ totalPages }} — {{ totalItems }} session{{ totalItems !== 1 ? 's' : '' }}
        </p>
        <div class="flex items-center gap-1">
          <button
            :disabled="currentPage <= 1"
            class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-white transition disabled:opacity-40 disabled:cursor-not-allowed"
            @click="goPage(currentPage - 1)"
          >
            ← Préc.
          </button>
          <button
            :disabled="currentPage >= totalPages"
            class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-white transition disabled:opacity-40 disabled:cursor-not-allowed"
            @click="goPage(currentPage + 1)"
          >
            Suiv. →
          </button>
        </div>
      </div>
    </div>

    <!-- Force Close Confirm Modal -->
    <BaseModal v-model="showForceClose" title="Forcer la fermeture" size="sm">
      <div class="space-y-3">
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Êtes-vous sûr de vouloir fermer de force la session sur le terminal
          <span class="font-semibold">{{ forceCloseTarget?.terminal?.name }}</span> ?
        </p>
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-700">
          <strong>Attention :</strong> Le fond de caisse sera défini à la valeur d'ouverture. L'utilisateur ne pourra plus utiliser cette session.
        </div>
      </div>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition"
          @click="showForceClose = false"
        >
          Annuler
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg transition disabled:opacity-60"
          :disabled="forceClosing"
          @click="doForceClose"
        >
          {{ forceClosing ? 'Fermeture...' : 'Forcer la fermeture' }}
        </button>
      </template>
    </BaseModal>

    <!-- Detail Modal -->
    <BaseModal v-model="showDetail" title="Détails de la session" size="md">
      <div v-if="detailTarget" class="space-y-4">
        <div class="grid grid-cols-2 gap-3 text-sm">
          <div>
            <span class="text-gray-500 dark:text-gray-400">Terminal</span>
            <p class="font-medium text-gray-900 dark:text-white">{{ detailTarget.terminal?.name ?? '—' }}</p>
          </div>
          <div>
            <span class="text-gray-500 dark:text-gray-400">Utilisateur</span>
            <p class="font-medium text-gray-900 dark:text-white">{{ detailTarget.user?.name ?? '—' }}</p>
          </div>
          <div>
            <span class="text-gray-500 dark:text-gray-400">Ouverture</span>
            <p class="font-medium text-gray-900 dark:text-white">{{ formatDate(detailTarget.opened_at) }}</p>
          </div>
          <div>
            <span class="text-gray-500 dark:text-gray-400">Fermeture</span>
            <p class="font-medium text-gray-900 dark:text-white">
              {{ detailTarget.closed_at ? formatDate(detailTarget.closed_at) : 'En cours' }}
            </p>
          </div>
          <div>
            <span class="text-gray-500 dark:text-gray-400">Fond d'ouverture</span>
            <p class="font-mono font-medium text-gray-900 dark:text-white">{{ formatMoney(detailTarget.opening_cash) }}</p>
          </div>
          <div>
            <span class="text-gray-500 dark:text-gray-400">Fond de fermeture</span>
            <p class="font-mono font-medium text-gray-900 dark:text-white">
              {{ detailTarget.closing_cash != null ? formatMoney(detailTarget.closing_cash) : '—' }}
            </p>
          </div>
          <div>
            <span class="text-gray-500 dark:text-gray-400">Montant attendu</span>
            <p class="font-mono font-medium text-gray-900 dark:text-white">
              {{ detailTarget.expected_cash != null ? formatMoney(detailTarget.expected_cash) : '—' }}
            </p>
          </div>
          <div>
            <span class="text-gray-500 dark:text-gray-400">Écart de caisse</span>
            <p class="font-mono font-medium" :class="diffClass(detailTarget.cash_difference)">
              {{ detailTarget.cash_difference != null ? formatMoney(detailTarget.cash_difference) : '—' }}
            </p>
          </div>
        </div>
        <div v-if="detailTarget.notes">
          <span class="text-sm text-gray-500 dark:text-gray-400">Notes</span>
          <p class="text-sm text-gray-900 dark:text-white mt-0.5 bg-gray-50 dark:bg-gray-900 rounded-lg p-3">{{ detailTarget.notes }}</p>
        </div>
      </div>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition"
          @click="showDetail = false"
        >
          Fermer
        </button>
      </template>
    </BaseModal>

    <BaseNotification ref="toast" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import http from '@/services/http'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'

interface SessionTerminal {
  id: number
  name: string
  code: string
}

interface SessionUser {
  id: number
  name: string
}

interface PosSessionRow {
  id: number
  pos_terminal_id: number
  user_id: number
  opened_at: string
  closed_at: string | null
  opening_cash: number
  closing_cash: number | null
  expected_cash: number | null
  cash_difference: number | null
  notes: string | null
  terminal: SessionTerminal | null
  user: SessionUser | null
}

interface PaginatedResponse {
  data: PosSessionRow[]
  current_page: number
  last_page: number
  total: number
}

const sessions = ref<PosSessionRow[]>([])
const terminals = ref<SessionTerminal[]>([])
const loading = ref(true)
const filterStatus = ref('')
const filterTerminal = ref<number | string>('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)

const showForceClose = ref(false)
const forceCloseTarget = ref<PosSessionRow | null>(null)
const forceClosing = ref(false)

const showDetail = ref(false)
const detailTarget = ref<PosSessionRow | null>(null)

const toast = ref<InstanceType<typeof BaseNotification> | null>(null)

const openCount = computed(() => sessions.value.filter(s => !s.closed_at).length)

onMounted(async () => {
  const { data } = await http.get('/pos/terminals')
  terminals.value = Array.isArray(data) ? data : data.data ?? []
  await fetchSessions()
})

async function fetchSessions(page = 1) {
  loading.value = true
  try {
    const params: Record<string, string | number> = { page, per_page: 20 }
    if (filterStatus.value) params.status = filterStatus.value
    if (filterTerminal.value) params.terminal_id = filterTerminal.value

    const { data } = await http.get<PaginatedResponse>('/pos/sessions', { params })
    sessions.value = data.data
    currentPage.value = data.current_page
    totalPages.value = data.last_page
    totalItems.value = data.total
  } catch {
    toast.value?.notify('Erreur de chargement des sessions', 'error')
  } finally {
    loading.value = false
  }
}

function goPage(page: number) {
  if (page < 1 || page > totalPages.value) return
  fetchSessions(page)
}

function confirmForceClose(s: PosSessionRow) {
  forceCloseTarget.value = s
  showForceClose.value = true
}

async function doForceClose() {
  if (!forceCloseTarget.value) return
  forceClosing.value = true
  try {
    await http.post(`/pos/sessions/${forceCloseTarget.value.id}/force-close`)
    toast.value?.notify('Session fermée de force', 'success')
    showForceClose.value = false
    await fetchSessions(currentPage.value)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.value?.notify(e.response?.data?.message ?? 'Erreur', 'error')
  } finally {
    forceClosing.value = false
  }
}

function openDetail(s: PosSessionRow) {
  detailTarget.value = s
  showDetail.value = true
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatMoney(val: number | null | undefined): string {
  if (val == null) return '—'
  return Number(val).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function diffClass(diff: number | null | undefined): string {
  if (diff == null) return 'text-gray-400'
  if (Number(diff) > 0) return 'text-green-600'
  if (Number(diff) < 0) return 'text-red-600'
  return 'text-gray-700'
}
</script>
