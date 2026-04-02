import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

declare module 'vue-router' {
  interface RouteMeta {
    layout?: string
    title?: string
    breadcrumb?: string
    adminOnly?: boolean
    guest?: boolean
  }
}

const routes: RouteRecordRaw[] = [
  { path: '/', redirect: '/dashboard' },

  { path: '/landing', component: () => import('../pages/LandingPage.vue'), meta: { guest: true, layout: 'none' } },
  { path: '/login', component: () => import('../pages/auth/Login.vue'), meta: { guest: true } },

  {
    path: '/dashboard',
    component: () => import('../pages/Dashboard.vue'),
    meta: { layout: 'app', title: 'Dashboard', breadcrumb: 'Dashboard' },
  },
  {
    path: '/profile',
    component: () => import('../pages/Profile.vue'),
    meta: { layout: 'app', title: 'Mon Profil', breadcrumb: 'Profil' },
  },
  {
    path: '/products',
    component: () => import('../pages/Products.vue'),
    meta: { layout: 'app', title: 'Products', breadcrumb: 'Products' },
  },
  {
    path: '/categories',
    component: () => import('../pages/Categories.vue'),
    meta: { layout: 'app', title: 'Categories', breadcrumb: 'Categories' },
  },
  {
    path: '/brands',
    component: () => import('../pages/Brands.vue'),
    meta: { layout: 'app', title: 'Brands', breadcrumb: 'Brands' },
  },
  {
    path: '/warehouses',
    component: () => import('../pages/Warehouses.vue'),
    meta: { layout: 'app', title: 'Warehouses', breadcrumb: 'Warehouses' },
  },
  {
    path: '/customers',
    component: () => import('../pages/Customers.vue'),
    meta: { layout: 'app', title: 'Customers', breadcrumb: 'Customers' },
  },
  {
    path: '/suppliers',
    component: () => import('../pages/Suppliers.vue'),
    meta: { layout: 'app', title: 'Suppliers', breadcrumb: 'Suppliers' },
  },

  // Ventes
  {
    path: '/ventes/documents',
    component: () => import('../pages/ventes/DocumentsVente.vue'),
    meta: { layout: 'app', title: 'Documents de Vente', breadcrumb: 'Documents de Vente' },
  },
  {
    path: '/ventes/documents/create',
    component: () => import('../pages/ventes/DocumentCreateVente.vue'),
    meta: { layout: 'app', title: 'Nouveau Document Vente', breadcrumb: 'Nouveau' },
  },
  {
    path: '/ventes/documents/:id/edit',
    component: () => import('../pages/ventes/DocumentEditVente.vue'),
    meta: { layout: 'app', title: 'Modifier Document Vente', breadcrumb: 'Modifier' },
  },
  {
    path: '/ventes/documents/:id',
    component: () => import('../pages/ventes/DocumentVenteShow.vue'),
    meta: { layout: 'app', title: 'Document', breadcrumb: 'Détail' },
  },

  // Stock

  {
    path: '/stock/mouvements',
    component: () => import('../pages/stock/MouvementsStock.vue'),
    meta: { layout: 'app', title: 'Mouvements Stock', breadcrumb: 'Mouvements' },
  },
  {
    path: '/stock/documents',
    component: () => import('../pages/stock/DocumentsStock.vue'),
    meta: { layout: 'app', title: 'Documents de Stock', breadcrumb: 'Documents de Stock' },
  },
  {
    path: '/stock/documents/create',
    component: () => import('../pages/stock/DocumentCreateStock.vue'),
    meta: { layout: 'app', title: 'Nouveau Document Stock', breadcrumb: 'Nouveau' },
  },
  {
    path: '/stock/documents/:id',
    component: () => import('../pages/stock/DocumentStockShow.vue'),
    meta: { layout: 'app', title: 'Document Stock', breadcrumb: 'Détail' },
  },
  {
    path: '/stock/documents/:id/edit',
    component: () => import('../pages/stock/DocumentEditStock.vue'),
    meta: { layout: 'app', title: 'Modifier Document Stock', breadcrumb: 'Modifier' },
  },

  // Achats
  {
    path: '/achats/documents',
    component: () => import('../pages/achats/DocumentsAchat.vue'),
    meta: { layout: 'app', title: "Documents d'Achat", breadcrumb: "Documents d'Achat" },
  },
  {
    path: '/achats/documents/create',
    component: () => import('../pages/achats/DocumentCreateAchat.vue'),
    meta: { layout: 'app', title: 'Nouveau Document Achat', breadcrumb: 'Nouveau' },
  },
  {
    path: '/achats/documents/:id/edit',
    component: () => import('../pages/achats/DocumentEditAchat.vue'),
    meta: { layout: 'app', title: 'Modifier Document Achat', breadcrumb: 'Modifier' },
  },
  {
    path: '/achats/documents/:id',
    component: () => import('../pages/achats/DocumentAchatShow.vue'),
    meta: { layout: 'app', title: 'Document', breadcrumb: 'Détail' },
  },

  // Reports (admin, manager)
  {
    path: '/reports',
    component: () => import('../pages/Reports.vue'),
    meta: { layout: 'app', title: 'Rapports', breadcrumb: 'Rapports' },
  },

  // Storage Gallery
  {
    path: '/storage/gallery',
    component: () => import('../pages/StorageGallery.vue'),
    meta: { layout: 'app', title: 'Galerie Images', breadcrumb: 'Galerie Images' },
  },

  // Marketing & eCom
  {
    path: '/marketing/promotions',
    component: () => import('../pages/marketing/Promotions.vue'),
    meta: { layout: 'app', title: 'Promotions', breadcrumb: 'Promotions' },
  },
  {
    path: '/marketing/slides',
    component: () => import('../pages/marketing/Slides.vue'),
    meta: { layout: 'app', title: 'Slides & Bannières', breadcrumb: 'Slides' },
  },

  // POS (module-gated, layout: pos)
  {
    path: '/pos',
    component: () => import('../pages/pos/PosLogin.vue'),
    meta: { layout: 'pos', title: 'Point de Vente' },
  },
  {
    path: '/pos/main',
    component: () => import('../pages/pos/PosMain.vue'),
    meta: { layout: 'pos', title: 'POS' },
  },
  {
    path: '/pos/tickets',
    component: () => import('../pages/pos/PosTickets.vue'),
    meta: { layout: 'pos', title: 'Tickets POS' },
  },
  {
    path: '/pos/close',
    component: () => import('../pages/pos/PosSessionClose.vue'),
    meta: { layout: 'pos', title: 'Fermeture Session' },
  },

  // Settings (admin only)
  {
    path: '/settings/users',
    component: () => import('../pages/settings/Users.vue'),
    meta: { layout: 'app', title: 'Users', breadcrumb: 'Utilisateurs', adminOnly: true },
  },
  {
    path: '/settings/roles',
    component: () => import('../pages/settings/Roles.vue'),
    meta: { layout: 'app', title: 'Rôles & Permissions', breadcrumb: 'Rôles & Permissions', adminOnly: true },
  },
  {
    path: '/settings/structure-incrementors',
    component: () => import('../pages/settings/StructureIncrementors.vue'),
    meta: { layout: 'app', title: 'Structure Incrementors', breadcrumb: 'Numéroteurs Structure', adminOnly: true },
  },
  {
    path: '/settings/document-incrementors',
    component: () => import('../pages/settings/DocumentIncrementors.vue'),
    meta: { layout: 'app', title: 'Document Incrementors', breadcrumb: 'Numéroteurs Document', adminOnly: true },
  },
  {
    path: '/settings/app',
    component: () => import('../pages/settings/AppSettings.vue'),
    meta: { layout: 'app', title: 'App Settings', breadcrumb: 'Paramètres', adminOnly: true },
  },
  {
    path: '/settings/modules',
    component: () => import('../pages/settings/Modules.vue'),
    meta: { layout: 'app', title: 'Modules', breadcrumb: 'Modules', adminOnly: true },
  },
  {
    path: '/settings/pos-terminals',
    component: () => import('../pages/settings/PosTerminals.vue'),
    meta: { layout: 'app', title: 'Terminaux POS', breadcrumb: 'Terminaux POS', adminOnly: true },
  },
  {
    path: '/settings/pos-sessions',
    component: () => import('../pages/settings/PosSessions.vue'),
    meta: { layout: 'app', title: 'Sessions POS', breadcrumb: 'Sessions POS', adminOnly: true },
  },
  {
    path: '/settings/imports',
    component: () => import('../pages/settings/Imports.vue'),
    meta: { layout: 'app', title: 'Imports', breadcrumb: 'Imports', adminOnly: true },
  },
  {
    path: '/settings/activity-log',
    component: () => import('../pages/settings/ActivityLog.vue'),
    meta: { layout: 'app', title: "Piste d'audit", breadcrumb: "Piste d'audit", adminOnly: true },
  },

  // ── Central Admin (Tenant Management) ────────────────────────────
  {
    path: '/central/tenants',
    component: () => import('../pages/central/TenantList.vue'),
    meta: { layout: 'app', title: 'Gestion Clients', breadcrumb: 'Clients', adminOnly: true },
  },
  {
    path: '/central/tenants/create',
    component: () => import('../pages/central/TenantCreate.vue'),
    meta: { layout: 'app', title: 'Nouveau Client', breadcrumb: 'Nouveau Client', adminOnly: true },
  },
  {
    path: '/central/tenants/:id',
    component: () => import('../pages/central/TenantShow.vue'),
    meta: { layout: 'app', title: 'Détail Client', breadcrumb: 'Détail', adminOnly: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // ── 1. Auth check ──────────────────────────────────────────────
  const isAuthenticated = auth.isAuthenticated

  // Helper: check if on central domain
  const centralDomains = ['localhost', '127.0.0.1', import.meta.env.VITE_CENTRAL_DOMAIN].filter(Boolean)
  const onCentralDomain = centralDomains.includes(window.location.hostname)

  // Guest-only page (login, landing) — redirect after login
  if (to.meta.guest) {
    if (!isAuthenticated) return undefined
    // Central domain → redirect to tenant management
    if (onCentralDomain) {
      return { path: '/central/tenants' }
    }
    return { path: '/dashboard' }
  }

  // All other pages require authentication
  if (!isAuthenticated) {
    // On central domain, show landing page instead of login
    if (onCentralDomain && to.path !== '/login') {
      return { path: '/landing' }
    }
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  // ── 2. Ensure user data is loaded (after page refresh) ─────────
  if (!auth.user) {
    try {
      await auth.fetchMe()
    } catch {
      return { path: '/login' }
    }
    // If fetchMe failed silently (token invalid → cleared)
    if (!auth.isAuthenticated) {
      return { path: '/login' }
    }
  }

  // ── 3. Admin-only guard ────────────────────────────────────────
  if (to.meta.adminOnly && auth.userRole !== 'admin') {
    return { path: '/dashboard' }
  }

  // ── 3b. Central-only guard (tenant management) ─────────────────
  if (to.path.startsWith('/central') && !onCentralDomain) {
    return { path: '/dashboard' }
  }

  // On central domain, redirect non-central pages to tenant management
  if (onCentralDomain && to.path === '/dashboard') {
    return { path: '/central/tenants' }
  }

  // ── 4. POS module guard (frontend-side) ────────────────────────
  if (to.path.startsWith('/pos') && !auth.hasModule('pos')) {
    return { path: '/dashboard' }
  }
})

// ── Dynamic page title ──────────────────────────────────────────
router.afterEach((to) => {
  const title = to.meta.title
  document.title = title ? `${title} — O3 App` : 'O3 App — Gestion commerciale'
})

export default router
