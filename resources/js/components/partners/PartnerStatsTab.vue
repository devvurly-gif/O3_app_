<template>
  <PartnerTabSpinner v-if="loading" />
  <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-4">
    <div class="bg-[#F1ECFC] rounded-xl p-4 border border-blue-100">
      <p class="text-xs text-[#7C5CFC] font-medium mb-1">Total documents</p>
      <p class="text-2xl font-bold text-blue-900">{{ documentsCount }}</p>
    </div>
    <div class="bg-red-50 rounded-xl p-4 border border-red-100">
      <p class="text-xs text-red-600 font-medium mb-1">Documents impayés</p>
      <p class="text-2xl font-bold text-red-900">{{ unpaidCount }}</p>
    </div>
    <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
      <p class="text-xs text-emerald-600 font-medium mb-1">{{ totalLabel }}</p>
      <p class="text-xl font-bold text-emerald-900 font-mono">
        {{ formatNumber(totalTtc) }} <span class="text-sm font-normal text-emerald-600">DH</span>
      </p>
    </div>
    <div class="bg-teal-50 rounded-xl p-4 border border-teal-100">
      <p class="text-xs text-teal-600 font-medium mb-1">Total payé</p>
      <p class="text-xl font-bold text-teal-900 font-mono">
        {{ formatNumber(totalPayments) }} <span class="text-sm font-normal text-teal-600">DH</span>
      </p>
    </div>
    <div class="bg-amber-50 rounded-xl p-4 border border-amber-100">
      <p class="text-xs text-amber-600 font-medium mb-1">Reste à payer</p>
      <p class="text-xl font-bold font-mono" :class="totalDue > 0 ? 'text-amber-900' : 'text-emerald-700'">
        {{ formatNumber(totalDue) }} <span class="text-sm font-normal text-amber-600">DH</span>
      </p>
    </div>
    <div class="sm:col-span-3 bg-gray-50 dark:bg-gray-900 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
      <div class="flex items-center justify-between mb-2">
        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Taux de recouvrement</p>
        <span
          class="text-sm font-bold"
          :class="paymentRate >= 80 ? 'text-emerald-600' : paymentRate >= 50 ? 'text-amber-600' : 'text-red-600'"
        >
          {{ paymentRate.toFixed(1) }}%
        </span>
      </div>
      <div class="w-full bg-gray-200 rounded-full h-2.5">
        <div
          class="h-2.5 rounded-full transition-all duration-500"
          :class="paymentRate >= 80 ? 'bg-emerald-500' : paymentRate >= 50 ? 'bg-amber-500' : 'bg-red-500'"
          :style="{ width: Math.min(paymentRate, 100) + '%' }"
        ></div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Le resume chiffre d'une fiche tiers : cinq cartes et la jauge de
 * recouvrement.
 *
 * Rendu a l'identique dans la modale d'edition et dans celle de consultation.
 * Seul le libelle du cumul change de domaine — « Total achats » chez le
 * fournisseur, « CA total » chez le client.
 */
import PartnerTabSpinner from '@/components/partners/PartnerTabSpinner.vue'

defineProps<{
  loading: boolean
  documentsCount: number
  unpaidCount: number
  totalTtc: number
  totalPayments: number
  totalDue: number
  paymentRate: number
  /** « Total achats » cote fournisseur, « CA total » cote client. */
  totalLabel: string
}>()

function formatNumber(n: number): string {
  return n.toLocaleString('fr-MA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
