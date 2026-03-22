<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $t('imports.title') }}</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $t('imports.subtitle') }}</p>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-700">
      <nav class="-mb-px flex gap-4 overflow-x-auto" aria-label="Tabs">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="whitespace-nowrap border-b-2 py-2.5 px-1 text-sm font-medium transition-colors"
          :class="
            activeTab === tab.key
              ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
          "
          @click="switchTab(tab.key)"
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <!-- Active tab content -->
    <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-5">
      <!-- Upload + Template row -->
      <div class="flex flex-col sm:flex-row sm:items-end gap-4">
        <div class="flex-1">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('imports.selectFile') }}
          </label>
          <p class="text-xs text-gray-400 dark:text-gray-500 mb-2">
            {{ $t('imports.expectedColumns') }}: <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 rounded">{{ currentHeadings.join(', ') }}</code>
          </p>
          <input
            ref="fileInput"
            type="file"
            accept=".xlsx,.xls,.csv"
            class="block w-full text-sm text-gray-600 dark:text-gray-400
              file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
              file:text-sm file:font-semibold
              file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/30 dark:file:text-blue-400
              hover:file:bg-blue-100 dark:hover:file:bg-blue-900/50
              cursor-pointer"
            @change="onFileChange"
          />
        </div>

        <div class="flex gap-2 shrink-0">
          <button
            class="px-4 py-2 text-sm font-semibold rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
            @click="downloadTemplate"
          >
            <span class="flex items-center gap-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
              {{ $t('imports.downloadTemplate') }}
            </span>
          </button>
          <button
            v-if="selectedFile && !previewData"
            class="px-4 py-2 text-sm font-semibold bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg transition disabled:opacity-60"
            :disabled="previewing"
            @click="runPreview"
          >
            {{ previewing ? $t('imports.previewing') : $t('imports.preview') }}
          </button>
        </div>
      </div>

      <!-- Preview stats -->
      <div v-if="previewData" class="flex flex-wrap gap-3">
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
          {{ $t('imports.totalRows') }}: {{ previewData.total }}
        </span>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
          {{ $t('imports.valid') }}: {{ previewData.valid_count }}
        </span>
        <span v-if="previewData.invalid_count > 0" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">
          {{ $t('imports.invalid') }}: {{ previewData.invalid_count }}
        </span>
      </div>

      <!-- Preview table -->
      <div v-if="previewData && previewData.rows.length" class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
          <thead class="bg-gray-50 dark:bg-gray-900/50">
            <tr>
              <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 uppercase text-xs">#</th>
              <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 uppercase text-xs">{{ $t('imports.status') }}</th>
              <th
                v-for="h in previewData.expected"
                :key="h"
                class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 uppercase text-xs"
              >
                {{ h }}
              </th>
              <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 uppercase text-xs">{{ $t('imports.errors') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <tr
              v-for="row in previewData.rows"
              :key="row.row"
              :class="row.status === 'invalid' ? 'bg-red-50/50 dark:bg-red-900/10' : ''"
            >
              <td class="px-3 py-2 text-gray-600 dark:text-gray-400 font-mono text-xs">{{ row.row }}</td>
              <td class="px-3 py-2">
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="statusClass(row.status)"
                >
                  {{ row.status === 'valid' ? $t('imports.valid') : row.status === 'imported' ? $t('imports.imported') : $t('imports.invalid') }}
                </span>
              </td>
              <td
                v-for="h in previewData.expected"
                :key="h"
                class="px-3 py-2 text-gray-800 dark:text-gray-200 max-w-[200px] truncate"
                :class="row.errors[h] ? 'text-red-600 dark:text-red-400' : ''"
              >
                {{ row.data[h] ?? '' }}
              </td>
              <td class="px-3 py-2 text-xs text-red-600 dark:text-red-400 max-w-[250px]">
                <template v-if="Object.keys(row.errors).length">
                  <div v-for="(errs, field) in row.errors" :key="field">
                    <strong>{{ field }}</strong>: {{ Array.isArray(errs) ? errs.join(', ') : errs }}
                  </div>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Import actions -->
      <div v-if="previewData" class="flex items-center gap-3">
        <button
          class="px-5 py-2.5 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition disabled:opacity-60"
          :disabled="importing || previewData.valid_count === 0"
          @click="runImport"
        >
          {{ importing ? $t('imports.importing') : $t('imports.importNow') }}
          <span v-if="!importing" class="ml-1 text-xs opacity-80">({{ previewData.valid_count }} {{ $t('imports.rows') }})</span>
        </button>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition"
          @click="resetAll"
        >
          {{ $t('imports.reset') }}
        </button>
      </div>

      <!-- Import result -->
      <div
        v-if="importResult"
        class="rounded-lg p-4 text-sm font-medium"
        :class="importResult.success
          ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800'
          : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800'"
      >
        <p>{{ importResult.message }}</p>
        <p v-if="importResult.created !== undefined" class="mt-1 text-xs opacity-80">
          {{ importResult.created }} {{ $t('imports.created') }}, {{ importResult.updated }} {{ $t('imports.updated') }}
        </p>
        <!-- Validation failures from import -->
        <div v-if="importResult.failures?.length" class="mt-3 space-y-1">
          <p class="font-semibold text-xs">{{ $t('imports.validationErrors') }}:</p>
          <div v-for="f in importResult.failures" :key="`${f.row}-${f.attribute}`" class="text-xs">
            {{ $t('imports.row') }} {{ f.row }} — <strong>{{ f.attribute }}</strong>: {{ f.errors.join(', ') }}
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '@/services/http'
import { API_BASE_URL } from '@/config/api'

const { t } = useI18n()

interface PreviewRow {
  row: number
  data: Record<string, unknown>
  errors: Record<string, string[]>
  status: 'valid' | 'invalid' | 'imported'
}

interface PreviewResponse {
  headings: string[]
  expected: string[]
  rows: PreviewRow[]
  total: number
  valid_count: number
  invalid_count: number
}

interface ImportResultData {
  success: boolean
  message: string
  created?: number
  updated?: number
  failures?: { row: number; attribute: string; errors: string[] }[]
}

const HEADINGS: Record<string, string[]> = {
  products:   ['titre', 'sku', 'ean13', 'prix_achat', 'prix_vente', 'cout', 'tva', 'unite', 'categorie', 'marque'],
  customers:  ['nom', 'role', 'ice', 'rc', 'patente', 'if', 'telephone', 'email', 'adresse', 'ville', 'seuil_credit'],
  suppliers:  ['nom', 'role', 'ice', 'rc', 'patente', 'if', 'telephone', 'email', 'adresse', 'ville', 'seuil_credit'],
  categories: ['nom'],
  brands:     ['nom'],
}

const tabs = computed(() => [
  { key: 'products',   label: t('imports.products') },
  { key: 'customers',  label: t('imports.customers') },
  { key: 'suppliers',  label: t('imports.suppliers') },
  { key: 'categories', label: t('imports.categories') },
  { key: 'brands',     label: t('imports.brands') },
])

const activeTab = ref('products')
const selectedFile = ref<File | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const previewing = ref(false)
const importing = ref(false)
const previewData = ref<PreviewResponse | null>(null)
const importResult = ref<ImportResultData | null>(null)

const currentHeadings = computed(() => HEADINGS[activeTab.value] ?? [])

function switchTab(key: string) {
  activeTab.value = key
  resetAll()
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  selectedFile.value = input.files?.[0] ?? null
  previewData.value = null
  importResult.value = null
}

async function runPreview() {
  if (!selectedFile.value) return
  previewing.value = true
  previewData.value = null

  const form = new FormData()
  form.append('file', selectedFile.value)
  form.append('entity', activeTab.value)

  try {
    const res = await http.post<PreviewResponse>('/import/preview', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    previewData.value = res.data
  } catch (err: unknown) {
    const error = err as { response?: { data?: { message?: string } } }
    importResult.value = {
      success: false,
      message: error.response?.data?.message || t('imports.previewFailed'),
    }
  }
  previewing.value = false
}

async function runImport() {
  if (!selectedFile.value) return
  importing.value = true
  importResult.value = null

  const form = new FormData()
  form.append('file', selectedFile.value)
  form.append('entity', activeTab.value)

  try {
    const res = await http.post<{ message: string; created: number; updated: number }>('/import/run', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    importResult.value = {
      success: true,
      message: res.data.message,
      created: res.data.created,
      updated: res.data.updated,
    }
    // Update preview rows status to 'imported' for valid ones
    if (previewData.value) {
      previewData.value.rows.forEach((row) => {
        if (row.status === 'valid') row.status = 'imported'
      })
    }
  } catch (err: unknown) {
    const error = err as { response?: { status?: number; data?: { message?: string; failures?: { row: number; attribute: string; errors: string[] }[] } } }
    if (error.response?.status === 422 && error.response.data?.failures) {
      importResult.value = {
        success: false,
        message: error.response.data.message || t('imports.importFailed'),
        failures: error.response.data.failures,
      }
      // Mark failed rows in preview
      if (previewData.value && importResult.value.failures) {
        const failedRows = new Set(importResult.value.failures.map((f) => f.row))
        previewData.value.rows.forEach((row) => {
          if (failedRows.has(row.row)) row.status = 'invalid'
          else if (row.status === 'valid') row.status = 'imported'
        })
      }
    } else {
      importResult.value = {
        success: false,
        message: error.response?.data?.message || t('imports.importFailed'),
      }
    }
  }
  importing.value = false
}

function downloadTemplate() {
  const token = localStorage.getItem('token')
  const url = `${API_BASE_URL}/import/template/${activeTab.value}`
  // Use a hidden link with bearer token via fetch + blob
  fetch(url, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  })
    .then((res) => {
      if (!res.ok) throw new Error('Download failed')
      return res.blob()
    })
    .then((blob) => {
      const a = document.createElement('a')
      a.href = URL.createObjectURL(blob)
      a.download = `modele_${activeTab.value}.xlsx`
      a.click()
      URL.revokeObjectURL(a.href)
    })
    .catch(() => {
      importResult.value = { success: false, message: t('imports.templateFailed') }
    })
}

function resetAll() {
  selectedFile.value = null
  previewData.value = null
  importResult.value = null
  if (fileInput.value) fileInput.value.value = ''
}

function statusClass(status: string) {
  switch (status) {
    case 'valid':
      return 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
    case 'imported':
      return 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'
    case 'invalid':
      return 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
    default:
      return 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
  }
}
</script>
