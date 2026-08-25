<?php

namespace App\Http\Requests\Treasury;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_account_id' => ['required', 'integer', 'exists:cash_accounts,id', 'different:to_account_id'],
            'to_account_id'   => ['required', 'integer', 'exists:cash_accounts,id'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'date'            => ['required', 'date'],
            'label'           => ['nullable', 'string', 'max:255'],
            'method'          => ['nullable', 'string', 'max:50'],
            'notes'           => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_account_id.different' => 'Le compte source et le compte destinataire doivent être différents.',
        ];
    }
}
