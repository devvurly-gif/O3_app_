<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/authStore'

/**
 * Bandeau d'etat de l'abonnement, affiche sous la barre du haut.
 *
 * Ne s'affiche que quand il a quelque chose a dire : fin d'essai proche,
 * echeance depassee, acces suspendu. Un bandeau permanent finit par ne plus
 * etre lu, et c'est precisement le jour ou il compte qu'on a besoin qu'il le
 * soit.
 */
const auth = useAuthStore()
const { t } = useI18n()

const WARNING_THRESHOLD_DAYS = 7

type Tone = 'warning' | 'danger'

const state = computed<{ tone: Tone; message: string } | null>(() => {
  const sub = auth.subscription

  if (!sub) return null

  if (sub.status === 'suspended') {
    return { tone: 'danger', message: t('subscription.suspendedBanner') }
  }

  if (sub.status === 'past_due') {
    return { tone: 'danger', message: t('subscription.pastDueBanner') }
  }

  if (sub.status === 'trial') {
    const days = sub.days_left ?? 0

    if (days > WARNING_THRESHOLD_DAYS) return null

    return {
      tone: 'warning',
      message: days <= 1 ? t('subscription.trialLastDay') : t('subscription.trialBanner', { days }),
    }
  }

  return null
})

const toneClasses = computed(() =>
  state.value?.tone === 'danger'
    ? 'bg-red-50 text-red-800 border-red-200 dark:bg-red-950/50 dark:text-red-200 dark:border-red-900'
    : 'bg-amber-50 text-amber-900 border-amber-200 dark:bg-amber-950/50 dark:text-amber-200 dark:border-amber-900',
)

const buttonClasses = computed(() =>
  state.value?.tone === 'danger'
    ? 'bg-red-600 hover:bg-red-700 focus-visible:ring-red-500'
    : 'bg-amber-600 hover:bg-amber-700 focus-visible:ring-amber-500',
)
</script>

<template>
  <!-- role="status" et non "alert" : l'information est importante mais ne doit
       pas interrompre la saisie en cours d'un lecteur d'ecran. -->
  <div
    v-if="state"
    role="status"
    class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b text-sm"
    :class="toneClasses"
  >
    <div class="flex items-center gap-2 min-w-0">
      <svg class="w-5 h-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path
          fill-rule="evenodd"
          d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"
          clip-rule="evenodd"
        />
      </svg>
      <span class="font-medium">{{ state.message }}</span>
    </div>

    <RouterLink
      to="/abonnement"
      class="shrink-0 inline-flex items-center px-3 py-1.5 rounded-lg text-white text-xs font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
      :class="buttonClasses"
    >
      {{ $t('subscription.choosePlan') }}
    </RouterLink>
  </div>
</template>
