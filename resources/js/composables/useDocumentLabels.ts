/**
 * Centralised document type labels, status labels & status styling.
 * Replaces 6+ copies of typeLabels / statusConfig / statusLabels across pages.
 */

// ── Type labels (by domain) ────────────────────────────────────────────────
export const allTypeLabels: Record<string, string> = {
  // Ventes
  QuoteSale: 'Devis',
  CustomerOrder: 'Bon de Commande Client',
  DeliveryNote: 'Bon de Livraison',
  InvoiceSale: 'Facture',
  TicketSale: 'Ticket POS',
  CreditNoteSale: 'Avoir',
  ReturnSale: 'Bon de Retour',
  // Achats
  PurchaseOrder: 'Bon de Commande',
  ReceiptNotePurchase: 'Bon de Réception',
  InvoicePurchase: 'Facture Achat',
  CreditNotePurchase: 'Avoir Fournisseur',
  ReturnPurchase: 'Bon de Retour Achat',
  // Stock
  StockEntry: 'Entrée de Stock',
  StockExit: 'Sortie de Stock',
  StockAdjustmentNote: "Ajustement d'Inventaire",
  StockTransfer: 'Transfert inter-Dépôts',
}

export const saleTypeLabels: Record<string, string> = {
  QuoteSale: 'Devis',
  CustomerOrder: 'Bon de Commande Client',
  DeliveryNote: 'Bon de Livraison',
  InvoiceSale: 'Facture',
  TicketSale: 'Ticket POS',
  CreditNoteSale: 'Avoir',
  ReturnSale: 'Bon de Retour',
}

export const purchaseTypeLabels: Record<string, string> = {
  PurchaseOrder: 'Bon de Commande',
  ReceiptNotePurchase: 'Bon de Réception',
  InvoicePurchase: 'Facture Achat',
  CreditNotePurchase: 'Avoir Fournisseur',
  ReturnPurchase: 'Bon de Retour Achat',
}

export const stockTypeLabels: Record<string, string> = {
  StockEntry: 'Entrée de Stock',
  StockExit: 'Sortie de Stock',
  StockAdjustmentNote: "Ajustement d'Inventaire",
  StockTransfer: 'Transfert inter-Dépôts',
}

// ── Status labels ───────────────────────────────────────────────────────────
export const allStatusLabels: Record<string, string> = {
  draft: 'Brouillon',
  confirmed: 'Confirmé',
  converted: 'Converti',
  delivered: 'Livré',
  received: 'Reçu',
  pending: 'En attente',
  partial: 'Partiel',
  paid: 'Payé',
  applied: 'Appliqué',
  cancelled: 'Annulé',
}

// ── Status config (badge styling) ───────────────────────────────────────────
export interface StatusStyle {
  label: string
  color: string
  bg: string
  dot: string
}

export const statusConfig: Record<string, StatusStyle> = {
  draft: {
    label: 'Brouillon',
    color: 'text-gray-700 dark:text-gray-300',
    bg: 'bg-gray-100 dark:bg-gray-700',
    dot: 'bg-gray-400',
  },
  confirmed: {
    label: 'Confirmé',
    color: 'text-blue-700 dark:text-blue-300',
    bg: 'bg-blue-50 dark:bg-blue-900/30',
    dot: 'bg-blue-500',
  },
  converted: {
    label: 'Converti',
    color: 'text-purple-700 dark:text-purple-300',
    bg: 'bg-purple-50 dark:bg-purple-900/30',
    dot: 'bg-purple-500',
  },
  delivered: {
    label: 'Livré',
    color: 'text-green-700 dark:text-green-300',
    bg: 'bg-green-50 dark:bg-green-900/30',
    dot: 'bg-green-500',
  },
  received: {
    label: 'Reçu',
    color: 'text-teal-700 dark:text-teal-300',
    bg: 'bg-teal-50 dark:bg-teal-900/30',
    dot: 'bg-teal-500',
  },
  pending: {
    label: 'En attente',
    color: 'text-amber-700 dark:text-amber-300',
    bg: 'bg-amber-50 dark:bg-amber-900/30',
    dot: 'bg-amber-500',
  },
  partial: {
    label: 'Partiel',
    color: 'text-orange-700 dark:text-orange-300',
    bg: 'bg-orange-50 dark:bg-orange-900/30',
    dot: 'bg-orange-400',
  },
  paid: {
    label: 'Payé',
    color: 'text-emerald-700 dark:text-emerald-300',
    bg: 'bg-emerald-50 dark:bg-emerald-900/30',
    dot: 'bg-emerald-500',
  },
  applied: {
    label: 'Appliqué',
    color: 'text-emerald-700 dark:text-emerald-300',
    bg: 'bg-emerald-50 dark:bg-emerald-900/30',
    dot: 'bg-emerald-500',
  },
  cancelled: {
    label: 'Annulé',
    color: 'text-red-700 dark:text-red-300',
    bg: 'bg-red-50 dark:bg-red-900/30',
    dot: 'bg-red-500',
  },
}

/** Get status style, with fallback to draft */
export function getStatusStyle(status: string): StatusStyle {
  return statusConfig[status] ?? statusConfig.draft
}

// ── Payment method labels ───────────────────────────────────────────────────
export const paymentMethodLabels: Record<string, string> = {
  cash: 'Espèces',
  bank_transfer: 'Virement',
  cheque: 'Chèque',
  effet: 'Effet',
  credit: 'Crédit',
}
