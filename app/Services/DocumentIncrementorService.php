<?php

namespace App\Services;

use App\Models\DocumentIncrementor;
use App\Repositories\Contracts\DocumentIncrementorRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DocumentIncrementorService
{
    public function __construct(private DocumentIncrementorRepositoryInterface $incrementors)
    {
    }

    /**
     * Reserve the next reference for a document. Holds the reservation in cache
     * for 30 minutes so abandoned forms don't skip numbers.
     */
    public function reserveNext(DocumentIncrementor $incrementor): array
    {
        $token    = (string) Str::uuid();
        $cacheKey = "di_reserve_{$incrementor->id}";

        $reserved = $this->cacheGet($cacheKey);
        $nextNum  = $reserved['num'] ?? $incrementor->nextTrick;

        $reference = $this->formatReference($incrementor->template, $nextNum);

        $this->cachePut($cacheKey, ['num' => $nextNum, 'token' => $token], now()->addMinutes(30));

        return [
            'token'     => $token,
            'reference' => $reference,
            'num'       => $nextNum,
        ];
    }

    /**
     * Confirm a reserved reference: validates the token, increments the DB
     * counter, and clears the cache reservation.
     *
     * @return array|false False if the token is invalid or expired.
     */
    public function confirmNext(DocumentIncrementor $incrementor, string $token): array|false
    {
        $cacheKey = "di_reserve_{$incrementor->id}";
        $reserved = $this->cacheGet($cacheKey);

        if (!$reserved || $reserved['token'] !== $token) {
            return false;
        }

        $incrementor->nextTrick = $reserved['num'] + 1;
        $incrementor->save();

        $this->cacheForget($cacheKey);

        return [
            'reference' => $this->formatReference($incrementor->template, $reserved['num']),
            'nextTrick' => $incrementor->nextTrick,
        ];
    }

    /**
     * Build a human-readable reference from a template.
     * Placeholders: {YYYY}, {YY}, {MM}, {DD}, {NNNN} (N-count = zero-padded counter).
     */
    public function formatReference(?string $template, int $num): string
    {
        if (!$template) {
            return (string) $num;
        }

        $now = now();
        $ref = $template;
        $ref = str_replace('{YYYY}', $now->format('Y'), $ref);
        $ref = str_replace('{YY}', $now->format('y'), $ref);
        $ref = str_replace('{MM}', $now->format('m'), $ref);
        $ref = str_replace('{DD}', $now->format('d'), $ref);

        $ref = preg_replace_callback('/\{(N+)\}/', function ($m) use ($num) {
            return str_pad($num, strlen($m[1]), '0', STR_PAD_LEFT);
        }, $ref);

        return $ref;
    }

    private function cacheGet(string $key): mixed
    {
        try {
            return Cache::get($key);
        } catch (\BadMethodCallException) {
            return null;
        }
    }

    private function cachePut(string $key, mixed $value, mixed $ttl): void
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (\BadMethodCallException) {
            // file driver + Stancl tagging — reservation skipped
        }
    }

    private function cacheForget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\BadMethodCallException) {
            // ignore
        }
    }
}
