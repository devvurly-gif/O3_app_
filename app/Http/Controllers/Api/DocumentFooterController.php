<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentHeader;
use App\Repositories\Contracts\DocumentFooterRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentFooterController extends Controller
{
    public function __construct(private DocumentFooterRepositoryInterface $footers)
    {
    }

    public function show(DocumentHeader $documentHeader): JsonResponse
    {
        return response()->json($this->footers->findForDocument($documentHeader));
    }

    public function upsert(Request $request, DocumentHeader $documentHeader): JsonResponse
    {
        $data = $request->validate([
            'total_ht'        => ['nullable', 'numeric', 'min:0'],
            'total_discount'  => ['nullable', 'numeric', 'min:0'],
            'total_tax'       => ['nullable', 'numeric', 'min:0'],
            'total_ttc'       => ['nullable', 'numeric', 'min:0'],
            'amount_paid'     => ['nullable', 'numeric', 'min:0'],
            'amount_due'      => ['nullable', 'numeric'],
            'payment_method'  => ['nullable', 'string', 'max:50'],
            'payment_date'    => ['nullable', 'date'],
            'total_in_words'  => ['nullable', 'string'],
            'is_signed'       => ['boolean'],
            'is_printed'      => ['boolean'],
            'is_sent'         => ['boolean'],
            'sent_via'        => ['nullable', 'array'],
            'bank_details'    => ['nullable', 'string'],
            'legal_mentions'  => ['nullable', 'string'],
        ]);

        $footer = $this->footers->upsertForDocument($documentHeader, $data);

        return response()->json($footer);
    }
}
