<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h2 class="text-[26px] sm:text-[30px] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white">{{ $t('brands.title') }}</h2>
        <p class="text-sm text-[#8A8F9C] dark:text-gray-400 mt-1">{{ $t('brands.subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          class="flex items-center gap-2 px-4 sm:px-5 py-2.5 bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white text-sm font-bold rounded-[11px] shadow-[0_8px_20px_-8px_rgba(124,92,252,0.6)] transition"
          @click="openCreate"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {{ $t('brands.add') }}
        </button>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-3 gap-3 sm:gap-4">
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
        <div class="w-9 h-9 rounded-[10px] bg-[#EFF1F5] dark:bg-gray-700 flex items-center justify-center mb-3.5">
          <svg class="w-[17px] h-[17px]" fill="none" stroke="#5B6070" stroke-width="1.6" viewBox="0 0 20 20">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
          </svg>
        </div>
        <div class="text-2xl sm:text-[26px] font-extrabold text-gray-900 dark:text-white">{{ items.length }}</div>
        <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400 mt-0.5">{{ $t('brands.title') }}</div>
      </div>
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
        <div class="w-9 h-9 rounded-[10px] bg-[#EAF7F0] dark:bg-[#2FA86B]/20 flex items-center justify-center mb-3.5">
          <svg class="w-[17px] h-[17px]" fill="none" stroke="#2FA86B" stroke-width="1.8" viewBox="0 0 20 20">
            <path d="M4 12l4 4 8-9" />
          </svg>
        </div>
        <div class="text-2xl sm:text-[26px] font-extrabold text-gray-900 dark:text-white">{{ statActive }}</div>
        <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400 mt-0.5">{{ $t('common.active') }}</div>
      </div>
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
        <div class="w-9 h-9 rounded-[10px] bg-[#F0F1F4] dark:bg-gray-700 flex items-center justify-center mb-3.5">
          <svg class="w-[17px] h-[17px]" fill="none" stroke="#7A7F8C" stroke-width="1.8" viewBox="0 0 20 20">
            <circle cx="10" cy="10" r="7" /><line x1="7" y1="7" x2="13" y2="13" />
          </svg>
        </div>
        <div class="text-2xl sm:text-[26px] font-extrabold text-gray-900 dark:text-white">{{ items.length - statActive }}</div>
        <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400 mt-0.5">{{ $t('common.inactive') }}</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-[14px] p-3">
      <input
        :aria-label="$t('brands.search')"
        v-model="search"
        type="text"
        :placeholder="$t('brands.search')"
        class="px-3.5 py-2.5 text-input rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent w-64"
      />
      <select
        :aria-label="$t('a11y.filterStatus')"
        v-model="statusFilter"
        class="px-3.5 py-2.5 text-[13px] font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-[#4A4F5B] dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
      >
        <option value="">{{ $t('common.allStatus') }}</option>
        <option value="1">{{ $t('common.active') }}</option>
        <option value="0">{{ $t('common.inactive') }}</option>
      </select>
    </div>

    <!-- Table -->
    <BaseTable :columns="columns" :rows="pagedRows" :empty-text="$t('brands.notFound')">
      <template #cell-br_code="{ value }">
        <span class="font-mono text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">{{ value }}</span>
      </template>
      <template #cell-br_status="{ value }">
        <span
          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold"
          :class="value ? 'bg-[#E5F7ED] text-[#1F8A50]' : 'bg-[#F0F1F4] text-[#7A7F8C]'"
        >
          {{ value ? $t('common.active') : $t('common.inactive') }}
        </span>
      </template>
      <template #cell-created_at="{ value }">
        <span class="text-sm">{{ fmtDate(value) }}</span>
      </template>
      <template #actions="{ row }">
        <div class="flex items-center justify-end gap-2">
          <button
            class="p-1.5 rounded-lg text-[#7C5CFC] hover:bg-[#F1ECFC] transition"
            :title="$t('common.update')"
            @click="openEdit(row)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
              />
            </svg>
          </button>
          <button
            class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition"
            :title="$t('common.delete')"
            @click="confirmDelete(row)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
              />
            </svg>
          </button>
        </div>
      </template>
    </BaseTable>

    <!-- Pagination -->
    <BasePagination
      v-if="totalPages > 1"
      :current-page="currentPage"
      :last-page="totalPages"
      :total="filteredRows.length"
      :per-page="perPage"
      @change="currentPage = $event"
    />

    <!-- Create / Edit Modal -->
    <BaseModal v-model="showModal" :title="editTarget ? $t('brands.editTitle') : $t('brands.addTitle')" size="sm">
      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label for="brands-br-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >{{ $t('common.name') }} <span class="text-red-500">*</span></label
          >
          <input
            id="brands-br-title"
            v-model="form.br_title"
            type="text"
            required
            :placeholder="$t('brands.namePlaceholder')"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
          />
        </div>
        <div class="flex items-center gap-2">
          <input
            id="brand-status"
            v-model="form.br_status"
            type="checkbox"
            class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-[#7C5CFC] focus:ring-[#7C5CFC]"
          />
          <label for="brand-status" class="text-sm text-gray-700 dark:text-gray-300">{{ $t('common.active') }}</label>
        </div>

        <!-- Publier dans la boutique en ligne — visible uniquement si module ecom activé -->
        <div
          v-if="ecomEnabled"
          class="flex items-start gap-2 p-3 rounded-lg bg-indigo-50/50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800"
        >
          <input
            id="brand-ecom"
            v-model="form.is_ecom"
            type="checkbox"
            class="mt-0.5 w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
          />
          <label for="brand-ecom" class="flex-1 cursor-pointer">
            <span class="text-sm font-medium text-indigo-900 dark:text-indigo-200">
              Publier dans la boutique en ligne
            </span>
            <p class="text-[11px] text-indigo-700/70 dark:text-indigo-300/70 mt-0.5">
              Si décoché, aucun produit de cette marque n'apparaît dans le storefront.
            </p>
          </label>
        </div>
      </form>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition"
          @click="showModal = false"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white rounded-lg transition disabled:opacity-60"
          :disabled="saving"
          @click="submit"
        >
          {{ saving ? $t('common.saving') : editTarget ? $t('common.update') : $t('common.create') }}
        </button>
      </template>
    </BaseModal>

    <!-- Delete Modal -->
    <BaseModal v-model="showDelete" :title="$t('brands.deleteTitle')" size="sm">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ $t('brands.deleteConfirm') }} <span class="font-semibold">{{ deleteTarget?.br_title }}</span
        >? {{ $t('common.cannotUndo') }}
      </p>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition"
          @click="showDelete = false"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg transition disabled:opacity-60"
          :disabled="deleting"
          @click="doDelete"
        >
          {{ deleting ? $t('common.deleting') : $t('common.delete') }}
        </button>
      </template>
    </BaseModal>

    <BaseNotification ref="toast" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useBrandStore } from '@/stores/brand'
import { useAuthStore } from '@/stores/authStore'
import BaseTable from '@/components/BaseTable.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'
import { useFormat } from '@/composables/useFormat'

const { t } = useI18n()
const { date: fmtDate } = useFormat()
const store = useBrandStore()
const auth = useAuthStore()
const { items } = storeToRefs(store)

// Toggle "Publier dans la boutique" only matters when the tenant has the
// e-commerce module activated (driven by central tenants.ecom_enabled).
const ecomEnabled = computed(() => auth.hasModule('ecom'))

// ── UI state ──────────────────────────────────────────────────────────────
const search = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const perPage = 15
const toast = ref(null)

const showModal = ref(false)
const showDelete = ref(false)
const saving = ref(false)
const deleting = ref(false)
const editTarget = ref(null)
const deleteTarget = ref(null)

const form = reactive({ br_title: '', br_status: true, is_ecom: true })

const columns = computed(() => [
  { key: 'br_code', label: t('common.code') },
  { key: 'br_title', label: t('common.name') },
  { key: 'br_status', label: t('common.status') },
  { key: 'created_at', label: t('common.created') },
])

const statActive = computed(() => items.value.filter((r: any) => r.br_status).length)

// ── Client-side filter + paginate ─────────────────────────────────────────
const filteredRows = computed(() => {
  const q = search.value.trim().toLowerCase()
  const s = statusFilter.value
  return items.value.filter((r) => {
    const matchSearch = !q || r.br_title?.toLowerCase().includes(q) || r.br_code?.toLowerCase().includes(q)
    const matchStatus = s === '' || (s === '1' ? r.br_status : !r.br_status)
    return matchSearch && matchStatus
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / perPage)))
const pagedRows = computed(() =>
  filteredRows.value.slice((currentPage.value - 1) * perPage, currentPage.value * perPage),
)

watch([search, statusFilter], () => {
  currentPage.value = 1
})

// ── CRUD ─────────────────────────────────────────────────────────────────
function openCreate() {
  editTarget.value = null
  form.br_title = ''
  form.br_status = true
  form.is_ecom = true
  showModal.value = true
}

function openEdit(row) {
  editTarget.value = row
  form.br_title = row.br_title
  form.br_status = row.br_status
  form.is_ecom = row.is_ecom !== undefined ? row.is_ecom : true
  showModal.value = true
}

async function submit() {
  if (!form.br_title.trim()) return
  saving.value = true
  try {
    if (editTarget.value) {
      await store.update(editTarget.value.id, form)
      toast.value?.notify(t('brands.updated'), 'success')
    } else {
      await store.create(form)
      toast.value?.notify(t('brands.created'), 'success')
    }
    showModal.value = false
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.value?.notify(e.response?.data?.message ?? t('common.failedSave'), 'error')
  } finally {
    saving.value = false
  }
}

function confirmDelete(row) {
  deleteTarget.value = row
  showDelete.value = true
}

async function doDelete() {
  deleting.value = true
  try {
    await store.remove(deleteTarget.value.id)
    toast.value?.notify(t('brands.deleted'), 'success')
    showDelete.value = false
  } catch {
    toast.value?.notify(t('common.failedDelete'), 'error')
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  if (!items.value.length) store.fetchAll()
})
</script>
