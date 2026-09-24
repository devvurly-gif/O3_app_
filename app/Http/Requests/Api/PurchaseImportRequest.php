<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validation *de forme* du JSON envoyé par l'agent de saisie.
 * Les contrôles métier (fournisseur, SKU, totaux, doublons) sont dans
 * PurchaseImportService pour pouvoir tous les remonter en une seule réponse.
 *
 * authorize() s'appuie sur Sanctum\HasApiTokens::tokenCan() directement — pas besoin du
 * middleware `abilities:` (non enregistré dans ce Kernel) : le jeton doit avoir été créé
 * avec l'ability "achats:import" (voir importer\README.md).
 */
class PurchaseImportRequest extends FormRequest
{
    public const TYPES = ['bon_reception', 'facture_achat'];
    public const VAT_RATES = [0, 7, 10, 14, 20];

    public function authorize(): bool
    {
        return $this->user()?->tokenCan('achats:import') ?? false;
    }

    public function rules(): array
    {
        return [
            'dry_run'              => ['sometimes', 'boolean'],
            'external_id'          => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'type'                 => ['required', 'in:' . implode(',', self::TYPES)],
            'source_file'          => ['nullable', 'string', 'max:255'],

            'supplier'             => ['required', 'array'],
            'supplier.ice'         => ['nullable', 'digits:15'],
            'supplier.code'        => ['nullable', 'string', 'max:50'],
            'supplier.name'        => ['nullable', 'string', 'max:255'],
            'supplier.city'        => ['nullable', 'string', 'max:100'],
            'supplier.phone'       => ['nullable', 'string', 'max:30'],
            'supplier.address'     => ['nullable', 'string', 'max:255'],

            // Création automatique des éléments manquants (activée par défaut).
            'allow_create'          => ['sometimes', 'array'],
            'allow_create.products' => ['sometimes', 'boolean'],
            'allow_create.supplier' => ['sometimes', 'boolean'],

            'supplier_reference'   => ['required', 'string', 'max:60'],   // n° BL ou n° facture fournisseur
            'date'                 => ['required', 'date_format:Y-m-d'],
            'due_date'             => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'warehouse'            => ['nullable', 'string', 'max:100'],
            'currency'             => ['sometimes', 'in:MAD'],
            'prices_include_vat'   => ['sometimes', 'boolean'],

            'totals'               => ['required', 'array'],
            'totals.ht'            => ['nullable', 'numeric', 'min:0'],
            'totals.tva'           => ['nullable', 'numeric', 'min:0'],
            'totals.stamp'         => ['nullable', 'numeric', 'min:0'],
            'totals.ttc'           => ['nullable', 'numeric', 'min:0'],

            'lines'                => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.sku'          => ['required', 'string', 'max:100'],
            'lines.*.designation'  => ['required', 'string', 'max:500'],
            'lines.*.qty'          => ['required', 'numeric', 'gt:0'],
            'lines.*.unit'         => ['nullable', 'string', 'max:20'],
            // Prix unitaire tel qu'imprimé : HT par défaut, TTC si prices_include_vat = true.
            'lines.*.unit_price'   => ['required_without:lines.*.unit_price_ht', 'nullable', 'numeric', 'min:0'],
            'lines.*.unit_price_ht'=> ['required_without:lines.*.unit_price', 'nullable', 'numeric', 'min:0'],
            'lines.*.discount_pct' => ['nullable', 'numeric', 'between:0,100'],
            'lines.*.vat_rate'     => ['nullable', 'in:' . implode(',', self::VAT_RATES)],

            'notes'                => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $s = $this->input('supplier', []);
            if (empty($s['ice']) && empty($s['code']) && empty($s['name'])) {
                $v->errors()->add('supplier', 'Au moins un identifiant fournisseur est requis (ice, code ou name).');
            }
            $t = $this->input('totals', []);
            if (!isset($t['ht']) && !isset($t['ttc'])) {
                $v->errors()->add('totals', 'Au moins totals.ht ou totals.ttc est requis.');
            }
        });
    }

    /** Même format d'erreur que les contrôles métier, pour que le Log soit uniforme. */
    protected function failedValidation(Validator $validator): void
    {
        $errors = [];
        foreach ($validator->errors()->messages() as $field => $messages) {
            foreach ($messages as $m) {
                $errors[] = ['level' => 'BLOQUANT', 'code' => 'VALIDATION', 'field' => $field, 'message' => $m];
            }
        }
        throw new HttpResponseException(response()->json([
            'status'      => 'rejected',
            'external_id' => $this->input('external_id'),
            'dry_run'     => (bool) $this->input('dry_run', false),
            'errors'      => $errors,
            'warnings'    => [],
        ], 422));
    }
}
