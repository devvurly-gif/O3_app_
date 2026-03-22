<template>
  <div class="flex items-center justify-center h-full bg-gray-100 dark:bg-gray-700">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 w-full max-w-md">
      <div class="text-center mb-8">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-blue-600 flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-2 8l-4-4H7a2 2 0 01-2-2V5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2l-4 4z" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Point de Vente</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
          {{ existingSession ? 'Vous avez une session en cours' : 'Sélectionnez un terminal et ouvrez une session' }}
        </p>
      </div>

      <!-- Existing session: resume -->
      <div v-if="existingSession" class="space-y-4">
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm space-y-1">
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">Terminal</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ existingSession.terminal?.name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">Ouvert à</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ new Date(existingSession.opened_at).toLocaleString('fr-FR') }}</span>
          </div>
        </div>
        <button
          class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl transition"
          @click="router.push('/pos/main')"
        >
          Reprendre la session
        </button>
      </div>

      <!-- No session: open new -->
      <form v-else class="space-y-5" @submit.prevent="open">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Terminal</label>
          <select
            v-model="terminalId"
            required
            class="w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option :value="null" disabled>-- Sélectionner un terminal --</option>
            <option v-for="t in terminals" :key="t.id" :value="t.id" :disabled="!t.is_active">
              {{ t.name }} ({{ t.warehouse?.wh_title ?? '—' }})
              {{ !t.is_active ? '(désactivé)' : '' }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Fond de caisse (MAD)</label>
          <input
            v-model.number="openingCash"
            type="number"
            step="0.01"
            min="0"
            required
            placeholder="0.00"
            class="w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>

        <button
          type="submit"
          :disabled="loading || !terminalId"
          class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ loading ? 'Ouverture...' : 'Ouvrir la session' }}
        </button>
      </form>

      <p v-if="error" class="mt-4 text-sm text-red-600 text-center">{{ error }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePosStore, type PosTerminal, type PosSessionData } from '@/stores/pos/posStore'
import http from '@/services/http'

const router = useRouter()
const posStore = usePosStore()

const terminals = ref<PosTerminal[]>([])
const terminalId = ref<number | null>(null)
const openingCash = ref(0)
const loading = ref(false)
const error = ref('')
const existingSession = ref<PosSessionData | null>(null)

onMounted(async () => {
  // Check for existing session
  await posStore.fetchCurrentSession()
  if (posStore.hasOpenSession) {
    existingSession.value = posStore.currentSession
    return
  }
  // Fetch terminals
  const { data } = await http.get<PosTerminal[]>('/pos/terminals')
  terminals.value = data
})

async function open() {
  if (!terminalId.value) return
  loading.value = true
  error.value = ''
  try {
    await posStore.openSession(terminalId.value, openingCash.value)
    router.push('/pos/main')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    error.value = e.response?.data?.message ?? 'Erreur lors de l\'ouverture de la session'
  } finally {
    loading.value = false
  }
}
</script>
