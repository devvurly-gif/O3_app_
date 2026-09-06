<template>
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
          class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
          @input="emit('slug-from-title')"
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
          class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
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
          class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
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
          class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
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
          class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
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
          class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input resize-none focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
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
          class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input resize-none focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
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
        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input resize-none focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
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
            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
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
            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
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
            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-input font-mono focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent"
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
</template>

<script setup lang="ts">
/**
 * L'onglet Infos d'une fiche produit : identite, classement, description,
 * et la publication sur la boutique quand le module ecom est actif.
 *
 * Le formulaire arrive par injection et non par prop — voir
 * `useProductEditContext` : treize champs en `v-model` sur une prop seraient
 * autant de mutations de prop.
 */
import { computed } from 'vue'
import { useProductEdit } from '@/composables/useProductEditContext'

defineProps<{
  categories: any[]
  brands: any[]
  /** Le module boutique est-il actif chez ce tenant ? */
  ecomEnabled: boolean
  /** Le module IMEI est-il actif chez ce tenant ? */
  imeiEnabled: boolean
}>()

const emit = defineEmits<{
  /** Le titre a change : deriver le slug s'il est encore vide. */
  'slug-from-title': []
}>()

const { form } = useProductEdit()

/** Indication d'URL de boutique affichee a cote de l'interrupteur. */
const tenantDomain = computed(() => {
  if (typeof window === 'undefined') return ''
  // On retire un « shop. » de tete si l'on est deja sur la boutique, puis
  // tout « www. » par proprete — d'ou par exemple « teliphoni.o3app.ma ».
  return window.location.hostname.replace(/^shop\./, '').replace(/^www\./, '')
})
</script>
