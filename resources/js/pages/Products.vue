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

    <!-- Create / Edit Modal with Tabs -->
    <BaseModal v-model="showModal" :title="editTarget ? $t('products.editTitle') : $t('products.addTitle')" size="2xl">
      <form class="space-y-0" @submit.prevent="submit">
        <!-- Tab Navigation (sticky, flush to modal top) -->
        <div class="sticky top-0 z-10 -mx-4 sm:-mx-5 -mt-3 mb-3 px-4 sm:px-5 bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm border-b border-gray-200 dark:border-gray-700">
          <div class="flex gap-0.5 overflow-x-auto scrollbar-thin">
            <button
              v-for="(tab, idx) in tabs"
              :key="idx"
              type="button"
              class="relative whitespace-nowrap py-1.5 px-2.5 sm:px-3 text-sm font-medium transition-colors border-b-2 -mb-px"
              :class="
                currentTab === idx
                  ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                  : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'
              "
              @click="currentTab = idx"
            >
              {{ tab.label }}
            </button>
          </div>
        </div>

        <!-- Tab: Info -->
        <div v-if="currentTab === 0" ref="infoTabRef" class="space-y-3 py-2">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <!-- Title (full row) -->
            <div class="sm:col-span-2 lg:col-span-3">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >{{ $t('common.name') }} <span class="text-red-500">*</span></label
              >
              <input
                v-model="form.p_title"
                type="text"
                required
                :placeholder="$t('products.titlePlaceholder')"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                @input="generateSlugFromTitle"
              />
            </div>

            <!-- Code -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.code') }}</label>
              <input
                v-model="form.p_code"
                type="text"
                :placeholder="$t('products.codePlaceholder')"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>

            <!-- SKU -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.sku') }}</label>
              <input
                v-model="form.p_sku"
                type="text"
                :placeholder="$t('products.skuPlaceholder')"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $t('products.skuAuto') ?? 'Auto-generated if empty' }}</p>
            </div>

            <!-- EAN13 -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.ean') }}</label>
              <input
                v-model="form.p_ean13"
                type="text"
                :placeholder="$t('products.eanPlaceholder')"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>

            <!-- IMEI -->
            <div class="sm:col-span-2 lg:col-span-3">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IMEI</label>
              <input
                v-model="form.p_imei"
                type="text"
                placeholder="Device IMEI..."
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <!-- Description -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.description') }}</label>
              <textarea
                v-model="form.p_description"
                rows="3"
                placeholder="…"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>

            <!-- Long Description (E-commerce) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.longDescription') ?? 'Long Description' }}</label>
              <textarea
                v-model="form.p_long_description"
                rows="3"
                placeholder="E-commerce description…"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
            <textarea
              v-model="form.p_notes"
              rows="2"
              placeholder="Internal notes…"
              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <!-- Category / Brand / Slug -->
          <div class="pt-3 border-t border-gray-200 dark:border-gray-700 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              <!-- Category -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.category') }}</label>
                <select
                  v-model="form.category_id"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option :value="null">—</option>
                  <option v-for="br in brands" :key="br.id" :value="br.id">
                    {{ br.br_title }}
                  </option>
                </select>
              </div>

              <!-- E-commerce Slug -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.slug') ?? 'Slug' }}</label>
                <input
                  v-model="form.p_slug"
                  type="text"
                  :placeholder="$t('products.slugPlaceholder') ?? 'Auto-generated from title'"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
            </div>

            <!-- E-commerce Toggle -->
            <div class="flex items-center gap-2 pt-1">
              <input
                id="product-ecom"
                v-model="form.is_ecom"
                type="checkbox"
                class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
              />
              <label for="product-ecom" class="text-sm text-gray-700 dark:text-gray-300">{{ $t('products.ecommerce') ?? 'E-commerce Product' }}</label>
            </div>
          </div>

          <!-- Status -->
          <div class="flex items-center gap-2 pt-1">
            <input
              id="product-status"
              v-model="form.p_status"
              type="checkbox"
              class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
            />
            <label for="product-status" class="text-sm text-gray-700 dark:text-gray-300">{{ $t('common.active') }}</label>
          </div>
        </div>

        <!-- Tab: Tarifs (Pricing) -->
        <div v-if="currentTab === 1" class="space-y-4 py-2" :style="{ minHeight: tabMinHeight }">
          <!-- Master Prices Section -->
          <div class="bg-gray-50 dark:bg-gray-800/60 p-3 rounded-lg space-y-3">
            <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Master Prices</h4>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
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
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>

              <!-- Cost Price -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.costPrice') ?? 'Cost Price' }}</label>
                <input
                  v-model.number="form.p_cost"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="0.00"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>

              <!-- Unit -->
              <div class="sm:col-span-2 lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.unit') }}</label>
                <input
                  v-model="form.p_unit"
                  type="text"
                  :placeholder="$t('products.unitPlaceholder')"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
            </div>

            <!-- Margin Indicator -->
            <div v-if="form.p_salePrice > 0 && form.p_purchasePrice > 0" class="px-3 py-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded text-sm">
              <p class="text-blue-800 dark:text-blue-200">
                <span class="font-semibold">Margin:</span>
                {{ marginPercent }}%
                <span :class="marginPercent >= 20 ? 'text-green-600 dark:text-green-400' : 'text-orange-600 dark:text-orange-400'">
                  ({{ marginPercent >= 20 ? 'Healthy' : 'Low' }})
                </span>
              </p>
            </div>
          </div>

          <!-- Price List Tiers Section -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Tarifs par grille</h4>
              <button
                v-if="editTarget"
                type="button"
                :disabled="tierAdding"
                class="text-xs px-2.5 py-1 rounded-md bg-blue-600 hover:bg-blue-700 text-white font-medium transition disabled:opacity-50"
                @click="tierAdding = !tierAdding"
              >
                {{ tierAdding ? 'Annuler' : '+ Ajouter un tarif' }}
              </button>
            </div>

            <!-- Inline add-tier form -->
            <div
              v-if="editTarget && tierAdding"
              class="p-2.5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg grid grid-cols-1 sm:grid-cols-4 gap-2"
            >
              <div class="sm:col-span-2">
                <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Grille</label>
                <select
                  v-model.number="newTier.price_list_id"
                  class="w-full px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-800"
                >
                  <option :value="null" disabled>— Choisir —</option>
                  <option
                    v-for="pl in priceListsOptions"
                    :key="pl.id"
                    :value="pl.id"
                    :disabled="isListAlreadyUsed(pl.id, newTier.min_qty)"
                  >
                    {{ pl.name }}{{ pl.is_default ? ' (défaut)' : '' }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Qté min</label>
                <input
                  v-model.number="newTier.min_qty"
                  type="number"
                  min="1"
                  class="w-full px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 text-sm font-mono bg-white dark:bg-gray-800"
                />
              </div>
              <div>
                <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Prix HT</label>
                <input
                  v-model.number="newTier.price_ht"
                  type="number"
                  min="0"
                  step="0.01"
                  class="w-full px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 text-sm font-mono bg-white dark:bg-gray-800"
                />
              </div>
              <div class="sm:col-span-4 flex items-center justify-between pt-0.5">
                <p class="text-xs text-gray-600 dark:text-gray-400">
                  Prix TTC estimé :
                  <span class="font-mono font-semibold">{{ newTierTtc }} MAD</span>
                </p>
                <button
                  type="button"
                  :disabled="!canAddTier || tierSaving"
                  class="text-xs px-2.5 py-1 rounded-md bg-green-600 hover:bg-green-700 text-white font-semibold disabled:opacity-50"
                  @click="addTier"
                >
                  {{ tierSaving ? 'Enregistrement…' : 'Enregistrer' }}
                </button>
              </div>
            </div>

            <div v-if="editTarget && priceListItems.length" class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                  <tr>
                    <th class="px-2.5 py-1.5 text-left text-gray-600 dark:text-gray-300 font-medium">Grille</th>
                    <th class="px-2.5 py-1.5 text-right text-gray-600 dark:text-gray-300 font-medium">Qté min</th>
                    <th class="px-2.5 py-1.5 text-right text-gray-600 dark:text-gray-300 font-medium">Prix HT</th>
                    <th class="px-2.5 py-1.5 text-right text-gray-600 dark:text-gray-300 font-medium">Prix TTC</th>
                    <th class="px-2.5 py-1.5 w-8"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="item in priceListItems" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td class="px-2.5 py-1.5 text-gray-800 dark:text-gray-200">{{ item.price_list?.name ?? item.priceList?.name ?? '—' }}</td>
                    <td class="px-2.5 py-1.5 text-right font-mono">{{ item.min_qty }}</td>
                    <td class="px-2.5 py-1.5 text-right font-mono">{{ Number(item.price_ht).toFixed(2) }} MAD</td>
                    <td class="px-2.5 py-1.5 text-right font-mono">{{ Number(item.price_ttc).toFixed(2) }} MAD</td>
                    <td class="px-2.5 py-1.5 text-right">
                      <button
                        type="button"
                        class="text-red-600 hover:text-red-800 dark:text-red-400 text-xs"
                        :disabled="tierDeletingId === item.id"
                        @click="removeTier(item)"
                      >
                        ×
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="text-sm text-gray-500 dark:text-gray-400">
              {{ editTarget ? 'Aucun tarif spécifique — le prix de vente principal est utilisé.' : 'Enregistrez d\'abord le produit pour ajouter des tarifs par grille.' }}
            </div>
          </div>

        </div>

        <!-- Tab: Stock -->
        <div v-if="currentTab === 2" class="space-y-3 py-2" :style="{ minHeight: tabMinHeight }">
          <div v-if="editTarget" class="space-y-3">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div class="bg-blue-50 dark:bg-blue-900/20 px-3 py-2.5 rounded-lg border border-blue-200 dark:border-blue-800">
                <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">Total Stock</p>
                <p class="text-lg font-bold text-blue-900 dark:text-blue-200 leading-tight">{{ editTarget.total_stock ?? 0 }}</p>
              </div>
              <div class="bg-green-50 dark:bg-green-900/20 px-3 py-2.5 rounded-lg border border-green-200 dark:border-green-800">
                <p class="text-xs text-green-600 dark:text-green-400 font-medium">Stock Value</p>
                <p class="text-lg font-bold text-green-900 dark:text-green-200 leading-tight">{{ (Number(editTarget.total_stock ?? 0) * Number(form.p_cost || 0)).toFixed(2) }} MAD</p>
              </div>
            </div>

            <!-- Warehouse Breakdown -->
            <div class="space-y-1.5">
              <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Warehouse Breakdown</h3>
              <div v-if="editTarget.warehouseStocks && editTarget.warehouseStocks.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                      <th class="px-2.5 py-1.5 text-left text-gray-600 dark:text-gray-300 font-medium">Warehouse</th>
                      <th class="px-2.5 py-1.5 text-right text-gray-600 dark:text-gray-300 font-medium">Stock</th>
                      <th class="px-2.5 py-1.5 text-center text-gray-600 dark:text-gray-300 font-medium">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="ws in editTarget.warehouseStocks" :key="ws.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td class="px-2.5 py-1.5 text-gray-800 dark:text-gray-200">{{ ws.warehouse?.wh_name ?? '—' }}</td>
                      <td class="px-2.5 py-1.5 text-right font-mono">{{ Number(ws.stockLevel).toFixed(2) }} {{ editTarget.p_unit ?? 'pcs' }}</td>
                      <td class="px-2.5 py-1.5 text-center">
                        <span
                          class="inline-flex items-center px-2 py-1 rounded text-xs font-medium"
                          :class="Number(ws.stockLevel) > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                        >
                          {{ Number(ws.stockLevel) > 0 ? 'In Stock' : 'Out' }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                {{ $t('products.noStock') ?? 'No stock records yet' }}
              </div>
            </div>

            <!-- Recent Movements -->
            <div class="space-y-1.5 pt-3 border-t border-gray-200 dark:border-gray-700">
              <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Recent Movements</h3>
              <div v-if="stockMouvements.length" class="space-y-1.5 max-h-40 overflow-y-auto">
                <div v-for="mov in stockMouvements" :key="mov.id" class="px-2 py-1.5 bg-gray-50 dark:bg-gray-700/50 rounded text-xs">
                  <p class="font-mono text-gray-800 dark:text-gray-200">
                    {{ mov.direction === 'in' ? '➕' : '➖' }}
                    {{ mov.quantity }} on {{ new Date(mov.created_at).toLocaleDateString() }}
                  </p>
                </div>
              </div>
              <div v-else class="text-xs text-gray-500 dark:text-gray-400">No movements yet</div>
            </div>
          </div>
          <div v-else class="text-sm text-gray-500 dark:text-gray-400">
            {{ $t('products.stockAfterSave') ?? 'Stock information available after saving the product.' }}
          </div>
        </div>

        <!-- Tab: Statistics -->
        <div v-if="currentTab === 3" class="space-y-3 py-2" :style="{ minHeight: tabMinHeight }">
          <div v-if="editTarget" class="space-y-3">
            <!-- Sales Metrics -->
            <div class="space-y-1.5">
              <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Sales Metrics</h4>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                <div class="bg-purple-50 dark:bg-purple-900/20 px-3 py-2 rounded border border-purple-200 dark:border-purple-800">
                  <p class="text-xs text-purple-600 dark:text-purple-400 font-medium">Total Units Sold</p>
                  <p class="text-lg font-bold text-purple-900 dark:text-purple-200 leading-tight">{{ statistics?.sales?.total_units ?? 0 }}</p>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 px-3 py-2 rounded border border-blue-200 dark:border-blue-800">
                  <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">Total Revenue</p>
                  <p class="text-lg font-bold text-blue-900 dark:text-blue-200 leading-tight">{{ (statistics?.sales?.total_revenue ?? 0).toFixed(2) }} MAD</p>
                </div>
                <div class="bg-indigo-50 dark:bg-indigo-900/20 px-3 py-2 rounded border border-indigo-200 dark:border-indigo-800">
                  <p class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">Avg Sale Price</p>
                  <p class="text-lg font-bold text-indigo-900 dark:text-indigo-200 leading-tight">{{ (statistics?.sales?.avg_price ?? 0).toFixed(2) }} MAD</p>
                </div>
                <div class="bg-pink-50 dark:bg-pink-900/20 px-3 py-2 rounded border border-pink-200 dark:border-pink-800">
                  <p class="text-xs text-pink-600 dark:text-pink-400 font-medium">Sale Transactions</p>
                  <p class="text-lg font-bold text-pink-900 dark:text-pink-200 leading-tight">{{ statistics?.sales?.count ?? 0 }}</p>
                </div>
              </div>
            </div>

            <!-- Purchase Metrics -->
            <div class="space-y-1.5 pt-3 border-t border-gray-200 dark:border-gray-700">
              <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Purchase Metrics</h4>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                <div class="bg-emerald-50 dark:bg-emerald-900/20 px-3 py-2 rounded border border-emerald-200 dark:border-emerald-800">
                  <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Total Units Purchased</p>
                  <p class="text-lg font-bold text-emerald-900 dark:text-emerald-200 leading-tight">{{ statistics?.purchases?.total_units ?? 0 }}</p>
                </div>
                <div class="bg-teal-50 dark:bg-teal-900/20 px-3 py-2 rounded border border-teal-200 dark:border-teal-800">
                  <p class="text-xs text-teal-600 dark:text-teal-400 font-medium">Total Cost</p>
                  <p class="text-lg font-bold text-teal-900 dark:text-teal-200 leading-tight">{{ (statistics?.purchases?.total_cost ?? 0).toFixed(2) }} MAD</p>
                </div>
                <div class="bg-cyan-50 dark:bg-cyan-900/20 px-3 py-2 rounded border border-cyan-200 dark:border-cyan-800">
                  <p class="text-xs text-cyan-600 dark:text-cyan-400 font-medium">Avg Purchase Price</p>
                  <p class="text-lg font-bold text-cyan-900 dark:text-cyan-200 leading-tight">{{ (statistics?.purchases?.avg_price ?? 0).toFixed(2) }} MAD</p>
                </div>
                <div class="bg-orange-50 dark:bg-orange-900/20 px-3 py-2 rounded border border-orange-200 dark:border-orange-800">
                  <p class="text-xs text-orange-600 dark:text-orange-400 font-medium">Purchase Transactions</p>
                  <p class="text-lg font-bold text-orange-900 dark:text-orange-200 leading-tight">{{ statistics?.purchases?.count ?? 0 }}</p>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-sm text-gray-500 dark:text-gray-400">
            Statistics available after saving the product.
          </div>
        </div>

        <!-- Tab: Gallery -->
        <div v-if="currentTab === 4" class="space-y-3 py-2" :style="{ minHeight: tabMinHeight }">
          <div v-if="editTarget">
            <!-- Unified grid: images + upload tile -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2.5">
              <div
                v-for="img in editImages"
                :key="img.id"
                class="relative group rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 aspect-square bg-gray-50 dark:bg-gray-900"
              >
                <img :src="img.url" :alt="img.title" class="w-full h-full object-cover" />
                <span
                  v-if="img.isPrimary"
                  class="absolute top-1 left-1 text-[10px] font-bold bg-blue-600 text-white px-1.5 py-0.5 rounded"
                >
                  {{ $t('products.primary') }}
                </span>
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
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
                <svg v-if="!uploadingImage" class="w-7 h-7 mb-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <svg v-else class="w-6 h-6 animate-spin mb-1" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                </svg>
                <span class="text-xs font-medium text-center px-2">
                  {{ uploadingImage ? $t('products.uploadingImage') : $t('products.addImage') }}
                </span>
                <input
                  type="file"
                  multiple
                  accept="image/*"
                  class="hidden"
                  :disabled="uploadingImage"
                  @change="handleImageUpload"
                />
              </label>
            </div>

            <p v-if="!editImages.length" class="text-xs text-gray-400 dark:text-gray-500 text-center mt-2">
              {{ $t('products.noImages') }}
            </p>
          </div>
          <div v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
            {{ $t('products.stockAfterSave') ?? 'Gallery available after saving the product.' }}
          </div>
        </div>
      </form>

      <template #footer>
        <button
          class="px-3.5 py-1.5 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-md transition"
          @click="showModal = false"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          class="px-3.5 py-1.5 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-md transition disabled:opacity-60"
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
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useProductStore } from '@/stores/product'
import { useCategoryStore } from '@/stores/category'
import { useBrandStore } from '@/stores/brand'
import { usePriceListStore } from '@/stores/priceList'
import http from '@/services/http'
import { useExcelExport } from '@/composables/useExcelExport'
import BaseTable from '@/components/BaseTable.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'
import { Str } from '@/utils/helpers'

const { t } = useI18n()
const store = useProductStore()
const categoryStore = useCategoryStore()
const brandStore = useBrandStore()
const priceListStore = usePriceListStore()

const { items } = storeToRefs(store)
const { items: categories } = storeToRefs(categoryStore)
const { items: brands } = storeToRefs(brandStore)
const { items: priceListsOptions } = storeToRefs(priceListStore)

const { exporting, exportExcel } = useExcelExport()

function onExport() {
  exportExcel('/export/products', buildParams())
}

// ── UI state ───────────────────────────────────────────────────────────────
const search = ref('')
const statusFilter = ref('')
const toast = ref(null)
const currentTab = ref(0)

// Template ref on the Info tab — its height is used as the min-height
// for every other tab so the modal doesn't resize when switching tabs.
const infoTabRef = ref<HTMLElement | null>(null)
const tabMinHeight = ref<string>('')

async function measureInfoTab() {
  await nextTick()
  if (infoTabRef.value) {
    tabMinHeight.value = infoTabRef.value.offsetHeight + 'px'
  }
}

let searchTimer = null

const showModal = ref(false)

watch(currentTab, (val) => {
  if (val === 0) measureInfoTab()
})

watch(showModal, (val) => {
  if (val) {
    // Reset min-height so the first render on Info can measure fresh.
    tabMinHeight.value = ''
    measureInfoTab()
  }
})

const showDelete = ref(false)
const saving = ref(false)
const deleting = ref(false)
const uploadingImage = ref(false)
const editTarget = ref(null)
const deleteTarget = ref(null)

// Images for the currently-edited product (reactive copy)
const editImages = ref([])

// Statistics, movements, and price lists for the currently-edited product
const statistics = ref(null)
const stockMouvements = ref([])
const priceListItems = ref([])

// Add-tier inline form state
const tierAdding = ref(false)
const tierSaving = ref(false)
const tierDeletingId = ref<number | null>(null)
const newTier = reactive({
  price_list_id: null as number | null,
  min_qty: 1,
  price_ht: 0,
})

const tabs = [
  { label: t('products.tabInfo') ?? 'Info' },
  { label: t('products.tabTarifs') ?? 'Tarifs' },
  { label: t('products.tabStock') ?? 'Stock' },
  { label: t('products.tabStatistics') ?? 'Statistics' },
  { label: t('products.tabGallery') ?? 'Gallery' },
]

const emptyForm = () => ({
  p_title: '',
  p_code: '',
  p_sku: '',
  p_ean13: '',
  p_imei: '',
  p_purchasePrice: 0,
  p_salePrice: 0,
  p_cost: 0,
  p_taxRate: 20,
  p_unit: 'pièce',
  p_description: '',
  p_long_description: '',
  p_notes: '',
  p_slug: '',
  p_status: true,
  is_ecom: false,
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

const marginPercent = computed(() => {
  if (!form.p_salePrice || !form.p_purchasePrice) return 0
  return Math.round(((form.p_salePrice - form.p_purchasePrice) / form.p_salePrice) * 100)
})

// ── Price-list tier helpers ──────────────────────────────────────────────
const newTierTtc = computed(() => {
  const ht = Number(newTier.price_ht) || 0
  const rate = Number(form.p_taxRate) || 0
  return (ht * (1 + rate / 100)).toFixed(2)
})

const canAddTier = computed(() =>
  !!newTier.price_list_id &&
  Number(newTier.min_qty) >= 1 &&
  Number(newTier.price_ht) > 0,
)

function isListAlreadyUsed(listId: number, minQty: number): boolean {
  return priceListItems.value.some(
    (i: any) => Number(i.price_list_id) === listId && Number(i.min_qty) === Number(minQty),
  )
}

async function reloadPriceListItems() {
  if (!editTarget.value) return
  try {
    const { data } = await http.get(`/products/${editTarget.value.id}/price-lists`)
    priceListItems.value = Array.isArray(data) ? data : data.data ?? []
  } catch (e) {
    console.error('Failed to reload price tiers', e)
  }
}

async function addTier() {
  if (!canAddTier.value || !editTarget.value) return
  tierSaving.value = true
  try {
    await http.post(`/price-lists/${newTier.price_list_id}/items`, {
      items: [
        {
          product_id: editTarget.value.id,
          price_ht: Number(newTier.price_ht),
          min_qty: Number(newTier.min_qty) || 1,
        },
      ],
    })
    await reloadPriceListItems()
    // Reset form
    newTier.price_list_id = null
    newTier.min_qty = 1
    newTier.price_ht = 0
    tierAdding.value = false
    toast.value?.notify('Tarif ajouté', 'success')
  } catch (e: any) {
    const msg = e?.response?.data?.message ?? 'Échec de l\'ajout du tarif'
    toast.value?.notify(msg, 'error')
  } finally {
    tierSaving.value = false
  }
}

async function removeTier(item: any) {
  if (!confirm('Supprimer ce tarif ?')) return
  tierDeletingId.value = item.id
  try {
    await http.delete(`/price-lists/${item.price_list_id}/items/${item.id}`)
    priceListItems.value = priceListItems.value.filter((i: any) => i.id !== item.id)
    toast.value?.notify('Tarif supprimé', 'success')
  } catch (e: any) {
    const msg = e?.response?.data?.message ?? 'Échec de la suppression'
    toast.value?.notify(msg, 'error')
  } finally {
    tierDeletingId.value = null
  }
}

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
  currentTab.value = 0
  Object.assign(form, emptyForm())
  showModal.value = true
}

async function openEdit(row) {
  editTarget.value = row
  editImages.value = [...(row.images ?? [])]
  currentTab.value = 0
  Object.assign(form, {
    p_title: row.p_title,
    p_code: row.p_code ?? '',
    p_sku: row.p_sku ?? '',
    p_ean13: row.p_ean13 ?? '',
    p_imei: row.p_imei ?? '',
    p_purchasePrice: Number(row.p_purchasePrice),
    p_salePrice: Number(row.p_salePrice),
    p_cost: Number(row.p_cost) || 0,
    p_taxRate: Number(row.p_taxRate),
    p_unit: row.p_unit ?? 'pièce',
    p_description: row.p_description ?? '',
    p_long_description: row.p_long_description ?? '',
    p_notes: row.p_notes ?? '',
    p_slug: row.p_slug ?? '',
    p_status: row.p_status,
    is_ecom: row.is_ecom,
    category_id: row.category_id ?? null,
    brand_id: row.brand_id ?? null,
  })

  // Load additional data for tabs
  try {
    const [statsRes, stockRes, pricesRes] = await Promise.all([
      fetch(`/api/products/${row.id}/statistics`),
      fetch(`/api/products/${row.id}/stock-history?per_page=5`),
      fetch(`/api/products/${row.id}/price-lists`),
    ])

    if (statsRes.ok) statistics.value = await statsRes.json()
    if (stockRes.ok) {
      const data = await stockRes.json()
      stockMouvements.value = Array.isArray(data) ? data : data.data ?? []
    }
    if (pricesRes.ok) {
      const data = await pricesRes.json()
      priceListItems.value = Array.isArray(data) ? data : data.data ?? []
    }
  } catch (e) {
    console.error('Error loading product details:', e)
  }

  showModal.value = true
}

function generateSlugFromTitle() {
  if (form.is_ecom && form.p_title && !form.p_slug) {
    // Simple slug generation (if Str helper doesn't exist, use basic implementation)
    form.p_slug = form.p_title
      .toLowerCase()
      .replace(/\s+/g, '-')
      .replace(/[^\w-]/g, '')
  }
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
  const files = e.target.files
  if (!files || !editTarget.value) return
  e.target.value = '' // reset input

  uploadingImage.value = true
  try {
    // Support multiple files
    for (let i = 0; i < files.length; i++) {
      const fd = new FormData()
      fd.append('image', files[i])
      fd.append('isPrimary', editImages.value.length === 0 && i === 0 ? '1' : '0')
      const img = await store.uploadImage(editTarget.value.id, fd)
      editImages.value.push(img)
      if (img.isPrimary) {
        editImages.value.forEach((im) => {
          if (im.id !== img.id) im.isPrimary = false
        })
      }
    }
    toast.value?.notify(`${files.length} image(s) uploaded`, 'success')
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
  categoryStore.fetchAll()
  brandStore.fetchAll()
  priceListStore.fetchAll()
  loadPage()
})
</script>
