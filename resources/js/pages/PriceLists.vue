<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h2 class="text-[26px] sm:text-[30px] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white">{{ $t('pricelists.title') }}</h2>
        <p class="text-sm text-[#8A8F9C] dark:text-gray-400 mt-1">{{ $t('pricelists.subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          class="flex items-center gap-2 px-4 sm:px-5 py-2.5 bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white text-sm font-bold rounded-[11px] shadow-[0_8px_20px_-8px_rgba(124,92,252,0.6)] transition"
          @click="openCreate"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {{ $t('pricelists.add') }}
        </button>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-3 gap-3 sm:gap-4">
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
        <div class="w-9 h-9 rounded-[10px] bg-[#EFF1F5] dark:bg-gray-700 flex items-center justify-center mb-3.5">
          <svg class="w-[17px] h-[17px]" fill="none" stroke="#5B6070" stroke-width="1.6" viewBox="0 0 20 20">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2 3h16v4H2V3zm0 7h11v4H2v-4zm0 7h16v4H2v-4z" />
          </svg>
        </div>
        <div class="text-2xl sm:text-[26px] font-extrabold text-gray-900 dark:text-white">{{ items.length }}</div>
        <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400 mt-0.5">{{ $t('pricelists.title') }}</div>
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
        v-model="search"
        type="text"
        :placeholder="$t('pricelists.search')"
        class="px-3.5 py-2.5 text-sm rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent w-64"
      />
      <select
        v-model="channelFilter"
        class="px-3.5 py-2.5 text-[13px] font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-[#4A4F5B] dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
      >
        <option value="">{{ $t('common.allStatus') }}</option>
        <option value="all">{{ $t('pricelists.channelAll') }}</option>
        <option value="pos">{{ $t('pricelists.channelPos') }}</option>
        <option value="ecom">{{ $t('pricelists.channelEcom') }}</option>
      </select>
    </div>

    <!-- Table -->
    <BaseTable :columns="columns" :rows="pagedRows" :empty-text="$t('pricelists.notFound')">
      <template #cell-name="{ row }">
        <div class="flex items-center gap-2">
          <span class="font-medium text-gray-900 dark:text-gray-100">{{ row.name }}</span>
          <span
            v-if="row.is_default"
            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#F1ECFC] text-[#7C5CFC]"
          >
            {{ $t('pricelists.defaultBadge') }}
          </span>
        </div>
        <div v-if="row.description" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ row.description }}</div>
      </template>
      <template #cell-channel="{ value }">
        <span
          class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
          :class="{
            'bg-gray-100 text-gray-700': value === 'all',
            'bg-purple-100 text-purple-700': value === 'pos',
            'bg-teal-100 text-teal-700': value === 'ecom',
          }"
        >
          {{ channelLabel(value) }}
        </span>
      </template>
      <template #cell-items_count="{ value }">
        <span class="font-mono text-xs">{{ value ?? 0 }}</span>
      </template>
      <template #cell-customers_count="{ value }">
        <span class="font-mono text-xs">{{ value ?? 0 }}</span>
      </template>
      <template #cell-is_active="{ value }">
        <span
          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold"
          :class="value ? 'bg-[#E5F7ED] text-[#1F8A50]' : 'bg-[#F0F1F4] text-[#7A7F8C]'"
        >
          {{ value ? $t('common.active') : $t('common.inactive') }}
        </span>
      </template>
      <template #actions="{ row }">
        <div class="flex items-center justify-end gap-2">
          <button
            class="p-1.5 rounded-lg text-indigo-600 hover:bg-indigo-50 transition"
            :title="$t('pricelists.items')"
            @click="openItems(row)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h18v4H3V3zm0 7h12v4H3v-4zm0 7h18v4H3v-4z" />
            </svg>
          </button>
          <button
            class="p-1.5 rounded-lg text-[#7C5CFC] hover:bg-[#F1ECFC] transition"
            :title="$t('common.update')"
            @click="openEdit(row)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
          </button>
          <button
            class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition disabled:opacity-40 disabled:cursor-not-allowed"
            :disabled="row.is_default"
            :title="row.is_default ? '' : $t('common.delete')"
            @click="confirmDelete(row)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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

    <!-- Create / Edit Modal (metadata) -->
    <BaseModal v-model="showModal" :title="editTarget ? $t('pricelists.editTitle') : $t('pricelists.addTitle')" size="md">
      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label for="pricelists-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('pricelists.name') }} <span class="text-red-500">*</span>
          </label>
          <input
            id="pricelists-name"
            v-model="form.name"
            type="text"
            required
            :placeholder="$t('pricelists.namePlaceholder')"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
          />
        </div>
        <div>
          <label for="pricelists-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('pricelists.description') }}
          </label>
          <textarea
            id="pricelists-description"
            v-model="form.description"
            rows="2"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
          />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="pricelists-channel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('pricelists.channel') }}
            </label>
            <select
              id="pricelists-channel"
              v-model="form.channel"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
            >
              <option value="all">{{ $t('pricelists.channelAll') }}</option>
              <option value="pos">{{ $t('pricelists.channelPos') }}</option>
              <option value="ecom">{{ $t('pricelists.channelEcom') }}</option>
            </select>
          </div>
          <div>
            <label for="pricelists-priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('pricelists.priority') }}
            </label>
            <input
              id="pricelists-priority"
              v-model.number="form.priority"
              type="number"
              min="0"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
            />
          </div>
        </div>
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2">
            <input
              id="pl-default"
              v-model="form.is_default"
              type="checkbox"
              class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-[#7C5CFC] focus:ring-[#7C5CFC]"
            />
            <label for="pl-default" class="text-sm text-gray-700 dark:text-gray-300">{{ $t('pricelists.isDefault') }}</label>
          </div>
          <div class="flex items-center gap-2">
            <input
              id="pl-active"
              v-model="form.is_active"
              type="checkbox"
              class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-[#7C5CFC] focus:ring-[#7C5CFC]"
            />
            <label for="pl-active" class="text-sm text-gray-700 dark:text-gray-300">{{ $t('pricelists.isActive') }}</label>
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
          class="px-4 py-2 text-sm font-semibold bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white rounded-lg transition disabled:opacity-60"
          :disabled="saving"
          @click="submit"
        >
          {{ saving ? $t('common.saving') : editTarget ? $t('common.update') : $t('common.create') }}
        </button>
      </template>
    </BaseModal>

    <!-- Items editor modal -->
    <BaseModal v-model="showItems" :title="itemsTarget?.name ?? $t('pricelists.items')" size="xl">
      <div v-if="itemsTarget" class="space-y-4">
        <!-- Add item row -->
        <div class="flex flex-wrap items-end gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
          <div class="flex-1 min-w-[240px] relative">
            <label for="pricelists-productsearch" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
              {{ $t('pricelists.product') }}
            </label>
            <input
              id="pricelists-productsearch"
              v-model="productSearch"
              type="text"
              :placeholder="$t('pricelists.search')"
              class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
              @focus="productDropdown = true"
            />
            <div
              v-if="productDropdown && productResults.length"
              class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-auto"
            >
              <button
                v-for="p in productResults"
                :key="p.id"
                type="button"
                class="w-full text-left px-3 py-2 text-sm hover:bg-[#F1ECFC] dark:hover:bg-gray-700 flex items-center justify-between gap-2"
                @click="pickProduct(p)"
              >
                <span class="truncate">{{ p.p_title }}</span>
                <span class="font-mono text-[11px] text-gray-500">{{ p.p_code }}</span>
              </button>
            </div>
          </div>
          <div class="w-28">
            <label for="pricelists-newitem-price-ht" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
              {{ $t('pricelists.priceHt') }}
            </label>
            <input
              id="pricelists-newitem-price-ht"
              v-model.number="newItem.price_ht"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
            />
          </div>
          <div class="w-24">
            <label for="pricelists-newitem-min-qty" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
              {{ $t('pricelists.minQty') }}
            </label>
            <input
              id="pricelists-newitem-min-qty"
              v-model.number="newItem.min_qty"
              type="number"
              min="1"
              class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
            />
          </div>
          <div class="w-36">
            <label for="pricelists-newitem-valid-from" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
              {{ $t('pricelists.validFrom') }}
            </label>
            <input
              id="pricelists-newitem-valid-from"
              v-model="newItem.valid_from"
              type="date"
              class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
            />
          </div>
          <div class="w-36">
            <label for="pricelists-newitem-valid-to" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
              {{ $t('pricelists.validTo') }}
            </label>
            <input
              id="pricelists-newitem-valid-to"
              v-model="newItem.valid_to"
              type="date"
              class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
            />
          </div>
          <button
            type="button"
            class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg disabled:opacity-50"
            :disabled="!newItem.product_id || !newItem.price_ht || savingItems"
            @click="addRow"
          >
            {{ $t('pricelists.addItem') }}
          </button>
        </div>

        <!-- Items table -->
        <div class="overflow-auto border border-gray-200 dark:border-gray-700 rounded-lg">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500">
              <tr>
                <th class="px-3 py-2 text-left">{{ $t('pricelists.product') }}</th>
                <th class="px-3 py-2 text-right">{{ $t('pricelists.priceHt') }}</th>
                <th class="px-3 py-2 text-right">{{ $t('pricelists.priceTtc') }}</th>
                <th class="px-3 py-2 text-right">{{ $t('pricelists.minQty') }}</th>
                <th class="px-3 py-2 text-left">{{ $t('pricelists.validFrom') }}</th>
                <th class="px-3 py-2 text-left">{{ $t('pricelists.validTo') }}</th>
                <th class="px-3 py-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-if="!itemsTarget.items?.length">
                <td colspan="7" class="px-3 py-6 text-center text-gray-500">{{ $t('pricelists.notFound') }}</td>
              </tr>
              <tr v-for="it in itemsTarget.items ?? []" :key="it.id">
                <td class="px-3 py-2">
                  <div class="font-medium">{{ it.product?.p_title ?? '#' + it.product_id }}</div>
                  <div class="text-[11px] text-gray-500 font-mono">{{ it.product?.p_code }}</div>
                </td>
                <td class="px-3 py-2 text-right font-mono">{{ fmt(it.price_ht) }}</td>
                <td class="px-3 py-2 text-right font-mono">{{ fmt(it.price_ttc) }}</td>
                <td class="px-3 py-2 text-right">{{ it.min_qty }}</td>
                <td class="px-3 py-2 text-xs">{{ it.valid_from ?? '—' }}</td>
                <td class="px-3 py-2 text-xs">{{ it.valid_to ?? '—' }}</td>
                <td class="px-3 py-2 text-right">
                  <button
                    class="p-1 text-red-500 hover:bg-red-50 rounded transition"
                    @click="removeRow(it.id)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg"
          @click="showItems = false"
        >
          {{ $t('common.close') }}
        </button>
      </template>
    </BaseModal>

    <!-- Delete modal -->
    <BaseModal v-model="showDelete" :title="$t('pricelists.deleteTitle')" size="sm">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ $t('pricelists.deleteConfirm') }} <span class="font-semibold">{{ deleteTarget?.name }}</span>? {{ $t('common.cannotUndo') }}
      </p>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg"
          @click="showDelete = false"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg disabled:opacity-60"
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
import { usePriceListStore } from '@/stores/priceList'
import http from '@/services/http'
import BaseTable from '@/components/BaseTable.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'
import type { PriceList, PriceListChannel, Product } from '@/types'

const { t } = useI18n()
const store = usePriceListStore()
const { items } = storeToRefs(store)

// ── UI state ──────────────────────────────────────────────────────────────
const search = ref('')
const channelFilter = ref('')
const currentPage = ref(1)
const perPage = 15
const toast = ref<{ notify: (msg: string, type?: string) => void } | null>(null)

const showModal = ref(false)
const showDelete = ref(false)
const showItems = ref(false)
const saving = ref(false)
const deleting = ref(false)
const savingItems = ref(false)
const editTarget = ref<PriceList | null>(null)
const deleteTarget = ref<PriceList | null>(null)
const itemsTarget = ref<PriceList | null>(null)

const form = reactive({
  name: '',
  description: '',
  channel: 'all' as PriceListChannel,
  is_default: false,
  is_active: true,
  priority: 0,
})

const newItem = reactive({
  product_id: null as number | null,
  price_ht: 0,
  min_qty: 1,
  valid_from: '' as string | null,
  valid_to: '' as string | null,
})

const productSearch = ref('')
const productResults = ref<Product[]>([])
const productDropdown = ref(false)

const columns = computed(() => [
  { key: 'name', label: t('pricelists.name') },
  { key: 'channel', label: t('pricelists.channel') },
  { key: 'items_count', label: t('pricelists.itemsCount') },
  { key: 'customers_count', label: t('pricelists.customersCount') },
  { key: 'priority', label: t('pricelists.priority') },
  { key: 'is_active', label: t('common.status') },
])

const statActive = computed(() => items.value.filter((r: any) => r.is_active).length)

// ── Filter + paginate ─────────────────────────────────────────────────────
const filteredRows = computed(() => {
  const q = search.value.trim().toLowerCase()
  const ch = channelFilter.value
  return items.value.filter((r) => {
    const matchSearch = !q || r.name?.toLowerCase().includes(q) || (r.description ?? '').toLowerCase().includes(q)
    const matchChannel = !ch || r.channel === ch
    return matchSearch && matchChannel
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / perPage)))
const pagedRows = computed(() =>
  filteredRows.value.slice((currentPage.value - 1) * perPage, currentPage.value * perPage),
)

watch([search, channelFilter], () => {
  currentPage.value = 1
})

function channelLabel(c: string): string {
  if (c === 'pos') return t('pricelists.channelPos')
  if (c === 'ecom') return t('pricelists.channelEcom')
  return t('pricelists.channelAll')
}

function fmt(v: number | string | null | undefined): string {
  const n = typeof v === 'string' ? parseFloat(v) : (v ?? 0)
  return n.toFixed(2)
}

// ── CRUD (metadata) ──────────────────────────────────────────────────────
function resetForm() {
  form.name = ''
  form.description = ''
  form.channel = 'all'
  form.is_default = false
  form.is_active = true
  form.priority = 0
}

function openCreate() {
  editTarget.value = null
  resetForm()
  showModal.value = true
}

function openEdit(row: PriceList) {
  editTarget.value = row
  form.name = row.name
  form.description = row.description ?? ''
  form.channel = row.channel
  form.is_default = row.is_default
  form.is_active = row.is_active
  form.priority = row.priority
  showModal.value = true
}

async function submit() {
  if (!form.name.trim()) return
  saving.value = true
  try {
    if (editTarget.value) {
      await store.update(editTarget.value.id, { ...form })
      toast.value?.notify(t('pricelists.updated'), 'success')
    } else {
      await store.create({ ...form })
      toast.value?.notify(t('pricelists.created'), 'success')
    }
    await store.fetchAll() // refresh counts
    showModal.value = false
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.value?.notify(e.response?.data?.message ?? t('common.failedSave'), 'error')
  } finally {
    saving.value = false
  }
}

function confirmDelete(row: PriceList) {
  if (row.is_default) return
  deleteTarget.value = row
  showDelete.value = true
}

async function doDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await store.remove(deleteTarget.value.id)
    toast.value?.notify(t('pricelists.deleted'), 'success')
    showDelete.value = false
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.value?.notify(e.response?.data?.message ?? t('common.failedDelete'), 'error')
  } finally {
    deleting.value = false
  }
}

// ── Items editor ─────────────────────────────────────────────────────────
async function openItems(row: PriceList) {
  showItems.value = true
  try {
    const fresh = await store.fetchOne(row.id)
    itemsTarget.value = fresh
  } catch {
    toast.value?.notify(t('common.failedLoad'), 'error')
  }
  resetNewItem()
}

function resetNewItem() {
  newItem.product_id = null
  newItem.price_ht = 0
  newItem.min_qty = 1
  newItem.valid_from = ''
  newItem.valid_to = ''
  productSearch.value = ''
  productResults.value = []
}

// Live product search
let searchHandle: ReturnType<typeof setTimeout> | null = null
watch(productSearch, (q) => {
  if (searchHandle) clearTimeout(searchHandle)
  if (!q || q.length < 2) {
    productResults.value = []
    return
  }
  searchHandle = setTimeout(async () => {
    try {
      const { data } = await http.get<Product[] | { data: Product[] }>('/products', {
        params: { search: q, per_page: 10 },
      })
      const list = Array.isArray(data) ? data : ((data as { data?: Product[] }).data ?? [])
      productResults.value = list
      productDropdown.value = true
    } catch {
      productResults.value = []
    }
  }, 250)
})

function pickProduct(p: Product) {
  newItem.product_id = p.id
  productSearch.value = p.p_title + ' (' + p.p_code + ')'
  newItem.price_ht = Number(p.p_salePrice) || 0
  productResults.value = []
  productDropdown.value = false
}

async function addRow() {
  if (!itemsTarget.value || !newItem.product_id || !newItem.price_ht) return
  savingItems.value = true
  try {
    await store.upsertItems(itemsTarget.value.id, [
      {
        product_id: newItem.product_id,
        price_ht: newItem.price_ht,
        min_qty: newItem.min_qty || 1,
        valid_from: newItem.valid_from || null,
        valid_to: newItem.valid_to || null,
      },
    ])
    toast.value?.notify(t('pricelists.itemsSaved'), 'success')
    itemsTarget.value = store.current
    resetNewItem()
    await store.fetchAll() // refresh counts on list
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.value?.notify(e.response?.data?.message ?? t('common.failedSave'), 'error')
  } finally {
    savingItems.value = false
  }
}

async function removeRow(itemId: number) {
  if (!itemsTarget.value) return
  try {
    await store.removeItem(itemsTarget.value.id, itemId)
    toast.value?.notify(t('pricelists.itemDeleted'), 'success')
    await store.fetchAll()
  } catch {
    toast.value?.notify(t('common.failedDelete'), 'error')
  }
}

onMounted(() => {
  if (!items.value.length) store.fetchAll()
})
</script>
