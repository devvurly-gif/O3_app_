<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Rôles & Permissions</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Gérer les rôles et leurs permissions d'accès</p>
      </div>
      <button
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition"
        @click="openCreate"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Nouveau rôle
      </button>
    </div>

    <!-- Loading -->
    <div v-if="roleStore.loading" class="flex items-center justify-center py-12">
      <svg class="w-8 h-8 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
      </svg>
    </div>

    <!-- Roles Cards -->
    <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <div
        v-for="role in roleStore.items"
        :key="role.id"
        class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm hover:shadow-md transition"
      >
        <div class="flex items-start justify-between mb-3">
          <div>
            <div class="flex items-center gap-2">
              <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ role.display_name }}</h3>
              <span
                v-if="role.is_system"
                class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 uppercase"
              >
                Système
              </span>
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500 font-mono mt-0.5">{{ role.name }}</p>
            <p v-if="role.description" class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ role.description }}</p>
          </div>
          <div class="flex items-center gap-1">
            <button
              class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition"
              title="Modifier"
              @click="openEdit(role)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                />
              </svg>
            </button>
            <button
              v-if="!role.is_system"
              class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition"
              title="Supprimer"
              @click="confirmDelete(role)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                />
              </svg>
            </button>
          </div>
        </div>
        <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
          <span class="flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            {{ role.users_count ?? 0 }} utilisateur(s)
          </span>
          <span class="flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            {{ role.permissions?.length ?? 0 }} permission(s)
          </span>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <BaseModal v-model="showModal" :title="editTarget ? 'Modifier le rôle' : 'Nouveau rôle'" size="lg">
      <form class="space-y-5" @submit.prevent="submit">
        <!-- Role info -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom (slug) <span class="text-red-500">*</span></label>
            <input
              v-model="form.name"
              type="text"
              required
              :disabled="editTarget?.is_system"
              placeholder="ex: commercial"
              pattern="^[a-z_]+$"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-gray-100 disabled:cursor-not-allowed"
            />
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Lettres minuscules et underscores uniquement</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom affiché <span class="text-red-500">*</span></label>
            <input
              v-model="form.display_name"
              type="text"
              required
              placeholder="ex: Commercial"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
            <input
              v-model="form.description"
              type="text"
              placeholder="Description optionnelle du rôle"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
        </div>

        <!-- Permissions matrix -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Permissions</label>
            <div class="flex gap-2">
              <button type="button" class="text-xs text-blue-600 hover:underline" @click="selectAll">Tout sélectionner</button>
              <span class="text-gray-300">|</span>
              <button type="button" class="text-xs text-gray-500 dark:text-gray-400 hover:underline" @click="deselectAll">Tout désélectionner</button>
            </div>
          </div>

          <div class="border border-gray-200 dark:border-gray-700 rounded-lg divide-y divide-gray-100 dark:divide-gray-700 max-h-96 overflow-y-auto">
            <div v-for="(perms, module) in permissionsByModule" :key="module" class="p-3">
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="text-xs font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                    @click="toggleModule(module as string, perms)"
                  >
                    {{ moduleLabels[module as string] || module }}
                  </button>
                  <span class="text-[10px] text-gray-400 dark:text-gray-500">
                    {{ countModuleSelected(perms) }}/{{ perms.length }}
                  </span>
                </div>
              </div>
              <div class="flex flex-wrap gap-2">
                <label
                  v-for="perm in perms"
                  :key="perm.id"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-xs cursor-pointer transition"
                  :class="
                    form.permissions.includes(perm.id)
                      ? 'bg-blue-50 border-blue-300 text-blue-700'
                      : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'
                  "
                >
                  <input
                    v-model="form.permissions"
                    type="checkbox"
                    :value="perm.id"
                    class="sr-only"
                  />
                  <svg
                    v-if="form.permissions.includes(perm.id)"
                    class="w-3.5 h-3.5 text-blue-500"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                  <svg
                    v-else
                    class="w-3.5 h-3.5 text-gray-300"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                  >
                    <rect x="3" y="3" width="18" height="18" rx="3" />
                  </svg>
                  {{ perm.display_name }}
                </label>
              </div>
            </div>
          </div>
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
          {{ saving ? 'Enregistrement...' : editTarget ? 'Mettre à jour' : 'Créer' }}
        </button>
      </template>
    </BaseModal>

    <!-- Delete Modal -->
    <BaseModal v-model="showDelete" title="Supprimer le rôle" size="sm">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        Voulez-vous vraiment supprimer le rôle <span class="font-semibold">{{ deleteTarget?.display_name }}</span> ?
        Cette action est irréversible.
      </p>
      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition"
          @click="showDelete = false"
        >
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
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoleStore } from '@/stores/role'
import { usePermissionStore } from '@/stores/permission'
import type { Role, Permission } from '@/types'
import BaseModal from '@/components/BaseModal.vue'
import BaseNotification from '@/components/BaseNotification.vue'

const roleStore = useRoleStore()
const permStore = usePermissionStore()

const toast = ref<InstanceType<typeof BaseNotification> | null>(null)
const showModal = ref(false)
const showDelete = ref(false)
const saving = ref(false)
const deleting = ref(false)
const editTarget = ref<Role | null>(null)
const deleteTarget = ref<Role | null>(null)

const emptyForm = () => ({
  name: '',
  display_name: '',
  description: '',
  permissions: [] as number[],
})
const form = reactive(emptyForm())

const moduleLabels: Record<string, string> = {
  products: 'Produits',
  categories: 'Catégories',
  brands: 'Marques',
  third_partners: 'Tiers',
  warehouses: 'Entrepôts',
  documents: 'Documents',
  payments: 'Paiements',
  stock: 'Stock',
  users: 'Utilisateurs',
  roles: 'Rôles',
  settings: 'Paramètres',
}

const permissionsByModule = computed(() => {
  const grouped: Record<string, Permission[]> = {}
  for (const perm of permStore.items) {
    if (!grouped[perm.module]) grouped[perm.module] = []
    grouped[perm.module].push(perm)
  }
  return grouped
})

function countModuleSelected(perms: Permission[]): number {
  return perms.filter((p) => form.permissions.includes(p.id)).length
}

function toggleModule(module: string, perms: Permission[]) {
  const allSelected = perms.every((p) => form.permissions.includes(p.id))
  if (allSelected) {
    const ids = new Set(perms.map((p) => p.id))
    form.permissions = form.permissions.filter((id) => !ids.has(id))
  } else {
    const existing = new Set(form.permissions)
    for (const p of perms) existing.add(p.id)
    form.permissions = [...existing]
  }
}

function selectAll() {
  form.permissions = permStore.items.map((p) => p.id)
}

function deselectAll() {
  form.permissions = []
}

function openCreate() {
  editTarget.value = null
  Object.assign(form, emptyForm())
  showModal.value = true
}

function openEdit(role: Role) {
  editTarget.value = role
  Object.assign(form, {
    name: role.name,
    display_name: role.display_name,
    description: role.description ?? '',
    permissions: role.permissions?.map((p) => p.id) ?? [],
  })
  showModal.value = true
}

async function submit() {
  if (!form.name.trim() || !form.display_name.trim()) return
  saving.value = true
  try {
    if (editTarget.value) {
      await roleStore.update(editTarget.value.id, { ...form })
      toast.value?.notify('Rôle mis à jour avec succès', 'success')
    } else {
      await roleStore.create({ ...form })
      toast.value?.notify('Rôle créé avec succès', 'success')
    }
    showModal.value = false
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.value?.notify(e.response?.data?.message ?? 'Erreur lors de la sauvegarde', 'error')
  } finally {
    saving.value = false
  }
}

function confirmDelete(role: Role) {
  deleteTarget.value = role
  showDelete.value = true
}

async function doDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await roleStore.remove(deleteTarget.value.id)
    toast.value?.notify('Rôle supprimé avec succès', 'success')
    showDelete.value = false
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.value?.notify(e.response?.data?.message ?? 'Erreur lors de la suppression', 'error')
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  roleStore.fetchAll()
  permStore.fetchAll()
})
</script>
