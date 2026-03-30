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
      { value: '99.9%', label: 'Disponibilité', icon: 'server' },
      { value: '<2min', label: 'Mise en route', icon: 'clock' },
      { value: '256-bit', label: 'Chiffrement SSL', icon: 'lock' },
      { value: '24/7', label: 'Support', icon: 'support' },
    ],
    howItWorks: {
      title: 'Démarrez en 3 étapes simples',
      subtitle: 'Pas de complexité, pas d\'installation. Votre ERP est prêt en quelques minutes.',
      steps: [
        { number: '01', title: 'Créez votre compte', desc: 'Inscrivez-vous en 30 secondes. Choisissez votre sous-domaine personnalisé.' },
        { number: '02', title: 'Configurez votre espace', desc: 'Ajoutez vos produits, clients et paramètres. Import Excel disponible.' },
        { number: '03', title: 'Gérez votre activité', desc: 'Ventes, achats, stock, caisse — tout est prêt. Commencez à vendre !' },
      ],
    },
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
    testimonials: {
      title: 'Ce que disent nos clients',
      subtitle: 'Des entrepreneurs marocains qui nous font confiance au quotidien',
      items: [
        { name: 'Youssef El Mansouri', role: 'Gérant, TechnoShop Casablanca', text: 'O3 App a transformé ma gestion. Le POS est incroyablement rapide et la facturation automatique me fait gagner 2h par jour.', avatar: 'Y' },
        { name: 'Fatima Zahra Bennani', role: 'Directrice, Bennani Import-Export', text: 'Enfin un ERP adapté au marché marocain ! La gestion des achats et du stock multi-dépôts est exactement ce qu\'il me fallait.', avatar: 'F' },
        { name: 'Karim Tazi', role: 'Fondateur, Espace Déco Marrakech', text: 'Mise en place en 2 minutes, pas de serveur à gérer. Le support est réactif et en français. Je recommande à 100%.', avatar: 'K' },
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
      { value: '99.9%', label: 'Uptime', icon: 'server' },
      { value: '<2min', label: 'Setup time', icon: 'clock' },
      { value: '256-bit', label: 'SSL Encryption', icon: 'lock' },
      { value: '24/7', label: 'Support', icon: 'support' },
    ],
    howItWorks: {
      title: 'Get started in 3 simple steps',
      subtitle: 'No complexity, no installation. Your ERP is ready in minutes.',
      steps: [
        { number: '01', title: 'Create your account', desc: 'Sign up in 30 seconds. Choose your custom subdomain.' },
        { number: '02', title: 'Set up your workspace', desc: 'Add your products, clients and settings. Excel import available.' },
        { number: '03', title: 'Manage your business', desc: 'Sales, purchases, inventory, POS — everything is ready. Start selling!' },
      ],
    },
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
    testimonials: {
      title: 'What our clients say',
      subtitle: 'Moroccan entrepreneurs who trust us every day',
      items: [
        { name: 'Youssef El Mansouri', role: 'Manager, TechnoShop Casablanca', text: 'O3 App transformed my management. The POS is incredibly fast and automatic invoicing saves me 2 hours a day.', avatar: 'Y' },
        { name: 'Fatima Zahra Bennani', role: 'Director, Bennani Import-Export', text: 'Finally an ERP adapted to the Moroccan market! Purchase and multi-warehouse stock management is exactly what I needed.', avatar: 'F' },
        { name: 'Karim Tazi', role: 'Founder, Espace Déco Marrakech', text: 'Setup in 2 minutes, no server to manage. Support is responsive and helpful. I recommend 100%.', avatar: 'K' },
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

const avatarColors = ['from-blue-500 to-cyan-400', 'from-pink-500 to-rose-400', 'from-emerald-500 to-teal-400']
const stepIcons = ['user-plus', 'cog', 'rocket']

function getStepIcon(index: number) {
  const icons = [
    // User Plus
    'M19 7.5v3m3-3h-6m-5.25 6a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0zm-9.75 6.5a6.25 6.25 0 0112.5 0',
    // Cog/Settings
    'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    // Rocket
    'M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.58-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z',
  ]
  return icons[index] || ''
}

const stepColors = ['from-blue-500 to-blue-600', 'from-indigo-500 to-indigo-600', 'from-violet-500 to-violet-600']
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

    <!-- Hero Section with Dashboard Mockup -->
    <section class="pt-32 pb-20 px-4 sm:px-6 lg:px-8 overflow-hidden">
      <div class="max-w-7xl mx-auto">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
          <!-- Left: Text -->
          <div class="text-center lg:text-left">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 bg-blue-50 text-blue-700 text-sm font-medium rounded-full mb-6">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              {{ l.hero.badge }}
            </span>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-gray-900 tracking-tight leading-tight">
              {{ l.hero.title }}<br>
              <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">{{ l.hero.titleHighlight }}</span>
            </h1>
            <p class="mt-6 text-lg text-gray-500 max-w-xl leading-relaxed">
              {{ l.hero.subtitle }}
            </p>
            <div class="mt-10 flex flex-col sm:flex-row items-center lg:items-start justify-center lg:justify-start gap-4">
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

            <!-- Trust avatars -->
            <div class="mt-8 flex items-center gap-3 justify-center lg:justify-start">
              <div class="flex -space-x-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">M</div>
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-pink-500 to-rose-400 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">A</div>
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-500 to-teal-400 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">S</div>
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-amber-500 to-orange-400 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">R</div>
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 to-purple-400 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">+</div>
              </div>
              <div class="text-left">
                <div class="flex items-center gap-1">
                  <svg v-for="i in 5" :key="i" class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">{{ currentLang === 'fr' ? '+150 entreprises marocaines' : '+150 Moroccan businesses' }}</p>
              </div>
            </div>
          </div>

          <!-- Right: Dashboard Mockup Illustration -->
          <div class="relative lg:pl-8">
            <!-- Glow effect -->
            <div class="absolute -inset-4 bg-gradient-to-r from-blue-100 to-indigo-100 rounded-3xl blur-2xl opacity-60"></div>

            <!-- Dashboard mockup -->
            <div class="relative bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
              <!-- Browser bar -->
              <div class="flex items-center gap-2 px-4 py-3 bg-gray-50 border-b border-gray-200">
                <div class="flex gap-1.5">
                  <div class="w-3 h-3 rounded-full bg-red-400"></div>
                  <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                  <div class="w-3 h-3 rounded-full bg-green-400"></div>
                </div>
                <div class="flex-1 mx-4 bg-white rounded-lg px-3 py-1.5 text-xs text-gray-400 border border-gray-200">
                  mon-entreprise.o3app.ma/dashboard
                </div>
              </div>

              <!-- Dashboard content -->
              <div class="p-5">
                <!-- Top stats row -->
                <div class="grid grid-cols-4 gap-3 mb-4">
                  <div class="bg-blue-50 rounded-xl p-3 text-center">
                    <p class="text-xs text-blue-600 font-medium">{{ currentLang === 'fr' ? 'Ventes' : 'Sales' }}</p>
                    <p class="text-lg font-bold text-gray-900 mt-1">124,500</p>
                    <p class="text-xs text-green-500 font-medium">+12.5%</p>
                  </div>
                  <div class="bg-emerald-50 rounded-xl p-3 text-center">
                    <p class="text-xs text-emerald-600 font-medium">{{ currentLang === 'fr' ? 'Commandes' : 'Orders' }}</p>
                    <p class="text-lg font-bold text-gray-900 mt-1">89</p>
                    <p class="text-xs text-green-500 font-medium">+8.3%</p>
                  </div>
                  <div class="bg-violet-50 rounded-xl p-3 text-center">
                    <p class="text-xs text-violet-600 font-medium">{{ currentLang === 'fr' ? 'Clients' : 'Clients' }}</p>
                    <p class="text-lg font-bold text-gray-900 mt-1">342</p>
                    <p class="text-xs text-green-500 font-medium">+5.7%</p>
                  </div>
                  <div class="bg-amber-50 rounded-xl p-3 text-center">
                    <p class="text-xs text-amber-600 font-medium">{{ currentLang === 'fr' ? 'Produits' : 'Products' }}</p>
                    <p class="text-lg font-bold text-gray-900 mt-1">1,256</p>
                    <p class="text-xs text-blue-500 font-medium">Active</p>
                  </div>
                </div>

                <!-- Chart area -->
                <div class="bg-gray-50 rounded-xl p-4 mb-4">
                  <div class="flex items-center justify-between mb-3">
                    <p class="text-sm font-semibold text-gray-700">{{ currentLang === 'fr' ? 'Chiffre d\'affaires' : 'Revenue' }}</p>
                    <span class="text-xs text-gray-400">{{ currentLang === 'fr' ? 'Ce mois' : 'This month' }}</span>
                  </div>
                  <!-- SVG Chart -->
                  <svg class="w-full h-24" viewBox="0 0 400 100" fill="none">
                    <defs>
                      <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#3B82F6" stop-opacity="0.3" />
                        <stop offset="100%" stop-color="#3B82F6" stop-opacity="0" />
                      </linearGradient>
                    </defs>
                    <path d="M0 80 L40 65 L80 70 L120 45 L160 50 L200 30 L240 35 L280 20 L320 25 L360 15 L400 10" stroke="#3B82F6" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M0 80 L40 65 L80 70 L120 45 L160 50 L200 30 L240 35 L280 20 L320 25 L360 15 L400 10 L400 100 L0 100Z" fill="url(#chartGrad)" />
                    <!-- Data points -->
                    <circle cx="0" cy="80" r="3" fill="#3B82F6" />
                    <circle cx="80" cy="70" r="3" fill="#3B82F6" />
                    <circle cx="160" cy="50" r="3" fill="#3B82F6" />
                    <circle cx="240" cy="35" r="3" fill="#3B82F6" />
                    <circle cx="320" cy="25" r="3" fill="#3B82F6" />
                    <circle cx="400" cy="10" r="4" fill="#3B82F6" stroke="white" stroke-width="2" />
                  </svg>
                </div>

                <!-- Recent orders table -->
                <div class="bg-white rounded-xl border border-gray-100">
                  <div class="px-4 py-2 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-700">{{ currentLang === 'fr' ? 'Dernières commandes' : 'Recent orders' }}</p>
                  </div>
                  <div class="divide-y divide-gray-50">
                    <div class="flex items-center justify-between px-4 py-2.5">
                      <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center text-white text-xs font-bold">AM</div>
                        <div>
                          <p class="text-sm font-medium text-gray-800">Ahmed M.</p>
                          <p class="text-xs text-gray-400">#ORD-2024-089</p>
                        </div>
                      </div>
                      <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900">4,250 MAD</p>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">{{ currentLang === 'fr' ? 'Payée' : 'Paid' }}</span>
                      </div>
                    </div>
                    <div class="flex items-center justify-between px-4 py-2.5">
                      <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-pink-500 to-rose-400 flex items-center justify-center text-white text-xs font-bold">SB</div>
                        <div>
                          <p class="text-sm font-medium text-gray-800">Sara B.</p>
                          <p class="text-xs text-gray-400">#ORD-2024-088</p>
                        </div>
                      </div>
                      <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900">1,890 MAD</p>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 font-medium">{{ currentLang === 'fr' ? 'En cours' : 'Pending' }}</span>
                      </div>
                    </div>
                    <div class="flex items-center justify-between px-4 py-2.5">
                      <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-500 to-teal-400 flex items-center justify-center text-white text-xs font-bold">KT</div>
                        <div>
                          <p class="text-sm font-medium text-gray-800">Khalid T.</p>
                          <p class="text-xs text-gray-400">#ORD-2024-087</p>
                        </div>
                      </div>
                      <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900">7,620 MAD</p>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">{{ currentLang === 'fr' ? 'Payée' : 'Paid' }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Floating notification cards -->
            <div class="absolute -left-6 top-1/4 bg-white rounded-xl shadow-lg border border-gray-100 p-3 flex items-center gap-3 animate-bounce" style="animation-duration: 3s;">
              <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
              </div>
              <div>
                <p class="text-xs font-semibold text-gray-800">{{ currentLang === 'fr' ? 'Vente confirmée' : 'Sale confirmed' }}</p>
                <p class="text-xs text-gray-400">+3,200 MAD</p>
              </div>
            </div>

            <div class="absolute -right-4 bottom-1/4 bg-white rounded-xl shadow-lg border border-gray-100 p-3 flex items-center gap-3 animate-bounce" style="animation-duration: 4s; animation-delay: 1s;">
              <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                </svg>
              </div>
              <div>
                <p class="text-xs font-semibold text-gray-800">{{ currentLang === 'fr' ? 'Nouveau client' : 'New client' }}</p>
                <p class="text-xs text-gray-400">Boutique Atlas</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Stats Bar with Icons -->
    <section class="py-10 bg-gray-50 border-y border-gray-100">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
          <div v-for="stat in l.stats" :key="stat.label" class="text-center">
            <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-white shadow-sm border border-gray-100 flex items-center justify-center">
              <!-- Server -->
              <svg v-if="stat.icon === 'server'" class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
              </svg>
              <!-- Clock -->
              <svg v-if="stat.icon === 'clock'" class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <!-- Lock -->
              <svg v-if="stat.icon === 'lock'" class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
              </svg>
              <!-- Support -->
              <svg v-if="stat.icon === 'support'" class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
              </svg>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ stat.value }}</p>
            <p class="text-sm text-gray-500 mt-1">{{ stat.label }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-24 px-4 sm:px-6 lg:px-8 bg-white">
      <div class="max-w-5xl mx-auto">
        <div class="text-center mb-16">
          <span class="inline-flex items-center px-3 py-1 bg-indigo-50 text-indigo-700 text-xs font-semibold rounded-full mb-4">
            {{ currentLang === 'fr' ? 'COMMENT CA MARCHE' : 'HOW IT WORKS' }}
          </span>
          <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">{{ l.howItWorks.title }}</h2>
          <p class="mt-4 text-lg text-gray-500 max-w-2xl mx-auto">{{ l.howItWorks.subtitle }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
          <!-- Connector line -->
          <div class="hidden md:block absolute top-20 left-1/6 right-1/6 h-0.5 bg-gradient-to-r from-blue-200 via-indigo-200 to-violet-200"></div>

          <div v-for="(step, index) in l.howItWorks.steps" :key="step.number" class="relative text-center">
            <!-- Step circle with icon -->
            <div class="relative z-10 mx-auto mb-6">
              <div :class="['w-20 h-20 mx-auto rounded-2xl bg-gradient-to-br flex items-center justify-center shadow-lg', stepColors[index]]">
                <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="getStepIcon(index)" />
                </svg>
              </div>
              <span class="absolute -top-2 -right-2 w-8 h-8 bg-white rounded-full border-2 border-gray-200 flex items-center justify-center text-sm font-bold text-gray-700 shadow-sm">{{ step.number }}</span>
            </div>

            <!-- Illustration below icon -->
            <div class="mb-4 mx-auto w-full max-w-[200px] h-32 rounded-xl bg-gray-50 border border-gray-100 overflow-hidden flex items-center justify-center">
              <!-- Step 1: Account creation form illustration -->
              <svg v-if="index === 0" class="w-full h-full p-4" viewBox="0 0 180 100" fill="none">
                <rect x="20" y="10" width="140" height="80" rx="8" fill="white" stroke="#E5E7EB" stroke-width="1.5" />
                <rect x="35" y="25" width="110" height="12" rx="4" fill="#EFF6FF" stroke="#BFDBFE" stroke-width="1" />
                <rect x="35" y="45" width="110" height="12" rx="4" fill="#EFF6FF" stroke="#BFDBFE" stroke-width="1" />
                <rect x="35" y="65" width="60" height="14" rx="6" fill="#3B82F6" />
                <text x="52" y="75" fill="white" font-size="7" font-weight="bold">Sign up</text>
                <circle cx="44" cy="31" r="3" fill="#93C5FD" />
                <circle cx="44" cy="51" r="3" fill="#93C5FD" />
              </svg>
              <!-- Step 2: Settings/Config illustration -->
              <svg v-if="index === 1" class="w-full h-full p-4" viewBox="0 0 180 100" fill="none">
                <rect x="15" y="10" width="150" height="80" rx="8" fill="white" stroke="#E5E7EB" stroke-width="1.5" />
                <!-- Sidebar -->
                <rect x="15" y="10" width="40" height="80" rx="8" fill="#F9FAFB" />
                <rect x="22" y="22" width="26" height="4" rx="2" fill="#D1D5DB" />
                <rect x="22" y="32" width="20" height="4" rx="2" fill="#818CF8" />
                <rect x="22" y="42" width="24" height="4" rx="2" fill="#D1D5DB" />
                <rect x="22" y="52" width="18" height="4" rx="2" fill="#D1D5DB" />
                <!-- Content -->
                <rect x="65" y="22" width="90" height="8" rx="3" fill="#EEF2FF" stroke="#C7D2FE" stroke-width="0.5" />
                <rect x="65" y="38" width="90" height="8" rx="3" fill="#EEF2FF" stroke="#C7D2FE" stroke-width="0.5" />
                <rect x="65" y="54" width="90" height="8" rx="3" fill="#EEF2FF" stroke="#C7D2FE" stroke-width="0.5" />
                <rect x="65" y="70" width="40" height="12" rx="5" fill="#6366F1" />
                <text x="72" y="79" fill="white" font-size="6" font-weight="bold">Save</text>
              </svg>
              <!-- Step 3: Rocket/Dashboard illustration -->
              <svg v-if="index === 2" class="w-full h-full p-4" viewBox="0 0 180 100" fill="none">
                <rect x="15" y="10" width="150" height="80" rx="8" fill="white" stroke="#E5E7EB" stroke-width="1.5" />
                <!-- Mini chart bars -->
                <rect x="30" y="65" width="16" height="15" rx="3" fill="#DBEAFE" />
                <rect x="52" y="50" width="16" height="30" rx="3" fill="#93C5FD" />
                <rect x="74" y="35" width="16" height="45" rx="3" fill="#60A5FA" />
                <rect x="96" y="45" width="16" height="35" rx="3" fill="#3B82F6" />
                <rect x="118" y="25" width="16" height="55" rx="3" fill="#2563EB" />
                <rect x="140" y="20" width="16" height="60" rx="3" fill="#1D4ED8" />
                <!-- Arrow up -->
                <path d="M135 18 L143 10 L151 18" stroke="#10B981" stroke-width="2" stroke-linecap="round" />
                <text x="30" y="26" fill="#1E40AF" font-size="8" font-weight="bold">+45%</text>
              </svg>
            </div>

            <h3 class="text-lg font-bold text-gray-900 mb-2">{{ step.title }}</h3>
            <p class="text-sm text-gray-500 leading-relaxed max-w-xs mx-auto">{{ step.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section with Illustrations -->
    <section id="features" class="py-24 px-4 sm:px-6 lg:px-8 bg-gray-50">
      <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
          <span class="inline-flex items-center px-3 py-1 bg-blue-50 text-blue-700 text-xs font-semibold rounded-full mb-4">
            {{ currentLang === 'fr' ? 'FONCTIONNALITES' : 'FEATURES' }}
          </span>
          <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">{{ l.features.title }}</h2>
          <p class="mt-4 text-lg text-gray-500 max-w-2xl mx-auto">{{ l.features.subtitle }}</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div
            v-for="(feature, idx) in l.features.items"
            :key="feature.title"
            class="group bg-white p-6 rounded-2xl border border-gray-100 hover:border-blue-200 hover:shadow-xl transition-all duration-300"
          >
            <!-- Feature mini illustration -->
            <div class="w-full h-36 mb-5 rounded-xl bg-gradient-to-br from-gray-50 to-blue-50 border border-gray-100 overflow-hidden flex items-center justify-center">
              <!-- Sales illustration -->
              <svg v-if="feature.icon === 'sales'" class="w-full h-full p-5" viewBox="0 0 200 120" fill="none">
                <rect x="10" y="10" width="120" height="100" rx="8" fill="white" stroke="#E5E7EB" stroke-width="1.5" />
                <text x="20" y="30" fill="#1E40AF" font-size="8" font-weight="bold">{{ currentLang === 'fr' ? 'DEVIS #247' : 'QUOTE #247' }}</text>
                <line x1="20" y1="36" x2="120" y2="36" stroke="#E5E7EB" stroke-width="1" />
                <rect x="20" y="42" width="60" height="5" rx="2" fill="#DBEAFE" />
                <rect x="90" y="42" width="24" height="5" rx="2" fill="#93C5FD" />
                <rect x="20" y="52" width="50" height="5" rx="2" fill="#DBEAFE" />
                <rect x="90" y="52" width="24" height="5" rx="2" fill="#93C5FD" />
                <rect x="20" y="62" width="55" height="5" rx="2" fill="#DBEAFE" />
                <rect x="90" y="62" width="24" height="5" rx="2" fill="#93C5FD" />
                <line x1="20" y1="74" x2="120" y2="74" stroke="#E5E7EB" stroke-width="1" />
                <text x="70" y="86" fill="#1D4ED8" font-size="10" font-weight="bold">12,450 MAD</text>
                <!-- Arrow -->
                <path d="M140 50 L165 50" stroke="#3B82F6" stroke-width="2" stroke-linecap="round" />
                <path d="M160 45 L168 50 L160 55" fill="#3B82F6" />
                <!-- Invoice -->
                <rect x="170" y="30" width="25" height="35" rx="3" fill="#3B82F6" opacity="0.2" stroke="#3B82F6" stroke-width="1" />
                <rect x="174" y="38" width="17" height="3" rx="1" fill="#3B82F6" opacity="0.5" />
                <rect x="174" y="44" width="12" height="3" rx="1" fill="#3B82F6" opacity="0.5" />
                <rect x="174" y="50" width="15" height="3" rx="1" fill="#3B82F6" opacity="0.5" />
              </svg>
              <!-- Purchase illustration -->
              <svg v-if="feature.icon === 'purchase'" class="w-full h-full p-5" viewBox="0 0 200 120" fill="none">
                <rect x="10" y="20" width="80" height="80" rx="8" fill="white" stroke="#E5E7EB" stroke-width="1.5" />
                <text x="18" y="38" fill="#4338CA" font-size="7" font-weight="bold">{{ currentLang === 'fr' ? 'FOURNISSEUR' : 'SUPPLIER' }}</text>
                <circle cx="50" cy="60" r="15" fill="#EEF2FF" stroke="#A5B4FC" stroke-width="1.5" />
                <path d="M43 60 L50 60 L50 53" stroke="#6366F1" stroke-width="2" stroke-linecap="round" />
                <rect x="30" y="82" width="40" height="10" rx="4" fill="#6366F1" />
                <text x="36" y="89.5" fill="white" font-size="5" font-weight="bold">{{ currentLang === 'fr' ? 'Valider' : 'Approve' }}</text>
                <!-- Arrow -->
                <path d="M100 60 L120 60" stroke="#6366F1" stroke-width="2" stroke-dasharray="4 3" />
                <path d="M115 55 L123 60 L115 65" fill="#6366F1" />
                <!-- Receiving -->
                <rect x="130" y="20" width="60" height="80" rx="8" fill="#EEF2FF" stroke="#A5B4FC" stroke-width="1.5" />
                <rect x="140" y="35" width="40" height="10" rx="3" fill="white" />
                <rect x="140" y="50" width="40" height="10" rx="3" fill="white" />
                <rect x="140" y="65" width="40" height="10" rx="3" fill="white" />
                <path d="M155 39 L158 42 L165 36" stroke="#10B981" stroke-width="1.5" stroke-linecap="round" />
                <path d="M155 54 L158 57 L165 51" stroke="#10B981" stroke-width="1.5" stroke-linecap="round" />
              </svg>
              <!-- Stock illustration -->
              <svg v-if="feature.icon === 'stock'" class="w-full h-full p-5" viewBox="0 0 200 120" fill="none">
                <!-- Warehouse 1 -->
                <rect x="10" y="30" width="55" height="60" rx="6" fill="white" stroke="#E5E7EB" stroke-width="1.5" />
                <rect x="18" y="14" width="40" height="20" rx="4" fill="#FEF3C7" stroke="#FCD34D" stroke-width="1" />
                <text x="24" y="28" fill="#92400E" font-size="7" font-weight="bold">{{ currentLang === 'fr' ? 'Dpt A' : 'Wh A' }}</text>
                <rect x="18" y="42" width="38" height="8" rx="2" fill="#DBEAFE" />
                <rect x="18" y="42" width="28" height="8" rx="2" fill="#3B82F6" opacity="0.7" />
                <rect x="18" y="56" width="38" height="8" rx="2" fill="#DBEAFE" />
                <rect x="18" y="56" width="15" height="8" rx="2" fill="#3B82F6" opacity="0.7" />
                <rect x="18" y="70" width="38" height="8" rx="2" fill="#DBEAFE" />
                <rect x="18" y="70" width="35" height="8" rx="2" fill="#3B82F6" opacity="0.7" />
                <!-- Transfer arrows -->
                <path d="M72 55 L92 45" stroke="#10B981" stroke-width="2" stroke-linecap="round" />
                <path d="M88 42 L95 45 L90 50" fill="#10B981" />
                <path d="M92 65 L72 55" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-dasharray="4 3" />
                <!-- Warehouse 2 -->
                <rect x="100" y="15" width="55" height="60" rx="6" fill="white" stroke="#E5E7EB" stroke-width="1.5" />
                <rect x="108" y="0" width="40" height="20" rx="4" fill="#DCFCE7" stroke="#86EFAC" stroke-width="1" />
                <text x="115" y="14" fill="#166534" font-size="7" font-weight="bold">{{ currentLang === 'fr' ? 'Dpt B' : 'Wh B' }}</text>
                <rect x="108" y="28" width="38" height="8" rx="2" fill="#D1FAE5" />
                <rect x="108" y="28" width="32" height="8" rx="2" fill="#10B981" opacity="0.5" />
                <rect x="108" y="42" width="38" height="8" rx="2" fill="#D1FAE5" />
                <rect x="108" y="42" width="22" height="8" rx="2" fill="#10B981" opacity="0.5" />
                <rect x="108" y="56" width="38" height="8" rx="2" fill="#D1FAE5" />
                <rect x="108" y="56" width="10" height="8" rx="2" fill="#EF4444" opacity="0.5" />
                <!-- Alert -->
                <circle cx="180" cy="60" r="15" fill="#FEF2F2" stroke="#FCA5A5" stroke-width="1" />
                <text x="177" y="64" fill="#DC2626" font-size="14" font-weight="bold">!</text>
              </svg>
              <!-- POS illustration -->
              <svg v-if="feature.icon === 'pos'" class="w-full h-full p-5" viewBox="0 0 200 120" fill="none">
                <!-- POS Screen -->
                <rect x="10" y="5" width="130" height="90" rx="8" fill="#1E293B" />
                <rect x="16" y="11" width="118" height="78" rx="5" fill="white" />
                <!-- Product grid -->
                <rect x="22" y="17" width="28" height="28" rx="4" fill="#DBEAFE" />
                <rect x="54" y="17" width="28" height="28" rx="4" fill="#D1FAE5" />
                <rect x="86" y="17" width="28" height="28" rx="4" fill="#FEF3C7" />
                <rect x="22" y="50" width="28" height="28" rx="4" fill="#EDE9FE" />
                <rect x="54" y="50" width="28" height="28" rx="4" fill="#FEE2E2" />
                <rect x="86" y="50" width="28" height="28" rx="4" fill="#DBEAFE" />
                <!-- Labels inside boxes -->
                <rect x="28" y="35" width="16" height="4" rx="1" fill="#3B82F6" opacity="0.5" />
                <rect x="60" y="35" width="16" height="4" rx="1" fill="#10B981" opacity="0.5" />
                <rect x="92" y="35" width="16" height="4" rx="1" fill="#F59E0B" opacity="0.5" />
                <!-- Cart/Total panel -->
                <rect x="148" y="5" width="45" height="90" rx="8" fill="white" stroke="#E5E7EB" stroke-width="1.5" />
                <rect x="154" y="14" width="33" height="5" rx="2" fill="#F3F4F6" />
                <rect x="154" y="24" width="33" height="5" rx="2" fill="#F3F4F6" />
                <rect x="154" y="34" width="33" height="5" rx="2" fill="#F3F4F6" />
                <line x1="154" y1="50" x2="187" y2="50" stroke="#E5E7EB" stroke-width="1" />
                <text x="154" y="62" fill="#1E40AF" font-size="8" font-weight="bold">Total</text>
                <text x="154" y="74" fill="#111827" font-size="9" font-weight="bold">2,340</text>
                <rect x="154" y="80" width="33" height="10" rx="4" fill="#10B981" />
                <text x="159" y="88" fill="white" font-size="6" font-weight="bold">{{ currentLang === 'fr' ? 'Payer' : 'Pay' }}</text>
                <!-- Ticket receipt floating -->
                <rect x="120" y="95" width="30" height="20" rx="2" fill="white" stroke="#D1D5DB" stroke-width="1" transform="rotate(-10 135 105)" />
              </svg>
              <!-- Invoice illustration -->
              <svg v-if="feature.icon === 'invoice'" class="w-full h-full p-5" viewBox="0 0 200 120" fill="none">
                <rect x="40" y="5" width="120" height="110" rx="8" fill="white" stroke="#E5E7EB" stroke-width="1.5" />
                <!-- Header -->
                <rect x="50" y="15" width="50" height="8" rx="3" fill="#1E40AF" />
                <text x="55" y="22" fill="white" font-size="6" font-weight="bold">FACTURE</text>
                <rect x="115" y="15" width="35" height="8" rx="3" fill="#DBEAFE" />
                <!-- Info rows -->
                <rect x="50" y="32" width="70" height="4" rx="1" fill="#F3F4F6" />
                <rect x="50" y="40" width="55" height="4" rx="1" fill="#F3F4F6" />
                <!-- Table -->
                <rect x="50" y="52" width="100" height="6" rx="2" fill="#EFF6FF" />
                <rect x="50" y="62" width="100" height="5" rx="1" fill="#F9FAFB" />
                <rect x="50" y="70" width="100" height="5" rx="1" fill="white" stroke="#F3F4F6" stroke-width="0.5" />
                <rect x="50" y="78" width="100" height="5" rx="1" fill="#F9FAFB" />
                <!-- Total -->
                <line x1="50" y1="90" x2="150" y2="90" stroke="#E5E7EB" stroke-width="1" />
                <text x="90" y="102" fill="#1E40AF" font-size="10" font-weight="bold">15,750 MAD</text>
                <!-- Check mark badge -->
                <circle cx="155" cy="15" r="12" fill="#DCFCE7" stroke="#86EFAC" stroke-width="1.5" />
                <path d="M149 15 L153 19 L161 11" stroke="#16A34A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <!-- Payment indicator -->
                <rect x="10" y="45" width="25" height="50" rx="5" fill="#10B981" opacity="0.15" stroke="#10B981" stroke-width="1" />
                <text x="14" y="75" fill="#10B981" font-size="6" font-weight="bold" transform="rotate(-90 22 75)">{{ currentLang === 'fr' ? 'PAYEE' : 'PAID' }}</text>
              </svg>
              <!-- Report illustration -->
              <svg v-if="feature.icon === 'report'" class="w-full h-full p-5" viewBox="0 0 200 120" fill="none">
                <!-- Dashboard layout -->
                <rect x="10" y="5" width="180" height="110" rx="8" fill="white" stroke="#E5E7EB" stroke-width="1.5" />
                <!-- Pie chart -->
                <circle cx="55" cy="45" r="25" fill="#DBEAFE" />
                <path d="M55 20 A25 25 0 0 1 80 45 L55 45 Z" fill="#3B82F6" />
                <path d="M80 45 A25 25 0 0 1 55 70 L55 45 Z" fill="#6366F1" />
                <circle cx="55" cy="45" r="10" fill="white" />
                <!-- Bar chart -->
                <rect x="100" y="55" width="12" height="25" rx="3" fill="#DBEAFE" />
                <rect x="118" y="40" width="12" height="40" rx="3" fill="#93C5FD" />
                <rect x="136" y="30" width="12" height="50" rx="3" fill="#3B82F6" />
                <rect x="154" y="45" width="12" height="35" rx="3" fill="#6366F1" />
                <rect x="172" y="20" width="12" height="60" rx="3" fill="#4F46E5" />
                <!-- Legend dots -->
                <circle cx="105" cy="20" r="3" fill="#3B82F6" />
                <rect x="112" y="18" width="20" height="4" rx="1" fill="#D1D5DB" />
                <circle cx="140" cy="20" r="3" fill="#6366F1" />
                <rect x="147" y="18" width="20" height="4" rx="1" fill="#D1D5DB" />
                <!-- KPI cards bottom -->
                <rect x="20" y="82" width="50" height="22" rx="4" fill="#F0FDF4" stroke="#BBF7D0" stroke-width="1" />
                <text x="28" y="96" fill="#16A34A" font-size="8" font-weight="bold">+23%</text>
                <rect x="80" y="82" width="50" height="22" rx="4" fill="#EFF6FF" stroke="#BFDBFE" stroke-width="1" />
                <text x="88" y="96" fill="#2563EB" font-size="8" font-weight="bold">1.2M</text>
                <rect x="140" y="82" width="40" height="22" rx="4" fill="#FEF3C7" stroke="#FDE68A" stroke-width="1" />
                <text x="147" y="96" fill="#D97706" font-size="8" font-weight="bold">Top 5</text>
              </svg>
            </div>

            <div class="flex items-start gap-4">
              <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0 group-hover:bg-blue-200 transition">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="getFeatureIcon(feature.icon)" />
                </svg>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ feature.title }}</h3>
                <p class="text-sm text-gray-500 leading-relaxed">{{ feature.desc }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-24 px-4 sm:px-6 lg:px-8 bg-white">
      <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
          <span class="inline-flex items-center px-3 py-1 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-full mb-4">
            {{ currentLang === 'fr' ? 'TEMOIGNAGES' : 'TESTIMONIALS' }}
          </span>
          <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">{{ l.testimonials.title }}</h2>
          <p class="mt-4 text-lg text-gray-500 max-w-2xl mx-auto">{{ l.testimonials.subtitle }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div
            v-for="(testimonial, idx) in l.testimonials.items"
            :key="testimonial.name"
            class="bg-white rounded-2xl border border-gray-100 p-8 hover:shadow-xl transition-all duration-300 relative"
          >
            <!-- Quote icon -->
            <svg class="w-10 h-10 text-blue-100 mb-4" fill="currentColor" viewBox="0 0 24 24">
              <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10H14.017zM0 21v-7.391c0-5.704 3.731-9.57 8.983-10.609L9.978 5.151c-2.432.917-3.995 3.638-3.995 5.849h4V21H0z" />
            </svg>
            <p class="text-gray-600 leading-relaxed mb-6 text-sm">{{ testimonial.text }}</p>
            <div class="flex items-center gap-4">
              <!-- Avatar -->
              <div :class="['w-12 h-12 rounded-full bg-gradient-to-br flex items-center justify-center text-white text-lg font-bold shadow-md', avatarColors[idx]]">
                {{ testimonial.avatar }}
              </div>
              <div>
                <p class="font-semibold text-gray-900 text-sm">{{ testimonial.name }}</p>
                <p class="text-xs text-gray-500">{{ testimonial.role }}</p>
              </div>
            </div>
            <!-- Stars -->
            <div class="flex items-center gap-0.5 mt-4">
              <svg v-for="i in 5" :key="i" class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-24 px-4 sm:px-6 lg:px-8 bg-gray-50">
      <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
          <span class="inline-flex items-center px-3 py-1 bg-violet-50 text-violet-700 text-xs font-semibold rounded-full mb-4">
            {{ currentLang === 'fr' ? 'TARIFICATION' : 'PRICING' }}
          </span>
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

            <!-- Plan icon -->
            <div :class="['w-14 h-14 rounded-2xl flex items-center justify-center mb-4', plan.popular ? 'bg-blue-100' : 'bg-gray-100']">
              <!-- Starter: single user -->
              <svg v-if="plan.name === 'Starter'" :class="['w-7 h-7', plan.popular ? 'text-blue-600' : 'text-gray-600']" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
              </svg>
              <!-- Business: building -->
              <svg v-if="plan.name === 'Business'" :class="['w-7 h-7', plan.popular ? 'text-blue-600' : 'text-gray-600']" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
              </svg>
              <!-- Enterprise: rocket -->
              <svg v-if="plan.name === 'Enterprise'" :class="['w-7 h-7', plan.popular ? 'text-blue-600' : 'text-gray-600']" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.58-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
              </svg>
            </div>

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

    <!-- CTA Section with illustration -->
    <section id="contact" class="py-24 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-blue-600 to-indigo-700 relative overflow-hidden">
      <!-- Background pattern -->
      <div class="absolute inset-0 opacity-10">
        <svg class="w-full h-full" viewBox="0 0 400 400" fill="none">
          <circle cx="50" cy="50" r="80" stroke="white" stroke-width="0.5" />
          <circle cx="350" cy="100" r="120" stroke="white" stroke-width="0.5" />
          <circle cx="200" cy="300" r="100" stroke="white" stroke-width="0.5" />
          <circle cx="100" cy="350" r="60" stroke="white" stroke-width="0.5" />
          <circle cx="380" cy="350" r="90" stroke="white" stroke-width="0.5" />
        </svg>
      </div>
      <div class="max-w-3xl mx-auto text-center relative z-10">
        <!-- Floating avatars around CTA -->
        <div class="flex justify-center mb-8">
          <div class="flex -space-x-4">
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-white font-bold ring-4 ring-blue-600/50">H</div>
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-400 to-green-500 flex items-center justify-center text-white font-bold ring-4 ring-blue-600/50">N</div>
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-pink-400 to-rose-500 flex items-center justify-center text-white font-bold ring-4 ring-blue-600/50">L</div>
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-cyan-400 to-blue-500 flex items-center justify-center text-white font-bold ring-4 ring-blue-600/50">O</div>
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-violet-400 to-purple-500 flex items-center justify-center text-white font-bold ring-4 ring-blue-600/50">Z</div>
          </div>
        </div>

        <h2 class="text-3xl sm:text-4xl font-bold text-white">{{ l.cta.title }}</h2>
        <p class="mt-4 text-lg text-blue-100">{{ l.cta.subtitle }}</p>
        <div class="mt-10">
          <a href="/login" class="inline-flex items-center gap-2 px-8 py-4 text-base font-semibold text-blue-600 bg-white rounded-xl hover:bg-blue-50 transition shadow-xl">
            {{ l.cta.button }}
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
            </svg>
          </a>
          <p class="mt-4 text-sm text-blue-200">{{ l.cta.sub }}</p>
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
