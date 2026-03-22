<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'                 => ['nullable', 'integer', 'exists:third_partners,id'],
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.product_id'          => ['required', 'integer', 'exists:products,id'],
            'items.*.designation'         => ['required', 'string', 'max:255'],
            'items.*.reference'           => ['nullable', 'string', 'max:100'],
            'items.*.quantity'            => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price'          => ['required', 'numeric', 'min:0'],
            'items.*.unit'                => ['nullable', 'string', 'max:20'],
            'items.*.discount_percent'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_percent'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payments'                    => ['required', 'array', 'min:1'],
            'payments.*.amount'           => ['required', 'numeric', 'gt:0'],
            'payments.*.method'           => ['required', 'string', 'in:cash,card,cheque,bank_transfer,effet,credit'],
            'payments.*.reference'        => ['nullable', 'string', 'max:100'],
        ];
    }
}
