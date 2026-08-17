<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDocumentAchatStore } from '@/stores/achats/useDocumentAchatStore'
import DocumentForm from '@/components/DocumentForm.vue'

const route = useRoute()
const router = useRouter()
const store = useDocumentAchatStore()
const formRef = ref<InstanceType<typeof DocumentForm> | null>(null)

const ALL_TYPES = [
  { value: 'PurchaseOrder', label: 'Bon de Commande' },
  { value: 'ReceiptNotePurchase', label: 'Bon de Réception' },
  { value: 'InvoicePurchase', label: 'Facture Achat' },
  { value: 'CreditNotePurchase', label: 'Avoir Fournisseur' },
]

// The sidebar links here with ?type=… so a menu entry lands on the right
// kind of document. DocumentForm seeds its select from the first entry, so
// preselecting is a matter of ordering — the other types stay reachable.
const documentTypes = computed(() => {
  const wanted = String(route.query.type ?? '')
  const match = ALL_TYPES.find((t) => t.value === wanted)
  return match ? [match, ...ALL_TYPES.filter((t) => t !== match)] : ALL_TYPES
})

async function onSubmit(payload: Record<string, unknown>) {
  try {
    const doc = await store.create(payload)
    router.push(`/achats/documents/${doc.id}`)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { errors?: Record<string, string[]> } } }
    if (err.response?.data?.errors) {
      formRef.value?.setValidationErrors(err.response.data.errors)
    }
  }
}

function onCancel() {
  router.push('/achats/documents')
}
</script>

<template>
  <div class="max-w-9xl mx-auto py-6 px-4">
    <div class="mb-6">
      <router-link to="/achats/documents" class="text-sm text-teal-600 dark:text-teal-400 hover:underline">
        &larr; Retour aux documents d'achat
      </router-link>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2">Nouveau Document d'Achat</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
        Créez un bon de commande, bon de réception, facture achat ou avoir.
      </p>
    </div>

    <!-- Remount on ?type= change: vue-router reuses the component when only
         the query moves, and DocumentForm seeds its select once, on setup. -->
    <DocumentForm
      :key="String($route.query.type ?? 'default')"
      ref="formRef"
      domain="achat"
      :document-types="documentTypes"
      partner-label="Fournisseur"
      :partner-roles="['supplier', 'both']"
      :loading="store.loading"
      @submit="onSubmit"
      @cancel="onCancel"
    />
  </div>
</template>
