<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDocumentVenteStore } from '@/stores/ventes/useDocumentVenteStore'
import BaseModal from '@/components/BaseModal.vue'
import FlashMessages from '@/components/FlashMessages.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import DocumentLinesTable from '@/components/DocumentLinesTable.vue'
import PaymentModal from '@/components/PaymentModal.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
import { useFlash } from '@/composables/useFlash'
import { useFormat } from '@/composables/useFormat'
import { usePdf } from '@/composables/usePdf'
import { saleTypeLabels } from '@/composables/useDocumentLabels'
import type { DocumentHeader } from '@/types'

const route = useRoute()
const router = useRouter()
const store = useDocumentVenteStore()

const { fmt } = useFormat()
const { successMsg, errorMsg, flash, flashError } = useFlash()
const { pdfLoading, downloadPdf: doPdfDownload, previewPdf: doPdfPreview } = usePdf()

const doc = ref<DocumentHeader | null>(null)
const showPaymentModal = ref(false)
const showDeleteConfirm = ref(false)
const showInvoiceModal = ref(false)
const invoicePaymentMethod = ref('credit')
const actionLoading = ref(false)
const paymentError = ref('')

const typeLabels = saleTypeLabels

onMounted(async () => {
  doc.value = await store.fetchOne(Number(route.params.id))
})

const canEdit = computed(() => doc.value?.status === 'draft')
const canConfirm = computed(() => doc.value?.status === 'draft')
const canConvert = computed(() => {
  if (!doc.value) return false
  if (doc.value.document_type === 'QuoteSale' && doc.value.status === 'confirmed') return true
  if (doc.value.document_type === 'DeliveryNote' && doc.value.status === 'confirmed') return true
  return false
})
const canPay = computed(() => {
  if (!doc.value) return false
  const payable = ['InvoiceSale']
  return (
    payable.includes(doc.value.document_type) &&
    ['confirmed', 'pending', 'partial'].includes(doc.value.status) &&
    Number(doc.value.footer?.amount_due ?? 0) > 0
  )
})
const canCancel = computed(
  () => doc.value && ['draft', 'confirmed'].includes(doc.value.status) && doc.value.status !== 'cancelled',
)
const canDelete = computed(() => doc.value?.status === 'draft')

const convertLabel = computed(() => {
  if (doc.value?.document_type === 'QuoteSale') return 'Transformer en BL'
  if (doc.value?.document_type === 'DeliveryNote') return 'Générer Facture'
  return 'Convertir'
})

async function confirmDocument() {
  if (!doc.value) return
  actionLoading.value = true
  try {
    await store.updateStatus(doc.value.id, 'confirmed')
    doc.value = await store.fetchOne(doc.value.id)
    flash('Document confirmé avec succès.')
  } catch {
    flashError('Erreur lors de la confirmation.')
  }
  actionLoading.value = false
}

async function convertDocument() {
  if (!doc.value) return

  // For DeliveryNote → Invoice, show payment method selection modal first
  if (doc.value.document_type === 'DeliveryNote') {
    invoicePaymentMethod.value = 'credit'
    showInvoiceModal.value = true
    return
  }

  actionLoading.value = true
  try {
    if (doc.value.document_type === 'QuoteSale') {
      const result = await store.genererBL(doc.value.id)
      if (result.success && result.bl) {
        router.push(`/ventes/documents/${result.bl.id}`)
        return
      }
      if (result.creditError) flashError(result.creditError)
    }
  } catch {
    flashError('Erreur lors de la conversion.')
  }
  actionLoading.value = false
}

async function confirmGenerateInvoice() {
  if (!doc.value) return
  showInvoiceModal.value = false
  actionLoading.value = true
  try {
    const result = await store.confirmerReception(doc.value.id, invoicePaymentMethod.value)
    if (result.success && result.facture) {
      // Refresh current document to show updated status
      doc.value = await store.fetchOne(doc.value.id)
      flash('Facture ' + result.facture.reference + ' créée avec succès.')
      // Navigate to the new invoice
      router.push(`/ventes/documents/${result.facture.id}`)
      return
    }
  } catch {
    flashError('Erreur lors de la génération de la facture.')
  }
  actionLoading.value = false
}

async function cancelDocument() {
  if (!doc.value) return
  actionLoading.value = true
  try {
    await store.updateStatus(doc.value.id, 'cancelled')
    doc.value = await store.fetchOne(doc.value.id)
    flash('Document annulé.')
  } catch {
    flashError("Erreur lors de l'annulation.")
  }
  actionLoading.value = false
}

async function deleteDocument() {
  if (!doc.value) return
  actionLoading.value = true
  try {
    await store.remove(doc.value.id)
    router.push('/ventes/documents')
  } catch {
    flashError('Erreur lors de la suppression.')
  }
  actionLoading.value = false
  showDeleteConfirm.value = false
}

async function submitPayment(payload: {
  amount: number
  method: string
  paid_at: string
  reference: string | null
  notes: string | null
}) {
  if (!doc.value) return
  actionLoading.value = true
  paymentError.value = ''
  try {
    await store.addPayment({
      document_header_id: doc.value.id,
      ...payload,
    })
    showPaymentModal.value = false
    doc.value = await store.fetchOne(doc.value.id)
    flash('Paiement enregistré.')
  } catch {
    paymentError.value = "Erreur lors de l'enregistrement du paiement."
  }
  actionLoading.value = false
}

async function duplicateDocument() {
  if (!doc.value) return
  router.push('/ventes/documents/create')
}

async function downloadPdf() {
  if (!doc.value) return
  doPdfDownload(doc.value.id, `${doc.value.document_type}_${doc.value.reference || 'doc'}.pdf`)
}

async function previewPdf() {
  if (!doc.value) return
  doPdfPreview(doc.value.id)
}

const paymentProgress = computed(() => {
  if (!doc.value?.footer) return 0
  const ttc = Number(doc.value.footer.total_ttc)
  const paid = Number(doc.value.footer.amount_paid ?? 0)
  if (ttc <= 0) return 0
  return Math.min(100, Math.round((paid / ttc) * 100))
})
</script>

<template>
  <div class="max-w-9xl mx-auto py-4 sm:py-6 px-3 sm:px-4">
    <!-- Loading -->
    <div v-if="store.loading && !doc" class="flex items-center justify-center py-24">
      <div class="flex flex-col items-center gap-3">
        <svg class="w-8 h-8 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <span class="text-sm text-gray-500 dark:text-gray-400">Chargement du document...</span>
      </div>
    </div>

    <!-- Document -->
    <div v-else-if="doc">
      <FlashMessages :success="successMsg" :error="errorMsg || store.creditError || ''" />

      <!-- Breadcrumb + Title Row -->
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div>
          <router-link
            to="/ventes/documents"
            class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition"
          >
            &larr; Documents de Vente
          </router-link>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2 flex items-center gap-3">
            {{ typeLabels[doc.document_type] ?? doc.document_type }}
            <span class="text-lg font-normal text-gray-400 dark:text-gray-500">#{{ doc.reference }}</span>
          </h1>
          <p
            v-if="doc.document_title && doc.document_title !== doc.document_type"
            class="text-sm text-gray-500 dark:text-gray-400 mt-0.5"
          >
            {{ doc.document_title }}
          </p>
        </div>

        <StatusBadge :status="doc.status" class="shrink-0 self-start" />
      </div>

      <!-- Action Bar -->
      <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-4 mb-6">
        <div class="flex flex-wrap items-center gap-2">
          <router-link
            v-if="canEdit"
            :to="`/ventes/documents/${doc.id}/edit`"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition bg-amber-500 text-white hover:bg-amber-600"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
              />
            </svg>
            Modifier
          </router-link>

          <button
            v-if="canConfirm"
            :disabled="actionLoading"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
            @click="confirmDocument"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Confirmer
          </button>

          <button
            v-if="canConvert"
            :disabled="actionLoading"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition bg-green-600 text-white hover:bg-green-700 disabled:opacity-50"
            @click="convertDocument"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"
              />
            </svg>
            {{ convertLabel }}
          </button>

          <button
            v-if="canPay"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition bg-emerald-600 text-white hover:bg-emerald-700"
            @click="showPaymentModal = true"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            Enregistrer paiement
          </button>

          <div class="flex-1"></div>

          <button
            :disabled="pdfLoading"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
            @click="downloadPdf"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
              />
            </svg>
            PDF
          </button>
          <button
            :disabled="pdfLoading"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
            @click="previewPdf"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
              />
            </svg>
            Aperçu
          </button>
          <button
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
            @click="duplicateDocument"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
              />
            </svg>
            Dupliquer
          </button>

          <button
            v-if="canCancel"
            :disabled="actionLoading"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-50"
            @click="cancelDocument"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
              />
            </svg>
            Annuler
          </button>

          <button
            v-if="canDelete"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
            @click="showDeleteConfirm = true"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
              />
            </svg>
            Supprimer
          </button>
        </div>
      </div>

      <!-- Info Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
          <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Client</p>
          <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
            {{ doc.third_partner?.tp_title ?? doc.thirdPartner?.tp_title ?? '—' }}
          </p>
          <p
            v-if="(doc.third_partner ?? doc.thirdPartner)?.tp_phone"
            class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"
          >
            {{ (doc.third_partner ?? doc.thirdPartner)?.tp_phone }}
          </p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
          <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Entrepôt</p>
          <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ doc.warehouse?.wh_title ?? '—' }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
          <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">
            Date d'émission
          </p>
          <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ doc.issued_at ?? '—' }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
          <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">
            Date d'échéance
          </p>
          <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ doc.due_at ?? '—' }}</p>
        </div>
      </div>

      <DocumentLinesTable :lines="doc.lignes ?? []" />

      <!-- Footer + Payment Progress -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
          <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">
            Récapitulatif
          </h3>
          <div v-if="doc.footer" class="space-y-3 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-500 dark:text-gray-400">Total HT</span>
              <span class="font-medium dark:text-gray-200">{{ fmt(doc.footer.total_ht) }} DH</span>
            </div>
            <div v-if="Number(doc.footer.total_discount) > 0" class="flex justify-between">
              <span class="text-gray-500 dark:text-gray-400">Remise</span>
              <span class="font-medium text-red-600 dark:text-red-400">-{{ fmt(doc.footer.total_discount) }} DH</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500 dark:text-gray-400">TVA</span>
              <span class="font-medium dark:text-gray-200">{{ fmt(doc.footer.total_tax) }} DH</span>
            </div>
            <div class="flex justify-between border-t pt-3 border-gray-200 dark:border-gray-700">
              <span class="text-gray-900 dark:text-white font-bold text-base">Total TTC</span>
              <span class="text-gray-900 dark:text-white font-bold text-base">{{ fmt(doc.footer.total_ttc) }} DH</span>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
          <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">Paiement</h3>
          <div v-if="doc.footer" class="space-y-4">
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">Payé</span>
              <span class="font-semibold text-emerald-600 dark:text-emerald-400"
                >{{ fmt(doc.footer.amount_paid) }} DH</span
              >
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">Reste à payer</span>
              <span
                class="font-semibold"
                :class="
                  Number(doc.footer.amount_due) > 0
                    ? 'text-red-600 dark:text-red-400'
                    : 'text-gray-600 dark:text-gray-300'
                "
              >
                {{ fmt(doc.footer.amount_due) }} DH
              </span>
            </div>
            <div class="relative pt-1">
              <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                <span>Progression</span>
                <span>{{ paymentProgress }}%</span>
              </div>
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div
                  class="h-2 rounded-full transition-all duration-500"
                  :class="paymentProgress >= 100 ? 'bg-emerald-500' : 'bg-blue-500'"
                  :style="{ width: paymentProgress + '%' }"
                ></div>
              </div>
            </div>

            <div v-if="(doc as any).payments?.length" class="mt-3 space-y-2">
              <div
                v-for="p in (doc as any).payments"
                :key="p.id"
                class="flex items-center justify-between text-xs bg-gray-50 dark:bg-gray-700 rounded-lg px-3 py-2"
              >
                <span class="text-gray-600 dark:text-gray-300">{{ p.method }} — {{ p.paid_at }}</span>
                <span class="font-medium text-gray-800 dark:text-gray-200">{{ fmt(p.amount) }} DH</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div
        v-if="doc.notes"
        class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 mb-6"
      >
        <p class="text-xs font-medium text-amber-600 dark:text-amber-400 uppercase tracking-wider mb-1">Notes</p>
        <p class="text-sm text-amber-900 dark:text-amber-200">{{ doc.notes }}</p>
      </div>

      <!-- Document chain -->
      <div
        v-if="doc.parent || doc.children?.length"
        class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 mb-6"
      >
        <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">Documents liés</p>
        <div class="flex flex-wrap gap-2">
          <router-link
            v-if="doc.parent"
            :to="`/ventes/documents/${doc.parent.id}`"
            class="inline-flex items-center gap-1.5 text-sm text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50 px-3 py-1.5 rounded-lg transition"
          >
            &larr; {{ typeLabels[doc.parent.document_type] ?? doc.parent.document_type }} #{{ doc.parent.reference }}
          </router-link>
          <router-link
            v-for="child in doc.children"
            :key="child.id"
            :to="`/ventes/documents/${child.id}`"
            class="inline-flex items-center gap-1.5 text-sm text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30 hover:bg-purple-100 dark:hover:bg-purple-900/50 px-3 py-1.5 rounded-lg transition"
          >
            {{ typeLabels[child.document_type] ?? child.document_type }} #{{ child.reference }} &rarr;
          </router-link>
        </div>
      </div>
    </div>

    <!-- Not found -->
    <div v-else class="flex flex-col items-center justify-center py-24 text-gray-400 dark:text-gray-500">
      <svg class="w-16 h-16 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
        />
      </svg>
      <p class="text-lg font-medium">Document introuvable</p>
      <router-link to="/ventes/documents" class="text-sm text-blue-600 dark:text-blue-400 hover:underline mt-2">
        Retour à la liste
      </router-link>
    </div>

    <PaymentModal
      v-model="showPaymentModal"
      :amount-due="Number(doc?.footer?.amount_due ?? 0)"
      :label="`Facture : ${doc?.reference ?? ''} — ${doc?.third_partner?.tp_title ?? doc?.thirdPartner?.tp_title ?? ''}`"
      :loading="actionLoading"
      :error="paymentError"
      @submit="submitPayment"
    />

    <!-- Invoice Generation Modal (payment method selection) -->
    <BaseModal v-model="showInvoiceModal" title="Générer la Facture" size="sm">
      <div class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-300">Choisissez le moyen de paiement pour cette facture :</p>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Moyen de paiement *</label>
          <select
            v-model="invoicePaymentMethod"
            class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="credit">En compte (Crédit)</option>
            <option value="cash">Espèces</option>
            <option value="bank_transfer">Virement bancaire</option>
            <option value="cheque">Chèque</option>
            <option value="effet">Effet</option>
          </select>
        </div>
        <div
          v-if="invoicePaymentMethod === 'credit'"
          class="flex items-start gap-2 px-3 py-2.5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg"
        >
          <svg
            class="w-4 h-4 text-amber-500 shrink-0 mt-0.5"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"
            />
          </svg>
          <p class="text-xs text-amber-700 dark:text-amber-300">Le montant TTC sera ajouté à l'encours du client.</p>
        </div>
      </div>
      <template #footer>
        <button
          class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
          @click="showInvoiceModal = false"
        >
          Annuler
        </button>
        <button
          :disabled="actionLoading"
          class="px-5 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50"
          @click="confirmGenerateInvoice"
        >
          {{ actionLoading ? 'Génération...' : 'Générer la Facture' }}
        </button>
      </template>
    </BaseModal>

    <ConfirmModal
      v-model="showDeleteConfirm"
      title="Supprimer le document"
      confirm-label="Supprimer"
      loading-label="Suppression..."
      :loading="actionLoading"
      @confirm="deleteDocument"
    >
      Êtes-vous sûr de vouloir supprimer ce document ? Cette action est irréversible.
    </ConfirmModal>
  </div>
</template>
