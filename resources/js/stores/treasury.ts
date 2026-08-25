import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'

/**
 * Store du module Trésorerie.
 *
 * Le journal et la synthèse viennent du serveur déjà agrégés (écritures
 * manuelles + règlements documents) : les recalculer côté client obligerait à
 * charger tous les paiements, et donnerait un chiffre différent de celui des
 * rapports à la première divergence d'arrondi.
 */

export interface CashAccount {
  id: number
  ca_title: string
  ca_code?: string | null
  ca_type: 'cash' | 'bank' | 'cheque' | 'other'
  ca_payment_method?: string | null
  ca_initial_balance?: number | string
  ca_status: boolean
  ca_notes?: string | null
  /** Calculé côté serveur, présent sur la liste des comptes. */
  balance?: number
  initial_balance?: number
  manual_net?: number
  payments_net?: number
}

export interface CashCategory {
  id: number
  cc_title: string
  cc_code?: string | null
  cc_direction: 'in' | 'out' | 'both'
  cc_color?: string | null
  cc_status: boolean
}

export interface CashTransaction {
  id: number
  ct_code?: string | null
  cash_account_id: number
  cash_category_id?: number | null
  ct_direction: 'in' | 'out'
  ct_amount: number | string
  ct_date: string
  ct_label: string
  ct_method?: string | null
  ct_reference?: string | null
  thirdPartner_id?: number | null
  document_header_id?: number | null
  ct_transfer_group?: string | null
  ct_attachment_path?: string | null
  ct_attachment_name?: string | null
  ct_notes?: string | null
  ct_status: 'active' | 'cancelled'
  account?: CashAccount
  category?: CashCategory
  thirdPartner?: { id: number; tp_title: string }
  document?: { id: number; reference: string }
  user?: { id: number; name: string }
}

export interface CashRecurrence {
  id: number
  cr_label: string
  cr_direction: 'in' | 'out'
  cr_amount: number | string
  cash_account_id: number
  cash_category_id?: number | null
  thirdPartner_id?: number | null
  cr_method?: string | null
  cr_frequency: 'weekly' | 'monthly' | 'quarterly' | 'yearly'
  cr_anchor_day: number
  cr_start_date: string
  cr_end_date?: string | null
  cr_next_run_at: string
  cr_status: boolean
  cr_notes?: string | null
  account?: CashAccount
  category?: CashCategory
}

export interface JournalRow {
  source: 'manual' | 'payment'
  id: number
  code: string | null
  date: string
  direction: 'in' | 'out'
  amount: number | string
  label: string
  method: string | null
  account_id: number | null
  account_title: string | null
  category_title: string | null
  partner_title: string | null
  document_reference: string | null
  status: string
  attachment_path: string | null
  transfer_group: string | null
}

export interface TreasurySummary {
  from: string | null
  to: string | null
  total_in: number
  total_out: number
  net: number
  manual_in: number
  manual_out: number
  payments_in: number
  payments_out: number
  total_balance: number
  accounts: CashAccount[]
  by_category: Array<{
    category_id: number | null
    category_title: string
    direction: 'in' | 'out'
    total: number
    entries: number
  }>
}

export interface JournalFilters {
  from?: string
  to?: string
  direction?: string
  account_id?: number | string
  category_id?: number | string
  source?: string
  search?: string
  page?: number
  per_page?: number
}

/** Retire les filtres vides pour ne pas envoyer `?direction=` au serveur. */
function clean(params: Record<string, unknown>): Record<string, unknown> {
  return Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined))
}

export const useTreasuryStore = defineStore('treasury', () => {
  // ── State ────────────────────────────────────────────────────────────────
  const accounts = ref<CashAccount[]>([])
  const categories = ref<CashCategory[]>([])
  const recurrences = ref<CashRecurrence[]>([])
  const journal = ref<JournalRow[]>([])
  const journalMeta = ref({ current_page: 1, last_page: 1, total: 0 })
  const transactions = ref<CashTransaction[]>([])
  const transactionsMeta = ref({ current_page: 1, last_page: 1, total: 0 })
  const summary = ref<TreasurySummary | null>(null)
  const loading = ref(false)
  const error = ref<unknown>(null)

  // ── Lecture ──────────────────────────────────────────────────────────────
  async function fetchAccounts(): Promise<void> {
    const { data } = await http.get<CashAccount[]>('/cash-accounts')
    accounts.value = data
  }

  async function fetchCategories(direction?: string): Promise<void> {
    const { data } = await http.get<CashCategory[]>('/cash-categories', { params: clean({ direction }) })
    categories.value = data
  }

  async function fetchRecurrences(): Promise<void> {
    const { data } = await http.get<CashRecurrence[]>('/cash-recurrences')
    recurrences.value = data
  }

  async function fetchSummary(from?: string, to?: string): Promise<void> {
    const { data } = await http.get<TreasurySummary>('/treasury/summary', { params: clean({ from, to }) })
    summary.value = data
  }

  async function fetchJournal(filters: JournalFilters = {}): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get('/treasury/journal', { params: clean({ ...filters }) })
      journal.value = data.data
      journalMeta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total }
    } catch (e: unknown) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  async function fetchTransactions(filters: JournalFilters = {}): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get('/cash-transactions', { params: clean({ ...filters }) })
      transactions.value = data.data
      transactionsMeta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total }
    } catch (e: unknown) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  // ── Écritures ────────────────────────────────────────────────────────────

  /**
   * Un justificatif joint impose un multipart : Laravel ne lit pas de fichier
   * dans un corps JSON, et PUT ne transporte pas de fichier en PHP — d'où le
   * POST avec `_method` pour les modifications.
   */
  function toPayload(payload: Record<string, unknown>, file?: File | null): FormData | Record<string, unknown> {
    if (!file) return payload

    const form = new FormData()
    Object.entries(payload).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') form.append(key, String(value))
    })
    form.append('attachment', file)
    return form
  }

  async function createTransaction(payload: Record<string, unknown>, file?: File | null): Promise<CashTransaction> {
    const { data } = await http.post<CashTransaction>('/cash-transactions', toPayload(payload, file))
    return data
  }

  async function updateTransaction(
    id: number,
    payload: Record<string, unknown>,
    file?: File | null,
  ): Promise<CashTransaction> {
    if (file) {
      const { data } = await http.post<CashTransaction>(`/cash-transactions/${id}`, toPayload(payload, file))
      return data
    }
    const { data } = await http.put<CashTransaction>(`/cash-transactions/${id}`, payload)
    return data
  }

  /** Annule l'écriture (défaut) ou la supprime définitivement (`force`). */
  async function removeTransaction(id: number, force = false): Promise<void> {
    await http.delete(`/cash-transactions/${id}`, { params: force ? { force: 1 } : {} })
  }

  async function transfer(payload: Record<string, unknown>): Promise<void> {
    await http.post('/cash-transfers', payload)
  }

  // ── Paramétrage ──────────────────────────────────────────────────────────
  async function createAccount(payload: Partial<CashAccount>): Promise<CashAccount> {
    const { data } = await http.post<CashAccount>('/cash-accounts', payload)
    await fetchAccounts()
    return data
  }

  async function updateAccount(id: number, payload: Partial<CashAccount>): Promise<void> {
    await http.put(`/cash-accounts/${id}`, payload)
    await fetchAccounts()
  }

  async function removeAccount(id: number): Promise<void> {
    await http.delete(`/cash-accounts/${id}`)
    await fetchAccounts()
  }

  async function createCategory(payload: Partial<CashCategory>): Promise<void> {
    await http.post('/cash-categories', payload)
    await fetchCategories()
  }

  async function updateCategory(id: number, payload: Partial<CashCategory>): Promise<void> {
    await http.put(`/cash-categories/${id}`, payload)
    await fetchCategories()
  }

  async function removeCategory(id: number): Promise<void> {
    await http.delete(`/cash-categories/${id}`)
    await fetchCategories()
  }

  // ── Récurrences ──────────────────────────────────────────────────────────
  async function createRecurrence(payload: Partial<CashRecurrence>): Promise<void> {
    await http.post('/cash-recurrences', payload)
    await fetchRecurrences()
  }

  async function updateRecurrence(id: number, payload: Partial<CashRecurrence>): Promise<void> {
    await http.put(`/cash-recurrences/${id}`, payload)
    await fetchRecurrences()
  }

  async function removeRecurrence(id: number): Promise<void> {
    await http.delete(`/cash-recurrences/${id}`)
    await fetchRecurrences()
  }

  async function runRecurrences(upTo?: string): Promise<string> {
    const { data } = await http.post('/cash-recurrences/run', {}, { params: clean({ up_to: upTo }) })
    await fetchRecurrences()
    return data.message as string
  }

  return {
    accounts,
    categories,
    recurrences,
    journal,
    journalMeta,
    transactions,
    transactionsMeta,
    summary,
    loading,
    error,
    fetchAccounts,
    fetchCategories,
    fetchRecurrences,
    fetchSummary,
    fetchJournal,
    fetchTransactions,
    createTransaction,
    updateTransaction,
    removeTransaction,
    transfer,
    createAccount,
    updateAccount,
    removeAccount,
    createCategory,
    updateCategory,
    removeCategory,
    createRecurrence,
    updateRecurrence,
    removeRecurrence,
    runRecurrences,
  }
})
