import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { usePartnerLedger, useCreditGauge } from './usePartnerLedger'

/**
 * Les valeurs attendues ici ont ete relevees sur l'implementation d'avant
 * extraction — celle que Clients.vue et Fournisseurs.vue portaient en trois
 * exemplaires. Elles font foi : si un de ces calculs change, c'est un solde
 * client ou fournisseur qui change a l'ecran.
 */

const doc = (
  id: number,
  document_type: string,
  status: string,
  issued_at: string,
  total_ttc: number | string,
  amount_due: number | string,
  payments: any[] = [],
) => ({ id, document_type, status, issued_at, reference: `REF-${id}`, footer: { total_ttc, amount_due }, payments })

const isPurchase = (d: any) =>
  d.document_type === 'InvoicePurchase' && d.status !== 'cancelled' && d.status !== 'draft'

const isReturn = (d: any) =>
  d.document_type === 'ReturnPurchase' && d.status !== 'cancelled' && d.status !== 'draft'

const purchaseScope = { isCountable: isPurchase, isDeductible: isReturn }

describe('usePartnerLedger', () => {
  it('classe les documents du plus recent au plus ancien', () => {
    const detail = ref({
      document_headers: [
        doc(1, 'InvoicePurchase', 'confirmed', '2026-01-10', 100, 0),
        doc(2, 'InvoicePurchase', 'confirmed', '2026-03-02', 200, 0),
        doc(3, 'InvoicePurchase', 'confirmed', '2026-02-01', 300, 0),
      ],
    })
    const { documents } = usePartnerLedger(detail, purchaseScope)
    expect(documents.value.map((d: any) => d.id)).toEqual([2, 3, 1])
  })

  it('ne trie pas la source en place', () => {
    // Le tri portait autrefois sur `document_headers` lui-meme, depuis
    // l'interieur d'un computed : il reordonnait le tableau du store.
    const headers = [
      doc(1, 'InvoicePurchase', 'confirmed', '2026-01-10', 100, 0),
      doc(2, 'InvoicePurchase', 'confirmed', '2026-03-02', 200, 0),
    ]
    const detail = ref({ document_headers: headers })
    const { documents } = usePartnerLedger(detail, purchaseScope)
    expect(documents.value.map((d: any) => d.id)).toEqual([2, 1])
    expect(headers.map((d) => d.id)).toEqual([1, 2])
  })

  it('met les reglements a plat et les rattache a leur document', () => {
    const detail = ref({
      document_headers: [
        doc(1, 'InvoicePurchase', 'paid', '2026-01-10', 100, 0, [
          { amount: 60, paid_at: '2026-01-12' },
          { amount: 40, paid_at: '2026-01-20' },
        ]),
        doc(2, 'InvoicePurchase', 'paid', '2026-02-10', 50, 0, [{ amount: 50, paid_at: '2026-02-15' }]),
      ],
    })
    const { payments, totalPayments } = usePartnerLedger(detail, purchaseScope)
    expect(payments.value.map((p: any) => p.paid_at)).toEqual(['2026-02-15', '2026-01-20', '2026-01-12'])
    expect(payments.value.map((p: any) => p._doc_code)).toEqual(['REF-2', 'REF-1', 'REF-1'])
    expect(totalPayments.value).toBe(150)
  })

  it('deduit les retours des cumuls, et ecarte annules et brouillons', () => {
    const detail = ref({
      document_headers: [
        doc(1, 'InvoicePurchase', 'confirmed', '2026-01-10', 1000, 400),
        doc(2, 'InvoicePurchase', 'paid', '2026-03-02', 2500, 0),
        doc(3, 'ReturnPurchase', 'confirmed', '2026-02-20', 300, 100),
        doc(4, 'ReceiptNotePurchase', 'converted', '2026-01-08', 1000, 0),
        doc(5, 'InvoicePurchase', 'cancelled', '2026-04-01', 999, 999),
        doc(6, 'InvoicePurchase', 'draft', '2026-04-05', 888, 888),
      ],
    })
    const { totalTtc, totalDue, countableDocuments } = usePartnerLedger(detail, purchaseScope)
    expect(countableDocuments.value.map((d: any) => d.id)).toEqual([2, 1])
    expect(totalTtc.value).toBe(3200) // 1000 + 2500 − 300
    expect(totalDue.value).toBe(300) // 400 + 0 − 100
  })

  it('compte les impayes sans filtre de perimetre', () => {
    // Indicateur de suivi : un bon de reception non solde y figure, alors
    // qu'il ne pese pas sur la dette.
    const detail = ref({
      document_headers: [
        doc(1, 'InvoicePurchase', 'confirmed', '2026-01-10', 1000, 400),
        doc(2, 'ReceiptNotePurchase', 'confirmed', '2026-01-11', 700, 700),
        doc(3, 'InvoicePurchase', 'paid', '2026-01-12', 500, 0),
      ],
    })
    const { unpaidCount } = usePartnerLedger(detail, purchaseScope)
    expect(unpaidCount.value).toBe(2)
  })

  it('rapporte le taux de reglement au TTC comptable', () => {
    const detail = ref({
      document_headers: [
        doc(1, 'InvoicePurchase', 'partial', '2026-01-10', 1000, 250, [{ amount: 750, paid_at: '2026-01-11' }]),
      ],
    })
    const { paymentRate } = usePartnerLedger(detail, purchaseScope)
    expect(paymentRate.value).toBe(75)
  })

  it('rend zero plutot que NaN quand rien n est comptable', () => {
    const detail = ref({ document_headers: [doc(1, 'ReceiptNotePurchase', 'confirmed', '2026-01-10', 700, 700)] })
    const { totalTtc, paymentRate } = usePartnerLedger(detail, purchaseScope)
    expect(totalTtc.value).toBe(0)
    expect(paymentRate.value).toBe(0)
  })

  it('supporte une fiche vide, absente, ou sans pied de document', () => {
    for (const detail of [ref(null), ref({}), ref({ document_headers: [] })]) {
      const { documents, payments, totalTtc, totalDue, unpaidCount } = usePartnerLedger(detail, purchaseScope)
      expect(documents.value).toEqual([])
      expect(payments.value).toEqual([])
      expect(totalTtc.value).toBe(0)
      expect(totalDue.value).toBe(0)
      expect(unpaidCount.value).toBe(0)
    }

    const noFooter = ref({
      document_headers: [{ id: 1, document_type: 'InvoicePurchase', status: 'confirmed', issued_at: '2026-01-10' }],
    })
    expect(usePartnerLedger(noFooter, purchaseScope).totalTtc.value).toBe(0)
  })

  it('accepte des montants renvoyes en chaine par l API', () => {
    const detail = ref({
      document_headers: [doc(1, 'InvoicePurchase', 'confirmed', '2026-01-10', '1500.50', '500.25')],
    })
    const { totalTtc, totalDue } = usePartnerLedger(detail, purchaseScope)
    expect(totalTtc.value).toBe(1500.5)
    expect(totalDue.value).toBe(500.25)
  })

  it('suit la regle de vente : le BL ne compte ni converti ni deja facture', () => {
    const bl = (id: number, status: string, children: any[] = []) => ({
      ...doc(id, 'DeliveryNote', status, `2026-01-0${id}`, 400, 400),
      children,
    })
    const headers = [
      doc(1, 'InvoiceSale', 'confirmed', '2026-01-05', 1200, 200),
      bl(2, 'confirmed'),
      bl(3, 'converted'),
      bl(4, 'confirmed', [{ document_type: 'InvoiceSale' }]),
    ]
    const isBilledBl = (d: any) =>
      d.document_type === 'DeliveryNote' &&
      (d.status === 'converted' || (d.children ?? []).some((c: any) => c.document_type === 'InvoiceSale'))

    const saleScope = (paiementSurBl: boolean) => ({
      isCountable: (d: any) =>
        (paiementSurBl ? ['InvoiceSale', 'DeliveryNote'] : ['InvoiceSale']).includes(d.document_type) &&
        d.status !== 'cancelled' &&
        d.status !== 'draft' &&
        !isBilledBl(d),
    })

    const sansBl = usePartnerLedger(ref({ document_headers: headers }), saleScope(false))
    expect(sansBl.countableDocuments.value.map((d: any) => d.id)).toEqual([1])
    expect(sansBl.totalTtc.value).toBe(1200)

    // Le BL converti et celui deja porte par une facture restent exclus, sans
    // quoi le bon et sa facture compteraient deux fois.
    const avecBl = usePartnerLedger(ref({ document_headers: headers }), saleScope(true))
    expect(avecBl.countableDocuments.value.map((d: any) => d.id)).toEqual([1, 2])
    expect(avecBl.totalTtc.value).toBe(1600)
  })
})

describe('useCreditGauge', () => {
  it('rapporte l encours au plafond', () => {
    const { percent, available } = useCreditGauge(() => ({ seuil_credit: 10000, encours_actuel: 2500 }))
    expect(percent.value).toBe(25)
    expect(available.value).toBe(7500)
  })

  it('rend zero pour un plafond nul, sans division', () => {
    const { percent, available } = useCreditGauge(() => ({ seuil_credit: 0, encours_actuel: 500 }))
    expect(percent.value).toBe(0)
    expect(available.value).toBe(-500)
  })

  it('signale le depassement par un disponible negatif', () => {
    const { percent, available } = useCreditGauge(() => ({ seuil_credit: 1000, encours_actuel: 1500 }))
    expect(percent.value).toBe(150)
    expect(available.value).toBe(-500)
  })

  it('traite une fiche vide comme un plafond nul', () => {
    for (const source of [{}, { seuil_credit: null, encours_actuel: null }, null]) {
      const { percent, available } = useCreditGauge(() => source)
      expect(percent.value).toBe(0)
      expect(available.value).toBe(0)
    }
  })
})
