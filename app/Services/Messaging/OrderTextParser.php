<?php

namespace App\Services\Messaging;

/**
 * Lecture « par règles » d'une commande tapée en texte libre (WhatsApp,
 * SMS, chat). Ne cherche aucun produit : découpe seulement le message en
 * lignes { query, quantity, unit } et met de côté ce qu'elle ne comprend pas.
 *
 * Formats reconnus, un article par ligne (ou séparés par « ; », « et », « + »,
 * ou une virgule suivie d'un chiffre) :
 *   2 perceuses 18V · 2x perceuse · 2 boîtes de vis 4x40 · une perceuse
 *   perceuse x2 · perceuse : 2 · perceuse = 2 · perceuse 2 pcs
 * 1re ligne « Client : … » (messages de l'équipe) → customer_hint.
 *
 * Volontairement prudente : « vis 4x40 » (pas de quantité claire) n'est PAS
 * lue comme « 40 × vis 4 » ; elle part dans unparsed.
 */
class OrderTextParser
{
    private const UNITS = 'bo[iî]tes?|btes?|cartons?|ctns?|pi[eè]ces?|pcs?|unit[eé]s?|kgs?|g|ml|m|l|litres?|rouleaux?|sacs?|paquets?|pqts?|lots?|palettes?|douzaines?|bidons?|seaux?';

    private const NUMBER_WORDS = [
        'un' => 1, 'une' => 1, 'deux' => 2, 'trois' => 3, 'quatre' => 4, 'cinq' => 5, 'six' => 6,
        'sept' => 7, 'huit' => 8, 'neuf' => 9, 'dix' => 10, 'douze' => 12, 'quinze' => 15, 'vingt' => 20,
    ];

    /** Fragments sans article (salutations, politesse) : ignorés, sans être signalés. */
    private const CHITCHAT = [
        'salam', 'slm', 'salut', 'bonjour', 'bjr', 'bonsoir', 'hello', 'hi', 'coucou',
        'merci', 'mrc', 'merci beaucoup', 'choukran', 'chokran', 'shukran', 'svp', 'stp',
        's il vous plait', 's il te plait', 'cordialement', 'ok', 'voila', 'bonne journee',
        'salam alaykoum', 'salam alaikum', 'assalamu alaykum', 'la commande', 'ma commande',
        'commande', 'voici ma commande', 'voici la commande', 'je veux', 'je voudrais',
    ];

    public function parse(string $text): ParsedOrder
    {
        $customerHint = null;
        $lines = [];
        $unparsed = [];

        $rows = preg_split('/\R/u', trim($text)) ?: [];

        // « Client : … » en tête de message.
        foreach ($rows as $i => $row) {
            if (trim($row) === '') {
                continue;
            }
            if (preg_match('/^\s*client\s*[:=\-]\s*(.+?)\s*$/iu', $row, $m)) {
                $customerHint = $m[1];
                unset($rows[$i]);
            }
            break;
        }

        foreach ($rows as $row) {
            foreach ($this->fragments($row) as $fragment) {
                $fragment = $this->clean($fragment);
                if ($fragment === '' || $this->isChitchat($fragment)) {
                    continue;
                }
                $line = $this->parseFragment($fragment);
                if ($line) {
                    $lines[] = $line;
                } else {
                    $unparsed[] = $fragment;
                }
            }
        }

        return new ParsedOrder($customerHint, array_slice($lines, 0, 100), $unparsed, 'rules');
    }

    /** @return string[] */
    private function fragments(string $row): array
    {
        $parts = preg_split('/\s*;\s*|\s+(?:et|\+|&)\s+|,\s*(?=\d)|,\s*(?=(?:un|une|deux|trois|quatre|cinq|six|sept|huit|neuf|dix)\b)/iu', $row) ?: [];
        return array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
    }

    private function clean(string $fragment): string
    {
        $fragment = trim($fragment);
        $fragment = preg_replace('/^[\-\*•–·>]+\s*/u', '', $fragment);
        // Politesse collée devant l'article : « bonjour, 2 perceuses », « svp 3 marteaux ».
        $fragment = preg_replace('/^(?:(?:salam|slm|salut|bonjour|bonsoir|bjr|svp|stp|merci|hello)\b[\s,!.:]*)+/iu', '', $fragment);
        $fragment = preg_replace('/^(?:je (?:veux|voudrais|souhaite)|il me faut|j[\'’]ai besoin de|besoin de|envoyez(?:-moi)?|merci de (?:m[\'’]envoyer|livrer))\s+/iu', '', $fragment);
        return trim($fragment, " \t.,!?;:");
    }

    private function isChitchat(string $fragment): bool
    {
        $plain = $this->plain($fragment);
        $plain = trim(preg_replace('/[^a-z ]+/', ' ', $plain));
        $plain = preg_replace('/\s+/', ' ', $plain);
        if ($plain === '' || in_array($plain, self::CHITCHAT, true)) {
            return true;
        }
        // « ok merci », « merci beaucoup svp » : uniquement des mots de politesse.
        $words = array_unique(explode(' ', implode(' ', self::CHITCHAT) . ' beaucoup okay bonne journee soiree'));
        return array_diff(explode(' ', $plain), $words) === [];
    }

    private function parseFragment(string $fragment): ?array
    {
        $units = self::UNITS;

        // Nombre en toutes lettres en tête : « une perceuse », « deux marteaux ».
        if (preg_match('/^(' . implode('|', array_keys(self::NUMBER_WORDS)) . ')\s+(.+)$/iu', $fragment, $m)) {
            $fragment = self::NUMBER_WORDS[mb_strtolower($m[1])] . ' ' . $m[2];
        }

        // a) quantité devant : « 2 perceuses », « 2x perceuse », « 2 boîtes de vis 4x40 ».
        //    Un « x » collé à un chiffre (« 4x40 ») n'est pas un séparateur.
        if (preg_match('/^(\d+(?:[.,]\d+)?)(?:\s*[x×*]\s*(?=\D)|\s+)(?:(' . $units . ')\.?\s+(?:de\s+|d[\'’]\s*)?)?(.*\p{L}.*)$/iu', $fragment, $m)) {
            return $this->line($m[3], $m[1], $m[2] ?: null);
        }

        // b) quantité derrière : « perceuse x2 », « perceuse : 2 », « perceuse 2 pcs ».
        //    Le « x » doit suivre une lettre ou un espace (« vis 4x40 » reste non compris).
        if (preg_match('/^(.*\p{L}.*?)(?:\s+[x×*]\s*|(?<=\p{L})[x×*]\s*|\s*[:=]\s*|\s+)(\d+(?:[.,]\d+)?)\s*(' . $units . ')?\.?$/iu', $fragment, $m)) {
            return $this->line($m[1], $m[2], $m[3] ?? null);
        }

        return null;
    }

    private function line(string $query, string $qty, ?string $unit): ?array
    {
        $query = trim($query, " \t.,:;-=");
        $quantity = (float) str_replace(',', '.', $qty);
        if ($query === '' || mb_strlen($query) > 255 || $quantity <= 0 || $quantity > 100000) {
            return null;
        }
        return [
            'query'    => $query,
            'quantity' => $quantity,
            'unit'     => $unit ? mb_substr(mb_strtolower($unit), 0, 20) : null,
        ];
    }

    private function plain(string $s): string
    {
        $s = mb_strtolower($s);
        return strtr($s, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', '’' => ' ', '\'' => ' ']);
    }
}
