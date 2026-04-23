<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import http from '@/services/http'
import type { ThirdPartner, Warehouse, Product, DocumentIncrementor } from '@/types'

interface LineItem {
  key: number
  product_id: number | null
  designation: string
  reference: string
  quantity: number
  unit: string
  unit_price: number
  discount_percent: number
  tax_percent: number
}

interface Props {
  domain: 'vente' | 'achat'
  documentTypes: Array<{ value: string; label: string }>
  partnerLabel: string
  partnerRoles: string[]
  loading?: boolean
  initialData?: Record<string, unknown> | null
  editMode?: boolean
}

const props = withDefaults(defineProps<Props>(), { loading: false, initialData: null, editMode: false })

const emit = defineEmits<{
  submit: [payload: Record<string, unknown>]
  cancel: []
}>()

let lineKeyCounter = 0

const incrementors = ref<DocumentIncrementor[]>([])
const partners = ref<ThirdPartner[]>([])
const warehouses = ref<Warehouse[]>([])
const products = ref<Product[]>([])
const partnerSearch = ref('')
const productSearches = reactive<Record<number, string>>({})
const validationErrors = ref<Record<string, string[]>>({})
const nextReference = ref<string | null>(null)
const loadingRef = ref(false)

async function fetchNextReference(incrementorId: number | null) {
  if (!incrementorId) {
    nextReference.value = null
    return
  }
  loadingRef.value = true
  try {
    const res = await http.get<{ reference: string }>(`/document-incrementors/${incrementorId}/reserve`)
    nextReference.value = res.data?.reference ?? null
  } catch {
    nextReference.value = null
  } finally {
    loadingRef.value = false
  }
}

const form = reactive({
  document_type: props.documentTypes[0]?.value ?? '',
  document_incrementor_id: null as number | null,
  thirdPartner_id: null as number | null,
  warehouse_id: null as number | null,
  issued_at: new Date().toISOString().split('T')[0],
  due_at: '',
  notes: '',
  payment_method: 'credit' as string,
})

const lines = ref<LineItem[]>([])

function createEmptyLine(): LineItem {
  return {
    key: ++lineKeyCounter,
    product_id: null,
    designation: '',
    reference: '',
    quantity: 1,
    unit: 'pcs',
    unit_price: 0,
    discount_percent: 0,
    tax_percent: 20,
  }
}

const pendingLine = reactive(createEmptyLine())
const pendingSearch = ref('')
const showPendingDropdown = ref(false)

// Resolved display prices keyed by product_id. Populated once products are
// loaded (as the comptoir tariff) and refreshed whenever the partner changes
// so the product dropdown reflects the customer-specific price.
const displayPrices = ref<Map<number, number>>(new Map())

function displayPriceFor(p: any): number {
  const resolved = displayPrices.value.get(Number(p.id))
  if (typeof resolved === 'number') return resolved
  return Number(props.domain === 'vente' ? (p.p_salePrice ?? 0) : (p.p_purchasePrice ?? 0))
}

async function refreshDisplayPrices() {
  if (props.domain !== 'vente' || !products.value.length) {
    displayPrices.value = new Map()
    return
  }
  try {
    const { data } = await http.post<{
      items: Array<{ product_id: number; unit_price: number }>
    }>('/products/reprice', {
      customer_id: form.thirdPartner_id,
      channel: 'all',
      items: products.value.map((p) => ({ product_id: p.id, quantity: 1 })),
    })
    const next = new Map<number, number>()
    for (const row of data.items) {
      next.set(Number(row.product_id), Number(row.unit_price))
    }
    displayPrices.value = next
  } catch {
    /* keep previous map on failure */
  }
}

/**
 * Resolve the unit price (HT) and tax rate for a single product against the
 * currently selected customer. Sales use the PriceResolver (channel 'all') so
 * the price reflects the customer's assigned list, the default walk-in tariff,
 * or finally p_salePrice. Purchases keep the raw p_purchasePrice.
 */
async function resolveSalePrice(
  product: Product,
  quantity: number,
): Promise<{ unit_price: number; tax_percent: number }> {
  const fallback = {
    unit_price: Number(product.p_salePrice ?? 0),
    tax_percent: Number(product.p_taxRate ?? 20),
  }
  try {
    const { data } = await http.post<{
      items: Array<{ product_id: number; unit_price: number; tax_percent: number }>
    }>('/products/reprice', {
      customer_id: form.thirdPartner_id,
      channel: 'all',
      items: [{ product_id: product.id, quantity: Math.max(1, Math.floor(quantity || 1)) }],
    })
    const row = data.items?.[0]
    if (!row) return fallback
    return {
      unit_price: Number(row.unit_price ?? fallback.unit_price),
      tax_percent: Number(row.tax_percent ?? fallback.tax_percent),
    }
  } catch {
    return fallback
  }
}

async function selectPendingProduct(product: Product) {
  pendingLine.product_id = product.id
  pendingLine.designation = product.p_title
  pendingLine.reference = product.p_code ?? ''
  pendingLine.tax_percent = product.p_taxRate ?? 20
  pendingLine.unit = product.p_unit ?? 'pcs'
  pendingSearch.value = product.p_title
  showPendingDropdown.value = false

  if (props.domain === 'vente') {
    const { unit_price, tax_percent } = await resolveSalePrice(product, pendingLine.quantity)
    pendingLine.unit_price = unit_price
    pendingLine.tax_percent = tax_percent
  } else {
    pendingLine.unit_price = product.p_purchasePrice
  }
}

function stockForWarehouse(p: any, whId: number | null): number {
  // Laravel serialises the `warehouseStocks` relation as `warehouse_stocks`
  // (snake_case) but some call sites still read the raw camelCase form, so
  // accept both. Pivot columns keep their raw names.
  const list = p?.warehouse_stocks ?? p?.warehouseStocks ?? []
  if (!list.length) return 0
  const readLevel = (ws: any) => Number(ws.stockLevel ?? ws.stock_level ?? 0)
  if (whId) {
    const hit = list.find((ws: any) => Number(ws.warehouse_id) === Number(whId))
    return readLevel(hit ?? {})
  }
  return list.reduce((sum: number, ws: any) => sum + readLevel(ws), 0)
}

const selectedWarehouseName = computed(() => {
  if (!form.warehouse_id) return ''
  return warehouses.value.find((w: any) => Number(w.id) === Number(form.warehouse_id))?.wh_title ?? ''
})

const filteredPendingProducts = computed(() => {
  const q = pendingSearch.value.toLowerCase().trim()
  const filtered = !q
    ? products.value.slice(0, 15)
    : products.value
        .filter((p) => p.p_title.toLowerCase().includes(q) || p.p_code.toLowerCase().includes(q))
        .slice(0, 15)

  return filtered.map((p: any) => ({
    ...p,
    warehouse_stock: stockForWarehouse(p, form.warehouse_id),
  }))
})

function canAddArticle(): boolean {
  return !!pendingLine.product_id && pendingLine.quantity > 0
}

function addArticle() {
  if (!canAddArticle()) return
  lines.value.push({ ...pendingLine, key: ++lineKeyCounter })
  Object.assign(pendingLine, createEmptyLine())
  pendingSearch.value = ''
}

function removeLine(idx: number) {
  lines.value.splice(idx, 1)
}

async function selectProduct(line: LineItem, product: Product) {
  line.product_id = product.id
  line.designation = product.p_title
  line.reference = product.p_code ?? ''
  line.tax_percent = product.p_taxRate ?? 20
  line.unit = product.p_unit ?? 'pcs'
  delete productSearches[line.key]

  if (props.domain === 'vente') {
    const { unit_price, tax_percent } = await resolveSalePrice(product, line.quantity)
    line.unit_price = unit_price
    line.tax_percent = tax_percent
  } else {
    line.unit_price = product.p_purchasePrice
  }
}

/**
 * Re-price every line already in the grid when the selected customer
 * changes. Keeps existing quantities so tier-based pricing still applies.
 */
async function repriceAllLines() {
  if (props.domain !== 'vente') return
  const withProduct = lines.value.filter((l) => l.product_id)
  if (!withProduct.length) return
  try {
    const { data } = await http.post<{
      items: Array<{ product_id: number; unit_price: number; tax_percent: number }>
    }>('/products/reprice', {
      customer_id: form.thirdPartner_id,
      channel: 'all',
      items: withProduct.map((l) => ({
        product_id: l.product_id,
        quantity: Math.max(1, Math.floor(l.quantity || 1)),
      })),
    })
    const byId = new Map(data.items.map((r) => [r.product_id, r]))
    for (const line of lines.value) {
      if (!line.product_id) continue
      const priced = byId.get(line.product_id)
      if (!priced) continue
      line.unit_price = Number(priced.unit_price)
      line.tax_percent = Number(priced.tax_percent)
    }
  } catch {
    // Leave prices untouched on failure.
  }
}

watch(
  () => form.thirdPartner_id,
  (newId, oldId) => {
    if (newId === oldId) return
    repriceAllLines()
    refreshDisplayPrices()
  },
)

function lineHt(line: LineItem): number {
  return line.quantity * line.unit_price * (1 - line.discount_percent / 100)
}

function lineTax(line: LineItem): number {
  return (lineHt(line) * line.tax_percent) / 100
}

function lineTtc(line: LineItem): number {
  return lineHt(line) + lineTax(line)
}

const totalHt = computed(() => lines.value.reduce((s, l) => s + lineHt(l), 0))
const totalDiscount = computed(() =>
  lines.value.reduce((s, l) => s + (l.quantity * l.unit_price * l.discount_percent) / 100, 0),
)
const totalTax = computed(() => lines.value.reduce((s, l) => s + lineTax(l), 0))
const totalTtc = computed(() => totalHt.value + totalTax.value)

const filteredPartners = computed(() => {
  const q = partnerSearch.value.toLowerCase().trim()
  let list = partners.value.filter((p) => props.partnerRoles.includes(p.tp_Role))
  if (q) list = list.filter((p) => p.tp_title.toLowerCase().includes(q))
  return list.slice(0, 20)
})

function filteredProducts(lineKey: number): Product[] {
  const q = (productSearches[lineKey] ?? '').toLowerCase().trim()
  if (!q) return products.value.slice(0, 15)
  return products.value
    .filter((p) => p.p_title.toLowerCase().includes(q) || p.p_code.toLowerCase().includes(q))
    .slice(0, 15)
}

const matchedIncrementor = computed(() => {
  return (
    incrementors.value.find((inc) => inc.di_model?.toLowerCase() === form.document_type.toLowerCase()) ??
    incrementors.value[0] ??
    null
  )
})

watch(
  () => form.document_type,
  () => {
    if (matchedIncrementor.value) {
      form.document_incrementor_id = matchedIncrementor.value.id
      if (!props.editMode) fetchNextReference(matchedIncrementor.value.id)
    } else {
      nextReference.value = null
    }
  },
)

const selectedPartnerName = computed(() => {
  if (!form.thirdPartner_id) return ''
  return partners.value.find((p) => p.id === form.thirdPartner_id)?.tp_title ?? ''
})

const showPartnerDropdown = ref(false)
const showProductDropdown = ref<number | null>(null)

function selectPartner(p: ThirdPartner) {
  form.thirdPartner_id = p.id
  partnerSearch.value = p.tp_title
  showPartnerDropdown.value = false
}

function delayedClosePartner() {
  window.setTimeout(() => {
    showPartnerDropdown.value = false
  }, 200)
}

function openProductDropdown(lineKey: number) {
  showProductDropdown.value = lineKey
}

function closeProductDropdown() {
  showProductDropdown.value = null
}

function delayedCloseProduct(lineKey: number) {
  window.setTimeout(() => {
    if (showProductDropdown.value === lineKey) closeProductDropdown()
  }, 200)
}

function delayedClosePending() {
  window.setTimeout(() => {
    showPendingDropdown.value = false
  }, 200)
}

function canSubmit(): boolean {
  return !!form.document_incrementor_id && lines.value.some((l) => l.designation.trim() !== '' && l.quantity > 0)
}

function onSubmit() {
  validationErrors.value = {}
  const payload: Record<string, unknown> = {
    document_incrementor_id: form.document_incrementor_id,
    document_type: form.document_type,
    thirdPartner_id: form.thirdPartner_id,
    warehouse_id: form.warehouse_id,
    issued_at: form.issued_at || null,
    due_at: form.due_at || null,
    notes: form.notes || null,
    lines: lines.value
      .filter((l) => l.designation.trim() !== '')
      .map((l) => ({
        product_id: l.product_id,
        designation: l.designation,
        reference: l.reference || null,
        quantity: l.quantity,
        unit: l.unit,
        unit_price: l.unit_price,
        discount_percent: l.discount_percent,
        tax_percent: l.tax_percent,
        line_type: 'product',
      })),
    footer: {
      total_ht: totalHt.value,
      total_discount: totalDiscount.value,
      total_tax: totalTax.value,
      total_ttc: totalTtc.value,
      amount_paid: 0,
      amount_due: totalTtc.value,
      payment_method: form.payment_method,
    },
  }
  console.log(payload)
  emit('submit', payload)
}

function setValidationErrors(errors: Record<string, string[]>) {
  validationErrors.value = errors
}

defineExpose({ setValidationErrors })

onMounted(async () => {
  const [incRes, partRes, whRes, prodRes] = await Promise.all([
    http.get<DocumentIncrementor[]>('/document-incrementors'),
    http.get('/third-partners', { params: { per_page: 200 } }),
    http.get<Warehouse[]>('/warehouses'),
    http.get('/products', { params: { per_page: 200 } }),
  ])
  incrementors.value = Array.isArray(incRes.data) ? incRes.data : ((incRes.data as any).data ?? [])
  partners.value = Array.isArray(partRes.data) ? partRes.data : ((partRes.data as any).data ?? [])
  warehouses.value = Array.isArray(whRes.data) ? whRes.data : ((whRes.data as any).data ?? [])
  products.value = Array.isArray(prodRes.data) ? prodRes.data : ((prodRes.data as any).data ?? [])

  // Seed dropdown prices from the walk-in tariff (no customer yet). Sales only.
  refreshDisplayPrices()

  if (props.initialData && props.editMode) {
    const d = props.initialData as any
    form.document_type = d.document_type ?? form.document_type
    form.document_incrementor_id = d.document_incrementor_id ?? null
    form.thirdPartner_id = d.thirdPartner_id ?? d.third_partner_id ?? null
    form.warehouse_id = d.warehouse_id ?? null
    form.issued_at = d.issued_at ? String(d.issued_at).split('T')[0] : form.issued_at
    form.due_at = d.due_at ? String(d.due_at).split('T')[0] : ''
    form.notes = d.notes ?? ''
    form.payment_method = d.footer?.payment_method ?? 'credit'

    const partner = partners.value.find((p) => p.id === form.thirdPartner_id)
    if (partner) partnerSearch.value = partner.tp_title

    if (d.lignes?.length) {
      lines.value = d.lignes.map((l: any) => ({
        key: ++lineKeyCounter,
        product_id: l.product_id ?? null,
        designation: l.designation ?? '',
        reference: l.reference ?? '',
        quantity: l.quantity ?? 1,
        unit: l.unit ?? 'pcs',
        unit_price: Number(l.unit_price ?? 0),
        discount_percent: Number(l.discount_percent ?? 0),
        tax_percent: Number(l.tax_percent ?? 20),
      }))
    }
  } else if (matchedIncrementor.value) {
    form.document_incrementor_id = matchedIncrementor.value.id
    fetchNextReference(matchedIncrementor.value.id)
  }
})

function fmt(n: number): string {
  return n.toFixed(2)
}

function getStockClass(stock: number): string {
  if (stock > 0) return 'text-green-600 dark:text-green-400'
  if (stock < 0) return 'text-red-600 dark:text-red-400'
  return 'text-yellow-600 dark:text-yellow-400'
}
</script>

<template>
  <form class="space-y-6" @submit.prevent="onSubmit">
    <!-- Document Metadata -->
    <div
      class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden"
    >
      <div
        class="px-6 py-4 bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between"
      >
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">
          Informations du document
        </h3>
        <span v-if="loadingRef" class="flex items-center gap-1.5 text-xs text-gray-400">
          <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </span>
        <span
          v-else-if="nextReference && !editMode"
          class="px-3 py-0.5 text-lg font-mono font-semibold text-gray-800 dark:text-gray-200"
        >
          Numéro: {{ nextReference }}
        </span>
      </div>
      <div class="p-4 sm:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
        <!-- Document Type -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Type de document *</label>
          <select
            v-model="form.document_type"
            :disabled="editMode"
            class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition disabled:opacity-60 disabled:cursor-not-allowed"
          >
            <option v-for="dt in documentTypes" :key="dt.value" :value="dt.value">{{ dt.label }}</option>
          </select>
        </div>

        <!-- Partner -->
        <div class="relative">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ partnerLabel }}</label>
          <input
            v-model="partnerSearch"
            :placeholder="`Rechercher ${partnerLabel.toLowerCase()}...`"
            class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
            @focus="showPartnerDropdown = true"
            @blur="delayedClosePartner"
          />
          <input type="hidden" :value="form.thirdPartner_id" />
          <div
            v-if="selectedPartnerName && !showPartnerDropdown && partnerSearch !== selectedPartnerName"
            class="text-xs text-gray-500 dark:text-gray-400 mt-1"
          >
            Sélectionné : {{ selectedPartnerName }}
          </div>
          <div
            v-if="showPartnerDropdown && filteredPartners.length"
            class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-48 overflow-y-auto"
          >
            <button
              v-for="p in filteredPartners"
              :key="p.id"
              type="button"
              class="w-full text-left px-4 py-2.5 text-sm hover:bg-blue-50 dark:hover:bg-blue-900/30 transition"
              :class="{ 'bg-blue-50 dark:bg-blue-900/30 font-medium': form.thirdPartner_id === p.id }"
              @mousedown.prevent="selectPartner(p)"
            >
              <span class="font-medium dark:text-gray-200">{{ p.tp_title }}</span>
              <span class="text-gray-400 dark:text-gray-500 ml-2 text-xs">{{ p.tp_code }}</span>
            </button>
          </div>
        </div>

        <!-- Warehouse -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Entrepôt</label>
          <select
            v-model="form.warehouse_id"
            class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
          >
            <option :value="null">— Aucun —</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.wh_title }}</option>
          </select>
        </div>

        <!-- Issued date -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Date d'émission</label>
          <input
            v-model="form.issued_at"
            type="date"
            class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
          />
        </div>

        <!-- Due date -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Date d'échéance</label>
          <input
            v-model="form.due_at"
            type="date"
            class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
          />
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
          <input
            v-model="form.notes"
            type="text"
            placeholder="Notes internes..."
            class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
          />
        </div>

        <!-- Payment Method -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Moyen de paiement</label>
          <select
            v-model="form.payment_method"
            class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
          >
            <option value="credit">En compte (Crédit)</option>
            <option value="cash">Espèces</option>
            <option value="bank_transfer">Virement bancaire</option>
            <option value="cheque">Chèque</option>
            <option value="effet">Effet</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Line Items -->
    <div
      class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden"
    >
      <div class="px-4 sm:px-6 py-4 bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Articles</h3>
      </div>

      <!-- Add-article bar -->
      <div class="px-4 sm:px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-blue-50/50 dark:bg-blue-900/10">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
          <!-- Product search -->
          <div class="relative flex-1">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Produit</label>
            <div class="flex gap-2">
              <input
                v-model="pendingSearch"
                placeholder="Chercher un produit..."
                class="flex-1 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                @input="showPendingDropdown = true"
                @blur="delayedClosePending"
              />
              <button
                type="button"
                class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600 hover:text-blue-600 dark:hover:text-blue-400 transition"
                title="Rechercher"
                @click="showPendingDropdown = !showPendingDropdown"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <circle cx="11" cy="11" r="8" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35" />
                </svg>
              </button>
            </div>
            <div
              v-if="showPendingDropdown && filteredPendingProducts.length"
              class="absolute z-30 mt-1 left-0 right-0 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-48 overflow-y-auto"
            >
              <button
                v-for="p in filteredPendingProducts"
                :key="p.id"
                type="button"
                class="w-full text-left px-4 py-2.5 text-sm hover:bg-blue-50 dark:hover:bg-blue-900/30 transition flex justify-between items-center"
                @mousedown.prevent="selectPendingProduct(p)"
              >
                <span class="flex-1">
                  <span class="font-medium dark:text-gray-200">{{ p.p_title }}</span>
                  <span class="text-gray-400 dark:text-gray-500 text-xs ml-1">[{{ p.p_code }}]</span>
                  <span :class="getStockClass(p.warehouse_stock)" class="ml-2 text-xs font-medium">
                    <template v-if="form.warehouse_id">
                      Stock ({{ selectedWarehouseName }}): {{ Number(p.warehouse_stock ?? 0).toFixed(2) }} {{ p.p_unit ?? 'pcs' }}
                    </template>
                    <template v-else>
                      Stock total: {{ Number(p.warehouse_stock ?? 0).toFixed(2) }} {{ p.p_unit ?? 'pcs' }}
                    </template>
                  </span>
                </span>
                <span class="text-gray-500 dark:text-gray-400 text-xs"
                  >{{ fmt(displayPriceFor(p)) }} DH</span
                >
              </button>
            </div>
          </div>

          <!-- Quantity -->
          <div class="w-28">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Quantité</label>
            <input
              v-model.number="pendingLine.quantity"
              type="number"
              min="0.01"
              step="0.01"
              class="w-full px-3 py-2 text-sm text-right rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
              @keydown.enter.prevent="addArticle()"
            />
          </div>

          <!-- Add button -->
          <button
            type="button"
            :disabled="!canAddArticle()"
            class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-medium text-white rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed"
            :class="domain === 'vente' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-teal-600 hover:bg-teal-700'"
            @click="addArticle"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Ajouter article
          </button>
        </div>
      </div>

      <!-- Lines table -->
      <div v-if="lines.length" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800/80 text-gray-500 dark:text-gray-400 uppercase text-xs">
            <tr>
              <th class="text-left px-3 py-3 w-8">#</th>
              <th class="text-left px-3 py-3 min-w-[220px]">Produit / Désignation</th>
              <th class="text-right px-3 py-3 w-20">Qté</th>
              <th class="text-left px-3 py-3 w-16">Unité</th>
              <th class="text-right px-3 py-3 w-28">PU HT</th>
              <th class="text-right px-3 py-3 w-20">Remise %</th>
              <th class="text-right px-3 py-3 w-20">TVA %</th>
              <th class="text-right px-3 py-3 w-28">Total TTC</th>
              <th class="px-3 py-3 w-10"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr
              v-for="(line, idx) in lines"
              :key="line.key"
              class="group hover:bg-gray-50/50 dark:hover:bg-gray-700/30"
            >
              <td class="px-3 py-2 text-gray-400 dark:text-gray-500 text-xs">{{ idx + 1 }}</td>

              <td class="px-3 py-2 relative">
                <div class="flex gap-1">
                  <input
                    v-model="productSearches[line.key]"
                    :placeholder="line.designation || 'Chercher un produit...'"
                    class="flex-1 px-2.5 py-1.5 text-sm rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-400 focus:border-blue-400"
                    @input="openProductDropdown(line.key)"
                    @blur="delayedCloseProduct(line.key)"
                  />
                  <button
                    type="button"
                    class="px-2 py-1.5 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-400 dark:text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-600 hover:text-blue-600 dark:hover:text-blue-400 transition"
                    title="Rechercher"
                    @click="openProductDropdown(line.key)"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <circle cx="11" cy="11" r="8" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35" />
                    </svg>
                  </button>
                </div>
                <div
                  v-if="line.designation && !productSearches[line.key]"
                  class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate"
                >
                  {{ line.designation }}
                </div>
                <div
                  v-if="showProductDropdown === line.key && filteredProducts(line.key).length"
                  class="absolute z-30 mt-1 left-3 right-3 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-40 overflow-y-auto"
                >
                  <button
                    v-for="p in filteredProducts(line.key)"
                    :key="p.id"
                    type="button"
                    class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 dark:hover:bg-blue-900/30 transition flex justify-between items-center"
                    @mousedown.prevent="selectProduct(line, p); closeProductDropdown()"
                  >
                    <span class="flex-1">
                      <span class="font-medium dark:text-gray-200">{{ p.p_title }}</span>
                      <span class="text-gray-400 dark:text-gray-500 text-xs ml-1">[{{ p.p_code }}]</span>
                      <span :class="getStockClass(stockForWarehouse(p, form.warehouse_id))" class="ml-2 text-xs font-medium">
                        <template v-if="form.warehouse_id">
                          Stock ({{ selectedWarehouseName }}): {{ stockForWarehouse(p, form.warehouse_id).toFixed(2) }}
                        </template>
                        <template v-else>
                          Stock total: {{ stockForWarehouse(p, form.warehouse_id).toFixed(2) }}
                        </template>
                        {{ p.p_unit ?? 'pcs' }}
                      </span>
                    </span>
                    <span class="text-gray-500 dark:text-gray-400 text-xs"
                      >{{ fmt(displayPriceFor(p)) }} DH</span
                    >
                  </button>
                </div>
              </td>

              <td class="px-3 py-2">
                <input
                  v-model.number="line.quantity"
                  type="number"
                  min="0.01"
                  step="0.01"
                  class="w-full px-2 py-1.5 text-sm text-right rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-400"
                />
              </td>

              <td class="px-3 py-2">
                <input
                  v-model="line.unit"
                  type="text"
                  class="w-full px-2 py-1.5 text-sm rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-400"
                />
              </td>

              <td class="px-3 py-2">
                <input
                  v-model.number="line.unit_price"
                  type="number"
                  min="0"
                  step="0.01"
                  class="w-full px-2 py-1.5 text-sm text-right rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-400"
                />
              </td>

              <td class="px-3 py-2">
                <input
                  v-model.number="line.discount_percent"
                  type="number"
                  min="0"
                  max="100"
                  step="0.1"
                  class="w-full px-2 py-1.5 text-sm text-right rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-400"
                />
              </td>

              <td class="px-3 py-2">
                <input
                  v-model.number="line.tax_percent"
                  type="number"
                  min="0"
                  step="0.1"
                  class="w-full px-2 py-1.5 text-sm text-right rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-400"
                />
              </td>

              <td class="px-3 py-2 text-right font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap">
                {{ fmt(lineTtc(line)) }}
              </td>

              <td class="px-3 py-2">
                <button
                  type="button"
                  class="p-1 text-gray-300 dark:text-gray-600 hover:text-red-500 dark:hover:text-red-400 transition opacity-0 group-hover:opacity-100"
                  @click="removeLine(idx)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                    />
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Empty state -->
      <div v-else class="px-6 py-10 text-center text-gray-400 dark:text-gray-500 text-sm">
        Aucun article ajouté. Recherchez un produit ci-dessus pour commencer.
      </div>
    </div>

    <!-- Footer Totals -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-6">
      <div class="flex justify-end">
        <div class="w-72 space-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Total HT</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">{{ fmt(totalHt) }} DH</span>
          </div>
          <div v-if="totalDiscount > 0" class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Remise</span>
            <span class="font-medium text-red-600 dark:text-red-400">-{{ fmt(totalDiscount) }} DH</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">TVA</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">{{ fmt(totalTax) }} DH</span>
          </div>
          <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-3">
            <span class="text-gray-900 dark:text-white font-bold text-base">Total TTC</span>
            <span class="text-gray-900 dark:text-white font-bold text-base">{{ fmt(totalTtc) }} DH</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Validation Errors -->
    <div
      v-if="Object.keys(validationErrors).length"
      class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4"
    >
      <p class="text-sm font-medium text-red-800 dark:text-red-300 mb-2">Erreurs de validation :</p>
      <ul class="text-sm text-red-700 dark:text-red-400 list-disc list-inside space-y-1">
        <li v-for="(msgs, field) in validationErrors" :key="field">
          <span class="font-medium">{{ field }}</span> : {{ msgs.join(', ') }}
        </li>
      </ul>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between">
      <button
        type="button"
        class="px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
        @click="emit('cancel')"
      >
        Annuler
      </button>
      <button
        type="submit"
        :disabled="loading || !canSubmit()"
        class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-white rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
        :class="domain === 'vente' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-teal-600 hover:bg-teal-700'"
      >
        <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        {{
          loading ? (editMode ? 'Enregistrement...' : 'Création...') : editMode ? 'Enregistrer' : 'Créer le document'
        }}
      </button>
    </div>
  </form>
</template>
