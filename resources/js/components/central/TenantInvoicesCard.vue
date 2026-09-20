<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import http from '@/services/http'
import { useToastStore } from '@/stores/toastStore'

/**
 * Factures d'abonnement d'un tenant, cote back-office.
 *
 * L'emission automatique passe par `subscriptions:invoice`. Cet ecran sert les
 * cas ou il faut reprendre la main : un client qui reclame sa facture en
 * avance, un email qui n'est pas arrive, une erreur a annuler.
 *
 * Une facture ne se supprime jamais — le bouton correspondant annule, et le
 * numero reste consomme pour que la sequence demeure continue.
 */
const props = defineProps<{ tenantId: string }>()

const toast = useToastStore()

interface Invoice {
  id: number
  number: string
  issued_at: string
  due_at: string
  period_starts_at: string
  period_ends_at: string
  amount_ttc_cents: number
  status: 'draft' | 'sent' | 'paid' | 'cancelled'
  paid_at: string | null
}

const invoices = ref<Invoice[]>([])
const issuerMissing = ref<string[]>([])
const loading = ref(true)
const busy = ref<number | 'new' | null>(null)

const statusLabels: Record<Invoice['status'], string> = {
  draft: 'Brouillon',
  sent: 'Envoyée',
  paid: 'Réglée',
  cancelled: 'Annulée',
}

const statusTone: Record<Invoice['status'], string> = {
  draft: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
  sent: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
  paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
  cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
}

const canIssue = computed(() => issuerMissing.value.length === 0)

function money(cents: number): string {
  return (cents / 100).toLocaleString('fr-MA', { minimumFractionDigits: 2 }) + ' MAD'
}

function formatDate(value: string | null): string {
  return value ? new Date(value).toLocaleDateString('fr-MA') : '—'
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const { data } = await http.get(`/central/tenants/${props.tenantId}/invoices`)
    invoices.value = data.data
    issuerMissing.value = data.issuer_missing ?? []
  } finally {
    loading.value = false
  }
}

async function issue(): Promise<void> {
  busy.value = 'new'
  try {
    const { data } = await http.post(`/central/tenants/${props.tenantId}/invoices`, { send: true })
    toast.success(data.message)
    await load()
  } finally {
    busy.value = null
  }
}

async function resend(invoice: Invoice): Promise<void> {
  busy.value = invoice.id
  try {
    const { data } = await http.post(`/central/invoices/${invoice.id}/send`)
    toast.success(data.message)
    await load()
  } finally {
    busy.value = null
  }
}

async function cancel(invoice: Invoice): Promise<void> {
  const reason = window.prompt(`Annuler la facture ${invoice.number} — motif :`)

  if (!reason) return

  busy.value = invoice.id
  try {
    const { data } = await http.post(`/central/invoices/${invoice.id}/cancel`, { reason })
    toast.success(data.message)
    await load()
  } finally {
    busy.value = null
  }
}

/** Le jeton vit dans localStorage : un lien direct ne le transmettrait pas. */
async function download(invoice: Invoice): Promise<void> {
  const response = await http.get(`/central/invoices/${invoice.id}/pdf`, { responseType: 'blob' })
  const url = URL.createObjectURL(response.data as Blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `Facture_${invoice.number}.pdf`
  link.click()
  URL.revokeObjectURL(url)
}

onMounted(load)
</script>

<template>
  <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Factures d'abonnement</h3>

      <button
        v-if="canIssue"
        type="button"
        class="px-3 py-1.5 rounded-lg bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold transition-colors disabled:opacity-60"
        :disabled="busy !== null"
        @click="issue"
      >
        {{ busy === 'new' ? 'Émission…' : 'Émettre une facture' }}
      </button>
    </div>

    <!-- Identite de facturation incomplete : bloquant, et il faut dire pourquoi -->
    <p
      v-if="issuerMissing.length"
      class="mb-4 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/30 text-sm text-red-800 dark:text-red-200"
    >
      Aucune facture ne peut être émise : l'identité de facturation est incomplète
      (<strong>{{ issuerMissing.join(', ') }}</strong>). À renseigner dans le <code>.env</code> du
      serveur, puis <code>php artisan config:cache</code>.
    </p>

    <div v-if="loading" class="h-16 animate-pulse bg-gray-100 dark:bg-gray-700 rounded-lg"></div>

    <p v-else-if="!invoices.length" class="text-sm text-gray-500 dark:text-gray-400">
      Aucune facture émise pour ce client.
    </p>

    <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
      <li
        v-for="invoice in invoices"
        :key="invoice.id"
        class="py-3 flex flex-wrap items-center justify-between gap-3"
      >
        <div class="min-w-0">
          <p class="font-medium text-gray-900 dark:text-gray-100">
            {{ invoice.number }}
            <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium" :class="statusTone[invoice.status]">
              {{ statusLabels[invoice.status] }}
            </span>
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            émise le {{ formatDate(invoice.issued_at) }} ·
            période {{ formatDate(invoice.period_starts_at) }} — {{ formatDate(invoice.period_ends_at) }}
            <template v-if="invoice.status !== 'paid' && invoice.status !== 'cancelled'">
              · échéance {{ formatDate(invoice.due_at) }}
            </template>
          </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <span class="font-semibold text-gray-900 dark:text-gray-100">
            {{ money(invoice.amount_ttc_cents) }}
          </span>

          <button
            type="button"
            class="px-2.5 py-1 rounded-lg border border-gray-300 dark:border-gray-600 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
            @click="download(invoice)"
          >
            PDF
          </button>

          <button
            v-if="invoice.status !== 'cancelled'"
            type="button"
            class="px-2.5 py-1 rounded-lg border border-gray-300 dark:border-gray-600 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
            :disabled="busy === invoice.id"
            @click="resend(invoice)"
          >
            Renvoyer
          </button>

          <button
            v-if="invoice.status !== 'paid' && invoice.status !== 'cancelled'"
            type="button"
            class="px-2.5 py-1 rounded-lg border border-red-300 dark:border-red-800 text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30 disabled:opacity-50"
            :disabled="busy === invoice.id"
            @click="cancel(invoice)"
          >
            Annuler
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>
