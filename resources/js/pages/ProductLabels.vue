<template>
  <div class="p-4 md:p-6 space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Étiquettes produits</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Sélectionnez des produits, les champs à afficher, puis imprimez.</p>
      </div>
      <button
        type="button"
        :disabled="!selectedList.length"
        class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600 disabled:opacity-40 disabled:cursor-not-allowed transition flex items-center gap-2"
        @click="printLabels"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
        </svg>
        Imprimer ({{ totalLabelCount }})
      </button>
    </div>

    <!-- Options -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex flex-wrap gap-6">
      <div>
        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Champs à afficher</p>
        <div class="flex flex-wrap gap-3">
          <label v-for="f in availableFields" :key="f.key" class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
            <input type="checkbox" v-model="fields[f.key]" class="rounded border-gray-300 text-orange-500 focus:ring-orange-500" />
            {{ f.label }}
          </label>
        </div>
      </div>
      <div>
        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Colonnes par page</p>
        <div class="flex gap-1.5">
          <button
            v-for="n in [2, 3, 4, 5]"
            :key="n"
            type="button"
            class="w-8 h-8 rounded-lg text-sm font-medium border transition"
            :class="columns === n
              ? 'bg-orange-500 border-orange-500 text-white'
              : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
            @click="columns = n"
          >
            {{ n }}
          </button>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <!-- Product picker -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex flex-col min-h-[420px]">
        <div class="relative mb-3">
          <input
            v-model="search"
            type="text"
            placeholder="Rechercher un produit (titre, SKU, code)..."
            class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500"
          />
          <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
          </svg>
        </div>

        <div class="flex-1 overflow-y-auto -mx-1 px-1 space-y-1">
          <div v-if="store.loading" class="text-sm text-gray-400 text-center py-8">Chargement...</div>
          <div v-else-if="!store.items.length" class="text-sm text-gray-400 text-center py-8">Aucun produit trouvé.</div>
          <label
            v-for="p in store.items"
            :key="p.id"
            class="flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer"
          >
            <input
              type="checkbox"
              :checked="selected.has(p.id)"
              class="rounded border-gray-300 text-orange-500 focus:ring-orange-500 shrink-0"
              @change="toggleProduct(p)"
            />
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ p.p_title }}</p>
              <p class="text-xs text-gray-400 font-mono">{{ p.p_sku || p.p_code }} <span v-if="p.p_ean13">· {{ p.p_ean13 }}</span></p>
            </div>
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 shrink-0">{{ Number(p.p_salePrice).toFixed(2) }} DH</span>
          </label>
        </div>

        <BasePagination
          v-if="store.meta.last_page > 1"
          :current-page="store.meta.current_page"
          :last-page="store.meta.last_page"
          :total="store.meta.total"
          :per-page="store.meta.per_page"
          class="mt-3"
          @change="store.goToPage"
        />
      </div>

      <!-- Selected products + preview -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex flex-col min-h-[420px]">
        <div class="flex items-center justify-between mb-3">
          <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Sélection ({{ selectedList.length }})</p>
          <button v-if="selectedList.length" type="button" class="text-xs text-red-500 hover:underline" @click="clearSelection">
            Tout retirer
          </button>
        </div>

        <div v-if="!selectedList.length" class="flex-1 flex items-center justify-center text-sm text-gray-400 text-center px-6">
          Cochez des produits à gauche pour prévisualiser leurs étiquettes ici.
        </div>

        <template v-else>
          <div class="space-y-1 mb-4 max-h-40 overflow-y-auto">
            <div v-for="s in selectedList" :key="s.product.id" class="flex items-center gap-2 text-sm px-2 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-900">
              <span class="flex-1 truncate text-gray-800 dark:text-gray-200">{{ s.product.p_title }}</span>
              <input
                v-model.number="s.qty"
                type="number"
                min="1"
                max="99"
                class="w-14 text-center text-xs rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 py-0.5"
              />
              <button type="button" class="text-gray-400 hover:text-red-500" @click="removeProduct(s.product.id)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Aperçu</p>
          <div class="flex-1 overflow-y-auto bg-gray-100 dark:bg-gray-900 rounded-lg p-3">
            <div class="grid gap-2" :style="{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }">
              <LabelCard
                v-for="(item, idx) in previewLabels"
                :key="idx"
                :product="item.product"
                :show-fields="fields"
              />
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, h, defineComponent } from 'vue'
import { useProductStore } from '@/stores/product'
import BasePagination from '@/components/BasePagination.vue'
import type { Product } from '@/types'
import { renderBarcodeDataUrl } from '@/composables/useBarcode'

const store = useProductStore()

const search = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null
watch(search, (val) => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    store.params.search = val || null
    store.fetchPage(1)
  }, 300)
})

onMounted(() => {
  store.params.per_page = 20
  store.fetchPage(1)
})

// ── Field selection ──────────────────────────────────────────────
const availableFields = [
  { key: 'title', label: 'Titre' },
  { key: 'barcode', label: 'Code-barres (EAN13)' },
  { key: 'sku', label: 'SKU' },
  { key: 'salePrice', label: 'Prix de vente' },
  { key: 'purchasePrice', label: "Prix d'achat" },
  { key: 'category', label: 'Catégorie' },
  { key: 'brand', label: 'Marque' },
] as const
type FieldKey = typeof availableFields[number]['key']

const fields = reactive<Record<FieldKey, boolean>>({
  title: true,
  barcode: true,
  sku: false,
  salePrice: true,
  purchasePrice: false,
  category: false,
  brand: true,
})

function formatPriceDh(value: number): string {
  return value.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' dhs'
}

const columns = ref(3)

// ── Selection state ──────────────────────────────────────────────
interface SelectedEntry { product: Product; qty: number }
const selected = reactive(new Map<number, SelectedEntry>())
const selectedList = computed(() => Array.from(selected.values()))

function toggleProduct(p: Product) {
  if (selected.has(p.id)) {
    selected.delete(p.id)
  } else {
    selected.set(p.id, { product: p, qty: 1 })
  }
}
function removeProduct(id: number) {
  selected.delete(id)
}
function clearSelection() {
  selected.clear()
}

const previewLabels = computed(() => {
  const out: { product: Product }[] = []
  for (const s of selectedList.value) {
    for (let i = 0; i < Math.max(1, s.qty || 1); i++) out.push({ product: s.product })
  }
  return out
})
const totalLabelCount = computed(() => previewLabels.value.length)

// ── Label preview card (also reused to build the print HTML) ─────
// Layout mirrors the ZebraDesigner template: title top-left, small
// meta (SKU/category/purchase price if enabled) under it, barcode
// centered, brand bottom-left, sale price boxed bottom-right.
const LabelCard = defineComponent({
  props: { product: { type: Object as () => Product, required: true }, showFields: { type: Object, required: true } },
  setup(props) {
    return () => {
      const p = props.product
      const f = props.showFields
      const meta: string[] = []
      if (f.sku && (p.p_sku || p.p_code)) meta.push(String(p.p_sku || p.p_code))
      if (f.category && p.category) meta.push(p.category.ctg_title)
      if (f.purchasePrice) meta.push('Achat: ' + formatPriceDh(Number(p.p_purchasePrice)))

      return h('div', { class: 'bg-white border border-gray-300 rounded-md p-2.5 flex flex-col gap-1.5 text-left' }, [
        f.title ? h('p', { class: 'text-[12px] font-bold text-gray-900 leading-tight line-clamp-2' }, p.p_title) : null,
        meta.length ? h('p', { class: 'text-[8px] text-gray-500' }, meta.join(' · ')) : null,
        f.barcode
          ? h('div', { class: 'flex justify-center py-1' }, [
              p.p_ean13
                ? h('img', { src: renderBarcodeDataUrl(p.p_ean13), class: 'max-w-[85%] h-auto' })
                : h('p', { class: 'text-[9px] text-gray-400 italic' }, 'Pas de code EAN'),
            ])
          : null,
        (f.brand || f.salePrice)
          ? h('div', { class: 'flex items-end justify-between mt-auto pt-1' }, [
              f.brand && p.brand ? h('p', { class: 'text-[11px] font-bold text-gray-900' }, p.brand.br_title) : h('span'),
              f.salePrice
                ? h('div', { class: 'border-2 border-gray-900 rounded px-1.5 py-0.5' }, [
                    h('p', { class: 'text-[13px] font-bold text-gray-900 leading-none' }, formatPriceDh(Number(p.p_salePrice))),
                  ])
                : null,
            ])
          : null,
      ].filter(Boolean))
    }
  },
})

// ── Print ──────────────────────────────────────────────────────────
function esc(v: unknown): string {
  return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] as string))
}

function labelHtml(p: Product): string {
  const parts: string[] = []
  if (fields.title) parts.push(`<div class="t">${esc(p.p_title)}</div>`)

  const meta: string[] = []
  if (fields.sku && (p.p_sku || p.p_code)) meta.push(esc(p.p_sku || p.p_code))
  if (fields.category && p.category) meta.push(esc(p.category.ctg_title))
  if (fields.purchasePrice) meta.push('Achat: ' + esc(formatPriceDh(Number(p.p_purchasePrice))))
  if (meta.length) parts.push(`<div class="meta">${meta.join(' &middot; ')}</div>`)

  if (fields.barcode) {
    parts.push(
      `<div class="bc-wrap">` +
      (p.p_ean13
        ? `<img class="bc" src="${renderBarcodeDataUrl(p.p_ean13)}" />`
        : `<div class="meta italic">Pas de code EAN</div>`) +
      `</div>`
    )
  }

  if (fields.brand || fields.salePrice) {
    const brandHtml = fields.brand && p.brand ? `<div class="brand">${esc(p.brand.br_title)}</div>` : `<div></div>`
    const priceHtml = fields.salePrice ? `<div class="price-box"><div class="price">${esc(formatPriceDh(Number(p.p_salePrice)))}</div></div>` : ''
    parts.push(`<div class="bottom-row">${brandHtml}${priceHtml}</div>`)
  }

  return `<div class="label">${parts.join('')}</div>`
}

function printLabels() {
  if (!previewLabels.value.length) return

  const labels = previewLabels.value.map((l) => labelHtml(l.product)).join('')

  const html = `<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Étiquettes produits</title>
<style>
  @page { size: A4; margin: 10mm; }
  body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; }
  .sheet { display: grid; grid-template-columns: repeat(${columns.value}, 1fr); gap: 3mm; }
  .label { border: 1px solid #d1d5db; border-radius: 3mm; padding: 3.5mm; display: flex; flex-direction: column; gap: 1.5mm; text-align: left; page-break-inside: avoid; }
  .t { font-size: 11px; font-weight: 700; color: #111827; line-height: 1.25; }
  .meta { font-size: 7.5px; color: #6b7280; }
  .meta.italic { font-style: italic; color: #9ca3af; }
  .bc-wrap { display: flex; justify-content: center; padding: 1mm 0; }
  .bc { width: 85%; max-width: 45mm; height: auto; }
  .bottom-row { display: flex; align-items: flex-end; justify-content: space-between; margin-top: auto; padding-top: 1mm; }
  .brand { font-size: 10.5px; font-weight: 700; color: #111827; }
  .price-box { border: 1.5pt solid #111827; border-radius: 1mm; padding: 0.5mm 2mm; }
  .price { font-size: 12.5px; font-weight: 700; color: #111827; line-height: 1.3; white-space: nowrap; }
</style></head><body>
<div class="sheet">${labels}</div>
</body></html>`

  const printWindow = window.open('', '_blank')
  if (!printWindow) return
  printWindow.document.write(html)
  printWindow.document.close()
  printWindow.onload = () => {
    printWindow.print()
  }
}
</script>
