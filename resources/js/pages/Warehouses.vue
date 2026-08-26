<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h2 class="text-[26px] sm:text-[30px] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white">{{ $t('warehouses.title') }}</h2>
        <p class="text-sm text-[#8A8F9C] dark:text-gray-400 mt-1">{{ $t('warehouses.subtitle') }}</p>
      </div>
      <button
        class="flex items-center gap-2 px-4 sm:px-5 py-2.5 bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white text-sm font-bold rounded-[11px] shadow-[0_8px_20px_-8px_rgba(124,92,252,0.6)] transition"
        @click="openCreate"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        {{ $t('warehouses.add') }}
      </button>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-3 gap-3 sm:gap-4">
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
        <div class="w-9 h-9 rounded-[10px] bg-[#EFF1F5] dark:bg-gray-700 flex items-center justify-center mb-3.5">
          <svg class="w-[17px] h-[17px]" fill="none" stroke="#5B6070" stroke-width="1.6" viewBox="0 0 20 20">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10l7-7 7 7M4.5 8.5v7a1 1 0 001 1h3v-4h3v4h3a1 1 0 001-1v-7" />
          </svg>
        </div>
        <div class="text-2xl sm:text-[26px] font-extrabold text-gray-900 dark:text-white">{{ items.length }}</div>
        <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400 mt-0.5">{{ $t('warehouses.title') }}</div>
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
        :aria-label="$t('warehouses.search')"
        v-model="search"
        type="text"
        :placeholder="$t('warehouses.search')"
        class="px-3.5 py-2.5 text-input rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent w-64"
      />
      <select
        :aria-label="$t('a11y.filterStatus')"
        v-model="statusFilter"
        class="px-3.5 py-2.5 text-input font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-[#4A4F5B] dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
      >
        <option value="">{{ $t('common.allStatus') }}</option>
        <option value="1">{{ $t('common.active') }}</option>
        <option value="0">{{ $t('common.inactive') }}</option>
      </select>
    </div>

    <!-- Table -->
    <BaseTable :columns="columns" :rows="pagedRows" :empty-text="$t('warehouses.notFound')">
      <template #cell-wh_code="{ value }">
        <span class="font-mono text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">{{ value }}</span>
      </template>
      <template #cell-wh_status="{ value }">
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
            class="p-1.5 rounded-lg text-violet-600 hover:bg-violet-50 transition"
            title="Voir le stock"
            @click="openStockModal(row)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
              />
            </svg>
          </button>
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
    <BaseModal
      v-model="showModal"
      :title="editTarget ? $t('warehouses.editTitle') : $t('warehouses.addTitle')"
      size="sm"
    >
      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label for="warehouses-wh-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >{{ $t('common.name') }} <span class="text-red-500">*</span></label
          >
          <input
            id="warehouses-wh-title"
            v-model="form.wh_title"
            type="text"
            required
            :placeholder="$t('warehouses.namePlaceholder')"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
          />
        </div>
        <div class="flex items-center gap-2">
          <input
            id="wh-status"
            v-model="form.wh_status"
            type="checkbox"
            class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-[#7C5CFC] focus:ring-[#7C5CFC]"
          />
          <label for="wh-status" class="text-sm text-gray-700 dark:text-gray-300">{{ $t('common.active') }}</label>
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
    <BaseModal v-model="showDelete" :title="$t('warehouses.deleteTitle')" size="sm">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ $t('warehouses.deleteConfirm') }} <span class="font-semibold">{{ deleteTarget?.wh_title }}</span
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

    <!-- Stock Detail Modal -->
    <BaseModal v-model="showStockModal" :title="'Stock — ' + (stockWarehouse?.wh_title ?? '')" size="xl">
      <div class="space-y-4">
        <!-- Search -->
        <div class="relative">
          <svg
            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <circle cx="11" cy="11" r="8" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35" />
          </svg>
          <input
            :aria-label="$t('a11y.searchProduct')"
            v-model="stockSearch"
            type="text"
            placeholder="Rechercher un produit..."
            class="w-full pl-10 pr-4 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
          />
        </div>

        <!-- Loading -->
        <div v-if="stockLoading" class="flex items-center justify-center py-12">
          <svg class="w-6 h-6 animate-spin text-violet-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>

        <!-- Table -->
        <div v-else-if="filteredStockItems.length" class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 uppercase text-xs">
              <tr>
                <th class="text-left px-4 py-3 font-semibold">Code</th>
                <th class="text-left px-4 py-3 font-semibold">Produit</th>
                <th class="text-left px-4 py-3 font-semibold">SKU</th>
                <th class="text-right px-4 py-3 font-semibold">Stock</th>
                <th class="text-right px-4 py-3 font-semibold">Coût moy.</th>
                <th class="text-right px-4 py-3 font-semibold">Valeur</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="item in filteredStockItems" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <td class="px-4 py-3">
                  <span class="font-mono text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">{{
                    item.product?.p_code
                  }}</span>
                </td>
                <td class="px-4 py-3">
                  <div class="font-medium text-gray-800 dark:text-gray-200">{{ item.product?.p_title }}</div>
                </td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs font-mono">{{ item.product?.p_sku ?? '—' }}</td>
                <td class="px-4 py-3 text-right">
                  <span
                    class="font-mono font-semibold text-base"
                    :class="
                      Number(item.stockLevel) > 0
                        ? 'text-emerald-600'
                        : Number(item.stockLevel) < 0
                          ? 'text-red-600'
                          : 'text-gray-400'
                    "
                  >
                    {{ Number(item.stockLevel).toFixed(2) }}
                  </span>
                  <span class="text-xs text-gray-400 dark:text-gray-500 ml-0.5">{{ item.product?.p_unit ?? '' }}</span>
                </td>
                <td class="px-4 py-3 text-right font-mono text-gray-600 dark:text-gray-400">
                  {{ Number(item.wh_average ?? 0).toFixed(2) }} <span class="text-xs text-gray-400 dark:text-gray-500">DH</span>
                </td>
                <td class="px-4 py-3 text-right font-mono font-medium text-gray-800 dark:text-gray-200">
                  {{ (Number(item.stockLevel) * Number(item.wh_average ?? 0)).toFixed(2) }}
                  <span class="text-xs text-gray-400 dark:text-gray-500">DH</span>
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
              <tr>
                <td colspan="3" class="px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Total</td>
                <td class="px-4 py-3 text-right font-mono font-bold text-gray-800 dark:text-gray-200">
                  {{ filteredStockItems.reduce((s, i) => s + Number(i.stockLevel), 0).toFixed(2) }}
                </td>
                <td class="px-4 py-3"></td>
                <td class="px-4 py-3 text-right font-mono font-bold text-gray-800 dark:text-gray-200">
                  {{
                    filteredStockItems
                      .reduce((s, i) => s + Number(i.stockLevel) * Number(i.wh_average ?? 0), 0)
                      .toFixed(2)
                  }}
                  <span class="text-xs text-gray-400 dark:text-gray-500">DH</span>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Empty -->
        <div v-else class="py-12 text-center text-gray-400 dark:text-gray-500 text-sm">Aucun produit en stock dans ce dépôt.</div>
      </div>
      <template #footer>
        <span class="text-xs text-gray-400 dark:text-gray-500">{{ stockItems.length }} produit(s)</span>
        <div class="flex items-center gap-2">
          <button
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition"
            :disabled="!filteredStockItems.length"
            @click="exportStockXls"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
              />
            </svg>
            Export Excel
          </button>
          <button
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-violet-700 bg-violet-50 hover:bg-violet-100 rounded-lg transition"
            :disabled="!filteredStockItems.length"
            @click="printStockPdf"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
              />
            </svg>
            Imprimer PDF
          </button>
          <button
            class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition"
            @click="showStockModal = false"
          >
            Fermer
          </button>
        </div>
      </template>
    </BaseModal>

    <BaseNotification ref="toast" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useWarehouseStore } from '@/stores/warehouse'
import http from '@/services/http'
import BaseTable from '@/components/BaseTable.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'
import { useFormat } from '@/composables/useFormat'

const { t } = useI18n()
const { date: fmtDate } = useFormat()
const store = useWarehouseStore()
const { items } = storeToRefs(store)

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

const form = reactive({ wh_title: '', wh_status: true })

const columns = computed(() => [
  { key: 'wh_code', label: t('common.code') },
  { key: 'wh_title', label: t('common.name') },
  { key: 'wh_status', label: t('common.status') },
  { key: 'created_at', label: t('common.created') },
])

const statActive = computed(() => items.value.filter((r: any) => r.wh_status).length)

// ── Client-side filter + paginate ─────────────────────────────────────────
const filteredRows = computed(() => {
  const q = search.value.trim().toLowerCase()
  const s = statusFilter.value
  return items.value.filter((r) => {
    const matchSearch = !q || r.wh_title?.toLowerCase().includes(q) || r.wh_code?.toLowerCase().includes(q)
    const matchStatus = s === '' || (s === '1' ? r.wh_status : !r.wh_status)
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
  form.wh_title = ''
  form.wh_status = true
  showModal.value = true
}

function openEdit(row) {
  editTarget.value = row
  form.wh_title = row.wh_title
  form.wh_status = row.wh_status
  showModal.value = true
}

async function submit() {
  if (!form.wh_title.trim()) return
  saving.value = true
  try {
    if (editTarget.value) {
      await store.update(editTarget.value.id, form)
      toast.value?.notify(t('warehouses.updated'), 'success')
    } else {
      await store.create(form)
      toast.value?.notify(t('warehouses.created'), 'success')
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
    toast.value?.notify(t('warehouses.deleted'), 'success')
    showDelete.value = false
  } catch {
    toast.value?.notify(t('common.failedDelete'), 'error')
  } finally {
    deleting.value = false
  }
}

// ── Stock Detail Modal ───────────────────────────────────────────────────
const showStockModal = ref(false)
const stockWarehouse = ref<any>(null)
const stockItems = ref<any[]>([])
const stockLoading = ref(false)
const stockSearch = ref('')

const filteredStockItems = computed(() => {
  const q = stockSearch.value.trim().toLowerCase()
  if (!q) return stockItems.value
  return stockItems.value.filter((item) => {
    const p = item.product
    return (
      p?.p_title?.toLowerCase().includes(q) ||
      p?.p_code?.toLowerCase().includes(q) ||
      p?.p_sku?.toLowerCase().includes(q) ||
      p?.p_ean13?.toLowerCase().includes(q)
    )
  })
})

function exportStockXls() {
  const wh = stockWarehouse.value
  const items = filteredStockItems.value
  if (!wh || !items.length) return

  const fmt = (n: number) => Number(n).toFixed(2)
  const totalQty = items.reduce((s: number, i: any) => s + Number(i.stockLevel), 0)
  const totalVal = items.reduce((s: number, i: any) => s + Number(i.stockLevel) * Number(i.wh_average ?? 0), 0)

  const rows = items
    .map((item: any, idx: number) => {
      const qty = Number(item.stockLevel)
      const avg = Number(item.wh_average ?? 0)
      return `<tr>
            <td>${idx + 1}</td>
            <td>${item.product?.p_code ?? ''}</td>
            <td>${item.product?.p_title ?? ''}</td>
            <td>${item.product?.p_sku ?? ''}</td>
            <td>${item.product?.p_ean13 ?? ''}</td>
            <td style="mso-number-format:'0.00'">${fmt(qty)}</td>
            <td>${item.product?.p_unit ?? ''}</td>
            <td style="mso-number-format:'0.00'">${fmt(avg)}</td>
            <td style="mso-number-format:'0.00'">${fmt(qty * avg)}</td>
        </tr>`
    })
    .join('')

  const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head><meta charset="utf-8">
<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
<x:Name>Stock ${wh.wh_title}</x:Name>
<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
</head><body>
<table border="1" cellpadding="4" style="border-collapse:collapse;font-family:Calibri;font-size:11pt">
    <tr><td colspan="9" style="font-size:14pt;font-weight:bold;background:#7c3aed;color:white">État du Stock — ${wh.wh_title} (${wh.wh_code})</td></tr>
    <tr><td colspan="9" style="font-size:9pt;color:#666">Exporté le ${fmtDate(new Date())} — ${items.length} produit(s)</td></tr>
    <tr></tr>
    <tr style="background:#f3f4f6;font-weight:bold;font-size:10pt">
        <td>#</td><td>Code</td><td>Produit</td><td>SKU</td><td>EAN13</td><td>Quantité</td><td>Unité</td><td>Coût Moy.</td><td>Valeur (DH)</td>
    </tr>
    ${rows}
    <tr style="background:#f3f4f6;font-weight:bold;border-top:2px solid #7c3aed">
        <td colspan="5" style="text-align:right">Total</td>
        <td style="mso-number-format:'0.00'">${fmt(totalQty)}</td>
        <td></td>
        <td></td>
        <td style="mso-number-format:'0.00'">${fmt(totalVal)}</td>
    </tr>
</table>
</body></html>`

  const blob = new Blob(['\uFEFF' + html], { type: 'application/vnd.ms-excel;charset=utf-8' })
  const link = Object.assign(document.createElement('a'), {
    href: URL.createObjectURL(blob),
    download: `stock_${wh.wh_code}_${new Date().toISOString().split('T')[0]}.xls`,
  })
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(link.href)
}

function printStockPdf() {
  const wh = stockWarehouse.value
  const items = filteredStockItems.value
  if (!wh || !items.length) return

  const totalQty = items.reduce((s: number, i: any) => s + Number(i.stockLevel), 0)
  const totalVal = items.reduce((s: number, i: any) => s + Number(i.stockLevel) * Number(i.wh_average ?? 0), 0)
  const nowDate = new Date()
  const now = fmtDate(nowDate) + ' ' + nowDate.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  const fmt = (n: number) => n.toLocaleString('fr-MA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

  const rows = items
    .map((item: any, idx: number) => {
      const qty = Number(item.stockLevel)
      const avg = Number(item.wh_average ?? 0)
      return `<tr>
            <td style="padding:6px 10px;border-bottom:1px solid #e5e7eb;text-align:center;font-size:12px;color:#6b7280">${idx + 1}</td>
            <td style="padding:6px 10px;border-bottom:1px solid #e5e7eb;font-family:monospace;font-size:11px;color:#6b7280">${item.product?.p_code ?? ''}</td>
            <td style="padding:6px 10px;border-bottom:1px solid #e5e7eb;font-weight:500">${item.product?.p_title ?? ''}</td>
            <td style="padding:6px 10px;border-bottom:1px solid #e5e7eb;font-family:monospace;font-size:11px;color:#6b7280">${item.product?.p_sku ?? '—'}</td>
            <td style="padding:6px 10px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:600;font-family:monospace;color:${qty > 0 ? '#059669' : qty < 0 ? '#dc2626' : '#9ca3af'}">${fmt(qty)}</td>
            <td style="padding:6px 10px;border-bottom:1px solid #e5e7eb;text-align:right;font-family:monospace;color:#4b5563">${fmt(avg)}</td>
            <td style="padding:6px 10px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:600;font-family:monospace">${fmt(qty * avg)}</td>
        </tr>`
    })
    .join('')

  const html = `<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Stock — ${wh.wh_title}</title>
<style>
    @page { size: A4 landscape; margin: 15mm; }
    body { font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 13px; color: #1f2937; margin: 0; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #7c3aed; }
    .header h1 { font-size: 20px; color: #7c3aed; margin: 0; }
    .header .sub { font-size: 12px; color: #6b7280; margin-top: 4px; }
    .header .date { font-size: 11px; color: #9ca3af; text-align: right; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    thead th { background: #f3f4f6; padding: 8px 10px; font-size: 11px; text-transform: uppercase; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb; }
    tfoot td { background: #f9fafb; padding: 8px 10px; font-weight: 700; border-top: 2px solid #7c3aed; }
    @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style></head><body>
<div class="header">
    <div>
        <h1>État du Stock</h1>
        <div class="sub">Dépôt : <strong>${wh.wh_title}</strong> (${wh.wh_code})</div>
    </div>
    <div class="date">Imprimé le ${now}<br>${items.length} produit(s)</div>
</div>
<table>
    <thead><tr>
        <th style="text-align:center;width:40px">#</th>
        <th style="text-align:left">Code</th>
        <th style="text-align:left">Produit</th>
        <th style="text-align:left">SKU</th>
        <th style="text-align:right">Quantité</th>
        <th style="text-align:right">Coût Moy.</th>
        <th style="text-align:right">Valeur (DH)</th>
    </tr></thead>
    <tbody>${rows}</tbody>
    <tfoot><tr>
        <td colspan="4" style="text-align:right;padding-right:10px">Total</td>
        <td style="text-align:right;font-family:monospace">${fmt(totalQty)}</td>
        <td></td>
        <td style="text-align:right;font-family:monospace">${fmt(totalVal)} DH</td>
    </tr></tfoot>
</table>
</body></html>`

  const printWindow = window.open('', '_blank')
  if (!printWindow) return
  printWindow.document.write(html)
  printWindow.document.close()
  printWindow.onload = () => {
    printWindow.print()
  }
}

async function openStockModal(warehouse: any) {
  stockWarehouse.value = warehouse
  stockSearch.value = ''
  stockItems.value = []
  showStockModal.value = true
  stockLoading.value = true
  try {
    const { data } = await http.get('/warehouse-stocks', {
      params: { warehouse_id: warehouse.id, per_page: 500 },
    })
    stockItems.value = (data.data ?? data).filter((s: any) => Number(s.stockLevel) !== 0)
  } catch {
    stockItems.value = []
  } finally {
    stockLoading.value = false
  }
}

onMounted(() => {
  if (!items.value.length) store.fetchAll()
})
</script>
