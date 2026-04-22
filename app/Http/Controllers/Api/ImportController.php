<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\BrandsImport;
use App\Imports\CategoriesImport;
use App\Imports\ProductsImport;
use App\Imports\ThirdPartnersImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use Maatwebsite\Excel\HeadingRowImport;

class ImportController extends Controller
{
    private const ENTITY_MAP = [
        'products'       => ProductsImport::class,
        'customers'      => ThirdPartnersImport::class,
        'suppliers'      => ThirdPartnersImport::class,
        'categories'     => CategoriesImport::class,
        'brands'         => BrandsImport::class,
    ];

    private const HEADINGS_MAP = [
        'products'   => [
            'titre', 'sku', 'ean13', 'imei',
            'categorie', 'marque',
            'description', 'notes',
            'prix_achat',
            'prix_comptoir', 'prix_revendeur', 'prix_grossiste',
            'prix_vente',
            'quantite_dp',
            'cout', 'tva', 'unite',
        ],
        'customers'  => ['nom', 'role', 'ice', 'rc', 'patente', 'if', 'telephone', 'email', 'adresse', 'ville', 'seuil_credit'],
        'suppliers'  => ['nom', 'role', 'ice', 'rc', 'patente', 'if', 'telephone', 'email', 'adresse', 'ville', 'seuil_credit'],
        'categories' => ['nom'],
        'brands'     => ['nom'],
    ];

    // ── Preview (parse + validate without persisting) ────────────────────

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file'   => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'entity' => ['required', 'string', 'in:products,customers,suppliers,categories,brands'],
        ]);

        $entity = $request->input('entity');
        $file   = $request->file('file');

        // Read headings from the file
        $headingRows = (new HeadingRowImport)->toArray($file);
        $fileHeadings = $headingRows[0][0] ?? [];

        $expectedHeadings = self::HEADINGS_MAP[$entity];

        // Read raw data rows using a simple array import
        $rawRows = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray, \Maatwebsite\Excel\Concerns\WithHeadingRow {
            public function array(array $array) {}
        }, $file);

        $rows = $rawRows[0] ?? [];

        // Validate each row against import rules
        $importClass = self::ENTITY_MAP[$entity];
        $import = new $importClass();

        // For customers/suppliers, force the role
        $rules = $import->rules();

        $preview = [];
        $validCount = 0;
        $invalidCount = 0;

        foreach ($rows as $index => $row) {
            // Skip completely empty rows
            $nonEmpty = array_filter($row, fn ($v) => $v !== null && $v !== '');
            if (empty($nonEmpty)) continue;

            $rowErrors = [];
            foreach ($rules as $field => $fieldRules) {
                $value = $row[$field] ?? null;
                $validator = validator([$field => $value], [$field => $fieldRules]);
                if ($validator->fails()) {
                    $rowErrors[$field] = $validator->errors()->get($field);
                }
            }

            $status = empty($rowErrors) ? 'valid' : 'invalid';
            if ($status === 'valid') $validCount++;
            else $invalidCount++;

            $preview[] = [
                'row'    => $index + 2, // +2 because heading row is 1, array is 0-indexed
                'data'   => $row,
                'errors' => $rowErrors,
                'status' => $status,
            ];
        }

        return response()->json([
            'headings'      => $fileHeadings,
            'expected'      => $expectedHeadings,
            'rows'          => array_slice($preview, 0, 200), // limit to 200 rows for preview
            'total'         => count($preview),
            'valid_count'   => $validCount,
            'invalid_count' => $invalidCount,
        ]);
    }

    // ── Import (actual persist) ──────────────────────────────────────────

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file'   => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'entity' => ['required', 'string', 'in:products,customers,suppliers,categories,brands'],
        ]);

        $entity = $request->input('entity');
        $importClass = self::ENTITY_MAP[$entity];
        $import = new $importClass();

        try {
            Excel::import($import, $request->file('file'));
        } catch (ValidationException $e) {
            return response()->json([
                'message'  => 'Erreurs de validation dans le fichier.',
                'failures' => collect($e->failures())->map(fn ($f) => [
                    'row'       => $f->row(),
                    'attribute' => $f->attribute(),
                    'errors'    => $f->errors(),
                ])->take(50),
            ], 422);
        }

        return response()->json([
            'message' => 'Import terminé.',
            'created' => $import->getCreatedCount(),
            'updated' => $import->getUpdatedCount(),
        ]);
    }

    // ── Template download ────────────────────────────────────────────────

    public function template(Request $request, string $entity)
    {
        if (!isset(self::HEADINGS_MAP[$entity])) {
            return response()->json(['message' => 'Entité inconnue.'], 404);
        }

        $headings = self::HEADINGS_MAP[$entity];
        $samples  = self::sampleRowsFor($entity);

        $export = new \App\Exports\ImportTemplateExport($headings, $samples);

        return Excel::download($export, "modele_{$entity}.xlsx");
    }

    /**
     * Representative sample rows shown in the downloaded template so users
     * can see the expected format at a glance. Keys must match HEADINGS_MAP.
     */
    private static function sampleRowsFor(string $entity): array
    {
        return match ($entity) {
            'products' => [
                [
                    'titre'          => 'Samsung Galaxy A15 128Go',
                    'sku'            => 'SAM-A15-128',
                    'ean13'          => '8806095000000',
                    'imei'           => '',
                    'categorie'      => 'Téléphones',
                    'marque'         => 'Samsung',
                    'description'    => 'Smartphone Samsung 6.5"',
                    'notes'          => 'Stock initial en DP',
                    'prix_achat'     => '1800,00',
                    'prix_comptoir'  => '2400,00',
                    'prix_revendeur' => '2200,00',
                    'prix_grossiste' => '2050,00',
                    'prix_vente'     => '',
                    'quantite_dp'    => '10',
                    'cout'           => '1800,00',
                    'tva'            => '20',
                    'unite'          => 'pcs',
                ],
                [
                    'titre'          => 'Chargeur USB-C 20W',
                    'sku'            => 'ACC-CH-20W',
                    'ean13'          => '',
                    'imei'           => '',
                    'categorie'      => 'Accessoires',
                    'marque'         => 'Generic',
                    'description'    => '',
                    'notes'          => '',
                    'prix_achat'     => '35,00',
                    'prix_comptoir'  => '79,00',
                    'prix_revendeur' => '65,00',
                    'prix_grossiste' => '55,00',
                    'prix_vente'     => '',
                    'quantite_dp'    => '50',
                    'cout'           => '35,00',
                    'tva'            => '20',
                    'unite'          => 'pcs',
                ],
            ],
            'customers', 'suppliers' => [
                [
                    'nom'          => 'Client Exemple SARL',
                    'role'         => $entity === 'suppliers' ? 'supplier' : 'customer',
                    'ice'          => '001234567890123',
                    'rc'           => '12345',
                    'patente'      => '12345678',
                    'if'           => '12345678',
                    'telephone'    => '+212 600 000 000',
                    'email'        => 'contact@exemple.ma',
                    'adresse'      => '123 Avenue Mohammed V',
                    'ville'        => 'Casablanca',
                    'seuil_credit' => '10000',
                ],
            ],
            'categories' => [['nom' => 'Téléphones'], ['nom' => 'Accessoires']],
            'brands'     => [['nom' => 'Samsung'], ['nom' => 'Apple']],
            default      => [],
        };
    }

    // ── Legacy endpoints (kept for backward compatibility) ───────────────

    public function products(Request $request): JsonResponse
    {
        $request->merge(['entity' => 'products']);
        return $this->import($request);
    }

    public function thirdPartners(Request $request): JsonResponse
    {
        $request->merge(['entity' => 'customers']);
        return $this->import($request);
    }

    public function categories(Request $request): JsonResponse
    {
        $request->merge(['entity' => 'categories']);
        return $this->import($request);
    }

    public function brands(Request $request): JsonResponse
    {
        $request->merge(['entity' => 'brands']);
        return $this->import($request);
    }
}
