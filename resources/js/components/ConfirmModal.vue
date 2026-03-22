<script setup lang="ts">
import BaseModal from '@/components/BaseModal.vue'

/**
 * Generic confirmation modal.
 * Replaces identical delete/confirm modals across 9+ pages.
 *
 * Usage:
 *   <ConfirmModal v-model="showDelete" title="Supprimer" :loading="deleting"
 *     confirm-label="Supprimer" variant="danger" @confirm="doDelete">
 *     Êtes-vous sûr ?
 *   </ConfirmModal>
 */
const show = defineModel<boolean>({ required: true })

withDefaults(
  defineProps<{
    title: string
    confirmLabel?: string
    cancelLabel?: string
    loading?: boolean
    loadingLabel?: string
    /** 'danger' = red button, 'primary' = blue button */
    variant?: 'danger' | 'primary'
  }>(),
  {
    confirmLabel: 'Confirmer',
    cancelLabel: 'Annuler',
    loading: false,
    loadingLabel: '',
    variant: 'danger',
  },
)

const emit = defineEmits<{
  confirm: []
}>()
</script>

<template>
  <BaseModal v-model="show" :title="title" size="sm">
    <div class="text-sm text-gray-600 dark:text-gray-300">
      <slot />
    </div>
    <template #footer>
      <button
        class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
        @click="show = false"
      >
        {{ cancelLabel }}
      </button>
      <button
        :disabled="loading"
        class="px-5 py-2 text-sm font-medium text-white rounded-lg disabled:opacity-50"
        :class="variant === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
        @click="emit('confirm')"
      >
        {{ loading ? loadingLabel || confirmLabel + '...' : confirmLabel }}
      </button>
    </template>
  </BaseModal>
</template>
