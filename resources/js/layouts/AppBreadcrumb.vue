<template>
  <nav v-if="crumbs.length > 1" aria-label="Breadcrumb">
    <ol class="flex items-center gap-1 text-sm">
      <li v-for="(crumb, i) in crumbs" :key="crumb.path" class="flex items-center gap-1">
        <!-- Separator -->
        <svg
          v-if="i > 0"
          class="w-3.5 h-3.5 text-gray-400 dark:text-gray-600 shrink-0"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>

        <!-- Last crumb (current page) -->
        <span
          v-if="i === crumbs.length - 1"
          class="font-medium text-gray-700 dark:text-gray-200 truncate max-w-[180px]"
          aria-current="page"
        >
          {{ crumb.label }}
        </span>

        <!-- Ancestor crumb -->
        <router-link
          v-else
          :to="crumb.path"
          class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors truncate max-w-[120px]"
        >
          {{ crumb.label }}
        </router-link>
      </li>
    </ol>
  </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const crumbs = computed(() => {
  // Always start with Home
  const result = [{ label: 'Home', path: '/' }]

  // Walk matched route records, picking up meta.breadcrumb or falling back to the route name / segment
  const segments = route.path.split('/').filter(Boolean)
  let accumulated = ''

  for (const segment of segments) {
    accumulated += `/${segment}`

    // Try to resolve the route to read its meta
    const resolved = router.resolve(accumulated)
    const label =
      (resolved?.meta?.breadcrumb as string | undefined) ??
      (resolved?.meta?.title as string | undefined) ??
      capitalize(segment.replace(/-/g, ' '))

    result.push({ label, path: accumulated })
  }

  return result
})

function capitalize(str: string) {
  return str.charAt(0).toUpperCase() + str.slice(1)
}
</script>
