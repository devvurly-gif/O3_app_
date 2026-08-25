<?php

namespace App\Http\Requests\Achats;

use Illuminate\Foundation\Http\FormRequest;

class GroupReceiptNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipt_ids'   => ['required', 'array', 'min:1'],
            'receipt_ids.*' => ['integer', 'distinct', 'exists:document_headers,id'],
            'issued_at'     => ['nullable', 'date'],
            'supplier_ref'  => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'receipt_ids.required' => 'Sélectionnez au moins un bon de réception.',
            'receipt_ids.min'      => 'Sélectionnez au moins un bon de réception.',
        ];
    }
}
