<?php

namespace App\Services\Agents;

/**
 * Les phrases de lecture que l'orchestrateur comprend sans modèle de langage, une par commande. Elles servent de
 * catalogue au renfort par IA : quand une question est formulée librement (« combien j'ai vendu hier ? »), le modèle
 * la reformule en l'une de ces phrases, que les règles traitent ensuite comme si elle avait été tapée.
 *
 * Seules des LECTURES figurent ici. L'orchestrateur vérifie en plus que la phrase rendue par le modèle est bien reconnue
 * comme une lecture : un ordre ne passe jamais par ce chemin.
 */
final class ReadCommands
{
    /** @var array<int, string> */
    public const EXAMPLES = [
        // Activité du jour
        'résume la journée', 'que dois-je valider ?', 'sessions de caisse', 'encaissements du jour', 'solde de chaque compte de trésorerie',
        // Ventes
        "chiffre d'affaires du mois", "ventes d'hier", 'ventes du mois par vendeur', 'ventes du mois par caisse', 'ventes du mois par catégorie', 'top 10 des produits vendus ce mois',
        'meilleurs clients du trimestre', 'panier moyen du mois', "évolution du chiffre d'affaires sur 6 mois", 'marge réalisée du mois', 'remises accordées ce mois',
        'lignes vendues sous le prix de référence', 'compare ce mois au mois dernier', 'ventes par jour de la semaine', 'heures de pointe', 'tickets annulés ce mois', 'factures annulées ce mois',
        // Clients et recouvrement
        'factures échues', 'factures impayées du client Atlas', 'devis sans suite depuis 10 jours', 'taux de transformation des devis', 'bons de livraison non facturés',
        'commandes clients en attente de livraison', 'retours et avoirs du mois', 'clients inactifs depuis 60 jours', 'clients qui dépassent leur seuil de crédit', 'clients en compte à facturer ce mois',
        'fiche du client Atlas', 'factures du client Atlas', 'produits achetés par le client Atlas', 'clients sans téléphone, e-mail ou ICE', 'doublons de clients', 'nouveaux clients du mois', 'clients par ville',
        // Achats
        'achats du mois par fournisseur', 'factures fournisseurs à payer', 'factures fournisseurs à payer cette semaine', 'bons de commande en attente de réception', "prix d'achat en hausse",
        'produits sans fournisseur', 'fournisseur le moins cher pour perceuse', 'fournisseurs inactifs depuis un an', 'fiche du fournisseur Bati',
        // Stock et catalogue
        'valeur du stock', 'valeur du stock par catégorie', 'produits dormants', 'produits à stock négatif', 'produits bientôt en rupture', 'transferts en attente', 'pertes du mois',
        'mouvements de stock du mois', 'mouvements du produit PRC1', "ajustements d'inventaire récents", 'mouvements de stock en attente', 'fiche du produit PRC1', 'stock de perceuse',
        'doublons de produits', 'marge par catégorie', 'marge par marque', 'produits jamais vendus depuis 90 jours', 'produits avec une TVA inhabituelle', 'codes-barres invalides',
        'produits par catégorie', 'produits sans marque', 'produits absents de la liste de prix revendeur', 'nouveaux produits du mois', 'valeur du stock par catégorie',
        // Trésorerie
        'dépenses du mois par catégorie', 'dépenses sans justificatif', 'flux de trésorerie du mois', 'prévision de trésorerie à 30 jours', 'opérations récurrentes des 30 prochains jours', 'chèques et effets reçus ce mois',
        // Suivi, documents, droits
        'montre la facture FV-0001', 'cherche perceuse', 'brouillons anciens', 'derniers documents créés', 'qui a modifié la facture FV-0001', 'activité récente', 'actions des agents aujourd\'hui',
        'utilisateurs inactifs', 'dernières connexions', 'permissions du rôle manager', 'promotions actives', 'produits en ligne sans stock ou sans photo', 'bannières actives', 'terminaux de caisse',
        'produits de la promotion rentrée', 'promotions du produit PRC1', 'règles de routage', 'seuils des agents', 'événements des agents du mois', 'mes notifications non lues', 'appareils abonnés aux notifications',
        'factures non envoyées', 'répartition des ventes par mode de paiement', 'livraisons par ville', 'mes entrepôts', 'catégories de trésorerie', 'listes de prix',
        'commandes WhatsApp du jour', "importations d'achat récentes", 'relances de paiement du mois', 'dossiers ouverts des agents', 'produits avec variantes', 'prix du produit PRC1 par liste de prix',
    ];

    /** La liste, une phrase par ligne, pour l'invite du modèle. */
    public static function prompt(): string
    {
        return '- ' . implode("\n- ", array_values(array_unique(self::EXAMPLES)));
    }
}
