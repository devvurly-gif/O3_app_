<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTenantStore } from '@/stores/central/useTenantStore'
import { useToastStore } from '@/stores/toastStore'

const router = useRouter()
const store = useTenantStore()
const toast = useToastStore()

const loading = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref({
  id: '',
  name: '',
  email: '',
  domain: '',
  plan: 'starter',
  admin_password: '',
  pos_enabled: false,
  paiement_bl_enabled: false,
  ecom_enabled: false,
})

// Auto-generate domain from ID
function onIdInput() {
  form.value.id = form.value.id.toLowerCase().replace(/[^a-z0-9-]/g, '')
  const host = window.location.hostname
  const baseDomain = (host === 'localhost' || host === '127.0.0.1') ? `${host}.nip.io` : host
  form.value.domain = form.value.id ? `${form.value.id}.${baseDomain}` : ''
}

async function onSubmit() {
  loading.value = true
  errors.value = {}

  try {
    const tenant = await store.create(form.value)
    toast.success(`Client "${tenant.name}" créé avec succès.`)
    router.push('/central/tenants')
  } catch (e: any) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors || {}
    }
  } finally {
    loading.value = false
  }
}

function onPlanChange(plan: string) {
  form.value.plan = plan
  // Auto-enable POS for business/enterprise plans
  if (plan === 'business' || plan === 'enterprise') {
    form.value.pos_enabled = true
  }
  // Auto-enable eCom for enterprise plan
  if (plan === 'enterprise') {
    form.value.ecom_enabled = true
  }
}

const toggleClass = 'w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[\'\'] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 dark:after:border-gray-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600'

const plans = [
  {
    value: 'starter',
    label: 'Starter',
    desc: 'Ventes + Stock',
    features: ['Gestion des ventes', 'Gestion du stock', 'Sous-domaine gratuit', '500 emails/mois', 'Support email'],
    price: '499',
    color: 'border-gray-300 dark:border-gray-600',
    selectedColor: 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20',
    badge: '',
  },
  {
    value: 'business',
    label: 'Business',
    desc: 'Ventes + Achats + Stock + POS',
    features: ['Tout le plan Starter', 'Gestion des achats', 'Point de vente (POS)', 'Domaine personnalisé', '2000 emails/mois', '200 WhatsApp/mois', 'Support prioritaire'],
    price: '999',
    color: 'border-gray-300 dark:border-gray-600',
    selectedColor: 'border-blue-500 bg-blue-50 dark:bg-blue-900/20',
    badge: 'Populaire',
  },
  {
    value: 'enterprise',
    label: 'Enterprise',
    desc: 'Tout + eCom + WhatsApp illimité',
    features: ['Tout le plan Business', 'Module e-Commerce', 'WhatsApp illimité', 'Emails illimités', 'Domaine personnalisé (.ma)', 'Backup quotidien', 'Formation 2h incluse', 'Support téléphonique'],
    price: '1999',
    color: 'border-gray-300 dark:border-gray-600',
    selectedColor: 'border-purple-500 bg-purple-50 dark:bg-purple-900/20',
    badge: 'Premium',
  },
]
</script>

<template>
  <div class="max-w-2xl mx-auto space-y-6">
    <!-- Header -->
    <div>
      <button @click="router.push('/central/tenants')" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </svg>
        Retour
      </button>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Nouveau Client</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Créer un nouveau tenant avec sa base de données isolée</p>
    </div>

    <!-- Form -->
    <form @submit.prevent="onSubmit" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-6">
      <!-- ID -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Identifiant unique</label>
        <input
          v-model="form.id"
          @input="onIdInput"
          type="text"
          required
          placeholder="acme"
          class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500"
        />
        <p class="text-xs text-gray-400 mt-1">Lettres minuscules, chiffres et tirets uniquement. Sert d'identifiant et de préfixe DB.</p>
        <p v-if="errors.id" class="text-xs text-red-500 mt-1">{{ errors.id[0] }}</p>
      </div>

      <!-- Name -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom de l'entreprise</label>
        <input
          v-model="form.name"
          type="text"
          required
          placeholder="Acme Corporation"
          class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500"
        />
        <p v-if="errors.name" class="text-xs text-red-500 mt-1">{{ errors.name[0] }}</p>
      </div>

      <!-- Email -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email administrateur</label>
        <input
          v-model="form.email"
          type="email"
          required
          placeholder="admin@acme.com"
          class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500"
        />
        <p v-if="errors.email" class="text-xs text-red-500 mt-1">{{ errors.email[0] }}</p>
      </div>

      <!-- Domain (auto-generated) -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Domaine d'accès</label>
        <input
          v-model="form.domain"
          type="text"
          required
          placeholder="acme.o3app.com"
          class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500"
        />
        <p class="text-xs text-gray-400 mt-1">Le client accédera à l'application via ce domaine.</p>
        <p v-if="errors.domain" class="text-xs text-red-500 mt-1">{{ errors.domain[0] }}</p>
      </div>

      <!-- Password -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mot de passe admin</label>
        <input
          v-model="form.admin_password"
          type="password"
          required
          minlength="6"
          placeholder="Minimum 6 caractères"
          class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500"
        />
        <p v-if="errors.admin_password" class="text-xs text-red-500 mt-1">{{ errors.admin_password[0] }}</p>
      </div>

      <!-- Plan Selection -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Plan d'abonnement</label>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <button
            v-for="plan in plans"
            :key="plan.value"
            type="button"
            @click="onPlanChange(plan.value)"
            :class="[
              'relative rounded-xl border-2 p-5 text-left transition cursor-pointer',
              form.plan === plan.value ? plan.selectedColor : plan.color,
            ]"
          >
            <!-- Badge -->
            <span
              v-if="plan.badge"
              :class="[
                'absolute -top-2.5 right-3 px-2.5 py-0.5 text-xs font-bold rounded-full',
                plan.value === 'business' ? 'bg-blue-600 text-white' : 'bg-purple-600 text-white',
              ]"
            >{{ plan.badge }}</span>

            <!-- Header -->
            <div class="flex items-center justify-between mb-1">
              <span class="text-base font-bold text-gray-900 dark:text-white">{{ plan.label }}</span>
              <span
                v-if="form.plan === plan.value"
                class="w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center"
              >
                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
              </span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ plan.desc }}</p>

            <!-- Price -->
            <div class="mb-3">
              <span class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ plan.price }}</span>
              <span class="text-sm text-gray-500 dark:text-gray-400"> MAD/mois</span>
            </div>

            <!-- Features -->
            <ul class="space-y-1.5">
              <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2 text-xs text-gray-600 dark:text-gray-400">
                <svg class="w-3.5 h-3.5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                {{ feature }}
              </li>
            </ul>
          </button>
        </div>
      </div>

      <!-- Feature Flags -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Modules & Options</label>
        <div class="space-y-4 bg-gray-50 dark:bg-gray-900/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
          <!-- POS Toggle -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center" :class="form.pos_enabled ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-gray-200 dark:bg-gray-700'">
                <svg :class="['w-5 h-5', form.pos_enabled ? 'text-blue-600' : 'text-gray-400']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Point de Vente (POS)</p>
                <p class="text-xs text-gray-400">Caisse, tickets, sessions</p>
              </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input v-model="form.pos_enabled" type="checkbox" class="sr-only peer" />
              <div :class="toggleClass"></div>
            </label>
          </div>

          <!-- Paiement BL Toggle -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center" :class="form.paiement_bl_enabled ? 'bg-green-100 dark:bg-green-900/30' : 'bg-gray-200 dark:bg-gray-700'">
                <svg :class="['w-5 h-5', form.paiement_bl_enabled ? 'text-green-600' : 'text-gray-400']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                </svg>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Paiement sur Bon de Livraison</p>
                <p class="text-xs text-gray-400">Paiements directement sur les BL</p>
              </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input v-model="form.paiement_bl_enabled" type="checkbox" class="sr-only peer" />
              <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 dark:after:border-gray-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
            </label>
          </div>

          <!-- eCom Toggle -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center" :class="form.ecom_enabled ? 'bg-indigo-100 dark:bg-indigo-900/30' : 'bg-gray-200 dark:bg-gray-700'">
                <svg :class="['w-5 h-5', form.ecom_enabled ? 'text-indigo-600' : 'text-gray-400']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016A3.001 3.001 0 0021 9.349m-18 0h18" />
                </svg>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Boutique en Ligne (eCom)</p>
                <p class="text-xs text-gray-400">Catalogue, panier, commandes en ligne</p>
              </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input v-model="form.ecom_enabled" type="checkbox" class="sr-only peer" />
              <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 dark:after:border-gray-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
            </label>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
        <button
          type="button"
          @click="router.push('/central/tenants')"
          class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition"
        >
          Annuler
        </button>
        <button
          type="submit"
          :disabled="loading"
          class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition"
        >
          <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          {{ loading ? 'Création...' : 'Créer le client' }}
        </button>
      </div>
    </form>
  </div>
</template>
