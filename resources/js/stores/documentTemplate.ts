import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'

export interface DocumentTemplateColumns {
  index: boolean
  reference: boolean
  quantity: boolean
  unit_price: boolean
  discount: boolean
  tax: boolean
  total: boolean
}

export interface DocumentTemplateConfig {
  accent_color: string
  text_color: string
  font_family: string
  font_size: number
  paper_size: string
  orientation: string
  margin_x: number
  margin_y: number
  logo_position: string
  logo_height: number
  show_company_block: boolean
  title_override: string
  header_note: string
  show_partner_block: boolean
  show_status: boolean
  show_warehouse: boolean
  show_user: boolean
  show_totals: boolean
  show_total_in_words: boolean
  show_payments: boolean
  show_notes: boolean
  show_bank_details: boolean
  show_legal_mentions: boolean
  table_style: string
  zebra_rows: boolean
  columns: DocumentTemplateColumns
  show_signature: boolean
  signature_left_label: string
  signature_right_label: string
  watermark_text: string
  watermark_opacity: number
  terms: string
  footer_note: string
  currency: string
}

interface TemplateOptions {
  fonts: string[]
  papers: string[]
  orientations: string[]
  table_styles: string[]
  logo_positions: string[]
  columns: string[]
}

export const DEFAULT_KEY = 'default'

export const useDocumentTemplateStore = defineStore('documentTemplate', () => {
  /** Libellé imprimé par défaut, par type de document. */
  const types = ref<Record<string, string>>({})
  /** Mise en page livrée avec l'application. */
  const defaults = ref<Partial<DocumentTemplateConfig>>({})
  /** Config enregistrée par le tenant, par clé ('default' + types). */
  const configs = ref<Record<string, Partial<DocumentTemplateConfig>>>({})
  const options = ref<TemplateOptions>({
    fonts: [],
    papers: [],
    orientations: [],
    table_styles: [],
    logo_positions: [],
    columns: [],
  })
  const loading = ref(false)

  async function fetchAll(): Promise<void> {
    loading.value = true
    try {
      const { data } = await http.get('/document-templates')
      types.value = data.types ?? {}
      defaults.value = data.defaults ?? {}
      configs.value = data.configs ?? {}
      options.value = data.options ?? options.value
    } finally {
      loading.value = false
    }
  }

  /** Une clé a-t-elle sa propre mise en page enregistrée ? */
  function isCustomised(key: string): boolean {
    return Object.keys(configs.value[key] ?? {}).length > 0
  }

  /**
   * Valeurs affichées pour une clé : défauts du code, surchargés par le
   * réglage général, puis par celui du type. Même ordre que côté serveur
   * (DocumentTemplateService::resolve).
   */
  function resolve(key: string): DocumentTemplateConfig {
    const merged = {
      ...defaults.value,
      ...(configs.value[DEFAULT_KEY] ?? {}),
      ...(key === DEFAULT_KEY ? {} : (configs.value[key] ?? {})),
    } as DocumentTemplateConfig

    merged.columns = {
      ...(defaults.value.columns as DocumentTemplateColumns),
      ...(configs.value[DEFAULT_KEY]?.columns ?? {}),
      ...(key === DEFAULT_KEY ? {} : (configs.value[key]?.columns ?? {})),
    }

    // Le titre imprimé ne s'hérite pas du réglage général — même règle
    // que DocumentTemplateService::resolve côté serveur.
    merged.title_override = (key === DEFAULT_KEY ? '' : (configs.value[key]?.title_override ?? '')) as string

    return merged
  }

  async function save(key: string, config: DocumentTemplateConfig): Promise<void> {
    const { data } = await http.put(`/document-templates/${key}`, { config })
    configs.value = { ...configs.value, [key]: data.config }
  }

  async function reset(key: string): Promise<void> {
    await http.delete(`/document-templates/${key}`)
    configs.value = { ...configs.value, [key]: {} }
  }

  /** Aperçu PDF de la config *non enregistrée* — renvoie une URL blob. */
  async function preview(key: string, config: DocumentTemplateConfig): Promise<string> {
    const { data } = await http.post(`/document-templates/${key}/preview`, { config }, { responseType: 'blob' })

    return URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
  }

  return { types, defaults, configs, options, loading, fetchAll, isCustomised, resolve, save, reset, preview }
})
