<template>
  <div class="space-y-2">
    <div class="flex items-center justify-between">
      <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Fournisseurs</h4>
      <button
        v-if="hasProduct"
        type="button"
        :disabled="linkAdding"
        class="text-xs px-2.5 py-1 rounded-md bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white font-medium transition disabled:opacity-50"
        @click="linkAdding = !linkAdding"
      >
        {{ linkAdding ? 'Annuler' : '+ Ajouter un fournisseur' }}
      </button>
    </div>

    <!-- Formulaire d'ajout en ligne -->
    <div
      v-if="hasProduct && linkAdding"
      class="p-2.5 bg-[#F1ECFC] dark:bg-[#7C5CFC]/20 border border-[#E4D9FE] dark:border-[#4C3999] rounded-lg grid grid-cols-1 sm:grid-cols-5 gap-2"
    >
      <div class="sm:col-span-2">
        <label for="products-newlink-supplier" class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Fournisseur</label>
        <select
          id="products-newlink-supplier"
          v-model.number="newLink.third_partner_id"
          class="w-full px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 text-input bg-white dark:bg-gray-800"
        >
          <option :value="null" disabled>— Choisir —</option>
          <option
            v-for="s in supplierOptions"
            :key="s.id"
            :value="s.id"
            :disabled="isSupplierAlreadyLinked(s.id)"
          >
            {{ s.tp_title }}
          </option>
        </select>
      </div>
      <div>
        <label for="products-newlink-priority" class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Priorité</label>
        <input
          id="products-newlink-priority"
          v-model.number="newLink.priority"
          type="number"
          min="1"
          class="w-full px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 text-input font-mono bg-white dark:bg-gray-800"
        />
      </div>
      <div>
        <label for="products-newlink-price" class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Prix d'achat</label>
        <input
          id="products-newlink-price"
          v-model.number="newLink.purchase_price"
          type="number"
          min="0"
          step="0.01"
          placeholder="0.00"
          class="w-full px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 text-input font-mono bg-white dark:bg-gray-800"
        />
      </div>
      <div>
        <label for="products-newlink-leadtime" class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Délai (j)</label>
        <input
          id="products-newlink-leadtime"
          v-model.number="newLink.lead_time_days"
          type="number"
          min="0"
          class="w-full px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 text-input font-mono bg-white dark:bg-gray-800"
        />
      </div>
      <div class="sm:col-span-3">
        <label for="products-newlink-sku" class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Réf. chez ce fournisseur</label>
        <input
          id="products-newlink-sku"
          v-model="newLink.supplier_sku"
          type="text"
          class="w-full px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 text-input bg-white dark:bg-gray-800"
        />
      </div>
      <div class="sm:col-span-5 flex items-center justify-end pt-0.5">
        <button
          type="button"
          :disabled="!newLink.third_partner_id || linkSaving"
          class="text-xs px-2.5 py-1 rounded-md bg-green-600 hover:bg-green-700 text-white font-semibold disabled:opacity-50"
          @click="emit('add')"
        >
          {{ linkSaving ? 'Enregistrement…' : 'Enregistrer' }}
        </button>
      </div>
    </div>

    <div v-if="hasProduct && suppliers.length" class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-700">
          <tr>
            <th class="px-2.5 py-1.5 text-right text-gray-600 dark:text-gray-300 font-medium">Priorité</th>
            <th class="px-2.5 py-1.5 text-left text-gray-600 dark:text-gray-300 font-medium">Fournisseur</th>
            <th class="px-2.5 py-1.5 text-left text-gray-600 dark:text-gray-300 font-medium">Réf. fournisseur</th>
            <th class="px-2.5 py-1.5 text-right text-gray-600 dark:text-gray-300 font-medium">Prix d'achat</th>
            <th class="px-2.5 py-1.5 text-right text-gray-600 dark:text-gray-300 font-medium">Délai (j)</th>
            <th class="px-2.5 py-1.5 w-8"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr v-for="item in suppliers" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
            <td class="px-2.5 py-1.5 text-right font-mono">{{ item.pivot?.priority ?? 1 }}</td>
            <td class="px-2.5 py-1.5 text-gray-800 dark:text-gray-200">{{ item.tp_title }}</td>
            <td class="px-2.5 py-1.5 text-gray-600 dark:text-gray-400">{{ item.pivot?.supplier_sku ?? '—' }}</td>
            <td class="px-2.5 py-1.5 text-right font-mono">{{ item.pivot?.purchase_price != null ? Number(item.pivot.purchase_price).toFixed(2) + ' MAD' : '—' }}</td>
            <td class="px-2.5 py-1.5 text-right font-mono">{{ item.pivot?.lead_time_days ?? '—' }}</td>
            <td class="px-2.5 py-1.5 text-right">
              <button
                type="button"
                class="text-red-600 hover:text-red-800 dark:text-red-400 text-xs"
                :disabled="linkDeletingId === item.id"
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
      {{ hasProduct ? "Aucun fournisseur lié — l'historique d'achat sera utilisé par défaut pour les commandes automatiques." : "Enregistrez d'abord le produit pour lier des fournisseurs." }}
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * L'onglet Fournisseurs d'une fiche produit : la liste des fournisseurs
 * pouvant livrer ce produit, du plus prefere (priorite 1) au moins prefere,
 * avec prix d'achat et delai propres a chacun.
 *
 * Contrairement a l'onglet Tarifs, tout passe par des props/emits plutot
 * que par injection (useProductEditContext) : ce formulaire n'a pas besoin
 * d'etre partage avec l'onglet Infos.
 */
defineProps<{
  /** Faux en creation : pas de fournisseur tant que le produit n'existe pas. */
  hasProduct: boolean
  /** Fournisseurs deja lies a ce produit (avec pivot). */
  suppliers: any[]
  /** Tiers de role fournisseur/both, pour le select. */
  supplierOptions: any[]
  linkSaving: boolean
  linkDeletingId: number | null
  /** Le formulaire d'ajout, v-model depuis le parent. */
  newLink: { third_partner_id: number | null; supplier_sku: string; purchase_price: number | null; priority: number; lead_time_days: number | null }
  isSupplierAlreadyLinked: (thirdPartnerId: number) => boolean
}>()

const linkAdding = defineModel<boolean>('linkAdding', { required: true })

const emit = defineEmits<{
  add: []
  remove: [item: any]
}>()
</script>
