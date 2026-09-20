<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cycle de vie commercial d'un tenant.
 *
 *   pending ──(email vérifié)──► trial ──(échéance)──► past_due ──► suspended
 *                                  │                      │
 *                                  └──(paiement)──► active ◄┘
 *
 * Stocké dans la colonne `status` de la table centrale `tenants`. Ne pas
 * confondre avec `is_active`, qui reste le coupe-circuit manuel de
 * l'administrateur (fraude, demande du client) et se cumule avec ce statut.
 */
enum TenantStatus: string
{
    /** Inscrit, email pas encore vérifié. Aucun accès. */
    case Pending = 'pending';

    /** Essai gratuit en cours. Accès complet au périmètre de la formule d'essai. */
    case Trial = 'trial';

    /** Abonnement payé et en cours de validité. */
    case Active = 'active';

    /** Échéance dépassée, dans le délai de grâce. Lecture seule. */
    case PastDue = 'past_due';

    /** Délai de grâce épuisé. Accès refusé sauf authentification et abonnement. */
    case Suspended = 'suspended';

    /**
     * Le tenant peut-il écrire (créer une facture, encaisser, modifier un produit) ?
     */
    public function allowsWrites(): bool
    {
        return in_array($this, [self::Trial, self::Active], true);
    }

    /**
     * Le tenant peut-il au moins consulter ses données ?
     *
     * Un compte impayé garde l'accès en lecture : ses factures et son stock
     * lui appartiennent, les lui cacher est à la fois injustifiable et le
     * meilleur moyen de ne jamais être payé.
     */
    public function allowsReads(): bool
    {
        return $this !== self::Pending && $this !== self::Suspended;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'En attente de vérification',
            self::Trial     => 'Essai gratuit',
            self::Active    => 'Abonnement actif',
            self::PastDue   => 'Échéance dépassée',
            self::Suspended => 'Suspendu',
        };
    }
}
