<template>
  <div class="h-full flex flex-col bg-white dark:bg-gray-800">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between shrink-0">
      <div>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tickets de la session</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Historique des ventes de la session en cours</p>
      </div>
      <router-link
        to="/pos/main"
        class="px-4 py-2 bg-orange-700 hover:bg-orange-800 text-white text-sm font-medium rounded-lg transition"
      >
        Retour au POS
      </router-link>
    </div>

    <div class="flex-1 overflow-y-auto p-6">
      <div v-if="!posStore.tickets.length" class="flex items-center justify-center h-full text-gray-400 dark:text-gray-500">
        Aucun ticket pour cette session
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="ticket in posStore.tickets"
          :key="ticket.id"
          class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 flex items-center justify-between"
        >
          <div>
            <div class="flex items-center gap-2 mb-1">
              <button
                class="font-mono text-sm font-semibold text-orange-500 hover:text-orange-700 hover:underline transition"
                @click="openTicketDetail(ticket)"
              >
                {{ ticket.reference }}
              </button>
              <span
                class="text-[10px] font-semibold px-2 py-0.5 rounded-full uppercase"
                :class="typeClass(ticket.document_type)"
              >
                {{ typeLabel(ticket.document_type) }}
              </span>
              <span
                class="text-[10px] font-semibold px-2 py-0.5 rounded-full uppercase"
                :class="statusClass(ticket.status)"
              >
                {{ statusLabel(ticket.status) }}
              </span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              {{ formatDateTime(ticket.created_at) }}
              <span v-if="ticket.third_partner && ticket.third_partner.tp_code !== 'CLIENT-COMPTOIR'">
                — {{ ticket.third_partner.tp_title }}
              </span>
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-lg font-bold text-gray-900 dark:text-white">
              {{ ticket.footer ? Number(ticket.footer.total_ttc).toFixed(2) : '—' }} MAD
            </span>
            <!-- Print button (not for already-created returns) -->
            <button
              v-if="ticket.status !== 'cancelled' && ticket.document_type !== 'ReturnSale'"
              class="px-3 py-1.5 text-xs font-medium text-orange-500 bg-orange-50 hover:bg-orange-100 dark:bg-orange-900/30 dark:text-orange-400 dark:hover:bg-blue-900/50 rounded-lg transition flex items-center gap-1"
              :disabled="printing === ticket.id"
              @click="printTicket(ticket.id, ticket.reference)"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18.25 7.234l-.001.006" />
              </svg>
              {{ printing === ticket.id ? '...' : 'Imprimer' }}
            </button>
            <!-- Retour button: reverses a TicketSale or converts a BL into a BR -->
            <button
              v-if="canReturn(ticket)"
              class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 rounded-lg transition"
              :disabled="returning === ticket.id"
              @click="returnTicket(ticket)"
            >
              {{ returning === ticket.id ? '...' : 'Retour' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Ticket Detail Modal -->
    <BaseModal v-model="showTicketDetailModal" :title="ticketDetail?.reference ?? 'Ticket'" size="lg">
      <div v-if="ticketDetailLoading" class="flex items-center justify-center py-12">
        <svg class="w-6 h-6 animate-spin text-orange-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
        </svg>
      </div>
      <div v-else-if="ticketDetail" class="space-y-4">
        <!-- Ticket Header Info -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
              <p class="text-gray-500 dark:text-gray-400 text-xs uppercase font-semibold mb-1">Type</p>
              <p class="font-medium text-gray-900 dark:text-white">{{ typeLabel(ticketDetail.document_type) }}</p>
            </div>
            <div>
              <p class="text-gray-500 dark:text-gray-400 text-xs uppercase font-semibold mb-1">Date</p>
              <p class="font-medium text-gray-900 dark:text-white">{{ formatDateTime(ticketDetail.created_at) }}</p>
            </div>
            <div>
              <p class="text-gray-500 dark:text-gray-400 text-xs uppercase font-semibold mb-1">Statut</p>
              <span
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                :class="statusClass(ticketDetail.status)"
              >
                {{ statusLabel(ticketDetail.status) }}
              </span>
            </div>
            <div>
              <p class="text-gray-500 dark:text-gray-400 text-xs uppercase font-semibold mb-1">Total TTC</p>
              <p class="font-mono font-semibold text-gray-900 dark:text-white">
                {{ ticketDetail.footer ? Number(ticketDetail.footer.total_ttc).toFixed(2) : '0.00' }} MAD
              </p>
            </div>
          </div>
          <div v-if="ticketDetail.third_partner && ticketDetail.third_partner.tp_code !== 'CLIENT-COMPTOIR'" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Client</p>
            <p class="font-medium text-gray-900 dark:text-white">{{ ticketDetail.third_partner.tp_title }}</p>
          </div>
        </div>

        <!-- Ticket Lines Table -->
        <div v-if="ticketDetail.lignes && ticketDetail.lignes.length > 0" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700 text-left bg-gray-50 dark:bg-gray-900">
                <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Désignation</th>
                <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">Quantité</th>
                <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">P.U.</th>
                <th class="py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="ligne in ticketDetail.lignes" :key="ligne.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="py-2.5 px-3 text-gray-900 dark:text-white">
                  <div class="flex items-start gap-2">
                    <div class="flex-1">
                      <p class="font-medium">{{ ligne.designation }}</p>
                      <p v-if="ligne.product?.tp_code" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ ligne.product.tp_code }}
                      </p>
                    </div>
                  </div>
                </td>
                <td class="py-2.5 px-3 text-right font-mono text-gray-600 dark:text-gray-400">
                  {{ Number(ligne.quantity).toFixed(2) }} {{ ligne.unit }}
                </td>
                <td class="py-2.5 px-3 text-right font-mono text-gray-600 dark:text-gray-400">
                  {{ Number(ligne.unit_price).toFixed(2) }}
                </td>
                <td class="py-2.5 px-3 text-right font-mono font-medium text-gray-900 dark:text-white">
                  {{ (Number(ligne.quantity) * Number(ligne.unit_price) * (1 - (Number(ligne.discount_percent) ?? 0) / 100)).toFixed(2) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
          <p class="text-sm">Aucune ligne de détail</p>
        </div>
      </div>

      <template #footer>
        <button
          class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition"
          @click="showTicketDetailModal = false"
        >
          Fermer
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePosStore } from '@/stores/pos/posStore'
import http from '@/services/http'
import { useFormat } from '@/composables/useFormat'

const { date: fmtDate } = useFormat()

function formatDateTime(value: string): string {
  const d = new Date(value)
  return fmtDate(d) + ' ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

interface PosDocument {
  id: number
  reference: string
  document_type: string
  status: string
  created_at: string
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  footer?: any
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  third_partner?: any
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  lignes?: any[]
}

const posStore = usePosStore()
const printing = ref<number | null>(null)
const returning = ref<number | null>(null)
const showTicketDetailModal = ref(false)
const ticketDetail = ref<any>(null)
const ticketDetailLoading = ref(false)

function statusLabel(status: string): string {
  switch (status) {
    case 'cancelled': return 'Annulé'
    case 'partial': return 'Partiel'
    case 'pending': return 'En Compte'
    // POS BLs are marked 'confirmed' the moment the customer walks out with
    // the goods; the invoice is still owed but the delivery is done.
    case 'confirmed': return 'Livré'
    default: return 'Payé'
  }
}

function statusClass(status: string): string {
  switch (status) {
    case 'cancelled': return 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-400'
    case 'partial': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
    case 'pending': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
    case 'confirmed': return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-400'
    default: return 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-400'
  }
}

function typeLabel(type: string): string {
  switch (type) {
    case 'DeliveryNote': return 'BL'
    case 'ReturnSale':   return 'BR'
    default:             return 'Ticket'
  }
}

function typeClass(type: string): string {
  switch (type) {
    case 'DeliveryNote': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
    case 'ReturnSale':   return 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-400'
    default:             return 'bg-orange-100 text-orange-600 dark:bg-orange-900/50 dark:text-orange-400'
  }
}

/** A retour can be triggered on a TicketSale or a DeliveryNote, only
 *  when the doc is still active. ReturnSale rows (BR) are the result
 *  of a previous retour and cannot be returned again. */
function canReturn(ticket: PosDocument): boolean {
  if (ticket.status === 'cancelled') return false
  return ticket.document_type === 'TicketSale' || ticket.document_type === 'DeliveryNote'
}

async function printTicket(id: number, reference: string) {
  printing.value = id
  try {
    const response = await http.get(`/pos/tickets/${id}/print`, {
      responseType: 'blob',
    })
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)

    // Open in new window for printing
    const printWindow = window.open(url, '_blank')
    if (printWindow) {
      printWindow.addEventListener('load', () => {
        printWindow.print()
      })
    }
  } catch {
    alert('Erreur lors de l\'impression du ticket')
  } finally {
    printing.value = null
  }
}

async function returnTicket(ticket: PosDocument) {
  const label = ticket.document_type === 'DeliveryNote'
    ? `Créer un Bon de Retour (BR) à partir du BL ${ticket.reference} ? Le stock sera restauré et le crédit client libéré.`
    : `Effectuer un retour sur le ticket ${ticket.reference} ? Le stock sera restauré.`
  if (!confirm(label)) return
  returning.value = ticket.id
  try {
    await posStore.returnTicket(ticket.id)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message ?? 'Erreur lors du retour')
  } finally {
    returning.value = null
  }
}

async function openTicketDetail(ticket: PosDocument) {
  ticketDetail.value = ticket
  ticketDetailLoading.value = true
  showTicketDetailModal.value = true
  // Ensure the ticket has lignes loaded
  if (!ticket.lignes) {
    try {
      const { data } = await http.get(`/documents/${ticket.id}`)
      ticketDetail.value = data
    } catch {
      // Silently continue with what we have
    } finally {
      ticketDetailLoading.value = false
    }
  } else {
    ticketDetailLoading.value = false
  }
}

onMounted(() => {
  posStore.fetchTickets()
})
</script>
