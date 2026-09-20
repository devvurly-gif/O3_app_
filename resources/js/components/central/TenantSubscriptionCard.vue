<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import http from '@/services/http'
import { useToastStore } from '@/stores/toastStore'

/**
 * Abonnement d'un tenant, cote back-office O3App : etat, encaissement,
 * historique des reglements.
 *
 * L'encaissement est manuel et assume — au Maroc, sur ce segment, le client
 * paie par virement ou par cheque. Ce qui doit rester automatique, c'est la
 * consequence : l'echeance repoussee, le statut remis a jour et les capacites
 * de la formule appliquees. C'est exactement ce que fait le bouton ci-dessous.
 */
const props = defineProps<{ tenantId: string }>()
const emit = defineEmits<{ (e: 'updated'): void }>()

const toast = useToastStore()

interface Summary {
  status: string
  status_label: string
  plan: string
  plan_name: string
  subscription_ends_at: string | null
  days_left: number | null
  requested_plan: string | null
  plans: { key: string; name: string; price_month_cents: number; price_year_cents: number }[]
}

interface Payment {
  id: number
  plan: string
  billing_period: string
  amount_cents: number
  paid_at: string
  period_ends_at: string
  method: string
  reference: string | null
}

const summary = ref<Summary | null>(null)
const payments = ref<Payment[]>([])
const loading = ref(true)
const saving = ref(false)

const form = ref({
  plan: '',
  billing_period: 'monthly' as 'monthly' | 'yearly',
  amount: '' as string,
  paid_at: new Date().toISOString().slice(0, 10),
  method: 'virement',
  reference: '',
})

const statusTone = computed(() => {
  switch (summary.value?.status) {
    case 'suspended':
    case 'past_due':
      return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
    case 'trial':
      return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
    case 'pending':
      return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
    default:
      return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
  }
})

/** Tarif catalogue de la formule et de la periodicite choisies, en dirhams. */
const catalogPrice = computed<number | null>(() => {
  const plan = summary.value?.plans.find((p) => p.key === form.value.plan)

  if (!plan) return null

  const cents = form.value.billing_period === 'yearly' ? plan.price_year_cents : plan.price_month_cents

  return cents / 100
})

function money(cents: number): string {
  return (cents / 100).toLocaleString('fr-MA', { maximumFractionDigits: 2 }) + ' MAD'
}

function formatDate(value: string | null): string {
  return value ? new Date(value).toLocaleDateString('fr-MA') : '—'
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const { data } = await http.get(`/central/tenants/${props.tenantId}/subscription`)
    summary.value = data.subscription
    payments.value = data.payments
    form.value.plan = data.subscription.requested_plan || data.subscription.plan
  } finally {
    loading.value = false
  }
}

async function recordPayment(): Promise<void> {
  saving.value = true
  try {
    // Montant laisse vide = tarif catalogue. Une remise negociee se saisit
    // telle quelle, sinon l'historique cesse de refleter ce qui a ete encaisse.
    const { data } = await http.post(`/central/tenants/${props.tenantId}/subscription/payment`, {
      plan: form.value.plan,
      billing_period: form.value.billing_period,
      amount_cents: form.value.amount === '' ? null : Math.round(Number(form.value.amount) * 100),
      paid_at: form.value.paid_at,
      method: form.value.method,
      reference: form.value.reference || null,
    })

    toast.success(data.message)
    form.value.amount = ''
    form.value.reference = ''

    await load()
    emit('updated')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">Abonnement</h3>

    <div v-if="loading" class="h-20 animate-pulse bg-gray-100 dark:bg-gray-700 rounded-lg"></div>

    <template v-else-if="summary">
      <!-- Etat -->
      <dl class="grid grid-cols-2 gap-4 mb-5 sm:grid-cols-4">
        <div>
          <dt class="text-xs text-gray-500 dark:text-gray-400">Formule</dt>
          <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
            {{ summary.plan_name }}
          </dd>
        </div>
        <div>
          <dt class="text-xs text-gray-500 dark:text-gray-400">Statut</dt>
          <dd class="mt-1">
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium" :class="statusTone">
              {{ summary.status_label }}
            </span>
          </dd>
        </div>
        <div>
          <dt class="text-xs text-gray-500 dark:text-gray-400">Échéance</dt>
          <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
            {{ formatDate(summary.subscription_ends_at) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs text-gray-500 dark:text-gray-400">Jours restants</dt>
          <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
            {{ summary.days_left ?? '—' }}
          </dd>
        </div>
      </dl>

      <p
        v-if="summary.requested_plan"
        class="mb-5 px-4 py-3 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-sm text-blue-800 dark:text-blue-200"
      >
        Le client a demandé la formule <strong>{{ summary.requested_plan }}</strong> — contrat et
        facture à envoyer.
      </p>

      <!-- Encaisser -->
      <form class="space-y-3 border-t border-gray-100 dark:border-gray-700 pt-4" @submit.prevent="recordPayment">
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Enregistrer un règlement</p>

        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block">
            <span class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Formule</span>
            <select
              v-model="form.plan"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm"
            >
              <option v-for="plan in summary.plans" :key="plan.key" :value="plan.key">
                {{ plan.name }}
              </option>
            </select>
          </label>

          <label class="block">
            <span class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Périodicité</span>
            <select
              v-model="form.billing_period"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm"
            >
              <option value="monthly">Mensuel</option>
              <option value="yearly">Annuel</option>
            </select>
          </label>

          <label class="block">
            <span class="block text-xs text-gray-500 dark:text-gray-400 mb-1">
              Montant HT (MAD)
              <span v-if="catalogPrice !== null" class="text-gray-400">
                — catalogue : {{ catalogPrice.toLocaleString('fr-MA') }}
              </span>
            </span>
            <input
              v-model="form.amount"
              type="number"
              min="0"
              step="0.01"
              :placeholder="catalogPrice !== null ? String(catalogPrice) : ''"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm"
            />
          </label>

          <label class="block">
            <span class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Date de règlement</span>
            <input
              v-model="form.paid_at"
              type="date"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm"
            />
          </label>

          <label class="block">
            <span class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Moyen</span>
            <select
              v-model="form.method"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm"
            >
              <option value="virement">Virement</option>
              <option value="cheque">Chèque</option>
              <option value="especes">Espèces</option>
              <option value="carte">Carte</option>
            </select>
          </label>

          <label class="block">
            <span class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Référence</span>
            <input
              v-model="form.reference"
              type="text"
              placeholder="N° de chèque, référence du virement…"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm"
            />
          </label>
        </div>

        <button
          type="submit"
          :disabled="saving"
          class="w-full px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition-colors disabled:opacity-60"
        >
          {{ saving ? 'Enregistrement…' : "Encaisser et repousser l'échéance" }}
        </button>
      </form>

      <!-- Historique -->
      <div v-if="payments.length" class="mt-5 border-t border-gray-100 dark:border-gray-700 pt-4">
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Règlements</p>
        <ul class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
          <li v-for="payment in payments" :key="payment.id" class="py-2 flex justify-between gap-3">
            <span class="text-gray-600 dark:text-gray-400">
              {{ formatDate(payment.paid_at) }} · {{ payment.plan }} ·
              {{ payment.billing_period === 'yearly' ? 'annuel' : 'mensuel' }}
              <span v-if="payment.reference" class="text-gray-400">· {{ payment.reference }}</span>
            </span>
            <span class="font-semibold text-gray-900 dark:text-gray-100 shrink-0">
              {{ money(payment.amount_cents) }}
            </span>
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>
