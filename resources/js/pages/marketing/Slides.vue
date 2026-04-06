<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Slides & Bannières</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Gérez les carrousels et bannières du site eCom</p>
      </div>
      <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
        Nouveau Slide
      </button>
    </div>

    <!-- Position tabs -->
    <div class="flex gap-2">
      <button v-for="pos in positions" :key="pos.value"
        @click="positionFilter = pos.value"
        class="px-4 py-2 text-sm font-medium rounded-lg transition"
        :class="positionFilter === pos.value ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
      >
        {{ pos.label }}
      </button>
    </div>

    <!-- Slides grid -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="i in 3" :key="i" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden animate-pulse">
        <div class="h-40 bg-gray-200 dark:bg-gray-700"></div>
        <div class="p-4 space-y-2">
          <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
          <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
        </div>
      </div>
    </div>

    <div v-else-if="!slides.length" class="text-center py-12 text-gray-400 dark:text-gray-500">
      <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a1.5 1.5 0 001.5-1.5V5.25a1.5 1.5 0 00-1.5-1.5H3.75a1.5 1.5 0 00-1.5 1.5V19.5a1.5 1.5 0 001.5 1.5z" /></svg>
      <p>Aucun slide pour cette position</p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="slide in slides" :key="slide.id"
        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm hover:shadow-md transition group"
        :class="{ 'opacity-50': !slide.is_active }"
      >
        <!-- Image preview -->
        <div class="relative h-40 bg-gray-100 dark:bg-gray-900 overflow-hidden">
          <img v-if="slide.image" :src="slide.image" :alt="slide.title" class="w-full h-full object-cover" @error="($event.target as HTMLImageElement).style.display='none'">
          <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a1.5 1.5 0 001.5-1.5V5.25a1.5 1.5 0 00-1.5-1.5H3.75a1.5 1.5 0 00-1.5 1.5V19.5a1.5 1.5 0 001.5 1.5z" /></svg>
          </div>
          <!-- Sort badge -->
          <span class="absolute top-2 left-2 px-2 py-0.5 text-xs font-bold rounded bg-black/60 text-white">
            #{{ slide.sort_order }}
          </span>
          <!-- Status badge -->
          <span class="absolute top-2 right-2 px-2 py-0.5 text-xs font-medium rounded-full"
            :class="slide.is_active ? 'bg-green-500 text-white' : 'bg-gray-500 text-white'">
            {{ slide.is_active ? 'Actif' : 'Inactif' }}
          </span>
          <!-- Link type badge -->
          <span v-if="slide.link_type !== 'none'" class="absolute bottom-2 left-2 px-2 py-0.5 text-xs font-medium rounded bg-blue-600 text-white">
            {{ linkTypeLabels[slide.link_type] || slide.link_type }}
          </span>
        </div>

        <!-- Content -->
        <div class="p-4">
          <h4 class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ slide.title }}</h4>
          <p v-if="slide.subtitle" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ slide.subtitle }}</p>
          <p v-if="slide.button_text" class="text-xs text-blue-600 dark:text-blue-400 mt-1">{{ slide.button_text }}</p>

          <!-- Period -->
          <div v-if="slide.starts_at || slide.ends_at" class="text-xs text-gray-400 mt-2">
            <span v-if="slide.starts_at">Du {{ formatDate(slide.starts_at) }}</span>
            <span v-if="slide.ends_at"> au {{ formatDate(slide.ends_at) }}</span>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-1 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
            <button @click="moveUp(slide)" :disabled="slide.sort_order <= 0" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition disabled:opacity-30">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
            </button>
            <button @click="moveDown(slide)" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
            </button>
            <button @click="openEdit(slide)" class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
            </button>
            <button @click="confirmDelete(slide)" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Create/Edit -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showModal = false">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-xl max-h-[90vh] overflow-y-auto mx-4">
        <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ editingSlide ? 'Modifier le Slide' : 'Nouveau Slide' }}</h3>
          <button @click="showModal = false" class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
        <form @submit.prevent="save" class="p-5 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Titre *</label>
              <input v-model="form.title" type="text" required class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sous-titre</label>
              <input v-model="form.subtitle" type="text" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Image *</label>
              <div class="flex items-center gap-3">
                <label class="flex-1 flex items-center justify-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition">
                  <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                  <span class="text-sm text-gray-500 dark:text-gray-400">{{ imageFile ? imageFile.name : 'Choisir une image...' }}</span>
                  <input type="file" accept="image/*" class="hidden" @change="onFileSelected">
                </label>
              </div>
              <div v-if="imagePreview || form.image" class="mt-2 rounded-lg overflow-hidden h-32 bg-gray-100 dark:bg-gray-900">
                <img :src="imagePreview || form.image" class="w-full h-full object-cover" @error="($event.target as HTMLImageElement).style.display='none'">
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Texte bouton</label>
              <input v-model="form.button_text" type="text" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="Voir les offres">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Position *</label>
              <select v-model="form.position" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                <option value="hero">Hero (principal)</option>
                <option value="sidebar">Sidebar</option>
                <option value="popup">Popup</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type de lien</label>
              <select v-model="form.link_type" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                <option value="none">Aucun</option>
                <option value="promotion">Promotion</option>
                <option value="category">Catégorie</option>
                <option value="product">Produit</option>
                <option value="url">URL personnalisée</option>
              </select>
            </div>
            <div v-if="form.link_type === 'url'">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">URL</label>
              <input v-model="form.link_url" type="text" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="https://...">
            </div>
            <div v-if="['promotion','category','product'].includes(form.link_type)">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ID {{ form.link_type }}</label>
              <input v-model.number="form.link_id" type="number" min="1" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ordre</label>
              <input v-model.number="form.sort_order" type="number" min="0" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date début</label>
              <input v-model="form.starts_at" type="datetime-local" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date fin</label>
              <input v-model="form.ends_at" type="datetime-local" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <button type="button" @click="showModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition">Annuler</button>
            <button type="submit" :disabled="saving" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition disabled:opacity-50">
              {{ saving ? 'Enregistrement...' : (editingSlide ? 'Mettre à jour' : 'Créer') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete confirmation -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showDeleteModal = false">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-sm mx-4 p-6 text-center">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Supprimer le slide ?</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">{{ deletingSlide?.title }}</p>
        <div class="flex gap-3 justify-center">
          <button @click="showDeleteModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">Annuler</button>
          <button @click="deleteSlide" :disabled="saving" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition disabled:opacity-50">Supprimer</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import http from '@/services/http'

const positions = [
  { value: '', label: 'Tous' },
  { value: 'hero', label: 'Hero' },
  { value: 'sidebar', label: 'Sidebar' },
  { value: 'popup', label: 'Popup' },
]
const linkTypeLabels: Record<string, string> = {
  promotion: 'Promotion',
  category: 'Catégorie',
  product: 'Produit',
  url: 'Lien',
}

const slides = ref<any[]>([])
const loading = ref(false)
const saving = ref(false)
const positionFilter = ref('')
const showModal = ref(false)
const showDeleteModal = ref(false)
const editingSlide = ref<any>(null)
const deletingSlide = ref<any>(null)

const emptyForm = () => ({
  title: '',
  subtitle: '',
  image: '',
  button_text: '',
  link_type: 'none' as string,
  link_id: null as number | null,
  link_url: '',
  position: 'hero' as string,
  sort_order: 0,
  starts_at: '',
  ends_at: '',
  is_active: true,
})
const form = ref(emptyForm())
const imageFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)

function onFileSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) {
    imageFile.value = file
    imagePreview.value = URL.createObjectURL(file)
  }
}

async function fetchSlides() {
  loading.value = true
  try {
    const { data } = await http.get('/slides', {
      params: { position: positionFilter.value || undefined },
    })
    slides.value = Array.isArray(data) ? data : data.data
  } finally {
    loading.value = false
  }
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function openCreate() {
  editingSlide.value = null
  form.value = emptyForm()
  imageFile.value = null
  imagePreview.value = null
  showModal.value = true
}

function openEdit(slide: any) {
  editingSlide.value = slide
  form.value = {
    title: slide.title,
    subtitle: slide.subtitle || '',
    image: slide.image,
    button_text: slide.button_text || '',
    link_type: slide.link_type || 'none',
    link_id: slide.link_id,
    link_url: slide.link_url || '',
    position: slide.position,
    sort_order: slide.sort_order,
    starts_at: slide.starts_at ? slide.starts_at.slice(0, 16) : '',
    ends_at: slide.ends_at ? slide.ends_at.slice(0, 16) : '',
    is_active: slide.is_active,
  }
  imageFile.value = null
  imagePreview.value = null
  showModal.value = true
}

async function save() {
  saving.value = true
  try {
    const fd = new FormData()
    fd.append('title', form.value.title)
    fd.append('subtitle', form.value.subtitle || '')
    fd.append('button_text', form.value.button_text || '')
    fd.append('link_type', form.value.link_type)
    fd.append('position', form.value.position)
    fd.append('sort_order', String(form.value.sort_order))
    fd.append('is_active', form.value.is_active ? '1' : '0')
    if (form.value.link_id) fd.append('link_id', String(form.value.link_id))
    if (form.value.link_url) fd.append('link_url', form.value.link_url)
    if (form.value.starts_at) fd.append('starts_at', form.value.starts_at)
    if (form.value.ends_at) fd.append('ends_at', form.value.ends_at)

    // File or existing URL
    if (imageFile.value) {
      fd.append('image', imageFile.value)
    } else if (form.value.image) {
      fd.append('image', form.value.image)
    }

    if (editingSlide.value) {
      fd.append('_method', 'PUT')
      await http.post(`/slides/${editingSlide.value.id}`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    } else {
      await http.post('/slides', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }
    showModal.value = false
    fetchSlides()
  } finally {
    saving.value = false
  }
}

async function moveUp(slide: any) {
  const idx = slides.value.indexOf(slide)
  if (idx <= 0) return
  const prev = slides.value[idx - 1]
  await http.post('/slides/reorder', {
    slides: [
      { id: slide.id, sort_order: prev.sort_order },
      { id: prev.id, sort_order: slide.sort_order },
    ]
  })
  fetchSlides()
}

async function moveDown(slide: any) {
  const idx = slides.value.indexOf(slide)
  if (idx >= slides.value.length - 1) return
  const next = slides.value[idx + 1]
  await http.post('/slides/reorder', {
    slides: [
      { id: slide.id, sort_order: next.sort_order },
      { id: next.id, sort_order: slide.sort_order },
    ]
  })
  fetchSlides()
}

function confirmDelete(slide: any) {
  deletingSlide.value = slide
  showDeleteModal.value = true
}

async function deleteSlide() {
  saving.value = true
  try {
    await http.delete(`/slides/${deletingSlide.value.id}`)
    showDeleteModal.value = false
    fetchSlides()
  } finally {
    saving.value = false
  }
}

watch(positionFilter, fetchSlides)
onMounted(fetchSlides)
</script>
