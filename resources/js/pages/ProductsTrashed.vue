<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div class="min-w-0">
        <h2 class="text-[26px] sm:text-[30px] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white truncate">
          {{ $t('products.trashedTitle') }}
        </h2>
        <p class="text-sm text-[#8A8F9C] dark:text-gray-400 mt-1 hidden sm:block">
          {{ $t('products.trashedSubtitle') }}
        </p>
      </div>
      <router-link
        to="/products"
        class="flex items-center gap-2 px-3.5 sm:px-[18px] py-2.5 border border-[#E1E3E9] dark:border-gray-600 text-gray-900 dark:text-gray-300 text-sm font-semibold rounded-[11px] bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition shrink-0"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        <span class="hidden sm:inline">{{ $t('products.backToProducts') }}</span>
      </router-link>
    </div>

    <!-- Search -->
    <div class="flex items-stretch gap-2.5 bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-[14px] p-3">
      <div class="relative flex-1 min-w-[220px]">
        <svg class="w-[15px] h-[15px] absolute left-3.5 top-1/2 -translate-y-1/2 text-[#B0B4BE]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 20 20">
          <circle cx="9" cy="9" r="6" /><line x1="14" y1="14" x2="18" y2="18" />
        </svg>
        <input
          :aria-label="$t('products.search')"
          v-model="search"
          type="text"
          :placeholder="$t('products.search')"
          class="w-full pl-9 pr-3.5 py-2.5 text-sm rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
        />
      </div>
    </div>

    <!-- Table -->
    <BaseTable :columns="columns" :rows="items" :empty-text="$t('products.trashedEmpty')">
      <template #cell-p_title="{ row }">
        <span class="font-medium text-gray-900 dark:text-white">{{ row.p_title }}</span>
      </template>
      <template #cell-category="{ row }">
        {{ row.category?.name ?? '—' }}
      </template>
      <template #cell-brand="{ row }">
        {{ row.brand?.name ?? '—' }}
      </template>
      <template #cell-deleted_at="{ row }">
        {{ fmtDate(row.deleted_at) }}
      </template>
      <template #actions="{ row }">
        <div class="flex items-center justify-end gap-2">
          <button
            class="p-1.5 rounded-lg text-[#7C5CFC] hover:bg-[#F1ECFC] transition"
            :title="$t('products.restore')"
            @click="confirmRestore(row)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 10a8 8 0 1 1 2.343 5.657M3 10V4m0 6h6" />
            </svg>
          </button>
        </div>
      </template>
    </BaseTable>

    <BasePagination
      v-if="meta.total > 0"
      :current-page="meta.current_page"
      :last-page="meta.last_page"
      :total="meta.total"
      :per-page="meta.per_page"
      @change="onPageChange"
    />

    <!-- Restore confirmation -->
    <BaseModal v-model="showRestore" :title="$t('products.restoreTitle')" size="sm">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ $t('products.restoreConfirm') }}
        <span class="font-semibold">{{ restoreTarget?.p_title }}</span> ?
      </p>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition"
          @click="showRestore = false"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white rounded-lg transition disabled:opacity-60"
          :disabled="restoring"
          @click="doRestore"
        >
          {{ restoring ? $t('products.restoring') : $t('products.restore') }}
        </button>
      </template>
    </BaseModal>

    <BaseNotification ref="toast" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useProductStore } from '@/stores/product'
import { useFormat } from '@/composables/useFormat'
import BaseTable from '@/components/BaseTable.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'

const { t } = useI18n()
const { date: fmtDate } = useFormat()
const store = useProductStore()

const { trashedItems: items, trashedMeta: meta } = storeToRefs(store)

const toast = ref<InstanceType<typeof BaseNotification> | null>(null)
const search = ref('')
let searchTimer: ReturnType<typeof setTimeout> | undefined

const columns = computed(() => [
  { key: 'p_code', label: t('common.code') },
  { key: 'p_title', label: t('common.name') },
  { key: 'p_sku', label: t('products.sku') },
  { key: 'category', label: t('products.category') },
  { key: 'brand', label: t('products.brand') },
  { key: 'deleted_at', label: t('products.deletedAt') },
])

function loadPage(page = 1) {
  store.trashedParams.page = page
  store.trashedParams.search = search.value.trim() || null
  store.fetchTrashedPage(page)
}

function onPageChange(page: number) {
  loadPage(page)
}

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadPage(1), 350)
})

const showRestore = ref(false)
const restoreTarget = ref<{ id: number; p_title: string } | null>(null)
const restoring = ref(false)

function confirmRestore(row: { id: number; p_title: string }) {
  restoreTarget.value = row
  showRestore.value = true
}

async function doRestore() {
  if (!restoreTarget.value) return
  restoring.value = true
  try {
    await store.restore(restoreTarget.value.id)
    toast.value?.notify(t('products.restored'), 'success')
    showRestore.value = false
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.value?.notify(e.response?.data?.message ?? t('products.restoreFailed'), 'error')
  } finally {
    restoring.value = false
  }
}

onMounted(() => loadPage(1))
</script>
