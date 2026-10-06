<template>
  <div class="space-y-3">
    <!-- Barre d'outils -->
    <div class="flex items-center justify-end gap-2 flex-wrap">
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
            ? 'Le modèle range vos phrases libres dans une demande connue (il ne voit aucune donnée de l\'entreprise) et lit les photos et PDF que vous déposez (leur contenu est envoyé à Anthropic). Il n\'exécute rien : chaque création attend votre clic.'
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

    <!-- Conversation (on peut y glisser des photos ou des PDF) -->
    <div
      class="relative bg-white dark:bg-gray-800 border rounded-2xl flex flex-col min-h-[360px]"
      :class="[
        heightClass,
        dragging ? 'border-[#7C5CFC] ring-2 ring-[#7C5CFC]/30' : 'border-[#ECEEF2] dark:border-gray-700',
      ]"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <div
        v-if="dragging"
        class="absolute inset-0 z-10 rounded-2xl bg-[#F6F3FF]/90 dark:bg-gray-800/90 flex items-center justify-center text-sm font-semibold text-[#7C5CFC] pointer-events-none"
      >
        Déposez vos photos ou PDF ici
      </div>
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
            <ul v-if="m.attachments?.length" class="mt-1.5 space-y-0.5 text-xs opacity-90">
              <li v-for="(a, i) in m.attachments" :key="i" class="break-all">📎 {{ a.name }}</li>
            </ul>
            <div v-if="m.images?.length" class="flex flex-wrap gap-2 mt-2">
              <AuthImage v-for="img in m.images" :key="img.url" :url="img.url" :label="img.label" />
            </div>
            <div v-if="m.links.length" class="flex flex-wrap gap-2 mt-2">
              <router-link
                v-for="l in m.links"
                :key="l.to"
                :to="l.to"
                class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-white/80 dark:bg-gray-800 text-[#7C5CFC] border border-[#E4DEFF] dark:border-gray-600 hover:bg-white transition"
                @click="emit('navigate')"
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
                @click="send(s.text, false)"
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

      <!-- Raccourcis -->
      <div class="flex flex-wrap gap-2 px-4 pt-3 border-t border-[#ECEEF2] dark:border-gray-700">
        <button
          v-for="s in SHORTCUTS"
          :key="s.text"
          class="text-xs font-semibold px-3 py-1.5 rounded-full border border-[#E4DEFF] dark:border-gray-600 text-[#7C5CFC] hover:bg-[#F6F3FF] dark:hover:bg-gray-700 transition disabled:opacity-50"
          :disabled="sending"
          @click="send(s.text, false)"
        >
          {{ s.label }}
        </button>
      </div>

      <!-- Fichiers en attente d'envoi -->
      <ul v-if="files.length" class="flex flex-wrap gap-2 px-4 pt-3">
        <li
          v-for="(f, i) in files"
          :key="f.name + i"
          class="flex items-center gap-1.5 max-w-full text-xs px-2.5 py-1 rounded-full bg-[#F6F3FF] dark:bg-gray-700 text-[#5B3FD6] dark:text-gray-100 border border-[#E4DEFF] dark:border-gray-600"
        >
          <span class="truncate max-w-[200px]">📎 {{ f.name }}</span>
          <button
            type="button"
            class="font-bold hover:text-red-600"
            :aria-label="`Retirer ${f.name}`"
            :disabled="sending"
            @click="files.splice(i, 1)"
          >
            ×
          </button>
        </li>
      </ul>

      <!-- Saisie -->
      <form class="flex items-end gap-2 p-4" @submit.prevent="send(draft)">
        <input
          ref="fileInput"
          type="file"
          class="hidden"
          multiple
          accept="image/jpeg,image/png,image/webp,image/gif,application/pdf"
          @change="onPick"
        />
        <button
          type="button"
          class="px-3 py-2.5 text-base rounded-[11px] border border-[#ECEEF2] dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50"
          :disabled="sending || !ai.enabled"
          :title="
            ai.enabled
              ? 'Joindre des photos ou des PDF (3 maximum, 10 Mo chacun) : l\'IA d\'Anthropic les lit, vous dit ce que c\'est et propose la suite. Rien n\'est créé sans votre clic.'
              : 'Activez d\'abord la compréhension avancée (IA) pour que je puisse lire des photos et des PDF.'
          "
          aria-label="Joindre des photos ou des PDF"
          @click="fileInput?.click()"
        >
          📎
        </button>
        <textarea
          ref="input"
          v-model="draft"
          rows="2"
          maxlength="1000"
          class="field flex-1 resize-none"
          :placeholder="
            files.length
              ? 'Ajoutez une précision si besoin, puis envoyez…'
              : 'Écrivez à l\'orchestrateur… (Entrée pour envoyer, Maj+Entrée pour un retour à la ligne)'
          "
          :disabled="sending"
          @keydown.enter.exact.prevent="send(draft)"
        ></textarea>
        <button
          type="submit"
          class="px-4 py-2.5 text-sm font-semibold rounded-[11px] bg-[#7C5CFC] text-white hover:bg-[#6A49F0] transition disabled:opacity-50"
          :disabled="sending || (!draft.trim() && !files.length)"
        >
          Envoyer
        </button>
      </form>
      <p v-if="ai.enabled" class="px-4 pb-3 -mt-2 text-[11px] text-gray-400">
        Photos et PDF : lus par l'IA d'Anthropic (leur contenu lui est envoyé). Rien n'est créé sans votre clic.
      </p>
    </div>
    <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
/**
 * La conversation avec l'orchestrateur (le chef des agents), partagée par la page Orchestrateur et par
 * la fenêtre ouverte depuis Activité des agents. Elle charge l'historique à son affichage.
 *
 * `ordered` est émis quand une réponse vient d'un ordre confié à un agent (un événement a été créé) :
 * la page qui l'héberge peut alors se rafraîchir. `navigate` est émis quand l'administrateur suit un
 * lien de la réponse : une fenêtre modale doit alors se fermer.
 */
import { nextTick, onMounted, ref } from 'vue'
import http from '@/services/http'
import { useAuthStore } from '@/stores/authStore'
import { addFiles } from '@/utils/orchestratorFiles'
import AuthImage from './AuthImage.vue'

withDefaults(defineProps<{ heightClass?: string }>(), { heightClass: 'h-[62vh]' })

const emit = defineEmits<{ (e: 'ordered'): void; (e: 'navigate'): void }>()

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
  images?: { label: string; url: string }[]
  attachments?: { name: string; mime?: string; size?: number }[]
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
  { label: 'Que peut-on faire ?', text: 'que peut-on faire dans O3' },
  { label: 'Aide', text: 'aide' },
]

const auth = useAuthStore()
const messages = ref<ChatMessage[]>([])
const draft = ref('')
const loading = ref(false)
const sending = ref(false)
const error = ref('')
const scroller = ref<HTMLElement | null>(null)
const input = ref<HTMLTextAreaElement | null>(null)
const ai = ref<AiState>({ configured: false, enabled: false, model: '' })
const togglingAi = ref(false)
const files = ref<File[]>([])
const dragging = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

function takeFiles(list: FileList | File[] | null | undefined) {
  if (!list || !ai.value.enabled) return
  const r = addFiles(files.value, Array.from(list))
  files.value = r.files
  error.value = r.error
}

function onPick(e: Event) {
  const el = e.target as HTMLInputElement
  takeFiles(el.files)
  el.value = '' // permet de re-choisir le même fichier
}

function onDrop(e: DragEvent) {
  dragging.value = false
  if (!ai.value.enabled) {
    error.value = "Activez d'abord la compréhension avancée (IA) pour que je puisse lire des photos et des PDF."
    return
  }
  takeFiles(e.dataTransfer?.files)
}

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

async function send(text: string, withFiles = true) {
  const message = text.trim()
  const sentFiles = withFiles ? [...files.value] : []
  if ((!message && !sentFiles.length) || sending.value) return
  sending.value = true
  error.value = ''
  draft.value = withFiles ? '' : draft.value
  if (withFiles) files.value = []
  // Affichage immédiat du message de l'administrateur ; il est remplacé par la version enregistrée.
  const pending: ChatMessage = {
    id: -Date.now(),
    role: 'admin',
    body: message || (sentFiles.length > 1 ? 'Documents déposés' : 'Document déposé'),
    attachments: sentFiles.map((f) => ({ name: f.name })),
    links: [],
    error: false,
    event_id: null,
    created_at: new Date().toISOString(),
  }
  messages.value.push(pending)
  await scrollToEnd()
  try {
    let data: { user: ChatMessage; reply: ChatMessage }
    if (sentFiles.length) {
      const form = new FormData()
      if (message) form.append('message', message)
      sentFiles.forEach((f) => form.append('files[]', f))
      // La lecture par l'IA peut prendre quelques secondes par fichier.
      ;({ data } = await http.post<{ user: ChatMessage; reply: ChatMessage }>('/agents/orchestrateur/fichiers', form, {
        timeout: 180000,
      }))
    } else {
      ;({ data } = await http.post<{ user: ChatMessage; reply: ChatMessage }>('/agents/orchestrateur', { message }))
    }
    messages.value.splice(messages.value.indexOf(pending), 1, data.user, data.reply)
    if (data.reply.event_id || sentFiles.length) emit('ordered')
  } catch (e: unknown) {
    messages.value.splice(messages.value.indexOf(pending), 1)
    if (withFiles) {
      draft.value = message
      files.value = sentFiles
    }
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? "Le message n'a pas pu être envoyé."
  } finally {
    sending.value = false
    await scrollToEnd()
    input.value?.focus()
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

onMounted(async () => {
  await load()
  input.value?.focus()
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
