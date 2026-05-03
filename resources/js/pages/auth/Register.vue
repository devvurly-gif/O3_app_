<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import http from '@/services/http'

const router = useRouter()

// Build the central domain from window so the same code works on
// o3app.ma (prod), o3app.test (Laragon) and any nip.io dev URL.
const centralDomain = computed(() => {
  const host = typeof window !== 'undefined' ? window.location.hostname : 'o3app.ma'
  return host.replace(/^www\./, '')
})

const form = reactive({
  company_name:          '',
  admin_name:            '',
  email:                 '',
  phone:                 '',
  tenant_id:             '',
  password:              '',
  password_confirmation: '',
  accept_terms:          false,
})

const errors    = ref<Record<string, string[]>>({})
const submitting = ref(false)
const generalError = ref('')

// Live sub-domain availability check ------------------------------------
const subdomainStatus = ref<'idle' | 'checking' | 'available' | 'taken'>('idle')
const subdomainReason = ref('')
let subdomainTimer: ReturnType<typeof setTimeout> | null = null

function autoSuggestSubdomain() {
  // Only auto-suggest if the user hasn't typed in the sub-domain yet.
  if (form.tenant_id) return
  const slug = form.company_name
    .toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '') // strip accents
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 31)
  if (slug.length >= 3) form.tenant_id = slug
}

watch(() => form.company_name, autoSuggestSubdomain)

watch(() => form.tenant_id, (id) => {
  // Force-lowercase + strip invalid chars as the user types
  const cleaned = id.toLowerCase().replace(/[^a-z0-9-]/g, '')
  if (cleaned !== id) {
    form.tenant_id = cleaned
    return
  }

  if (subdomainTimer) clearTimeout(subdomainTimer)
  if (!cleaned || cleaned.length < 3) {
    subdomainStatus.value = 'idle'
    subdomainReason.value = ''
    return
  }
  subdomainStatus.value = 'checking'
  subdomainTimer = setTimeout(async () => {
    try {
      const { data } = await http.get('/central/register/check-subdomain', {
        params: { id: cleaned },
      })
      if (cleaned !== form.tenant_id) return // user kept typing
      subdomainStatus.value = data.available ? 'available' : 'taken'
      subdomainReason.value = data.reason ?? ''
    } catch {
      subdomainStatus.value = 'idle'
    }
  }, 350)
})

// Submit ----------------------------------------------------------------
async function submit() {
  generalError.value = ''
  errors.value = {}
  submitting.value = true
  try {
    const { data } = await http.post('/central/register', form)
    // Persist email + domain for the "we sent you a link" confirmation
    sessionStorage.setItem(
      'o3.signup.pending',
      JSON.stringify({ email: data.email, domain: data.domain }),
    )
    router.push('/register/sent')
  } catch (err: unknown) {
    type ApiError = { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const e = err as ApiError
    errors.value      = e.response?.data?.errors ?? {}
    generalError.value = e.response?.data?.message ?? 'Une erreur est survenue. Réessayez.'
  } finally {
    submitting.value = false
  }
}

const passwordsMatch = computed(
  () => form.password.length === 0 || form.password === form.password_confirmation,
)

const canSubmit = computed(() =>
  !submitting.value
    && form.company_name.trim().length > 1
    && form.admin_name.trim().length > 1
    && /^.+@.+\..+$/.test(form.email)
    && subdomainStatus.value === 'available'
    && form.password.length >= 8
    && passwordsMatch.value
    && form.accept_terms,
)
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 py-10 px-4">
    <div class="max-w-2xl mx-auto">
      <!-- Header -->
      <div class="text-center mb-8">
        <a href="/" class="inline-flex items-center gap-2 mb-6">
          <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white font-bold">O3</div>
          <span class="text-xl font-bold text-gray-900 dark:text-white">O3 App</span>
        </a>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Créez votre espace</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400">Essai gratuit de 14 jours · sans carte bancaire</p>
      </div>

      <form
        class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-6 sm:p-8 space-y-5"
        @submit.prevent="submit"
      >
        <!-- General error -->
        <div v-if="generalError" class="p-3 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-400">
          {{ generalError }}
        </div>

        <!-- Company info -->
        <div class="space-y-4">
          <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Votre entreprise</h2>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Raison sociale <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.company_name"
              type="text"
              required
              maxlength="255"
              placeholder="Ex: Teliphoni SARL"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <p v-if="errors.company_name" class="mt-1 text-xs text-red-500">{{ errors.company_name[0] }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Sous-domaine <span class="text-red-500">*</span>
            </label>
            <div class="flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-transparent">
              <input
                v-model="form.tenant_id"
                type="text"
                required
                minlength="3"
                maxlength="31"
                placeholder="monentreprise"
                class="flex-1 px-3.5 py-2.5 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white focus:outline-none"
              />
              <span class="flex items-center px-3 bg-gray-50 dark:bg-gray-700 text-sm font-mono text-gray-500 dark:text-gray-400 border-l border-gray-300 dark:border-gray-600">
                .{{ centralDomain }}
              </span>
            </div>

            <p v-if="subdomainStatus === 'checking'" class="mt-1 text-xs text-gray-400">Vérification…</p>
            <p v-else-if="subdomainStatus === 'available'" class="mt-1 text-xs text-green-600 dark:text-green-400 flex items-center gap-1">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              {{ form.tenant_id }}.{{ centralDomain }} est disponible
            </p>
            <p v-else-if="subdomainStatus === 'taken'" class="mt-1 text-xs text-red-500">{{ subdomainReason || 'Indisponible' }}</p>
            <p v-else class="mt-1 text-xs text-gray-400">3 à 31 caractères, minuscules / chiffres / tirets uniquement</p>
            <p v-if="errors.tenant_id" class="mt-1 text-xs text-red-500">{{ errors.tenant_id[0] }}</p>
          </div>
        </div>

        <!-- Contact -->
        <div class="space-y-4">
          <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Votre compte administrateur</h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Nom et prénom <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.admin_name"
                type="text"
                required
                maxlength="255"
                placeholder="Ex: Hassan Alami"
                class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              <p v-if="errors.admin_name" class="mt-1 text-xs text-red-500">{{ errors.admin_name[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Téléphone
              </label>
              <input
                v-model="form.phone"
                type="tel"
                maxlength="30"
                placeholder="+212 6XX XX XX XX"
                class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Email professionnel <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.email"
              type="email"
              required
              maxlength="255"
              placeholder="vous@monentreprise.ma"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <p class="mt-1 text-xs text-gray-400">Un lien de vérification y sera envoyé.</p>
            <p v-if="errors.email" class="mt-1 text-xs text-red-500">{{ errors.email[0] }}</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Mot de passe <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.password"
                type="password"
                required
                minlength="8"
                placeholder="8 caractères minimum"
                class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              <p v-if="errors.password" class="mt-1 text-xs text-red-500">{{ errors.password[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Confirmation <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.password_confirmation"
                type="password"
                required
                placeholder="Retapez le mot de passe"
                class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              <p v-if="!passwordsMatch" class="mt-1 text-xs text-red-500">Les mots de passe ne correspondent pas.</p>
            </div>
          </div>
        </div>

        <!-- Terms -->
        <label class="flex items-start gap-3 p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 cursor-pointer">
          <input
            v-model="form.accept_terms"
            type="checkbox"
            class="mt-0.5 w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          <span class="text-sm text-gray-700 dark:text-gray-300">
            J'accepte les
            <a href="/legal/cgs" target="_blank" class="text-blue-600 hover:underline">Conditions Générales de Service</a>
            et la
            <a href="/legal/privacy" target="_blank" class="text-blue-600 hover:underline">Politique de confidentialité</a>
            (loi marocaine 09-08).
          </span>
        </label>
        <p v-if="errors.accept_terms" class="-mt-2 text-xs text-red-500">{{ errors.accept_terms[0] }}</p>

        <!-- Submit -->
        <button
          type="submit"
          :disabled="!canSubmit"
          class="w-full py-3 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
          </svg>
          {{ submitting ? 'Création en cours…' : 'Créer mon espace' }}
        </button>

        <p class="text-center text-xs text-gray-500 dark:text-gray-400">
          Déjà un compte ?
          <a href="/login" class="text-blue-600 hover:underline font-medium">Se connecter</a>
        </p>
      </form>

      <p class="text-center text-xs text-gray-400 mt-6">
        🇲🇦 Hébergé en Europe · Conforme loi 09-08 · Sauvegarde quotidienne
      </p>
    </div>
  </div>
</template>
