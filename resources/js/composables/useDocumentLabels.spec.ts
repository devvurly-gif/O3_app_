import { describe, expect, it } from 'vitest'
import {
  docTypeShortLabel,
  partnerDocTypeBadgeClass,
  partnerDocTypeLabel,
  partnerStatusBadgeClass,
  partnerStatusLabel,
  paymentMethodLabel,
} from './useDocumentLabels'

/**
 * Ces libelles servent l'historique des fiches tiers — clients, fournisseurs,
 * et la modale de consultation partagee. Ils ont deja diverge une fois : la
 * modale gardait une copie ou un devis, un bon de reception et un retour
 * s'affichaient avec leur code brut.
 */

describe('partnerDocTypeLabel', () => {
  it('abrege les types, comme le veut la colonne etroite', () => {
    expect(partnerDocTypeLabel('DeliveryNote')).toBe('BL')
    expect(partnerDocTypeLabel('ReceiptNotePurchase')).toBe('BR')
    expect(partnerDocTypeLabel('InvoiceSale')).toBe('Facture')
    expect(partnerDocTypeLabel('InvoicePurchase')).toBe('Facture achat')
  })

  it('couvre la vente et l achat, y compris devis, avoirs et retours', () => {
    // Le devis passait autrefois sous la cle morte `Quote`, renommee
    // `QuoteSale` par la migration harmonize_document_type_values.
    expect(partnerDocTypeLabel('QuoteSale')).toBe('Devis')
    expect(partnerDocTypeLabel('CustomerOrder')).toBe('Commande')
    expect(partnerDocTypeLabel('PurchaseOrder')).toBe('Commande')
    expect(partnerDocTypeLabel('CreditNoteSale')).toBe('Avoir')
    expect(partnerDocTypeLabel('CreditNotePurchase')).toBe('Avoir achat')
    expect(partnerDocTypeLabel('ReturnSale')).toBe('Retour')
    expect(partnerDocTypeLabel('ReturnPurchase')).toBe('Retour achat')
  })

  it('rend le code tel quel pour un type inconnu', () => {
    expect(partnerDocTypeLabel('Quote')).toBe('Quote')
    expect(partnerDocTypeLabel('Inexistant')).toBe('Inexistant')
  })
})

describe('partnerStatusLabel', () => {
  it('traduit les etats courants', () => {
    expect(partnerStatusLabel('paid')).toBe('Payé')
    expect(partnerStatusLabel('partial')).toBe('Partiel')
    expect(partnerStatusLabel('pending')).toBe('En attente')
    expect(partnerStatusLabel('confirmed')).toBe('Confirmé')
    expect(partnerStatusLabel('draft')).toBe('Brouillon')
    expect(partnerStatusLabel('cancelled')).toBe('Annulé')
  })

  it('lit « confirmed » comme « Livré » sur un bon de livraison', () => {
    // Sur un BL, confirmed veut dire que la marchandise est partie, pas
    // qu'elle est payee.
    expect(partnerStatusLabel('confirmed', 'DeliveryNote')).toBe('Livré')
    expect(partnerStatusLabel('confirmed', 'InvoiceSale')).toBe('Confirmé')
    expect(partnerStatusLabel('paid', 'DeliveryNote')).toBe('Payé')
  })

  it('rend l etat brut plutot qu un libelle fourre-tout', () => {
    // La modale de consultation repliait autrefois tout le reste sur
    // « Ouvert », ce qui masquait notamment « Partiel ».
    expect(partnerStatusLabel('converted')).toBe('converted')
    expect(partnerStatusLabel('inconnu')).toBe('inconnu')
  })
})

describe('les pastilles', () => {
  it('donne une classe par type, et un gris par defaut', () => {
    expect(partnerDocTypeBadgeClass('InvoiceSale')).toBe('bg-[#F1ECFC] text-[#6D4CE0]')
    expect(partnerDocTypeBadgeClass('DeliveryNote')).toBe('bg-emerald-100 text-emerald-700')
    expect(partnerDocTypeBadgeClass('Inexistant')).toBe('bg-gray-100 text-gray-600')
  })

  it('donne une classe par etat, et un gris par defaut', () => {
    expect(partnerStatusBadgeClass('paid')).toBe('bg-emerald-100 text-emerald-700')
    expect(partnerStatusBadgeClass('partial')).toBe('bg-amber-100 text-amber-700')
    expect(partnerStatusBadgeClass('cancelled')).toBe('bg-red-100 text-red-600')
    expect(partnerStatusBadgeClass('inconnu')).toBe('bg-gray-100 text-gray-500')
  })
})

describe('les sigles et modes de reglement', () => {
  it('abrege les types soldables', () => {
    expect(docTypeShortLabel('DeliveryNote')).toBe('BL')
    expect(docTypeShortLabel('InvoiceSale')).toBe('FAC')
    expect(docTypeShortLabel('InvoicePurchase')).toBe('FACA')
    expect(docTypeShortLabel('QuoteSale')).toBe('QuoteSale')
  })

  it('nomme les cinq modes de reglement, credit compris', () => {
    // `credit` manquait a la copie de la modale de consultation.
    expect(paymentMethodLabel('cash')).toBe('Espèces')
    expect(paymentMethodLabel('bank_transfer')).toBe('Virement')
    expect(paymentMethodLabel('cheque')).toBe('Chèque')
    expect(paymentMethodLabel('effet')).toBe('Effet')
    expect(paymentMethodLabel('credit')).toBe('Crédit')
    expect(paymentMethodLabel('inconnu')).toBe('inconnu')
  })
})
