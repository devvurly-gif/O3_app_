import { partnerDetail, partnerPage, priceLists, settings } from '../fixtures'
import {
  brands,
  categories,
  productDetail,
  productPage,
  productPriceTiers,
  productStatistics,
  productVariants,
  stockMovements,
  taxSettings,
  variantOptions,
} from '../fixtures-products'

/**
 * Remplace `@/services/http` sur le banc, par alias Vite.
 *
 * Meme surface que l'instance axios : `get`/`post`/`put`/`delete` rendent une
 * promesse d'objet `{ data }`. Les ecrans ne font pas la difference.
 */

type Params = Record<string, unknown> | undefined

/*
 * Resolution sur microtache, sans temporisation : un onglet en arriere-plan
 * voit ses `setTimeout` brides a la seconde, et le banc est pilote par script.
 * Les etats de chargement ne sont pas ce qu'on vient y regarder.
 */
const delay = () => Promise.resolve()

async function respond<T>(data: T): Promise<{ data: T }> {
  await delay()

  console.debug('[banc] ->', data)
  return { data }
}

function pathOf(url: string): string {
  return url.split('?')[0]
}

function queryOf(url: string, params: Params): Record<string, string> {
  const out: Record<string, string> = {}
  const qs = url.includes('?') ? url.slice(url.indexOf('?') + 1) : ''
  for (const [k, v] of new URLSearchParams(qs)) out[k] = v
  for (const [k, v] of Object.entries(params ?? {})) {
    if (v !== null && v !== undefined && v !== '') out[k] = String(v)
  }
  return out
}

/**
 * Chiffrage de la revision des prix, rejoue cote banc.
 *
 * Le calcul reprend celui de BulkSalePriceUpdater sur les produits de la
 * fixture : l'ecran montre un tableau credible plutot qu'une reponse figee.
 */
interface BulkPriceRequest {
  category_ids?: number[]
  brand_ids?: number[]
  status?: string
  search?: string | null
  mode: 'percent' | 'amount' | 'margin' | 'set'
  value: number
  basis?: 'sale' | 'purchase' | 'cost'
  rounding?: string
  expected_count?: number
}

function bulkPriceRound(price: number, rounding: string): number {
  const step: Record<string, number> = { '0.05': 0.05, '0.10': 0.1, '0.50': 0.5, '1': 1, '5': 5, '10': 10 }
  let out = price
  if (step[rounding]) out = Math.round(price / step[rounding]) * step[rounding]
  else if (rounding === 'end_90') out = Math.round(price) - 0.1
  else if (rounding === 'end_99') out = Math.round(price) - 0.01
  if (price >= 0 && out < 0) out = 0
  return Math.round(out * 100) / 100
}

/** (prix − achat) ÷ base, en %. Meme regle que BulkSalePriceUpdater. */
function bulkPriceMargin(price: number, purchase: number, base: number): number | null {
  if (purchase <= 0 || base <= 0) return null
  return Math.round(((price - purchase) / base) * 1000) / 10
}

function bulkPriceResponse(url: string, body: BulkPriceRequest) {
  const rows = (productPage.data as Array<Record<string, unknown>>).filter((p) => {
    if (body.category_ids?.length && !body.category_ids.includes(Number(p.category_id))) return false
    if (body.brand_ids?.length && !body.brand_ids.includes(Number(p.brand_id))) return false
    if (body.status === 'active' && !p.p_status) return false
    if (body.status === 'inactive' && p.p_status) return false
    if (body.search && !String(p.p_title).toLowerCase().includes(body.search.toLowerCase())) return false
    // Le stock positif n'est pas negociable, comme cote serveur. Le banc n'a
    // pas de lignes de depot : `total_stock` de la fixture tient lieu de somme.
    if (Number(p.total_stock ?? 0) <= 0) return false
    return true
  })

  let changed = 0
  let skipped = 0
  let negative = 0
  let belowPurchase = 0
  const sample: Array<Record<string, unknown>> = []

  for (const p of rows) {
    const current = Math.round(Number(p.p_salePrice) * 100) / 100

    // Meme resolution que BulkSalePriceUpdater : `margin` est l'alias de
    // « pourcentage applique au prix d'achat ».
    const mode = body.mode === 'margin' ? 'percent' : body.mode
    const basisKey = body.basis ?? (body.mode === 'margin' ? 'purchase' : 'sale')
    const base = Number(basisKey === 'purchase' ? p.p_purchasePrice : basisKey === 'cost' ? p.p_cost : p.p_salePrice)

    let raw: number | null
    if (mode === 'set') raw = body.value
    else if (basisKey !== 'sale' && base <= 0) raw = null
    else raw = mode === 'percent' ? base * (1 + body.value / 100) : base + body.value

    if (raw === null) {
      skipped++
      continue
    }

    const purchase = Math.round(Number(p.p_purchasePrice) * 100) / 100
    const next = bulkPriceRound(raw, body.rounding ?? 'none')
    if (next < 0) negative++
    if (purchase > 0 && next < purchase) belowPurchase++
    if (next === current) continue

    changed++
    if (sample.length < 50) {
      sample.push({
        id: p.id,
        p_code: p.p_code,
        p_title: p.p_title,
        stock: Number(p.total_stock ?? 0),
        current,
        new: next,
        delta: Math.round((next - current) * 100) / 100,
        purchase,
        cost: Math.round(Number(p.p_cost) * 100) / 100,
        margin: bulkPriceMargin(next, purchase, purchase),
        margin_before: bulkPriceMargin(current, purchase, purchase),
        margin_sale: bulkPriceMargin(next, purchase, next),
        margin_sale_before: bulkPriceMargin(current, purchase, current),
      })
    }
  }

  if (url.endsWith('/apply')) {
    return { message: changed + ' prix de vente mis a jour (banc).', updated: changed, matched: rows.length }
  }

  return {
    matched: rows.length,
    changed,
    unchanged: rows.length - changed - skipped,
    skipped_no_basis: skipped,
    negative,
    below_purchase: belowPurchase,
    costs_visible: true,
    sample,
    max_products: 5000,
  }
}

const http = {
  async get(url: string, config?: { params?: Params }) {
    const path = pathOf(url)
    const query = queryOf(url, config?.params)

    console.debug('[banc] GET', path, query)

    if (path === '/settings') return respond(settings)
    if (path === '/price-lists') return respond(priceLists)
    if (path === '/third-partners') {
      return respond(partnerPage(query.role ?? 'supplier', query.search ?? '', query.status ?? ''))
    }

    const partner = path.match(/^\/third-partners\/(\d+)$/)
    if (partner) return respond(partnerDetail(Number(partner[1])))

    // ── Produits ──────────────────────────────────────────────────────────
    if (path === '/categories') return respond(categories)
    if (path === '/brands') return respond(brands)
    if (path === '/variant-options') return respond(variantOptions)
    if (path === '/tax-settings') return respond(taxSettings)
    if (path === '/products') return respond(productPage(query))

    const productSub = path.match(/^\/products\/(\d+)\/(.+)$/)
    if (productSub) {
      const [, , sub] = productSub
      if (sub === 'statistics') return respond(productStatistics)
      if (sub === 'stock-history') return respond({ data: stockMovements })
      if (sub === 'price-lists') return respond(productPriceTiers)
      if (sub === 'variants') return respond(productVariants)
      return respond([])
    }

    const product = path.match(/^\/products\/(\d+)$/)
    if (product) return respond(productDetail(Number(product[1])))

    const document = path.match(/^\/documents\/(\d+)$/)
    if (document) {
      const id = Number(document[1])
      const detail = partnerDetail(11) ?? partnerDetail(1)
      const found = detail?.document_headers.find((d: any) => d.id === id)
      return respond({ ...found, lignes: [] })
    }

    return respond({})
  },

  async post(url: string, body?: unknown) {
    console.debug('[banc] POST', url, body)
    if (url === '/products/bulk-price/preview' || url === '/products/bulk-price/apply') {
      return respond(bulkPriceResponse(url, body as BulkPriceRequest))
    }
    if (url.endsWith('/bulk-payment')) {
      return respond({ message: 'Reglement enregistre (banc).', allocated: [], remaining: 0 })
    }
    return respond({ ...(body as object), id: Math.floor(Math.random() * 1000) + 500 })
  },

  async put(url: string, body?: unknown) {
    console.debug('[banc] PUT', url, body)
    return respond({ ...(body as object) })
  },

  async delete(url: string) {
    console.debug('[banc] DELETE', url)
    return respond({})
  },
}

export default http
