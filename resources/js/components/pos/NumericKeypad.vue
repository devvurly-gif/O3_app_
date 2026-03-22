<template>
  <div class="grid grid-cols-3 gap-2">
    <button
      v-for="key in keys"
      :key="key"
      type="button"
      class="h-14 rounded-xl text-lg font-semibold transition-all duration-100 active:scale-95"
      :class="
        key === 'C'
          ? 'bg-red-100 text-red-600 hover:bg-red-200'
          : key === '⌫'
            ? 'bg-amber-100 text-amber-600 hover:bg-amber-200'
            : 'bg-gray-100 text-gray-800 hover:bg-gray-200'
      "
      @click="handleKey(key)"
    >
      {{ key }}
    </button>
  </div>
</template>

<script setup lang="ts">
const emit = defineEmits<{
  (e: 'input', value: string): void
  (e: 'clear'): void
  (e: 'backspace'): void
}>()

const keys = ['7', '8', '9', '4', '5', '6', '1', '2', '3', 'C', '0', '⌫']

function handleKey(key: string) {
  if (key === 'C') {
    emit('clear')
  } else if (key === '⌫') {
    emit('backspace')
  } else {
    emit('input', key)
  }
}
</script>
