<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h2 class="text-[26px] sm:text-[30px] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white">{{ $t('suppliers.title') }}</h2>
        <p class="text-sm text-[#8A8F9C] dark:text-gray-400 mt-1">{{ $t('suppliers.subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2.5">
        <button
          class="flex items-center gap-2 px-3.5 sm:px-[18px] py-2.5 border border-[#E1E3E9] dark:border-gray-600 text-gray-900 dark:text-gray-300 text-sm font-semibold rounded-[11px] bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
          v-if="canExport"
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
          class="flex items-center gap-2 px-4 sm:px-5 py-2.5 bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white text-sm font-bold rounded-[11px] shadow-[0_8px_20px_-8px_rgba(124,92,252,0.6)] transition"
          @click="openCreate"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {{ $t('suppliers.add') }}
        </button>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
        <div class="w-9 h-9 rounded-[10px] bg-[#EFF1F5] dark:bg-gray-700 flex items-center justify-center mb-3.5">
          <svg class="w-[17px] h-[17px]" fill="none" stroke="#5B6070" stroke-width="1.6" viewBox="0 0 20 20">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
          </svg>
        </div>
        <div class="text-2xl sm:text-[26px] font-extrabold text-gray-900 dark:text-white">{{ store.meta.total ?? items.length }}</div>
        <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400 mt-0.5">{{ $t('suppliers.title') }}</div>
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
        <div class="w-9 h-9 rounded-[10px] bg-[#FDECEC] dark:bg-[#C6383E]/20 flex items-center justify-center mb-3.5">
          <svg class="w-[17px] h-[17px]" fill="none" stroke="#E5484D" stroke-width="1.8" viewBox="0 0 20 20">
            <line x1="10" y1="5" x2="10" y2="12" /><circle cx="10" cy="15" r="0.8" fill="#E5484D" />
          </svg>
        </div>
        <div class="text-2xl sm:text-[26px] font-extrabold text-[#E5484D]">{{ statOverLimit }}</div>
        <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400 mt-0.5">{{ $t('suppliers.statOverLimit') ?? 'En dépassement' }}</div>
      </div>
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
        <div class="w-9 h-9 rounded-[10px] bg-[#FFF1E6] dark:bg-[#7C5CFC]/20 flex items-center justify-center mb-3.5">
          <svg class="w-[17px] h-[17px]" fill="none" stroke="#7C5CFC" stroke-width="1.8" viewBox="0 0 20 20">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 15V5m0 0L1 8m3-3l3 3m6 0v10m0 0l3-3m-3 3l-3-3" />
          </svg>
        </div>
        <div class="text-xl sm:text-[22px] font-extrabold text-gray-900 dark:text-white">{{ formatNumber(statEncours) }}</div>
        <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400 mt-0.5">{{ $t('suppliers.statEncours') ?? 'Encours total (DH)' }}</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-[14px] p-3">
      <input
        :aria-label="$t('suppliers.search')"
        v-model="search"
        type="text"
        :placeholder="$t('suppliers.search')"
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
    <BaseTable :columns="columns" :rows="items" :empty-text="$t('suppliers.notFound')">
      <template #cell-tp_code="{ value }">
        <span class="font-mono text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">{{ value }}</span>
      </template>
      <template #cell-credit_available="{ row }">
        <div class="text-right">
          <span v-if="(row.seuil_credit ?? 0) === 0" class="text-gray-400 dark:text-gray-500 text-xs">&mdash;</span>
          <template v-else>
            <span
              class="font-mono text-sm font-medium"
              :class="
                creditAvailable(row) > 0
                  ? 'text-emerald-600'
                  : creditAvailable(row) === 0
                    ? 'text-amber-500'
                    : 'text-red-600'
              "
            >
              {{ formatNumber(creditAvailable(row)) }}
            </span>
            <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">DH</span>
            <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
              {{ formatNumber(row.encours_actuel ?? 0) }} / {{ formatNumber(row.seuil_credit ?? 0) }}
            </div>
          </template>
        </div>
      </template>
      <template #cell-tp_status="{ value }">
        <span
          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold"
          :class="value ? 'bg-[#E5F7ED] text-[#1F8A50]' : 'bg-[#F0F1F4] text-[#7A7F8C]'"
        >
          {{ value ? $t('common.active') : $t('common.inactive') }}
        </span>
      </template>
      <template #actions="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button
            class="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 transition"
            title="Enregistrer un paiement"
            @click="openBulkPayment(row)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </button>
          <button
            class="p-1.5 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 transition"
            title="Voir"
            @click="openShow(row)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
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
      v-if="store.meta.last_page > 1"
      :current-page="store.meta.current_page"
      :last-page="store.meta.last_page"
      :total="store.meta.total"
      :per-page="store.meta.per_page"
      @change="onPageChange"
    />

    <!-- Create / Edit Modal (Tabbed) -->
    <BaseModal v-model="showModal" :title="editTarget ? $t('suppliers.editTitle') : $t('suppliers.addTitle')" size="lg">
      <!-- Tab Navigation -->
      <div class="border-b border-gray-200 dark:border-gray-700 -mt-2 mb-4">
        <nav class="flex gap-0 -mb-px overflow-x-auto">
          <button
            v-for="tab in availableTabs"
            :key="tab.key"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
            :class="
              activeTab === tab.key
                ? 'border-[#7C5CFC] text-[#7C5CFC]'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            "
            @click="activeTab = tab.key"
          >
            <component :is="tab.icon" class="w-4 h-4" />
            {{ tab.label }}
            <span
              v-if="tab.badge !== undefined && tab.badge > 0"
              class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-xs font-semibold leading-none"
              :class="activeTab === tab.key ? 'bg-[#F1ECFC] text-[#6D4CE0]' : 'bg-gray-100 text-gray-600'"
            >
              {{ tab.badge }}
            </span>
          </button>
        </nav>
      </div>

      <!-- Tab content area with fixed min-height -->
      <div class="min-h-[350px]">
        <!-- TAB: Info -->
        <div v-show="activeTab === 'info'">
          <form class="space-y-5" @submit.prevent="submit">
            <!-- Nom -->
            <div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                  <label for="suppliers-tp-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                    >{{ $t('common.name') }} <span class="text-red-500">*</span></label
                  >
                  <input
                    id="suppliers-tp-title"
                    v-model="form.tp_title"
                    type="text"
                    required
                    :placeholder="$t('suppliers.namePlaceholder')"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                  />
                </div>
              </div>
            </div>
            <hr class="border-gray-100 dark:border-gray-700" />
            <!-- Contact -->
            <div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label for="suppliers-tp-phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.phone') }}</label>
                  <input
                    id="suppliers-tp-phone"
                    v-model="form.tp_phone"
                    type="text"
                    placeholder="+212..."
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                  />
                </div>
                <div>
                  <label for="suppliers-tp-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.email') }}</label>
                  <input
                    id="suppliers-tp-email"
                    v-model="form.tp_email"
                    type="email"
                    placeholder="contact@example.com"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                  />
                </div>
                <div>
                  <label for="suppliers-tp-city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.city') }}</label>
                  <input
                    id="suppliers-tp-city"
                    v-model="form.tp_city"
                    type="text"
                    placeholder="Casablanca"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                  />
                </div>
                <div class="sm:col-span-2">
                  <label for="suppliers-tp-address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.address') }}</label>
                  <textarea
                    id="suppliers-tp-address"
                    v-model="form.tp_address"
                    rows="2"
                    :placeholder="$t('common.addressPlaceholder')"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent resize-none"
                  ></textarea>
                </div>
              </div>
            </div>
            <hr class="border-gray-100 dark:border-gray-700" />
            <!-- Status -->
            <div class="flex items-center gap-2">
              <input
                id="sup-status"
                v-model="form.tp_status"
                type="checkbox"
                class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-[#7C5CFC] focus:ring-[#7C5CFC]"
              />
              <label for="sup-status" class="text-sm text-gray-700 dark:text-gray-300">{{ $t('common.active') }}</label>
            </div>
          </form>
        </div>

        <!-- TAB: Fiscal -->
        <div v-show="activeTab === 'fiscal'">
          <div class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="suppliers-tp-ice-number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('suppliers.ice') }}</label>
                <input
                  id="suppliers-tp-ice-number"
                  v-model="form.tp_Ice_Number"
                  type="text"
                  :placeholder="$t('suppliers.icePlaceholder')"
                  class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                />
              </div>
              <div>
                <label for="suppliers-tp-rc-number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('suppliers.rc') }}</label>
                <input
                  id="suppliers-tp-rc-number"
                  v-model="form.tp_Rc_Number"
                  type="text"
                  :placeholder="$t('suppliers.rcPlaceholder')"
                  class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                />
              </div>
              <div>
                <label for="suppliers-tp-patente-number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('suppliers.patente') }}</label>
                <input
                  id="suppliers-tp-patente-number"
                  v-model="form.tp_patente_Number"
                  type="text"
                  :placeholder="$t('suppliers.patentePlaceholder')"
                  class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                />
              </div>
              <div>
                <label for="suppliers-tp-idenfiscal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('suppliers.if') }}</label>
                <input
                  id="suppliers-tp-idenfiscal"
                  v-model="form.tp_IdenFiscal"
                  type="text"
                  :placeholder="$t('suppliers.ifPlaceholder')"
                  class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- TAB: Crédit -->
        <div v-show="activeTab === 'credit'">
          <div class="space-y-5">
            <!-- Credit gauge -->
            <div
              v-if="editTarget"
              class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-5 border border-blue-100"
            >
              <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Situation crédit</h4>
                <span
                  class="text-xs font-medium px-2 py-1 rounded-full"
                  :class="
                    creditPercent <= 70
                      ? 'bg-emerald-100 text-emerald-700'
                      : creditPercent <= 90
                        ? 'bg-amber-100 text-amber-700'
                        : 'bg-red-100 text-red-700'
                  "
                >
                  {{ creditPercent.toFixed(0) }}% utilisé
                </span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-3 mb-3">
                <div
                  class="h-3 rounded-full transition-all duration-500"
                  :class="creditPercent <= 70 ? 'bg-emerald-500' : creditPercent <= 90 ? 'bg-amber-500' : 'bg-red-500'"
                  :style="{ width: Math.min(creditPercent, 100) + '%' }"
                ></div>
              </div>
              <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">Encours actuel</p>
                  <p class="text-lg font-bold text-gray-900 dark:text-white font-mono">{{ formatNumber(form.encours_actuel ?? 0) }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">Seuil crédit</p>
                  <p class="text-lg font-bold text-gray-900 dark:text-white font-mono">{{ formatNumber(form.seuil_credit ?? 0) }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">Disponible</p>
                  <p
                    class="text-lg font-bold font-mono"
                    :class="creditAvailableForm > 0 ? 'text-emerald-600' : 'text-red-600'"
                  >
                    {{ formatNumber(creditAvailableForm) }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Edit fields -->
            <div>
              <h4 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Paramètres crédit</h4>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{
                    $t('suppliers.currentBalance')
                  }}</label>
                  <div class="relative">
                    <div
                      class="w-full px-3.5 py-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm font-mono pr-12 text-gray-700 dark:text-gray-300"
                    >
                      {{ formatNumber(form.encours_actuel ?? 0) }}
                    </div>
                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 dark:text-gray-500 font-medium"
                      >DH</span
                    >
                  </div>
                  <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $t('suppliers.balanceAutoCalculated') }}</p>
                </div>
                <div>
                  <label for="suppliers-seuil-credit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('suppliers.creditLimit') }}</label>
                  <div class="relative">
                    <input
                      id="suppliers-seuil-credit"
                      v-model.number="form.seuil_credit"
                      type="number"
                      min="0"
                      step="0.01"
                      placeholder="0.00"
                      class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-input font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent pr-12"
                    />
                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 dark:text-gray-500 font-medium"
                      >DH</span
                    >
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB: Documents -->
        <div v-show="activeTab === 'factures'">
          <PartnerDocumentsTab
            :loading="loadingDetail"
            :documents="supplierDocuments"
            :countable-documents="countableSupplierDocuments"
            :total-ttc="totalDocsTTC"
            :total-due="totalDocsDue"
            :is-billed="isBilledReceipt"
            billed-title="Bon déjà facturé : son montant est porté par la facture, et ne compte pas une seconde fois."
          />
        </div>

        <!-- TAB: Paiements -->
        <div v-show="activeTab === 'paiements'">
          <PartnerPaymentsTab
            :loading="loadingDetail"
            :payments="supplierPayments"
            :total-payments="totalPaymentsAmount"
          />
        </div>

        <!-- TAB: Statistiques -->
        <div v-show="activeTab === 'statistiques'">
          <PartnerStatsTab
            :loading="loadingDetail"
            :documents-count="supplierDocuments.length"
            :unpaid-count="unpaidDocs"
            :total-ttc="totalDocsTTC"
            :total-payments="totalPaymentsAmount"
            :total-due="totalDocsDue"
            :payment-rate="paymentRate"
            total-label="Total achats"
          />
        </div>
      </div>
      <!-- /min-h wrapper -->

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

    <!-- Show (Read-only) Modal -->
    <BaseModal v-model="showShowModal" :title="showTarget?.tp_title ?? 'Fournisseur'" size="xl">
      <!-- Tab Navigation -->
      <div class="border-b border-gray-200 dark:border-gray-700 -mt-2 mb-4">
        <nav class="flex gap-0 -mb-px overflow-x-auto">
          <button
            v-for="tab in showTabs"
            :key="tab.key"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
            :class="
              showActiveTab === tab.key
                ? 'border-[#7C5CFC] text-[#7C5CFC]'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            "
            @click="showActiveTab = tab.key"
          >
            <component :is="tab.icon" class="w-4 h-4" />
            {{ tab.label }}
            <span
              v-if="tab.badge !== undefined && tab.badge > 0"
              class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-xs font-semibold leading-none"
              :class="showActiveTab === tab.key ? 'bg-[#F1ECFC] text-[#6D4CE0]' : 'bg-gray-100 text-gray-600'"
            >
              {{ tab.badge }}
            </span>
          </button>
        </nav>
      </div>

      <div class="min-h-[380px]">
        <!-- TAB: Info -->
        <div v-show="showActiveTab === 'info'">
          <div class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
              <div class="sm:col-span-2">
                <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">Informations générales</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Code</p>
                <p class="text-sm font-mono font-medium text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
                  {{ showTarget?.tp_code ?? '—' }}
                </p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Nom</p>
                <p class="text-sm font-medium text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
                  {{ showTarget?.tp_title ?? '—' }}
                </p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Téléphone</p>
                <p class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">{{ showTarget?.tp_phone || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Email</p>
                <p class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">{{ showTarget?.tp_email || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Ville</p>
                <p class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">{{ showTarget?.tp_city || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Statut</p>
                <p class="text-sm bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="showTarget?.tp_status ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                  >
                    {{ showTarget?.tp_status ? 'Actif' : 'Inactif' }}
                  </span>
                </p>
              </div>
              <div class="sm:col-span-2">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Adresse</p>
                <p class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg min-h-[40px]">
                  {{ showTarget?.tp_address || '—' }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB: Fiscal -->
        <div v-show="showActiveTab === 'fiscal'">
          <div class="space-y-4">
            <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">Informations fiscales</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">ICE</p>
                <p class="text-sm font-mono text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
                  {{ showTarget?.tp_Ice_Number || '—' }}
                </p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">RC</p>
                <p class="text-sm font-mono text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
                  {{ showTarget?.tp_Rc_Number || '—' }}
                </p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Patente</p>
                <p class="text-sm font-mono text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
                  {{ showTarget?.tp_patente_Number || '—' }}
                </p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Identifiant fiscal</p>
                <p class="text-sm font-mono text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
                  {{ showTarget?.tp_IdenFiscal || '—' }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB: Crédit -->
        <div v-show="showActiveTab === 'credit'">
          <div class="space-y-5">
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-5 border border-blue-100">
              <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Situation crédit</h4>
                <span
                  class="text-xs font-medium px-2 py-1 rounded-full"
                  :class="
                    showCreditPercent <= 70
                      ? 'bg-emerald-100 text-emerald-700'
                      : showCreditPercent <= 90
                        ? 'bg-amber-100 text-amber-700'
                        : 'bg-red-100 text-red-700'
                  "
                >
                  {{ showCreditPercent.toFixed(0) }}% utilisé
                </span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-3 mb-3">
                <div
                  class="h-3 rounded-full transition-all duration-500"
                  :class="
                    showCreditPercent <= 70 ? 'bg-emerald-500' : showCreditPercent <= 90 ? 'bg-amber-500' : 'bg-red-500'
                  "
                  :style="{ width: Math.min(showCreditPercent, 100) + '%' }"
                ></div>
              </div>
              <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">Encours actuel</p>
                  <p class="text-lg font-bold text-gray-900 dark:text-white font-mono">
                    {{ formatNumber(showTarget?.encours_actuel ?? 0) }}
                  </p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">Seuil crédit</p>
                  <p class="text-lg font-bold text-gray-900 dark:text-white font-mono">
                    {{ formatNumber(showTarget?.seuil_credit ?? 0) }}
                  </p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">Disponible</p>
                  <p
                    class="text-lg font-bold font-mono"
                    :class="showCreditAvailable > 0 ? 'text-emerald-600' : 'text-red-600'"
                  >
                    {{ formatNumber(showCreditAvailable) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB: Documents -->
        <div v-show="showActiveTab === 'factures'">
          <PartnerDocumentsTab
            :loading="showLoadingDetail"
            :documents="showDocuments"
            :countable-documents="countableShowDocuments"
            :total-ttc="showTotalTTC"
            :total-due="showTotalDue"
            :is-billed="isBilledReceipt"
            billed-title="Bon déjà facturé : son montant est porté par la facture, et ne compte pas une seconde fois."
          />
        </div>

        <!-- TAB: Paiements -->
        <div v-show="showActiveTab === 'paiements'">
          <PartnerPaymentsTab
            :loading="showLoadingDetail"
            :payments="showPayments"
            :total-payments="showTotalPayments"
          />
        </div>

        <!-- TAB: Statistiques -->
        <div v-show="showActiveTab === 'statistiques'">
          <PartnerStatsTab
            :loading="showLoadingDetail"
            :documents-count="showDocuments.length"
            :unpaid-count="showUnpaidCount"
            :total-ttc="showTotalTTC"
            :total-payments="showTotalPayments"
            :total-due="showTotalDue"
            :payment-rate="showPaymentRate"
            total-label="Total achats"
          />
        </div>
      </div>
      <!-- /min-h wrapper -->

      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition"
          @click="showShowModal = false"
        >
          Fermer
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white rounded-lg transition"
          @click="showShowModal = false; openEdit(showTarget)"
        >
          <svg
            class="w-4 h-4 inline -mt-0.5 mr-1"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
            />
          </svg>
          Modifier
        </button>
      </template>
    </BaseModal>

    <!-- Bulk Payment Modal -->
    <PartnerPaymentModal
      v-model="showBulkPaymentModal"
      v-model:amount="bulkPaymentForm.amount"
      v-model:method="bulkPaymentForm.method"
      v-model:reference="bulkPaymentForm.reference"
      v-model:notes="bulkPaymentForm.notes"
      :partner-name="bulkPaymentTarget?.tp_title"
      :loading="bulkPaymentLoading"
      :saving="bulkPaymentSaving"
      :result="bulkPaymentResult"
      :unpaid-docs="bulkPaymentUnpaidDocs"
      :selected-ids="bulkPaymentSelectedIds"
      :selected-total-due="bulkPaymentSelectedTotalDue"
      :all-selected="bulkPaymentAllSelected"
      empty-message="Aucune facture impayée"
      id-prefix="suppliers-payment"
      @toggle-doc="toggleBulkPaymentDoc"
      @toggle-all="toggleBulkPaymentSelectAll"
      @submit="submitBulkPayment"
    />

    <!-- Delete Modal -->
    <BaseModal v-model="showDelete" :title="$t('suppliers.deleteTitle')" size="sm">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ $t('suppliers.deleteConfirm') }} <span class="font-semibold">{{ deleteTarget?.tp_title }}</span
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
import { ref, computed, onMounted } from 'vue'
import type { Component } from 'vue'
import { useI18n } from 'vue-i18n'
import { useExcelExport } from '@/composables/useExcelExport'
import { useBulkPayment } from '@/composables/useBulkPayment'
import { usePartnerLedger, useCreditGauge } from '@/composables/usePartnerLedger'
import { useThirdPartnerList, useThirdPartnerForm } from '@/composables/useThirdPartnerCrud'
import http from '@/services/http'
import BaseTable from '@/components/BaseTable.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'
import PartnerDocumentsTab from '@/components/partners/PartnerDocumentsTab.vue'
import PartnerPaymentModal from '@/components/partners/PartnerPaymentModal.vue'
import PartnerPaymentsTab from '@/components/partners/PartnerPaymentsTab.vue'
import PartnerStatsTab from '@/components/partners/PartnerStatsTab.vue'
import { formatAmount as formatNumber, useFormat } from '@/composables/useFormat'
import { IconCredit, IconFiscal, IconInfo, IconInvoice, IconPayment, IconStats } from '@/components/icons/tabIcons'
// Les libelles et pastilles de l'historique sont partages avec la fiche
// fournisseur/client d'en face : ils sont aliases ici sous les noms que le
// template emploie deja.
import {
  partnerDocTypeLabel as docTypeLabel,
  partnerDocTypeBadgeClass as docTypeClass,
  partnerStatusBadgeClass as statusClass,
  partnerStatusLabel as statusLabel,
  paymentMethodLabel as methodLabel,
  docTypeShortLabel as bulkDocTypeShort,
} from '@/composables/useDocumentLabels'

const { t } = useI18n()
const { date: fmtDate } = useFormat()

const { exporting, exportExcel, canExport } = useExcelExport()

function onExport() {
  exportExcel('/export/third-partners', { ...buildParams(), tp_Role: 'supplier' })
}

// ── Tab state ────────────────────────────────────────────────────────────
const activeTab = ref<'info' | 'fiscal' | 'credit' | 'factures' | 'paiements' | 'statistiques'>('info')
const loadingDetail = ref(false)
const supplierDetail = ref<any>(null)

interface TabDef {
  key: 'info' | 'fiscal' | 'credit' | 'factures' | 'paiements' | 'statistiques'
  label: string
  icon: Component
  badge?: number
}

const availableTabs = computed<TabDef[]>(() => {
  const tabs: TabDef[] = [
    { key: 'info', label: 'Info', icon: IconInfo },
    { key: 'fiscal', label: 'Fiscal', icon: IconFiscal },
    { key: 'credit', label: 'Crédit', icon: IconCredit },
  ]
  if (editTarget.value) {
    tabs.push(
      { key: 'factures', label: 'Documents', icon: IconInvoice, badge: supplierDocuments.value.length },
      { key: 'paiements', label: 'Paiements', icon: IconPayment, badge: supplierPayments.value.length },
      { key: 'statistiques', label: 'Statistiques', icon: IconStats },
    )
  }
  return tabs
})

// ── Compte fournisseur ───────────────────────────────────────────────────
/**
 * Un document qui pese sur la dette fournisseur.
 *
 * Seule la facture d'achat compte, jamais le bon de reception : recevoir la
 * marchandise ne cree pas la dette, c'est la facture qui la cree. Compter les
 * deux doublait le total des que les bons etaient factures — 103 455 de bons
 * plus 103 455 de facture groupee affichaient 206 910 dus a un fournisseur qui
 * n'en reclamait que 103 455.
 *
 * C'est la regle qu'applique deja ThirdPartner::recalculateEncours() : l'ecran
 * doit montrer le meme perimetre que le chiffre qu'il place a cote.
 */
function isCountablePurchase(doc: any): boolean {
  return (
    doc.document_type === 'InvoicePurchase' &&
    doc.status !== 'cancelled' &&
    doc.status !== 'draft'
  )
}

/** Un retour fournisseur vient en deduction, comme dans le calcul d'encours. */
function isCountableReturn(doc: any): boolean {
  return (
    doc.document_type === 'ReturnPurchase' &&
    doc.status !== 'cancelled' &&
    doc.status !== 'draft'
  )
}

/** Ce bon est-il deja porte par une facture ? Sert a le griser dans la liste. */
function isBilledReceipt(doc: any): boolean {
  if (doc.document_type !== 'ReceiptNotePurchase') return false
  if (doc.status === 'converted') return true
  return (doc.children ?? []).some((c: any) => c.document_type === 'InvoicePurchase')
}
/**
 * Le meme perimetre sert les deux modales : celle qui edite la fiche et celle
 * qui la consulte. Elles ne different que par la fiche qu'elles regardent.
 */
const supplierScope = { isCountable: isCountablePurchase, isDeductible: isCountableReturn }

const {
  documents: supplierDocuments,
  payments: supplierPayments,
  countableDocuments: countableSupplierDocuments,
  totalTtc: totalDocsTTC,
  totalDue: totalDocsDue,
  totalPayments: totalPaymentsAmount,
  unpaidCount: unpaidDocs,
  paymentRate,
} = usePartnerLedger(supplierDetail, supplierScope)

const { percent: creditPercent, available: creditAvailableForm } = useCreditGauge(() => form)

async function loadSupplierDetail(id: number) {
  loadingDetail.value = true
  try {
    const { data } = await http.get(`/third-partners/${id}`)
    supplierDetail.value = data
  } catch {
    supplierDetail.value = null
  } finally {
    loadingDetail.value = false
  }
}

// ── Show (read-only) modal state ─────────────────────────────────────────
const showShowModal = ref(false)
const showTarget = ref<any>(null)
const showActiveTab = ref<'info' | 'fiscal' | 'credit' | 'factures' | 'paiements' | 'statistiques'>('info')
const showLoadingDetail = ref(false)
const showDetail = ref<any>(null)

const {
  documents: showDocuments,
  payments: showPayments,
  countableDocuments: countableShowDocuments,
  totalTtc: showTotalTTC,
  totalDue: showTotalDue,
  totalPayments: showTotalPayments,
  unpaidCount: showUnpaidCount,
  paymentRate: showPaymentRate,
} = usePartnerLedger(showDetail, supplierScope)

const { percent: showCreditPercent, available: showCreditAvailable } = useCreditGauge(showTarget)

// ── Reglement groupe ─────────────────────────────────────────────────────
// Cote achat, seules les factures se soldent : il n'y a pas de « paiement sur
// bon de reception » comme il y a un « paiement sur BL » cote vente.
const {
  show: showBulkPaymentModal,
  target: bulkPaymentTarget,
  loading: bulkPaymentLoading,
  saving: bulkPaymentSaving,
  result: bulkPaymentResult,
  form: bulkPaymentForm,
  unpaidDocs: bulkPaymentUnpaidDocs,
  selectedIds: bulkPaymentSelectedIds,
  selectedTotalDue: bulkPaymentSelectedTotalDue,
  allSelected: bulkPaymentAllSelected,
  toggleDoc: toggleBulkPaymentDoc,
  toggleSelectAll: toggleBulkPaymentSelectAll,
  open: openBulkPayment,
  submit: submitBulkPayment,
} = useBulkPayment({
  payableDocTypes: ['InvoiceSale', 'InvoicePurchase'],
  notify: (message, level) => (toast.value as any)?.notify(message, level),
  onSettled: () => loadPage(store.meta.current_page),
})

const showTabs = computed<TabDef[]>(() => [
  { key: 'info', label: 'Info', icon: IconInfo },
  { key: 'fiscal', label: 'Fiscal', icon: IconFiscal },
  { key: 'credit', label: 'Crédit', icon: IconCredit },
  { key: 'factures', label: 'Documents', icon: IconInvoice, badge: showDocuments.value.length },
  { key: 'paiements', label: 'Paiements', icon: IconPayment, badge: showPayments.value.length },
  { key: 'statistiques', label: 'Statistiques', icon: IconStats },
])

async function openShow(row: any) {
  showTarget.value = row
  showActiveTab.value = 'info'
  showDetail.value = null
  showShowModal.value = true
  showLoadingDetail.value = true
  try {
    const { data } = await http.get(`/third-partners/${row.id}`)
    showDetail.value = data
  } catch {
    showDetail.value = null
  } finally {
    showLoadingDetail.value = false
  }
}

// ── UI state ──────────────────────────────────────────────────────────────
const toast = ref(null)

const {
  store,
  items,
  search,
  statusFilter,
  buildParams,
  loadPage,
  onPageChange,
  creditAvailable,
  statActive,
  statOverLimit,
  statEncours,
} = useThirdPartnerList('supplier')

const emptyForm = () => ({
  tp_title: '',
  tp_Role: 'supplier' as const,
  tp_status: true,
  tp_phone: '',
  tp_email: '',
  tp_city: '',
  tp_address: '',
  tp_Ice_Number: '',
  tp_Rc_Number: '',
  tp_patente_Number: '',
  tp_IdenFiscal: '',
  encours_actuel: 0,
  seuil_credit: 0,
})

const {
  form,
  showModal,
  showDelete,
  saving,
  deleting,
  editTarget,
  deleteTarget,
  openCreate,
  openEdit,
  submit,
  confirmDelete,
  doDelete,
} = useThirdPartnerForm({
  scope: 'suppliers',
  blank: emptyForm,
  notify: (message, level) => (toast.value as any)?.notify(message, level),
  onOpen: (row) => {
    activeTab.value = 'info'
    supplierDetail.value = null
    // Documents et reglements arrivent en arriere-plan.
    if (row) loadSupplierDetail(row.id)
  },
})

const columns = computed(() => [
  { key: 'tp_code', label: t('common.code') },
  { key: 'tp_title', label: t('common.name') },
  { key: 'tp_phone', label: t('common.phone') },
  { key: 'tp_email', label: t('common.email') },
  { key: 'tp_city', label: t('common.city') },
  { key: 'credit_available', label: t('suppliers.creditAvailable') ?? 'Crédit disponible' },
  { key: 'tp_status', label: t('common.status') },
])

function formatDate(d: string): string {
  return fmtDate(d)
}

onMounted(() => loadPage())
</script>
