<template>
  <Teleport to="body">
    <div class="fixed top-4 right-4 z-[9999] flex flex-col gap-3 w-96 pointer-events-none">
      <TransitionGroup
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 translate-x-8 scale-95"
        enter-to-class="opacity-100 translate-x-0 scale-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-x-0 scale-100"
        leave-to-class="opacity-0 translate-x-8 scale-95"
      >
        <div
          v-for="t in toasts"
          :key="t.id"
          class="flex items-start gap-3 rounded-xl px-4 py-3 shadow-xl text-sm font-medium pointer-events-auto backdrop-blur-sm"
          :class="typeClass(t.type)"
        >
          <!-- Icon -->
          <span class="mt-0.5 shrink-0">
            <!-- Success -->
            <svg v-if="t.type === 'success'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <!-- Error -->
            <svg v-else-if="t.type === 'error'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <!-- Warning -->
            <svg v-else-if="t.type === 'warning'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <!-- Info -->
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </span>

          <!-- Message -->
          <span class="flex-1 leading-snug">{{ t.message }}</span>

          <!-- Close -->
          <button
            class="shrink-0 opacity-60 hover:opacity-100 transition-opacity"
            @click="toastStore.remove(t.id)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useToastStore } from '@/stores/toastStore'

const toastStore = useToastStore()
const toasts = computed(() => toastStore.toasts)

function typeClass(type: string) {
  return {
    success: 'bg-green-50/95 text-green-800 border border-green-200 shadow-green-100/50',
    error:   'bg-red-50/95 text-red-800 border border-red-200 shadow-red-100/50',
    warning: 'bg-amber-50/95 text-amber-800 border border-amber-200 shadow-amber-100/50',
    info:    'bg-blue-50/95 text-blue-800 border border-blue-200 shadow-blue-100/50',
  }[type] ?? 'bg-gray-50/95 text-gray-800 border border-gray-200'
}
</script>
