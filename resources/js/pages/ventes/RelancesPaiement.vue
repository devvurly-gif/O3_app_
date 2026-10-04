<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h2 class="text-[26px] sm:text-[30px] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white">
          Relances de paiement
        </h2>
        <p class="text-sm text-[#8A8F9C] dark:text-gray-400 mt-1">
          L'agent Recouvrement contrôle les encaissements et prépare les relances. Il n'envoie rien : chaque message
          part seulement quand vous le validez.
        </p>
      </div>
      <button
        class="px-4 py-2 text-sm font-semibold rounded-[11px] bg-[#7C5CFC] text-white hover:bg-[#6A49F0] transition disabled:opacity-50"
        :disabled="controlling"
        @click="runControl"
      >
        {{ controlling ? 'Contrôle...' : 'Contrôler les encaissements' }}
      </button>
    </div>

    <p
      v-if="data && !data.agent_active"
      class="text-sm rounded-xl bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:text-amber-200 px-3 py-2"
    >
      L'agent Recouvrement est inactif : le contrôle est refusé tant qu'il n'est pas activé (Paramètres → Activité des
      agents).
    </p>
    <p v-if="controlError" class="text-sm text-red-600">{{ controlError }}</p>

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

    <!-- Anomalies -->
    <div
      v-if="data?.last_control"
      class="bg-white dark:bg-gray-800 border rounded-2xl px-4 py-3"
      :class="
        data.last_control.anomalies.length
          ? 'border-amber-300 dark:border-amber-700'
          : 'border-[#ECEEF2] dark:border-gray-700'
      "
    >
      <p class="text-sm font-bold text-gray-700 dark:text-gray-200">
        Dernier contrôle : {{ formatTime(data.last_control.at) }} · {{ data.last_control.overdue }} facture{{
          data.last_control.overdue > 1 ? 's' : ''
        }}
        en retard · {{ data.last_control.created }} relance{{ data.last_control.created > 1 ? 's' : '' }} préparée{{
          data.last_control.created > 1 ? 's' : ''
        }}
      </p>
      <p v-if="!data.last_control.anomalies.length" class="text-sm text-emerald-700 dark:text-emerald-300 mt-1">
        Encaissements cohérents : aucun écart entre factures, paiements et statuts.
      </p>
      <ul v-else class="mt-2 space-y-1">
        <li v-for="(a, i) in data.last_control.anomalies" :key="i" class="text-sm text-amber-800 dark:text-amber-200">
          <span class="font-semibold">{{ a.reference }}</span> : {{ a.message }}
        </li>
      </ul>
    </div>

    <!-- Filter -->
    <div class="flex items-center gap-2">
      <select v-model="statusFilter" class="field" @change="load">
        <option value="">Toutes les relances</option>
        <option value="draft">À valider</option>
        <option value="failed">Envoi échoué</option>
        <option value="sent">Envoyées</option>
        <option value="rejected">Rejetées</option>
      </select>
    </div>

    <!-- Reminders -->
    <div class="space-y-3">
      <p
        v-if="data && !data.reminders.length && !loading"
        class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl px-4 py-10 text-center text-sm text-gray-400"
      >
        Aucune relance. Lancez le contrôle pour préparer celles des factures en retard.
      </p>

      <div
        v-for="r in data?.reminders ?? []"
        :key="r.id"
        class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl px-4 py-3"
      >
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <p class="font-semibold text-sm text-gray-800 dark:text-gray-100">
              {{ r.client?.title ?? 'Client inconnu' }}
              <span class="text-gray-400 font-normal">· {{ r.document?.reference }}</span>
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              {{ formatMoney(r.amount_due) }} · {{ r.days_overdue }} jour{{ r.days_overdue > 1 ? 's' : '' }} de retard ·
              {{ LEVEL_LABELS[r.level] }} ·
              {{ r.channel === 'whatsapp' ? `WhatsApp ${r.client?.phone ?? ''}` : 'À faire vous-même' }}
            </p>
          </div>
          <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-md" :class="statusClass(r.status)">
            {{ STATUS_LABELS[r.status] ?? r.status }}
          </span>
        </div>

        <textarea v-model="drafts[r.id]" rows="4" class="field w-full mt-3 text-sm" :disabled="!canAct(r)"></textarea>
        <p v-if="r.error" class="text-sm text-red-600 mt-1">{{ r.error }}</p>
        <p v-if="r.reason" class="text-xs text-gray-400 mt-1">Rejetée : {{ r.reason }}</p>

        <div v-if="canAct(r)" class="flex items-center gap-2 flex-wrap mt-3">
          <button
            class="px-3 py-1.5 text-sm font-semibold rounded-[11px] bg-[#7C5CFC] text-white hover:bg-[#6A49F0] transition disabled:opacity-50"
            :disabled="busy === r.id"
            @click="validate(r)"
          >
            {{ r.channel === 'whatsapp' ? 'Valider et envoyer' : "J'ai relancé ce client" }}
          </button>
          <button
            v-if="r.channel === 'whatsapp'"
            class="field"
            :disabled="busy === r.id"
            @click="validate(r, 'manual')"
          >
            Je le contacte moi-même
          </button>
          <button class="field" :disabled="busy === r.id" @click="reject(r)">Rejeter</button>
        </div>
        <p v-else-if="r.sent_at" class="text-xs text-gray-400 mt-2">{{ formatTime(r.sent_at) }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import http from '@/services/http'

interface Reminder {
  id: number
  level: number
  channel: string
  status: string
  message: string
  amount_due: number
  days_overdue: number
  error: string | null
  reason: string | null
  sent_at: string | null
  document: { id: number; reference: string; due_at: string | null } | null
  client: { id: number; title: string; phone: string | null } | null
}

interface Anomaly {
  code: string
  reference: string
  message: string
}

interface ReminderData {
  agent_active: boolean
  summary: { to_validate: number; sent: number; amount_due: number }
  last_control: { at: string; overdue: number; created: number; anomalies: Anomaly[] } | null
  reminders: Reminder[]
}

const LEVEL_LABELS: Record<number, string> = {
  1: 'Rappel courtois',
  2: 'Rappel ferme',
  3: 'Escalade (à traiter par vous)',
}

const STATUS_LABELS: Record<string, string> = {
  draft: 'À valider',
  failed: 'Envoi échoué',
  sent: 'Traitée',
  rejected: 'Rejetée',
}

const data = ref<ReminderData | null>(null)
const loading = ref(false)
const statusFilter = ref('')
const drafts = reactive<Record<number, string>>({})
const busy = ref<number | null>(null)
const controlling = ref(false)
const controlError = ref('')

const cards = computed(() => [
  {
    label: 'À valider',
    value: data.value?.summary.to_validate ?? 0,
    tone: (data.value?.summary.to_validate ?? 0) > 0 ? 'text-amber-600' : 'text-gray-900 dark:text-white',
  },
  {
    label: 'Montant à relancer',
    value: formatMoney(data.value?.summary.amount_due ?? 0),
    tone: 'text-gray-900 dark:text-white',
  },
  { label: 'Traitées', value: data.value?.summary.sent ?? 0, tone: 'text-gray-900 dark:text-white' },
  {
    label: 'Anomalies',
    value: data.value?.last_control?.anomalies.length ?? 0,
    tone: (data.value?.last_control?.anomalies.length ?? 0) > 0 ? 'text-amber-600' : 'text-gray-900 dark:text-white',
  },
])

const canAct = (r: Reminder) => r.status === 'draft' || r.status === 'failed'

function formatMoney(n: number) {
  return `${n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} MAD`
}

function formatTime(dateStr: string) {
  return new Date(dateStr).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function statusClass(status: string) {
  if (status === 'sent') return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
  if (status === 'draft') return 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
  if (status === 'failed') return 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300'
  return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
}

async function load() {
  loading.value = true
  try {
    const res = await http.get<ReminderData>('/ventes/relances', {
      params: { status: statusFilter.value || undefined },
    })
    data.value = res.data
    res.data.reminders.forEach((r) => {
      drafts[r.id] = r.message
    })
  } catch {
    /* Erreur déjà affichée par l'intercepteur http. */
  } finally {
    loading.value = false
  }
}

async function runControl() {
  controlling.value = true
  controlError.value = ''
  try {
    await http.post('/ventes/relances/controle')
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    controlError.value = err.response?.data?.message ?? "Le contrôle n'a pas pu être exécuté."
  } finally {
    controlling.value = false
  }
}

async function validate(r: Reminder, channel?: string) {
  busy.value = r.id
  try {
    await http.post(`/ventes/relances/${r.id}/valider`, { channel, message: drafts[r.id] })
    await load()
  } catch {
    /* Erreur déjà affichée par l'intercepteur http. */
  } finally {
    busy.value = null
  }
}

async function reject(r: Reminder) {
  const reason = window.prompt('Motif du rejet (facultatif) :') ?? undefined
  busy.value = r.id
  try {
    await http.post(`/ventes/relances/${r.id}/rejeter`, { reason: reason || undefined })
    await load()
  } catch {
    /* Erreur déjà affichée par l'intercepteur http. */
  } finally {
    busy.value = null
  }
}

onMounted(load)
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
