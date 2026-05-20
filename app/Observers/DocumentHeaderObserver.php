<?php

namespace App\Observers;

use App\Events\PartnerEncoursUpdated;
use App\Models\DocumentHeader;

/**
 * Automatically recalculate customer/supplier encours_actuel
 * whenever a document changes.
 */
class DocumentHeaderObserver
{
    public function created(DocumentHeader $document): void
    {
        $this->recalculatePartnerEncours($document);
    }

    public function updated(DocumentHeader $document): void
    {
        $this->recalculatePartnerEncours($document);
    }

    public function deleted(DocumentHeader $document): void
    {
        $this->recalculatePartnerEncours($document);
    }

    public function restored(DocumentHeader $document): void
    {
        $this->recalculatePartnerEncours($document);
    }

    private function recalculatePartnerEncours(DocumentHeader $document): void
    {
        if ($document->thirdPartner_id) {
            $partner = $document->thirdPartner;
            $newEncours = $partner->recalculateEncours();
            PartnerEncoursUpdated::dispatch($partner, $newEncours);
        }
    }
}
