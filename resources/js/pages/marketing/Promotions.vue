<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Marketing</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Gérez vos promotions et bannières du site eCom</p>
      </div>
      <button
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition"
        @click="activeTab === 'promos' ? openCreatePromo() : openCreateSlide()"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
        {{ activeTab === 'promos' ? 'Nouvelle Promotion' : 'Nouvelle Bannière' }}
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1 w-fit">
      <button
        @click="activeTab = 'promos'"
        class="px-4 py-2 text-sm font-medium rounded-md transition"
        :class="activeTab === 'promos' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
      >
        Promotions
        <span v-if="promotions.length" class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">{{ pagination.total || promotions.length }}</span>
      </button>
      <button
        @click="activeTab = 'slides'"
        class="px-4 py-2 text-sm font-medium rounded-md transition"
        :class="activeTab === 'slides' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
      >
        Bannières
        <span v-if="slides.length" class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">{{ slides.length }}</span>
      </button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         TAB: Promotions
         ═══════════════════════════════════════════════════════════════ -->
    <template v-if="activeTab === 'promos'">
      <!-- Filters -->
      <div class="flex flex-wrap items-center gap-3">
        <input
          v-model="search"
          type="text"
          placeholder="Rechercher une promotion..."
          class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 w-64"
        />
        <select
          v-model="statusFilter"
          class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">Tous</option>
          <option value="active">Actives</option>
          <option value="inactive">Inactives / Expirées</option>
        </select>
      </div>

      <!-- Table -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-900/50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Promotion</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Type</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Valeur</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Période</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Produits</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Statut</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="loading" v-for="i in 5" :key="i">
              <td colspan="7" class="px-4 py-3"><div class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div></td>
            </tr>
            <tr v-else-if="!promotions.length">
              <td colspan="7" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">Aucune promotion trouvée</td>
            </tr>
            <tr v-for="promo in promotions" :key="promo.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
              <td class="px-4 py-3">
                <div class="font-medium text-gray-900 dark:text-white">{{ promo.name }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ promo.slug }}</div>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                  :class="promo.type === 'percentage' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300'">
                  {{ promo.type === 'percentage' ? 'Pourcentage' : 'Montant fixe' }}
                </span>
              </td>
              <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                {{ promo.type === 'percentage' ? promo.value + '%' : promo.value + ' MAD' }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                <div v-if="promo.starts_at || promo.ends_at">
                  <div v-if="promo.starts_at">Du {{ formatDate(promo.starts_at) }}</div>
                  <div v-if="promo.ends_at">Au {{ formatDate(promo.ends_at) }}</div>
                </div>
                <span v-else class="text-gray-400">Permanente</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 text-xs font-bold">
                  {{ promo.products_count }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="isActive(promo) ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'">
                  {{ isActive(promo) ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button @click="openEditPromo(promo)" class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition" title="Modifier">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                  </button>
                  <button @click="toggleActive(promo)" class="p-1.5 rounded-lg transition" :class="promo.is_active ? 'text-yellow-600 hover:bg-yellow-50 dark:hover:bg-yellow-900/20' : 'text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20'" :title="promo.is_active ? 'Désactiver' : 'Activer'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="promo.is_active ? 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636' : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'" /></svg>
                  </button>
                  <button @click="confirmDeletePromo(promo)" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition" title="Supprimer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="flex items-center justify-between">
        <p class="text-sm text-gray-500 dark:text-gray-400">
          {{ pagination.from }}–{{ pagination.to }} sur {{ pagination.total }}
        </p>
        <div class="flex gap-1">
          <button v-for="p in pagination.last_page" :key="p"
            @click="page = p"
            class="px-3 py-1.5 text-sm rounded-lg transition"
            :class="p === page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'">
            {{ p }}
          </button>
        </div>
      </div>
    </template>

    <!-- ═══════════════════════════════════════════════════════════════
         TAB: Bannières (Slides)
         ═══════════════════════════════════════════════════════════════ -->
    <template v-if="activeTab === 'slides'">
      <!-- Position tabs -->
      <div class="flex gap-2">
        <button v-for="pos in positions" :key="pos.value"
          @click="positionFilter = pos.value"
          class="px-4 py-2 text-sm font-medium rounded-lg transition"
          :class="positionFilter === pos.value ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
        >
          {{ pos.label }}
        </button>
      </div>

      <!-- Slides grid -->
      <div v-if="slidesLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="i in 3" :key="i" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden animate-pulse">
          <div class="h-40 bg-gray-200 dark:bg-gray-700"></div>
          <div class="p-4 space-y-2">
            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
          </div>
        </div>
      </div>

      <div v-else-if="!slides.length" class="text-center py-12 text-gray-400 dark:text-gray-500">
        <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a1.5 1.5 0 001.5-1.5V5.25a1.5 1.5 0 00-1.5-1.5H3.75a1.5 1.5 0 00-1.5 1.5V19.5a1.5 1.5 0 001.5 1.5z" /></svg>
        <p>Aucune bannière pour cette position</p>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="slide in slides" :key="slide.id"
          class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm hover:shadow-md transition group"
          :class="{ 'opacity-50': !slide.is_active }"
        >
          <div class="relative h-40 bg-gray-100 dark:bg-gray-900 overflow-hidden">
            <img v-if="slide.image" :src="slide.image" :alt="slide.title" class="w-full h-full object-cover" @error="($event.target as HTMLImageElement).style.display='none'">
            <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
              <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a1.5 1.5 0 001.5-1.5V5.25a1.5 1.5 0 00-1.5-1.5H3.75a1.5 1.5 0 00-1.5 1.5V19.5a1.5 1.5 0 001.5 1.5z" /></svg>
            </div>
            <span class="absolute top-2 left-2 px-2 py-0.5 text-xs font-bold rounded bg-black/60 text-white">#{{ slide.sort_order }}</span>
            <span class="absolute top-2 right-2 px-2 py-0.5 text-xs font-medium rounded-full"
              :class="slide.is_active ? 'bg-green-500 text-white' : 'bg-gray-500 text-white'">
              {{ slide.is_active ? 'Actif' : 'Inactif' }}
            </span>
            <span v-if="slide.link_type !== 'none'" class="absolute bottom-2 left-2 px-2 py-0.5 text-xs font-medium rounded bg-blue-600 text-white">
              {{ linkTypeLabels[slide.link_type] || slide.link_type }}
            </span>
          </div>
          <div class="p-4">
            <h4 class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ slide.title }}</h4>
            <p v-if="slide.subtitle" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ slide.subtitle }}</p>
            <p v-if="slide.button_text" class="text-xs text-blue-600 dark:text-blue-400 mt-1">{{ slide.button_text }}</p>
            <div v-if="slide.starts_at || slide.ends_at" class="text-xs text-gray-400 mt-2">
              <span v-if="slide.starts_at">Du {{ formatDate(slide.starts_at) }}</span>
              <span v-if="slide.ends_at"> au {{ formatDate(slide.ends_at) }}</span>
            </div>
            <div class="flex items-center justify-end gap-1 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
              <button @click="moveUp(slide)" :disabled="slide.sort_order <= 0" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition disabled:opacity-30">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
              </button>
              <button @click="moveDown(slide)" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
              </button>
              <button @click="openEditSlide(slide)" class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
              </button>
              <button @click="confirmDeleteSlide(slide)" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ═══════════════════════════════════════════════════════════════
         MODAL: Create/Edit Promotion
         ═══════════════════════════════════════════════════════════════ -->
    <div v-if="showPromoModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showPromoModal = false">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto mx-4">
        <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ editingPromo ? 'Modifier la Promotion' : 'Nouvelle Promotion' }}</h3>
          <button @click="showPromoModal = false" class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
        <form @submit.prevent="savePromo" class="p-5 space-y-0">

          <!-- ── Section steps ── -->
          <div class="flex gap-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1 mb-5">
            <button type="button" @click="promoStep = 'info'"
              class="flex-1 px-3 py-2 text-sm font-medium rounded-md transition flex items-center justify-center gap-1.5"
              :class="promoStep === 'info' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400'">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              Informations
            </button>
            <button type="button" @click="promoStep = 'slide'"
              class="flex-1 px-3 py-2 text-sm font-medium rounded-md transition flex items-center justify-center gap-1.5"
              :class="promoStep === 'slide' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400'">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
              Slide
            </button>
            <button type="button" @click="promoStep = 'products'"
              class="flex-1 px-3 py-2 text-sm font-medium rounded-md transition flex items-center justify-center gap-1.5"
              :class="promoStep === 'products' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400'">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
              Produits
              <span v-if="promoForm.product_ids.length" class="px-1.5 py-0.5 text-xs rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">{{ promoForm.product_ids.length }}</span>
            </button>
          </div>

          <!-- ── Step 1: Informations ── -->
          <div v-show="promoStep === 'info'" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom *</label>
                <input v-model="promoForm.name" type="text" required class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="Ex: Soldes d'été -20%">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type *</label>
                <select v-model="promoForm.type" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                  <option value="percentage">Pourcentage (%)</option>
                  <option value="fixed_amount">Montant fixe (MAD)</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valeur *</label>
                <div class="relative">
                  <input v-model.number="promoForm.value" type="number" step="0.01" min="0" required class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                  <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">{{ promoForm.type === 'percentage' ? '%' : 'MAD' }}</span>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date début</label>
                <div class="flex gap-2">
                  <input :value="promoForm.starts_at?.slice(0, 10)" @input="promoForm.starts_at = ($event.target as HTMLInputElement).value + 'T' + (promoForm.starts_at?.slice(11, 16) || '00:00')" type="date" class="flex-1 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                  <input :value="promoForm.starts_at?.slice(11, 16)" @input="promoForm.starts_at = (promoForm.starts_at?.slice(0, 10) || '') + 'T' + ($event.target as HTMLInputElement).value" type="time" class="w-28 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date fin</label>
                <div class="flex gap-2">
                  <input :value="promoForm.ends_at?.slice(0, 10)" @input="promoForm.ends_at = ($event.target as HTMLInputElement).value + 'T' + (promoForm.ends_at?.slice(11, 16) || '23:59')" type="date" class="flex-1 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                  <input :value="promoForm.ends_at?.slice(11, 16)" @input="promoForm.ends_at = (promoForm.ends_at?.slice(0, 10) || '') + 'T' + ($event.target as HTMLInputElement).value" type="time" class="w-28 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Remise max (MAD)</label>
                <input v-model.number="promoForm.max_discount" type="number" step="0.01" min="0" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="Optionnel">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priorité</label>
                <input v-model.number="promoForm.priority" type="number" min="0" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="0">
              </div>
              <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                <textarea v-model="promoForm.description" rows="2" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="Description optionnelle..."></textarea>
              </div>
            </div>
          </div>

          <!-- ── Step 2: Slide / Bannière ── -->
          <div v-show="promoStep === 'slide'" class="space-y-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Image affichée sur le site eCom pour illustrer cette promotion (hero, bannière, etc.)</p>

            <!-- File upload -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Télécharger une image</label>
              <label class="flex items-center justify-center gap-2 px-4 py-6 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition bg-gray-50 dark:bg-gray-900/30">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ promoBannerFile ? promoBannerFile.name : 'Cliquez pour choisir une image (JPG, PNG, WebP)' }}</span>
                <input type="file" accept="image/*" class="hidden" @change="onPromoBannerSelected">
              </label>
            </div>

            <!-- Preview -->
            <div v-if="promoBannerPreview || promoForm.banner_image" class="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700">
              <div class="relative h-44 bg-gray-100 dark:bg-gray-900">
                <img :src="promoBannerPreview || promoForm.banner_image" class="w-full h-full object-cover" @error="($event.target as HTMLImageElement).style.display='none'">
                <button type="button" @click="clearPromoBanner" class="absolute top-2 right-2 p-1.5 rounded-full bg-red-500 text-white hover:bg-red-600 transition shadow" title="Supprimer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>
            </div>

            <!-- Or URL -->
            <div class="relative">
              <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200 dark:border-gray-700"></div></div>
              <div class="relative flex justify-center"><span class="px-3 bg-white dark:bg-gray-800 text-xs text-gray-400 uppercase">ou coller une URL</span></div>
            </div>
            <input v-model="promoForm.banner_image" type="text" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="https://exemple.com/banner.jpg">
          </div>

          <!-- ── Step 3: Produits ── -->
          <div v-show="promoStep === 'products'" class="space-y-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">Sélectionnez les produits concernés par cette promotion. Vous pouvez forcer un prix promo spécifique par produit.</p>
            <div class="relative">
              <input
                v-model="productSearch"
                type="text"
                placeholder="Rechercher un produit à ajouter..."
                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500"
                @input="searchProducts"
              />
              <div v-if="productResults.length" class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                <button
                  v-for="p in productResults"
                  :key="p.id"
                  type="button"
                  class="w-full px-3 py-2 text-left text-sm hover:bg-blue-50 dark:hover:bg-blue-900/20 flex items-center justify-between"
                  @click="addProduct(p)"
                >
                  <span class="text-gray-900 dark:text-white">{{ p.p_title }}</span>
                  <span class="text-xs text-gray-400 font-mono">{{ p.p_code }} — {{ Number(p.p_salePrice).toFixed(2) }} MAD</span>
                </button>
              </div>
            </div>
            <div v-if="promoForm.product_ids.length" class="space-y-1.5 max-h-64 overflow-y-auto">
              <div v-for="(item, idx) in promoForm.product_ids" :key="item.id" class="flex items-center gap-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2">
                <span class="flex-1 text-sm text-gray-900 dark:text-white truncate">{{ item.title }}</span>
                <div class="flex items-center gap-2 shrink-0">
                  <input v-model.number="item.promo_price" type="number" step="0.01" min="0" class="w-28 px-2 py-1 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Prix forcé" />
                  <button type="button" @click="promoForm.product_ids.splice(idx, 1)" class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>
                </div>
              </div>
            </div>
            <p v-else class="text-center py-6 text-sm text-gray-400 dark:text-gray-500">Aucun produit sélectionné — la promotion ne s'appliquera à rien</p>
          </div>

          <!-- ── Footer actions ── -->
          <div class="flex items-center justify-end gap-3 pt-5 mt-5 border-t border-gray-200 dark:border-gray-700">
            <button type="button" @click="showPromoModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition">Annuler</button>
            <button type="submit" :disabled="saving" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition disabled:opacity-50">
              {{ saving ? 'Enregistrement...' : (editingPromo ? 'Mettre à jour' : 'Créer') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         MODAL: Create/Edit Slide (Bannière)
         ═══════════════════════════════════════════════════════════════ -->
    <div v-if="showSlideModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showSlideModal = false">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-xl max-h-[90vh] overflow-y-auto mx-4">
        <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ editingSlide ? 'Modifier la Bannière' : 'Nouvelle Bannière' }}</h3>
          <button @click="showSlideModal = false" class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
        <form @submit.prevent="saveSlide" class="p-5 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Titre *</label>
              <input v-model="slideForm.title" type="text" required class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sous-titre</label>
              <input v-model="slideForm.subtitle" type="text" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Image *</label>
              <label class="flex items-center justify-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ imageFile ? imageFile.name : 'Choisir une image...' }}</span>
                <input type="file" accept="image/*" class="hidden" @change="onFileSelected">
              </label>
              <div v-if="imagePreview || slideForm.image" class="mt-2 rounded-lg overflow-hidden h-32 bg-gray-100 dark:bg-gray-900">
                <img :src="imagePreview || slideForm.image" class="w-full h-full object-cover" @error="($event.target as HTMLImageElement).style.display='none'">
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Texte bouton</label>
              <input v-model="slideForm.button_text" type="text" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="Voir les offres">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Position *</label>
              <select v-model="slideForm.position" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                <option value="hero">Hero (principal)</option>
                <option value="sidebar">Sidebar</option>
                <option value="popup">Popup</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type de lien</label>
              <select v-model="slideForm.link_type" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                <option value="none">Aucun</option>
                <option value="promotion">Promotion</option>
                <option value="category">Catégorie</option>
                <option value="product">Produit</option>
                <option value="url">URL personnalisée</option>
              </select>
            </div>
            <div v-if="slideForm.link_type === 'url'">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">URL</label>
              <input v-model="slideForm.link_url" type="text" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" placeholder="https://...">
            </div>
            <div v-if="slideForm.link_type === 'promotion'">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Promotion</label>
              <select v-model.number="slideForm.link_id" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                <option :value="null">-- Sélectionner --</option>
                <option v-for="pr in linkPromotions" :key="pr.id" :value="pr.id">{{ pr.name }}</option>
              </select>
            </div>
            <div v-if="slideForm.link_type === 'category'">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Catégorie</label>
              <select v-model.number="slideForm.link_id" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                <option :value="null">-- Sélectionner --</option>
                <option v-for="c in linkCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div v-if="slideForm.link_type === 'product'">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Produit</label>
              <select v-model.number="slideForm.link_id" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
                <option :value="null">-- Sélectionner --</option>
                <option v-for="p in linkProducts" :key="p.id" :value="p.id">{{ p.p_name }} ({{ p.p_code }})</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ordre</label>
              <input v-model.number="slideForm.sort_order" type="number" min="0" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date début</label>
              <input v-model="slideForm.starts_at" type="date" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date fin</label>
              <input v-model="slideForm.ends_at" type="date" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <button type="button" @click="showSlideModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition">Annuler</button>
            <button type="submit" :disabled="saving" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition disabled:opacity-50">
              {{ saving ? 'Enregistrement...' : (editingSlide ? 'Mettre à jour' : 'Créer') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         MODAL: Delete confirmation (shared)
         ═══════════════════════════════════════════════════════════════ -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showDeleteModal = false">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-sm mx-4 p-6 text-center">
        <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
        </div>
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ deleteTarget.label }}</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">{{ deleteTarget.name }}</p>
        <div class="flex gap-3 justify-center">
          <button @click="showDeleteModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">Annuler</button>
          <button @click="executeDelete" :disabled="saving" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition disabled:opacity-50">Supprimer</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue'
import http from '@/services/http'

// ── Shared state ──────────────────────────────────────────────────
const activeTab = ref<'promos' | 'slides'>('promos')
const saving = ref(false)
const showDeleteModal = ref(false)
const deleteTarget = reactive({ type: '' as 'promo' | 'slide', id: 0, label: '', name: '' })

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

// ── PROMOTIONS ────────────────────────────────────────────────────
interface PromoProduct { id: number; title: string; promo_price: number | null }

const promotions = ref<any[]>([])
const loading = ref(false)
const search = ref('')
const statusFilter = ref('')
const page = ref(1)
const pagination = ref({ from: 0, to: 0, total: 0, last_page: 1 })
const showPromoModal = ref(false)
const editingPromo = ref<any>(null)
const productSearch = ref('')
const productResults = ref<any[]>([])
let searchTimeout: ReturnType<typeof setTimeout>

const promoStep = ref<'info' | 'slide' | 'products'>('info')
const promoBannerFile = ref<File | null>(null)
const promoBannerPreview = ref<string | null>(null)

const emptyPromoForm = () => ({
  name: '', type: 'percentage' as string, value: 0, description: '',
  banner_image: '', starts_at: '', ends_at: '', max_discount: null as number | null,
  priority: 0, product_ids: [] as PromoProduct[],
})
const promoForm = ref(emptyPromoForm())

function onPromoBannerSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) {
    promoBannerFile.value = file
    promoBannerPreview.value = URL.createObjectURL(file)
    promoForm.value.banner_image = '' // clear URL when file is selected
  }
}

function clearPromoBanner() {
  promoBannerFile.value = null
  promoBannerPreview.value = null
  promoForm.value.banner_image = ''
}

async function fetchPromotions() {
  loading.value = true
  try {
    const { data } = await http.get('/promotions', {
      params: { search: search.value || undefined, status: statusFilter.value || undefined, page: page.value },
    })
    promotions.value = data.data
    pagination.value = { from: data.from, to: data.to, total: data.total, last_page: data.last_page }
  } finally { loading.value = false }
}

function isActive(promo: any): boolean {
  if (!promo.is_active) return false
  if (promo.starts_at && new Date(promo.starts_at) > new Date()) return false
  if (promo.ends_at && new Date(promo.ends_at) < new Date()) return false
  return true
}

function openCreatePromo() {
  editingPromo.value = null
  promoForm.value = emptyPromoForm()
  promoStep.value = 'info'
  promoBannerFile.value = null
  promoBannerPreview.value = null
  showPromoModal.value = true
}

async function openEditPromo(promo: any) {
  editingPromo.value = promo
  const { data } = await http.get(`/promotions/${promo.id}`)
  promoForm.value = {
    name: data.name, type: data.type, value: Number(data.value),
    description: data.description || '', banner_image: data.banner_image || '',
    starts_at: data.starts_at ? data.starts_at.slice(0, 16) : '',
    ends_at: data.ends_at ? data.ends_at.slice(0, 16) : '',
    max_discount: data.max_discount ? Number(data.max_discount) : null,
    priority: data.priority || 0,
    product_ids: (data.products || []).map((p: any) => ({
      id: p.id, title: p.p_title,
      promo_price: p.pivot?.promo_price ? Number(p.pivot.promo_price) : null,
    })),
  }
  promoStep.value = 'info'
  promoBannerFile.value = null
  promoBannerPreview.value = null
  showPromoModal.value = true
}

async function savePromo() {
  saving.value = true
  try {
    const hasBannerFile = !!promoBannerFile.value

    if (hasBannerFile) {
      // Use FormData when file upload is needed
      const fd = new FormData()
      fd.append('name', promoForm.value.name)
      fd.append('type', promoForm.value.type)
      fd.append('value', String(promoForm.value.value))
      if (promoForm.value.description) fd.append('description', promoForm.value.description)
      if (promoForm.value.banner_image) fd.append('banner_image', promoForm.value.banner_image)
      if (promoForm.value.starts_at) fd.append('starts_at', promoForm.value.starts_at)
      if (promoForm.value.ends_at) fd.append('ends_at', promoForm.value.ends_at)
      if (promoForm.value.max_discount != null) fd.append('max_discount', String(promoForm.value.max_discount))
      fd.append('priority', String(promoForm.value.priority))
      fd.append('banner_image_file', promoBannerFile.value!)
      promoForm.value.product_ids.forEach((p, i) => {
        fd.append(`product_ids[${i}][id]`, String(p.id))
        if (p.promo_price) fd.append(`product_ids[${i}][promo_price]`, String(p.promo_price))
      })

      if (editingPromo.value) {
        fd.append('_method', 'PUT')
        await http.post(`/promotions/${editingPromo.value.id}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      } else {
        await http.post('/promotions', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      }
    } else {
      // JSON payload when no file
      const payload = {
        ...promoForm.value,
        starts_at: promoForm.value.starts_at || null,
        ends_at: promoForm.value.ends_at || null,
        product_ids: promoForm.value.product_ids.map(p => ({ id: p.id, promo_price: p.promo_price || null })),
      }
      if (editingPromo.value) {
        await http.put(`/promotions/${editingPromo.value.id}`, payload)
      } else {
        await http.post('/promotions', payload)
      }
    }

    showPromoModal.value = false
    fetchPromotions()
  } finally { saving.value = false }
}

async function toggleActive(promo: any) {
  await http.patch(`/promotions/${promo.id}`, { is_active: !promo.is_active })
  fetchPromotions()
}

function confirmDeletePromo(promo: any) {
  deleteTarget.type = 'promo'
  deleteTarget.id = promo.id
  deleteTarget.label = 'Supprimer la promotion ?'
  deleteTarget.name = promo.name
  showDeleteModal.value = true
}

function searchProducts() {
  clearTimeout(searchTimeout)
  if (productSearch.value.length < 2) { productResults.value = []; return }
  searchTimeout = setTimeout(async () => {
    const { data } = await http.get('/products', { params: { search: productSearch.value, per_page: 10 } })
    const existingIds = promoForm.value.product_ids.map(p => p.id)
    productResults.value = (data.data || data).filter((p: any) => !existingIds.includes(p.id))
  }, 300)
}

function addProduct(p: any) {
  promoForm.value.product_ids.push({ id: p.id, title: p.p_title, promo_price: null })
  productSearch.value = ''
  productResults.value = []
}

// ── SLIDES (Bannières) ────────────────────────────────────────────
const positions = [
  { value: '', label: 'Tous' },
  { value: 'hero', label: 'Hero' },
  { value: 'sidebar', label: 'Sidebar' },
  { value: 'popup', label: 'Popup' },
]
const linkTypeLabels: Record<string, string> = {
  promotion: 'Promotion', category: 'Catégorie', product: 'Produit', url: 'Lien',
}

const slides = ref<any[]>([])
const slidesLoading = ref(false)
const positionFilter = ref('')
const showSlideModal = ref(false)
const editingSlide = ref<any>(null)
const linkProducts = ref<any[]>([])
const linkCategories = ref<any[]>([])
const linkPromotions = ref<any[]>([])

const emptySlideForm = () => ({
  title: '', subtitle: '', image: '', button_text: '',
  link_type: 'none' as string, link_id: null as number | null, link_url: '',
  position: 'hero' as string, sort_order: 0, starts_at: '', ends_at: '', is_active: true,
})
const slideForm = ref(emptySlideForm())
const imageFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)

function onFileSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) { imageFile.value = file; imagePreview.value = URL.createObjectURL(file) }
}

async function fetchSlides() {
  slidesLoading.value = true
  try {
    const { data } = await http.get('/slides', { params: { position: positionFilter.value || undefined } })
    slides.value = Array.isArray(data) ? data : data.data
  } finally { slidesLoading.value = false }
}

async function fetchLinkOptions() {
  const [products, categories, promos] = await Promise.all([
    http.get('/products').catch(() => ({ data: [] })),
    http.get('/categories').catch(() => ({ data: [] })),
    http.get('/promotions').catch(() => ({ data: [] })),
  ])
  linkProducts.value = Array.isArray(products.data) ? products.data : products.data.data || []
  linkCategories.value = Array.isArray(categories.data) ? categories.data : categories.data.data || []
  linkPromotions.value = Array.isArray(promos.data) ? promos.data : promos.data.data || []
}

function openCreateSlide() {
  editingSlide.value = null
  slideForm.value = emptySlideForm()
  imageFile.value = null
  imagePreview.value = null
  showSlideModal.value = true
  fetchLinkOptions()
}

function openEditSlide(slide: any) {
  editingSlide.value = slide
  slideForm.value = {
    title: slide.title, subtitle: slide.subtitle || '', image: slide.image,
    button_text: slide.button_text || '', link_type: slide.link_type || 'none',
    link_id: slide.link_id, link_url: slide.link_url || '', position: slide.position,
    sort_order: slide.sort_order,
    starts_at: slide.starts_at ? slide.starts_at.slice(0, 10) : '',
    ends_at: slide.ends_at ? slide.ends_at.slice(0, 10) : '',
    is_active: slide.is_active,
  }
  imageFile.value = null
  imagePreview.value = null
  showSlideModal.value = true
  fetchLinkOptions()
}

async function saveSlide() {
  saving.value = true
  try {
    const fd = new FormData()
    fd.append('title', slideForm.value.title)
    fd.append('subtitle', slideForm.value.subtitle || '')
    fd.append('button_text', slideForm.value.button_text || '')
    fd.append('link_type', slideForm.value.link_type)
    fd.append('position', slideForm.value.position)
    fd.append('sort_order', String(slideForm.value.sort_order))
    fd.append('is_active', slideForm.value.is_active ? '1' : '0')
    if (slideForm.value.link_id) fd.append('link_id', String(slideForm.value.link_id))
    if (slideForm.value.link_url) fd.append('link_url', slideForm.value.link_url)
    if (slideForm.value.starts_at) fd.append('starts_at', slideForm.value.starts_at)
    if (slideForm.value.ends_at) fd.append('ends_at', slideForm.value.ends_at)
    if (imageFile.value) { fd.append('image', imageFile.value) }
    else if (slideForm.value.image) { fd.append('image', slideForm.value.image) }

    if (editingSlide.value) {
      fd.append('_method', 'PUT')
      await http.post(`/slides/${editingSlide.value.id}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    } else {
      await http.post('/slides', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    }
    showSlideModal.value = false
    fetchSlides()
  } finally { saving.value = false }
}

async function moveUp(slide: any) {
  const idx = slides.value.indexOf(slide)
  if (idx <= 0) return
  const prev = slides.value[idx - 1]
  await http.post('/slides/reorder', { slides: [{ id: slide.id, sort_order: prev.sort_order }, { id: prev.id, sort_order: slide.sort_order }] })
  fetchSlides()
}

async function moveDown(slide: any) {
  const idx = slides.value.indexOf(slide)
  if (idx >= slides.value.length - 1) return
  const next = slides.value[idx + 1]
  await http.post('/slides/reorder', { slides: [{ id: slide.id, sort_order: next.sort_order }, { id: next.id, sort_order: slide.sort_order }] })
  fetchSlides()
}

function confirmDeleteSlide(slide: any) {
  deleteTarget.type = 'slide'
  deleteTarget.id = slide.id
  deleteTarget.label = 'Supprimer la bannière ?'
  deleteTarget.name = slide.title
  showDeleteModal.value = true
}

// ── Shared delete ─────────────────────────────────────────────────
async function executeDelete() {
  saving.value = true
  try {
    if (deleteTarget.type === 'promo') {
      await http.delete(`/promotions/${deleteTarget.id}`)
      fetchPromotions()
    } else {
      await http.delete(`/slides/${deleteTarget.id}`)
      fetchSlides()
    }
    showDeleteModal.value = false
  } finally { saving.value = false }
}

// ── Watchers & init ───────────────────────────────────────────────
watch([search, statusFilter], () => { page.value = 1; fetchPromotions() })
watch(page, fetchPromotions)
watch(positionFilter, fetchSlides)

onMounted(() => {
  fetchPromotions()
  fetchSlides()
})
</script>
