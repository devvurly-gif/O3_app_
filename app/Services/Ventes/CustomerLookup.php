<?php

namespace App\Services\Ventes;

use App\Models\ThirdPartner;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Recherche d'un client O3 à partir de ce qu'on sait d'un message :
 * téléphone de l'expéditeur, code client ou raison sociale exacte.
 *
 * Ne choisit jamais : renvoie tous les candidats, c'est à l'appelant de
 * refuser quand il y en a zéro ou plusieurs.
 */
class CustomerLookup
{
    /** Clients dont le téléphone est le même numéro, quel que soit son format de saisie. */
    public function byPhone(string $phone): Collection
    {
        $tail = PhoneNumber::tail($phone);
        if ($tail === null) {
            return collect();
        }

        return $this->customers()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(tp_phone, ' ', ''), '-', ''), '.', ''), '(', ''), ')', '') LIKE ?",
                ['%' . $tail]
            )
            ->get()
            ->filter(fn (ThirdPartner $c) => PhoneNumber::equals($c->tp_phone, $phone))
            ->values();
    }

    public function byCode(string $code): Collection
    {
        return $this->customers()->where('tp_code', trim($code))->get();
    }

    public function byExactName(string $name): Collection
    {
        return $this->customers()->where('tp_title', trim($name))->get();
    }

    /**
     * « Client : … » tapé par un employé : téléphone s'il en a l'air, sinon
     * code client, sinon raison sociale exacte.
     */
    public function byHint(string $hint): Collection
    {
        $hint = trim($hint);
        if ($hint === '') {
            return collect();
        }
        if (PhoneNumber::looksLikePhone($hint)) {
            return $this->byPhone($hint);
        }
        $found = $this->byCode($hint);
        return $found->isNotEmpty() ? $found : $this->byExactName($hint);
    }

    private function customers(): Builder
    {
        return ThirdPartner::query()->whereIn('tp_Role', ['customer', 'both']);
    }
}
