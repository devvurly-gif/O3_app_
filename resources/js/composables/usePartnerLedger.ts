import { computed, toValue } from 'vue'
import type { MaybeRefOrGetter } from 'vue'

/**
 * Le compte d'un tiers, lu depuis sa fiche detaillee.
 *
 * Documents tries, reglements a plat, et les cumuls affiches en pied de
 * modale. Fournisseurs.vue en portait deux copies dans le meme fichier — une
 * pour la modale d'edition, une pour celle de consultation — et Clients.vue
 * une troisieme.
 *
 * Ce qui change d'un compte a l'autre n'est pas le calcul mais le perimetre :
 * quels documents pesent sur le solde. C'est le role de `isCountable` et de
 * `isDeductible`, laisses a l'appelant parce que la reponse est metier.
 */
export interface PartnerLedgerOptions {
  /** Un document qui pese sur le solde du tiers. */
  isCountable: (doc: any) => boolean
  /** Un document qui vient en deduction — un retour, typiquement. */
  isDeductible?: (doc: any) => boolean
}

function totalTtcOf(docs: any[]): number {
  return docs.reduce((sum, doc) => sum + Number(doc.footer?.total_ttc ?? 0), 0)
}

function amountDueOf(docs: any[]): number {
  return docs.reduce((sum, doc) => sum + Number(doc.footer?.amount_due ?? 0), 0)
}

/**
 * @param detail  la fiche renvoyee par `/third-partners/{id}`, ou null tant
 *                qu'elle n'est pas chargee.
 */
export function usePartnerLedger(detail: MaybeRefOrGetter<any>, options: PartnerLedgerOptions) {
  /** Du plus recent au plus ancien : c'est l'ordre d'affichage de l'historique. */
  const documents = computed<any[]>(() => {
    const headers = toValue(detail)?.document_headers
    if (!headers) return []
    return [...headers].sort((a: any, b: any) => new Date(b.issued_at).getTime() - new Date(a.issued_at).getTime())
  })

  /**
   * Les reglements de tous les documents, remis a plat et rattaches a la
   * reference de leur document d'origine.
   */
  const payments = computed<any[]>(() => {
    const headers = toValue(detail)?.document_headers
    if (!headers) return []
    const flat: any[] = []
    for (const doc of headers) {
      for (const payment of doc.payments ?? []) {
        flat.push({ ...payment, _doc_code: doc.reference })
      }
    }
    return flat.sort((a, b) => new Date(b.paid_at).getTime() - new Date(a.paid_at).getTime())
  })

  const countableDocuments = computed(() => documents.value.filter(options.isCountable))

  const deductibleDocuments = computed(() =>
    options.isDeductible ? documents.value.filter(options.isDeductible) : [],
  )

  const totalTtc = computed(() => totalTtcOf(countableDocuments.value) - totalTtcOf(deductibleDocuments.value))

  const totalDue = computed(() => amountDueOf(countableDocuments.value) - amountDueOf(deductibleDocuments.value))

  const totalPayments = computed(() => payments.value.reduce((sum, p) => sum + Number(p.amount ?? 0), 0))

  /**
   * Compte tous les documents qui laissent un reste a payer, sans filtre de
   * perimetre : c'est un indicateur de suivi, pas une ligne de solde.
   */
  const unpaidCount = computed(() => documents.value.filter((doc: any) => Number(doc.footer?.amount_due ?? 0) > 0).length)

  const paymentRate = computed(() => (totalTtc.value > 0 ? (totalPayments.value / totalTtc.value) * 100 : 0))

  return {
    documents,
    payments,
    countableDocuments,
    deductibleDocuments,
    totalTtc,
    totalDue,
    totalPayments,
    unpaidCount,
    paymentRate,
  }
}

/**
 * La jauge de credit : ou en est l'encours face au plafond accorde.
 *
 * Prend indifferemment le formulaire d'edition ou la ligne consultee — les
 * deux portent `seuil_credit` et `encours_actuel`.
 */
export function useCreditGauge(source: MaybeRefOrGetter<any>) {
  const limit = computed(() => Number(toValue(source)?.seuil_credit ?? 0))
  const used = computed(() => Number(toValue(source)?.encours_actuel ?? 0))

  const percent = computed(() => (limit.value <= 0 ? 0 : (used.value / limit.value) * 100))
  const available = computed(() => limit.value - used.value)

  return { percent, available }
}
