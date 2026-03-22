<?php

namespace App\Repositories\Eloquent;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Repositories\Contracts\DocumentFooterRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class DocumentFooterRepository extends BaseRepository implements DocumentFooterRepositoryInterface
{
    public function __construct(DocumentFooter $model)
    {
        parent::__construct($model);
    }

    public function findForDocument(DocumentHeader $document): ?Model
    {
        return $document->footer;
    }

    public function upsertForDocument(DocumentHeader $document, array $data): Model
    {
        return $document->footer()->updateOrCreate(
            ['document_header_id' => $document->id],
            $data
        );
    }
}
