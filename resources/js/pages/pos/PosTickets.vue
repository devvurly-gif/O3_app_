<template>
  <div class="h-full flex flex-col bg-white dark:bg-gray-800">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between shrink-0">
      <div>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tickets de la session</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Historique des ventes de la session en cours</p>
      </div>
      <router-link
        to="/pos/main"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition"
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
              <span class="font-mono text-sm font-semibold text-gray-900 dark:text-white">
                {{ ticket.reference }}
              </span>
              <span
                class="text-[10px] font-semibold px-2 py-0.5 rounded-full uppercase"
                :class="statusClass(ticket.status)"
              >
                {{ statusLabel(ticket.status) }}
              </span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              {{ new Date(ticket.created_at).toLocaleString('fr-FR') }}
              <span v-if="ticket.third_partner && ticket.third_partner.tp_code !== 'CLIENT-COMPTOIR'">
                — {{ ticket.third_partner.tp_title }}
              </span>
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-lg font-bold text-gray-900 dark:text-white">
              {{ ticket.footer ? Number(ticket.footer.total_ttc).toFixed(2) : '—' }} MAD
            </span>
            <!-- Print button -->
            <button
              v-if="ticket.status !== 'cancelled'"
              class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50 rounded-lg transition flex items-center gap-1"
              :disabled="printing === ticket.id"
              @click="printTicket(ticket.id, ticket.reference)"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18.25 7.234l-.001.006" />
              </svg>
              {{ printing === ticket.id ? '...' : 'Imprimer' }}
            </button>
            <!-- Void button -->
            <button
              v-if="ticket.status !== 'cancelled'"
              class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 rounded-lg transition"
              @click="voidTicket(ticket.id)"
            >
              Annuler
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePosStore } from '@/stores/pos/posStore'
import http from '@/services/http'

const posStore = usePosStore()
const printing = ref<number | null>(null)

function statusLabel(status: string): string {
  switch (status) {
    case 'cancelled': return 'Annulé'
    case 'partial': return 'Partiel'
    case 'pending': return 'En Compte'
    default: return 'Payé'
  }
}

function statusClass(status: string): string {
  switch (status) {
    case 'cancelled': return 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-400'
    case 'partial': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
    case 'pending': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
    default: return 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-400'
  }
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

async function voidTicket(id: number) {
  if (!confirm('Êtes-vous sûr de vouloir annuler ce ticket ?')) return
  await posStore.voidTicket(id)
}

onMounted(() => {
  posStore.fetchTickets()
})
</script>
