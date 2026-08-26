<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="utf-8">
    {{-- maximum-scale et user-scalable=no empechaient d'agrandir la page :
         un utilisateur malvoyant ne pouvait pas zoomer, et le RGAA l'exige
         (critere 10.11). Ces attributs etaient la pour eviter le zoom
         intempestif d'iOS a la prise de focus sur un champ ; ce zoom vient
         d'une taille de police inferieure a 16 px, corrigee a la source par
         la charte v0.3 §11. Le verrou n'a donc plus lieu d'etre. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    {{-- ── PWA / Kiosk mode ───────────────────────────────────────── --}}
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="O3 POS">
    <meta name="theme-color" content="#f97316">
    <link rel="manifest" href="/pwa-manifest.json">

    {{-- ── Google Analytics (GA4) ──────────────────────────────────── --}}
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-ZV94W40XP9"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-ZV94W40XP9');
    </script>

    {{-- ── SEO : Title & Description ───────────────────────────────── --}}
    <title>O3 App — Logiciel de gestion commerciale pour PME au Maroc</title>
    <meta name="description" content="O3 App est un ERP cloud 100% marocain : Ventes, Achats, Stock, POS, Facturation. Démarrez en 2 minutes sans installation. Essai gratuit 14 jours.">
    <meta name="keywords" content="logiciel gestion maroc, erp maroc, facturation maroc, logiciel caisse maroc, gestion stock en ligne, point de vente maroc, logiciel commercial maroc">
    <meta name="author" content="O3 App">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://o3app.ma">

    {{-- ── Open Graph (Facebook, LinkedIn, WhatsApp) ───────────────── --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://o3app.ma">
    <meta property="og:title" content="O3 App — Gérez votre entreprise simplement">
    <meta property="og:description" content="Ventes, Achats, Stock, POS, Facturation — tout en un seul endroit. ERP cloud pour PME marocaines. Essai gratuit 14 jours.">
    <meta property="og:image" content="https://o3app.ma/images/og-preview.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="fr_MA">
    <meta property="og:site_name" content="O3 App">

    {{-- ── Twitter Card ────────────────────────────────────────────── --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="O3 App — Logiciel de gestion pour PME au Maroc">
    <meta name="twitter:description" content="ERP cloud 100% marocain. Ventes, Achats, Stock, POS, Facturation. Essai gratuit 14 jours, sans carte bancaire.">
    <meta name="twitter:image" content="https://o3app.ma/images/og-preview.png">

    {{-- ── Schema.org (Structured Data) ────────────────────────────── --}}
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "O3 App",
        "url": "https://o3app.ma",
        "description": "Logiciel de gestion commerciale cloud pour les PME marocaines — Ventes, Achats, Stock, POS, Facturation.",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web",
        "offers": [
            {
                "@type": "Offer",
                "name": "Starter",
                "price": "499",
                "priceCurrency": "MAD",
                "billingDuration": "P1M",
                "description": "Gestion des ventes, stock, 1 entrepôt, sous-domaine gratuit"
            },
            {
                "@type": "Offer",
                "name": "Business",
                "price": "999",
                "priceCurrency": "MAD",
                "billingDuration": "P1M",
                "description": "Ventes + Achats + POS, multi-entrepôts, domaine personnalisé"
            },
            {
                "@type": "Offer",
                "name": "Enterprise",
                "price": "1999",
                "priceCurrency": "MAD",
                "billingDuration": "P1M",
                "description": "Tout inclus + e-Commerce, WhatsApp illimité, support dédié"
            }
        ],
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.8",
            "reviewCount": "24"
        },
        "creator": {
            "@type": "Organization",
            "name": "O3 App",
            "url": "https://o3app.ma",
            "logo": "https://o3app.ma/images/logo.png",
            "contactPoint": {
                "@type": "ContactPoint",
                "email": "contact@o3app.ma",
                "contactType": "customer support",
                "availableLanguage": ["French", "Arabic"]
            },
            "address": {
                "@type": "PostalAddress",
                "addressCountry": "MA"
            }
        }
    }
    </script>

    {{-- ── Favicon ─────────────────────────────────────────────────── --}}
    {{--
        Disque orange de marque (#F97316, identique au theme-color ci-dessus)
        et sigle en brun-orange profond (#1C1917) : 6,3:1, lisible à 16 px là
        où du blanc sur cet orange ne donnerait que 2,8:1.
        Le bleu #2563EB d'origine n'appartenait à aucune palette du produit.
    --}}
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><circle cx='16' cy='16' r='14' fill='%23F97316'/><text x='16' y='22' text-anchor='middle' fill='%231C1917' font-size='18' font-weight='bold' font-family='Arial'>O3</text></svg>">

    {{-- ── Vite Assets ─────────────────────────────────────────────── --}}
    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    {{-- ── Touch / Kiosk global CSS ────────────────────────────────── --}}
    <style>
        /* Prevent double-tap zoom and tap flash on all interactive elements */
        button, a, [role="button"], input, select, textarea {
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }
        /* Prevent accidental text selection on UI chrome (not inputs) */
        button, a, label, th, td:not(.selectable), nav, header, [role="button"] {
            -webkit-user-select: none;
            user-select: none;
        }
        /* Ensure minimum 44px touch targets */
        button, a {
            min-height: 36px;
        }
        /* Prevent overscroll bounce (especially on iOS) */
        html, body {
            overscroll-behavior: none;
            -webkit-overflow-scrolling: touch;
        }
        /* Hide scrollbars on POS panels (touch scroll still works) */
        .pos-panel::-webkit-scrollbar { display: none; }
        /* Print: clean page with no browser headers/footers */
        @media print {
            @page { margin: 0; }
            body { margin: 0; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div id="app"></div>
</body>
</html>