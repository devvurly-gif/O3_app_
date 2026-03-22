<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-950">
    <!-- Sidebar -->
    <AppSidebar :mobile-open="mobileOpen" @hover="sidebarExpanded = $event" @close-mobile="mobileOpen = false" />

    <!-- Topbar -->
    <AppTopbar
      :sidebar-collapsed="!sidebarExpanded"
      :user-name="userName"
      :user-email="userEmail"
      @menu="mobileOpen = !mobileOpen"
      @logout="handleLogout"
    />

    <!-- Main content -->
    <div class="flex flex-col min-h-screen transition-all duration-300" :class="mainContentClass">
      <main class="flex-1 pt-16">
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
const sidebarExpanded = ref(false)
const mobileOpen = ref(false)
const year = computed(() => new Date().getFullYear())

const mainContentClass = computed(() => {
  return sidebarExpanded.value ? 'lg:pl-64 pl-0' : 'lg:pl-16 pl-0'
})

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>
