<template>
  <figure class="w-24">
    <a v-if="src" :href="src" target="_blank" rel="noopener">
      <img :src="src" :alt="label" class="w-24 h-24 object-contain rounded-lg bg-white border border-[#E4DEFF] dark:border-gray-600" />
    </a>
    <div v-else class="w-24 h-24 rounded-lg bg-white/60 dark:bg-gray-800 border border-dashed border-[#E4DEFF] dark:border-gray-600 flex items-center justify-center text-[10px] text-gray-400 px-1 text-center">
      {{ failed ? 'Aperçu indisponible' : '…' }}
    </div>
    <figcaption class="text-[10px] mt-0.5 text-center break-all opacity-70">{{ label }}</figcaption>
  </figure>
</template>

<script setup lang="ts">
/**
 * Une image servie par une route authentifiée (l'aperçu d'une photo proposée par un agent) : on la charge avec
 * le jeton de l'utilisateur puis on l'affiche depuis un objet local, car une balise <img> ne peut pas l'envoyer.
 */
import { onBeforeUnmount, onMounted, ref } from 'vue'
import http from '@/services/http'

const props = defineProps<{ url: string; label: string }>()
const src = ref('')
const failed = ref(false)

onMounted(async () => {
  try {
    const { data } = await http.get<Blob>(props.url, { responseType: 'blob' })
    src.value = URL.createObjectURL(data)
  } catch {
    failed.value = true
  }
})

onBeforeUnmount(() => {
  if (src.value) URL.revokeObjectURL(src.value)
})
</script>
