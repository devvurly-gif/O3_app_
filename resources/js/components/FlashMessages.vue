<script setup lang="ts">
/**
 * Reusable flash message component for success & error notifications.
 * Replaces the duplicated Transition + success/error HTML in 6+ pages.
 *
 * Usage:
 *   <FlashMessages :success="successMsg" :error="errorMsg" />
 */
defineProps<{
  success?: string
  error?: string
}>()
</script>

<template>
  <Transition
    enter-active-class="transition duration-300"
    enter-from-class="opacity-0 -translate-y-2"
    leave-active-class="transition duration-200"
    leave-to-class="opacity-0"
  >
    <div
      v-if="success"
      class="mb-4 flex items-center gap-2 px-4 py-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-300 rounded-xl text-sm"
    >
      <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      {{ success }}
    </div>
  </Transition>

  <Transition
    enter-active-class="transition duration-300"
    enter-from-class="opacity-0 -translate-y-2"
    leave-active-class="transition duration-200"
    leave-to-class="opacity-0"
  >
    <div
      v-if="error"
      class="mb-4 flex items-center gap-2 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 rounded-xl text-sm"
    >
      <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"
        />
      </svg>
      {{ error }}
    </div>
  </Transition>
</template>
