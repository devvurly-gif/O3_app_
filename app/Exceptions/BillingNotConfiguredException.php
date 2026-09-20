<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * L'identite de facturation est incomplete : aucune facture ne peut etre emise.
 *
 * Levee plutot qu'ignoree a dessein. Une facture marocaine sans ICE n'est pas
 * une facture : l'emettre quand meme produirait une liasse de documents a
 * refaire, et un client qui les a deja recus. Mieux vaut un cron qui s'arrete
 * en disant ce qui manque.
 */
class BillingNotConfiguredException extends RuntimeException
{
    /**
     * @param array<int, string> $missing Champs absents de config('billing.issuer')
     */
    public function __construct(public readonly array $missing)
    {
        parent::__construct(
            "Identité de facturation incomplète : " . implode(', ', $missing)
            . ". Renseignez ces valeurs dans le .env du serveur (voir config/billing.php)."
        );
    }
}
