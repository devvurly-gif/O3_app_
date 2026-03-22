<?php

namespace App\Services;

use App\Models\DocumentHeader;
use App\Repositories\Contracts\DocumentHeaderRepositoryInterface;
use App\Repositories\Contracts\DocumentIncrementorRepositoryInterface;
use App\Repositories\Contracts\DocumentLigneRepositoryInterface;
use App\Repositories\Contracts\DocumentFooterRepositoryInterface;
use Illuminate\Support\Facades\DB;

class DocumentHeaderService
{
    public function __construct(
        private DocumentHeaderRepositoryInterface $documents,
        private DocumentIncrementorRepositoryInterface $incrementors,
        private DocumentLigneRepositoryInterface $lignes,
        private DocumentFooterRepositoryInterface $footers,
        private DocumentIncrementorService $incrementorService,
    ) {
    }

    /**
     * Create a full document (header + lines + footer) in a single transaction.
     *
     * If no reference is provided, one is derived from the incrementor template
     * and the counter is bumped.
     */
    public function createWithLinesAndFooter(array $headerData, array $lines, ?array $footerData): DocumentHeader
    {
        return DB::transaction(function () use ($headerData, $lines, $footerData) {
            if (empty($headerData['reference'])) {
                $incrementor = $this->incrementors->find($headerData['document_incrementor_id']);
                $headerData['reference'] = $this->incrementorService->formatReference(
                    $incrementor->template,
                    $incrementor->nextTrick
                );
                $incrementor->increment('nextTrick');
            }

            $headerData['status'] = 'draft';

            /** @var DocumentHeader $document */
            $document = $this->documents->create($headerData);

            foreach ($lines as $i => $lineData) {
                $this->lignes->createForDocument($document, array_merge($lineData, ['sort_order' => $i + 1]));
            }

            if (!empty($footerData)) {
                $this->footers->upsertForDocument($document, $footerData);
            }

            return $document;
        });
    }
}
