<?php

namespace App\Services\Agents;

/**
 * Le nombre de lignes qu'une lecture affiche. Dix par défaut ; « voir plus » le relève le temps d'une réponse (jamais
 * au-delà de 50). Toutes les lectures le lisent ici : un seul réglage, donc un seul comportement.
 */
final class ListLimit
{
    public const DEFAULT = 10;
    public const MAX = 50;

    private static int $limit = self::DEFAULT;

    public static function get(): int
    {
        return self::$limit;
    }

    /**
     * Exécute $fn avec une limite relevée, puis rétablit la limite habituelle (même en cas d'exception).
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function with(int $limit, callable $fn): mixed
    {
        $previous = self::$limit;
        self::$limit = max(self::DEFAULT, min(self::MAX, $limit));
        try {
            return $fn();
        } finally {
            self::$limit = $previous;
        }
    }
}
