<template>
  <!-- Desktop: standard table -->
  <div class="hidden sm:block overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800 text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800/80">
        <tr>
          <th
            v-for="col in columns"
            :key="col.key"
            class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap"
          >
            {{ col.label }}
          </th>
          <th
            v-if="$slots.actions"
            class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider"
          >
            Actions
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <tr v-if="!rows.length">
          <td
            :colspan="$slots.actions ? columns.length + 1 : columns.length"
            class="px-4 py-8 text-center text-gray-400 dark:text-gray-500"
          >
            {{ emptyText }}
          </td>
        </tr>
        <tr
          v-for="(row, i) in rows"
          :key="row.id ?? i"
          class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
        >
          <td
            v-for="col in columns"
            :key="col.key"
            class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap"
          >
            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
              {{ row[col.key] ?? '—' }}
            </slot>
          </td>
          <td v-if="$slots.actions" class="px-4 py-3 text-right">
            <slot name="actions" :row="row" />
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Mobile: card view -->
  <div class="sm:hidden space-y-3">
    <div
      v-if="!rows.length"
      class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-8 text-center text-gray-400 dark:text-gray-500 text-sm"
    >
      {{ emptyText }}
    </div>
    <div
      v-for="(row, i) in rows"
      :key="row.id ?? i"
      class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden"
    >
      <div class="divide-y divide-gray-100 dark:divide-gray-700">
        <div v-for="col in columns" :key="col.key" class="flex items-center justify-between px-4 py-2.5 gap-3">
          <span class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider shrink-0">{{
            col.label
          }}</span>
          <span class="text-sm text-gray-700 dark:text-gray-300 text-right truncate">
            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
              {{ row[col.key] ?? '—' }}
            </slot>
          </span>
        </div>
      </div>
      <div
        v-if="$slots.actions"
        class="px-4 py-2.5 bg-gray-50 dark:bg-gray-800/80 border-t border-gray-100 dark:border-gray-700 flex justify-end"
      >
        <slot name="actions" :row="row" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  columns: Array<{ key: string; label: string; [k: string]: unknown }>
  rows: any[]
  emptyText?: string
}

withDefaults(defineProps<Props>(), {
  rows: () => [],
  emptyText: 'No records found.',
})
</script>
