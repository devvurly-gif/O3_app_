<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-[26px] sm:text-[30px] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white">
        Orchestrateur
      </h2>
      <p class="text-sm text-[#8A8F9C] dark:text-gray-400 mt-1">
        Session administrateur de {{ auth.userName }}. Vous parlez au chef des agents : il comprend une liste de
        demandes et les confie aux agents. Les agents préparent des brouillons, la validation reste la vôtre.
      </p>
    </div>

    <OrchestratorChat />
  </div>
</template>

<script setup lang="ts">
import OrchestratorChat from '@/components/agents/OrchestratorChat.vue'
import { useAuthStore } from '@/stores/authStore'

const auth = useAuthStore()
</script>
