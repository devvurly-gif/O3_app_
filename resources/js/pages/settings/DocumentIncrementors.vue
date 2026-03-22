<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $t('documentIncrementors.title') }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $t('documentIncrementors.subtitle') }}</p>
      </div>
      <button
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition"
        @click="openCreate"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        {{ $t('documentIncrementors.add') }}
      </button>
    </div>

    <!-- Table -->
    <BaseTable :columns="columns" :rows="items" :empty-text="$t('documentIncrementors.notFound')">
      <template #cell-di_title="{ row }">
        <span>{{
          row.di_model && te('documentIncrementors.models.' + row.di_model)
            ? $t('documentIncrementors.models.' + row.di_model)
            : row.di_title
        }}</span>
      </template>
      <template #cell-template="{ value }">
        <span class="font-mono text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded">{{ value }}</span>
      </template>
      <template #cell-nextTrick="{ value }">
        <span class="font-mono text-sm text-gray-700 dark:text-gray-300">{{ value }}</span>
      </template>
      <template #cell-status="{ value }">
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

    <!-- Create / Edit Modal -->
    <BaseModal
      v-model="showModal"
      :title="editTarget ? $t('documentIncrementors.editTitle') : $t('documentIncrementors.addTitle')"
      size="md"
    >
      <form class="space-y-4" @submit.prevent="submit">
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >{{ $t('common.title') }} <span class="text-red-500">*</span></label
            >
            <input
              v-model="form.di_title"
              type="text"
              :placeholder="$t('documentIncrementors.titlePlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.model') }}</label>
            <input
              v-model="form.di_model"
              type="text"
              :placeholder="$t('documentIncrementors.modelPlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.domain') }}</label>
            <input
              v-model="form.di_domain"
              type="text"
              :placeholder="$t('documentIncrementors.domainPlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.template') }}</label>
            <input
              v-model="form.template"
              type="text"
              :placeholder="$t('documentIncrementors.templatePlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $t('documentIncrementors.placeholders') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{
              $t('documentIncrementors.operatorSens')
            }}</label>
            <input
              v-model="form.operatorSens"
              type="text"
              :placeholder="$t('documentIncrementors.operatorSensPlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.nextNum') }}</label>
            <input
              v-model.number="form.nextTrick"
              type="number"
              min="1"
              placeholder="1"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div class="flex items-center gap-2 pt-6">
            <input
              id="di-status"
              v-model="form.status"
              type="checkbox"
              class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
            />
            <label for="di-status" class="text-sm text-gray-700 dark:text-gray-300">{{ $t('common.active') }}</label>
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
    <BaseModal v-model="showDelete" :title="$t('documentIncrementors.deleteTitle')" size="sm">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ $t('documentIncrementors.deleteConfirm') }} <span class="font-semibold">{{ deleteTarget?.di_title }}</span
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
import { ref, reactive, computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useDocumentIncrementorStore } from '@/stores/documentIncrementor'
import BaseTable from '@/components/BaseTable.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'

const { t, te } = useI18n()
const store = useDocumentIncrementorStore()
const { items } = storeToRefs(store)

const toast = ref(null)
const showModal = ref(false)
const showDelete = ref(false)
const saving = ref(false)
const deleting = ref(false)
const editTarget = ref(null)
const deleteTarget = ref(null)

const emptyForm = () => ({
  di_title: '',
  di_model: '',
  di_domain: '',
  template: '',
  nextTrick: 1,
  status: true,
  operatorSens: 'in' as 'in' | 'out',
})
const form = reactive(emptyForm())

const columns = computed(() => [
  { key: 'di_title', label: t('common.title') },
  { key: 'di_model', label: t('common.model') },
  { key: 'di_domain', label: t('common.domain') },
  { key: 'template', label: t('common.template') },
  { key: 'nextTrick', label: t('common.nextNum') },
  { key: 'status', label: t('common.status') },
])

function openCreate() {
  editTarget.value = null
  Object.assign(form, emptyForm())
  showModal.value = true
}

function openEdit(row) {
  editTarget.value = row
  Object.assign(form, {
    di_title: row.di_title,
    di_model: row.di_model ?? '',
    di_domain: row.di_domain ?? '',
    template: row.template ?? '',
    nextTrick: row.nextTrick ?? 1,
    status: row.status,
    operatorSens: row.operatorSens ?? '',
  })
  showModal.value = true
}

async function submit() {
  if (!form.di_title.trim()) return
  saving.value = true
  try {
    if (editTarget.value) {
      await store.update(editTarget.value.id, form)
      toast.value?.notify(t('documentIncrementors.updated'), 'success')
    } else {
      await store.create(form)
      toast.value?.notify(t('documentIncrementors.created'), 'success')
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
    toast.value?.notify(t('documentIncrementors.deleted'), 'success')
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
