<?php

namespace App\Services\Agents;

/**
 * Les questions de suite (« et hier ? », « et le mois dernier ? », « et par vendeur ? ») : elles n'ont de sens qu'avec
 * la question précédente. On reprend la dernière lecture de l'administrateur et on n'en change que ce qui est demandé,
 * la période ou le découpage. Rien d'autre n'est deviné : une phrase qui n'est pas clairement une suite rend null.
 */
final class FollowUp
{
    private const PERIOD = '(?:d )?(?:aujourd hui|hier|avant hier)|(?:du |de la |de l |ce |cette |le |la |l )?(?:mois|semaine|annee|trimestre)(?: (?:derniere?|passee?|precedente?|en cours))?|(?:des )?\d{1,3} derniers? jours';
    private const FILLER = '/^(et|puis|alors|pareil|idem|meme chose|maintenant|mais|plutot|sinon|pour|sur|en|qu en est il|et si on prenait|donne moi|montre moi|et pour|et en)\b\s*/';
    /** Les lectures qui dépendent d'une période : elles seules se prolongent par « et hier ? ». */
    private const PERIODIC = '/ventes?|chiffre|encaissements?|depenses?|achats?|marge|panier|remises?|retours?|avoirs?|flux|tickets?|top |meilleurs|evolution|tva|mouvements|pertes|nouveaux|brouillons|factures? (annulees|non envoyees)|combien j ai vendu|resume/';

    /**
     * @param string $n phrase normalisée de l'administrateur
     * @param string $last la dernière lecture comprise (phrase normalisée)
     * @return string|null la nouvelle lecture complète, ou null si la phrase n'est pas une suite
     */
    public static function resolve(string $n, string $last): ?string
    {
        $t = trim(preg_replace('/[^a-z0-9 ]+/', ' ', $n) ?? $n);
        $t = trim(preg_replace('/\s+/', ' ', $t) ?? $t);
        if ($t === '' || str_word_count($t) > 7 || !preg_match(self::PERIODIC, $last)) {
            return null;
        }

        // Une suite commence par un mot de liaison, ou se réduit à la période ou au découpage.
        $linked = (bool) preg_match(self::FILLER, $t);
        $rest = $t;
        while (preg_match(self::FILLER, $rest)) {
            $rest = trim(preg_replace(self::FILLER, '', $rest, 1) ?? $rest);
        }
        $rest = trim(preg_replace('/^(le|la|les|l)\s+(?=hier|aujourd|\d)/', '', $rest) ?? $rest);
        // « le mois dernier » se range comme « du mois dernier » dans la phrase reprise.
        $rest = preg_replace(['/^le (mois|trimestre)\b/', '/^la semaine\b/', '/^l annee\b/'], ['du $1', 'de la semaine', 'de l annee'], $rest) ?? $rest;

        // Une nouvelle période : « et hier ? », « le mois dernier », « cette semaine ».
        if ($rest !== '' && preg_match('/^(?:' . self::PERIOD . ')$/', $rest)) {
            return trim(preg_replace('/\s+/', ' ', self::withoutPeriod($last) . ' ' . $rest) ?? $last);
        }

        // Un autre découpage : « et par vendeur ? », « par caisse », « par client ».
        if (($linked || $rest === $t) && preg_match('/^par (vendeur|utilisateur|caissier|commercial|caisse|session|categorie|marque|client|fournisseur)$/', $rest, $m)) {
            $period = preg_match('/' . self::PERIOD . '/', $last, $p) ? $p[0] : '';
            $group = $m[1];
            $kind = preg_match('/achats?/', $last) ? 'achats' : 'ventes';

            return trim(match (true) {
                $group === 'client'                                                  => "meilleurs clients {$period}",
                $group === 'fournisseur'                                             => "achats {$period} par fournisseur",
                in_array($group, ['vendeur', 'utilisateur', 'caissier', 'commercial'], true) => "ventes {$period} par vendeur",
                in_array($group, ['caisse', 'session'], true)                        => "ventes {$period} par caisse",
                default                                                              => "{$kind} {$period} par {$group}",
            });
        }

        return null;
    }

    /** La phrase sans son expression de période (« ventes du mois par vendeur » → « ventes par vendeur »). */
    private static function withoutPeriod(string $s): string
    {
        $s = preg_replace('/\b(?:' . self::PERIOD . ')\b/', ' ', $s) ?? $s;

        return trim(preg_replace('/\b(du|de la|de l|des|d|ce|cette)\b\s*$/', '', trim(preg_replace('/\s+/', ' ', $s) ?? $s)) ?? $s);
    }
}
