<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validation *de forme* du JSON envoye par l'agent de facturation client
 * pour une commande WhatsApp. Memes principes que PurchaseImportRequest :
 * les controles metier (client, produits, quantites) restent dans
 * WhatsAppOrderImportService pour tout remonter en une seule reponse.
 */
class WhatsAppOrderImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tokenCan('ventes:whatsapp-import') ?? false;
    }

    public function rules(): array
    {
        return [
            'dry_run'              => ['sometimes', 'boolean'],
            'external_id'          => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'source_text'          => ['nullable', 'string', 'max:4000'],

            'customer'              => ['required', 'array'],
            'customer.phone'        => ['nullable', 'string', 'max:50'],
            'customer.name'         => ['nullable', 'string', 'max:255'],

            'lines'                => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.query'        => ['required', 'string', 'max:255'],
            'lines.*.quantity'     => ['required', 'numeric', 'gt:0'],
            'lines.*.unit'         => ['nullable', 'string', 'max:20'],

            'notes'                => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $c = $this->input('customer', []);
            if (empty($c['phone']) && empty($c['name'])) {
                $v->errors()->add('customer', 'Au moins un identifiant client est requis (phone ou name).');
            }
        });
    }

    /** Meme format d'erreur que les controles metier, pour un Log uniforme. */
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
