<?php

namespace App\Services\Agents;

use App\Models\Setting;
use Carbon\Carbon;

/**
 * Horaire d'une routine : tous les jours, toutes les semaines (jour de la semaine) ou tous les mois (jour 1 à 28),
 * à une heure fixe, dans le fuseau du tenant (réglage locale.timezone). Jamais plus d'une exécution par jour.
 *
 * Forme : ['frequency' => 'daily'|'weekly'|'monthly', 'weekday' => 1..7 (lundi = 1), 'day' => 1..28, 'time' => 'HH:MM'].
 */
class RoutineSchedule
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    private const WEEKDAYS = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

    /** Nettoie un horaire proposé par l'IA ; null s'il est inutilisable. @return array<string, mixed>|null */
    public static function sanitize(mixed $in): ?array
    {
        if (!is_array($in) || !in_array($in['frequency'] ?? null, self::FREQUENCIES, true)) {
            return null;
        }
        if (!is_string($in['time'] ?? null) || !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', trim($in['time']), $m)) {
            return null;
        }
        $out = ['frequency' => $in['frequency'], 'time' => sprintf('%02d:%s', (int) $m[1], $m[2])];

        if ($in['frequency'] === 'weekly') {
            $weekday = (int) ($in['weekday'] ?? 0);
            if ($weekday < 1 || $weekday > 7) {
                return null;
            }
            $out['weekday'] = $weekday;
        }
        if ($in['frequency'] === 'monthly') {
            $day = (int) ($in['day'] ?? 0);
            if ($day < 1 || $day > 28) {
                return null;
            }
            $out['day'] = $day;
        }

        return $out;
    }

    /** La prochaine exécution strictement après $after, rendue en UTC (comme les dates de la base). */
    public static function next(array $schedule, ?Carbon $after = null): Carbon
    {
        $tz = self::timezone();
        $now = ($after ?? Carbon::now())->copy()->setTimezone($tz);
        [$h, $i] = array_map('intval', explode(':', $schedule['time']));

        $candidate = $now->copy()->setTime($h, $i, 0);
        switch ($schedule['frequency']) {
            case 'daily':
                if ($candidate->lte($now)) {
                    $candidate->addDay();
                }
                break;
            case 'weekly':
                $candidate->addDays(($schedule['weekday'] - $now->isoWeekday() + 7) % 7);
                if ($candidate->lte($now)) {
                    $candidate->addWeek();
                }
                break;
            default: // monthly
                $candidate->day(min((int) $schedule['day'], 28));
                if ($candidate->lte($now)) {
                    $candidate->addMonthNoOverflow()->day(min((int) $schedule['day'], 28));
                }
        }

        return $candidate->setTimezone('UTC');
    }

    /** « chaque lundi à 08:00 », « tous les jours à 09:15 », « le 5 de chaque mois à 07:30 ». */
    public static function describe(array $schedule): string
    {
        return match ($schedule['frequency']) {
            'daily'  => "tous les jours à {$schedule['time']}",
            'weekly' => 'chaque ' . self::WEEKDAYS[$schedule['weekday']] . " à {$schedule['time']}",
            default  => "le {$schedule['day']} de chaque mois à {$schedule['time']}",
        };
    }

    /** Une date de la base (UTC) lisible dans le fuseau du tenant : « lundi 06/10 à 08:00 ». */
    public static function display(Carbon $at): string
    {
        $local = $at->copy()->setTimezone(self::timezone())->locale('fr');

        return $local->isoFormat('dddd DD/MM [à] HH:mm');
    }

    private static function timezone(): string
    {
        $tz = Setting::get('locale', 'timezone') ?: config('app.timezone');
        try {
            new \DateTimeZone((string) $tz);

            return (string) $tz;
        } catch (\Throwable) {
            return 'UTC';
        }
    }
}
