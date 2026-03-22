<?php

namespace App\Repositories\Contracts;

use App\Models\DocumentHeader;
use Illuminate\Database\Eloquent\Model;

interface DocumentFooterRepositoryInterface extends BaseRepositoryInterface
{
    public function findForDocument(DocumentHeader $document): ?Model;

    public function upsertForDocument(DocumentHeader $document, array $data): Model;
}
