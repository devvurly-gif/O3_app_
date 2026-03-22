<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Promotions</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Gérez vos promotions et offres spéciales pour le site eCom</p>
      </div>
      <button
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition"
        @click="openCreate"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
        Nouvelle Promotion
      </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
      <input
        v-model="search"
        type="text"
        placeholder="Rechercher une promotion..."
        class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 w-64"
      />
      <select
        v-model="statusFilter"
        class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="">Tous</option>
        <option value="active">Actives</option>
        <option value="inactive">Inactives / Expirées</option>
      </select>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-900/50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Promotion</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Type</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Valeur</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Période</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Produits</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Statut</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr v-if="loading" v-for="i in 5" :key="i">
            <td colspan="7" class="px-4 py-3"><div class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div></td>
          </tr>
          <tr v-else-if="!promotions.length">
            <td colspan="7" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">Aucune promotion trouvée</td>
          </tr>
          <tr v-for="promo in promotions" :key="promo.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900 dark:text-white">{{ promo.name }}</div>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ promo.slug }}</div>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="promo.type === 'percentage' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300'">
                {{ promo.type === 'percentage' ? 'Pourcentage' : 'Montant fixe' }}
              </span>
            </td>
            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
              {{ promo.type === 'percentage' ? promo.value + '%' : promo.value + ' MAD' }}
            </td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
              <div v-if="promo.starts_at || promo.ends_at">
                <div v-if="promo.starts_at">Du {{ formatDate(promo.starts_at) }}</div>
                <div v-if="promo.ends_at">Au {{ formatDate(promo.ends_at) }}</div>
              </div>
              <span v-else class="text-gray-400">Permanente</span>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 text-xs font-bold">
                {{ promo.products_count }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                :class="isActive(promo) ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'">
                {{ isActive(promo) ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click="openEdit(promo)" class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition" title="Modifier">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                </button>
                <button @click="toggleActive(promo)" class="p-1.5 rounded-lg transition" :class="promo.is_active ? 'text-yellow-600 hover:bg-yellow-50 dark:hover:bg-yellow-900/20' : 'text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20'" :title="promo.is_active ? 'Désactiver' : 'Activer'">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="promo.is_active ? 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636' : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'" /></svg>
                </button>
                <button @click="confirmDelete(promo)" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition" title="Supprimer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="flex items-center justify-between">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ pagination.from }}–{{ pagination.to }} sur {{ pagination.total }}
      </p>
      <div class="flex gap-1">
        <button v-for="p in pagination.last_page" :key="p"
          @click="page = p"
          class="px-3 py-1.5 text-sm rounded-lg transition"
          :class="p === page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'">
          {{ p }}
        </button>
      </div>
    </div>

    <!-- Modal Create/Edit -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showModal = false">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto mx-4">
        <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ editingPromo ? 'Modifier la Promotion' : 'Nouvelle Promotion' }}</h3>
          <button @click="showModal = false" class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
        <form @submit.prevent="save" class="p-5 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom *</label>
              <input v-model="form.name" type="text" required class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="Ex: Soldes d'été -20%">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type *</label>
              <select v-model="form.type" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                <option value="percentage">Pourcentage (%)</option>
                <option value="fixed_amount">Montant fixe (MAD)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valeur *</label>
              <div class="relative">
                <input v-model.number="form.value" type="number" step="0.01" min="0" required class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">{{ form.type === 'percentage' ? '%' : 'MAD' }}</span>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date début</label>
              <input v-model="form.starts_at" type="datetime-local" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date fin</label>
              <input v-model="form.ends_at" type="datetime-local" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Remise max (MAD)</label>
              <input v-model.number="form.max_discount" type="number" step="0.01" min="0" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="Optionnel">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priorité</label>
              <input v-model.number="form.priority" type="number" min="0" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="0">
            </div>
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Texte bannière</label>
              <input v-model="form.banner_text" type="text" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="Ex: Jusqu'à -30% sur tout le catalogue !">
            </div>
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Image bannière (URL)</label>
              <input v-model="form.banner_image" type="text" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="https://...">
            </div>
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
              <textarea v-model="form.description" rows="2" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="Description optionnelle..."></textarea>
            </div>
          </div>

          <!-- Products selector -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Produits en promotion</label>
            <div class="relative mb-2">
              <input
                v-model="productSearch"
                type="text"
                placeholder="Rechercher un produit à ajouter..."
                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500"
                @input="searchProducts"
              />
              <div v-if="productResults.length" class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                <button
                  v-for="p in productResults"
                  :key="p.id"
                  type="button"
                  class="w-full px-3 py-2 text-left text-sm hover:bg-blue-50 dark:hover:bg-blue-900/20 flex items-center justify-between"
                  @click="addProduct(p)"
                >
                  <span class="text-gray-900 dark:text-white">{{ p.p_title }}</span>
                  <span class="text-xs text-gray-400 font-mono">{{ p.p_code }} — {{ Number(p.p_salePrice).toFixed(2) }} MAD</span>
                </button>
              </div>
            </div>
            <div v-if="form.product_ids.length" class="space-y-1.5">
              <div v-for="(item, idx) in form.product_ids" :key="item.id" class="flex items-center gap-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2">
                <span class="flex-1 text-sm text-gray-900 dark:text-white">{{ item.title }}</span>
                <div class="flex items-center gap-2">
                  <input
                    v-model.number="item.promo_price"
                    type="number"
                    step="0.01"
                    min="0"
                    class="w-28 px-2 py-1 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    placeholder="Prix forcé"
                  />
                  <button type="button" @click="form.product_ids.splice(idx, 1)" class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>
                </div>
              </div>
            </div>
            <p v-else class="text-xs text-gray-400 dark:text-gray-500 mt-1">Aucun produit sélectionné — la promotion ne s'appliquera à rien</p>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <button type="button" @click="showModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition">Annuler</button>
            <button type="submit" :disabled="saving" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition disabled:opacity-50">
              {{ saving ? 'Enregistrement...' : (editingPromo ? 'Mettre à jour' : 'Créer') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete confirmation -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showDeleteModal = false">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-sm mx-4 p-6 text-center">
        <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
        </div>
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Supprimer la promotion ?</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">{{ deletingPromo?.name }}</p>
        <div class="flex gap-3 justify-center">
          <button @click="showDeleteModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">Annuler</button>
          <button @click="deletePromo" :disabled="saving" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition disabled:opacity-50">Supprimer</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import http from '@/services/http'

interface PromoProduct {
  id: number
  title: string
  promo_price: number | null
}

interface PromotionForm {
  name: string
  type: 'percentage' | 'fixed_amount'
  value: number
  description: string
  banner_image: string
  banner_text: string
  starts_at: string
  ends_at: string
  max_discount: number | null
  priority: number
  product_ids: PromoProduct[]
}

const promotions = ref<any[]>([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const statusFilter = ref('')
const page = ref(1)
const pagination = ref({ from: 0, to: 0, total: 0, last_page: 1 })
const showModal = ref(false)
const showDeleteModal = ref(false)
const editingPromo = ref<any>(null)
const deletingPromo = ref<any>(null)
const productSearch = ref('')
const productResults = ref<any[]>([])
let searchTimeout: ReturnType<typeof setTimeout>

const emptyForm = (): PromotionForm => ({
  name: '',
  type: 'percentage',
  value: 0,
  description: '',
  banner_image: '',
  banner_text: '',
  starts_at: '',
  ends_at: '',
  max_discount: null,
  priority: 0,
  product_ids: [],
})
const form = ref<PromotionForm>(emptyForm())

async function fetchPromotions() {
  loading.value = true
  try {
    const { data } = await http.get('/promotions', {
      params: { search: search.value || undefined, status: statusFilter.value || undefined, page: page.value },
    })
    promotions.value = data.data
    pagination.value = { from: data.from, to: data.to, total: data.total, last_page: data.last_page }
  } finally {
    loading.value = false
  }
}

function isActive(promo: any): boolean {
  if (!promo.is_active) return false
  if (promo.starts_at && new Date(promo.starts_at) > new Date()) return false
  if (promo.ends_at && new Date(promo.ends_at) < new Date()) return false
  return true
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function openCreate() {
  editingPromo.value = null
  form.value = emptyForm()
  showModal.value = true
}

async function openEdit(promo: any) {
  editingPromo.value = promo
  const { data } = await http.get(`/promotions/${promo.id}`)
  form.value = {
    name: data.name,
    type: data.type,
    value: Number(data.value),
    description: data.description || '',
    banner_image: data.banner_image || '',
    banner_text: data.banner_text || '',
    starts_at: data.starts_at ? data.starts_at.slice(0, 16) : '',
    ends_at: data.ends_at ? data.ends_at.slice(0, 16) : '',
    max_discount: data.max_discount ? Number(data.max_discount) : null,
    priority: data.priority || 0,
    product_ids: (data.products || []).map((p: any) => ({
      id: p.id,
      title: p.p_title,
      promo_price: p.pivot?.promo_price ? Number(p.pivot.promo_price) : null,
    })),
  }
  showModal.value = true
}

async function save() {
  saving.value = true
  try {
    const payload = {
      ...form.value,
      starts_at: form.value.starts_at || null,
      ends_at: form.value.ends_at || null,
      product_ids: form.value.product_ids.map(p => ({
        id: p.id,
        promo_price: p.promo_price || null,
      })),
    }
    if (editingPromo.value) {
      await http.put(`/promotions/${editingPromo.value.id}`, payload)
    } else {
      await http.post('/promotions', payload)
    }
    showModal.value = false
    fetchPromotions()
  } finally {
    saving.value = false
  }
}

async function toggleActive(promo: any) {
  await http.patch(`/promotions/${promo.id}`, { is_active: !promo.is_active })
  fetchPromotions()
}

function confirmDelete(promo: any) {
  deletingPromo.value = promo
  showDeleteModal.value = true
}

async function deletePromo() {
  saving.value = true
  try {
    await http.delete(`/promotions/${deletingPromo.value.id}`)
    showDeleteModal.value = false
    fetchPromotions()
  } finally {
    saving.value = false
  }
}

function searchProducts() {
  clearTimeout(searchTimeout)
  if (productSearch.value.length < 2) {
    productResults.value = []
    return
  }
  searchTimeout = setTimeout(async () => {
    const { data } = await http.get('/products', {
      params: { search: productSearch.value, per_page: 10 },
    })
    const existingIds = form.value.product_ids.map(p => p.id)
    productResults.value = (data.data || data).filter((p: any) => !existingIds.includes(p.id))
  }, 300)
}

function addProduct(p: any) {
  form.value.product_ids.push({
    id: p.id,
    title: p.p_title,
    promo_price: null,
  })
  productSearch.value = ''
  productResults.value = []
}

watch([search, statusFilter], () => { page.value = 1; fetchPromotions() })
watch(page, fetchPromotions)
onMounted(fetchPromotions)
</script>
