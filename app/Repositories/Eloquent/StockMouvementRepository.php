<?php

namespace App\Repositories\Eloquent;

use App\Models\StockMouvement;
use App\Repositories\Contracts\StockMouvementRepositoryInterface;

class StockMouvementRepository extends BaseRepository implements StockMouvementRepositoryInterface
{
    public function __construct(StockMouvement $model)
    {
        parent::__construct($model);
    }

    public function forDocument(int $documentHeaderId): \Illuminate\Database\Eloquent\Collection
    {
        return StockMouvement::where('document_header_id', $documentHeaderId)
            ->where('reason', '!=', 'cancellation')
            ->get();
    }

    public function forDocumentByStatus(int $documentHeaderId, string $status): \Illuminate\Database\Eloquent\Collection
    {
        return StockMouvement::where('document_header_id', $documentHeaderId)
            ->where('status', $status)
            ->where('reason', '!=', 'cancellation')
            ->get();
    }

    public function updateStatusForDocument(int $documentHeaderId, string $fromStatus, string $toStatus): int
    {
        return StockMouvement::where('document_header_id', $documentHeaderId)
            ->where('status', $fromStatus)
            ->update(['status' => $toStatus]);
    }
}
