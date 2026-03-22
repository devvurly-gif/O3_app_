<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDocumentStockStore } from '@/stores/stock/useDocumentStockStore'
import { useWarehouseStore } from '@/stores/warehouse'
import http from '@/services/http'
import type { DocumentHeader, Product } from '@/types'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const store = useDocumentStockStore()
const whStore = useWarehouseStore()

const doc = ref<DocumentHeader | null>(null)
const products = ref<Product[]>([])
const saving = ref(false)
const errorMsg = ref('')
const initialLoading = ref(true)

// ── Header ────────────────────────────────────────────────────────────────
const header = reactive({
  document_type: '' as string,
  warehouse_id: '' as string | number,
  warehouse_dest_id: '' as string | number,
  issued_at: '',
  notes: '',
})

// ── Lines ─────────────────────────────────────────────────────────────────
interface StockLine {
  key: number
  product_id: number | null
  designation: string
  reference: string
  quantity: number
  unit: string
  unit_price: number
}

let lineKeyCounter = 0

function createEmptyLine(): StockLine {
  return {
    key: ++lineKeyCounter,
    product_id: null,
    designation: '',
    reference: '',
    quantity: 1,
    unit: 'pcs',
    unit_price: 0,
  }
}

const lines = ref<StockLine[]>([])

// ── Pending line (add-article bar) ────────────────────────────────────────
const pendingLine = reactive(createEmptyLine())
const pendingSearch = ref('')
const showPendingDropdown = ref(false)

const filteredPendingProducts = computed(() => {
  const q = pendingSearch.value.toLowerCase().trim()
  if (!q) return products.value.slice(0, 15)
  return products.value
    .filter((p) => p.p_title.toLowerCase().includes(q) || (p.p_code ?? '').toLowerCase().includes(q))
    .slice(0, 15)
})

function selectPendingProduct(product: Product) {
  pendingLine.product_id = product.id
  pendingLine.designation = product.p_title
  pendingLine.reference = product.p_code ?? ''
  pendingLine.unit = product.p_unit ?? 'pcs'
  pendingLine.unit_price = product.p_purchasePrice ?? 0
  pendingSearch.value = product.p_title
  showPendingDropdown.value = false
}

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

// ── Inline product search per line ────────────────────────────────────────
const productSearches = reactive<Record<number, string>>({})
const showProductDropdown = ref<number | null>(null)

function filteredProducts(lineKey: number): Product[] {
  const q = (productSearches[lineKey] ?? '').toLowerCase().trim()
  if (!q) return products.value.slice(0, 15)
  return products.value
    .filter((p) => p.p_title.toLowerCase().includes(q) || (p.p_code ?? '').toLowerCase().includes(q))
    .slice(0, 15)
}

function selectProduct(line: StockLine, product: Product) {
  line.product_id = product.id
  line.designation = product.p_title
  line.reference = product.p_code ?? ''
  line.unit = product.p_unit ?? 'pcs'
  line.unit_price = product.p_purchasePrice ?? 0
  delete productSearches[line.key]
}

function openProductDropdown(lineKey: number) {
  showProductDropdown.value = lineKey
}
function closeProductDropdown() {
  showProductDropdown.value = null
}
function delayedCloseProduct(lineKey: number) {
  setTimeout(() => {
    if (showProductDropdown.value === lineKey) closeProductDropdown()
  }, 200)
}
function delayedClosePending() {
  setTimeout(() => {
    showPendingDropdown.value = false
  }, 200)
}

// ── Computed ──────────────────────────────────────────────────────────────
const stockTypeLabels: Record<string, string> = {
  StockEntry: 'Entrée de Stock',
  StockExit: 'Sortie de Stock',
  StockAdjustmentNote: "Ajustement d'Inventaire",
  StockTransfer: 'Transfert inter-Dépôts',
}

const isTransfer = computed(() => header.document_type === 'StockTransfer')
const isAdjustment = computed(() => header.document_type === 'StockAdjustmentNote')

const qtyLabel = computed(() => (isAdjustment.value ? 'Qtité cible' : 'Qté'))

const destWarehouses = computed(() => whStore.items.filter((wh) => String(wh.id) !== String(header.warehouse_id)))

// ── Data loading ──────────────────────────────────────────────────────────
onMounted(async () => {
  if (!whStore.items.length) whStore.fetchAll()

  const [docData, prodRes] = await Promise.all([
    store.fetchOne(Number(route.params.id)),
    http.get<{ data: Product[] }>('/products', { params: { per_page: 500 } }),
  ])

  products.value = prodRes.data?.data ?? (prodRes.data as unknown as Product[])

  if (docData) {
    doc.value = docData
    header.document_type = docData.document_type
    header.warehouse_id = docData.warehouse_id ?? ''
    header.warehouse_dest_id = (docData as any).warehouse_dest_id ?? ''
    header.issued_at = docData.issued_at ?? new Date().toISOString().split('T')[0]
    header.notes = docData.notes ?? ''

    if (docData.lignes?.length) {
      lines.value = docData.lignes.map((l: any) => ({
        key: ++lineKeyCounter,
        product_id: l.product_id ?? null,
        designation: l.designation ?? '',
        reference: l.reference ?? '',
        quantity: Number(l.quantity ?? 1),
        unit: l.unit ?? 'pcs',
        unit_price: Number(l.unit_price ?? 0),
      }))
    }
  }

  initialLoading.value = false
})

// ── Submit ────────────────────────────────────────────────────────────────
async function submit() {
  if (!doc.value) return
  errorMsg.value = ''

  if (!header.warehouse_id) {
    errorMsg.value = 'Veuillez sélectionner un dépôt.'
    return
  }
  if (isTransfer.value && !header.warehouse_dest_id) {
    errorMsg.value = 'Veuillez sélectionner le dépôt de destination.'
    return
  }
  if (!lines.value.length || lines.value.some((l) => !l.designation || l.quantity <= 0)) {
    errorMsg.value = 'Ajoutez au moins un article avec une désignation et une quantité.'
    return
  }

  saving.value = true
  try {
    const payload = {
      document_title: stockTypeLabels[header.document_type] ?? header.document_type,
      warehouse_id: header.warehouse_id || null,
      warehouse_dest_id: isTransfer.value ? header.warehouse_dest_id || null : null,
      issued_at: header.issued_at,
      notes: header.notes || null,
      lines: lines.value.map((l) => ({
        product_id: l.product_id || null,
        designation: l.designation,
        reference: l.reference || null,
        quantity: l.quantity,
        unit: l.unit,
        unit_price: l.unit_price || 0,
        discount_percent: 0,
        tax_percent: 0,
        line_type: 'product',
      })),
    }

    await store.update(doc.value.id, payload)
    router.push(`/stock/documents/${doc.value.id}`)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    errorMsg.value = err.response?.data?.message ?? 'Erreur lors de la mise à jour.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-9xl mx-auto py-6 px-4">
    <!-- Loading -->
    <div v-if="initialLoading" class="flex items-center justify-center py-24">
      <div class="flex flex-col items-center gap-3">
        <svg class="w-8 h-8 animate-spin text-violet-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <span class="text-sm text-gray-500 dark:text-gray-400">Chargement...</span>
      </div>
    </div>

    <template v-else-if="doc">
      <!-- Page Header -->
      <div class="mb-6">
        <router-link
          :to="`/stock/documents/${doc.id}`"
          class="text-sm text-violet-600 dark:text-violet-400 hover:underline"
        >
          &larr; Retour au document
        </router-link>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2">
          Modifier le document
          <span class="text-lg font-normal text-gray-400 dark:text-gray-500">#{{ doc.reference }}</span>
        </h1>
      </div>

      <form class="space-y-6" @submit.prevent="submit">
        <!-- Document Metadata Card -->
        <div
          class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden"
        >
          <div
            class="px-6 py-4 bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between"
          >
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">
              Informations du document
            </h3>
            <span
              class="px-3 py-0.5 text-sm font-medium text-violet-700 dark:text-violet-400 bg-violet-50 dark:bg-violet-900/20 rounded-full"
            >
              {{ stockTypeLabels[header.document_type] ?? header.document_type }}
            </span>
          </div>

          <div class="p-4 sm:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
            <!-- Dépôt source -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                {{ isTransfer ? 'Dépôt Source' : 'Dépôt' }} *
              </label>
              <select
                v-model="header.warehouse_id"
                required
                class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition"
              >
                <option value="">Sélectionner un dépôt</option>
                <option v-for="wh in whStore.items" :key="wh.id" :value="wh.id">{{ wh.wh_title }}</option>
              </select>
            </div>

            <!-- Dépôt destination (transfert) -->
            <div v-if="isTransfer">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                >Dépôt Destination *</label
              >
              <select
                v-model="header.warehouse_dest_id"
                required
                class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition"
              >
                <option value="">Sélectionner un dépôt</option>
                <option v-for="wh in destWarehouses" :key="wh.id" :value="wh.id">{{ wh.wh_title }}</option>
              </select>
            </div>

            <!-- Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Date d'émission *</label>
              <input
                v-model="header.issued_at"
                type="date"
                required
                class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition"
              />
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
              <input
                v-model="header.notes"
                type="text"
                placeholder="Motif, observations..."
                class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition"
              />
            </div>
          </div>
        </div>

        <!-- Line Items Card -->
        <div
          class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden"
        >
          <div class="px-4 sm:px-6 py-4 bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Articles</h3>
          </div>

          <!-- Add-article bar -->
          <div
            class="px-4 sm:px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-violet-50/50 dark:bg-violet-900/10"
          >
            <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
              <!-- Product search -->
              <div class="relative flex-1">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Produit</label>
                <div class="flex gap-2">
                  <input
                    v-model="pendingSearch"
                    placeholder="Chercher un produit..."
                    class="flex-1 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition"
                    @input="showPendingDropdown = true"
                    @blur="delayedClosePending"
                  />
                  <button
                    type="button"
                    class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600 hover:text-violet-600 dark:hover:text-violet-400 transition"
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
                    class="w-full text-left px-4 py-2.5 text-sm hover:bg-violet-50 dark:hover:bg-violet-900/30 transition flex justify-between items-center"
                    @mousedown.prevent="selectPendingProduct(p)"
                  >
                    <span>
                      <span class="font-medium dark:text-gray-200">{{ p.p_title }}</span>
                      <span class="text-gray-400 dark:text-gray-500 text-xs ml-1">[{{ p.p_code }}]</span>
                    </span>
                    <span class="text-gray-500 dark:text-gray-400 text-xs">{{ p.p_purchasePrice }} DH</span>
                  </button>
                </div>
              </div>

              <!-- Quantity -->
              <div class="w-28">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ qtyLabel }}</label>
                <input
                  v-model.number="pendingLine.quantity"
                  type="number"
                  min="0.01"
                  step="0.01"
                  class="w-full px-3 py-2 text-sm text-right rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition"
                  @keydown.enter.prevent="addArticle()"
                />
              </div>

              <!-- Add button -->
              <button
                type="button"
                :disabled="!canAddArticle()"
                class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed"
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
            <table class="w-full text-sm table-fixed">
              <colgroup>
                <col class="w-12" />
                <col />
                <col class="w-28 sm:w-32" />
                <col class="w-20 sm:w-24" />
                <col class="w-28 sm:w-36" />
                <col class="w-12" />
              </colgroup>
              <thead class="bg-gray-50 dark:bg-gray-800/80 text-gray-500 dark:text-gray-400 uppercase text-xs">
                <tr>
                  <th class="text-left px-4 py-3">#</th>
                  <th class="text-left px-4 py-3">Produit / Désignation</th>
                  <th class="text-right px-4 py-3">{{ qtyLabel }}</th>
                  <th class="text-left px-4 py-3">Unité</th>
                  <th class="text-right px-4 py-3">Coût unit.</th>
                  <th class="px-2 py-3"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr
                  v-for="(line, idx) in lines"
                  :key="line.key"
                  class="group hover:bg-gray-50/50 dark:hover:bg-gray-700/30"
                >
                  <td class="px-4 py-3 text-gray-400 dark:text-gray-500 text-xs font-medium align-top pt-4">
                    {{ idx + 1 }}
                  </td>

                  <!-- Product / Designation -->
                  <td class="px-4 py-3 relative">
                    <div class="flex gap-1.5">
                      <input
                        v-model="productSearches[line.key]"
                        :placeholder="line.designation || 'Chercher un produit...'"
                        class="flex-1 min-w-0 px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-violet-400 transition"
                        @input="openProductDropdown(line.key)"
                        @blur="delayedCloseProduct(line.key)"
                      />
                      <button
                        type="button"
                        class="shrink-0 px-2.5 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-400 dark:text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-600 hover:text-violet-600 dark:hover:text-violet-400 transition"
                        title="Rechercher"
                        @click="openProductDropdown(line.key)"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <circle cx="11" cy="11" r="8" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35" />
                        </svg>
                      </button>
                    </div>
                    <div
                      v-if="line.designation && !productSearches[line.key]"
                      class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate max-w-xs"
                    >
                      {{ line.designation }}
                    </div>
                    <div
                      v-if="showProductDropdown === line.key && filteredProducts(line.key).length"
                      class="absolute z-30 mt-1 left-4 right-4 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-48 overflow-y-auto"
                    >
                      <button
                        v-for="p in filteredProducts(line.key)"
                        :key="p.id"
                        type="button"
                        class="w-full text-left px-4 py-2.5 text-sm hover:bg-violet-50 dark:hover:bg-violet-900/30 transition flex justify-between items-center gap-3"
                        @mousedown.prevent="selectProduct(line, p); closeProductDropdown()"
                      >
                        <span class="truncate">
                          <span class="font-medium dark:text-gray-200">{{ p.p_title }}</span>
                          <span class="text-gray-400 dark:text-gray-500 text-xs ml-1">[{{ p.p_code }}]</span>
                        </span>
                        <span class="text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap"
                          >{{ p.p_purchasePrice }} DH</span
                        >
                      </button>
                    </div>
                  </td>

                  <!-- Quantity -->
                  <td class="px-4 py-3 align-top pt-4">
                    <input
                      v-model.number="line.quantity"
                      type="number"
                      min="0.01"
                      step="0.01"
                      class="w-full px-3 py-2 text-sm text-right rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-violet-400 transition"
                    />
                  </td>

                  <!-- Unit -->
                  <td class="px-4 py-3 align-top pt-4">
                    <input
                      v-model="line.unit"
                      type="text"
                      class="w-full px-3 py-2 text-sm text-center rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-violet-400 transition"
                    />
                  </td>

                  <!-- Unit price -->
                  <td class="px-4 py-3 align-top pt-4">
                    <input
                      v-model.number="line.unit_price"
                      type="number"
                      min="0"
                      step="0.01"
                      class="w-full px-3 py-2 text-sm text-right rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-violet-400 transition"
                      placeholder="0.00"
                    />
                  </td>

                  <!-- Remove -->
                  <td class="px-2 py-3 align-top pt-4 text-center">
                    <button
                      type="button"
                      class="p-1.5 rounded-lg text-gray-300 dark:text-gray-600 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition opacity-0 group-hover:opacity-100"
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
          <div v-else class="px-6 py-12 text-center text-gray-400 dark:text-gray-500 text-sm">
            Aucun article. Recherchez un produit ci-dessus pour commencer.
          </div>
        </div>

        <!-- Adjustment hint -->
        <div
          v-if="isAdjustment"
          class="flex items-start gap-3 px-4 py-3 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-xl text-sm text-indigo-800 dark:text-indigo-300"
        >
          <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          <span
            >Pour un ajustement, la <strong>quantité</strong> représente la <strong>nouvelle valeur cible</strong> du
            stock. La différence (positive ou négative) sera enregistrée automatiquement.</span
          >
        </div>

        <!-- Validation Errors -->
        <div
          v-if="errorMsg"
          class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4"
        >
          <div class="flex items-center gap-2 text-sm text-red-800 dark:text-red-300">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"
              />
            </svg>
            {{ errorMsg }}
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between">
          <button
            type="button"
            class="px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
            @click="router.push(`/stock/documents/${doc.id}`)"
          >
            Annuler
          </button>
          <button
            type="submit"
            :disabled="saving"
            class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ saving ? 'Enregistrement...' : 'Enregistrer les modifications' }}
          </button>
        </div>
      </form>
    </template>

    <!-- Not found -->
    <div v-else class="flex flex-col items-center justify-center py-24 text-gray-400 dark:text-gray-500">
      <svg class="w-16 h-16 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
        />
      </svg>
      <p class="text-lg font-medium">Document introuvable</p>
      <router-link to="/stock/documents" class="text-sm text-violet-600 dark:text-violet-400 hover:underline mt-2">
        Retour à la liste
      </router-link>
    </div>
  </div>
</template>
