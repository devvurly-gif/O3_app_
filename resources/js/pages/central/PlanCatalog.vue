<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import http from '@/services/http'
import { useToastStore } from '@/stores/toastStore'

/**
 * Formules et prix : personnaliser les packs depuis la gestion des tenants.
 *
 * Les montants s'affichent en dirhams hors taxes et voyagent en centimes. Enregistrer ne touche JAMAIS une facture déjà
 * émise : les prochaines factures utilisent les nouveaux prix ; les capacités incluses sont mises à jour chez les clients de
 * la formule à la synchronisation de la nuit. Chaque modification est tracée (qui, quand, avant / après).
 */

interface Limits { users: number | null; pos_terminals: number | null; storage_gb: number | null }
interface PlanFields {
  name: string
  tagline: string
  price_month_cents: number
  price_year_cents: number
  setup_fee_cents: number
  features: string[]
  limits: Limits
  agents: boolean
}
interface PlanView { key: string; effective: PlanFields; defaults: PlanFields; overridden: boolean; custom: boolean; tenants_count: number; updated_at: string | null }
interface AddonView { key: string; effective: { name: string; price_month_cents: number }; defaults: { name: string; price_month_cents: number }; overridden: boolean }
interface ChangeView { id: number; kind: string; key: string; action: string; by: string | null; tenants_concerned: number; at: string | null; before: Record<string, unknown>; after: Record<string, unknown> }
interface Catalog { plans: PlanView[]; addons: AddonView[]; capabilities: Record<string, string>; core: string[]; changes: ChangeView[] }

/** Ce que l'on édite : montants en dirhams (texte libre), quotas vides = illimité. */
interface PlanForm { name: string; tagline: string; month: string; year: string; setup: string; features: string[]; users: string; pos: string; storage: string; agents: boolean }

const toast = useToastStore()
const catalog = ref<Catalog | null>(null)
const loading = ref(true)
const busy = ref<string>('')
const forms = reactive<Record<string, PlanForm>>({})
const addonForms = reactive<Record<string, { name: string; price: string }>>({})

const mad = (cents: number) => (cents / 100).toLocaleString('fr-MA', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
const toCents = (value: string) => Math.round(Number(String(value).replace(/\s/g, '').replace(',', '.')) * 100)
const toQuota = (value: string) => (String(value).trim() === '' ? null : Math.round(Number(value)))
const quotaText = (value: number | null) => (value === null || value === undefined ? '' : String(value))

function toForm(p: PlanFields): PlanForm {
  return {
    name: p.name, tagline: p.tagline ?? '', month: String(p.price_month_cents / 100), year: String(p.price_year_cents / 100), setup: String(p.setup_fee_cents / 100),
    features: [...p.features], users: quotaText(p.limits.users), pos: quotaText(p.limits.pos_terminals), storage: quotaText(p.limits.storage_gb), agents: p.agents,
  }
}

function fromForm(f: PlanForm) {
  return {
    name: f.name.trim(), tagline: f.tagline.trim() || null, price_month_cents: toCents(f.month), price_year_cents: toCents(f.year), setup_fee_cents: toCents(f.setup),
    features: f.features, limits: { users: toQuota(f.users), pos_terminals: toQuota(f.pos), storage_gb: toQuota(f.storage) }, agents: f.agents,
  }
}

async function load() {
  loading.value = true
  try {
    const { data } = await http.get<{ data: Catalog }>('/central/plan-catalog')
    catalog.value = data.data
    for (const p of data.data.plans) forms[p.key] = toForm(p.effective)
    for (const a of data.data.addons) addonForms[a.key] = { name: a.effective.name, price: String(a.effective.price_month_cents / 100) }
  } catch {
    /* message déjà affiché par l'intercepteur */
  } finally {
    loading.value = false
  }
}

onMounted(load)

const dirty = (key: string): boolean => {
  const plan = catalog.value?.plans.find((p) => p.key === key)
  return !!plan && forms[key] !== undefined && JSON.stringify(fromForm(forms[key])) !== JSON.stringify(fromForm(toForm(plan.effective)))
}
const isCore = (feature: string) => !!catalog.value?.core.includes(feature)

function toggleFeature(key: string, feature: string) {
  const list = forms[key].features
  forms[key].features = list.includes(feature) ? list.filter((f) => f !== feature) : [...list, feature]
}

async function savePlan(plan: PlanView) {
  const f = forms[plan.key]
  const invalid = [f.month, f.year, f.setup].some((v) => !Number.isFinite(toCents(v)) || toCents(v) < 0)
  if (invalid || !f.name.trim()) {
    toast.error('Vérifiez le nom et les montants : des nombres positifs sont attendus.')
    return
  }
  const message =
    `Enregistrer la formule « ${f.name.trim()} » ?\n\n` +
    `${plan.tenants_count} client(s) sont actuellement sur cette formule.\n` +
    `• Les factures déjà émises ne changent pas.\n• Les prochaines factures et les nouveaux abonnements utiliseront ces prix.\n` +
    `• Les capacités incluses sont mises à jour chez les clients de la formule cette nuit.`
  if (!window.confirm(message)) return
  busy.value = plan.key
  try {
    await http.put(`/central/plan-catalog/plans/${plan.key}`, fromForm(f))
    toast.success('Formule enregistrée.')
    await load()
  } catch {
    /* message déjà affiché */
  } finally {
    busy.value = ''
  }
}

async function resetPlan(plan: PlanView) {
  if (!window.confirm(`Remettre la formule « ${plan.effective.name} » à ses valeurs d'origine (celles livrées avec l'application) ?`)) return
  busy.value = plan.key
  try {
    await http.delete(`/central/plan-catalog/plans/${plan.key}`)
    toast.success("Formule remise à ses valeurs d'origine.")
    await load()
  } catch {
    /* message déjà affiché */
  } finally {
    busy.value = ''
  }
}

/** Nouvelle formule : on part d'une formule existante (contenu copié), on choisit un identifiant définitif et on ajuste. */
const creating = ref(false)
const newKey = ref('')
const copyFrom = ref('pro')
const newForm = reactive<PlanForm>(toForm({ name: '', tagline: '', price_month_cents: 0, price_year_cents: 0, setup_fee_cents: 0, features: [], limits: { users: null, pos_terminals: null, storage_gb: null }, agents: false }))
const keyValid = computed(() => /^[a-z][a-z0-9_]{1,29}$/.test(newKey.value))

function startCreate() {
  const base = catalog.value?.plans.find((p) => p.key === copyFrom.value) ?? catalog.value?.plans[0]
  if (base) Object.assign(newForm, toForm(base.effective), { name: '' })
  newKey.value = ''
  creating.value = true
}

function pickBase() {
  const base = catalog.value?.plans.find((p) => p.key === copyFrom.value)
  if (base) Object.assign(newForm, toForm(base.effective), { name: newForm.name })
}

function toggleNewFeature(feature: string) {
  newForm.features = newForm.features.includes(feature) ? newForm.features.filter((f) => f !== feature) : [...newForm.features, feature]
}

async function createPlan() {
  const invalid = [newForm.month, newForm.year, newForm.setup].some((v) => !Number.isFinite(toCents(v)) || toCents(v) < 0)
  if (!keyValid.value || !newForm.name.trim() || invalid) {
    toast.error('Identifiant (lettres minuscules, chiffres, _), nom et montants positifs sont attendus.')
    return
  }
  if (!window.confirm(`Créer la formule « ${newForm.name.trim()} » (identifiant « ${newKey.value} », définitif) ?\n\nElle sera proposable à vos clients dès maintenant.`)) return
  busy.value = 'new'
  try {
    await http.post('/central/plan-catalog/plans', { key: newKey.value, ...fromForm(newForm) })
    toast.success('Formule créée.')
    creating.value = false
    await load()
  } catch {
    /* message déjà affiché */
  } finally {
    busy.value = ''
  }
}

async function deletePlan(plan: PlanView) {
  if (!window.confirm(`Supprimer la formule « ${plan.effective.name} » ? Cette action est refusée tant qu'un abonné l'utilise.`)) return
  busy.value = plan.key
  try {
    await http.delete(`/central/plan-catalog/plans/${plan.key}`)
    toast.success('Formule supprimée.')
    await load()
  } catch {
    /* message déjà affiché (ex. : encore utilisée) */
  } finally {
    busy.value = ''
  }
}

async function saveAddon(addon: AddonView) {
  const f = addonForms[addon.key]
  if (!f.name.trim() || !Number.isFinite(toCents(f.price)) || toCents(f.price) < 0) {
    toast.error('Un nom et un prix positif sont attendus.')
    return
  }
  if (!window.confirm(`Enregistrer l'option « ${f.name.trim()} » à ${mad(toCents(f.price))} MAD par mois HT ?`)) return
  busy.value = `addon:${addon.key}`
  try {
    await http.put(`/central/plan-catalog/addons/${addon.key}`, { name: f.name.trim(), price_month_cents: toCents(f.price) })
    toast.success('Option enregistrée.')
    await load()
  } catch {
    /* message déjà affiché */
  } finally {
    busy.value = ''
  }
}

const addonDirty = (addon: AddonView) => {
  const f = addonForms[addon.key]
  return !!f && (f.name !== addon.effective.name || toCents(f.price) !== addon.effective.price_month_cents)
}

/** Résumé lisible d'une modification de l'historique. */
function summarize(c: ChangeView): string {
  if (c.action === 'reset') return "Retour aux valeurs d'origine"
  if (c.action === 'create') return 'Formule créée'
  if (c.action === 'delete') return 'Formule supprimée'
  const labels: Record<string, string> = { name: 'nom', tagline: 'slogan', price_month_cents: 'prix mensuel', price_year_cents: 'prix annuel', setup_fee_cents: "frais d'installation", features: 'capacités', limits: 'quotas', agents: 'agents IA' }
  const parts: string[] = []
  for (const key of Object.keys(c.after)) {
    if (JSON.stringify(c.before[key]) === JSON.stringify(c.after[key])) continue
    const money = key.endsWith('_cents')
    parts.push(money ? `${labels[key] ?? key} : ${mad(Number(c.before[key]))} → ${mad(Number(c.after[key]))} MAD` : `${labels[key] ?? key} modifié`)
  }
  return parts.join(' · ') || 'Aucun changement de valeur'
}

const when = (iso: string | null) => (iso ? new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '')
const optional = computed(() => (catalog.value ? Object.entries(catalog.value.capabilities).filter(([key]) => !catalog.value!.core.includes(key)) : []))
const core = computed(() => (catalog.value ? Object.entries(catalog.value.capabilities).filter(([key]) => catalog.value!.core.includes(key)) : []))
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-start justify-between gap-3">
     <div>
      <h1 class="text-xl font-bold text-gray-900 dark:text-white">Formules et prix</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
        Personnalisez le contenu et les prix des packs. Montants en dirhams, hors taxes. Une modification ne touche jamais une facture déjà émise : les
        prochaines factures utilisent les nouveaux prix, et les capacités incluses sont mises à jour chez les clients de la formule la nuit suivante.
      </p>
     </div>
     <button v-if="catalog && !creating" type="button" class="shrink-0 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600" @click="startCreate">Nouvelle formule</button>
    </div>

    <section v-if="catalog && creating" class="bg-white dark:bg-gray-800 rounded-xl border border-orange-300 p-5 space-y-4">
      <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Nouvelle formule</h2>
      <div class="grid gap-3 sm:grid-cols-3">
        <label class="block">
          <span class="text-xs font-medium text-gray-500">Identifiant (définitif)</span>
          <input v-model="newKey" maxlength="30" placeholder="pro_plus" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-3 py-2 text-sm" />
          <span v-if="newKey && !keyValid" class="text-[11px] text-red-600">Minuscules, chiffres et _ ; commence par une lettre.</span>
        </label>
        <label class="block">
          <span class="text-xs font-medium text-gray-500">Nom affiché</span>
          <input v-model="newForm.name" maxlength="40" placeholder="Pro Plus" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-3 py-2 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs font-medium text-gray-500">Partir du contenu de</span>
          <select v-model="copyFrom" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-3 py-2 text-sm" @change="pickBase">
            <option v-for="p in catalog.plans" :key="p.key" :value="p.key">{{ p.effective.name }}</option>
          </select>
        </label>
      </div>
      <label class="block">
        <span class="text-xs font-medium text-gray-500">Slogan</span>
        <input v-model="newForm.tagline" maxlength="160" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-3 py-2 text-sm" />
      </label>
      <div class="grid grid-cols-3 gap-2">
        <label class="block"><span class="text-xs font-medium text-gray-500">Mensuel (MAD HT)</span><input v-model="newForm.month" inputmode="decimal" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-2 py-2 text-sm" /></label>
        <label class="block"><span class="text-xs font-medium text-gray-500">Annuel (MAD HT)</span><input v-model="newForm.year" inputmode="decimal" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-2 py-2 text-sm" /></label>
        <label class="block"><span class="text-xs font-medium text-gray-500">Installation (MAD HT)</span><input v-model="newForm.setup" inputmode="decimal" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-2 py-2 text-sm" /></label>
      </div>
      <div class="grid grid-cols-3 gap-2">
        <label class="block"><span class="text-xs font-medium text-gray-500">Utilisateurs</span><input v-model="newForm.users" inputmode="numeric" placeholder="Illimité" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-2 py-2 text-sm" /></label>
        <label class="block"><span class="text-xs font-medium text-gray-500">Terminaux caisse</span><input v-model="newForm.pos" inputmode="numeric" placeholder="Illimité" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-2 py-2 text-sm" /></label>
        <label class="block"><span class="text-xs font-medium text-gray-500">Stockage (Go)</span><input v-model="newForm.storage" inputmode="numeric" placeholder="Illimité" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-2 py-2 text-sm" /></label>
      </div>
      <div class="grid sm:grid-cols-2 gap-x-6 gap-y-1">
        <label v-for="[key, label] in optional" :key="key" class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
          <input type="checkbox" :checked="newForm.features.includes(key)" @change="toggleNewFeature(key)" /> {{ label }}
        </label>
      </div>
      <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input v-model="newForm.agents" type="checkbox" /> Agents IA (ouvre le droit à l'option, une fois la formule payée)</label>
      <div class="flex justify-end gap-2">
        <button type="button" class="px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700" @click="creating = false">Annuler</button>
        <button type="button" class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600 disabled:opacity-40" :disabled="busy === 'new'" @click="createPlan">{{ busy === 'new' ? 'Création…' : 'Créer la formule' }}</button>
      </div>
    </section>

    <p v-if="loading" class="text-sm text-gray-400">Chargement…</p>

    <div v-else-if="catalog" class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
      <section
        v-for="plan in catalog.plans"
        :key="plan.key"
        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-xs uppercase tracking-wide text-gray-400">{{ plan.key }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ plan.tenants_count }} client(s) sur cette formule</p>
          </div>
          <span v-if="plan.custom" class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-violet-100 text-violet-700">Personnalisée</span>
          <span v-else-if="plan.overridden" class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Modifiée</span>
        </div>

        <label class="block">
          <span class="text-xs font-medium text-gray-500">Nom</span>
          <input v-model="forms[plan.key].name" maxlength="40" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-3 py-2 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs font-medium text-gray-500">Slogan</span>
          <input v-model="forms[plan.key].tagline" maxlength="160" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-3 py-2 text-sm" />
        </label>

        <div class="grid grid-cols-3 gap-2">
          <label class="block">
            <span class="text-xs font-medium text-gray-500">Mensuel (MAD HT)</span>
            <input v-model="forms[plan.key].month" inputmode="decimal" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-2 py-2 text-sm" />
          </label>
          <label class="block">
            <span class="text-xs font-medium text-gray-500">Annuel (MAD HT)</span>
            <input v-model="forms[plan.key].year" inputmode="decimal" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-2 py-2 text-sm" />
          </label>
          <label class="block">
            <span class="text-xs font-medium text-gray-500">Installation (MAD HT)</span>
            <input v-model="forms[plan.key].setup" inputmode="decimal" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-2 py-2 text-sm" />
          </label>
        </div>
        <p v-if="!plan.custom" class="text-[11px] text-gray-400 -mt-2">Valeur d'origine : {{ mad(plan.defaults.price_month_cents) }} / {{ mad(plan.defaults.price_year_cents) }} / {{ mad(plan.defaults.setup_fee_cents) }} MAD</p>

        <div class="grid grid-cols-3 gap-2">
          <label class="block">
            <span class="text-xs font-medium text-gray-500">Utilisateurs</span>
            <input v-model="forms[plan.key].users" inputmode="numeric" placeholder="Illimité" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-2 py-2 text-sm" />
          </label>
          <label class="block">
            <span class="text-xs font-medium text-gray-500">Terminaux caisse</span>
            <input v-model="forms[plan.key].pos" inputmode="numeric" placeholder="Illimité" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-2 py-2 text-sm" />
          </label>
          <label class="block">
            <span class="text-xs font-medium text-gray-500">Stockage (Go)</span>
            <input v-model="forms[plan.key].storage" inputmode="numeric" placeholder="Illimité" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-2 py-2 text-sm" />
          </label>
        </div>

        <div>
          <p class="text-xs font-medium text-gray-500 mb-1">Capacités incluses</p>
          <div class="space-y-1">
            <label v-for="[key, label] in core" :key="key" class="flex items-center gap-2 text-xs text-gray-400">
              <input type="checkbox" checked disabled /> {{ label }} <span class="text-[10px]">(toujours incluse)</span>
            </label>
            <label v-for="[key, label] in optional" :key="key" class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
              <input type="checkbox" :checked="forms[plan.key].features.includes(key)" @change="toggleFeature(plan.key, key)" /> {{ label }}
            </label>
          </div>
        </div>

        <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300 border-t border-gray-100 dark:border-gray-700 pt-3">
          <input v-model="forms[plan.key].agents" type="checkbox" class="mt-1" />
          <span>Agents IA <span class="block text-[11px] text-gray-400">Ouvre le droit à l'option « Agents IA » (une fois la formule payée). L'installation se fait depuis la fiche du client.</span></span>
        </label>

        <div class="flex items-center justify-between pt-1">
          <button
            v-if="plan.custom"
            type="button"
            class="text-xs text-gray-500 hover:text-red-600 underline disabled:opacity-50"
            :disabled="busy === plan.key"
            @click="deletePlan(plan)"
          >
            Supprimer
          </button>
          <button
            v-else-if="plan.overridden"
            type="button"
            class="text-xs text-gray-500 hover:text-red-600 underline disabled:opacity-50"
            :disabled="busy === plan.key"
            @click="resetPlan(plan)"
          >
            Valeurs d'origine
          </button>
          <span v-else />
          <button
            type="button"
            class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600 disabled:opacity-40 disabled:cursor-not-allowed"
            :disabled="!dirty(plan.key) || busy === plan.key"
            @click="savePlan(plan)"
          >
            {{ busy === plan.key ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
        </div>
        <p v-if="plan.updated_at" class="text-[11px] text-gray-400">Dernière modification : {{ when(plan.updated_at) }}</p>
      </section>
    </div>

    <section v-if="catalog" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
      <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Options mensuelles</h2>
      <div class="space-y-2">
        <div v-for="addon in catalog.addons" :key="addon.key" class="flex flex-wrap items-center gap-3">
          <input v-model="addonForms[addon.key].name" maxlength="60" class="flex-1 min-w-[12rem] rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-3 py-2 text-sm" />
          <input v-model="addonForms[addon.key].price" inputmode="decimal" class="w-28 rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent px-3 py-2 text-sm" />
          <span class="text-xs text-gray-400">MAD / mois HT (origine : {{ mad(addon.defaults.price_month_cents) }})</span>
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-orange-500 hover:bg-orange-600 disabled:opacity-40"
            :disabled="!addonDirty(addon) || busy === `addon:${addon.key}`"
            @click="saveAddon(addon)"
          >
            Enregistrer
          </button>
        </div>
      </div>
    </section>

    <section v-if="catalog" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
      <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Historique des modifications</h2>
      <p v-if="!catalog.changes.length" class="text-sm text-gray-400">Aucune modification : toutes les formules ont leurs valeurs d'origine.</p>
      <table v-else class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-400">
            <th class="py-1 pr-3">Date</th>
            <th class="py-1 pr-3">Par</th>
            <th class="py-1 pr-3">Élément</th>
            <th class="py-1">Changement</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in catalog.changes" :key="c.id" class="border-t border-gray-100 dark:border-gray-700 align-top">
            <td class="py-2 pr-3 whitespace-nowrap text-gray-500">{{ when(c.at) }}</td>
            <td class="py-2 pr-3 whitespace-nowrap">{{ c.by ?? '—' }}</td>
            <td class="py-2 pr-3 whitespace-nowrap">{{ c.kind === 'addon' ? 'Option' : 'Formule' }} {{ c.key }}</td>
            <td class="py-2 text-gray-600 dark:text-gray-300">{{ summarize(c) }}<span v-if="c.tenants_concerned" class="text-xs text-gray-400"> · {{ c.tenants_concerned }} client(s) concerné(s)</span></td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
</template>
