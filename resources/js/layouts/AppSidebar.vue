<template>
  <!-- ── Mobile backdrop ────────────────────────────────────────────── -->
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
    class="fixed inset-y-0 left-0 z-50 flex flex-col select-none bg-white dark:bg-slate-950 border-r border-slate-200 dark:border-slate-800 transition-[width,transform] duration-200 ease-out"
    :class="sidebarClasses"
    @mouseenter="onMouseEnter"
    @mouseleave="onMouseLeave"
  >
    <!-- ── Header ───────────────────────────────────────────────────── -->
    <div
      class="h-16 shrink-0 flex items-center gap-2.5 px-3 border-b border-slate-200 dark:border-slate-800 overflow-hidden"
    >
      <div class="w-[34px] h-[34px] rounded-xl bg-[#7C5CFC] flex items-center justify-center shrink-0">
        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
      </div>
      <div v-if="showLabels" class="min-w-0 flex-1 flex items-center justify-between gap-2">
        <div class="min-w-0">
          <p class="text-[13px] font-bold text-slate-900 dark:text-slate-100 truncate leading-none">
            {{ $t('common.appName') }}
          </p>
          <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1 truncate">{{ $t('common.management') }}</p>
        </div>
        <button
          type="button"
          class="shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 dark:text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200 transition"
          :title="mobileOpen ? $t('nav.close') : $t('nav.unpin')"
          @click="onHeaderClose"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6" />
          </svg>
        </button>
      </div>
    </div>

    <!-- ── Expanded nav ─────────────────────────────────────────────── -->
    <div v-if="showLabels" class="flex-1 min-h-0 flex flex-col">
      <!-- Search -->
      <div class="shrink-0 px-3 pt-3 pb-2">
        <div
          class="flex items-center gap-2 h-9 px-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 focus-within:border-[#7C5CFC]"
        >
          <svg
            class="w-[15px] h-[15px] shrink-0 text-slate-400 dark:text-slate-500"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
          </svg>
          <input
            v-model="query"
            type="search"
            :placeholder="$t('nav.searchPlaceholder')"
            class="flex-1 min-w-0 bg-transparent border-0 p-0 text-[13px] text-slate-800 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-0"
          />
          <button
            v-if="query"
            type="button"
            class="shrink-0 w-4 h-4 rounded text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
            :title="$t('nav.clear')"
            @click="query = ''"
          >
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
              <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
        </div>
      </div>

      <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden px-2 pb-3">
        <!-- Search results -->
        <template v-if="query">
          <p class="px-2 pt-1.5 pb-1 text-[10px] font-bold uppercase tracking-[0.13em] text-slate-400 dark:text-slate-500">
            {{ $t('nav.results') }}
          </p>
          <router-link
            v-for="link in searchResults"
            :key="link.to"
            :to="link.to"
            class="group flex items-center gap-2.5 h-9 px-2.5 rounded-lg text-[13px] font-medium transition-colors"
            :class="rowClass(link.to)"
            @click="onNavClick"
          >
            <span class="shrink-0 w-[18px] h-[18px]" v-html="safeIcon(link.icon)" />
            <span class="flex-1 min-w-0 truncate text-left">{{ $t(link.labelKey) }}</span>
            <span class="shrink-0 text-[10px] text-slate-400 dark:text-slate-500">{{ $t(link.groupLabelKey) }}</span>
          </router-link>
          <p v-if="!searchResults.length" class="px-2 py-3 text-xs text-slate-400 dark:text-slate-500">
            {{ $t('nav.noResults') }}
          </p>
        </template>

        <!-- Favorites + groups -->
        <template v-else>
          <template v-if="favoriteLinks.length">
            <p class="px-2 pt-2 pb-1 text-[10px] font-bold uppercase tracking-[0.13em] text-slate-400 dark:text-slate-500">
              {{ $t('nav.favorites') }}
            </p>
            <div v-for="link in favoriteLinks" :key="'fav-' + link.to" class="relative flex items-center">
              <router-link
                :to="link.to"
                class="flex-1 flex items-center gap-2.5 h-9 pl-2.5 pr-9 rounded-lg text-[13px] font-medium transition-colors"
                :class="rowClass(link.to)"
                @click="onNavClick"
              >
                <span class="shrink-0 w-[18px] h-[18px]" v-html="safeIcon(link.icon)" />
                <span class="flex-1 min-w-0 truncate text-left">{{ $t(link.labelKey) }}</span>
              </router-link>
              <button
                type="button"
                class="absolute right-1.5 w-[22px] h-[22px] rounded-md flex items-center justify-center text-[#7C5CFC]"
                :title="$t('nav.removeFavorite')"
                @click.stop.prevent="toggleFavorite(link.to)"
              >
                <svg class="w-[13px] h-[13px]" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 3l2.6 5.5 6 .8-4.4 4.2 1.1 6-5.3-3-5.3 3 1.1-6L3.4 9.3l6-.8z" />
                </svg>
              </button>
            </div>
            <hr class="border-slate-200 dark:border-slate-800 mx-2 mt-2" />
          </template>

          <p class="px-2 pt-2 pb-1 text-[10px] font-bold uppercase tracking-[0.13em] text-slate-400 dark:text-slate-500">
            {{ $t('nav.sections') }}
          </p>

          <div v-for="group in visibleGroups" :key="group.id" class="mb-0.5">
            <button
              type="button"
              class="w-full flex items-center gap-2.5 h-[34px] px-2.5 rounded-lg text-xs font-semibold transition-colors hover:bg-slate-100 dark:hover:bg-slate-800/70"
              :class="
                groupHasActive(group)
                  ? 'text-slate-900 dark:text-slate-100'
                  : 'text-slate-500 dark:text-slate-400'
              "
              :aria-expanded="isGroupOpen(group.id)"
              @click="toggleGroup(group.id)"
            >
              <span class="shrink-0 w-[17px] h-[17px] opacity-80" v-html="safeIcon(group.icon)" />
              <span class="flex-1 min-w-0 truncate text-left">{{ $t(group.labelKey) }}</span>
              <span v-if="groupHasActive(group)" class="shrink-0 w-1.5 h-1.5 rounded-full bg-[#7C5CFC]" />
              <span class="shrink-0 text-[10px] text-slate-400 dark:text-slate-500">{{ group.links.length }}</span>
              <svg
                class="shrink-0 w-3 h-3 opacity-60 transition-transform duration-200"
                :class="isGroupOpen(group.id) ? 'rotate-180' : ''"
                fill="none"
                stroke="currentColor"
                stroke-width="2.4"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div
              v-if="isGroupOpen(group.id)"
              class="ml-2 pl-3 border-l border-slate-200 dark:border-slate-800 flex flex-col gap-px py-0.5"
            >
              <div v-for="link in group.links" :key="link.to" class="relative flex items-center group/row">
                <router-link
                  :to="link.to"
                  class="flex-1 flex items-center gap-2.5 h-9 pl-2.5 pr-9 rounded-lg text-[13px] font-medium transition-colors"
                  :class="rowClass(link.to)"
                  @click="onNavClick"
                >
                  <span class="shrink-0 w-[17px] h-[17px]" v-html="safeIcon(link.icon)" />
                  <span class="flex-1 min-w-0 truncate text-left">{{ $t(link.labelKey) }}</span>
                  <span
                    v-if="link.badge"
                    class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400"
                  >
                    {{ link.badge }}
                  </span>
                </router-link>
                <button
                  type="button"
                  class="absolute right-1.5 w-[22px] h-[22px] rounded-md flex items-center justify-center transition-opacity"
                  :class="
                    isFavorite(link.to)
                      ? 'text-[#7C5CFC] opacity-100'
                      : 'text-slate-400 opacity-0 group-hover/row:opacity-60 hover:!opacity-100'
                  "
                  :title="isFavorite(link.to) ? $t('nav.removeFavorite') : $t('nav.addFavorite')"
                  @click.stop.prevent="toggleFavorite(link.to)"
                >
                  <svg
                    class="w-[13px] h-[13px]"
                    :fill="isFavorite(link.to) ? 'currentColor' : 'none'"
                    stroke="currentColor"
                    stroke-width="1.6"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linejoin="round" d="M12 3l2.6 5.5 6 .8-4.4 4.2 1.1 6-5.3-3-5.3 3 1.1-6L3.4 9.3l6-.8z" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- ── Collapsed rail ───────────────────────────────────────────── -->
    <div v-else class="flex-1 min-h-0 flex flex-col items-center gap-0.5 py-2.5 overflow-y-auto overflow-x-hidden">
      <button
        type="button"
        class="w-10 h-10 mb-1 rounded-xl flex items-center justify-center text-slate-400 dark:text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800/70 hover:text-slate-700 dark:hover:text-slate-200 transition"
        :title="$t('nav.search')"
        @click="openPalette"
      >
        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
        </svg>
      </button>

      <router-link
        v-for="link in favoriteLinks"
        :key="'rail-fav-' + link.to"
        :to="link.to"
        class="relative w-10 h-10 rounded-xl flex items-center justify-center transition-colors"
        :class="railClass(isActive(link.to))"
        :title="$t(link.labelKey)"
        @mouseenter="flyout = null"
        @click="onNavClick"
      >
        <span class="w-[18px] h-[18px]" v-html="safeIcon(link.icon)" />
      </router-link>

      <hr class="w-6 border-slate-200 dark:border-slate-800 my-1.5" />

      <button
        v-for="group in visibleGroups"
        :key="'rail-' + group.id"
        type="button"
        class="relative w-10 h-10 rounded-xl flex items-center justify-center transition-colors"
        :class="railClass(groupHasActive(group))"
        :title="$t(group.labelKey)"
        @mouseenter="openFlyout(group.id, $event)"
        @click="pinGroup(group.id)"
      >
        <span class="w-[18px] h-[18px]" v-html="safeIcon(group.icon)" />
        <span v-if="groupHasActive(group)" class="absolute left-1 top-1/2 -mt-2 w-[3px] h-4 rounded-sm bg-[#7C5CFC]" />
      </button>
    </div>

    <!-- ── Footer ───────────────────────────────────────────────────── -->
    <div
      class="shrink-0 border-t border-slate-200 dark:border-slate-800 px-3 py-2.5 flex items-center gap-2.5 overflow-hidden"
      :class="showLabels ? 'justify-start' : 'justify-center'"
    >
      <div
        class="w-8 h-8 rounded-full bg-[#7C5CFC] text-white text-[11px] font-bold uppercase flex items-center justify-center shrink-0"
      >
        {{ userInitials }}
      </div>
      <div v-if="showLabels" class="min-w-0 flex-1 flex items-center gap-2">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-1.5">
            <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">{{ auth.userName || '—' }}</p>
            <span
              v-if="auth.userRole"
              class="shrink-0 text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-[#7C5CFC]/10 dark:bg-[#7C5CFC]/20 text-[#6d3ff0] dark:text-violet-300"
            >
              {{ auth.userRole }}
            </span>
          </div>
          <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate">{{ auth.userEmail || '' }}</p>
        </div>
        <button
          type="button"
          class="shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 dark:text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-red-500 transition"
          :title="$t('nav.logout')"
          @click="doLogout"
        >
          <svg class="w-[15px] h-[15px]" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
            />
          </svg>
        </button>
      </div>
    </div>
  </aside>

  <!-- ── Flyout (collapsed only) ─────────────────────────────────────── -->
  <Teleport to="body">
    <div
      v-if="flyoutGroup"
      class="hidden lg:block fixed z-[60] w-60 p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl shadow-slate-900/10 dark:shadow-black/40"
      :style="{ top: flyoutTop + 'px', left: '70px' }"
      @mouseenter="flyout = flyoutGroup!.id"
      @mouseleave="flyout = null"
    >
      <div class="flex items-center justify-between gap-2 px-1.5 pb-2">
        <span class="text-[10px] font-bold uppercase tracking-[0.13em] text-slate-400 dark:text-slate-500">
          {{ $t(flyoutGroup.labelKey) }}
        </span>
        <button
          type="button"
          class="px-1.5 py-0.5 rounded-md text-[10px] font-semibold bg-[#7C5CFC]/10 dark:bg-[#7C5CFC]/20 text-[#6d3ff0] dark:text-violet-300"
          @click="pinGroup(flyoutGroup.id)"
        >
          {{ $t('nav.pinShort') }}
        </button>
      </div>
      <router-link
        v-for="link in flyoutGroup.links"
        :key="'fly-' + link.to"
        :to="link.to"
        class="flex items-center gap-2.5 h-9 px-2.5 rounded-lg text-[13px] font-medium transition-colors"
        :class="rowClass(link.to)"
        @click="flyout = null"
      >
        <span class="shrink-0 w-[17px] h-[17px]" v-html="safeIcon(link.icon)" />
        <span class="flex-1 min-w-0 truncate text-left">{{ $t(link.labelKey) }}</span>
      </router-link>
    </div>

    <!-- ── Command palette (Ctrl/⌘ + K) ──────────────────────────────── -->
    <div
      v-if="paletteOpen"
      class="fixed inset-0 z-[70] bg-slate-950/55 flex items-start justify-center pt-24 px-4"
      @click="paletteOpen = false"
    >
      <div
        class="w-full max-w-xl rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xl overflow-hidden"
        @click.stop
      >
        <div class="flex items-center gap-2.5 px-4 py-3.5 border-b border-slate-200 dark:border-slate-800">
          <svg
            class="w-[17px] h-[17px] text-slate-400 dark:text-slate-500"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
          </svg>
          <input
            ref="paletteInput"
            v-model="paletteQuery"
            type="text"
            :placeholder="$t('nav.searchPlaceholder')"
            class="flex-1 bg-transparent border-0 p-0 text-[15px] text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:ring-0"
            @keydown.down.prevent="movePalette(1)"
            @keydown.up.prevent="movePalette(-1)"
            @keydown.enter.prevent="commitPalette()"
          />
          <span class="text-[10px] text-slate-400 dark:text-slate-500">↑↓ · ↵ · esc</span>
        </div>
        <div class="max-h-80 overflow-y-auto p-1.5">
          <button
            v-for="(link, i) in paletteResults"
            :key="'pal-' + link.to"
            type="button"
            class="w-full flex items-center gap-2.5 h-10 px-2.5 rounded-lg text-[13.5px] transition-colors"
            :class="
              i === paletteIndex
                ? 'bg-[#7C5CFC]/10 dark:bg-[#7C5CFC]/20 text-[#6d3ff0] dark:text-violet-300 font-semibold'
                : 'text-slate-600 dark:text-slate-300 font-medium'
            "
            @mouseenter="paletteIndex = i"
            @click="goTo(link.to)"
          >
            <span class="shrink-0 w-[17px] h-[17px]" v-html="safeIcon(link.icon)" />
            <span class="flex-1 min-w-0 truncate text-left">{{ $t(link.labelKey) }}</span>
            <span class="shrink-0 text-[10px] text-slate-400 dark:text-slate-500">{{ $t(link.groupLabelKey) }}</span>
          </button>
          <p v-if="!paletteResults.length" class="px-3 py-4 text-[13px] text-slate-400 dark:text-slate-500">
            {{ $t('nav.noResults') }}
          </p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/authStore'
import { useDocumentIncrementorStore } from '@/stores/documentIncrementor'

interface SidebarLink {
  to: string
  labelKey: string
  icon: string
  permission?: string
  module?: string
  badge?: string | number
}

interface SidebarGroup {
  id: string
  labelKey: string
  icon: string
  links: SidebarLink[]
}

type FlatLink = SidebarLink & { groupLabelKey: string }

// SECURITY (L2): defense-in-depth around v-html on icon props.
// Today every icon is a static SVG literal compiled into the bundle,
// but if a future change ever sources `item.icon` from API/DB this
// guard prevents stored XSS. Render only strings that look like a
// plain <svg>…</svg> with no event handlers, scripts, or dangerous
// URI schemes; otherwise emit nothing.
function safeIcon(html: string | undefined): string {
  if (!html) return ''
  const trimmed = html.trim()
  if (!/^<svg[\s>]/i.test(trimmed)) return ''
  if (/<script\b|on[a-z]+\s*=|javascript:/i.test(trimmed)) return ''
  return trimmed
}

const props = defineProps<{
  mobileOpen?: boolean
  pinned?: boolean
}>()

// `hover` and `closeMobile` are the existing contract with AppTopbar / AppLayout.
// `requestPin` is additive: the rail asks the layout to pin the sidebar open.
const emit = defineEmits(['hover', 'closeMobile', 'requestPin'])

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const auth = useAuthStore()
const diStore = useDocumentIncrementorStore()

const centralDomains = ['localhost', '127.0.0.1', import.meta.env.VITE_CENTRAL_DOMAIN].filter(Boolean)
const isCentralDomain = computed(() => centralDomains.includes(window.location.hostname))

onMounted(() => {
  if (!diStore.items.length) diStore.fetchAll()
  window.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

// ── Width / expansion ────────────────────────────────────────────────
// Hovering the rail no longer widens the sidebar: it opens a flyout.
// The sidebar is only wide when pinned (topbar toggle) or on mobile.
const hovered = ref(false)
const showLabels = computed(() => !!props.mobileOpen || !!props.pinned)

const sidebarClasses = computed(() => {
  if (props.mobileOpen) return 'w-72 translate-x-0'
  return [props.pinned ? 'w-72' : 'w-16', 'max-lg:-translate-x-full max-lg:w-72']
})

function onMouseEnter() {
  hovered.value = true
  emit('hover', true)
}
function onMouseLeave() {
  hovered.value = false
  flyout.value = null
  emit('hover', false)
}
function onNavClick() {
  flyout.value = null
  if (props.mobileOpen) emit('closeMobile')
}
function onHeaderClose() {
  if (props.mobileOpen) emit('closeMobile')
  else emit('requestPin', false)
}

// ── Persisted state (per user) ───────────────────────────────────────
const storageKey = computed(() => `o3.sidebar.${auth.user?.id ?? auth.userEmail ?? 'anon'}`)

const DEFAULT_OPEN = ['general', 'sales']
const DEFAULT_FAVORITES = ['/dashboard', '/ventes/documents']

const openGroups = ref<string[]>([...DEFAULT_OPEN])
const favorites = ref<string[]>([...DEFAULT_FAVORITES])

function loadState(): void {
  try {
    const raw = localStorage.getItem(storageKey.value)
    if (!raw) return
    const parsed = JSON.parse(raw) as { openGroups?: string[]; favorites?: string[] }
    if (Array.isArray(parsed.openGroups)) openGroups.value = parsed.openGroups
    if (Array.isArray(parsed.favorites)) favorites.value = parsed.favorites
  } catch {
    // ignore malformed storage
  }
}
function saveState(): void {
  try {
    localStorage.setItem(
      storageKey.value,
      JSON.stringify({ openGroups: openGroups.value, favorites: favorites.value }),
    )
  } catch {
    // storage may be unavailable (private mode, quota)
  }
}

loadState()
watch(storageKey, loadState)
watch([openGroups, favorites], saveState, { deep: true })

function isGroupOpen(id: string): boolean {
  return openGroups.value.includes(id)
}
function toggleGroup(id: string): void {
  openGroups.value = isGroupOpen(id) ? openGroups.value.filter((g) => g !== id) : [...openGroups.value, id]
}
function isFavorite(to: string): boolean {
  return favorites.value.includes(to)
}
function toggleFavorite(to: string): void {
  favorites.value = isFavorite(to) ? favorites.value.filter((f) => f !== to) : [...favorites.value, to]
}
function pinGroup(id: string): void {
  flyout.value = null
  if (!isGroupOpen(id)) openGroups.value = [...openGroups.value, id]
  emit('requestPin', true)
}

// ── Active route ─────────────────────────────────────────────────────
function isActive(to: string): boolean {
  const [toPath, toQuery] = to.split('?')
  if (toQuery) {
    const [key, val] = toQuery.split('=')
    return route.path === toPath && route.query[key] === val
  }
  return route.path === toPath
}
function groupHasActive(group: SidebarGroup): boolean {
  return group.links.some((l) => isActive(l.to))
}
function rowClass(to: string): string {
  return isActive(to)
    ? 'bg-[#7C5CFC]/10 dark:bg-[#7C5CFC]/20 text-[#6d3ff0] dark:text-violet-300 font-semibold shadow-[inset_2px_0_0_#7C5CFC]'
    : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/70 hover:text-slate-900 dark:hover:text-slate-100'
}
function railClass(active: boolean): string {
  return active
    ? 'bg-[#7C5CFC]/10 dark:bg-[#7C5CFC]/20 text-[#6d3ff0] dark:text-violet-300'
    : 'text-slate-400 dark:text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800/70 hover:text-slate-700 dark:hover:text-slate-200'
}

const userInitials = computed(() => {
  const n = auth.userName
  if (!n) return '?'
  const parts = n.trim().split(' ')
  return parts.length >= 2 ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase() : n.slice(0, 2).toUpperCase()
})

async function doLogout(): Promise<void> {
  await auth.logout()
  router.push('/login')
}
function goTo(to: string): void {
  paletteOpen.value = false
  flyout.value = null
  router.push(to)
  if (props.mobileOpen) emit('closeMobile')
}

// ── Flyout ───────────────────────────────────────────────────────────
const flyout = ref<string | null>(null)
const flyoutTop = ref(80)
const flyoutGroup = computed<SidebarGroup | null>(
  () => (flyout.value ? visibleGroups.value.find((g) => g.id === flyout.value) ?? null : null),
)

function openFlyout(id: string, event: MouseEvent): void {
  if (showLabels.value) return
  const el = event.currentTarget as HTMLElement
  const rect = el.getBoundingClientRect()
  const group = visibleGroups.value.find((g) => g.id === id)
  const height = 52 + (group?.links.length ?? 0) * 38
  flyoutTop.value = Math.max(12, Math.min(rect.top - 8, window.innerHeight - height - 12))
  flyout.value = id
}

// ── Command palette ──────────────────────────────────────────────────
const paletteOpen = ref(false)
const paletteQuery = ref('')
const paletteIndex = ref(0)
const paletteInput = ref<HTMLInputElement | null>(null)

function openPalette(): void {
  paletteOpen.value = true
  paletteQuery.value = ''
  paletteIndex.value = 0
  flyout.value = null
  nextTick(() => paletteInput.value?.focus())
}
function movePalette(delta: number): void {
  const max = paletteResults.value.length - 1
  if (max < 0) return
  paletteIndex.value = Math.min(Math.max(paletteIndex.value + delta, 0), max)
}
function commitPalette(): void {
  const link = paletteResults.value[paletteIndex.value]
  if (link) goTo(link.to)
}
function onKeydown(e: KeyboardEvent): void {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault()
    paletteOpen.value ? (paletteOpen.value = false) : openPalette()
    return
  }
  if (e.key === 'Escape') {
    paletteOpen.value = false
    flyout.value = null
  }
}
watch(paletteQuery, () => (paletteIndex.value = 0))

// ── Search ───────────────────────────────────────────────────────────
const query = ref('')
const normalize = (s: string): string =>
  s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')

function filterLinks(q: string): FlatLink[] {
  const needle = normalize(q.trim())
  if (!needle) return flatLinks.value
  return flatLinks.value.filter(
    (l) => normalize(t(l.labelKey)).includes(needle) || normalize(t(l.groupLabelKey)).includes(needle),
  )
}

const searchResults = computed(() => filterLinks(query.value))
const paletteResults = computed(() => filterLinks(paletteQuery.value).slice(0, 12))

// ── Icons ────────────────────────────────────────────────────────────
const icons = {
  scan: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v2m10-4h2a2 2 0 012 2v2M5 15v2a2 2 0 002 2h2m10 0h2a2 2 0 002-2v-2M4 12h16"/></svg>`,
  dashboard: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>`,
  products: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0v10l-8 4m0-14L4 17m8 4V10"/></svg>`,
  categories: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>`,
  brands: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>`,
  customers: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>`,
  suppliers: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>`,
  warehouses: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v9a1 1 0 001 1h4v-5h4v5h4a1 1 0 001-1v-9"/></svg>`,
  sales: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`,
  stock: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>`,
  listdoc: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h10"/></svg>`,
  users: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>`,
  code: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>`,
  docs: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>`,
  appsettings: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>`,
  roles: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>`,
  pos: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-2 8l-4-4H7a2 2 0 01-2-2V5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2l-4 4z"/></svg>`,
  terminal: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>`,
  reports: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`,
  audit: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`,
  promo: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>`,
  pricelists: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h18v4H3V3zm0 7h12v4H3v-4zm0 7h18v4H3v-4z"/></svg>`,
  labels: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>`,
  slides: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>`,
  tenants: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>`,
  plus: `<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>`,
}

const groupIcons = {
  general: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h6v6H4zM14 6h6v12h-6zM4 16h6v2H4z"/></svg>`,
  catalogue: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>`,
  partners: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`,
  sales: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`,
  purchases: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>`,
  stock: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>`,
  marketing: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>`,
  settings: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`,
  central: `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>`,
}

// ── Menus ────────────────────────────────────────────────────────────
// `permission` values must exist in RolePermissionSeeder::modules() — an
// unknown name simply hides the entry from every non-admin (hasPermission
// returns true for admins whatever happens).
//
// /dashboard, /reports and /marketing/promotions are deliberately ungated:
// no dedicated permission exists for them and they were visible to all roles
// before. The settings section uses settings.manage, the only seeded
// permission no non-admin role holds — it reproduces the previous adminOnly
// gate exactly.
const centralGroups: SidebarGroup[] = [
  {
    id: 'central',
    labelKey: 'nav.central',
    icon: groupIcons.central,
    links: [
      { to: '/central/tenants', labelKey: 'nav.tenants', icon: icons.tenants },
      { to: '/central/tenants/create', labelKey: 'nav.tenantCreate', icon: icons.plus },
    ],
  },
]

const tenantGroups: SidebarGroup[] = [
  {
    id: 'general',
    labelKey: 'nav.general',
    icon: groupIcons.general,
    links: [
      { to: '/dashboard', labelKey: 'nav.dashboard', icon: icons.dashboard },
      { to: '/reports', labelKey: 'nav.reports', icon: icons.reports },
      { to: '/pos', labelKey: 'nav.pos', icon: icons.pos, permission: 'pos.access', module: 'pos' },
    ],
  },
  {
    id: 'catalogue',
    labelKey: 'nav.catalogue',
    icon: groupIcons.catalogue,
    links: [
      { to: '/products', labelKey: 'nav.products', icon: icons.products, permission: 'products.view' },
      { to: '/categories', labelKey: 'nav.categories', icon: icons.categories, permission: 'categories.view' },
      { to: '/brands', labelKey: 'nav.brands', icon: icons.brands, permission: 'brands.view' },
      { to: '/price-lists', labelKey: 'nav.pricelists', icon: icons.pricelists, permission: 'products.view' },
      { to: '/storage/gallery', labelKey: 'nav.gallery', icon: icons.slides, permission: 'products.view' },
      { to: '/products/labels', labelKey: 'nav.labels', icon: icons.labels, permission: 'products.view' },
    ],
  },
  {
    id: 'partners',
    labelKey: 'nav.partners',
    icon: groupIcons.partners,
    links: [
      { to: '/customers', labelKey: 'nav.customers', icon: icons.customers, permission: 'third_partners.view' },
      { to: '/suppliers', labelKey: 'nav.suppliers', icon: icons.suppliers, permission: 'third_partners.view' },
      { to: '/warehouses', labelKey: 'nav.warehouses', icon: icons.warehouses, permission: 'warehouses.view' },
    ],
  },
  {
    id: 'sales',
    labelKey: 'nav.sales',
    icon: groupIcons.sales,
    links: [
      { to: '/ventes/documents', labelKey: 'nav.documentsVente', icon: icons.listdoc, permission: 'documents.view' },
    ],
  },
  {
    id: 'purchases',
    labelKey: 'nav.purchases',
    icon: groupIcons.purchases,
    links: [
      { to: '/achats/documents', labelKey: 'nav.documentsAchat', icon: icons.listdoc, permission: 'documents.view' },
      {
        to: '/achats/ocr-import',
        labelKey: 'nav.ocrImport',
        icon: icons.scan,
        permission: 'documents.create',
        module: 'ocr_import',
      },
    ],
  },
  {
    id: 'stock',
    labelKey: 'nav.stock',
    icon: groupIcons.stock,
    links: [
      { to: '/stock/documents', labelKey: 'nav.documentsStock', icon: icons.docs, permission: 'stock.view' },
      { to: '/stock/mouvements', labelKey: 'nav.mouvements', icon: icons.listdoc, permission: 'stock.view' },
    ],
  },
  {
    id: 'marketing',
    labelKey: 'nav.marketing',
    icon: groupIcons.marketing,
    links: [
      { to: '/marketing/promotions', labelKey: 'nav.promotions', icon: icons.promo },
    ],
  },
  {
    id: 'settings',
    labelKey: 'nav.settings',
    icon: groupIcons.settings,
    links: [
      { to: '/settings/users', labelKey: 'users.title', icon: icons.users, permission: 'settings.manage' },
      { to: '/settings/roles', labelKey: 'nav.roles', icon: icons.roles, permission: 'settings.manage' },
      {
        to: '/settings/structure-incrementors',
        labelKey: 'structureIncrementors.title',
        icon: icons.code,
        permission: 'settings.manage',
      },
      {
        to: '/settings/document-incrementors',
        labelKey: 'documentIncrementors.title',
        icon: icons.docs,
        permission: 'settings.manage',
      },
      { to: '/settings/app', labelKey: 'appSettings.title', icon: icons.appsettings, permission: 'settings.manage' },
      { to: '/settings/imports', labelKey: 'imports.title', icon: icons.stock, permission: 'settings.manage' },
      {
        to: '/settings/pos-terminals',
        labelKey: 'nav.posTerminals',
        icon: icons.terminal,
        permission: 'settings.manage',
        module: 'pos',
      },
      {
        to: '/settings/pos-sessions',
        labelKey: 'nav.posSessions',
        icon: icons.listdoc,
        permission: 'settings.manage',
        module: 'pos',
      },
      { to: '/settings/activity-log', labelKey: 'nav.audit', icon: icons.audit, permission: 'settings.manage' },
    ],
  },
]

// ── Filtering: permission + module ───────────────────────────────────
function canSee(link: SidebarLink): boolean {
  if (link.module && !auth.hasModule(link.module)) return false
  if (!link.permission) return true
  return auth.hasPermission(link.permission)
}

const visibleGroups = computed<SidebarGroup[]>(() => {
  const source = isCentralDomain.value ? centralGroups : tenantGroups
  return source
    .map((group) => ({ ...group, links: group.links.filter(canSee) }))
    .filter((group) => group.links.length > 0)
})

const flatLinks = computed<FlatLink[]>(() =>
  visibleGroups.value.flatMap((group) => group.links.map((link) => ({ ...link, groupLabelKey: group.labelKey }))),
)

const favoriteLinks = computed<FlatLink[]>(() =>
  favorites.value
    .map((to) => flatLinks.value.find((link) => link.to === to))
    .filter((link): link is FlatLink => !!link),
)

// A search hit inside a collapsed group should be reachable: while filtering,
// every group renders open.
watch(query, (q) => {
  if (q) openGroups.value = Array.from(new Set([...openGroups.value, ...visibleGroups.value.map((g) => g.id)]))
})
</script>
