<template>
    <div v-if="hasProduct">
      <!-- Unified grid: images + upload tile -->
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2.5">
        <div
          v-for="img in images"
          :key="img.id"
          class="relative group rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 aspect-square bg-gray-50 dark:bg-gray-900"
        >
          <img :src="img.url" :alt="img.title" class="w-full h-full object-cover" />
          <span
            v-if="img.isPrimary"
            class="absolute top-1 left-1 text-[10px] font-bold bg-[#7C5CFC] text-white px-1.5 py-0.5 rounded"
          >
            {{ $t('products.primary') }}
          </span>
          <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
            <button
              v-if="!img.isPrimary"
              type="button"
              class="p-1.5 bg-white dark:bg-gray-800 rounded-lg text-[#7C5CFC] hover:bg-[#F1ECFC] transition"
              :title="$t('products.setPrimary')"
              @click="emit('set-primary', img)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                />
              </svg>
            </button>
            <button
              type="button"
              class="p-1.5 bg-white dark:bg-gray-800 rounded-lg text-red-500 hover:bg-red-50 transition"
              :title="$t('common.delete')"
              @click="emit('delete-image', img)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                />
              </svg>
            </button>
          </div>
        </div>

        <!-- Upload tile -->
        <label
          class="aspect-square rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-blue-400 bg-gray-50 dark:bg-gray-900 flex flex-col items-center justify-center cursor-pointer transition text-gray-400 dark:text-gray-500 hover:text-[#7C5CFC]"
        >
          <svg v-if="!uploadingImage" class="w-7 h-7 mb-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          <svg v-else class="w-6 h-6 animate-spin mb-1" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
          </svg>
          <span class="text-xs font-medium text-center px-2">
            {{ uploadingImage ? $t('products.uploadingImage') : $t('products.addImage') }}
          </span>
          <input
            type="file"
            multiple
            accept="image/*"
            class="hidden"
            :disabled="uploadingImage"
            @change="emit('upload-image', $event)"
          />
        </label>
      </div>

      <p v-if="!images.length" class="text-xs text-gray-400 dark:text-gray-500 text-center mt-2">
        {{ $t('products.noImages') }}
      </p>

      <!-- Videos section -->
      <div class="mt-5">
        <h4 class="font-semibold text-gray-900 dark:text-white text-sm mb-2">{{ $t('products.videos') ?? 'Videos' }}</h4>
        <div class="space-y-2">
          <div
            v-for="video in videos"
            :key="video.id"
            class="flex items-center gap-2.5 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900"
          >
            <svg class="w-5 h-5 flex-shrink-0 text-[#7C5CFC]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
            </svg>
            <a :href="video.url" target="_blank" rel="noopener" class="flex-1 min-w-0 truncate text-sm text-gray-700 dark:text-gray-200 hover:underline">
              {{ video.title || video.url }}
            </a>
            <button
              type="button"
              class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition flex-shrink-0"
              :title="$t('common.delete')"
              @click="emit('delete-video', video)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
        <p v-if="!videos.length" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
          {{ $t('products.noVideos') ?? 'No videos.' }}
        </p>
        <div class="flex flex-col sm:flex-row gap-2 mt-2.5">
          <input
            v-model="newVideoTitle" :aria-label="$t('products.videoTitlePlaceholder') ?? 'Title (optional)'"
            type="text"
            :placeholder="$t('products.videoTitlePlaceholder') ?? 'Title (optional)'"
            class="flex-1 px-3 py-1.5 text-input border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 dark:text-white"
          />
          <input
            v-model="newVideoUrl" :aria-label="$t('products.videoUrlPlaceholder') ?? 'Video URL'"
            type="url"
            :placeholder="$t('products.videoUrlPlaceholder') ?? 'https://youtube.com/watch?v=...'"
            class="flex-[2] px-3 py-1.5 text-input border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 dark:text-white"
            @keyup.enter="emit('add-video')"
          />
          <button
            type="button"
            class="px-3 py-1.5 text-xs font-medium bg-[#7C5CFC] text-white rounded-lg hover:bg-[#6D4CE0] transition disabled:opacity-50"
            :disabled="addingVideo || !newVideoUrl.trim()"
            @click="emit('add-video')"
          >
            {{ addingVideo ? $t('products.uploadingImage') : ($t('products.addVideo') ?? 'Add') }}
          </button>
        </div>
      </div>

      <!-- Documents section -->
      <div class="mt-5">
        <h4 class="font-semibold text-gray-900 dark:text-white text-sm mb-2">{{ $t('products.documents') ?? 'Documents' }}</h4>
        <div class="space-y-2">
          <div
            v-for="doc in documents"
            :key="doc.id"
            class="flex items-center gap-2.5 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900"
          >
            <svg class="w-5 h-5 flex-shrink-0 text-[#7C5CFC]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            <a :href="doc.url" target="_blank" rel="noopener" download class="flex-1 min-w-0 truncate text-sm text-gray-700 dark:text-gray-200 hover:underline">
              {{ doc.title || doc.file_name }}
            </a>
            <span v-if="doc.size" class="text-[10px] text-gray-400 flex-shrink-0">{{ (doc.size / 1024).toFixed(0) }} KB</span>
            <button
              type="button"
              class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition flex-shrink-0"
              :title="$t('common.delete')"
              @click="emit('delete-document', doc)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
        <p v-if="!documents.length" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
          {{ $t('products.noDocuments') ?? 'No documents.' }}
        </p>
        <label
          class="mt-2.5 flex items-center gap-2 px-3 py-2 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-blue-400 bg-gray-50 dark:bg-gray-900 cursor-pointer transition text-gray-400 dark:text-gray-500 hover:text-[#7C5CFC] w-fit"
        >
          <svg v-if="!uploadingDocument" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
          </svg>
          <span class="text-xs font-medium">
            {{ uploadingDocument ? $t('products.uploadingDocument') : ($t('products.addDocument') ?? 'Add document') }}
          </span>
          <input
            type="file"
            multiple
            accept=".doc,.docx,.xls,.xlsx,.pdf"
            class="hidden"
            :disabled="uploadingDocument"
            @change="emit('upload-document', $event)"
          />
        </label>
      </div>
    </div>
    <div v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
      {{ $t('products.stockAfterSave') ?? 'Media available after saving the product.' }}
    </div>
</template>

<script setup lang="ts">
/**
 * L'onglet Medias d'une fiche produit : images, liens video, documents.
 *
 * L'etat vit dans `useProductMedia`, chez l'ecran hote — il doit survivre au
 * changement d'onglet, or ce composant est monte sous `v-if` et disparait a
 * chaque bascule. Ne restent ici que l'affichage et les intentions.
 *
 * Les deux champs du formulaire video passent en `defineModel` : c'est le
 * composable qui les vide, et seulement quand l'ajout a reussi.
 */
defineProps<{
  /** Faux en creation : rien a joindre a un produit qui n'existe pas encore. */
  hasProduct: boolean
  images: any[]
  videos: any[]
  documents: any[]
  uploadingImage: boolean
  uploadingDocument: boolean
  addingVideo: boolean
}>()

const emit = defineEmits<{
  'upload-image': [event: Event]
  'set-primary': [image: any]
  'delete-image': [image: any]
  'add-video': []
  'delete-video': [video: any]
  'upload-document': [event: Event]
  'delete-document': [document: any]
}>()

const newVideoTitle = defineModel<string>('newVideoTitle', { required: true })
const newVideoUrl = defineModel<string>('newVideoUrl', { required: true })
</script>
