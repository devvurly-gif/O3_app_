<?php

namespace App\Http\Requests\Treasury;

use App\Models\CashCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCashTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cash_account_id'    => ['sometimes', 'integer', 'exists:cash_accounts,id'],
            'cash_category_id'   => ['nullable', 'integer', 'exists:cash_categories,id'],
            'ct_direction'       => ['sometimes', 'in:in,out'],
            'ct_amount'          => ['sometimes', 'numeric', 'min:0.01'],
            'ct_date'            => ['sometimes', 'date'],
            'ct_label'           => ['sometimes', 'string', 'max:255'],
            'ct_method'          => ['nullable', 'string', 'max:50'],
            'ct_reference'       => ['nullable', 'string', 'max:255'],
            'thirdPartner_id'    => ['nullable', 'integer', 'exists:third_partners,id'],
            'document_header_id' => ['nullable', 'integer', 'exists:document_headers,id'],
            'ct_notes'           => ['nullable', 'string'],
            'ct_status'          => ['sometimes', 'in:active,cancelled'],
            'attachment'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (!$this->cash_category_id) {
                return;
            }

            // Le sens peut ne pas être renvoyé sur une modification partielle :
            // dans ce cas c'est celui déjà en base qui fait foi.
            $direction = $this->ct_direction ?? $this->route('cash_transaction')?->ct_direction;
            if (!$direction) {
                return;
            }

            $category = CashCategory::find($this->cash_category_id);
            if ($category && !$category->acceptsDirection($direction)) {
                $v->errors()->add(
                    'cash_category_id',
                    "La catégorie « {$category->cc_title} » ne s'applique pas à ce sens d'écriture."
                );
            }
        });
    }
}
