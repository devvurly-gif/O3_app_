<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDocumentStockStore } from '@/stores/stock/useDocumentStockStore'
import FlashMessages from '@/components/FlashMessages.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import DocumentLinesTable from '@/components/DocumentLinesTable.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
import { useFlash } from '@/composables/useFlash'
import { useFormat } from '@/composables/useFormat'
import { stockTypeLabels } from '@/composables/useDocumentLabels'
import type { DocumentHeader } from '@/types'

const route = useRoute()
const router = useRouter()
const store = useDocumentStockStore()

const { fmt } = useFormat()
const { successMsg, errorMsg, flash, flashError } = useFlash()

const doc = ref<DocumentHeader | null>(null)
const actionLoading = ref(false)
const showDeleteConfirm = ref(false)

onMounted(async () => {
  doc.value = await store.fetchOne(Number(route.params.id))
})

// ── Labels / config ────────────────────────────────────────────────────────
const typeLabels = stockTypeLabels

const typeColors: Record<string, string> = {
  StockEntry: 'text-green-700 dark:text-green-400',
  StockExit: 'text-red-700 dark:text-red-400',
  StockAdjustmentNote: 'text-indigo-700 dark:text-indigo-400',
  StockTransfer: 'text-blue-700 dark:text-blue-400',
}

// ── Actions visibility ─────────────────────────────────────────────────────
const canEdit = computed(() => doc.value?.status === 'draft')
const canApply = computed(() => doc.value && ['draft', 'confirmed'].includes(doc.value.status))
const canCancel = computed(() => doc.value && ['draft', 'confirmed'].includes(doc.value.status))
const canDelete = computed(() => doc.value?.status === 'draft')
const isApplied = computed(() => doc.value?.status === 'applied')
const isTransfer = computed(() => doc.value?.document_type === 'StockTransfer')
const isAdjustment = computed(() => doc.value?.document_type === 'StockAdjustmentNote')

// ── Actions ────────────────────────────────────────────────────────────────
async function applyDocument() {
  if (!doc.value) return
  actionLoading.value = true
  try {
    const result = await store.appliquer(doc.value.id)
    if (result.success) {
      doc.value = result.document!
      flash('Document appliqué. Les mouvements de stock ont été enregistrés.')
    } else {
      flashError(store.error ?? "Erreur lors de l'application.")
    }
  } catch {
    flashError("Erreur lors de l'application.")
  }
  actionLoading.value = false
}

async function cancelDocument() {
  if (!doc.value) return
  actionLoading.value = true
  try {
    const result = await store.annuler(doc.value.id)
    if (result.success) {
      doc.value = result.document!
      flash('Document annulé.')
    } else {
      flashError(store.error ?? "Erreur lors de l'annulation.")
    }
  } catch {
    flashError("Erreur lors de l'annulation.")
  }
  actionLoading.value = false
}

async function deleteDocument() {
  if (!doc.value) return
  actionLoading.value = true
  try {
    await store.remove(doc.value.id)
    router.push('/stock/documents')
  } catch {
    flashError('Erreur lors de la suppression.')
  }
  actionLoading.value = false
  showDeleteConfirm.value = false
}
</script>

<template>
  <div class="max-w-9xl mx-auto py-4 sm:py-6 px-3 sm:px-4">
    <!-- Loading -->
    <div v-if="store.loading && !doc" class="flex items-center justify-center py-24">
      <div class="flex flex-col items-center gap-3">
        <svg class="w-8 h-8 animate-spin text-violet-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <span class="text-sm text-gray-500 dark:text-gray-400">Chargement du document...</span>
      </div>
    </div>

    <!-- Document -->
    <div v-else-if="doc">
      <FlashMessages :success="successMsg" :error="errorMsg || store.error || ''" />

      <!-- Breadcrumb + Title Row -->
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div>
          <router-link
            to="/stock/documents"
            class="text-sm text-violet-600 dark:text-violet-400 hover:text-violet-800 dark:hover:text-violet-300 transition"
          >
            &larr; Documents de Stock
          </router-link>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2 flex items-center gap-3 flex-wrap">
            <span :class="typeColors[doc.document_type]">{{ typeLabels[doc.document_type] ?? doc.document_type }}</span>
            <span class="text-lg font-normal text-gray-400 dark:text-gray-500">#{{ doc.reference }}</span>
          </h1>
          <p
            v-if="doc.document_title && doc.document_title !== doc.document_type"
            class="text-sm text-gray-500 dark:text-gray-400 mt-0.5"
          >
            {{ doc.document_title }}
          </p>
        </div>

        <StatusBadge :status="doc.status" class="shrink-0 self-start" />
      </div>

      <!-- Action Bar -->
      <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-4 mb-6">
        <div class="flex flex-wrap items-center gap-2">
          <!-- Modifier -->
          <router-link
            v-if="canEdit"
            :to="`/stock/documents/${doc.id}/edit`"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition bg-amber-500 text-white hover:bg-amber-600"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
              />
            </svg>
            Modifier
          </router-link>

          <!-- Appliquer -->
          <button
            v-if="canApply"
            :disabled="actionLoading"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50"
            @click="applyDocument"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            Appliquer au stock
          </button>

          <!-- Badge appliqué -->
          <span
            v-if="isApplied"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 rounded-lg"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Mouvements enregistrés
          </span>

          <div class="flex-1"></div>

          <!-- Annuler -->
          <button
            v-if="canCancel"
            :disabled="actionLoading"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-50"
            @click="cancelDocument"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
              />
            </svg>
            Annuler
          </button>
          <button
            v-if="canDelete"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
            @click="showDeleteConfirm = true"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
              />
            </svg>
            Supprimer
          </button>
        </div>
      </div>

      <!-- Info cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-4">
          <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">
            {{ isTransfer ? 'Dépôt Source' : 'Dépôt' }}
          </p>
          <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ doc.warehouse?.wh_title ?? '—' }}</p>
        </div>
        <div
          v-if="isTransfer"
          class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-4"
        >
          <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">
            Dépôt Destination
          </p>
          <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
            {{ (doc as any).warehouseDest?.wh_title ?? '—' }}
          </p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-4">
          <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">
            Date d'émission
          </p>
          <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ doc.issued_at ?? '—' }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-4">
          <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Créé par</p>
          <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ doc.user?.name ?? '—' }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-4">
          <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Nb. lignes</p>
          <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ doc.lignes?.length ?? 0 }}</p>
        </div>
      </div>

      <!-- Ajustement hint -->
      <div
        v-if="isAdjustment"
        class="flex items-start gap-3 px-4 py-3 mb-6 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-xl text-sm text-indigo-800 dark:text-indigo-300"
      >
        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
        <span
          >La <strong>quantité</strong> représente la <strong>nouvelle valeur cible</strong> du stock. Lors de
          l'application, la différence avec le stock actuel sera enregistrée.</span
        >
      </div>

      <DocumentLinesTable :lines="doc.lignes ?? []" variant="stock" :adjustment="isAdjustment" />

      <!-- Notes -->
      <div
        v-if="doc.notes"
        class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 mb-6"
      >
        <p class="text-xs font-medium text-amber-600 dark:text-amber-400 uppercase tracking-wider mb-1">Notes</p>
        <p class="text-sm text-amber-900 dark:text-amber-200">{{ doc.notes }}</p>
      </div>

      <!-- Mouvements générés (après application) -->
      <div
        v-if="isApplied && (doc as any).stockMouvements?.length"
        class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden mb-6"
      >
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">
            Mouvements de stock générés
          </h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50/50 dark:bg-gray-800/60 text-gray-500 dark:text-gray-400 uppercase text-xs">
              <tr>
                <th class="text-left px-5 py-3">Produit</th>
                <th class="text-left px-5 py-3">Dépôt</th>
                <th class="text-center px-5 py-3">Direction</th>
                <th class="text-right px-5 py-3">Quantité</th>
                <th class="text-right px-5 py-3">Avant</th>
                <th class="text-right px-5 py-3">Après</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr
                v-for="mv in (doc as any).stockMouvements"
                :key="mv.id"
                class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition"
              >
                <td class="px-5 py-3 text-gray-800 dark:text-gray-200 font-medium">{{ mv.product?.p_title ?? '—' }}</td>
                <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ mv.warehouse?.wh_title ?? '—' }}</td>
                <td class="px-5 py-3 text-center">
                  <span
                    :class="
                      mv.direction === 'in'
                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                        : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                    "
                    class="px-2 py-0.5 rounded-full text-xs font-medium"
                  >
                    {{ mv.direction === 'in' ? '↑ Entrée' : '↓ Sortie' }}
                  </span>
                </td>
                <td class="px-5 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">
                  {{ fmt(mv.quantity) }}
                </td>
                <td class="px-5 py-3 text-right font-mono text-gray-400">{{ fmt(mv.stock_before) }}</td>
                <td class="px-5 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">
                  {{ fmt(mv.stock_after) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Not found -->
    <div v-else class="flex flex-col items-center justify-center py-24 text-gray-400 dark:text-gray-500">
      <svg class="w-16 h-16 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
        />
      </svg>
      <p class="text-lg font-medium">Document introuvable</p>
      <router-link to="/stock/documents" class="text-sm text-violet-600 dark:text-violet-400 hover:underline mt-2">
        Retour à la liste
      </router-link>
    </div>

    <ConfirmModal
      v-model="showDeleteConfirm"
      title="Supprimer le document"
      confirm-label="Supprimer"
      loading-label="Suppression..."
      :loading="actionLoading"
      @confirm="deleteDocument"
    >
      Êtes-vous sûr de vouloir supprimer ce document ? Cette action est irréversible.
    </ConfirmModal>
  </div>
</template>
