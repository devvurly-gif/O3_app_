<template>
  <div>
    <label :for="`${idPrefix}-amount`" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
      >Montant <span class="text-red-500">*</span></label
    >
    <div class="relative">
      <input
        :id="`${idPrefix}-amount`"
        v-model.number="amount"
        type="number"
        min="0.01"
        step="0.01"
        placeholder="0.00"
        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent pr-12"
      />
      <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 dark:text-gray-500 font-medium">DH</span>
    </div>
    <p v-if="amount > selectedTotalDue && selectedTotalDue > 0" class="text-xs text-amber-600 mt-1">
      Le montant dépasse le total dû des documents cochés. L'excédent de
      {{ formatNumber(amount - selectedTotalDue) }} DH ne sera pas affecté.
    </p>
  </div>
  <div>
    <label :for="`${idPrefix}-method`" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
      >Méthode de paiement <span class="text-red-500">*</span></label
    >
    <select
      :id="`${idPrefix}-method`"
      v-model="method"
      class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
    >
      <option value="cash">Espèces</option>
      <option value="bank_transfer">Virement bancaire</option>
      <option value="cheque">Chèque</option>
      <option value="effet">Effet</option>
    </select>
  </div>
  <div>
    <label :for="`${idPrefix}-reference`" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Référence</label>
    <input
      :id="`${idPrefix}-reference`"
      v-model="reference"
      type="text"
      placeholder="N° chèque, virement..."
      class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
    />
  </div>
  <div>
    <label :for="`${idPrefix}-notes`" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
    <textarea
      :id="`${idPrefix}-notes`"
      v-model="notes"
      rows="2"
      placeholder="Remarques..."
      class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent resize-none"
    ></textarea>
  </div>
</template>

<script setup lang="ts">
/**
 * Les quatre champs d'un reglement : montant, mode, reference, notes.
 *
 * Ils apparaissent deux fois dans la meme modale — une fois pour le reglement
 * groupe, une fois pour celui a l'unite quand tout est deja solde — et sur
 * les deux fiches, client et fournisseur. Soit quatre copies a maintenir.
 *
 * Les champs remontent par `defineModel` plutot que par mutation d'un objet
 * passe en prop : l'ecran hote reste seul proprietaire de son formulaire.
 */
withDefaults(
  defineProps<{
    /** Prefixe des identifiants, pour que `label for` reste unique dans la page. */
    idPrefix: string
    /**
     * Total du des documents coches. A zero, l'avertissement de depassement
     * ne s'affiche pas — le reglement a l'unite n'en a pas besoin.
     */
    selectedTotalDue?: number
  }>(),
  { selectedTotalDue: 0 },
)

const amount = defineModel<number>('amount', { required: true })
const method = defineModel<string>('method', { required: true })
const reference = defineModel<string>('reference', { required: true })
const notes = defineModel<string>('notes', { required: true })

function formatNumber(n: number): string {
  return n.toLocaleString('fr-MA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
