<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithEvents
{
    /**
     * @param  array<int, string>          $headingColumns Ordered header names
     * @param  array<int, array<int|string, mixed>> $sampleRows Optional example rows
     */
    public function __construct(
        private array $headingColumns,
        private array $sampleRows = [],
    ) {}

    public function headings(): array
    {
        return $this->headingColumns;
    }

    public function array(): array
    {
        // Align each sample row to the heading order, filling missing keys with ''.
        return array_map(
            fn (array $row) => array_map(
                fn (string $h) => $row[$h] ?? '',
                $this->headingColumns
            ),
            $this->sampleRows
        );
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E3A8A']],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DBEAFE'],
                ],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                // Freeze the header row.
                $sheet->freezePane('A2');
                // Make header row a touch taller for readability.
                $sheet->getRowDimension(1)->setRowHeight(22);
            },
        ];
    }
}
