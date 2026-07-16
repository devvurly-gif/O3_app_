<template>
  <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md overflow-hidden flex flex-col hover:shadow-lg transition-shadow duration-200">
    <!-- Image -->
    <div class="relative">
      <img
        :src="product.imageUrl"
        :alt="product.name"
        class="w-full h-48 object-cover"
        loading="lazy"
      />
      <!-- Badge Nouveau -->
      <span
        v-if="product.isNew"
        class="absolute top-2 left-2 bg-[#7C5CFC] text-white text-xs font-bold px-2 py-1 rounded-full uppercase tracking-wide"
      >
        Nouveau
      </span>
    </div>

    <!-- Contenu -->
    <div class="flex flex-col flex-1 p-4 gap-3">
      <!-- Nom du produit -->
      <h3 class="text-gray-900 dark:text-white font-semibold text-base leading-snug line-clamp-2">
        {{ product.name }}
      </h3>

      <!-- Prix formaté -->
      <p class="text-[#6D4CE0] font-bold text-lg">
        {{ formattedPrice }}
      </p>

      <!-- Bouton Ajouter au panier -->
      <button
        type="button"
        class="mt-auto w-full bg-[#7C5CFC] hover:bg-[#6D4CE0] active:bg-[#5B3FD1] text-white font-medium py-2 px-4 rounded-xl transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-[#A78BFA] focus:ring-offset-2"
        @click="handleAddToCart"
      >
        Ajouter au panier
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useNumberFormat } from '@/composables/useNumberFormat'

// ── Types ──────────────────────────────────────────────────────────────────
interface Product {
  id: number | string
  name: string
  price: number
  imageUrl: string
  isNew?: boolean
}

interface Props {
  product: Product
}

// ── Props / Emits ──────────────────────────────────────────────────────────
const props = withDefaults(defineProps<Props>(), {})

const emit = defineEmits<{
  addToCart: [product: Product]
}>()

// ── Composables ────────────────────────────────────────────────────────────
const { formatPrice } = useNumberFormat()

// ── Computed ───────────────────────────────────────────────────────────────
const formattedPrice = computed<string>(() => formatPrice(props.product.price))

// ── Fonctions ──────────────────────────────────────────────────────────────
function handleAddToCart(): void {
  emit('addToCart', props.product)
}
</script>
