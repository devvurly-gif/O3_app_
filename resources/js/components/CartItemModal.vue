<template>
  <div v-if="isOpen" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4">
      <div>
        <h2 class="text-xl font-bold">{{ item?.designation }}</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
          Prix actuel: <span class="font-semibold text-[#6D4CE0]">{{ formatPrice(formData.price) }}</span>
        </p>
      </div>
      <div>
        <label for="cartitemmodal-formdata-price" class="block text-sm font-medium mb-2">Prix Unitaire</label>
        <input id="cartitemmodal-formdata-price" v-model.number="formData.price" type="number" min="0" step="0.01" class="w-full px-3 py-2 border-2 border-[#A78BFA] rounded-lg bg-[#F1ECFC] text-lg font-semibold" />
      </div>
      <div>
        <label for="cartitemmodal-formdata-quantity" class="block text-sm font-medium mb-2">Quantité</label>
        <input id="cartitemmodal-formdata-quantity" v-model.number="formData.quantity" type="number" min="1" class="w-full px-3 py-2 border-2 border-blue-400 rounded-lg bg-blue-50 text-lg font-semibold" placeholder="0" />
      </div>
      <div>
        <label for="cartitemmodal-formdata-discount" class="block text-sm font-medium mb-2">Remise (%)</label>
        <input id="cartitemmodal-formdata-discount" v-model.number="formData.discount" type="number" min="0" max="100" step="0.01" class="w-full px-3 py-2 border-2 border-green-400 rounded-lg bg-green-50 text-lg font-semibold" />
      </div>
      <div class="bg-gray-100 p-3 rounded-lg">
        <p class="text-xs text-gray-600">Total Ligne (HT):</p>
        <p class="text-lg font-bold">{{ formatPrice(formData.quantity * formData.price * (1 - formData.discount / 100)) }}</p>
      </div>
      <div class="flex gap-3">
        <button @click="close" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg font-medium hover:bg-gray-400">Annuler</button>
        <button @click="save" class="flex-1 px-4 py-2 bg-[#7C5CFC] text-white rounded-lg font-medium hover:bg-[#6D4CE0]">Sauvegarder</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from "vue"
import { useNumberFormat } from "@/composables/useNumberFormat"

const props = defineProps({ isOpen: Boolean, item: Object as any })
const emit = defineEmits(["close", "save"])
const formData = ref({ price: 0, quantity: 1, discount: 0 })

// Use the number formatting composable
const { formatPrice } = useNumberFormat()

// Watch isOpen and initialize form data when modal opens
watch(() => props.isOpen, (newVal) => {
  if (newVal && props.item) {
    formData.value = {
      price: props.item.unit_price || 0,
      quantity: props.item.quantity || 1,
      discount: props.item.discount_percent || 0
    }
  }
})

const close = () => emit("close")
const save = () => emit("save", formData.value)
</script>
