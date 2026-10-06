<template>
  <button
    type="button"
    class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg bg-white/80 dark:bg-gray-800 text-[#7C5CFC] border border-[#E4DEFF] dark:border-gray-600 hover:bg-white transition disabled:opacity-50"
    :disabled="busy"
    @click="download"
  >
    <span aria-hidden="true">⬇</span>
    {{ busy ? 'Téléchargement…' : label }}
  </button>
  <p v-if="failed" class="text-[11px] text-red-600 mt-1">Le fichier n'est plus disponible (il reste 24 h) : refaites l'export.</p>
</template>

<script setup lang="ts">
/**
 * Un fichier servi par une route authentifiée (un export demandé à l'orchestrateur) : on le charge avec le jeton de
 * l'utilisateur puis on le propose au téléchargement, car un simple lien ne peut pas envoyer le jeton.
 */
import { ref } from 'vue'
import http from '@/services/http'

const props = defineProps<{ url: string; label: string; name: string }>()
const busy = ref(false)
const failed = ref(false)

async function download() {
  busy.value = true
  failed.value = false
  try {
    const { data } = await http.get<Blob>(props.url, { responseType: 'blob' })
    const href = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = href
    a.download = props.name
    document.body.appendChild(a)
    a.click()
    a.remove()
    setTimeout(() => URL.revokeObjectURL(href), 10000)
  } catch {
    failed.value = true
  } finally {
    busy.value = false
  }
}
</script>
