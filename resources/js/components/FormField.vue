<template>
  <div class="form-field" :class="{ 'has-error': error }">
    <label v-if="label" :for="id" class="form-label">
      {{ label }}
      <span v-if="required" class="required">*</span>
    </label>

    <div class="input-wrapper">
      <input
        v-if="type !== 'textarea'"
        :id="id"
        v-model="modelValue"
        :type="type"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :class="inputClasses"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        @blur="$emit('blur')"
      />

      <textarea
        v-else
        :id="id"
        v-model="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :rows="rows"
        :class="inputClasses"
        @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        @blur="$emit('blur')"
      />

      <div v-if="showCharCount && type === 'textarea'" class="char-count">
        {{ String(modelValue ?? '').length }} / {{ maxlength }}
      </div>

      <button
        v-if="showCopy && modelValue"
        type="button"
        class="copy-btn"
        @click="copyToClipboard"
        title="Copy to clipboard"
      >
        📋
      </button>
    </div>

    <div v-if="error" class="error-message">
      {{ error }}
    </div>

    <div v-if="helperText && !error" class="helper-text">
      {{ helperText }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

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
  maxlength?: number
  showCharCount?: boolean
  showCopy?: boolean
  id?: string
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  rows: 3,
  maxlength: 500,
  showCharCount: false,
  showCopy: false,
})

defineEmits<{
  'update:modelValue': [value: string]
  blur: []
}>()

const inputClasses = computed(() => ({
  'form-input': true,
  'is-error': props.error,
  'is-disabled': props.disabled,
}))

const copyToClipboard = () => {
  if (props.modelValue) {
    navigator.clipboard.writeText(String(props.modelValue))
  }
}
</script>

<style scoped>
.form-field {
  margin-bottom: 1.5rem;
}

.form-label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
  color: #374151;
}

.required {
  color: #ef4444;
  margin-left: 0.25rem;
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: flex-start;
}

.form-input,
textarea {
  flex: 1;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-family: inherit;
  transition: all 0.2s;
}

.form-input:focus,
textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input.is-error,
textarea.is-error {
  border-color: #ef4444;
  background-color: #fef2f2;
}

.form-input.is-error:focus,
textarea.is-error:focus {
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.form-input.is-disabled,
textarea.is-disabled {
  background-color: #f3f4f6;
  cursor: not-allowed;
  opacity: 0.6;
}

.char-count {
  position: absolute;
  bottom: 0.5rem;
  right: 2.5rem;
  font-size: 0.75rem;
  color: #9ca3af;
}

.copy-btn {
  position: absolute;
  right: 0.5rem;
  top: 0.5rem;
  background: none;
  border: none;
  padding: 0.25rem;
  cursor: pointer;
  font-size: 1rem;
  transition: opacity 0.2s;
}

.copy-btn:hover {
  opacity: 0.7;
}

.error-message {
  margin-top: 0.375rem;
  font-size: 0.75rem;
  color: #ef4444;
}

.helper-text {
  margin-top: 0.375rem;
  font-size: 0.75rem;
  color: #6b7280;
}

/* Dark mode support */
:global(.dark) .form-label {
  color: #e5e7eb;
}

:global(.dark) .form-input,
:global(.dark) textarea {
  background-color: #1f2937;
  border-color: #374151;
  color: #f3f4f6;
}

:global(.dark) .form-input:focus,
:global(.dark) textarea:focus {
  border-color: #60a5fa;
}

:global(.dark) .form-input.is-error,
:global(.dark) textarea.is-error {
  background-color: #7f1d1d;
  border-color: #fca5a5;
}

:global(.dark) .form-input.is-disabled,
:global(.dark) textarea.is-disabled {
  background-color: #374151;
  color: #9ca3af;
}
</style>
