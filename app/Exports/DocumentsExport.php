<?php

namespace App\Exports;

use App\Models\DocumentHeader;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class DocumentsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $q = DocumentHeader::query()->with(['thirdPartner', 'user', 'footer']);

        if (!empty($this->filters['document_type'])) {
            $q->where('document_type', $this->filters['document_type']);
        }

        if (!empty($this->filters['status'])) {
            $q->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['search'])) {
            $q->where(function ($q2) {
                $q2->where('reference', 'like', '%' . $this->filters['search'] . '%')
                   ->orWhere('document_title', 'like', '%' . $this->filters['search'] . '%');
            });
        }

        return $q->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'ID', 'Référence', 'Type', 'Titre',
            'Client / Fournisseur', 'Statut',
            'Total HT', 'TVA', 'Total TTC',
            'Payé', 'Reste dû',
            'Date émission', 'Échéance', 'Créé par',
        ];
    }

    public function map($doc): array
    {
        return [
            $doc->id,
            $doc->reference,
            $doc->document_type,
            $doc->document_title,
            $doc->thirdPartner?->tp_title,
            $doc->status,
            $doc->footer?->total_ht,
            $doc->footer?->total_tax,
            $doc->footer?->total_ttc,
            $doc->footer?->amount_paid,
            $doc->footer?->amount_due,
            $doc->issued_at,
            $doc->due_at,
            $doc->user?->name,
        ];
    }
}
