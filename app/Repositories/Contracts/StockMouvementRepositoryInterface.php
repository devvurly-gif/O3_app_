<?php

namespace App\Repositories\Contracts;

interface StockMouvementRepositoryInterface extends BaseRepositoryInterface
{
    /** Return all movements linked to a given document (excludes cancellation entries). */
    public function forDocument(int $documentHeaderId): \Illuminate\Database\Eloquent\Collection;
}
