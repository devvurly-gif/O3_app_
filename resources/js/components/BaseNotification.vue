<template>
  <Teleport to="body">
    <div class="fixed top-4 right-4 z-50 flex flex-col gap-3 w-80">
      <TransitionGroup
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 translate-x-8"
        enter-to-class="opacity-100 translate-x-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-x-0"
        leave-to-class="opacity-0 translate-x-8"
      >
        <div
          v-for="n in notifications"
          :key="n.id"
          class="flex items-start gap-3 rounded-lg px-4 py-3 shadow-lg text-sm font-medium"
          :class="typeClass(n.type)"
        >
          <!-- Icon -->
          <span class="mt-0.5 shrink-0">
            <svg
              v-if="n.type === 'success'"
              class="w-5 h-5"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            <svg
              v-else-if="n.type === 'error'"
              class="w-5 h-5"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <svg
              v-else-if="n.type === 'warning'"
              class="w-5 h-5"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
              />
            </svg>
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"
              />
            </svg>
          </span>

          <!-- Message -->
          <span class="flex-1">{{ n.message }}</span>

          <!-- Close -->
          <button class="shrink-0 opacity-60 hover:opacity-100 transition-opacity" @click="remove(n.id)">
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
import { ref } from 'vue'

interface Notification {
  id: number
  message: string
  type: string
}

const notifications = ref<Notification[]>([])

let _id = 0

function notify(message: string, type = 'info', duration = 4000) {
  const id = ++_id
  notifications.value.push({ id, message, type })
  if (duration > 0) setTimeout(() => remove(id), duration)
}

function remove(id: number) {
  notifications.value = notifications.value.filter((n) => n.id !== id)
}

function typeClass(type: string) {
  return (
    {
      success: 'bg-green-50 text-green-800 border border-green-200',
      error: 'bg-red-50 text-red-800 border border-red-200',
      warning: 'bg-yellow-50 text-yellow-800 border border-yellow-200',
      info: 'bg-blue-50 text-blue-800 border border-blue-200',
    }[type] ?? 'bg-gray-50 text-gray-800 border border-gray-200'
  )
}

defineExpose({ notify })
</script>
