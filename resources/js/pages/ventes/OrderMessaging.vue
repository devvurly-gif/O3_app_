<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h2 class="text-[26px] sm:text-[30px] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white">
          Messagerie commandes
        </h2>
        <p class="text-sm text-[#8A8F9C] dark:text-gray-400 mt-1">
          Commandes reçues par WhatsApp, SMS ou chat, et saisie d'un BL en texte libre. Chaque commande comprise crée un
          BL en brouillon, à confirmer vous-même.
        </p>
      </div>
      <select
        v-model="channelFilter"
        class="px-3 py-2 text-sm rounded-[11px] border border-[#ECEEF2] dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200"
        @change="loadConversations"
      >
        <option value="">Tous les canaux</option>
        <option v-for="(label, key) in CHANNEL_LABELS" :key="key" :value="key">{{ label }}</option>
      </select>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[320px_1fr] gap-4">
      <!-- Conversations -->
      <div
        class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl overflow-hidden flex flex-col max-h-[70vh]"
      >
        <button
          class="flex items-center gap-2 px-4 py-3 text-sm font-bold text-[#7C5CFC] hover:bg-[#F6F3FF] dark:hover:bg-gray-700 border-b border-[#ECEEF2] dark:border-gray-700 transition"
          @click="newConversation"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          Nouveau BL par message
        </button>
        <div class="overflow-y-auto flex-1">
          <p v-if="!conversations.length && !loadingConversations" class="px-4 py-8 text-center text-sm text-gray-400">
            Aucune conversation pour l'instant.
          </p>
          <button
            v-for="c in conversations"
            :key="c.key"
            class="w-full text-left px-4 py-3 border-b border-gray-50 dark:border-gray-700 last:border-0 transition"
            :class="
              c.key === activeKey ? 'bg-[#F6F3FF] dark:bg-gray-700' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'
            "
            @click="openConversation(c)"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="font-semibold text-sm text-gray-800 dark:text-gray-100 truncate">{{ c.title }}</span>
              <span class="text-[11px] text-gray-400 shrink-0">{{ timeAgo(c.last_at) }}</span>
            </div>
            <div class="flex items-center gap-1.5 mt-1">
              <span
                v-for="ch in c.channels"
                :key="ch"
                class="text-[10px] font-bold px-1.5 py-0.5 rounded-md"
                :class="channelClass(ch)"
              >
                {{ CHANNEL_LABELS[ch] ?? ch }}
              </span>
              <span v-if="c.last_status" class="text-[10px] font-semibold" :class="statusClass(c.last_status)">
                {{ STATUS_LABELS[c.last_status] ?? c.last_status }}
              </span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">{{ c.last_body }}</p>
          </button>
        </div>
      </div>

      <!-- Fil + saisie -->
      <div
        class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl flex flex-col min-h-[60vh] max-h-[70vh]"
      >
        <div class="px-5 py-3 border-b border-[#ECEEF2] dark:border-gray-700">
          <p class="font-semibold text-gray-800 dark:text-gray-100">{{ activeTitle }}</p>
          <p v-if="!activeKey" class="text-xs text-[#8A8F9C]">
            Choisissez le client, puis tapez la commande : un article par ligne, quantité puis produit.
          </p>
        </div>

        <div ref="threadEl" class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
          <p v-if="activeKey && !messages.length && !loadingThread" class="text-center text-sm text-gray-400 py-8">
            Aucun message.
          </p>
          <div
            v-for="m in messages"
            :key="m.id"
            class="flex"
            :class="m.direction === 'out' ? 'justify-end' : 'justify-start'"
          >
            <div
              class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm whitespace-pre-line"
              :class="
                m.direction === 'out'
                  ? 'bg-[#F6F3FF] dark:bg-[#7C5CFC]/20 text-gray-800 dark:text-gray-100 rounded-br-md'
                  : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 rounded-bl-md'
              "
            >
              <div class="flex items-center gap-1.5 mb-1 text-[11px] text-gray-400">
                <span class="font-bold px-1.5 py-0.5 rounded-md" :class="channelClass(m.channel)">
                  {{ CHANNEL_LABELS[m.channel] ?? m.channel }}
                </span>
                <span v-if="m.author">{{ m.author }}</span>
                <span v-if="m.direction === 'out'">Réponse automatique</span>
                <span
                  v-if="m.parse_method === 'ai'"
                  title="Message lu par l'IA (les règles simples n'avaient pas tout compris)"
                  >· IA</span
                >
                <span>· {{ formatTime(m.created_at) }}</span>
                <span v-if="m.direction === 'out' && m.status === 'failed'" class="text-red-500 font-semibold">
                  · non envoyée
                </span>
              </div>
              {{ m.body }}
              <router-link
                v-if="m.document && m.direction === 'in'"
                :to="`/ventes/documents/${m.document.id}`"
                class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-[#7C5CFC] hover:underline"
              >
                Ouvrir {{ m.document.reference }} ({{
                  m.document.status === 'draft' ? 'brouillon' : m.document.status
                }})
              </router-link>
            </div>
          </div>
        </div>

        <!-- Saisie -->
        <form class="border-t border-[#ECEEF2] dark:border-gray-700 p-4 space-y-3" @submit.prevent="send">
          <div class="relative">
            <div
              v-if="customer"
              class="flex items-center justify-between gap-2 px-3 py-2 rounded-[11px] bg-[#EFF1F5] dark:bg-gray-700 text-sm"
            >
              <span class="text-gray-800 dark:text-gray-100">
                Client : <strong>{{ customer.tp_title }}</strong>
                <span v-if="customer.tp_code" class="text-gray-400"> · {{ customer.tp_code }}</span>
              </span>
              <button type="button" class="text-xs text-gray-500 hover:text-red-500" @click="customer = null">
                Changer
              </button>
            </div>
            <template v-else>
              <input
                v-model="customerSearch"
                type="text"
                placeholder="Rechercher le client (nom, code, téléphone)…"
                class="w-full px-3 py-2 text-sm rounded-[11px] border border-[#ECEEF2] dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-100"
                @input="searchCustomers"
              />
              <div
                v-if="customerResults.length"
                class="absolute z-10 left-0 right-0 bottom-full mb-1 bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-xl shadow-lg max-h-60 overflow-y-auto"
              >
                <button
                  v-for="r in customerResults"
                  :key="r.id"
                  type="button"
                  class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                  @click="pickCustomer(r)"
                >
                  <span class="font-semibold text-gray-800 dark:text-gray-100">{{ r.tp_title }}</span>
                  <span class="text-gray-400">
                    · {{ r.tp_code }}<template v-if="r.tp_phone"> · {{ r.tp_phone }}</template></span
                  >
                </button>
              </div>
            </template>
          </div>

          <textarea
            v-model="text"
            rows="4"
            maxlength="4000"
            :placeholder="'2 perceuse 18V\n5 boîte vis 4x40\nmarteau x3'"
            class="w-full px-3 py-2 text-sm rounded-[11px] border border-[#ECEEF2] dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-100 font-mono"
            @keydown.ctrl.enter.prevent="send"
          ></textarea>

          <div class="flex items-center justify-between gap-3 flex-wrap">
            <p class="text-xs text-[#8A8F9C]">
              Sans client choisi, indiquez-le en 1re ligne : « Client : code, téléphone ou nom exact ». Ctrl+Entrée pour
              envoyer.
            </p>
            <button
              type="submit"
              :disabled="sending || !text.trim()"
              class="px-5 py-2.5 bg-[#7C5CFC] hover:bg-[#6D4CE0] disabled:opacity-50 text-white text-sm font-bold rounded-[11px] transition"
            >
              {{ sending ? 'Analyse…' : 'Créer le BL brouillon' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <BaseNotification ref="toast" />
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import http from '@/services/http'
import BaseNotification from '@/components/BaseNotification.vue'

interface Conversation {
  key: string
  title: string
  subtitle: string | null
  channels: string[]
  last_body: string
  last_at: string
  last_status: string | null
}

interface Message {
  id: number
  channel: string
  direction: 'in' | 'out'
  body: string
  status: string | null
  author: string | null
  document: { id: number; reference: string; status: string } | null
  parse_method: string | null
  created_at: string
}

interface Customer {
  id: number
  tp_title: string
  tp_code: string | null
  tp_phone: string | null
  tp_Role?: string
}

const CHANNEL_LABELS: Record<string, string> = {
  whatsapp: 'WhatsApp',
  sms: 'SMS',
  web_staff: 'Chat équipe',
  web_client: 'Chat boutique',
}

const STATUS_LABELS: Record<string, string> = {
  created: 'BL créé',
  rejected: 'À corriger',
  ignored: 'Ignoré',
  error: 'Erreur',
}

const toast = ref<InstanceType<typeof BaseNotification> | null>(null)
const threadEl = ref<HTMLElement | null>(null)

const conversations = ref<Conversation[]>([])
const loadingConversations = ref(false)
const channelFilter = ref('')

const activeKey = ref<string | null>(null)
const activeConversation = ref<Conversation | null>(null)
const messages = ref<Message[]>([])
const loadingThread = ref(false)

const customer = ref<Customer | null>(null)
const customerSearch = ref('')
const customerResults = ref<Customer[]>([])
const text = ref('')
const sending = ref(false)

const activeTitle = computed(() => activeConversation.value?.title ?? 'Nouveau BL par message')

async function loadConversations() {
  loadingConversations.value = true
  try {
    const { data } = await http.get('/messagerie/conversations', {
      params: channelFilter.value ? { channel: channelFilter.value } : {},
    })
    conversations.value = data.data ?? []
    if (activeKey.value) {
      activeConversation.value = conversations.value.find((c) => c.key === activeKey.value) ?? activeConversation.value
    }
  } finally {
    loadingConversations.value = false
  }
}

async function loadThread(scroll = true) {
  if (!activeKey.value) return
  loadingThread.value = true
  try {
    const { data } = await http.get('/messagerie/fil', { params: { key: activeKey.value } })
    const before = messages.value.length
    messages.value = data.data ?? []
    if (scroll || messages.value.length !== before) scrollToBottom()
  } finally {
    loadingThread.value = false
  }
}

function scrollToBottom() {
  nextTick(() => {
    if (threadEl.value) threadEl.value.scrollTop = threadEl.value.scrollHeight
  })
}

async function openConversation(c: Conversation) {
  activeKey.value = c.key
  activeConversation.value = c
  messages.value = []
  // Conversation d'un client : il est présélectionné pour la saisie.
  if (c.key.startsWith('c')) {
    customer.value = { id: Number(c.key.slice(1)), tp_title: c.title, tp_code: c.subtitle, tp_phone: null }
  }
  await loadThread()
}

function newConversation() {
  activeKey.value = null
  activeConversation.value = null
  messages.value = []
  customer.value = null
  text.value = ''
}

let searchTimer: ReturnType<typeof setTimeout> | undefined
function searchCustomers() {
  clearTimeout(searchTimer)
  const term = customerSearch.value.trim()
  if (term.length < 2) {
    customerResults.value = []
    return
  }
  searchTimer = setTimeout(async () => {
    const { data } = await http.get('/third-partners', { params: { search: term, per_page: 20 } })
    const rows: Customer[] = Array.isArray(data) ? data : (data.data ?? [])
    customerResults.value = rows.filter((r) => r.tp_Role === 'customer' || r.tp_Role === 'both')
  }, 250)
}

function pickCustomer(r: Customer) {
  customer.value = r
  customerSearch.value = ''
  customerResults.value = []
}

async function send() {
  if (!text.value.trim() || sending.value) return
  sending.value = true
  try {
    const { data } = await http.post('/messagerie/commandes', {
      third_partner_id: customer.value?.id ?? null,
      text: text.value,
    })
    if (data.status === 'created') {
      toast.value?.notify(`BL brouillon ${data.document?.reference} créé.`, 'success')
      text.value = ''
    } else {
      toast.value?.notify('Commande non enregistrée : voir la réponse dans le fil.', 'error')
    }
    await loadConversations()
    // Ouvre (ou garde) la conversation où le message vient d'être rangé.
    const key = customer.value ? `c${customer.value.id}` : activeKey.value
    const conv = conversations.value.find((c) => c.key === key) ?? conversations.value[0]
    if (conv) {
      activeKey.value = conv.key
      activeConversation.value = conv
      await loadThread()
    }
  } catch {
    // Erreur déjà affichée par l'intercepteur http.
  } finally {
    sending.value = false
  }
}

function channelClass(ch: string) {
  switch (ch) {
    case 'whatsapp':
      return 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
    case 'sms':
      return 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'
    case 'web_client':
      return 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300'
    default:
      return 'bg-[#EFF1F5] text-gray-600 dark:bg-gray-700 dark:text-gray-300'
  }
}

function statusClass(s: string) {
  if (s === 'created') return 'text-green-600'
  if (s === 'rejected' || s === 'error') return 'text-red-500'
  return 'text-gray-400'
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

// Les messages WhatsApp/SMS arrivent sans action de l'utilisateur : rafraîchissement régulier.
let poll: ReturnType<typeof setInterval> | undefined
onMounted(async () => {
  await loadConversations()
  poll = setInterval(() => {
    loadConversations()
    loadThread(false)
  }, 15000)
})
onBeforeUnmount(() => {
  clearInterval(poll)
  clearTimeout(searchTimer)
})
</script>
