<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDocumentVenteStore } from '@/stores/ventes/useDocumentVenteStore'
import DocumentForm from '@/components/DocumentForm.vue'

const router = useRouter()
const store = useDocumentVenteStore()
const formRef = ref<InstanceType<typeof DocumentForm> | null>(null)

const documentTypes = [
  { value: 'QuoteSale', label: 'Devis' },
  { value: 'DeliveryNote', label: 'Bon de Livraison' },
  { value: 'InvoiceSale', label: 'Facture' },
  { value: 'CreditNoteSale', label: 'Avoir' },
]

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
      <router-link to="/ventes/documents" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
        &larr; Retour aux documents de vente
      </router-link>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2">Nouveau Document de Vente</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Créez un devis, bon de livraison, facture ou avoir.</p>
    </div>

    <DocumentForm
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
