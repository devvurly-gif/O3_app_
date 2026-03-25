<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $t('users.title') }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $t('users.subtitle') }}</p>
      </div>
      <button
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition"
        @click="openCreate"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        {{ $t('users.add') }}
      </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
      <input
        v-model="search"
        type="text"
        :placeholder="$t('users.search')"
        class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64"
      />
      <select
        v-model="roleFilter"
        class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
      >
        <option value="">{{ $t('users.allRoles') }}</option>
        <option v-for="r in availableRoles" :key="r.id" :value="r.name">{{ r.display_name }}</option>
      </select>
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
    <BaseTable :columns="columns" :rows="pagedRows" :empty-text="$t('users.notFound')">
      <template #cell-user_code="{ value }">
        <span class="font-mono text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">{{ value }}</span>
      </template>
      <template #cell-role="{ value }">
        <span
          class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
          :class="{
            'bg-red-100 text-red-700': value === 'admin',
            'bg-blue-100 text-blue-700': value === 'manager',
            'bg-teal-100 text-teal-700': value === 'cashier',
            'bg-amber-100 text-amber-700': value === 'warehouse',
          }"
        >
          {{ value }}
        </span>
      </template>
      <template #cell-is_active="{ value }">
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
    <BaseModal v-model="showModal" :title="editTarget ? $t('users.editTitle') : $t('users.addTitle')" size="md">
      <form class="space-y-4" @submit.prevent="submit">
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >{{ $t('common.name') }} <span class="text-red-500">*</span></label
            >
            <input
              v-model="form.name"
              type="text"
              required
              :placeholder="$t('users.namePlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >{{ $t('common.email') }} <span class="text-red-500">*</span></label
            >
            <input
              v-model="form.email"
              type="email"
              required
              placeholder="user@example.com"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('auth.password') }} <span v-if="!editTarget" class="text-red-500">*</span>
              <span v-else class="text-gray-400 dark:text-gray-500 font-normal">{{ $t('users.keepPassword') }}</span>
            </label>
            <input
              v-model="form.password"
              type="password"
              :required="!editTarget"
              placeholder="••••••••"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >{{ $t('users.role') }} <span class="text-red-500">*</span></label
            >
            <select
              v-model="form.role_id"
              required
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option :value="null" disabled>-- Sélectionner --</option>
              <option v-for="r in availableRoles" :key="r.id" :value="r.id">{{ r.display_name }}</option>
            </select>
          </div>
          <div class="flex items-center gap-2 pt-6">
            <input
              id="user-active"
              v-model="form.is_active"
              type="checkbox"
              class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
            />
            <label for="user-active" class="text-sm text-gray-700 dark:text-gray-300">{{ $t('common.active') }}</label>
          </div>
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
    <BaseModal v-model="showDelete" :title="$t('users.deleteTitle')" size="sm">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ $t('users.deleteConfirm') }} <span class="font-semibold">{{ deleteTarget?.name }}</span
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

  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '@/stores/user'
import { useRoleStore } from '@/stores/role'
import { useAuthStore } from '@/stores/authStore'
import BaseTable from '@/components/BaseTable.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseModal from '@/components/BaseModal.vue'
import { useToastStore } from '@/stores/toastStore'

const { t } = useI18n()
const store = useUserStore()
const roleStore = useRoleStore()
const auth = useAuthStore()

const availableRoles = computed(() =>
  roleStore.items.filter((r) => r.name !== 'cashier' || auth.hasModule('pos')),
)
const { items } = storeToRefs(store)

const search = ref('')
const roleFilter = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const perPage = 15
const toast = useToastStore()

const showModal = ref(false)
const showDelete = ref(false)
const saving = ref(false)
const deleting = ref(false)
const editTarget = ref(null)
const deleteTarget = ref(null)

const emptyForm = () => ({ name: '', email: '', password: '', role_id: null as number | null, is_active: true })
const form = reactive(emptyForm())

const columns = computed(() => [
  { key: 'user_code', label: t('common.code') },
  { key: 'name', label: t('common.name') },
  { key: 'email', label: t('common.email') },
  { key: 'role', label: t('users.role') },
  { key: 'is_active', label: t('common.status') },
])

const filteredRows = computed(() => {
  const q = search.value.trim().toLowerCase()
  const r = roleFilter.value
  const s = statusFilter.value
  return items.value.filter((u) => {
    const matchSearch =
      !q ||
      u.name?.toLowerCase().includes(q) ||
      u.email?.toLowerCase().includes(q) ||
      u.user_code?.toLowerCase().includes(q)
    const matchRole = !r || u.role === r || String(u.role_id) === r
    const matchStatus = s === '' || (s === '1' ? u.is_active : !u.is_active)
    return matchSearch && matchRole && matchStatus
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / perPage)))
const pagedRows = computed(() =>
  filteredRows.value.slice((currentPage.value - 1) * perPage, currentPage.value * perPage),
)

watch([search, roleFilter, statusFilter], () => {
  currentPage.value = 1
})

function openCreate() {
  editTarget.value = null
  Object.assign(form, emptyForm())
  showModal.value = true
}

function openEdit(row) {
  editTarget.value = row
  Object.assign(form, { name: row.name, email: row.email, password: '', role_id: row.role_id, is_active: row.is_active })
  showModal.value = true
}

async function submit() {
  if (!form.name.trim() || !form.email.trim()) return
  saving.value = true
  const payload = { ...form }
  if (editTarget.value && !payload.password) delete payload.password
  try {
    if (editTarget.value) {
      await store.update(editTarget.value.id, payload)
      toast.success(t('users.updated'))
    } else {
      await store.create(payload)
      toast.success(t('users.created'))
    }
    showModal.value = false
  } catch { /* Axios interceptor shows toast */ }
  saving.value = false
}

function confirmDelete(row: Record<string, unknown>) {
  deleteTarget.value = row
  showDelete.value = true
}

async function doDelete() {
  deleting.value = true
  try {
    await store.remove(deleteTarget.value.id)
    toast.success(t('users.deleted'))
    showDelete.value = false
  } catch { /* Axios interceptor shows toast */ }
  deleting.value = false
}

onMounted(() => {
  if (!items.value.length) store.fetchAll()
  if (!roleStore.items.length) roleStore.fetchAll()
})
</script>
