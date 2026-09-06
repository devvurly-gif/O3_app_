/**
 * Donnees de banc. Assez riches pour que chaque onglet des fiches tiers ait
 * quelque chose a montrer : des documents de tous types, des reglements de
 * plusieurs modes, un BL deja facture, un depassement de plafond.
 */

let paymentSeq = 900

const payment = (amount: number, paid_at: string, method: string, reference: string | null = null) => ({
  id: ++paymentSeq,
  amount,
  paid_at,
  method,
  reference,
  notes: null,
})

const footer = (total_ht: number, total_ttc: number, amount_due: number) => ({
  total_ht,
  total_tva: Number((total_ttc - total_ht).toFixed(2)),
  total_ttc,
  amount_due,
})

// ── Fournisseur ────────────────────────────────────────────────────────────
export const supplierRows = [
  {
    id: 1,
    tp_code: 'FRN-0001',
    tp_title: 'Sanitaire Atlas SARL',
    tp_Role: 'supplier',
    tp_status: true,
    tp_phone: '05 22 44 18 90',
    tp_email: 'contact@sanitaire-atlas.ma',
    tp_city: 'Casablanca',
    tp_address: '145, Zone industrielle Sidi Bernoussi',
    tp_Ice_Number: '001784523000041',
    tp_Rc_Number: '287451',
    tp_patente_Number: '31204587',
    tp_IdenFiscal: '14785236',
    encours_actuel: 103455,
    seuil_credit: 150000,
  },
  {
    id: 2,
    tp_code: 'FRN-0002',
    tp_title: 'Comptoir du Bois — Fes',
    tp_Role: 'supplier',
    tp_status: true,
    tp_phone: '05 35 62 11 07',
    tp_email: 'achats@comptoirdubois.ma',
    tp_city: 'Fes',
    tp_address: '8, Route de Sefrou',
    tp_Ice_Number: '002145789000023',
    tp_Rc_Number: '119874',
    tp_patente_Number: '20014789',
    tp_IdenFiscal: '20147859',
    // Au-dela du plafond : la carte « depassement » doit le compter.
    encours_actuel: 68000,
    seuil_credit: 50000,
  },
  {
    id: 3,
    tp_code: 'FRN-0003',
    tp_title: 'Quincaillerie El Menzeh',
    tp_Role: 'supplier',
    tp_status: false,
    tp_phone: '05 37 71 45 22',
    tp_email: null,
    tp_city: 'Rabat',
    tp_address: null,
    tp_Ice_Number: null,
    tp_Rc_Number: null,
    tp_patente_Number: null,
    tp_IdenFiscal: null,
    encours_actuel: 0,
    seuil_credit: 0,
  },
]

const supplierDocuments = [
  {
    id: 101,
    reference: 'FACA-2026-0087',
    document_type: 'InvoicePurchase',
    status: 'partial',
    issued_at: '2026-08-12',
    footer: footer(86212.5, 103455, 41455),
    payments: [payment(40000, '2026-08-20', 'bank_transfer', 'VIR-88421'), payment(22000, '2026-09-01', 'cheque', 'CHQ-004512')],
    children: [],
  },
  {
    id: 102,
    reference: 'BR-2026-0231',
    document_type: 'ReceiptNotePurchase',
    // Deja porte par la facture ci-dessus : il doit apparaitre grise, et
    // rester hors du cumul.
    status: 'converted',
    issued_at: '2026-08-10',
    footer: footer(86212.5, 103455, 0),
    payments: [],
    children: [{ id: 101, document_type: 'InvoicePurchase', reference: 'FACA-2026-0087' }],
  },
  {
    id: 103,
    reference: 'RET-2026-0009',
    document_type: 'ReturnPurchase',
    status: 'confirmed',
    issued_at: '2026-08-25',
    footer: footer(2500, 3000, 3000),
    payments: [],
    children: [],
  },
  {
    id: 104,
    reference: 'BCA-2026-0140',
    document_type: 'PurchaseOrder',
    status: 'draft',
    issued_at: '2026-09-02',
    footer: footer(12000, 14400, 14400),
    payments: [],
    children: [],
  },
  {
    id: 105,
    reference: 'FACA-2026-0061',
    document_type: 'InvoicePurchase',
    status: 'paid',
    issued_at: '2026-06-30',
    footer: footer(20000, 24000, 0),
    payments: [payment(24000, '2026-07-05', 'cash')],
    children: [],
  },
]

// ── Client ─────────────────────────────────────────────────────────────────
export const customerRows = [
  {
    id: 11,
    tp_code: 'CLI-0011',
    tp_title: 'Residence Al Boustane',
    tp_Role: 'customer',
    tp_status: true,
    tp_phone: '06 61 23 45 67',
    tp_email: 'syndic@alboustane.ma',
    tp_city: 'Marrakech',
    tp_address: '22, Avenue Mohammed VI',
    tp_Ice_Number: '003214569000018',
    tp_Rc_Number: '445120',
    tp_patente_Number: '40021458',
    tp_IdenFiscal: '30125478',
    encours_actuel: 18400,
    seuil_credit: 40000,
    type_compte: 'en_compte',
    frequence_facturation: 'mensuelle',
    price_list_id: 2,
  },
  {
    id: 12,
    tp_code: 'CLI-0012',
    tp_title: 'Cafe Vue sur Mer',
    tp_Role: 'customer',
    tp_status: true,
    tp_phone: '06 12 98 76 54',
    tp_email: 'gerance@vuesurmer.ma',
    tp_city: 'Essaouira',
    tp_address: '3, Boulevard Mohammed V',
    tp_Ice_Number: '004785412000037',
    tp_Rc_Number: '221004',
    tp_patente_Number: '50014785',
    tp_IdenFiscal: '40125896',
    encours_actuel: 9600,
    seuil_credit: 5000,
    type_compte: 'normal',
    frequence_facturation: null,
    price_list_id: null,
  },
  {
    id: 13,
    tp_code: 'CLI-0013',
    tp_title: 'Nouveau compte — sans historique',
    tp_Role: 'customer',
    tp_status: true,
    tp_phone: null,
    tp_email: null,
    tp_city: 'Tanger',
    tp_address: null,
    tp_Ice_Number: null,
    tp_Rc_Number: null,
    tp_patente_Number: null,
    tp_IdenFiscal: null,
    encours_actuel: 0,
    seuil_credit: 0,
    type_compte: 'normal',
    frequence_facturation: null,
    price_list_id: null,
  },
]

const customerDocuments = [
  {
    id: 201,
    reference: 'FAC-2026-0412',
    document_type: 'InvoiceSale',
    status: 'partial',
    issued_at: '2026-08-18',
    footer: footer(12000, 14400, 4400),
    payments: [payment(10000, '2026-08-22', 'bank_transfer', 'VIR-55120')],
    children: [],
  },
  {
    id: 202,
    reference: 'BL-2026-0908',
    document_type: 'DeliveryNote',
    status: 'confirmed',
    issued_at: '2026-09-01',
    footer: footer(3333.33, 4000, 4000),
    payments: [],
    children: [],
  },
  {
    id: 203,
    reference: 'BL-2026-0855',
    document_type: 'DeliveryNote',
    // Converti en facture : grise dans la liste, hors cumul.
    status: 'converted',
    issued_at: '2026-08-15',
    footer: footer(12000, 14400, 0),
    payments: [],
    children: [{ id: 201, document_type: 'InvoiceSale', reference: 'FAC-2026-0412' }],
  },
  {
    id: 204,
    reference: 'DEV-2026-0077',
    document_type: 'QuoteSale',
    status: 'draft',
    issued_at: '2026-07-20',
    footer: footer(50000, 60000, 60000),
    payments: [],
    children: [],
  },
  {
    id: 205,
    reference: 'AV-2026-0004',
    document_type: 'CreditNoteSale',
    status: 'confirmed',
    issued_at: '2026-08-28',
    footer: footer(500, 600, 0),
    payments: [payment(600, '2026-08-29', 'credit')],
    children: [],
  },
]

export const priceLists = [
  { id: 1, name: 'Tarif public', is_default: true },
  { id: 2, name: 'Tarif syndic', is_default: false },
  { id: 3, name: 'Tarif revendeur', is_default: false },
]

export const settings = {
  display: { price_decimals: '2' },
  locale: { date_format: 'd/m/Y' },
  ventes: { paiement_sur_bl: 'true' },
}

/**
 * La fiche detaillee que renvoie `/third-partners/{id}`.
 *
 * Chaque tiers porte volontairement un cas different : le reglement groupe
 * n'affiche pas la meme chose selon qu'il reste quelque chose a solder, que
 * tout est solde, ou qu'il n'y a aucun document — et c'est precisement la que
 * les fiches client et fournisseur divergent.
 */
const soldes = (docs: typeof customerDocuments) =>
  docs.map((d) => ({ ...d, footer: { ...d.footer, amount_due: 0 }, status: 'paid' }))

const detailById: Record<number, unknown[]> = {
  1: supplierDocuments,
  2: supplierDocuments,
  3: [], // aucun document : etat vide
  11: customerDocuments,
  12: soldes(customerDocuments), // tout solde : repli sur le reglement a l'unite
  13: [], // aucun document
}

export function partnerDetail(id: number) {
  const row = [...supplierRows, ...customerRows].find((r) => r.id === id)
  if (!row) return null
  const document_headers = (detailById[id] ?? []) as Record<string, unknown>[]
  return { ...row, document_headers: document_headers.map((d) => ({ ...d })) }
}

export function partnerPage(role: string, search = '', status = '') {
  let rows = (role === 'supplier' ? supplierRows : customerRows).slice()
  if (search) {
    const needle = search.toLowerCase()
    rows = rows.filter(
      (r) => r.tp_title.toLowerCase().includes(needle) || (r.tp_code ?? '').toLowerCase().includes(needle),
    )
  }
  if (status !== '') {
    const wanted = status === '1' || status === 'true'
    rows = rows.filter((r) => Boolean(r.tp_status) === wanted)
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
