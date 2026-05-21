<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import BaseModal from '@/components/BaseModal.vue'
import http from '@/services/http'
import { useFormat } from '@/composables/useFormat'

interface Props {
  modelValue: boolean
  customer: any
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const { fmt } = useFormat()

const activeTab = ref('info')
const loading = ref(false)
const detail = ref<any>(null)

const tabs = [
  { key: 'info', label: 'Informations', icon: 'IconInfoCircle' },
  { key: 'fiscal', label: 'Fiscal', icon: 'IconFileText' },
  { key: 'credit', label: 'Crédit', icon: 'IconCreditCard' },
]

const creditPercent = computed(() => {
  if (!detail.value || (detail.value.seuil_credit ?? 0) === 0) return 0
  return Math.min(100, ((detail.value.encours_actuel ?? 0) / (detail.value.seuil_credit ?? 0)) * 100)
})

const creditAvailable = computed(() => {
  if (!detail.value) return 0
  return (detail.value.seuil_credit ?? 0) - (detail.value.encours_actuel ?? 0)
})

watch(
  () => props.modelValue,
  async (isOpen) => {
    if (isOpen && props.customer) {
      activeTab.value = 'info'
      detail.value = props.customer
      loading.value = true
      try {
        const { data } = await http.get(`/third-parties/${props.customer.id}`)
        detail.value = data
      } catch {
        // Silently continue with what we have
      } finally {
        loading.value = false
      }
    }
  },
  { immediate: true }
)

const handleClose = () => {
  emit('update:modelValue', false)
}
</script>

<template>
  <BaseModal v-model="modelValue" :title="`Détails Client : ${detail?.tp_title ?? ''}`" size="xl" @update:model-value="handleClose">
    <!-- Tab Navigation -->
    <div class="border-b border-gray-200 dark:border-gray-700 -mt-2 mb-4">
      <nav class="flex gap-0 -mb-px overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
          :class="
            activeTab === tab.key
              ? 'border-orange-500 text-orange-500'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
          "
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="w-6 h-6 animate-spin text-orange-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
      </svg>
    </div>

    <div v-else class="min-h-[300px]">
      <!-- TAB: Info -->
      <div v-show="activeTab === 'info'" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
          <div class="sm:col-span-2">
            <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">
              Informations générales
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Code</p>
            <p class="text-sm font-mono font-medium text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
              {{ detail?.tp_code ?? '—' }}
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Nom</p>
            <p class="text-sm font-medium text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
              {{ detail?.tp_title ?? '—' }}
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Téléphone</p>
            <p class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
              {{ detail?.tp_phone || '—' }}
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Email</p>
            <p class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
              {{ detail?.tp_email || '—' }}
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Ville</p>
            <p class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
              {{ detail?.tp_city || '—' }}
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Statut</p>
            <p class="text-sm bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                :class="detail?.tp_status ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
              >
                {{ detail?.tp_status ? 'Actif' : 'Inactif' }}
              </span>
            </p>
          </div>
          <div class="sm:col-span-2">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Adresse</p>
            <p class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg min-h-[40px]">
              {{ detail?.tp_address || '—' }}
            </p>
          </div>
        </div>
      </div>

      <!-- TAB: Fiscal -->
      <div v-show="activeTab === 'fiscal'" class="space-y-4">
        <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">Informations fiscales</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">ICE</p>
            <p class="text-sm font-mono text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
              {{ detail?.tp_Ice_Number || '—' }}
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">RC</p>
            <p class="text-sm font-mono text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
              {{ detail?.tp_Rc_Number || '—' }}
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Patente</p>
            <p class="text-sm font-mono text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
              {{ detail?.tp_patente_Number || '—' }}
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Identifiant fiscal</p>
            <p class="text-sm font-mono text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
              {{ detail?.tp_IdenFiscal || '—' }}
            </p>
          </div>
        </div>
      </div>

      <!-- TAB: Credit -->
      <div v-show="activeTab === 'credit'" class="space-y-5">
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-5 border border-blue-100">
          <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Situation crédit</h4>
            <span
              class="text-xs font-medium px-2 py-1 rounded-full"
              :class="
                creditPercent <= 70
                  ? 'bg-emerald-100 text-emerald-700'
                  : creditPercent <= 90
                    ? 'bg-amber-100 text-amber-700'
                    : 'bg-red-100 text-red-700'
              "
            >
              {{ creditPercent.toFixed(0) }}% utilisé
            </span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-3 mb-3">
            <div
              class="h-3 rounded-full transition-all duration-500"
              :class="
                creditPercent <= 70 ? 'bg-emerald-500' : creditPercent <= 90 ? 'bg-amber-500' : 'bg-red-500'
              "
              :style="{ width: Math.min(creditPercent, 100) + '%' }"
            ></div>
          </div>
          <div class="grid grid-cols-3 gap-4 text-center">
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Encours actuel</p>
              <p class="text-lg font-bold text-gray-900 dark:text-white font-mono">
                {{ fmt(detail?.encours_actuel ?? 0) }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Seuil crédit</p>
              <p class="text-lg font-bold text-gray-900 dark:text-white font-mono">
                {{ fmt(detail?.seuil_credit ?? 0) }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Disponible</p>
              <p
                class="text-lg font-bold font-mono"
                :class="creditAvailable > 0 ? 'text-emerald-600' : 'text-red-600'"
              >
                {{ fmt(creditAvailable) }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </BaseModal>
</script>
