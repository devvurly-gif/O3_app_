<?php

namespace App\Imports;

use App\Models\Brand;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\Importable;

class BrandsImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    use Importable;

    private int $created = 0;
    private int $updated = 0;

    public function model(array $row)
    {
        $existing = Brand::where('br_title', $row['nom'])->first();

        if ($existing) {
            $this->updated++;
            return null;
        }

        $this->created++;
        return new Brand([
            'br_title'  => $row['nom'],
            'br_status' => true,
        ]);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
        ];
    }

    public function getCreatedCount(): int { return $this->created; }
    public function getUpdatedCount(): int { return $this->updated; }
}
