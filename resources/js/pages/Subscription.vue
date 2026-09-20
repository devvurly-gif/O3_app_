<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '@/services/http'
import { usePlanLabels } from '@/composables/usePlanLabels'
import { useAuthStore } from '@/stores/authStore'
import { useToastStore } from '@/stores/toastStore'
import type { Plan, SubscriptionDetail, SubscriptionInvoice } from '@/types'

/**
 * Ecran « choisir une formule ».
 *
 * Joignable meme quand l'abonnement est echu ou suspendu : les routes
 * /api/subscription sont volontairement exclues du middleware `tenant.active`,
 * sans quoi un client impaye n'aurait aucun moyen de regulariser.
 */
const { t } = useI18n()
const auth = useAuthStore()
const toast = useToastStore()
const { featureLabel, formatMad } = usePlanLabels()

const detail = ref<SubscriptionDetail | null>(null)
const loading = ref(true)
const submitting = ref<string | null>(null)
const period = ref<'monthly' | 'yearly'>('monthly')
const note = ref('')
const invoices = ref<SubscriptionInvoice[]>([])

const plans = computed<Plan[]>(() => detail.value?.plans ?? [])

const statusTone = computed(() => {
  switch (detail.value?.status) {
    case 'suspended':
    case 'past_due':
      return 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300'
    case 'trial':
      return 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'
    default:
      return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
  }
})

function price(plan: Plan): string {
  const cents = period.value === 'yearly' ? plan.price_year_cents : plan.price_month_cents

  return (cents / 100).toLocaleString('fr-MA', { maximumFractionDigits: 0 }) + ' MAD'
}

function formatDate(value: string | null): string {
  return value ? new Date(value).toLocaleDateString('fr-MA') : '—'
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const { data } = await http.get<SubscriptionDetail>('/subscription')
    detail.value = data
    period.value = 'monthly'
  } finally {
    loading.value = false
  }
}

async function loadInvoices(): Promise<void> {
  // Sépare du chargement de la formule : un client dont les factures
  // n'arriveraient pas doit quand même pouvoir choisir son offre.
  try {
    const { data } = await http.get<{ data: SubscriptionInvoice[] }>('/subscription/invoices')
    invoices.value = data.data
  } catch {
    invoices.value = []
  }
}

/**
 * Téléchargement par le navigateur.
 *
 * Passe par une requête authentifiée plutôt que par un lien direct : le jeton
 * vit dans localStorage, une balise <a> ne le transmettrait pas.
 */
async function downloadInvoice(invoice: SubscriptionInvoice): Promise<void> {
  const response = await http.get(`/subscription/invoices/${invoice.id}/pdf`, {
    responseType: 'blob',
  })

  const url = URL.createObjectURL(response.data as Blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `Facture_${invoice.number}.pdf`
  link.click()
  URL.revokeObjectURL(url)
}

async function request(plan: Plan): Promise<void> {
  submitting.value = plan.key
  try {
    await http.post('/subscription/request', {
      plan: plan.key,
      billing_period: period.value,
      note: note.value || null,
    })
    toast.success(t('subscription.requestSuccess'))
    await load()
  } catch {
    // L'intercepteur http affiche deja le detail de l'erreur ; ce message
    // ajoute seulement ce qu'il faut faire ensuite.
    toast.error(t('subscription.requestFailed'))
  } finally {
    submitting.value = null
  }
}

onMounted(async () => {
  await load()
  await loadInvoices()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Etat courant -->
    <section
      class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5"
    >
      <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
        {{ $t('subscription.title') }}
      </h1>

      <div v-if="loading" class="h-16 animate-pulse bg-gray-100 dark:bg-gray-800 rounded-lg"></div>

      <dl v-else-if="detail" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div>
          <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $t('subscription.currentPlan') }}</dt>
          <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ detail.plan_name }}</dd>
        </div>
        <div>
          <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $t('subscription.status') }}</dt>
          <dd class="mt-1">
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold" :class="statusTone">
              {{ detail.status_label }}
            </span>
          </dd>
        </div>
        <div>
          <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $t('subscription.endsAt') }}</dt>
          <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
            {{ formatDate(detail.subscription_ends_at) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $t('subscription.daysLeft') }}</dt>
          <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
            {{ detail.days_left ?? '—' }}
          </dd>
        </div>
      </dl>

      <p
        v-if="detail?.requested_plan"
        class="mt-4 px-4 py-3 rounded-lg bg-blue-50 dark:bg-blue-950/50 text-sm text-blue-800 dark:text-blue-200"
      >
        {{ $t('subscription.requestPending', { plan: detail.requested_plan }) }}
      </p>
    </section>

    <!-- Periodicite -->
    <div v-if="!loading" class="flex justify-center">
      <div
        class="inline-flex rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-1"
      >
        <button
          type="button"
          class="px-4 py-1.5 rounded-md text-sm font-medium transition-colors"
          :class="
            period === 'monthly'
              ? 'bg-orange-600 text-white'
              : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
          "
          @click="period = 'monthly'"
        >
          {{ $t('subscription.monthly') }}
        </button>
        <button
          type="button"
          class="px-4 py-1.5 rounded-md text-sm font-medium transition-colors"
          :class="
            period === 'yearly'
              ? 'bg-orange-600 text-white'
              : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
          "
          @click="period = 'yearly'"
        >
          {{ $t('subscription.yearly') }}
          <span class="ml-1 text-xs opacity-80">({{ $t('subscription.yearlySaving') }})</span>
        </button>
      </div>
    </div>

    <!-- Formules -->
    <section class="grid gap-4 md:grid-cols-3">
      <article
        v-for="plan in plans"
        :key="plan.key"
        class="flex flex-col bg-white dark:bg-gray-900 rounded-xl border p-5"
        :class="
          plan.key === detail?.plan
            ? 'border-orange-500 ring-1 ring-orange-500'
            : 'border-gray-200 dark:border-gray-800'
        "
      >
        <header>
          <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ plan.name }}</h2>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ plan.tagline }}</p>
        </header>

        <p class="mt-4">
          <span class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ price(plan) }}</span>
          <span class="ml-1 text-sm text-gray-500 dark:text-gray-400">
            {{ period === 'yearly' ? $t('subscription.perYear') : $t('subscription.perMonth') }}
          </span>
        </p>

        <ul class="mt-4 space-y-1.5 text-sm text-gray-700 dark:text-gray-300 flex-1">
          <li v-if="plan.limits.users" class="flex gap-2">
            <span aria-hidden="true">•</span>
            {{ $t('subscription.usersIncluded', { count: plan.limits.users }) }}
          </li>
          <li v-for="feature in plan.features" :key="feature" class="flex gap-2">
            <span aria-hidden="true">•</span>
            {{ featureLabel(feature) }}
          </li>
        </ul>

        <p
          v-if="plan.key === detail?.plan"
          class="mt-5 text-center text-sm font-semibold text-orange-600 dark:text-orange-400"
        >
          {{ $t('subscription.currentlyActive') }}
        </p>

        <button
          v-else-if="auth.isAdmin"
          type="button"
          class="mt-5 w-full px-4 py-2 rounded-lg bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold transition-colors disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2"
          :disabled="submitting !== null"
          @click="request(plan)"
        >
          {{ submitting === plan.key ? $t('subscription.requesting') : $t('subscription.requestPlan') }}
        </button>
      </article>
    </section>

    <!-- Factures -->
    <section
      v-if="invoices.length"
      class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5"
    >
      <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">
        {{ $t('subscription.invoices.title') }}
      </h2>

      <ul class="divide-y divide-gray-100 dark:divide-gray-800">
        <li
          v-for="invoice in invoices"
          :key="invoice.id"
          class="py-3 flex flex-wrap items-center justify-between gap-3"
        >
          <div class="min-w-0">
            <p class="font-medium text-gray-900 dark:text-gray-100">{{ invoice.number }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ formatDate(invoice.period_starts_at) }} — {{ formatDate(invoice.period_ends_at) }}
              <template v-if="invoice.status !== 'paid'">
                · {{ $t('subscription.invoices.dueOn', { date: formatDate(invoice.due_at) }) }}
              </template>
            </p>
          </div>

          <div class="flex items-center gap-3 shrink-0">
            <span
              class="px-2.5 py-1 rounded-full text-xs font-semibold"
              :class="
                invoice.status === 'paid'
                  ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                  : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'
              "
            >
              {{ $t(`subscription.invoices.statuses.${invoice.status}`) }}
            </span>

            <span class="font-semibold text-gray-900 dark:text-gray-100">
              {{ formatMad(invoice.amount_ttc_cents) }}
            </span>

            <button
              type="button"
              class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
              @click="downloadInvoice(invoice)"
            >
              {{ $t('subscription.invoices.download') }}
            </button>
          </div>
        </li>
      </ul>
    </section>

    <!-- Message libre -->
    <section
      v-if="auth.isAdmin && !loading"
      class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5"
    >
      <label
        for="subscription-note"
        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
      >
        {{ $t('subscription.noteLabel') }}
      </label>
      <textarea
        id="subscription-note"
        v-model="note"
        rows="3"
        :placeholder="$t('subscription.notePlaceholder')"
        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500"
      ></textarea>
    </section>
  </div>
</template>
