<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Piste d'audit</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Historique de toutes les actions effectuées dans l'application</p>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Type</label>
          <select
            v-model="filters.subject_type"
            class="block w-44 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">Tous</option>
            <option v-for="t in subjectTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Action</label>
          <select
            v-model="filters.event"
            class="block w-36 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">Toutes</option>
            <option value="created">Création</option>
            <option value="updated">Modification</option>
            <option value="deleted">Suppression</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Du</label>
          <input
            v-model="filters.from"
            type="date"
            class="block w-40 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Au</label>
          <input
            v-model="filters.to"
            type="date"
            class="block w-40 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition"
          @click="fetchLogs(1)"
        >
          Filtrer
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
          @click="resetFilters"
        >
          Réinitialiser
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
      <BaseSkeleton v-if="loading" type="table" :rows="10" />
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Utilisateur</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Action</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Détails</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ formatDate(log.created_at) }}</td>
              <td class="px-4 py-3 text-sm text-gray-900 dark:text-white whitespace-nowrap">
                {{ log.causer?.name || 'Système' }}
              </td>
              <td class="px-4 py-3 whitespace-nowrap">
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                  :class="eventBadge(log.event)"
                >
                  {{ eventLabel(log.event) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                {{ subjectLabel(log.subject_type) }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate">{{ log.description }}</td>
              <td class="px-4 py-3 text-center">
                <button
                  type="button"
                  class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                  @click="showDetail(log)"
                >
                  Voir
                </button>
              </td>
            </tr>
            <tr v-if="!logs.length">
              <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                Aucune activité enregistrée
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.lastPage > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
        <p class="text-sm text-gray-600 dark:text-gray-400">
          {{ pagination.from }}–{{ pagination.to }} sur {{ pagination.total }}
        </p>
        <div class="flex gap-1">
          <button
            v-for="page in paginationPages"
            :key="page"
            type="button"
            class="px-3 py-1 text-sm rounded-md transition"
            :class="page === pagination.currentPage ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-200'"
            @click="fetchLogs(page)"
          >
            {{ page }}
          </button>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="detailLog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="detailLog = null">
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Détails de l'activité</h3>
              <button type="button" class="text-gray-400 dark:text-gray-500 hover:text-gray-600" @click="detailLog = null">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span class="text-gray-500 dark:text-gray-400">Date :</span>
                  <span class="ml-2 font-medium">{{ formatDate(detailLog.created_at) }}</span>
                </div>
                <div>
                  <span class="text-gray-500 dark:text-gray-400">Utilisateur :</span>
                  <span class="ml-2 font-medium">{{ detailLog.causer?.name || 'Système' }}</span>
                </div>
                <div>
                  <span class="text-gray-500 dark:text-gray-400">Action :</span>
                  <span class="ml-2 font-medium">{{ eventLabel(detailLog.event) }}</span>
                </div>
                <div>
                  <span class="text-gray-500 dark:text-gray-400">Type :</span>
                  <span class="ml-2 font-medium">{{ subjectLabel(detailLog.subject_type) }}</span>
                </div>
                <div class="col-span-2">
                  <span class="text-gray-500 dark:text-gray-400">Description :</span>
                  <span class="ml-2 font-medium">{{ detailLog.description }}</span>
                </div>
              </div>

              <!-- Changes -->
              <div v-if="detailLog.properties?.old || detailLog.properties?.attributes">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Modifications</h4>
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 space-y-2">
                  <div
                    v-for="(val, key) in detailLog.properties?.attributes || {}"
                    :key="key"
                    class="flex items-start gap-2 text-sm"
                  >
                    <span class="text-gray-500 dark:text-gray-400 font-mono min-w-[140px]">{{ key }}</span>
                    <span v-if="detailLog.properties?.old?.[key] !== undefined" class="text-red-500 line-through">
                      {{ detailLog.properties.old[key] }}
                    </span>
                    <span v-if="detailLog.properties?.old?.[key] !== undefined" class="text-gray-400 dark:text-gray-500 mx-1">&rarr;</span>
                    <span class="text-green-600 font-medium">{{ val }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import http from '@/services/http'
import { useToastStore } from '@/stores/toastStore'
import BaseSkeleton from '@/components/BaseSkeleton.vue'

const toast = useToastStore()

interface ActivityLog {
  id: number
  description: string
  subject_type: string | null
  subject_id: number | null
  causer_id: number | null
  causer: { id: number; name: string; email: string } | null
  event: string | null
  properties: { old?: Record<string, any>; attributes?: Record<string, any> } | null
  created_at: string
}

const logs = ref<ActivityLog[]>([])
const loading = ref(false)
const detailLog = ref<ActivityLog | null>(null)

const pagination = reactive({
  currentPage: 1,
  lastPage: 1,
  total: 0,
  from: 0,
  to: 0,
})

const filters = reactive({
  subject_type: '',
  event: '',
  from: '',
  to: '',
})

const subjectTypes = [
  { value: 'Product', label: 'Produit' },
  { value: 'DocumentHeader', label: 'Document' },
  { value: 'ThirdPartner', label: 'Tiers' },
  { value: 'Payment', label: 'Paiement' },
  { value: 'Warehouse', label: 'Entrepôt' },
  { value: 'User', label: 'Utilisateur' },
  { value: 'StockMouvement', label: 'Mouvement Stock' },
  { value: 'PosSession', label: 'Session POS' },
]

const paginationPages = computed(() => {
  const pages: number[] = []
  const start = Math.max(1, pagination.currentPage - 2)
  const end = Math.min(pagination.lastPage, pagination.currentPage + 2)
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
})

onMounted(() => fetchLogs(1))

async function fetchLogs(page: number) {
  loading.value = true
  try {
    const params: Record<string, string | number> = { page, per_page: 30 }
    if (filters.subject_type) params.subject_type = filters.subject_type
    if (filters.event) params.event = filters.event
    if (filters.from) params.from = filters.from
    if (filters.to) params.to = filters.to

    const resp = await http.get('/activity-log', { params })
    logs.value = resp.data.data
    pagination.currentPage = resp.data.current_page
    pagination.lastPage = resp.data.last_page
    pagination.total = resp.data.total
    pagination.from = resp.data.from ?? 0
    pagination.to = resp.data.to ?? 0
  } catch {
    toast.error('Erreur lors du chargement du journal d\'activité.')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.subject_type = ''
  filters.event = ''
  filters.from = ''
  filters.to = ''
  fetchLogs(1)
}

function showDetail(log: ActivityLog) {
  detailLog.value = log
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

function eventLabel(event: string | null): string {
  switch (event) {
    case 'created': return 'Création'
    case 'updated': return 'Modification'
    case 'deleted': return 'Suppression'
    default: return event || 'Autre'
  }
}

function eventBadge(event: string | null): string {
  switch (event) {
    case 'created': return 'bg-green-100 text-green-700'
    case 'updated': return 'bg-blue-100 text-blue-700'
    case 'deleted': return 'bg-red-100 text-red-700'
    default: return 'bg-gray-100 text-gray-700'
  }
}

function subjectLabel(type: string | null): string {
  if (!type) return '—'
  const short = type.replace(/^App\\Models\\/, '')
  const found = subjectTypes.find((t) => t.value === short)
  return found?.label || short
}
</script>
