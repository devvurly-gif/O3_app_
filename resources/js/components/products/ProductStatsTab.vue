<template>
    <div v-if="hasProduct" class="space-y-3">
      <!-- Sales Metrics -->
      <div class="space-y-1.5">
        <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Sales Metrics</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
          <div class="bg-purple-50 dark:bg-purple-900/20 px-3 py-2 rounded border border-purple-200 dark:border-purple-800">
            <p class="text-xs text-purple-600 dark:text-purple-400 font-medium">Total Units Sold</p>
            <p class="text-lg font-bold text-purple-900 dark:text-purple-200 leading-tight">{{ statistics?.sales?.total_units ?? 0 }}</p>
          </div>
          <div class="bg-[#F1ECFC] dark:bg-[#7C5CFC]/20 px-3 py-2 rounded border border-[#E4D9FE] dark:border-[#4C3999]">
            <p class="text-xs text-[#7C5CFC] dark:text-[#A78BFA] font-medium">Total Revenue</p>
            <p class="text-lg font-bold text-blue-900 dark:text-blue-200 leading-tight">{{ (statistics?.sales?.total_revenue ?? 0).toFixed(2) }} MAD</p>
          </div>
          <div class="bg-indigo-50 dark:bg-indigo-900/20 px-3 py-2 rounded border border-indigo-200 dark:border-indigo-800">
            <p class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">Avg Sale Price</p>
            <p class="text-lg font-bold text-indigo-900 dark:text-indigo-200 leading-tight">{{ (statistics?.sales?.avg_price ?? 0).toFixed(2) }} MAD</p>
          </div>
          <div class="bg-pink-50 dark:bg-pink-900/20 px-3 py-2 rounded border border-pink-200 dark:border-pink-800">
            <p class="text-xs text-pink-600 dark:text-pink-400 font-medium">Sale Transactions</p>
            <p class="text-lg font-bold text-pink-900 dark:text-pink-200 leading-tight">{{ statistics?.sales?.count ?? 0 }}</p>
          </div>
        </div>
      </div>

      <!-- Purchase Metrics — absentes du payload sans products.view_cost -->
      <div v-if="statistics?.purchases" class="space-y-1.5 pt-3 border-t border-gray-200 dark:border-gray-700">
        <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Purchase Metrics</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
          <div class="bg-emerald-50 dark:bg-emerald-900/20 px-3 py-2 rounded border border-emerald-200 dark:border-emerald-800">
            <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Total Units Purchased</p>
            <p class="text-lg font-bold text-emerald-900 dark:text-emerald-200 leading-tight">{{ statistics?.purchases?.total_units ?? 0 }}</p>
          </div>
          <div class="bg-teal-50 dark:bg-teal-900/20 px-3 py-2 rounded border border-teal-200 dark:border-teal-800">
            <p class="text-xs text-teal-600 dark:text-teal-400 font-medium">Total Cost</p>
            <p class="text-lg font-bold text-teal-900 dark:text-teal-200 leading-tight">{{ (statistics?.purchases?.total_cost ?? 0).toFixed(2) }} MAD</p>
          </div>
          <div class="bg-cyan-50 dark:bg-cyan-900/20 px-3 py-2 rounded border border-cyan-200 dark:border-cyan-800">
            <p class="text-xs text-cyan-600 dark:text-cyan-400 font-medium">Avg Purchase Price</p>
            <p class="text-lg font-bold text-cyan-900 dark:text-cyan-200 leading-tight">{{ (statistics?.purchases?.avg_price ?? 0).toFixed(2) }} MAD</p>
          </div>
          <div class="bg-[#F1ECFC] dark:bg-[#7C5CFC]/20 px-3 py-2 rounded border border-[#E4D9FE] dark:border-[#4C3999]">
            <p class="text-xs text-[#6D4CE0] dark:text-[#A78BFA] font-medium">Purchase Transactions</p>
            <p class="text-lg font-bold text-[#3D2E85] dark:text-[#E4D9FE] leading-tight">{{ statistics?.purchases?.count ?? 0 }}</p>
          </div>
        </div>
      </div>
    </div>
    <div v-else class="text-sm text-gray-500 dark:text-gray-400">
      Statistics available after saving the product.
    </div>
</template>

<script setup lang="ts">
/**
 * L'onglet Statistiques d'une fiche produit : ventes et achats.
 *
 * Le bloc achats disparait de lui-meme quand le serveur ne l'envoie pas —
 * `/products/{id}/statistics` l'omet sans le droit `products.view_cost`.
 * Le gabarit teste donc la presence du bloc, pas le droit.
 */
defineProps<{
  /** Le payload de /products/{id}/statistics, ou null tant qu'il n'est pas la. */
  statistics: any
  /** Faux en creation : il n'y a pas encore de produit a mesurer. */
  hasProduct: boolean
}>()
</script>
