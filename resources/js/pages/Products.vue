<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="space-y-5">
      <div class="flex items-start justify-between gap-3 flex-wrap">
        <div class="min-w-0">
          <h2 class="text-[26px] sm:text-[30px] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white truncate">
            {{ $t('products.title') }}
          </h2>
          <p class="text-sm text-[#8A8F9C] dark:text-gray-400 mt-1 hidden sm:block">
            {{ $t('products.subtitle') }}
          </p>
        </div>
        <div class="flex items-center gap-2.5 shrink-0">
          <router-link
            to="/products/trashed"
            class="flex items-center gap-2 px-3.5 sm:px-[18px] py-2.5 border border-[#E1E3E9] dark:border-gray-600 text-gray-900 dark:text-gray-300 text-sm font-semibold rounded-[11px] bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
            :title="$t('products.trashedTitle')"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
              />
            </svg>
            <span class="hidden sm:inline">{{ $t('products.trashedTitle') }}</span>
          </router-link>
          <div v-if="canExport" class="relative">
            <button
              class="flex items-center gap-2 px-3.5 sm:px-[18px] py-2.5 border border-[#E1E3E9] dark:border-gray-600 text-gray-900 dark:text-gray-300 text-sm font-semibold rounded-[11px] bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
              :disabled="exporting"
              @click="exportMenuOpen = !exportMenuOpen"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
              <span class="hidden sm:inline">{{ exporting ? 'Export...' : 'Export' }}</span>
              <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div aria-hidden="true" v-if="exportMenuOpen" class="fixed inset-0 z-20" @click="exportMenuOpen = false" />
            <div
              v-if="exportMenuOpen"
              class="absolute right-0 mt-2 w-60 z-30 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-sm"
            >
              <button
                class="w-full text-left px-4 py-2.5 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                @click="onExport(false)"
              >
                Excel (sans images)
              </button>
              <button
                class="w-full text-left px-4 py-2.5 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 border-t border-gray-100 dark:border-gray-700"
                @click="onExport(true)"
              >
                Excel avec images
                <span class="block text-[11px] text-gray-400 dark:text-gray-500">Photo principale intégrée — plus long</span>
              </button>
            </div>
          </div>
          <button
            class="flex items-center gap-2 px-4 sm:px-5 py-2.5 bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white text-sm font-bold rounded-[11px] shadow-[0_8px_20px_-8px_rgba(124,92,252,0.6)] transition"
            @click="openCreate"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span class="hidden sm:inline">{{ $t('products.add') }}</span>
          </button>
        </div>
      </div>

      <!-- Stat cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
          <div class="w-9 h-9 rounded-[10px] bg-[#EFF1F5] dark:bg-gray-700 flex items-center justify-center mb-3.5">
            <svg class="w-[17px] h-[17px]" fill="none" stroke="#5B6070" stroke-width="1.6" viewBox="0 0 20 20">
              <rect x="3" y="3" width="6" height="6" rx="1.5" /><rect x="11" y="3" width="6" height="6" rx="1.5" />
              <rect x="3" y="11" width="6" height="6" rx="1.5" /><rect x="11" y="11" width="6" height="6" rx="1.5" />
            </svg>
          </div>
          <div class="text-2xl sm:text-[26px] font-extrabold text-gray-900 dark:text-white">{{ statTotal }}</div>
          <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400 mt-0.5">{{ $t('products.statTotal') ?? 'Produits au catalogue' }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
          <div class="w-9 h-9 rounded-[10px] bg-[#FDECEC] dark:bg-[#C6383E]/20 flex items-center justify-center mb-3.5">
            <svg class="w-[17px] h-[17px]" fill="none" stroke="#E5484D" stroke-width="1.8" viewBox="0 0 20 20">
              <line x1="10" y1="5" x2="10" y2="12" /><circle cx="10" cy="15" r="0.8" fill="#E5484D" />
            </svg>
          </div>
          <div class="text-2xl sm:text-[26px] font-extrabold text-[#E5484D]">{{ statOutOfStock }}</div>
          <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400 mt-0.5">{{ $t('products.statOutOfStock') ?? 'Ruptures de stock' }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
          <div class="w-9 h-9 rounded-[10px] bg-[#EAF7F0] dark:bg-[#2FA86B]/20 flex items-center justify-center mb-3.5">
            <svg class="w-[17px] h-[17px]" fill="none" stroke="#2FA86B" stroke-width="1.6" viewBox="0 0 20 20">
              <rect x="4" y="10" width="3" height="7" /><rect x="8.5" y="6" width="3" height="11" /><rect x="13" y="2" width="3" height="15" />
            </svg>
          </div>
          <div class="text-2xl sm:text-[26px] font-extrabold text-gray-900 dark:text-white">{{ statStockValue }}</div>
          <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400 mt-0.5">{{ $t('products.statStockValue') ?? 'Valeur du stock' }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
          <div class="w-9 h-9 rounded-[10px] bg-[#FFF1E6] dark:bg-[#7C5CFC]/20 flex items-center justify-center mb-3.5">
            <svg class="w-[17px] h-[17px]" fill="none" stroke="#7C5CFC" stroke-width="1.8" viewBox="0 0 20 20">
              <path d="M4 12l4 4 8-9" />
            </svg>
          </div>
          <div class="text-2xl sm:text-[26px] font-extrabold text-gray-900 dark:text-white">{{ statActivePercent }}%</div>
          <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400 mt-0.5">{{ $t('products.statActive') ?? 'Produits actifs' }}</div>
        </div>
      </div>

      <!-- Filters — stacked on mobile -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 sm:gap-3 bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-[14px] p-3">
        <div class="relative flex-1 min-w-[220px]">
          <svg class="w-[15px] h-[15px] absolute left-3.5 top-1/2 -translate-y-1/2 text-[#B0B4BE]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 20 20">
            <circle cx="9" cy="9" r="6" /><line x1="14" y1="14" x2="18" y2="18" />
          </svg>
          <input
            :aria-label="$t('products.search')"
            v-model="search"
            type="text"
            :placeholder="$t('products.search')"
            class="w-full pl-9 pr-3.5 py-2.5 text-input rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
          />
        </div>
        <div class="flex flex-wrap gap-2">
          <select
            :aria-label="$t('a11y.filterStatus')"
            v-model="statusFilter"
            class="flex-1 sm:flex-none px-3.5 py-2.5 text-input font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-[#4A4F5B] dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
          >
            <option value="">{{ $t('common.allStatus') }}</option>
            <option value="1">{{ $t('common.active') }}</option>
            <option value="0">{{ $t('common.inactive') }}</option>
          </select>
          <select
            :aria-label="$t('a11y.filterStock')"
            v-model="stockFilter"
            class="flex-1 sm:flex-none px-3.5 py-2.5 text-input font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-[#4A4F5B] dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
            title="Filtrer par disponibilité du stock"
          >
            <option value="">Stock : tous</option>
            <option value="1">En stock</option>
            <option value="0">Rupture</option>
          </select>
          <select
            :aria-label="$t('a11y.filterEcom')"
            v-if="ecomEnabled"
            v-model="ecomFilter"
            class="flex-1 sm:flex-none px-3.5 py-2.5 text-input font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-[#4A4F5B] dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
            title="Filtrer par publication sur la boutique en ligne"
          >
            <option value="">Boutique : tous</option>
            <option value="1">Publié</option>
            <option value="0">Non publié</option>
          </select>
          <select
            :aria-label="$t('a11y.filterPromo')"
            v-model="promoFilter"
            class="flex-1 sm:flex-none px-3.5 py-2.5 text-input font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-[#4A4F5B] dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
            title="Filtrer par promotion active"
          >
            <option value="">Promo : tous</option>
            <option value="1">En promo</option>
            <option value="0">Sans promo</option>
          </select>
          <!-- Colonnes affichees (vue liste uniquement) -->
          <div v-if="viewMode === 'list'" class="relative hidden sm:block shrink-0">
            <button
              type="button"
              class="flex items-center gap-2 px-3.5 py-2.5 text-[13px] font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-[#4A4F5B] dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition"
              :title="$t('products.columnsTitle') ?? 'Afficher / masquer des colonnes'"
              @click="columnsMenuOpen = !columnsMenuOpen"
            >
              <svg class="w-[15px] h-[15px] opacity-70" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 20 20">
                <rect x="3" y="3" width="14" height="14" rx="2" /><line x1="8" y1="3" x2="8" y2="17" /><line x1="13" y1="3" x2="13" y2="17" />
              </svg>
              <span>{{ $t('products.columns') ?? 'Colonnes' }}</span>
              <span class="text-[11px] font-bold text-[#7C5CFC]">{{ columns.length }}/{{ allColumns.length }}</span>
            </button>
            <div aria-hidden="true" v-if="columnsMenuOpen" class="fixed inset-0 z-20" @click="columnsMenuOpen = false" />
            <div
              v-if="columnsMenuOpen"
              class="absolute right-0 mt-2 w-64 z-30 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-sm"
            >
              <div class="max-h-[320px] overflow-y-auto py-1">
                <label
                  v-for="col in allColumns"
                  :key="col.key"
                  class="flex items-center gap-2.5 px-4 py-1.5 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                >
                  <input
                    type="checkbox"
                    class="rounded border-gray-300 text-[#7C5CFC] focus:ring-[#7C5CFC]"
                    :checked="isColumnVisible(col.key)"
                    :disabled="isColumnVisible(col.key) && columns.length <= 1"
                    @change="toggleColumn(col.key)"
                  />
                  <span class="truncate">{{ col.menuLabel ?? col.label }}</span>
                </label>
              </div>
              <div class="flex border-t border-gray-100 dark:border-gray-700">
                <button
                  type="button"
                  class="flex-1 px-4 py-2.5 text-[13px] font-semibold text-[#7C5CFC] hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40"
                  :disabled="columns.length === allColumns.length"
                  @click="showAllColumns"
                >
                  {{ $t('products.columnsShowAll') }}
                </button>
                <button
                  type="button"
                  class="flex-1 px-4 py-2.5 text-[13px] font-semibold text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 border-l border-gray-100 dark:border-gray-700"
                  @click="resetColumns"
                >
                  {{ $t('products.columnsReset') }}
                </button>
              </div>
            </div>
          </div>
          <div class="hidden sm:flex items-center bg-[#F0F1F4] dark:bg-gray-700 rounded-[10px] p-[3px] gap-0.5 shrink-0">
            <button
              type="button"
              class="w-[34px] h-[30px] rounded-lg flex items-center justify-center transition"
              :class="viewMode === 'list' ? 'bg-white dark:bg-gray-800 shadow-sm' : ''"
              :title="$t('products.viewList') ?? 'Vue liste'"
              @click="viewMode = 'list'"
            >
              <svg class="w-[15px] h-[15px]" fill="none" :stroke="viewMode === 'list' ? '#181B22' : '#9599A6'" stroke-width="1.8" viewBox="0 0 20 20">
                <line x1="3" y1="6" x2="17" y2="6" /><line x1="3" y1="10" x2="17" y2="10" /><line x1="3" y1="14" x2="17" y2="14" />
              </svg>
            </button>
            <button
              type="button"
              class="w-[34px] h-[30px] rounded-lg flex items-center justify-center transition"
              :class="viewMode === 'grid' ? 'bg-white dark:bg-gray-800 shadow-sm' : ''"
              :title="$t('products.viewGrid') ?? 'Vue grille'"
              @click="viewMode = 'grid'"
            >
              <svg class="w-[15px] h-[15px]" fill="none" :stroke="viewMode === 'grid' ? '#181B22' : '#9599A6'" stroke-width="1.8" viewBox="0 0 20 20">
                <rect x="3" y="3" width="6" height="6" rx="1" /><rect x="11" y="3" width="6" height="6" rx="1" />
                <rect x="3" y="11" width="6" height="6" rx="1" /><rect x="11" y="11" width="6" height="6" rx="1" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Grid view -->
    <div v-if="viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
      <div
        v-for="row in items"
        :key="row.id"
        class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 cursor-pointer hover:shadow-md transition"
        @click="openEdit(row)"
      >
        <div class="w-full h-[110px] rounded-xl overflow-hidden bg-[#EFF1F5] dark:bg-gray-700 mb-3 flex items-center justify-center">
          <img
            v-if="row.primary_image || (row.images && row.images.length)"
            :src="(row.primary_image || row.images[0]).url"
            :alt="row.p_title"
            class="w-full h-full object-cover"
            @error="($event: Event) => (($event.target as HTMLImageElement).style.display = 'none')"
          />
          <svg v-else class="w-9 h-9 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5M3.75 3.75h16.5A2.25 2.25 0 0122.5 6v12a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 18V6a2.25 2.25 0 012.25-2.25z"
            />
          </svg>
        </div>
        <div class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ row.p_title }}</div>
        <div class="text-xs text-[#9599A6] mt-0.5 font-mono truncate">{{ row.p_code }}</div>
        <div class="flex items-center gap-1.5 mt-2.5 flex-wrap">
          <span v-if="row.category" class="bg-[#F1ECFC] text-[#7C5CFC] text-[11px] font-semibold px-2 py-0.5 rounded-full">
            {{ row.category.ctg_title }}
          </span>
          <span v-if="row.brand" class="text-xs text-[#9599A6]">{{ row.brand.br_title }}</span>
        </div>
        <div class="flex items-center justify-between mt-3">
          <span class="text-[15px] font-extrabold text-gray-900 dark:text-white">{{ Number(row.p_salePrice).toFixed(2) }} MAD</span>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold" :class="statusBadgeClass(row)">
            {{ statusBadgeLabel(row) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Table (list view) -->
    <BaseTable v-else :columns="columns" :rows="items" :empty-text="$t('products.notFound')">
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
          class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#F1ECFC] text-[#7C5CFC]"
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
      <template #cell-p_status="{ row }">
        <span
          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold"
          :class="statusBadgeClass(row)"
        >
          {{ statusBadgeLabel(row) }}
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
      <template #cell-id="{ value }">
        <span class="font-mono text-xs text-gray-400 dark:text-gray-500">{{ value }}</span>
      </template>
      <template #cell-p_sku="{ value }">
        <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ value || '—' }}</span>
      </template>
      <template #cell-p_ean13="{ value }">
        <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ value || '—' }}</span>
      </template>
      <template #cell-p_imei="{ value }">
        <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ value || '—' }}</span>
      </template>
      <!-- Prix d'achat / cout : le serveur retire ces champs du payload
           pour les utilisateurs sans products.view_cost, d'ou le garde-fou
           sur `undefined` meme si la colonne leur est deja masquee. -->
      <template #cell-p_purchasePrice="{ value }">
        <span v-if="value != null" class="font-mono text-sm text-gray-700 dark:text-gray-300">{{ Number(value).toFixed(2) }}</span>
        <span v-else class="text-gray-300 text-xs">—</span>
      </template>
      <template #cell-p_cost="{ value }">
        <span v-if="value != null" class="font-mono text-sm text-gray-700 dark:text-gray-300">{{ Number(value).toFixed(2) }}</span>
        <span v-else class="text-gray-300 text-xs">—</span>
      </template>
      <template #cell-p_taxRate="{ value }">
        <span class="font-mono text-sm text-gray-600 dark:text-gray-400">{{ Number(value ?? 0).toFixed(2) }} %</span>
      </template>
      <template #cell-p_unit="{ value }">
        <span class="text-sm text-gray-600 dark:text-gray-400">{{ value || '—' }}</span>
      </template>
      <template #cell-is_ecom="{ row }">
        <span
          class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold"
          :class="row.is_ecom ? 'bg-[#EAF7F0] text-[#2FA86B]' : 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-400'"
        >
          {{ row.is_ecom ? $t('products.ecomYes') : $t('products.ecomNo') }}
        </span>
      </template>
      <template #cell-p_slug="{ value }">
        <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ value || '—' }}</span>
      </template>
      <template #cell-p_description="{ value }">
        <span class="text-xs text-gray-500 dark:text-gray-400" :title="value">{{ truncateText(value) || '—' }}</span>
      </template>
      <template #cell-p_long_description="{ value }">
        <span class="text-xs text-gray-500 dark:text-gray-400" :title="value">{{ truncateText(value) || '—' }}</span>
      </template>
      <template #cell-p_notes="{ value }">
        <span class="text-xs text-gray-500 dark:text-gray-400 italic" :title="value">{{ truncateText(value) || '—' }}</span>
      </template>
      <template #cell-created_at="{ value }">
        <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ value ? fmtDate(value) : '—' }}</span>
      </template>
      <template #cell-updated_at="{ value }">
        <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ value ? fmtDate(value) : '—' }}</span>
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
            class="p-1.5 rounded-lg text-blue-500 hover:bg-blue-50 transition disabled:opacity-50"
            :title="$t('products.duplicate')"
            :disabled="duplicatingId === row.id"
            @click="onDuplicate(row)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
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
          <div class="flex gap-0.5 overflow-x-auto scrollbar-thin overflow-hidden">
            <button
              v-for="(tab, idx) in tabs"
              :key="idx"
              type="button"
              class="relative whitespace-nowrap py-1.5 px-2.5 sm:px-3 text-sm font-medium transition-colors border-b-2 -mb-px"
              :class="
                currentTab === idx
                  ? 'border-[#7C5CFC] text-[#7C5CFC] dark:text-[#A78BFA]'
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
          <ProductInfoTab
            :categories="categories"
            :brands="brands"
            :ecom-enabled="ecomEnabled"
            :imei-enabled="imeiEnabled"
            @slug-from-title="generateSlugFromTitle"
          />
        </div>

        <!-- Tab: Tarifs (Pricing) -->
        <div v-if="currentTab === 1" class="space-y-4 py-2" :style="{ minHeight: tabMinHeight }">
          <ProductPricingTab
            v-model:tier-adding="tierAdding"
            :has-product="!!editTarget"
            :price-lists="priceListsOptions"
            :tiers="priceListItems"
            :tier-saving="tierSaving"
            :tier-deleting-id="tierDeletingId"
            :new-tier-ttc="newTierTtc"
            :can-add-tier="canAddTier"
            :is-list-already-used="isListAlreadyUsed"
            :margin-percent="marginPercent"
            @add="addTier"
            @remove="removeTier"
          />
        </div>

        <!-- Tab: Stock -->
        <div v-if="currentTab === 2" class="space-y-3 py-2" :style="{ minHeight: tabMinHeight }">
          <ProductStockTab
            :product="editTarget"
            :warehouse-stocks="warehouseStocksList"
            :movements="stockMouvements"
            :variants="productVariants"
            :cost="form.p_cost"
            :variants-enabled="variantsEnabled"
          />
        </div>

        <!-- Tab: Statistics -->
        <div v-if="currentTab === 3" class="space-y-3 py-2" :style="{ minHeight: tabMinHeight }">
          <ProductStatsTab :statistics="statistics" :has-product="!!editTarget" />
        </div>

        <!-- Tab: Gallery -->
        <div v-if="currentTab === 4" class="space-y-3 py-2" :style="{ minHeight: tabMinHeight }">
          <ProductMediaTab
            v-model:new-video-title="newVideoTitle"
            v-model:new-video-url="newVideoUrl"
            :has-product="!!editTarget"
            :images="editImages"
            :videos="editVideos"
            :documents="editDocuments"
            :uploading-image="uploadingImage"
            :uploading-document="uploadingDocument"
            :adding-video="addingVideo"
            @upload-image="handleImageUpload"
            @set-primary="doSetPrimary"
            @delete-image="doDeleteImage"
            @add-video="handleAddVideo"
            @delete-video="doDeleteVideo"
            @upload-document="handleDocumentUpload"
            @delete-document="doDeleteDocument"
          />
        </div>
        <!-- Tab: Variantes -->
        <div v-if="currentTab === 5 && variantsEnabled" class="space-y-3 py-2">
          <ProductVariantsTab
            :variants="productVariants"
            @generate="applyGenerated"
            @add="addVariantRow"
            @remove="removeVariant"
            @touch="variantsDirty = true"
          />
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
          class="px-3.5 py-1.5 text-sm font-semibold bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white rounded-md transition disabled:opacity-60"
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
import { useAuthStore } from '@/stores/authStore'
import http from '@/services/http'
import { useVariantOptionsStore } from '@/stores/useVariantOptionsStore'
import { useExcelExport } from '@/composables/useExcelExport'
import { useTaxSettings } from '@/composables/useTaxSettings'
import { provideProductEdit } from '@/composables/useProductEditContext'
import { useProductColumns } from '@/composables/useProductColumns'
import { useProductMedia } from '@/composables/useProductMedia'
import { useProductPriceTiers } from '@/composables/useProductPriceTiers'
import { useProductVariants } from '@/composables/useProductVariants'
import BaseTable from '@/components/BaseTable.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'
import ProductInfoTab from '@/components/products/ProductInfoTab.vue'
import ProductMediaTab from '@/components/products/ProductMediaTab.vue'
import ProductPricingTab from '@/components/products/ProductPricingTab.vue'
import ProductStatsTab from '@/components/products/ProductStatsTab.vue'
import ProductStockTab from '@/components/products/ProductStockTab.vue'
import ProductVariantsTab from '@/components/products/ProductVariantsTab.vue'
import { useFormat } from '@/composables/useFormat'

const { t } = useI18n()
const { date: fmtDate } = useFormat()
const store = useProductStore()
const categoryStore = useCategoryStore()
const brandStore = useBrandStore()
const priceListStore = usePriceListStore()
const auth = useAuthStore()
const variantStore = useVariantOptionsStore()

// E-commerce module gating: the "Publier dans la boutique" toggle and slug
// field only appear when the tenant has the ecom feature enabled (driven
// by the central tenants.ecom_enabled flag, surfaced via auth.hasModule).
const ecomEnabled = computed(() => auth.hasModule('ecom'))
const variantsEnabled = computed(() => auth.hasModule('variants'))
const imeiEnabled = computed(() => auth.hasModule('imei'))
// Best-effort hint for the storefront URL shown beside the toggle.
const tenantDomain = computed(() => {
  if (typeof window === 'undefined') return ''
  // Strip a leading "shop." if we're already on the storefront, then drop
  // any "www." for cleanliness — yields e.g. "teliphoni.o3app.ma".
  return window.location.hostname.replace(/^shop\./, '').replace(/^www\./, '')
})

const { items } = storeToRefs(store)
const { items: categories } = storeToRefs(categoryStore)
const { items: brands } = storeToRefs(brandStore)
const { items: priceListsOptions } = storeToRefs(priceListStore)

const { exporting, exportExcel, canExport } = useExcelExport()
const { getTaxRate, initTaxSettings } = useTaxSettings()

const exportMenuOpen = ref(false)

function onExport(withImages = false) {
  exportMenuOpen.value = false
  const params: Record<string, string> = buildParams()
  if (withImages) params.with_images = '1'
  exportExcel('/export/products', params)
}

// ── UI state ───────────────────────────────────────────────────────────────
const search = ref('')
const statusFilter = ref('')
const stockFilter = ref('')
const ecomFilter = ref('')
const promoFilter = ref('')
const viewMode = ref<'grid' | 'list'>('grid')
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
const editTarget = ref(null)
const deleteTarget = ref(null)
const duplicatingId = ref(null)

// Images, videos et documents du produit ouvert. Les noms exposes au gabarit
// gardent leur prefixe `edit*` : le composable est neuf, pas le vocabulaire.
const {
  images: editImages,
  videos: editVideos,
  documents: editDocuments,
  uploadingImage,
  uploadingDocument,
  addingVideo,
  newVideoTitle,
  newVideoUrl,
  reset: resetMedia,
  handleImageUpload,
  setPrimary: doSetPrimary,
  deleteImage: doDeleteImage,
  addVideo: handleAddVideo,
  deleteVideo: doDeleteVideo,
  handleDocumentUpload,
  deleteDocument: doDeleteDocument,
} = useProductMedia({
  product: () => editTarget.value,
  notify: (message, level) => (toast.value as any)?.notify(message, level),
})

// Statistics, movements, and price lists for the currently-edited product
const statistics = ref(null)
const stockMouvements = ref([])

// Paliers tarifaires du produit ouvert. Le taux de TVA passe par un getter :
// `form` est declare plus bas, et le TTC affiche doit suivre ce que
// l'utilisateur tape dans l'onglet Infos.
const {
  items: priceListItems,
  adding: tierAdding,
  saving: tierSaving,
  deletingId: tierDeletingId,
  newTier,
  newTierTtc,
  canAdd: canAddTier,
  isListAlreadyUsed,
  reload: reloadPriceListItems,
  add: addTier,
  remove: removeTier,
} = useProductPriceTiers({
  product: () => editTarget.value,
  taxRate: () => form.p_taxRate,
  notify: (message, level) => (toast.value as any)?.notify(message, level),
})

// Declinaisons du produit, inertes tant que le module n'est pas actif.
const {
  variants: productVariants,
  dirty: variantsDirty,
  load: loadVariants,
  save: saveVariants,
  addRow: addVariantRow,
  remove: removeVariant,
  applyGenerated,
  reset: resetVariants,
} = useProductVariants(() => variantsEnabled.value)

// Resolve warehouse stocks regardless of JSON casing (snake_case by default,
// but left camelCase tolerant in case the serializer changes).
const warehouseStocksList = computed(() => {
  const t: any = editTarget.value
  if (!t) return []
  return t.warehouse_stocks ?? t.warehouseStocks ?? []
})

const tabs = computed(() => [
  { label: t('products.tabInfo') ?? 'Info' },
  { label: t('products.tabTarifs') ?? 'Tarifs' },
  { label: t('products.tabStock') ?? 'Stock' },
  { label: t('products.tabStatistics') ?? 'Statistics' },
  { label: t('products.tabGallery') ?? 'Media' },
  ...(variantsEnabled.value ? [{ label: 'Variantes' }] : []),
])

const emptyForm = () => ({
  p_title: '',
  p_code: '',
  p_sku: '',
  p_ean13: '',
  p_imei: '',
  p_purchasePrice: 0,
  p_salePrice: 0,
  p_cost: 0,
  // Tenant-configured rate (invoice settings), not a hardcoded 20.
  p_taxRate: getTaxRate.value,
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

// Les onglets Infos et Tarifs ecrivent dans ce formulaire : il leur est
// fourni par injection plutot que par prop. Voir useProductEditContext.
provideProductEdit({ form, newTier })

// ── Colonnes de la liste ─────────────────────────────────────────────────
// Catalogue des colonnes, filtrage par droits/modules et selection de
// l'utilisateur : le tout vit dans useProductColumns.
const {
  allColumns,
  columns,
  columnsMenuOpen,
  isColumnVisible,
  toggleColumn,
  showAllColumns,
  resetColumns,
} = useProductColumns()

// Coupe les champs texte libres pour garder les lignes lisibles.
function truncateText(value: unknown, max = 60): string {
  const text = String(value ?? '').trim()
  if (!text) return ''
  return text.length > max ? `${text.slice(0, max)}…` : text
}

const marginPercent = computed(() => {
  if (!form.p_salePrice || !form.p_purchasePrice) return 0
  return Math.round(((form.p_salePrice - form.p_purchasePrice) / form.p_salePrice) * 100)
})

// ── Stat cards (best-effort — derived from the currently loaded page of
// items, since no dedicated aggregate endpoint is available here) ─────────
const statTotal = computed(() => store.meta?.total ?? items.value.length)
const statOutOfStock = computed(() => items.value.filter((p: any) => Number(p.total_stock ?? 0) <= 0).length)
const statStockValue = computed(() => {
  const total = items.value.reduce(
    (sum: number, p: any) => sum + Number(p.p_salePrice ?? 0) * Math.max(Number(p.total_stock ?? 0), 0),
    0,
  )
  return `${new Intl.NumberFormat('fr-MA').format(Math.round(total))} MAD`
})
const statActivePercent = computed(() => {
  if (!items.value.length) return 0
  const active = items.value.filter((p: any) => p.p_status).length
  return Math.round((active / items.value.length) * 100)
})

function statusBadgeLabel(row: any) {
  if (!row.p_status) return t('common.inactive')
  if (Number(row.total_stock ?? 0) <= 0) return t('products.outOfStock') ?? 'Rupture'
  return t('common.active')
}
function statusBadgeClass(row: any) {
  if (!row.p_status) return 'bg-[#F0F1F4] text-[#7A7F8C]'
  if (Number(row.total_stock ?? 0) <= 0) return 'bg-[#FDECEC] text-[#C6383E]'
  return 'bg-[#E5F7ED] text-[#1F8A50]'
}

function buildParams(): Record<string, string> {
  const p: Record<string, string> = {}
  if (search.value.trim()) p.search = search.value.trim()
  if (statusFilter.value !== '') p.status = statusFilter.value
  if (stockFilter.value !== '') p.in_stock = stockFilter.value
  if (ecomFilter.value !== '') p.is_ecom = ecomFilter.value
  if (promoFilter.value !== '') p.on_promo = promoFilter.value
  return p
}

function loadPage(page = 1) {
  const p = buildParams()
  store.params.page = page
  // Assign every filter explicitly (not just the ones present in `p`) so a
  // cleared field actually clears the stored param instead of leaving a
  // stale value behind — usePaginatedApi drops null/'' before the request.
  store.params.search = p.search ?? null
  store.params.status = p.status ?? null
  store.params.in_stock = p.in_stock ?? null
  store.params.is_ecom = p.is_ecom ?? null
  store.params.on_promo = p.on_promo ?? null
  store.fetchPage(page)
}

function onPageChange(page) {
  loadPage(page)
}

watch([search, statusFilter, stockFilter, ecomFilter, promoFilter], () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadPage(1), 350)
})

// ── CRUD ───────────────────────────────────────────────────────────────────
function openCreate() {
  editTarget.value = null
  resetMedia()
  resetVariants()
  currentTab.value = 0
  Object.assign(form, emptyForm())
  showModal.value = true
}

async function openEdit(row) {
  editTarget.value = row
  resetMedia(row)
  resetVariants(row)
  // Les declinaisons deja enregistrees doivent etre a l ecran avant toute
  // sauvegarde : `variants/sync` supprime ce qu on ne lui renvoie pas.
  loadVariants(row.id)
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

  // Load additional data for tabs — use the authenticated http client
  // (bearer token is attached via interceptor). Native fetch() would
  // return 401 silently and leave the arrays empty.
  // Also reload the product itself via /products/{id} to get the full
  // relation graph (warehouseStocks.warehouse, priceListItems, etc.)
  // that the paginated list endpoint does not eager-load.
  try {
    const [productRes, statsRes, stockRes, pricesRes] = await Promise.allSettled([
      http.get(`/products/${row.id}`),
      http.get(`/products/${row.id}/statistics`),
      http.get(`/products/${row.id}/stock-history`, { params: { per_page: 20 } }),
      http.get(`/products/${row.id}/price-lists`),
    ])

    if (productRes.status === 'fulfilled' && productRes.value.data) {
      // Merge: preserve list-level computed fields (e.g. total_stock) if the
      // show endpoint omits them, but prefer the fresh, fully-loaded relations.
      editTarget.value = { ...row, ...productRes.value.data }
    }
    if (statsRes.status === 'fulfilled') {
      statistics.value = statsRes.value.data
    }
    if (stockRes.status === 'fulfilled') {
      const data = stockRes.value.data
      stockMouvements.value = Array.isArray(data) ? data : data.data ?? []
    }
    if (pricesRes.status === 'fulfilled') {
      const data = pricesRes.value.data
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
    let savedId
    if (editTarget.value) {
      await store.update(editTarget.value.id, form)
      savedId = editTarget.value.id
      toast.value?.notify(t('products.updated'), 'success')
    } else {
      const res = await store.create(form)
      savedId = res?.id
      toast.value?.notify(t('products.created'), 'success')
    }
    if (savedId) await saveVariants(savedId)
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

async function onDuplicate(row) {
  duplicatingId.value = row.id
  try {
    await store.duplicate(row.id)
    toast.value?.notify(t('products.duplicated'), 'success')
  } catch {
    toast.value?.notify(t('products.duplicateFailed'), 'error')
  } finally {
    duplicatingId.value = null
  }
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

onMounted(() => {
  categoryStore.fetchAll()
  brandStore.fetchAll()
  priceListStore.fetchAll()
  initTaxSettings()
  loadPage()
})
</script>
