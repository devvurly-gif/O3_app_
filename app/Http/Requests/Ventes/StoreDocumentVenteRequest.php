<?php

namespace App\Http\Requests\Ventes;

use App\Models\ThirdPartner;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentVenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if (in_array($this->route()->getActionMethod(), ['generer_bc', 'generer_bl'])) {
            return [];
        }

        return [
            'client_id'                   => ['required', 'exists:third_partners,id'],
            'lignes'                      => ['required', 'array', 'min:1'],
            'lignes.*.product_id'         => ['required', 'exists:products,id'],
            'lignes.*.quantite_commandee' => ['required', 'numeric', 'min:0.01'],
            'lignes.*.prix_unitaire_ht'   => ['required', 'numeric', 'min:0'],
            'lignes.*.taux_tva'           => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Credit verification -- only for BL generation.
     * Reads the client from the route-bound devis model, not from the request body.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->route()->getActionMethod() !== 'generer_bl') {
                return;
            }

            /** @var \App\Models\DocumentHeader|null $source */
            $source = $this->route('bc') ?? $this->route('source');
            if (!$source) return;

            $client = ThirdPartner::find($source->thirdPartner_id);
            if (!$client) return;

            if ($client->seuil_credit <= 0) return;

            $montantBL = $source->footer?->total_ttc ?? 0;

            $totalApres = $client->encours_actuel + $montantBL;

            if ($totalApres > $client->seuil_credit) {
                $v->errors()->add('credit', sprintf(
                    'Encours dépassé. Seuil : %.2f MAD | Encours actuel : %.2f MAD | Montant BL : %.2f MAD | Total après : %.2f MAD',
                    $client->seuil_credit,
                    $client->encours_actuel,
                    $montantBL,
                    $totalApres
                ));
            }
        });
    }
}
