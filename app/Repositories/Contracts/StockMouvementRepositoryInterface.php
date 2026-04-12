<?php

namespace App\Repositories\Contracts;

interface StockMouvementRepositoryInterface extends BaseRepositoryInterface
{
    /** Return all movements linked to a given document (excludes cancellation entries). */
    public function forDocument(int $documentHeaderId): \Illuminate\Database\Eloquent\Collection;

    /** Return movements for a document filtered by status (pending, applied, cancelled). */
    public function forDocumentByStatus(int $documentHeaderId, string $status): \Illuminate\Database\Eloquent\Collection;

    /** Bulk update status for all movements of a document matching a given current status. */
    public function updateStatusForDocument(int $documentHeaderId, string $fromStatus, string $toStatus): int;
}
