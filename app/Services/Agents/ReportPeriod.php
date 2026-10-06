<?php

namespace App\Services\Agents;

use Carbon\Carbon;

/**
 * La période que désigne une phrase (« ventes d'hier », « du mois », « de la semaine dernière », « des 15 derniers
 * jours »), partagée par toutes les lectures : une seule règle, donc un seul comportement. Un adjectif comme
 * « dernier » ou « passé » ne doit jamais être ignoré : « ventes du mois dernier » ne se répond pas avec le mois en cours.
 */
final class ReportPeriod
{
    /**
     * @param string $n phrase normalisée (minuscules, sans accents)
     * @param string $default « day », « month » ou « quarter » : la période quand la phrase n'en nomme aucune
     * @return array{0: Carbon, 1: Carbon, 2: string} début, fin (incluse, minuit) et libellé (« du mois (depuis le 01/10) »)
     */
    public static function resolve(string $n, string $default, Carbon $today): array
    {
        $today = $today->copy()->startOfDay();
        $month = fn (Carbon $c) => 'du mois (depuis le ' . $c->copy()->startOfMonth()->format('d/m') . ')';
        $quarter = fn (Carbon $c) => 'du trimestre (depuis le ' . $c->copy()->startOfQuarter()->format('d/m') . ')';

        if (preg_match('/(\d{1,3})\s*derniers?\s*jours|\b(\d{1,3})\s*jours\b/', $n, $m)) {
            $d = max(1, min(366, (int) ($m[1] !== '' ? $m[1] : $m[2])));

            return [$today->copy()->subDays($d - 1), $today, "des {$d} derniers jours"];
        }

        return match (true) {
            (bool) preg_match('/avant[- ]hier/', $n)                                    => [$today->copy()->subDays(2), $today->copy()->subDays(2), "d'avant-hier"],
            (bool) preg_match('/\bhier\b/', $n)                                         => [$today->copy()->subDay(), $today->copy()->subDay(), "d'hier"],
            (bool) preg_match('/semaine\s+(derniere|passee|precedente)/', $n)           => (function () use ($today) {
                $from = $today->copy()->startOfWeek()->subWeek();
                $to = $from->copy()->addDays(6);

                return [$from, $to, 'de la semaine dernière (du ' . $from->format('d/m') . ' au ' . $to->format('d/m') . ')'];
            })(),
            (bool) preg_match('/trimestre\s+(dernier|passe|precedent)/', $n)            => (function () use ($today) {
                $from = $today->copy()->startOfQuarter()->subQuarterNoOverflow()->startOfQuarter();

                return [$from, $from->copy()->endOfQuarter()->startOfDay(), 'du trimestre dernier (depuis le ' . $from->format('d/m/Y') . ')'];
            })(),
            (bool) preg_match('/mois\s+(dernier|passe|precedent)/', $n)                 => (function () use ($today) {
                $from = $today->copy()->subMonthNoOverflow()->startOfMonth();

                return [$from, $from->copy()->endOfMonth()->startOfDay(), 'du mois dernier (' . $from->copy()->locale('fr')->isoFormat('MMMM YYYY') . ')'];
            })(),
            (bool) preg_match('/(annee\s+(derniere|passee|precedente)|\ban\s+dernier|l.an passe)/', $n) => (function () use ($today) {
                $from = $today->copy()->subYear()->startOfYear();

                return [$from, $from->copy()->endOfYear()->startOfDay(), "de l'année dernière ({$from->format('Y')})"];
            })(),
            (bool) preg_match('/semaine/', $n)                                          => [$today->copy()->startOfWeek(), $today, 'de la semaine (depuis lundi)'],
            (bool) preg_match('/trimestre/', $n)                                        => [$today->copy()->startOfQuarter(), $today, $quarter($today)],
            (bool) preg_match('/\bannee\b|\ban\b/', $n)                                 => [$today->copy()->startOfYear(), $today, "de l'année"],
            (bool) preg_match('/\bmois\b/', $n)                                         => [$today->copy()->startOfMonth(), $today, $month($today)],
            (bool) preg_match('/aujourd|du jour|journee|\bjour\b/', $n)                 => [$today, $today, "d'aujourd'hui"],
            $default === 'quarter'                                                      => [$today->copy()->startOfQuarter(), $today, $quarter($today)],
            $default === 'month'                                                        => [$today->copy()->startOfMonth(), $today, $month($today)],
            default                                                                     => [$today, $today, "d'aujourd'hui"],
        };
    }
}
