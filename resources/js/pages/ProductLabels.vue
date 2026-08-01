<template>
  <div class="p-4 md:p-6 space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Étiquettes produits</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Définissez le format, placez les champs, puis imprimez.</p>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-xs" :class="saveStatus.class">{{ saveStatus.text }}</span>
        <button
          type="button"
          class="px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
          @click="resetTemplate"
        >
          Réinitialiser
        </button>
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
    </div>

    <div v-if="printError" class="rounded-lg border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
      {{ printError }}
    </div>

    <!-- Label format -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex flex-wrap items-end gap-5">
      <div>
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Largeur (mm)</label>
        <input v-model.number="label.width" type="number" min="10" max="210" step="1" :class="numClass" />
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Hauteur (mm)</label>
        <input v-model.number="label.height" type="number" min="10" max="297" step="1" :class="numClass" />
      </div>
      <div>
        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Format courant</p>
        <div class="flex gap-1.5">
          <button
            v-for="p in presets"
            :key="p.label"
            type="button"
            class="px-2.5 h-9 rounded-lg text-xs font-medium border transition"
            :class="label.width === p.w && label.height === p.h
              ? 'bg-orange-500 border-orange-500 text-white'
              : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
            @click="label.width = p.w; label.height = p.h"
          >
            {{ p.label }}
          </button>
        </div>
      </div>
      <div>
        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Impression</p>
        <div class="flex gap-1.5">
          <button
            v-for="m in printModes"
            :key="m.value"
            type="button"
            class="px-3 h-9 rounded-lg text-xs font-medium border transition"
            :class="printMode === m.value
              ? 'bg-orange-500 border-orange-500 text-white'
              : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
            @click="printMode = m.value"
          >
            {{ m.label }}
          </button>
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Bordure</label>
        <label class="flex items-center gap-1.5 h-9 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
          <input v-model="label.border" type="checkbox" class="rounded border-gray-300 text-orange-500 focus:ring-orange-500" />
          Afficher
        </label>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <!-- ── Designer ─────────────────────────────────────────── -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-4">
        <div class="flex items-center justify-between">
          <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Maquette</p>
          <p class="text-xs text-gray-400">Glissez les champs pour les positionner</p>
        </div>

        <div
          v-if="overflowing.length"
          class="rounded-lg border border-red-300 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-3 py-2 text-xs text-red-700 dark:text-red-300"
        >
          <span class="font-semibold">Hors de l'étiquette :</span> {{ overflowNames }} —
          ce qui dépasse le cadre sera coupé à l'impression. Repositionnez le champ, réduisez sa taille,
          ou agrandissez l'étiquette.
        </div>

        <!-- Canvas -->
        <div class="bg-gray-100 dark:bg-gray-900 rounded-lg p-6 flex justify-center overflow-auto">
          <div
            ref="canvasEl"
            class="relative bg-white shadow-sm shrink-0 overflow-hidden"
            :class="label.border ? 'border border-gray-400' : 'border border-dashed border-gray-300'"
            :style="{ width: label.width * PX_PER_MM + 'px', height: label.height * PX_PER_MM + 'px' }"
          >
            <div
              v-for="f in enabledFields"
              :key="f.key"
              :ref="(el) => setFieldEl(f.key, el)"
              class="absolute cursor-move select-none"
              :class="[
                overflowing.includes(f.key)
                  ? 'outline outline-2 outline-red-500'
                  : activeField === f.key
                    ? 'outline outline-2 outline-orange-500'
                    : 'hover:outline hover:outline-1 hover:outline-orange-300',
                layout[f.key].boxed ? 'border-2 border-black px-1' : '',
              ]"
              :style="fieldStyle(f.key)"
              @pointerdown="startDrag($event, f.key)"
            >
              <img
                v-if="f.key === 'barcode'"
                :src="sampleBarcode"
                class="pointer-events-none block"
                :style="{ width: layout.barcode.size * PX_PER_MM + 'px', height: layout.barcode.height * PX_PER_MM + 'px' }"
                draggable="false"
              />
              <span v-else class="pointer-events-none whitespace-nowrap leading-none">{{ fieldText(f.key, sampleProduct) }}</span>
            </div>
          </div>
        </div>

        <!-- Field controls -->
        <div class="overflow-x-auto -mx-1 px-1">
          <table class="w-full text-xs">
            <thead>
              <tr class="text-gray-400 dark:text-gray-500">
                <th class="text-left font-medium py-1.5 pr-2">Champ</th>
                <th class="font-medium py-1.5 px-1 w-16">X (mm)</th>
                <th class="font-medium py-1.5 px-1 w-16">Y (mm)</th>
                <th class="font-medium py-1.5 px-1 w-16">{{ 'Taille' }}</th>
                <th class="font-medium py-1.5 px-1 w-16">Haut.</th>
                <th class="font-medium py-1.5 px-1 w-10">G</th>
                <th class="font-medium py-1.5 px-1 w-10">Cadre</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="f in availableFields"
                :key="f.key"
                class="border-t border-gray-100 dark:border-gray-700"
                :class="activeField === f.key ? 'bg-orange-50 dark:bg-orange-900/20' : ''"
              >
                <td class="py-1.5 pr-2">
                  <label class="flex items-center gap-1.5 cursor-pointer text-gray-700 dark:text-gray-300">
                    <input
                      v-model="layout[f.key].enabled"
                      type="checkbox"
                      class="rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                    />
                    {{ f.label }}
                  </label>
                </td>
                <td class="px-1"><input v-model.number="layout[f.key].x" :disabled="!layout[f.key].enabled" type="number" step="0.5" :class="cellClass" /></td>
                <td class="px-1"><input v-model.number="layout[f.key].y" :disabled="!layout[f.key].enabled" type="number" step="0.5" :class="cellClass" /></td>
                <td class="px-1"><input v-model.number="layout[f.key].size" :disabled="!layout[f.key].enabled" type="number" step="0.5" min="1" :class="cellClass" /></td>
                <td class="px-1">
                  <input
                    v-if="f.key === 'barcode'"
                    v-model.number="layout.barcode.height"
                    :disabled="!layout.barcode.enabled"
                    type="number"
                    step="0.5"
                    min="1"
                    :class="cellClass"
                  />
                  <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                </td>
                <td class="px-1 text-center">
                  <input
                    v-if="f.key !== 'barcode'"
                    v-model="layout[f.key].bold"
                    :disabled="!layout[f.key].enabled"
                    type="checkbox"
                    class="rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                  />
                </td>
                <td class="px-1 text-center">
                  <input
                    v-if="f.key !== 'barcode'"
                    v-model="layout[f.key].boxed"
                    :disabled="!layout[f.key].enabled"
                    type="checkbox"
                    class="rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                  />
                </td>
              </tr>
            </tbody>
          </table>
          <p class="text-[11px] text-gray-400 mt-2">
            « Taille » = corps du texte en pt (largeur en mm pour le code-barres). « G » = gras.
          </p>
        </div>
      </div>

      <!-- ── Products ─────────────────────────────────────────── -->
      <div class="space-y-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex flex-col h-[400px]">
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
              <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 shrink-0">{{ formatPriceDh(Number(p.p_salePrice)) }}</span>
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

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
          <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Sélection ({{ selectedList.length }})</p>
            <button v-if="selectedList.length" type="button" class="text-xs text-red-500 hover:underline" @click="clearSelection">
              Tout retirer
            </button>
          </div>

          <p v-if="!selectedList.length" class="text-sm text-gray-400 py-4 text-center">
            Cochez des produits ci-dessus. La maquette utilise le premier produit sélectionné comme aperçu.
          </p>

          <div v-else class="space-y-1 max-h-48 overflow-y-auto">
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
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import { useProductStore } from '@/stores/product'
import { useAuthStore } from '@/stores/authStore'
import BasePagination from '@/components/BasePagination.vue'
import http from '@/services/http'
import type { Product } from '@/types'
import { renderBarcodeDataUrl } from '@/composables/useBarcode'

const store = useProductStore()
const auth = useAuthStore()

// Preview scale: how many screen pixels represent one millimetre.
const PX_PER_MM = 5
// 1pt = 1/72 inch = 0.3528mm — used to show pt font sizes at canvas scale.
const PT_TO_MM = 0.352778

const numClass =
  'w-24 h-9 px-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500'
const cellClass =
  'w-full px-1 py-1 text-center rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 disabled:opacity-40 focus:outline-none focus:ring-1 focus:ring-orange-500'

// ── Search / listing ─────────────────────────────────────────────
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
  loadTemplate()
})

// ── Template (label format + per-field placement) ────────────────
const ALL_FIELDS = [
  { key: 'title', label: 'Titre' },
  { key: 'barcode', label: 'Code-barres' },
  { key: 'sku', label: 'SKU' },
  { key: 'imei', label: 'IMEI' },
  { key: 'salePrice', label: 'Prix de vente' },
  { key: 'purchasePrice', label: "Prix d'achat" },
  { key: 'category', label: 'Catégorie' },
  { key: 'brand', label: 'Marque' },
] as const
type FieldKey = (typeof ALL_FIELDS)[number]['key']

// IMEI only makes sense for tenants tracking serial numbers (téléphonie,
// électronique), so it's gated on the tenant's IMEI module. The template
// still carries its placement, so toggling the module back on restores it.
const availableFields = computed(() => ALL_FIELDS.filter((f) => f.key !== 'imei' || auth.hasModule('imei')))

interface FieldLayout {
  enabled: boolean
  x: number // mm from left edge
  y: number // mm from top edge
  size: number // pt for text, mm width for the barcode
  height: number // mm, barcode only
  bold: boolean
  boxed: boolean
}

const presets = [
  { label: '50×30', w: 50, h: 30 },
  { label: '40×30', w: 40, h: 30 },
  { label: '60×40', w: 60, h: 40 },
  { label: '100×50', w: 100, h: 50 },
]

const printModes = [
  { value: 'sheet' as const, label: 'Planche A4' },
  { value: 'roll' as const, label: 'Rouleau' },
]

function defaultLabel() {
  return { width: 50, height: 30, border: true }
}

function defaultLayout(): Record<FieldKey, FieldLayout> {
  return {
    title: { enabled: true, x: 2, y: 2, size: 8, height: 0, bold: true, boxed: false },
    barcode: { enabled: true, x: 8, y: 8, size: 34, height: 11, bold: false, boxed: false },
    sku: { enabled: false, x: 2, y: 6, size: 6, height: 0, bold: false, boxed: false },
    imei: { enabled: false, x: 2, y: 21, size: 6, height: 0, bold: false, boxed: false },
    salePrice: { enabled: true, x: 28, y: 23, size: 10, height: 0, bold: true, boxed: true },
    purchasePrice: { enabled: false, x: 2, y: 20, size: 6, height: 0, bold: false, boxed: false },
    category: { enabled: false, x: 2, y: 6, size: 6, height: 0, bold: false, boxed: false },
    brand: { enabled: true, x: 2, y: 24, size: 8, height: 0, bold: true, boxed: false },
  }
}

const label = reactive(defaultLabel())
const layout = reactive<Record<FieldKey, FieldLayout>>(defaultLayout())
const printMode = ref<'sheet' | 'roll'>('sheet')
const printError = ref('')

const enabledFields = computed(() => availableFields.value.filter((f) => layout[f.key].enabled))

// ── Persistence ──────────────────────────────────────────────────
// The template lives in the tenant's `settings` table (domain `labels`,
// key `template`) as a JSON blob, so one layout is shared by every user
// and every device of the shop. Reading is open to all authenticated
// users; writing is admin-only (POST /settings sits behind the admin
// middleware), so non-admins can still tweak and print locally but their
// changes are session-only.
type SaveState = 'idle' | 'saving' | 'saved' | 'error' | 'readonly'
const saveState = ref<SaveState>('idle')
const templateLoaded = ref(false)

function serializeTemplate(): string {
  return JSON.stringify({
    label: { ...label },
    layout: JSON.parse(JSON.stringify(layout)),
    printMode: printMode.value,
  })
}

function applyTemplate(raw: string) {
  const saved = JSON.parse(raw)
  if (saved.label) Object.assign(label, saved.label)
  if (saved.printMode) printMode.value = saved.printMode
  if (saved.layout) {
    for (const f of ALL_FIELDS) {
      if (saved.layout[f.key]) Object.assign(layout[f.key], saved.layout[f.key])
    }
  }
}

async function loadTemplate() {
  try {
    // GET /settings nests values under their domain: { labels: { template } }
    const { data } = await http.get('/settings', { params: { domain: 'labels' } })
    if (data?.labels?.template) applyTemplate(data.labels.template)
  } catch {
    // No saved template yet, or the request failed — defaults stay in place.
  } finally {
    // Only start auto-saving once the stored template has been applied,
    // otherwise the initial defaults would immediately overwrite it.
    templateLoaded.value = true
    if (!auth.isAdmin) saveState.value = 'readonly'
  }
}

let saveTimer: ReturnType<typeof setTimeout> | null = null

async function persistTemplate() {
  if (!auth.isAdmin) {
    saveState.value = 'readonly'
    return
  }
  saveState.value = 'saving'
  try {
    await http.post('/settings', { domain: 'labels', settings: { template: serializeTemplate() } })
    saveState.value = 'saved'
  } catch {
    saveState.value = 'error'
  }
}

const saveStatus = computed(() => {
  switch (saveState.value) {
    case 'saving':
      return { text: 'Enregistrement...', class: 'text-gray-400' }
    case 'saved':
      return { text: 'Modèle enregistré', class: 'text-green-600 dark:text-green-400' }
    case 'error':
      return { text: 'Échec de l’enregistrement', class: 'text-red-600 dark:text-red-400' }
    case 'readonly':
      return { text: 'Lecture seule (admin requis)', class: 'text-amber-600 dark:text-amber-400' }
    default:
      return { text: '', class: '' }
  }
})

function scheduleSave() {
  if (!templateLoaded.value) return
  if (saveTimer) clearTimeout(saveTimer)
  // Dragging fires continuously — debounce so one gesture is a single write.
  saveTimer = setTimeout(persistTemplate, 700)
}

function resetTemplate() {
  Object.assign(label, defaultLabel())
  const d = defaultLayout()
  for (const f of ALL_FIELDS) Object.assign(layout[f.key], d[f.key])
  printMode.value = 'sheet'
}

watch([label, layout, printMode], scheduleSave, { deep: true })

// ── Selection ────────────────────────────────────────────────────
interface SelectedEntry {
  product: Product
  qty: number
}
const selected = reactive(new Map<number, SelectedEntry>())
const selectedList = computed(() => Array.from(selected.values()))

function toggleProduct(p: Product) {
  if (selected.has(p.id)) selected.delete(p.id)
  else selected.set(p.id, { product: p, qty: 1 })
}
function removeProduct(id: number) {
  selected.delete(id)
}
function clearSelection() {
  selected.clear()
}

const previewLabels = computed(() => {
  const out: Product[] = []
  for (const s of selectedList.value) {
    for (let i = 0; i < Math.max(1, s.qty || 1); i++) out.push(s.product)
  }
  return out
})
const totalLabelCount = computed(() => previewLabels.value.length)

// The designer needs something to draw before any product is picked.
const PLACEHOLDER = {
  id: 0,
  p_title: 'Nom du produit',
  p_code: 'PRD-0000',
  p_sku: 'SKU-0000',
  p_ean13: '6923736790424',
  p_salePrice: 699,
  p_purchasePrice: 500,
  category: { ctg_title: 'Catégorie' },
  brand: { br_title: 'MARQUE' },
} as unknown as Product

const sampleProduct = computed<Product>(() => selectedList.value[0]?.product ?? PLACEHOLDER)
const sampleBarcode = computed(() =>
  sampleProduct.value.p_ean13 ? renderBarcodeDataUrl(sampleProduct.value.p_ean13) : renderBarcodeDataUrl('6923736790424')
)

// ── Field content ────────────────────────────────────────────────
function formatPriceDh(value: number): string {
  return value.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' dhs'
}

function fieldText(key: FieldKey, p: Product): string {
  switch (key) {
    case 'title':
      return p.p_title
    case 'sku':
      return p.p_sku || p.p_code
    case 'imei':
      return p.p_imei ?? ''
    case 'salePrice':
      return formatPriceDh(Number(p.p_salePrice))
    case 'purchasePrice':
      return formatPriceDh(Number(p.p_purchasePrice))
    case 'category':
      return p.category?.ctg_title ?? ''
    case 'brand':
      return p.brand?.br_title ?? ''
    default:
      return ''
  }
}

function fieldStyle(key: FieldKey) {
  const f = layout[key]
  const base: Record<string, string> = {
    left: f.x * PX_PER_MM + 'px',
    top: f.y * PX_PER_MM + 'px',
  }
  if (key !== 'barcode') {
    base.fontSize = f.size * PT_TO_MM * PX_PER_MM + 'px'
    base.fontWeight = f.bold ? '700' : '400'
    base.color = '#111827'
  }
  return base
}

// ── Drag to position ─────────────────────────────────────────────
const canvasEl = ref<HTMLElement | null>(null)

// ── Overflow detection ───────────────────────────────────────────
// The printed label sets overflow:hidden, so anything past the edges is
// silently cut. The canvas clips identically — and we flag the offending
// fields, otherwise a clipped field just disappears with no explanation.
const fieldEls: Record<string, HTMLElement | null> = {}
const overflowing = ref<FieldKey[]>([])

function setFieldEl(key: FieldKey, el: unknown) {
  fieldEls[key] = (el as HTMLElement) ?? null
}

async function checkOverflow() {
  await nextTick()
  const canvas = canvasEl.value
  if (!canvas) return
  const c = canvas.getBoundingClientRect()
  const tol = 1 // px, absorbs the canvas border and sub-pixel rounding

  overflowing.value = enabledFields.value
    .filter((f) => {
      const el = fieldEls[f.key]
      if (!el) return false
      const r = el.getBoundingClientRect()
      return r.right > c.right + tol || r.bottom > c.bottom + tol || r.left < c.left - tol || r.top < c.top - tol
    })
    .map((f) => f.key)
}

const overflowNames = computed(() =>
  overflowing.value.map((k) => ALL_FIELDS.find((f) => f.key === k)?.label ?? k).join(', ')
)

watch([layout, label, enabledFields, sampleProduct], checkOverflow, { deep: true })
onMounted(checkOverflow)
const activeField = ref<FieldKey | null>(null)

function clamp(v: number, min: number, max: number) {
  return Math.min(Math.max(v, min), max)
}
function round1(v: number) {
  return Math.round(v * 2) / 2
}

function startDrag(e: PointerEvent, key: FieldKey) {
  e.preventDefault()
  activeField.value = key

  const startX = e.clientX
  const startY = e.clientY
  const origX = layout[key].x
  const origY = layout[key].y

  // Listeners go on window rather than the dragged element so the field keeps
  // following the cursor even when it briefly leaves the element (or the
  // canvas) mid-drag — the element itself is only a few millimetres wide.
  const move = (ev: PointerEvent) => {
    layout[key].x = round1(clamp(origX + (ev.clientX - startX) / PX_PER_MM, 0, label.width))
    layout[key].y = round1(clamp(origY + (ev.clientY - startY) / PX_PER_MM, 0, label.height))
  }
  const up = () => {
    window.removeEventListener('pointermove', move)
    window.removeEventListener('pointerup', up)
  }
  window.addEventListener('pointermove', move)
  window.addEventListener('pointerup', up)
}

// ── Print ────────────────────────────────────────────────────────
function esc(v: unknown): string {
  return String(v ?? '').replace(
    /[&<>"']/g,
    (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] as string
  )
}

function labelHtml(p: Product): string {
  const parts = enabledFields.value.map((f) => {
    const l = layout[f.key]
    const pos = `position:absolute;left:${l.x}mm;top:${l.y}mm;`

    if (f.key === 'barcode') {
      const src = p.p_ean13 ? renderBarcodeDataUrl(p.p_ean13) : ''
      return src
        ? `<img style="${pos}width:${l.size}mm;height:${l.height}mm" src="${src}" />`
        : `<div style="${pos}font-size:6pt;color:#9ca3af;font-style:italic">Pas de code EAN</div>`
    }

    const box = l.boxed ? 'border:1.5pt solid #111827;padding:0.3mm 1mm;' : ''
    const style = `${pos}font-size:${l.size}pt;font-weight:${l.bold ? 700 : 400};line-height:1;white-space:nowrap;${box}`
    return `<div style="${style}">${esc(fieldText(f.key, p))}</div>`
  })

  return `<div class="label">${parts.join('')}</div>`
}

function printLabels() {
  if (!previewLabels.value.length) return

  const labels = previewLabels.value.map(labelHtml).join('')
  const border = label.border ? 'border:0.2mm solid #9ca3af;' : ''

  // Roll printers (e.g. Zebra GC420t) feed one label at a time, so the page
  // itself is the label. Sheet mode flows them across an A4 page instead.
  const pageCss =
    printMode.value === 'roll'
      ? `@page { size: ${label.width}mm ${label.height}mm; margin: 0; }
         .label { page-break-after: always; }`
      : `@page { size: A4; margin: 8mm; }
         .label { margin: 0 2mm 2mm 0; }`

  const html = `<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Étiquettes produits</title>
<style>
  ${pageCss}
  body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; color: #111827; }
  .sheet { display: flex; flex-wrap: wrap; }
  .label {
    position: relative;
    width: ${label.width}mm;
    height: ${label.height}mm;
    ${border}
    box-sizing: border-box;
    overflow: hidden;
    page-break-inside: avoid;
  }
</style></head><body>
<div class="sheet">${labels}</div>
</body></html>`

  const printWindow = window.open('', '_blank')
  if (!printWindow) {
    // Popup blocked — without this the click looks like it did nothing at all.
    printError.value = "Impression bloquée par le navigateur. Autorisez les fenêtres pop-up pour ce site, puis réessayez."
    return
  }
  printError.value = ''
  printWindow.document.write(html)
  printWindow.document.close()
  printWindow.onload = () => {
    printWindow.print()
  }
}
</script>
