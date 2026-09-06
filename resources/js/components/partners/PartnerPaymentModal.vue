<template>
  <BaseModal v-model="show" :title="'Enregistrer un paiement — ' + (partnerName ?? '')" size="md">
    <div class="space-y-5">
      <!-- Documents restant a solder, coches par defaut -->
      <div v-if="unpaidDocs.length > 0" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <div class="flex items-center justify-between mb-2">
          <p class="text-sm font-semibold text-amber-800">Documents impayés (BL + Factures)</p>
          <button
            type="button"
            class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 hover:bg-amber-200"
            @click="emit('toggle-all')"
          >
            {{ allSelected ? 'Tout décocher' : 'Tout cocher' }}
          </button>
        </div>
        <div class="max-h-48 overflow-y-auto space-y-1">
          <label
            v-for="doc in unpaidDocs"
            :key="doc.id"
            class="flex items-center justify-between gap-2 text-sm py-1.5 px-2 rounded hover:bg-amber-100/50 cursor-pointer"
          >
            <div class="flex items-center gap-2 min-w-0">
              <input
                :checked="selectedIds.includes(doc.id)"
                type="checkbox"
                class="w-4 h-4 rounded border-amber-300 text-emerald-600 focus:ring-emerald-500"
                @change="emit('toggle-doc', doc.id)"
              />
              <span
                class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase"
                :class="doc.document_type === 'DeliveryNote'
                  ? 'bg-[#F1ECFC] text-[#6D4CE0]'
                  : doc.document_type === 'InvoicePurchase'
                    ? 'bg-purple-100 text-purple-700'
                    : 'bg-emerald-100 text-emerald-700'"
              >
                {{ docTypeShortLabel(doc.document_type) }}
              </span>
              <span class="font-mono text-xs text-gray-700 dark:text-gray-400">{{ doc.reference }}</span>
              <span class="text-xs text-gray-400 dark:text-gray-500">{{ formatDate(doc.issued_at) }}</span>
            </div>
            <span class="font-mono text-sm font-medium text-red-600 whitespace-nowrap"
              >{{ formatNumber(Number(doc.footer?.amount_due ?? 0)) }}
              <span class="text-xs text-gray-400 dark:text-gray-500">DH</span></span
            >
          </label>
        </div>
        <div class="mt-2 pt-2 border-t border-amber-200 flex items-center justify-between">
          <span class="text-sm font-semibold text-amber-800"> {{ selectedIds.length }} sélectionné(s) / Total dû </span>
          <span class="font-mono text-base font-bold text-red-600"
            >{{ formatNumber(selectedTotalDue) }} <span class="text-xs text-gray-400 dark:text-gray-500">DH</span></span
          >
        </div>
      </div>

      <!-- Rien a solder, et rien de soldable non plus -->
      <div v-else-if="!loading && payableDocs.length === 0" class="text-center py-8 text-gray-400 dark:text-gray-500">
        <svg
          class="w-10 h-10 mx-auto mb-2 text-gray-300"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
        <p class="text-sm">{{ emptyMessage }}</p>
      </div>

      <!-- Tout est solde, mais des documents restent : reglement a l'unite -->
      <div v-else-if="allowSingleDoc && !loading && unpaidDocs.length === 0" class="space-y-4">
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-sm text-emerald-800">
          Toutes les factures sont soldées. Vous pouvez enregistrer un paiement sur une facture spécifique (ex.
          correction, avoir).
        </div>
        <div>
          <label :for="`${idPrefix}-single-doc`" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Facture <span class="text-red-500">*</span>
          </label>
          <select
            :id="`${idPrefix}-single-doc`"
            v-model="selectedDocId"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
          >
            <option :value="null" disabled>-- Choisir une facture --</option>
            <option v-for="doc in payableDocs" :key="doc.id" :value="doc.id">
              {{ doc.reference }} — {{ formatDate(doc.issued_at) }} — {{ formatNumber(Number(doc.footer?.total_ttc ?? 0)) }} DH
            </option>
          </select>
        </div>
        <PartnerPaymentFields
          v-model:amount="amount"
          v-model:method="method"
          v-model:reference="reference"
          v-model:notes="notes"
          :id-prefix="`${idPrefix}-single`"
        />
      </div>

      <div v-if="loading" class="flex items-center justify-center py-8">
        <svg class="w-6 h-6 animate-spin text-[#7C5CFC]" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
        </svg>
      </div>

      <!-- Reglement groupe -->
      <div v-if="unpaidDocs.length > 0 && !loading" class="space-y-4">
        <PartnerPaymentFields
          v-model:amount="amount"
          v-model:method="method"
          v-model:reference="reference"
          v-model:notes="notes"
          :id-prefix="idPrefix"
          :selected-total-due="selectedTotalDue"
        />

        <div
          v-if="result"
          class="rounded-lg border p-3 text-sm"
          :class="
            result.remaining > 0
              ? 'bg-amber-50 border-amber-200 text-amber-800'
              : 'bg-emerald-50 border-emerald-200 text-emerald-800'
          "
        >
          <p class="font-semibold">{{ result.message }}</p>
          <p class="text-xs mt-1">Montant affecté : {{ formatNumber(result.total_applied) }} DH</p>
          <p v-if="result.remaining > 0" class="text-xs">
            Excédent non affecté : {{ formatNumber(result.remaining) }} DH
          </p>
        </div>
      </div>
    </div>

    <template #footer>
      <button
        class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition"
        @click="show = false"
      >
        Fermer
      </button>
      <button
        v-if="unpaidDocs.length > 0 && !loading"
        class="px-4 py-2 text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition disabled:opacity-60"
        :disabled="saving || !amount || amount <= 0 || selectedIds.length === 0"
        @click="emit('submit')"
      >
        <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
        {{ saving ? 'Enregistrement...' : 'Enregistrer le paiement' }}
      </button>
      <button
        v-else-if="allowSingleDoc && !loading && payableDocs.length > 0"
        class="px-4 py-2 text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition disabled:opacity-60"
        :disabled="saving || !selectedDocId || !amount || amount <= 0"
        @click="emit('submit-single')"
      >
        <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
        {{ saving ? 'Enregistrement...' : 'Enregistrer le paiement' }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup lang="ts">
/**
 * Le reglement depuis une fiche tiers.
 *
 * Fournisseurs.vue et Clients.vue portaient le meme ecran, au prefixe pres.
 * Deux choses seulement les separaient, devenues des options :
 *
 * - la phrase affichee quand il n'y a rien a solder, `emptyMessage` ;
 * - le reglement a l'unite, `allowSingleDoc`, que seule la fiche client
 *   propose : quand tout est deja solde, elle laisse imputer un montant sur
 *   une facture choisie, pour une correction ou un avoir. Cote achat, ce
 *   repli n'existe pas — `payableDocs` y reste vide, et la branche ne se
 *   rend jamais.
 *
 * L'etat vit dans `useBulkPayment`, chez l'ecran hote : cette modale n'ecrit
 * que les quatre champs du formulaire, et par `defineModel`.
 */
import BaseModal from '@/components/BaseModal.vue'
import PartnerPaymentFields from '@/components/partners/PartnerPaymentFields.vue'
import { docTypeShortLabel } from '@/composables/useDocumentLabels'
import { formatAmount as formatNumber, useFormat } from '@/composables/useFormat'

withDefaults(
  defineProps<{
    /** Nom du tiers, pour le titre. */
    partnerName?: string
    loading: boolean
    saving: boolean
    /** Ce que le serveur a repondu au dernier encaissement groupe. */
    result?: any
    unpaidDocs: any[]
    /** Documents soldables, soldes compris. Vide cote achat. */
    payableDocs?: any[]
    selectedIds: number[]
    selectedTotalDue: number
    allSelected: boolean
    emptyMessage: string
    /** Prefixe des identifiants de champ, unique par ecran. */
    idPrefix: string
    allowSingleDoc?: boolean
  }>(),
  { partnerName: '', result: null, payableDocs: () => [], allowSingleDoc: false },
)

const emit = defineEmits<{
  'toggle-doc': [id: number]
  'toggle-all': []
  submit: []
  'submit-single': []
}>()

const show = defineModel<boolean>({ required: true })
const amount = defineModel<number>('amount', { required: true })
const method = defineModel<string>('method', { required: true })
const reference = defineModel<string>('reference', { required: true })
const notes = defineModel<string>('notes', { required: true })
const selectedDocId = defineModel<number | null>('selectedDocId', { default: null })

const { date: formatDate } = useFormat()
</script>
