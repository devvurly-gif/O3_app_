<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Attribue les numeros de facture d'abonnement.
 *
 * Sequence continue par annee, sans trou ni doublon : c'est une exigence
 * legale, pas une commodite. D'ou le verrou sur la ligne du compteur plutot
 * qu'un `MAX(number) + 1` — deux emissions simultanees (le cron du matin et un
 * clic au back-office) liraient sinon le meme maximum et produiraient deux fois
 * le meme numero.
 *
 * Le numero est consomme meme si l'emission echoue ensuite : c'est voulu. Un
 * trou dans la sequence se justifie et s'explique ; deux factures portant le
 * meme numero, non.
 */
class InvoiceNumberService
{
    /**
     * Reserve le prochain numero de l'annee donnee.
     *
     * Accepte n'importe quelle saveur de date : le code appelant manipule
     * tantot CarbonImmutable, tantot Carbon\Carbon, tantot Illuminate\Support\Carbon,
     * et un typage sur l'une d'elles casse sur les deux autres.
     *
     * @param DateTimeInterface|null $date Date d'emission ; aujourd'hui par defaut.
     */
    public function next(?DateTimeInterface $date = null): string
    {
        $date   = $date ? Carbon::instance($date) : Carbon::today();
        $prefix = (string) config('billing.number_prefix', 'FA');
        $year   = $date->format('Y');
        $scope  = "{$prefix}-{$year}";

        $sequence = DB::transaction(function () use ($scope) {
            // Cree la ligne si l'annee est nouvelle, puis la verrouille. Le
            // firstOrCreate doit preceder le lock : on ne verrouille pas une
            // ligne qui n'existe pas encore.
            DB::table('invoice_sequences')->insertOrIgnore([
                'scope'       => $scope,
                'next_number' => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $row = DB::table('invoice_sequences')
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            DB::table('invoice_sequences')
                ->where('scope', $scope)
                ->update([
                    'next_number' => $row->next_number + 1,
                    'updated_at'  => now(),
                ]);

            return (int) $row->next_number;
        });

        return str_replace(
            ['{PREFIX}', '{YEAR}', '{SEQ}'],
            [
                $prefix,
                $year,
                str_pad((string) $sequence, (int) config('billing.number_padding', 4), '0', STR_PAD_LEFT),
            ],
            (string) config('billing.number_format', '{PREFIX}-{YEAR}-{SEQ}')
        );
    }
}
