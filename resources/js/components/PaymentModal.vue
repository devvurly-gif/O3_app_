<script setup lang="ts">
import { ref, watch } from 'vue'
import BaseModal from '@/components/BaseModal.vue'

/**
 * Reusable payment recording modal.
 * Replaces identical payment modals in DocumentsVente, DocumentVenteShow,
 * DocumentAchatShow, Customers.
 *
 * Usage:
 *   <PaymentModal v-model="showPayment" :amount-due="500" :loading="saving"
 *     label="Facture FAC-0012 — Client XYZ" @submit="onPay" />
 */
const show = defineModel<boolean>({ required: true })

const props = withDefaults(
  defineProps<{
    /** Pre-fill the amount field (typically footer.amount_due) */
    amountDue?: number
    /** Label shown at the top (e.g. "Facture FAC-0012 — Client") */
    label?: string
    /** Whether the submit is loading */
    loading?: boolean
    /** Error message to display inside the modal */
    error?: string
  }>(),
  {
    amountDue: 0,
    label: '',
    loading: false,
    error: '',
  },
)

const emit = defineEmits<{
  submit: [
    payload: {
      amount: number
      method: string
      paid_at: string
      reference: string | null
      notes: string | null
    },
  ]
}>()

const form = ref({
  amount: 0,
  method: 'cash',
  paid_at: new Date().toISOString().split('T')[0],
  reference: '',
  notes: '',
})

// Reset form when modal opens
watch(show, (open) => {
  if (open) {
    form.value = {
      amount: props.amountDue,
      method: 'cash',
      paid_at: new Date().toISOString().split('T')[0],
      reference: '',
      notes: '',
    }
  }
})

function onSubmit() {
  emit('submit', {
    amount: form.value.amount,
    method: form.value.method,
    paid_at: form.value.paid_at,
    reference: form.value.reference || null,
    notes: form.value.notes || null,
  })
}
</script>

<template>
  <BaseModal v-model="show" title="Enregistrer un paiement" size="md">
    <!-- Context label -->
    <div v-if="label" class="mb-4 px-3 py-2.5 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm">
      {{ label }}
    </div>

    <!-- Error -->
    <div
      v-if="error"
      class="mb-4 px-3 py-2.5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg text-sm"
    >
      {{ error }}
    </div>

    <form class="space-y-4" @submit.prevent="onSubmit">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Montant *</label>
        <input
          v-model.number="form.amount"
          type="number"
          step="0.01"
          min="0.01"
          required
          class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Méthode *</label>
        <select
          v-model="form.method"
          required
          class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        >
          <option value="cash">Espèces</option>
          <option value="bank_transfer">Virement</option>
          <option value="cheque">Chèque</option>
          <option value="effet">Effet</option>
          <option value="credit">Crédit</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
        <input
          v-model="form.paid_at"
          type="date"
          class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Référence</label>
        <input
          v-model="form.reference"
          type="text"
          placeholder="N° chèque, virement..."
          class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
        <textarea
          v-model="form.notes"
          rows="2"
          placeholder="Notes..."
          class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        ></textarea>
      </div>
    </form>

    <template #footer>
      <button
        class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
        @click="show = false"
      >
        Annuler
      </button>
      <button
        :disabled="loading || form.amount <= 0"
        class="px-5 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50"
        @click="onSubmit"
      >
        {{ loading ? 'Enregistrement...' : 'Enregistrer' }}
      </button>
    </template>
  </BaseModal>
</template>
