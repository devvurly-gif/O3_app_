<script setup lang="ts">
import { computed } from 'vue'
import { statusConfig, allStatusLabels } from '@/composables/useDocumentLabels'

const props = withDefaults(
  defineProps<{
    /** Status key: draft, confirmed, pending, paid, etc. */
    status: string
    /** Render as pill badge (default) or inline text */
    variant?: 'badge' | 'text'
  }>(),
  {
    variant: 'badge',
  },
)

const style = computed(() => statusConfig[props.status] ?? statusConfig.draft)
const label = computed(() => allStatusLabels[props.status] ?? props.status)
</script>

<template>
  <!-- Badge variant (pill with dot) -->
  <span
    v-if="variant === 'badge'"
    :class="[style.bg, style.color]"
    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold"
  >
    <span :class="style.dot" class="w-2 h-2 rounded-full"></span>
    {{ label }}
  </span>

  <!-- Text variant (simple colored text) -->
  <span v-else :class="style.color" class="text-sm font-medium">
    {{ label }}
  </span>
</template>
