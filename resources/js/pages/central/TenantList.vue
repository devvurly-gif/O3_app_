<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTenantStore } from '@/stores/central/useTenantStore'
import { useToastStore } from '@/stores/toastStore'

const router = useRouter()
const store = useTenantStore()
const toast = useToastStore()

const search = ref('')
const planFilter = ref('')

// Build a tenant URL using the SAME protocol as the central admin page.
// In production (https) → https://tenant.o3app.ma. In local dev (http) → http://tenant.o3app.test.
function tenantUrl(domain: string, prefix = ''): string {
  const proto = typeof window !== 'undefined' ? window.location.protocol : 'https:'
  return `${proto}//${prefix}${domain}`
}

onMounted(() => store.fetchAll())

const filtered = computed(() => {
  let list = store.items
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(t =>
      t.name.toLowerCase().includes(q) ||
      t.email.toLowerCase().includes(q) ||
      t.id.toLowerCase().includes(q)
    )
  }
  if (planFilter.value) {
    list = list.filter(t => t.plan === planFilter.value)
  }
  return list
})

const planPrices: Record<string, number> = {
  starter: 499,
  business: 999,
  enterprise: 1999,
}

const stats = computed(() => {
  const active = store.items.filter(t => t.is_active)
  return {
    total: store.items.length,
    active: active.length,
    starter: store.items.filter(t => t.plan === 'starter').length,
    business: store.items.filter(t => t.plan === 'business').length,
    enterprise: store.items.filter(t => t.plan === 'enterprise').length,
    revenue: active.reduce((sum, t) => sum + (planPrices[t.plan] || 0), 0),
  }
})

async function toggleActive(tenant: any) {
  try {
    await store.update(tenant.id, { is_active: !tenant.is_active })
    toast.success(`Tenant ${tenant.is_active ? 'désactivé' : 'activé'}.`)
  } catch { /* interceptor */ }
}

async function deleteTenant(tenant: any) {
  if (!confirm(`Supprimer le tenant "${tenant.name}" et sa base de données ? Cette action est irréversible.`)) return
  try {
    await store.remove(tenant.id)
    toast.success(`Tenant "${tenant.name}" supprimé.`)
  } catch { /* interceptor */ }
}

function getPlanColor(plan: string) {
  return {
    starter: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    business: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    enterprise: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
  }[plan] || 'bg-gray-100 text-gray-700'
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('fr-FR')
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Gestion des Clients</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Panneau d'administration central O3</p>
      </div>
      <button
        @click="router.push('/central/tenants/create')"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Nouveau Client
      </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
      <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
        <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
        <p class="text-sm text-gray-500 dark:text-gray-400">Actifs</p>
        <p class="text-2xl font-bold text-green-600">{{ stats.active }}</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
        <p class="text-sm text-gray-500 dark:text-gray-400">Starter</p>
        <p class="text-2xl font-bold text-gray-600">{{ stats.starter }}</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
        <p class="text-sm text-gray-500 dark:text-gray-400">Business</p>
        <p class="text-2xl font-bold text-blue-600">{{ stats.business }}</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
        <p class="text-sm text-gray-500 dark:text-gray-400">Enterprise</p>
        <p class="text-2xl font-bold text-purple-600">{{ stats.enterprise }}</p>
      </div>
    </div>

    <!-- Revenue Card -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl p-5 text-white">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm text-blue-100">Revenu mensuel estimé</p>
          <p class="text-3xl font-extrabold mt-1">{{ stats.revenue.toLocaleString('fr-FR') }} MAD</p>
          <p class="text-xs text-blue-200 mt-1">Basé sur {{ stats.active }} client(s) actif(s)</p>
        </div>
        <div class="bg-white/20 rounded-full p-3">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
          </svg>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-3">
      <div class="relative flex-1">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
        <input
          v-model="search"
          type="text"
          placeholder="Rechercher par nom, email ou ID..."
          class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        />
      </div>
      <select
        v-model="planFilter"
        class="px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm"
      >
        <option value="">Tous les plans</option>
        <option value="starter">Starter</option>
        <option value="business">Business</option>
        <option value="enterprise">Enterprise</option>
      </select>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div v-if="store.loading" class="flex items-center justify-center py-12">
        <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
      </div>

      <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-900/50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Client</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Domaine</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Plan</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Statut</th>
            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Modules</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Essai expire</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Créé le</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr v-if="filtered.length === 0">
            <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
              Aucun client trouvé.
            </td>
          </tr>
          <tr
            v-for="tenant in filtered"
            :key="tenant.id"
            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
          >
            <td class="px-6 py-4">
              <div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ tenant.name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ tenant.email }}</p>
              </div>
            </td>
            <td class="px-6 py-4">
              <a
                v-if="tenant.domains?.length"
                :href="tenantUrl(tenant.domains[0].domain)"
                target="_blank"
                class="text-sm text-blue-600 hover:underline"
              >
                {{ tenant.domains[0].domain }}
              </a>
              <span v-else class="text-sm text-gray-400">-</span>
            </td>
            <td class="px-6 py-4">
              <span :class="['px-2.5 py-1 text-xs font-medium rounded-full', getPlanColor(tenant.plan)]">
                {{ tenant.plan }}
              </span>
            </td>
            <td class="px-6 py-4">
              <span
                :class="[
                  'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full',
                  tenant.is_active
                    ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                    : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
                ]"
              >
                <span :class="['w-1.5 h-1.5 rounded-full', tenant.is_active ? 'bg-green-500' : 'bg-red-500']" />
                {{ tenant.is_active ? 'Actif' : 'Inactif' }}
              </span>
            </td>
            <td class="px-6 py-4">
              <div class="flex items-center justify-center gap-2">
                <span
                  :class="[
                    'inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full',
                    tenant.pos_enabled
                      ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                      : 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500'
                  ]"
                  :title="tenant.pos_enabled ? 'POS activé' : 'POS désactivé'"
                >
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                  </svg>
                  POS
                </span>
                <span
                  :class="[
                    'inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full',
                    tenant.paiement_bl_enabled
                      ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                      : 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500'
                  ]"
                  :title="tenant.paiement_bl_enabled ? 'Paiement BL activé' : 'Paiement BL désactivé'"
                >
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                  </svg>
                  BL
                </span>
                <span
                  :class="[
                    'inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full',
                    tenant.ecom_enabled
                      ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300'
                      : 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500'
                  ]"
                  :title="tenant.ecom_enabled ? 'eCom activé' : 'eCom désactivé'"
                >
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016A3.001 3.001 0 0021 9.349m-18 0h18" />
                  </svg>
                  eCom
                </span>
              </div>
            </td>
            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
              {{ tenant.trial_ends_at ? formatDate(tenant.trial_ends_at) : '-' }}
            </td>
            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
              {{ formatDate(tenant.created_at) }}
            </td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <button
                  @click="router.push(`/central/tenants/${tenant.id}`)"
                  class="p-1.5 text-gray-400 hover:text-blue-600 transition"
                  title="Voir"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </button>
                <button
                  @click="toggleActive(tenant)"
                  :class="['p-1.5 transition', tenant.is_active ? 'text-gray-400 hover:text-orange-600' : 'text-gray-400 hover:text-green-600']"
                  :title="tenant.is_active ? 'Désactiver' : 'Activer'"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
                  </svg>
                </button>
                <button
                  @click="deleteTenant(tenant)"
                  class="p-1.5 text-gray-400 hover:text-red-600 transition"
                  title="Supprimer"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
