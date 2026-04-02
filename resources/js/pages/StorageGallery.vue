<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Galerie Images Produits</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
          {{ images.length }} image(s) dans storage/products
        </p>
      </div>
      <div class="flex items-center gap-3">
        <input
          v-model="search"
          type="text"
          placeholder="Rechercher..."
          class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64"
        />
        <button
          @click="viewMode = viewMode === 'grid' ? 'list' : 'grid'"
          class="p-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
          :title="viewMode === 'grid' ? 'Vue liste' : 'Vue grille'"
        >
          <svg v-if="viewMode === 'grid'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
          <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Selection toolbar -->
    <div
      v-if="selected.size > 0"
      class="flex items-center gap-4 px-4 py-3 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-xl"
    >
      <span class="text-sm font-medium text-blue-700 dark:text-blue-300">
        {{ selected.size }} image(s) sélectionnée(s)
      </span>
      <div class="flex-1 flex items-center gap-3">
        <select
          v-model="selectedProductId"
          class="px-3 py-1.5 text-sm rounded-lg border border-blue-300 dark:border-blue-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 min-w-[250px]"
        >
          <option :value="null" disabled>-- Choisir un produit --</option>
          <option v-for="p in products" :key="p.id" :value="p.id">
            {{ p.p_code }} — {{ p.p_title }}
          </option>
        </select>
        <button
          :disabled="!selectedProductId || assigning"
          @click="assignToProduct"
          class="flex items-center gap-2 px-4 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.686-5.749a4.5 4.5 0 00-6.364-6.364L4.5 8.25" />
          </svg>
          {{ assigning ? 'Affectation...' : 'Affecter au produit' }}
        </button>
      </div>
      <button
        @click="selected.clear()"
        class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
      >
        Désélectionner tout
      </button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
      <div v-for="i in 12" :key="i" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden animate-pulse">
        <div class="aspect-square bg-gray-200 dark:bg-gray-700"></div>
        <div class="p-2"><div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div></div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!filtered.length" class="text-center py-16 text-gray-400 dark:text-gray-500">
      <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a1.5 1.5 0 001.5-1.5V5.25a1.5 1.5 0 00-1.5-1.5H3.75a1.5 1.5 0 00-1.5 1.5V19.5a1.5 1.5 0 001.5 1.5z" />
      </svg>
      <p>Aucune image trouvée</p>
    </div>

    <!-- Grid view -->
    <div v-else-if="viewMode === 'grid'" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
      <div
        v-for="img in filtered"
        :key="img.name"
        class="group bg-white dark:bg-gray-800 rounded-xl border-2 overflow-hidden hover:shadow-lg transition cursor-pointer"
        :class="selected.has(img.name) ? 'border-blue-500 ring-2 ring-blue-200 dark:ring-blue-800' : 'border-gray-200 dark:border-gray-700'"
        @click="toggleSelect(img)"
      >
        <div class="relative aspect-square bg-gray-100 dark:bg-gray-900 overflow-hidden">
          <img
            :src="img.url"
            :alt="img.name"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            loading="lazy"
          />
          <!-- Selection checkbox -->
          <div
            class="absolute top-2 left-2 w-6 h-6 rounded-md border-2 flex items-center justify-center transition"
            :class="selected.has(img.name) ? 'bg-blue-600 border-blue-600' : 'bg-white/80 border-gray-300 group-hover:border-blue-400'"
          >
            <svg v-if="selected.has(img.name)" class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <!-- Preview button -->
          <button
            class="absolute top-2 right-2 w-7 h-7 flex items-center justify-center bg-black/50 hover:bg-black/70 text-white rounded-full opacity-0 group-hover:opacity-100 transition"
            @click.stop="openPreview(img)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
          </button>
        </div>
        <div class="p-2">
          <p class="text-xs text-gray-600 dark:text-gray-400 truncate" :title="img.name">{{ img.name }}</p>
          <p class="text-[10px] text-gray-400 dark:text-gray-500">{{ formatSize(img.size) }}</p>
        </div>
      </div>
    </div>

    <!-- List view -->
    <div v-else class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 text-xs uppercase">
          <tr>
            <th class="px-4 py-3 text-left w-10">
              <input
                type="checkbox"
                :checked="allFilteredSelected"
                :indeterminate="someFilteredSelected && !allFilteredSelected"
                @change="toggleSelectAll"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
            </th>
            <th class="px-4 py-3 text-left">Image</th>
            <th class="px-4 py-3 text-left">Nom du fichier</th>
            <th class="px-4 py-3 text-left">Taille</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr
            v-for="img in filtered"
            :key="img.name"
            class="hover:bg-gray-50 dark:hover:bg-gray-700/30 cursor-pointer transition"
            :class="selected.has(img.name) ? 'bg-blue-50 dark:bg-blue-900/20' : ''"
            @click="toggleSelect(img)"
          >
            <td class="px-4 py-2" @click.stop>
              <input
                type="checkbox"
                :checked="selected.has(img.name)"
                @change="toggleSelect(img)"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
            </td>
            <td class="px-4 py-2">
              <img :src="img.url" :alt="img.name" class="w-10 h-10 rounded object-cover" loading="lazy" />
            </td>
            <td class="px-4 py-2 text-gray-700 dark:text-gray-300 font-mono text-xs">{{ img.name }}</td>
            <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ formatSize(img.size) }}</td>
            <td class="px-4 py-2 text-right">
              <button
                @click.stop="openPreview(img)"
                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mr-3"
                title="Aperçu"
              >
                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
              </button>
              <button
                @click.stop="copyUrl(img.url)"
                class="text-blue-600 hover:text-blue-800 dark:text-blue-400 text-xs"
              >
                Copier URL
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Lightbox preview -->
    <Teleport to="body">
      <div
        v-if="preview"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm"
        @click.self="preview = null"
      >
        <div class="relative max-w-4xl max-h-[90vh] mx-4">
          <img :src="preview.url" :alt="preview.name" class="max-w-full max-h-[85vh] rounded-lg shadow-2xl object-contain" />
          <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-4 rounded-b-lg">
            <p class="text-white text-sm font-mono">{{ preview.name }}</p>
            <p class="text-gray-300 text-xs">{{ formatSize(preview.size) }}</p>
          </div>
          <button
            class="absolute top-2 right-2 w-8 h-8 flex items-center justify-center bg-black/50 hover:bg-black/70 text-white rounded-full transition"
            @click="preview = null"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <button
            class="absolute top-2 left-2 px-3 py-1.5 bg-black/50 hover:bg-black/70 text-white text-xs rounded-full transition"
            @click.stop="copyUrl(preview.url)"
          >
            Copier URL
          </button>
        </div>
      </div>
    </Teleport>

    <!-- Success toast -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="successMsg"
          class="fixed bottom-6 right-6 z-50 flex items-center gap-2 px-4 py-3 bg-green-600 text-white text-sm font-medium rounded-lg shadow-lg"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          {{ successMsg }}
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import http from '@/services/http'
import type { Product } from '@/types'

interface StorageImage {
  name: string
  url: string
  size: number
}

const images = ref<StorageImage[]>([])
const products = ref<Product[]>([])
const loading = ref(true)
const search = ref('')
const viewMode = ref<'grid' | 'list'>('grid')
const preview = ref<StorageImage | null>(null)
const selected = reactive(new Set<string>())
const selectedProductId = ref<number | null>(null)
const assigning = ref(false)
const successMsg = ref('')

const filtered = computed(() => {
  if (!search.value) return images.value
  const q = search.value.toLowerCase()
  return images.value.filter(img => img.name.toLowerCase().includes(q))
})

const allFilteredSelected = computed(() =>
  filtered.value.length > 0 && filtered.value.every(img => selected.has(img.name))
)
const someFilteredSelected = computed(() =>
  filtered.value.some(img => selected.has(img.name))
)

async function fetchImages() {
  loading.value = true
  try {
    const { data } = await http.get('/storage/products')
    images.value = data
  } finally {
    loading.value = false
  }
}

async function fetchProducts() {
  const { data } = await http.get('/products', { params: { per_page: 999 } })
  products.value = data.data ?? data
}

function toggleSelect(img: StorageImage) {
  if (selected.has(img.name)) {
    selected.delete(img.name)
  } else {
    selected.add(img.name)
  }
}

function toggleSelectAll() {
  if (allFilteredSelected.value) {
    filtered.value.forEach(img => selected.delete(img.name))
  } else {
    filtered.value.forEach(img => selected.add(img.name))
  }
}

async function assignToProduct() {
  if (!selectedProductId.value || selected.size === 0) return

  assigning.value = true
  try {
    const { data } = await http.post('/storage/products/assign', {
      product_id: selectedProductId.value,
      files: Array.from(selected),
    })

    const product = products.value.find(p => p.id === selectedProductId.value)
    const productName = product ? product.p_title : `#${selectedProductId.value}`

    successMsg.value = `${data.images.length} image(s) affectée(s) à "${productName}"`
    selected.clear()
    selectedProductId.value = null

    setTimeout(() => { successMsg.value = '' }, 4000)
  } finally {
    assigning.value = false
  }
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function openPreview(img: StorageImage) {
  preview.value = img
}

async function copyUrl(url: string) {
  try {
    await navigator.clipboard.writeText(url)
  } catch {
    const ta = document.createElement('textarea')
    ta.value = url
    document.body.appendChild(ta)
    ta.select()
    document.execCommand('copy')
    document.body.removeChild(ta)
  }
}

onMounted(() => {
  fetchImages()
  fetchProducts()
})
</script>
