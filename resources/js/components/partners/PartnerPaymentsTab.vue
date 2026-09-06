<template>
  <PartnerTabSpinner v-if="loading" />
  <div v-else-if="payments.length === 0" class="text-center py-12 text-gray-400 dark:text-gray-500">
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
        d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"
      />
    </svg>
    <p class="text-sm">Aucun paiement</p>
  </div>
  <div v-else class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
          <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Code</th>
          <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Facture</th>
          <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Date</th>
          <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Méthode</th>
          <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">Montant</th>
          <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Référence</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <tr v-for="pay in payments" :key="pay.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
          <td class="py-2.5 px-3 font-mono text-xs">{{ pay.payment_code }}</td>
          <td class="py-2.5 px-3 font-mono text-xs text-[#7C5CFC]">{{ pay._doc_code }}</td>
          <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400">{{ formatDate(pay.paid_at) }}</td>
          <td class="py-2.5 px-3">
            <span
              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300"
            >
              {{ paymentMethodLabel(pay.method) }}
            </span>
          </td>
          <td class="py-2.5 px-3 text-right font-mono font-medium text-emerald-600">
            {{ formatNumber(Number(pay.amount)) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
          </td>
          <td class="py-2.5 px-3 text-gray-500 dark:text-gray-400 text-xs">{{ pay.reference || '—' }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr class="border-t-2 border-gray-200 dark:border-gray-700 font-semibold bg-gray-50 dark:bg-gray-900">
          <td colspan="4" class="py-2.5 px-3 text-sm text-gray-600 dark:text-gray-400">
            {{ payments.length }} paiement(s)
          </td>
          <td class="py-2.5 px-3 text-right font-mono text-emerald-600">
            {{ formatNumber(totalPayments) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
          </td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</template>

<script setup lang="ts">
/**
 * Les reglements d'une fiche tiers, tous documents confondus.
 *
 * Rendu a l'identique dans la modale d'edition et dans celle de consultation.
 * La colonne « Facture » porte `_doc_code`, la reference du document d'ou le
 * reglement provient — c'est `usePartnerLedger` qui la rattache en remettant
 * les reglements a plat.
 */
import PartnerTabSpinner from '@/components/partners/PartnerTabSpinner.vue'
import { paymentMethodLabel } from '@/composables/useDocumentLabels'
import { formatAmount as formatNumber, useFormat } from '@/composables/useFormat'

defineProps<{
  loading: boolean
  payments: any[]
  totalPayments: number
}>()

const { date: formatDate } = useFormat()
</script>
