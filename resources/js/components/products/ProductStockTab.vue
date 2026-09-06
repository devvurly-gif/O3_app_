<template>
    <div v-if="product" class="space-y-3">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
        <div class="bg-[#F1ECFC] dark:bg-[#7C5CFC]/20 px-3 py-2.5 rounded-lg border border-[#E4D9FE] dark:border-[#4C3999]">
          <p class="text-xs text-[#7C5CFC] dark:text-[#A78BFA] font-medium">Total Stock</p>
          <p class="text-lg font-bold text-blue-900 dark:text-blue-200 leading-tight">{{ product.total_stock ?? 0 }}</p>
        </div>
        <div class="bg-green-50 dark:bg-green-900/20 px-3 py-2.5 rounded-lg border border-green-200 dark:border-green-800">
          <p class="text-xs text-green-600 dark:text-green-400 font-medium">Stock Value</p>
          <p class="text-lg font-bold text-green-900 dark:text-green-200 leading-tight">{{ (Number(product.total_stock ?? 0) * Number(cost || 0)).toFixed(2) }} MAD</p>
        </div>
      </div>

      <!-- Warehouse Breakdown -->
      <div class="space-y-1.5">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
          {{ variantsEnabled && variants.length ? 'Stock par Variante' : 'Warehouse Breakdown' }}
        </h3>

        <!-- WITH VARIANTS -->
        <div v-if="variantsEnabled && variants.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-2.5 py-1.5 text-left text-gray-600 dark:text-gray-300 font-medium">Variante</th>
                <th class="px-2.5 py-1.5 text-left text-gray-600 dark:text-gray-300 font-medium">SKU</th>
                <th class="px-2.5 py-1.5 text-right text-gray-600 dark:text-gray-300 font-medium">Stock</th>
                <th class="px-2.5 py-1.5 text-center text-gray-600 dark:text-gray-300 font-medium">Statut</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="v in variants" :key="v.id ?? v.label" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td class="px-2.5 py-1.5 text-gray-800 dark:text-gray-200 font-medium">{{ v.label }}</td>
                <td class="px-2.5 py-1.5 text-gray-500 dark:text-gray-400 font-mono text-xs">{{ v.sku || '—' }}</td>
                <td class="px-2.5 py-1.5 text-right font-mono">{{ Number(v.stock ?? 0).toFixed(2) }} {{ product.p_unit ?? 'pcs' }}</td>
                <td class="px-2.5 py-1.5 text-center">
                  <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium"
                    :class="Number(v.stock ?? 0) > 0 ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'">
                    {{ Number(v.stock ?? 0) > 0 ? 'En stock' : 'Epuise' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
          <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 italic">
            Stock lu depuis warehouse_has_stock. Saisie via documents de stock.
          </p>
        </div>

        <!-- WITHOUT VARIANTS -->
        <div v-else-if="warehouseStocks.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-2.5 py-1.5 text-left text-gray-600 dark:text-gray-300 font-medium">Warehouse</th>
                <th class="px-2.5 py-1.5 text-right text-gray-600 dark:text-gray-300 font-medium">Stock</th>
                <th class="px-2.5 py-1.5 text-center text-gray-600 dark:text-gray-300 font-medium">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="ws in warehouseStocks" :key="ws.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td class="px-2.5 py-1.5 text-gray-800 dark:text-gray-200">{{ ws.warehouse?.wh_title ?? ws.warehouse?.wh_name ?? '—' }}</td>
                <td class="px-2.5 py-1.5 text-right font-mono">{{ Number(ws.stockLevel ?? ws.stock_level ?? 0).toFixed(2) }} {{ product.p_unit ?? 'pcs' }}</td>
                <td class="px-2.5 py-1.5 text-center">
                  <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium"
                    :class="Number(ws.stockLevel ?? ws.stock_level ?? 0) > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                    {{ Number(ws.stockLevel ?? ws.stock_level ?? 0) > 0 ? 'In Stock' : 'Out' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-sm text-gray-500 dark:text-gray-400">
          {{ $t('products.noStock') ?? 'No stock records yet' }}
        </div>
      </div>

      <!-- Recent Movements -->
      <div class="space-y-1.5 pt-3 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Recent Movements</h3>
          <span v-if="movements.length" class="text-[11px] text-gray-500 dark:text-gray-400">
            {{ movements.length }} mouvement(s)
          </span>
        </div>
        <div v-if="movements.length" class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
          <table class="w-full text-[11px] border-collapse">
            <thead class="bg-gray-100 dark:bg-gray-700/70 text-gray-600 dark:text-gray-300 uppercase">
              <tr>
                <th class="px-2 py-1.5 text-left font-semibold whitespace-nowrap">Date</th>
                <th class="px-2 py-1.5 text-center font-semibold">Sens</th>
                <th class="px-2 py-1.5 text-right font-semibold">Qté</th>
                <th class="px-2 py-1.5 text-left font-semibold">Motif</th>
                <th class="px-2 py-1.5 text-left font-semibold">Document</th>
                <th class="px-2 py-1.5 text-left font-semibold">Dépôt</th>
                <th class="px-2 py-1.5 text-left font-semibold">Utilisateur</th>
                <th class="px-2 py-1.5 text-center font-semibold whitespace-nowrap">Solde (avant → après)</th>
                <th class="px-2 py-1.5 text-right font-semibold">PU</th>
                <th class="px-2 py-1.5 text-right font-semibold">Total</th>
                <th class="px-2 py-1.5 text-left font-semibold">Notes</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="mov in movements"
                :key="mov.id"
                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors"
                :class="mov.direction === 'in'
                  ? 'border-l-2 border-l-green-500 dark:border-l-green-400'
                  : 'border-l-2 border-l-red-500 dark:border-l-red-400'"
              >
                <td class="px-2 py-1.5 whitespace-nowrap text-gray-600 dark:text-gray-400 font-mono text-[10px]">
                  <div>{{ fmtDate(mov.created_at) }}</div>
                  <div class="text-gray-400">{{ new Date(mov.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</div>
                </td>
                <td class="px-2 py-1.5 text-center">
                  <span
                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase"
                    :class="mov.direction === 'in'
                      ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                      : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'"
                  >
                    {{ mov.direction === 'in' ? '↑ IN' : '↓ OUT' }}
                  </span>
                </td>
                <td class="px-2 py-1.5 text-right font-mono font-semibold whitespace-nowrap"
                    :class="mov.direction === 'in' ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
                  {{ mov.direction === 'in' ? '+' : '−' }}{{ Number(mov.quantity).toFixed(2) }}
                  <span class="text-gray-400 font-normal">{{ product?.p_unit ?? 'pcs' }}</span>
                </td>
                <td class="px-2 py-1.5">
                  <div class="flex flex-col gap-0.5">
                    <span v-if="mov.reason" class="px-1.5 py-0.5 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 rounded text-[10px] font-medium self-start">
                      {{ mov.reason }}
                    </span>
                    <span
                      v-if="mov.status && mov.status !== 'applied'"
                      class="px-1.5 py-0.5 rounded text-[10px] font-medium self-start"
                      :class="mov.status === 'pending'
                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                        : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                    >
                      {{ mov.status }}
                    </span>
                  </div>
                </td>
                <td class="px-2 py-1.5 font-mono text-gray-700 dark:text-gray-300 whitespace-nowrap">
                  {{ mov.document_reference || mov.document_type || '—' }}
                </td>
                <td class="px-2 py-1.5 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                  {{ mov.warehouse?.wh_title ?? mov.warehouse?.wh_code ?? '—' }}
                </td>
                <td class="px-2 py-1.5 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                  {{ mov.user?.name ?? '—' }}
                </td>
                <td class="px-2 py-1.5 text-center font-mono whitespace-nowrap">
                  <template v-if="mov.stock_before !== null && mov.stock_after !== null">
                    <span class="text-gray-500">{{ Number(mov.stock_before).toFixed(2) }}</span>
                    <span class="text-gray-400 mx-1">→</span>
                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ Number(mov.stock_after).toFixed(2) }}</span>
                  </template>
                  <span v-else class="text-gray-400">—</span>
                </td>
                <td class="px-2 py-1.5 text-right font-mono whitespace-nowrap text-gray-700 dark:text-gray-300">
                  <template v-if="mov.unit_cost && Number(mov.unit_cost) > 0">{{ Number(mov.unit_cost).toFixed(2) }}</template>
                  <span v-else class="text-gray-400">—</span>
                </td>
                <td class="px-2 py-1.5 text-right font-mono whitespace-nowrap text-gray-900 dark:text-gray-100">
                  <template v-if="mov.unit_cost && Number(mov.unit_cost) > 0">
                    {{ (Number(mov.unit_cost) * Number(mov.quantity)).toFixed(2) }}
                  </template>
                  <span v-else class="text-gray-400">—</span>
                </td>
                <td class="px-2 py-1.5 text-gray-500 dark:text-gray-400 italic max-w-[200px] truncate" :title="mov.notes">
                  {{ mov.notes || '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-xs text-gray-500 dark:text-gray-400">No movements yet</div>
      </div>
    </div>
    <div v-else class="text-sm text-gray-500 dark:text-gray-400">
      {{ $t('products.stockAfterSave') ?? 'Stock information available after saving the product.' }}
    </div>
</template>

<script setup lang="ts">
/**
 * L'onglet Stock d'une fiche produit : le stock par depot — ou par
 * declinaison quand le produit en a — puis l'historique des mouvements.
 *
 * Lecture seule. Les listes lui arrivent deja resolues : la tolerance de
 * casse sur `stockLevel` / `stock_level` reste dans le gabarit, telle qu'elle
 * y etait, parce que c'est la reponse du serveur qui varie, pas l'ecran.
 */
import { useFormat } from '@/composables/useFormat'

defineProps<{
  /** Le produit ouvert, ou null en creation. */
  product: any
  warehouseStocks: any[]
  movements: any[]
  variants: any[]
  /** Cout unitaire courant du formulaire, pour valoriser le stock. */
  cost: number | string
  /** Le module « variantes » est-il actif chez ce tenant ? */
  variantsEnabled: boolean
}>()

const { date: fmtDate } = useFormat()
</script>
