<script setup lang="ts">
import BaseCard from '@/components/BaseCard.vue'

defineProps<{
  title: string
  columns: string[]
  rows: (string | number)[][]
  highlight?: 'red' | 'orange'
}>()
</script>

<template>
  <BaseCard :padded="false">
    <div
      class="px-5 py-3 border-b"
      :class="{
        'border-danger-200 dark:border-danger-800 bg-danger-50 dark:bg-danger-900/20': highlight === 'red',
        'border-warning-200 dark:border-warning-800 bg-warning-50 dark:bg-warning-900/20': highlight === 'orange',
        'border-gray-200 dark:border-gray-700': !highlight,
      }"
    >
      <h3
        class="text-sm font-semibold"
        :class="{
          'text-danger-700 dark:text-danger-400': highlight === 'red',
          'text-warning-700 dark:text-warning-400': highlight === 'orange',
          'text-gray-700 dark:text-gray-300': !highlight,
        }"
      >
        {{ title }}
      </h3>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-900">
          <tr>
            <th
              v-for="(col, i) in columns"
              :key="i"
              class="px-4 py-2.5 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
              :class="i > 0 ? 'text-right' : 'text-left'"
            >
              {{ col }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <tr v-for="(row, ri) in rows" :key="ri" class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <td
              v-for="(cell, ci) in row"
              :key="ci"
              class="px-4 py-2.5 text-sm whitespace-nowrap"
              :class="ci > 0 ? 'text-right text-gray-600 dark:text-gray-400 font-medium tabular-nums' : 'text-gray-900 dark:text-white'"
            >
              {{ cell }}
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td :colspan="columns.length" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
              Aucune donnée
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </BaseCard>
</template>
