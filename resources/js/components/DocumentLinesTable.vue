<script setup lang="ts">
import { useFormat } from '@/composables/useFormat'

/**
 * Reusable document lines table.
 * Replaces the identical lines table in DocumentVenteShow, DocumentAchatShow,
 * and the similar (but simpler) one in DocumentStockShow.
 *
 * Usage:
 *   <DocumentLinesTable :lines="doc.lignes" />
 *   <DocumentLinesTable :lines="doc.lignes" variant="stock" :adjustment="isAdjustment" />
 */
withDefaults(
  defineProps<{
    lines: Array<Record<string, unknown>>
    /** 'commercial' = full table with prices/tax, 'stock' = simplified */
    variant?: 'commercial' | 'stock'
    /** For stock adjustments, show "Qtité cible" instead of "Quantité" */
    adjustment?: boolean
  }>(),
  {
    variant: 'commercial',
    adjustment: false,
  },
)

const { fmt } = useFormat()
</script>

<template>
  <div
    class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden mb-6"
  >
    <div
      class="px-6 py-4 bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between"
    >
      <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Lignes du document</h3>
      <span class="text-xs text-gray-400 dark:text-gray-500">{{ lines?.length ?? 0 }} article(s)</span>
    </div>

    <!-- ── Mobile: card view ── -->
    <div v-if="lines?.length" class="sm:hidden divide-y divide-gray-100 dark:divide-gray-700">
      <div
        v-for="(ligne, i) in lines"
        :key="'m-' + ((ligne as any).id ?? i)"
        class="px-4 py-3 space-y-1.5"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="font-medium text-sm text-gray-800 dark:text-gray-200 truncate">{{ (ligne as any).designation }}</p>
            <p v-if="(ligne as any).reference" class="text-xs text-gray-400 dark:text-gray-500">[{{ (ligne as any).reference }}]</p>
          </div>
          <span v-if="variant === 'commercial'" class="text-sm font-bold text-gray-900 dark:text-white shrink-0">
            {{ fmt((ligne as any).total_ttc) }} DH
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
          <span>Qté: <strong class="text-gray-700 dark:text-gray-300">{{ (ligne as any).quantity }} {{ (ligne as any).unit }}</strong></span>
          <span v-if="variant === 'commercial'">PU: <strong class="text-gray-700 dark:text-gray-300">{{ fmt((ligne as any).unit_price) }}</strong></span>
          <span v-if="variant === 'commercial' && Number((ligne as any).discount_percent ?? 0) > 0">Remise: <strong class="text-gray-700 dark:text-gray-300">{{ (ligne as any).discount_percent }}%</strong></span>
          <span v-if="variant === 'commercial'">TVA: <strong class="text-gray-700 dark:text-gray-300">{{ (ligne as any).tax_percent ?? 0 }}%</strong></span>
          <span v-if="variant === 'stock'">Coût: <strong class="text-gray-700 dark:text-gray-300">{{ fmt((ligne as any).unit_price) }} DH</strong></span>
        </div>
      </div>
    </div>

    <!-- ── Desktop: table view ── -->
    <div v-if="lines?.length" class="hidden sm:block overflow-x-auto">
      <!-- Commercial variant (ventes / achats) -->
      <table v-if="variant === 'commercial'" class="w-full text-sm">
        <thead class="bg-gray-50/50 dark:bg-gray-800/60 text-gray-500 dark:text-gray-400 uppercase text-xs">
          <tr>
            <th class="text-left px-5 py-3 w-8">#</th>
            <th class="text-left px-5 py-3">Désignation</th>
            <th class="text-right px-5 py-3">Qté</th>
            <th class="text-right px-5 py-3">PU HT</th>
            <th class="text-right px-5 py-3">Remise</th>
            <th class="text-right px-5 py-3">TVA</th>
            <th class="text-right px-5 py-3">Total TTC</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <tr
            v-for="(ligne, i) in lines"
            :key="(ligne as any).id ?? i"
            class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition"
          >
            <td class="px-5 py-3 text-gray-400 dark:text-gray-500 text-xs">{{ i + 1 }}</td>
            <td class="px-5 py-3">
              <span class="font-medium text-gray-800 dark:text-gray-200">{{ (ligne as any).designation }}</span>
              <span v-if="(ligne as any).reference" class="text-gray-400 dark:text-gray-500 text-xs ml-2"
                >[{{ (ligne as any).reference }}]</span
              >
            </td>
            <td class="px-5 py-3 text-right text-gray-600 dark:text-gray-300">
              {{ (ligne as any).quantity }} {{ (ligne as any).unit }}
            </td>
            <td class="px-5 py-3 text-right text-gray-600 dark:text-gray-300">{{ fmt((ligne as any).unit_price) }}</td>
            <td class="px-5 py-3 text-right text-gray-600 dark:text-gray-300">
              {{ (ligne as any).discount_percent ?? 0 }}%
            </td>
            <td class="px-5 py-3 text-right text-gray-600 dark:text-gray-300">
              {{ (ligne as any).tax_percent ?? 0 }}%
            </td>
            <td class="px-5 py-3 text-right font-semibold text-gray-800 dark:text-gray-200">
              {{ fmt((ligne as any).total_ttc) }}
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Stock variant -->
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50/50 dark:bg-gray-800/60">
          <tr class="text-gray-500 dark:text-gray-400 uppercase text-xs">
            <th class="text-left px-5 py-3.5 font-semibold w-14">#</th>
            <th class="text-left px-5 py-3.5 font-semibold">Désignation</th>
            <th class="text-left px-5 py-3.5 font-semibold">Réf.</th>
            <th class="text-right px-5 py-3.5 font-semibold">{{ adjustment ? 'Qtité cible' : 'Quantité' }}</th>
            <th class="text-center px-5 py-3.5 font-semibold">Unité</th>
            <th class="text-right px-5 py-3.5 font-semibold">Coût unit.</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <tr
            v-for="(ligne, i) in lines"
            :key="(ligne as any).id ?? i"
            class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition"
          >
            <td class="px-5 py-3.5 text-gray-400 dark:text-gray-500 text-xs font-medium">{{ i + 1 }}</td>
            <td class="px-5 py-3.5">
              <div class="font-medium text-gray-800 dark:text-gray-200">{{ (ligne as any).designation }}</div>
              <div v-if="(ligne as any).product?.p_code" class="text-gray-400 dark:text-gray-500 text-xs mt-0.5">
                Code: {{ (ligne as any).product.p_code }}
              </div>
            </td>
            <td class="px-5 py-3.5 text-gray-500 dark:text-gray-400 text-xs">{{ (ligne as any).reference ?? '—' }}</td>
            <td class="px-5 py-3.5 text-right">
              <span class="font-mono font-semibold text-gray-800 dark:text-gray-200 text-base">{{
                fmt((ligne as any).quantity)
              }}</span>
            </td>
            <td class="px-5 py-3.5 text-center">
              <span
                class="inline-block px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-xs text-gray-600 dark:text-gray-300"
                >{{ (ligne as any).unit }}</span
              >
            </td>
            <td class="px-5 py-3.5 text-right text-gray-600 dark:text-gray-300 font-mono">
              {{ fmt((ligne as any).unit_price) }} <span class="text-xs text-gray-400">DH</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else class="px-6 py-12 text-center text-gray-400 dark:text-gray-500">Aucune ligne.</div>
  </div>
</template>
