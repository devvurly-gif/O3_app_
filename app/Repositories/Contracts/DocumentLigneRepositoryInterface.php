<?php

namespace App\Repositories\Contracts;

use App\Models\DocumentHeader;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface DocumentLigneRepositoryInterface extends BaseRepositoryInterface
{
    public function allForDocument(DocumentHeader $document, array $with = [], string $orderBy = 'sort_order'): Collection;

    public function createForDocument(DocumentHeader $document, array $data): Model;
}
