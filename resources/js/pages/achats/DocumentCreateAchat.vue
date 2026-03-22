<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDocumentAchatStore } from '@/stores/achats/useDocumentAchatStore'
import DocumentForm from '@/components/DocumentForm.vue'

const router = useRouter()
const store = useDocumentAchatStore()
const formRef = ref<InstanceType<typeof DocumentForm> | null>(null)

const documentTypes = [
  { value: 'PurchaseOrder', label: 'Bon de Commande' },
  { value: 'ReceiptNotePurchase', label: 'Bon de Réception' },
  { value: 'InvoicePurchase', label: 'Facture Achat' },
]

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
        Créez un bon de commande, bon de réception ou facture achat.
      </p>
    </div>

    <DocumentForm
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
