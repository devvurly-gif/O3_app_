<?php

namespace App\Observers;

use App\Models\DocumentHeader;

/**
 * Stock movements for ReceiptNotes are now handled explicitly
 * via StockMouvementService::processDocument() in the controllers,
 * called AFTER lines have been created.
 *
 * This observer is kept as a hook point for future purchase-specific
 * side effects that should fire on the Eloquent event.
 */
class DocumentAchatObserver
{
}
