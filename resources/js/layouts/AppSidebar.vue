<template>
  <!-- ── Mobile backdrop ────────────────────────────────────── -->
  <Transition
    enter-active-class="transition duration-200"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition duration-150"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="emit('closeMobile')" />
  </Transition>

  <aside
    class="fixed inset-y-0 left-0 z-50 flex flex-col bg-slate-950 border-r border-slate-800 transition-all duration-300 ease-in-out select-none"
    :class="sidebarClasses"
    @mouseenter="onMouseEnter"
    @mouseleave="onMouseLeave"
  >
    <!-- ── Logo ─────────────────────────────────────────────────── -->
    <div class="flex items-center gap-3 px-3 h-16 border-b border-slate-800 shrink-0 overflow-hidden">
      <div
        class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center shrink-0 shadow-lg shadow-blue-900/40"
      >
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
      </div>
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-x-2"
        enter-to-class="opacity-100 translate-x-0"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showLabels" class="min-w-0 flex-1 flex items-center justify-between">
          <div>
            <p class="text-sm font-bold text-white tracking-wide truncate leading-none">{{ $t('common.appName') }}</p>
            <p class="text-xs text-slate-500 mt-0.5">{{ $t('common.management') }}</p>
          </div>
          <!-- Mobile close button -->
          <button
            v-if="mobileOpen"
            type="button"
            class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
            @click="emit('closeMobile')"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </Transition>
    </div>

    <!-- ── Nav ──────────────────────────────────────────────────── -->
    <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 space-y-0.5">
      <template v-for="item in navItems" :key="item.to ?? item.groupKey">
        <!-- ── Group header (collapsible) ─────────────────── -->
        <div v-if="item.groupKey" class="overflow-hidden">
          <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
          >
            <button
              v-if="showLabels"
              type="button"
              class="w-full flex items-center justify-between mt-4 mb-1 px-3 group"
              @click="toggleGroup(item.groupKey)"
            >
              <span class="flex items-center gap-2">
                <span
                  v-if="item.groupIcon"
                  class="w-4 h-4 text-slate-600 group-hover:text-slate-400 transition-colors"
                  v-html="item.groupIcon"
                />
                <span
                  class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500 group-hover:text-slate-300 transition-colors"
                >
                  {{ $t(item.groupKey) }}
                </span>
              </span>
              <svg
                class="w-3 h-3 text-slate-600 transition-transform duration-200"
                :class="isGroupOpen(item.groupKey) ? 'rotate-180' : ''"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
          </Transition>
          <hr v-if="!showLabels" class="border-slate-800 my-3 mx-2" />
        </div>

        <!-- ── Nav link ────────────────────────────────────── -->
        <template v-if="item.to">
          <router-link
            v-show="
              showLabels ? !item.groupKey_ref || isGroupOpen(item.groupKey_ref) : !item.groupKey_ref || item.groupFirst
            "
            :to="item.to"
            class="group relative flex items-center gap-3 px-3 py-2.5 text-sm font-medium transition-all duration-150 overflow-hidden"
            :class="
              isActive(item.to)
                ? 'bg-orange-600 text-white shadow-md shadow-blue-900/30'
                : 'text-slate-400 hover:bg-slate-800/70 hover:text-slate-100'
            "
            :title="!showLabels ? (item.labelKey ? $t(item.labelKey) : item.label) : undefined"
            @click="onNavClick"
          >
            <span v-if="isActive(item.to)" class="absolute right-0 inset-y-2 w-0.5 rounded-r-full bg-white/60" />
            <span
              class="shrink-0 w-5 h-5 transition-transform duration-150 group-hover:scale-105"
              :class="isActive(item.to) ? 'text-white' : 'text-slate-500 group-hover:text-slate-300'"
              v-html="item.icon"
            />
            <Transition
              enter-active-class="transition duration-150 ease-out"
              enter-from-class="opacity-0 -translate-x-1"
              enter-to-class="opacity-100 translate-x-0"
              leave-active-class="transition duration-100 ease-in"
              leave-from-class="opacity-100"
              leave-to-class="opacity-0"
            >
              <span v-if="showLabels" class="truncate">{{ item.labelKey ? $t(item.labelKey) : item.label }}</span>
            </Transition>
            <Transition
              enter-active-class="transition duration-150"
              enter-from-class="opacity-0"
              enter-to-class="opacity-100"
            >
              <span
                v-if="showLabels && item.badge"
                class="ml-auto text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                :class="isActive(item.to) ? 'bg-white/20 text-white' : 'bg-slate-700 text-slate-300'"
              >
                {{ item.badge }}
              </span>
            </Transition>
          </router-link>
        </template>
      </template>
    </nav>

    <!-- ── Footer: real user info ────────────────────────────── -->
    <div class="shrink-0 border-t border-slate-800 p-3 flex items-center gap-3 overflow-hidden">
      <div
        class="w-9 h-9 rounded-full bg-blue-700 flex items-center justify-center shrink-0 text-white text-xs font-bold uppercase"
      >
        {{ userInitials }}
      </div>
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 -translate-x-1"
        enter-to-class="opacity-100 translate-x-0"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showLabels" class="min-w-0 flex-1">
          <div class="flex items-center gap-1.5">
            <p class="text-xs font-medium text-slate-300 truncate">{{ auth.userName || '—' }}</p>
            <span
              v-if="auth.userRole"
              class="shrink-0 text-[9px] font-semibold px-1.5 py-0.5 rounded-full bg-slate-700 text-slate-400 uppercase tracking-wide"
            >
              {{ auth.userRole }}
            </span>
          </div>
          <p class="text-[10px] text-slate-500 truncate">{{ auth.userEmail || '' }}</p>
        </div>
      </Transition>
      <Transition
        enter-active-class="transition duration-150"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
      >
        <button
          v-if="showLabels"
          type="button"
          class="shrink-0 p-1.5 rounded-lg text-slate-500 hover:text-red-400 hover:bg-slate-800 transition"
          :title="$t('nav.logout')"
          @click="doLogout"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
            />
          </svg>
        </button>
      </Transition>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import { useDocumentIncrementorStore } from '@/stores/documentIncrementor'

interface SidebarLink {
  to?: string
  labelKey?: string
  label?: string
  icon?: string
  groupKey?: string
  groupIcon?: string
  groupKey_ref?: string
  groupFirst?: boolean
  adminOnly?: boolean
  centralOnly?: boolean
  badge?: string | number
}

const props = defineProps<{
  mobileOpen?: boolean
}>()

const emit = defineEmits(['hover', 'closeMobile'])
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const diStore = useDocumentIncrementorStore()

const centralDomains = ['localhost', '127.0.0.1', import.meta.env.VITE_CENTRAL_DOMAIN].filter(Boolean)
const isCentralDomain = computed(() => {
  const host = window.location.hostname
  return centralDomains.includes(host)
})

onMounted(() => {
  if (!diStore.items.length) diStore.fetchAll()
})

const hovered = ref(false)
const openGroups = ref(
  new Set(['nav.catalogue', 'nav.partners', 'nav.sales', 'nav.purchases', 'nav.stock', 'nav.marketing', 'nav.settings']),
)

const isAdmin = computed(() => auth.userRole === 'admin')
const isExpanded = computed(() => hovered.value)

const showLabels = computed(() => props.mobileOpen || isExpanded.value)

const sidebarClasses = computed(() => {
  if (props.mobileOpen) {
    return 'w-64 translate-x-0'
  }
  return [isExpanded.value ? 'w-64' : 'w-16', 'max-lg:-translate-x-full max-lg:w-64']
})

const userInitials = computed(() => {
  const n = auth.userName
  if (!n) return '?'
  const parts = n.trim().split(' ')
  return parts.length >= 2 ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase() : n.slice(0, 2).toUpperCase()
})

function onMouseEnter() {
  hovered.value = true
  emit('hover', true)
}
function onMouseLeave() {
  hovered.value = false
  emit('hover', false)
}

function onNavClick() {
  if (props.mobileOpen) emit('closeMobile')
}

function toggleGroup(key: string) {
  if (openGroups.value.has(key)) openGroups.value.delete(key)
  else openGroups.value.add(key)
}
function isGroupOpen(key: string) {
  return openGroups.value.has(key)
}

function isActive(to: string) {
  const [toPath, toQuery] = to.split('?')
  if (toQuery) {
    const [key, val] = toQuery.split('=')
    return route.path === toPath && route.query[key] === val
  }
  return route.path === toPath
}

async function doLogout() {
  await auth.logout()
  router.push('/login')
}

// ── Icons ─────────────────────────────────────────────────────────────────
const icons = {
  dashboard: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>`,
  products: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0v10l-8 4m0-14L4 17m8 4V10"/></svg>`,
  categories: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>`,
  brands: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>`,
  customers: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>`,
  suppliers: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>`,
  warehouses: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v9a1 1 0 001 1h4v-5h4v5h4a1 1 0 001-1v-9"/></svg>`,
  purchase: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>`,
  sales: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`,
  stock: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>`,
  newdoc: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>`,
  listdoc: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h10"/></svg>`,
  users: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>`,
  code: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>`,
  docs: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>`,
  appsettings: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>`,
  roles: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>`,
  pos: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-2 8l-4-4H7a2 2 0 01-2-2V5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2l-4 4z"/></svg>`,
  modules: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>`,
  terminal: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>`,
  reports: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`,
  audit: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`,
  promo: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>`,
  pricelists: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h18v4H3V3zm0 7h12v4H3v-4zm0 7h18v4H3v-4z"/></svg>`,
  slides: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>`,
}

const groupIcons = {
  catalogue: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>`,
  partners: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`,
  purchases: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>`,
  sales: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`,
  stock: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>`,
  settings: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`,
  marketing: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>`,
}

// ── Central-only menu (super admin) ──────────────────────────────────
const centralNavItems: SidebarLink[] = [
  {
    to: '/central/tenants',
    label: 'Gestion Clients',
    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>',
  },
  {
    to: '/central/tenants/create',
    label: 'Nouveau Client',
    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
  },
]

// ── Tenant menu (client apps) ────────────────────────────────────────
const tenantNavItems = computed((): SidebarLink[] => {
  const items: SidebarLink[] = [
    { to: '/dashboard', labelKey: 'nav.dashboard', icon: icons.dashboard },

    { groupKey: 'nav.catalogue', groupIcon: groupIcons.catalogue },
    {
      to: '/products',
      labelKey: 'nav.products',
      icon: icons.products,
      groupKey_ref: 'nav.catalogue',
      groupFirst: true,
    },
    { to: '/categories', labelKey: 'nav.categories', icon: icons.categories, groupKey_ref: 'nav.catalogue' },
    { to: '/brands', labelKey: 'nav.brands', icon: icons.brands, groupKey_ref: 'nav.catalogue' },
    { to: '/price-lists', labelKey: 'nav.pricelists', icon: icons.pricelists, groupKey_ref: 'nav.catalogue' },
    { to: '/storage/gallery', label: 'Galerie Images', icon: icons.slides, groupKey_ref: 'nav.catalogue' },

    { groupKey: 'nav.partners', groupIcon: groupIcons.partners },
    {
      to: '/customers',
      labelKey: 'nav.customers',
      icon: icons.customers,
      groupKey_ref: 'nav.partners',
      groupFirst: true,
    },
    { to: '/suppliers', labelKey: 'nav.suppliers', icon: icons.suppliers, groupKey_ref: 'nav.partners' },
    { to: '/warehouses', labelKey: 'nav.warehouses', icon: icons.warehouses, groupKey_ref: 'nav.partners' },

    { groupKey: 'nav.sales', groupIcon: groupIcons.sales },
    {
      to: '/ventes/documents',
      labelKey: 'nav.documentsVente',
      icon: icons.listdoc,
      groupKey_ref: 'nav.sales',
      groupFirst: true,
    },

    { groupKey: 'nav.purchases', groupIcon: groupIcons.purchases },
    {
      to: '/achats/documents',
      labelKey: 'nav.documentsAchat',
      icon: icons.listdoc,
      groupKey_ref: 'nav.purchases',
      groupFirst: true,
    },

    { groupKey: 'nav.stock', groupIcon: groupIcons.stock },
    {
      to: '/stock/documents',
      labelKey: 'nav.documentsStock',
      icon: icons.docs,
      groupKey_ref: 'nav.stock',
      groupFirst: true,
    },
    { to: '/stock/mouvements', labelKey: 'nav.mouvements', icon: icons.listdoc, groupKey_ref: 'nav.stock' },

    // Reports
    { to: '/reports', label: 'Rapports', icon: icons.reports },

    // Marketing & eCom
    { groupKey: 'nav.marketing', groupIcon: groupIcons.marketing },
    {
      to: '/marketing/promotions',
      label: 'Promotions & Bannières',
      icon: icons.promo,
      groupKey_ref: 'nav.marketing',
      groupFirst: true,
    },

    // POS (conditional on module)
    ...(auth.hasModule('pos')
      ? [
          {
            to: '/pos',
            label: 'Point de Vente',
            icon: icons.pos,
          } as SidebarLink,
        ]
      : []),

    { groupKey: 'nav.settings', groupIcon: groupIcons.settings, adminOnly: true },
    {
      to: '/settings/users',
      labelKey: 'users.title',
      icon: icons.users,
      adminOnly: true,
      groupKey_ref: 'nav.settings',
    },
    {
      to: '/settings/roles',
      label: 'Rôles & Permissions',
      icon: icons.roles,
      adminOnly: true,
      groupKey_ref: 'nav.settings',
    },
    {
      to: '/settings/structure-incrementors',
      labelKey: 'structureIncrementors.title',
      icon: icons.code,
      adminOnly: true,
      groupKey_ref: 'nav.settings',
    },
    {
      to: '/settings/document-incrementors',
      labelKey: 'documentIncrementors.title',
      icon: icons.docs,
      adminOnly: true,
      groupKey_ref: 'nav.settings',
    },
    {
      to: '/settings/app',
      labelKey: 'appSettings.title',
      icon: icons.appsettings,
      adminOnly: true,
      groupKey_ref: 'nav.settings',
    },
    {
      to: '/settings/modules',
      label: 'Modules',
      icon: icons.modules,
      adminOnly: true,
      groupKey_ref: 'nav.settings',
    },
    {
      to: '/settings/imports',
      labelKey: 'imports.title',
      icon: icons.stock,
      adminOnly: true,
      groupKey_ref: 'nav.settings',
    },
    ...(auth.hasModule('pos')
      ? [
          {
            to: '/settings/pos-terminals',
            label: 'Terminaux POS',
            icon: icons.terminal,
            adminOnly: true,
            groupKey_ref: 'nav.settings',
          } as SidebarLink,
          {
            to: '/settings/pos-sessions',
            label: 'Sessions POS',
            icon: icons.listdoc,
            adminOnly: true,
            groupKey_ref: 'nav.settings',
          } as SidebarLink,
        ]
      : []),
    {
      to: '/settings/activity-log',
      label: "Piste d'audit",
      icon: icons.audit,
      adminOnly: true,
      groupKey_ref: 'nav.settings',
    },
  ]

  return items.filter((item) => {
    if (item.adminOnly && !isAdmin.value) return false
    return true
  })
})

// ── Choose menu based on domain ──────────────────────────────────────
const navItems = computed((): SidebarLink[] => {
  return isCentralDomain.value ? centralNavItems : tenantNavItems.value
})
</script>
