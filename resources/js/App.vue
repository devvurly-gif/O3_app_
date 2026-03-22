<template>
  <div id="app" class="antialiased">
    <component :is="layout">
      <router-view />
    </component>
    <GlobalToast />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from './layouts/AppLayout.vue'
import PosLayout from './layouts/PosLayout.vue'
import GlobalToast from './components/GlobalToast.vue'

const route = useRoute()

// Pages with meta: { layout: 'app' } get the full shell.
// Pages with meta: { layout: 'pos' } get the POS fullscreen layout.
// Everything else (login, register, etc.) renders bare.
const layout = computed(() => {
  if (route.meta?.layout === 'app') return AppLayout
  if (route.meta?.layout === 'pos') return PosLayout
  return 'div'
})
</script>

<style scoped></style>
