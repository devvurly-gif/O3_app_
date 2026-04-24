<template>
  <div class="flex h-full">
    <!-- LEFT: Products panel (60%) -->
    <div class="w-[60%] flex flex-col border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
      <!-- Category tabs -->
      <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 dark:border-gray-700 overflow-x-auto shrink-0">
        <button
          class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium transition"
          :class="!posStore.selectedCategoryId ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
          @click="selectCategory(null)"
        >
          Tout
        </button>
        <button
          v-for="cat in categories"
          :key="cat.id"
          class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium transition"
          :class="posStore.selectedCategoryId === cat.id ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
          @click="selectCategory(cat.id)"
        >
          {{ cat.ctg_title }}
        </button>
      </div>

      <!-- Search bar -->
      <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 shrink-0">
        <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            v-model="posStore.searchQuery"
            type="text"
            placeholder="Rechercher par nom, SKU, code-barres..."
            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @input="debouncedSearch"
          />
        </div>
      </div>

      <!-- Products grid -->
      <div class="flex-1 overflow-y-auto p-4">
        <div v-if="posStore.loadingProducts" class="flex items-center justify-center h-full text-gray-400 dark:text-gray-500">
          Chargement...
        </div>
        <div v-else-if="!posStore.products.length" class="flex items-center justify-center h-full text-gray-400 dark:text-gray-500">
          Aucun produit trouvé
        </div>
        <div v-else class="grid grid-cols-3 xl:grid-cols-4 gap-3">
          <button
            v-for="product in posStore.products"
            :key="product.id"
            class="flex flex-col bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-3 hover:border-blue-400 hover:shadow-md transition-all text-left group"
            @click="addToCart(product)"
          >
            <div class="w-full aspect-square rounded-lg bg-gray-200 mb-2 overflow-hidden flex items-center justify-center">
              <img
                v-if="product.primary_image?.url"
                :src="product.primary_image.url"
                :alt="product.p_title"
                class="w-full h-full object-cover"
                @error="($event: Event) => (($event.target as HTMLImageElement).style.display = 'none')"
              />
              <svg v-else class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0v10l-8 4m0-14L4 17m8 4V10" />
              </svg>
            </div>
            <p class="text-xs font-medium text-gray-900 dark:text-white line-clamp-2 leading-tight mb-1">{{ product.p_title }}</p>
            <div class="mt-auto flex items-center justify-between">
              <span class="text-sm font-bold text-blue-600">{{ formatPrice(product.p_salePrice) }}</span>
              <span class="text-[10px] px-1.5 py-0.5 rounded-full" :class="product.stock > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'">
                {{ product.stock > 0 ? product.stock : 'Rupture' }}
              </span>
            </div>
          </button>
        </div>
      </div>
    </div>

    <!-- RIGHT: Cart panel (40%) -->
    <div class="w-[40%] flex flex-col bg-white dark:bg-gray-800">
      <!-- Cart header -->
      <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between shrink-0">
        <h2 class="text-sm font-bold text-gray-900 dark:text-white">
          Panier
          <span v-if="posStore.cartItemCount" class="ml-1 text-xs bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full">
            {{ posStore.cartItemCount }}
          </span>
        </h2>
        <div class="flex items-center gap-2 relative">
          <!-- Held tickets dropdown -->
          <div class="relative">
            <button
              type="button"
              class="flex items-center gap-1 text-xs px-2 py-1 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/40 dark:text-indigo-300 transition"
              @click.stop="showHeldDropdown = !showHeldDropdown"
              :title="posStore.draftTickets.length ? 'Tickets en attente' : 'Aucun ticket en attente'"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
              <span>Tickets</span>
              <span
                v-if="posStore.draftTickets.length"
                class="ml-0.5 text-[10px] bg-indigo-600 text-white px-1.5 py-0.5 rounded-full"
              >
                {{ posStore.draftTickets.length }}
              </span>
            </button>

            <div
              v-if="showHeldDropdown"
              class="absolute right-0 top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg z-30 w-64 overflow-hidden"
              @click.stop
            >
              <button
                type="button"
                class="w-full px-3 py-2 text-left text-xs font-medium text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed"
                :disabled="!posStore.cart.length"
                @click="parkCurrent"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Mettre en attente + nouveau ticket
              </button>

              <div v-if="!posStore.draftTickets.length" class="px-3 py-3 text-xs text-gray-400 text-center">
                Aucun ticket en attente
              </div>
              <div v-else class="max-h-60 overflow-y-auto">
                <div
                  v-for="draft in posStore.draftTickets"
                  :key="draft.id"
                  class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/40 border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                >
                  <button
                    type="button"
                    class="flex-1 min-w-0 text-left"
                    @click="switchToDraft(draft.id)"
                  >
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ draft.label }}</p>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400">
                      {{ draft.cart.length }} article{{ draft.cart.length > 1 ? 's' : '' }} ·
                      {{ formatPrice(draftTotalTtc(draft)) }}
                    </p>
                  </button>
                  <button
                    type="button"
                    class="p-1 text-gray-400 hover:text-red-500 transition"
                    title="Supprimer ce ticket"
                    @click="discardDraft(draft.id)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <router-link to="/pos/tickets" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 transition">
            Historique
          </router-link>
          <button
            v-if="posStore.cart.length"
            class="text-xs text-red-500 hover:text-red-700 transition"
            @click="posStore.clearCart()"
          >
            Vider
          </button>
        </div>
      </div>

      <!-- Customer selector -->
      <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 shrink-0">
        <!-- Selected customer display -->
        <div v-if="selectedCustomer" class="flex items-center gap-3 p-2.5 rounded-xl bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ selectedCustomer.tp_title }}</p>
            <div class="flex items-center gap-2 mt-0.5">
              <span v-if="selectedCustomer.tp_phone" class="text-xs text-gray-500 dark:text-gray-400">{{ selectedCustomer.tp_phone }}</span>
              <span
                v-if="selectedCustomer.type_compte === 'en_compte'"
                class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400 font-medium"
              >
                En compte
              </span>
            </div>
            <p v-if="selectedCustomer.type_compte === 'en_compte'" class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">
              Encours: {{ formatPrice(selectedCustomer.encours_actuel ?? 0) }}
              <span v-if="Number(selectedCustomer.seuil_credit) > 0"> / Plafond: {{ formatPrice(selectedCustomer.seuil_credit) }}</span>
            </p>
          </div>
          <button class="p-1 text-gray-400 hover:text-red-500 transition" @click="clearCustomer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Customer search -->
        <div v-else class="relative">
          <div class="flex gap-2">
            <div class="relative flex-1">
              <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              <input
                v-model="customerSearch"
                type="text"
                placeholder="Client (optionnel)..."
                class="w-full pl-8 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                @input="debouncedCustomerSearch"
                @focus="showCustomerDropdown = true"
              />
            </div>
            <button
              class="px-3 py-2 rounded-lg text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition whitespace-nowrap"
              @click="showNewCustomerModal = true"
            >
              + Nouveau
            </button>
          </div>

          <!-- Dropdown results -->
          <div
            v-if="showCustomerDropdown && customerResults.length"
            class="absolute left-0 right-0 top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg z-20 max-h-48 overflow-y-auto"
          >
            <button
              v-for="cust in customerResults"
              :key="cust.id"
              class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition text-left"
              @click="selectCustomer(cust)"
            >
              <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-900 dark:text-white truncate">{{ cust.tp_title }}</p>
                <p v-if="cust.tp_phone" class="text-xs text-gray-400">{{ cust.tp_phone }}</p>
              </div>
              <span
                v-if="cust.type_compte === 'en_compte'"
                class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400 font-medium shrink-0"
              >
                En compte
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- Cart items -->
      <div class="flex-1 overflow-y-auto px-5 py-3 space-y-2">
        <div v-if="!posStore.cart.length" class="flex items-center justify-center h-full text-gray-300 text-sm">
          Le panier est vide
        </div>
        <div
          v-for="item in posStore.cart"
          :key="item.product_id"
          class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-700"
        >
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ item.designation }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatPrice(item.unit_price) }} / {{ item.unit }}</p>
          </div>
          <div class="flex items-center gap-1.5">
            <button
              class="w-7 h-7 rounded-lg bg-gray-200 hover:bg-gray-300 flex items-center justify-center transition"
              @click="posStore.updateCartItemQty(item.product_id, item.quantity - 1)"
            >
              <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M5 12h14" />
              </svg>
            </button>
            <span class="w-8 text-center text-sm font-semibold">{{ item.quantity }}</span>
            <button
              class="w-7 h-7 rounded-lg bg-gray-200 hover:bg-gray-300 flex items-center justify-center transition"
              @click="posStore.updateCartItemQty(item.product_id, item.quantity + 1)"
            >
              <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M12 5v14m7-7H5" />
              </svg>
            </button>
          </div>
          <p class="text-sm font-semibold text-gray-900 dark:text-white w-20 text-right">
            {{ formatPrice(item.quantity * item.unit_price * (1 - item.discount_percent / 100)) }}
          </p>
          <button
            class="p-1 text-gray-400 dark:text-gray-500 hover:text-red-500 transition"
            @click="posStore.removeFromCart(item.product_id)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Totals + Payment -->
      <div class="border-t border-gray-200 dark:border-gray-700 px-5 py-4 space-y-3 shrink-0">
        <div class="flex justify-between text-sm text-gray-500 dark:text-gray-400">
          <span>Sous-total HT</span>
          <span>{{ formatPrice(posStore.cartSubtotal) }}</span>
        </div>
        <div class="flex justify-between text-sm text-gray-500 dark:text-gray-400">
          <span>TVA</span>
          <span>{{ formatPrice(posStore.cartTax) }}</span>
        </div>
        <div class="flex justify-between text-lg font-bold text-gray-900 dark:text-white">
          <span>Total TTC</span>
          <span>{{ formatPrice(posStore.cartTotal) }}</span>
        </div>

        <div class="grid grid-cols-3 gap-2 pt-2">
          <button
            :disabled="!posStore.cart.length"
            class="py-3 rounded-xl font-semibold text-sm transition disabled:opacity-40 disabled:cursor-not-allowed bg-green-600 hover:bg-green-700 text-white"
            @click="payWith('cash')"
          >
            Espèces
          </button>
          <button
            :disabled="!posStore.cart.length"
            class="py-3 rounded-xl font-semibold text-sm transition disabled:opacity-40 disabled:cursor-not-allowed bg-blue-600 hover:bg-blue-700 text-white"
            @click="payWith('card')"
          >
            Carte
          </button>
          <button
            :disabled="!posStore.cart.length"
            class="py-3 rounded-xl font-semibold text-sm transition disabled:opacity-40 disabled:cursor-not-allowed bg-amber-500 hover:bg-amber-600 text-white"
            @click="payWithCredit"
          >
            En Compte
          </button>
        </div>

        <button
          class="w-full py-2.5 rounded-xl text-sm font-medium bg-amber-100 text-amber-700 hover:bg-amber-200 transition"
          @click="router.push('/pos/close')"
        >
          Fermer la session
        </button>
      </div>
    </div>

    <!-- Checkout Modal -->
    <PosCheckout
      v-if="showCheckout"
      :method="checkoutMethod"
      :total="posStore.cartTotal"
      :customer="selectedCustomer"
      @close="showCheckout = false"
      @confirm="confirmPayment"
    />

    <!-- New Customer Modal -->
    <div v-if="showNewCustomerModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showNewCustomerModal = false">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm p-6">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Nouveau Client</h3>

        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom *</label>
            <input
              v-model="newCustomer.tp_title"
              type="text"
              class="w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Nom du client"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Téléphone</label>
            <input
              v-model="newCustomer.tp_phone"
              type="text"
              class="w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="06 xx xx xx xx"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
            <input
              v-model="newCustomer.tp_email"
              type="email"
              class="w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="email@exemple.com"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type de compte</label>
            <div class="flex gap-2">
              <button
                class="flex-1 py-2 rounded-lg text-sm font-medium transition"
                :class="newCustomer.type_compte === 'normal' ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'"
                @click="newCustomer.type_compte = 'normal'"
              >
                Normal
              </button>
              <button
                class="flex-1 py-2 rounded-lg text-sm font-medium transition"
                :class="newCustomer.type_compte === 'en_compte' ? 'bg-amber-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'"
                @click="newCustomer.type_compte = 'en_compte'"
              >
                En Compte
              </button>
            </div>
          </div>
          <div v-if="newCustomer.type_compte === 'en_compte'">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Plafond crédit (MAD)</label>
            <input
              v-model.number="newCustomer.seuil_credit"
              type="number"
              min="0"
              step="100"
              class="w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="0 = illimité"
            />
          </div>
        </div>

        <div class="flex gap-3 mt-6">
          <button
            class="flex-1 py-2.5 rounded-xl text-sm font-medium text-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
            @click="showNewCustomerModal = false"
          >
            Annuler
          </button>
          <button
            :disabled="!newCustomer.tp_title.trim() || creatingCustomer"
            class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition disabled:opacity-40 disabled:cursor-not-allowed"
            @click="createCustomer"
          >
            {{ creatingCustomer ? 'Création...' : 'Créer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePosStore, type DraftTicket } from '@/stores/pos/posStore'
import { useBarcodeScanner } from '@/composables/useBarcodeScanner'
import http from '@/services/http'
import PosCheckout from './PosCheckout.vue'

interface PosCustomer {
  id: number
  tp_title: string
  tp_phone: string | null
  tp_email: string | null
  type_compte: 'normal' | 'en_compte'
  encours_actuel: number
  seuil_credit: number
  price_list_id: number | null
}

const router = useRouter()
const posStore = usePosStore()

const categories = ref<{ id: number; ctg_title: string }[]>([])
const showCheckout = ref(false)
const checkoutMethod = ref('cash')
const showHeldDropdown = ref(false)
let searchTimeout: ReturnType<typeof setTimeout> | null = null

// Customer state
const selectedCustomer = ref<PosCustomer | null>(null)
const customerSearch = ref('')
const customerResults = ref<PosCustomer[]>([])
const showCustomerDropdown = ref(false)
const showNewCustomerModal = ref(false)
const creatingCustomer = ref(false)
let customerSearchTimeout: ReturnType<typeof setTimeout> | null = null

const newCustomer = ref({
  tp_title: '',
  tp_phone: '',
  tp_email: '',
  type_compte: 'normal' as 'normal' | 'en_compte',
  seuil_credit: 0,
})

const isEnCompteCustomer = computed(() => selectedCustomer.value?.type_compte === 'en_compte')

function formatPrice(val: number): string {
  const num = Number(val)
  if (isNaN(num)) return '0.00 MAD'
  return num.toFixed(2) + ' MAD'
}

function selectCategory(id: number | null) {
  posStore.selectedCategoryId = id
  posStore.fetchProducts()
}

function debouncedSearch() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => posStore.fetchProducts(), 300)
}

// ── Customer methods ────────────────────────────────────────────

function debouncedCustomerSearch() {
  if (customerSearchTimeout) clearTimeout(customerSearchTimeout)
  customerSearchTimeout = setTimeout(() => searchCustomers(), 300)
}

async function searchCustomers() {
  if (!customerSearch.value.trim()) {
    customerResults.value = []
    return
  }
  try {
    const { data } = await http.get<PosCustomer[]>('/pos/customers', {
      params: { search: customerSearch.value },
    })
    customerResults.value = Array.isArray(data) ? data : []
    showCustomerDropdown.value = true
  } catch {
    customerResults.value = []
  }
}

function selectCustomer(customer: PosCustomer) {
  selectedCustomer.value = customer
  posStore.activeCustomer = customer
  customerSearch.value = ''
  customerResults.value = []
  showCustomerDropdown.value = false
  // Re-price existing cart lines using the new customer's price list.
  posStore.refreshPricesForCustomer(customer.id)
}

function clearCustomer() {
  selectedCustomer.value = null
  posStore.activeCustomer = null
  // Reset prices to the base (no customer → default/fallback).
  posStore.refreshPricesForCustomer(null)
}

// ── Held tickets ────────────────────────────────────────────────

function draftTotalTtc(draft: DraftTicket): number {
  return draft.cart.reduce((sum, item) => {
    const ht = item.quantity * item.unit_price * (1 - item.discount_percent / 100)
    return sum + ht * (1 + item.tax_percent / 100)
  }, 0)
}

function parkCurrent() {
  if (!posStore.cart.length) return
  posStore.parkCurrentTicket(selectedCustomer.value)
  selectedCustomer.value = null
  showHeldDropdown.value = false
}

function switchToDraft(id: string) {
  const draft = posStore.restoreDraftTicket(id)
  if (draft) {
    selectedCustomer.value = draft.customer as PosCustomer | null
  }
  showHeldDropdown.value = false
}

function discardDraft(id: string) {
  if (!confirm('Supprimer ce ticket en attente ?')) return
  posStore.discardDraftTicket(id)
}

async function createCustomer() {
  if (!newCustomer.value.tp_title.trim()) return
  creatingCustomer.value = true
  try {
    const { data } = await http.post<PosCustomer>('/pos/customers', {
      tp_title: newCustomer.value.tp_title,
      tp_phone: newCustomer.value.tp_phone || null,
      tp_email: newCustomer.value.tp_email || null,
      type_compte: newCustomer.value.type_compte,
      seuil_credit: newCustomer.value.type_compte === 'en_compte' ? newCustomer.value.seuil_credit : 0,
    })
    selectedCustomer.value = data
    showNewCustomerModal.value = false
    // Reset form
    newCustomer.value = { tp_title: '', tp_phone: '', tp_email: '', type_compte: 'normal', seuil_credit: 0 }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message ?? 'Erreur lors de la création du client')
  } finally {
    creatingCustomer.value = false
  }
}

// ── Payment ─────────────────────────────────────────────────────

function payWith(method: string) {
  checkoutMethod.value = method
  showCheckout.value = true
}

function payWithCredit() {
  if (!selectedCustomer.value) {
    alert('Veuillez sélectionner un client avant de payer en compte.')
    return
  }
  if (selectedCustomer.value.type_compte !== 'en_compte') {
    alert('Ce client n\'est pas un client "en compte". Seuls les clients en compte peuvent payer à crédit.')
    return
  }
  payWith('credit')
}

async function confirmPayment(payments: { amount: number; method: string }[]) {
  try {
    await posStore.checkout(payments, selectedCustomer.value?.id)
    showCheckout.value = false
    selectedCustomer.value = null
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message ?? 'Erreur lors du paiement')
  }
}

// Barcode scanner
/**
 * Wrapper around posStore.addToCart that re-prices the line using the
 * currently selected customer's price list (if any). Without this,
 * lines added after a customer is picked would keep the product base
 * price.
 */
function addToCart(product: Parameters<typeof posStore.addToCart>[0]) {
  posStore.addToCart(product)
  if (selectedCustomer.value) {
    posStore.refreshPricesForCustomer(selectedCustomer.value.id)
  }
}

useBarcodeScanner(async (code: string) => {
  posStore.searchQuery = code
  await posStore.fetchProducts()
  // Auto-add if exactly one match
  if (posStore.products.length === 1) {
    addToCart(posStore.products[0])
    posStore.searchQuery = ''
  }
})

// Close customer dropdown on outside click
function handleDocumentClick(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (!target.closest('.relative')) {
    showCustomerDropdown.value = false
    showHeldDropdown.value = false
  }
}

onMounted(async () => {
  if (!posStore.hasOpenSession) {
    await posStore.fetchCurrentSession()
    if (!posStore.hasOpenSession) {
      router.replace('/pos')
      return
    }
  }

  // Fetch categories
  const { data } = await http.get<{ id: number; ctg_title: string }[]>('/categories?per_page=100')
  categories.value = Array.isArray(data) ? data : (data as unknown as { data: typeof categories.value }).data ?? []

  await posStore.fetchProducts()

  document.addEventListener('click', handleDocumentClick)
})
</script>
