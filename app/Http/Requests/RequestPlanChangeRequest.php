<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\SubscriptionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Le client choisit sa formule depuis son espace.
 *
 * Réservé à l'administrateur du tenant : souscrire engage la société, ce n'est
 * pas une action de caissier.
 */
class RequestPlanChangeRequest extends FormRequest
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
                'string',
                Rule::in(array_keys((array) config('plans.plans', []))),
            ],
            'billing_period' => [
                'nullable',
                Rule::in([SubscriptionService::PERIOD_MONTHLY, SubscriptionService::PERIOD_YEARLY]),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
            // Mentions légales reprises sur la facture. Demandées ici parce
            // que c'est le seul moment où le client est devant l'écran et
            // motivé : les réclamer après coup, facture déjà émise, oblige à
            // l'annuler et à la refaire.
            'billing_ice'     => ['nullable', 'string', 'max:50'],
            'billing_address' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan.required' => 'Choisissez une formule.',
            'plan.in'       => 'Cette formule n\'existe pas.',
        ];
    }
}
