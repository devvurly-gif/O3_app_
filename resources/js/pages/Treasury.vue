<template>
  <div class="space-y-5">
    <!-- ── Header ────────────────────────────────────────────────── -->
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h2 class="text-[26px] sm:text-[30px] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white">
          {{ $t('treasury.title') }}
        </h2>
        <p class="text-sm text-[#8A8F9C] dark:text-gray-400 mt-1">{{ $t('treasury.subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <button
          class="flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-bold rounded-[11px] transition"
          @click="openTransaction('out')"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m0 0l-6-6m6 6l6-6" />
          </svg>
          {{ $t('treasury.newExpense') }}
        </button>
        <button
          class="flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-[11px] transition"
          @click="openTransaction('in')"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 20V4m0 0l-6 6m6-6l6 6" />
          </svg>
          {{ $t('treasury.newIncome') }}
        </button>
        <button
          class="flex items-center gap-2 px-4 py-2.5 bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white text-sm font-bold rounded-[11px] shadow-[0_8px_20px_-8px_rgba(124,92,252,0.6)] transition"
          @click="openTransfer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4"
            />
          </svg>
          {{ $t('treasury.transfer') }}
        </button>
      </div>
    </div>

    <!-- ── Période ───────────────────────────────────────────────── -->
    <div
      class="flex flex-wrap items-center gap-2.5 sm:gap-3 bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-[14px] p-3"
    >
      <label class="text-[13px] font-semibold text-[#4A4F5B] dark:text-gray-300">{{ $t('treasury.period') }}</label>
      <input
        id="treasury-from"
        v-model="period.from"
        type="date"
        :aria-label="$t('treasury.from')"
        class="px-3 py-2 text-input rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
      />
      <span class="text-gray-400">→</span>
      <input
        id="treasury-to"
        v-model="period.to"
        type="date"
        :aria-label="$t('treasury.to')"
        class="px-3 py-2 text-input rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
      />
      <button
        v-for="shortcut in shortcuts"
        :key="shortcut.key"
        class="px-3 py-1.5 text-[13px] font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 text-[#4A4F5B] dark:text-gray-300 hover:bg-[#F5F6F8] dark:hover:bg-gray-700 transition"
        @click="applyShortcut(shortcut.key)"
      >
        {{ shortcut.label }}
      </button>
    </div>

    <!-- ── Cartes de synthèse ────────────────────────────────────── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
        <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400">{{ $t('treasury.totalBalance') }}</div>
        <div class="text-2xl sm:text-[26px] font-extrabold text-gray-900 dark:text-white mt-1 font-mono">
          {{ fmt(summary?.total_balance) }} <span class="text-sm font-normal text-gray-400">DH</span>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
        <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400">{{ $t('treasury.totalIn') }}</div>
        <div class="text-2xl sm:text-[26px] font-extrabold text-emerald-600 mt-1 font-mono">
          {{ fmt(summary?.total_in) }} <span class="text-sm font-normal text-gray-400">DH</span>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
        <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400">{{ $t('treasury.totalOut') }}</div>
        <div class="text-2xl sm:text-[26px] font-extrabold text-red-600 mt-1 font-mono">
          {{ fmt(summary?.total_out) }} <span class="text-sm font-normal text-gray-400">DH</span>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-4 sm:p-5">
        <div class="text-[13px] text-[#8A8F9C] dark:text-gray-400">{{ $t('treasury.net') }}</div>
        <div
          class="text-2xl sm:text-[26px] font-extrabold mt-1 font-mono"
          :class="Number(summary?.net ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600'"
        >
          {{ fmt(summary?.net) }} <span class="text-sm font-normal text-gray-400">DH</span>
        </div>
      </div>
    </div>

    <!-- ── Onglets ───────────────────────────────────────────────── -->
    <div class="border-b border-[#ECEEF2] dark:border-gray-700 flex gap-1 overflow-x-auto">
      <button
        v-for="tab in visibleTabs"
        :key="tab.key"
        class="px-4 py-2.5 text-sm font-semibold whitespace-nowrap border-b-2 transition"
        :class="
          activeTab === tab.key
            ? 'border-[#7C5CFC] text-[#7C5CFC]'
            : 'border-transparent text-[#8A8F9C] dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
        "
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- ── Onglet : Synthèse ─────────────────────────────────────── -->
    <div v-show="activeTab === 'summary'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-5">
        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wide mb-4">
          {{ $t('treasury.accounts') }}
        </h3>
        <div v-if="!summary?.accounts?.length" class="text-sm text-gray-400 py-6 text-center">
          {{ $t('treasury.noAccount') }}
        </div>
        <table v-else class="w-full text-sm">
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr v-for="acc in summary.accounts" :key="acc.id">
              <td class="py-2.5">
                <div class="font-medium text-gray-800 dark:text-gray-200">{{ acc.ca_title }}</div>
                <div class="text-xs text-gray-400">{{ $t('treasury.types.' + acc.ca_type) }}</div>
              </td>
              <td
                class="py-2.5 text-right font-mono font-semibold"
                :class="Number(acc.balance) >= 0 ? 'text-gray-800 dark:text-gray-100' : 'text-red-600'"
              >
                {{ fmt(acc.balance) }} <span class="text-xs text-gray-400">DH</span>
              </td>
            </tr>
          </tbody>
          <tfoot class="border-t-2 border-gray-200 dark:border-gray-600">
            <tr>
              <td class="py-3 font-bold text-gray-700 dark:text-gray-200">{{ $t('treasury.totalBalance') }}</td>
              <td class="py-3 text-right font-mono font-extrabold text-gray-900 dark:text-white">
                {{ fmt(summary.total_balance) }} <span class="text-xs text-gray-400">DH</span>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl p-5">
        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wide mb-4">
          {{ $t('treasury.byCategory') }}
        </h3>
        <div v-if="!summary?.by_category?.length" class="text-sm text-gray-400 py-6 text-center">
          {{ $t('treasury.noEntry') }}
        </div>
        <div v-else class="space-y-3">
          <div v-for="row in summary.by_category" :key="row.category_title + row.direction">
            <div class="flex items-center justify-between text-sm mb-1">
              <span class="text-gray-700 dark:text-gray-300">
                {{ row.category_title }}
                <span class="text-xs text-gray-400">({{ row.entries }})</span>
              </span>
              <span
                class="font-mono font-semibold"
                :class="row.direction === 'in' ? 'text-emerald-600' : 'text-red-600'"
              >
                {{ row.direction === 'in' ? '+' : '−' }}{{ fmt(row.total) }} DH
              </span>
            </div>
            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
              <div
                class="h-1.5 rounded-full"
                :class="row.direction === 'in' ? 'bg-emerald-500' : 'bg-red-500'"
                :style="{ width: categoryShare(row) + '%' }"
              />
            </div>
          </div>
        </div>
        <p class="text-xs text-gray-400 mt-4">{{ $t('treasury.byCategoryHint') }}</p>
      </div>
    </div>

    <!-- ── Onglet : Journal ──────────────────────────────────────── -->
    <div v-show="activeTab === 'journal'" class="space-y-3">
      <div
        class="flex flex-wrap items-center gap-2.5 bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-[14px] p-3"
      >
        <input
          v-model="journalFilters.search"
          type="text"
          :placeholder="$t('common.search')"
          :aria-label="$t('common.search')"
          class="px-3.5 py-2.5 text-input rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 dark:text-gray-100 w-56 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
        />
        <select
          v-model="journalFilters.direction"
          :aria-label="$t('treasury.direction')"
          class="px-3 py-2.5 text-[13px] font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 dark:text-gray-200"
        >
          <option value="">{{ $t('treasury.allDirections') }}</option>
          <option value="in">{{ $t('treasury.in') }}</option>
          <option value="out">{{ $t('treasury.out') }}</option>
        </select>
        <select
          v-model="journalFilters.source"
          :aria-label="$t('treasury.source')"
          class="px-3 py-2.5 text-[13px] font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 dark:text-gray-200"
        >
          <option value="all">{{ $t('treasury.allSources') }}</option>
          <option value="manual">{{ $t('treasury.sourceManual') }}</option>
          <option value="payment">{{ $t('treasury.sourcePayment') }}</option>
        </select>
        <select
          v-model="journalFilters.account_id"
          :aria-label="$t('treasury.account')"
          class="px-3 py-2.5 text-[13px] font-semibold rounded-[10px] border border-[#E1E3E9] dark:border-gray-600 bg-[#FAFBFC] dark:bg-gray-700 dark:text-gray-200"
        >
          <option value="">{{ $t('treasury.allAccounts') }}</option>
          <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.ca_title }}</option>
        </select>
      </div>

      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 uppercase text-xs">
              <tr>
                <th class="text-left px-4 py-3 font-semibold">{{ $t('common.date') }}</th>
                <th class="text-left px-4 py-3 font-semibold">{{ $t('common.code') }}</th>
                <th class="text-left px-4 py-3 font-semibold">{{ $t('treasury.label') }}</th>
                <th class="text-left px-4 py-3 font-semibold">{{ $t('treasury.account') }}</th>
                <th class="text-left px-4 py-3 font-semibold">{{ $t('treasury.category') }}</th>
                <th class="text-left px-4 py-3 font-semibold">{{ $t('treasury.source') }}</th>
                <th class="text-right px-4 py-3 font-semibold">{{ $t('common.amount') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-if="loading">
                <td colspan="7" class="px-4 py-10 text-center text-gray-400">{{ $t('common.loading') }}</td>
              </tr>
              <tr v-else-if="!journal.length">
                <td colspan="7" class="px-4 py-10 text-center text-gray-400">{{ $t('treasury.noEntry') }}</td>
              </tr>
              <tr
                v-for="row in journal"
                v-else
                :key="row.source + '-' + row.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                :class="row.status === 'cancelled' ? 'opacity-50 line-through' : ''"
              >
                <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300">{{ fmtDate(row.date) }}</td>
                <td class="px-4 py-3">
                  <span
                    class="font-mono text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 px-2 py-0.5 rounded"
                  >
                    {{ row.code ?? '—' }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="text-gray-800 dark:text-gray-200">{{ row.label }}</div>
                  <div v-if="row.partner_title" class="text-xs text-gray-400">{{ row.partner_title }}</div>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.account_title ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.category_title ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold"
                    :class="
                      row.source === 'payment'
                        ? 'bg-[#EEF2FF] text-[#4F46E5] dark:bg-indigo-500/20 dark:text-indigo-300'
                        : 'bg-[#F0F1F4] text-[#7A7F8C] dark:bg-gray-700 dark:text-gray-300'
                    "
                  >
                    {{ row.source === 'payment' ? $t('treasury.sourcePayment') : $t('treasury.sourceManual') }}
                  </span>
                </td>
                <td
                  class="px-4 py-3 text-right font-mono font-semibold whitespace-nowrap"
                  :class="row.direction === 'in' ? 'text-emerald-600' : 'text-red-600'"
                >
                  {{ row.direction === 'in' ? '+' : '−' }}{{ fmt(row.amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
          <span class="text-xs text-gray-400">{{ journalMeta.total }} {{ $t('treasury.entries') }}</span>
          <div class="flex items-center gap-2">
            <button
              class="px-3 py-1.5 text-sm rounded-lg border border-gray-200 dark:border-gray-600 disabled:opacity-40 dark:text-gray-200"
              :disabled="journalMeta.current_page <= 1"
              @click="goJournal(journalMeta.current_page - 1)"
            >
              ‹
            </button>
            <span class="text-sm text-gray-500 dark:text-gray-400">
              {{ journalMeta.current_page }} / {{ journalMeta.last_page }}
            </span>
            <button
              class="px-3 py-1.5 text-sm rounded-lg border border-gray-200 dark:border-gray-600 disabled:opacity-40 dark:text-gray-200"
              :disabled="journalMeta.current_page >= journalMeta.last_page"
              @click="goJournal(journalMeta.current_page + 1)"
            >
              ›
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Onglet : Écritures ────────────────────────────────────── -->
    <div
      v-show="activeTab === 'entries'"
      class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl overflow-hidden"
    >
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 uppercase text-xs">
            <tr>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('common.date') }}</th>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('common.code') }}</th>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('treasury.label') }}</th>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('treasury.category') }}</th>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('treasury.account') }}</th>
              <th class="text-right px-4 py-3 font-semibold">{{ $t('common.amount') }}</th>
              <th class="text-right px-4 py-3 font-semibold">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr v-if="!transactions.length">
              <td colspan="7" class="px-4 py-10 text-center text-gray-400">{{ $t('treasury.noEntry') }}</td>
            </tr>
            <tr
              v-for="row in transactions"
              :key="row.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
              :class="row.ct_status === 'cancelled' ? 'opacity-50' : ''"
            >
              <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300">{{ fmtDate(row.ct_date) }}</td>
              <td class="px-4 py-3">
                <span
                  class="font-mono text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 px-2 py-0.5 rounded"
                >
                  {{ row.ct_code }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div
                  class="text-gray-800 dark:text-gray-200"
                  :class="row.ct_status === 'cancelled' ? 'line-through' : ''"
                >
                  {{ row.ct_label }}
                </div>
                <div v-if="row.thirdPartner" class="text-xs text-gray-400">{{ row.thirdPartner.tp_title }}</div>
              </td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.category?.cc_title ?? '—' }}</td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.account?.ca_title ?? '—' }}</td>
              <td
                class="px-4 py-3 text-right font-mono font-semibold whitespace-nowrap"
                :class="row.ct_direction === 'in' ? 'text-emerald-600' : 'text-red-600'"
              >
                {{ row.ct_direction === 'in' ? '+' : '−' }}{{ fmt(row.ct_amount) }}
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <a
                  v-if="row.ct_attachment_path"
                  :href="row.ct_attachment_path"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex p-1.5 text-gray-400 hover:text-[#7C5CFC] transition"
                  :title="$t('treasury.attachment')"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
                    />
                  </svg>
                </a>
                <button
                  v-if="row.ct_status === 'active'"
                  class="inline-flex p-1.5 text-gray-400 hover:text-[#7C5CFC] transition"
                  :title="$t('common.edit')"
                  @click="openTransaction(row.ct_direction, row)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                    />
                  </svg>
                </button>
                <button
                  v-if="row.ct_status === 'active'"
                  class="inline-flex p-1.5 text-gray-400 hover:text-red-600 transition"
                  :title="$t('treasury.cancelEntry')"
                  @click="confirmCancel(row)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9" />
                    <path stroke-linecap="round" d="M8 12h8" />
                  </svg>
                </button>
                <span v-else class="text-xs font-semibold text-gray-400">{{ $t('treasury.cancelled') }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
        <span class="text-xs text-gray-400">{{ transactionsMeta.total }} {{ $t('treasury.entries') }}</span>
        <div class="flex items-center gap-2">
          <button
            class="px-3 py-1.5 text-sm rounded-lg border border-gray-200 dark:border-gray-600 disabled:opacity-40 dark:text-gray-200"
            :disabled="transactionsMeta.current_page <= 1"
            @click="goEntries(transactionsMeta.current_page - 1)"
          >
            ‹
          </button>
          <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ transactionsMeta.current_page }} / {{ transactionsMeta.last_page }}
          </span>
          <button
            class="px-3 py-1.5 text-sm rounded-lg border border-gray-200 dark:border-gray-600 disabled:opacity-40 dark:text-gray-200"
            :disabled="transactionsMeta.current_page >= transactionsMeta.last_page"
            @click="goEntries(transactionsMeta.current_page + 1)"
          >
            ›
          </button>
        </div>
      </div>
    </div>

    <!-- ── Onglet : Comptes ──────────────────────────────────────── -->
    <div v-show="activeTab === 'accounts'" class="space-y-3">
      <div class="flex justify-end">
        <button
          class="px-4 py-2.5 bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white text-sm font-bold rounded-[11px] transition"
          @click="openAccount()"
        >
          {{ $t('treasury.addAccount') }}
        </button>
      </div>
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 uppercase text-xs">
            <tr>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('common.name') }}</th>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('treasury.type') }}</th>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('treasury.paymentMethod') }}</th>
              <th class="text-right px-4 py-3 font-semibold">{{ $t('treasury.initialBalance') }}</th>
              <th class="text-right px-4 py-3 font-semibold">{{ $t('treasury.balance') }}</th>
              <th class="text-right px-4 py-3 font-semibold">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr v-for="acc in accounts" :key="acc.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <td class="px-4 py-3">
                <span class="font-medium text-gray-800 dark:text-gray-200">{{ acc.ca_title }}</span>
                <span v-if="!acc.ca_status" class="ml-2 text-xs text-gray-400">({{ $t('common.inactive') }})</span>
              </td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $t('treasury.types.' + acc.ca_type) }}</td>
              <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs font-mono">
                {{ acc.ca_payment_method ?? '—' }}
              </td>
              <td class="px-4 py-3 text-right font-mono text-gray-500">{{ fmt(acc.initial_balance) }}</td>
              <td class="px-4 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-100">
                {{ fmt(acc.balance) }}
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  class="inline-flex p-1.5 text-gray-400 hover:text-[#7C5CFC]"
                  :title="$t('common.edit')"
                  @click="openAccount(acc)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                    />
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Onglet : Catégories ───────────────────────────────────── -->
    <div v-show="activeTab === 'categories'" class="space-y-3">
      <div class="flex justify-end">
        <button
          class="px-4 py-2.5 bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white text-sm font-bold rounded-[11px] transition"
          @click="openCategory()"
        >
          {{ $t('treasury.addCategory') }}
        </button>
      </div>
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 uppercase text-xs">
            <tr>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('common.name') }}</th>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('treasury.direction') }}</th>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('common.status') }}</th>
              <th class="text-right px-4 py-3 font-semibold">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr v-for="cat in allCategories" :key="cat.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ cat.cc_title }}</td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                {{ $t('treasury.directions.' + cat.cc_direction) }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold"
                  :class="cat.cc_status ? 'bg-[#E5F7ED] text-[#1F8A50]' : 'bg-[#F0F1F4] text-[#7A7F8C]'"
                >
                  {{ cat.cc_status ? $t('common.active') : $t('common.inactive') }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  class="inline-flex p-1.5 text-gray-400 hover:text-[#7C5CFC]"
                  :title="$t('common.edit')"
                  @click="openCategory(cat)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                    />
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Onglet : Récurrences ──────────────────────────────────── -->
    <div v-show="activeTab === 'recurrences'" class="space-y-3">
      <div class="flex justify-end gap-2">
        <button
          class="px-4 py-2.5 border border-[#E1E3E9] dark:border-gray-600 text-[#4A4F5B] dark:text-gray-200 text-sm font-semibold rounded-[11px] hover:bg-gray-50 dark:hover:bg-gray-700 transition"
          :disabled="running"
          @click="runNow"
        >
          {{ running ? $t('common.loading') : $t('treasury.runNow') }}
        </button>
        <button
          class="px-4 py-2.5 bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white text-sm font-bold rounded-[11px] transition"
          @click="openRecurrence()"
        >
          {{ $t('treasury.addRecurrence') }}
        </button>
      </div>
      <div class="bg-white dark:bg-gray-800 border border-[#ECEEF2] dark:border-gray-700 rounded-2xl overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 uppercase text-xs">
            <tr>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('treasury.label') }}</th>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('treasury.frequency') }}</th>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('treasury.nextRun') }}</th>
              <th class="text-left px-4 py-3 font-semibold">{{ $t('treasury.account') }}</th>
              <th class="text-right px-4 py-3 font-semibold">{{ $t('common.amount') }}</th>
              <th class="text-right px-4 py-3 font-semibold">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr v-if="!recurrences.length">
              <td colspan="6" class="px-4 py-10 text-center text-gray-400">{{ $t('treasury.noRecurrence') }}</td>
            </tr>
            <tr v-for="rec in recurrences" :key="rec.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800 dark:text-gray-200">{{ rec.cr_label }}</div>
                <div v-if="!rec.cr_status" class="text-xs text-gray-400">{{ $t('common.inactive') }}</div>
              </td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                {{ $t('treasury.frequencies.' + rec.cr_frequency) }}
              </td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ fmtDate(rec.cr_next_run_at) }}</td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ rec.account?.ca_title ?? '—' }}</td>
              <td
                class="px-4 py-3 text-right font-mono font-semibold"
                :class="rec.cr_direction === 'in' ? 'text-emerald-600' : 'text-red-600'"
              >
                {{ rec.cr_direction === 'in' ? '+' : '−' }}{{ fmt(rec.cr_amount) }}
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <button
                  class="inline-flex p-1.5 text-gray-400 hover:text-[#7C5CFC]"
                  :title="$t('common.edit')"
                  @click="openRecurrence(rec)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                    />
                  </svg>
                </button>
                <button
                  class="inline-flex p-1.5 text-gray-400 hover:text-red-600"
                  :title="$t('common.delete')"
                  @click="deleteRecurrence(rec)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"
                    />
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Modale : écriture ─────────────────────────────────────── -->
    <BaseModal v-model="showTransaction" :title="transactionTitle" size="lg">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
          <label for="trz-label" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.label') }} *
          </label>
          <input
            id="trz-label"
            v-model="form.ct_label"
            type="text"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
          />
        </div>
        <div>
          <label for="trz-amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('common.amount') }} *
          </label>
          <input
            id="trz-amount"
            v-model="form.ct_amount"
            type="number"
            step="0.01"
            min="0.01"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
          />
        </div>
        <div>
          <label for="trz-date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('common.date') }} *
          </label>
          <input
            id="trz-date"
            v-model="form.ct_date"
            type="date"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
          />
        </div>
        <div>
          <label for="trz-account" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.account') }} *
          </label>
          <select
            id="trz-account"
            v-model="form.cash_account_id"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
          >
            <option v-for="acc in activeAccounts" :key="acc.id" :value="acc.id">{{ acc.ca_title }}</option>
          </select>
        </div>
        <div>
          <label for="trz-category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.category') }}
          </label>
          <select
            id="trz-category"
            v-model="form.cash_category_id"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
          >
            <option :value="null">—</option>
            <option v-for="cat in categoriesForDirection" :key="cat.id" :value="cat.id">{{ cat.cc_title }}</option>
          </select>
        </div>
        <div>
          <label for="trz-partner" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.partner') }}
          </label>
          <select
            id="trz-partner"
            v-model="form.thirdPartner_id"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
          >
            <option :value="null">—</option>
            <option v-for="tp in partners" :key="tp.id" :value="tp.id">{{ tp.tp_title }}</option>
          </select>
        </div>
        <div>
          <label for="trz-method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.paymentMethod') }}
          </label>
          <select
            id="trz-method"
            v-model="form.ct_method"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
          >
            <option :value="null">—</option>
            <option v-for="m in methods" :key="m" :value="m">{{ $t('treasury.methods.' + m) }}</option>
          </select>
        </div>
        <div class="sm:col-span-2">
          <label for="trz-file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.attachment') }}
          </label>
          <input
            id="trz-file"
            type="file"
            accept="image/*,application/pdf"
            class="w-full text-input text-gray-600 dark:text-gray-300 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-gray-100 dark:file:bg-gray-700 file:text-input file:font-semibold"
            @change="onFile"
          />
        </div>
        <div class="sm:col-span-2">
          <label for="trz-notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('common.notes') }}
          </label>
          <textarea
            id="trz-notes"
            v-model="form.ct_notes"
            rows="2"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-[#7C5CFC]"
          />
        </div>
        <p v-if="formError" class="sm:col-span-2 text-sm text-red-600">{{ formError }}</p>
      </div>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
          @click="showTransaction = false"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold text-white rounded-lg transition disabled:opacity-60"
          :class="form.ct_direction === 'in' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'"
          :disabled="saving"
          @click="submitTransaction"
        >
          {{ saving ? $t('common.saving') : $t('common.save') }}
        </button>
      </template>
    </BaseModal>

    <!-- ── Modale : virement ─────────────────────────────────────── -->
    <BaseModal v-model="showTransfer" :title="$t('treasury.transfer')" size="md">
      <div class="space-y-4">
        <div>
          <label for="trf-from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.fromAccount') }} *
          </label>
          <select
            id="trf-from"
            v-model="transferForm.from_account_id"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          >
            <option v-for="acc in activeAccounts" :key="acc.id" :value="acc.id">{{ acc.ca_title }}</option>
          </select>
        </div>
        <div>
          <label for="trf-to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.toAccount') }} *
          </label>
          <select
            id="trf-to"
            v-model="transferForm.to_account_id"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          >
            <option v-for="acc in activeAccounts" :key="acc.id" :value="acc.id">{{ acc.ca_title }}</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="trf-amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              {{ $t('common.amount') }} *
            </label>
            <input
              id="trf-amount"
              v-model="transferForm.amount"
              type="number"
              step="0.01"
              class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            />
          </div>
          <div>
            <label for="trf-date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              {{ $t('common.date') }} *
            </label>
            <input
              id="trf-date"
              v-model="transferForm.date"
              type="date"
              class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            />
          </div>
        </div>
        <div>
          <label for="trf-label" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.label') }}
          </label>
          <input
            id="trf-label"
            v-model="transferForm.label"
            type="text"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          />
        </div>
        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
      </div>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
          @click="showTransfer = false"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white rounded-lg disabled:opacity-60"
          :disabled="saving"
          @click="submitTransfer"
        >
          {{ saving ? $t('common.saving') : $t('common.save') }}
        </button>
      </template>
    </BaseModal>

    <!-- ── Modale : compte ───────────────────────────────────────── -->
    <BaseModal v-model="showAccount" :title="$t('treasury.account')" size="md">
      <div class="space-y-4">
        <div>
          <label for="acc-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('common.name') }} *
          </label>
          <input
            id="acc-title"
            v-model="accountForm.ca_title"
            type="text"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="acc-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              {{ $t('treasury.type') }}
            </label>
            <select
              id="acc-type"
              v-model="accountForm.ca_type"
              class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            >
              <option v-for="accType in accountTypes" :key="accType" :value="accType">
                {{ $t('treasury.types.' + accType) }}
              </option>
            </select>
          </div>
          <div>
            <label for="acc-initial" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              {{ $t('treasury.initialBalance') }}
            </label>
            <input
              id="acc-initial"
              v-model="accountForm.ca_initial_balance"
              type="number"
              step="0.01"
              class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            />
          </div>
        </div>
        <div>
          <label for="acc-method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.paymentMethod') }}
          </label>
          <select
            id="acc-method"
            v-model="accountForm.ca_payment_method"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          >
            <option :value="null">—</option>
            <option v-for="m in methods" :key="m" :value="m">{{ $t('treasury.methods.' + m) }}</option>
          </select>
          <p class="text-xs text-gray-400 mt-1">{{ $t('treasury.paymentMethodHint') }}</p>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
          <input v-model="accountForm.ca_status" type="checkbox" class="rounded" />
          {{ $t('common.active') }}
        </label>
        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
      </div>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
          @click="showAccount = false"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white rounded-lg disabled:opacity-60"
          :disabled="saving"
          @click="submitAccount"
        >
          {{ saving ? $t('common.saving') : $t('common.save') }}
        </button>
      </template>
    </BaseModal>

    <!-- ── Modale : catégorie ────────────────────────────────────── -->
    <BaseModal v-model="showCategory" :title="$t('treasury.category')" size="md">
      <div class="space-y-4">
        <div>
          <label for="cat-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('common.name') }} *
          </label>
          <input
            id="cat-title"
            v-model="categoryForm.cc_title"
            type="text"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          />
        </div>
        <div>
          <label for="cat-direction" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.direction') }}
          </label>
          <select
            id="cat-direction"
            v-model="categoryForm.cc_direction"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          >
            <option value="out">{{ $t('treasury.directions.out') }}</option>
            <option value="in">{{ $t('treasury.directions.in') }}</option>
            <option value="both">{{ $t('treasury.directions.both') }}</option>
          </select>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
          <input v-model="categoryForm.cc_status" type="checkbox" class="rounded" />
          {{ $t('common.active') }}
        </label>
        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
      </div>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
          @click="showCategory = false"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white rounded-lg disabled:opacity-60"
          :disabled="saving"
          @click="submitCategory"
        >
          {{ saving ? $t('common.saving') : $t('common.save') }}
        </button>
      </template>
    </BaseModal>

    <!-- ── Modale : récurrence ───────────────────────────────────── -->
    <BaseModal v-model="showRecurrence" :title="$t('treasury.recurrence')" size="lg">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
          <label for="rec-label" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.label') }} *
          </label>
          <input
            id="rec-label"
            v-model="recurrenceForm.cr_label"
            type="text"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          />
        </div>
        <div>
          <label for="rec-direction" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.direction') }} *
          </label>
          <select
            id="rec-direction"
            v-model="recurrenceForm.cr_direction"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          >
            <option value="out">{{ $t('treasury.directions.out') }}</option>
            <option value="in">{{ $t('treasury.directions.in') }}</option>
          </select>
        </div>
        <div>
          <label for="rec-amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('common.amount') }} *
          </label>
          <input
            id="rec-amount"
            v-model="recurrenceForm.cr_amount"
            type="number"
            step="0.01"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          />
        </div>
        <div>
          <label for="rec-account" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.account') }} *
          </label>
          <select
            id="rec-account"
            v-model="recurrenceForm.cash_account_id"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          >
            <option v-for="acc in activeAccounts" :key="acc.id" :value="acc.id">{{ acc.ca_title }}</option>
          </select>
        </div>
        <div>
          <label for="rec-category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.category') }}
          </label>
          <select
            id="rec-category"
            v-model="recurrenceForm.cash_category_id"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          >
            <option :value="null">—</option>
            <option v-for="cat in allCategories" :key="cat.id" :value="cat.id">{{ cat.cc_title }}</option>
          </select>
        </div>
        <div>
          <label for="rec-frequency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.frequency') }} *
          </label>
          <select
            id="rec-frequency"
            v-model="recurrenceForm.cr_frequency"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          >
            <option v-for="f in frequencies" :key="f" :value="f">{{ $t('treasury.frequencies.' + f) }}</option>
          </select>
        </div>
        <div>
          <label for="rec-anchor" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.anchorDay') }}
          </label>
          <input
            id="rec-anchor"
            v-model="recurrenceForm.cr_anchor_day"
            type="number"
            min="1"
            max="31"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          />
        </div>
        <div>
          <label for="rec-start" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.startDate') }} *
          </label>
          <input
            id="rec-start"
            v-model="recurrenceForm.cr_start_date"
            type="date"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          />
        </div>
        <div>
          <label for="rec-end" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ $t('treasury.endDate') }}
          </label>
          <input
            id="rec-end"
            v-model="recurrenceForm.cr_end_date"
            type="date"
            class="w-full px-3.5 py-2.5 text-input rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          />
        </div>
        <label class="sm:col-span-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
          <input v-model="recurrenceForm.cr_status" type="checkbox" class="rounded" />
          {{ $t('common.active') }}
        </label>
        <p v-if="formError" class="sm:col-span-2 text-sm text-red-600">{{ formError }}</p>
      </div>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
          @click="showRecurrence = false"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-[#7C5CFC] hover:bg-[#6D4CE0] text-white rounded-lg disabled:opacity-60"
          :disabled="saving"
          @click="submitRecurrence"
        >
          {{ saving ? $t('common.saving') : $t('common.save') }}
        </button>
      </template>
    </BaseModal>

    <!-- ── Modale : annulation ───────────────────────────────────── -->
    <BaseModal v-model="showCancel" :title="$t('treasury.cancelEntry')" size="sm">
      <p class="text-sm text-gray-600 dark:text-gray-300">
        {{ $t('treasury.cancelConfirm', { label: cancelTarget?.ct_label ?? '' }) }}
      </p>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
          @click="showCancel = false"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg disabled:opacity-60"
          :disabled="saving"
          @click="doCancel"
        >
          {{ $t('treasury.cancelEntry') }}
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
import {
  useTreasuryStore,
  type CashAccount,
  type CashCategory,
  type CashRecurrence,
  type CashTransaction,
} from '@/stores/treasury'
import { useAuthStore } from '@/stores/authStore'
import { useFormat } from '@/composables/useFormat'
import http from '@/services/http'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'

const { t } = useI18n()
const { fmt, date: fmtDate } = useFormat()
const store = useTreasuryStore()
const auth = useAuthStore()
const { accounts, journal, journalMeta, transactions, transactionsMeta, summary, recurrences, loading } =
  storeToRefs(store)

const methods = ['cash', 'bank_transfer', 'cheque', 'effet', 'card']
const accountTypes = ['cash', 'bank', 'cheque', 'other']
const frequencies = ['weekly', 'monthly', 'quarterly', 'yearly']

const toast = ref<InstanceType<typeof BaseNotification> | null>(null)
const activeTab = ref('summary')
const saving = ref(false)
const running = ref(false)
const formError = ref('')

/**
 * Le paramétrage (comptes, postes, récurrences) est réservé à la direction :
 * le caissier saisit les dépenses du jour mais ne redéfinit pas le plan. Les
 * onglets suivent la même règle que les routes API, sinon l'écran proposerait
 * des boutons qui répondent 403.
 */
const canManage = computed(() => ['admin', 'manager'].includes(auth.user?.role ?? ''))

const visibleTabs = computed(() => {
  const tabs = [
    { key: 'summary', label: t('treasury.tabSummary') },
    { key: 'journal', label: t('treasury.tabJournal') },
    { key: 'entries', label: t('treasury.tabEntries') },
  ]
  if (canManage.value) {
    tabs.push(
      { key: 'accounts', label: t('treasury.tabAccounts') },
      { key: 'categories', label: t('treasury.tabCategories') },
      { key: 'recurrences', label: t('treasury.tabRecurrences') },
    )
  }
  return tabs
})

// ── Période ───────────────────────────────────────────────────────────────

/**
 * Date au format AAAA-MM-JJ, dans le fuseau de l'utilisateur.
 *
 * `toISOString()` convertit d'abord en UTC : à Casablanca (UTC+1), le 1er août
 * à minuit devient le 31 juillet à 23 h, et tous les raccourcis de période
 * partaient donc un jour trop tôt.
 */
function isoDate(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function firstOfMonth(): string {
  const d = new Date()
  return isoDate(new Date(d.getFullYear(), d.getMonth(), 1))
}
function today(): string {
  return isoDate(new Date())
}

/**
 * L'écran s'ouvre sur la totalité de l'historique, pas sur le mois courant.
 *
 * Une trésorerie qu'on vient d'alimenter avec des mouvements passés — une
 * reprise d'antériorité, une régularisation — s'affichait entièrement vide au
 * premier chargement : les écritures existaient, mais hors de la fenêtre par
 * défaut. Mieux vaut tout montrer et laisser l'utilisateur restreindre.
 */
const period = reactive({ from: '', to: '' })

const shortcuts = computed(() => [
  { key: 'month', label: t('treasury.thisMonth') },
  { key: 'lastMonth', label: t('treasury.lastMonth') },
  { key: 'year', label: t('treasury.thisYear') },
  { key: 'all', label: t('treasury.all') },
])

function applyShortcut(key: string): void {
  const d = new Date()
  if (key === 'month') {
    period.from = firstOfMonth()
    period.to = today()
  } else if (key === 'lastMonth') {
    period.from = isoDate(new Date(d.getFullYear(), d.getMonth() - 1, 1))
    period.to = isoDate(new Date(d.getFullYear(), d.getMonth(), 0))
  } else if (key === 'year') {
    period.from = isoDate(new Date(d.getFullYear(), 0, 1))
    period.to = today()
  } else {
    period.from = ''
    period.to = ''
  }
}

// ── Données ───────────────────────────────────────────────────────────────
const allCategories = ref<CashCategory[]>([])
const partners = ref<Array<{ id: number; tp_title: string }>>([])

const activeAccounts = computed(() => accounts.value.filter((a) => a.ca_status))

const categoriesForDirection = computed(() =>
  allCategories.value.filter((c) => c.cc_status && (c.cc_direction === 'both' || c.cc_direction === form.ct_direction)),
)

/** Part relative d'un poste, pour la barre de progression. */
function categoryShare(row: { total: number; direction: string }): number {
  const rows = summary.value?.by_category ?? []
  const max = Math.max(...rows.map((r) => Number(r.total)), 1)
  return Math.round((Number(row.total) / max) * 100)
}

const journalFilters = reactive({ search: '', direction: '', source: 'all', account_id: '' as string | number })

async function reloadSummary(): Promise<void> {
  await store.fetchSummary(period.from || undefined, period.to || undefined)
}

async function reloadJournal(page = 1): Promise<void> {
  await store.fetchJournal({ ...journalFilters, from: period.from, to: period.to, page })
}

async function reloadEntries(page = 1): Promise<void> {
  await store.fetchTransactions({ from: period.from, to: period.to, page })
}

function goJournal(page: number): void {
  reloadJournal(page)
}
function goEntries(page: number): void {
  reloadEntries(page)
}

async function reloadAll(): Promise<void> {
  await Promise.all([reloadSummary(), reloadJournal(), reloadEntries(), store.fetchAccounts()])
}

watch([() => period.from, () => period.to], reloadAll)
watch(journalFilters, () => reloadJournal(1), { deep: true })

// ── Écriture ──────────────────────────────────────────────────────────────
const showTransaction = ref(false)
const editTarget = ref<CashTransaction | null>(null)
const attachment = ref<File | null>(null)

const form = reactive({
  ct_direction: 'out' as 'in' | 'out',
  ct_label: '',
  ct_amount: '' as string | number,
  ct_date: today(),
  cash_account_id: null as number | null,
  cash_category_id: null as number | null,
  thirdPartner_id: null as number | null,
  ct_method: null as string | null,
  ct_notes: '',
})

const transactionTitle = computed(() =>
  editTarget.value
    ? t('treasury.editEntry')
    : form.ct_direction === 'in'
      ? t('treasury.newIncome')
      : t('treasury.newExpense'),
)

function openTransaction(direction: 'in' | 'out', row: CashTransaction | null = null): void {
  formError.value = ''
  attachment.value = null
  editTarget.value = row
  form.ct_direction = direction
  form.ct_label = row?.ct_label ?? ''
  form.ct_amount = row ? Number(row.ct_amount) : ''
  form.ct_date = row?.ct_date?.slice(0, 10) ?? today()
  form.cash_account_id = row?.cash_account_id ?? activeAccounts.value[0]?.id ?? null
  form.cash_category_id = row?.cash_category_id ?? null
  form.thirdPartner_id = row?.thirdPartner_id ?? null
  form.ct_method = row?.ct_method ?? null
  form.ct_notes = row?.ct_notes ?? ''
  showTransaction.value = true
}

function onFile(event: Event): void {
  const input = event.target as HTMLInputElement
  attachment.value = input.files?.[0] ?? null
}

async function submitTransaction(): Promise<void> {
  formError.value = ''
  if (!form.ct_label.trim() || !form.ct_amount || !form.cash_account_id) {
    formError.value = t('treasury.requiredFields')
    return
  }

  saving.value = true
  try {
    const payload = { ...form }
    if (editTarget.value) {
      await store.updateTransaction(editTarget.value.id, payload, attachment.value)
      toast.value?.notify(t('treasury.entryUpdated'), 'success')
    } else {
      await store.createTransaction(payload, attachment.value)
      toast.value?.notify(t('treasury.entryCreated'), 'success')
    }
    showTransaction.value = false
    await reloadAll()
  } catch (err: unknown) {
    formError.value = extractError(err)
  } finally {
    saving.value = false
  }
}

// ── Virement ──────────────────────────────────────────────────────────────
const showTransfer = ref(false)
const transferForm = reactive({
  from_account_id: null as number | null,
  to_account_id: null as number | null,
  amount: '' as string | number,
  date: today(),
  label: '',
})

function openTransfer(): void {
  formError.value = ''
  transferForm.from_account_id = activeAccounts.value[0]?.id ?? null
  transferForm.to_account_id = activeAccounts.value[1]?.id ?? null
  transferForm.amount = ''
  transferForm.date = today()
  transferForm.label = ''
  showTransfer.value = true
}

async function submitTransfer(): Promise<void> {
  formError.value = ''
  saving.value = true
  try {
    await store.transfer({ ...transferForm })
    toast.value?.notify(t('treasury.transferDone'), 'success')
    showTransfer.value = false
    await reloadAll()
  } catch (err: unknown) {
    formError.value = extractError(err)
  } finally {
    saving.value = false
  }
}

// ── Annulation ────────────────────────────────────────────────────────────
const showCancel = ref(false)
const cancelTarget = ref<CashTransaction | null>(null)

function confirmCancel(row: CashTransaction): void {
  cancelTarget.value = row
  showCancel.value = true
}

async function doCancel(): Promise<void> {
  if (!cancelTarget.value) return
  saving.value = true
  try {
    await store.removeTransaction(cancelTarget.value.id)
    toast.value?.notify(t('treasury.entryCancelled'), 'success')
    showCancel.value = false
    await reloadAll()
  } catch (err: unknown) {
    toast.value?.notify(extractError(err), 'error')
  } finally {
    saving.value = false
  }
}

// ── Comptes ───────────────────────────────────────────────────────────────
const showAccount = ref(false)
const accountTarget = ref<CashAccount | null>(null)
const accountForm = reactive({
  ca_title: '',
  ca_type: 'cash' as CashAccount['ca_type'],
  ca_payment_method: null as string | null,
  ca_initial_balance: 0 as string | number,
  ca_status: true,
})

function openAccount(row: CashAccount | null = null): void {
  formError.value = ''
  accountTarget.value = row
  accountForm.ca_title = row?.ca_title ?? ''
  accountForm.ca_type = row?.ca_type ?? 'cash'
  accountForm.ca_payment_method = row?.ca_payment_method ?? null
  accountForm.ca_initial_balance = row ? Number(row.initial_balance ?? row.ca_initial_balance ?? 0) : 0
  accountForm.ca_status = row?.ca_status ?? true
  showAccount.value = true
}

async function submitAccount(): Promise<void> {
  formError.value = ''
  saving.value = true
  try {
    if (accountTarget.value) {
      await store.updateAccount(accountTarget.value.id, { ...accountForm })
    } else {
      await store.createAccount({ ...accountForm })
    }
    showAccount.value = false
    await reloadSummary()
  } catch (err: unknown) {
    formError.value = extractError(err)
  } finally {
    saving.value = false
  }
}

// ── Catégories ────────────────────────────────────────────────────────────
const showCategory = ref(false)
const categoryTarget = ref<CashCategory | null>(null)
const categoryForm = reactive({ cc_title: '', cc_direction: 'out', cc_status: true })

function openCategory(row: CashCategory | null = null): void {
  formError.value = ''
  categoryTarget.value = row
  categoryForm.cc_title = row?.cc_title ?? ''
  categoryForm.cc_direction = row?.cc_direction ?? 'out'
  categoryForm.cc_status = row?.cc_status ?? true
  showCategory.value = true
}

async function submitCategory(): Promise<void> {
  formError.value = ''
  saving.value = true
  try {
    if (categoryTarget.value) {
      await store.updateCategory(categoryTarget.value.id, { ...categoryForm } as Partial<CashCategory>)
    } else {
      await store.createCategory({ ...categoryForm } as Partial<CashCategory>)
    }
    showCategory.value = false
    await loadCategories()
  } catch (err: unknown) {
    formError.value = extractError(err)
  } finally {
    saving.value = false
  }
}

// ── Récurrences ───────────────────────────────────────────────────────────
const showRecurrence = ref(false)
const recurrenceTarget = ref<CashRecurrence | null>(null)
const recurrenceForm = reactive({
  cr_label: '',
  cr_direction: 'out',
  cr_amount: '' as string | number,
  cash_account_id: null as number | null,
  cash_category_id: null as number | null,
  cr_frequency: 'monthly',
  cr_anchor_day: 1,
  cr_start_date: today(),
  cr_end_date: null as string | null,
  cr_status: true,
})

function openRecurrence(row: CashRecurrence | null = null): void {
  formError.value = ''
  recurrenceTarget.value = row
  recurrenceForm.cr_label = row?.cr_label ?? ''
  recurrenceForm.cr_direction = row?.cr_direction ?? 'out'
  recurrenceForm.cr_amount = row ? Number(row.cr_amount) : ''
  recurrenceForm.cash_account_id = row?.cash_account_id ?? activeAccounts.value[0]?.id ?? null
  recurrenceForm.cash_category_id = row?.cash_category_id ?? null
  recurrenceForm.cr_frequency = row?.cr_frequency ?? 'monthly'
  recurrenceForm.cr_anchor_day = row?.cr_anchor_day ?? 1
  recurrenceForm.cr_start_date = row?.cr_start_date?.slice(0, 10) ?? today()
  recurrenceForm.cr_end_date = row?.cr_end_date?.slice(0, 10) ?? null
  recurrenceForm.cr_status = row?.cr_status ?? true
  showRecurrence.value = true
}

async function submitRecurrence(): Promise<void> {
  formError.value = ''
  saving.value = true
  try {
    if (recurrenceTarget.value) {
      await store.updateRecurrence(recurrenceTarget.value.id, { ...recurrenceForm } as Partial<CashRecurrence>)
    } else {
      await store.createRecurrence({ ...recurrenceForm } as Partial<CashRecurrence>)
    }
    showRecurrence.value = false
  } catch (err: unknown) {
    formError.value = extractError(err)
  } finally {
    saving.value = false
  }
}

async function deleteRecurrence(row: CashRecurrence): Promise<void> {
  try {
    await store.removeRecurrence(row.id)
    toast.value?.notify(t('common.deleted'), 'success')
  } catch (err: unknown) {
    toast.value?.notify(extractError(err), 'error')
  }
}

async function runNow(): Promise<void> {
  running.value = true
  try {
    const message = await store.runRecurrences()
    toast.value?.notify(message, 'success')
    await reloadAll()
  } catch (err: unknown) {
    toast.value?.notify(extractError(err), 'error')
  } finally {
    running.value = false
  }
}

// ── Divers ────────────────────────────────────────────────────────────────
function extractError(err: unknown): string {
  const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  const errors = e.response?.data?.errors
  if (errors) return Object.values(errors).flat()[0] ?? t('common.failedSave')
  return e.response?.data?.message ?? t('common.failedSave')
}

async function loadCategories(): Promise<void> {
  const { data } = await http.get<CashCategory[]>('/cash-categories')
  allCategories.value = data
}

onMounted(async () => {
  await Promise.all([
    store.fetchAccounts(),
    loadCategories(),
    reloadSummary(),
    reloadJournal(),
    reloadEntries(),
    canManage.value ? store.fetchRecurrences() : Promise.resolve(),
    http
      .get('/third-partners', { params: { per_page: 200 } })
      .then(({ data }) => {
        partners.value = data.data ?? data
      })
      .catch(() => {
        partners.value = []
      }),
  ])
})
</script>
