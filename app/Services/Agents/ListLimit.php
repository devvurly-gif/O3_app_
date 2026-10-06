<?php

namespace App\Services\Agents;

/**
 * Le nombre de lignes qu'une lecture affiche, et à partir d'où. Dix lignes depuis le début par défaut ; « voir plus »
 * en montre jusqu'à 30, « les 10 suivants » avance d'une page. Le réglage ne dure que le temps d'une réponse.
 * Toutes les lectures le lisent ici : un seul réglage, donc un seul comportement.
 */
final class ListLimit
{
    public const DEFAULT = 10;
    public const MAX = 50;

    private static int $limit = self::DEFAULT;
    private static int $offset = 0;
    private static ?int $total = null;

    public static function get(): int
    {
        return self::$limit;
    }

    /** Le nombre de lignes à sauter (les pages précédentes). */
    public static function offset(): int
    {
        return self::$offset;
    }

    /** Le total de la dernière liste affichée avec more(), ou null si la lecture n'est pas une liste. */
    public static function total(): ?int
    {
        return self::$total;
    }

    /**
     * La mention des lignes restantes après la page affichée (« … et 5 autre(s). »), et mémorise le total de la liste.
     *
     * @param int $count le nombre total de lignes de la liste
     */
    public static function more(int $count): string
    {
        self::$total = $count;
        $left = $count - self::$offset - self::$limit;

        return $left > 0 ? "\n… et {$left} autre(s)." : '';
    }

    /**
     * Exécute $fn avec une autre page, puis rétablit la page habituelle (même en cas d'exception).
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function with(int $limit, callable $fn, int $offset = 0): mixed
    {
        [$pl, $po, $pt] = [self::$limit, self::$offset, self::$total];
        self::$limit = max(5, min(self::MAX, $limit));
        self::$offset = max(0, $offset);
        self::$total = null;
        try {
            return $fn();
        } finally {
            [self::$limit, self::$offset, self::$total] = [$pl, $po, $pt];
        }
    }
}