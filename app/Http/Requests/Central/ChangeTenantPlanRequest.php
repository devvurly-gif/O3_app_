<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\TenantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changement de formule décidé au back-office, sans encaissement.
 *
 * Sert aux corrections et aux gestes commerciaux. Un règlement passe par
 * RecordTenantPaymentRequest, qui repousse aussi l'échéance.
 */
class ChangeTenantPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->name === 'admin';
    }

    public function rules(): array
    {
        return [
            'plan' => [
                'required',
                Rule::in(array_keys((array) config('plans.plans', []))),
            ],
            'status' => [
                'nullable',
                Rule::in(array_column(TenantStatus::cases(), 'value')),
            ],
            'subscription_ends_at' => ['nullable', 'date'],
            // Dérogations par capacité : true accorde hors formule, false
            // retire une capacité pourtant incluse, l'absence de clé laisse la
            // formule décider.
            'feature_overrides'   => ['nullable', 'array'],
            'feature_overrides.*' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan.required' => 'La formule est obligatoire.',
            'plan.in'       => 'Cette formule n\'existe pas.',
        ];
    }
}
