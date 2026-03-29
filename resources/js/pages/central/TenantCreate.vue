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
})

// Auto-generate domain from ID
function onIdInput() {
  form.value.id = form.value.id.toLowerCase().replace(/[^a-z0-9-]/g, '')
  form.value.domain = form.value.id ? `${form.value.id}.161.35.19.66.nip.io` : ''
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

const plans = [
  {
    value: 'starter',
    label: 'Starter',
    desc: 'Ventes + Stock',
    price: '299 MAD/mois',
    color: 'border-gray-300 dark:border-gray-600',
    selectedColor: 'border-gray-500 bg-gray-50 dark:bg-gray-700',
  },
  {
    value: 'business',
    label: 'Business',
    desc: 'Ventes + Achats + Stock + POS',
    price: '499 MAD/mois',
    color: 'border-gray-300 dark:border-gray-600',
    selectedColor: 'border-blue-500 bg-blue-50 dark:bg-blue-900/30',
  },
  {
    value: 'enterprise',
    label: 'Enterprise',
    desc: 'Tout + eCom + WhatsApp',
    price: '999 MAD/mois',
    color: 'border-gray-300 dark:border-gray-600',
    selectedColor: 'border-purple-500 bg-purple-50 dark:bg-purple-900/30',
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
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Plan</label>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <button
            v-for="plan in plans"
            :key="plan.value"
            type="button"
            @click="form.plan = plan.value"
            :class="[
              'relative rounded-xl border-2 p-4 text-left transition cursor-pointer',
              form.plan === plan.value ? plan.selectedColor : plan.color,
            ]"
          >
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ plan.label }}</span>
              <span
                v-if="form.plan === plan.value"
                class="w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center"
              >
                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
              </span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ plan.desc }}</p>
            <p class="text-sm font-bold text-gray-900 dark:text-white mt-2">{{ plan.price }}</p>
          </button>
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
