<script setup lang="ts">
import { ref, onMounted } from 'vue'

const email  = ref('')
const domain = ref('')

onMounted(() => {
  try {
    const raw = sessionStorage.getItem('o3.signup.pending')
    if (raw) {
      const parsed = JSON.parse(raw) as { email?: string; domain?: string }
      email.value  = parsed.email  ?? ''
      domain.value = parsed.domain ?? ''
    }
  } catch { /* noop */ }
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 flex items-center justify-center px-4">
    <div class="max-w-lg w-full bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
      <div class="w-16 h-16 mx-auto mb-5 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
        <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
        </svg>
      </div>

      <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">
        Vérifiez votre email
      </h1>
      <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
        Nous avons envoyé un lien d'activation à
        <span v-if="email" class="font-semibold text-gray-900 dark:text-white">{{ email }}</span>
        <span v-else>votre adresse email</span>.
        Cliquez dessus pour activer votre espace
        <span v-if="domain" class="font-mono text-blue-600 dark:text-blue-400">{{ domain }}</span>.
      </p>

      <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 text-left text-sm text-blue-900 dark:text-blue-200 mb-6">
        <p class="font-semibold mb-1">⏱️ Le lien est valide 24 heures</p>
        <p class="text-blue-700/80 dark:text-blue-300/80">
          Pas reçu ? Vérifiez votre dossier spam, ou réessayez l'inscription dans 24h
          (l'espace en attente sera automatiquement supprimé).
        </p>
      </div>

      <a
        href="/login"
        class="inline-block w-full py-3 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
      >
        Retour à la connexion
      </a>
    </div>
  </div>
</template>
