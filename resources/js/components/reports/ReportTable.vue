<template>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div
      class="px-5 py-3 border-b"
      :class="{
        'border-red-200 bg-red-50': highlight === 'red',
        'border-orange-200 bg-orange-50': highlight === 'orange',
        'border-gray-200': !highlight,
      }"
    >
      <h3
        class="text-sm font-semibold"
        :class="{
          'text-red-700': highlight === 'red',
          'text-orange-700': highlight === 'orange',
          'text-gray-700': !highlight,
        }"
      >
        {{ title }}
      </h3>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th
              v-for="(col, i) in columns"
              :key="i"
              class="px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider"
              :class="i > 0 ? 'text-right' : 'text-left'"
            >
              {{ col }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="(row, ri) in rows" :key="ri" class="hover:bg-gray-50 transition-colors">
            <td
              v-for="(cell, ci) in row"
              :key="ci"
              class="px-4 py-2.5 text-sm whitespace-nowrap"
              :class="ci > 0 ? 'text-right text-gray-600 font-medium tabular-nums' : 'text-gray-900'"
            >
              {{ cell }}
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td :colspan="columns.length" class="px-4 py-6 text-center text-sm text-gray-400">
              Aucune donnée
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string
  columns: string[]
  rows: (string | number)[][]
  highlight?: 'red' | 'orange'
}>()
</script>
