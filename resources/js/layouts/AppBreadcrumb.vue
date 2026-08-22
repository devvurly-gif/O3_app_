<template>
  <nav v-if="crumbs.length > 1" aria-label="Breadcrumb">
    <ol class="flex items-center gap-1 text-sm">
      <li v-for="(crumb, i) in crumbs" :key="crumb.path" class="flex items-center gap-1">
        <!-- Last crumb (current page) -->
        <span
          v-if="i === crumbs.length - 1"
          class="font-medium text-gray-700 dark:text-gray-200 truncate max-w-[180px]"
          aria-current="page"
        >
          {{ crumb.label }}
        </span>

        <!--
          Ancestor crumb.

          gray-500 et non gray-400 : sur fond clair, gray-400 ne donne que
          2,84:1 contre les 4,5:1 exigés en AA. gray-500 monte à 4,83:1.
        -->
        <router-link
          v-else
          :to="crumb.path"
          class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors truncate max-w-[120px]"
        >
          {{ crumb.label }}
        </router-link>

        <!--
          Séparateur, en fin de li plutôt qu'en tête du suivant : il reste
          ainsi collé au libellé qu'il suit même si celui-ci est tronqué.

          aria-hidden : purement décoratif, il n'a pas à être annoncé — et
          c'est aussi pourquoi gray-400 reste admis ici, le seuil de contraste
          ne s'appliquant pas à ce qui est masqué aux lecteurs d'écran.
        -->
        <svg
          v-if="i < crumbs.length - 1"
          class="w-3.5 h-3.5 text-gray-400 dark:text-gray-600 shrink-0 translate-y-px"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
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
