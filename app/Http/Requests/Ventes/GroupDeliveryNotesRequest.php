<?php

namespace App\Http\Requests\Ventes;

use Illuminate\Foundation\Http\FormRequest;

class GroupDeliveryNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_note_ids'   => ['required', 'array', 'min:1'],
            'delivery_note_ids.*' => ['integer', 'distinct', 'exists:document_headers,id'],
            'issued_at'           => ['nullable', 'date'],
            'customer_ref'        => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'delivery_note_ids.required' => 'Sélectionnez au moins un bon de livraison.',
            'delivery_note_ids.min'      => 'Sélectionnez au moins un bon de livraison.',
        ];
    }
}
