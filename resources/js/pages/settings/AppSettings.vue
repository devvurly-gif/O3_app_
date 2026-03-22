<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $t('appSettings.title') }}</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $t('appSettings.subtitle') }}</p>
    </div>

    <div v-if="store.loading" class="text-sm text-gray-400 dark:text-gray-500">{{ $t('appSettings.loading') }}</div>

    <div v-else class="space-y-6">
      <!-- ── Company ────────────────────────────────────────────── -->
      <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">{{ $t('appSettings.company') }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.companyName') }}</label>
            <input
              v-model="company.name"
              type="text"
              placeholder="Acme Corp"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.phone') }}</label>
            <input
              v-model="company.phone"
              type="text"
              placeholder="+212..."
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.email') }}</label>
            <input
              v-model="company.email"
              type="email"
              placeholder="contact@company.com"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.ice') }}</label>
            <input
              v-model="company.ice"
              type="text"
              :placeholder="$t('appSettings.icePlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.rc') }}</label>
            <input
              v-model="company.rc"
              type="text"
              :placeholder="$t('appSettings.rcPlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.if') }}</label>
            <input
              v-model="company.if"
              type="text"
              :placeholder="$t('appSettings.ifPlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div class="col-span-1 sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.address') }}</label>
            <input
              v-model="company.address"
              type="text"
              :placeholder="$t('common.addressPlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
        </div>
        <div class="flex justify-end">
          <button
            class="px-4 py-2 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition disabled:opacity-60"
            :disabled="saving.company"
            @click="saveSection('company', company)"
          >
            {{ saving.company ? $t('common.saving') : $t('appSettings.saveCompany') }}
          </button>
        </div>
      </section>

      <!-- ── Localization ───────────────────────────────────────── -->
      <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">
          {{ $t('appSettings.localization') }}
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.currency') }}</label>
            <input
              v-model="locale.currency"
              type="text"
              placeholder="MAD"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.currencySymbol') }}</label>
            <input
              v-model="locale.currency_symbol"
              type="text"
              placeholder="د.م."
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.timezone') }}</label>
            <input
              v-model="locale.timezone"
              type="text"
              placeholder="Africa/Casablanca"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.dateFormat') }}</label>
            <input
              v-model="locale.date_format"
              type="text"
              placeholder="DD/MM/YYYY"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.language') }}</label>
            <select
              v-model="locale.language"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="en">{{ $t('appSettings.langEn') }}</option>
              <option value="fr">{{ $t('appSettings.langFr') }}</option>
              <option value="ar">{{ $t('appSettings.langAr') }}</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end">
          <button
            class="px-4 py-2 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition disabled:opacity-60"
            :disabled="saving.locale"
            @click="saveSection('locale', locale)"
          >
            {{ saving.locale ? $t('common.saving') : $t('appSettings.saveLocalization') }}
          </button>
        </div>
      </section>

      <!-- ── Invoice ───────────────────────────────────────────── -->
      <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">{{ $t('appSettings.invoice') }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.defaultTaxRate') }}</label>
            <input
              v-model="invoice.default_tax_rate"
              type="number"
              min="0"
              max="100"
              placeholder="20"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.paymentTerms') }}</label>
            <input
              v-model="invoice.payment_terms_days"
              type="number"
              min="0"
              placeholder="30"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div class="col-span-1 sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.footerNote') }}</label>
            <textarea
              v-model="invoice.footer_note"
              rows="2"
              :placeholder="$t('appSettings.footerNotePlaceholder')"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
            />
          </div>
        </div>
        <div class="flex justify-end">
          <button
            class="px-4 py-2 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition disabled:opacity-60"
            :disabled="saving.invoice"
            @click="saveSection('invoice', invoice)"
          >
            {{ saving.invoice ? $t('common.saving') : $t('appSettings.saveInvoice') }}
          </button>
        </div>
      </section>

      <!-- ── Stock ────────────────────────────────────────────────── -->
      <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">Stock</h3>
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Autoriser le stock négatif</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
              Permet de créer des BL et factures même si le stock est insuffisant
            </p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input
              v-model="stock.autoriser_stock_negatif"
              type="checkbox"
              true-value="true"
              false-value="false"
              class="sr-only peer"
            />
            <div
              class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 dark:after:border-gray-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"
            ></div>
          </label>
        </div>
        <div class="flex justify-end">
          <button
            class="px-4 py-2 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition disabled:opacity-60"
            :disabled="saving.stock"
            @click="saveSection('stock', stock)"
          >
            {{ saving.stock ? 'Enregistrement...' : 'Enregistrer Stock' }}
          </button>
        </div>
      </section>

      <!-- ── Ventes ───────────────────────────────────────────────── -->
      <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">Ventes</h3>
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Paiements sur Bon de Livraison</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
              Permet d'enregistrer des paiements directement sur les BL sans passer par la facturation
            </p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input
              v-model="ventes.paiement_sur_bl"
              type="checkbox"
              true-value="true"
              false-value="false"
              class="sr-only peer"
            />
            <div
              class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 dark:after:border-gray-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"
            ></div>
          </label>
        </div>
        <div class="flex justify-end">
          <button
            class="px-4 py-2 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition disabled:opacity-60"
            :disabled="saving.ventes"
            @click="saveSection('ventes', ventes)"
          >
            {{ saving.ventes ? 'Enregistrement...' : 'Enregistrer Ventes' }}
          </button>
        </div>
      </section>
    </div>

    <BaseNotification ref="toast" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSettingStore } from '@/stores/setting'
import BaseNotification from '@/components/BaseNotification.vue'

const { t } = useI18n()
const store = useSettingStore()
const toast = ref(null)

const saving = reactive({ company: false, locale: false, invoice: false, stock: false, ventes: false })

const company = reactive({ name: '', phone: '', email: '', ice: '', rc: '', if: '', address: '' })
const locale = reactive({ currency: '', currency_symbol: '', timezone: '', date_format: '', language: 'en' })
const invoice = reactive({ default_tax_rate: '', payment_terms_days: '', footer_note: '' })
const stock = reactive({ autoriser_stock_negatif: 'false' })
const ventes = reactive({ paiement_sur_bl: 'false' })

function applySettings() {
  const s = store.settings
  Object.assign(company, s.company ?? {})
  Object.assign(locale, s.locale ?? {})
  Object.assign(invoice, s.invoice ?? {})
  Object.assign(stock, s.stock ?? {})
  Object.assign(ventes, s.ventes ?? {})
}

watch(() => store.settings, applySettings, { deep: true })

async function saveSection(domain, values) {
  saving[domain] = true
  try {
    await store.save(domain, { ...values })
    toast.value?.notify(t('appSettings.saved'), 'success')
  } catch {
    toast.value?.notify(t('common.failedSave'), 'error')
  } finally {
    saving[domain] = false
  }
}

onMounted(async () => {
  if (!Object.keys(store.settings).length) await store.fetchAll()
  applySettings()
})
</script>
