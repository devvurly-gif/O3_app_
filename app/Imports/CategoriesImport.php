<?php

namespace App\Imports;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\Importable;

class CategoriesImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    use Importable;

    private int $created = 0;
    private int $updated = 0;

    public function model(array $row)
    {
        $existing = Category::where('ctg_title', $row['nom'])->first();

        if ($existing) {
            $this->updated++;
            return null;
        }

        $this->created++;
        return new Category([
            'ctg_title'  => $row['nom'],
            'ctg_status' => true,
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
