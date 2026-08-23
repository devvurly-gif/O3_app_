<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="modelValue" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-3">
        <!-- Backdrop : purement décoratif, retiré de l'arbre d'accessibilité. -->
        <div
          class="absolute inset-0 bg-black/50 backdrop-blur-sm"
          aria-hidden="true"
          @click="closeOnBackdrop && close()"
        />

        <!-- Panel -->
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 sm:scale-95 translate-y-4 sm:translate-y-0"
          enter-to-class="opacity-100 sm:scale-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 sm:scale-100 translate-y-0"
          leave-to-class="opacity-0 sm:scale-95 translate-y-4 sm:translate-y-0"
        >
          <div
            v-if="modelValue"
            ref="panel"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
            tabindex="-1"
            class="relative z-10 w-full bg-white dark:bg-gray-800 rounded-t-2xl sm:rounded-2xl shadow-2xl flex flex-col max-h-[95vh] sm:max-h-[90vh] focus:outline-none"
            :class="widthClass"
            @keydown.esc.stop="close()"
            @keydown.tab="trapFocus"
          >
            <!-- Header -->
            <div
              class="flex items-center justify-between px-4 sm:px-5 py-2.5 border-b border-gray-200 dark:border-gray-700 shrink-0"
            >
              <h3 :id="titleId" class="text-sm sm:text-base font-semibold text-gray-800 dark:text-gray-100 truncate">
                <slot name="title">{{ title }}</slot>
              </h3>
              <button
                type="button"
                :aria-label="closeLabel"
                class="shrink-0 ml-2 w-7 h-7 flex items-center justify-center rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
                @click="close()"
              >
                <svg
                  class="w-4 h-4"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Body -->
            <div class="px-4 sm:px-5 py-3 overflow-y-auto flex-1">
              <slot />
            </div>

            <!-- Footer -->
            <div
              v-if="$slots.footer"
              class="px-4 sm:px-5 py-2.5 border-t border-gray-200 dark:border-gray-700 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 shrink-0 bg-gray-50/50 dark:bg-gray-900/30 rounded-b-2xl"
            >
              <slot name="footer" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'

/**
 * Modale accessible.
 *
 * Le composant était visuellement soigné — feuille remontante sur mobile,
 * pied en flex-col-reverse pour mettre l'action principale sous le pouce —
 * mais il ne portait aucun attribut d'accessibilité. Au clavier, l'ouvrir
 * laissait le focus derrière : on tabulait dans la page masquée sans jamais
 * atteindre les champs affichés, et Échap ne fermait rien.
 *
 * Cinq manques comblés ici : le rôle et l'étiquette, la touche Échap, le
 * piège de focus, la restitution du focus à la fermeture, et le blocage du
 * défilement de la page derrière.
 */

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  title: {
    type: String,
    default: '',
  },
  size: {
    type: String,
    default: 'md',
    validator: (v: string) => ['sm', 'md', 'lg', 'xl', '2xl', '3xl'].includes(v),
  },
  closeOnBackdrop: {
    type: Boolean,
    default: true,
  },
  /** Nom accessible du bouton de fermeture, qui n'affiche qu'une croix. */
  closeLabel: {
    type: String,
    default: 'Fermer',
  },
})

const emit = defineEmits(['update:modelValue'])

// Relie le panneau à son titre : c'est ce que le lecteur d'écran annonce à
// l'ouverture. useId() garantit l'unicité quand plusieurs modales coexistent.
const titleId = useId()
const panel = ref<HTMLElement | null>(null)

/** Élément qui avait le focus avant l'ouverture, pour le lui rendre après. */
let previouslyFocused: HTMLElement | null = null

function close() {
  emit('update:modelValue', false)
}

const FOCUSABLE = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

function focusableInPanel(): HTMLElement[] {
  if (!panel.value) return []

  return (
    Array.from(panel.value.querySelectorAll<HTMLElement>(FOCUSABLE))
      // Un élément masqué reste dans le DOM mais ne doit pas recevoir le focus.
      .filter((el) => el.offsetParent !== null || el === document.activeElement)
  )
}

/**
 * Fait cycler Tab à l'intérieur du panneau. Sans ça, `aria-modal` ment : les
 * lecteurs d'écran l'annoncent comme modale, mais le clavier en sort.
 */
function trapFocus(event: KeyboardEvent) {
  const items = focusableInPanel()
  if (!items.length) {
    event.preventDefault()
    panel.value?.focus()
    return
  }

  const first = items[0]
  const last = items[items.length - 1]
  const active = document.activeElement

  if (event.shiftKey && (active === first || active === panel.value)) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && active === last) {
    event.preventDefault()
    first.focus()
  }
}

/**
 * Compteur partagé plutôt qu'un simple booléen : une modale ouverte par-dessus
 * une autre ne doit pas rendre le défilement à la page en se refermant.
 */
let openCount = 0

function lockScroll() {
  if (openCount++ === 0) {
    document.body.style.overflow = 'hidden'
  }
}

function unlockScroll() {
  if (openCount > 0 && --openCount === 0) {
    document.body.style.overflow = ''
  }
}

watch(
  () => props.modelValue,
  async (open, wasOpen) => {
    if (open) {
      previouslyFocused = document.activeElement as HTMLElement | null
      lockScroll()

      // Le panneau n'existe qu'après le rendu de la transition.
      await nextTick()
      // On vise le panneau plutôt que son premier élément focusable — qui est
      // le bouton de fermeture — pour que le lecteur d'écran annonce le titre
      // avant toute autre chose.
      panel.value?.focus()
      return
    }

    if (wasOpen) {
      unlockScroll()
      previouslyFocused?.focus()
      previouslyFocused = null
    }
  },
  { immediate: true },
)

// Une modale démontée alors qu'elle est ouverte — navigation, v-if du parent —
// laisserait le défilement bloqué.
onBeforeUnmount(() => {
  if (props.modelValue) {
    unlockScroll()
  }
})

const widthClass = computed(
  () =>
    ({
      sm: 'max-w-sm',
      md: 'max-w-lg',
      lg: 'max-w-2xl',
      xl: 'max-w-4xl',
      '2xl': 'max-w-5xl',
      '3xl': 'max-w-6xl',
    })[props.size] ?? 'max-w-lg',
)
</script>
