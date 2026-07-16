<script setup lang="ts">
import BaseCard from '@/components/BaseCard.vue'

defineProps<{
  label: string
  value: string
  currency?: boolean
}>()
</script>

<template>
  <BaseCard>
    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ label }}</p>
    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
      {{ value }}<span v-if="currency" class="text-sm font-normal text-gray-400 dark:text-gray-500 ml-1">MAD</span>
    </p>
  </BaseCard>
</template>
