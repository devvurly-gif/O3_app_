<?php

namespace App\Services\Agents;

/**
 * « Comment tu calcules la marge ? », « c'est quoi un produit dormant ? » : la définition de chaque chiffre que les
 * lectures donnent, en clair. Un chiffre dont on ignore la règle est un chiffre auquel on ne peut pas se fier ; chaque
 * définition dit donc ce qui est compté, ce qui est exclu et la limite connue.
 *
 * Texte seulement : aucune lecture de la base, aucun appel à un modèle de langage. À tenir à jour avec les lectures
 * (un test vérifie que chaque définition est joignable par une question).
 */
class GlossaryAssistant
{
    /**
     * @var array<string, array{title: string, keys: string, text: string, read: string}>
     */
    public const ENTRIES = [
        'ca' => ['title' => "Chiffre d'affaires", 'keys' => "chiffre d.affaires|\\bca\\b|ventes? du mois", 'read' => "chiffre d'affaires du mois",
            'text' => "Somme des factures de vente et des tickets de caisse de la période, hors brouillons et annulés ; HT et TTC lus sur le pied du document. La date retenue est la date d'émission. Les avoirs sont annoncés à part et ne sont pas déduits."],
        'marge_realisee' => ['title' => 'Marge réalisée', 'keys' => 'marge realisee|benefice|rentabilite|marge du mois', 'read' => 'marge réalisée du mois',
            'text' => "Chiffre d'affaires HT des lignes de produit vendues moins leur coût. Le coût est celui de la fiche produit aujourd'hui (le coût, à défaut le prix d'achat), pas celui du jour de la vente ; les lignes sans produit sont ignorées."],
        'marge_catalogue' => ['title' => 'Marge par catégorie ou par marque', 'keys' => 'marge par|marge moyenne|marge brute', 'read' => 'marge par catégorie',
            'text' => "(prix de vente − prix d'achat) / prix de vente, avec les prix saisis dans la fiche, sur les produits actifs qui ont les deux prix."],
        'dormant' => ['title' => 'Produit dormant', 'keys' => 'dormants?|sans mouvement|sans rotation', 'read' => 'produits dormants',
            'text' => "Produit en stock qui n'a eu aucun mouvement de stock non annulé depuis N jours (90 par défaut). La valeur immobilisée est la quantité en stock fois le coût moyen."],
        'inactif' => ['title' => 'Client inactif', 'keys' => 'clients? inactifs?|client inactif|inactivite', 'read' => 'clients inactifs depuis 60 jours',
            'text' => "Client actif qui n'a aucune facture ni ticket (hors brouillons et annulés) depuis N jours (60 par défaut). « Jamais acheté » si aucune vente n'existe."],
        'echue' => ['title' => 'Facture échue', 'keys' => 'factures? echues?|echeance depassee|en retard de paiement|impayes?', 'read' => 'factures échues',
            'text' => "Facture de vente (ni brouillon, ni annulée) dont il reste un montant à payer et dont la date d'échéance est dépassée. Une facture sans échéance n'est jamais échue."],
        'devis' => ['title' => 'Devis sans suite', 'keys' => 'devis sans suite|devis ouverts?|devis non', 'read' => 'devis sans suite depuis 10 jours',
            'text' => "Devis envoyé, confirmé ou en attente (ni brouillon, ni converti, ni annulé) émis depuis plus de N jours (10 par défaut). Le taux de transformation compare les devis convertis à tous les devis non brouillons de la période."],
        'bl' => ['title' => 'Bon de livraison non facturé', 'keys' => 'bons? de livraison non|bl non|non factures?', 'read' => 'bons de livraison non facturés',
            'text' => "Bon de livraison confirmé, envoyé, livré ou en attente qui n'a été ni converti en facture, ni annulé, et n'est pas un brouillon."],
        'stock_faible' => ['title' => 'Stock faible', 'keys' => 'stock faible|stock bas|seuil d.alerte|en rupture|rupture de stock', 'read' => 'stock faible',
            'text' => "Produit dont le stock total (tous entrepôts) est au seuil d'alerte ou en dessous. Le seuil est le réglage « seuil d'alerte stock » du stock (5 par défaut)."],
        'rupture_avenir' => ['title' => 'Rupture à venir', 'keys' => 'bientot en rupture|rupture a venir|couverture', 'read' => 'produits bientôt en rupture',
            'text' => "Stock actuel divisé par la vente moyenne par jour des 30 derniers jours. On liste les produits dont cette couverture est inférieure au nombre de jours demandé (14 par défaut). Suppose que le rythme de vente continue à l'identique."],
        'hausse_achat' => ['title' => "Hausse du prix d'achat", 'keys' => "prix d.achat en hausse|hausse du prix", 'read' => "prix d'achat en hausse",
            'text' => "Produit dont le prix moyen sur les factures d'achat des 30 derniers jours dépasse de plus de 5 % celui des 90 jours précédents."],
        'prevision' => ['title' => 'Prévision de trésorerie', 'keys' => 'prevision de tresorerie|tresorerie previsionnelle|solde previsible', 'read' => 'prévision de trésorerie à 30 jours',
            'text' => "Solde actuel des comptes + factures clients échéant dans 30 jours − factures fournisseurs échues ou à échoir ± opérations récurrentes. Les factures clients déjà échues et les ventes à venir ne sont pas comptées : c'est une estimation à partir des échéances saisies, pas une garantie."],
        'tva' => ['title' => 'TVA', 'keys' => '\btva\b|taxes?', 'read' => 'TVA du mois',
            'text' => "TVA collectée (factures de vente et tickets, avoirs déduits) moins TVA déductible (factures d'achat, avoirs déduits), d'après le montant de TVA de chaque document. Une estimation : ce n'est pas la déclaration (régime, prorata et opérations hors documents ne sont pas pris en compte)."],
        'panier' => ['title' => 'Panier moyen', 'keys' => 'panier moyen|ticket moyen', 'read' => 'panier moyen du mois',
            'text' => "Montant TTC moyen d'une vente (facture ou ticket, hors brouillons et annulés), donné par type de document puis pour l'ensemble."],
        'encaissements' => ['title' => 'Encaissements', 'keys' => 'encaissements?', 'read' => 'encaissements du jour',
            'text' => "Paiements enregistrés sur des factures de vente, tickets, bons de livraison ou commandes clients, à la date du paiement. Les paiements aux fournisseurs sont donnés à part."],
        'solde' => ['title' => 'Solde de trésorerie', 'keys' => 'solde de tresorerie|solde des comptes|soldes?', 'read' => 'solde de chaque compte de trésorerie',
            'text' => "Solde initial du compte + entrées − sorties actives (annulées exclues). Les virements entre comptes y sont compris : ils s'annulent dans le total."],
        'encours' => ['title' => 'Encours et seuil de crédit', 'keys' => 'encours|seuil de credit|plafond de credit', 'read' => 'clients qui dépassent leur seuil de crédit',
            'text' => "L'encours est le montant que le client doit, tel qu'il est tenu à jour sur sa fiche ; on le compare au seuil de crédit de la fiche. Un seuil à zéro veut dire « pas de plafond »."],
        'perte' => ['title' => 'Produit vendu à perte', 'keys' => 'a perte|sous le prix d.achat|prix trop bas', 'read' => 'produits vendus à perte',
            'text' => "Produit actif dont le prix de vente de la fiche est inférieur à son prix d'achat. Ne dit rien des remises accordées sur une vente précise : pour cela, « remises accordées ce mois »."],
        'brouillon' => ['title' => 'Brouillon ancien', 'keys' => 'brouillons? (anciens?|oublies?)|brouillons?', 'read' => 'brouillons anciens',
            'text' => "Document en brouillon, hors documents de stock, créé depuis plus de N jours (7 par défaut)."],
        'remises' => ['title' => 'Remises', 'keys' => 'remises?', 'read' => 'remises accordées ce mois',
            'text' => "Lignes de produit vendues avec un pourcentage de remise ; le montant d'une remise est quantité × prix unitaire × pourcentage."],
        'valeur_stock' => ['title' => 'Valeur du stock', 'keys' => 'valeur du stock|stock valorise', 'read' => 'valeur du stock',
            'text' => "Quantité en stock (positive) × coût moyen de l'entrepôt, par entrepôt puis au total."],
    ];

    /** La question est-elle une demande de définition ? @param string $n phrase normalisée */
    public static function asks(string $n): bool
    {
        return (bool) preg_match('/comment (tu |on |vous |est ce que tu |est ce qu on |est ce que vous |est |sont |se )?(calcule|mesure|determine|obtient|obtiens)\w*|c est quoi|qu est ce (que c est|qu un|qu une)|que (veut dire|signifie)|\bdefinition\b|\bexplique\w*\b|d ou (vient|viens|sort)\w* (le|ce) (chiffre|montant|total)|\bglossaire\b|\bquelles? definitions\b/', $n);
    }

    /** Une phrase de définition. Null si ce n'en est pas une. @param string $n phrase normalisée */
    public function answer(string $n): ?array
    {
        $n = trim(preg_replace('/[^a-z0-9 ]+/', ' ', str_replace(["'", '’'], ' ', $n)) ?? $n);
        $n = trim(preg_replace('/\s+/', ' ', $n) ?? $n);
        if (!self::asks($n)) {
            return null;
        }

        $best = null;
        $bestLen = 0;
        foreach (self::ENTRIES as $key => $e) {
            if (preg_match('/' . $e['keys'] . '/', $n, $m) && mb_strlen($m[0]) > $bestLen) {
                [$best, $bestLen] = [$e, mb_strlen($m[0])];
            }
        }

        if ($best === null) {
            return $this->reply("Je peux expliquer comment sont calculés ces chiffres :\n\n" . collect(self::ENTRIES)->map(fn ($e) => "• {$e['title']}")->implode("\n") . "\n\nPar exemple : « comment tu calcules la marge réalisée ? » ou « c'est quoi un produit dormant ? ».");
        }

        return $this->reply("{$best['title']} — {$best['text']}", [['label' => 'Voir le chiffre', 'text' => $best['read']]]);
    }

    /**
     * @param array<int, array{label: string, text: string}> $suggestions
     * @return array{body: string, meta: array<string, mixed>}
     */
    private function reply(string $body, array $suggestions = []): array
    {
        return ['body' => $body, 'meta' => array_filter(['intent' => 'glossary', 'suggestions' => $suggestions ?: null], fn ($v) => $v !== null)];
    }
}
