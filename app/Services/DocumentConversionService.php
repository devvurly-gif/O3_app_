<?php

namespace App\Services;

use App\Models\DocumentHeader;
use App\Repositories\Contracts\DocumentHeaderRepositoryInterface;
use App\Repositories\Contracts\DocumentIncrementorRepositoryInterface;
use Illuminate\Support\Facades\DB;

class DocumentConversionService
{
    public function __construct(
        private DocumentHeaderRepositoryInterface $documents,
        private DocumentIncrementorRepositoryInterface $incrementors,
        private DocumentIncrementorService $incrementorService,
    ) {
    }

    public function convert(DocumentHeader $source, int $targetIncrementorId): DocumentHeader
    {
        $this->validateConversion($source, $targetIncrementorId);

        return DB::transaction(function () use ($source, $targetIncrementorId) {
            $incrementor = $this->incrementors->findAndLock($targetIncrementorId);

            $reference = $this->incrementorService->formatReference(
                $incrementor->template,
                $incrementor->nextTrick
            );

            $target = $this->documents->create([
                'document_incrementor_id' => $incrementor->id,
                'reference'               => $reference,
                'document_type'           => $incrementor->di_model,
                'document_title'          => $incrementor->di_title,
                'parent_id'               => $source->id,
                'thirdPartner_id'         => $source->thirdPartner_id,
                'company_role'            => $source->company_role,
                'warehouse_id'            => $source->warehouse_id,
                'user_id'                 => auth()->id(),
                'status'                  => 'draft',
                'issued_at'               => now(),
                'due_at'                  => now()->addDays(30),
                'notes'                   => $source->notes,
            ]);

            foreach ($source->lignes as $ligne) {
                $target->lignes()->create($ligne->only([
                    'product_id', 'sort_order', 'line_type',
                    'designation', 'reference', 'quantity', 'unit',
                    'unit_price', 'discount_percent', 'tax_percent',
                    'total_ligne_ht', 'total_tax', 'total_ttc',
                ]));
            }

            if ($source->footer) {
                $target->footer()->create([
                    'total_ht'       => $source->footer->total_ht,
                    'total_discount' => $source->footer->total_discount,
                    'total_tax'      => $source->footer->total_tax,
                    'total_ttc'      => $source->footer->total_ttc,
                    'amount_paid'    => 0,
                    'amount_due'     => $source->footer->total_ttc,
                ]);
            }

            $this->documents->update($source, ['status' => 'converted']);

            $this->incrementors->incrementNextTrick($incrementor);

            return $target->load('lignes', 'footer', 'incrementor', 'thirdPartner');
        });
    }

    private function validateConversion(DocumentHeader $source, int $targetIncrementorId): void
    {
        if ($source->isConverted()) {
            throw new \Exception("Document {$source->reference} is already converted.");
        }

        $targetIncrementor = $this->incrementors->find($targetIncrementorId);

        if (!$source->canConvertTo($targetIncrementor->di_model)) {
            throw new \Exception(
                "Cannot convert {$source->document_type} to {$targetIncrementor->di_model}."
            );
        }
    }
}
