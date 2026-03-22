<template>
  <div v-if="lastPage > 1" class="flex items-center justify-between gap-4 text-sm">
    <!-- Info -->
    <p class="text-gray-500 dark:text-gray-400">
      Showing <span class="font-medium text-gray-700 dark:text-gray-200">{{ from }}</span
      >–<span class="font-medium text-gray-700 dark:text-gray-200">{{ to }}</span> of
      <span class="font-medium text-gray-700 dark:text-gray-200">{{ total }}</span>
    </p>

    <!-- Controls -->
    <div class="flex items-center gap-1">
      <!-- Previous -->
      <button
        :disabled="currentPage === 1"
        class="px-2 py-1.5 rounded-md border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition"
        @click="emit('change', currentPage - 1)"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
      </button>

      <!-- Pages -->
      <template v-for="page in pages" :key="page">
        <span v-if="page === '...'" class="px-2 py-1.5 text-gray-400 dark:text-gray-500 select-none">…</span>
        <button
          v-else
          class="px-3 py-1.5 rounded-md border font-medium transition"
          :class="
            page === currentPage
              ? 'bg-blue-600 text-white border-blue-600'
              : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
          "
          @click="emit('change', page)"
        >
          {{ page }}
        </button>
      </template>

      <!-- Next -->
      <button
        :disabled="currentPage === lastPage"
        class="px-2 py-1.5 rounded-md border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition"
        @click="emit('change', currentPage + 1)"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  currentPage: { type: Number, required: true },
  lastPage: { type: Number, required: true },
  total: { type: Number, required: true },
  perPage: { type: Number, default: 15 },
})

const emit = defineEmits(['change'])

const from = computed(() => (props.currentPage - 1) * props.perPage + 1)
const to = computed(() => Math.min(props.currentPage * props.perPage, props.total))

const pages = computed(() => {
  const total = props.lastPage
  const cur = props.currentPage
  const delta = 2
  const range = []

  for (let i = Math.max(2, cur - delta); i <= Math.min(total - 1, cur + delta); i++) {
    range.push(i)
  }

  if (cur - delta > 2) range.unshift('...')
  if (cur + delta < total - 1) range.push('...')

  range.unshift(1)
  if (total > 1) range.push(total)

  return range
})
</script>
