<?php

namespace App\Services;

use App\Models\DocumentIncrementor;
use App\Repositories\Contracts\DocumentIncrementorRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
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
     * Consume the next number of a counter and return the formatted reference.
     *
     * Every caller used to read `nextTrick`, format it, then increment — with
     * nothing in between holding the row. Two tills numbering at the same
     * instant therefore produced the same reference, and since
     * `document_headers.reference` is UNIQUE the loser got a QueryException:
     * a 500 at the till and a lost sale, precisely at peak hours.
     *
     * Locking the row first serialises the two, so the second caller reads the
     * already-incremented value.
     *
     * MUST be called inside a transaction — outside one MySQL commits and
     * releases the lock immediately and the guarantee is gone.
     *
     * Accepts a plain Model because the repositories are still typed against
     * the base class; only the key is read here, and findAndLock() returns the
     * concrete DocumentIncrementor.
     */
    public function consumeNext(DocumentIncrementor|Model $incrementor): string
    {
        $locked = $this->incrementors->findAndLock((int) $incrementor->getKey());

        $reference = $this->formatReference($locked->template, $locked->nextTrick);

        $locked->increment('nextTrick');

        return $reference;
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
