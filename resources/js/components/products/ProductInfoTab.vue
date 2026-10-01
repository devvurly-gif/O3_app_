<template>
    <!-- Image principale a gauche, identite et classement a droite ; un clic sur l'image ouvre l'onglet Medias -->
    <div class="flex flex-col sm:flex-row gap-4">
      <button
        v-if="hasProduct"
        type="button"
        class="shrink-0 w-full sm:w-56 aspect-square self-start rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:ring-2 hover:ring-[#7C5CFC] transition flex items-center justify-center"
        :title="primaryImage ? 'Gérer les images' : 'Ajouter une image'"
        @click="emit('open-media')"
      >
        <img v-if="primaryImage" :src="primaryImage.url" :alt="primaryImage.title || form.p_title" class="w-full h-full object-contain" />
        <svg v-else class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21zm9-12.75h.008v.008h-.008V8.25z"
          />
        </svg>
      </button>

    <div class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-2.5 content-start">
      <!-- Title (full row) -->
      <div class="sm:col-span-2">
        <label for="products-p-title" :class="labelClass"
          >{{ $t('common.name') }} <span class="text-red-500">*</span></label
        >
        <input
          id="products-p-title"
          v-model="form.p_title"
          type="text"
          required
          :placeholder="$t('products.titlePlaceholder')"
          :class="inputClass"
          @input="emit('slug-from-title')"
        />
      </div>

      <!-- Code -->
      <div>
        <label for="products-p-code" :class="labelClass">{{ $t('common.code') }}</label>
        <input
          id="products-p-code"
          v-model="form.p_code"
          type="text"
          :placeholder="$t('products.codePlaceholder')"
          :class="[inputClass, 'font-mono']"
        />
      </div>

      <!-- SKU -->
      <div>
        <label for="products-p-sku" :class="labelClass">{{ $t('products.sku') }}</label>
        <input
          id="products-p-sku"
          v-model="form.p_sku"
          type="text"
          :placeholder="$t('products.skuPlaceholder')"
          :class="[inputClass, 'font-mono']"
        />
        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">{{ $t('products.skuAuto') ?? 'Auto-generated if empty' }}</p>
      </div>

      <!-- EAN13 -->
      <div>
        <label for="products-p-ean13" :class="labelClass">{{ $t('products.ean') }}</label>
        <input
          id="products-p-ean13"
          v-model="form.p_ean13"
          type="text"
          :placeholder="$t('products.eanPlaceholder')"
          :class="[inputClass, 'font-mono']"
        />
      </div>

      <!-- IMEI — only for tenants tracking serial numbers -->
      <div v-if="imeiEnabled" class="sm:col-span-2">
        <label for="products-p-imei" :class="labelClass">IMEI</label>
        <input
          id="products-p-imei"
          v-model="form.p_imei"
          type="text"
          placeholder="Device IMEI..."
          :class="[inputClass, 'font-mono']"
        />
      </div>

      <!-- E-commerce Slug — only when ecom module is enabled AND product is flagged for the store -->
      <div v-if="ecomEnabled && form.is_ecom">
        <label for="products-p-slug" :class="labelClass">{{ $t('products.slug') ?? 'Slug' }}</label>
        <input
          id="products-p-slug"
          v-model="form.p_slug"
          type="text"
          :placeholder="$t('products.slugPlaceholder') ?? 'Auto-généré depuis le titre'"
          :class="[inputClass, 'font-mono']"
        />
        <p class="mt-0.5 text-[11px] text-gray-400">URL de la fiche produit dans la boutique en ligne.</p>
      </div>

      <!-- Category -->
      <div>
        <label for="products-category-id" :class="labelClass">{{ $t('products.category') }}</label>
        <select id="products-category-id" v-model="form.category_id" :class="inputClass">
          <option :value="null">—</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">
            {{ cat.ctg_title }}
          </option>
        </select>
      </div>

      <!-- Brand -->
      <div>
        <label for="products-brand-id" :class="labelClass">{{ $t('products.brand') }}</label>
        <select id="products-brand-id" v-model="form.brand_id" :class="inputClass">
          <option :value="null">—</option>
          <option v-for="br in brands" :key="br.id" :value="br.id">
            {{ br.br_title }}
          </option>
        </select>
      </div>
    </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-3 gap-y-2.5">
      <!-- Description -->
      <div>
        <label for="products-p-description" :class="labelClass">{{ $t('products.description') }}</label>
        <textarea
          id="products-p-description"
          v-model="form.p_description"
          rows="2"
          placeholder="…"
          :class="[inputClass, 'resize-y']"
        />
      </div>

      <!-- Long Description (E-commerce) -->
      <div>
        <label for="products-p-long-description" :class="labelClass">{{ $t('products.longDescription') ?? 'Long Description' }}</label>
        <textarea
          id="products-p-long-description"
          v-model="form.p_long_description"
          rows="2"
          placeholder="E-commerce description…"
          :class="[inputClass, 'resize-y']"
        />
      </div>
    </div>

    <!-- Notes -->
    <div>
      <label for="products-p-notes" :class="labelClass">Notes</label>
      <textarea
        id="products-p-notes"
        v-model="form.p_notes"
        rows="1"
        placeholder="Internal notes…"
        :class="[inputClass, 'resize-y']"
      />
    </div>

    <div class="pt-2.5 border-t border-gray-200 dark:border-gray-700 space-y-2.5">
      <!-- Publish to Online Store + Status on one row -->
      <div class="flex flex-col sm:flex-row sm:items-center gap-2.5">
        <!-- Publish to Online Store — only visible when the tenant has the ecom module enabled -->
        <div v-if="ecomEnabled" class="flex-1 flex items-start gap-2 px-3 py-2 rounded-lg bg-indigo-50/50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800">
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
            <p class="text-[11px] text-indigo-700/70 dark:text-indigo-300/70">
              Le produit sera visible et achetable sur shop.{{ tenantDomain || '[domaine]' }}.
            </p>
          </label>
        </div>

        <!-- Status -->
        <div class="flex items-center gap-2 sm:px-2">
          <input
            id="product-status"
            v-model="form.p_status"
            type="checkbox"
            class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-[#7C5CFC] focus:ring-[#7C5CFC]"
          />
          <label for="product-status" class="text-sm text-gray-700 dark:text-gray-300">{{ $t('common.active') }}</label>
        </div>
      </div>
    </div>
</template>

<script setup lang="ts">
/**
 * L'onglet Infos d'une fiche produit : image principale, identite,
 * classement, description, et la publication sur la boutique quand le module
 * ecom est actif.
 *
 * Le formulaire arrive par injection et non par prop — voir
 * `useProductEditContext` : treize champs en `v-model` sur une prop seraient
 * autant de mutations de prop.
 */
import { computed } from 'vue'
import { useProductEdit } from '@/composables/useProductEditContext'

withDefaults(
  defineProps<{
    categories: any[]
    brands: any[]
    /** Le module boutique est-il actif chez ce tenant ? */
    ecomEnabled: boolean
    /** Le module IMEI est-il actif chez ce tenant ? */
    imeiEnabled: boolean
    /** L'image principale du produit (ou la premiere), null s'il n'en a pas. */
    primaryImage?: { url: string; title?: string } | null
    /** Fiche existante : a la creation, pas encore d'image a montrer. */
    hasProduct?: boolean
  }>(),
  { primaryImage: null, hasProduct: false },
)

const emit = defineEmits<{
  /** Le titre a change : deriver le slug s'il est encore vide. */
  'slug-from-title': []
  /** Clic sur l'image : ouvrir l'onglet Medias. */
  'open-media': []
}>()

const { form } = useProductEdit()

const labelClass = 'block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5'
const inputClass =
  'w-full px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-input focus:outline-none focus:ring-2 focus:ring-[#7C5CFC] focus:border-transparent'

/** Indication d'URL de boutique affichee a cote de l'interrupteur. */
const tenantDomain = computed(() => {
  if (typeof window === 'undefined') return ''
  // On retire un « shop. » de tete si l'on est deja sur la boutique, puis
  // tout « www. » par proprete — d'ou par exemple « teliphoni.o3app.ma ».
  return window.location.hostname.replace(/^shop\./, '').replace(/^www\./, '')
})
</script>
