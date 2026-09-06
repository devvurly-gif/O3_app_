<template>
  <PartnerTabSpinner v-if="loading" />
  <div v-else-if="documents.length === 0" class="text-center py-12 text-gray-400 dark:text-gray-500">
    <svg
      class="w-12 h-12 mx-auto mb-3 text-gray-300"
      fill="none"
      stroke="currentColor"
      stroke-width="1.5"
      viewBox="0 0 24 24"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
      />
    </svg>
    <p class="text-sm">Aucun document</p>
  </div>
  <div v-else class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
          <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Code</th>
          <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Date</th>
          <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Type</th>
          <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">Total TTC</th>
          <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">Reste dû</th>
          <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-center">Statut</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <tr
          v-for="inv in documents"
          :key="inv.id"
          class="hover:bg-gray-50 dark:hover:bg-gray-700"
          :class="isBilled(inv) ? 'opacity-50' : ''"
          :title="isBilled(inv) ? billedTitle : ''"
        >
          <td class="py-2.5 px-3 font-mono text-xs">{{ inv.reference }}</td>
          <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400">{{ formatDate(inv.issued_at) }}</td>
          <td class="py-2.5 px-3">
            <span
              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
              :class="partnerDocTypeBadgeClass(inv.document_type)"
            >
              {{ partnerDocTypeLabel(inv.document_type) }}
            </span>
          </td>
          <td class="py-2.5 px-3 text-right font-mono font-medium">
            {{ formatNumber(inv.footer?.total_ttc ?? 0) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
          </td>
          <td
            class="py-2.5 px-3 text-right font-mono font-medium"
            :class="(inv.footer?.amount_due ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600'"
          >
            {{ formatNumber(inv.footer?.amount_due ?? 0) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
          </td>
          <td class="py-2.5 px-3 text-center">
            <span
              class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
              :class="partnerStatusBadgeClass(inv.status)"
            >
              {{ partnerStatusLabel(inv.status, inv.document_type) }}
            </span>
          </td>
        </tr>
      </tbody>
      <tfoot>
        <tr class="border-t-2 border-gray-200 dark:border-gray-700 font-semibold bg-gray-50 dark:bg-gray-900">
          <td colspan="3" class="py-2.5 px-3 text-sm text-gray-600 dark:text-gray-400">
            {{ countableDocuments.length }} document(s) comptabilisé(s)
            <span
              v-if="documents.length !== countableDocuments.length"
              class="text-xs font-normal text-gray-400 dark:text-gray-500 ml-1"
            >
              (sur {{ documents.length }} — devis et annulés exclus)
            </span>
          </td>
          <td class="py-2.5 px-3 text-right font-mono">
            {{ formatNumber(totalTtc) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
          </td>
          <td class="py-2.5 px-3 text-right font-mono" :class="totalDue > 0 ? 'text-red-600' : 'text-emerald-600'">
            {{ formatNumber(totalDue) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
          </td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</template>

<script setup lang="ts">
/**
 * L'historique des documents d'une fiche tiers.
 *
 * Rendu a l'identique dans la modale d'edition et dans celle de consultation :
 * la fiche fournisseur en portait deux copies, a la variable de chargement
 * pres.
 *
 * Les lignes grisees sont celles que `isBilled` designe — un bon deja porte
 * par une facture. Elles restent visibles, mais hors du cumul du pied, qui
 * s'appuie sur `countableDocuments`.
 */
import PartnerTabSpinner from '@/components/partners/PartnerTabSpinner.vue'
import {
  partnerDocTypeBadgeClass,
  partnerDocTypeLabel,
  partnerStatusBadgeClass,
  partnerStatusLabel,
} from '@/composables/useDocumentLabels'
import { useFormat } from '@/composables/useFormat'

withDefaults(
  defineProps<{
    loading: boolean
    documents: any[]
    countableDocuments: any[]
    totalTtc: number
    totalDue: number
    /** Ce document est-il deja porte par un autre ? Il est alors grise. */
    isBilled?: (doc: any) => boolean
    /** L'infobulle qui l'explique, propre au domaine. */
    billedTitle?: string
  }>(),
  { isBilled: () => false, billedTitle: '' },
)

const { date: formatDate } = useFormat()

function formatNumber(n: number): string {
  return n.toLocaleString('fr-MA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
