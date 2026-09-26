<?php

namespace App\Services\Messaging;

use App\Models\Setting;

/**
 * Textes des réponses automatiques de la messagerie commandes.
 *
 * Pas d'emoji ni de caractères hors alphabet SMS courant : un seul emoji fait
 * passer tout le SMS en encodage UCS-2 (70 caractères par segment au lieu de 160).
 */
class OrderReplyFormatter
{
    public function created(array $result, string $customerName): string
    {
        $doc = $result['document'];
        $out = !empty($doc['appended'])
            ? ["{$this->shop()} - commande ajoutée à votre BL du jour.", "BL brouillon {$doc['reference']} ({$customerName}), contenu complet :"]
            : ["{$this->shop()} - commande reçue.", "BL brouillon {$doc['reference']} ({$customerName}) :"];

        // Contenu complet du BL (toutes les commandes du jour), à défaut les lignes de ce message.
        $lines = $doc['lines'] ?? ($result['preview']['lines'] ?? []);
        foreach ($lines as $l) {
            $out[] = '- ' . $this->qty($l['qty']) . ' x ' . $l['designation'] . (!empty($l['sku']) ? " ({$l['sku']})" : '');
        }
        $ttc = $doc['totals']['ttc'] ?? ($result['preview']['totals']['ttc'] ?? 0);
        $out[] = 'Total TTC estimé : ' . $this->money($ttc) . ' MAD.';
        $out[] = 'Elle sera vérifiée puis confirmée par notre équipe.';
        return implode("\n", $out);
    }

    /**
     * @param array $errors erreurs BLOQUANT du moteur d'import
     * @param string[] $unparsed passages du message non compris
     */
    public function rejected(array $errors, array $unparsed, bool $forStaff): string
    {
        $out = ['Commande non enregistrée :'];
        $technical = false;

        foreach ($unparsed as $u) {
            $out[] = "- \"{$u}\" : ligne non comprise (indiquez la quantité puis le produit, ex. \"2 perceuse 18V\").";
        }

        foreach ($errors as $e) {
            switch ($e['code']) {
                case 'PRODUCT_NOT_FOUND':
                    $out[] = "- \"{$e['query']}\" : produit introuvable, précisez le nom ou la référence.";
                    break;
                case 'PRODUCT_AMBIGUOUS':
                    $choices = collect($e['candidates'] ?? [])->take(5)
                        ->map(fn ($c) => "{$c['title']} ({$c['sku']})")->implode(', ');
                    $out[] = "- \"{$e['query']}\" : plusieurs produits possibles : {$choices}. Précisez lequel.";
                    break;
                case 'CUSTOMER_NOT_FOUND':
                case 'CUSTOMER_AMBIGUOUS':
                    $out[] = $forStaff ? "- {$e['message']}" : '- Votre compte client n\'a pas pu être identifié. Contactez-nous.';
                    break;
                default:
                    if ($forStaff) {
                        $out[] = "- {$e['message']}";
                    } else {
                        $technical = true;
                    }
            }
        }

        if ($technical) {
            $out[] = '- Un problème technique empêche l\'enregistrement ; notre équipe est prévenue.';
        }

        $out[] = 'Merci de renvoyer la commande complète corrigée.';
        return implode("\n", $out);
    }

    public function help(): string
    {
        return "{$this->shop()} - je n'ai pas trouvé d'article dans votre message.\n"
            . "Écrivez un article par ligne, quantité puis produit, par exemple :\n"
            . "2 perceuse 18V\n5 boîte vis 4x40";
    }

    public function staffNeedsCustomer(): string
    {
        return "Indiquez le client en première ligne, par exemple :\nClient : C0012 (code, téléphone ou nom exact)\n2 perceuse 18V";
    }

    public function staffCustomerProblem(string $hint, int $count, array $candidates = []): string
    {
        if ($count === 0) {
            return "Client \"{$hint}\" introuvable dans O3 (code, téléphone ou nom exact). Commande non enregistrée.";
        }
        $list = collect($candidates)->take(5)->implode(', ');
        return "Plusieurs clients correspondent à \"{$hint}\" : {$list}. Précisez le code client. Commande non enregistrée.";
    }

    public function unknownSender(): string
    {
        return "{$this->shop()} - ce numéro n'est pas associé à un compte client. Contactez-nous pour passer commande.";
    }

    public function ambiguousSender(): string
    {
        return "{$this->shop()} - ce numéro est associé à plusieurs comptes clients. Contactez-nous pour passer commande.";
    }

    public function mediaNotSupported(): string
    {
        return "{$this->shop()} - merci d'envoyer votre commande en texte (les photos et messages vocaux ne sont pas lus automatiquement).";
    }

    private function shop(): string
    {
        return Setting::get('company', 'name') ?: (Setting::get('general', 'company_name') ?: 'O3');
    }

    private function qty(float|int|string $q): string
    {
        $q = (float) $q;
        return fmod($q, 1.0) === 0.0 ? (string) (int) $q : rtrim(rtrim(number_format($q, 3, ',', ''), '0'), ',');
    }

    private function money(float|int|string $v): string
    {
        return number_format((float) $v, 2, ',', ' ');
    }
}
