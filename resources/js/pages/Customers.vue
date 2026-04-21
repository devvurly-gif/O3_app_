<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $t('customers.title') }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $t('customers.subtitle') }}</p>
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
          {{ $t('customers.add') }}
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
      <input
        v-model="search"
        type="text"
        :placeholder="$t('customers.search')"
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
    <BaseTable :columns="columns" :rows="items" :empty-text="$t('customers.notFound')">
      <template #cell-tp_code="{ value }">
        <span class="font-mono text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">{{ value }}</span>
      </template>
      <template #cell-credit_available="{ row }">
        <div class="text-right">
          <span v-if="(row.seuil_credit ?? 0) === 0" class="text-gray-400 dark:text-gray-500 text-xs">—</span>
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
          class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
          :class="value ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
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

    <!-- Create / Edit Modal (Tabbed) -->
    <BaseModal v-model="showModal" :title="editTarget ? $t('customers.editTitle') : $t('customers.addTitle')" size="lg">
      <!-- Tab Navigation -->
      <div class="border-b border-gray-200 dark:border-gray-700 -mt-2 mb-4">
        <nav class="flex gap-0 -mb-px overflow-x-auto">
          <button
            v-for="tab in availableTabs"
            :key="tab.key"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
            :class="
              activeTab === tab.key
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            "
            @click="activeTab = tab.key"
          >
            <component :is="tab.icon" class="w-4 h-4" />
            {{ tab.label }}
            <span
              v-if="tab.badge !== undefined && tab.badge > 0"
              class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-xs font-semibold leading-none"
              :class="activeTab === tab.key ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'"
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
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                    >{{ $t('common.name') }} <span class="text-red-500">*</span></label
                  >
                  <input
                    v-model="form.tp_title"
                    type="text"
                    required
                    :placeholder="$t('customers.namePlaceholder')"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                </div>
              </div>
            </div>
            <hr class="border-gray-100 dark:border-gray-700" />
            <!-- Contact -->
            <div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.phone') }}</label>
                  <input
                    v-model="form.tp_phone"
                    type="text"
                    placeholder="+212..."
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.email') }}</label>
                  <input
                    v-model="form.tp_email"
                    type="email"
                    placeholder="contact@example.com"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.city') }}</label>
                  <input
                    v-model="form.tp_city"
                    type="text"
                    placeholder="Casablanca"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.address') }}</label>
                  <textarea
                    v-model="form.tp_address"
                    rows="2"
                    :placeholder="$t('common.addressPlaceholder')"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                  ></textarea>
                </div>
              </div>
            </div>
            <hr class="border-gray-100 dark:border-gray-700" />
            <!-- Status -->
            <div class="flex items-center gap-2">
              <input
                id="cust-status"
                v-model="form.tp_status"
                type="checkbox"
                class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
              />
              <label for="cust-status" class="text-sm text-gray-700 dark:text-gray-300">{{ $t('common.active') }}</label>
            </div>
          </form>
        </div>

        <!-- TAB: Fiscal -->
        <div v-show="activeTab === 'fiscal'">
          <div class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('customers.ice') }}</label>
                <input
                  v-model="form.tp_Ice_Number"
                  type="text"
                  :placeholder="$t('customers.icePlaceholder')"
                  class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('customers.rc') }}</label>
                <input
                  v-model="form.tp_Rc_Number"
                  type="text"
                  :placeholder="$t('customers.rcPlaceholder')"
                  class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('customers.patente') }}</label>
                <input
                  v-model="form.tp_patente_Number"
                  type="text"
                  :placeholder="$t('customers.patentePlaceholder')"
                  class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('customers.if') }}</label>
                <input
                  v-model="form.tp_IdenFiscal"
                  type="text"
                  :placeholder="$t('customers.ifPlaceholder')"
                  class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
                    $t('customers.currentBalance')
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
                  <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $t('customers.balanceAutoCalculated') }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('customers.creditLimit') }}</label>
                  <div class="relative">
                    <input
                      v-model.number="form.seuil_credit"
                      type="number"
                      min="0"
                      step="0.01"
                      placeholder="0.00"
                      class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-12"
                    />
                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 dark:text-gray-500 font-medium"
                      >DH</span
                    >
                  </div>
                </div>
              </div>

              <!-- Type de Compte & Fréquence de facturation -->
              <div class="grid grid-cols-2 gap-4 mt-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type de compte</label>
                  <select
                    v-model="form.type_compte"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  >
                    <option value="normal">Normal</option>
                    <option value="en_compte">En Compte</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fréquence de facturation</label>
                  <select
                    v-model="form.frequence_facturation"
                    :disabled="form.type_compte !== 'en_compte'"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-gray-100 disabled:text-gray-400"
                  >
                    <option :value="null">—</option>
                    <option value="mensuelle">Mensuelle</option>
                    <option value="trimestrielle">Trimestrielle</option>
                    <option value="semestrielle">Semestrielle</option>
                  </select>
                </div>
              </div>

              <!-- Grille tarifaire (price list) assignée -->
              <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('pricelists.title') }}
                </label>
                <select
                  v-model="form.price_list_id"
                  class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option :value="null">{{ $t('pricelists.selectList') }}</option>
                  <option v-for="pl in priceLists" :key="pl.id" :value="pl.id">
                    {{ pl.name }}<template v-if="pl.is_default"> ({{ $t('pricelists.defaultBadge') }})</template>
                  </option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB: Factures -->
        <div v-show="activeTab === 'factures'">
          <div v-if="loadingDetail" class="flex items-center justify-center py-12">
            <svg class="w-6 h-6 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
          </div>
          <div v-else-if="customerDocuments.length === 0" class="text-center py-12 text-gray-400 dark:text-gray-500">
            <svg
              class="w-12 h-12 mx-auto mb-3 text-gray-300"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
              />
            </svg>
            <p class="text-sm">Aucun document</p>
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Code</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Date</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Type</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">Total TTC</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">Reste dû</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-center">Statut</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="inv in customerDocuments" :key="inv.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td class="py-2.5 px-3 font-mono text-xs">{{ inv.reference }}</td>
                  <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400">{{ formatDate(inv.issued_at) }}</td>
                  <td class="py-2.5 px-3">
                    <span
                      class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                      :class="docTypeClass(inv.document_type)"
                    >
                      {{ docTypeLabel(inv.document_type) }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-medium">
                    {{ formatNumber(inv.footer?.total_ttc ?? 0) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                  </td>
                  <td
                    class="py-2.5 px-3 text-right font-mono font-medium"
                    :class="(inv.footer?.amount_due ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600'"
                  >
                    {{ formatNumber(inv.footer?.amount_due ?? 0) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                  </td>
                  <td class="py-2.5 px-3 text-center">
                    <span
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                      :class="statusClass(inv.status)"
                    >
                      {{ statusLabel(inv.status) }}
                    </span>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="border-t-2 border-gray-200 dark:border-gray-700 font-semibold bg-gray-50 dark:bg-gray-900">
                  <td colspan="3" class="py-2.5 px-3 text-sm text-gray-600 dark:text-gray-400">
                    {{ customerDocuments.length }} document(s)
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono">
                    {{ formatNumber(totalInvoicesTTC) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                  </td>
                  <td
                    class="py-2.5 px-3 text-right font-mono"
                    :class="totalInvoicesDue > 0 ? 'text-red-600' : 'text-emerald-600'"
                  >
                    {{ formatNumber(totalInvoicesDue) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- TAB: Paiements -->
        <div v-show="activeTab === 'paiements'">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
              Historique des paiements
            </h3>
            <button
              v-if="editTarget"
              type="button"
              class="flex items-center gap-2 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition"
              @click="openBulkPayment(editTarget)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              Enregistrer un paiement
            </button>
          </div>
          <div v-if="loadingDetail" class="flex items-center justify-center py-12">
            <svg class="w-6 h-6 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
          </div>
          <div v-else-if="customerPayments.length === 0" class="text-center py-12 text-gray-400 dark:text-gray-500">
            <svg
              class="w-12 h-12 mx-auto mb-3 text-gray-300"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"
              />
            </svg>
            <p class="text-sm">Aucun paiement</p>
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Code</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Facture</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Date</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Méthode</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">Montant</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Référence</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="pay in customerPayments" :key="pay.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td class="py-2.5 px-3 font-mono text-xs">{{ pay.payment_code }}</td>
                  <td class="py-2.5 px-3 font-mono text-xs text-blue-600">{{ pay._doc_code }}</td>
                  <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400">{{ formatDate(pay.paid_at) }}</td>
                  <td class="py-2.5 px-3">
                    <span
                      class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                    >
                      {{ methodLabel(pay.method) }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-medium text-emerald-600">
                    {{ formatNumber(Number(pay.amount)) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                  </td>
                  <td class="py-2.5 px-3 text-gray-500 dark:text-gray-400 text-xs">{{ pay.reference || '—' }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="border-t-2 border-gray-200 dark:border-gray-700 font-semibold bg-gray-50 dark:bg-gray-900">
                  <td colspan="4" class="py-2.5 px-3 text-sm text-gray-600 dark:text-gray-400">
                    {{ customerPayments.length }} paiement(s)
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono text-emerald-600">
                    {{ formatNumber(totalPayments) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- TAB: Statistiques -->
        <div v-show="activeTab === 'statistiques'">
          <div v-if="loadingDetail" class="flex items-center justify-center py-12">
            <svg class="w-6 h-6 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
          </div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <!-- Total documents -->
            <div class="bg-blue-50 rounded-xl p-4 border border-blue-100">
              <p class="text-xs text-blue-600 font-medium mb-1">Total documents</p>
              <p class="text-2xl font-bold text-blue-900">{{ customerDocuments.length }}</p>
            </div>
            <!-- Documents impayés -->
            <div class="bg-red-50 rounded-xl p-4 border border-red-100">
              <p class="text-xs text-red-600 font-medium mb-1">Documents impayés</p>
              <p class="text-2xl font-bold text-red-900">{{ unpaidInvoices }}</p>
            </div>
            <!-- CA total -->
            <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
              <p class="text-xs text-emerald-600 font-medium mb-1">CA total</p>
              <p class="text-xl font-bold text-emerald-900 font-mono">
                {{ formatNumber(totalInvoicesTTC) }} <span class="text-sm font-normal text-emerald-600">DH</span>
              </p>
            </div>
            <!-- Total payé -->
            <div class="bg-teal-50 rounded-xl p-4 border border-teal-100">
              <p class="text-xs text-teal-600 font-medium mb-1">Total payé</p>
              <p class="text-xl font-bold text-teal-900 font-mono">
                {{ formatNumber(totalPayments) }} <span class="text-sm font-normal text-teal-600">DH</span>
              </p>
            </div>
            <!-- Reste à payer -->
            <div class="bg-amber-50 rounded-xl p-4 border border-amber-100">
              <p class="text-xs text-amber-600 font-medium mb-1">Reste à payer</p>
              <p
                class="text-xl font-bold font-mono"
                :class="totalInvoicesDue > 0 ? 'text-amber-900' : 'text-emerald-700'"
              >
                {{ formatNumber(totalInvoicesDue) }} <span class="text-sm font-normal text-amber-600">DH</span>
              </p>
            </div>
            <!-- Taux de paiement -->
            <div class="sm:col-span-3 bg-gray-50 dark:bg-gray-900 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
              <div class="flex items-center justify-between mb-2">
                <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Taux de recouvrement</p>
                <span
                  class="text-sm font-bold"
                  :class="
                    paymentRate >= 80 ? 'text-emerald-600' : paymentRate >= 50 ? 'text-amber-600' : 'text-red-600'
                  "
                >
                  {{ paymentRate.toFixed(1) }}%
                </span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div
                  class="h-2.5 rounded-full transition-all duration-500"
                  :class="paymentRate >= 80 ? 'bg-emerald-500' : paymentRate >= 50 ? 'bg-amber-500' : 'bg-red-500'"
                  :style="{ width: Math.min(paymentRate, 100) + '%' }"
                ></div>
              </div>
            </div>
          </div>
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
          class="px-4 py-2 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition disabled:opacity-60"
          :disabled="saving"
          @click="submit"
        >
          {{ saving ? $t('common.saving') : editTarget ? $t('common.update') : $t('common.create') }}
        </button>
      </template>
    </BaseModal>

    <!-- Show (Read-only) Modal -->
    <BaseModal v-model="showShowModal" :title="showTarget?.tp_title ?? 'Client'" size="xl">
      <!-- Tab Navigation -->
      <div class="border-b border-gray-200 dark:border-gray-700 -mt-2 mb-4">
        <nav class="flex gap-0 -mb-px overflow-x-auto">
          <button
            v-for="tab in showTabs"
            :key="tab.key"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
            :class="
              showActiveTab === tab.key
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            "
            @click="showActiveTab = tab.key"
          >
            <component :is="tab.icon" class="w-4 h-4" />
            {{ tab.label }}
            <span
              v-if="tab.badge !== undefined && tab.badge > 0"
              class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-xs font-semibold leading-none"
              :class="showActiveTab === tab.key ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'"
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
            <!-- Credit gauge -->
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

        <!-- TAB: Factures -->
        <div v-show="showActiveTab === 'factures'">
          <div v-if="showLoadingDetail" class="flex items-center justify-center py-12">
            <svg class="w-6 h-6 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
          </div>
          <div v-else-if="showDocuments.length === 0" class="text-center py-12 text-gray-400 dark:text-gray-500">
            <svg
              class="w-12 h-12 mx-auto mb-3 text-gray-300"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
              />
            </svg>
            <p class="text-sm">Aucun document</p>
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Code</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Date</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Type</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">Total TTC</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">Reste dû</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-center">Statut</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="inv in showDocuments" :key="inv.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td class="py-2.5 px-3 font-mono text-xs">{{ inv.reference }}</td>
                  <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400">{{ formatDate(inv.issued_at) }}</td>
                  <td class="py-2.5 px-3">
                    <span
                      class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                      :class="docTypeClass(inv.document_type)"
                    >
                      {{ docTypeLabel(inv.document_type) }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-medium">
                    {{ formatNumber(inv.footer?.total_ttc ?? 0) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                  </td>
                  <td
                    class="py-2.5 px-3 text-right font-mono font-medium"
                    :class="(inv.footer?.amount_due ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600'"
                  >
                    {{ formatNumber(inv.footer?.amount_due ?? 0) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                  </td>
                  <td class="py-2.5 px-3 text-center">
                    <span
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                      :class="statusClass(inv.status)"
                    >
                      {{ statusLabel(inv.status) }}
                    </span>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="border-t-2 border-gray-200 dark:border-gray-700 font-semibold bg-gray-50 dark:bg-gray-900">
                  <td colspan="3" class="py-2.5 px-3 text-sm text-gray-600 dark:text-gray-400">{{ showDocuments.length }} document(s)</td>
                  <td class="py-2.5 px-3 text-right font-mono">
                    {{ formatNumber(showTotalTTC) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                  </td>
                  <td
                    class="py-2.5 px-3 text-right font-mono"
                    :class="showTotalDue > 0 ? 'text-red-600' : 'text-emerald-600'"
                  >
                    {{ formatNumber(showTotalDue) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- TAB: Paiements -->
        <div v-show="showActiveTab === 'paiements'">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
              Historique des paiements
            </h3>
            <button
              v-if="showTarget"
              type="button"
              class="flex items-center gap-2 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition"
              @click="openBulkPayment(showTarget)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              Enregistrer un paiement
            </button>
          </div>
          <div v-if="showLoadingDetail" class="flex items-center justify-center py-12">
            <svg class="w-6 h-6 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
          </div>
          <div v-else-if="showPayments.length === 0" class="text-center py-12 text-gray-400 dark:text-gray-500">
            <svg
              class="w-12 h-12 mx-auto mb-3 text-gray-300"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"
              />
            </svg>
            <p class="text-sm">Aucun paiement</p>
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Code</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Facture</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Date</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Méthode</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">Montant</th>
                  <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Référence</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="pay in showPayments" :key="pay.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td class="py-2.5 px-3 font-mono text-xs">{{ pay.payment_code }}</td>
                  <td class="py-2.5 px-3 font-mono text-xs text-blue-600">{{ pay._doc_code }}</td>
                  <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400">{{ formatDate(pay.paid_at) }}</td>
                  <td class="py-2.5 px-3">
                    <span
                      class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                    >
                      {{ methodLabel(pay.method) }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-medium text-emerald-600">
                    {{ formatNumber(Number(pay.amount)) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                  </td>
                  <td class="py-2.5 px-3 text-gray-500 dark:text-gray-400 text-xs">{{ pay.reference || '—' }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="border-t-2 border-gray-200 dark:border-gray-700 font-semibold bg-gray-50 dark:bg-gray-900">
                  <td colspan="4" class="py-2.5 px-3 text-sm text-gray-600 dark:text-gray-400">{{ showPayments.length }} paiement(s)</td>
                  <td class="py-2.5 px-3 text-right font-mono text-emerald-600">
                    {{ formatNumber(showTotalPayments) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- TAB: Statistiques -->
        <div v-show="showActiveTab === 'statistiques'">
          <div v-if="showLoadingDetail" class="flex items-center justify-center py-12">
            <svg class="w-6 h-6 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
          </div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <div class="bg-blue-50 rounded-xl p-4 border border-blue-100">
              <p class="text-xs text-blue-600 font-medium mb-1">Total documents</p>
              <p class="text-2xl font-bold text-blue-900">{{ showDocuments.length }}</p>
            </div>
            <div class="bg-red-50 rounded-xl p-4 border border-red-100">
              <p class="text-xs text-red-600 font-medium mb-1">Documents impayés</p>
              <p class="text-2xl font-bold text-red-900">{{ showUnpaidCount }}</p>
            </div>
            <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
              <p class="text-xs text-emerald-600 font-medium mb-1">CA total</p>
              <p class="text-xl font-bold text-emerald-900 font-mono">
                {{ formatNumber(showTotalTTC) }} <span class="text-sm font-normal text-emerald-600">DH</span>
              </p>
            </div>
            <div class="bg-teal-50 rounded-xl p-4 border border-teal-100">
              <p class="text-xs text-teal-600 font-medium mb-1">Total payé</p>
              <p class="text-xl font-bold text-teal-900 font-mono">
                {{ formatNumber(showTotalPayments) }} <span class="text-sm font-normal text-teal-600">DH</span>
              </p>
            </div>
            <div class="bg-amber-50 rounded-xl p-4 border border-amber-100">
              <p class="text-xs text-amber-600 font-medium mb-1">Reste à payer</p>
              <p class="text-xl font-bold font-mono" :class="showTotalDue > 0 ? 'text-amber-900' : 'text-emerald-700'">
                {{ formatNumber(showTotalDue) }} <span class="text-sm font-normal text-amber-600">DH</span>
              </p>
            </div>
            <div class="sm:col-span-3 bg-gray-50 dark:bg-gray-900 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
              <div class="flex items-center justify-between mb-2">
                <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Taux de recouvrement</p>
                <span
                  class="text-sm font-bold"
                  :class="
                    showPaymentRate >= 80
                      ? 'text-emerald-600'
                      : showPaymentRate >= 50
                        ? 'text-amber-600'
                        : 'text-red-600'
                  "
                >
                  {{ showPaymentRate.toFixed(1) }}%
                </span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div
                  class="h-2.5 rounded-full transition-all duration-500"
                  :class="
                    showPaymentRate >= 80 ? 'bg-emerald-500' : showPaymentRate >= 50 ? 'bg-amber-500' : 'bg-red-500'
                  "
                  :style="{ width: Math.min(showPaymentRate, 100) + '%' }"
                ></div>
              </div>
            </div>
          </div>
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
          class="px-4 py-2 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition"
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
    <BaseModal
      v-model="showPaymentModal"
      :title="'Enregistrer un paiement — ' + (paymentTarget?.tp_title ?? '')"
      size="md"
    >
      <div class="space-y-5">
        <!-- Unpaid invoices summary -->
        <div v-if="paymentUnpaidDocs.length > 0" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-semibold text-amber-800">Factures impayées</p>
            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 text-amber-700"
              >{{ paymentUnpaidDocs.length }} facture(s)</span
            >
          </div>
          <div class="max-h-40 overflow-y-auto space-y-1">
            <div
              v-for="doc in paymentUnpaidDocs"
              :key="doc.id"
              class="flex items-center justify-between text-sm py-1 px-2 rounded hover:bg-amber-100/50"
            >
              <div class="flex items-center gap-2">
                <span class="font-mono text-xs text-gray-600 dark:text-gray-400">{{ doc.reference }}</span>
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ formatDate(doc.issued_at) }}</span>
              </div>
              <span class="font-mono text-sm font-medium text-red-600"
                >{{ formatNumber(Number(doc.footer?.amount_due ?? 0)) }}
                <span class="text-xs text-gray-400 dark:text-gray-500">DH</span></span
              >
            </div>
          </div>
          <div class="mt-2 pt-2 border-t border-amber-200 flex items-center justify-between">
            <span class="text-sm font-semibold text-amber-800">Total restant dû</span>
            <span class="font-mono text-base font-bold text-red-600"
              >{{ formatNumber(paymentTotalDue) }} <span class="text-xs text-gray-400 dark:text-gray-500">DH</span></span
            >
          </div>
        </div>
        <div v-else-if="!paymentLoading" class="text-center py-8 text-gray-400 dark:text-gray-500">
          <svg
            class="w-10 h-10 mx-auto mb-2 text-gray-300"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          <p class="text-sm">Aucune facture impayée</p>
        </div>
        <div v-if="paymentLoading" class="flex items-center justify-center py-8">
          <svg class="w-6 h-6 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
          </svg>
        </div>

        <!-- Payment form -->
        <div v-if="paymentUnpaidDocs.length > 0 && !paymentLoading" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >Montant <span class="text-red-500">*</span></label
            >
            <div class="relative">
              <input
                v-model.number="paymentForm.amount"
                type="number"
                min="0.01"
                step="0.01"
                placeholder="0.00"
                class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent pr-12"
              />
              <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 dark:text-gray-500 font-medium">DH</span>
            </div>
            <p v-if="paymentForm.amount > paymentTotalDue && paymentTotalDue > 0" class="text-xs text-amber-600 mt-1">
              Le montant dépasse le total dû. L'excédent de {{ formatNumber(paymentForm.amount - paymentTotalDue) }} DH
              ne sera pas affecté.
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >Méthode de paiement <span class="text-red-500">*</span></label
            >
            <select
              v-model="paymentForm.method"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
            >
              <option value="cash">Espèces</option>
              <option value="bank_transfer">Virement bancaire</option>
              <option value="cheque">Chèque</option>
              <option value="effet">Effet</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Référence</label>
            <input
              v-model="paymentForm.reference"
              type="text"
              placeholder="N° chèque, virement..."
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
            <textarea
              v-model="paymentForm.notes"
              rows="2"
              placeholder="Remarques..."
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent resize-none"
            ></textarea>
          </div>

          <!-- Payment result -->
          <div
            v-if="paymentResult"
            class="rounded-lg border p-3 text-sm"
            :class="
              paymentResult.remaining > 0
                ? 'bg-amber-50 border-amber-200 text-amber-800'
                : 'bg-emerald-50 border-emerald-200 text-emerald-800'
            "
          >
            <p class="font-semibold">{{ paymentResult.message }}</p>
            <p class="text-xs mt-1">Montant affecté : {{ formatNumber(paymentResult.total_applied) }} DH</p>
            <p v-if="paymentResult.remaining > 0" class="text-xs">
              Excédent non affecté : {{ formatNumber(paymentResult.remaining) }} DH
            </p>
          </div>
        </div>
      </div>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition"
          @click="showPaymentModal = false"
        >
          Fermer
        </button>
        <button
          v-if="paymentUnpaidDocs.length > 0 && !paymentLoading"
          class="px-4 py-2 text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition disabled:opacity-60"
          :disabled="paymentSaving || !paymentForm.amount || paymentForm.amount <= 0"
          @click="submitBulkPayment"
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
              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          {{ paymentSaving ? 'Enregistrement...' : 'Enregistrer le paiement' }}
        </button>
      </template>
    </BaseModal>

    <!-- Delete Modal -->
    <BaseModal v-model="showDelete" :title="$t('customers.deleteTitle')" size="sm">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ $t('customers.deleteConfirm') }} <span class="font-semibold">{{ deleteTarget?.tp_title }}</span
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
import { ref, reactive, computed, watch, onMounted, h } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useThirdPartnerStore } from '@/stores/thirdPartner'
import { usePriceListStore } from '@/stores/priceList'
import { useExcelExport } from '@/composables/useExcelExport'
import http from '@/services/http'
import BaseTable from '@/components/BaseTable.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'

const { t } = useI18n()
const store = useThirdPartnerStore()
const { items } = storeToRefs(store)
const priceListStore = usePriceListStore()
const { items: priceLists } = storeToRefs(priceListStore)

const { exporting, exportExcel } = useExcelExport()

function onExport() {
  exportExcel('/export/third-partners', { ...buildParams(), tp_Role: 'customer' })
}

// ── Tab icons (render functions) ─────────────────────────────────────────
const IconInfo = () =>
  h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', viewBox: '0 0 24 24' }, [
    h('path', {
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
      d: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    }),
  ])
const IconCredit = () =>
  h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', viewBox: '0 0 24 24' }, [
    h('path', {
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
      d: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    }),
  ])
const IconInvoice = () =>
  h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', viewBox: '0 0 24 24' }, [
    h('path', {
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
      d: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    }),
  ])
const IconPayment = () =>
  h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', viewBox: '0 0 24 24' }, [
    h('path', {
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
      d: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    }),
  ])
const IconFiscal = () =>
  h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', viewBox: '0 0 24 24' }, [
    h('path', {
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
      d: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    }),
  ])
const IconStats = () =>
  h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', viewBox: '0 0 24 24' }, [
    h('path', {
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
      d: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
    }),
  ])

// ── Tab state ────────────────────────────────────────────────────────────
const activeTab = ref<'info' | 'fiscal' | 'credit' | 'factures' | 'paiements' | 'statistiques'>('info')
const loadingDetail = ref(false)
const customerDetail = ref<any>(null)

interface TabDef {
  key: 'info' | 'fiscal' | 'credit' | 'factures' | 'paiements' | 'statistiques'
  label: string
  icon: () => any
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
      { key: 'factures', label: 'Documents', icon: IconInvoice, badge: customerDocuments.value.length },
      { key: 'paiements', label: 'Paiements', icon: IconPayment, badge: customerPayments.value.length },
      { key: 'statistiques', label: 'Statistiques', icon: IconStats },
    )
  }
  return tabs
})

// ── Customer detail data ─────────────────────────────────────────────────
const customerDocuments = computed(() => {
  if (!customerDetail.value?.document_headers) return []
  return customerDetail.value.document_headers.sort(
    (a: any, b: any) => new Date(b.issued_at).getTime() - new Date(a.issued_at).getTime(),
  )
})

const customerPayments = computed(() => {
  if (!customerDetail.value?.document_headers) return []
  const payments: any[] = []
  for (const doc of customerDetail.value.document_headers) {
    if (doc.payments?.length) {
      for (const p of doc.payments) {
        payments.push({ ...p, _doc_code: doc.reference })
      }
    }
  }
  return payments.sort((a, b) => new Date(b.paid_at).getTime() - new Date(a.paid_at).getTime())
})

const totalInvoicesTTC = computed(() =>
  customerDocuments.value.reduce((sum: number, inv: any) => sum + Number(inv.footer?.total_ttc ?? 0), 0),
)

const totalInvoicesDue = computed(() =>
  customerDocuments.value.reduce((sum: number, inv: any) => sum + Number(inv.footer?.amount_due ?? 0), 0),
)

const totalPayments = computed(() =>
  customerPayments.value.reduce((sum: number, p: any) => sum + Number(p.amount ?? 0), 0),
)

const unpaidInvoices = computed(
  () => customerDocuments.value.filter((inv: any) => Number(inv.footer?.amount_due ?? 0) > 0).length,
)

const paymentRate = computed(() =>
  totalInvoicesTTC.value > 0 ? (totalPayments.value / totalInvoicesTTC.value) * 100 : 0,
)

const creditPercent = computed(() => {
  const limit = form.seuil_credit ?? 0
  if (limit <= 0) return 0
  return ((form.encours_actuel ?? 0) / limit) * 100
})

const creditAvailableForm = computed(() => (form.seuil_credit ?? 0) - (form.encours_actuel ?? 0))

async function loadCustomerDetail(id: number) {
  loadingDetail.value = true
  try {
    const { data } = await http.get(`/third-partners/${id}`)
    customerDetail.value = data
  } catch {
    customerDetail.value = null
  } finally {
    loadingDetail.value = false
  }
}

// ── UI state ──────────────────────────────────────────────────────────────
const search = ref('')
const statusFilter = ref('')
const toast = ref(null)

let searchTimer: ReturnType<typeof setTimeout> | null = null

const showModal = ref(false)
const showDelete = ref(false)
const saving = ref(false)
const deleting = ref(false)
const editTarget = ref<any>(null)
const deleteTarget = ref<any>(null)

// ── Show (read-only) modal state ─────────────────────────────────────────
const showShowModal = ref(false)
const showTarget = ref<any>(null)
const showActiveTab = ref<'info' | 'fiscal' | 'credit' | 'factures' | 'paiements' | 'statistiques'>('info')
const showLoadingDetail = ref(false)
const showDetail = ref<any>(null)

const showDocuments = computed(() => {
  if (!showDetail.value?.document_headers) return []
  return showDetail.value.document_headers.sort(
    (a: any, b: any) => new Date(b.issued_at).getTime() - new Date(a.issued_at).getTime(),
  )
})

const showPayments = computed(() => {
  if (!showDetail.value?.document_headers) return []
  const payments: any[] = []
  for (const doc of showDetail.value.document_headers) {
    if (doc.payments?.length) {
      for (const p of doc.payments) {
        payments.push({ ...p, _doc_code: doc.reference })
      }
    }
  }
  return payments.sort((a, b) => new Date(b.paid_at).getTime() - new Date(a.paid_at).getTime())
})

const showTotalTTC = computed(() =>
  showDocuments.value.reduce((sum: number, inv: any) => sum + Number(inv.footer?.total_ttc ?? 0), 0),
)
const showTotalDue = computed(() =>
  showDocuments.value.reduce((sum: number, inv: any) => sum + Number(inv.footer?.amount_due ?? 0), 0),
)
const showTotalPayments = computed(() =>
  showPayments.value.reduce((sum: number, p: any) => sum + Number(p.amount ?? 0), 0),
)
const showUnpaidCount = computed(
  () => showDocuments.value.filter((inv: any) => Number(inv.footer?.amount_due ?? 0) > 0).length,
)
const showPaymentRate = computed(() =>
  showTotalTTC.value > 0 ? (showTotalPayments.value / showTotalTTC.value) * 100 : 0,
)
const showCreditPercent = computed(() => {
  const limit = showTarget.value?.seuil_credit ?? 0
  if (limit <= 0) return 0
  return ((showTarget.value?.encours_actuel ?? 0) / limit) * 100
})
const showCreditAvailable = computed(
  () => (showTarget.value?.seuil_credit ?? 0) - (showTarget.value?.encours_actuel ?? 0),
)

// ── Bulk Payment state ──────────────────────────────────────────────────
const showPaymentModal = ref(false)
const paymentTarget = ref<any>(null)
const paymentLoading = ref(false)
const paymentSaving = ref(false)
const paymentDetail = ref<any>(null)
const paymentResult = ref<any>(null)

const paymentForm = reactive({
  amount: 0 as number,
  method: 'cash',
  reference: '',
  notes: '',
})

const paymentUnpaidDocs = computed(() => {
  if (!paymentDetail.value?.document_headers) return []
  return paymentDetail.value.document_headers
    .filter(
      (d: any) => ['InvoiceSale', 'InvoicePurchase'].includes(d.document_type) && Number(d.footer?.amount_due ?? 0) > 0,
    )
    .sort((a: any, b: any) => new Date(a.issued_at).getTime() - new Date(b.issued_at).getTime())
})

const paymentTotalDue = computed(() =>
  paymentUnpaidDocs.value.reduce((sum: number, d: any) => sum + Number(d.footer?.amount_due ?? 0), 0),
)

async function openBulkPayment(row: any) {
  paymentTarget.value = row
  paymentResult.value = null
  Object.assign(paymentForm, { amount: 0, method: 'cash', reference: '', notes: '' })
  paymentDetail.value = null
  showPaymentModal.value = true
  paymentLoading.value = true
  try {
    const { data } = await http.get(`/third-partners/${row.id}`)
    paymentDetail.value = data
  } catch {
    paymentDetail.value = null
  } finally {
    paymentLoading.value = false
  }
}

async function submitBulkPayment() {
  if (!paymentTarget.value || !paymentForm.amount || paymentForm.amount <= 0) return
  paymentSaving.value = true
  paymentResult.value = null
  try {
    const { data } = await http.post(`/third-partners/${paymentTarget.value.id}/bulk-payment`, {
      amount: paymentForm.amount,
      method: paymentForm.method,
      reference: paymentForm.reference || null,
      notes: paymentForm.notes || null,
    })
    paymentResult.value = data
    ;(toast.value as any)?.notify(data.message, 'success')
    // Reload unpaid list
    const { data: refreshed } = await http.get(`/third-partners/${paymentTarget.value.id}`)
    paymentDetail.value = refreshed
    // Reset amount
    paymentForm.amount = 0
    // Refresh main list
    loadPage(store.meta.current_page)
    // Refresh detail panels if their modals are open for the same customer
    if (showModal.value && editTarget.value?.id === paymentTarget.value.id) {
      customerDetail.value = refreshed
    }
    if (showShowModal.value && showTarget.value?.id === paymentTarget.value.id) {
      showDetail.value = refreshed
    }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    ;(toast.value as any)?.notify(e.response?.data?.message ?? 'Erreur lors du paiement', 'error')
  } finally {
    paymentSaving.value = false
  }
}

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
  // Load detail in background
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

const emptyForm = () => ({
  tp_title: '',
  tp_Role: 'customer' as const,
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
  type_compte: 'normal' as 'normal' | 'en_compte',
  frequence_facturation: null as string | null,
  price_list_id: null as number | null,
})
const form = reactive(emptyForm())

const columns = computed(() => [
  { key: 'tp_code', label: t('common.code') },
  { key: 'tp_title', label: t('common.name') },
  { key: 'tp_phone', label: t('common.phone') },
  { key: 'tp_email', label: t('common.email') },
  { key: 'tp_city', label: t('common.city') },
  { key: 'credit_available', label: t('customers.creditAvailable') },
  { key: 'tp_status', label: t('common.status') },
])

function creditAvailable(row: any): number {
  return (row.seuil_credit ?? 0) - (row.encours_actuel ?? 0)
}

function formatNumber(n: number): string {
  return n.toLocaleString('fr-MA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(d: string): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-MA', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function docTypeLabel(type: string): string {
  const map: Record<string, string> = {
    QuoteSale: 'Devis',
    CustomerOrder: 'Commande',
    DeliveryNote: 'BL',
    InvoiceSale: 'Facture',
    CreditNoteSale: 'Avoir',
    ReturnSale: 'Retour',
    PurchaseOrder: 'Commande',
    ReceiptNotePurchase: 'BR',
    InvoicePurchase: 'Facture achat',
    CreditNotePurchase: 'Avoir achat',
    ReturnPurchase: 'Retour achat',
  }
  return map[type] ?? type
}

function docTypeClass(type: string): string {
  const map: Record<string, string> = {
    InvoiceSale: 'bg-blue-100 text-blue-700',
    CreditNoteSale: 'bg-orange-100 text-orange-700',
    InvoicePurchase: 'bg-violet-100 text-violet-700',
    DeliveryNote: 'bg-emerald-100 text-emerald-700',
    QuoteSale: 'bg-gray-100 text-gray-600',
    CustomerOrder: 'bg-cyan-100 text-cyan-700',
    ReceiptNotePurchase: 'bg-teal-100 text-teal-700',
    PurchaseOrder: 'bg-indigo-100 text-indigo-700',
    ReturnSale: 'bg-red-100 text-red-700',
    ReturnPurchase: 'bg-red-100 text-red-700',
  }
  return map[type] ?? 'bg-gray-100 text-gray-600'
}

function statusClass(status: string): string {
  const map: Record<string, string> = {
    paid: 'bg-emerald-100 text-emerald-700',
    partial: 'bg-amber-100 text-amber-700',
    confirmed: 'bg-blue-100 text-blue-700',
    draft: 'bg-gray-100 text-gray-500',
    cancelled: 'bg-red-100 text-red-600',
  }
  return map[status] ?? 'bg-gray-100 text-gray-500'
}

function statusLabel(status: string): string {
  const map: Record<string, string> = {
    paid: 'Payé',
    partial: 'Partiel',
    confirmed: 'Confirmé',
    draft: 'Brouillon',
    cancelled: 'Annulé',
  }
  return map[status] ?? status
}

function methodLabel(method: string): string {
  const map: Record<string, string> = {
    cash: 'Espèces',
    bank_transfer: 'Virement',
    cheque: 'Chèque',
    effet: 'Effet',
    credit: 'Crédit',
  }
  return map[method] ?? method
}

// ── Server-side filter + pagination ─────────────────────────────────────
function buildParams(): Record<string, string> {
  const p: Record<string, string> = { role: 'customer' }
  if (search.value.trim()) p.search = search.value.trim()
  if (statusFilter.value !== '') p.status = statusFilter.value
  return p
}

function loadPage(page = 1) {
  Object.assign(store.params, buildParams())
  store.fetchPage(page)
}

function onPageChange(page: number) {
  loadPage(page)
}

watch([search, statusFilter], () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadPage(1), 350)
})

// ── CRUD ─────────────────────────────────────────────────────────────────
function openCreate() {
  editTarget.value = null
  customerDetail.value = null
  activeTab.value = 'info'
  Object.assign(form, emptyForm())
  showModal.value = true
}

function openEdit(row: any) {
  editTarget.value = row
  activeTab.value = 'info'
  customerDetail.value = null
  Object.assign(form, {
    tp_title: row.tp_title,
    tp_Role: row.tp_Role,
    tp_status: row.tp_status,
    tp_phone: row.tp_phone ?? '',
    tp_email: row.tp_email ?? '',
    tp_city: row.tp_city ?? '',
    tp_address: row.tp_address ?? '',
    tp_Ice_Number: row.tp_Ice_Number ?? '',
    tp_Rc_Number: row.tp_Rc_Number ?? '',
    tp_patente_Number: row.tp_patente_Number ?? '',
    tp_IdenFiscal: row.tp_IdenFiscal ?? '',
    encours_actuel: row.encours_actuel ?? 0,
    seuil_credit: row.seuil_credit ?? 0,
    type_compte: row.type_compte ?? 'normal',
    frequence_facturation: row.frequence_facturation ?? null,
    price_list_id: row.price_list_id ?? null,
  })
  showModal.value = true
  // Load detail data (invoices, payments) in background
  loadCustomerDetail(row.id)
}

async function submit() {
  if (!form.tp_title.trim()) return
  saving.value = true
  try {
    if (editTarget.value) {
      const { encours_actuel, ...updateData } = form
      await store.update(editTarget.value.id, updateData)
      ;(toast.value as any)?.notify(t('customers.updated'), 'success')
    } else {
      const { encours_actuel, ...createData } = form
      await store.create(createData)
      ;(toast.value as any)?.notify(t('customers.created'), 'success')
    }
    showModal.value = false
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    ;(toast.value as any)?.notify(e.response?.data?.message ?? t('common.failedSave'), 'error')
  } finally {
    saving.value = false
  }
}

function confirmDelete(row: any) {
  deleteTarget.value = row
  showDelete.value = true
}

async function doDelete() {
  deleting.value = true
  try {
    await store.remove(deleteTarget.value.id)
    ;(toast.value as any)?.notify(t('customers.deleted'), 'success')
    showDelete.value = false
  } catch {
    ;(toast.value as any)?.notify(t('common.failedDelete'), 'error')
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  loadPage()
  if (!priceLists.value.length) priceListStore.fetchAll()
})
</script>
