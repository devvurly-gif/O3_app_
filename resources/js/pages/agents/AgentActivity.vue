<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h2 class="text-[26px] sm:text-[30px] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white">
          Activité des agents
        </h2>
        <p class="text-sm text-[#8A8F9C] dark:text-gray-400 mt-1">
          Ce que le routeur reçoit et à quel agent il le confie. Lecture seule : les agents préparent des brouillons, la
          validation reste la vôtre.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <span
          class="text-xs font-bold px-2.5 py-1 rounded-full"
          :class="
            summary?.router_on
              ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
              : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
          "
        >
          Routeur {{ summary?.router_on ? 'activé' : 'désactivé' }}
        </span>
        <button
          class="px-3 py-2 text-sm font-semibold rounded-[11px] bg-[#7C5CFC] text-white hover:bg-[#6A49F0] transition"
          @click="showChat ? (showChat = false) : openChat()"
        >
          Parler à l'orchestrateur
        </button>
        <button
          class="px-3 py-2 text-sm font-medium rounded-[11px] border border-[#ECEEF2] dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50"
          :disabled="loading"
          @click="load()"
        >
          {{ loading ? 'Actualisation...' : 'Actualiser' }}
        </button>
      </div>
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div
        v-for="card in cards"
        :key="card.label"
        class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl px-4 py-3"
      >
        <p class="text-xs text-[#8A8F9C] dark:text-gray-400">{{ card.label }}</p>
        <p class="text-2xl font-extrabold mt-1" :class="card.tone">{{ card.value }}</p>
      </div>
    </div>

    <!-- Agents -->
    <div>
      <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">Agents</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        <button
          v-for="a in agents"
          :key="a.domain"
          class="text-left bg-white dark:bg-gray-800 border rounded-2xl px-4 py-3 transition hover:border-[#7C5CFC]"
          :class="
            agentFilter === a.domain
              ? 'border-[#7C5CFC] ring-1 ring-[#7C5CFC]'
              : 'border-[#ECEEF2] dark:border-gray-700'
          "
          @click="toggleAgent(a.domain)"
        >
          <div class="flex items-center justify-between gap-2">
            <span class="font-semibold text-sm text-gray-800 dark:text-gray-100 truncate">{{ a.name }}</span>
            <span
              class="text-[10px] font-bold px-1.5 py-0.5 rounded-md shrink-0"
              :class="
                a.is_active
                  ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                  : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
              "
            >
              {{ a.is_active ? 'Actif' : 'Inactif' }}
            </span>
          </div>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">
            {{ a.account ? `Compte : ${a.account}` : 'Aucun compte rattaché' }}
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
            {{ a.events_count }} événement{{ a.events_count > 1 ? 's' : '' }}
            <span v-if="a.last_event_at">· dernier {{ timeAgo(a.last_event_at) }}</span>
          </p>
        </button>
      </div>
    </div>

    <!-- Order -->
    <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl px-4 py-4">
      <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200">Donner un ordre à l'agent Stocks</h3>
      <p class="text-xs text-[#8A8F9C] dark:text-gray-400 mt-0.5">
        L'agent prépare une feuille d'inventaire à compter (Excel). Il ne modifie aucun stock : vous comptez, puis vous
        ajustez vous-même.
      </p>
      <div class="flex items-end gap-3 flex-wrap mt-3">
        <label class="text-xs text-gray-500 dark:text-gray-400">
          Entrepôt
          <select v-model="order.warehouse_id" class="filter block mt-1">
            <option :value="null">Tous les entrepôts</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.wh_title }}</option>
          </select>
        </label>
        <label class="text-xs text-gray-500 dark:text-gray-400">
          Périmètre
          <select v-model="order.scope" class="filter block mt-1">
            <option value="all">Tous les articles en stock</option>
            <option value="attention">Articles à vérifier seulement</option>
          </select>
        </label>
        <label class="text-xs text-gray-500 dark:text-gray-400 grow min-w-[180px]">
          Note (facultatif)
          <input v-model="order.note" type="text" maxlength="500" class="filter block mt-1 w-full" />
        </label>
        <button
          class="px-4 py-2 text-sm font-semibold rounded-[11px] bg-[#7C5CFC] text-white hover:bg-[#6A49F0] transition disabled:opacity-50"
          :disabled="ordering"
          @click="sendOrder"
        >
          {{ ordering ? 'Préparation...' : 'Préparer un inventaire' }}
        </button>
      </div>
      <div
        v-if="orderResult"
        class="mt-3 flex items-center justify-between gap-3 flex-wrap rounded-xl bg-emerald-50 dark:bg-emerald-900/20 px-3 py-2.5"
      >
        <p class="text-sm text-emerald-800 dark:text-emerald-200">
          Feuille prête : {{ orderResult.rows }} article{{ orderResult.rows > 1 ? 's' : '' }} dont
          {{ orderResult.flagged }} à vérifier ({{ orderResult.warehouses.join(', ') || '—' }}).
        </p>
        <button
          class="px-3 py-1.5 text-sm font-semibold rounded-[11px] border border-emerald-300 text-emerald-800 dark:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition"
          @click="downloadSheet"
        >
          Télécharger {{ orderResult.name }}
        </button>
      </div>
      <p v-if="orderError" class="mt-3 text-sm text-red-600">{{ orderError }}</p>
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-2 flex-wrap">
      <select v-model="statusFilter" class="filter" @change="load(1)">
        <option value="">Tous les statuts</option>
        <option v-for="(label, key) in STATUS_LABELS" :key="key" :value="key">{{ label }}</option>
      </select>
      <select v-model="sourceFilter" class="filter" @change="load(1)">
        <option value="">Toutes les sources</option>
        <option v-for="(label, key) in SOURCE_LABELS" :key="key" :value="key">{{ label }}</option>
      </select>
      <button v-if="agentFilter" class="filter font-semibold text-[#7C5CFC]" @click="toggleAgent(agentFilter)">
        Agent : {{ agentFilter }} ✕
      </button>
    </div>

    <!-- Events -->
    <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl overflow-hidden">
      <p v-if="!events.length && !loading" class="px-4 py-10 text-center text-sm text-gray-400">
        Aucun événement pour l'instant.
        <span v-if="!summary?.router_on"
          >Le routeur est désactivé : activez le réglage « agents / router_enabled ».</span
        >
      </p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr
              class="text-left text-xs text-[#8A8F9C] dark:text-gray-400 border-b border-[#ECEEF2] dark:border-gray-700"
            >
              <th class="px-4 py-2.5 font-semibold">Quand</th>
              <th class="px-4 py-2.5 font-semibold">Source</th>
              <th class="px-4 py-2.5 font-semibold">Type</th>
              <th class="px-4 py-2.5 font-semibold">Agent</th>
              <th class="px-4 py-2.5 font-semibold">Statut</th>
              <th class="px-4 py-2.5 font-semibold">Client / numéro</th>
              <th class="px-4 py-2.5 font-semibold">Message</th>
              <th class="px-4 py-2.5 font-semibold">Dossier</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="e in events"
              :key="e.id"
              class="border-b border-gray-50 dark:border-gray-700 last:border-0 align-top"
            >
              <td
                class="px-4 py-2.5 whitespace-nowrap text-gray-500 dark:text-gray-400"
                :title="formatTime(e.created_at)"
              >
                {{ timeAgo(e.created_at) }}
              </td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <span
                  class="text-[11px] font-bold px-1.5 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200"
                >
                  {{ SOURCE_LABELS[e.source] ?? e.source }}
                </span>
              </td>
              <td class="px-4 py-2.5 text-gray-800 dark:text-gray-100">
                {{ e.type ? (TYPE_LABELS[e.type] ?? e.type) : '—' }}
              </td>
              <td class="px-4 py-2.5 text-gray-800 dark:text-gray-100">{{ e.agent?.name ?? '—' }}</td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-md" :class="statusClass(e.status)">
                  {{ STATUS_LABELS[e.status] ?? e.status }}
                </span>
                <p v-if="e.reason" class="text-[11px] text-gray-400 mt-0.5">
                  {{ REASON_LABELS[e.reason] ?? e.reason }}
                </p>
              </td>
              <td class="px-4 py-2.5 text-gray-800 dark:text-gray-100">
                <span v-if="e.client">{{ e.client.title }}</span>
                <span v-else-if="e.phone" class="text-gray-500 dark:text-gray-400">{{ e.phone }}</span>
                <span v-else>—</span>
              </td>
              <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300 max-w-[280px]">
                <p class="truncate" :title="e.text ?? ''">{{ e.text ?? '—' }}</p>
                <router-link
                  v-if="e.document"
                  :to="documentLink(e.document)"
                  class="text-xs font-semibold text-[#7C5CFC] hover:underline"
                >
                  {{ e.document.reference }} ({{ e.document.status === 'draft' ? 'brouillon' : e.document.status }})
                </router-link>
              </td>
              <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">{{ e.case_id ? `#${e.case_id}` : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="meta.last_page > 1"
        class="flex items-center justify-between px-4 py-2.5 border-t border-[#ECEEF2] dark:border-gray-700 text-sm"
      >
        <span class="text-gray-500 dark:text-gray-400">{{ meta.total }} événements</span>
        <div class="flex items-center gap-2">
          <button class="filter" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">
            Précédent
          </button>
          <span class="text-gray-500 dark:text-gray-400">{{ meta.current_page }} / {{ meta.last_page }}</span>
          <button class="filter" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">
            Suivant
          </button>
        </div>
      </div>
    </div>

    <!--
      Orchestrateur : un panneau ancré en bas à droite, sans fond ni flou, pour lui parler sans quitter la page
      qui reste utilisable derrière. Réduit, il laisse une pastille toujours visible. La conversation reste
      montée une fois ouverte (v-show) : réduire le panneau ne perd ni l'historique ni la saisie en cours.
    -->
    <Teleport to="body">
      <button
        v-show="!showChat"
        type="button"
        class="fixed bottom-4 right-4 z-40 flex items-center gap-2 px-4 py-3 rounded-full bg-[#7C5CFC] text-white text-sm font-semibold shadow-lg hover:bg-[#6A49F0] transition"
        @click="openChat"
      >
        <span class="w-2 h-2 rounded-full bg-emerald-300" aria-hidden="true"></span>
        Orchestrateur
      </button>

      <section
        v-if="chatMounted"
        v-show="showChat"
        role="dialog"
        aria-label="Orchestrateur : le chef des agents"
        class="fixed bottom-4 right-4 z-40 w-[min(440px,calc(100vw-2rem))] max-h-[calc(100vh-2rem)] flex flex-col bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl shadow-2xl"
        @keydown.esc="showChat = false"
      >
        <header
          class="flex items-center justify-between gap-2 px-4 py-2.5 border-b border-[#ECEEF2] dark:border-gray-700 shrink-0"
        >
          <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">Orchestrateur · chef des agents</h3>
          <button
            type="button"
            aria-label="Réduire l'orchestrateur"
            class="w-7 h-7 flex items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 transition"
            @click="showChat = false"
          >
            <svg
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
            </svg>
          </button>
        </header>
        <div class="px-3 py-3 overflow-y-auto">
          <OrchestratorChat height-class="h-[min(48vh,420px)]" @ordered="load()" />
        </div>
      </section>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import http from '@/services/http'
import OrchestratorChat from '@/components/agents/OrchestratorChat.vue'

interface Summary {
  total: number
  last_24h: number
  by_status: Record<string, number>
  open_cases: number
  router_on: boolean
}

interface AgentRow {
  domain: string
  name: string
  is_active: boolean
  account: string | null
  ability: string | null
  events_count: number
  last_event_at: string | null
}

interface EventDocument {
  id: number
  reference: string
  status: string
  type: string
}

interface EventRow {
  id: number
  created_at: string
  source: string
  type: string | null
  status: string
  agent: { domain: string; name: string } | null
  case_id: number | null
  client: { id: number; title: string; code: string | null } | null
  phone: string | null
  text: string | null
  reason: string | null
  document: EventDocument | null
}

const STATUS_LABELS: Record<string, string> = {
  new: 'Nouveau',
  routed: 'Routé',
  in_progress: 'En cours',
  done: 'Clos',
  to_sort: 'À trier',
  rejected: 'Refusé',
  error: 'Erreur',
}

const SOURCE_LABELS: Record<string, string> = {
  whatsapp: 'WhatsApp',
  sms: 'SMS',
  email: 'Email',
  pdf: 'PDF',
  erp: 'ERP',
  bank: 'Banque',
  manual: 'Manuel',
  agent: 'Agent',
}

const TYPE_LABELS: Record<string, string> = {
  commande_creee: 'Commande créée',
  demande_devis: 'Demande de devis',
  suivi_colis: 'Suivi de colis',
  facture_fournisseur: 'Facture fournisseur',
  bon_livraison_fournisseur: 'BL fournisseur',
  alerte_stock: 'Alerte de stock',
  produit_dormant: 'Produit dormant',
  commande_confirmee: 'Commande confirmée',
  virement_recu: 'Virement reçu',
  echeance_depassee: 'Échéance dépassée',
  ecriture_a_passer: 'Écriture à passer',
  campagne_demandee: 'Campagne demandée',
  inventaire_demande: "Demande d'inventaire",
  controle_encaissements: 'Contrôle des encaissements',
}

const REASON_LABELS: Record<string, string> = {
  pin_missing: 'PIN manquant',
  pin_wrong: 'PIN incorrect',
  pin_locked: 'Canal bloqué',
  pin_none: 'Aucun PIN défini',
  pin_without_order: 'PIN sans commande',
  rate_limited: 'Trop de messages',
  disabled: 'Canal désactivé',
  unknown_sender: 'Expéditeur inconnu',
  order_refused: 'Ordre refusé (agent inactif)',
  ambiguous_sender: 'Expéditeur ambigu',
}

const REFRESH_MS = 20000

const summary = ref<Summary | null>(null)
const agents = ref<AgentRow[]>([])
const events = ref<EventRow[]>([])
const meta = reactive({ current_page: 1, last_page: 1, per_page: 25, total: 0 })
const loading = ref(false)

const statusFilter = ref('')
const sourceFilter = ref('')
const agentFilter = ref('')

interface Warehouse {
  id: number
  wh_title: string
}

interface OrderResult {
  name: string
  rows: number
  flagged: number
  warehouses: string[]
  url: string
}

const warehouses = ref<Warehouse[]>([])
const order = reactive<{ warehouse_id: number | null; scope: string; note: string }>({
  warehouse_id: null,
  scope: 'all',
  note: '',
})
const ordering = ref(false)
const orderResult = ref<OrderResult | null>(null)
const orderError = ref('')

let timer: ReturnType<typeof setInterval> | null = null

const cards = computed(() => [
  { label: 'Événements', value: summary.value?.total ?? 0, tone: 'text-gray-900 dark:text-white' },
  { label: 'Dernières 24 h', value: summary.value?.last_24h ?? 0, tone: 'text-gray-900 dark:text-white' },
  {
    label: 'À trier (humain)',
    value: summary.value?.by_status?.to_sort ?? 0,
    tone: (summary.value?.by_status?.to_sort ?? 0) > 0 ? 'text-amber-600' : 'text-gray-900 dark:text-white',
  },
  { label: 'Dossiers ouverts', value: summary.value?.open_cases ?? 0, tone: 'text-gray-900 dark:text-white' },
])

const showChat = ref(false)
/** Monté à la première ouverture seulement : pas d'appel à l'API tant qu'on n'a pas parlé au chef. */
const chatMounted = ref(false)

function openChat() {
  chatMounted.value = true
  showChat.value = true
}

async function load(page = meta.current_page) {
  loading.value = true
  try {
    const { data } = await http.get('/agents/activite', {
      params: {
        page,
        status: statusFilter.value || undefined,
        source: sourceFilter.value || undefined,
        agent: agentFilter.value || undefined,
      },
    })
    summary.value = data.summary
    agents.value = data.agents
    events.value = data.events
    Object.assign(meta, data.meta)
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

async function sendOrder() {
  ordering.value = true
  orderError.value = ''
  orderResult.value = null
  try {
    const { data } = await http.post('/agents/ordres', {
      type: 'inventaire',
      warehouse_id: order.warehouse_id ?? undefined,
      scope: order.scope,
      note: order.note || undefined,
    })
    orderResult.value = data.file
    await load(1)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    orderError.value = err.response?.data?.message ?? "L'ordre n'a pas pu être exécuté."
    await load(1)
  } finally {
    ordering.value = false
  }
}

async function downloadSheet() {
  if (!orderResult.value) return
  try {
    // L'URL renvoyée commence par /api : http a déjà ce préfixe.
    const { data } = await http.get(orderResult.value.url.replace(/^\/api/, ''), { responseType: 'blob' })
    const href = URL.createObjectURL(data as Blob)
    const a = document.createElement('a')
    a.href = href
    a.download = orderResult.value.name
    a.click()
    URL.revokeObjectURL(href)
  } catch {
    /* Erreur déjà affichée par l'intercepteur http. */
  }
}

function toggleAgent(domain: string) {
  agentFilter.value = agentFilter.value === domain ? '' : domain
  load(1)
}

function statusClass(status: string) {
  switch (status) {
    case 'done':
    case 'routed':
      return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
    case 'to_sort':
      return 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
    case 'rejected':
    case 'error':
      return 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300'
    default:
      return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
  }
}

function documentLink(doc: EventDocument) {
  return doc.type === 'DeliveryNote' ? `/ventes/documents/${doc.id}` : `/achats/documents/${doc.id}`
}

function timeAgo(dateStr: string) {
  if (!dateStr) return ''
  const mins = Math.floor((Date.now() - new Date(dateStr).getTime()) / 60000)
  if (mins < 1) return "à l'instant"
  if (mins < 60) return `${mins} min`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours} h`
  return `${Math.floor(hours / 24)} j`
}

function formatTime(dateStr: string) {
  return new Date(dateStr).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(() => {
  load(1)
  loadWarehouses()
  timer = setInterval(() => load(), REFRESH_MS)
})

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})
</script>

<style scoped>
.filter {
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  border-radius: 11px;
  border: 1px solid #eceef2;
  background: white;
  color: #374151;
}
.filter:disabled {
  opacity: 0.5;
}
:global(.dark) .filter {
  background: #1f2937;
  border-color: #374151;
  color: #e5e7eb;
}
</style>
