<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $t('documentTemplates.title') }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $t('documentTemplates.subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          class="px-4 py-2 text-sm font-semibold rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-60"
          :disabled="previewing"
          @click="openPreview"
        >
          {{ previewing ? $t('documentTemplates.previewing') : $t('documentTemplates.preview') }}
        </button>
        <button :class="btnClass" :disabled="saving || !editable" @click="save">
          {{ saving ? $t('common.saving') : $t('common.save') }}
        </button>
      </div>
    </div>

    <div v-if="store.loading" class="text-sm text-gray-400 dark:text-gray-500">{{ $t('common.loading') }}</div>

    <div v-else class="flex flex-col lg:flex-row gap-6">
      <!-- ── Liste des documents ─────────────────────────────── -->
      <aside class="lg:w-72 shrink-0">
        <nav class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-2 space-y-0.5">
          <button
            v-for="entry in entries"
            :key="entry.key"
            class="w-full text-left px-3 py-2 rounded-lg text-sm transition flex items-center justify-between gap-2"
            :class="
              entry.key === selected
                ? 'bg-orange-50 dark:bg-orange-500/10 text-orange-700 dark:text-orange-300 font-semibold'
                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
            "
            @click="select(entry.key)"
          >
            <span>{{ entry.label }}</span>
            <span
              v-if="entry.key !== DEFAULT_KEY && store.isCustomised(entry.key)"
              class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-orange-100 dark:bg-orange-500/20 text-orange-700 dark:text-orange-300"
            >
              {{ $t('documentTemplates.custom') }}
            </span>
          </button>
        </nav>
      </aside>

      <!-- ── Formulaire ──────────────────────────────────────── -->
      <div class="flex-1 space-y-6">
        <!-- Bascule : ce type suit-il le réglage général ? -->
        <section
          v-if="selected !== DEFAULT_KEY"
          class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 flex items-start justify-between gap-4"
        >
          <div>
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
              {{ $t('documentTemplates.ownLayout') }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
              {{ overridden ? $t('documentTemplates.ownLayoutOn') : $t('documentTemplates.ownLayoutOff') }}
            </p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer shrink-0">
            <input type="checkbox" class="sr-only peer" :checked="overridden" @change="toggleOverride" />
            <div :class="toggleClass"></div>
          </label>
        </section>

        <fieldset :disabled="!editable" :class="editable ? '' : 'opacity-60'" class="space-y-6">
          <!-- Identité visuelle -->
          <section
            class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4"
          >
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">
              {{ $t('documentTemplates.sectionBranding') }}
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.accentColor') }}</label>
                <div class="flex items-center gap-2">
                  <input
                    v-model="form.accent_color"
                    type="color"
                    class="h-10 w-14 rounded border border-gray-300 dark:border-gray-600 bg-transparent"
                  />
                  <input v-model="form.accent_color" type="text" :class="inputClass" />
                </div>
              </div>
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.textColor') }}</label>
                <div class="flex items-center gap-2">
                  <input
                    v-model="form.text_color"
                    type="color"
                    class="h-10 w-14 rounded border border-gray-300 dark:border-gray-600 bg-transparent"
                  />
                  <input v-model="form.text_color" type="text" :class="inputClass" />
                </div>
              </div>
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.font') }}</label>
                <select v-model="form.font_family" :class="inputClass">
                  <option v-for="font in store.options.fonts" :key="font" :value="font">
                    {{ fontLabels[font] ?? font }}
                  </option>
                </select>
              </div>
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.fontSize') }}</label>
                <input v-model.number="form.font_size" type="number" min="7" max="16" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.paper') }}</label>
                <select v-model="form.paper_size" :class="inputClass">
                  <option v-for="paper in store.options.papers" :key="paper" :value="paper">
                    {{ paper.toUpperCase() }}
                  </option>
                </select>
              </div>
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.orientation') }}</label>
                <select v-model="form.orientation" :class="inputClass">
                  <option v-for="o in store.options.orientations" :key="o" :value="o">
                    {{ $t(`documentTemplates.orientation_${o}`) }}
                  </option>
                </select>
              </div>
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.marginX') }}</label>
                <input v-model.number="form.margin_x" type="number" min="10" max="80" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.marginY') }}</label>
                <input v-model.number="form.margin_y" type="number" min="10" max="80" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.currency') }}</label>
                <input v-model="form.currency" type="text" maxlength="8" :class="inputClass" />
              </div>
            </div>
          </section>

          <!-- En-tête -->
          <section
            class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4"
          >
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">
              {{ $t('documentTemplates.sectionHeader') }}
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.logoPosition') }}</label>
                <select v-model="form.logo_position" :class="inputClass">
                  <option v-for="p in store.options.logo_positions" :key="p" :value="p">
                    {{ $t(`documentTemplates.logo_${p}`) }}
                  </option>
                </select>
              </div>
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.logoHeight') }}</label>
                <input v-model.number="form.logo_height" type="number" min="20" max="160" :class="inputClass" />
              </div>
              <!-- Le titre ne se règle que par document : au niveau général
                   il renommerait factures et bons de livraison à l'identique. -->
              <div v-if="selected !== DEFAULT_KEY">
                <label :class="labelClass">{{ $t('documentTemplates.titleOverride') }}</label>
                <input
                  v-model="form.title_override"
                  type="text"
                  maxlength="60"
                  :placeholder="defaultTitle"
                  :class="inputClass"
                />
              </div>
            </div>
            <div>
              <label :class="labelClass">{{ $t('documentTemplates.headerNote') }}</label>
              <textarea v-model="form.header_note" rows="2" maxlength="500" :class="inputClass"></textarea>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
              <input v-model="form.show_company_block" type="checkbox" :class="checkboxClass" />
              {{ $t('documentTemplates.showCompanyBlock') }}
            </label>
          </section>

          <!-- Blocs affichés -->
          <section
            class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4"
          >
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">
              {{ $t('documentTemplates.sectionBlocks') }}
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              <label
                v-for="key in blockKeys"
                :key="key"
                class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
              >
                <input v-model="form[key]" type="checkbox" :class="checkboxClass" />
                {{ $t(`documentTemplates.${key}`) }}
              </label>
            </div>
          </section>

          <!-- Tableau des lignes -->
          <section
            class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4"
          >
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">
              {{ $t('documentTemplates.sectionTable') }}
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.tableStyle') }}</label>
                <select v-model="form.table_style" :class="inputClass">
                  <option v-for="s in store.options.table_styles" :key="s" :value="s">
                    {{ $t(`documentTemplates.table_${s}`) }}
                  </option>
                </select>
              </div>
              <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 md:mt-7">
                <input v-model="form.zebra_rows" type="checkbox" :class="checkboxClass" />
                {{ $t('documentTemplates.zebraRows') }}
              </label>
            </div>
            <div>
              <p :class="labelClass">{{ $t('documentTemplates.columns') }}</p>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <label
                  v-for="col in store.options.columns"
                  :key="col"
                  class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                >
                  <input
                    v-model="form.columns[col as keyof DocumentTemplateColumns]"
                    type="checkbox"
                    :class="checkboxClass"
                  />
                  {{ $t(`documentTemplates.column_${col}`) }}
                </label>
              </div>
            </div>
          </section>

          <!-- Signature & filigrane -->
          <section
            class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4"
          >
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">
              {{ $t('documentTemplates.sectionSignature') }}
            </h3>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
              <input v-model="form.show_signature" type="checkbox" :class="checkboxClass" />
              {{ $t('documentTemplates.showSignature') }}
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.signatureLeft') }}</label>
                <input v-model="form.signature_left_label" type="text" maxlength="60" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.signatureRight') }}</label>
                <input v-model="form.signature_right_label" type="text" maxlength="60" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.watermark') }}</label>
                <input v-model="form.watermark_text" type="text" maxlength="40" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">{{ $t('documentTemplates.watermarkOpacity') }}</label>
                <input v-model.number="form.watermark_opacity" type="number" min="1" max="40" :class="inputClass" />
              </div>
            </div>
          </section>

          <!-- Textes de bas de page -->
          <section
            class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4"
          >
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">
              {{ $t('documentTemplates.sectionFooter') }}
            </h3>
            <div>
              <label :class="labelClass">{{ $t('documentTemplates.terms') }}</label>
              <textarea v-model="form.terms" rows="4" maxlength="1000" :class="inputClass"></textarea>
            </div>
            <div>
              <label :class="labelClass">{{ $t('documentTemplates.footerNote') }}</label>
              <textarea v-model="form.footer_note" rows="2" maxlength="300" :class="inputClass"></textarea>
            </div>
          </section>
        </fieldset>

        <!-- Réinitialisation -->
        <section
          v-if="store.isCustomised(selected)"
          class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 flex items-center justify-between gap-4"
        >
          <p class="text-sm text-gray-600 dark:text-gray-300">{{ $t('documentTemplates.resetHint') }}</p>
          <button
            class="px-4 py-2 text-sm font-semibold rounded-lg border border-red-300 dark:border-red-500/40 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition disabled:opacity-60"
            :disabled="resetting"
            @click="resetTemplate"
          >
            {{ $t('documentTemplates.reset') }}
          </button>
        </section>
      </div>
    </div>

    <BaseNotification ref="toast" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  useDocumentTemplateStore,
  DEFAULT_KEY,
  type DocumentTemplateColumns,
  type DocumentTemplateConfig,
} from '@/stores/documentTemplate'
import BaseNotification from '@/components/BaseNotification.vue'

const { t } = useI18n()
const store = useDocumentTemplateStore()
const toast = ref<InstanceType<typeof BaseNotification> | null>(null)

const selected = ref<string>(DEFAULT_KEY)
const saving = ref(false)
const resetting = ref(false)
const previewing = ref(false)

/**
 * `overridden` pilote la bascule « ce type a sa propre mise en page ».
 * Il suit l'état enregistré à chaque changement de type, mais reste
 * local tant que l'utilisateur n'a pas enregistré : cocher la bascule
 * ouvre le formulaire, c'est l'enregistrement qui crée la surcharge.
 */
const overridden = ref(true)

// `columns` est pré-initialisé : le premier rendu a lieu avant que
// fetchAll() ait répondu, et le template y accède par index.
const form = reactive<DocumentTemplateConfig>({ columns: {} } as DocumentTemplateConfig)

/** Le réglage général est toujours éditable ; un type l'est s'il est surchargé. */
const editable = computed(() => selected.value === DEFAULT_KEY || overridden.value)

const entries = computed(() => [
  { key: DEFAULT_KEY, label: t('documentTemplates.generalLayout') },
  ...Object.entries(store.types).map(([key, label]) => ({ key, label })),
])

const defaultTitle = computed(() =>
  selected.value === DEFAULT_KEY ? t('documentTemplates.generalLayout') : (store.types[selected.value] ?? ''),
)

/** Cases à cocher « blocs affichés », dans l'ordre d'apparition sur le PDF. */
const blockKeys = [
  'show_partner_block',
  'show_status',
  'show_warehouse',
  'show_user',
  'show_totals',
  'show_total_in_words',
  'show_payments',
  'show_notes',
  'show_bank_details',
  'show_legal_mentions',
] as const

const fontLabels: Record<string, string> = {
  dejavu: 'DejaVu Sans',
  helvetica: 'Helvetica',
  times: 'Times New Roman',
  courier: 'Courier New',
}

const inputClass =
  'w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent'
const labelClass = 'block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5'
const checkboxClass = 'rounded border-gray-300 dark:border-gray-600 text-orange-600 focus:ring-orange-500'
const btnClass =
  'px-4 py-2 text-sm font-semibold bg-orange-700 hover:bg-orange-800 text-white rounded-lg transition disabled:opacity-60'
const toggleClass =
  "w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-orange-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 dark:after:border-gray-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500"

/** Recharge le formulaire depuis la config résolue de la clé courante. */
function loadForm(): void {
  const resolved = store.resolve(selected.value)
  Object.assign(form, resolved, { columns: { ...resolved.columns } })
}

function select(key: string): void {
  selected.value = key
  overridden.value = key === DEFAULT_KEY || store.isCustomised(key)
  loadForm()
}

function toggleOverride(event: Event): void {
  overridden.value = (event.target as HTMLInputElement).checked

  // Décocher n'efface rien tout seul : on repart des valeurs générales
  // pour que la prévisualisation montre ce que le type affichera.
  if (!overridden.value) loadForm()
}

async function save(): Promise<void> {
  saving.value = true
  try {
    await store.save(selected.value, { ...form, columns: { ...form.columns } })
    toast.value?.notify(t('documentTemplates.saved'), 'success')
  } catch (e: any) {
    toast.value?.notify(e?.response?.data?.message || t('common.failedSave'), 'error')
  } finally {
    saving.value = false
  }
}

async function resetTemplate(): Promise<void> {
  resetting.value = true
  try {
    await store.reset(selected.value)
    overridden.value = selected.value === DEFAULT_KEY
    loadForm()
    toast.value?.notify(t('documentTemplates.resetDone'), 'success')
  } catch (e: any) {
    toast.value?.notify(e?.response?.data?.message || t('common.failedSave'), 'error')
  } finally {
    resetting.value = false
  }
}

async function openPreview(): Promise<void> {
  previewing.value = true
  try {
    const url = await store.preview(selected.value, { ...form, columns: { ...form.columns } })
    window.open(url, '_blank')
    // L'onglet a pris la main sur le blob ; on libère la référence plus tard
    // pour ne pas invalider l'URL avant que le lecteur PDF l'ait chargée.
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
  } catch (e: any) {
    toast.value?.notify(e?.response?.data?.message || t('documentTemplates.previewFailed'), 'error')
  } finally {
    previewing.value = false
  }
}

watch(() => store.configs, loadForm, { deep: true })

onMounted(async () => {
  await store.fetchAll()
  select(DEFAULT_KEY)
})
</script>
