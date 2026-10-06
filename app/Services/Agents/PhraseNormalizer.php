<?php

namespace App\Services\Agents;

/**
 * Remet une phrase que les règles n'ont pas comprise sous la forme que celles-ci connaissent : fautes de frappe
 * (« stok bas », « cleints inactifs »), abréviations (« ajd », « cmd », « tréso »), anglais (« overdue invoices »)
 * et darija transcrite (« chhal bi3t lyoum », « stock qalil »).
 *
 * N'agit qu'en second recours : l'orchestrateur essaie toujours la phrase telle quelle, et ne se sert de cette
 * version que si elle est comprise alors que l'originale ne l'était pas. Aucun modèle de langage, aucune donnée
 * de l'entreprise : une table de correspondances et une distance d'édition de un ou deux caractères.
 */
final class PhraseNormalizer
{
    /** Les mots du métier que l'on sait corriger : une faute d'un caractère s'y ramène. */
    private const VOCAB = [
        'chiffre', 'affaires', 'factures', 'facture', 'impayees', 'impayes', 'echues', 'retard', 'stock', 'stocks', 'faible', 'produits', 'produit', 'dormants', 'dormant', 'clients', 'client',
        'inactifs', 'inactif', 'fournisseurs', 'fournisseur', 'resume', 'journee', 'ventes', 'vente', 'hier', 'semaine', 'annee', 'marge', 'benefice', 'devis', 'commandes', 'livraison',
        'livraisons', 'encaissements', 'encaissement', 'tresorerie', 'depenses', 'depense', 'solde', 'caisse', 'achats', 'prix', 'valider', 'validation', 'relances', 'paiements', 'paiement',
        'remises', 'promotions', 'utilisateurs', 'entrepots', 'rupture', 'commander', 'rentabilite', 'meilleurs', 'principaux', 'dernier', 'derniere', 'derniers', 'dernieres', 'comparer',
        'evolution', 'tendance', 'categorie', 'categories', 'marque', 'marques', 'echeance', 'combien', 'vendu', 'argent', 'fiche', 'historique', 'connexions', 'notifications', 'bannieres',
        'terminaux', 'variantes', 'agents', 'routage', 'seuils', 'evenements', 'documents', 'brouillons', 'anciens', 'doublons', 'inventaire', 'fournisseurs', 'tickets', 'retours', 'avoirs',
    ];

    /** @var array<string, string> abréviations, anglais et darija, mot à mot */
    private const WORDS = [
        'ajd' => 'aujourd hui', 'stk' => 'stock', 'enc' => 'encaissements', 'treso' => 'tresorerie', 'cmd' => 'commandes', 'cmds' => 'commandes', 'fact' => 'factures', 'bl' => 'bons de livraison',
        'devi' => 'devis', 'stok' => 'stock', 'stoc' => 'stock', 'stck' => 'stock', 'resumer' => 'resume', 'dhier' => 'd hier', 'daujourdhui' => 'd aujourd hui', 'aujourdhui' => 'aujourd hui',
        // anglais
        'sales' => 'ventes', 'sale' => 'vente', 'today' => 'aujourd hui', 'yesterday' => 'hier', 'month' => 'mois', 'monthly' => 'du mois', 'week' => 'semaine', 'weekly' => 'de la semaine', 'year' => 'annee',
        'invoices' => 'factures', 'invoice' => 'facture', 'customers' => 'clients', 'customer' => 'client', 'suppliers' => 'fournisseurs', 'supplier' => 'fournisseur', 'products' => 'produits',
        'product' => 'produit', 'quotes' => 'devis', 'quote' => 'devis', 'orders' => 'commandes', 'payments' => 'paiements', 'expenses' => 'depenses', 'inactive' => 'inactifs', 'stock' => 'stock',
        'overdue' => 'echues', 'unpaid' => 'impayees', 'balance' => 'solde', 'cash' => 'caisse', 'margin' => 'marge', 'deliveries' => 'livraisons',
        // darija transcrite
        'lyoum' => 'aujourd hui', 'lyom' => 'aujourd hui', 'bar7' => 'hier', 'lbare7' => 'hier', 'flous' => 'argent', 'lflous' => 'argent', 'chhal' => 'combien', 'kifach' => 'comment', 'dyal' => 'de', 'dial' => 'de',
    ];

    /** @var array<int, array{0: string, 1: string}> compositions à remplacer en bloc (appliquées avant les mots) */
    private const PHRASES = [
        // anglais
        ['/\bhow much (did i|have i|do i) (sell|sold|made|make)\b/', 'combien j ai vendu'],
        ['/\bwho owes me\b.*/', 'qui me doit de l argent'],
        ['/\bwho (has not|hasn t|did not|didn t|have not) paid\b.*/', 'qui n a pas paye'],
        ['/\b(what|which) (should|do) i (validate|approve)\b.*|\bpending approvals?\b|\bto approve\b/', 'que dois je valider'],
        ['/\bdaily (summary|report|recap)\b|\bsummary of (the day|today)\b/', 'resume de la journee'],
        ['/\bout of stock\b/', 'en rupture'],
        ['/\blow stock\b/', 'stock faible'],
        ['/\bcash (balance|available|in hand)\b|\bhow much (cash|money) do i have\b/', 'argent en caisse'],
        ['/\b(monthly )?(revenue|turnover)\b( (this|for the) month)?/', 'chiffre d affaires du mois'],
        ['/\bstock value\b|\bvalue of (my )?stock\b/', 'valeur du stock'],
        ['/\b(net )?(profit|income)\b/', 'benefice'],
        ['/\bsupplier invoices?\b.*\b(pay|due)\b|\bbills? to pay\b/', 'factures fournisseurs a payer'],
        ['/\bbest (customers|clients)\b|\btop (customers|clients)\b/', 'meilleurs clients'],
        ['/\btop (products|items)\b|\bbest sellers?\b/', 'top 10 des produits vendus'],
        ['/\boverdue invoices?\b|\blate invoices?\b/', 'factures echues'],
        ['/\bunpaid invoices?\b/', 'factures impayees'],
        ['/\b(today s|todays) sales\b|\bsales (for )?today\b/', 'ventes d aujourd hui'],
        ['/\b(yesterday s) sales\b|\bsales (for )?yesterday\b/', 'ventes d hier'],
        // darija transcrite
        ['/\bchhal (bi3t|bi3na|b3t|bi3ti|bi3o)\b/', 'combien j ai vendu'],
        ['/\bchkoun (li|illi) (3ndo|3ndou|3andou|3ando) (3liya|3lina) (flous|lflous)\b|\bchkoun ma (khellas|xlass)\w*\b|\bflous li (3ndhom|3andhom) 3liya\b/', 'qui me doit de l argent'],
        ['/\bchhal (3ndi )?(fl|f|fil|fe) (la )?caisse\b|\bflous li 3ndi\b/', 'argent en caisse'],
        ['/\bstock (qalil|9alil|qlil|raqiq)\b|\bqalil (fl )?stock\b/', 'stock faible'],
        ['/\bchhal (rbe7t|rb7t|rba7t|rbe7na)\b/', 'combien j ai gagne ce mois'],
        ['/\bproduits? li ma (kay?bi3ouch|kaybi3och|kaytbi3ouch|tbi3ouch)\b/', 'produits qui ne se vendent pas'],
        ['/\bclients? li ma (xrawch|chrawch|khrawch|chraw\w*)\b/', 'clients inactifs'],
        ['/\bdettes dyali\b|\bchhal khassni nkhellas\b|\bchhal khassni n?khellas\b/', 'ce que je dois payer'],
        ['/\bach khass(ni)? n(e)?chri\b|\bach khassni\b.*chri\b/', 'quoi commander'],
        ['/\b(3tini|3tina|3ti ?lia|bghit n3ref|bghit nchouf)\b/', ''],
        ['/\bresume (de|d|dyal) (aujourd hui|lyoum)\b/', 'resume de la journee'],
        ['/^(comment|kifach) (vont |sont |marchent )?(les )?ventes\b.*|^(les )?ventes$/', 'ventes du mois'],
        ['/^(ma )?tresorerie$/', 'ma tresorerie'],
        // abréviations et fautes de langage courant
        ['/\b(du|ce|de) moi\b/', '\1 mois'],
        ['/^(ttc|ht)( (du|de la|de l) (mois|jour|semaine|annee))?$/', 'chiffre d affaires \2'],
        ['/^marge$/', 'marge du mois'],
        ['/\btop (\d+) clients?\b|\bclients? top\b/', 'meilleurs clients'],
        ['/\bsolde caisse\b/', 'argent en caisse'],
        ['/\bfa fournisseurs?\b/', 'factures fournisseurs'],
    ];

    /** @param string $n phrase normalisée par l'orchestrateur (minuscules, sans accents) */
    public static function canonical(string $n): string
    {
        $t = trim(preg_replace('/[^a-z0-9 #\/\-]+/', ' ', str_replace(["'", '’'], ' ', $n)) ?? $n);
        $t = trim(preg_replace('/\s+/', ' ', $t) ?? $t);

        $t = self::rewrite($t);

        $out = [];
        foreach (explode(' ', $t) as $word) {
            $out[] = self::WORDS[$word] ?? self::fixTypo($word);
        }

        // Un second passage : un mot corrigé ou traduit peut former une composition connue (« tréso » → « tresorerie » → « ma tresorerie »).
        return self::rewrite(implode(' ', $out));
    }

    private static function rewrite(string $t): string
    {
        $t = trim(preg_replace('/\s+/', ' ', $t) ?? $t);
        foreach (self::PHRASES as [$regex, $to]) {
            $t = trim(preg_replace('/\s+/', ' ', preg_replace($regex, $to, $t) ?? $t) ?? $t);
        }

        return $t;
    }

    /** Un mot du métier mal orthographié (une lettre en trop, en moins, changée ou inversée) est ramené à sa forme correcte. */
    private static function fixTypo(string $w): string
    {
        $len = strlen($w);
        if ($len < 5 || preg_match('/\d/', $w) || in_array($w, self::VOCAB, true)) {
            return $w;
        }
        $max = $len >= 8 ? 2 : 1;
        $candidates = [];
        foreach (self::VOCAB as $v) {
            if ($v[0] !== $w[0] || abs(strlen($v) - $len) > $max) {
                continue;
            }
            $d = self::distance($w, $v);
            $d <= $max && $candidates[$v] = $d;
        }
        if ($candidates === []) {
            return $w;
        }
        $min = min($candidates);
        $best = array_keys(array_filter($candidates, fn ($d) => $d === $min));
        if (count($best) > 1) {
            $best = array_values(array_filter($best, fn ($v) => substr($v, -1) === substr($w, -1)));
        }

        return count($best) === 1 ? $best[0] : $w;
    }

    /** Distance d'édition avec inversion de deux lettres voisines (une seule faute de frappe). */
    private static function distance(string $a, string $b): int
    {
        $la = strlen($a);
        $lb = strlen($b);
        $d = [];
        for ($i = 0; $i <= $la; $i++) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $lb; $j++) {
            $d[0][$j] = $j;
        }
        for ($i = 1; $i <= $la; $i++) {
            for ($j = 1; $j <= $lb; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $cost);
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }

        return $d[$la][$lb];
    }
}
