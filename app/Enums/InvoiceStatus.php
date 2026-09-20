<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cycle de vie d'une facture d'abonnement.
 *
 *   draft ──(envoi)──► sent ──(reglement)──► paid
 *     │                  │
 *     └───(annulation)───┴──► cancelled
 *
 * Une facture n'est jamais supprimee : la sequence des numeros doit rester
 * continue, une facture erronee s'annule.
 */
enum InvoiceStatus: string
{
    case Draft     = 'draft';
    case Sent      = 'sent';
    case Paid      = 'paid';
    case Cancelled = 'cancelled';

    /**
     * La facture couvre-t-elle encore sa periode ?
     *
     * Une facture annulee ne couvre plus rien : c'est ce qui autorise le
     * service a en emettre une nouvelle sur la meme periode.
     */
    public function isLive(): bool
    {
        return $this !== self::Cancelled;
    }

    public function isSettled(): bool
    {
        return $this === self::Paid || $this === self::Cancelled;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Brouillon',
            self::Sent      => 'Envoyée',
            self::Paid      => 'Réglée',
            self::Cancelled => 'Annulée',
        };
    }
}
