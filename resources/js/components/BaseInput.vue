<script setup lang="ts">
interface Props {
  modelValue?: string | number
  label?: string
  type?: string
  placeholder?: string
  error?: string
  helperText?: string
  required?: boolean
  disabled?: boolean
  readonly?: boolean
  rows?: number
  id?: string
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  rows: 3,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  blur: []
}>()

function onInput(event: Event) {
  const target = event.target as HTMLInputElement | HTMLTextAreaElement
  emit('update:modelValue', target.value)
}
</script>

<template>
  <div>
    <label v-if="label" :for="id" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
      {{ label }}
      <span v-if="required" class="text-danger-500">*</span>
    </label>

    <textarea
      v-if="type === 'textarea'"
      :id="id"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :required="required"
      :rows="rows"
      class="w-full rounded-lg border px-3 py-2 text-sm transition focus:outline-none focus:ring-2 disabled:opacity-60 disabled:cursor-not-allowed dark:bg-gray-800 dark:text-gray-100"
      :class="
        error
          ? 'border-danger-300 dark:border-danger-700 focus:ring-danger-500'
          : 'border-gray-300 dark:border-gray-600 focus:ring-primary-500'
      "
      @input="onInput"
      @blur="emit('blur')"
    />

    <input
      v-else
      :id="id"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :required="required"
      class="w-full rounded-lg border px-3 py-2 text-sm transition focus:outline-none focus:ring-2 disabled:opacity-60 disabled:cursor-not-allowed dark:bg-gray-800 dark:text-gray-100"
      :class="
        error
          ? 'border-danger-300 dark:border-danger-700 focus:ring-danger-500'
          : 'border-gray-300 dark:border-gray-600 focus:ring-primary-500'
      "
      @input="onInput"
      @blur="emit('blur')"
    />

    <p v-if="error" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ error }}</p>
    <p v-else-if="helperText" class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ helperText }}</p>
  </div>
</template>
