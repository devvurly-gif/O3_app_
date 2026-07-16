<script setup lang="ts">
interface Props {
  title?: string
  padded?: boolean
}

withDefaults(defineProps<Props>(), {
  padded: true,
})
</script>

<template>
  <div
    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm"
  >
    <div
      v-if="title || $slots.header"
      class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-700"
    >
      <slot name="header">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ title }}</h3>
      </slot>
    </div>

    <div :class="padded ? 'p-5' : ''">
      <slot />
    </div>

    <div
      v-if="$slots.footer"
      class="px-5 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30 rounded-b-xl"
    >
      <slot name="footer" />
    </div>
  </div>
</template>
