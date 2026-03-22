<?php

namespace App\Http\Requests\Achats;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentAchatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->route()->getActionMethod() === 'generer_reception') {
            return [];
        }

        return [
            'fournisseur_id'              => ['required', 'exists:third_partners,id'],
            'warehouse_id'                => ['required', 'exists:warehouses,id'],
            'lignes'                      => ['required', 'array', 'min:1'],
            'lignes.*.product_id'         => ['required', 'exists:products,id'],
            'lignes.*.quantite_commandee' => ['required', 'numeric', 'min:0.01'],
            'lignes.*.prix_unitaire_ht'   => ['required', 'numeric', 'min:0'],
            'lignes.*.taux_tva'           => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Validate that the PurchaseOrder is confirmed before generating a ReceiptNote.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->route()->getActionMethod() !== 'generer_reception') {
                return;
            }

            /** @var \App\Models\DocumentHeader|null $commande */
            $commande = $this->route('commande');
            if (!$commande) return;

            if ($commande->status !== 'confirmed') {
                $v->errors()->add(
                    'commande',
                    'Le Bon de Commande doit être confirmé avant de générer un Bon de Réception.'
                );
            }
        });
    }
}
