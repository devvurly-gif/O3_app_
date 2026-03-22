<template>
  <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ label }}</p>
    <p class="text-2xl font-bold text-gray-900 mt-1">
      {{ value }}<span v-if="currency" class="text-sm font-normal text-gray-400 ml-1">MAD</span>
    </p>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  label: string
  value: string
  currency?: boolean
}>()
</script>
