<script setup lang="ts">
import { ref } from 'vue'

const currentLang = ref<'fr' | 'en'>('fr')

const t = {
  fr: {
    nav: { features: 'Fonctionnalités', pricing: 'Tarifs', contact: 'Contact', login: 'Se connecter', demo: 'Essai gratuit' },
    hero: {
      badge: 'ERP Cloud 100% Marocain',
      title: 'Gérez votre entreprise',
      titleHighlight: 'simplement.',
      subtitle: 'Ventes, Achats, Stock, POS, Facturation — tout en un seul endroit. Démarrez en 2 minutes, sans installation.',
      cta: 'Démarrer l\'essai gratuit — 14 jours',
      ctaSub: 'Aucune carte bancaire requise',
      demo: 'Voir la démo',
    },
    stats: [
      { value: '99.9%', label: 'Disponibilité' },
      { value: '<2min', label: 'Mise en route' },
      { value: '256-bit', label: 'Chiffrement SSL' },
      { value: '24/7', label: 'Support' },
    ],
    features: {
      title: 'Tout ce dont votre entreprise a besoin',
      subtitle: 'Une solution complète pour piloter votre activité au quotidien',
      items: [
        { icon: 'sales', title: 'Gestion des Ventes', desc: 'Devis, Bons de commande, Bons de livraison, Factures — flux automatisé de bout en bout.' },
        { icon: 'purchase', title: 'Gestion des Achats', desc: 'Commandes fournisseurs, réception, contrôle qualité et rapprochement factures.' },
        { icon: 'stock', title: 'Gestion du Stock', desc: 'Multi-entrepôts, mouvements temps réel, transferts inter-dépôts, alertes de réapprovisionnement.' },
        { icon: 'pos', title: 'Point de Vente (POS)', desc: 'Caisse tactile, gestion des sessions, tickets, multi-terminaux. Fonctionne hors-ligne.' },
        { icon: 'invoice', title: 'Facturation & Paiements', desc: 'Factures conformes, suivi des paiements, relances automatiques, gestion des crédits clients.' },
        { icon: 'report', title: 'Rapports & Analyses', desc: 'Tableau de bord temps réel, chiffre d\'affaires, marges, top produits, export Excel.' },
      ],
    },
    pricing: {
      title: 'Tarifs simples et transparents',
      subtitle: 'Choisissez le plan qui correspond à votre activité. Essai gratuit de 14 jours.',
      plans: [
        {
          name: 'Starter',
          price: '499',
          period: 'MAD/mois',
          desc: 'Idéal pour les petites entreprises',
          features: ['Gestion des ventes complète', 'Gestion du stock', 'Sous-domaine gratuit', '500 emails/mois', '1 entrepôt', 'Support par email'],
          cta: 'Commencer',
          popular: false,
          color: 'border-gray-200',
        },
        {
          name: 'Business',
          price: '999',
          period: 'MAD/mois',
          desc: 'Pour les entreprises en croissance',
          features: ['Tout le plan Starter', 'Gestion des achats', 'Point de vente (POS)', 'Domaine personnalisé', '2000 emails/mois', '200 WhatsApp/mois', 'Multi-entrepôts', 'Support prioritaire'],
          cta: 'Commencer',
          popular: true,
          color: 'border-blue-500',
        },
        {
          name: 'Enterprise',
          price: '1999',
          period: 'MAD/mois',
          desc: 'Solution complète sans limites',
          features: ['Tout le plan Business', 'Module e-Commerce', 'WhatsApp illimité', 'Emails illimités', 'Domaine .ma personnalisé', 'Backup quotidien', 'Formation 2h incluse', 'Support téléphonique dédié'],
          cta: 'Nous contacter',
          popular: false,
          color: 'border-purple-200',
        },
      ],
    },
    cta: {
      title: 'Prêt à digitaliser votre entreprise ?',
      subtitle: 'Rejoignez les entreprises marocaines qui font confiance à O3 App pour gérer leur activité.',
      button: 'Créer mon compte gratuitement',
      sub: '14 jours d\'essai gratuit — Aucun engagement',
    },
    footer: {
      desc: 'Solution ERP Cloud pour les entreprises marocaines. Ventes, Achats, Stock, POS — tout en un.',
      product: 'Produit',
      links: ['Fonctionnalités', 'Tarifs', 'Sécurité', 'API'],
      support: 'Support',
      supportLinks: ['Documentation', 'Formation', 'Contact', 'WhatsApp'],
      legal: 'Légal',
      legalLinks: ['CGU', 'Confidentialité', 'Mentions légales'],
      copy: '2026 O3 App. Tous droits réservés. Fait avec amour au Maroc.',
    },
  },
  en: {
    nav: { features: 'Features', pricing: 'Pricing', contact: 'Contact', login: 'Log in', demo: 'Free trial' },
    hero: {
      badge: '100% Moroccan Cloud ERP',
      title: 'Manage your business',
      titleHighlight: 'effortlessly.',
      subtitle: 'Sales, Purchases, Inventory, POS, Invoicing — all in one place. Get started in 2 minutes, no installation needed.',
      cta: 'Start free trial — 14 days',
      ctaSub: 'No credit card required',
      demo: 'View demo',
    },
    stats: [
      { value: '99.9%', label: 'Uptime' },
      { value: '<2min', label: 'Setup time' },
      { value: '256-bit', label: 'SSL Encryption' },
      { value: '24/7', label: 'Support' },
    ],
    features: {
      title: 'Everything your business needs',
      subtitle: 'A complete solution to manage your daily operations',
      items: [
        { icon: 'sales', title: 'Sales Management', desc: 'Quotes, Orders, Delivery Notes, Invoices — fully automated end-to-end workflow.' },
        { icon: 'purchase', title: 'Purchase Management', desc: 'Supplier orders, receiving, quality control and invoice matching.' },
        { icon: 'stock', title: 'Inventory Management', desc: 'Multi-warehouse, real-time movements, inter-depot transfers, reorder alerts.' },
        { icon: 'pos', title: 'Point of Sale (POS)', desc: 'Touch register, session management, tickets, multi-terminal. Works offline.' },
        { icon: 'invoice', title: 'Invoicing & Payments', desc: 'Compliant invoices, payment tracking, auto-reminders, credit management.' },
        { icon: 'report', title: 'Reports & Analytics', desc: 'Real-time dashboard, revenue, margins, top products, Excel export.' },
      ],
    },
    pricing: {
      title: 'Simple, transparent pricing',
      subtitle: 'Choose the plan that fits your business. 14-day free trial included.',
      plans: [
        {
          name: 'Starter',
          price: '499',
          period: 'MAD/mo',
          desc: 'Perfect for small businesses',
          features: ['Complete sales management', 'Inventory management', 'Free subdomain', '500 emails/month', '1 warehouse', 'Email support'],
          cta: 'Get started',
          popular: false,
          color: 'border-gray-200',
        },
        {
          name: 'Business',
          price: '999',
          period: 'MAD/mo',
          desc: 'For growing businesses',
          features: ['Everything in Starter', 'Purchase management', 'Point of Sale (POS)', 'Custom domain', '2000 emails/month', '200 WhatsApp/month', 'Multi-warehouse', 'Priority support'],
          cta: 'Get started',
          popular: true,
          color: 'border-blue-500',
        },
        {
          name: 'Enterprise',
          price: '1999',
          period: 'MAD/mo',
          desc: 'Complete solution, no limits',
          features: ['Everything in Business', 'e-Commerce module', 'Unlimited WhatsApp', 'Unlimited emails', 'Custom .ma domain', 'Daily backup', '2h training included', 'Dedicated phone support'],
          cta: 'Contact us',
          popular: false,
          color: 'border-purple-200',
        },
      ],
    },
    cta: {
      title: 'Ready to digitalize your business?',
      subtitle: 'Join Moroccan businesses that trust O3 App to manage their operations.',
      button: 'Create my free account',
      sub: '14-day free trial — No commitment',
    },
    footer: {
      desc: 'Cloud ERP solution for Moroccan businesses. Sales, Purchases, Inventory, POS — all in one.',
      product: 'Product',
      links: ['Features', 'Pricing', 'Security', 'API'],
      support: 'Support',
      supportLinks: ['Documentation', 'Training', 'Contact', 'WhatsApp'],
      legal: 'Legal',
      legalLinks: ['Terms', 'Privacy', 'Legal notice'],
      copy: '2026 O3 App. All rights reserved. Made with love in Morocco.',
    },
  },
}

const l = ref(t.fr)

function switchLang(lang: 'fr' | 'en') {
  currentLang.value = lang
  l.value = t[lang]
}

function getFeatureIcon(icon: string) {
  const icons: Record<string, string> = {
    sales: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z',
    purchase: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    stock: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
    pos: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
    invoice: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
    report: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
  }
  return icons[icon] || ''
}
</script>

<template>
  <div class="min-h-screen bg-white">
    <!-- Navbar -->
    <nav class="fixed top-0 w-full bg-white/80 backdrop-blur-lg border-b border-gray-100 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <!-- Logo -->
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
              </svg>
            </div>
            <span class="text-xl font-bold text-gray-900">O3 <span class="text-blue-600">App</span></span>
          </div>

          <!-- Nav Links -->
          <div class="hidden md:flex items-center gap-8">
            <a href="#features" class="text-sm text-gray-600 hover:text-gray-900 transition">{{ l.nav.features }}</a>
            <a href="#pricing" class="text-sm text-gray-600 hover:text-gray-900 transition">{{ l.nav.pricing }}</a>
            <a href="#contact" class="text-sm text-gray-600 hover:text-gray-900 transition">{{ l.nav.contact }}</a>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-3">
            <!-- Lang switch -->
            <div class="flex items-center bg-gray-100 rounded-lg p-0.5">
              <button @click="switchLang('fr')" :class="['px-2.5 py-1 text-xs font-medium rounded-md transition', currentLang === 'fr' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500']">FR</button>
              <button @click="switchLang('en')" :class="['px-2.5 py-1 text-xs font-medium rounded-md transition', currentLang === 'en' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500']">EN</button>
            </div>
            <a href="/login" class="text-sm font-medium text-gray-700 hover:text-gray-900 transition">{{ l.nav.login }}</a>
            <a href="/login" class="hidden sm:inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm">{{ l.nav.demo }}</a>
          </div>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <section class="pt-32 pb-20 px-4 sm:px-6 lg:px-8">
      <div class="max-w-5xl mx-auto text-center">
        <span class="inline-flex items-center gap-2 px-4 py-1.5 bg-blue-50 text-blue-700 text-sm font-medium rounded-full mb-6">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          {{ l.hero.badge }}
        </span>
        <h1 class="text-5xl sm:text-6xl lg:text-7xl font-extrabold text-gray-900 tracking-tight leading-tight">
          {{ l.hero.title }}<br>
          <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">{{ l.hero.titleHighlight }}</span>
        </h1>
        <p class="mt-6 text-lg sm:text-xl text-gray-500 max-w-2xl mx-auto leading-relaxed">
          {{ l.hero.subtitle }}
        </p>
        <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
          <a href="/login" class="inline-flex items-center gap-2 px-8 py-3.5 text-base font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-600/25">
            {{ l.hero.cta }}
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
            </svg>
          </a>
          <a href="http://demo.161.35.19.66.nip.io" target="_blank" class="inline-flex items-center gap-2 px-8 py-3.5 text-base font-semibold text-gray-700 bg-white border-2 border-gray-200 rounded-xl hover:border-gray-300 hover:bg-gray-50 transition">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z" />
            </svg>
            {{ l.hero.demo }}
          </a>
        </div>
        <p class="mt-4 text-sm text-gray-400">{{ l.hero.ctaSub }}</p>
      </div>
    </section>

    <!-- Stats Bar -->
    <section class="py-8 bg-gray-50 border-y border-gray-100">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
          <div v-for="stat in l.stats" :key="stat.label" class="text-center">
            <p class="text-2xl font-bold text-gray-900">{{ stat.value }}</p>
            <p class="text-sm text-gray-500 mt-1">{{ stat.label }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 px-4 sm:px-6 lg:px-8">
      <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
          <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">{{ l.features.title }}</h2>
          <p class="mt-4 text-lg text-gray-500 max-w-2xl mx-auto">{{ l.features.subtitle }}</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div
            v-for="feature in l.features.items"
            :key="feature.title"
            class="group p-6 rounded-2xl border border-gray-100 hover:border-blue-100 hover:bg-blue-50/30 transition-all duration-300"
          >
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-4 group-hover:bg-blue-200 transition">
              <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="getFeatureIcon(feature.icon)" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ feature.title }}</h3>
            <p class="text-sm text-gray-500 leading-relaxed">{{ feature.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-24 px-4 sm:px-6 lg:px-8 bg-gray-50">
      <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
          <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">{{ l.pricing.title }}</h2>
          <p class="mt-4 text-lg text-gray-500 max-w-2xl mx-auto">{{ l.pricing.subtitle }}</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
          <div
            v-for="plan in l.pricing.plans"
            :key="plan.name"
            :class="[
              'relative bg-white rounded-2xl p-8 transition-all duration-300',
              plan.popular ? 'border-2 border-blue-500 shadow-xl shadow-blue-100 scale-105' : 'border border-gray-200 hover:shadow-lg',
            ]"
          >
            <span
              v-if="plan.popular"
              class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-4 py-1 bg-blue-600 text-white text-xs font-bold rounded-full"
            >
              {{ currentLang === 'fr' ? 'Le plus populaire' : 'Most popular' }}
            </span>
            <h3 class="text-xl font-bold text-gray-900">{{ plan.name }}</h3>
            <p class="text-sm text-gray-500 mt-1">{{ plan.desc }}</p>
            <div class="mt-6 mb-6">
              <span class="text-4xl font-extrabold text-gray-900">{{ plan.price }}</span>
              <span class="text-base text-gray-500 ml-1">{{ plan.period }}</span>
            </div>
            <ul class="space-y-3 mb-8">
              <li v-for="f in plan.features" :key="f" class="flex items-start gap-3">
                <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                <span class="text-sm text-gray-600">{{ f }}</span>
              </li>
            </ul>
            <a
              href="/login"
              :class="[
                'block text-center w-full py-3 text-sm font-semibold rounded-xl transition',
                plan.popular
                  ? 'bg-blue-600 text-white hover:bg-blue-700 shadow-lg shadow-blue-600/25'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
              ]"
            >
              {{ plan.cta }}
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section id="contact" class="py-24 px-4 sm:px-6 lg:px-8">
      <div class="max-w-3xl mx-auto text-center">
        <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">{{ l.cta.title }}</h2>
        <p class="mt-4 text-lg text-gray-500">{{ l.cta.subtitle }}</p>
        <div class="mt-10">
          <a href="/login" class="inline-flex items-center gap-2 px-8 py-4 text-base font-semibold text-white bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl hover:from-blue-700 hover:to-indigo-700 transition shadow-xl shadow-blue-600/25">
            {{ l.cta.button }}
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
            </svg>
          </a>
          <p class="mt-4 text-sm text-gray-400">{{ l.cta.sub }}</p>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-16 px-4 sm:px-6 lg:px-8">
      <div class="max-w-6xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
          <!-- Brand -->
          <div class="md:col-span-1">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-9 h-9 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                </svg>
              </div>
              <span class="text-lg font-bold text-white">O3 App</span>
            </div>
            <p class="text-sm leading-relaxed">{{ l.footer.desc }}</p>
          </div>
          <!-- Product -->
          <div>
            <h4 class="text-sm font-semibold text-white mb-4">{{ l.footer.product }}</h4>
            <ul class="space-y-2">
              <li v-for="link in l.footer.links" :key="link"><a href="#" class="text-sm hover:text-white transition">{{ link }}</a></li>
            </ul>
          </div>
          <!-- Support -->
          <div>
            <h4 class="text-sm font-semibold text-white mb-4">{{ l.footer.support }}</h4>
            <ul class="space-y-2">
              <li v-for="link in l.footer.supportLinks" :key="link"><a href="#" class="text-sm hover:text-white transition">{{ link }}</a></li>
            </ul>
          </div>
          <!-- Legal -->
          <div>
            <h4 class="text-sm font-semibold text-white mb-4">{{ l.footer.legal }}</h4>
            <ul class="space-y-2">
              <li v-for="link in l.footer.legalLinks" :key="link"><a href="#" class="text-sm hover:text-white transition">{{ link }}</a></li>
            </ul>
          </div>
        </div>
        <div class="border-t border-gray-800 pt-8 text-center text-sm">
          &copy; {{ l.footer.copy }}
        </div>
      </div>
    </footer>
  </div>
</template>
