<template>
  <div class="min-h-screen bg-[#F6F7F9] dark:bg-gray-900">
    <header class="sticky top-0 z-50 flex items-center gap-2 border-b border-[#E1E3E9] bg-white px-4 py-2.5 dark:border-gray-700 dark:bg-gray-800">
      <span class="mr-2 text-xs font-bold uppercase tracking-wider text-[#8A8F9C]">Banc</span>
      <button
        v-for="screen in screens"
        :key="screen.key"
        class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
        :class="
          current === screen.key
            ? 'bg-[#7C5CFC] text-white'
            : 'border border-[#E1E3E9] bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300'
        "
        @click="current = screen.key"
      >
        {{ screen.label }}
      </button>
      <span class="ml-auto text-xs text-[#8A8F9C]">donnees fixes — aucun backend</span>
    </header>

    <main class="p-4 sm:p-6">
      <!-- `key` force un remontage complet : chaque bascule repart d'un ecran neuf. -->
      <component :is="screens.find((s) => s.key === current)?.component" :key="current" />
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Customers from '@/pages/Customers.vue'
import Suppliers from '@/pages/Suppliers.vue'

const screens = [
  { key: 'suppliers', label: 'Fournisseurs', component: Suppliers },
  { key: 'customers', label: 'Clients', component: Customers },
]

const current = ref('suppliers')
</script>
