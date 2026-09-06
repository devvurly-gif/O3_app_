/**
 * Donnees de banc pour l'ecran Produits.
 *
 * Comme pour les fiches tiers, les montants partent en chaine : les colonnes
 * monetaires de Product sont castees `decimal:2` cote Laravel, et Eloquent
 * serialise un decimal en chaine JSON.
 *
 * Chaque produit porte volontairement un cas different — un en rupture, un
 * inactif, un sans image, un publie sur la boutique — pour que les pastilles
 * de statut et les cartes de tete aient tous leurs cas a rendre.
 */

const decimal = (n: number) => n.toFixed(2)

export const categories = [
  { id: 1, ctg_title: 'Sanitaire', ctg_status: true },
  { id: 2, ctg_title: 'Électricité', ctg_status: true },
  { id: 3, ctg_title: 'Outillage', ctg_status: true },
]

export const brands = [
  { id: 1, brd_title: 'Grohe', brd_status: true },
  { id: 2, brd_title: 'Legrand', brd_status: true },
]

export const priceLists = [
  { id: 1, name: 'Tarif public', is_default: true },
  { id: 2, name: 'Tarif revendeur', is_default: false },
  { id: 3, name: 'Tarif chantier', is_default: false },
]

export const variantOptions = [
  { id: 1, name: 'Couleur', values: [{ key: 'Blanc' }, { key: 'Chrome' }] },
  { id: 2, name: 'Taille', values: [{ key: '1/2"' }, { key: '3/4"' }] },
]

export const taxSettings = { default_rate: 20, rates: [0, 7, 10, 14, 20] }

const image = (id: number, name: string, isPrimary = false) => ({
  id,
  url: `/storage/products/${name}.jpg`,
  isPrimary,
})

const warehouseStock = (id: number, warehouse: string, qty: number) => ({
  id,
  warehouse_id: id,
  quantity: qty,
  warehouse: { id, wh_title: warehouse },
})

const produit = (o: Record<string, unknown>) => ({
  p_code: null,
  p_sku: null,
  p_ean13: null,
  p_imei: null,
  p_cost: decimal(0),
  p_taxRate: decimal(20),
  p_unit: 'pièce',
  p_description: null,
  p_long_description: null,
  p_notes: null,
  p_slug: null,
  p_status: true,
  is_ecom: false,
  category_id: null,
  brand_id: null,
  images: [],
  videos: [],
  documents: [],
  warehouse_stocks: [],
  created_at: '2026-05-12T09:00:00.000000Z',
  updated_at: '2026-08-30T14:22:00.000000Z',
  ...o,
})

export const productRows = [
  produit({
    id: 101,
    p_title: 'Mitigeur lavabo chromé',
    p_code: 'ART-0101',
    p_sku: 'GRO-32842',
    p_ean13: '4005176312847',
    p_purchasePrice: decimal(420),
    p_salePrice: decimal(690),
    p_cost: decimal(445),
    category_id: 1,
    brand_id: 1,
    category: categories[0],
    brand: brands[0],
    total_stock: 34,
    is_ecom: true,
    p_slug: 'mitigeur-lavabo-chrome',
    p_description: 'Mitigeur monocommande pour lavabo, finition chromée.',
    images: [image(1, 'mitigeur-1', true), image(2, 'mitigeur-2')],
    warehouse_stocks: [warehouseStock(1, 'Dépôt principal', 26), warehouseStock(2, 'Magasin', 8)],
  }),
  produit({
    id: 102,
    p_title: 'Disjoncteur 16A courbe C',
    p_code: 'ART-0102',
    p_sku: 'LEG-403416',
    p_purchasePrice: decimal(48),
    p_salePrice: decimal(79.5),
    category_id: 2,
    brand_id: 2,
    category: categories[1],
    brand: brands[1],
    // En rupture : la pastille doit virer au rouge et la carte « rupture » compter.
    total_stock: 0,
    warehouse_stocks: [warehouseStock(1, 'Dépôt principal', 0)],
  }),
  produit({
    id: 103,
    p_title: 'Clé à molette 250 mm',
    p_code: 'ART-0103',
    p_purchasePrice: decimal(65),
    p_salePrice: decimal(110),
    category_id: 3,
    category: categories[2],
    // Inactif, et sans image ni marque : cas le plus depouille.
    p_status: false,
    total_stock: 12,
  }),
]

export const productStatistics = {
  total_sold: 148,
  total_revenue: decimal(102120),
  total_purchased: 180,
  avg_sale_price: decimal(690),
  last_sale_at: '2026-09-02',
  last_purchase_at: '2026-08-11',
}

export const stockMovements = [
  {
    id: 9001,
    type: 'in',
    quantity: 40,
    reference: 'BR-2026-0231',
    unit_cost: decimal(420),
    created_at: '2026-08-10T10:12:00.000000Z',
    warehouse: { wh_title: 'Dépôt principal' },
  },
  {
    id: 9002,
    type: 'out',
    quantity: 6,
    reference: 'BL-2026-0908',
    unit_cost: decimal(0),
    created_at: '2026-09-01T16:40:00.000000Z',
    warehouse: { wh_title: 'Magasin' },
  },
]

export const productPriceTiers = [
  { id: 501, price_list_id: 2, min_qty: 1, price_ht: decimal(620), price_list: priceLists[1] },
  { id: 502, price_list_id: 2, min_qty: 10, price_ht: decimal(585), price_list: priceLists[1] },
  { id: 503, price_list_id: 3, min_qty: 50, price_ht: decimal(540), price_list: priceLists[2] },
]

export const productVariants = [
  { id: 701, label: 'Blanc / 1/2"', sku: 'GRO-32842-B12', price: decimal(690), is_active: true },
  { id: 702, label: 'Chrome / 3/4"', sku: 'GRO-32842-C34', price: decimal(720), is_active: true },
]

export function productDetail(id: number) {
  const row = productRows.find((p) => p.id === id)
  if (!row) return null
  return { ...row }
}

export function productPage(params: Record<string, string>) {
  let rows = productRows.slice()
  if (params.search) {
    const needle = params.search.toLowerCase()
    rows = rows.filter(
      (p) =>
        String(p.p_title).toLowerCase().includes(needle) ||
        String(p.p_code ?? '').toLowerCase().includes(needle) ||
        String(p.p_sku ?? '').toLowerCase().includes(needle),
    )
  }
  if (params.status !== undefined && params.status !== '') {
    const wanted = params.status === '1' || params.status === 'true'
    rows = rows.filter((p) => Boolean(p.p_status) === wanted)
  }
  if (params.in_stock !== undefined && params.in_stock !== '') {
    const wanted = params.in_stock === '1' || params.in_stock === 'true'
    rows = rows.filter((p) => Number(p.total_stock ?? 0) > 0 === wanted)
  }
  if (params.is_ecom !== undefined && params.is_ecom !== '') {
    const wanted = params.is_ecom === '1' || params.is_ecom === 'true'
    rows = rows.filter((p) => Boolean(p.is_ecom) === wanted)
  }
  return {
    data: rows,
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: rows.length,
    from: rows.length ? 1 : null,
    to: rows.length || null,
  }
}
