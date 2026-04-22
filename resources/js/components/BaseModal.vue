<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="modelValue" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-3">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeOnBackdrop && emit('update:modelValue', false)" />

        <!-- Panel -->
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 sm:scale-95 translate-y-4 sm:translate-y-0"
          enter-to-class="opacity-100 sm:scale-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 sm:scale-100 translate-y-0"
          leave-to-class="opacity-0 sm:scale-95 translate-y-4 sm:translate-y-0"
        >
          <div
            v-if="modelValue"
            class="relative z-10 w-full bg-white dark:bg-gray-800 rounded-t-2xl sm:rounded-2xl shadow-2xl flex flex-col max-h-[95vh] sm:max-h-[90vh]"
            :class="widthClass"
          >
            <!-- Header -->
            <div class="flex items-center justify-between px-4 sm:px-5 py-2.5 border-b border-gray-200 dark:border-gray-700 shrink-0">
              <h3 class="text-sm sm:text-base font-semibold text-gray-800 dark:text-gray-100 truncate">
                <slot name="title">{{ title }}</slot>
              </h3>
              <button
                class="shrink-0 ml-2 w-7 h-7 flex items-center justify-center rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-gray-200 dark:hover:bg-gray-700 transition"
                @click="emit('update:modelValue', false)"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Body -->
            <div class="px-4 sm:px-5 py-3 overflow-y-auto flex-1">
              <slot />
            </div>

            <!-- Footer -->
            <div
              v-if="$slots.footer"
              class="px-4 sm:px-5 py-2.5 border-t border-gray-200 dark:border-gray-700 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 shrink-0 bg-gray-50/50 dark:bg-gray-900/30 rounded-b-2xl"
            >
              <slot name="footer" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  title: {
    type: String,
    default: '',
  },
  size: {
    type: String,
    default: 'md',
    validator: (v: string) => ['sm', 'md', 'lg', 'xl', '2xl', '3xl'].includes(v),
  },
  closeOnBackdrop: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['update:modelValue'])

const widthClass = computed(
  () =>
    ({
      sm: 'max-w-sm',
      md: 'max-w-lg',
      lg: 'max-w-2xl',
      xl: 'max-w-4xl',
      '2xl': 'max-w-5xl',
      '3xl': 'max-w-6xl',
    })[props.size] ?? 'max-w-lg',
)
</script>
