<template>
  <header
    class="fixed top-0 right-0 z-20 flex items-center justify-between h-16 px-4 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 shadow-sm transition-all duration-300 left-0"
    :class="sidebarCollapsed ? 'lg:left-16' : 'lg:left-64'"
  >
    <!-- Left: mobile menu toggle -->
    <button
      class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors lg:hidden"
      @click="emit('menu')"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>

    <!-- Page title (hidden on mobile — breadcrumb below carries context) -->
    <h1 class="hidden lg:block text-base font-semibold text-gray-700 dark:text-gray-200 truncate">
      {{ pageTitle }}
    </h1>

    <!-- Right: actions -->
    <div class="flex items-center gap-2 ml-auto">
      <!-- Dark mode toggle -->
      <button
        class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
        :title="isDark ? 'Mode clair' : 'Mode sombre'"
        @click="toggleDark"
      >
        <svg v-if="isDark" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
          />
        </svg>
        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
          />
        </svg>
      </button>

      <!-- Search -->
      <button
        class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
        </svg>
      </button>

      <!-- Language switcher -->
      <div class="flex items-center gap-1 px-1">
        <button
          v-for="lang in langs"
          :key="lang.code"
          class="px-2 py-1 rounded text-xs font-semibold transition-colors"
          :class="
            currentLocale === lang.code
              ? 'bg-blue-600 text-white'
              : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'
          "
          @click="switchLang(lang.code)"
        >
          {{ lang.label }}
        </button>
      </div>

      <!-- Notifications -->
      <div class="relative">
        <button
          class="relative p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
          @click="notifOpen = !notifOpen"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
            />
          </svg>
          <span
            v-if="unreadCount > 0"
            class="absolute top-0.5 right-0.5 inline-flex items-center justify-center min-w-[16px] h-4 px-1 text-[10px] font-bold text-white bg-red-500 rounded-full"
          >
            {{ unreadCount > 99 ? '99+' : unreadCount }}
          </span>
        </button>

        <!-- Dropdown -->
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 scale-95 -translate-y-1"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="notifOpen"
            v-click-outside="() => (notifOpen = false)"
            class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden max-h-96 flex flex-col"
          >
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
              <span class="font-semibold text-sm text-gray-700 dark:text-gray-200">{{ $t('nav.notifications') }}</span>
              <button
                v-if="unreadCount > 0"
                class="text-xs text-blue-600 hover:text-blue-800 font-medium"
                @click="doMarkAllRead"
              >
                Tout marquer lu
              </button>
            </div>
            <div class="overflow-y-auto flex-1">
              <p v-if="!notifications.length" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                {{ $t('nav.noNotifications') }}
              </p>
              <div
                v-for="n in notifications"
                :key="n.id"
                class="flex items-start gap-2.5 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-50 dark:border-gray-700 last:border-0 transition"
                @click="doMarkRead(n)"
              >
                <span
                  class="mt-0.5 shrink-0 inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold"
                  :class="notifIconClass(n.data?.type)"
                >
                  {{ notifIcon(n.data?.type) }}
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-sm text-gray-800 dark:text-gray-100 font-medium leading-snug">
                    {{ notifTitle(n.data) }}
                  </p>
                  <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ timeAgo(n.created_at) }}</p>
                </div>
                <span v-if="!n.read_at" class="mt-1.5 w-2 h-2 rounded-full bg-blue-500 shrink-0"></span>
              </div>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Avatar / User menu -->
      <div class="relative">
        <button
          class="flex items-center gap-2 pl-1 pr-2 py-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
          @click="userOpen = !userOpen"
        >
          <span
            class="w-8 h-8 rounded-full bg-blue-600 text-white text-sm font-bold flex items-center justify-center select-none"
          >
            {{ initials }}
          </span>
          <span class="hidden sm:block text-sm font-medium text-gray-700 dark:text-gray-200 truncate max-w-[120px]">{{
            userName
          }}</span>
          <svg
            class="w-4 h-4 text-gray-400 dark:text-gray-500"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 scale-95 -translate-y-1"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="userOpen"
            v-click-outside="() => (userOpen = false)"
            class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-sm"
          >
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
              <p class="font-semibold text-gray-800 dark:text-gray-100 truncate">{{ userName }}</p>
              <p class="text-gray-400 dark:text-gray-500 text-xs truncate">{{ userEmail }}</p>
            </div>
            <router-link
              to="/profile"
              class="flex items-center gap-2 px-4 py-2 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
              @click="userOpen = false"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                />
              </svg>
              {{ $t('nav.profile') }}
            </router-link>
            <button
              class="w-full flex items-center gap-2 px-4 py-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30"
              @click="emit('logout')"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"
                />
              </svg>
              {{ $t('nav.logout') }}
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { setLocale } from '@/i18n/index'
import { useNotifications } from '@/composables/useNotifications'
import { useDarkMode } from '@/composables/useDarkMode'

const props = defineProps({
  sidebarCollapsed: { type: Boolean, default: false },
  userName: { type: String, default: 'User' },
  userEmail: { type: String, default: '' },
})

const emit = defineEmits(['menu', 'logout'])

const route = useRoute()
const { locale } = useI18n()
const notifOpen = ref(false)
const userOpen = ref(false)

const { unreadCount, notifications, markAsRead, markAllAsRead } = useNotifications()
const { isDark, toggle: toggleDark } = useDarkMode()

function doMarkRead(n) {
  markAsRead(n.id)
}
function doMarkAllRead() {
  markAllAsRead()
}

function notifTitle(data) {
  if (!data) return 'Notification'
  switch (data.type) {
    case 'low_stock':
      return `${data.count} produit(s) en stock bas`
    case 'order_confirmation':
      return `Document ${data.reference} confirme`
    case 'invoice_due_reminder':
      return `${data.count} facture(s) en retard`
    case 'payment_received':
      return `Paiement ${data.amount} DH recu — ${data.document_reference}`
    case 'stock_movement':
      return `Stock bas: ${data.product_name} (${data.new_stock_level} restant)`
    default:
      return 'Notification'
  }
}

function notifIconClass(type) {
  switch (type) {
    case 'low_stock':
    case 'stock_movement':
      return 'bg-orange-100 text-orange-600'
    case 'order_confirmation':
    case 'payment_received':
      return 'bg-green-100 text-green-600'
    case 'invoice_due_reminder':
      return 'bg-red-100 text-red-600'
    default:
      return 'bg-gray-100 text-gray-500'
  }
}

function notifIcon(type) {
  switch (type) {
    case 'low_stock':
    case 'stock_movement':
      return '!'
    case 'order_confirmation':
      return '✓'
    case 'invoice_due_reminder':
      return '$'
    case 'payment_received':
      return '+'
    default:
      return '?'
  }
}

function timeAgo(dateStr) {
  if (!dateStr) return ''
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return "À l'instant"
  if (mins < 60) return `Il y a ${mins} min`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `Il y a ${hours}h`
  const days = Math.floor(hours / 24)
  return `Il y a ${days}j`
}

const langs = [
  { code: 'en', label: 'EN' },
  { code: 'fr', label: 'FR' },
]

const currentLocale = computed(() => locale.value)

function switchLang(lang) {
  setLocale(lang)
}

const pageTitle = computed(() => route.meta?.title ?? '')
const initials = computed(() =>
  props.userName
    .split(' ')
    .map((w) => w[0])
    .slice(0, 2)
    .join('')
    .toUpperCase(),
)

// Simple click-outside directive
const vClickOutside = {
  mounted(el, binding) {
    el._clickOutside = (e) => {
      if (!el.contains(e.target)) binding.value(e)
    }
    document.addEventListener('click', el._clickOutside, true)
  },
  unmounted(el) {
    document.removeEventListener('click', el._clickOutside, true)
  },
}
</script>
