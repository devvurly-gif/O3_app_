<template>
    <!-- Master Prices Section -->
    <div class="bg-gray-50 dark:bg-gray-800/60 p-3 rounded-lg space-y-3">
      <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Master Prices</h4>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <!-- Purchase Price -->
        <div>
          <label for="products-p-purchaseprice" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >{{ $t('products.purchasePrice') }} <span class="text-red-500">*</span></label
          >
          <input
            id="products-p-purchaseprice"
            v-model.number="form.p_purchasePrice"
            type="number"
            min="0"
            step="0.01"
            required
            placeholder="0.00"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
          />
        </div>

        <!-- Sale Price -->
        <div>
          <label for="products-p-saleprice" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >{{ $t('products.salePrice') }} <span class="text-red-500">*</span></label
          >
          <input
            id="products-p-saleprice"
            v-model.number="form.p_salePrice"
            type="number"
            min="0"
            step="0.01"
            required
            placeholder="0.00"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
          />
        </div>

        <!-- Cost Price -->
        <div>
          <label for="products-p-cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.costPrice') ?? 'Cost Price' }}</label>
          <input
            id="products-p-cost"
            v-model.number="form.p_cost"
            type="number"
            min="0"
            step="0.01"
            placeholder="0.00"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
          />
        </div>

        <!-- Tax Rate -->
        <div>
          <label for="products-p-taxrate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.taxRate') }}</label>
          <input
            id="products-p-taxrate"
            v-model.number="form.p_taxRate"
            type="number"
            min="0"
            max="100"
            step="0.01"
            placeholder="20"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
          />
        </div>

        <!-- Unit -->
        <div class="sm:col-span-2 lg:col-span-1">
          <label for="products-p-unit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.unit') }}</label>
          <input
            id="products-p-unit"
            v-model="form.p_unit"
            type="text"
            :placeholder="$t('products.unitPlaceholder')"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
          />
        </div>
      </div>

      <!-- Margin Indicator -->
      <div v-if="form.p_salePrice > 0 && form.p_purchasePrice > 0" class="px-3 py-2 bg-[#F1ECFC] dark:bg-[#7C5CFC]/20 border border-[#E4D9FE] dark:border-[#4C3999] rounded text-sm">
        <p class="text-blue-800 dark:text-blue-200">
          <span class="font-semibold">Margin:</span>
          {{ marginPercent }}%
          <span :class="marginPercent >= 20 ? 'text-green-600 dark:text-green-400' : 'text-[#6D4CE0] dark:text-[#A78BFA]'">
            ({{ marginPercent >= 20 ? 'Healthy' : 'Low' }})
          </span>
        </p>
      </div>
    </div>

    <!-- Price List Tiers Section -->
    <div class="space-y-2">
      <div class="flex items-center justify-between">
        <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Tarifs par grille</h4>
        <button
          v-if="hasProduct"
          type="button"
          :disabled="tierAdding"
          class="text-xs px-2.5 py-1 rounded-md bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white font-medium transition disabled:opacity-50"
          @click="tierAdding = !tierAdding"
        >
          {{ tierAdding ? 'Annuler' : '+ Ajouter un tarif' }}
        </button>
      </div>

      <!-- Inline add-tier form -->
      <div
        v-if="hasProduct && tierAdding"
        class="p-2.5 bg-[#F1ECFC] dark:bg-[#7C5CFC]/20 border border-[#E4D9FE] dark:border-[#4C3999] rounded-lg grid grid-cols-1 sm:grid-cols-4 gap-2"
      >
        <div class="sm:col-span-2">
          <label for="products-newtier-price-list-id" class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Grille</label>
          <select
            id="products-newtier-price-list-id"
            v-model.number="newTier.price_list_id"
            class="w-full px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 text-input bg-white dark:bg-gray-800"
          >
            <option :value="null" disabled>— Choisir —</option>
            <option
              v-for="pl in priceLists"
              :key="pl.id"
              :value="pl.id"
              :disabled="isListAlreadyUsed(pl.id, newTier.min_qty)"
            >
              {{ pl.name }}{{ pl.is_default ? ' (défaut)' : '' }}
            </option>
          </select>
        </div>
        <div>
          <label for="products-newtier-min-qty" class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Qté min</label>
          <input
            id="products-newtier-min-qty"
            v-model.number="newTier.min_qty"
            type="number"
            min="1"
            class="w-full px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 text-input font-mono bg-white dark:bg-gray-800"
          />
        </div>
        <div>
          <label for="products-newtier-price-ht" class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Prix HT</label>
          <input
            id="products-newtier-price-ht"
            v-model.number="newTier.price_ht"
            type="number"
            min="0"
            step="0.01"
            class="w-full px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 text-input font-mono bg-white dark:bg-gray-800"
          />
        </div>
        <div class="sm:col-span-4 flex items-center justify-between pt-0.5">
          <p class="text-xs text-gray-600 dark:text-gray-400">
            Prix TTC estimé :
            <span class="font-mono font-semibold">{{ newTierTtc }} MAD</span>
          </p>
          <button
            type="button"
            :disabled="!canAddTier || tierSaving"
            class="text-xs px-2.5 py-1 rounded-md bg-green-600 hover:bg-green-700 text-white font-semibold disabled:opacity-50"
            @click="emit('add')"
          >
            {{ tierSaving ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
        </div>
      </div>

      <div v-if="hasProduct && tiers.length" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-2.5 py-1.5 text-left text-gray-600 dark:text-gray-300 font-medium">Grille</th>
              <th class="px-2.5 py-1.5 text-right text-gray-600 dark:text-gray-300 font-medium">Qté min</th>
              <th class="px-2.5 py-1.5 text-right text-gray-600 dark:text-gray-300 font-medium">Prix HT</th>
              <th class="px-2.5 py-1.5 text-right text-gray-600 dark:text-gray-300 font-medium">Prix TTC</th>
              <th class="px-2.5 py-1.5 w-8"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="item in tiers" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <td class="px-2.5 py-1.5 text-gray-800 dark:text-gray-200">{{ item.price_list?.name ?? item.priceList?.name ?? '—' }}</td>
              <td class="px-2.5 py-1.5 text-right font-mono">{{ item.min_qty }}</td>
              <td class="px-2.5 py-1.5 text-right font-mono">{{ Number(item.price_ht).toFixed(2) }} MAD</td>
              <td class="px-2.5 py-1.5 text-right font-mono">{{ Number(item.price_ttc).toFixed(2) }} MAD</td>
              <td class="px-2.5 py-1.5 text-right">
                <button
                  type="button"
                  class="text-red-600 hover:text-red-800 dark:text-red-400 text-xs"
                  :disabled="tierDeletingId === item.id"
                  @click="emit('remove', item)"
                >
                  ×
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="text-sm text-gray-500 dark:text-gray-400">
        {{ hasProduct ? 'Aucun tarif spécifique — le prix de vente principal est utilisé.' : 'Enregistrez d\'abord le produit pour ajouter des tarifs par grille.' }}
      </div>
    </div>

</template>

<script setup lang="ts">
/**
 * L'onglet Tarifs d'une fiche produit : les prix maitres, puis les paliers
 * par liste de prix et quantite minimale.
 *
 * Le formulaire de la fiche et celui d'ajout d'un palier arrivent par
 * injection — tous deux sont edites au `v-model` depuis ce gabarit.
 * Voir `useProductEditContext`.
 */
import { useProductEdit } from '@/composables/useProductEditContext'

defineProps<{
  /** Faux en creation : pas de palier tant que le produit n'existe pas. */
  hasProduct: boolean
  /** Les listes de prix du tenant. */
  priceLists: any[]
  /** Les paliers deja enregistres pour ce produit. */
  tiers: any[]
  tierSaving: boolean
  tierDeletingId: number | null
  /** TTC calcule du palier en cours de saisie. */
  newTierTtc: string
  canAddTier: boolean
  /** Un palier existe-t-il deja pour ce couple liste / quantite ? */
  isListAlreadyUsed: (listId: number, minQty: number) => boolean
  /** Marge en pourcentage, calculee par l'ecran a partir des prix maitres. */
  marginPercent: number
}>()

const emit = defineEmits<{
  add: []
  remove: [item: any]
}>()

/** Deplie ou replie le formulaire d'ajout : le gabarit l'ecrit lui-meme. */
const tierAdding = defineModel<boolean>('tierAdding', { required: true })

const { form, newTier } = useProductEdit()
</script>
