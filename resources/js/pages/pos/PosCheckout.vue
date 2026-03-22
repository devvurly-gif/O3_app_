<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="$emit('close')">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
      <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
        Paiement — {{ methodLabel }}
      </h2>

      <div class="text-center mb-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">Total à payer</p>
        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ total.toFixed(2) }} MAD</p>
      </div>

      <!-- Cash: numeric keypad -->
      <div v-if="method === 'cash'" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Montant donné</label>
          <input
            v-model="cashGiven"
            type="text"
            inputmode="decimal"
            class="w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-gray-600 text-right text-xl font-bold focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            readonly
          />
        </div>

        <NumericKeypad
          @input="onKeypadInput"
          @clear="cashGiven = ''"
          @backspace="cashGiven = cashGiven.slice(0, -1)"
        />

        <!-- Quick amounts -->
        <div class="grid grid-cols-4 gap-2">
          <button
            v-for="amt in quickAmounts"
            :key="amt"
            class="py-2 rounded-lg text-sm font-medium bg-blue-50 text-blue-600 hover:bg-blue-100 transition"
            @click="cashGiven = String(amt)"
          >
            {{ amt }}
          </button>
        </div>

        <div v-if="change >= 0" class="bg-green-50 border border-green-200 rounded-xl p-4 text-center">
          <p class="text-sm text-green-600">Rendu monnaie</p>
          <p class="text-2xl font-bold text-green-700">{{ change.toFixed(2) }} MAD</p>
        </div>
      </div>

      <!-- Card: simple confirm -->
      <div v-else-if="method === 'card'" class="text-center py-8">
        <svg class="w-16 h-16 mx-auto text-blue-500 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
        </svg>
        <p class="text-gray-500 dark:text-gray-400">Confirmer le paiement par carte</p>
      </div>

      <!-- Credit (En Compte): customer info + confirm -->
      <div v-else-if="method === 'credit'" class="space-y-4">
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center">
              <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ customer?.tp_title }}</p>
              <p v-if="customer?.tp_phone" class="text-xs text-gray-500 dark:text-gray-400">{{ customer.tp_phone }}</p>
            </div>
          </div>

          <div class="space-y-1.5 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600 dark:text-gray-400">Encours actuel</span>
              <span class="font-medium text-gray-900 dark:text-white">{{ Number(customer?.encours_actuel ?? 0).toFixed(2) }} MAD</span>
            </div>
            <div v-if="customer?.seuil_credit && Number(customer.seuil_credit) > 0" class="flex justify-between">
              <span class="text-gray-600 dark:text-gray-400">Plafond crédit</span>
              <span class="font-medium text-gray-900 dark:text-white">{{ Number(customer.seuil_credit).toFixed(2) }} MAD</span>
            </div>
            <div class="flex justify-between pt-1.5 border-t border-amber-200 dark:border-amber-700">
              <span class="text-gray-600 dark:text-gray-400">Nouvel encours</span>
              <span class="font-bold text-amber-600 dark:text-amber-400">{{ (Number(customer?.encours_actuel ?? 0) + total).toFixed(2) }} MAD</span>
            </div>
          </div>
        </div>

        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
          Ce montant sera ajouté à l'encours du client.
        </p>
      </div>

      <!-- Actions -->
      <div class="flex gap-3 mt-6">
        <button
          class="flex-1 py-3 rounded-xl text-sm font-medium text-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
          @click="$emit('close')"
        >
          Annuler
        </button>
        <button
          :disabled="method === 'cash' && (parseFloat(cashGiven || '0') < total)"
          class="flex-1 py-3 rounded-xl text-sm font-semibold text-white transition disabled:opacity-40 disabled:cursor-not-allowed"
          :class="method === 'credit' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-green-600 hover:bg-green-700'"
          @click="confirm"
        >
          Valider
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import NumericKeypad from '@/components/pos/NumericKeypad.vue'

const props = defineProps<{
  method: string
  total: number
  customer?: {
    id: number
    tp_title: string
    tp_phone: string | null
    type_compte: string
    encours_actuel: number
    seuil_credit: number
  } | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm', payments: { amount: number; method: string }[]): void
}>()

const cashGiven = ref('')

const methodLabel = computed(() => {
  switch (props.method) {
    case 'cash': return 'Espèces'
    case 'card': return 'Carte'
    case 'credit': return 'En Compte'
    default: return props.method
  }
})

const change = computed(() => {
  const given = parseFloat(cashGiven.value || '0')
  return given - props.total
})

const quickAmounts = computed(() => {
  const t = Math.ceil(props.total)
  return [t, t + 10, t + 20, t + 50].filter((v) => v >= props.total)
})

function onKeypadInput(key: string) {
  // Prevent multiple dots
  if (key === '.' && cashGiven.value.includes('.')) return
  cashGiven.value += key
}

function confirm() {
  emit('confirm', [
    {
      amount: props.total,
      method: props.method,
    },
  ])
}
</script>
