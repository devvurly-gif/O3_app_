<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">📖 Guides d'utilisation</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Documentation pour utiliser O3 App</p>
      </div>
      <button @click="printGuide" class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
        🖨️ Imprimer
      </button>
    </div>

    <!-- Guide tabs -->
    <div class="border-b border-gray-200 dark:border-gray-700">
      <nav class="flex gap-1 -mb-px overflow-x-auto">
        <button
          v-for="guide in guides"
          :key="guide.id"
          @click="activeGuide = guide.id"
          class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors flex items-center gap-2"
          :class="activeGuide === guide.id
            ? 'border-blue-500 text-blue-600 dark:text-blue-400 dark:border-blue-400'
            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
        >
          <span>{{ guide.icon }}</span> {{ guide.label }}
        </button>
      </nav>
    </div>

    <!-- Guide content rendered in iframe for full fidelity -->
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <iframe
        ref="iframeRef"
        :src="activeGuideSrc"
        class="w-full"
        :style="{ height: frameHeight + 'px' }"
        frameborder="0"
        @load="onFrameLoad"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

const guides = [
  { id: 'tenant', label: 'Guide général', icon: '🚀', src: '/guide-tenant.html' },
  { id: 'pos',    label: 'Guide POS Terminal', icon: '🖥️', src: '/guide-pos.html' },
]

const activeGuide = ref('tenant')
const frameHeight = ref(800)
const iframeRef = ref<HTMLIFrameElement | null>(null)

const activeGuideSrc = computed(() => guides.find(g => g.id === activeGuide.value)?.src ?? '')

function onFrameLoad() {
  try {
    const doc = iframeRef.value?.contentDocument
    if (doc) {
      const h = doc.body?.scrollHeight
      if (h && h > 400) frameHeight.value = h + 40
    }
  } catch {
    frameHeight.value = 900
  }
}

function printGuide() {
  iframeRef.value?.contentWindow?.print()
}
</script>
