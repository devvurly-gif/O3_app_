<?php

namespace App\Exports;

use App\Models\ThirdPartner;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ThirdPartnersExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $q = ThirdPartner::query();

        if (!empty($this->filters['tp_Role'])) {
            $q->where('tp_Role', $this->filters['tp_Role']);
        }

        if (!empty($this->filters['search'])) {
            $q->where(function ($q2) {
                $q2->where('tp_title', 'like', '%' . $this->filters['search'] . '%')
                   ->orWhere('tp_email', 'like', '%' . $this->filters['search'] . '%')
                   ->orWhere('tp_phone', 'like', '%' . $this->filters['search'] . '%');
            });
        }

        return $q->orderBy('tp_title');
    }

    public function headings(): array
    {
        return [
            'ID', 'Code', 'Nom', 'Rôle',
            'ICE', 'RC', 'Patente', 'IF',
            'Téléphone', 'Email', 'Adresse', 'Ville',
            'Encours actuel', 'Seuil crédit', 'Statut',
        ];
    }

    public function map($partner): array
    {
        return [
            $partner->id,
            $partner->tp_code,
            $partner->tp_title,
            $partner->tp_Role,
            $partner->tp_Ice_Number,
            $partner->tp_Rc_Number,
            $partner->tp_patente_Number,
            $partner->tp_IdenFiscal,
            $partner->tp_phone,
            $partner->tp_email,
            $partner->tp_address,
            $partner->tp_city,
            $partner->encours_actuel,
            $partner->seuil_credit,
            $partner->tp_status ? 'Actif' : 'Inactif',
        ];
    }
}
