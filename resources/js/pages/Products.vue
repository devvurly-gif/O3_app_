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
            <div v-if="exportMenuOpen" class="fixed inset-0 z-20" @click="exportMenuOpen = false" />
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
            class="w-full pl-9 pr-3.5 py-2.5 text-sm rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
          />
        </div>
        <div class="flex flex-wrap gap-2">
          <select
            :aria-label="$t('a11y.filterStatus')"
            v-model="statusFilter"
            class="flex-1 sm:flex-none px-3.5 py-2.5 text-[13px] font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-[#4A4F5B] dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
          >
            <option value="">{{ $t('common.allStatus') }}</option>
            <option value="1">{{ $t('common.active') }}</option>
            <option value="0">{{ $t('common.inactive') }}</option>
          </select>
          <select
            :aria-label="$t('a11y.filterStock')"
            v-model="stockFilter"
            class="flex-1 sm:flex-none px-3.5 py-2.5 text-[13px] font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-[#4A4F5B] dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
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
            class="flex-1 sm:flex-none px-3.5 py-2.5 text-[13px] font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-[#4A4F5B] dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
            title="Filtrer par publication sur la boutique en ligne"
          >
            <option value="">Boutique : tous</option>
            <option value="1">Publié</option>
            <option value="0">Non publié</option>
          </select>
          <select
            :aria-label="$t('a11y.filterPromo')"
            v-model="promoFilter"
            class="flex-1 sm:flex-none px-3.5 py-2.5 text-[13px] font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 text-[#4A4F5B] dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
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
            <div v-if="columnsMenuOpen" class="fixed inset-0 z-20" @click="columnsMenuOpen = false" />
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
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <!-- Title (full row) -->
            <div class="sm:col-span-2 lg:col-span-3">
              <label for="products-p-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >{{ $t('common.name') }} <span class="text-red-500">*</span></label
              >
              <input
                id="products-p-title"
                v-model="form.p_title"
                type="text"
                required
                :placeholder="$t('products.titlePlaceholder')"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                @input="generateSlugFromTitle"
              />
            </div>

            <!-- Code -->
            <div>
              <label for="products-p-code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.code') }}</label>
              <input
                id="products-p-code"
                v-model="form.p_code"
                type="text"
                :placeholder="$t('products.codePlaceholder')"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
              />
            </div>

            <!-- SKU -->
            <div>
              <label for="products-p-sku" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.sku') }}</label>
              <input
                id="products-p-sku"
                v-model="form.p_sku"
                type="text"
                :placeholder="$t('products.skuPlaceholder')"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
              />
              <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $t('products.skuAuto') ?? 'Auto-generated if empty' }}</p>
            </div>

            <!-- EAN13 -->
            <div>
              <label for="products-p-ean13" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.ean') }}</label>
              <input
                id="products-p-ean13"
                v-model="form.p_ean13"
                type="text"
                :placeholder="$t('products.eanPlaceholder')"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
              />
            </div>

            <!-- IMEI — only for tenants tracking serial numbers -->
            <div v-if="imeiEnabled" class="sm:col-span-2 lg:col-span-3">
              <label for="products-p-imei" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IMEI</label>
              <input
                id="products-p-imei"
                v-model="form.p_imei"
                type="text"
                placeholder="Device IMEI..."
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <!-- Description -->
            <div>
              <label for="products-p-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.description') }}</label>
              <textarea
                id="products-p-description"
                v-model="form.p_description"
                rows="3"
                placeholder="…"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
              />
            </div>

            <!-- Long Description (E-commerce) -->
            <div>
              <label for="products-p-long-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.longDescription') ?? 'Long Description' }}</label>
              <textarea
                id="products-p-long-description"
                v-model="form.p_long_description"
                rows="3"
                placeholder="E-commerce description…"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
              />
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label for="products-p-notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
            <textarea
              id="products-p-notes"
              v-model="form.p_notes"
              rows="2"
              placeholder="Internal notes…"
              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
            />
          </div>

          <!-- Category / Brand / Slug -->
          <div class="pt-3 border-t border-gray-200 dark:border-gray-700 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              <!-- Category -->
              <div>
                <label for="products-category-id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.category') }}</label>
                <select
                  id="products-category-id"
                  v-model="form.category_id"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                >
                  <option :value="null">—</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                    {{ cat.ctg_title }}
                  </option>
                </select>
              </div>

              <!-- Brand -->
              <div>
                <label for="products-brand-id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.brand') }}</label>
                <select
                  id="products-brand-id"
                  v-model="form.brand_id"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                >
                  <option :value="null">—</option>
                  <option v-for="br in brands" :key="br.id" :value="br.id">
                    {{ br.br_title }}
                  </option>
                </select>
              </div>

              <!-- E-commerce Slug — only when ecom module is enabled AND product is flagged for the store -->
              <div v-if="ecomEnabled && form.is_ecom">
                <label for="products-p-slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.slug') ?? 'Slug' }}</label>
                <input
                  id="products-p-slug"
                  v-model="form.p_slug"
                  type="text"
                  :placeholder="$t('products.slugPlaceholder') ?? 'Auto-généré depuis le titre'"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                />
                <p class="mt-1 text-[11px] text-gray-400">URL de la fiche produit dans la boutique en ligne.</p>
              </div>
            </div>

            <!-- Publish to Online Store — only visible when the tenant has the ecom module enabled -->
            <div v-if="ecomEnabled" class="flex items-start gap-2 pt-1 p-3 rounded-lg bg-indigo-50/50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800">
              <input
                id="product-ecom"
                v-model="form.is_ecom"
                type="checkbox"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
              />
              <label for="product-ecom" class="flex-1 cursor-pointer">
                <span class="text-sm font-medium text-indigo-900 dark:text-indigo-200 flex items-center gap-1.5">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016A3.001 3.001 0 0021 9.349m-18 0h18" />
                  </svg>
                  Publier dans la boutique en ligne
                </span>
                <p class="text-[11px] text-indigo-700/70 dark:text-indigo-300/70 mt-0.5">
                  Le produit sera visible et achetable sur shop.{{ tenantDomain || '[domaine]' }}.
                </p>
              </label>
            </div>
          </div>

          <!-- Status -->
          <div class="flex items-center gap-2 pt-1">
            <input
              id="product-status"
              v-model="form.p_status"
              type="checkbox"
              class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-[#7C5CFC] focus:ring-[#7C5CFC]"
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
                <label for="products-p-purchaseprice" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                  >{{ $t('products.purchasePrice') }} <span class="text-red-500">*</span></label
                >
                <input
                  id="products-p-purchaseprice"
                  v-model.number="form.p_purchasePrice"
                  type="number"
                  min="0"
                  step="0.01"
                  required
                  placeholder="0.00"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                />
              </div>

              <!-- Sale Price -->
              <div>
                <label for="products-p-saleprice" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                  >{{ $t('products.salePrice') }} <span class="text-red-500">*</span></label
                >
                <input
                  id="products-p-saleprice"
                  v-model.number="form.p_salePrice"
                  type="number"
                  min="0"
                  step="0.01"
                  required
                  placeholder="0.00"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                />
              </div>

              <!-- Cost Price -->
              <div>
                <label for="products-p-cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.costPrice') ?? 'Cost Price' }}</label>
                <input
                  id="products-p-cost"
                  v-model.number="form.p_cost"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="0.00"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                />
              </div>

              <!-- Tax Rate -->
              <div>
                <label for="products-p-taxrate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.taxRate') }}</label>
                <input
                  id="products-p-taxrate"
                  v-model.number="form.p_taxRate"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  placeholder="20"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                />
              </div>

              <!-- Unit -->
              <div class="sm:col-span-2 lg:col-span-1">
                <label for="products-p-unit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('products.unit') }}</label>
                <input
                  id="products-p-unit"
                  v-model="form.p_unit"
                  type="text"
                  :placeholder="$t('products.unitPlaceholder')"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                />
              </div>
            </div>

            <!-- Margin Indicator -->
            <div v-if="form.p_salePrice > 0 && form.p_purchasePrice > 0" class="px-3 py-2 bg-[#F1ECFC] dark:bg-[#7C5CFC]/20 border border-[#E4D9FE] dark:border-[#4C3999] rounded text-sm">
              <p class="text-blue-800 dark:text-blue-200">
                <span class="font-semibold">Margin:</span>
                {{ marginPercent }}%
                <span :class="marginPercent >= 20 ? 'text-green-600 dark:text-green-400' : 'text-[#6D4CE0] dark:text-[#A78BFA]'">
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
                class="text-xs px-2.5 py-1 rounded-md bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white font-medium transition disabled:opacity-50"
                @click="tierAdding = !tierAdding"
              >
                {{ tierAdding ? 'Annuler' : '+ Ajouter un tarif' }}
              </button>
            </div>

            <!-- Inline add-tier form -->
            <div
              v-if="editTarget && tierAdding"
              class="p-2.5 bg-[#F1ECFC] dark:bg-[#7C5CFC]/20 border border-[#E4D9FE] dark:border-[#4C3999] rounded-lg grid grid-cols-1 sm:grid-cols-4 gap-2"
            >
              <div class="sm:col-span-2">
                <label for="products-newtier-price-list-id" class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Grille</label>
                <select
                  id="products-newtier-price-list-id"
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
                <label for="products-newtier-min-qty" class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Qté min</label>
                <input
                  id="products-newtier-min-qty"
                  v-model.number="newTier.min_qty"
                  type="number"
                  min="1"
                  class="w-full px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 text-sm font-mono bg-white dark:bg-gray-800"
                />
              </div>
              <div>
                <label for="products-newtier-price-ht" class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Prix HT</label>
                <input
                  id="products-newtier-price-ht"
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
              <div class="bg-[#F1ECFC] dark:bg-[#7C5CFC]/20 px-3 py-2.5 rounded-lg border border-[#E4D9FE] dark:border-[#4C3999]">
                <p class="text-xs text-[#7C5CFC] dark:text-[#A78BFA] font-medium">Total Stock</p>
                <p class="text-lg font-bold text-blue-900 dark:text-blue-200 leading-tight">{{ editTarget.total_stock ?? 0 }}</p>
              </div>
              <div class="bg-green-50 dark:bg-green-900/20 px-3 py-2.5 rounded-lg border border-green-200 dark:border-green-800">
                <p class="text-xs text-green-600 dark:text-green-400 font-medium">Stock Value</p>
                <p class="text-lg font-bold text-green-900 dark:text-green-200 leading-tight">{{ (Number(editTarget.total_stock ?? 0) * Number(form.p_cost || 0)).toFixed(2) }} MAD</p>
              </div>
            </div>

            <!-- Warehouse Breakdown -->
            <div class="space-y-1.5">
              <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                {{ variantsEnabled && productVariants.length ? 'Stock par Variante' : 'Warehouse Breakdown' }}
              </h3>

              <!-- WITH VARIANTS -->
              <div v-if="variantsEnabled && productVariants.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                      <th class="px-2.5 py-1.5 text-left text-gray-600 dark:text-gray-300 font-medium">Variante</th>
                      <th class="px-2.5 py-1.5 text-left text-gray-600 dark:text-gray-300 font-medium">SKU</th>
                      <th class="px-2.5 py-1.5 text-right text-gray-600 dark:text-gray-300 font-medium">Stock</th>
                      <th class="px-2.5 py-1.5 text-center text-gray-600 dark:text-gray-300 font-medium">Statut</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="v in productVariants" :key="v.id ?? v.label" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td class="px-2.5 py-1.5 text-gray-800 dark:text-gray-200 font-medium">{{ v.label }}</td>
                      <td class="px-2.5 py-1.5 text-gray-500 dark:text-gray-400 font-mono text-xs">{{ v.sku || '—' }}</td>
                      <td class="px-2.5 py-1.5 text-right font-mono">{{ Number(v.stock ?? 0).toFixed(2) }} {{ editTarget.p_unit ?? 'pcs' }}</td>
                      <td class="px-2.5 py-1.5 text-center">
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium"
                          :class="Number(v.stock ?? 0) > 0 ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'">
                          {{ Number(v.stock ?? 0) > 0 ? 'En stock' : 'Epuise' }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 italic">
                  Stock lu depuis warehouse_has_stock. Saisie via documents de stock.
                </p>
              </div>

              <!-- WITHOUT VARIANTS -->
              <div v-else-if="warehouseStocksList.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                      <th class="px-2.5 py-1.5 text-left text-gray-600 dark:text-gray-300 font-medium">Warehouse</th>
                      <th class="px-2.5 py-1.5 text-right text-gray-600 dark:text-gray-300 font-medium">Stock</th>
                      <th class="px-2.5 py-1.5 text-center text-gray-600 dark:text-gray-300 font-medium">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="ws in warehouseStocksList" :key="ws.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td class="px-2.5 py-1.5 text-gray-800 dark:text-gray-200">{{ ws.warehouse?.wh_title ?? ws.warehouse?.wh_name ?? '—' }}</td>
                      <td class="px-2.5 py-1.5 text-right font-mono">{{ Number(ws.stockLevel ?? ws.stock_level ?? 0).toFixed(2) }} {{ editTarget.p_unit ?? 'pcs' }}</td>
                      <td class="px-2.5 py-1.5 text-center">
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium"
                          :class="Number(ws.stockLevel ?? ws.stock_level ?? 0) > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                          {{ Number(ws.stockLevel ?? ws.stock_level ?? 0) > 0 ? 'In Stock' : 'Out' }}
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
              <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Recent Movements</h3>
                <span v-if="stockMouvements.length" class="text-[11px] text-gray-500 dark:text-gray-400">
                  {{ stockMouvements.length }} mouvement(s)
                </span>
              </div>
              <div v-if="stockMouvements.length" class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
                <table class="w-full text-[11px] border-collapse">
                  <thead class="bg-gray-100 dark:bg-gray-700/70 text-gray-600 dark:text-gray-300 uppercase">
                    <tr>
                      <th class="px-2 py-1.5 text-left font-semibold whitespace-nowrap">Date</th>
                      <th class="px-2 py-1.5 text-center font-semibold">Sens</th>
                      <th class="px-2 py-1.5 text-right font-semibold">Qté</th>
                      <th class="px-2 py-1.5 text-left font-semibold">Motif</th>
                      <th class="px-2 py-1.5 text-left font-semibold">Document</th>
                      <th class="px-2 py-1.5 text-left font-semibold">Dépôt</th>
                      <th class="px-2 py-1.5 text-left font-semibold">Utilisateur</th>
                      <th class="px-2 py-1.5 text-center font-semibold whitespace-nowrap">Solde (avant → après)</th>
                      <th class="px-2 py-1.5 text-right font-semibold">PU</th>
                      <th class="px-2 py-1.5 text-right font-semibold">Total</th>
                      <th class="px-2 py-1.5 text-left font-semibold">Notes</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="mov in stockMouvements"
                      :key="mov.id"
                      class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors"
                      :class="mov.direction === 'in'
                        ? 'border-l-2 border-l-green-500 dark:border-l-green-400'
                        : 'border-l-2 border-l-red-500 dark:border-l-red-400'"
                    >
                      <td class="px-2 py-1.5 whitespace-nowrap text-gray-600 dark:text-gray-400 font-mono text-[10px]">
                        <div>{{ fmtDate(mov.created_at) }}</div>
                        <div class="text-gray-400">{{ new Date(mov.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</div>
                      </td>
                      <td class="px-2 py-1.5 text-center">
                        <span
                          class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase"
                          :class="mov.direction === 'in'
                            ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                            : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'"
                        >
                          {{ mov.direction === 'in' ? '↑ IN' : '↓ OUT' }}
                        </span>
                      </td>
                      <td class="px-2 py-1.5 text-right font-mono font-semibold whitespace-nowrap"
                          :class="mov.direction === 'in' ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
                        {{ mov.direction === 'in' ? '+' : '−' }}{{ Number(mov.quantity).toFixed(2) }}
                        <span class="text-gray-400 font-normal">{{ editTarget?.p_unit ?? 'pcs' }}</span>
                      </td>
                      <td class="px-2 py-1.5">
                        <div class="flex flex-col gap-0.5">
                          <span v-if="mov.reason" class="px-1.5 py-0.5 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 rounded text-[10px] font-medium self-start">
                            {{ mov.reason }}
                          </span>
                          <span
                            v-if="mov.status && mov.status !== 'applied'"
                            class="px-1.5 py-0.5 rounded text-[10px] font-medium self-start"
                            :class="mov.status === 'pending'
                              ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                              : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                          >
                            {{ mov.status }}
                          </span>
                        </div>
                      </td>
                      <td class="px-2 py-1.5 font-mono text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        {{ mov.document_reference || mov.document_type || '—' }}
                      </td>
                      <td class="px-2 py-1.5 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        {{ mov.warehouse?.wh_title ?? mov.warehouse?.wh_code ?? '—' }}
                      </td>
                      <td class="px-2 py-1.5 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        {{ mov.user?.name ?? '—' }}
                      </td>
                      <td class="px-2 py-1.5 text-center font-mono whitespace-nowrap">
                        <template v-if="mov.stock_before !== null && mov.stock_after !== null">
                          <span class="text-gray-500">{{ Number(mov.stock_before).toFixed(2) }}</span>
                          <span class="text-gray-400 mx-1">→</span>
                          <span class="font-semibold text-gray-900 dark:text-gray-100">{{ Number(mov.stock_after).toFixed(2) }}</span>
                        </template>
                        <span v-else class="text-gray-400">—</span>
                      </td>
                      <td class="px-2 py-1.5 text-right font-mono whitespace-nowrap text-gray-700 dark:text-gray-300">
                        <template v-if="mov.unit_cost && Number(mov.unit_cost) > 0">{{ Number(mov.unit_cost).toFixed(2) }}</template>
                        <span v-else class="text-gray-400">—</span>
                      </td>
                      <td class="px-2 py-1.5 text-right font-mono whitespace-nowrap text-gray-900 dark:text-gray-100">
                        <template v-if="mov.unit_cost && Number(mov.unit_cost) > 0">
                          {{ (Number(mov.unit_cost) * Number(mov.quantity)).toFixed(2) }}
                        </template>
                        <span v-else class="text-gray-400">—</span>
                      </td>
                      <td class="px-2 py-1.5 text-gray-500 dark:text-gray-400 italic max-w-[200px] truncate" :title="mov.notes">
                        {{ mov.notes || '—' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
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
                <div class="bg-[#F1ECFC] dark:bg-[#7C5CFC]/20 px-3 py-2 rounded border border-[#E4D9FE] dark:border-[#4C3999]">
                  <p class="text-xs text-[#7C5CFC] dark:text-[#A78BFA] font-medium">Total Revenue</p>
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

            <!-- Purchase Metrics — absentes du payload sans products.view_cost -->
            <div v-if="statistics?.purchases" class="space-y-1.5 pt-3 border-t border-gray-200 dark:border-gray-700">
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
                <div class="bg-[#F1ECFC] dark:bg-[#7C5CFC]/20 px-3 py-2 rounded border border-[#E4D9FE] dark:border-[#4C3999]">
                  <p class="text-xs text-[#6D4CE0] dark:text-[#A78BFA] font-medium">Purchase Transactions</p>
                  <p class="text-lg font-bold text-[#3D2E85] dark:text-[#E4D9FE] leading-tight">{{ statistics?.purchases?.count ?? 0 }}</p>
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
                  class="absolute top-1 left-1 text-[10px] font-bold bg-[#7C5CFC] text-white px-1.5 py-0.5 rounded"
                >
                  {{ $t('products.primary') }}
                </span>
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                  <button
                    v-if="!img.isPrimary"
                    type="button"
                    class="p-1.5 bg-white dark:bg-gray-800 rounded-lg text-[#7C5CFC] hover:bg-[#F1ECFC] transition"
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
                class="aspect-square rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-blue-400 bg-gray-50 dark:bg-gray-900 flex flex-col items-center justify-center cursor-pointer transition text-gray-400 dark:text-gray-500 hover:text-[#7C5CFC]"
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

            <!-- Videos section -->
            <div class="mt-5">
              <h4 class="font-semibold text-gray-900 dark:text-white text-sm mb-2">{{ $t('products.videos') ?? 'Videos' }}</h4>
              <div class="space-y-2">
                <div
                  v-for="video in editVideos"
                  :key="video.id"
                  class="flex items-center gap-2.5 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900"
                >
                  <svg class="w-5 h-5 flex-shrink-0 text-[#7C5CFC]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
                  </svg>
                  <a :href="video.url" target="_blank" rel="noopener" class="flex-1 min-w-0 truncate text-sm text-gray-700 dark:text-gray-200 hover:underline">
                    {{ video.title || video.url }}
                  </a>
                  <button
                    type="button"
                    class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition flex-shrink-0"
                    :title="$t('common.delete')"
                    @click="doDeleteVideo(video)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>
              <p v-if="!editVideos.length" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                {{ $t('products.noVideos') ?? 'No videos.' }}
              </p>
              <div class="flex flex-col sm:flex-row gap-2 mt-2.5">
                <input
                  v-model="newVideoTitle" :aria-label="$t('products.videoTitlePlaceholder') ?? 'Title (optional)'"
                  type="text"
                  :placeholder="$t('products.videoTitlePlaceholder') ?? 'Title (optional)'"
                  class="flex-1 px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 dark:text-white"
                />
                <input
                  v-model="newVideoUrl" :aria-label="$t('products.videoUrlPlaceholder') ?? 'Video URL'"
                  type="url"
                  :placeholder="$t('products.videoUrlPlaceholder') ?? 'https://youtube.com/watch?v=...'"
                  class="flex-[2] px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 dark:text-white"
                  @keyup.enter="handleAddVideo"
                />
                <button
                  type="button"
                  class="px-3 py-1.5 text-xs font-medium bg-[#7C5CFC] text-white rounded-lg hover:bg-[#6D4CE0] transition disabled:opacity-50"
                  :disabled="addingVideo || !newVideoUrl.trim()"
                  @click="handleAddVideo"
                >
                  {{ addingVideo ? $t('products.uploadingImage') : ($t('products.addVideo') ?? 'Add') }}
                </button>
              </div>
            </div>

            <!-- Documents section -->
            <div class="mt-5">
              <h4 class="font-semibold text-gray-900 dark:text-white text-sm mb-2">{{ $t('products.documents') ?? 'Documents' }}</h4>
              <div class="space-y-2">
                <div
                  v-for="doc in editDocuments"
                  :key="doc.id"
                  class="flex items-center gap-2.5 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900"
                >
                  <svg class="w-5 h-5 flex-shrink-0 text-[#7C5CFC]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                  </svg>
                  <a :href="doc.url" target="_blank" rel="noopener" download class="flex-1 min-w-0 truncate text-sm text-gray-700 dark:text-gray-200 hover:underline">
                    {{ doc.title || doc.file_name }}
                  </a>
                  <span v-if="doc.size" class="text-[10px] text-gray-400 flex-shrink-0">{{ (doc.size / 1024).toFixed(0) }} KB</span>
                  <button
                    type="button"
                    class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition flex-shrink-0"
                    :title="$t('common.delete')"
                    @click="doDeleteDocument(doc)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>
              <p v-if="!editDocuments.length" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                {{ $t('products.noDocuments') ?? 'No documents.' }}
              </p>
              <label
                class="mt-2.5 flex items-center gap-2 px-3 py-2 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-blue-400 bg-gray-50 dark:bg-gray-900 cursor-pointer transition text-gray-400 dark:text-gray-500 hover:text-[#7C5CFC] w-fit"
              >
                <svg v-if="!uploadingDocument" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                </svg>
                <span class="text-xs font-medium">
                  {{ uploadingDocument ? $t('products.uploadingDocument') : ($t('products.addDocument') ?? 'Add document') }}
                </span>
                <input
                  type="file"
                  multiple
                  accept=".doc,.docx,.xls,.xlsx,.pdf"
                  class="hidden"
                  :disabled="uploadingDocument"
                  @change="handleDocumentUpload"
                />
              </label>
            </div>
          </div>
          <div v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
            {{ $t('products.stockAfterSave') ?? 'Media available after saving the product.' }}
          </div>
        </div>
        <!-- Tab: Variantes -->
        <div v-if="currentTab === 5 && variantsEnabled" class="space-y-3 py-2">
          <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-gray-500 dark:text-gray-400">Configurez les variantes pour ce produit.</p>
            <div class="flex gap-2">
              <button type="button"
                class="px-3 py-1.5 text-xs font-medium border border-indigo-300 text-indigo-600 rounded-lg hover:bg-indigo-50 dark:border-indigo-700 dark:text-indigo-400 transition"
                @click="applyGenerated">Generer combinaisons</button>
              <button type="button"
                class="px-3 py-1.5 text-xs font-medium bg-[#7C5CFC] text-white rounded-lg hover:bg-[#6D4CE0] transition"
                @click="addVariantRow">+ Ajouter</button>
            </div>
          </div>
          <div v-if="!productVariants.length" class="text-sm text-gray-400 dark:text-gray-500 text-center py-8 border border-dashed border-gray-300 dark:border-gray-700 rounded-lg">
            Aucune variante. Cliquez sur Generer ou Ajouter.
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead>
                <tr class="text-left text-gray-500 border-b border-gray-200 dark:border-gray-700">
                  <th class="py-2 pr-2">Label</th>
                  <th class="py-2 pr-2">SKU</th>
                  <th class="py-2 pr-2 w-24">Prix</th>
                  <th class="py-2 pr-2 w-20">Stock</th>
                  <th class="py-2 pr-2 w-16 text-center">Actif</th>
                  <th class="py-2 w-8"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(v, idx) in productVariants" :key="idx" class="border-b border-gray-100 dark:border-gray-800">
                  <td class="py-1.5 pr-2">
                    <input v-model="v.label" :aria-label="`Libellé de la variante ${idx + 1}`" @input="variantsDirty = true" type="text" placeholder="Rouge / XL"
                      class="w-full px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-[#7C5CFC]" />
                  </td>
                  <td class="py-1.5 pr-2">
                    <input v-model="v.sku" :aria-label="`SKU de la variante ${idx + 1}`" @input="variantsDirty = true" type="text" placeholder="SKU"
                      class="w-full px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-[#7C5CFC]" />
                  </td>
                  <td class="py-1.5 pr-2">
                    <input v-model.number="v.price" :aria-label="`Prix de la variante ${idx + 1}`" @input="variantsDirty = true" type="number" step="0.01" placeholder="—"
                      class="w-full px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-[#7C5CFC]" />
                  </td>

                  <td class="py-1.5 pr-2 text-center">
                    <input type="checkbox" v-model="v.is_active" :aria-label="`Variante ${idx + 1} active`" @change="variantsDirty = true" class="w-4 h-4 text-[#7C5CFC] rounded" />
                  </td>
                  <td class="py-1.5">
                    <button type="button" @click="removeVariant(idx)" class="p-1 text-red-400 hover:text-red-600 rounded transition">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
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
import BaseTable from '@/components/BaseTable.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'
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
const uploadingImage = ref(false)
const uploadingDocument = ref(false)
const addingVideo = ref(false)
const editTarget = ref(null)
const deleteTarget = ref(null)
const duplicatingId = ref(null)

// Images/videos/documents for the currently-edited product (reactive copies)
const editImages = ref([])
const editVideos = ref([])
const editDocuments = ref([])
const newVideoTitle = ref('')
const newVideoUrl = ref('')

// Statistics, movements, and price lists for the currently-edited product
const statistics = ref(null)
const stockMouvements = ref([])
const priceListItems = ref([])

// Resolve warehouse stocks regardless of JSON casing (snake_case by default,
// but left camelCase tolerant in case the serializer changes).
const warehouseStocksList = computed(() => {
  const t: any = editTarget.value
  if (!t) return []
  return t.warehouse_stocks ?? t.warehouseStocks ?? []
})

// Add-tier inline form state
const tierAdding = ref(false)
const tierSaving = ref(false)
const tierDeletingId = ref<number | null>(null)
const newTier = reactive({
  price_list_id: null as number | null,
  min_qty: 1,
  price_ht: 0,
})

// variants
const productVariants = ref([])
const variantsDirty = ref(false)

async function loadVariants(productId) {
  if (!variantsEnabled.value || !productId) return
  try {
    const { data } = await http.get('/products/' + productId + '/variants')
    productVariants.value = data
  } catch { productVariants.value = [] }
}

async function saveVariants(productId) {
  if (!variantsEnabled.value || !variantsDirty.value) return
  await http.post('/products/' + productId + '/variants/sync', { variants: productVariants.value })
  variantsDirty.value = false
}

function addVariantRow() {
  productVariants.value.push({ label: '', sku: '', price: null, is_active: true })
  variantsDirty.value = true
}

function removeVariant(idx) {
  productVariants.value.splice(idx, 1)
  variantsDirty.value = true
}

function applyGenerated() {
  if (!variantStore.items.length) return
  const combos = variantStore.items.reduce((acc, type) => {
    if (!type.values || !type.values.length) return acc
    if (!acc.length) return type.values.map(v => v.key)
    return acc.flatMap(a => type.values.map(v => a + ' / ' + v.key))
  }, [])
  const existing = new Set(productVariants.value.map(v => v.label))
  const newOnes = combos.filter(c => !existing.has(c)).map(label => ({ label, sku: '', price: null, is_active: true }))
  productVariants.value = [...productVariants.value, ...newOnes]
  variantsDirty.value = true
}

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

// ── Colonnes de la liste ─────────────────────────────────────────────────
// Un attribut du modele Product = une colonne. `permission` reserve les
// champs sensibles (prix d'achat, cout) aux roles autorises ; `module`
// masque les colonnes des fonctionnalites non activees chez le tenant.
// `menuLabel` sert au menu « Colonnes » quand l'en-tete est trop court.
// Alias de type (et non interface) : BaseTable attend une signature
// d'index, que seuls les alias satisfont implicitement.
type ColumnDef = {
  key: string
  label: string
  menuLabel?: string
  permission?: string
  module?: string
  hideOnMobile?: boolean
}

// Colonnes affichees tant que l'utilisateur n'a rien choisi.
const DEFAULT_VISIBLE_COLUMNS = [
  'primary_image',
  'p_code',
  'p_title',
  'category',
  'brand',
  'p_salePrice',
  'p_status',
  'total_stock',
]

const catalogColumns = computed<ColumnDef[]>(() => [
  { key: 'id', label: 'ID', hideOnMobile: true },
  { key: 'primary_image', label: '#', menuLabel: t('products.image') },
  { key: 'p_code', label: t('common.code') },
  { key: 'p_title', label: t('common.name') },
  { key: 'p_sku', label: 'SKU' },
  { key: 'p_ean13', label: t('products.ean'), hideOnMobile: true },
  { key: 'p_imei', label: 'IMEI', module: 'imei', hideOnMobile: true },
  { key: 'category', label: t('products.category') },
  { key: 'brand', label: t('products.brand') },
  { key: 'p_purchasePrice', label: t('products.purchasePrice'), permission: 'products.view_cost' },
  { key: 'p_salePrice', label: t('products.salePrice') },
  { key: 'p_cost', label: t('products.cost'), permission: 'products.view_cost' },
  { key: 'p_taxRate', label: t('products.taxRate') },
  { key: 'p_unit', label: t('products.unit') },
  { key: 'total_stock', label: 'Stock' },
  { key: 'p_status', label: t('common.status') },
  { key: 'is_ecom', label: t('products.columnEcom'), module: 'ecom' },
  { key: 'p_slug', label: t('products.slug'), module: 'ecom', hideOnMobile: true },
  { key: 'p_description', label: t('products.description'), hideOnMobile: true },
  { key: 'p_long_description', label: t('products.longDescription'), hideOnMobile: true },
  { key: 'p_notes', label: t('products.notes'), hideOnMobile: true },
  { key: 'created_at', label: t('common.createdAt'), hideOnMobile: true },
  { key: 'updated_at', label: t('common.updatedAt'), hideOnMobile: true },
])

// Colonnes que cet utilisateur a le droit de voir. Le serveur retire de
// toute facon p_purchasePrice / p_cost du payload sans products.view_cost —
// ce filtre evite d'afficher (et de proposer) des colonnes vides.
const allColumns = computed(() =>
  catalogColumns.value.filter(col => {
    if (col.permission && !auth.hasPermission(col.permission)) return false
    if (col.module && !auth.hasModule(col.module)) return false
    return true
  }),
)

// ── Colonnes affichees / masquees ────────────────────────────────────────
// Le choix est propre a chaque utilisateur (cle prefixee par son id) et
// conserve d'une session a l'autre sur ce navigateur.
const columnsMenuOpen = ref(false)
const columnsStorageKey = computed(() => `products.visibleColumns.v1:${auth.user?.id ?? 'anon'}`)
const visibleColumns = ref<string[]>([...DEFAULT_VISIBLE_COLUMNS])

function loadVisibleColumns() {
  try {
    const raw = localStorage.getItem(columnsStorageKey.value)
    const parsed = raw ? JSON.parse(raw) : null
    const keys = Array.isArray(parsed) ? parsed.filter((k: unknown) => typeof k === 'string') : []
    visibleColumns.value = keys.length ? keys : [...DEFAULT_VISIBLE_COLUMNS]
  } catch {
    visibleColumns.value = [...DEFAULT_VISIBLE_COLUMNS]
  }
}

// Recharge a la connexion / au changement d'utilisateur.
watch(() => auth.user?.id, loadVisibleColumns, { immediate: true })

watch(
  visibleColumns,
  val => {
    try {
      localStorage.setItem(columnsStorageKey.value, JSON.stringify(val))
    } catch {
      /* stockage indisponible (mode prive / quota) — on ignore */
    }
  },
  { deep: true },
)

// L'ordre d'affichage suit le catalogue, pas l'ordre des clics.
const columns = computed(() => allColumns.value.filter(c => visibleColumns.value.includes(c.key)))

function isColumnVisible(key: string) {
  return visibleColumns.value.includes(key)
}

function toggleColumn(key: string) {
  if (isColumnVisible(key)) {
    // On garde toujours au moins une colonne visible.
    if (columns.value.length <= 1) return
    visibleColumns.value = visibleColumns.value.filter(k => k !== key)
  } else {
    visibleColumns.value = [...visibleColumns.value, key]
  }
}

function showAllColumns() {
  visibleColumns.value = allColumns.value.map(c => c.key)
}

function resetColumns() {
  visibleColumns.value = [...DEFAULT_VISIBLE_COLUMNS]
}

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
  editImages.value = []
  editVideos.value = []
  editDocuments.value = []
  currentTab.value = 0
  Object.assign(form, emptyForm())
  showModal.value = true
}

async function openEdit(row) {
  editTarget.value = row
  editImages.value = [...(row.images ?? [])]
  editVideos.value = [...(row.videos ?? [])]
  editDocuments.value = [...(row.documents ?? [])]
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
    await store.fetchPage()
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
    await store.fetchPage()
  } catch {
    toast.value?.notify(t('common.failedSave'), 'error')
  }
}

async function doDeleteImage(img) {
  try {
    await store.deleteImage(editTarget.value.id, img.id)
    editImages.value = editImages.value.filter((i) => i.id !== img.id)
    toast.value?.notify(t('products.imageDeleted'), 'success')
    await store.fetchPage()
  } catch {
    toast.value?.notify(t('common.failedDelete'), 'error')
  }
}

// ── Video links ────────────────────────────────────────────────────────────
async function handleAddVideo() {
  const url = newVideoUrl.value.trim()
  if (!url || !editTarget.value) return

  addingVideo.value = true
  try {
    const video = await store.addVideo(editTarget.value.id, {
      title: newVideoTitle.value.trim() || undefined,
      url,
    })
    editVideos.value.push(video)
    newVideoTitle.value = ''
    newVideoUrl.value = ''
    toast.value?.notify(t('products.videoAdded'), 'success')
  } catch {
    toast.value?.notify(t('products.videoFailed'), 'error')
  } finally {
    addingVideo.value = false
  }
}

async function doDeleteVideo(video) {
  try {
    await store.deleteVideo(editTarget.value.id, video.id)
    editVideos.value = editVideos.value.filter((v) => v.id !== video.id)
    toast.value?.notify(t('products.videoDeleted'), 'success')
  } catch {
    toast.value?.notify(t('common.failedDelete'), 'error')
  }
}

// ── Document upload ──────────────────────────────────────────────────────
async function handleDocumentUpload(e) {
  const files = e.target.files
  if (!files || !editTarget.value) return
  e.target.value = ''

  uploadingDocument.value = true
  try {
    for (let i = 0; i < files.length; i++) {
      const fd = new FormData()
      fd.append('file', files[i])
      const doc = await store.uploadDocument(editTarget.value.id, fd)
      editDocuments.value.push(doc)
    }
    toast.value?.notify(t('products.documentUploaded'), 'success')
  } catch {
    toast.value?.notify(t('products.documentFailed'), 'error')
  } finally {
    uploadingDocument.value = false
  }
}

async function doDeleteDocument(doc) {
  try {
    await store.deleteDocument(editTarget.value.id, doc.id)
    editDocuments.value = editDocuments.value.filter((d) => d.id !== doc.id)
    toast.value?.notify(t('products.documentDeleted'), 'success')
  } catch {
    toast.value?.notify(t('common.failedDelete'), 'error')
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
