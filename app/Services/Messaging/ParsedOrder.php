<?php

namespace App\Services\Messaging;

/**
 * Résultat de la lecture d'un message, par règles ou par IA — même forme
 * dans les deux cas, pour que la suite du traitement ne dépende pas de la
 * méthode.
 */
final class ParsedOrder
{
    /**
     * @param array<int, array{query: string, quantity: float, unit: ?string}> $lines
     * @param string[] $unparsed fragments du message qui ne sont ni une salutation ni un article compris
     */
    public function __construct(
        public readonly ?string $customerHint,
        public readonly array $lines,
        public readonly array $unparsed,
        public readonly string $method, // rules | ai
    ) {
    }

    /** Tout le message a été compris et il contient au moins un article. */
    public function isComplete(): bool
    {
        return $this->lines !== [] && $this->unparsed === [];
    }
}
