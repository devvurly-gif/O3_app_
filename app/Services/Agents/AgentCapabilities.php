<?php

namespace App\Services\Agents;

/**
 * Le catalogue des tâches connues : ce que sait faire chaque agent du socle aujourd'hui, et ce qui lui manque
 * encore. Il sert à répondre à « que sait faire chaque agent ? » et à rédiger une demande de développement
 * quand une tâche n'existe pas. À tenir à jour à chaque nouvelle capacité (même lieu que la spécification).
 *
 * Chaque tâche : [libellé, comment la demander]. « manque » : ce qui n'existe pas encore pour ce domaine.
 */
class AgentCapabilities
{
    /** @var array<string, array{tasks: array<int, array{0: string, 1: string}>, missing: array<int, string>}> */
    public const BY_DOMAIN = [
        'achats' => [
            'tasks' => [
                ['Lire une facture fournisseur (photo ou PDF) et préparer un brouillon d\'achat', 'déposez le fichier avec le trombone'],
                ['Brouillons de bons de commande fournisseurs chaque matin', 'planificateur (07:30)'],
                ['Contrôler les fiches produits : photo, description, catégorie, marque, prix, code-barres', '« mettre à jour les fiches produits »'],
                ['Compléter descriptions, catégories, marques et descriptions longues (IA)', '« complète les descriptions, catégories et marques des fiches produits »'],
                ['Réviser les prix par marge', '« révise les prix des fiches produits avec une marge de 25 % »'],
                ['Attribuer des codes-barres internes', '« attribue des codes-barres aux fiches produits »'],
                ['Activer les fiches prêtes', '« active les fiches produits »'],
                ['Rattacher une photo à un produit', 'déposez la photo avec le trombone'],
                ['Enchaîner tout cela jusqu\'à des fiches prêtes à l\'emploi', '« prépare les fiches pour l\'utilisation »'],
                ['Réapprovisionner le stock faible : un bon de commande fournisseur brouillon par fournisseur, à créer après votre clic', '« réapprovisionne le stock faible »'],
            ],
            'missing' => ['comparer les prix de plusieurs fournisseurs', 'relancer un fournisseur en retard de livraison'],
        ],
        'ventes' => [
            'tasks' => [
                ['Préparer les relances des devis sans réponse (messages WhatsApp ou e-mail prêts à envoyer, rien n\'est envoyé par O3)', '« relance les devis sans réponse »'],
                ['Transformer une commande reçue par WhatsApp, SMS ou chat en brouillon de bon de livraison', 'automatique (messagerie), ou « Messagerie commandes »'],
                ['Lire un bon de commande client (photo ou PDF) et préparer un brouillon de livraison', 'déposez le fichier avec le trombone'],
            ],
            'missing' => ['préparer des devis à partir d\'une demande de prix', 'suivre les clients qui n\'ont pas commandé depuis longtemps'],
        ],
        'stocks' => [
            'tasks' => [
                ['Préparer une feuille d\'inventaire (tous les entrepôts, un entrepôt, ou les articles à vérifier)', '« prépare un inventaire »'],
                ['Alerte quand le stock est bas', 'planificateur (08:00)'],
                ['Proposer des transferts entre entrepôts : un bon de transfert brouillon par couple d\'entrepôts, le stock ne bouge qu\'à l\'application du bon', '« propose des transferts entre entrepôts »'],
            ],
            'missing' => [],
        ],
        'recouvrement' => [
            'tasks' => [
                ['Contrôler les encaissements et préparer les relances de paiement en brouillon', '« contrôle les encaissements »'],
                ['Voir les relances à valider', '« relances à valider »'],
                ['Proposer un échéancier de paiement en mensualités et préparer le message au client (rien n\'est envoyé, aucune facture modifiée)', '« propose un échéancier pour Atlas en 3 mensualités »'],
                ['Suivre les échéanciers enregistrés', '« échéanciers en cours »'],
                ['Repérer les versements en retard et préparer les messages de relance (rien n\'est envoyé) ; à planifier en routine', '« relance les versements en retard »'],
                ['Rapprocher un paiement reçu (virement, chèque, espèces, effet) des factures de vente : affectation proposée, règlement enregistré à votre clic, client non notifié', '« rapproche un virement de 4 500 dirhams de Atlas »'],
            ],
            'missing' => [],
        ],
        'expedition' => [
            'tasks' => [],
            'missing' => ['préparer les livraisons du jour', 'suivre un colis et répondre « où est ma commande »', 'organiser les tournées'],
        ],
        'comptabilite' => [
            'tasks' => [],
            'missing' => ['rapprochement bancaire', 'préparation de la déclaration de TVA', 'contrôle des écritures'],
        ],
        'marketing' => [
            'tasks' => [
                ['Publier sur la boutique en ligne (website) les fiches actives, complètes et avec photo', '« publie les produits sur le website »'],
            ],
            'missing' => ['promotions et bannières', 'messages et publicités à partir des produits', 'relance de clients par e-mail ou WhatsApp'],
        ],
    ];

    /** @return array<string, array{tasks: array<int, array{0: string, 1: string}>, missing: array<int, string>}> */
    public static function all(): array
    {
        return self::BY_DOMAIN;
    }

    /** Les demandes que l'orchestrateur comprend en général (hors agents). */
    public const GENERAL = [
        ['Dire où faire quelque chose dans O3 (38 écrans)', '« que peut-on faire dans O3 », « où créer une facture »'],
        ['Lire un document déposé (facture, commande, paiement, photo) et proposer la suite', 'trombone'],
        ['Lire l\'activité de l\'entreprise : point de la journée, ventes, factures échues, devis sans suite, encaissements, soldes, sessions de caisse, stock (valeur, dormants, transferts, pertes), doublons, marges, clients inactifs, seuils de crédit, achats et fournisseurs, remises, dépenses, activité récente, promotions, classements de ventes, qualité du catalogue, historique d\'une fiche, actions des agents, fiches produit/client/document, marge réalisée, tendances, ruptures à venir, prévision de trésorerie, recherche, documents d\'un tiers, brouillons oubliés, comparaison de périodes, messagerie, relances, droits, chèques, bannières, terminaux, variantes, questions libres, promotions par produit, règles et seuils des agents, notifications, entrepôts, listes de prix', '« résume la journée », « chiffre d\'affaires du mois », « factures échues »'],
        ['Recruter un agent de lecture et de rapport, planifier des routines, retenir des consignes', '« recrute un agent qui… », « chaque lundi… », « retiens : … »'],
    ];
}
