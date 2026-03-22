<?php

namespace App\Repositories\Eloquent;

use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Repositories\Contracts\DocumentLigneRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class DocumentLigneRepository extends BaseRepository implements DocumentLigneRepositoryInterface
{
    public function __construct(DocumentLigne $model)
    {
        parent::__construct($model);
    }

    public function allForDocument(DocumentHeader $document, array $with = [], string $orderBy = 'sort_order'): Collection
    {
        return $document->lignes()->with($with)->orderBy($orderBy)->get();
    }

    public function createForDocument(DocumentHeader $document, array $data): Model
    {
        $data['document_header_id'] = $document->id;
        return $this->model->create($data);
    }
}
