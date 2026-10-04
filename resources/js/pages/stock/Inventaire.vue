<template>
  <div class="space-y-5">
    <!-- Header -->
    <div>
      <h2 class="text-[26px] sm:text-[30px] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white">
        Inventaire
      </h2>
      <p class="text-sm text-[#8A8F9C] dark:text-gray-400 mt-1">
        L'agent Stocks prépare la feuille à compter. Vous comptez ici ou sur papier, puis vous appliquez vous-même les
        écarts : l'agent ne modifie jamais le stock.
      </p>
    </div>

    <!-- New inventory -->
    <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl px-4 py-4">
      <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200">Nouvel inventaire</h3>
      <div class="flex items-end gap-3 flex-wrap mt-3">
        <label class="text-xs text-gray-500 dark:text-gray-400">
          Entrepôt
          <select v-model="order.warehouse_id" class="field block mt-1">
            <option :value="null">Tous les entrepôts</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.wh_title }}</option>
          </select>
        </label>
        <label class="text-xs text-gray-500 dark:text-gray-400">
          Périmètre
          <select v-model="order.scope" class="field block mt-1">
            <option value="all">Tous les articles en stock</option>
            <option value="attention">Articles à vérifier seulement</option>
          </select>
        </label>
        <label class="text-xs text-gray-500 dark:text-gray-400 grow min-w-[180px]">
          Note (facultatif)
          <input v-model="order.note" type="text" maxlength="500" class="field block mt-1 w-full" />
        </label>
        <button
          class="px-4 py-2 text-sm font-semibold rounded-[11px] bg-[#7C5CFC] text-white hover:bg-[#6A49F0] transition disabled:opacity-50"
          :disabled="ordering"
          @click="createSession"
        >
          {{ ordering ? 'Préparation...' : "Demander à l'agent Stocks" }}
        </button>
      </div>
      <p v-if="orderError" class="mt-3 text-sm text-red-600">{{ orderError }}</p>
    </div>

    <!-- History -->
    <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl overflow-hidden">
      <p v-if="!sessions.length && !loading" class="px-4 py-10 text-center text-sm text-gray-400">
        Aucun inventaire pour l'instant.
      </p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr
              class="text-left text-xs text-[#8A8F9C] dark:text-gray-400 border-b border-[#ECEEF2] dark:border-gray-700"
            >
              <th class="px-4 py-2.5 font-semibold">Feuille</th>
              <th class="px-4 py-2.5 font-semibold">Date</th>
              <th class="px-4 py-2.5 font-semibold">Entrepôt</th>
              <th class="px-4 py-2.5 font-semibold">Articles</th>
              <th class="px-4 py-2.5 font-semibold">Statut</th>
              <th class="px-4 py-2.5 font-semibold text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="s in sessions"
              :key="s.event_id"
              class="border-b border-gray-50 dark:border-gray-700 last:border-0"
              :class="active?.event_id === s.event_id ? 'bg-[#F6F3FF] dark:bg-gray-700/60' : ''"
            >
              <td class="px-4 py-2.5 font-semibold text-gray-800 dark:text-gray-100">
                #{{ s.event_id }}
                <p v-if="s.note" class="text-xs font-normal text-gray-400 truncate max-w-[200px]">{{ s.note }}</p>
              </td>
              <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                {{ formatTime(s.created_at) }}
                <p v-if="s.ordered_by" class="text-xs">{{ s.ordered_by }}</p>
              </td>
              <td class="px-4 py-2.5 text-gray-800 dark:text-gray-100">{{ s.warehouses.join(', ') || '—' }}</td>
              <td class="px-4 py-2.5 text-gray-800 dark:text-gray-100">
                {{ s.rows }}
                <span v-if="s.flagged" class="text-xs text-amber-600">({{ s.flagged }} à vérifier)</span>
              </td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-md" :class="statusClass(s.status)">
                  {{ STATUS_LABELS[s.status] ?? s.status }}
                </span>
                <p v-if="s.status === 'applied'" class="text-[11px] text-gray-400 mt-0.5">
                  {{ s.adjusted }} ajustement{{ (s.adjusted ?? 0) > 1 ? 's' : '' }} · {{ s.applied_by }}
                </p>
              </td>
              <td class="px-4 py-2.5 text-right whitespace-nowrap">
                <button
                  v-if="s.file_name"
                  class="text-xs font-semibold text-gray-600 dark:text-gray-300 hover:underline mr-3"
                  @click="download(s)"
                >
                  Excel
                </button>
                <button
                  v-if="s.status !== 'error'"
                  class="text-xs font-semibold text-[#7C5CFC] hover:underline"
                  @click="open(s)"
                >
                  {{ s.status === 'applied' ? 'Consulter' : 'Compter' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Counting -->
    <div
      v-if="active"
      class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl overflow-hidden"
    >
      <div
        class="flex items-center justify-between gap-3 flex-wrap px-4 py-3 border-b border-[#ECEEF2] dark:border-gray-700"
      >
        <div>
          <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200">
            Comptage de la feuille #{{ active.event_id }}
          </h3>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ filledCount }} / {{ lines.length }} quantité{{ filledCount > 1 ? 's' : '' }} saisie{{
              filledCount > 1 ? 's' : ''
            }}
            · {{ diffCount }} écart{{ diffCount > 1 ? 's' : '' }} (<span class="text-emerald-600"
              >+{{ totalPlus }}</span
            >
            / <span class="text-red-600">−{{ totalMinus }}</span
            >)
          </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <label class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
            <input v-model="flaggedOnly" type="checkbox" /> À vérifier seulement
          </label>
          <template v-if="active.status !== 'applied'">
            <button
              v-if="!confirming"
              class="px-3 py-1.5 text-sm font-semibold rounded-[11px] bg-[#7C5CFC] text-white hover:bg-[#6A49F0] transition disabled:opacity-50"
              :disabled="!diffCount || applying"
              @click="confirming = true"
            >
              Appliquer les écarts
            </button>
            <template v-else>
              <span class="text-xs text-amber-700 dark:text-amber-300">
                Ajuster {{ diffCount }} article{{ diffCount > 1 ? 's' : '' }} ? Action définitive, une seule fois.
              </span>
              <button
                class="px-3 py-1.5 text-sm font-semibold rounded-[11px] bg-red-600 text-white hover:bg-red-700 transition disabled:opacity-50"
                :disabled="applying"
                @click="applyCounts"
              >
                {{ applying ? 'Application...' : 'Confirmer' }}
              </button>
              <button class="field" :disabled="applying" @click="confirming = false">Annuler</button>
            </template>
          </template>
        </div>
      </div>

      <p
        v-if="applyMessage"
        class="px-4 py-2.5 text-sm bg-emerald-50 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-200"
      >
        {{ applyMessage }}
      </p>
      <p v-if="!lines.length" class="px-4 py-8 text-center text-sm text-gray-400">
        Cette feuille ne contient pas de lignes à compter à l'écran (préparée avant cette fonction) : utilisez le
        fichier Excel.
      </p>
      <div v-else class="overflow-x-auto max-h-[60vh] overflow-y-auto">
        <table class="w-full text-sm">
          <thead class="sticky top-0 bg-white dark:bg-gray-800">
            <tr
              class="text-left text-xs text-[#8A8F9C] dark:text-gray-400 border-b border-[#ECEEF2] dark:border-gray-700"
            >
              <th class="px-4 py-2.5 font-semibold">Article</th>
              <th class="px-4 py-2.5 font-semibold">Entrepôt</th>
              <th class="px-4 py-2.5 font-semibold text-right">Théorique</th>
              <th class="px-4 py-2.5 font-semibold text-right">Actuel</th>
              <th class="px-4 py-2.5 font-semibold">Compté</th>
              <th class="px-4 py-2.5 font-semibold text-right">Écart</th>
              <th class="px-4 py-2.5 font-semibold">À vérifier</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="l in visibleLines"
              :key="lineKey(l)"
              class="border-b border-gray-50 dark:border-gray-700 last:border-0"
            >
              <td class="px-4 py-2 text-gray-800 dark:text-gray-100">
                <span class="font-semibold">{{ l.sku }}</span>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[260px]">{{ l.title }}</p>
              </td>
              <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ l.warehouse }}</td>
              <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-300">{{ l.theoretical }}</td>
              <td
                class="px-4 py-2 text-right"
                :class="
                  l.current !== l.theoretical ? 'text-amber-600 font-semibold' : 'text-gray-600 dark:text-gray-300'
                "
                :title="l.current !== l.theoretical ? 'Le stock a bougé depuis la feuille' : ''"
              >
                {{ l.current }}
              </td>
              <td class="px-4 py-2">
                <input
                  v-model="counts[lineKey(l)]"
                  type="number"
                  min="0"
                  step="any"
                  class="field w-24"
                  :disabled="active.status === 'applied'"
                />
              </td>
              <td class="px-4 py-2 text-right font-semibold" :class="gapClass(l)">{{ gapLabel(l) }}</td>
              <td class="px-4 py-2 text-xs text-amber-700 dark:text-amber-300">{{ l.flags.join(', ') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import http from '@/services/http'

interface Warehouse {
  id: number
  wh_title: string
}

interface Session {
  event_id: number
  created_at: string
  ordered_by: string | null
  scope: string
  note: string | null
  status: string
  warehouses: string[]
  rows: number
  flagged: number
  file_name: string | null
  applied_at: string | null
  applied_by: string | null
  adjusted: number | null
}

interface Line {
  warehouse_id: number
  warehouse: string
  product_id: number
  sku: string
  title: string
  theoretical: number
  current: number
  flags: string[]
}

const STATUS_LABELS: Record<string, string> = {
  prepared: 'À compter',
  applied: 'Appliqué',
  error: 'Erreur',
  to_sort: 'Refusé',
}

const sessions = ref<Session[]>([])
const warehouses = ref<Warehouse[]>([])
const loading = ref(false)

const order = reactive<{ warehouse_id: number | null; scope: string; note: string }>({
  warehouse_id: null,
  scope: 'all',
  note: '',
})
const ordering = ref(false)
const orderError = ref('')

const active = ref<Session | null>(null)
const lines = ref<Line[]>([])
const counts = reactive<Record<string, string | number | null>>({})
const flaggedOnly = ref(false)
const confirming = ref(false)
const applying = ref(false)
const applyMessage = ref('')

const lineKey = (l: Line) => `${l.warehouse_id}:${l.product_id}`

function counted(l: Line): number | null {
  const v = counts[lineKey(l)]
  if (v === '' || v === null || v === undefined) return null
  const n = Number(v)
  return Number.isFinite(n) ? n : null
}

/** Écart réel = comptée − stock actuel (c'est ce que l'ajustement appliquera). */
function gap(l: Line): number | null {
  const c = counted(l)
  return c === null ? null : Math.round((c - l.current) * 100) / 100
}

const visibleLines = computed(() => (flaggedOnly.value ? lines.value.filter((l) => l.flags.length) : lines.value))
const filledCount = computed(() => lines.value.filter((l) => counted(l) !== null).length)
const gaps = computed(() => lines.value.map(gap).filter((g): g is number => g !== null && g !== 0))
const diffCount = computed(() => gaps.value.length)
const totalPlus = computed(() => gaps.value.filter((g) => g > 0).reduce((a, b) => a + b, 0))
const totalMinus = computed(() => Math.abs(gaps.value.filter((g) => g < 0).reduce((a, b) => a + b, 0)))

function gapLabel(l: Line) {
  const g = gap(l)
  if (g === null) return '—'
  return g > 0 ? `+${g}` : String(g)
}

function gapClass(l: Line) {
  const g = gap(l)
  if (g === null || g === 0) return 'text-gray-400'
  return g > 0 ? 'text-emerald-600' : 'text-red-600'
}

function statusClass(status: string) {
  if (status === 'applied') return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
  if (status === 'prepared') return 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
  return 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300'
}

function formatTime(dateStr: string) {
  return new Date(dateStr).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function loadSessions() {
  loading.value = true
  try {
    const { data } = await http.get<{ sessions: Session[] }>('/stock/inventaires')
    sessions.value = data.sessions
  } catch {
    /* Erreur déjà affichée par l'intercepteur http. */
  } finally {
    loading.value = false
  }
}

async function loadWarehouses() {
  try {
    const { data } = await http.get<Warehouse[]>('/warehouses')
    warehouses.value = data
  } catch {
    /* Erreur déjà affichée par l'intercepteur http. */
  }
}

async function createSession() {
  ordering.value = true
  orderError.value = ''
  try {
    const { data } = await http.post<Session>('/stock/inventaires', {
      warehouse_id: order.warehouse_id ?? undefined,
      scope: order.scope,
      note: order.note || undefined,
    })
    await loadSessions()
    await open(data)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    orderError.value = err.response?.data?.message ?? "L'agent Stocks n'a pas pu préparer l'inventaire."
  } finally {
    ordering.value = false
  }
}

async function open(s: Session) {
  applyMessage.value = ''
  confirming.value = false
  flaggedOnly.value = false
  try {
    const { data } = await http.get<{ session: Session; lines: Line[] }>(`/stock/inventaires/${s.event_id}`)
    active.value = data.session
    lines.value = data.lines
    Object.keys(counts).forEach((k) => delete counts[k])
  } catch {
    /* Erreur déjà affichée par l'intercepteur http. */
  }
}

async function applyCounts() {
  if (!active.value) return
  applying.value = true
  try {
    const payload = lines.value
      .filter((l) => counted(l) !== null)
      .map((l) => ({ warehouse_id: l.warehouse_id, product_id: l.product_id, counted: counted(l) }))
    const { data } = await http.post<{ adjusted: number; unchanged: number; errors: unknown[] }>(
      `/stock/inventaires/${active.value.event_id}/appliquer`,
      { counts: payload },
    )
    applyMessage.value = `${data.adjusted} stock${data.adjusted > 1 ? 's ajustés' : ' ajusté'}, ${data.unchanged} déjà conforme${data.unchanged > 1 ? 's' : ''}${data.errors.length ? `, ${data.errors.length} en erreur` : ''}.`
    confirming.value = false
    await loadSessions()
    const refreshed = sessions.value.find((s) => s.event_id === active.value?.event_id)
    if (refreshed) await open(refreshed)
  } catch {
    /* Erreur déjà affichée par l'intercepteur http. */
  } finally {
    applying.value = false
  }
}

async function download(s: Session) {
  try {
    const { data } = await http.get(`/stock/inventaires/${s.event_id}/fichier`, { responseType: 'blob' })
    const href = URL.createObjectURL(data as Blob)
    const a = document.createElement('a')
    a.href = href
    a.download = s.file_name ?? `inventaire-${s.event_id}.xlsx`
    a.click()
    URL.revokeObjectURL(href)
  } catch {
    /* Erreur déjà affichée par l'intercepteur http. */
  }
}

onMounted(() => {
  loadSessions()
  loadWarehouses()
})
</script>

<style scoped>
.field {
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  border-radius: 11px;
  border: 1px solid #eceef2;
  background: white;
  color: #374151;
}
.field:disabled {
  opacity: 0.6;
}
:global(.dark) .field {
  background: #1f2937;
  border-color: #374151;
  color: #e5e7eb;
}
</style>
