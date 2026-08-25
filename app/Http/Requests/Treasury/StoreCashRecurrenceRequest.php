<?php

namespace App\Http\Requests\Treasury;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashRecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'cr_label'         => [$required, 'string', 'max:255'],
            'cr_direction'     => [$required, 'in:in,out'],
            'cr_amount'        => [$required, 'numeric', 'min:0.01'],
            'cash_account_id'  => [$required, 'integer', 'exists:cash_accounts,id'],
            'cash_category_id' => ['nullable', 'integer', 'exists:cash_categories,id'],
            'thirdPartner_id'  => ['nullable', 'integer', 'exists:third_partners,id'],
            'cr_method'        => ['nullable', 'string', 'max:50'],
            'cr_frequency'     => [$required, 'in:weekly,monthly,quarterly,yearly'],
            'cr_anchor_day'    => ['nullable', 'integer', 'min:1', 'max:31'],
            'cr_start_date'    => [$required, 'date'],
            'cr_end_date'      => ['nullable', 'date', 'after_or_equal:cr_start_date'],
            'cr_next_run_at'   => ['nullable', 'date'],
            'cr_status'        => ['sometimes', 'boolean'],
            'cr_notes'         => ['nullable', 'string'],
        ];
    }
}
