<template>
  <div class="p-4 md:p-6 space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Étiquettes produits</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Définissez le format, placez les champs, puis imprimez.</p>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-xs" :class="saveStatus.class">{{ saveStatus.text }}</span>
        <button
          type="button"
          class="px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
          @click="resetTemplate"
        >
          Réinitialiser
        </button>
        <button
          type="button"
          :disabled="!selectedList.length || printing"
          class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600 disabled:opacity-40 disabled:cursor-not-allowed transition flex items-center gap-2"
          @click="printLabels"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
          </svg>
          {{ printing ? 'Envoi…' : `Imprimer (${totalLabelCount})` }}
        </button>
      </div>
    </div>

    <div v-if="printError" class="rounded-lg border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
      {{ printError }}
    </div>
    <div v-if="printInfo" class="rounded-lg border border-green-300 dark:border-green-800 bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-800 dark:text-green-300">
      {{ printInfo }}
    </div>

    <!-- Label format -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex flex-wrap items-end gap-5">
      <div>
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Largeur (mm)</label>
        <input v-model.number="label.width" type="number" min="10" max="210" step="1" :class="numClass" />
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Hauteur (mm)</label>
        <input v-model.number="label.height" type="number" min="10" max="297" step="1" :class="numClass" />
      </div>
      <div>
        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Format courant</p>
        <div class="flex gap-1.5">
          <button
            v-for="p in presets"
            :key="p.label"
            type="button"
            class="px-2.5 h-9 rounded-lg text-xs font-medium border transition"
            :class="label.width === p.w && label.height === p.h
              ? 'bg-orange-500 border-orange-500 text-white'
              : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
            @click="label.width = p.w; label.height = p.h"
          >
            {{ p.label }}
          </button>
        </div>
      </div>
      <div>
        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Impression</p>
        <div class="flex gap-1.5">
          <button
            v-for="m in printModes"
            :key="m.value"
            type="button"
            class="px-3 h-9 rounded-lg text-xs font-medium border transition"
            :class="printMode === m.value
              ? 'bg-orange-500 border-orange-500 text-white'
              : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
            @click="printMode = m.value"
          >
            {{ m.label }}
          </button>
        </div>
      </div>
      <div>
        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Sortie</p>
        <div class="flex gap-1.5">
          <button
            v-for="t in transports"
            :key="t.value"
            type="button"
            class="px-3 h-9 rounded-lg text-xs font-medium border transition"
            :class="printer.transport === t.value
              ? 'bg-orange-500 border-orange-500 text-white'
              : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
            :title="t.hint"
            @click="printer.transport = t.value"
          >
            {{ t.label }}
          </button>
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Bordure</label>
        <label class="flex items-center gap-1.5 h-9 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
          <input v-model="label.border" type="checkbox" class="rounded border-gray-300 text-orange-500 focus:ring-orange-500" />
          Afficher
        </label>
      </div>
    </div>

    <!-- Thermal transports bypass the browser entirely: Laravel renders TSPL
         and it goes straight into the printer, so these are the printer's own
         parameters rather than page-setup ones. -->
    <div
      v-if="printer.transport !== 'browser'"
      class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex flex-wrap items-end gap-4"
    >
      <template v-if="printer.transport === 'agent'">
        <div class="min-w-[16rem]">
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Imprimante</label>
          <div class="flex gap-1.5">
            <select
              :value="printer.name"
              class="flex-1 h-9 px-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500"
              @change="selectPrinter(($event.target as HTMLSelectElement).value)"
            >
              <option value="">— choisir —</option>
              <!-- L'imprimante enregistrée peut ne pas être dans la liste si
                   l'agent est éteint : on la garde visible pour ne pas
                   silencieusement désélectionner la configuration du magasin. -->
              <option v-if="printer.name && !printers.some((p) => p.name === printer.name)" :value="printer.name">
                {{ printer.name }} (agent hors ligne)
              </option>
              <option v-for="p in printers" :key="p.name" :value="p.name">
                {{ p.name }}{{ p.isDefault ? ' (par défaut)' : '' }}
              </option>
            </select>
            <button
              type="button"
              class="px-2.5 h-9 rounded-lg text-xs font-medium border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 transition"
              :disabled="printersLoading"
              title="Relire les imprimantes du poste"
              @click="fetchPrinters"
            >
              {{ printersLoading ? '…' : '⟳' }}
            </button>
          </div>
        </div>
        <div v-if="selectedPrinter?.papers?.length" class="min-w-[13rem]">
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Format papier du pilote</label>
          <select
            :value="printer.paper"
            class="w-full h-9 px-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500"
            @change="selectPaper(($event.target as HTMLSelectElement).value)"
          >
            <option value="">— aucun —</option>
            <option v-for="p in selectedPrinter.papers" :key="p.name" :value="p.name">
              {{ p.name }} — {{ p.widthMm }} × {{ p.heightMm }} mm
            </option>
          </select>
        </div>
      </template>
      <div v-if="printer.transport === 'agent'" class="min-w-[16rem]">
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">URL de l'agent local</label>
        <input v-model="printer.agent_url" type="text" class="w-full h-9 px-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500" />
      </div>
      <template v-if="printer.transport === 'server'">
        <div>
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">IP imprimante</label>
          <input v-model="printer.host" type="text" placeholder="192.168.1.50" :class="numClass" class="!w-36" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Port</label>
          <input v-model.number="printer.port" type="number" min="1" max="65535" :class="numClass" class="!w-20" />
        </div>
      </template>
      <div>
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">DPI</label>
        <select v-model.number="printer.dpi" :class="numClass" class="!w-24">
          <option :value="203">203</option>
          <option :value="300">300</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Densité</label>
        <input v-model.number="printer.darkness" type="number" min="0" max="15" :class="numClass" class="!w-20" />
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Vitesse</label>
        <input v-model.number="printer.speed" type="number" min="1" max="12" step="0.5" :class="numClass" class="!w-20" />
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Gap (mm)</label>
        <input v-model.number="printer.gap" type="number" min="0" max="10" step="0.5" :class="numClass" class="!w-20" />
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Sens</label>
        <select v-model.number="printer.direction" :class="numClass" class="!w-24">
          <option :value="1">Normal</option>
          <option :value="0">Inversé</option>
        </select>
      </div>
      <p v-if="printersError" class="text-xs text-amber-700 dark:text-amber-400 basis-full">
        {{ printersError }}
      </p>
      <p class="text-xs text-gray-500 dark:text-gray-400 basis-full">
        Densité 8–10 et vitesse 3–4 donnent les barres les plus nettes sur du thermique direct.
        Si l'étiquette sort à l'envers, basculez le sens.
        <span v-if="selectedPrinter"> · 1 point = {{ (25.4 / printer.dpi).toFixed(3) }} mm à {{ printer.dpi }} dpi.</span>
      </p>
    </div>

    <!-- L'aperçu ne vaut que s'il correspond au papier réellement chargé. -->
    <div
      v-if="paperMismatch"
      class="rounded-lg border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-2.5 text-xs text-amber-800 dark:text-amber-300 flex items-center justify-between gap-3"
    >
      <span>
        L'étiquette fait <span class="font-mono font-semibold">{{ label.width }} × {{ label.height }} mm</span>
        alors que le pilote annonce
        <span class="font-mono font-semibold">{{ paperMismatch.widthMm }} × {{ paperMismatch.heightMm }} mm</span>
        pour « {{ paperMismatch.name }} ». Le pilote rognera ou décalera la sortie.
      </span>
      <button
        type="button"
        class="shrink-0 px-2.5 h-8 rounded-lg text-xs font-semibold border border-amber-400 text-amber-800 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/40 transition"
        @click="applyPaperSize(paperMismatch)"
      >
        Aligner sur le papier
      </button>
    </div>

    <!-- The browser rescales the page when the printer's paper size differs
         from the @page size, which silently crops fields off the stock.
         Spell out the three settings that give true 1:1 millimetres. -->
    <div
      v-if="printer.transport === 'browser'"
      class="rounded-lg border border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-sky-900/20 px-4 py-2.5 text-xs text-sky-800 dark:text-sky-300"
    >
      <span class="font-semibold">Réglages d'impression (impératif pour une taille réelle) :</span>
      Format papier <span class="font-mono font-semibold">{{ label.width }} × {{ label.height }} mm</span>
      (identique à l'étiquette ci-dessus) · Marges <span class="font-semibold">Aucune</span> ·
      Échelle <span class="font-semibold">100 %</span> (pas « ajuster à la page »).
      Si le format papier de votre imprimante diffère, le navigateur agrandit la page et les champs sont rognés.
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <!-- ── Designer ─────────────────────────────────────────── -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-4">
        <div class="flex items-center justify-between">
          <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Maquette</p>
          <p class="text-xs text-gray-400">Glissez les champs pour les positionner</p>
        </div>

        <div
          v-if="overflowing.length"
          class="rounded-lg border border-red-300 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-3 py-2 text-xs text-red-700 dark:text-red-300"
        >
          <span class="font-semibold">Hors zone imprimable :</span> {{ overflowNames }} —
          ce qui dépasse le cadre sera coupé à l'impression. Repositionnez le champ, réduisez sa taille,
          ou agrandissez l'étiquette.
          <template v-if="printerMargins">
            Le liseré rouge pointillé marque les marges que « {{ printer.name }} » ne peut pas atteindre.
          </template>
        </div>

        <!-- Canvas -->
        <div class="bg-gray-100 dark:bg-gray-900 rounded-lg p-6 flex justify-center overflow-auto">
          <div
            ref="canvasEl"
            class="relative bg-white shadow-sm shrink-0 overflow-hidden"
            :class="label.border ? 'border border-gray-400' : 'border border-dashed border-gray-300'"
            :style="{ width: label.width * PX_PER_MM + 'px', height: label.height * PX_PER_MM + 'px' }"
          >
            <!-- Marges non imprimables déclarées par le pilote : la tête ne
                 peut physiquement rien y déposer, un champ posé dessus est
                 perdu même s'il tient dans l'étiquette. -->
            <div
              v-if="printableInset"
              class="absolute pointer-events-none border border-dashed border-red-400/70"
              :style="printableInset"
            />
            <div
              v-for="f in enabledFields"
              :key="f.key"
              :ref="(el) => setFieldEl(f.key, el)"
              class="absolute cursor-move select-none"
              :class="[
                overflowing.includes(f.key)
                  ? 'outline outline-2 outline-red-500'
                  : activeField === f.key
                    ? 'outline outline-2 outline-orange-500'
                    : 'hover:outline hover:outline-1 hover:outline-orange-300',
                layout[f.key].boxed ? 'border-2 border-black px-1' : '',
              ]"
              :style="fieldStyle(f.key)"
              @pointerdown="startDrag($event, f.key)"
            >
              <img
                v-if="f.key === 'barcode'"
                :src="sampleBarcode"
                class="pointer-events-none block"
                :style="{ width: layout.barcode.size * PX_PER_MM + 'px', height: layout.barcode.height * PX_PER_MM + 'px' }"
                draggable="false"
              />
              <span v-else class="pointer-events-none whitespace-nowrap leading-none">{{ fieldText(f.key, sampleProduct) }}</span>
            </div>
          </div>
        </div>

        <!-- Field controls -->
        <div class="overflow-x-auto -mx-1 px-1">
          <table class="w-full text-xs">
            <thead>
              <tr class="text-gray-400 dark:text-gray-500">
                <th class="text-left font-medium py-1.5 pr-2">Champ</th>
                <th class="font-medium py-1.5 px-1 w-16">X (mm)</th>
                <th class="font-medium py-1.5 px-1 w-16">Y (mm)</th>
                <th class="font-medium py-1.5 px-1 w-16">{{ 'Taille' }}</th>
                <th class="font-medium py-1.5 px-1 w-16">Haut.</th>
                <th class="font-medium py-1.5 px-1 w-10">G</th>
                <th class="font-medium py-1.5 px-1 w-10">Cadre</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="f in availableFields"
                :key="f.key"
                class="border-t border-gray-100 dark:border-gray-700"
                :class="activeField === f.key ? 'bg-orange-50 dark:bg-orange-900/20' : ''"
              >
                <td class="py-1.5 pr-2">
                  <label class="flex items-center gap-1.5 cursor-pointer text-gray-700 dark:text-gray-300">
                    <input
                      v-model="layout[f.key].enabled"
                      type="checkbox"
                      class="rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                    />
                    {{ f.label }}
                  </label>
                </td>
                <td class="px-1"><input v-model.number="layout[f.key].x" :disabled="!layout[f.key].enabled" type="number" step="0.5" :class="cellClass" /></td>
                <td class="px-1"><input v-model.number="layout[f.key].y" :disabled="!layout[f.key].enabled" type="number" step="0.5" :class="cellClass" /></td>
                <td class="px-1"><input v-model.number="layout[f.key].size" :disabled="!layout[f.key].enabled" type="number" step="0.5" min="1" :class="cellClass" /></td>
                <td class="px-1">
                  <input
                    v-if="f.key === 'barcode'"
                    v-model.number="layout.barcode.height"
                    :disabled="!layout.barcode.enabled"
                    type="number"
                    step="0.5"
                    min="1"
                    :class="cellClass"
                  />
                  <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                </td>
                <td class="px-1 text-center">
                  <input
                    v-if="f.key !== 'barcode'"
                    v-model="layout[f.key].bold"
                    :disabled="!layout[f.key].enabled"
                    type="checkbox"
                    class="rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                  />
                </td>
                <td class="px-1 text-center">
                  <input
                    v-if="f.key !== 'barcode'"
                    v-model="layout[f.key].boxed"
                    :disabled="!layout[f.key].enabled"
                    type="checkbox"
                    class="rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                  />
                </td>
              </tr>
            </tbody>
          </table>
          <p class="text-[11px] text-gray-400 mt-2">
            « Taille » = corps du texte en pt (largeur en mm pour le code-barres). « G » = gras.
          </p>
        </div>
      </div>

      <!-- ── Products ─────────────────────────────────────────── -->
      <div class="space-y-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex flex-col h-[400px]">
          <div class="relative mb-3">
            <input
              v-model="search"
              type="text"
              placeholder="Rechercher un produit (titre, SKU, code)..."
              class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500"
            />
            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
          </div>

          <div class="flex-1 overflow-y-auto -mx-1 px-1 space-y-1">
            <div v-if="store.loading" class="text-sm text-gray-400 text-center py-8">Chargement...</div>
            <div v-else-if="!store.items.length" class="text-sm text-gray-400 text-center py-8">Aucun produit trouvé.</div>
            <label
              v-for="p in store.items"
              :key="p.id"
              class="flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer"
            >
              <input
                type="checkbox"
                :checked="selected.has(p.id)"
                class="rounded border-gray-300 text-orange-500 focus:ring-orange-500 shrink-0"
                @change="toggleProduct(p)"
              />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ p.p_title }}</p>
                <p class="text-xs text-gray-400 font-mono">{{ p.p_sku || p.p_code }} <span v-if="p.p_ean13">· {{ p.p_ean13 }}</span></p>
              </div>
              <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 shrink-0">{{ formatPriceDh(Number(p.p_salePrice)) }}</span>
            </label>
          </div>

          <BasePagination
            v-if="store.meta.last_page > 1"
            :current-page="store.meta.current_page"
            :last-page="store.meta.last_page"
            :total="store.meta.total"
            :per-page="store.meta.per_page"
            class="mt-3"
            @change="store.goToPage"
          />
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
          <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Sélection ({{ selectedList.length }})</p>
            <button v-if="selectedList.length" type="button" class="text-xs text-red-500 hover:underline" @click="clearSelection">
              Tout retirer
            </button>
          </div>

          <p v-if="!selectedList.length" class="text-sm text-gray-400 py-4 text-center">
            Cochez des produits ci-dessus. La maquette utilise le premier produit sélectionné comme aperçu.
          </p>

          <div v-else class="space-y-1 max-h-48 overflow-y-auto">
            <div v-for="s in selectedList" :key="s.product.id" class="flex items-center gap-2 text-sm px-2 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-900">
              <span class="flex-1 truncate text-gray-800 dark:text-gray-200">{{ s.product.p_title }}</span>
              <input
                v-model.number="s.qty"
                type="number"
                min="1"
                max="99"
                class="w-14 text-center text-xs rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 py-0.5"
              />
              <button type="button" class="text-gray-400 hover:text-red-500" @click="removeProduct(s.product.id)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import { useProductStore } from '@/stores/product'
import { useAuthStore } from '@/stores/authStore'
import BasePagination from '@/components/BasePagination.vue'
import http from '@/services/http'
import type { Product } from '@/types'
import { renderBarcodeDataUrl } from '@/composables/useBarcode'

const store = useProductStore()
const auth = useAuthStore()

// Preview scale: how many screen pixels represent one millimetre.
const PX_PER_MM = 5
// 1pt = 1/72 inch = 0.3528mm — used to show pt font sizes at canvas scale.
const PT_TO_MM = 0.352778

const numClass =
  'w-24 h-9 px-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500'
const cellClass =
  'w-full px-1 py-1 text-center rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 disabled:opacity-40 focus:outline-none focus:ring-1 focus:ring-orange-500'

// ── Search / listing ─────────────────────────────────────────────
const search = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null
watch(search, (val) => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    store.params.search = val || null
    store.fetchPage(1)
  }, 300)
})

onMounted(() => {
  store.params.per_page = 20
  store.fetchPage(1)
  loadTemplate()
})

// ── Template (label format + per-field placement) ────────────────
const ALL_FIELDS = [
  { key: 'title', label: 'Titre' },
  { key: 'barcode', label: 'Code-barres' },
  { key: 'sku', label: 'SKU' },
  { key: 'imei', label: 'IMEI' },
  { key: 'salePrice', label: 'Prix de vente' },
  { key: 'purchasePrice', label: "Prix d'achat" },
  { key: 'category', label: 'Catégorie' },
  { key: 'brand', label: 'Marque' },
] as const
type FieldKey = (typeof ALL_FIELDS)[number]['key']

// IMEI only makes sense for tenants tracking serial numbers (téléphonie,
// électronique), so it's gated on the tenant's IMEI module. The template
// still carries its placement, so toggling the module back on restores it.
const availableFields = computed(() => ALL_FIELDS.filter((f) => f.key !== 'imei' || auth.hasModule('imei')))

interface FieldLayout {
  enabled: boolean
  x: number // mm from left edge
  y: number // mm from top edge
  size: number // pt for text, mm width for the barcode
  height: number // mm, barcode only
  bold: boolean
  boxed: boolean
}

const presets = [
  { label: '50×30', w: 50, h: 30 },
  { label: '40×30', w: 40, h: 30 },
  { label: '60×40', w: 60, h: 40 },
  { label: '100×50', w: 100, h: 50 },
]

const printModes = [
  { value: 'sheet' as const, label: 'Planche A4' },
  { value: 'roll' as const, label: 'Rouleau' },
]

// How the job reaches paper. `browser` renders HTML and lets the print
// pipeline rasterise it — universal, but the driver rescales to the head's
// dot grid and barcode bars land on fractional dots. The two TSPL transports
// have Laravel emit the printer's native language instead, so coordinates are
// dots and the firmware draws the barcode: exact 1:1, no rescaling.
type Transport = 'browser' | 'agent' | 'server'
const transports = [
  { value: 'browser' as const, label: 'Navigateur', hint: 'Impression HTML via la boîte de dialogue du navigateur' },
  { value: 'agent' as const, label: 'Thermique (agent)', hint: 'TSPL envoyé à l\'agent local du poste de caisse' },
  { value: 'server' as const, label: 'Thermique (réseau)', hint: 'TSPL envoyé par le serveur — imprimante joignable depuis l\'application' },
]

/** Shape returned by the agent's /printers inventory (windows-printers.ps1). */
interface PrinterPaper {
  name: string
  widthMm: number
  heightMm: number
}
interface PrinterInfo {
  name: string
  isDefault: boolean
  papers: PrinterPaper[]
  resolutions: { x: number; y: number }[]
  paper: PrinterPaper | null
  printable: { leftMm: number; topMm: number; widthMm: number; heightMm: number } | null
}

function defaultPrinter() {
  return {
    transport: 'browser' as Transport,
    // Windows print queue chosen from the agent's inventory.
    name: '',
    paper: '',
    // Snapshot of the chosen printer's capabilities, persisted so the preview
    // still reflects the real hardware on a machine where the agent is not
    // running (back office, phone) — the layout is shared shop-wide.
    caps: null as PrinterInfo | null,
    host: '',
    port: 9100,
    agent_url: 'http://127.0.0.1:9110/print',
    dpi: 203,
    darkness: 10,
    speed: 4,
    gap: 2,
    direction: 1,
  }
}

function defaultLabel() {
  return { width: 50, height: 30, border: true }
}

function defaultLayout(): Record<FieldKey, FieldLayout> {
  return {
    title: { enabled: true, x: 2, y: 2, size: 8, height: 0, bold: true, boxed: false },
    barcode: { enabled: true, x: 8, y: 8, size: 34, height: 11, bold: false, boxed: false },
    sku: { enabled: false, x: 2, y: 6, size: 6, height: 0, bold: false, boxed: false },
    imei: { enabled: false, x: 2, y: 21, size: 6, height: 0, bold: false, boxed: false },
    salePrice: { enabled: true, x: 28, y: 23, size: 10, height: 0, bold: true, boxed: true },
    purchasePrice: { enabled: false, x: 2, y: 20, size: 6, height: 0, bold: false, boxed: false },
    category: { enabled: false, x: 2, y: 6, size: 6, height: 0, bold: false, boxed: false },
    brand: { enabled: true, x: 2, y: 24, size: 8, height: 0, bold: true, boxed: false },
  }
}

const label = reactive(defaultLabel())
const layout = reactive<Record<FieldKey, FieldLayout>>(defaultLayout())
const printMode = ref<'sheet' | 'roll'>('sheet')
const printer = reactive(defaultPrinter())
const printError = ref('')
const printInfo = ref('')

// ── Printer inventory (via the local agent) ──────────────────────
// No browser API exposes the system's printers, so the list can only come
// from the agent running on the cashier's machine.
const printers = ref<PrinterInfo[]>([])
const printersLoading = ref(false)
const printersError = ref('')

/** The agent URL is stored as the /print endpoint; other routes hang off it. */
function agentBase(): string {
  return printer.agent_url.replace(/\/print\/?$/, '')
}

const selectedPrinter = computed<PrinterInfo | null>(
  () => printers.value.find((p) => p.name === printer.name) ?? printer.caps
)

async function fetchPrinters() {
  if (printersLoading.value) return
  printersLoading.value = true
  printersError.value = ''
  try {
    const res = await fetch(`${agentBase()}/printers`)
    const body = await res.json()
    if (!res.ok || !body.ok) throw new Error(body?.error ?? `HTTP ${res.status}`)

    printers.value = body.printers as PrinterInfo[]

    // First run: adopt the machine's default printer so the preview is
    // calibrated without the user having to pick anything.
    if (!printer.name) {
      const fallback = printers.value.find((p) => p.isDefault) ?? printers.value[0]
      if (fallback) selectPrinter(fallback.name)
    } else {
      const current = printers.value.find((p) => p.name === printer.name)
      if (current) printer.caps = current
    }
  } catch (e: unknown) {
    printersError.value =
      e instanceof TypeError
        ? `Agent injoignable sur ${agentBase()} — impossible de lister les imprimantes du poste. Démarrez-le, puis relancez la lecture.`
        : `Lecture des imprimantes impossible : ${e instanceof Error ? e.message : String(e)}`
  } finally {
    printersLoading.value = false
  }
}

function selectPrinter(name: string) {
  printer.name = name
  const info = printers.value.find((p) => p.name === name) ?? null
  printer.caps = info
  if (!info) return

  // The driver's own resolution beats whatever was typed: it decides the dot
  // grid every coordinate is rounded onto.
  const best = info.resolutions.reduce((a, r) => Math.max(a, r.x), 0)
  if (best > 0) printer.dpi = best

  if (info.paper) selectPaper(info.paper.name)
}

function selectPaper(name: string) {
  printer.paper = name
  const paper = selectedPrinter.value?.papers.find((p) => p.name === name)
  if (paper) applyPaperSize(paper)
}

/** Snap the label to the stock the driver is actually set up for. */
function applyPaperSize(paper: PrinterPaper) {
  label.width = Math.round(paper.widthMm * 10) / 10
  label.height = Math.round(paper.heightMm * 10) / 10
}

const currentPaper = computed<PrinterPaper | null>(
  () => selectedPrinter.value?.papers.find((p) => p.name === printer.paper) ?? null
)

/**
 * Non-printable margins, in mm. The driver reports the printable area of its
 * *default* paper, so we read the four bands off that and carry them over to
 * whatever stock is selected — an approximation, but the right order of
 * magnitude (0 on thermal label printers, 4–5 mm on laser).
 */
const printerMargins = computed(() => {
  const info = selectedPrinter.value
  const base = info?.paper
  const area = info?.printable
  if (!info || !base || !area) return null

  const m = {
    left: Math.max(0, area.leftMm),
    top: Math.max(0, area.topMm),
    right: Math.max(0, base.widthMm - (area.leftMm + area.widthMm)),
    bottom: Math.max(0, base.heightMm - (area.topMm + area.heightMm)),
  }
  // Sub-tenth-of-a-millimetre bands are rounding noise in the driver's own
  // numbers, not a real dead zone worth drawing.
  return m.left + m.top + m.right + m.bottom < 0.4 ? null : m
})

const printableInset = computed(() => {
  const m = printerMargins.value
  if (!m) return null
  return {
    left: m.left * PX_PER_MM + 'px',
    top: m.top * PX_PER_MM + 'px',
    width: Math.max(0, label.width - m.left - m.right) * PX_PER_MM + 'px',
    height: Math.max(0, label.height - m.top - m.bottom) * PX_PER_MM + 'px',
  }
})

/** Flags a label size that the selected driver stock cannot produce 1:1. */
const paperMismatch = computed<PrinterPaper | null>(() => {
  const paper = currentPaper.value
  if (!paper) return null
  const off = Math.abs(paper.widthMm - label.width) > 0.6 || Math.abs(paper.heightMm - label.height) > 0.6
  return off ? paper : null
})

const enabledFields = computed(() => availableFields.value.filter((f) => layout[f.key].enabled))

// ── Persistence ──────────────────────────────────────────────────
// The template lives in the tenant's `settings` table (domain `labels`,
// key `template`) as a JSON blob, so one layout is shared by every user
// and every device of the shop. Reading is open to all authenticated
// users; writing is admin-only (POST /settings sits behind the admin
// middleware), so non-admins can still tweak and print locally but their
// changes are session-only.
type SaveState = 'idle' | 'saving' | 'saved' | 'error' | 'readonly'
const saveState = ref<SaveState>('idle')
const templateLoaded = ref(false)

function serializeTemplate(): string {
  return JSON.stringify({
    label: { ...label },
    layout: JSON.parse(JSON.stringify(layout)),
    printMode: printMode.value,
  })
}

function applyTemplate(raw: string) {
  const saved = JSON.parse(raw)
  if (saved.label) Object.assign(label, saved.label)
  if (saved.printMode) printMode.value = saved.printMode
  if (saved.layout) {
    for (const f of ALL_FIELDS) {
      if (saved.layout[f.key]) Object.assign(layout[f.key], saved.layout[f.key])
    }
  }
}

/**
 * Printer wiring lives in its own settings keys rather than inside the
 * template blob: one shop has one printer, but may keep several label
 * layouts, and the backend reads these keys directly when rendering TSPL.
 */
function applyPrinter(raw: Record<string, string> | undefined) {
  if (!raw) return
  const num = (key: string, fallback: number) => {
    const v = Number(raw[key])
    return Number.isFinite(v) && raw[key] !== '' && raw[key] != null ? v : fallback
  }
  if (raw.printer_transport === 'agent' || raw.printer_transport === 'server' || raw.printer_transport === 'browser') {
    printer.transport = raw.printer_transport
  }
  if (raw.printer_host) printer.host = raw.printer_host
  if (raw.agent_url) printer.agent_url = raw.agent_url
  if (raw.printer_name) printer.name = raw.printer_name
  if (raw.printer_caps) {
    try {
      const caps = JSON.parse(raw.printer_caps) as PrinterInfo
      printer.caps = caps
      printer.paper = caps.paper?.name ?? ''
    } catch {
      // Stored snapshot unreadable — the preview falls back to plain
      // millimetres until the agent is queried again.
    }
  }
  printer.port = num('printer_port', printer.port)
  printer.dpi = num('printer_dpi', printer.dpi)
  printer.darkness = num('printer_darkness', printer.darkness)
  printer.speed = num('printer_speed', printer.speed)
  printer.gap = num('printer_gap', printer.gap)
  printer.direction = num('printer_direction', printer.direction)
}

function serializePrinter(): Record<string, string> {
  return {
    printer_transport: printer.transport,
    printer_name: printer.name,
    // Only the fields the preview reads: a full driver dump can list several
    // hundred paper sizes, which has no business in a settings row.
    printer_caps: printer.caps
      ? JSON.stringify({
          name: printer.caps.name,
          isDefault: printer.caps.isDefault,
          resolutions: printer.caps.resolutions,
          printable: printer.caps.printable,
          paper: currentPaper.value ?? printer.caps.paper,
          papers: currentPaper.value ? [currentPaper.value] : [],
        })
      : '',
    printer_host: printer.host,
    printer_port: String(printer.port),
    agent_url: printer.agent_url,
    printer_dpi: String(printer.dpi),
    printer_darkness: String(printer.darkness),
    printer_speed: String(printer.speed),
    printer_gap: String(printer.gap),
    printer_direction: String(printer.direction),
  }
}

async function loadTemplate() {
  try {
    // GET /settings nests values under their domain: { labels: { template } }
    const { data } = await http.get('/settings', { params: { domain: 'labels' } })
    if (data?.labels?.template) applyTemplate(data.labels.template)
    applyPrinter(data?.labels)
  } catch {
    // No saved template yet, or the request failed — defaults stay in place.
  } finally {
    // Only start auto-saving once the stored template has been applied,
    // otherwise the initial defaults would immediately overwrite it.
    templateLoaded.value = true
    if (!auth.isAdmin) saveState.value = 'readonly'
    // Refresh the inventory against this machine: the saved snapshot may come
    // from another till whose printer list differs.
    if (printer.transport === 'agent') fetchPrinters()
  }
}

let saveTimer: ReturnType<typeof setTimeout> | null = null

async function persistTemplate() {
  if (!auth.isAdmin) {
    saveState.value = 'readonly'
    return
  }
  saveState.value = 'saving'
  try {
    await http.post('/settings', {
      domain: 'labels',
      settings: { template: serializeTemplate(), ...serializePrinter() },
    })
    saveState.value = 'saved'
  } catch {
    saveState.value = 'error'
  }
}

const saveStatus = computed(() => {
  switch (saveState.value) {
    case 'saving':
      return { text: 'Enregistrement...', class: 'text-gray-400' }
    case 'saved':
      return { text: 'Modèle enregistré', class: 'text-green-600 dark:text-green-400' }
    case 'error':
      return { text: 'Échec de l’enregistrement', class: 'text-red-600 dark:text-red-400' }
    case 'readonly':
      return { text: 'Lecture seule (admin requis)', class: 'text-amber-600 dark:text-amber-400' }
    default:
      return { text: '', class: '' }
  }
})

function scheduleSave() {
  if (!templateLoaded.value) return
  if (saveTimer) clearTimeout(saveTimer)
  // Dragging fires continuously — debounce so one gesture is a single write.
  saveTimer = setTimeout(persistTemplate, 700)
}

function resetTemplate() {
  Object.assign(label, defaultLabel())
  const d = defaultLayout()
  for (const f of ALL_FIELDS) Object.assign(layout[f.key], d[f.key])
  printMode.value = 'sheet'
}

watch([label, layout, printMode, printer], scheduleSave, { deep: true })

// ── Selection ────────────────────────────────────────────────────
interface SelectedEntry {
  product: Product
  qty: number
}
const selected = reactive(new Map<number, SelectedEntry>())
const selectedList = computed(() => Array.from(selected.values()))

function toggleProduct(p: Product) {
  if (selected.has(p.id)) selected.delete(p.id)
  else selected.set(p.id, { product: p, qty: 1 })
}
function removeProduct(id: number) {
  selected.delete(id)
}
function clearSelection() {
  selected.clear()
}

const previewLabels = computed(() => {
  const out: Product[] = []
  for (const s of selectedList.value) {
    for (let i = 0; i < Math.max(1, s.qty || 1); i++) out.push(s.product)
  }
  return out
})
const totalLabelCount = computed(() => previewLabels.value.length)

// The designer needs something to draw before any product is picked.
const PLACEHOLDER = {
  id: 0,
  p_title: 'Nom du produit',
  p_code: 'PRD-0000',
  p_sku: 'SKU-0000',
  p_ean13: '6923736790424',
  p_salePrice: 699,
  p_purchasePrice: 500,
  category: { ctg_title: 'Catégorie' },
  brand: { br_title: 'MARQUE' },
} as unknown as Product

const sampleProduct = computed<Product>(() => selectedList.value[0]?.product ?? PLACEHOLDER)
const sampleBarcode = computed(() =>
  sampleProduct.value.p_ean13 ? renderBarcodeDataUrl(sampleProduct.value.p_ean13) : renderBarcodeDataUrl('6923736790424')
)

// ── Field content ────────────────────────────────────────────────
function formatPriceDh(value: number): string {
  return value.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' dhs'
}

function fieldText(key: FieldKey, p: Product): string {
  switch (key) {
    case 'title':
      return p.p_title
    case 'sku':
      return p.p_sku || p.p_code
    case 'imei':
      return p.p_imei ?? ''
    case 'salePrice':
      return formatPriceDh(Number(p.p_salePrice))
    case 'purchasePrice':
      return formatPriceDh(Number(p.p_purchasePrice))
    case 'category':
      return p.category?.ctg_title ?? ''
    case 'brand':
      return p.brand?.br_title ?? ''
    default:
      return ''
  }
}

function fieldStyle(key: FieldKey) {
  const f = layout[key]
  const base: Record<string, string> = {
    left: f.x * PX_PER_MM + 'px',
    top: f.y * PX_PER_MM + 'px',
  }
  if (key !== 'barcode') {
    base.fontSize = f.size * PT_TO_MM * PX_PER_MM + 'px'
    base.fontWeight = f.bold ? '700' : '400'
    base.color = '#111827'
  }
  return base
}

// ── Drag to position ─────────────────────────────────────────────
const canvasEl = ref<HTMLElement | null>(null)

// ── Overflow detection ───────────────────────────────────────────
// The printed label sets overflow:hidden, so anything past the edges is
// silently cut. The canvas clips identically — and we flag the offending
// fields, otherwise a clipped field just disappears with no explanation.
const fieldEls: Record<string, HTMLElement | null> = {}
const overflowing = ref<FieldKey[]>([])

function setFieldEl(key: FieldKey, el: unknown) {
  fieldEls[key] = (el as HTMLElement) ?? null
}

async function checkOverflow() {
  await nextTick()
  const canvas = canvasEl.value
  if (!canvas) return
  const c = canvas.getBoundingClientRect()
  const tol = 1 // px, absorbs the canvas border and sub-pixel rounding

  // The usable area is the label minus whatever the driver declares it cannot
  // reach — a field sitting in that band is lost even though it fits the stock.
  const m = printerMargins.value
  const bounds = {
    left: c.left + (m ? m.left * PX_PER_MM : 0),
    top: c.top + (m ? m.top * PX_PER_MM : 0),
    right: c.right - (m ? m.right * PX_PER_MM : 0),
    bottom: c.bottom - (m ? m.bottom * PX_PER_MM : 0),
  }

  overflowing.value = enabledFields.value
    .filter((f) => {
      const el = fieldEls[f.key]
      if (!el) return false
      const r = el.getBoundingClientRect()
      return (
        r.right > bounds.right + tol ||
        r.bottom > bounds.bottom + tol ||
        r.left < bounds.left - tol ||
        r.top < bounds.top - tol
      )
    })
    .map((f) => f.key)
}

const overflowNames = computed(() =>
  overflowing.value.map((k) => ALL_FIELDS.find((f) => f.key === k)?.label ?? k).join(', ')
)

watch([layout, label, enabledFields, sampleProduct], checkOverflow, { deep: true })
onMounted(checkOverflow)
const activeField = ref<FieldKey | null>(null)

function clamp(v: number, min: number, max: number) {
  return Math.min(Math.max(v, min), max)
}
function round1(v: number) {
  return Math.round(v * 2) / 2
}

function startDrag(e: PointerEvent, key: FieldKey) {
  e.preventDefault()
  activeField.value = key

  const startX = e.clientX
  const startY = e.clientY
  const origX = layout[key].x
  const origY = layout[key].y

  // Listeners go on window rather than the dragged element so the field keeps
  // following the cursor even when it briefly leaves the element (or the
  // canvas) mid-drag — the element itself is only a few millimetres wide.
  const move = (ev: PointerEvent) => {
    layout[key].x = round1(clamp(origX + (ev.clientX - startX) / PX_PER_MM, 0, label.width))
    layout[key].y = round1(clamp(origY + (ev.clientY - startY) / PX_PER_MM, 0, label.height))
  }
  const up = () => {
    window.removeEventListener('pointermove', move)
    window.removeEventListener('pointerup', up)
  }
  window.addEventListener('pointermove', move)
  window.addEventListener('pointerup', up)
}

// ── Print ────────────────────────────────────────────────────────
function esc(v: unknown): string {
  return String(v ?? '').replace(
    /[&<>"']/g,
    (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] as string
  )
}

function labelHtml(p: Product): string {
  const parts = enabledFields.value.map((f) => {
    const l = layout[f.key]
    const pos = `position:absolute;left:${l.x}mm;top:${l.y}mm;`

    if (f.key === 'barcode') {
      const src = p.p_ean13 ? renderBarcodeDataUrl(p.p_ean13) : ''
      return src
        ? `<img style="${pos}width:${l.size}mm;height:${l.height}mm" src="${src}" />`
        : `<div style="${pos}font-size:6pt;color:#9ca3af;font-style:italic">Pas de code EAN</div>`
    }

    const box = l.boxed ? 'border:1.5pt solid #111827;padding:0.3mm 1mm;' : ''
    const style = `${pos}font-size:${l.size}pt;font-weight:${l.bold ? 700 : 400};line-height:1;white-space:nowrap;${box}`
    return `<div style="${style}">${esc(fieldText(f.key, p))}</div>`
  })

  return `<div class="label">${parts.join('')}</div>`
}

const printing = ref(false)

async function printLabels() {
  if (!previewLabels.value.length || printing.value) return

  printError.value = ''
  printInfo.value = ''

  if (printer.transport === 'browser') {
    printViaBrowser()
    return
  }

  await printViaTspl()
}

/**
 * Native thermal path: Laravel turns the template into TSPL, which reaches
 * the printer either through the agent running on this machine or straight
 * from the server, depending on which side can see the printer.
 */
async function printViaTspl() {
  printing.value = true

  const body = {
    items: selectedList.value.map((s) => ({
      product_id: s.product.id,
      qty: Math.max(1, s.qty || 1),
    })),
    // Sent explicitly rather than read from the stored settings, so a
    // non-admin's unsaved tweaks still print the way they see them.
    template: { label: { ...label }, layout: JSON.parse(JSON.stringify(layout)) },
  }
  // The queue is resolved client-side: it names a printer on *this* machine,
  // which is the one the agent can actually reach.
  const queue = printer.name

  try {
    if (printer.transport === 'server') {
      const { data } = await http.post('/labels/print', body)
      printInfo.value = data?.message ?? 'Travail envoyé à l’imprimante.'
      return
    }

    const { data } = await http.post('/labels/tspl', body)

    const res = await fetch(printer.agent_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ payload_base64: data.payload_base64, printer: queue }),
    })
    if (!res.ok) {
      const err = await res.json().catch(() => null)
      throw new Error(err?.error ?? `Agent d’impression : HTTP ${res.status}`)
    }

    printInfo.value = `${totalLabelCount.value} étiquette(s) envoyée(s) à l’imprimante.`
  } catch (e: unknown) {
    printError.value = tsplError(e)
  } finally {
    printing.value = false
  }
}

function tsplError(e: unknown): string {
  // fetch() rejects with a bare TypeError when nothing is listening — by far
  // the most common failure, and the least self-explanatory.
  if (e instanceof TypeError) {
    return `Agent d’impression injoignable sur ${printer.agent_url}. Démarrez-le sur ce poste (tools/label-print-agent), puis réessayez.`
  }
  const res = (e as { response?: { data?: { message?: string } } }).response
  if (res?.data?.message) return res.data.message
  return e instanceof Error ? e.message : "Échec de l’impression."
}

function printViaBrowser() {
  const labels = previewLabels.value.map(labelHtml).join('')
  const border = label.border ? 'border:0.2mm solid #9ca3af;' : ''

  // Roll printers (e.g. Zebra GC420t) feed one label at a time, so the page
  // itself is the label. Sheet mode flows them across an A4 page instead.
  //
  // Roll mode pins html/body to the exact stock size on purpose: a page whose
  // content is narrower than the sheet is what makes Firefox's "ajuster à la
  // largeur" kick in and silently blow the label up.
  const pageCss =
    printMode.value === 'roll'
      ? `@page { size: ${label.width}mm ${label.height}mm; margin: 0; }
         html, body { width: ${label.width}mm; }
         .sheet { display: block; }
         .label { break-after: page; page-break-after: always; }
         /* Sans ça, la dernière étiquette entraîne l'avance d'une étiquette
            vierge — une perdue à chaque impression sur un rouleau. */
         .label:last-child { break-after: auto; page-break-after: auto; }`
      : `@page { size: A4; margin: 8mm; }
         .sheet { display: flex; flex-wrap: wrap; align-content: flex-start; }
         .label { margin: 0 2mm 2mm 0; }`

  const html = `<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Étiquettes produits</title>
<style>
  ${pageCss}
  html, body { margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', Tahoma, sans-serif;
    color: #111827;
    /* Métriques de texte calculées sur la géométrie et non arrondies au
       pixel écran : sur 30 mm, l'arrondi décale visiblement les champs. */
    text-rendering: geometricPrecision;
  }
  /* Les navigateurs suppriment couleurs et fonds à l'impression par défaut :
     le cadre du prix et le liseré de l'étiquette sortiraient délavés. */
  * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .label {
    position: relative;
    width: ${label.width}mm;
    height: ${label.height}mm;
    ${border}
    box-sizing: border-box;
    overflow: hidden;
    break-inside: avoid;
    page-break-inside: avoid;
  }
  /* Le code-barres est un PNG d'environ 230 px agrandi à ~340 points sur une
     tête 203 dpi. Le lissage par défaut transforme les barres en dégradés de
     gris que les douchettes lisent mal ; on force des bords francs. */
  .label img {
    image-rendering: -moz-crisp-edges;
    image-rendering: crisp-edges;
    image-rendering: pixelated;
  }
</style></head><body>
<div class="sheet">${labels}</div>
</body></html>`

  sendToPrinter(html)
}

// The job is rendered in a hidden same-origin iframe rather than a popup
// window: no tab is opened, nothing for the popup blocker to swallow, and the
// click goes straight to the print pipeline. With Chrome started under
// --kiosk-printing it prints on the default printer without any dialog at all.
function sendToPrinter(html: string) {
  const frame = document.createElement('iframe')
  frame.setAttribute('aria-hidden', 'true')
  frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;'
  document.body.appendChild(frame)

  let done = false
  const cleanup = () => {
    if (done) return
    done = true
    frame.remove()
  }

  frame.onload = () => {
    const win = frame.contentWindow
    if (!win) return cleanup()

    // Barcodes are data-URL <img>: the document can fire load while they are
    // still decoding, and an undecoded image prints as a blank rectangle.
    const images = Array.from(win.document.images).map((img) =>
      img.decode().catch(() => undefined)
    )
    Promise.all(images).then(() => {
      win.onafterprint = cleanup
      win.focus()
      win.print()
      // Safety net: onafterprint never fires in kiosk-printing mode, and the
      // frame must outlive print() or the job is cancelled mid-spool.
      setTimeout(cleanup, 60000)
    })
  }

  const doc = frame.contentDocument
  if (!doc) {
    frame.remove()
    printError.value =
      "Impression impossible : le navigateur a refusé le cadre d'impression. Rechargez la page et réessayez."
    return
  }
  printError.value = ''
  doc.open()
  doc.write(html)
  doc.close()
}
</script>
