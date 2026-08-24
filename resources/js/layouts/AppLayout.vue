<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-950">
    <!-- Lien d'evitement : premier element focusable de la page, invisible
         tant qu'il n'a pas le focus. Sans lui, atteindre le contenu au clavier
         impose de traverser toute la navigation laterale a chaque page. -->
    <a
      href="#contenu-principal"
      class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] focus:px-4 focus:py-2 focus:rounded-lg focus:bg-orange-600 focus:text-white focus:text-sm focus:font-semibold focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-white"
    >
      {{ $t('a11y.skipToContent') }}
    </a>

    <!-- Sidebar -->
    <AppSidebar
      :mobile-open="mobileOpen"
      :pinned="sidebarPinned"
      @hover="sidebarHovered = $event"
      @close-mobile="mobileOpen = false"
      @request-pin="sidebarPinned = $event"
    />

    <!-- Topbar -->
    <AppTopbar
      :sidebar-collapsed="!sidebarExpanded"
      :sidebar-pinned="sidebarPinned"
      :user-name="userName"
      :user-email="userEmail"
      @menu="mobileOpen = !mobileOpen"
      @toggle-sidebar="sidebarPinned = !sidebarPinned"
      @logout="handleLogout"
    />

    <!-- Main content -->
    <div class="flex flex-col min-h-screen transition-all duration-300" :class="mainContentClass">
      <!-- tabindex="-1" : la cible d'un lien d'evitement doit pouvoir recevoir
           le focus, sinon le lien deplace le defilement sans deplacer le
           clavier et la tabulation repart de la navigation. -->
      <main id="contenu-principal" tabindex="-1" class="flex-1 pt-16 focus:outline-none">
        <div class="px-3 py-4 sm:px-6 sm:py-6">
          <!-- Breadcrumb -->
          <div class="mb-4">
            <AppBreadcrumb />
          </div>

          <!-- Page content -->
          <slot />
        </div>
      </main>

      <!-- Footer -->
      <footer
        class="px-4 py-4 text-center text-xs text-gray-400 dark:text-gray-600 border-t border-gray-200 dark:border-gray-800"
      >
        &copy; {{ year }} {{ $t('common.appName') }}. {{ $t('common.allRights') }}
      </footer>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import AppSidebar from './AppSidebar.vue'
import AppTopbar from './AppTopbar.vue'
import AppBreadcrumb from './AppBreadcrumb.vue'

const auth = useAuthStore()

const userName = computed(() => auth.userName || 'User')
const userEmail = computed(() => auth.userEmail || '')

const router = useRouter()
const sidebarPinned = ref(false)
const sidebarHovered = ref(false)
const mobileOpen = ref(false)
const year = computed(() => new Date().getFullYear())

// The sidebar no longer widens on hover — only pinning changes its width,
// so the content offset follows `sidebarPinned` alone (w-72 / w-16).
const sidebarExpanded = computed(() => sidebarPinned.value)

const mainContentClass = computed(() => (sidebarExpanded.value ? 'lg:pl-72 pl-0' : 'lg:pl-16 pl-0'))

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>
