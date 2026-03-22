<?php

namespace App\Exports;

use App\Models\Payment;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PaymentsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $q = Payment::query()->with(['document.thirdPartner', 'user']);

        if (!empty($this->filters['method'])) {
            $q->where('method', $this->filters['method']);
        }

        return $q->orderByDesc('paid_at');
    }

    public function headings(): array
    {
        return [
            'ID', 'Code', 'Date paiement',
            'Montant', 'Méthode', 'Référence',
            'Document', 'Client / Fournisseur',
            'Utilisateur', 'Notes',
        ];
    }

    public function map($p): array
    {
        return [
            $p->id,
            $p->payment_code,
            $p->paid_at?->format('d/m/Y'),
            $p->amount,
            $p->method,
            $p->reference,
            $p->document?->reference,
            $p->document?->thirdPartner?->tp_title,
            $p->user?->name,
            $p->notes,
        ];
    }
}
