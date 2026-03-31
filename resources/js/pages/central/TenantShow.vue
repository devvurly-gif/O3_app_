<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTenantStore, type Tenant } from '@/stores/central/useTenantStore'
import { useToastStore } from '@/stores/toastStore'

const route = useRoute()
const router = useRouter()
const store = useTenantStore()
const toast = useToastStore()

const tenant = ref<Tenant | null>(null)
const loading = ref(true)
const saving = ref(false)

// Editable fields
const editForm = reactive({
  name: '',
  email: '',
  domain: '',
})
const editingInfo = ref(false)
const savingInfo = ref(false)

function startEditInfo() {
  if (!tenant.value) return
  editForm.name = tenant.value.name
  editForm.email = tenant.value.email
  editForm.domain = tenant.value.domains?.[0]?.domain || ''
  editingInfo.value = true
}

function cancelEditInfo() {
  editingInfo.value = false
}

async function saveInfo() {
  if (!tenant.value) return
  savingInfo.value = true
  try {
    const payload: Record<string, string> = {}
    if (editForm.name !== tenant.value.name) payload.name = editForm.name
    if (editForm.email !== tenant.value.email) payload.email = editForm.email
    const currentDomain = tenant.value.domains?.[0]?.domain || ''
    if (editForm.domain !== currentDomain) payload.domain = editForm.domain

    if (Object.keys(payload).length === 0) {
      editingInfo.value = false
      savingInfo.value = false
      return
    }

    tenant.value = await store.update(tenant.value.id, payload as any)
    toast.success('Informations mises à jour.')
    editingInfo.value = false
  } catch (err: any) {
    const msg = err?.response?.data?.message || 'Erreur lors de la mise à jour.'
    toast.error(msg)
  }
  savingInfo.value = false
}

onMounted(async () => {
  try {
    tenant.value = await store.fetchOne(route.params.id as string)
  } catch { /* interceptor */ }
  loading.value = false
})

async function changePlan(plan: string) {
  if (!tenant.value) return
  saving.value = true
  try {
    tenant.value = await store.update(tenant.value.id, { plan } as any)
    toast.success(`Plan mis à jour : ${plan}`)
  } catch { /* interceptor */ }
  saving.value = false
}

async function toggleFeature(feature: string, value: boolean) {
  if (!tenant.value) return
  saving.value = true
  try {
    tenant.value = await store.update(tenant.value.id, { [feature]: value } as any)
    const label = feature === 'pos_enabled' ? 'POS' : 'Paiement sur BL'
    toast.success(`${label} ${value ? 'activé' : 'désactivé'}.`)
  } catch { /* interceptor */ }
  saving.value = false
}

async function toggleActive() {
  if (!tenant.value) return
  saving.value = true
  try {
    tenant.value = await store.update(tenant.value.id, { is_active: !tenant.value.is_active } as any)
    toast.success(tenant.value.is_active ? 'Tenant activé.' : 'Tenant désactivé.')
  } catch { /* interceptor */ }
  saving.value = false
}

// Password reset
const newPassword = ref('')
const showPassword = ref(false)
const resettingPassword = ref(false)

async function resetPassword() {
  if (!tenant.value || !newPassword.value) return
  if (newPassword.value.length < 6) {
    toast.error('Le mot de passe doit contenir au moins 6 caractères.')
    return
  }
  resettingPassword.value = true
  try {
    await store.resetPassword(tenant.value.id, newPassword.value)
    toast.success('Mot de passe admin réinitialisé avec succès.')
    newPassword.value = ''
    showPassword.value = false
  } catch (err: any) {
    toast.error(err?.response?.data?.message || 'Erreur lors de la réinitialisation.')
  }
  resettingPassword.value = false
}

async function deleteTenant() {
  if (!tenant.value) return
  if (!confirm(`Supprimer "${tenant.value.name}" et sa base de données ? Irréversible !`)) return
  try {
    await store.remove(tenant.value.id)
    toast.success('Tenant supprimé.')
    router.push('/central/tenants')
  } catch { /* interceptor */ }
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' })
}

function getPlanColor(plan: string) {
  return {
    starter: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    business: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    enterprise: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
  }[plan] || 'bg-gray-100 text-gray-700'
}
</script>

<template>
  <div class="max-w-3xl mx-auto space-y-6">
    <!-- Back -->
    <button @click="router.push('/central/tenants')" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Retour aux clients
    </button>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
      </svg>
    </div>

    <template v-else-if="tenant">
      <!-- Header Card -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-start justify-between">
          <div>
            <div class="flex items-center gap-3 mb-2">
              <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ tenant.name }}</h1>
              <span :class="['px-2.5 py-1 text-xs font-medium rounded-full', getPlanColor(tenant.plan)]">
                {{ tenant.plan }}
              </span>
              <span
                :class="[
                  'inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full',
                  tenant.is_active
                    ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                    : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
                ]"
              >
                <span :class="['w-1.5 h-1.5 rounded-full', tenant.is_active ? 'bg-green-500' : 'bg-red-500']" />
                {{ tenant.is_active ? 'Actif' : 'Inactif' }}
              </span>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">ID: {{ tenant.id }}</p>
          </div>
          <a
            v-if="tenant.domains?.length"
            :href="'http://' + tenant.domains[0].domain"
            target="_blank"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-blue-600 border border-blue-300 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
            </svg>
            Ouvrir l'app
          </a>
        </div>
      </div>

      <!-- Info Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Informations</h3>
            <button
              v-if="!editingInfo"
              @click="startEditInfo"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/40 dark:text-blue-400 rounded-lg transition"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
              </svg>
              Modifier
            </button>
          </div>

          <!-- View Mode -->
          <dl v-if="!editingInfo" class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Email</dt>
              <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ tenant.email }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Domaine</dt>
              <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ tenant.domains?.[0]?.domain || '-' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Base de données</dt>
              <dd class="text-sm font-mono text-gray-900 dark:text-white">{{ tenant.tenancy_db_name }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Créé le</dt>
              <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(tenant.created_at) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Essai expire le</dt>
              <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ tenant.trial_ends_at ? formatDate(tenant.trial_ends_at) : 'N/A' }}</dd>
            </div>
          </dl>

          <!-- Edit Mode -->
          <div v-else class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nom</label>
              <input
                v-model="editForm.name"
                type="text"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Email</label>
              <input
                v-model="editForm.email"
                type="email"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Domaine</label>
              <input
                v-model="editForm.domain"
                type="text"
                placeholder="tenant.o3app.ma"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              />
            </div>
            <div class="flex justify-between text-xs text-gray-400 dark:text-gray-500">
              <span>Base de données : <span class="font-mono">{{ tenant.tenancy_db_name }}</span></span>
              <span>Créé le {{ formatDate(tenant.created_at) }}</span>
            </div>
            <div class="flex items-center gap-2 pt-1">
              <button
                @click="saveInfo"
                :disabled="savingInfo"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition disabled:opacity-50"
              >
                <svg v-if="savingInfo" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                </svg>
                <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                Enregistrer
              </button>
              <button
                @click="cancelEditInfo"
                :disabled="savingInfo"
                class="px-4 py-2 text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition"
              >
                Annuler
              </button>
            </div>
          </div>
        </div>

        <!-- Plan Upgrade -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Changer de plan</h3>
          <div class="space-y-2">
            <button
              v-for="plan in ['starter', 'business', 'enterprise']"
              :key="plan"
              @click="changePlan(plan)"
              :disabled="saving || tenant.plan === plan"
              :class="[
                'w-full flex items-center justify-between px-4 py-3 rounded-lg border-2 text-sm font-medium transition',
                tenant.plan === plan
                  ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                  : 'border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-gray-400 cursor-pointer',
                saving ? 'opacity-50' : ''
              ]"
            >
              <span class="capitalize">{{ plan }}</span>
              <span v-if="tenant.plan === plan" class="text-xs">Plan actuel</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Feature Flags -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">Modules & Options</h3>
        <div class="space-y-4">
          <!-- POS Toggle -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="tenant.pos_enabled ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-gray-100 dark:bg-gray-700'">
                <svg :class="['w-5 h-5', tenant.pos_enabled ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Point de Vente (POS)</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">Activer la caisse et les tickets de vente</p>
              </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                :checked="tenant.pos_enabled"
                @change="toggleFeature('pos_enabled', !tenant.pos_enabled)"
                class="sr-only peer"
                :disabled="saving"
              />
              <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 dark:after:border-gray-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
          </div>

          <!-- Paiement sur BL Toggle -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="tenant.paiement_bl_enabled ? 'bg-green-100 dark:bg-green-900/30' : 'bg-gray-100 dark:bg-gray-700'">
                <svg :class="['w-5 h-5', tenant.paiement_bl_enabled ? 'text-green-600 dark:text-green-400' : 'text-gray-400']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                </svg>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Paiement sur Bon de Livraison</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">Permettre les paiements directement sur les BL</p>
              </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                :checked="tenant.paiement_bl_enabled"
                @change="toggleFeature('paiement_bl_enabled', !tenant.paiement_bl_enabled)"
                class="sr-only peer"
                :disabled="saving"
              />
              <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 dark:after:border-gray-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
            </label>
          </div>
        </div>
      </div>

      <!-- Reset Password -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">Réinitialiser le mot de passe admin</h3>
        <div class="flex items-end gap-3">
          <div class="flex-1">
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nouveau mot de passe</label>
            <div class="relative">
              <input
                v-model="newPassword"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Min. 6 caractères"
                class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                @keyup.enter="resetPassword"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
              >
                <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
              </button>
            </div>
          </div>
          <button
            @click="resetPassword"
            :disabled="resettingPassword || !newPassword"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg v-if="resettingPassword" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
            </svg>
            Réinitialiser
          </button>
        </div>
        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
          Cela changera le mot de passe de l'utilisateur admin principal de ce tenant.
        </p>
      </div>

      <!-- Actions -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">Actions</h3>
        <div class="flex flex-wrap gap-3">
          <button
            @click="toggleActive"
            :disabled="saving"
            :class="[
              'inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg transition',
              tenant.is_active
                ? 'bg-orange-100 text-orange-700 hover:bg-orange-200 dark:bg-orange-900/30 dark:text-orange-300'
                : 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300'
            ]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
            </svg>
            {{ tenant.is_active ? 'Désactiver' : 'Activer' }}
          </button>

          <button
            @click="deleteTenant"
            class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-red-700 bg-red-100 rounded-lg hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300 transition"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
            </svg>
            Supprimer le tenant
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
