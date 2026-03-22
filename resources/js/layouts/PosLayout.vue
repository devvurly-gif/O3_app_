<template>
  <div class="h-screen flex flex-col bg-gray-100 overflow-hidden">
    <!-- Top bar -->
    <header class="h-14 bg-slate-900 text-white flex items-center justify-between px-4 shrink-0">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
        </div>
        <span class="text-sm font-bold tracking-wide">Point de Vente</span>
        <span v-if="posStore.currentTerminal" class="text-xs bg-slate-700 px-2 py-0.5 rounded-full text-slate-300">
          {{ posStore.currentTerminal.name }}
        </span>
      </div>

      <div class="flex items-center gap-4">
        <span class="text-xs text-slate-400">{{ clock }}</span>
        <span class="text-xs text-slate-300">{{ auth.userName }}</span>
        <router-link
          to="/dashboard"
          class="text-xs bg-slate-700 hover:bg-slate-600 px-3 py-1.5 rounded-lg transition"
        >
          Quitter POS
        </router-link>
      </div>
    </header>

    <!-- Content -->
    <main class="flex-1 overflow-hidden">
      <router-view />
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import { usePosStore } from '@/stores/pos/posStore'

const auth = useAuthStore()
const posStore = usePosStore()
const clock = ref('')
let timer: ReturnType<typeof setInterval>

function updateClock() {
  const now = new Date()
  clock.value = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

onMounted(() => {
  updateClock()
  timer = setInterval(updateClock, 30000)
})

onUnmounted(() => {
  clearInterval(timer)
})
</script>
