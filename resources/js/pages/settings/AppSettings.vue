<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $t('appSettings.title') }}</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $t('appSettings.subtitle') }}</p>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-700">
      <nav class="flex gap-1 -mb-px overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors"
          :class="activeTab === tab.id
            ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <div v-if="store.loading" class="text-sm text-gray-400 dark:text-gray-500">{{ $t('appSettings.loading') }}</div>

    <div v-else class="space-y-6">

      <!-- ═══════════════════ TAB: INFO ═══════════════════ -->
      <template v-if="activeTab === 'info'">
        <!-- Company -->
        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">{{ $t('appSettings.company') }}</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.companyName') }}</label>
              <input v-model="company.name" type="text" placeholder="Acme Corp" :class="inputClass" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.phone') }}</label>
              <input v-model="company.phone" type="text" placeholder="+212..." :class="inputClass" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.email') }}</label>
              <input v-model="company.email" type="email" placeholder="contact@company.com" :class="inputClass" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.ice') }}</label>
              <input v-model="company.ice" type="text" :placeholder="$t('appSettings.icePlaceholder')" :class="inputClass + ' font-mono'" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.rc') }}</label>
              <input v-model="company.rc" type="text" :placeholder="$t('appSettings.rcPlaceholder')" :class="inputClass + ' font-mono'" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.if') }}</label>
              <input v-model="company.if" type="text" :placeholder="$t('appSettings.ifPlaceholder')" :class="inputClass + ' font-mono'" />
            </div>
            <div class="col-span-1 sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.address') }}</label>
              <input v-model="company.address" type="text" :placeholder="$t('common.addressPlaceholder')" :class="inputClass" />
            </div>
          </div>
          <div class="flex justify-end">
            <button :class="btnClass" :disabled="saving.company" @click="saveSection('company', company)">
              {{ saving.company ? $t('common.saving') : $t('appSettings.saveCompany') }}
            </button>
          </div>
        </section>

        <!-- Localization -->
        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">{{ $t('appSettings.localization') }}</h3>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.currency') }}</label>
              <input v-model="locale.currency" type="text" placeholder="MAD" :class="inputClass" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.currencySymbol') }}</label>
              <input v-model="locale.currency_symbol" type="text" placeholder="د.م." :class="inputClass" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.timezone') }}</label>
              <input v-model="locale.timezone" type="text" placeholder="Africa/Casablanca" :class="inputClass" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.dateFormat') }}</label>
              <input v-model="locale.date_format" type="text" placeholder="DD/MM/YYYY" :class="inputClass + ' font-mono'" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.language') }}</label>
              <select v-model="locale.language" :class="inputClass">
                <option value="en">{{ $t('appSettings.langEn') }}</option>
                <option value="fr">{{ $t('appSettings.langFr') }}</option>
                <option value="ar">{{ $t('appSettings.langAr') }}</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end">
            <button :class="btnClass" :disabled="saving.locale" @click="saveSection('locale', locale)">
              {{ saving.locale ? $t('common.saving') : $t('appSettings.saveLocalization') }}
            </button>
          </div>
        </section>
      </template>

      <!-- ═══════════════════ TAB: TAXES & FACTURATION ═══════════════════ -->
      <template v-if="activeTab === 'taxes'">
        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">{{ $t('appSettings.invoice') }}</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.defaultTaxRate') }}</label>
              <input v-model="invoice.default_tax_rate" type="number" min="0" max="100" placeholder="20" :class="inputClass" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.paymentTerms') }}</label>
              <input v-model="invoice.payment_terms_days" type="number" min="0" placeholder="30" :class="inputClass" />
            </div>
            <div class="col-span-1 sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.footerNote') }}</label>
              <textarea v-model="invoice.footer_note" rows="2" :placeholder="$t('appSettings.footerNotePlaceholder')" :class="inputClass + ' resize-none'" />
            </div>
          </div>
          <div class="flex justify-end">
            <button :class="btnClass" :disabled="saving.invoice" @click="saveSection('invoice', invoice)">
              {{ saving.invoice ? $t('common.saving') : $t('appSettings.saveInvoice') }}
            </button>
          </div>
        </section>
      </template>

      <!-- ═══════════════════ TAB: STOCK ═══════════════════ -->
      <template v-if="activeTab === 'stock'">
        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">Stock</h3>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('appSettings.allowNegativeStock') }}</p>
              <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $t('appSettings.allowNegativeStockDesc') }}</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input v-model="stock.autoriser_stock_negatif" type="checkbox" true-value="true" false-value="false" class="sr-only peer" />
              <div :class="toggleClass"></div>
            </label>
          </div>
          <div class="flex justify-end">
            <button :class="btnClass" :disabled="saving.stock" @click="saveSection('stock', stock)">
              {{ saving.stock ? $t('common.saving') : $t('appSettings.saveStock') }}
            </button>
          </div>
        </section>

        <!-- Ventes -->
        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">{{ $t('appSettings.sales') }}</h3>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('appSettings.paymentOnBL') }}</p>
              <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $t('appSettings.paymentOnBLDesc') }}</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input v-model="ventes.paiement_sur_bl" type="checkbox" true-value="true" false-value="false" class="sr-only peer" />
              <div :class="toggleClass"></div>
            </label>
          </div>
          <div class="flex justify-end">
            <button :class="btnClass" :disabled="saving.ventes" @click="saveSection('ventes', ventes)">
              {{ saving.ventes ? $t('common.saving') : $t('appSettings.saveSales') }}
            </button>
          </div>
        </section>
      </template>

      <!-- ═══════════════════ TAB: WHATSAPP ═══════════════════ -->
      <template v-if="activeTab === 'whatsapp'">
        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.832-1.438A9.955 9.955 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18a7.963 7.963 0 01-4.106-1.14l-.294-.176-2.848.846.846-2.848-.176-.294A7.963 7.963 0 014 12c0-4.411 3.589-8 8-8s8 3.589 8 8-3.589 8-8 8z" />
              </svg>
            </div>
            <div>
              <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">WhatsApp (Twilio)</h3>
              <p class="text-xs text-gray-400 dark:text-gray-500">{{ $t('appSettings.whatsappDesc') }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Twilio Account SID</label>
              <input v-model="whatsapp.twilio_sid" type="text" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" :class="inputClass + ' font-mono text-xs'" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Twilio Auth Token</label>
              <input v-model="whatsapp.twilio_auth_token" :type="showWhatsappToken ? 'text' : 'password'" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" :class="inputClass + ' font-mono text-xs'" />
              <button @click="showWhatsappToken = !showWhatsappToken" class="text-xs text-blue-500 mt-1 hover:underline">
                {{ showWhatsappToken ? $t('appSettings.hide') : $t('appSettings.show') }}
              </button>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.whatsappFrom') }}</label>
              <input v-model="whatsapp.twilio_whatsapp_from" type="text" placeholder="+14155238886" :class="inputClass" />
              <p class="text-xs text-gray-400 mt-1">{{ $t('appSettings.whatsappFromHint') }}</p>
            </div>
            <div class="flex items-end">
              <div class="flex items-center gap-2">
                <label class="relative inline-flex items-center cursor-pointer">
                  <input v-model="whatsapp.whatsapp_enabled" type="checkbox" true-value="true" false-value="false" class="sr-only peer" />
                  <div :class="toggleClass"></div>
                </label>
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $t('appSettings.enableWhatsapp') }}</span>
              </div>
            </div>
          </div>

          <div class="flex justify-between items-center">
            <button
              @click="testWhatsapp"
              :disabled="testingWhatsapp"
              class="px-4 py-2 text-sm font-medium border border-green-500 text-green-600 dark:text-green-400 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 transition disabled:opacity-60"
            >
              {{ testingWhatsapp ? $t('appSettings.testing') : $t('appSettings.testWhatsapp') }}
            </button>
            <button :class="btnClass" :disabled="saving.whatsapp" @click="saveSection('whatsapp', whatsapp)">
              {{ saving.whatsapp ? $t('common.saving') : $t('appSettings.saveWhatsapp') }}
            </button>
          </div>
        </section>
      </template>

      <!-- ═══════════════════ TAB: EMAIL ═══════════════════ -->
      <template v-if="activeTab === 'email'">
        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
              </svg>
            </div>
            <div>
              <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">{{ $t('appSettings.emailSmtp') }}</h3>
              <p class="text-xs text-gray-400 dark:text-gray-500">{{ $t('appSettings.emailDesc') }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.mailHost') }}</label>
              <input v-model="email.mail_host" type="text" placeholder="smtp.gmail.com" :class="inputClass" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.mailPort') }}</label>
              <select v-model="email.mail_port" :class="inputClass">
                <option value="25">25 (SMTP)</option>
                <option value="465">465 (SSL)</option>
                <option value="587">587 (TLS)</option>
                <option value="2525">2525 (Alt)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.mailUsername') }}</label>
              <input v-model="email.mail_username" type="text" placeholder="user@gmail.com" :class="inputClass" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.mailPassword') }}</label>
              <input v-model="email.mail_password" :type="showEmailPassword ? 'text' : 'password'" placeholder="********" :class="inputClass" />
              <button @click="showEmailPassword = !showEmailPassword" class="text-xs text-blue-500 mt-1 hover:underline">
                {{ showEmailPassword ? $t('appSettings.hide') : $t('appSettings.show') }}
              </button>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.mailEncryption') }}</label>
              <select v-model="email.mail_encryption" :class="inputClass">
                <option value="">{{ $t('appSettings.none') }}</option>
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.mailFromAddress') }}</label>
              <input v-model="email.mail_from_address" type="email" placeholder="noreply@company.com" :class="inputClass" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.mailFromName') }}</label>
              <input v-model="email.mail_from_name" type="text" placeholder="Mon Entreprise" :class="inputClass" />
            </div>
            <div class="flex items-end">
              <div class="flex items-center gap-2">
                <label class="relative inline-flex items-center cursor-pointer">
                  <input v-model="email.mail_enabled" type="checkbox" true-value="true" false-value="false" class="sr-only peer" />
                  <div :class="toggleClass"></div>
                </label>
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $t('appSettings.enableEmail') }}</span>
              </div>
            </div>
          </div>

          <div class="flex justify-between items-center">
            <button
              @click="testEmail"
              :disabled="testingEmail"
              class="px-4 py-2 text-sm font-medium border border-blue-500 text-blue-600 dark:text-blue-400 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition disabled:opacity-60"
            >
              {{ testingEmail ? $t('appSettings.testing') : $t('appSettings.testEmail') }}
            </button>
            <button :class="btnClass" :disabled="saving.email" @click="saveSection('email', email)">
              {{ saving.email ? $t('common.saving') : $t('appSettings.saveEmail') }}
            </button>
          </div>
        </section>
      </template>

    </div>

    <BaseNotification ref="toast" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSettingStore } from '@/stores/setting'
import http from '@/services/http'
import BaseNotification from '@/components/BaseNotification.vue'

const { t } = useI18n()
const store = useSettingStore()
const toast = ref<InstanceType<typeof BaseNotification> | null>(null)

const activeTab = ref('info')

const tabs = [
  { id: 'info', label: t('appSettings.tabInfo') },
  { id: 'taxes', label: t('appSettings.tabTaxes') },
  { id: 'stock', label: t('appSettings.tabStock') },
  { id: 'whatsapp', label: 'WhatsApp' },
  { id: 'email', label: 'Email' },
]

const saving = reactive<Record<string, boolean>>({
  company: false, locale: false, invoice: false, stock: false, ventes: false, whatsapp: false, email: false,
})

const showWhatsappToken = ref(false)
const showEmailPassword = ref(false)
const testingWhatsapp = ref(false)
const testingEmail = ref(false)

const company = reactive({ name: '', phone: '', email: '', ice: '', rc: '', if: '', address: '' })
const locale = reactive({ currency: '', currency_symbol: '', timezone: '', date_format: '', language: 'en' })
const invoice = reactive({ default_tax_rate: '', payment_terms_days: '', footer_note: '' })
const stock = reactive({ autoriser_stock_negatif: 'false' })
const ventes = reactive({ paiement_sur_bl: 'false' })
const whatsapp = reactive({
  twilio_sid: '',
  twilio_auth_token: '',
  twilio_whatsapp_from: '',
  whatsapp_enabled: 'false',
})
const email = reactive({
  mail_host: '',
  mail_port: '465',
  mail_username: '',
  mail_password: '',
  mail_encryption: 'ssl',
  mail_from_address: '',
  mail_from_name: '',
  mail_enabled: 'false',
})

const inputClass = 'w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent'
const btnClass = 'px-4 py-2 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition disabled:opacity-60'
const toggleClass = 'w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[\'\'] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 dark:after:border-gray-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600'

function applySettings() {
  const s = store.settings
  Object.assign(company, s.company ?? {})
  Object.assign(locale, s.locale ?? {})
  Object.assign(invoice, s.invoice ?? {})
  Object.assign(stock, s.stock ?? {})
  Object.assign(ventes, s.ventes ?? {})
  Object.assign(whatsapp, s.whatsapp ?? {})
  Object.assign(email, s.email ?? {})
}

watch(() => store.settings, applySettings, { deep: true })

async function saveSection(domain: string, values: Record<string, string>) {
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

async function testWhatsapp() {
  testingWhatsapp.value = true
  try {
    const { data } = await http.post('/settings/test-whatsapp')
    toast.value?.notify(data.message || 'WhatsApp test sent!', data.success ? 'success' : 'error')
  } catch (e: any) {
    toast.value?.notify(e.response?.data?.message || 'WhatsApp test failed', 'error')
  } finally {
    testingWhatsapp.value = false
  }
}

async function testEmail() {
  testingEmail.value = true
  try {
    const { data } = await http.post('/settings/test-email')
    toast.value?.notify(data.message || 'Email test sent!', data.success ? 'success' : 'error')
  } catch (e: any) {
    toast.value?.notify(e.response?.data?.message || 'Email test failed', 'error')
  } finally {
    testingEmail.value = false
  }
}

onMounted(async () => {
  if (!Object.keys(store.settings).length) await store.fetchAll()
  applySettings()
})
</script>
