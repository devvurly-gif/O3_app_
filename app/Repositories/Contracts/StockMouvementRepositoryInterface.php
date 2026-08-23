<?php

namespace App\Repositories\Contracts;

use App\Models\StockMouvement;
use Illuminate\Database\Eloquent\Collection;

/**
 * Les types de retour portent leur générique.
 *
 * Sans lui, `Collection` seul se lit `Collection<int, Model>` : l'appelant
 * reçoit des `Model` anonymes et toute lecture d'attribut — `$m->product_id`,
 * `$m->direction` — devient invérifiable. C'est ce qui produisait à soi seul
 * un cinquième des erreurs relevées par l'analyse statique, dans un service
 * qui manipule du stock au dirham près.
 */
interface StockMouvementRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Return all movements linked to a given document (excludes cancellation entries).
     *
     * @return Collection<int, StockMouvement>
     */
    public function forDocument(int $documentHeaderId): Collection;

    /**
     * Return movements for a document filtered by status (pending, applied, cancelled).
     *
     * @return Collection<int, StockMouvement>
     */
    public function forDocumentByStatus(int $documentHeaderId, string $status): Collection;

    /** Bulk update status for all movements of a document matching a given current status. */
    public function updateStatusForDocument(int $documentHeaderId, string $fromStatus, string $toStatus): int;
}
