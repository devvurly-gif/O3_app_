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
          :class="tab.id === 'danger'
            ? (activeTab === tab.id ? 'border-red-500 text-red-600 dark:text-red-400' : 'border-transparent text-red-400 dark:text-red-500/70 hover:text-red-600 hover:border-red-300')
            : activeTab === tab.id
              ? 'border-orange-500 text-orange-500 dark:text-orange-400 dark:border-blue-400'
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

          <!-- Logo Upload -->
          <div class="flex items-start gap-5 pb-4 border-b border-gray-100 dark:border-gray-700">
            <div class="flex-shrink-0">
              <div v-if="logoPreview || company.logo" class="w-24 h-24 rounded-xl border-2 border-gray-200 dark:border-gray-600 overflow-hidden bg-gray-50 dark:bg-gray-900 flex items-center justify-center">
                <img :src="logoPreview || company.logo" alt="Logo" class="max-w-full max-h-full object-contain" />
              </div>
              <div v-else class="w-24 h-24 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 flex items-center justify-center">
                <svg class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z" />
                </svg>
              </div>
            </div>
            <div class="flex-1 space-y-2">
              <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('appSettings.companyLogo') || 'Logo de l\'entreprise' }}</p>
              <p class="text-xs text-gray-400 dark:text-gray-500">{{ $t('appSettings.logoHint') || 'JPG, PNG, WebP ou SVG. Max 2 Mo. Apparaît sur les factures et documents imprimés.' }}</p>
              <div class="flex items-center gap-2">
                <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-orange-50 dark:bg-orange-900/20 text-orange-500 dark:text-orange-400 rounded-lg hover:bg-orange-100 dark:hover:bg-blue-900/30 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                  </svg>
                  {{ uploadingLogo ? ($t('common.uploading') || 'Upload...') : ($t('appSettings.uploadLogo') || 'Télécharger') }}
                  <input type="file" accept="image/jpeg,image/png,image/webp,image/svg+xml" class="hidden" @change="handleLogoUpload" :disabled="uploadingLogo" />
                </label>
                <button
                  v-if="company.logo"
                  @click="deleteLogo"
                  :disabled="deletingLogo"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 transition"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                  </svg>
                  {{ $t('common.delete') || 'Supprimer' }}
                </button>
              </div>
            </div>
          </div>

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
        <!-- TVA Activation Toggle -->
        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $t('appSettings.taxActivation') }}</h3>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $t('appSettings.taxActivationHint') }}</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input
                v-model="invoice.tax_enabled"
                type="checkbox"
                true-value="true"
                false-value="false"
                class="sr-only peer"
                @change="saveSection('invoice', { tax_enabled: invoice.tax_enabled })"
              />
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-orange-500"></div>
            </label>
          </div>
          <!-- Tax rate — only shown when enabled -->
          <div v-if="invoice.tax_enabled === 'true'" class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.defaultTaxRate') }}</label>
            <div class="flex items-center gap-2">
              <input v-model="invoice.default_tax_rate" type="number" min="0" max="100" placeholder="20" :class="inputClass + ' max-w-xs'" />
              <span class="text-sm text-gray-500 dark:text-gray-400">%</span>
            </div>
          </div>
        </section>

        <!-- Facturation Settings -->
        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">{{ $t('appSettings.invoice') }}</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
              <button @click="showWhatsappToken = !showWhatsappToken" class="text-xs text-orange-500 mt-1 hover:underline">
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

          <!-- WhatsApp Test Result -->
          <div v-if="whatsappTestResult" class="rounded-lg p-3 text-sm" :class="whatsappTestResult.success ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300'">
            <p class="font-semibold">{{ whatsappTestResult.success ? 'Success' : 'Error' }}</p>
            <p class="mt-1 text-xs break-all">{{ whatsappTestResult.message }}</p>
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
            <div class="w-10 h-10 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-orange-500 dark:text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
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
              <button @click="showEmailPassword = !showEmailPassword" class="text-xs text-orange-500 mt-1 hover:underline">
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

          <!-- Email Test Result -->
          <div v-if="emailTestResult" class="rounded-lg p-3 text-sm" :class="emailTestResult.success ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300'">
            <p class="font-semibold">{{ emailTestResult.success ? 'Success' : 'Error' }}</p>
            <p class="mt-1 text-xs break-all">{{ emailTestResult.message }}</p>
          </div>

          <div class="flex justify-between items-center">
            <button
              @click="testEmail"
              :disabled="testingEmail"
              class="px-4 py-2 text-sm font-medium border border-orange-500 text-orange-500 dark:text-orange-400 rounded-lg hover:bg-orange-50 dark:hover:bg-blue-900/20 transition disabled:opacity-60"
            >
              {{ testingEmail ? $t('appSettings.testing') : $t('appSettings.testEmail') }}
            </button>
            <button :class="btnClass" :disabled="saving.email" @click="saveSection('email', email)">
              {{ saving.email ? $t('common.saving') : $t('appSettings.saveEmail') }}
            </button>
          </div>
        </section>
      </template>

      <!-- ═══════════════════ TAB: DISPLAY ═══════════════════ -->
      <template v-if="activeTab === 'display'">
        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">{{ $t('appSettings.numberFormatting') || 'Format Numérique' }}</h3>
          
          <!-- Price Decimal Places -->
          <div class="space-y-3">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('appSettings.priceDecimalPlaces') || 'Décimales pour les prix' }}</label>
            <div class="flex gap-4">
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="display.price_decimals" type="radio" value="2" class="w-4 h-4 text-orange-500" />
                <span class="text-sm text-gray-700 dark:text-gray-300">2 décimales (120.00 MAD)</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="display.price_decimals" type="radio" value="3" class="w-4 h-4 text-orange-500" />
                <span class="text-sm text-gray-700 dark:text-gray-300">3 décimales (120.000 MAD)</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="display.price_decimals" type="radio" value="4" class="w-4 h-4 text-orange-500" />
                <span class="text-sm text-gray-700 dark:text-gray-300">4 décimales (120.0000 MAD)</span>
              </label>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $t('appSettings.priceDecimalPlacesHint') || 'Affecte l\'affichage des prix dans tout le POS et les rapports' }}</p>
          </div>

          <div class="flex justify-end">
            <button :class="btnClass" :disabled="saving.display" @click="saveSection('display', display)">
              {{ saving.display ? $t('common.saving') : $t('appSettings.save') }}
            </button>
          </div>
        </section>
      </template>

      <!-- ═══════════════════ TAB: ECOMMERCE ═══════════════════ -->
      <!-- ═══════════════════ TAB: VARIANTES ═══════════════════ -->
      <template v-if="activeTab === 'variants'">
        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-5">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">Rubriques de variantes</h3>
            <button @click="startAddType" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              Nouvelle rubrique
            </button>
          </div>

          <div v-if="variantStore.loading" class="text-sm text-gray-400">Chargement...</div>

          <!-- Add type form -->
          <div v-if="addingType" class="flex items-center gap-2 p-3 bg-orange-50 dark:bg-orange-900/10 border border-orange-200 dark:border-orange-800 rounded-lg">
            <input
              v-model="newTypeName"
              ref="newTypeInput"
              type="text"
              placeholder="Ex: Couleur, Taille, Matière..."
              class="flex-1 px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-400"
              @keyup.enter="confirmAddType"
              @keyup.escape="addingType = false"
            />
            <button @click="confirmAddType" :disabled="!newTypeName.trim()" class="px-3 py-1.5 text-xs font-medium bg-orange-500 text-white rounded-lg hover:bg-orange-600 disabled:opacity-40 transition">Créer</button>
            <button @click="addingType = false" class="px-3 py-1.5 text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition">Annuler</button>
          </div>

          <!-- Option types list -->
          <div v-if="!variantStore.loading && variantStore.items.length === 0 && !addingType" class="text-sm text-gray-400 dark:text-gray-500 text-center py-8">
            Aucune rubrique. Commencez par créer une rubrique (ex: Couleur, Taille...).
          </div>

          <div v-for="optType in variantStore.items" :key="optType.id" class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <!-- Type header -->
            <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-700/50">
              <input
                v-if="editingTypeId === optType.id"
                v-model="editingTypeName"
                type="text"
                class="flex-1 px-2 py-1 text-sm font-semibold border border-orange-400 rounded bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none"
                @keyup.enter="saveTypeName(optType)"
                @keyup.escape="editingTypeId = null"
                @blur="saveTypeName(optType)"
              />
              <span v-else class="flex-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ optType.name }}</span>

              <button v-if="editingTypeId !== optType.id" @click="startEditType(optType)" class="p-1 text-gray-400 hover:text-orange-500 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
              </button>
              <button @click="variantStore.deleteType(optType.id)" class="p-1 text-gray-400 hover:text-red-500 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
              </button>
            </div>

            <!-- Values table -->
            <div class="p-3 space-y-2">
              <div v-for="val in optType.values" :key="val.id" class="flex items-center gap-2 group">
                <div class="flex-1 grid grid-cols-2 gap-2">
                  <input
                    :value="val.key"
                    type="text"
                    placeholder="Clé (ex: blanc)"
                    class="px-2.5 py-1.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-orange-400"
                    @change="variantStore.updateValue(optType.id, val.id, { key: ($event.target as HTMLInputElement).value })"
                  />
                  <div class="flex items-center gap-1.5">
                    <input
                      :value="val.value"
                      type="text"
                      placeholder="Valeur (ex: #FFFFFF ou S)"
                      class="flex-1 px-2.5 py-1.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-orange-400"
                      @change="variantStore.updateValue(optType.id, val.id, { value: ($event.target as HTMLInputElement).value })"
                    />
                    <!-- Color preview if value looks like hex -->
                    <span
                      v-if="/^#[0-9a-fA-F]{3,6}$/.test(val.value)"
                      class="w-6 h-6 rounded border border-gray-300 dark:border-gray-600 flex-shrink-0"
                      :style="{ backgroundColor: val.value }"
                    />
                  </div>
                </div>
                <button @click="variantStore.deleteValue(optType.id, val.id)" class="p-1 text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>

              <!-- Add value row -->
              <div v-if="addingValueForType === optType.id" class="flex items-center gap-2">
                <div class="flex-1 grid grid-cols-2 gap-2">
                  <input v-model="newValueKey" type="text" placeholder="Clé (ex: blanc)" class="px-2.5 py-1.5 text-sm border border-orange-300 dark:border-orange-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-orange-400" @keyup.enter="confirmAddValue(optType.id)" @keyup.escape="addingValueForType = null" />
                  <input v-model="newValueVal" type="text" placeholder="Valeur (ex: #FFFFFF)" class="px-2.5 py-1.5 text-sm border border-orange-300 dark:border-orange-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-orange-400" @keyup.enter="confirmAddValue(optType.id)" @keyup.escape="addingValueForType = null" />
                </div>
                <button @click="confirmAddValue(optType.id)" :disabled="!newValueKey.trim() || !newValueVal.trim()" class="p-1 text-orange-500 hover:text-orange-600 disabled:opacity-40 transition">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                </button>
                <button @click="addingValueForType = null" class="p-1 text-gray-400 hover:text-gray-600 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>

              <button @click="startAddValue(optType.id)" class="inline-flex items-center gap-1 text-xs text-orange-500 hover:text-orange-600 font-medium transition mt-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Ajouter une valeur
              </button>
            </div>
          </div>
        </section>
      </template>

      <template v-if="activeTab === 'ecommerce'">
        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-6">
          <!-- Header -->
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 2.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
              </svg>
            </div>
            <div>
              <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">{{ $t('appSettings.ecomTitle') }}</h3>
              <p class="text-xs text-gray-400 dark:text-gray-500">{{ $t('appSettings.ecomDesc') }}</p>
            </div>
          </div>

          <!-- Boutique -->
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">{{ $t('appSettings.ecomSectionShop') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.ecomTagline') }}</label>
                <input v-model="ecommerce.shop_tagline" type="text" :placeholder="$t('appSettings.ecomTaglinePlaceholder')" :class="inputClass" />
              </div>
            </div>
          </div>

          <div class="border-t border-gray-100 dark:border-gray-700" />

          <!-- Promo banner -->
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">{{ $t('appSettings.ecomSectionBanner') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.ecomBannerText') }}</label>
                <input v-model="ecommerce.promo_banner" type="text" placeholder="🔥 Livraison gratuite dès 2 000 MAD" :class="inputClass" />
              </div>
              <div class="flex items-center gap-2">
                <label class="relative inline-flex items-center cursor-pointer">
                  <input v-model="ecommerce.promo_banner_enabled" type="checkbox" true-value="true" false-value="false" class="sr-only peer" />
                  <div :class="toggleClass"></div>
                </label>
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $t('appSettings.ecomBannerEnabled') }}</span>
              </div>
            </div>
          </div>

          <div class="border-t border-gray-100 dark:border-gray-700" />

          <!-- Apparence -->
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">{{ $t('appSettings.ecomSectionAppearance') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.ecomPrimaryColor') }}</label>
                <div class="flex items-center gap-3">
                  <input v-model="ecommerce.primary_color" type="color" class="h-10 w-16 cursor-pointer rounded-lg border border-gray-300 dark:border-gray-600 p-1 bg-white dark:bg-gray-900" />
                  <input v-model="ecommerce.primary_color" type="text" placeholder="#f97316" :class="inputClass + ' font-mono text-xs flex-1'" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.ecomDefaultTheme') }}</label>
                <div class="flex gap-3 mt-1">
                  <label v-for="theme in ['light', 'dark', 'system']" :key="theme" class="flex items-center gap-2 cursor-pointer">
                    <input v-model="ecommerce.default_theme" type="radio" :value="theme" class="accent-orange-500" />
                    <span class="text-sm text-gray-700 dark:text-gray-300 capitalize">{{ $t('appSettings.ecomTheme_' + theme) }}</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="border-t border-gray-100 dark:border-gray-700" />

          <!-- Livraison -->
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">{{ $t('appSettings.ecomSectionDelivery') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.ecomDeliveryThreshold') }}</label>
                <div class="relative">
                  <input v-model="ecommerce.delivery_threshold" type="text" inputmode="numeric" placeholder="2000" :class="inputClass + ' pr-14'" />
                  <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">MAD</span>
                </div>
              </div>
            </div>
          </div>

          <div class="border-t border-gray-100 dark:border-gray-700" />

          <!-- Coordonnées -->
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">{{ $t('appSettings.ecomSectionContact') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.address') }}</label>
                <input v-model="ecommerce.address" type="text" placeholder="Rue Example, Casablanca" :class="inputClass" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('appSettings.ecomLocation') }}</label>
                <input v-model="ecommerce.location" type="text" placeholder="Casablanca" :class="inputClass" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.phone') }}</label>
                <input v-model="ecommerce.phone" type="text" placeholder="+212 6XX XXX XXX" :class="inputClass" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.email') }}</label>
                <input v-model="ecommerce.email" type="email" placeholder="contact@teliphoni.ma" :class="inputClass" />
              </div>
            </div>
          </div>

          <div class="border-t border-gray-100 dark:border-gray-700" />

          <!-- Réseaux sociaux -->
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">{{ $t('appSettings.ecomSectionSocial') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Instagram</label>
                <input v-model="ecommerce.instagram_url" type="url" placeholder="https://instagram.com/..." :class="inputClass" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Facebook</label>
                <input v-model="ecommerce.facebook_url" type="url" placeholder="https://facebook.com/..." :class="inputClass" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">WhatsApp</label>
                <input v-model="ecommerce.whatsapp_number" type="text" placeholder="+212 6XX XXX XXX" :class="inputClass" />
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex justify-between items-center pt-2">
            <a href="https://shop.teliphoni.o3app.ma" target="_blank" class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
              {{ $t('appSettings.ecomOpenShop') }}
            </a>
            <button :class="btnClass" :disabled="saving.ecommerce" @click="saveSection('ecommerce', ecommerce)">
              {{ saving.ecommerce ? $t('common.saving') : $t('appSettings.ecomSave') }}
            </button>
          </div>
        </section>
      </template>

      <!-- ═══════════════════ TAB: DANGER ZONE ═══════════════════ -->
      <template v-if="activeTab === 'danger'">
        <section class="bg-white dark:bg-gray-800 border border-red-200 dark:border-red-900/50 rounded-xl p-5 space-y-4">
          <h3 class="text-sm font-semibold text-red-600 dark:text-red-400 uppercase tracking-wide">Zone de danger</h3>

          <div class="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-900/40 rounded-lg p-4 text-sm text-red-800 dark:text-red-300 space-y-2">
            <p class="font-medium">Réinitialiser les données de l'entreprise</p>
            <p>Cette action supprime <span class="font-semibold">définitivement et irréversiblement</span> :</p>
            <ul class="list-disc list-inside space-y-0.5">
              <li>Toutes les ventes, achats, devis, bons de livraison et avoirs</li>
              <li>Tous les paiements</li>
              <li>Tous les mouvements de stock (le stock de chaque produit repasse à 0)</li>
              <li>Toutes les sessions de caisse (POS)</li>
              <li>La numérotation des documents (repart à 1)</li>
            </ul>
            <p>Sont <span class="font-semibold">conservés</span> : produits, catégories, marques, entrepôts, clients/fournisseurs, utilisateurs et paramètres.</p>
            <p class="text-xs opacity-80">Une sauvegarde quotidienne automatique existe, mais cette action reste irréversible en pratique — assurez-vous d'avoir vérifié vos données avant de continuer.</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Tapez <span class="font-mono font-semibold">{{ tenantId || '…' }}</span> pour confirmer
            </label>
            <input
              v-model="resetConfirmText"
              type="text"
              :placeholder="tenantId || ''"
              class="w-full sm:w-80 px-3 py-2 text-sm font-mono border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-400"
            />
          </div>

          <div class="flex justify-end">
            <button
              type="button"
              class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed transition"
              :disabled="!tenantId || resetConfirmText !== tenantId || resettingTenant"
              @click="showResetModal = true"
            >
              {{ resettingTenant ? 'Réinitialisation...' : 'Réinitialiser les données' }}
            </button>
          </div>
        </section>
      </template>

    </div>

    <BaseModal v-model="showResetModal" title="Confirmer la réinitialisation" size="sm">
      <p class="text-sm text-gray-600 dark:text-gray-300">
        Vous êtes sur le point de supprimer définitivement toutes les transactions et de remettre le stock à zéro pour
        <span class="font-mono font-semibold">{{ tenantId }}</span>. Cette action est irréversible.
      </p>
      <template #footer>
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600"
          @click="showResetModal = false"
        >
          Annuler
        </button>
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-40"
          :disabled="resettingTenant"
          @click="confirmResetTenantData"
        >
          {{ resettingTenant ? 'Réinitialisation...' : 'Confirmer la suppression' }}
        </button>
      </template>
    </BaseModal>

    <BaseNotification ref="toast" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSettingStore } from '@/stores/setting'
import { useVariantOptionsStore } from '@/stores/useVariantOptionsStore'
import type { VariantOptionType } from '@/stores/useVariantOptionsStore'
import { useFeaturesSettings } from '@/composables/useFeaturesSettings'
import http from '@/services/http'
import BaseNotification from '@/components/BaseNotification.vue'
import BaseModal from '@/components/BaseModal.vue'

const { t } = useI18n()
const store = useSettingStore()
const variantStore = useVariantOptionsStore()
const { isVariantsEnabled } = useFeaturesSettings()
const toast = ref<InstanceType<typeof BaseNotification> | null>(null)

// ── Danger zone: tenant data reset ──────────────────────────────
const tenantId = computed(() => (store.settings as Record<string, unknown>).tenant_id as string | undefined)
const resetConfirmText = ref('')
const showResetModal = ref(false)
const resettingTenant = ref(false)

async function confirmResetTenantData() {
  if (!tenantId.value || resetConfirmText.value !== tenantId.value) return
  resettingTenant.value = true
  try {
    await http.post('/settings/reset-data', { confirm: resetConfirmText.value })
    toast.value?.notify('Données réinitialisées avec succès.', 'success')
    resetConfirmText.value = ''
    showResetModal.value = false
  } catch (e: any) {
    toast.value?.notify(e?.response?.data?.message || 'Échec de la réinitialisation.', 'error')
  } finally {
    resettingTenant.value = false
  }
}

// ── Variant option types ──────────────────────────────────────
const addingType = ref(false)
const newTypeName = ref('')
const newTypeInput = ref<HTMLInputElement | null>(null)
const editingTypeId = ref<number | null>(null)
const editingTypeName = ref('')
const addingValueForType = ref<number | null>(null)
const newValueKey = ref('')
const newValueVal = ref('')

function startAddType() {
  addingType.value = true
  newTypeName.value = ''
  nextTick(() => newTypeInput.value?.focus())
}

async function confirmAddType() {
  if (!newTypeName.value.trim()) return
  await variantStore.createType(newTypeName.value.trim())
  addingType.value = false
  newTypeName.value = ''
}

function startEditType(type: VariantOptionType) {
  editingTypeId.value = type.id
  editingTypeName.value = type.name
}

async function saveTypeName(type: VariantOptionType) {
  if (!editingTypeName.value.trim() || editingTypeName.value === type.name) {
    editingTypeId.value = null
    return
  }
  await variantStore.updateType(type.id, { name: editingTypeName.value.trim() })
  editingTypeId.value = null
}

function startAddValue(typeId: number) {
  addingValueForType.value = typeId
  newValueKey.value = ''
  newValueVal.value = ''
}

async function confirmAddValue(typeId: number) {
  if (!newValueKey.value.trim() || !newValueVal.value.trim()) return
  await variantStore.addValue(typeId, newValueKey.value.trim(), newValueVal.value.trim())
  newValueKey.value = ''
  newValueVal.value = ''
  addingValueForType.value = null
}

const activeTab = ref('info')

const tabs = computed(() => [
  { id: 'info', label: t('appSettings.tabInfo') },
  { id: 'taxes', label: t('appSettings.tabTaxes') },
  { id: 'stock', label: t('appSettings.tabStock') },
  { id: 'whatsapp', label: 'WhatsApp' },
  { id: 'email', label: 'Email' },
  { id: 'display', label: t('appSettings.tabDisplay') },
  { id: 'ecommerce', label: t('appSettings.tabEcommerce') },
  ...(isVariantsEnabled.value ? [{ id: 'variants', label: 'Variantes' }] : []),
  { id: 'danger', label: 'Zone de danger' },
])

const saving = reactive<Record<string, boolean>>({
  company: false, locale: false, invoice: false, stock: false, ventes: false, whatsapp: false, email: false, display: false, ecommerce: false,
})

const showWhatsappToken = ref(false)
const showEmailPassword = ref(false)
const testingWhatsapp = ref(false)
const testingEmail = ref(false)
const whatsappTestResult = ref<{ success: boolean; message: string } | null>(null)
const emailTestResult = ref<{ success: boolean; message: string } | null>(null)

const company = reactive({ name: '', phone: '', email: '', ice: '', rc: '', if: '', address: '', logo: '' })
const logoPreview = ref<string | null>(null)
const uploadingLogo = ref(false)
const deletingLogo = ref(false)
const locale = reactive({ currency: '', currency_symbol: '', timezone: '', date_format: '', language: 'en' })
const display = reactive({ price_decimals: '2' })
const invoice = reactive({ default_tax_rate: '', payment_terms_days: '', footer_note: '', tax_enabled: 'true' })
const stock = reactive({ autoriser_stock_negatif: 'false' })
const ventes = reactive({ paiement_sur_bl: 'false' })
const whatsapp = reactive({
  twilio_sid: '',
  twilio_auth_token: '',
  twilio_whatsapp_from: '',
  whatsapp_enabled: 'false',
})
const ecommerce = reactive({
  shop_tagline: '',
  promo_banner: '',
  promo_banner_enabled: 'true',
  primary_color: '#f97316',
  default_theme: 'light',
  delivery_threshold: '2000',
  address: '',
  location: '',
  phone: '',
  email: '',
  instagram_url: '',
  facebook_url: '',
  whatsapp_number: '',
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

const inputClass = 'w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent'
const btnClass = 'px-4 py-2 text-sm font-semibold bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition disabled:opacity-60'
const toggleClass = 'w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-orange-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[\'\'] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 dark:after:border-gray-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500'

/**
 * Merge a settings payload into a reactive form object, but ONLY for keys
 * that already exist on the form. Stops legacy/extraneous DB rows
 * (e.g. company.iden_fiscal) from polluting the form and being posted
 * back on save — the backend whitelist would reject them with 422
 * "Unknown setting keys for this domain."
 */
function mergeKnownKeys<T extends Record<string, unknown>>(target: T, source: Record<string, unknown> | null | undefined): void {
  if (!source) return
  for (const key of Object.keys(target)) {
    if (key in source && source[key] != null) {
      (target as Record<string, unknown>)[key] = source[key]
    }
  }
}

function applySettings() {
  const s = store.settings
  mergeKnownKeys(company, s.company)
  mergeKnownKeys(locale, s.locale)
  mergeKnownKeys(invoice, s.invoice)
  mergeKnownKeys(stock, s.stock)
  mergeKnownKeys(ventes, s.ventes)
  mergeKnownKeys(whatsapp, s.whatsapp)
  mergeKnownKeys(ecommerce, s.ecommerce)
  mergeKnownKeys(email, s.email)
  mergeKnownKeys(display, s.display)
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
  whatsappTestResult.value = null
  try {
    const { data } = await http.post('/settings/test-whatsapp')
    whatsappTestResult.value = { success: data.success, message: data.message }
    toast.value?.notify(data.message || 'WhatsApp test sent!', data.success ? 'success' : 'error')
  } catch (e: any) {
    const msg = e.response?.data?.message || e.message || 'WhatsApp test failed'
    whatsappTestResult.value = { success: false, message: msg }
    toast.value?.notify('WhatsApp test failed', 'error')
  } finally {
    testingWhatsapp.value = false
  }
}

async function testEmail() {
  testingEmail.value = true
  emailTestResult.value = null
  try {
    const { data } = await http.post('/settings/test-email')
    emailTestResult.value = { success: data.success, message: data.message }
    toast.value?.notify(data.message || 'Email test sent!', data.success ? 'success' : 'error')
  } catch (e: any) {
    const msg = e.response?.data?.message || e.message || 'Email test failed'
    emailTestResult.value = { success: false, message: msg }
    toast.value?.notify('Email test failed', 'error')
  } finally {
    testingEmail.value = false
  }
}

async function handleLogoUpload(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  // Preview
  logoPreview.value = URL.createObjectURL(file)
  uploadingLogo.value = true

  try {
    const formData = new FormData()
    formData.append('logo', file)
    const { data } = await http.post('/settings/logo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    company.logo = data.url
    logoPreview.value = null
    toast.value?.notify('Logo uploaded!', 'success')
    // Refresh settings store
    await store.fetchAll()
  } catch (e: any) {
    logoPreview.value = null
    toast.value?.notify(e.response?.data?.message || 'Logo upload failed', 'error')
  } finally {
    uploadingLogo.value = false
    input.value = ''
  }
}

async function deleteLogo() {
  deletingLogo.value = true
  try {
    await http.delete('/settings/logo')
    company.logo = ''
    logoPreview.value = null
    toast.value?.notify('Logo deleted.', 'success')
    await store.fetchAll()
  } catch {
    toast.value?.notify('Failed to delete logo', 'error')
  } finally {
    deletingLogo.value = false
  }
}

onMounted(async () => {
  if (!Object.keys(store.settings).length) await store.fetchAll()
  applySettings()
  if (isVariantsEnabled.value) variantStore.fetchAll()
})
</script>
