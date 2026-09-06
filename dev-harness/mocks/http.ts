import { partnerDetail, partnerPage, priceLists, settings } from '../fixtures'

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
  // eslint-disable-next-line no-console
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

const http = {
  async get(url: string, config?: { params?: Params }) {
    const path = pathOf(url)
    const query = queryOf(url, config?.params)
    // eslint-disable-next-line no-console
    console.debug('[banc] GET', path, query)

    if (path === '/settings') return respond(settings)
    if (path === '/price-lists') return respond(priceLists)
    if (path === '/third-partners') {
      return respond(partnerPage(query.role ?? 'supplier', query.search ?? '', query.status ?? ''))
    }

    const partner = path.match(/^\/third-partners\/(\d+)$/)
    if (partner) return respond(partnerDetail(Number(partner[1])))

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
    // eslint-disable-next-line no-console
    console.debug('[banc] POST', url, body)
    if (url.endsWith('/bulk-payment')) {
      return respond({ message: 'Reglement enregistre (banc).', allocated: [], remaining: 0 })
    }
    return respond({ ...(body as object), id: Math.floor(Math.random() * 1000) + 500 })
  },

  async put(url: string, body?: unknown) {
    // eslint-disable-next-line no-console
    console.debug('[banc] PUT', url, body)
    return respond({ ...(body as object) })
  },

  async delete(url: string) {
    // eslint-disable-next-line no-console
    console.debug('[banc] DELETE', url)
    return respond({})
  },
}

export default http
