<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDocumentAchatStore } from '@/stores/achats/useDocumentAchatStore'
import DocumentForm from '@/components/DocumentForm.vue'
import type { DocumentHeader } from '@/types'

const route = useRoute()
const router = useRouter()
const store = useDocumentAchatStore()
const formRef = ref<InstanceType<typeof DocumentForm> | null>(null)
const doc = ref<DocumentHeader | null>(null)
const initialLoading = ref(true)

const documentTypes = [
  { value: 'PurchaseOrder', label: 'Bon de Commande' },
  { value: 'ReceiptNotePurchase', label: 'Bon de Réception' },
  { value: 'InvoicePurchase', label: 'Facture Achat' },
]

onMounted(async () => {
  doc.value = await store.fetchOne(Number(route.params.id))
  initialLoading.value = false
})

async function onSubmit(payload: Record<string, unknown>) {
  if (!doc.value) return
  try {
    await store.update(doc.value.id, payload)
    router.push(`/achats/documents/${doc.value.id}`)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { errors?: Record<string, string[]> } } }
    if (err.response?.data?.errors) {
      formRef.value?.setValidationErrors(err.response.data.errors)
    }
  }
}

function onCancel() {
  if (doc.value) {
    router.push(`/achats/documents/${doc.value.id}`)
  } else {
    router.push('/achats/documents')
  }
}
</script>

<template>
  <div class="max-w-9xl mx-auto py-6 px-4">
    <div v-if="initialLoading" class="flex items-center justify-center py-24">
      <div class="flex flex-col items-center gap-3">
        <svg class="w-8 h-8 animate-spin text-teal-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <span class="text-sm text-gray-500 dark:text-gray-400">Chargement...</span>
      </div>
    </div>

    <template v-else-if="doc">
      <div class="mb-6">
        <router-link
          :to="`/achats/documents/${doc.id}`"
          class="text-sm text-teal-600 dark:text-teal-400 hover:underline"
        >
          &larr; Retour au document
        </router-link>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2">
          Modifier le document
          <span class="text-lg font-normal text-gray-400 dark:text-gray-500">#{{ doc.reference }}</span>
        </h1>
      </div>

      <DocumentForm
        ref="formRef"
        domain="achat"
        :document-types="documentTypes"
        partner-label="Fournisseur"
        :partner-roles="['supplier', 'both']"
        :loading="store.loading"
        :initial-data="doc as unknown as Record<string, unknown>"
        :edit-mode="true"
        @submit="onSubmit"
        @cancel="onCancel"
      />
    </template>

    <div v-else class="text-center py-24 text-gray-400 dark:text-gray-500">
      <p class="text-lg font-medium">Document introuvable</p>
      <router-link to="/achats/documents" class="text-sm text-teal-600 dark:text-teal-400 hover:underline mt-2">
        Retour à la liste
      </router-link>
    </div>
  </div>
</template>
