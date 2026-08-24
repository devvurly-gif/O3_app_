<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDocumentVenteStore } from '@/stores/ventes/useDocumentVenteStore'
import DocumentForm from '@/components/DocumentForm.vue'

const route = useRoute()
const router = useRouter()
const store = useDocumentVenteStore()
const formRef = ref<InstanceType<typeof DocumentForm> | null>(null)

const ALL_TYPES = [
  { value: 'QuoteSale', label: 'Devis' },
  { value: 'CustomerOrder', label: 'Bon de Commande' },
  { value: 'DeliveryNote', label: 'Bon de Livraison' },
  { value: 'InvoiceSale', label: 'Facture' },
  { value: 'CreditNoteSale', label: 'Avoir' },
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
    router.push(`/ventes/documents/${doc.id}`)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { errors?: Record<string, string[]> } } }
    if (err.response?.data?.errors) {
      formRef.value?.setValidationErrors(err.response.data.errors)
    }
  }
}

function onCancel() {
  router.push('/ventes/documents')
}
</script>

<template>
  <div class="max-w-9xl mx-auto py-6 px-4">
    <div class="mb-6">
      <router-link to="/ventes/documents" class="text-sm text-orange-700 dark:text-orange-400 hover:underline">
        &larr; Retour aux documents de vente
      </router-link>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2">Nouveau Document de Vente</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
        Créez un devis, bon de commande, bon de livraison, facture ou avoir.
      </p>
    </div>

    <!-- Remount on ?type= change: vue-router reuses the component when only
         the query moves, and DocumentForm seeds its select once, on setup.
         Without the key, jumping from "Nouveau BL" to "Nouvelle facture"
         would keep the previous type selected. -->
    <DocumentForm
      :key="String($route.query.type ?? 'default')"
      ref="formRef"
      domain="vente"
      :document-types="documentTypes"
      partner-label="Client"
      :partner-roles="['customer', 'both']"
      :loading="store.loading"
      @submit="onSubmit"
      @cancel="onCancel"
    />
  </div>
</template>
