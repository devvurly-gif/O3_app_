<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import http from '@/services/http'

const route = useRoute()

type Status = 'loading' | 'success' | 'error'
const status = ref<Status>('loading')
const errorMessage = ref('')

interface VerifyResponse {
  message:    string
  tenant_id:  string
  name:       string
  email:      string
  login_url:  string | null
}
const result = ref<VerifyResponse | null>(null)

onMounted(async () => {
  const token = (route.query.token as string | undefined) ?? ''
  if (!token || token.length !== 64) {
    status.value = 'error'
    errorMessage.value = 'Lien invalide.'
    return
  }
  try {
    const { data } = await http.post<VerifyResponse>('/central/register/verify', { token })
    result.value = data
    status.value = 'success'
    // Clear the "pending" marker from sessionStorage so the user
    // doesn't see the "check your email" page again on back-button.
    try { sessionStorage.removeItem('o3.signup.pending') } catch { /* noop */ }
  } catch (err: unknown) {
    type ApiError = { response?: { data?: { message?: string } } }
    const e = err as ApiError
    status.value = 'error'
    errorMessage.value = e.response?.data?.message ?? "Lien invalide ou expiré."
  }
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-green-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 flex items-center justify-center px-4">
    <div class="max-w-lg w-full bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-8 text-center">

      <!-- Loading -->
      <template v-if="status === 'loading'">
        <div class="w-16 h-16 mx-auto mb-5 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
          <svg class="w-8 h-8 text-blue-600 dark:text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Activation en cours…</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Cela ne devrait prendre qu'une seconde.</p>
      </template>

      <!-- Success -->
      <template v-else-if="status === 'success' && result">
        <div class="w-16 h-16 mx-auto mb-5 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
          <svg class="w-9 h-9 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
          Bienvenue, {{ result.name }} 🎉
        </h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
          Votre espace a été activé. 14 jours d'essai gratuit démarrent maintenant.
        </p>

        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 mb-6 text-left space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Identifiant tenant</span>
            <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ result.tenant_id }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Email de connexion</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ result.email }}</span>
          </div>
        </div>

        <a
          v-if="result.login_url"
          :href="result.login_url"
          class="inline-flex items-center justify-center gap-2 w-full py-3.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition shadow-lg shadow-blue-600/25"
        >
          Accéder à mon espace
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
          </svg>
        </a>
      </template>

      <!-- Error -->
      <template v-else>
        <div class="w-16 h-16 mx-auto mb-5 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
          <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Activation impossible</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">{{ errorMessage }}</p>
        <a
          href="/register"
          class="inline-block w-full py-3 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition"
        >
          Recommencer l'inscription
        </a>
      </template>

    </div>
  </div>
</template>
