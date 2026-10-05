<?php

namespace App\Services\Agents;

/**
 * Ce que l'on peut faire dans O3 App, par écran. L'orchestrateur s'en sert pour répondre à
 * « que peut-on faire ? » et à « où fait-on X ? » : il décrit et renvoie vers l'écran.
 *
 * Il ne fait rien dans ces écrans. Seules les demandes que les agents savent exécuter
 * (inventaire, encaissements…) restent des ordres ; ici, c'est de l'orientation en lecture seule.
 * Les chemins sont ceux du routeur du frontend (resources/js/router/index.ts).
 *
 * `keywords` : mots (sans accent, minuscules) qui désignent l'écran dans une phrase.
 * `feature`  : capacité de la formule requise (config/plans.php), s'il y en a une.
 * `agent`    : l'agent qui automatise déjà une partie de ce sujet, s'il y en a un.
 */
class AppCatalog
{
    /** @return array<string, array{title: string, group: string, path: string, does: string, keywords: array<int, string>, feature?: string, agent?: string}> */
    public static function screens(): array
    {
        return [
            'dashboard'   => ['title' => 'Tableau de bord', 'group' => 'Général', 'path' => '/dashboard', 'does' => "vue d'ensemble de l'activité (ventes, stock, encaissements)", 'keywords' => ['tableau de bord', 'dashboard', 'accueil', "vue d'ensemble"]],
            'rapports'    => ['title' => 'Rapports', 'group' => 'Général', 'path' => '/reports', 'does' => 'rapports de ventes, achats, stock et marges', 'keywords' => ['rapport', 'statistique', 'marge', 'chiffre d\'affaires'], 'feature' => 'reports'],
            'guides'      => ['title' => "Guides d'utilisation", 'group' => 'Général', 'path' => '/guides', 'does' => "mode d'emploi de l'application", 'keywords' => ['guide', "mode d'emploi", 'tutoriel', 'documentation']],

            'produits'    => ['title' => 'Produits', 'group' => 'Catalogue', 'path' => '/products', 'does' => 'créer, modifier, activer ou désactiver les fiches produits (prix, stock minimum, photos, codes)', 'keywords' => ['produit', 'prouit', 'fiche', 'article', 'catalogue', 'reference', 'sku']],
            'corbeille'   => ['title' => 'Produits supprimés', 'group' => 'Catalogue', 'path' => '/products/trashed', 'does' => 'retrouver et restaurer un produit supprimé', 'keywords' => ['corbeille', 'produit supprime', 'restaurer']],
            'categories'  => ['title' => 'Catégories', 'group' => 'Catalogue', 'path' => '/categories', 'does' => 'organiser les produits par catégorie', 'keywords' => ['categorie', 'famille']],
            'marques'     => ['title' => 'Marques', 'group' => 'Catalogue', 'path' => '/brands', 'does' => 'gérer les marques des produits', 'keywords' => ['marque', 'brand']],
            'tarifs'      => ['title' => 'Listes de prix', 'group' => 'Catalogue', 'path' => '/price-lists', 'does' => 'tarifs par type de client ou par canal', 'keywords' => ['liste de prix', 'liste des prix', 'tarif', 'grille']],
            'prix_masse'  => ['title' => 'Révision des prix', 'group' => 'Catalogue', 'path' => '/settings/bulk-prices', 'does' => 'modifier les prix de plusieurs produits à la fois', 'keywords' => ['revision des prix', 'prix en masse', 'changer les prix', 'modifier les prix', 'augmenter les prix', 'prix']],
            'etiquettes'  => ['title' => 'Étiquettes produits', 'group' => 'Catalogue', 'path' => '/products/labels', 'does' => 'imprimer des étiquettes avec codes-barres', 'keywords' => ['etiquette', 'code-barre', 'code barre']],
            'galerie'     => ['title' => 'Galerie images', 'group' => 'Catalogue', 'path' => '/storage/gallery', 'does' => 'les images téléversées (photos produits, bannières)', 'keywords' => ['galerie', 'image', 'photo']],
            'imports'     => ['title' => 'Imports', 'group' => 'Catalogue', 'path' => '/settings/imports', 'does' => 'importer produits, clients ou fournisseurs depuis un fichier Excel', 'keywords' => ['import', 'excel', 'csv', 'fichier']],

            'clients'     => ['title' => 'Clients', 'group' => 'Tiers', 'path' => '/customers', 'does' => 'fiches clients, plafond de crédit, historique', 'keywords' => ['client']],
            'fournisseurs' => ['title' => 'Fournisseurs', 'group' => 'Tiers', 'path' => '/suppliers', 'does' => 'fiches fournisseurs et leur historique', 'keywords' => ['fournisseur']],

            'ventes'      => ['title' => 'Documents de vente', 'group' => 'Ventes', 'path' => '/ventes/documents', 'does' => 'devis, bons de commande et de livraison, factures, avoirs', 'keywords' => ['vente', 'devis', 'facture client', 'bon de livraison', 'bl', 'avoir', 'commande client', 'facturer']],
            'ventes_new'  => ['title' => 'Nouveau document de vente', 'group' => 'Ventes', 'path' => '/ventes/documents/create', 'does' => 'créer un devis, un bon de livraison ou une facture', 'keywords' => ['nouvelle facture', 'nouveau devis', 'nouveau bl', 'creer une facture', 'creer un devis', 'creer un bon']],
            'relances'    => ['title' => 'Relances de paiement', 'group' => 'Ventes', 'path' => '/ventes/relances', 'does' => 'valider et envoyer les relances préparées par l\'agent Recouvrement', 'keywords' => ['relance', 'impaye'], 'agent' => 'Recouvrement'],
            'messagerie'  => ['title' => 'Messagerie commandes', 'group' => 'Ventes', 'path' => '/ventes/messagerie', 'does' => 'commandes reçues par WhatsApp ou SMS, transformées en brouillons de bon de livraison', 'keywords' => ['messagerie', 'whatsapp', 'sms', 'commande recue']],
            'pos'         => ['title' => 'Point de vente', 'group' => 'Ventes', 'path' => '/pos', 'does' => 'caisse tactile : ventes comptoir, tickets, fermeture de session', 'keywords' => ['pos', 'caisse', 'point de vente', 'ticket', 'comptoir'], 'feature' => 'pos'],
            'pos_sessions' => ['title' => 'Sessions POS', 'group' => 'Ventes', 'path' => '/settings/pos-sessions', 'does' => 'ouvertures, fermetures et écarts de caisse', 'keywords' => ['session pos', 'fermeture de caisse', 'ecart de caisse'], 'feature' => 'pos'],
            'pos_terminaux' => ['title' => 'Terminaux POS', 'group' => 'Ventes', 'path' => '/settings/pos-terminals', 'does' => 'déclarer les caisses et leurs imprimantes', 'keywords' => ['terminal', 'imprimante', 'caisse enregistreuse'], 'feature' => 'pos'],
            'promotions'  => ['title' => 'Promotions & bannières', 'group' => 'Ventes', 'path' => '/marketing/promotions', 'does' => 'promotions et bannières de la boutique', 'keywords' => ['promotion', 'promo', 'banniere', 'slide', 'marketing']],

            'achats'      => ['title' => "Documents d'achat", 'group' => 'Achats', 'path' => '/achats/documents', 'does' => "bons de commande, réceptions et factures d'achat", 'keywords' => ['achat', 'facture fournisseur', 'bon de reception', 'commande fournisseur', 'reception'], 'agent' => 'Achats'],
            'achats_ocr'  => ['title' => 'Import facture OCR', 'group' => 'Achats', 'path' => '/achats/ocr-import', 'does' => "lire une facture fournisseur (PDF ou photo) et préparer le brouillon d'achat", 'keywords' => ['ocr', 'importer une facture', 'import facture', 'scanner', 'lire une facture'], 'feature' => 'ocr_import', 'agent' => 'Achats'],

            'inventaire'  => ['title' => 'Inventaire', 'group' => 'Stock', 'path' => '/stock/inventaire', 'does' => "feuilles de comptage préparées par l'agent Stocks, saisie des comptages et application des écarts", 'keywords' => ['inventaire', 'comptage'], 'agent' => 'Stocks'],
            'mouvements'  => ['title' => 'Mouvements de stock', 'group' => 'Stock', 'path' => '/stock/mouvements', 'does' => 'historique des entrées et sorties de stock', 'keywords' => ['mouvement', 'historique du stock', 'entree de stock', 'sortie de stock']],
            'stock_docs'  => ['title' => 'Documents de stock', 'group' => 'Stock', 'path' => '/stock/documents', 'does' => 'transferts entre entrepôts, ajustements, sorties et entrées', 'keywords' => ['transfert', 'ajustement', 'document de stock', 'sortie', 'entree']],
            'entrepots'   => ['title' => 'Entrepôts', 'group' => 'Stock', 'path' => '/warehouses', 'does' => 'gérer les dépôts et magasins', 'keywords' => ['entrepot', 'depot', 'magasin']],

            'tresorerie'  => ['title' => 'Trésorerie', 'group' => 'Finances', 'path' => '/treasury', 'does' => 'encaissements, décaissements, caisses et comptes', 'keywords' => ['tresorerie', 'encaissement', 'decaissement', 'paiement', 'reglement', 'banque', 'cheque']],

            'utilisateurs' => ['title' => 'Utilisateurs', 'group' => 'Administration', 'path' => '/settings/users', 'does' => 'créer les comptes et activer ou bloquer un accès', 'keywords' => ['utilisateur', 'compte', 'employe', 'mot de passe']],
            'roles'       => ['title' => 'Rôles & permissions', 'group' => 'Administration', 'path' => '/settings/roles', 'does' => 'ce que chaque rôle a le droit de faire', 'keywords' => ['role', 'permission', 'droit', 'acces']],
            'parametres'  => ['title' => 'Paramètres', 'group' => 'Administration', 'path' => '/settings/app', 'does' => 'société, facturation, stock, messagerie (WhatsApp, SMS, e-mail), clés IA', 'keywords' => ['parametre', 'reglage', 'configuration', 'societe', 'tva', 'devise', 'cle api', 'anthropic']],
            'numeroteurs' => ['title' => 'Numérotation des documents', 'group' => 'Administration', 'path' => '/settings/document-incrementors', 'does' => 'format et compteur des numéros de documents', 'keywords' => ['numerotation', 'numeroteur', 'compteur', 'prefixe']],
            'modeles'     => ['title' => 'Modèles de documents', 'group' => 'Administration', 'path' => '/settings/document-templates', 'does' => "mise en page des factures et bons à l'impression", 'keywords' => ['modele de document', 'mise en page', 'impression', 'logo', 'pdf']],
            'audit'       => ['title' => "Piste d'audit", 'group' => 'Administration', 'path' => '/settings/activity-log', 'does' => 'qui a fait quoi, et quand', 'keywords' => ['audit', 'journal', 'historique des actions', 'qui a modifie', 'trace']],
            'orchestrateur' => ['title' => 'Orchestrateur', 'group' => 'Agents', 'path' => '/settings/orchestrateur', 'does' => "cet écran : parler au chef des agents", 'keywords' => ['ecran orchestrateur']],
            'agents'      => ['title' => 'Activité des agents', 'group' => 'Agents', 'path' => '/settings/agents', 'does' => 'événements reçus, agents actifs, journal de ce que chaque agent a fait', 'keywords' => ['activite des agents', 'journal des agents', 'evenement']],
            'abonnement'  => ['title' => 'Abonnement', 'group' => 'Compte', 'path' => '/abonnement', 'does' => 'formule, essai et facturation de votre abonnement', 'keywords' => ['abonnement', 'formule', 'essai', 'plan']],
            'profil'      => ['title' => 'Mon profil', 'group' => 'Compte', 'path' => '/profile', 'does' => 'vos informations et votre mot de passe', 'keywords' => ['profil', 'mon compte']],
        ];
    }

    /**
     * Les écrans désignés par une phrase (déjà normalisée : sans accent, en minuscules), du mieux
     * placé au moins bien placé. Un mot-clé plus long l'emporte : « liste de prix » avant « prix ».
     *
     * @return array<int, string> clés de screens()
     */
    public static function match(string $normalized): array
    {
        $scores = [];
        foreach (self::screens() as $key => $screen) {
            foreach ($screen['keywords'] as $word) {
                if (preg_match('/(?<![a-z0-9])' . preg_quote($word, '/') . '(?:s|x)?(?![a-z0-9])/', $normalized)) {
                    $scores[$key] = max($scores[$key] ?? 0, strlen($word));
                }
            }
        }
        arsort($scores);

        return array_keys($scores);
    }

    /** Écrans regroupés par domaine, dans l'ordre du menu. @return array<string, array<string, array<string, mixed>>> */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::screens() as $key => $screen) {
            $groups[$screen['group']][$key] = $screen;
        }

        return $groups;
    }
}
