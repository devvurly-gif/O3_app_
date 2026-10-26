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
        <div
          v-for="a in agents"
          :key="a.domain"
          class="bg-white dark:bg-gray-800 border rounded-2xl transition hover:border-[#7C5CFC]"
          :class="
            agentFilter === a.domain
              ? 'border-[#7C5CFC] ring-1 ring-[#7C5CFC]'
              : 'border-[#ECEEF2] dark:border-gray-700'
          "
        >
          <button type="button" class="block w-full text-left px-4 pt-3 pb-2" @click="openAgent(a)">
            <span class="block font-semibold text-sm text-gray-800 dark:text-gray-100 truncate">{{ a.name }}</span>
            <span class="block text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">
              {{ a.account ? `Compte : ${a.account}` : 'Aucun compte rattaché' }}
            </span>
            <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              {{ a.events_count }} événement{{ a.events_count > 1 ? 's' : '' }}
              <span v-if="a.last_event_at">· dernier {{ timeAgo(a.last_event_at) }}</span>
            </span>
          </button>
          <div class="flex items-center justify-between gap-2 px-4 py-2 border-t border-[#ECEEF2] dark:border-gray-700">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <button
                type="button"
                role="switch"
                :aria-checked="a.is_active"
                :aria-label="`${a.is_active ? 'Désactiver' : 'Activer'} l'agent ${a.name}`"
                :disabled="busyAgent === a.id"
                class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition disabled:opacity-50"
                :class="a.is_active ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600'"
                @click="toggleAgentState(a)"
              >
                <span
                  class="inline-block h-4 w-4 rounded-full bg-white shadow transition-transform"
                  :class="a.is_active ? 'translate-x-4' : 'translate-x-0.5'"
                ></span>
              </button>
              <span
                class="text-xs font-semibold"
                :class="a.is_active ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-500 dark:text-gray-400'"
              >
                {{ a.is_active ? 'Actif' : 'Inactif' }}
              </span>
            </label>
            <button
              v-if="!a.account"
              type="button"
              :disabled="busyAgent === a.id"
              class="text-xs font-semibold text-[#7C5CFC] hover:underline disabled:opacity-50"
              @click="createAgentAccount(a)"
            >
              + Compte
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Fiche d'un agent : ses tâches, la phrase à écrire, et « + » pour en configurer d'autres -->
    <Teleport to="body">
      <div
        v-if="selectedAgent"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="selectedAgent = null"
        @keydown.esc="selectedAgent = null"
      >
        <section
          role="dialog"
          :aria-label="`Tâches de l'agent ${selectedAgent.name}`"
          class="w-full max-w-2xl max-h-[calc(100vh-2rem)] overflow-y-auto bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-[#ECEEF2] dark:border-gray-700"
        >
          <header
            class="flex items-start justify-between gap-3 px-5 py-4 border-b border-[#ECEEF2] dark:border-gray-700"
          >
            <div>
              <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">{{ selectedAgent.name }}</h3>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                {{ selectedAgent.is_active ? 'Actif' : 'Inactif : ses tâches ne s’exécutent pas' }}
                · {{ selectedAgent.events_count }} événement{{ selectedAgent.events_count > 1 ? 's' : '' }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="text-xs font-semibold text-[#7C5CFC] hover:underline"
                @click="filterByAgent(selectedAgent)"
              >
                Voir ses événements
              </button>
              <button
                type="button"
                aria-label="Fermer"
                class="w-7 h-7 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                @click="selectedAgent = null"
              >
                ✕
              </button>
            </div>
          </header>

          <div class="px-5 py-4 space-y-4">
            <div class="flex items-center justify-between">
              <h4 class="text-sm font-bold text-gray-700 dark:text-gray-200">Tâches exécutées</h4>
              <button
                type="button"
                aria-label="Configurer une autre tâche pour cet agent"
                title="Configurer une autre tâche"
                class="w-8 h-8 flex items-center justify-center rounded-full bg-[#7C5CFC] text-white text-lg leading-none hover:bg-[#6A49F0] transition"
                @click="adding = !adding"
              >
                +
              </button>
            </div>

            <form
              v-if="adding"
              class="rounded-xl border border-[#7C5CFC]/40 bg-[#7C5CFC]/5 p-3 space-y-2"
              @submit.prevent="submitNewTask"
            >
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300" for="new-task-kind"
                >Type</label
              >
              <select id="new-task-kind" v-model="newKind" class="field w-full">
                <option v-for="(k, key) in KINDS" :key="key" :value="key">{{ k.label }}</option>
              </select>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300" for="new-task-text">
                Ce que l'agent doit faire
              </label>
              <textarea
                id="new-task-text"
                v-model="newText"
                rows="2"
                class="field w-full"
                :placeholder="KINDS[newKind].hint"
              ></textarea>
              <p class="text-[11px] text-gray-500 dark:text-gray-400">
                La phrase est envoyée à l'orchestrateur, qui vous propose la configuration : rien n'est enregistré avant
                votre clic de confirmation.
              </p>
              <div class="flex justify-end gap-2">
                <button
                  type="button"
                  class="px-3 py-1.5 text-xs rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                  @click="adding = false"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  :disabled="!newText.trim()"
                  class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-[#7C5CFC] text-white disabled:opacity-50"
                >
                  Envoyer à l'orchestrateur
                </button>
              </div>
            </form>

            <p v-if="!selectedAgent.tasks.length" class="text-sm text-gray-500 dark:text-gray-400">
              Aucune tâche pour l'instant. Utilisez « + » pour en demander une.
            </p>
            <ul v-else class="space-y-2">
              <li
                v-for="t in selectedAgent.tasks"
                :key="t.label"
                class="rounded-xl border border-[#ECEEF2] dark:border-gray-700 px-3 py-2.5"
              >
                <p class="text-sm text-gray-800 dark:text-gray-100">{{ t.label }}</p>
                <div v-if="promptOf(t.how)" class="mt-1.5 flex items-center gap-2">
                  <code
                    class="flex-1 text-xs bg-gray-50 dark:bg-gray-900 rounded-md px-2 py-1 text-gray-700 dark:text-gray-200 break-words"
                  >
                    {{ promptOf(t.how) }}
                  </code>
                  <button
                    type="button"
                    class="text-xs text-gray-500 hover:text-[#7C5CFC]"
                    @click="copyPrompt(promptOf(t.how)!)"
                  >
                    Copier
                  </button>
                  <button
                    type="button"
                    class="text-xs font-semibold text-[#7C5CFC] hover:underline"
                    @click="runPrompt(promptOf(t.how)!)"
                  >
                    Lancer
                  </button>
                </div>
                <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">Comment : {{ t.how }}</p>
              </li>
            </ul>

            <div v-if="selectedAgent.missing.length">
              <h4 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-1">Pas encore disponible</h4>
              <ul class="list-disc pl-5 text-sm text-gray-500 dark:text-gray-400 space-y-0.5">
                <li v-for="m in selectedAgent.missing" :key="m">{{ m }}</li>
              </ul>
            </div>
          </div>
        </section>
      </div>
    </Teleport>

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
          <OrchestratorChat ref="chat" height-class="h-[min(48vh,420px)]" @ordered="load()" />
        </div>
      </section>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
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
  id: number
  domain: string
  name: string
  is_active: boolean
  account: string | null
  ability: string | null
  events_count: number
  last_event_at: string | null
  tasks: { label: string; how: string }[]
  missing: string[]
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

const chat = ref<InstanceType<typeof OrchestratorChat> | null>(null)
const selectedAgent = ref<AgentRow | null>(null)
const adding = ref(false)
const newKind = ref<'routine' | 'consigne' | 'developpement'>('routine')
const newText = ref('')

const KINDS = {
  routine: {
    label: 'Routine planifiée',
    hint: 'Ex. : chaque lundi à 8 h, contrôle les encaissements',
    prefix: '',
  },
  consigne: {
    label: 'Consigne (règle de la maison)',
    hint: 'Ex. : toujours arrondir les prix au dirham',
    prefix: 'retiens : ',
  },
  developpement: {
    label: 'Nouvelle tâche à développer',
    hint: 'Ex. : comparer les prix de plusieurs fournisseurs',
    prefix: 'demande de développement : ',
  },
} as const

function openAgent(a: AgentRow) {
  selectedAgent.value = a
  adding.value = false
  newText.value = ''
}

/** La phrase à écrire au chef, extraite de « … » ; null si la tâche ne se lance pas par une phrase (fichier, planificateur). */
function promptOf(how: string): string | null {
  const m = how.match(/«\s*(.+?)\s*»/)
  return m ? m[1] : null
}

async function runPrompt(text: string) {
  selectedAgent.value = null
  openChat()
  await nextTick()
  await chat.value?.send(text)
}

async function copyPrompt(text: string) {
  try {
    await navigator.clipboard.writeText(text)
  } catch {
    /* Presse-papiers indisponible : la phrase reste affichée. */
  }
}

async function submitNewTask() {
  // Le préfixe est ajouté par le formulaire : on retire celui que l'utilisateur a pu écrire lui-même.
  const body = newText.value
    .trim()
    .replace(/^(demande de d[eé]veloppement|retiens)\s*(pour l'agent[^:]*)?\s*:?\s*/i, '')
    .trim()
  if (!body || !selectedAgent.value) return
  const kind = KINDS[newKind.value]
  const text =
    newKind.value === 'developpement'
      ? `${kind.prefix}pour l'agent ${selectedAgent.value.name} : ${body}`
      : `${kind.prefix}${body}`
  await runPrompt(text)
}

function filterByAgent(a: AgentRow) {
  selectedAgent.value = null
  if (agentFilter.value !== a.domain) toggleAgent(a.domain)
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
    if (selectedAgent.value) {
      selectedAgent.value = data.agents.find((x: AgentRow) => x.id === selectedAgent.value?.id) ?? null
    }
    events.value = data.events
    Object.assign(meta, data.meta)
  } catch {
    /* Erreur déjà affichée par l'intercepteur http. */
  } finally {
    loading.value = false
  }
}

/** Agent dont une action directe (activation, compte) est en cours : bloque ses boutons le temps de l'appel. */
const busyAgent = ref<number | null>(null)

async function toggleAgentState(a: AgentRow) {
  if (busyAgent.value !== null) return
  busyAgent.value = a.id
  try {
    await http.put(`/agents/${a.id}/etat`, { is_active: !a.is_active })
    await load()
  } catch {
    /* Erreur déjà affichée par l'intercepteur http. */
  } finally {
    busyAgent.value = null
  }
}

async function createAgentAccount(a: AgentRow) {
  if (busyAgent.value !== null) return
  busyAgent.value = a.id
  try {
    await http.post(`/agents/${a.id}/compte`)
    await load()
  } catch {
    /* Erreur déjà affichée par l'intercepteur http. */
  } finally {
    busyAgent.value = null
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
