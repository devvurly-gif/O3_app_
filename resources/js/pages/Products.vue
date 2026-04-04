<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex-1">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
          {{ $t('products.title') }}
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
          {{ $t('products.subtitle') }}
        </p>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap items-center gap-3 mr-1">
        <input
          v-model="search"
          type="text"
          :placeholder="$t('products.search')"
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
      <div class="flex items-center gap-2">
        <button
          class="flex items-center gap-2 px-3 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
          :disabled="exporting"
          @click="onExport"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
          </svg>
          {{ exporting ? 'Export...' : 'Export' }}
        </button>
        <button
          class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition"
          @click="openCreate"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {{ $t('products.add') }}
        </button>
      </div>
    </div>

    <!-- Table -->
    <BaseTable :columns="columns" :rows="items" :empty-text="$t('products.notFound')">
      <!-- show primary iamge -->
      <template #cell-primary_image="{ row }">
        <div class="w-20 h-15 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700 flex items-center justify-center shrink-0">
          <img
            v-if="row.primary_image || (row.images && row.images.length)"
            :src="(row.primary_image || row.images[0]).url"
            :alt="row.p_title"
            class="w-full h-full object-cover"
            @error="($event: Event) => (($event.target as HTMLImageElement).style.display = 'none')"
          />
          <svg
            v-else
            class="w-10 h-10 text-gray-300"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5M3.75 3.75h16.5A2.25 2.25 0 0122.5 6v12a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 18V6a2.25 2.25 0 012.25-2.25z"
            />
          </svg>
        </div>
      </template>

      <template #cell-p_code="{ value }">
        <span class="font-mono text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">{{ value }}</span>
      </template>
      <template #cell-p_title="{ row }">
        <div>
          <p class="font-medium text-gray-800 dark:text-gray-200 text-sm">
            {{ row.p_title }}
          </p>
          <p v-if="row.p_sku" class="text-xs text-gray-400 dark:text-gray-500 font-mono">
            {{ row.p_sku }}
          </p>
        </div>
      </template>
      <template #cell-category="{ row }">
        <span
          v-if="row.category"
          class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-purple-50 text-purple-700"
        >
          {{ row.category.ctg_title }}
        </span>
        <span v-else class="text-gray-300 text-xs">—</span>
      </template>
      <template #cell-brand="{ row }">
        <span v-if="row.brand" class="text-sm text-gray-600 dark:text-gray-400">{{ row.brand.br_title }}</span>
        <span v-else class="text-gray-300 text-xs">—</span>
      </template>
      <template #cell-p_salePrice="{ value }">
        <span class="font-mono text-sm text-gray-700 dark:text-gray-300">{{ Number(value).toFixed(2) }}</span>
      </template>
      <template #cell-p_status="{ value }">
        <span
          class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
          :class="value ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
        >
          {{ value ? $t('common.active') : $t('common.inactive') }}
        </span>
      </template>
      <template #cell-total_stock="{ row }">
        <div class="text-right">
          <span
            class="font-mono text-sm font-medium"
            :class="
              Number(row.total_stock ?? 0) > 0
                ? 'text-emerald-600'
                : Number(row.total_stock ?? 0) < 0
                  ? 'text-red-600'
                  : 'text-gray-400'
            "
          >
            {{ Number(row.total_stock ?? 0).toFixed(2) }}
          </span>
          <span class="text-xs text-gray-400 dark:text-gray-500 ml-0.5">{{ row.p_unit ?? '' }}</span>
        </div>
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
      v-if="store.meta.last_page > 1"
      :current-page="store.meta.current_page"
      :last-page="store.meta.last_page"
      :total="store.meta.total"
      :per-page="store.meta.per_page"
      @change="onPageChange"
    />

    <!-- Create / Edit Modal -->
    <BaseModal v-model="showModal" :title="editTarget ? $t('products.editTitle') : $t('products.addTitle')" size="xl">
      <form class="space-y-5" @submit.prevent="submit">
        <div class="grid grid-cols-2 gap-4">
          <!-- Title -->
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >{{ $t('common.name') }} <span class="text-red-500">*</span></label
            >
            <input
              v-model="form.p_title"
              type="text"
              required
              :placeholder="$t('products.titlePlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <!-- SKU -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.sku') }}</label>
            <input
              v-model="form.p_sku"
              type="text"
              :placeholder="$t('products.skuPlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <!-- EAN -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.ean') }}</label>
            <input
              v-model="form.p_ean13"
              type="text"
              :placeholder="$t('products.eanPlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <!-- Purchase Price -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >{{ $t('products.purchasePrice') }} <span class="text-red-500">*</span></label
            >
            <input
              v-model.number="form.p_purchasePrice"
              type="number"
              min="0"
              step="0.01"
              required
              placeholder="0.00"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <!-- Sale Price -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >{{ $t('products.salePrice') }} <span class="text-red-500">*</span></label
            >
            <input
              v-model.number="form.p_salePrice"
              type="number"
              min="0"
              step="0.01"
              required
              placeholder="0.00"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <!-- Tax Rate -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.taxRate') }}</label>
            <input
              v-model.number="form.p_taxRate"
              type="number"
              min="0"
              max="100"
              step="0.01"
              placeholder="20"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <!-- Unit -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.unit') }}</label>
            <input
              v-model="form.p_unit"
              type="text"
              :placeholder="$t('products.unitPlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <!-- Category -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.category') }}</label>
            <select
              v-model="form.category_id"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option :value="null">—</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.ctg_title }}
              </option>
            </select>
          </div>

          <!-- Brand -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.brand') }}</label>
            <select
              v-model="form.brand_id"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option :value="null">—</option>
              <option v-for="br in brands" :key="br.id" :value="br.id">
                {{ br.br_title }}
              </option>
            </select>
          </div>

          <!-- Description -->
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.description') }}</label>
            <textarea
              v-model="form.p_description"
              rows="2"
              placeholder="…"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <!-- Status -->
          <div class="col-span-2 flex items-center gap-2">
            <input
              id="product-status"
              v-model="form.p_status"
              type="checkbox"
              class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
            />
            <label for="product-status" class="text-sm text-gray-700 dark:text-gray-300">{{ $t('common.active') }}</label>
          </div>
        </div>

        <!-- ── Images (only shown when editing an existing product) ── -->
        <div v-if="editTarget" class="border-t border-gray-100 dark:border-gray-700 pt-4 space-y-3">
          <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
            {{ $t('products.images') }}
          </h3>

          <!-- Existing images grid -->
          <div v-if="editImages.length" class="grid grid-cols-4 gap-3">
            <div
              v-for="img in editImages"
              :key="img.id"
              class="relative group rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 aspect-square bg-gray-50 dark:bg-gray-900"
            >
              <img :src="img.url" :alt="img.title" class="w-full h-full object-cover" />

              <!-- Primary badge -->
              <span
                v-if="img.isPrimary"
                class="absolute top-1 left-1 text-[10px] font-bold bg-blue-600 text-white px-1.5 py-0.5 rounded"
              >
                {{ $t('products.primary') }}
              </span>

              <!-- Hover actions -->
              <div
                class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2"
              >
                <button
                  v-if="!img.isPrimary"
                  type="button"
                  class="p-1.5 bg-white dark:bg-gray-800 rounded-lg text-blue-600 hover:bg-blue-50 transition"
                  :title="$t('products.setPrimary')"
                  @click="doSetPrimary(img)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                    />
                  </svg>
                </button>
                <button
                  type="button"
                  class="p-1.5 bg-white dark:bg-gray-800 rounded-lg text-red-500 hover:bg-red-50 transition"
                  :title="$t('common.delete')"
                  @click="doDeleteImage(img)"
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
            </div>

            <!-- Upload tile -->
            <label
              class="aspect-square rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-blue-400 bg-gray-50 dark:bg-gray-900 flex flex-col items-center justify-center cursor-pointer transition text-gray-400 dark:text-gray-500 hover:text-blue-500"
            >
              <svg
                v-if="!uploadingImage"
                class="w-6 h-6 mb-1"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <svg v-else class="w-5 h-5 animate-spin mb-1" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
              </svg>
              <span class="text-xs font-medium">{{
                uploadingImage ? $t('products.uploadingImage') : $t('products.addImage')
              }}</span>
              <input
                type="file"
                accept="image/*"
                class="hidden"
                :disabled="uploadingImage"
                @change="handleImageUpload"
              />
            </label>
          </div>

          <!-- Empty state + upload -->
          <div v-else class="flex items-center gap-4">
            <p class="text-sm text-gray-400 dark:text-gray-500">
              {{ $t('products.noImages') }}
            </p>
            <label
              class="flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-blue-600 border border-blue-300 rounded-lg hover:bg-blue-50 cursor-pointer transition"
            >
              <svg
                v-if="!uploadingImage"
                class="w-4 h-4"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
              </svg>
              {{ uploadingImage ? $t('products.uploadingImage') : $t('products.addImage') }}
              <input
                type="file"
                accept="image/*"
                class="hidden"
                :disabled="uploadingImage"
                @change="handleImageUpload"
              />
            </label>
          </div>
        </div>

        <!-- Note: images can be added after saving when creating a new product -->
        <p v-if="!editTarget" class="text-xs text-gray-400 dark:text-gray-500 -mt-2">
          {{ $t('products.imagesAfterSave') ?? 'Images can be added after saving.' }}
        </p>
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
    <BaseModal v-model="showDelete" :title="$t('products.deleteTitle')" size="sm">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ $t('products.deleteConfirm') }}
        <span class="font-semibold">{{ deleteTarget?.p_title }}</span
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
import { useProductStore } from '@/stores/product'
import { useCategoryStore } from '@/stores/category'
import { useBrandStore } from '@/stores/brand'
import { useExcelExport } from '@/composables/useExcelExport'
import BaseTable from '@/components/BaseTable.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'

const { t } = useI18n()
const store = useProductStore()
const categoryStore = useCategoryStore()
const brandStore = useBrandStore()

const { items } = storeToRefs(store)
const { items: categories } = storeToRefs(categoryStore)
const { items: brands } = storeToRefs(brandStore)

const { exporting, exportExcel } = useExcelExport()

function onExport() {
  exportExcel('/export/products', buildParams())
}

// ── UI state ───────────────────────────────────────────────────────────────
const search = ref('')
const statusFilter = ref('')
const toast = ref(null)

let searchTimer = null

const showModal = ref(false)
const showDelete = ref(false)
const saving = ref(false)
const deleting = ref(false)
const uploadingImage = ref(false)
const editTarget = ref(null)
const deleteTarget = ref(null)

// Images for the currently-edited product (reactive copy)
const editImages = ref([])

const emptyForm = () => ({
  p_title: '',
  p_sku: '',
  p_ean13: '',
  p_purchasePrice: 0,
  p_salePrice: 0,
  p_taxRate: 20,
  p_unit: 'pièce',
  p_description: '',
  p_status: true,
  category_id: null,
  brand_id: null,
})

const form = reactive(emptyForm())

const columns = computed(() => [
  { key: 'primary_image', label: '#' },
  { key: 'p_code', label: t('common.code') },
  { key: 'p_title', label: t('common.name') },
  { key: 'category', label: t('products.category') },
  { key: 'brand', label: t('products.brand') },
  { key: 'p_salePrice', label: t('products.salePrice') },
  { key: 'p_status', label: t('common.status') },
  { key: 'total_stock', label: 'Stock' },
])

// ── Server-side filters + pagination ─────────────────────────────────────
function buildParams(): Record<string, string> {
  const p: Record<string, string> = {}
  if (search.value.trim()) p.search = search.value.trim()
  if (statusFilter.value !== '') p.status = statusFilter.value
  return p
}

function loadPage(page = 1) {
  store.params.page = page
  Object.assign(store.params, buildParams())
  store.fetchPage(page)
}

function onPageChange(page) {
  loadPage(page)
}

watch([search, statusFilter], () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadPage(1), 350)
})

// ── CRUD ───────────────────────────────────────────────────────────────────
function openCreate() {
  editTarget.value = null
  editImages.value = []
  Object.assign(form, emptyForm())
  showModal.value = true
}

function openEdit(row) {
  editTarget.value = row
  editImages.value = [...(row.images ?? [])]
  Object.assign(form, {
    p_title: row.p_title,
    p_sku: row.p_sku ?? '',
    p_ean13: row.p_ean13 ?? '',
    p_purchasePrice: Number(row.p_purchasePrice),
    p_salePrice: Number(row.p_salePrice),
    p_taxRate: Number(row.p_taxRate),
    p_unit: row.p_unit ?? 'pièce',
    p_description: row.p_description ?? '',
    p_status: row.p_status,
    category_id: row.category_id ?? null,
    brand_id: row.brand_id ?? null,
  })
  showModal.value = true
}

async function submit() {
  if (!form.p_title.trim()) return
  saving.value = true
  try {
    if (editTarget.value) {
      await store.update(editTarget.value.id, form)
      toast.value?.notify(t('products.updated'), 'success')
    } else {
      await store.create(form)
      toast.value?.notify(t('products.created'), 'success')
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
    toast.value?.notify(t('products.deleted'), 'success')
    showDelete.value = false
  } catch {
    toast.value?.notify(t('common.failedDelete'), 'error')
  } finally {
    deleting.value = false
  }
}

// ── Image upload ───────────────────────────────────────────────────────────
async function handleImageUpload(e) {
  const file = e.target.files?.[0]
  if (!file || !editTarget.value) return
  e.target.value = '' // reset input

  uploadingImage.value = true
  try {
    const fd = new FormData()
    fd.append('image', file)
    fd.append('isPrimary', editImages.value.length === 0 ? '1' : '0')
    const img = await store.uploadImage(editTarget.value.id, fd)
    editImages.value.push(img)
    if (img.isPrimary)
      editImages.value.forEach((i) => {
        if (i.id !== img.id) i.isPrimary = false
      })
    toast.value?.notify(t('products.imageUploaded'), 'success')
  } catch {
    toast.value?.notify(t('products.imageFailed'), 'error')
  } finally {
    uploadingImage.value = false
  }
}

async function doSetPrimary(img) {
  try {
    await store.setPrimaryImage(editTarget.value.id, img.id)
    editImages.value.forEach((i) => {
      i.isPrimary = i.id === img.id
    })
  } catch {
    toast.value?.notify(t('common.failedSave'), 'error')
  }
}

async function doDeleteImage(img) {
  try {
    await store.deleteImage(editTarget.value.id, img.id)
    editImages.value = editImages.value.filter((i) => i.id !== img.id)
    toast.value?.notify(t('products.imageDeleted'), 'success')
  } catch {
    toast.value?.notify(t('common.failedDelete'), 'error')
  }
}

onMounted(() => {
  store.fetchPage(1)
  if (!categories.value.length) categoryStore.fetchAll()
  if (!brands.value.length) brandStore.fetchAll()
})
</script>
