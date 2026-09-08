<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h2 class="text-xl font-bold text-gray-900 dark:text-white">Révision des prix de vente</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
        Recalculer le prix de vente d'un lot de produits, après avoir vu le chiffrage.
      </p>
    </div>

    <!-- Sans le droit, l'ecran le dit plutot que de laisser l'API refuser -->
    <div
      v-if="!canReprice"
      class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-5"
    >
      <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Droit manquant</p>
      <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
        La révision des prix demande la permission «&nbsp;Produits — Modifier&nbsp;». Un administrateur peut l'accorder
        depuis Paramètres → Rôles.
      </p>
    </div>

    <template v-else>
      <!-- ── 1. Périmètre ─────────────────────────────────────────── -->
      <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
        <div class="flex items-baseline gap-2">
          <span
            class="w-6 h-6 shrink-0 rounded-full bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 text-xs font-bold grid place-items-center"
            >1</span
          >
          <h3 class="text-sm font-bold text-gray-900 dark:text-white">Périmètre</h3>
          <span class="text-xs text-gray-400 dark:text-gray-500">sans filtre, tout le catalogue</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Catégories</label>
            <div
              class="max-h-40 overflow-y-auto rounded-lg border border-gray-300 dark:border-gray-600 divide-y divide-gray-100 dark:divide-gray-700"
            >
              <p v-if="!categories.length" class="px-3 py-2 text-sm text-gray-400">Aucune catégorie</p>
              <label
                v-for="c in categories"
                :key="c.id"
                class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer"
              >
                <input v-model="filters.category_ids" type="checkbox" :value="c.id" class="rounded" />
                {{ c.ctg_title }}
              </label>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Marques</label>
            <div
              class="max-h-40 overflow-y-auto rounded-lg border border-gray-300 dark:border-gray-600 divide-y divide-gray-100 dark:divide-gray-700"
            >
              <p v-if="!brands.length" class="px-3 py-2 text-sm text-gray-400">Aucune marque</p>
              <label
                v-for="b in brands"
                :key="b.id"
                class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer"
              >
                <input v-model="filters.brand_ids" type="checkbox" :value="b.id" class="rounded" />
                {{ b.br_title }}
              </label>
            </div>
          </div>

          <div>
            <label for="bp-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
              >Statut</label
            >
            <select
              id="bp-status"
              v-model="filters.status"
              class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm"
            >
              <option value="all">Tous</option>
              <option value="active">Actifs uniquement</option>
              <option value="inactive">Inactifs uniquement</option>
            </select>
          </div>

          <div>
            <label for="bp-search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              Recherche <span class="text-gray-400 font-normal">(titre, SKU, code)</span>
            </label>
            <input
              id="bp-search"
              v-model.trim="filters.search"
              type="text"
              placeholder="ex. clavier"
              class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm"
            />
          </div>
        </div>
      </section>

      <!-- ── 2. Règle ─────────────────────────────────────────────── -->
      <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
        <div class="flex items-baseline gap-2">
          <span
            class="w-6 h-6 shrink-0 rounded-full bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 text-xs font-bold grid place-items-center"
            >2</span
          >
          <h3 class="text-sm font-bold text-gray-900 dark:text-white">Règle de calcul</h3>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="m in modes"
            :key="m.key"
            type="button"
            class="px-3 py-1.5 text-sm font-semibold rounded-lg border transition"
            :class="
              rule.mode === m.key
                ? 'bg-orange-500 border-orange-500 text-white'
                : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
            "
            @click="selectMode(m.key)"
          >
            {{ m.label }}
          </button>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">{{ currentMode.hint }}</p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label for="bp-value" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              {{ currentMode.valueLabel }}
            </label>
            <input
              id="bp-value"
              v-model.number="rule.value"
              type="number"
              step="0.01"
              class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm"
            />
          </div>

          <div v-if="rule.mode === 'margin'">
            <label for="bp-basis" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Base</label>
            <select
              id="bp-basis"
              v-model="rule.basis"
              class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm"
            >
              <option value="purchase">Prix d'achat</option>
              <option value="cost">Coût de revient</option>
            </select>
          </div>

          <div>
            <label for="bp-rounding" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
              >Arrondi</label
            >
            <select
              id="bp-rounding"
              v-model="rule.rounding"
              class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm"
            >
              <option value="none">Aucun (au centime)</option>
              <option value="0.05">5 centimes</option>
              <option value="0.10">10 centimes</option>
              <option value="0.50">50 centimes</option>
              <option value="1">1 DH</option>
              <option value="5">5 DH</option>
              <option value="10">10 DH</option>
              <option value="end_90">Se termine par ,90</option>
              <option value="end_99">Se termine par ,99</option>
            </select>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-1">
          <button
            class="px-4 py-2 text-sm font-semibold rounded-lg bg-orange-500 hover:bg-orange-600 text-white transition disabled:opacity-60"
            :disabled="previewing"
            @click="runPreview"
          >
            {{ previewing ? 'Chiffrage…' : 'Chiffrer' }}
          </button>
          <button
            v-if="preview"
            class="px-4 py-2 text-sm font-semibold rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
            @click="resetAll"
          >
            Réinitialiser
          </button>
        </div>
      </section>

      <!-- ── 3. Chiffrage ─────────────────────────────────────────── -->
      <section
        v-if="preview"
        class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4"
      >
        <div class="flex items-baseline gap-2">
          <span
            class="w-6 h-6 shrink-0 rounded-full bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 text-xs font-bold grid place-items-center"
            >3</span
          >
          <h3 class="text-sm font-bold text-gray-900 dark:text-white">Chiffrage</h3>
          <span class="text-xs text-gray-400 dark:text-gray-500">rien n'est encore écrit</span>
        </div>

        <div class="flex flex-wrap gap-2">
          <span class="chip bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
            {{ preview.matched }} produit(s) dans le périmètre
          </span>
          <span class="chip bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
            {{ preview.changed }} à modifier
          </span>
          <span v-if="preview.unchanged" class="chip bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
            {{ preview.unchanged }} inchangé(s)
          </span>
          <span
            v-if="preview.skipped_no_basis"
            class="chip bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400"
          >
            {{ preview.skipped_no_basis }} ignoré(s) — base à zéro
          </span>
          <span v-if="preview.negative" class="chip bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">
            {{ preview.negative }} prix négatif(s)
          </span>
        </div>

        <p v-if="preview.negative" class="text-sm text-red-600 dark:text-red-400">
          Un prix négatif bloque l'opération. Corrigez la règle avant d'appliquer.
        </p>
        <p v-else-if="preview.matched > preview.max_products" class="text-sm text-red-600 dark:text-red-400">
          Lot trop grand : {{ preview.matched }} produits pour un maximum de {{ preview.max_products }}. Affinez les
          filtres.
        </p>
        <p v-else-if="!preview.changed" class="text-sm text-gray-500 dark:text-gray-400">
          Cette règle ne change aucun prix.
        </p>

        <!-- Echantillon -->
        <div v-if="preview.sample.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr
                class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700"
              >
                <th class="py-2 pr-3 font-semibold">Code</th>
                <th class="py-2 pr-3 font-semibold">Produit</th>
                <th class="py-2 pr-3 font-semibold text-right">Avant</th>
                <th class="py-2 pr-3 font-semibold text-right">Après</th>
                <th class="py-2 font-semibold text-right">Écart</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="row in preview.sample" :key="row.id">
                <td class="py-1.5 pr-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ row.p_code }}</td>
                <td class="py-1.5 pr-3 text-gray-800 dark:text-gray-200">{{ row.p_title }}</td>
                <td class="py-1.5 pr-3 text-right tabular-nums text-gray-500 dark:text-gray-400">
                  {{ formatAmount(row.current) }}
                </td>
                <td class="py-1.5 pr-3 text-right tabular-nums font-semibold text-gray-900 dark:text-white">
                  {{ formatAmount(row.new) }}
                </td>
                <td
                  class="py-1.5 text-right tabular-nums font-medium"
                  :class="row.delta >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                >
                  {{ row.delta >= 0 ? '+' : '' }}{{ formatAmount(row.delta) }}
                </td>
              </tr>
            </tbody>
          </table>
          <p v-if="preview.changed > preview.sample.length" class="text-xs text-gray-400 dark:text-gray-500 mt-2">
            {{ preview.sample.length }} premières lignes sur {{ preview.changed }} à modifier.
          </p>
        </div>

        <div class="pt-1">
          <button
            class="px-4 py-2 text-sm font-semibold rounded-lg bg-red-600 hover:bg-red-700 text-white transition disabled:opacity-60"
            :disabled="!canApply || applying"
            @click="showConfirm = true"
          >
            Appliquer aux {{ preview.changed }} produit(s)
          </button>
        </div>
      </section>

      <!-- Résultat -->
      <div
        v-if="result"
        class="rounded-xl border p-4 text-sm"
        :class="
          result.ok
            ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300'
            : 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-300'
        "
      >
        {{ result.message }}
      </div>

      <!-- Confirmation -->
      <BaseModal v-model="showConfirm" title="Appliquer les nouveaux prix ?" size="sm">
        <div class="space-y-3 text-sm text-gray-700 dark:text-gray-300">
          <p>
            <span class="font-semibold">{{ preview?.changed }}</span> produit(s) vont changer de prix de vente.
            L'opération n'est pas réversible d'un clic — chaque changement reste tracé dans la piste d'audit.
          </p>
          <p class="text-gray-500 dark:text-gray-400">{{ ruleSummary }}</p>
        </div>
        <template #footer>
          <div class="flex justify-end gap-2">
            <button
              class="px-4 py-2 text-sm font-semibold rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
              @click="showConfirm = false"
            >
              Annuler
            </button>
            <button
              class="px-4 py-2 text-sm font-semibold rounded-lg bg-red-600 hover:bg-red-700 text-white transition disabled:opacity-60"
              :disabled="applying"
              @click="runApply"
            >
              {{ applying ? 'Application…' : 'Appliquer' }}
            </button>
          </div>
        </template>
      </BaseModal>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import http from '@/services/http'
import BaseModal from '@/components/BaseModal.vue'
import { useAuthStore } from '@/stores/authStore'
import { useCategoryStore } from '@/stores/category'
import { useBrandStore } from '@/stores/brand'
import { formatAmount } from '@/composables/useFormat'
import { storeToRefs } from 'pinia'

type Mode = 'percent' | 'amount' | 'margin' | 'set'

interface PreviewRow {
  id: number
  p_code: string
  p_title: string
  current: number
  new: number
  delta: number
}

interface PreviewResponse {
  matched: number
  changed: number
  unchanged: number
  skipped_no_basis: number
  negative: number
  sample: PreviewRow[]
  max_products: number
}

const auth = useAuthStore()
const canReprice = computed(() => auth.hasPermission('products.update'))

const categoryStore = useCategoryStore()
const brandStore = useBrandStore()
const { items: categories } = storeToRefs(categoryStore)
const { items: brands } = storeToRefs(brandStore)

const filters = reactive({
  category_ids: [] as number[],
  brand_ids: [] as number[],
  status: 'all',
  search: '',
})

const rule = reactive({
  mode: 'percent' as Mode,
  value: 0,
  basis: 'purchase' as 'purchase' | 'cost',
  rounding: 'none',
})

const modes = [
  {
    key: 'percent' as Mode,
    label: 'Pourcentage',
    valueLabel: 'Variation (%)',
    hint: 'Applique un pourcentage au prix de vente actuel. −10 pour une baisse de 10 %.',
  },
  {
    key: 'amount' as Mode,
    label: 'Montant fixe',
    valueLabel: 'Variation (DH)',
    hint: 'Ajoute ou retire un montant au prix de vente actuel. −25 pour retirer 25 DH.',
  },
  {
    key: 'margin' as Mode,
    label: 'Marge sur achat',
    valueLabel: 'Marge (%)',
    hint: "Recalcule le prix depuis le prix d'achat : base × (1 + marge). Les produits dont la base est à zéro sont ignorés.",
  },
  {
    key: 'set' as Mode,
    label: 'Prix fixe',
    valueLabel: 'Prix (DH)',
    hint: 'Impose le même prix de vente à tout le périmètre.',
  },
]

const currentMode = computed(() => modes.find((m) => m.key === rule.mode) ?? modes[0])

const previewing = ref(false)
const applying = ref(false)
const preview = ref<PreviewResponse | null>(null)
const showConfirm = ref(false)
const result = ref<{ ok: boolean; message: string } | null>(null)

const canApply = computed(
  () =>
    !!preview.value &&
    preview.value.changed > 0 &&
    preview.value.negative === 0 &&
    preview.value.matched <= preview.value.max_products,
)

const ruleSummary = computed(() => {
  const v = rule.value
  const round = rule.rounding === 'none' ? '' : `, arrondi « ${rule.rounding} »`
  switch (rule.mode) {
    case 'percent':
      return `${v > 0 ? '+' : ''}${v} % sur le prix actuel${round}.`
    case 'amount':
      return `${v > 0 ? '+' : ''}${v} DH sur le prix actuel${round}.`
    case 'margin':
      return `Marge de ${v} % sur ${rule.basis === 'cost' ? 'le coût de revient' : "le prix d'achat"}${round}.`
    default:
      return `Prix fixé à ${v} DH${round}.`
  }
})

function payload() {
  return {
    category_ids: filters.category_ids,
    brand_ids: filters.brand_ids,
    status: filters.status,
    search: filters.search || null,
    mode: rule.mode,
    value: rule.value,
    basis: rule.basis,
    rounding: rule.rounding,
  }
}

function selectMode(mode: Mode) {
  rule.mode = mode
  // Le chiffrage affiche a l'ecran ne vaut plus pour la nouvelle regle.
  preview.value = null
}

async function runPreview() {
  previewing.value = true
  result.value = null
  preview.value = null

  try {
    const { data } = await http.post<PreviewResponse>('/products/bulk-price/preview', payload())
    preview.value = data
  } catch (err: unknown) {
    result.value = { ok: false, message: errorMessage(err, 'Le chiffrage a échoué.') }
  } finally {
    previewing.value = false
  }
}

async function runApply() {
  if (!preview.value) return
  applying.value = true

  try {
    const { data } = await http.post<{ message: string; updated: number }>('/products/bulk-price/apply', {
      ...payload(),
      expected_count: preview.value.matched,
    })
    result.value = { ok: true, message: data.message }
    preview.value = null
  } catch (err: unknown) {
    result.value = { ok: false, message: errorMessage(err, "L'application a échoué.") }
  } finally {
    applying.value = false
    showConfirm.value = false
  }
}

function errorMessage(err: unknown, fallback: string): string {
  const e = err as { response?: { data?: { message?: string } } }
  return e.response?.data?.message || fallback
}

function resetAll() {
  filters.category_ids = []
  filters.brand_ids = []
  filters.status = 'all'
  filters.search = ''
  rule.mode = 'percent'
  rule.value = 0
  rule.basis = 'purchase'
  rule.rounding = 'none'
  preview.value = null
  result.value = null
}

onMounted(() => {
  if (!canReprice.value) return
  categoryStore.fetchAll()
  brandStore.fetchAll()
})
</script>

<style scoped>
.chip {
  @apply inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium;
}
</style>
