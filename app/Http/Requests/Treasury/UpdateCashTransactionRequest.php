<?php

namespace App\Http\Requests\Treasury;

use App\Models\CashCategory;
use App\Models\Payment;
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
            // Seul un rattachement nouveau est controle : refuser un document
            // deja porte par l'ecriture bloquerait toute modification ulterieure
            // d'une ligne pourtant legitime.
            $current = $this->route('cash_transaction')?->document_header_id;
            $target  = $this->document_header_id ? (int) $this->document_header_id : null;

            if ($target && $target !== $current) {
                $this->refuseDoublonReglement($v, $target);
            }

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

    /**
     * Un document deja regle ne peut pas porter une ecriture manuelle.
     *
     * Le journal de tresorerie reunit deux sources : les ecritures saisies a la
     * main et les reglements des documents. Saisir a la main une depense qui
     * correspond a un reglement deja enregistre la ferait compter deux fois —
     * le solde de caisse serait faux, et rien ne le signalerait.
     */
    private function refuseDoublonReglement(Validator $v, ?int $documentId): void
    {
        if (!$documentId) {
            return;
        }

        // Un document de caisse ne compte pas : ses reglements sont hors du
        // journal jusqu'a la validation de la session, il n'y a donc rien a
        // doubler.
        $payments = Payment::where('document_header_id', $documentId)
            ->whereHas('document', fn ($q) => $q->whereNull('pos_session_id'))
            ->count();

        if ($payments > 0) {
            $v->errors()->add(
                'document_header_id',
                'Ce document porte déjà ' . $payments . ' règlement(s) : ils figurent au journal de trésorerie. '
                . 'Pour encaisser ou décaisser davantage sur ce document, passez par le bouton de paiement de la facture '
                . 'plutôt que par une écriture manuelle.'
            );
        }
    }
}
