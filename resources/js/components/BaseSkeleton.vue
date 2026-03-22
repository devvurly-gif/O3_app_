<script setup lang="ts">
withDefaults(
  defineProps<{
    type?: 'card' | 'table' | 'text' | 'chart' | 'dashboard'
    rows?: number
    cols?: number
  }>(),
  { type: 'table', rows: 5, cols: 4 },
)
</script>

<template>
  <!-- ── Dashboard skeleton: KPI cards + chart + 2 tables ── -->
  <div v-if="type === 'dashboard'" class="space-y-4 sm:space-y-6">
    <!-- KPI cards -->
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
      <div
        v-for="i in 4"
        :key="'kpi-' + i"
        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-3 py-3 sm:px-5 sm:py-4 flex items-center gap-3 sm:gap-4 shadow-sm"
      >
        <div class="skeleton w-11 h-11 rounded-lg shrink-0"></div>
        <div class="flex-1 space-y-2">
          <div class="skeleton h-3 w-20 rounded"></div>
          <div class="skeleton h-5 w-28 rounded"></div>
          <div class="skeleton h-2.5 w-24 rounded"></div>
        </div>
      </div>
    </div>

    <!-- Secondary pills -->
    <div class="flex flex-wrap gap-3">
      <div
        v-for="i in 3"
        :key="'pill-' + i"
        class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center gap-3 shadow-sm"
      >
        <div class="skeleton h-7 w-12 rounded"></div>
        <div class="skeleton h-4 w-20 rounded"></div>
      </div>
    </div>

    <!-- Chart + side panel -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
      <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
          <div class="skeleton h-4 w-52 rounded"></div>
        </div>
        <div class="px-5 py-4 flex items-end gap-1 h-48">
          <div
            v-for="i in 12"
            :key="'bar-' + i"
            class="flex-1"
          >
            <div
              class="skeleton rounded-t mx-auto"
              :style="{ height: (20 + Math.random() * 70) + '%', maxWidth: '24px' }"
            ></div>
          </div>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
          <div class="skeleton h-4 w-36 rounded"></div>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
          <div v-for="i in 5" :key="'tp-' + i" class="flex items-center gap-3 px-5 py-3">
            <div class="skeleton w-6 h-6 rounded-full shrink-0"></div>
            <div class="flex-1 space-y-1.5">
              <div class="skeleton h-3.5 w-3/4 rounded"></div>
              <div class="skeleton h-2.5 w-16 rounded"></div>
            </div>
            <div class="skeleton h-3.5 w-20 rounded"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Two tables side by side -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4">
      <div
        v-for="t in 2"
        :key="'tbl-' + t"
        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm"
      >
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
          <div class="skeleton h-4 w-40 rounded"></div>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
          <div v-for="i in 5" :key="i" class="flex items-center gap-3 px-5 py-2.5">
            <div class="flex-1 space-y-1.5">
              <div class="skeleton h-3.5 rounded" :style="{ width: (50 + Math.random() * 30) + '%' }"></div>
              <div class="skeleton h-2.5 w-24 rounded"></div>
            </div>
            <div class="skeleton h-5 w-16 rounded-full"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── KPI Cards skeleton ── -->
  <div v-else-if="type === 'card'" class="grid grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
    <div
      v-for="i in cols"
      :key="i"
      class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3 shadow-sm"
    >
      <div class="skeleton h-3 w-24 rounded"></div>
      <div class="skeleton h-6 w-32 rounded"></div>
      <div class="skeleton h-2.5 w-20 rounded"></div>
    </div>
  </div>

  <!-- ── Table skeleton ── -->
  <div v-else-if="type === 'table'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
    <!-- Header row -->
    <div class="flex gap-4 px-4 py-3 bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700">
      <div class="skeleton h-3 w-8 rounded"></div>
      <div class="skeleton h-3 w-32 rounded flex-1"></div>
      <div class="skeleton h-3 w-20 rounded hidden sm:block"></div>
      <div class="skeleton h-3 w-16 rounded hidden sm:block"></div>
      <div class="skeleton h-3 w-20 rounded hidden sm:block"></div>
      <div class="skeleton h-3 w-16 rounded"></div>
    </div>
    <!-- Data rows -->
    <div
      v-for="i in rows"
      :key="i"
      class="flex items-center gap-4 px-4 py-3 border-b border-gray-100 dark:border-gray-700/50"
    >
      <div class="skeleton h-4 w-6 rounded"></div>
      <div class="skeleton h-4 rounded flex-1" :style="{ maxWidth: (40 + Math.random() * 40) + '%' }"></div>
      <div class="skeleton h-4 w-20 rounded hidden sm:block"></div>
      <div class="skeleton h-4 w-16 rounded hidden sm:block"></div>
      <div class="skeleton h-4 w-20 rounded hidden sm:block"></div>
      <div class="skeleton h-6 w-16 rounded-full"></div>
    </div>
  </div>

  <!-- ── Chart skeleton ── -->
  <div v-else-if="type === 'chart'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
      <div class="skeleton h-4 w-48 rounded"></div>
    </div>
    <div class="px-5 py-4 flex items-end gap-1 h-48">
      <div v-for="i in 12" :key="i" class="flex-1">
        <div
          class="skeleton rounded-t mx-auto"
          :style="{ height: (15 + Math.random() * 75) + '%', maxWidth: '24px' }"
        ></div>
      </div>
    </div>
  </div>

  <!-- ── Text skeleton (lines of text) ── -->
  <div v-else-if="type === 'text'" class="space-y-3">
    <div
      v-for="i in rows"
      :key="i"
      class="skeleton h-4 rounded"
      :style="{ width: i === rows ? '60%' : (70 + Math.random() * 30) + '%' }"
    ></div>
  </div>
</template>

<style scoped>
.skeleton {
  background: linear-gradient(
    90deg,
    #e5e7eb 25%,
    #f3f4f6 50%,
    #e5e7eb 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s ease-in-out infinite;
}

:where(.dark, .dark *) .skeleton {
  background: linear-gradient(
    90deg,
    #374151 25%,
    #4b5563 50%,
    #374151 75%
  );
  background-size: 200% 100%;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
