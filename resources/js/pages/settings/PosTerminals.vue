<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Terminaux POS</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Gérer les terminaux de point de vente</p>
      </div>
      <button
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition"
        @click="openCreate"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Ajouter
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
          <tr>
            <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-gray-400">Code</th>
            <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-gray-400">Nom</th>
            <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-gray-400">Entrepôt</th>
            <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-gray-400">Statut</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading" class="border-b">
            <td colspan="5" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">Chargement...</td>
          </tr>
          <tr v-else-if="!terminals.length" class="border-b">
            <td colspan="5" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">Aucun terminal</td>
          </tr>
          <tr v-for="t in terminals" :key="t.id" class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
            <td class="px-4 py-3">
              <span class="font-mono text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">{{ t.code }}</span>
            </td>
            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ t.name }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ t.warehouse?.wh_title ?? '—' }}</td>
            <td class="px-4 py-3">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                :class="t.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
              >
                {{ t.is_active ? 'Actif' : 'Inactif' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <button
                  class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition"
                  @click="openEdit(t)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button
                  class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition"
                  @click="confirmDelete(t)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit Modal -->
    <BaseModal v-model="showModal" :title="editTarget ? 'Modifier le terminal' : 'Ajouter un terminal'" size="sm">
      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom <span class="text-red-500">*</span></label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="Caisse 1"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Code <span class="text-red-500">*</span></label>
          <input
            v-model="form.code"
            type="text"
            required
            placeholder="POS-01"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Entrepôt <span class="text-red-500">*</span></label>
          <select
            v-model="form.warehouse_id"
            required
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option :value="null" disabled>-- Sélectionner --</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.wh_title }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <input
            id="terminal-active"
            v-model="form.is_active"
            type="checkbox"
            class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
          />
          <label for="terminal-active" class="text-sm text-gray-700 dark:text-gray-300">Actif</label>
        </div>
      </form>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition"
          @click="showModal = false"
        >
          Annuler
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition disabled:opacity-60"
          :disabled="saving"
          @click="submit"
        >
          {{ saving ? 'Enregistrement...' : editTarget ? 'Modifier' : 'Créer' }}
        </button>
      </template>
    </BaseModal>

    <!-- Delete Modal -->
    <BaseModal v-model="showDelete" title="Supprimer le terminal" size="sm">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        Supprimer le terminal <span class="font-semibold">{{ deleteTarget?.name }}</span> ?
      </p>
      <template #footer>
        <button class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition" @click="showDelete = false">
          Annuler
        </button>
        <button
          class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg transition disabled:opacity-60"
          :disabled="deleting"
          @click="doDelete"
        >
          {{ deleting ? 'Suppression...' : 'Supprimer' }}
        </button>
      </template>
    </BaseModal>

    <BaseNotification ref="toast" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import http from '@/services/http'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'

interface Terminal {
  id: number
  name: string
  code: string
  warehouse_id: number
  is_active: boolean
  warehouse?: { id: number; wh_title: string }
}

interface Warehouse {
  id: number
  wh_title: string
}

const terminals = ref<Terminal[]>([])
const warehouses = ref<Warehouse[]>([])
const loading = ref(true)
const showModal = ref(false)
const showDelete = ref(false)
const saving = ref(false)
const deleting = ref(false)
const editTarget = ref<Terminal | null>(null)
const deleteTarget = ref<Terminal | null>(null)
const toast = ref<InstanceType<typeof BaseNotification> | null>(null)

const emptyForm = () => ({ name: '', code: '', warehouse_id: null as number | null, is_active: true })
const form = reactive(emptyForm())

onMounted(async () => {
  const [tRes, wRes] = await Promise.all([
    http.get<Terminal[]>('/pos/terminals'),
    http.get('/warehouses'),
  ])
  terminals.value = tRes.data
  // Handle paginated or flat warehouse response
  const wData = wRes.data
  warehouses.value = Array.isArray(wData) ? wData : (wData as { data: Warehouse[] }).data ?? []
  loading.value = false
})

function openCreate() {
  editTarget.value = null
  Object.assign(form, emptyForm())
  showModal.value = true
}

function openEdit(t: Terminal) {
  editTarget.value = t
  Object.assign(form, { name: t.name, code: t.code, warehouse_id: t.warehouse_id, is_active: t.is_active })
  showModal.value = true
}

async function submit() {
  saving.value = true
  try {
    if (editTarget.value) {
      const { data } = await http.put<Terminal>(`/pos/terminals/${editTarget.value.id}`, form)
      const idx = terminals.value.findIndex((t) => t.id === editTarget.value!.id)
      if (idx !== -1) terminals.value[idx] = data
      toast.value?.notify('Terminal modifié', 'success')
    } else {
      const { data } = await http.post<Terminal>('/pos/terminals', form)
      terminals.value.push(data)
      toast.value?.notify('Terminal créé', 'success')
    }
    showModal.value = false
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.value?.notify(e.response?.data?.message ?? 'Erreur', 'error')
  } finally {
    saving.value = false
  }
}

function confirmDelete(t: Terminal) {
  deleteTarget.value = t
  showDelete.value = true
}

async function doDelete() {
  deleting.value = true
  try {
    await http.delete(`/pos/terminals/${deleteTarget.value!.id}`)
    terminals.value = terminals.value.filter((t) => t.id !== deleteTarget.value!.id)
    toast.value?.notify('Terminal supprimé', 'success')
    showDelete.value = false
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.value?.notify(e.response?.data?.message ?? 'Erreur', 'error')
  } finally {
    deleting.value = false
  }
}
</script>
