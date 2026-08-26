<?php

namespace App\Http\Requests;

use App\Models\DocumentHeader;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_header_id' => ['required', 'integer', 'exists:document_headers,id'],
            'amount'             => ['required', 'numeric', 'min:0.01'],
            'method'             => ['required', 'string', 'in:cash,bank_transfer,cheque,effet,credit'],
            'paid_at'            => ['required', 'date'],
            'reference'          => ['nullable', 'string', 'max:255'],
            'notes'              => ['nullable', 'string'],
        ];
    }

    /**
     * Validate that payments are only allowed on permitted document types.
     * By default only invoices; BL allowed when the setting is active.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            /** @var DocumentHeader|null $doc */
            $doc = DocumentHeader::find($this->document_header_id);
            if (!$doc) {
                return;
            }

            // Symetrique du controle pose sur l'ecriture manuelle : la
            // saisie a la main d'abord, le reglement ensuite, doublerait le
            // montant au journal de tresorerie.
            if ($doc->hasManualTreasuryEntry()) {
                $v->errors()->add(
                    'document_header_id',
                    'Une ecriture de tresorerie saisie a la main pointe deja vers ce document : '
                    . 'enregistrer un reglement par-dessus compterait la somme deux fois. '
                    . 'Annulez l\'ecriture manuelle depuis la Tresorerie, puis ressaisissez le reglement ici.'
                );
                return;
            }

            $allowedTypes = ['InvoiceSale', 'InvoicePurchase'];

            if (Setting::get('ventes', 'paiement_sur_bl', 'false') === 'true') {
                $allowedTypes[] = 'DeliveryNote';
            }

            if (!in_array($doc->document_type, $allowedTypes)) {
                $message = 'Les paiements ne sont autorisés que sur les factures.';
                if ($doc->document_type === 'DeliveryNote') {
                    $message .= ' L\'option paiement sur BL n\'est pas activée dans les réglages.';
                }
                $v->errors()->add('document_header_id', $message);
            }
        });
    }
}
