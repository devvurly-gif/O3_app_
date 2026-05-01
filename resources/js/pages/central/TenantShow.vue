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

// Build a tenant URL using the SAME protocol as the central admin page.
// In production (central is on https) → returns https://tenant.o3app.ma
// In local dev (Laragon http) → returns http://tenant.o3app.test
// This avoids hardcoding "http://" which broke the link in prod.
function tenantUrl(domain: string, prefix = ''): string {
  const proto = typeof window !== 'undefined' ? window.location.protocol : 'https:'
  return `${proto}//${prefix}${domain}`
}

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
    const label = feature === 'pos_enabled' ? 'POS' : feature === 'ecom_enabled' ? 'Boutique eCom' : 'Paiement sur BL'
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

// Database reset
const resettingDb = ref(false)
const resetConfirmText = ref('')
const resetAdminPassword = ref('')
const showResetPassword = ref(false)

async function resetDatabase() {
  if (!tenant.value) return
  if (resetConfirmText.value !== 'RESET') {
    toast.error('Tapez RESET pour confirmer.')
    return
  }
  if (!resetAdminPassword.value || resetAdminPassword.value.length < 6) {
    toast.error('Le mot de passe admin doit contenir au moins 6 caractères.')
    return
  }
  resettingDb.value = true
  try {
    const msg = await store.resetDatabase(tenant.value.id, resetAdminPassword.value)
    toast.success(msg)
    resetConfirmText.value = ''
    resetAdminPassword.value = ''
    showResetPassword.value = false
  } catch (err: any) {
    toast.error(err?.response?.data?.message || 'Erreur lors de la réinitialisation.')
  }
  resettingDb.value = false
}

// Purge files
const purgingFiles = ref(false)
const purgeImages = ref(true)
const purgePdfs = ref(true)

async function purgeFiles() {
  if (!tenant.value) return
  const types: ('images' | 'pdfs')[] = []
  if (purgeImages.value) types.push('images')
  if (purgePdfs.value) types.push('pdfs')
  if (types.length === 0) {
    toast.error('Sélectionnez au moins un type de fichier.')
    return
  }
  const labels = types.map(t => t === 'images' ? 'images' : 'PDFs').join(' et ')
  if (!confirm(`Supprimer tous les ${labels} de "${tenant.value.name}" ? Cette action est irréversible !`)) return
  purgingFiles.value = true
  try {
    const result = await store.purgeFiles(tenant.value.id, types)
    toast.success(result.message)
  } catch (err: any) {
    toast.error(err?.response?.data?.message || 'Erreur lors de la suppression.')
  }
  purgingFiles.value = false
}

// Product import from URL
const scrapeUrl = ref('')
const scrapeCategory = ref('Import')
const scraping = ref(false)
const importing = ref(false)
const scrapedProducts = ref<any[]>([])
const scrapedSource = ref('')
const selectedProducts = ref<Set<number>>(new Set())

async function scrapeProducts() {
  if (!scrapeUrl.value) return
  scraping.value = true
  scrapedProducts.value = []
  try {
    const result = await store.scrapeProducts(scrapeUrl.value)
    scrapedProducts.value = result.products
    scrapedSource.value = result.source
    // Select all by default
    selectedProducts.value = new Set(result.products.map((_: any, i: number) => i))
    if (result.count === 0) toast.error('Aucun produit trouvé sur cette page.')
    else toast.success(`${result.count} produit(s) trouvé(s) depuis ${result.source}`)
  } catch (err: any) {
    toast.error(err?.response?.data?.message || 'Erreur lors du scraping.')
  }
  scraping.value = false
}

function toggleProduct(index: number) {
  if (selectedProducts.value.has(index)) selectedProducts.value.delete(index)
  else selectedProducts.value.add(index)
  selectedProducts.value = new Set(selectedProducts.value)
}

function toggleAllProducts() {
  if (selectedProducts.value.size === scrapedProducts.value.length) {
    selectedProducts.value = new Set()
  } else {
    selectedProducts.value = new Set(scrapedProducts.value.map((_: any, i: number) => i))
  }
}

async function importProducts() {
  if (!tenant.value || selectedProducts.value.size === 0) return
  importing.value = true
  toast.success(`Import de ${selectedProducts.value.size} produit(s) lancé... Patientez, téléchargement des images en cours.`)
  try {
    const toImport = scrapedProducts.value.filter((_: any, i: number) => selectedProducts.value.has(i))
    const result = await store.importProducts(tenant.value.id, toImport, scrapeCategory.value)
    toast.success(result.message)
    if (result.errors?.length) result.errors.forEach((e: string) => toast.error(e))
    // Clear after success
    scrapedProducts.value = []
    selectedProducts.value = new Set()
    scrapeUrl.value = ''
  } catch (err: any) {
    const msg = err?.response?.data?.message || err?.message || "Erreur lors de l'import."
    toast.error(`Import échoué: ${msg}`)
    console.error('Import error:', err)
  }
  importing.value = false
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

// ── Contrat de services ─────────────────────────────────────────
const downloadingContract = ref(false)
const showSendContractModal = ref(false)
const sendingContract = ref(false)
const sendContractForm = reactive({
  to: '',
  cc: '',
  message: '',
  include_intake_form: true,
})

async function downloadContract(doc: 'contrat' | 'fiche' = 'contrat') {
  if (!tenant.value) return
  downloadingContract.value = true
  try {
    await store.downloadContract(tenant.value.id, doc)
    toast.success(doc === 'fiche' ? 'Fiche téléchargée.' : 'Contrat téléchargé.')
  } catch (err: any) {
    toast.error(err?.response?.data?.message || 'Erreur lors du téléchargement.')
  }
  downloadingContract.value = false
}

function openSendContractModal() {
  if (!tenant.value) return
  sendContractForm.to = tenant.value.email || ''
  sendContractForm.cc = ''
  sendContractForm.message = ''
  sendContractForm.include_intake_form = true
  showSendContractModal.value = true
}

async function sendContract() {
  if (!tenant.value) return
  if (!sendContractForm.to) {
    toast.error('Renseignez l\'email du destinataire.')
    return
  }
  sendingContract.value = true
  try {
    const cc = sendContractForm.cc
      .split(/[,;\s]+/)
      .map(s => s.trim())
      .filter(Boolean)
    const result = await store.sendContract(tenant.value.id, {
      to: sendContractForm.to,
      cc: cc.length ? cc : undefined,
      message: sendContractForm.message || undefined,
      include_intake_form: sendContractForm.include_intake_form,
    })
    toast.success(result.message)
    showSendContractModal.value = false
  } catch (err: any) {
    toast.error(err?.response?.data?.message || 'Erreur lors de l\'envoi.')
  }
  sendingContract.value = false
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
            :href="tenantUrl(tenant.domains[0].domain)"
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

          <!-- eCom Toggle -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="tenant.ecom_enabled ? 'bg-indigo-100 dark:bg-indigo-900/30' : 'bg-gray-100 dark:bg-gray-700'">
                <svg :class="['w-5 h-5', tenant.ecom_enabled ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016A3.001 3.001 0 0021 9.349m-18 0h18" />
                </svg>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Boutique en Ligne (eCom)</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">Activer la boutique en ligne pour ce tenant</p>
              </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                :checked="tenant.ecom_enabled"
                @change="toggleFeature('ecom_enabled', !tenant.ecom_enabled)"
                class="sr-only peer"
                :disabled="saving"
              />
              <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 dark:after:border-gray-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
            </label>
          </div>

          <!-- eCom Shop URL (shown when enabled) -->
          <div v-if="tenant.ecom_enabled && tenant.domains?.length" class="ml-13 pl-1 border-l-2 border-indigo-200 dark:border-indigo-800">
            <div class="flex items-center gap-2 py-2 pl-3">
              <span class="text-xs text-gray-500 dark:text-gray-400">URL Boutique :</span>
              <a
                :href="tenantUrl(tenant.domains[0].domain, 'shop.')"
                target="_blank"
                class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
              >
                shop.{{ tenant.domains[0].domain }}
              </a>
            </div>
            <div class="flex items-center gap-2 py-1 pl-3">
              <span class="text-xs text-gray-500 dark:text-gray-400">API Key :</span>
              <code class="text-xs font-mono text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">
                {{ tenant.ecom_api_key ? tenant.ecom_api_key.substring(0, 15) + '...' : '-' }}
              </code>
            </div>
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

      <!-- Maintenance — Reset DB & Purge Files -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-red-200 dark:border-red-900/50 p-5">
        <div class="flex items-center gap-2 mb-4">
          <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
          </svg>
          <h3 class="text-sm font-semibold text-red-600 dark:text-red-400">Zone de maintenance</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <!-- Reset Database -->
          <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-3">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75" />
              </svg>
              <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Réinitialiser la base de données</h4>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              Supprime toutes les données et recrée l'admin avec les infos du tenant (<strong>{{ tenant?.email }}</strong>).
            </p>
            <div class="space-y-2">
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">
                Mot de passe admin après réinitialisation
              </label>
              <div class="relative">
                <input
                  v-model="resetAdminPassword"
                  :type="showResetPassword ? 'text' : 'password'"
                  placeholder="Min. 6 caractères"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition"
                />
                <button
                  type="button"
                  @click="showResetPassword = !showResetPassword"
                  class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                >
                  <svg v-if="!showResetPassword" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                </button>
              </div>
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">
                Tapez <span class="font-mono font-bold text-red-500">RESET</span> pour confirmer
              </label>
              <input
                v-model="resetConfirmText"
                type="text"
                placeholder="RESET"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition font-mono"
              />
              <button
                @click="resetDatabase"
                :disabled="resettingDb || resetConfirmText !== 'RESET' || resetAdminPassword.length < 6"
                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <svg v-if="resettingDb" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                </svg>
                Réinitialiser la BDD
              </button>
            </div>
          </div>

          <!-- Purge Files -->
          <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-3">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
              </svg>
              <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Supprimer les fichiers</h4>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              Supprime les images produits et/ou les fichiers PDF (factures, bons...) du stockage tenant.
            </p>
            <div class="space-y-2">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="purgeImages"
                  type="checkbox"
                  class="w-4 h-4 text-red-600 bg-gray-100 border-gray-300 rounded focus:ring-red-500 dark:bg-gray-700 dark:border-gray-600"
                />
                <span class="text-sm text-gray-700 dark:text-gray-300">
                  <svg class="w-4 h-4 inline text-blue-500 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a1.5 1.5 0 001.5-1.5v-13.5a1.5 1.5 0 00-1.5-1.5H3.75a1.5 1.5 0 00-1.5 1.5v13.5a1.5 1.5 0 001.5 1.5z" />
                  </svg>
                  Images (produits, photos)
                </span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="purgePdfs"
                  type="checkbox"
                  class="w-4 h-4 text-red-600 bg-gray-100 border-gray-300 rounded focus:ring-red-500 dark:bg-gray-700 dark:border-gray-600"
                />
                <span class="text-sm text-gray-700 dark:text-gray-300">
                  <svg class="w-4 h-4 inline text-red-400 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                  </svg>
                  PDFs (factures, bons, devis)
                </span>
              </label>
              <button
                @click="purgeFiles"
                :disabled="purgingFiles || (!purgeImages && !purgePdfs)"
                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <svg v-if="purgingFiles" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
                Supprimer les fichiers
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Import Products from URL -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-blue-200 dark:border-blue-900/50 p-5">
        <div class="flex items-center gap-2 mb-4">
          <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
          </svg>
          <h3 class="text-sm font-semibold text-blue-600 dark:text-blue-400">Importer des produits depuis un site e-commerce</h3>
        </div>

        <!-- Step 1: URL Input -->
        <div class="space-y-3">
          <div class="flex gap-2">
            <input
              v-model="scrapeUrl"
              type="url"
              placeholder="https://example.com/collections/smartphones"
              class="flex-1 px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              @keyup.enter="scrapeProducts"
            />
            <button
              @click="scrapeProducts"
              :disabled="scraping || !scrapeUrl"
              class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap"
            >
              <svg v-if="scraping" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
              </svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
              {{ scraping ? 'Analyse...' : 'Scanner' }}
            </button>
          </div>
          <p class="text-xs text-gray-400 dark:text-gray-500">
            Collez le lien d'une page produits (Shopify, WooCommerce, ou autre). Les produits seront extraits automatiquement.
          </p>
        </div>

        <!-- Step 2: Preview scraped products -->
        <div v-if="scrapedProducts.length > 0" class="mt-4 space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ scrapedProducts.length }} produit(s) trouvé(s)
                <span class="text-xs text-gray-400"> — {{ scrapedSource }}</span>
              </span>
              <label class="flex items-center gap-1.5 cursor-pointer text-xs text-blue-600 dark:text-blue-400">
                <input type="checkbox" :checked="selectedProducts.size === scrapedProducts.length" @change="toggleAllProducts" class="w-3.5 h-3.5 text-blue-600 rounded" />
                Tout sélectionner
              </label>
            </div>
            <span class="text-xs font-medium text-blue-600 dark:text-blue-400">
              {{ selectedProducts.size }} sélectionné(s)
            </span>
          </div>

          <!-- Category input -->
          <div class="flex items-center gap-2">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Catégorie :</label>
            <input
              v-model="scrapeCategory"
              type="text"
              placeholder="Smartphones"
              class="w-48 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
            />
          </div>

          <!-- Product list -->
          <div class="max-h-80 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
            <div
              v-for="(p, i) in scrapedProducts"
              :key="i"
              @click="toggleProduct(i)"
              :class="[
                'flex items-center gap-3 p-2.5 cursor-pointer transition',
                selectedProducts.has(i)
                  ? 'bg-blue-50 dark:bg-blue-900/20'
                  : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'
              ]"
            >
              <input type="checkbox" :checked="selectedProducts.has(i)" class="w-4 h-4 text-blue-600 rounded pointer-events-none" />
              <img
                v-if="p.image"
                :src="p.image"
                :alt="p.name"
                class="w-10 h-10 rounded object-cover bg-gray-100 dark:bg-gray-700 flex-shrink-0"
                loading="lazy"
              />
              <div v-else class="w-10 h-10 rounded bg-gray-100 dark:bg-gray-700 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a1.5 1.5 0 001.5-1.5v-13.5a1.5 1.5 0 00-1.5-1.5H3.75a1.5 1.5 0 00-1.5 1.5v13.5a1.5 1.5 0 001.5 1.5z" />
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ p.name }}</p>
                <div class="flex items-center gap-2">
                  <span v-if="p.brand" class="text-xs text-gray-400">{{ p.brand }}</span>
                </div>
              </div>
              <div class="text-right flex-shrink-0">
                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ p.price.toLocaleString() }} MAD</p>
                <p v-if="p.old_price" class="text-xs text-gray-400 line-through">{{ p.old_price.toLocaleString() }} MAD</p>
              </div>
            </div>
          </div>

          <!-- Import button -->
          <button
            @click="importProducts"
            :disabled="importing || selectedProducts.size === 0"
            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed"
          >
            <svg v-if="importing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            {{ importing ? 'Import en cours...' : `Importer ${selectedProducts.size} produit(s) dans ${tenant?.name}` }}
          </button>
        </div>
      </div>

      <!-- Contrat de services -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-blue-200 dark:border-blue-900/50 p-5">
        <div class="flex items-center gap-2 mb-3">
          <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
          </svg>
          <h3 class="text-sm font-semibold text-blue-700 dark:text-blue-400">Contrat de services</h3>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
          Document à transmettre au client pour signature électronique avant la mise en service du tenant.
        </p>

        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            :disabled="downloadingContract"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300 rounded-lg transition disabled:opacity-50"
            @click="downloadContract('contrat')"
          >
            <svg v-if="downloadingContract" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Consulter le contrat
          </button>

          <button
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 rounded-lg transition"
            @click="downloadContract('fiche')"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
            </svg>
            Fiche de souscription
          </button>

          <button
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition ml-auto"
            @click="openSendContractModal"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.125A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.875L5.999 12zm0 0h7.5" />
            </svg>
            Envoyer au client
          </button>
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

    <!-- Send Contract Modal -->
    <div
      v-if="showSendContractModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      @click.self="showSendContractModal = false"
    >
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
              <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.125A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.875L5.999 12zm0 0h7.5" />
              </svg>
              Envoyer le contrat
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              Le contrat et la fiche seront joints à l'email.
            </p>
          </div>
          <button
            type="button"
            class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
            @click="showSendContractModal = false"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Destinataire *</label>
            <input
              v-model="sendContractForm.to"
              type="email"
              required
              placeholder="contact@exemple.ma"
              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Copie (Cc)</label>
            <input
              v-model="sendContractForm.cc"
              type="text"
              placeholder="email1@exemple.ma, email2@exemple.ma"
              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <p class="mt-1 text-[11px] text-gray-400">Séparer plusieurs emails par virgule ou espace.</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Message personnalisé (optionnel)</label>
            <textarea
              v-model="sendContractForm.message"
              rows="4"
              placeholder="Ajoutez un mot personnel à l'attention du client..."
              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
            />
          </div>

          <label class="flex items-center gap-2 cursor-pointer">
            <input
              v-model="sendContractForm.include_intake_form"
              type="checkbox"
              class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500"
            />
            <span class="text-sm text-gray-700 dark:text-gray-300">
              Inclure la <strong>fiche de souscription</strong> en pièce jointe
            </span>
          </label>
        </div>

        <div class="mt-6 flex gap-2">
          <button
            type="button"
            class="flex-1 py-2.5 rounded-xl text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
            @click="showSendContractModal = false"
          >
            Annuler
          </button>
          <button
            type="button"
            :disabled="sendingContract || !sendContractForm.to"
            class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            @click="sendContract"
          >
            <svg v-if="sendingContract" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.125A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.875L5.999 12zm0 0h7.5" />
            </svg>
            {{ sendingContract ? 'Envoi en cours...' : 'Envoyer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
