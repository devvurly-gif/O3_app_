<template>
  <div class="max-w-2xl mx-auto space-y-6">
    <!-- Profile Info -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Informations personnelles</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Modifier votre nom et adresse email</p>
      </div>

      <form class="p-6 space-y-5" @submit.prevent="updateProfile">
        <div>
          <label for="profile-profileform-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Nom complet</label>
          <input
            id="profile-profileform-name"
            v-model="profileForm.name"
            type="text"
            required
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
          />
        </div>

        <div>
          <label for="profile-profileform-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email</label>
          <input
            id="profile-profileform-email"
            v-model="profileForm.email"
            type="email"
            required
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
          />
        </div>

        <div class="flex items-center gap-3">
          <div class="flex-1">
            <label for="profile-r-le" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Rôle</label>
            <input
              id="profile-r-le"
              :value="auth.userRole"
              type="text"
              disabled
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-sm text-gray-500 dark:text-gray-400 cursor-not-allowed"
            />
          </div>
        </div>

        <div class="flex justify-end">
          <button
            type="submit"
            :disabled="savingProfile"
            class="px-5 py-2.5 bg-orange-700 hover:bg-orange-800 text-white text-sm font-semibold rounded-lg transition disabled:opacity-50"
          >
            {{ savingProfile ? 'Enregistrement...' : 'Enregistrer' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Change Password -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Changer le mot de passe</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Assurez-vous d'utiliser un mot de passe sécurisé</p>
      </div>

      <form class="p-6 space-y-5" @submit.prevent="updatePassword">
        <div>
          <label for="profile-passwordform-current-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Mot de passe actuel</label>
          <input
            id="profile-passwordform-current-password"
            v-model="passwordForm.current_password"
            type="password"
            required
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
          />
        </div>

        <div>
          <label for="profile-passwordform-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Nouveau mot de passe</label>
          <input
            id="profile-passwordform-password"
            v-model="passwordForm.password"
            type="password"
            required
            minlength="8"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
          />
        </div>

        <div>
          <label for="profile-passwordform-password-confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Confirmer le mot de passe</label>
          <input
            id="profile-passwordform-password-confirmation"
            v-model="passwordForm.password_confirmation"
            type="password"
            required
            minlength="8"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
          />
        </div>

        <div class="flex justify-end">
          <button
            type="submit"
            :disabled="savingPassword"
            class="px-5 py-2.5 bg-orange-700 hover:bg-orange-800 text-white text-sm font-semibold rounded-lg transition disabled:opacity-50"
          >
            {{ savingPassword ? 'Modification...' : 'Modifier le mot de passe' }}
          </button>
        </div>
      </form>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import { useToastStore } from '@/stores/toastStore'
import http from '@/services/http'

const auth = useAuthStore()
const toast = useToastStore()

const profileForm = reactive({
  name: '',
  email: '',
})

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const savingProfile = ref(false)
const savingPassword = ref(false)

onMounted(() => {
  profileForm.name = auth.userName
  profileForm.email = auth.userEmail
})

async function updateProfile() {
  savingProfile.value = true
  try {
    const { data } = await http.put('/auth/profile', profileForm)
    auth.setUser(data)
    toast.success('Profil mis à jour.')
  } catch { /* Axios interceptor shows toast */ }
  savingProfile.value = false
}

async function updatePassword() {
  savingPassword.value = true
  try {
    await http.put('/auth/profile/password', passwordForm)
    passwordForm.current_password = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
    toast.success('Mot de passe modifié avec succès.')
  } catch { /* Axios interceptor shows toast */ }
  savingPassword.value = false
}
</script>
