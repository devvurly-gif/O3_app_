<template>
    <div class="flex items-center justify-between mb-2">
      <p class="text-xs text-gray-500 dark:text-gray-400">Configurez les variantes pour ce produit.</p>
      <div class="flex gap-2">
        <button type="button"
          class="px-3 py-1.5 text-xs font-medium border border-indigo-300 text-indigo-600 rounded-lg hover:bg-indigo-50 dark:border-indigo-700 dark:text-indigo-400 transition"
          @click="emit('generate')">Generer combinaisons</button>
        <button type="button"
          class="px-3 py-1.5 text-xs font-medium bg-[#7C5CFC] text-white rounded-lg hover:bg-[#6D4CE0] transition"
          @click="emit('add')">+ Ajouter</button>
      </div>
    </div>
    <div v-if="!variants.length" class="text-sm text-gray-400 dark:text-gray-500 text-center py-8 border border-dashed border-gray-300 dark:border-gray-700 rounded-lg">
      Aucune variante. Cliquez sur Generer ou Ajouter.
    </div>
    <div v-else class="overflow-x-auto">
      <table class="w-full text-xs">
        <thead>
          <tr class="text-left text-gray-500 border-b border-gray-200 dark:border-gray-700">
            <th class="py-2 pr-2">Label</th>
            <th class="py-2 pr-2">SKU</th>
            <th class="py-2 pr-2 w-24">Prix</th>
            <th class="py-2 pr-2 w-20">Stock</th>
            <th class="py-2 pr-2 w-16 text-center">Actif</th>
            <th class="py-2 w-8"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(v, idx) in variants" :key="idx" class="border-b border-gray-100 dark:border-gray-800">
            <td class="py-1.5 pr-2">
              <input v-model="v.label" :aria-label="`Libellé de la variante ${idx + 1}`" @input="emit('touch')" type="text" placeholder="Rouge / XL"
                class="w-full px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-[#7C5CFC]" />
            </td>
            <td class="py-1.5 pr-2">
              <input v-model="v.sku" :aria-label="`SKU de la variante ${idx + 1}`" @input="emit('touch')" type="text" placeholder="SKU"
                class="w-full px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-[#7C5CFC]" />
            </td>
            <td class="py-1.5 pr-2">
              <input v-model.number="v.price" :aria-label="`Prix de la variante ${idx + 1}`" @input="emit('touch')" type="number" step="0.01" placeholder="—"
                class="w-full px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-[#7C5CFC]" />
            </td>

            <td class="py-1.5 pr-2 text-center">
              <input type="checkbox" v-model="v.is_active" :aria-label="`Variante ${idx + 1} active`" @change="emit('touch')" class="w-4 h-4 text-[#7C5CFC] rounded" />
            </td>
            <td class="py-1.5">
              <button type="button" @click="emit('remove', idx)" class="p-1 text-red-400 hover:text-red-600 rounded transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
</template>

<script setup lang="ts">
/**
 * L'onglet Variantes d'une fiche produit.
 *
 * Les lignes sont editees en place — le tableau appartient a
 * `useProductVariants`, chez l'ecran hote. Chaque frappe remonte par `touch`,
 * qui marque la liste comme modifiee : sans cela, l'enregistrement du produit
 * n'enverrait rien au serveur.
 */
defineProps<{ variants: any[] }>()

const emit = defineEmits<{
  /** Composer toutes les combinaisons des options du tenant. */
  generate: []
  /** Ajouter une ligne vide. */
  add: []
  /** Retirer la ligne d'indice donne. */
  remove: [idx: number]
  /** Une valeur a change : la liste devra etre synchronisee. */
  touch: []
}>()
</script>
