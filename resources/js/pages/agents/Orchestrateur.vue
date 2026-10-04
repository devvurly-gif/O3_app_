<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h2 class="text-[26px] sm:text-[30px] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white">
          Orchestrateur
        </h2>
        <p class="text-sm text-[#8A8F9C] dark:text-gray-400 mt-1">
          Session administrateur de {{ auth.userName }}. Vous parlez au chef des agents : il comprend une liste de
          demandes et les confie aux agents. Les agents préparent des brouillons, la validation reste la vôtre.
        </p>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <button
          class="px-3 py-2 text-sm rounded-[11px] border bg-white dark:bg-gray-800 transition disabled:opacity-50"
          :class="
            ai.enabled
              ? 'border-emerald-300 text-emerald-700 dark:text-emerald-300'
              : 'border-[#ECEEF2] dark:border-gray-700 text-gray-600 dark:text-gray-300'
          "
          :disabled="togglingAi || (!ai.configured && !ai.enabled)"
          :title="
            ai.configured
              ? 'Le modèle ne fait que ranger vos phrases libres dans une demande connue : il ne voit aucune donnée de l\'entreprise et n\'exécute rien.'
              : 'Saisissez d\'abord la clé API Anthropic dans Paramètres → Réglages → Messagerie.'
          "
          @click="toggleAi"
        >
          Compréhension avancée (IA) : {{ ai.enabled ? 'activée' : 'désactivée' }}
        </button>
        <button
          v-if="messages.length"
          class="px-3 py-2 text-sm rounded-[11px] border border-[#ECEEF2] dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
          :disabled="sending"
          @click="clearSession"
        >
          Effacer la conversation
        </button>
      </div>
    </div>

    <!-- Conversation -->
    <div
      class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl flex flex-col h-[62vh] min-h-[360px]"
    >
      <div ref="scroller" class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
        <p v-if="!messages.length && !loading" class="text-center text-sm text-gray-400 py-10">
          Aucun échange pour l'instant. Écrivez « aide » pour voir ce que je sais faire, ou utilisez un raccourci
          ci-dessous.
        </p>

        <div
          v-for="m in messages"
          :key="m.id"
          class="flex"
          :class="m.role === 'admin' ? 'justify-end' : 'justify-start'"
        >
          <div class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm" :class="bubbleClass(m)">
            <p class="whitespace-pre-line break-words">{{ m.body }}</p>
            <div v-if="m.links.length" class="flex flex-wrap gap-2 mt-2">
              <router-link
                v-for="l in m.links"
                :key="l.to"
                :to="l.to"
                class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-white/80 dark:bg-gray-800 text-[#7C5CFC] border border-[#E4DEFF] dark:border-gray-600 hover:bg-white transition"
              >
                {{ l.label }} →
              </router-link>
            </div>
            <div v-if="m.suggestions?.length" class="flex flex-wrap gap-2 mt-2">
              <button
                v-for="s in m.suggestions"
                :key="s.text"
                class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-[#7C5CFC] text-white hover:bg-[#6A49F0] transition disabled:opacity-50"
                :disabled="sending"
                @click="send(s.text)"
              >
                {{ s.label }}
              </button>
            </div>
            <p class="text-[10px] mt-1.5 opacity-60">
              {{ m.role === 'admin' ? auth.userName : 'Orchestrateur' }} · {{ formatTime(m.created_at) }}
              <span v-if="m.event_id"> · événement #{{ m.event_id }}</span>
              <span v-if="m.ai"> · compris par IA</span>
            </p>
          </div>
        </div>

        <div v-if="sending" class="flex justify-start">
          <div class="rounded-2xl px-4 py-2.5 text-sm bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300">
            L'orchestrateur traite votre demande...
          </div>
        </div>
      </div>

      <!-- Shortcuts -->
      <div class="flex flex-wrap gap-2 px-4 pt-3 border-t border-[#ECEEF2] dark:border-gray-700">
        <button
          v-for="s in SHORTCUTS"
          :key="s.text"
          class="text-xs font-semibold px-3 py-1.5 rounded-full border border-[#E4DEFF] dark:border-gray-600 text-[#7C5CFC] hover:bg-[#F6F3FF] dark:hover:bg-gray-700 transition disabled:opacity-50"
          :disabled="sending"
          @click="send(s.text)"
        >
          {{ s.label }}
        </button>
      </div>

      <!-- Input -->
      <form class="flex items-end gap-2 p-4" @submit.prevent="send(draft)">
        <textarea
          v-model="draft"
          rows="2"
          maxlength="1000"
          class="field flex-1 resize-none"
          placeholder="Écrivez à l'orchestrateur… (Entrée pour envoyer, Maj+Entrée pour un retour à la ligne)"
          :disabled="sending"
          @keydown.enter.exact.prevent="send(draft)"
        ></textarea>
        <button
          type="submit"
          class="px-4 py-2.5 text-sm font-semibold rounded-[11px] bg-[#7C5CFC] text-white hover:bg-[#6A49F0] transition disabled:opacity-50"
          :disabled="sending || !draft.trim()"
        >
          Envoyer
        </button>
      </form>
    </div>
    <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'
import http from '@/services/http'
import { useAuthStore } from '@/stores/authStore'

interface AiState {
  configured: boolean
  enabled: boolean
  model: string
}

interface ChatMessage {
  id: number
  role: 'admin' | 'orchestrator'
  body: string
  links: { label: string; to: string }[]
  suggestions?: { label: string; text: string }[]
  ai?: boolean
  warning?: boolean
  error: boolean
  event_id: number | null
  created_at: string
}

const SHORTCUTS = [
  { label: 'État des agents', text: 'état des agents' },
  { label: 'Événements à trier', text: 'événements à trier' },
  { label: 'Préparer un inventaire', text: 'prépare un inventaire' },
  { label: 'Contrôler les encaissements', text: 'contrôle les encaissements' },
  { label: 'Relances à valider', text: 'relances à valider' },
  { label: 'Aide', text: 'aide' },
]

const auth = useAuthStore()
const messages = ref<ChatMessage[]>([])
const draft = ref('')
const loading = ref(false)
const sending = ref(false)
const error = ref('')
const scroller = ref<HTMLElement | null>(null)
const ai = ref<AiState>({ configured: false, enabled: false, model: '' })
const togglingAi = ref(false)

async function toggleAi() {
  togglingAi.value = true
  error.value = ''
  try {
    const { data } = await http.put<AiState>('/agents/orchestrateur/ia', { enabled: !ai.value.enabled })
    ai.value = data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? "La compréhension avancée n'a pas pu être modifiée."
  } finally {
    togglingAi.value = false
  }
}

function bubbleClass(m: ChatMessage) {
  if (m.role === 'admin') return 'bg-[#7C5CFC] text-white'
  if (m.error) return 'bg-red-50 text-red-800 dark:bg-red-900/20 dark:text-red-200'
  if (m.warning) return 'bg-amber-50 text-amber-900 dark:bg-amber-900/20 dark:text-amber-100'
  return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100'
}

function formatTime(dateStr: string) {
  return new Date(dateStr).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function scrollToEnd() {
  await nextTick()
  if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight
}

async function load() {
  loading.value = true
  try {
    const { data } = await http.get<{ messages: ChatMessage[]; ai: AiState }>('/agents/orchestrateur')
    messages.value = data.messages
    ai.value = data.ai
    await scrollToEnd()
  } catch {
    /* Erreur déjà affichée par l'intercepteur http. */
  } finally {
    loading.value = false
  }
}

async function send(text: string) {
  const message = text.trim()
  if (!message || sending.value) return
  sending.value = true
  error.value = ''
  draft.value = ''
  // Affichage immédiat du message de l'administrateur ; il est remplacé par la version enregistrée.
  const pending: ChatMessage = {
    id: -Date.now(),
    role: 'admin',
    body: message,
    links: [],
    error: false,
    event_id: null,
    created_at: new Date().toISOString(),
  }
  messages.value.push(pending)
  await scrollToEnd()
  try {
    const { data } = await http.post<{ user: ChatMessage; reply: ChatMessage }>('/agents/orchestrateur', { message })
    messages.value.splice(messages.value.indexOf(pending), 1, data.user, data.reply)
  } catch (e: unknown) {
    messages.value.splice(messages.value.indexOf(pending), 1)
    draft.value = message
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? "Le message n'a pas pu être envoyé."
  } finally {
    sending.value = false
    await scrollToEnd()
  }
}

async function clearSession() {
  if (!window.confirm('Effacer cette conversation ? Les ordres déjà donnés aux agents ne sont pas annulés.')) return
  try {
    await http.delete('/agents/orchestrateur')
    messages.value = []
  } catch {
    /* Erreur déjà affichée par l'intercepteur http. */
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
