<?php

namespace App\Observers;

use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Agents\AgentTriggers;
use Illuminate\Database\Eloquent\Model;

/**
 * Émet les événements internes qui peuvent déclencher une routine de l'orchestrateur (voir AgentTriggers).
 * Ne fait rien, et ne coûte presque rien, tant qu'aucune routine n'attend l'événement ; ne peut jamais faire
 * échouer l'opération qui l'a produit (AgentTriggers::emit absorbe toute erreur).
 *
 * Enregistré sur plusieurs modèles : chaque méthode accepte donc n'importe quel modèle et ne réagit qu'à ceux
 * qui la concernent (un type strict ferait échouer, par exemple, la création d'un document).
 */
class AgentTriggerObserver
{
    public function created(Model $model): void
    {
        if ($model instanceof Product) {
            AgentTriggers::emit('produit_cree', ['product_id' => $model->id, 'title' => $model->p_title, 'sku' => $model->p_sku]);
        } elseif ($model instanceof Payment) {
            AgentTriggers::emit('paiement_recu', ['payment_id' => $model->id, 'amount' => (float) $model->amount, 'document_header_id' => $model->document_header_id]);
        }
    }

    public function updated(Model $model): void
    {
        // Une facture de vente qui VIENT d'être confirmée (pas chaque modification d'une facture déjà confirmée).
        if ($model instanceof DocumentHeader && $model->wasChanged('status') && $model->status === 'confirmed' && $model->isInvoiceSale()) {
            $model->loadMissing('footer');
            AgentTriggers::emit('facture_vente_confirmee', [
                'document_id' => $model->id, 'reference' => $model->reference, 'amount' => (float) ($model->footer?->total_ttc ?? 0),
            ]);
        }
    }
}
