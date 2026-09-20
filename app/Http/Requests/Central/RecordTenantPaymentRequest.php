<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Services\SubscriptionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Saisie d'un règlement d'abonnement au back-office central.
 *
 * L'autorisation est déjà portée par le middleware `role:admin` du groupe de
 * routes central ; on la redouble ici pour que la règle survive à un
 * déplacement de la route.
 */
class RecordTenantPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->name === 'admin';
    }

    public function rules(): array
    {
        return [
            'plan' => [
                'nullable',
                Rule::in(array_keys((array) config('plans.plans', []))),
            ],
            'billing_period' => [
                'nullable',
                Rule::in([SubscriptionService::PERIOD_MONTHLY, SubscriptionService::PERIOD_YEARLY]),
            ],
            // Facultatif : le tarif catalogue s'applique par défaut. Une remise
            // négociée doit pouvoir être saisie telle quelle, sinon la table
            // des règlements cesse de refléter ce qui a été encaissé.
            'amount_cents' => ['nullable', 'integer', 'min:0'],
            'paid_at'      => ['nullable', 'date'],
            'method'       => ['nullable', Rule::in(['virement', 'cheque', 'especes', 'carte'])],
            'reference'    => ['nullable', 'string', 'max:255'],
            'note'         => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount_cents.min' => 'Le montant ne peut pas être négatif.',
            'plan.in'          => 'Cette formule n\'existe pas.',
        ];
    }
}
