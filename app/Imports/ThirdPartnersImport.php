<?php

namespace App\Imports;

use App\Models\ThirdPartner;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\Importable;

class ThirdPartnersImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    use Importable;

    private int $created = 0;
    private int $updated = 0;

    public function model(array $row)
    {
        $existing = !empty($row['ice'])
            ? ThirdPartner::where('tp_Ice_Number', $row['ice'])->first()
            : null;

        if ($existing) {
            $existing->update(array_filter([
                'tp_title'           => $row['nom'] ?? null,
                'tp_Role'            => $row['role'] ?? null,
                'tp_phone'           => $row['telephone'] ?? null,
                'tp_email'           => $row['email'] ?? null,
                'tp_address'         => $row['adresse'] ?? null,
                'tp_city'            => $row['ville'] ?? null,
                'tp_Rc_Number'       => $row['rc'] ?? null,
                'tp_patente_Number'  => $row['patente'] ?? null,
                'tp_IdenFiscal'      => $row['if'] ?? null,
                'seuil_credit'       => $row['seuil_credit'] ?? null,
            ]));
            $this->updated++;
            return null;
        }

        $this->created++;
        return new ThirdPartner([
            'tp_title'           => $row['nom'],
            'tp_Role'            => $row['role'] ?? 'customer',
            'tp_Ice_Number'      => $row['ice'] ?? null,
            'tp_Rc_Number'       => $row['rc'] ?? null,
            'tp_patente_Number'  => $row['patente'] ?? null,
            'tp_IdenFiscal'      => $row['if'] ?? null,
            'tp_phone'           => $row['telephone'] ?? null,
            'tp_email'           => $row['email'] ?? null,
            'tp_address'         => $row['adresse'] ?? null,
            'tp_city'            => $row['ville'] ?? null,
            'tp_status'          => true,
            'encours_actuel'     => 0,
            'seuil_credit'       => $row['seuil_credit'] ?? 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'nom'  => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'in:customer,supplier,both'],
        ];
    }

    public function getCreatedCount(): int { return $this->created; }
    public function getUpdatedCount(): int { return $this->updated; }
}
