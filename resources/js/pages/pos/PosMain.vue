<template>
  <div class="flex h-full relative">
    <!-- Mobile cart toggle FAB -->
    <button
      class="lg:hidden fixed bottom-4 right-4 z-30 w-14 h-14 rounded-full bg-orange-500 text-white shadow-lg flex items-center justify-center hover:bg-orange-600 transition"
      @click="mobileView = mobileView === 'cart' ? 'products' : 'cart'"
    >
      <svg v-if="mobileView === 'products'" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
      </svg>
      <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0v10l-8 4m0-14L4 17m8 4V10" />
      </svg>
      <span
        v-if="mobileView === 'products' && posStore.cartItemCount"
        class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 flex items-center justify-center text-[10px] font-bold bg-red-500 text-white rounded-full"
      >
        {{ posStore.cartItemCount }}
      </span>
    </button>

    <!-- LEFT: Products panel (60% desktop, full on mobile) -->
    <div
      class="flex flex-col border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800"
      :class="mobileView === 'products' ? 'w-full lg:w-[60%]' : 'hidden lg:flex lg:w-[60%]'"
    >
      <!-- Category tabs -->
      <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 dark:border-gray-700 overflow-x-auto shrink-0">
        <button
          class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium transition"
          :class="!posStore.selectedCategoryId ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
          @click="selectCategory(null)"
        >
          Tout
        </button>
        <button
          v-for="cat in categories"
          :key="cat.id"
          class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium transition"
          :class="posStore.selectedCategoryId === cat.id ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
          @click="selectCategory(cat.id)"
        >
          {{ cat.ctg_title }}
        </button>
      </div>

      <!-- Search bar -->
      <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 shrink-0">
        <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            v-model="posStore.searchQuery"
            type="text"
            placeholder="Rechercher par nom, SKU, code-barres..."
            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
            @input="debouncedSearch"
          />
        </div>
      </div>

      <!-- Products grid -->
      <div class="flex-1 overflow-y-auto p-4">
        <div v-if="posStore.loadingProducts" class="flex items-center justify-center h-full text-gray-400 dark:text-gray-500">
          Chargement...
        </div>
        <div v-else-if="!posStore.products.length" class="flex items-center justify-center h-full text-gray-400 dark:text-gray-500">
          Aucun produit trouvé
        </div>
        <div v-else class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-3 xl:grid-cols-4 gap-3">
          <button
            v-for="product in posStore.products"
            :key="product.id"
            class="flex flex-col bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-3 hover:border-orange-400 hover:shadow-md transition-all text-left group"
            @click="handleProductClick(product)"
          >
            <div class="w-full aspect-square rounded-lg bg-gray-200 mb-2 overflow-hidden flex items-center justify-center">
              <img
                v-if="product.primary_image?.url"
                :src="product.primary_image.url"
                :alt="product.p_title"
                class="w-full h-full object-cover"
                @error="($event: Event) => (($event.target as HTMLImageElement).style.display = 'none')"
              />
              <svg v-else class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0v10l-8 4m0-14L4 17m8 4V10" />
              </svg>
            </div>
            <p class="text-xs font-medium text-gray-900 dark:text-white line-clamp-2 leading-tight mb-1">{{ product.p_title }}</p>
            <div class="mt-auto flex items-center justify-between">
              <span class="text-sm font-bold text-orange-500">{{ formatPrice(product.p_salePrice) }}</span>
              <span v-if="product.variants && product.variants.length > 0"
                class="text-[10px] px-1.5 py-0.5 rounded-full bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                {{ product.variants.length }} var.
              </span>
              <span v-else class="text-[10px] px-1.5 py-0.5 rounded-full" :class="product.stock > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'">
                {{ product.stock > 0 ? product.stock : 'Rupture' }}
              </span>
            </div>
          </button>
        </div>
      </div>
    </div>

    <!-- RIGHT: Cart panel (40% desktop, full on mobile) -->
    <div
      class="flex flex-col bg-white dark:bg-gray-800"
      :class="mobileView === 'cart' ? 'w-full lg:w-[40%]' : 'hidden lg:flex lg:w-[40%]'"
    >
      <!-- Cart header -->
      <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between shrink-0">
        <div>
          <h2 class="text-sm font-bold text-gray-900 dark:text-white">
            Panier
            <span v-if="posStore.cartItemCount" class="ml-1 text-xs bg-orange-100 text-orange-500 px-2 py-0.5 rounded-full">
              {{ posStore.cartItemCount }}
            </span>
          </h2>
          <!-- Printer indicator -->
          <div v-if="posStore.currentTerminal?.printer_name" class="flex items-center gap-1 mt-0.5">
            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/>
            </svg>
            <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ posStore.currentTerminal.printer_name }}</span>
            <!-- QZ Tray connection dot -->
            <span
              class="w-1.5 h-1.5 rounded-full inline-block"
              :class="qzTray.isConnected.value ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600'"
              :title="qzTray.isConnected.value ? 'QZ Tray connecté — impression directe' : 'QZ Tray non connecté — dialog navigateur'"
            />
            <span v-if="posStore.currentTerminal.auto_print === false" class="text-[10px] text-amber-500">(manuel)</span>
          </div>
        </div>
        <div class="flex items-center gap-2 relative">
          <!-- Held tickets dropdown -->
          <div class="relative">
            <button
              type="button"
              class="flex items-center gap-1 text-xs px-2 py-1 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/40 dark:text-indigo-300 transition"
              @click.stop="showHeldDropdown = !showHeldDropdown"
              :title="posStore.draftTickets.length ? 'Tickets en attente' : 'Aucun ticket en attente'"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
              <span>Tickets</span>
              <span
                v-if="posStore.draftTickets.length"
                class="ml-0.5 text-[10px] bg-indigo-600 text-white px-1.5 py-0.5 rounded-full"
              >
                {{ posStore.draftTickets.length }}
              </span>
            </button>

            <div
              v-if="showHeldDropdown"
              class="absolute right-0 top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg z-30 w-64 overflow-hidden"
              @click.stop
            >
              <button
                type="button"
                class="w-full px-3 py-2 text-left text-xs font-medium text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed"
                :disabled="!posStore.cart.length"
                @click="parkCurrent"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Mettre en attente + nouveau ticket
              </button>

              <div v-if="!posStore.draftTickets.length" class="px-3 py-3 text-xs text-gray-400 text-center">
                Aucun ticket en attente
              </div>
              <div v-else class="max-h-60 overflow-y-auto">
                <div
                  v-for="draft in posStore.draftTickets"
                  :key="draft.id"
                  class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/40 border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                >
                  <button
                    type="button"
                    class="flex-1 min-w-0 text-left"
                    @click="switchToDraft(draft.id)"
                  >
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ draft.label }}</p>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400">
                      {{ draft.cart.length }} article{{ draft.cart.length > 1 ? 's' : '' }} ·
                      {{ formatPrice(draftTotalTtc(draft)) }}
                    </p>
                  </button>
                  <button
                    type="button"
                    class="p-1 text-gray-400 hover:text-red-500 transition"
                    title="Supprimer ce ticket"
                    @click="discardDraft(draft.id)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <button
            type="button"
            class="flex items-center gap-1 text-xs px-2 py-1 rounded-lg bg-orange-50 text-orange-600 hover:bg-orange-100 dark:bg-orange-900/40 dark:text-orange-300 transition"
            title="Ventes par mode de paiement"
            @click="openSessionStats"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 4 4 6-6" />
            </svg>
            <span>Ventes</span>
            <span
              v-if="(sessionStats?.total_tickets ?? 0) > 0"
              class="ml-0.5 text-[10px] bg-orange-500 text-white px-1.5 py-0.5 rounded-full"
            >
              {{ sessionStats?.total_tickets }}
            </span>
          </button>
          <router-link to="/pos/tickets" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 transition">
            Historique
          </router-link>
          <!-- Terminal settings -->
          <button
            type="button"
            class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition relative"
            title="Paramètres du terminal"
            @click="showTerminalSettings = true"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>
          <button
            v-if="posStore.cart.length"
            class="text-xs text-red-500 hover:text-red-700 transition"
            @click="posStore.clearCart()"
          >
            Vider
          </button>
        </div>
      </div>

      <!-- Customer selector -->
      <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 shrink-0">
        <!-- Selected customer display -->
        <div v-if="selectedCustomer" class="flex items-center gap-3 p-2.5 rounded-xl bg-orange-50 dark:bg-orange-900/30 border border-orange-200 dark:border-orange-800">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ selectedCustomer.tp_title }}</p>
            <div class="flex items-center gap-2 mt-0.5">
              <span v-if="selectedCustomer.tp_phone" class="text-xs text-gray-500 dark:text-gray-400">{{ selectedCustomer.tp_phone }}</span>
              <span
                v-if="selectedCustomer.type_compte === 'en_compte'"
                class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400 font-medium"
              >
                En compte
              </span>
            </div>
            <p v-if="selectedCustomer.type_compte === 'en_compte'" class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">
              Encours: {{ formatPrice(selectedCustomer.encours_actuel ?? 0) }}
              <span v-if="Number(selectedCustomer.seuil_credit) > 0"> / Plafond: {{ formatPrice(selectedCustomer.seuil_credit) }}</span>
            </p>
          </div>
          <button class="p-1 text-gray-400 hover:text-red-500 transition" @click="clearCustomer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Customer search -->
        <div v-else class="relative">
          <div class="flex gap-2">
            <div class="relative flex-1">
              <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              <input
                v-model="customerSearch"
                type="text"
                placeholder="Client (optionnel)..."
                class="w-full pl-8 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                @input="debouncedCustomerSearch"
                @focus="showCustomerDropdown = true"
              />
            </div>
            <button
              class="px-3 py-2 rounded-lg text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition whitespace-nowrap"
              @click="showNewCustomerModal = true"
            >
              + Nouveau
            </button>
          </div>

          <!-- Dropdown results -->
          <div
            v-if="showCustomerDropdown && customerResults.length"
            class="absolute left-0 right-0 top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg z-20 max-h-48 overflow-y-auto"
          >
            <button
              v-for="cust in customerResults"
              :key="cust.id"
              class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition text-left"
              @click="selectCustomer(cust)"
            >
              <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-900 dark:text-white truncate">{{ cust.tp_title }}</p>
                <p v-if="cust.tp_phone" class="text-xs text-gray-400">{{ cust.tp_phone }}</p>
              </div>
              <span
                v-if="cust.type_compte === 'en_compte'"
                class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400 font-medium shrink-0"
              >
                En compte
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- Cart items -->
      <div class="flex-1 overflow-y-auto px-5 py-3 space-y-2">
        <div v-if="!posStore.cart.length" class="flex items-center justify-center h-full text-gray-300 text-sm">
          Le panier est vide
        </div>
        <div
          v-for="item in posStore.cart"
          :key="`${item.product_id}-${item.variant_id ?? 'base'}`"
          class="p-3 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-md transition-all duration-200"
        >
          <!-- Row 1: Designation + Action buttons -->
          <div class="flex items-start justify-between gap-2 mb-2">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-800 dark:text-white leading-snug line-clamp-2">
                {{ item.designation }}
              </p>
              <span v-if="item.variant_id" class="inline-block mt-0.5 px-1.5 py-0.5 text-[10px] font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300 rounded-full">
                {{ item.variant_label ?? 'Variante' }}
              </span>
            </div>
            <div class="flex items-center gap-0.5 shrink-0 mt-0.5">
              <button
                @click="openEditModal(item)"
                class="p-1.5 rounded-lg text-blue-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition"
                title="Modifier"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
              </button>
              <button
                class="p-1.5 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition"
                @click="posStore.removeFromCart(item.product_id, item.variant_id ?? null)"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Row 2: Line total + Qty controls -->
          <div class="flex items-center justify-between gap-2">
            <!-- Price block -->
            <div>
              <p class="text-xl font-bold text-orange-500 leading-none">
                {{ formatPrice(item.quantity * item.unit_price * (1 - item.discount_percent / 100)) }}
              </p>
              <div class="flex items-center gap-1.5 mt-1">
                <span class="text-xs text-gray-400 dark:text-gray-500">Prix Unit :</span>
                <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ formatPrice(item.unit_price) }}</span>
                <span v-if="item.discount_percent > 0" class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400">
                  -{{ item.discount_percent }}%
                </span>
              </div>
            </div>

            <!-- Quantity stepper -->
            <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-700/60 rounded-xl p-1">
              <button
                class="w-7 h-7 rounded-lg bg-white dark:bg-gray-600 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-500 flex items-center justify-center text-gray-600 dark:text-gray-200 transition active:scale-95"
                @click="posStore.updateCartItemQty(item.product_id, item.quantity - 1, item.variant_id ?? null)"
              >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" d="M5 12h14" />
                </svg>
              </button>
              <span class="w-8 text-center text-sm font-bold text-gray-800 dark:text-white select-none">{{ item.quantity }}</span>
              <button
                class="w-7 h-7 rounded-lg bg-orange-500 hover:bg-orange-600 shadow-sm flex items-center justify-center text-white transition active:scale-95"
                @click="posStore.updateCartItemQty(item.product_id, item.quantity + 1, item.variant_id ?? null)"
              >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" d="M12 5v14m7-7H5" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Totals + Payment -->
      <div class="border-t border-gray-200 dark:border-gray-700 px-5 py-4 space-y-3 shrink-0">
        <div v-if="isTaxEnabled" class="flex justify-between text-sm text-gray-500 dark:text-gray-400">
          <span>Sous-total HT</span>
          <span>{{ formatPrice(posStore.cartSubtotal) }}</span>
        </div>
        <div v-if="isTaxEnabled" class="flex justify-between text-sm text-gray-500 dark:text-gray-400">
          <span>TVA</span>
          <span>{{ formatPrice(posStore.cartTax) }}</span>
        </div>
        <div class="flex justify-between text-lg font-bold text-gray-900 dark:text-white">
          <span>{{ isTaxEnabled ? 'Total TTC' : 'Total' }}</span>
          <span>{{ formatPrice(posStore.cartTotal) }}</span>
        </div>

        <div class="grid grid-cols-3 gap-2 pt-2">
          <button
            :disabled="!posStore.cart.length"
            class="py-3 rounded-xl font-semibold text-sm transition disabled:opacity-40 disabled:cursor-not-allowed bg-green-600 hover:bg-green-700 text-white"
            @click="payWith('cash')"
          >
            Espèces
          </button>
          <button
            :disabled="!posStore.cart.length"
            class="py-3 rounded-xl font-semibold text-sm transition disabled:opacity-40 disabled:cursor-not-allowed bg-orange-500 hover:bg-orange-600 text-white"
            @click="payWith('card')"
          >
            Carte
          </button>
          <button
            :disabled="!posStore.cart.length"
            class="py-3 rounded-xl font-semibold text-sm transition disabled:opacity-40 disabled:cursor-not-allowed bg-amber-500 hover:bg-amber-600 text-white"
            @click="payWithCredit"
          >
            En Compte
          </button>
        </div>

        <button
          class="w-full py-2.5 rounded-xl text-sm font-medium bg-amber-100 text-amber-700 hover:bg-amber-200 transition"
          @click="router.push('/pos/close')"
        >
          Fermer la session
        </button>
      </div>
    </div>

    <!-- Checkout Modal -->
    <PosCheckout
      v-if="showCheckout"
      :method="checkoutMethod"
      :total="posStore.cartTotal"
      :customer="selectedCustomer"
      @close="showCheckout = false"
      @confirm="confirmPayment"
    />

    <!-- New Customer Modal -->
    <div v-if="showNewCustomerModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showNewCustomerModal = false">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm p-6">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Nouveau Client</h3>

        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom *</label>
            <input
              v-model="newCustomer.tp_title"
              type="text"
              class="w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500"
              placeholder="Nom du client"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Téléphone</label>
            <input
              v-model="newCustomer.tp_phone"
              type="text"
              class="w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500"
              placeholder="06 xx xx xx xx"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
            <input
              v-model="newCustomer.tp_email"
              type="email"
              class="w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500"
              placeholder="email@exemple.com"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type de compte</label>
            <div class="flex gap-2">
              <button
                class="flex-1 py-2 rounded-lg text-sm font-medium transition"
                :class="newCustomer.type_compte === 'normal' ? 'bg-orange-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'"
                @click="newCustomer.type_compte = 'normal'"
              >
                Normal
              </button>
              <button
                class="flex-1 py-2 rounded-lg text-sm font-medium transition"
                :class="newCustomer.type_compte === 'en_compte' ? 'bg-amber-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'"
                @click="newCustomer.type_compte = 'en_compte'"
              >
                En Compte
              </button>
            </div>
          </div>
          <div v-if="newCustomer.type_compte === 'en_compte'">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Plafond crédit (MAD)</label>
            <input
              v-model.number="newCustomer.seuil_credit"
              type="number"
              min="0"
              step="100"
              class="w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500"
              placeholder="0 = illimité"
            />
          </div>
        </div>

        <div class="flex gap-3 mt-6">
          <button
            class="flex-1 py-2.5 rounded-xl text-sm font-medium text-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
            @click="showNewCustomerModal = false"
          >
            Annuler
          </button>
          <button
            :disabled="!newCustomer.tp_title.trim() || creatingCustomer"
            class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600 transition disabled:opacity-40 disabled:cursor-not-allowed"
            @click="createCustomer"
          >
            {{ creatingCustomer ? 'Création...' : 'Créer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Session Stats Modal: ventes par mode de paiement -->
    <div
      v-if="showSessionStats"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      @click.self="showSessionStats = false"
    >
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
              <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 4 4 6-6" />
              </svg>
              Ventes de la session
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              Détail par mode de paiement
            </p>
          </div>
          <button
            type="button"
            class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
            @click="showSessionStats = false"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Summary header -->
        <div class="grid grid-cols-2 gap-2 mb-4">
          <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-3">
            <p class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400 font-medium">Tickets</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">
              {{ sessionStats?.total_tickets ?? 0 }}
            </p>
            <p v-if="(sessionStats?.cancelled_tickets ?? 0) > 0" class="text-[10px] text-red-500 mt-0.5">
              {{ sessionStats?.cancelled_tickets }} annulé{{ (sessionStats?.cancelled_tickets ?? 0) > 1 ? 's' : '' }}
            </p>
          </div>
          <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-3">
            <p class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400 font-medium">Total TTC</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">
              {{ formatPrice(sessionStats?.total_ttc ?? 0) }}
            </p>
          </div>
        </div>

        <!-- Payment method breakdown -->
        <div class="space-y-2">
          <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">
            Modes de paiement
          </p>

          <div v-if="!hasAnySales" class="text-sm text-gray-400 dark:text-gray-500 text-center py-6 bg-gray-50 dark:bg-gray-900 rounded-xl">
            Aucune vente enregistrée pour le moment.
          </div>

          <template v-else>
            <div
              v-for="m in paymentMethodRows"
              :key="m.method"
              class="flex items-center justify-between p-3 rounded-xl"
              :class="m.bg"
            >
              <span class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full" :class="m.dot"></span>
                <span class="text-sm font-semibold" :class="m.label">{{ m.title }}</span>
                <span class="text-[11px] px-1.5 py-0.5 rounded-full bg-white/60 dark:bg-black/20" :class="m.muted">
                  {{ m.count }} ticket{{ m.count > 1 ? 's' : '' }}
                </span>
              </span>
              <span class="text-sm font-bold tabular-nums" :class="m.label">
                {{ formatPrice(m.amount) }}
              </span>
            </div>

</template>
        </div>

        <!-- Totals -->
        <div v-if="hasAnySales" class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700 space-y-1.5 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">Total encaissé</span>
            <span class="font-semibold text-gray-900 dark:text-white tabular-nums">
              {{ formatPrice(sessionStats?.total_paid ?? 0) }}
            </span>
          </div>
          <div v-if="(sessionStats?.total_credit ?? 0) > 0" class="flex justify-between">
            <span class="text-amber-600 dark:text-amber-400">Dont en compte (à recouvrer)</span>
            <span class="font-semibold text-amber-700 dark:text-amber-400 tabular-nums">
              {{ formatPrice(sessionStats?.total_credit ?? 0) }}
            </span>
          </div>
        </div>

        <!-- Footer actions -->
        <div class="mt-5 flex gap-2">
          <button
            type="button"
            class="flex-1 py-2.5 rounded-xl text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition flex items-center justify-center gap-1.5"
            :disabled="refreshingStats"
            @click="refreshSessionStats"
          >
            <svg class="w-4 h-4" :class="refreshingStats ? 'animate-spin' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            {{ refreshingStats ? 'Actualisation...' : 'Actualiser' }}
          </button>
          <button
            type="button"
            class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600 transition"
            @click="showSessionStats = false"
          >
            Fermer
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Cart Item Edit Modal -->
  <CartItemModal
    :isOpen="showEditModal"
    :item="editingItem"
    @close="closeEditModal"
    @save="saveEditModal"
  />

  <!-- Terminal Settings Drawer -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="showTerminalSettings" class="fixed inset-0 z-50 flex justify-end">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showTerminalSettings = false" />
        <!-- Drawer -->
        <Transition
          enter-active-class="transition-transform duration-300 ease-out"
          enter-from-class="translate-x-full"
          enter-to-class="translate-x-0"
          leave-active-class="transition-transform duration-200 ease-in"
          leave-from-class="translate-x-0"
          leave-to-class="translate-x-full"
        >
          <div v-if="showTerminalSettings" class="relative w-80 h-full bg-white dark:bg-gray-800 shadow-2xl flex flex-col z-10">
            <!-- Drawer header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
              <div>
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Paramètres du terminal</h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ posStore.currentTerminal?.name ?? '—' }}</p>
              </div>
              <button @click="showTerminalSettings = false" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Drawer body -->
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-6">

              <!-- Print mode status -->
              <div v-if="qzTray.isConnected.value" class="p-3 rounded-xl bg-green-50 dark:bg-green-900/20">
                <div class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-green-500"/>
                  <p class="text-xs font-semibold text-green-700 dark:text-green-300">QZ Tray connecté</p>
                </div>
                <p class="text-[10px] mt-1 text-green-600">Impression directe — aucun dialog.</p>
              </div>

              <!-- No QZ Tray: show kiosk mode tip -->
              <div v-else class="space-y-3">
                <!-- Kiosk mode tip (recommended) -->
                <div class="p-3 rounded-xl bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800">
                  <div class="flex items-center gap-2 mb-2">
                    <svg class="w-3.5 h-3.5 text-orange-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-xs font-semibold text-orange-700 dark:text-orange-300">Impression silencieuse sans logiciel</p>
                  </div>
                  <p class="text-[10px] text-orange-600 dark:text-orange-400 leading-relaxed mb-2">
                    Lancez Chrome avec le flag <code class="bg-orange-100 dark:bg-orange-900 px-1 py-0.5 rounded font-mono">--kiosk-printing</code> pour imprimer sans dialog sur l'imprimante par défaut Windows.
                  </p>
                  <div class="bg-orange-100 dark:bg-orange-900/50 rounded-lg p-2 mt-1">
                    <p class="text-[10px] font-mono text-orange-700 dark:text-orange-300 break-all select-all">
                      chrome.exe --kiosk-printing --app={{ currentUrl }}
                    </p>
                  </div>
                  <p class="text-[10px] text-orange-500 mt-1.5">Définissez l'imprimante ticket comme imprimante par défaut dans Windows.</p>
                </div>

                <!-- QZ Tray option -->
                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                      <span class="w-2 h-2 rounded-full bg-gray-300"/>
                      <p class="text-xs font-semibold text-gray-500">QZ Tray non connecté</p>
                    </div>
                    <button @click="qzTray.connect()" class="text-[10px] px-2 py-1 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-300 transition">
                      Reconnecter
                    </button>
                  </div>
                  <p class="text-[10px] mt-1 text-gray-400">Alternative : installez QZ Tray pour l'impression réseau avancée.</p>
                </div>
              </div>

              <!-- Printer name -->
              <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5 uppercase tracking-wide">
                  Imprimante
                </label>
                <input
                  v-model="terminalSettingsForm.printer_name"
                  type="text"
                  placeholder="ex: Epson TM-T20 Caisse 1"
                  class="w-full px-3 py-2.5 text-sm rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                />
                <p class="text-[10px] text-gray-400 mt-1">
                  Doit correspondre exactement au nom de l'imprimante dans Windows.
                </p>
              </div>

              <!-- Auto print toggle -->
              <div class="flex items-start gap-3">
                <button
                  type="button"
                  @click="terminalSettingsForm.auto_print = !terminalSettingsForm.auto_print"
                  class="relative shrink-0 w-10 h-5.5 mt-0.5 rounded-full transition-colors duration-200"
                  :class="terminalSettingsForm.auto_print ? 'bg-orange-500' : 'bg-gray-300 dark:bg-gray-600'"
                >
                  <span
                    class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200"
                    :class="terminalSettingsForm.auto_print ? 'translate-x-4.5' : 'translate-x-0'"
                  />
                </button>
                <div>
                  <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Impression automatique</p>
                  <p class="text-[10px] text-gray-400 mt-0.5">
                    {{ terminalSettingsForm.auto_print
                      ? 'Le ticket s\'imprime automatiquement après chaque vente.'
                      : 'L\'impression est manuelle — utilisez le bouton imprimer.' }}
                  </p>
                </div>
              </div>

              <!-- Test print -->
              <div v-if="qzTray.isConnected.value && terminalSettingsForm.printer_name">
                <button
                  type="button"
                  @click="testPrint"
                  :disabled="testPrinting"
                  class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-dashed border-gray-300 dark:border-gray-600 text-sm text-gray-500 dark:text-gray-400 hover:border-orange-400 hover:text-orange-500 transition disabled:opacity-50"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/>
                  </svg>
                  {{ testPrinting ? 'Impression en cours...' : 'Test d\'impression' }}
                </button>
              </div>

            </div>

            <!-- Drawer footer -->
            <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-700 flex gap-3">
              <button
                @click="showTerminalSettings = false"
                class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-xl hover:bg-gray-200 transition"
              >
                Annuler
              </button>
              <button
                @click="saveTerminalSettings"
                :disabled="savingTerminalSettings"
                class="flex-1 px-4 py-2.5 text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600 rounded-xl transition disabled:opacity-60"
              >
                {{ savingTerminalSettings ? 'Enregistrement...' : 'Enregistrer' }}
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>

  <!-- Variant Selection Modal -->
  <Teleport to="body">
    <div v-if="variantModalProduct" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="variantModalProduct = null" />
      <!-- Sheet -->
      <div class="relative w-full sm:max-w-sm bg-white dark:bg-gray-800 rounded-t-2xl sm:rounded-2xl shadow-xl p-5 z-10">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <div>
            <p class="text-xs text-gray-400 mb-0.5">Choisir une variante</p>
            <p class="text-sm font-semibold text-gray-900 dark:text-white line-clamp-1">{{ variantModalProduct.p_title }}</p>
          </div>
          <button @click="variantModalProduct = null" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <!-- Variant list -->
        <div class="space-y-2 max-h-72 overflow-y-auto">
          <button
            v-for="v in variantModalProduct.variants"
            :key="v.id"
            @click="selectVariant(v.id, v.label)"
            :disabled="v.stock <= 0"
            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl border transition text-left"
            :class="v.stock > 0
              ? 'border-gray-200 dark:border-gray-600 hover:border-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/20'
              : 'border-gray-100 dark:border-gray-700 opacity-50 cursor-not-allowed'"
          >
            <div>
              <p class="text-sm font-medium text-gray-900 dark:text-white">{{ v.label }}</p>
              <p v-if="v.sku" class="text-[10px] text-gray-400">{{ v.sku }}</p>
            </div>
            <div class="text-right shrink-0 ml-3">
              <p class="text-sm font-bold text-orange-500">{{ v.price ? formatPrice(v.price) : formatPrice(variantModalProduct.p_salePrice) }}</p>
              <span class="text-[10px] px-1.5 py-0.5 rounded-full" :class="v.stock > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'">
                {{ v.stock > 0 ? `Stock: ${v.stock}` : 'Rupture' }}
              </span>
            </div>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { usePosStore, type DraftTicket } from '@/stores/pos/posStore'
import { useBarcodeScanner } from '@/composables/useBarcodeScanner'
import { useQzTray } from '@/composables/useQzTray'
import http from '@/services/http'
import { useNumberFormat } from "@/composables/useNumberFormat"
import { useFormat } from '@/composables/useFormat'
import { useSettingStore } from '@/stores/setting'
import PosCheckout from './PosCheckout.vue'
import CartItemModal from "@/components/CartItemModal.vue"

interface PosCustomer {
  id: number
  tp_title: string
  tp_phone: string | null
  tp_email: string | null
  type_compte: 'normal' | 'en_compte'
  encours_actuel: number
  seuil_credit: number
  price_list_id: number | null
}

const router = useRouter()
function openEditModal(item: any) {
  editingItem.value = item
  showEditModal.value = true
}

function closeEditModal() {
  showEditModal.value = false
  editingItem.value = null
}

function saveEditModal(data: any) {
  if (editingItem.value) {
    editingItem.value.unit_price = data.price
    editingItem.value.quantity = data.quantity
    editingItem.value.discount_percent = data.discount
  }
  closeEditModal()
}
const posStore = usePosStore()
const settingStore = useSettingStore()
const { formatPrice, formatQuantity } = useNumberFormat()
const { date: fmtDate } = useFormat()
const qzTray = useQzTray()

const currentUrl = computed(() => window.location.origin + '/pos/main')

const isTaxEnabled = computed(() => {
  const val = settingStore.settings?.invoice?.tax_enabled
  return val === undefined || val === 'true'
})

const mobileView = ref<'products' | 'cart'>('products')
const showEditModal = ref(false)
const editingItem = ref<any>(null)
const categories = ref<{ id: number; ctg_title: string }[]>([])
const showCheckout = ref(false)
const checkoutMethod = ref('cash')
const showHeldDropdown = ref(false)

// ── Terminal settings drawer ─────────────────────────────────────
const showTerminalSettings = ref(false)
const savingTerminalSettings = ref(false)
const testPrinting = ref(false)
const terminalSettingsForm = ref({ printer_name: '', auto_print: true })

watch(showTerminalSettings, (open) => {
  if (open && posStore.currentTerminal) {
    terminalSettingsForm.value.printer_name = posStore.currentTerminal.printer_name ?? ''
    terminalSettingsForm.value.auto_print = posStore.currentTerminal.auto_print ?? true
  }
})

async function saveTerminalSettings() {
  if (!posStore.currentTerminal) return
  savingTerminalSettings.value = true
  try {
    const { data } = await http.put(`/pos/terminals/${posStore.currentTerminal.id}`, {
      name: posStore.currentTerminal.name,
      code: posStore.currentTerminal.code,
      warehouse_id: posStore.currentTerminal.warehouse_id,
      is_active: posStore.currentTerminal.is_active,
      printer_name: terminalSettingsForm.value.printer_name || null,
      auto_print: terminalSettingsForm.value.auto_print,
    })
    // Update the store's currentTerminal in-place
    Object.assign(posStore.currentTerminal, data)
    showTerminalSettings.value = false
  } catch (e) {
    console.error('Erreur sauvegarde terminal:', e)
  } finally {
    savingTerminalSettings.value = false
  }
}

async function testPrint() {
  testPrinting.value = true
  try {
    const testHtml = `<html><body style="font-family:monospace;text-align:center;padding:20px">
      <h2>Test d'impression</h2>
      <p>Terminal : ${posStore.currentTerminal?.name}</p>
      <p>Imprimante : ${terminalSettingsForm.value.printer_name}</p>
      <p>${fmtDate(new Date())} ${new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}</p>
      <p>— O3 POS —</p>
    </body></html>`
    const sent = await qzTray.printHtml(terminalSettingsForm.value.printer_name, testHtml)
    if (!sent) alert('Impression échouée. Vérifiez que QZ Tray est connecté et que le nom de l\'imprimante est correct.')
  } finally {
    testPrinting.value = false
  }
}
let searchTimeout: ReturnType<typeof setTimeout> | null = null

// ── Live session stats (ventes par mode de paiement) ────────────
interface SessionStats {
  total_tickets: number
  cancelled_tickets: number
  total_ttc: number
  total_ht: number
  total_tax: number
  total_paid: number
  total_credit: number
  payments_by_method: Record<string, number>
  tickets_count_by_method: Record<string, number>
}
const sessionStats = ref<SessionStats | null>(null)
const showSessionStats = ref(false)
const refreshingStats = ref(false)

const PAYMENT_METHOD_META: Record<string, { title: string; bg: string; dot: string; label: string; muted: string }> = {
  cash:   { title: 'Espèces',   bg: 'bg-green-50 dark:bg-green-900/20',   dot: 'bg-green-500',   label: 'text-green-700 dark:text-green-400',   muted: 'text-green-600/70 dark:text-green-400/60' },
  card:   { title: 'Carte',     bg: 'bg-orange-50 dark:bg-orange-900/20',     dot: 'bg-orange-500',    label: 'text-orange-600 dark:text-orange-400',     muted: 'text-orange-500/70 dark:text-orange-400/60' },
  credit: { title: 'En compte', bg: 'bg-amber-50 dark:bg-amber-900/20',   dot: 'bg-amber-500',   label: 'text-amber-700 dark:text-amber-400',   muted: 'text-amber-600/70 dark:text-amber-400/60' },
  cheque: { title: 'Chèque',    bg: 'bg-purple-50 dark:bg-purple-900/20', dot: 'bg-purple-500',  label: 'text-purple-700 dark:text-purple-400', muted: 'text-purple-600/70 dark:text-purple-400/60' },
  virement: { title: 'Virement', bg: 'bg-indigo-50 dark:bg-indigo-900/20', dot: 'bg-indigo-500', label: 'text-indigo-700 dark:text-indigo-400', muted: 'text-indigo-600/70 dark:text-indigo-400/60' },
}

const paymentMethodRows = computed(() => {
  const amounts = sessionStats.value?.payments_by_method ?? {}
  const counts = sessionStats.value?.tickets_count_by_method ?? {}
  // Always show cash/card/credit (the three primary buttons), plus any other method observed.
  const keys = new Set<string>(['cash', 'card', 'credit'])
  Object.keys(amounts).forEach((k) => keys.add(k))
  return Array.from(keys).map((method) => {
    const meta = PAYMENT_METHOD_META[method] ?? {
      title: method, bg: 'bg-gray-50 dark:bg-gray-900/40', dot: 'bg-gray-400',
      label: 'text-gray-700 dark:text-gray-300', muted: 'text-gray-500',
    }
    return {
      method,
      ...meta,
      amount: amounts[method] ?? 0,
      count: counts[method] ?? 0,
    }
  })
})

const hasAnySales = computed(() => (sessionStats.value?.total_tickets ?? 0) > 0)

async function fetchSessionStats(): Promise<void> {
  const sid = posStore.currentSession?.id
  if (!sid) {
    sessionStats.value = null
    return
  }
  try {
    const { data } = await http.get<{ stats: SessionStats }>(`/pos/sessions/${sid}/live-stats`)
    sessionStats.value = data?.stats ?? null
  } catch {
    // Non-fatal — leave previous stats in place.
  }
}

function openSessionStats(): void {
  showSessionStats.value = true
  // Refresh on open so the cashier always sees current numbers.
  fetchSessionStats()
}

async function refreshSessionStats(): Promise<void> {
  refreshingStats.value = true
  try {
    await fetchSessionStats()
  } finally {
    refreshingStats.value = false
  }
}

let statsInterval: ReturnType<typeof setInterval> | null = null

// Customer state
const selectedCustomer = ref<PosCustomer | null>(null)
const customerSearch = ref('')
const customerResults = ref<PosCustomer[]>([])
const showCustomerDropdown = ref(false)
const showNewCustomerModal = ref(false)
const creatingCustomer = ref(false)
let customerSearchTimeout: ReturnType<typeof setTimeout> | null = null

const newCustomer = ref({
  tp_title: '',
  tp_phone: '',
  tp_email: '',
  type_compte: 'normal' as 'normal' | 'en_compte',
  seuil_credit: 0,
})

const isEnCompteCustomer = computed(() => selectedCustomer.value?.type_compte === 'en_compte')


function selectCategory(id: number | null) {
  posStore.selectedCategoryId = id
  posStore.fetchProducts()
}

function debouncedSearch() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => posStore.fetchProducts(), 300)
}

// ── Customer methods ────────────────────────────────────────────

function debouncedCustomerSearch() {
  if (customerSearchTimeout) clearTimeout(customerSearchTimeout)
  customerSearchTimeout = setTimeout(() => searchCustomers(), 300)
}

async function searchCustomers() {
  if (!customerSearch.value.trim()) {
    customerResults.value = []
    return
  }
  try {
    const { data } = await http.get<PosCustomer[]>('/pos/customers', {
      params: { search: customerSearch.value },
    })
    customerResults.value = Array.isArray(data) ? data : []
    showCustomerDropdown.value = true
  } catch {
    customerResults.value = []
  }
}

function selectCustomer(customer: PosCustomer) {
  selectedCustomer.value = customer
  posStore.activeCustomer = customer
  customerSearch.value = ''
  customerResults.value = []
  showCustomerDropdown.value = false
  // Re-price existing cart lines using the new customer's price list.
  posStore.refreshPricesForCustomer(customer.id)
}

function clearCustomer() {
  selectedCustomer.value = null
  posStore.activeCustomer = null
  // Reset prices to the base (no customer → default/fallback).
  posStore.refreshPricesForCustomer(null)
}

// ── Held tickets ────────────────────────────────────────────────

function draftTotalTtc(draft: DraftTicket): number {
  return draft.cart.reduce((sum, item) => {
    const ht = item.quantity * item.unit_price * (1 - item.discount_percent / 100)
    return sum + ht * (1 + item.tax_percent / 100)
  }, 0)
}

function parkCurrent() {
  if (!posStore.cart.length) return
  posStore.parkCurrentTicket(selectedCustomer.value)
  selectedCustomer.value = null
  showHeldDropdown.value = false
}

function switchToDraft(id: string) {
  const draft = posStore.restoreDraftTicket(id)
  if (draft) {
    selectedCustomer.value = draft.customer as PosCustomer | null
  }
  showHeldDropdown.value = false
}

function discardDraft(id: string) {
  if (!confirm('Supprimer ce ticket en attente ?')) return
  posStore.discardDraftTicket(id)
}

async function createCustomer() {
  if (!newCustomer.value.tp_title.trim()) return
  creatingCustomer.value = true
  try {
    const { data } = await http.post<PosCustomer>('/pos/customers', {
      tp_title: newCustomer.value.tp_title,
      tp_phone: newCustomer.value.tp_phone || null,
      tp_email: newCustomer.value.tp_email || null,
      type_compte: newCustomer.value.type_compte,
      seuil_credit: newCustomer.value.type_compte === 'en_compte' ? newCustomer.value.seuil_credit : 0,
    })
    selectedCustomer.value = data
    showNewCustomerModal.value = false
    // Reset form
    newCustomer.value = { tp_title: '', tp_phone: '', tp_email: '', type_compte: 'normal', seuil_credit: 0 }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message ?? 'Erreur lors de la création du client')
  } finally {
    creatingCustomer.value = false
  }
}

// ── Payment ─────────────────────────────────────────────────────

function payWith(method: string) {
  checkoutMethod.value = method
  showCheckout.value = true
}

function payWithCredit() {
  if (!selectedCustomer.value) {
    alert('Veuillez sélectionner un client avant de payer en compte.')
    return
  }
  if (selectedCustomer.value.type_compte !== 'en_compte') {
    alert('Ce client n\'est pas un client "en compte". Seuls les clients en compte peuvent payer à crédit.')
    return
  }
  payWith('credit')
}

async function confirmPayment(payments: { amount: number; method: string }[]) {
  try {
    const ticket = await posStore.checkout(payments, selectedCustomer.value?.id) as any
    
    // Print the ticket receipt (respect terminal auto_print setting; default true)
    if (ticket?.id && posStore.currentTerminal?.auto_print !== false) {
      setTimeout(() => printTicketReceipt(ticket.id), 500)
    }
    showCheckout.value = false
    selectedCustomer.value = null
    // Refresh the live "ventes par mode de paiement" panel so the
    // cashier sees the new ticket immediately reflected in the totals.
    fetchSessionStats()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message ?? 'Erreur lors du paiement')
  }
}

async function printTicketReceipt(ticketId: number) {
  try {
    // 1 — Fetch the HTML receipt with the Bearer token
    const response = await http.get(`/pos/tickets/${ticketId}/print-html`, {
      responseType: 'text',
    })
    const html = response.data as string

    // 2 — Try QZ Tray direct print if connected and terminal has a printer configured
    const printerName = posStore.currentTerminal?.printer_name
    if (printerName && qzTray.isConnected.value) {
      const sent = await qzTray.printHtml(printerName, html)
      if (sent) return  // success — no browser dialog needed
    }

    // 3 — Fallback: browser iframe print dialog
    const blob    = new Blob([html], { type: 'text/html; charset=utf-8' })
    const blobUrl = URL.createObjectURL(blob)

    const iframe = document.createElement('iframe')
    iframe.style.cssText = 'position:fixed;width:1px;height:1px;top:-9999px;left:-9999px;border:0;'

    iframe.addEventListener('load', () => {
      setTimeout(() => {
        try {
          iframe.contentWindow?.focus()
          iframe.contentWindow?.print()
        } catch (e) {
          console.warn('Print error:', e)
        }
      }, 300)
      setTimeout(() => {
        URL.revokeObjectURL(blobUrl)
        if (document.body.contains(iframe)) document.body.removeChild(iframe)
      }, 5000)
    })

    document.body.appendChild(iframe)
    iframe.src = blobUrl

  } catch (e) {
    console.error('Erreur impression ticket:', e)
  }
}
  
// Barcode scanner
/**
 * Wrapper around posStore.addToCart that re-prices the line using the
 * currently selected customer's price list (if any). Without this,
 * lines added after a customer is picked would keep the product base
 * price.
 */
// ── Variant selection modal ───────────────────────────────────────────────
const variantModalProduct = ref<(typeof posStore.products)[0] | null>(null)

function handleProductClick(product: (typeof posStore.products)[0]) {
  if (product.variants && product.variants.length > 0) {
    variantModalProduct.value = product
  } else {
    addToCart(product)
  }
}

function selectVariant(variantId: number, variantLabel: string) {
  if (!variantModalProduct.value) return
  posStore.addToCart(variantModalProduct.value, variantId, variantLabel)
  if (selectedCustomer.value) {
    posStore.refreshPricesForCustomer(selectedCustomer.value.id)
  }
  variantModalProduct.value = null
}

function addToCart(product: Parameters<typeof posStore.addToCart>[0]) {
  posStore.addToCart(product)
  if (selectedCustomer.value) {
    posStore.refreshPricesForCustomer(selectedCustomer.value.id)
  }
}

useBarcodeScanner(async (code: string) => {
  posStore.searchQuery = code
  await posStore.fetchProducts()
  // Auto-add if exactly one match
  if (posStore.products.length === 1) {
    addToCart(posStore.products[0])
    posStore.searchQuery = ''
  }
})

// Close customer dropdown on outside click
function handleDocumentClick(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (!target.closest('.relative')) {
    showCustomerDropdown.value = false
    showHeldDropdown.value = false
  }
}

onMounted(async () => {
  if (!posStore.hasOpenSession) {
    await posStore.fetchCurrentSession()
    if (!posStore.hasOpenSession) {
      router.replace('/pos')
      return
    }
  }

  // Fetch categories
  const { data } = await http.get<{ id: number; ctg_title: string }[]>('/categories?per_page=100')
  categories.value = Array.isArray(data) ? data : (data as unknown as { data: typeof categories.value }).data ?? []

  await posStore.fetchProducts()

  // Try to connect to QZ Tray for direct printer access (silently, no error if absent)
  qzTray.connect()

  // Initial load + periodic refresh of the live session stats panel,
  // so the cashier always has up-to-date "ventes par mode de paiement".
  fetchSessionStats()
  statsInterval = setInterval(fetchSessionStats, 30_000)

  document.addEventListener('click', handleDocumentClick)

  // ── Wake Lock: prevent screen sleep on POS terminal ──────────────
  let wakeLock: WakeLockSentinel | null = null
  async function requestWakeLock() {
    try {
      if ('wakeLock' in navigator) {
        wakeLock = await (navigator as any).wakeLock.request('screen')
        wakeLock.addEventListener('release', () => {
          // Screen was unlocked (e.g. tab hidden) — re-acquire when visible again
        })
      }
    } catch { /* not supported or denied — silent */ }
  }
  requestWakeLock()
  // Re-acquire wake lock when the tab becomes visible again
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') requestWakeLock()
  })

  // ── Block browser Back button on POS ─────────────────────────────
  // Push a dummy history entry so Back brings user back to POS instead of leaving
  history.pushState(null, '', location.href)
  window.addEventListener('popstate', () => {
    history.pushState(null, '', location.href)
  })
})

onBeforeUnmount(() => {
  if (statsInterval) {
    clearInterval(statsInterval)
    statsInterval = null
  }
  document.removeEventListener('click', handleDocumentClick)
})
</script>
