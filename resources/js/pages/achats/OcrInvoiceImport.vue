<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRouter } from 'vue-router'
import http from '@/services/http'
import { useToastStore } from '@/stores/toastStore'
import { useFeaturesSettings } from '@/composables/useFeaturesSettings'
import * as pdfjsLib from 'pdfjs-dist'

// PDF.js worker — use unpkg CDN for reliable loading in both dev and prod
pdfjsLib.GlobalWorkerOptions.workerSrc = `https://unpkg.com/pdfjs-dist@${pdfjsLib.version}/build/pdf.worker.min.mjs`

const router = useRouter()
const toast = useToastStore()

// Feature access control
const { isOcrImportEnabled, loading: featuresLoading } = useFeaturesSettings()
const ocrDisabled = computed(() => !isOcrImportEnabled.value)
const ocrDisabledMessage = ref('OCR Import is only available in the Business package. Please upgrade your plan to access this feature.')

// ── State ────────────────────────────────────────────────────────
type Step = 'upload' | 'review' | 'success'
const step = ref<Step>('upload')
const uploading = ref(false)
const confirming = ref(false)
const dragOver = ref(false)

// Parsed OCR data
const ocrData = ref<any>(null)

// PDF preview state
const pdfBlobUrl = ref<string | null>(null)
let pdfDocRaw: any = null // NOT reactive — PDF.js v5 uses private fields incompatible with Proxy
const currentPage = ref(1)
const totalPages = ref(0)
const pdfScale = ref(1.2)
const pdfContainerRef = ref<HTMLDivElement | null>(null)
const pdfCanvasRef = ref<HTMLCanvasElement | null>(null)
const textLayerRef = ref<HTMLDivElement | null>(null)
const highlightLayerRef = ref<HTMLDivElement | null>(null)
const pdfRendering = ref(false)

// Highlight mode system
type HighlightMode = 'supplier' | 'invoice_number' | 'date' | 'items' | 'totals' | null
const activeHighlightMode = ref<HighlightMode>(null)
const showFormPanel = ref(true)

interface HighlightZone {
  id: string
  mode: HighlightMode
  page: number
  rects: Array<{ x: number; y: number; w: number; h: number }>
  text: string
}
const highlights = ref<HighlightZone[]>([])

const highlightModes = [
  { key: 'supplier' as HighlightMode, label: 'Fournisseur', color: 'bg-purple-500', lightColor: 'bg-purple-200/50 dark:bg-purple-500/30', borderColor: 'border-purple-500', textColor: 'text-purple-700 dark:text-purple-300', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
  { key: 'invoice_number' as HighlightMode, label: 'N Facture', color: 'bg-blue-500', lightColor: 'bg-blue-200/50 dark:bg-blue-500/30', borderColor: 'border-blue-500', textColor: 'text-blue-700 dark:text-blue-300', icon: 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14' },
  { key: 'date' as HighlightMode, label: 'Date', color: 'bg-teal-500', lightColor: 'bg-teal-200/50 dark:bg-teal-500/30', borderColor: 'border-teal-500', textColor: 'text-teal-700 dark:text-teal-300', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { key: 'items' as HighlightMode, label: 'Articles', color: 'bg-[#7C5CFC]', lightColor: 'bg-[#E4D9FE]/50 dark:bg-[#7C5CFC]/30', borderColor: 'border-[#7C5CFC]', textColor: 'text-[#5B3FD1] dark:text-[#C4B5FD]', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
  { key: 'totals' as HighlightMode, label: 'Totaux', color: 'bg-green-500', lightColor: 'bg-green-200/50 dark:bg-green-500/30', borderColor: 'border-green-500', textColor: 'text-green-700 dark:text-green-300', icon: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z' },
]

// Dropdown data
const suppliers = ref<any[]>([])
const products = ref<any[]>([])
const warehouses = ref<any[]>([])
const incrementors = ref<any[]>([])

// Editable form
const supplierCreated = ref(false)
const form = reactive({
  supplier_id: null as number | null,
  invoice_number: '',
  invoice_date: '',
  due_date: '',
  warehouse_id: null as number | null,
  document_incrementor_id: null as number | null,
  payment_method: 'credit' as string,
  notes: '',
  lines: [] as Array<{
    key: number
    product_id: number | null
    designation: string
    quantity: number
    unit_price: number
    discount_percent: number
    tax_percent: number
    match_score: number
    created: boolean
    ocr_total_ht: number   // original OCR total — used to detect qty parsing errors
  }>,
  total_ht: 0 as number,
  total_tax: 0 as number,
  total_ttc: 0 as number,
})

let lineKey = 0

// ── Field-level fill from PDF ───────────────────────────────────
interface ActiveField {
  type: 'header' | 'line'
  field: string       // e.g. 'invoice_number', 'designation', 'quantity'
  lineIdx?: number    // for line fields
  label: string       // shown in UI
}
const activeField = ref<ActiveField | null>(null)

function setActiveField(af: ActiveField) {
  activeField.value = af
  activeHighlightMode.value = null // clear category mode
}

function clearActiveField() {
  activeField.value = null
}

function fillActiveField(text: string) {
  const af = activeField.value
  if (!af) return

  const cleaned = text.replace(/\s+/g, ' ').trim()
  if (!cleaned) return

  if (af.type === 'header') {
    switch (af.field) {
      case 'invoice_number':
        form.invoice_number = cleaned
        break
      case 'invoice_date':
      case 'due_date': {
        const dm = cleaned.match(/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/)
        if (dm) {
          let [, d, m, y] = dm
          if (y.length === 2) y = '20' + y
          ;(form as any)[af.field] = `${y}-${m.padStart(2, '0')}-${d.padStart(2, '0')}`
        } else {
          ;(form as any)[af.field] = cleaned
        }
        break
      }
      case 'supplier_name': {
        const match = suppliers.value.find(s =>
          s.tp_title.toLowerCase().includes(cleaned.toLowerCase()) ||
          cleaned.toLowerCase().includes(s.tp_title.toLowerCase()),
        )
        if (match) {
          form.supplier_id = match.id
          toast.success(`Fournisseur: ${match.tp_title}`)
        } else {
          toast.info(`"${cleaned}" — aucun fournisseur trouvé`)
        }
        break
      }
      case 'notes':
        form.notes = form.notes ? form.notes + ' ' + cleaned : cleaned
        break
    }
  } else if (af.type === 'line' && af.lineIdx != null) {
    const line = form.lines[af.lineIdx]
    if (!line) return

    switch (af.field) {
      case 'designation':
        line.designation = cleaned
        break
      case 'quantity':
      case 'unit_price':
      case 'discount_percent':
      case 'tax_percent': {
        const num = parseFloat(cleaned.replace(/\s/g, '').replace(',', '.'))
        if (!isNaN(num)) (line as any)[af.field] = num
        else toast.info(`"${cleaned}" n'est pas un nombre valide`)
        break
      }
    }
  }

  toast.success(`${af.label} ← "${cleaned.substring(0, 40)}${cleaned.length > 40 ? '…' : ''}"`)
  activeField.value = null
}

// Created document
const createdDoc = ref<any>(null)

// ── Load dropdown data ───────────────────────────────────────────
onMounted(async () => {
  const results = await Promise.allSettled([
    http.get('/third-partners', { params: { per_page: 500, role: 'supplier' } }),
    http.get('/products', { params: { per_page: 500 } }),
    http.get('/warehouses'),
    http.get('/document-incrementors'),
  ])
  const [partRes, prodRes, whRes, incRes] = results

  if (partRes.status === 'fulfilled') {
    const d = partRes.value.data
    suppliers.value = Array.isArray(d) ? d : (d?.data ?? [])
  }
  if (prodRes.status === 'fulfilled') {
    const d = prodRes.value.data
    products.value = Array.isArray(d) ? d : (d?.data ?? [])
  }
  if (whRes.status === 'fulfilled') {
    const d = whRes.value.data
    warehouses.value = Array.isArray(d) ? d : (d?.data ?? [])
  }
  if (incRes.status === 'fulfilled') {
    const d = incRes.value.data
    incrementors.value = Array.isArray(d) ? d : (d?.data ?? [])
  }

  const ipInc = incrementors.value.find((i: any) => i.di_model === 'InvoicePurchase')
  if (ipInc) form.document_incrementor_id = ipInc.id
  if (warehouses.value.length === 1) form.warehouse_id = warehouses.value[0].id
})

onUnmounted(() => {
  if (pdfBlobUrl.value) URL.revokeObjectURL(pdfBlobUrl.value)
})

// ── Computed ─────────────────────────────────────────────────────
const confidencePercent = computed(() =>
  ocrData.value ? Math.round((ocrData.value.confidence ?? 0) * 100) : 0,
)

const confidenceColor = computed(() => {
  const p = confidencePercent.value
  if (p >= 70) return 'text-green-600 dark:text-green-400'
  if (p >= 40) return 'text-yellow-600 dark:text-yellow-400'
  return 'text-red-600 dark:text-red-400'
})

const computedTotalHt = computed(() =>
  form.lines.reduce((sum, l) => {
    const ht = l.quantity * l.unit_price * (1 - l.discount_percent / 100)
    return sum + ht
  }, 0),
)

const computedTotalTax = computed(() =>
  form.lines.reduce((sum, l) => {
    const ht = l.quantity * l.unit_price * (1 - l.discount_percent / 100)
    return sum + ht * (l.tax_percent / 100)
  }, 0),
)

const computedTotalTtc = computed(() => computedTotalHt.value + computedTotalTax.value)

const activeMode = computed(() => highlightModes.find(m => m.key === activeHighlightMode.value))

const highlightsForPage = computed(() =>
  highlights.value.filter(h => h.page === currentPage.value),
)

// ── PDF Rendering ───────────────────────────────────────────────
async function loadPdf(url: string) {
  const loadingTask = pdfjsLib.getDocument(url)
  pdfDocRaw = await loadingTask.promise
  totalPages.value = pdfDocRaw.numPages
  currentPage.value = 1
  await renderPage(1)
}

async function renderPage(pageNum: number) {
  if (!pdfDocRaw || pdfRendering.value) return
  pdfRendering.value = true

  try {
    const page = await pdfDocRaw.getPage(pageNum)
    const viewport = page.getViewport({ scale: pdfScale.value })

    const canvas = pdfCanvasRef.value!
    const ctx = canvas.getContext('2d')!
    canvas.width = viewport.width
    canvas.height = viewport.height
    canvas.style.width = viewport.width + 'px'
    canvas.style.height = viewport.height + 'px'

    await page.render({ canvasContext: ctx, viewport }).promise

    // Text layer
    const textContent = await page.getTextContent()
    const textLayer = textLayerRef.value!
    textLayer.innerHTML = ''
    textLayer.style.width = viewport.width + 'px'
    textLayer.style.height = viewport.height + 'px'

    // Position text spans
    for (const item of textContent.items as any[]) {
      if (!item.str) continue
      const tx = pdfjsLib.Util.transform(viewport.transform, item.transform)
      const span = document.createElement('span')
      span.textContent = item.str
      span.style.position = 'absolute'
      span.style.left = tx[4] + 'px'
      span.style.top = (viewport.height - tx[5]) + 'px'
      span.style.fontSize = Math.abs(tx[0]) + 'px'
      span.style.fontFamily = 'sans-serif'
      span.style.transformOrigin = '0% 0%'
      // Width of item
      if (item.width) {
        span.style.width = (item.width * pdfScale.value) + 'px'
      }
      span.style.color = 'transparent'
      span.style.whiteSpace = 'pre'
      span.style.lineHeight = '1'
      textLayer.appendChild(span)
    }

    // Highlight layer
    const hlLayer = highlightLayerRef.value!
    hlLayer.style.width = viewport.width + 'px'
    hlLayer.style.height = viewport.height + 'px'
    renderHighlights()
  } finally {
    pdfRendering.value = false
  }
}

function renderHighlights() {
  const hlLayer = highlightLayerRef.value
  if (!hlLayer) return
  hlLayer.innerHTML = ''

  for (const hl of highlightsForPage.value) {
    const mode = highlightModes.find(m => m.key === hl.mode)
    if (!mode) continue

    for (const rect of hl.rects) {
      const div = document.createElement('div')
      div.style.position = 'absolute'
      div.style.left = rect.x + 'px'
      div.style.top = rect.y + 'px'
      div.style.width = rect.w + 'px'
      div.style.height = rect.h + 'px'
      div.style.pointerEvents = 'none'
      div.style.mixBlendMode = 'multiply'
      div.style.borderRadius = '2px'

      // Color mapping
      const colorMap: Record<string, string> = {
        supplier: 'rgba(168,85,247,0.3)',
        invoice_number: 'rgba(59,130,246,0.3)',
        date: 'rgba(20,184,166,0.3)',
        items: 'rgba(249,115,22,0.3)',
        totals: 'rgba(34,197,94,0.3)',
      }
      div.style.backgroundColor = colorMap[hl.mode ?? ''] ?? 'rgba(156,163,175,0.3)'
      hlLayer.appendChild(div)
    }
  }
}

watch(highlightsForPage, () => renderHighlights(), { deep: true })

async function goToPage(page: number) {
  if (page < 1 || page > totalPages.value || page === currentPage.value) return
  currentPage.value = page
  await renderPage(page)
}

async function zoomPdf(delta: number) {
  const newScale = Math.max(0.5, Math.min(3, pdfScale.value + delta))
  if (newScale === pdfScale.value) return
  pdfScale.value = newScale
  await renderPage(currentPage.value)
}

// ── Text selection capture ──────────────────────────────────────
function onTextLayerMouseUp() {
  const hasTarget = activeHighlightMode.value || activeField.value
  if (!hasTarget) return

  const selection = window.getSelection()
  if (!selection || selection.isCollapsed || !selection.toString().trim()) return

  const text = selection.toString().trim()
  const range = selection.getRangeAt(0)
  const rects = Array.from(range.getClientRects())

  if (!rects.length) { selection.removeAllRanges(); return }

  // Convert rects from viewport coords to relative to text layer
  const container = textLayerRef.value!
  const containerRect = container.getBoundingClientRect()

  const hlRects = rects.map(r => ({
    x: r.left - containerRect.left,
    y: r.top - containerRect.top,
    w: r.width,
    h: r.height,
  })).filter(r => r.w > 2 && r.h > 2)

  if (!hlRects.length) { selection.removeAllRanges(); return }

  // Field-level fill takes priority
  if (activeField.value) {
    // Visual highlight for field fill
    const fieldColorMap: Record<string, string> = {
      invoice_number: 'rgba(59,130,246,0.3)',
      invoice_date: 'rgba(20,184,166,0.3)',
      due_date: 'rgba(20,184,166,0.3)',
      supplier_name: 'rgba(168,85,247,0.3)',
      designation: 'rgba(249,115,22,0.3)',
      quantity: 'rgba(234,179,8,0.3)',
      unit_price: 'rgba(234,179,8,0.3)',
      discount_percent: 'rgba(234,179,8,0.3)',
      tax_percent: 'rgba(234,179,8,0.3)',
      notes: 'rgba(156,163,175,0.3)',
    }
    // Add highlight
    highlights.value.push({
      id: Date.now().toString(),
      mode: null,
      page: currentPage.value,
      rects: hlRects,
      text,
    })
    // Draw colored rect directly
    const hlLayer = highlightLayerRef.value
    if (hlLayer) {
      const color = fieldColorMap[activeField.value.field] ?? 'rgba(59,130,246,0.25)'
      for (const rect of hlRects) {
        const div = document.createElement('div')
        div.style.cssText = `position:absolute;left:${rect.x}px;top:${rect.y}px;width:${rect.w}px;height:${rect.h}px;background:${color};pointer-events:none;border-radius:2px;`
        hlLayer.appendChild(div)
      }
    }

    fillActiveField(text)
    selection.removeAllRanges()
    return
  }

  // Category-level highlight mode
  if (activeHighlightMode.value) {
    highlights.value = highlights.value.filter(
      h => !(h.mode === activeHighlightMode.value && h.page === currentPage.value),
    )
    highlights.value.push({
      id: Date.now().toString(),
      mode: activeHighlightMode.value,
      page: currentPage.value,
      rects: hlRects,
      text,
    })
    assignHighlightToForm(activeHighlightMode.value!, text)
  }

  selection.removeAllRanges()
}

function assignHighlightToForm(mode: HighlightMode, text: string) {
  switch (mode) {
    case 'supplier':
      // Try to find a matching supplier by name
      const match = suppliers.value.find(s =>
        s.tp_title.toLowerCase().includes(text.toLowerCase()) ||
        text.toLowerCase().includes(s.tp_title.toLowerCase()),
      )
      if (match) {
        form.supplier_id = match.id
        toast.success(`Fournisseur: ${match.tp_title}`)
      } else {
        toast.info(`Texte capture: "${text}" — Selectionnez manuellement.`)
      }
      break
    case 'invoice_number':
      form.invoice_number = text.replace(/\s+/g, ' ').trim()
      toast.success(`N Facture: ${form.invoice_number}`)
      break
    case 'date': {
      // Try to parse date from selected text
      const dateMatch = text.match(/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/)
      if (dateMatch) {
        let [, d, m, y] = dateMatch
        if (y.length === 2) y = '20' + y
        const isoDate = `${y}-${m.padStart(2, '0')}-${d.padStart(2, '0')}`
        form.invoice_date = isoDate
        toast.success(`Date: ${d}/${m}/${y}`)
      } else {
        toast.info(`Texte capture: "${text}" — Entrez la date manuellement.`)
      }
      break
    }
    case 'items':
      toast.info(`Zone articles marquee (${text.substring(0, 50)}...)`)
      break
    case 'totals': {
      // Extract numbers from selected total text
      const nums = text.match(/[\d\s]+[,.]?\d+/g)
      if (nums && nums.length > 0) {
        toast.success(`Totaux captures (${nums.length} valeurs)`)
      } else {
        toast.info(`Zone totaux marquee`)
      }
      break
    }
  }
}

function clearHighlights(mode?: HighlightMode) {
  if (mode) {
    highlights.value = highlights.value.filter(h => h.mode !== mode)
  } else {
    highlights.value = []
  }
  renderHighlights()
}

function toggleMode(mode: HighlightMode) {
  activeHighlightMode.value = activeHighlightMode.value === mode ? null : mode
}

// ── Upload ───────────────────────────────────────────────────────
function onDragOver(e: DragEvent) {
  e.preventDefault()
  dragOver.value = true
}

function onDragLeave() {
  dragOver.value = false
}

function onDrop(e: DragEvent) {
  e.preventDefault()
  dragOver.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) uploadFile(file)
}

function onFileSelect(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) uploadFile(file)
  input.value = ''
}

async function uploadFile(file: File) {
  if (!file.name.toLowerCase().endsWith('.pdf')) {
    toast.error('Seuls les fichiers PDF sont acceptes.')
    return
  }
  if (file.size > 20 * 1024 * 1024) {
    toast.error('Le fichier ne doit pas depasser 20 Mo.')
    return
  }

  // Create blob URL for PDF preview
  if (pdfBlobUrl.value) URL.revokeObjectURL(pdfBlobUrl.value)
  pdfBlobUrl.value = URL.createObjectURL(file)

  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    const { data } = await http.post('/achats/ocr/parse', fd)

    if (!data.success) {
      toast.error(data.message || 'Erreur OCR.')
      return
    }

    ocrData.value = data.data
    await populateForm(data.data)
    step.value = 'review'
    toast.success('PDF analyse avec succes !')
  } catch (err: any) {
    const msg = err.response?.data?.message || "Erreur lors de l'analyse du PDF."
    toast.error(msg)
    return
  } finally {
    uploading.value = false
  }

  // Render PDF after DOM update (outside try/catch for OCR)
  try {
    await nextTick()
    await nextTick() // double nextTick to ensure canvas is mounted
    console.log('[OCR] Loading PDF preview...', pdfBlobUrl.value)
    await loadPdf(pdfBlobUrl.value!)
    console.log('[OCR] PDF rendered OK, pages:', totalPages.value)
  } catch (pdfErr: any) {
    console.error('[OCR] PDF preview error:', pdfErr)
  }
}

// ── Reload dropdown data (after auto-creation) ─────────────────
async function reloadDropdowns() {
  try {
    const [partRes, prodRes] = await Promise.all([
      http.get('/third-partners', { params: { per_page: 500, role: 'supplier' } }),
      http.get('/products', { params: { per_page: 500 } }),
    ])
    suppliers.value = Array.isArray(partRes.data) ? partRes.data : ((partRes.data as any).data ?? [])
    products.value = Array.isArray(prodRes.data) ? prodRes.data : ((prodRes.data as any).data ?? [])
  } catch (e) {
    console.warn('[OCR] Failed to reload dropdowns:', e)
  }
}

// ── Populate form from OCR result ────────────────────────────────
async function populateForm(data: any) {
  await reloadDropdowns()

  form.supplier_id = data.supplier?.matched_id ?? null
  supplierCreated.value = false

  form.invoice_number = data.invoice_number ?? ''
  form.invoice_date = data.invoice_date ?? new Date().toISOString().split('T')[0]
  form.due_date = data.due_date ?? ''
  form.total_ht = data.totals?.total_ht ?? 0
  form.total_tax = data.totals?.total_tax ?? 0
  form.total_ttc = data.totals?.total_ttc ?? 0

  form.lines = (data.lines ?? []).map((l: any) => ({
    key: ++lineKey,
    product_id: l.product_id ?? null,
    designation: l.designation ?? '',
    quantity: l.quantity ?? 1,
    unit_price: l.unit_price ?? 0,
    discount_percent: 0,
    tax_percent: l.tax_percent ?? 20,
    match_score: l.match_score ?? 0,
    created: false,
    ocr_total_ht: l.total_ht ?? 0,
  }))
}

// ── Line management ──────────────────────────────────────────────
function addLine() {
  form.lines.push({
    key: ++lineKey,
    product_id: null,
    designation: '',
    quantity: 1,
    unit_price: 0,
    discount_percent: 0,
    tax_percent: 20,
    match_score: 0,
    created: false,
    ocr_total_ht: 0,
  })
}

function removeLine(index: number) {
  form.lines.splice(index, 1)
}

function lineHt(l: typeof form.lines[0]) {
  return l.quantity * l.unit_price * (1 - l.discount_percent / 100)
}

// ── OCR qty sanity check ─────────────────────────────────────────
// If qty × unit_price diverges from the OCR-extracted total by >10%, the qty is likely wrong.
function isQtySuspicious(l: typeof form.lines[0]): boolean {
  if (!l.ocr_total_ht || !l.unit_price || l.quantity <= 0) return false
  const expected = l.ocr_total_ht / l.unit_price
  return Math.abs(l.quantity - expected) / expected > 0.1
}

function suggestedQty(l: typeof form.lines[0]): number {
  if (!l.ocr_total_ht || !l.unit_price) return l.quantity
  return Math.round(l.ocr_total_ht / l.unit_price)
}

function applyQtySuggestion(l: typeof form.lines[0]) {
  l.quantity = suggestedQty(l)
}

// ── Confirm & create document ────────────────────────────────────
async function onConfirm() {
  if (!form.supplier_id && !ocrData.value?.supplier?.name) {
    toast.error('Veuillez selectionner un fournisseur.')
    return
  }
  if (form.lines.length === 0) {
    toast.error('Ajoutez au moins une ligne.')
    return
  }

  confirming.value = true
  try {
    const payload: any = {
      document_type: 'InvoicePurchase',
      document_title: 'Facture Achat',
      document_incrementor_id: form.document_incrementor_id,
      reference: form.invoice_number || null,
      thirdPartner_id: form.supplier_id || null,
      warehouse_id: form.warehouse_id,
      issued_at: form.invoice_date,
      due_at: form.due_date || null,
      notes: form.notes || (form.invoice_number ? `Facture fournisseur ref: ${form.invoice_number}` : ''),
      lines: form.lines.map((l) => ({
        product_id: l.product_id || null,
        designation: l.designation,
        quantity: l.quantity,
        unit_price: l.unit_price,
        discount_percent: l.discount_percent,
        tax_percent: l.tax_percent,
        line_type: 'product',
        unit: 'pcs',
      })),
      footer: {
        total_ht: computedTotalHt.value,
        total_tax: computedTotalTax.value,
        total_ttc: computedTotalTtc.value,
        payment_method: form.payment_method,
      },
    }

    // Pass supplier OCR data for auto-creation if no supplier selected
    if (!form.supplier_id && ocrData.value?.supplier) {
      payload.supplier_data = {
        name: ocrData.value.supplier.name,
        ice: ocrData.value.supplier.ice || null,
        rc: ocrData.value.supplier.rc || null,
        if: ocrData.value.supplier.if || null,
        patente: ocrData.value.supplier.patente || null,
      }
    }

    const { data } = await http.post('/achats/ocr/confirm', payload)
    if (data.success) {
      createdDoc.value = data.data
      step.value = 'success'
      toast.success("Facture d'achat creee avec succes !")
    }
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Erreur lors de la creation.'
    toast.error(msg)
  } finally {
    confirming.value = false
  }
}

function resetAll() {
  step.value = 'upload'
  ocrData.value = null
  createdDoc.value = null
  supplierCreated.value = false
  highlights.value = []
  activeHighlightMode.value = null
  activeField.value = null
  form.lines = []
  form.supplier_id = null
  form.invoice_number = ''
  form.invoice_date = ''
  form.due_date = ''
  form.notes = ''
  form.total_ht = 0
  form.total_tax = 0
  form.total_ttc = 0
  if (pdfBlobUrl.value) { URL.revokeObjectURL(pdfBlobUrl.value); pdfBlobUrl.value = null }
  pdfDocRaw = null
  totalPages.value = 0
}

function fmt(n: number) {
  return n.toLocaleString('fr-MA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function matchBadgeClass(score: number) {
  if (score >= 0.9) return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
  if (score >= 0.5) return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300'
  if (score > 0) return 'bg-[#F1ECFC] text-[#5B3FD1] dark:bg-[#4C3999] dark:text-[#C4B5FD]'
  return 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
}

function matchLabel(score: number) {
  if (score >= 0.9) return 'Exact'
  if (score >= 0.5) return 'Partiel'
  if (score > 0) return 'Faible'
  return 'A creer'
}
</script>

<template>
  <div class="mx-auto py-4 px-4" :class="step === 'review' ? 'max-w-[1800px]' : 'max-w-7xl'">
    <!-- Header -->
    <div class="mb-4">
      <router-link to="/achats/documents" class="text-sm text-teal-600 dark:text-teal-400 hover:underline">
        &larr; Retour aux documents d'achat
      </router-link>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">Import Facture OCR</h1>
    </div>

    <!-- Steps indicator -->
    <div class="flex items-center gap-2 mb-6">
      <div
        v-for="(s, i) in [
          { key: 'upload', label: '1. Upload PDF' },
          { key: 'review', label: '2. Verification' },
          { key: 'success', label: '3. Termine' },
        ]"
        :key="s.key"
        class="flex items-center gap-2"
      >
        <span
          class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold transition"
          :class="
            step === s.key
              ? 'bg-teal-600 text-white'
              : i < ['upload', 'review', 'success'].indexOf(step)
                ? 'bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-300'
                : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
          "
        >{{ i + 1 }}</span>
        <span class="text-sm font-medium" :class="step === s.key ? 'text-gray-900 dark:text-white' : 'text-gray-400'">{{ s.label }}</span>
        <svg v-if="i < 2" class="w-4 h-4 text-gray-300 dark:text-gray-600 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </div>
    </div>

    <!-- ═════════════════════════════════════════════════════════════
         STEP 1: UPLOAD
         ═════════════════════════════════════════════════════════════ -->
    <div v-if="step === 'upload'" class="max-w-2xl mx-auto">
      <div
        class="border-2 border-dashed rounded-xl p-12 text-center transition-colors cursor-pointer"
        :class="
          dragOver
            ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/20'
            : 'border-gray-300 dark:border-gray-600 hover:border-teal-400'
        "
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
        @click="($refs.fileInput as HTMLInputElement)?.click()"
      >
        <div v-if="uploading" class="flex flex-col items-center gap-4">
          <div class="w-12 h-12 border-4 border-teal-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="text-gray-600 dark:text-gray-300 font-medium">Analyse du PDF en cours...</p>
          <p class="text-sm text-gray-400">Extraction du texte et identification des donnees</p>
        </div>
        <div v-else class="flex flex-col items-center gap-3">
          <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
          <p class="text-gray-700 dark:text-gray-200 font-medium text-lg">Glissez votre facture PDF ici</p>
          <p class="text-sm text-gray-400">ou cliquez pour parcourir (max 20 Mo)</p>
        </div>
      </div>
      <input
        ref="fileInput"
        type="file"
        accept=".pdf"
        aria-label="Choisir une facture PDF a analyser"
        class="sr-only"
        @change="onFileSelect"
      />
    </div>

    <!-- ═════════════════════════════════════════════════════════════
         STEP 2: REVIEW — Split layout: PDF left / Form right
         ═════════════════════════════════════════════════════════════ -->
    <div v-if="step === 'review'" class="flex gap-4" style="min-height: calc(100vh - 180px)">

      <!-- ── LEFT: PDF Preview Panel ──────────────────────────── -->
      <div class="flex-1 min-w-0 flex flex-col bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

        <!-- Highlight toolbar -->
        <div class="flex items-center gap-1.5 px-3 py-2 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80 flex-wrap">
          <span class="text-xs font-medium text-gray-500 dark:text-gray-400 mr-1">Surligner :</span>
          <button
            v-for="mode in highlightModes"
            :key="mode.key"
            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md border transition-all"
            :class="
              activeHighlightMode === mode.key
                ? `${mode.color} text-white border-transparent shadow-sm scale-105`
                : `bg-white dark:bg-gray-700 ${mode.textColor} ${mode.borderColor} border-opacity-40 hover:border-opacity-100`
            "
            @click="toggleMode(mode.key)"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" :d="mode.icon" />
            </svg>
            {{ mode.label }}
            <span
              v-if="highlights.filter(h => h.mode === mode.key).length"
              class="ml-0.5 w-4 h-4 flex items-center justify-center rounded-full text-[9px] font-bold"
              :class="activeHighlightMode === mode.key ? 'bg-white/30' : 'bg-current/10'"
            >{{ highlights.filter(h => h.mode === mode.key).length }}</span>
          </button>

          <div class="flex-1"></div>

          <!-- Clear all highlights -->
          <button
            v-if="highlights.length"
            class="text-xs text-gray-400 hover:text-red-500 transition"
            title="Effacer tous les surlignages"
            @click="clearHighlights()"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>

          <!-- Toggle form panel -->
          <button
            class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition ml-1"
            :title="showFormPanel ? 'Masquer le formulaire' : 'Afficher le formulaire'"
            @click="showFormPanel = !showFormPanel"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="showFormPanel ? 'M9 5l7 7-7 7' : 'M15 19l-7-7 7-7'" />
            </svg>
          </button>
        </div>

        <!-- Active mode indicator -->
        <div v-if="activeMode" class="px-3 py-1.5 text-xs font-medium flex items-center gap-2" :class="activeMode.lightColor">
          <span class="w-2 h-2 rounded-full animate-pulse" :class="activeMode.color"></span>
          <span :class="activeMode.textColor">Mode actif : <strong>{{ activeMode.label }}</strong> — Selectionnez du texte sur le PDF</span>
          <button class="ml-auto text-gray-400 hover:text-gray-600 text-xs" @click="activeHighlightMode = null">Annuler</button>
        </div>

        <!-- PDF controls -->
        <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 dark:border-gray-700 text-xs">
          <div class="flex items-center gap-2">
            <button
              class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-40 transition"
              :disabled="currentPage <= 1"
              @click="goToPage(currentPage - 1)"
            >&larr;</button>
            <span class="text-gray-600 dark:text-gray-300 font-medium">{{ currentPage }} / {{ totalPages }}</span>
            <button
              class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-40 transition"
              :disabled="currentPage >= totalPages"
              @click="goToPage(currentPage + 1)"
            >&rarr;</button>
          </div>
          <div class="flex items-center gap-1.5">
            <button class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 transition text-sm" @click="zoomPdf(-0.2)">-</button>
            <span class="text-gray-500 w-10 text-center">{{ Math.round(pdfScale * 100) }}%</span>
            <button class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 transition text-sm" @click="zoomPdf(0.2)">+</button>
          </div>
        </div>

        <!-- PDF canvas -->
        <div
          ref="pdfContainerRef"
          class="flex-1 overflow-auto bg-gray-100 dark:bg-gray-900 flex justify-center p-4"
          :class="(activeHighlightMode || activeField) ? 'cursor-text' : 'cursor-default'"
        >
          <div class="relative inline-block shadow-lg" style="line-height: 0;">
            <canvas ref="pdfCanvasRef"></canvas>
            <!-- Highlight overlay (below text layer for visual) -->
            <div
              ref="highlightLayerRef"
              class="absolute top-0 left-0 pointer-events-none"
              style="z-index: 1;"
            ></div>
            <!-- Text layer for selection -->
            <div
              ref="textLayerRef"
              class="absolute top-0 left-0 select-text"
              style="z-index: 2; opacity: 0.01;"
              @mouseup="onTextLayerMouseUp"
            ></div>
          </div>
        </div>

        <!-- Confidence bar (bottom of PDF) -->
        <div class="px-3 py-2 border-t border-gray-200 dark:border-gray-700 flex items-center gap-3 text-xs">
          <span class="text-gray-500">OCR</span>
          <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
            <div
              class="h-1.5 rounded-full transition-all"
              :class="confidencePercent >= 70 ? 'bg-green-500' : confidencePercent >= 40 ? 'bg-yellow-500' : 'bg-red-500'"
              :style="{ width: confidencePercent + '%' }"
            ></div>
          </div>
          <span class="font-bold" :class="confidenceColor">{{ confidencePercent }}%</span>
          <span class="text-gray-400">{{ ocrData?.extraction_method ?? '' }}</span>
          <span v-if="ocrData?.price_type === 'ttc'" class="px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300 font-semibold">Prix TTC &rarr; HT</span>
          <span v-else-if="ocrData?.price_type === 'ht'" class="px-1.5 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 font-semibold">Prix HT</span>
        </div>
      </div>

      <!-- ── RIGHT: Form Panel ────────────────────────────────── -->
      <div
        v-show="showFormPanel"
        class="w-[460px] flex-shrink-0 flex flex-col gap-4 overflow-y-auto pr-1"
        style="max-height: calc(100vh - 180px)"
      >
        <!-- Active field indicator -->
        <div v-if="activeField"
          class="flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-50 dark:bg-blue-900/30 border border-blue-300 dark:border-blue-700 text-sm animate-pulse">
          <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
          </svg>
          <span class="text-blue-700 dark:text-blue-300 font-medium truncate">Surlignez sur le PDF &rarr; <strong>{{ activeField.label }}</strong></span>
          <button class="ml-auto text-blue-400 hover:text-blue-600 transition" @click="clearActiveField">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Supplier & Metadata -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
          <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            Informations generales
          </h2>
          <div class="space-y-3">
            <!-- Supplier -->
            <div>
              <label for="ocrinvoiceimport-supplier-id" class="flex items-center gap-1 text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                Fournisseur *
                <button type="button" @click="setActiveField({ type:'header', field:'supplier_name', label:'Fournisseur' })"
                  class="p-0.5 rounded hover:bg-purple-100 dark:hover:bg-purple-900 transition" :class="activeField?.field === 'supplier_name' ? 'bg-purple-100 dark:bg-purple-900 ring-1 ring-purple-400' : ''" title="Remplir depuis le PDF">
                  <svg class="w-3.5 h-3.5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                </button>
                <span v-if="form.supplier_id"
                  class="px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">Existant</span>
                <span v-else-if="ocrData?.supplier?.name"
                  class="px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">A creer</span>
              </label>
              <select id="ocrinvoiceimport-supplier-id" v-model="form.supplier_id"
                class="w-full px-2.5 py-1.5 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 dark:text-gray-200 rounded-lg focus:ring-2 focus:ring-teal-500 focus:outline-none">
                <option :value="null">-- Selectionner --</option>
                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.tp_title }}</option>
              </select>
              <p v-if="ocrData?.supplier?.name" class="text-[10px] text-gray-400 mt-0.5">
                OCR: {{ ocrData.supplier.name }}
                <span v-if="ocrData.supplier.ice" class="ml-1">(ICE: {{ ocrData.supplier.ice }})</span>
              </p>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <!-- Invoice Number -->
              <div>
                <label for="ocrinvoiceimport-invoice-number" class="flex items-center gap-1 text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  <span class="inline-block w-2 h-2 rounded-full bg-blue-500 mr-1"></span>N Facture
                  <button type="button" @click="setActiveField({ type:'header', field:'invoice_number', label:'N° Facture' })"
                    class="p-0.5 rounded hover:bg-blue-100 dark:hover:bg-blue-900 transition" :class="activeField?.field === 'invoice_number' ? 'bg-blue-100 dark:bg-blue-900 ring-1 ring-blue-400' : ''" title="Remplir depuis le PDF">
                    <svg class="w-3 h-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                  </button>
                </label>
                <input id="ocrinvoiceimport-invoice-number" v-model="form.invoice_number" type="text"
                  class="w-full px-2.5 py-1.5 text-sm border rounded-lg focus:ring-2 focus:ring-teal-500 focus:outline-none"
                  :class="activeField?.field === 'invoice_number' ? 'border-blue-400 ring-2 ring-blue-200 dark:ring-blue-800 bg-blue-50 dark:bg-blue-950' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 dark:text-gray-200'" />
              </div>
              <!-- Invoice Date -->
              <div>
                <label for="ocrinvoiceimport-invoice-date" class="flex items-center gap-1 text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  <span class="inline-block w-2 h-2 rounded-full bg-teal-500 mr-1"></span>Date facture
                  <button type="button" @click="setActiveField({ type:'header', field:'invoice_date', label:'Date facture' })"
                    class="p-0.5 rounded hover:bg-teal-100 dark:hover:bg-teal-900 transition" :class="activeField?.field === 'invoice_date' ? 'bg-teal-100 dark:bg-teal-900 ring-1 ring-teal-400' : ''" title="Remplir depuis le PDF">
                    <svg class="w-3 h-3 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                  </button>
                </label>
                <input id="ocrinvoiceimport-invoice-date" v-model="form.invoice_date" type="date"
                  class="w-full px-2.5 py-1.5 text-sm border rounded-lg focus:ring-2 focus:ring-teal-500 focus:outline-none"
                  :class="activeField?.field === 'invoice_date' ? 'border-teal-400 ring-2 ring-teal-200 dark:ring-teal-800 bg-teal-50 dark:bg-teal-950' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 dark:text-gray-200'" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <!-- Due Date -->
              <div>
                <label for="ocrinvoiceimport-due-date" class="flex items-center gap-1 text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Echeance
                  <button type="button" @click="setActiveField({ type:'header', field:'due_date', label:'Echéance' })"
                    class="p-0.5 rounded hover:bg-teal-100 dark:hover:bg-teal-900 transition" :class="activeField?.field === 'due_date' ? 'bg-teal-100 dark:bg-teal-900 ring-1 ring-teal-400' : ''" title="Remplir depuis le PDF">
                    <svg class="w-3 h-3 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                  </button>
                </label>
                <input id="ocrinvoiceimport-due-date" v-model="form.due_date" type="date"
                  class="w-full px-2.5 py-1.5 text-sm border rounded-lg focus:ring-2 focus:ring-teal-500 focus:outline-none"
                  :class="activeField?.field === 'due_date' ? 'border-teal-400 ring-2 ring-teal-200 dark:ring-teal-800 bg-teal-50 dark:bg-teal-950' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 dark:text-gray-200'" />
              </div>
              <!-- Warehouse -->
              <div>
                <label for="ocrinvoiceimport-warehouse-id" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Entrepôt <span class="text-red-500">*</span></label>
                <select id="ocrinvoiceimport-warehouse-id" v-model="form.warehouse_id" required
                  class="w-full px-2.5 py-1.5 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 dark:text-gray-200 rounded-lg focus:ring-2 focus:ring-teal-500 focus:outline-none"
                  :class="{ 'border-red-400': !form.warehouse_id }">
                  <option :value="null" disabled>-- Sélectionner --</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
            </div>

            <!-- Payment method -->
            <div>
              <label for="ocrinvoiceimport-payment-method" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Mode de paiement</label>
              <select id="ocrinvoiceimport-payment-method" v-model="form.payment_method"
                class="w-full px-2.5 py-1.5 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 dark:text-gray-200 rounded-lg focus:ring-2 focus:ring-teal-500 focus:outline-none">
                <option value="cash">Especes</option>
                <option value="check">Cheque</option>
                <option value="transfer">Virement</option>
                <option value="credit">Credit</option>
                <option value="card">Carte</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Line Items -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              <svg class="w-4 h-4 text-[#7C5CFC]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
              </svg>
              Lignes ({{ form.lines.length }})
            </h2>
            <button class="text-xs text-teal-600 dark:text-teal-400 hover:underline font-medium" @click="addLine">+ Ajouter</button>
          </div>

          <div v-if="form.lines.length === 0" class="text-center py-6 text-gray-400 text-sm">
            Aucune ligne detectee.
          </div>

          <!-- Compact line cards -->
          <div v-else class="space-y-2">
            <div
              v-for="(line, idx) in form.lines"
              :key="line.key"
              class="border border-gray-200 dark:border-gray-700 rounded-lg p-2.5 space-y-1.5 hover:border-[#C4B5FD] dark:hover:border-[#7C5CFC]/40 transition"
            >
              <!-- Header row -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                  <span class="text-[10px] font-bold text-gray-400 w-4">{{ idx + 1 }}</span>
                  <span
                    class="px-1.5 py-0.5 text-[9px] font-bold rounded-full"
                    :class="matchBadgeClass(line.match_score)"
                  >{{ matchLabel(line.match_score) }}</span>
                </div>
                <button class="text-red-400 hover:text-red-600 transition" @click="removeLine(idx)">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <!-- Product select -->
              <select :aria-label="`Produit de la ligne`" v-model="line.product_id"
                class="w-full px-2 py-1 text-xs border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 dark:text-gray-200 rounded focus:ring-1 focus:ring-teal-500 focus:outline-none">
                <option :value="null">-- Produit --</option>
                <option v-for="p in products" :key="p.id" :value="p.id">
                  {{ p.p_code ? `[${p.p_code}] ` : '' }}{{ p.p_title }}
                </option>
              </select>

              <!-- Designation with pick button -->
              <div class="flex items-center gap-1">
                <input :aria-label="`Désignation de la ligne`" v-model="line.designation" placeholder="Designation"
                  class="flex-1 px-2 py-1 text-xs border rounded focus:ring-1 focus:ring-teal-500 focus:outline-none"
                  :class="activeField?.type === 'line' && activeField?.lineIdx === idx && activeField?.field === 'designation' ? 'border-[#A78BFA] ring-1 ring-[#E4D9FE] bg-[#F1ECFC] dark:bg-[#2A2151]' : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 dark:text-gray-200'" />
                <button type="button" @click="setActiveField({ type:'line', field:'designation', lineIdx: idx, label:`Désignation L${idx+1}` })"
                  class="p-0.5 rounded hover:bg-[#F1ECFC] dark:hover:bg-[#4C3999] transition flex-shrink-0"
                  :class="activeField?.type === 'line' && activeField?.lineIdx === idx && activeField?.field === 'designation' ? 'bg-[#F1ECFC] dark:bg-[#4C3999] ring-1 ring-[#A78BFA]' : ''"
                  title="Remplir depuis le PDF">
                  <svg class="w-3 h-3 text-[#7C5CFC]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                </button>
              </div>

              <!-- Numbers row -->
              <div class="grid grid-cols-5 gap-1.5">
                <div>
                  <label for="ocrinvoiceimport-line-quantity" class="flex items-center gap-0.5 text-[9px] text-gray-400">Qte
                    <button type="button" @click="setActiveField({ type:'line', field:'quantity', lineIdx: idx, label:`Qté L${idx+1}` })"
                      class="p-0 rounded hover:bg-yellow-100 transition" :class="activeField?.type === 'line' && activeField?.lineIdx === idx && activeField?.field === 'quantity' ? 'bg-yellow-100 ring-1 ring-yellow-400' : ''">
                      <svg class="w-2.5 h-2.5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                    </button>
                    <!-- ⚠️ Qty mismatch badge: OCR total ÷ unit_price suggests a different qty -->
                    <button v-if="isQtySuspicious(line)" type="button"
                      @click="applyQtySuggestion(line)"
                      :title="`Qté suspecte — le total suggère ${suggestedQty(line).toLocaleString()}. Cliquez pour corriger.`"
                      class="ml-0.5 text-amber-500 hover:text-amber-600 transition animate-pulse">
                      <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                      </svg>
                    </button>
                  </label>
                  <input id="ocrinvoiceimport-line-quantity" v-model.number="line.quantity" type="number" min="0"
                    class="w-full px-1.5 py-0.5 text-xs text-right border rounded focus:ring-1 focus:ring-teal-500 focus:outline-none"
                    :class="[
                      activeField?.type === 'line' && activeField?.lineIdx === idx && activeField?.field === 'quantity' ? 'border-yellow-400 ring-1 ring-yellow-200 bg-yellow-50' : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 dark:text-gray-200',
                      isQtySuspicious(line) ? 'border-amber-400 bg-amber-50 dark:bg-amber-950/30' : '',
                    ]" />
                </div>
                <div>
                  <label for="ocrinvoiceimport-line-unit-price" class="flex items-center gap-0.5 text-[9px] text-gray-400">PU HT
                    <button type="button" @click="setActiveField({ type:'line', field:'unit_price', lineIdx: idx, label:`PU HT L${idx+1}` })"
                      class="p-0 rounded hover:bg-yellow-100 transition" :class="activeField?.type === 'line' && activeField?.lineIdx === idx && activeField?.field === 'unit_price' ? 'bg-yellow-100 ring-1 ring-yellow-400' : ''">
                      <svg class="w-2.5 h-2.5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                    </button>
                  </label>
                  <input id="ocrinvoiceimport-line-unit-price" v-model.number="line.unit_price" type="number" min="0" step="0.01"
                    class="w-full px-1.5 py-0.5 text-xs text-right border rounded focus:ring-1 focus:ring-teal-500 focus:outline-none"
                    :class="activeField?.type === 'line' && activeField?.lineIdx === idx && activeField?.field === 'unit_price' ? 'border-yellow-400 ring-1 ring-yellow-200 bg-yellow-50' : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 dark:text-gray-200'" />
                </div>
                <div>
                  <label for="ocrinvoiceimport-line-discount-percent" class="text-[9px] text-gray-400 block">Rem%</label>
                  <input id="ocrinvoiceimport-line-discount-percent" v-model.number="line.discount_percent" type="number" min="0" max="100"
                    class="w-full px-1.5 py-0.5 text-xs text-right border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 dark:text-gray-200 rounded focus:ring-1 focus:ring-teal-500 focus:outline-none" />
                </div>
                <div>
                  <label for="ocrinvoiceimport-line-tax-percent" class="text-[9px] text-gray-400 block">TVA%</label>
                  <input id="ocrinvoiceimport-line-tax-percent" v-model.number="line.tax_percent" type="number" min="0" max="100"
                    class="w-full px-1.5 py-0.5 text-xs text-right border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 dark:text-gray-200 rounded focus:ring-1 focus:ring-teal-500 focus:outline-none" />
                </div>
                <div>
                  <label class="text-[9px] text-gray-400 block">Total</label>
                  <div class="px-1.5 py-0.5 text-xs text-right font-medium text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-900 rounded border border-transparent">
                    {{ fmt(lineHt(line)) }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Totals -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
          <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
            Totaux
          </h2>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-500">Total HT</span>
              <span class="font-medium text-gray-800 dark:text-gray-200">{{ fmt(computedTotalHt) }} MAD</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">TVA</span>
              <span class="font-medium text-gray-800 dark:text-gray-200">{{ fmt(computedTotalTax) }} MAD</span>
            </div>
            <div class="flex justify-between pt-1.5 border-t border-gray-200 dark:border-gray-700">
              <span class="text-gray-900 dark:text-white font-semibold">Total TTC</span>
              <span class="font-bold text-gray-900 dark:text-white">{{ fmt(computedTotalTtc) }} MAD</span>
            </div>
            <div v-if="ocrData?.totals?.total_ttc" class="text-[10px] text-gray-400 text-right">
              OCR: {{ fmt(ocrData.totals.total_ttc) }} MAD
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
          <label for="ocrinvoiceimport-notes" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
          <textarea
            id="ocrinvoiceimport-notes"
            v-model="form.notes"
            rows="2"
            class="w-full px-2.5 py-1.5 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 dark:text-gray-200 rounded-lg focus:ring-2 focus:ring-teal-500 focus:outline-none resize-none"
            :placeholder="form.invoice_number ? `Facture fournisseur ref: ${form.invoice_number}` : 'Notes...'"
          ></textarea>
        </div>

        <!-- Action buttons -->
        <div class="flex items-center justify-between py-2 sticky bottom-0 bg-gradient-to-t from-gray-50 dark:from-gray-900 pt-4">
          <button
            class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
            @click="resetAll"
          >Annuler</button>
          <button
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-teal-600 rounded-lg hover:bg-teal-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
            :disabled="confirming"
            @click="onConfirm"
          >
            <svg v-if="confirming" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            {{ confirming ? 'Creation...' : "Creer la facture" }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═════════════════════════════════════════════════════════════
         STEP 3: SUCCESS
         ═════════════════════════════════════════════════════════════ -->
    <div v-if="step === 'success'" class="max-w-lg mx-auto text-center py-12">
      <div class="w-16 h-16 mx-auto mb-4 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center">
        <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
      </div>
      <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Facture creee avec succes !</h2>
      <p class="text-gray-500 dark:text-gray-400 mb-1">
        Reference : <span class="font-medium text-gray-800 dark:text-gray-200">{{ createdDoc?.reference ?? '—' }}</span>
      </p>
      <p class="text-gray-500 dark:text-gray-400 mb-6">
        Fournisseur : {{ createdDoc?.third_partner?.tp_title ?? createdDoc?.thirdPartner?.tp_title ?? '—' }}
      </p>
      <div class="flex items-center justify-center gap-3">
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
          @click="resetAll"
        >Importer une autre</button>
        <router-link
          v-if="createdDoc?.id"
          :to="`/achats/documents/${createdDoc.id}`"
          class="px-4 py-2 text-sm font-semibold text-white bg-teal-600 rounded-lg hover:bg-teal-700 transition"
        >Voir le document</router-link>
      </div>
    </div>
  </div>
</template>
