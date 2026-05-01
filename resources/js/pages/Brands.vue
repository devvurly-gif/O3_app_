<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $t('brands.title') }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $t('brands.subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition"
          @click="openCreate"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {{ $t('brands.add') }}
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
      <input
        v-model="search"
        type="text"
        :placeholder="$t('brands.search')"
        class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64"
      />
      <select
        v-model="statusFilter"
        class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
          class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
          :class="value ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
        >
          {{ value ? $t('common.active') : $t('common.inactive') }}
        </span>
      </template>
      <template #actions="{ row }">
        <div class="flex items-center justify-end gap-2">
          <button
            class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition"
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
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >{{ $t('common.name') }} <span class="text-red-500">*</span></label
          >
          <input
            v-model="form.br_title"
            type="text"
            required
            :placeholder="$t('brands.namePlaceholder')"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
        <div class="flex items-center gap-2">
          <input
            id="brand-status"
            v-model="form.br_status"
            type="checkbox"
            class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
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
          class="px-4 py-2 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition disabled:opacity-60"
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

const { t } = useI18n()
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
