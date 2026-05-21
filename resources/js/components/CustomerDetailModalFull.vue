<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import BaseModal from '@/components/BaseModal.vue'
import http from '@/services/http'
import { useFormat } from '@/composables/useFormat'

interface Props {
  modelValue: boolean
  customerId: number | null
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const { fmt: formatNumber } = useFormat()

// State
const loading = ref(false)
const customerDetail = ref<any>(null)
const activeTab = ref<'info' | 'fiscal' | 'credit' | 'factures' | 'paiements' | 'statistiques'>('info')

// Tabs
const tabs = computed(() => {
  const tabList: any[] = [
    { key: 'info', label: 'Informations' },
    { key: 'fiscal', label: 'Fiscal' },
    { key: 'credit', label: 'Crédit' },
    { key: 'factures', label: 'Documents', badge: customerDocuments.value.length },
    { key: 'paiements', label: 'Paiements', badge: customerPayments.value.length },
    { key: 'statistiques', label: 'Statistiques' },
  ]
  return tabList
})

// Documents & Payments
const customerDocuments = computed(() => {
  if (!customerDetail.value?.document_headers) return []
  return customerDetail.value.document_headers.sort(
    (a: any, b: any) => new Date(b.issued_at).getTime() - new Date(a.issued_at).getTime(),
  )
})

const customerPayments = computed(() => {
  if (!customerDetail.value?.document_headers) return []
  const payments: any[] = []
  for (const doc of customerDetail.value.document_headers) {
    if (doc.payments?.length) {
      for (const p of doc.payments) {
        payments.push({ ...p, _doc_code: doc.reference })
      }
    }
  }
  return payments.sort((a, b) => new Date(b.paid_at).getTime() - new Date(a.paid_at).getTime())
})

// Credit calculations
const creditPercent = computed(() => {
  const limit = customerDetail.value?.seuil_credit ?? 0
  if (limit <= 0) return 0
  return ((customerDetail.value?.encours_actuel ?? 0) / limit) * 100
})

const creditAvailable = computed(() => (customerDetail.value?.seuil_credit ?? 0) - (customerDetail.value?.encours_actuel ?? 0))

// Statistics
const unpaidCount = computed(() => customerDocuments.value.filter((inv: any) => Number(inv.footer?.amount_due ?? 0) > 0).length)
const totalTTC = computed(() => customerDocuments.value.reduce((sum: number, inv: any) => sum + Number(inv.footer?.total_ttc ?? 0), 0))
const totalDue = computed(() => customerDocuments.value.reduce((sum: number, inv: any) => sum + Number(inv.footer?.amount_due ?? 0), 0))
const totalPayments = computed(() => customerPayments.value.reduce((sum: number, p: any) => sum + Number(p.amount ?? 0), 0))
const paymentRate = computed(() => (totalTTC.value > 0 ? (totalPayments.value / totalTTC.value) * 100 : 0))

// Load customer detail
async function loadCustomerDetail() {
  if (!props.customerId) return

  loading.value = true
  try {
    const { data } = await http.get(`/third-parties/${props.customerId}`)
    customerDetail.value = data
    activeTab.value = 'info'
  } catch (error) {
    console.error('Failed to load customer details:', error)
  } finally {
    loading.value = false
  }
}

// Helper functions
function formatDate(date: string) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('fr-FR', { year: 'numeric', month: '2-digit', day: '2-digit' })
}

function docTypeLabel(type: string) {
  const labels: Record<string, string> = {
    InvoiceSale: 'Facture',
    InvoicePurchase: 'Facture Fournisseur',
    DeliveryNote: 'BL',
    PurchaseOrder: 'Commande',
    Quote: 'Devis',
  }
  return labels[type] || type
}

function docTypeClass(type: string) {
  const classes: Record<string, string> = {
    InvoiceSale: 'bg-emerald-100 text-emerald-700',
    InvoicePurchase: 'bg-purple-100 text-purple-700',
    DeliveryNote: 'bg-orange-100 text-orange-600',
    PurchaseOrder: 'bg-blue-100 text-blue-700',
    Quote: 'bg-amber-100 text-amber-700',
  }
  return classes[type] || 'bg-gray-100 text-gray-700'
}

function statusLabel(status: string, docType: string) {
  if (status === 'paid') return 'Payé'
  if (status === 'delivered') return 'Livré'
  if (status === 'cancelled') return 'Annulé'
  if (status === 'draft') return 'Brouillon'
  return 'Ouvert'
}

function statusClass(status: string) {
  const classes: Record<string, string> = {
    paid: 'bg-emerald-100 text-emerald-700',
    delivered: 'bg-orange-100 text-orange-700',
    cancelled: 'bg-gray-100 text-gray-600',
    draft: 'bg-blue-100 text-blue-700',
  }
  return classes[status] || 'bg-gray-100 text-gray-700'
}

function methodLabel(method: string) {
  const labels: Record<string, string> = {
    cash: 'Espèces',
    bank_transfer: 'Virement',
    cheque: 'Chèque',
    effet: 'Effet',
  }
  return labels[method] || method
}

// Watch for modal open/close and customer ID changes
watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    loadCustomerDetail()
  }
}, { immediate: true })

watch(() => props.customerId, () => {
  if (props.modelValue) {
    loadCustomerDetail()
  }
})

function handleClose() {
  emit('update:modelValue', false)
}
</script>

<template>
  <BaseModal :modelValue="modelValue" :title="customerDetail?.tp_title ?? 'Client'" size="xl" @update:modelValue="handleClose">
    <!-- Tab Navigation -->
    <div class="border-b border-gray-200 dark:border-gray-700 -mt-2 mb-4">
      <nav class="flex gap-0 -mb-px overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
          :class="
            activeTab === tab.key
              ? 'border-orange-500 text-orange-500'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
          "
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
          <span
            v-if="tab.badge !== undefined && tab.badge > 0"
            class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-xs font-semibold leading-none"
            :class="activeTab === tab.key ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-600'"
          >
            {{ tab.badge }}
          </span>
        </button>
      </nav>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="w-6 h-6 animate-spin text-orange-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
      </svg>
    </div>

    <div v-else class="min-h-[380px]">
      <!-- TAB: Info -->
      <div v-show="activeTab === 'info'">
        <div class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
            <div class="sm:col-span-2">
              <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">Informations générales</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Code</p>
              <p class="text-sm font-mono font-medium text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
                {{ customerDetail?.tp_code ?? '—' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Nom</p>
              <p class="text-sm font-medium text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
                {{ customerDetail?.tp_title ?? '—' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Téléphone</p>
              <p class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">{{ customerDetail?.tp_phone || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Email</p>
              <p class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">{{ customerDetail?.tp_email || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Ville</p>
              <p class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">{{ customerDetail?.tp_city || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Statut</p>
              <p class="text-sm bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="customerDetail?.tp_status ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                >
                  {{ customerDetail?.tp_status ? 'Actif' : 'Inactif' }}
                </span>
              </p>
            </div>
            <div class="sm:col-span-2">
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Adresse</p>
              <p class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg min-h-[40px]">
                {{ customerDetail?.tp_address || '—' }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB: Fiscal -->
      <div v-show="activeTab === 'fiscal'">
        <div class="space-y-4">
          <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">Informations fiscales</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">ICE</p>
              <p class="text-sm font-mono text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
                {{ customerDetail?.tp_Ice_Number || '—' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">RC</p>
              <p class="text-sm font-mono text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
                {{ customerDetail?.tp_Rc_Number || '—' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Patente</p>
              <p class="text-sm font-mono text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
                {{ customerDetail?.tp_patente_Number || '—' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Identifiant fiscal</p>
              <p class="text-sm font-mono text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-lg">
                {{ customerDetail?.tp_IdenFiscal || '—' }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB: Crédit -->
      <div v-show="activeTab === 'credit'">
        <div class="space-y-5">
          <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-5 border border-blue-100">
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Situation crédit</h4>
              <span
                class="text-xs font-medium px-2 py-1 rounded-full"
                :class="
                  creditPercent <= 70
                    ? 'bg-emerald-100 text-emerald-700'
                    : creditPercent <= 90
                      ? 'bg-amber-100 text-amber-700'
                      : 'bg-red-100 text-red-700'
                "
              >
                {{ creditPercent.toFixed(0) }}% utilisé
              </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3 mb-3">
              <div
                class="h-3 rounded-full transition-all duration-500"
                :class="
                  creditPercent <= 70 ? 'bg-emerald-500' : creditPercent <= 90 ? 'bg-amber-500' : 'bg-red-500'
                "
                :style="{ width: Math.min(creditPercent, 100) + '%' }"
              ></div>
            </div>
            <div class="grid grid-cols-3 gap-4 text-center">
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Encours actuel</p>
                <p class="text-lg font-bold text-gray-900 dark:text-white font-mono">
                  {{ formatNumber(customerDetail?.encours_actuel ?? 0) }}
                </p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Seuil crédit</p>
                <p class="text-lg font-bold text-gray-900 dark:text-white font-mono">
                  {{ formatNumber(customerDetail?.seuil_credit ?? 0) }}
                </p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Disponible</p>
                <p
                  class="text-lg font-bold font-mono"
                  :class="creditAvailable > 0 ? 'text-emerald-600' : 'text-red-600'"
                >
                  {{ formatNumber(creditAvailable) }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB: Documents -->
      <div v-show="activeTab === 'factures'">
        <div v-if="customerDocuments.length === 0" class="text-center py-12 text-gray-400 dark:text-gray-500">
          <svg
            class="w-12 h-12 mx-auto mb-3 text-gray-300"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
            />
          </svg>
          <p class="text-sm">Aucun document</p>
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Code</th>
                <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Date</th>
                <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Type</th>
                <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">Total TTC</th>
                <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">Reste dû</th>
                <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-center">Statut</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="doc in customerDocuments" :key="doc.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="py-2.5 px-3 font-mono text-xs">{{ doc.reference }}</td>
                <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400">{{ formatDate(doc.issued_at) }}</td>
                <td class="py-2.5 px-3">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                    :class="docTypeClass(doc.document_type)"
                  >
                    {{ docTypeLabel(doc.document_type) }}
                  </span>
                </td>
                <td class="py-2.5 px-3 text-right font-mono font-medium">
                  {{ formatNumber(doc.footer?.total_ttc ?? 0) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                </td>
                <td
                  class="py-2.5 px-3 text-right font-mono font-medium"
                  :class="(doc.footer?.amount_due ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600'"
                >
                  {{ formatNumber(doc.footer?.amount_due ?? 0) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                </td>
                <td class="py-2.5 px-3 text-center">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="statusClass(doc.status)"
                  >
                    {{ statusLabel(doc.status, doc.document_type) }}
                  </span>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-gray-200 dark:border-gray-700 font-semibold bg-gray-50 dark:bg-gray-900">
                <td colspan="3" class="py-2.5 px-3 text-sm text-gray-600 dark:text-gray-400">
                  {{ customerDocuments.length }} document(s)
                </td>
                <td class="py-2.5 px-3 text-right font-mono">
                  {{ formatNumber(totalTTC) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                </td>
                <td
                  class="py-2.5 px-3 text-right font-mono"
                  :class="totalDue > 0 ? 'text-red-600' : 'text-emerald-600'"
                >
                  {{ formatNumber(totalDue) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                </td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- TAB: Paiements -->
      <div v-show="activeTab === 'paiements'">
        <div v-if="customerPayments.length === 0" class="text-center py-12 text-gray-400 dark:text-gray-500">
          <svg
            class="w-12 h-12 mx-auto mb-3 text-gray-300"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"
            />
          </svg>
          <p class="text-sm">Aucun paiement</p>
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Facture</th>
                <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Date</th>
                <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Méthode</th>
                <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">Montant</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="pay in customerPayments" :key="pay.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="py-2.5 px-3 font-mono text-xs text-orange-500">{{ pay._doc_code }}</td>
                <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400">{{ formatDate(pay.paid_at) }}</td>
                <td class="py-2.5 px-3">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                    {{ methodLabel(pay.method) }}
                  </span>
                </td>
                <td class="py-2.5 px-3 text-right font-mono font-medium text-emerald-600">
                  {{ formatNumber(Number(pay.amount)) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-gray-200 dark:border-gray-700 font-semibold bg-gray-50 dark:bg-gray-900">
                <td colspan="3" class="py-2.5 px-3 text-sm text-gray-600 dark:text-gray-400">{{ customerPayments.length }} paiement(s)</td>
                <td class="py-2.5 px-3 text-right font-mono text-emerald-600">
                  {{ formatNumber(totalPayments) }} <span class="text-gray-400 dark:text-gray-500 text-xs">DH</span>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- TAB: Statistiques -->
      <div v-show="activeTab === 'statistiques'">
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <div class="bg-orange-50 rounded-xl p-4 border border-orange-100">
            <p class="text-xs text-orange-600 font-medium mb-1">Total documents</p>
            <p class="text-2xl font-bold text-orange-900">{{ customerDocuments.length }}</p>
          </div>
          <div class="bg-red-50 rounded-xl p-4 border border-red-100">
            <p class="text-xs text-red-600 font-medium mb-1">Documents impayés</p>
            <p class="text-2xl font-bold text-red-900">{{ unpaidCount }}</p>
          </div>
          <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
            <p class="text-xs text-emerald-600 font-medium mb-1">CA total</p>
            <p class="text-xl font-bold text-emerald-900 font-mono">
              {{ formatNumber(totalTTC) }} <span class="text-sm font-normal text-emerald-600">DH</span>
            </p>
          </div>
          <div class="bg-teal-50 rounded-xl p-4 border border-teal-100">
            <p class="text-xs text-teal-600 font-medium mb-1">Total payé</p>
            <p class="text-xl font-bold text-teal-900 font-mono">
              {{ formatNumber(totalPayments) }} <span class="text-sm font-normal text-teal-600">DH</span>
            </p>
          </div>
          <div class="bg-amber-50 rounded-xl p-4 border border-amber-100">
            <p class="text-xs text-amber-600 font-medium mb-1">Reste à payer</p>
            <p class="text-xl font-bold font-mono" :class="totalDue > 0 ? 'text-amber-900' : 'text-emerald-700'">
              {{ formatNumber(totalDue) }} <span class="text-sm font-normal text-amber-600">DH</span>
            </p>
          </div>
          <div class="sm:col-span-3 bg-gray-50 dark:bg-gray-900 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-2">
              <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Taux de recouvrement</p>
              <span
                class="text-sm font-bold"
                :class="
                  paymentRate >= 80
                    ? 'text-emerald-600'
                    : paymentRate >= 50
                      ? 'text-amber-600'
                      : 'text-red-600'
                "
              >
                {{ paymentRate.toFixed(1) }}%
              </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
              <div
                class="h-2.5 rounded-full transition-all duration-500"
                :class="
                  paymentRate >= 80 ? 'bg-emerald-500' : paymentRate >= 50 ? 'bg-amber-500' : 'bg-red-500'
                "
                :style="{ width: Math.min(paymentRate, 100) + '%' }"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <button
        class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition"
        @click="handleClose"
      >
        Fermer
      </button>
    </template>
  </BaseModal>
</template>
