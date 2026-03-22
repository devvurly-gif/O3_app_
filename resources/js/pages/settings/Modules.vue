<template>
  <div class="space-y-5">
    <div>
      <h2 class="text-xl font-bold text-gray-900 dark:text-white">Modules</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Activer ou désactiver les modules de l'application.</p>
    </div>

    <div v-if="loading" class="text-sm text-gray-500 dark:text-gray-400">Chargement...</div>

    <div v-else class="grid gap-4">
      <div
        v-for="mod in items"
        :key="mod.id"
        class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 flex items-start gap-4"
      >
        <!-- Icon -->
        <div
          class="shrink-0 w-12 h-12 rounded-xl flex items-center justify-center"
          :class="mod.is_active ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400'"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M13 10V3L4 14h7v7l9-11h-7z"
            />
          </svg>
        </div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-1">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ mod.display_name }}</h3>
            <span
              class="text-[10px] font-semibold px-2 py-0.5 rounded-full uppercase"
              :class="mod.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
            >
              {{ mod.is_active ? 'Actif' : 'Inactif' }}
            </span>
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ mod.description }}</p>

          <!-- License -->
          <div class="flex flex-wrap items-end gap-3">
            <div class="w-64">
              <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Clé de licence</label>
              <input
                v-model="licenseKeys[mod.id]"
                type="text"
                placeholder="XXXX-XXXX-XXXX-XXXX"
                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
            <div class="w-44">
              <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Expire le</label>
              <input
                v-model="licenseDates[mod.id]"
                type="date"
                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
            <button
              class="px-3 py-2 text-sm font-medium rounded-lg transition"
              :class="
                saving[mod.id]
                  ? 'bg-gray-200 text-gray-500 cursor-wait'
                  : 'bg-blue-600 hover:bg-blue-700 text-white'
              "
              :disabled="saving[mod.id]"
              @click="saveLicense(mod)"
            >
              {{ saving[mod.id] ? 'Enregistrement...' : 'Enregistrer' }}
            </button>
          </div>
        </div>

        <!-- Toggle -->
        <button
          type="button"
          class="shrink-0 relative inline-flex h-6 w-11 items-center rounded-full transition-colors mt-1"
          :class="mod.is_active ? 'bg-green-500' : 'bg-gray-300'"
          @click="toggleModule(mod)"
        >
          <span
            class="inline-block h-4 w-4 transform rounded-full bg-white dark:bg-gray-800 transition-transform shadow"
            :class="mod.is_active ? 'translate-x-6' : 'translate-x-1'"
          />
        </button>
      </div>
    </div>

    <BaseNotification ref="toast" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useModuleStore, type ModuleItem } from '@/stores/module'
import BaseNotification from '@/components/BaseNotification.vue'

const store = useModuleStore()
const { items, loading } = storeToRefs(store)
const toast = ref(null)
const saving = reactive<Record<number, boolean>>({})
const licenseKeys = reactive<Record<number, string>>({})
const licenseDates = reactive<Record<number, string>>({})

onMounted(async () => {
  await store.fetchAll()
  for (const mod of items.value) {
    licenseKeys[mod.id] = mod.license_key ?? ''
    licenseDates[mod.id] = mod.licensed_until?.split('T')[0] ?? ''
  }
})

async function toggleModule(mod: ModuleItem) {
  try {
    await store.update(mod.id, { is_active: !mod.is_active })
    toast.value?.notify(
      mod.is_active ? `${mod.display_name} désactivé` : `${mod.display_name} activé`,
      'success',
    )
  } catch {
    toast.value?.notify('Erreur lors de la mise à jour', 'error')
  }
}

async function saveLicense(mod: ModuleItem) {
  saving[mod.id] = true
  try {
    await store.update(mod.id, {
      license_key: licenseKeys[mod.id] || null,
      licensed_until: licenseDates[mod.id] || null,
    })
    toast.value?.notify('Licence enregistrée', 'success')
  } catch {
    toast.value?.notify('Erreur lors de l\'enregistrement', 'error')
  } finally {
    saving[mod.id] = false
  }
}
</script>
