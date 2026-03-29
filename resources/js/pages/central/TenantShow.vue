<script setup lang="ts">
import { ref, onMounted } from 'vue'
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

async function toggleActive() {
  if (!tenant.value) return
  saving.value = true
  try {
    tenant.value = await store.update(tenant.value.id, { is_active: !tenant.value.is_active } as any)
    toast.success(tenant.value.is_active ? 'Tenant activé.' : 'Tenant désactivé.')
  } catch { /* interceptor */ }
  saving.value = false
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
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Informations</h3>
          <dl class="space-y-3">
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
