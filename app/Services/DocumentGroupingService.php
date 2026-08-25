<?php

namespace App\Services;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\DocumentLigne;
use App\Models\ThirdPartner;
use App\Observers\DocumentNotificationObserver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Produit une facture unique à partir de plusieurs documents d'un même tiers.
 *
 * Un fournisseur qui livre huit fois dans le mois n'envoie pas huit factures :
 * il en envoie une, qui récapitule les huit bons. Tant que la comptabilité du
 * tenant porte huit factures là où le fournisseur en a émis une, aucun
 * rapprochement ne tombe juste.
 *
 * Quatre entrées, pour quatre situations réelles :
 *
 *   - `fromReceiptNotes()` — achats : on coche les bons de réception reçus dans
 *     le mois et on les facture d'un coup.
 *   - `fromDeliveryNotes()` — ventes : le pendant exact, sur les bons de
 *     livraison d'un client.
 *   - `fromTickets()` — caisse : les tickets d'une session, récapitulés à la
 *     clôture, un client à la fois.
 *   - `fromInvoices()` — le rattrapage : les bons ont déjà été facturés un par
 *     un et il faut recoller les morceaux, en reprenant les règlements déjà
 *     imputés.
 *
 * Le mécanisme reprend celui de la facture périodique côté vente
 * (GeneratePeriodicInvoices) : un document sans parent, les lignes de toutes
 * les sources concaténées, les pieds additionnés, et la liste des sources
 * rappelée en note — un `parent_id` ne pouvant pas désigner huit documents.
 *
 * La règle métier vit ici et non dans la commande console, parce que l'écran
 * et la commande l'appellent tous les deux : la laisser dans la commande
 * aurait obligé l'interface à la réimplémenter, et les deux auraient divergé.
 */
class DocumentGroupingService
{
    /**
     * Bons de réception → une facture d'achat unique.
     *
     * Les bons ne sont pas supprimés : ils restent la preuve de la livraison.
     * Ils passent en 'converted', ce qui les retire des bons facturables sans
     * effacer leur histoire — et empêche de les facturer une seconde fois.
     *
     * @param  Collection<int, DocumentHeader>  $receipts
     * @return array{invoice: DocumentHeader, payments_moved: int, replaced: int}
     */
    public function fromReceiptNotes(
        Collection $receipts,
        ?string $issuedAt = null,
        ?string $supplierRef = null,
    ): array {
        $this->guardCommon($receipts, 'ReceiptNotePurchase', 'Seuls des bons de réception peuvent être facturés ici.');

        foreach ($receipts as $receipt) {
            if (!in_array($receipt->status, ['confirmed', 'received'], true)) {
                throw new HttpException(422, "Le bon {$receipt->reference} n'est pas confirmé (statut : {$receipt->status}).");
            }

            if ($receipt->children()->where('document_type', 'InvoicePurchase')->exists()) {
                throw new HttpException(422, "Le bon {$receipt->reference} est déjà facturé.");
            }
        }

        return $this->build($receipts, $issuedAt, $supplierRef, 'InvoicePurchase', deleteSources: false);
    }

    /**
     * Bons de livraison → une facture de vente unique.
     *
     * Le pendant exact du côté achat, à trois nuances près :
     *
     *   - un BL peut déjà porter des règlements quand l'option « paiement sur
     *     BL » est active : ils suivent la facture, comme dans la conversion
     *     unitaire ;
     *   - un BL non facturé compte déjà dans l'encours du client. La facture
     *     prend le relais, l'encours ne bouge donc pas ;
     *   - le statut posé est 'converted' et non 'delivered'. `isBilled()`
     *     reconnaît le premier, pas le second : marquer 'delivered' laisserait
     *     le BL refacturable un par un par-dessus la facture groupée.
     *
     * @param  Collection<int, DocumentHeader>  $notes
     * @return array{invoice: DocumentHeader, payments_moved: int, replaced: int}
     */
    public function fromDeliveryNotes(
        Collection $notes,
        ?string $issuedAt = null,
        ?string $customerRef = null,
    ): array {
        $this->guardCommon($notes, 'DeliveryNote', 'Seuls des bons de livraison peuvent être facturés ici.');

        foreach ($notes as $note) {
            // Avec « paiement sur BL », encaisser fait passer le bon en
            // 'partial' ou 'paid' : un bon paye reste un bon a facturer.
            if (!in_array($note->status, ['confirmed', 'delivered', 'partial', 'paid'], true)) {
                throw new HttpException(422, "Le bon {$note->reference} n'est pas confirmé (statut : {$note->status}).");
            }

            if ($note->isBilled() || $note->children()->where('document_type', 'InvoiceSale')->exists()) {
                throw new HttpException(422, "Le bon {$note->reference} est déjà facturé.");
            }
        }

        return $this->build($notes, $issuedAt, $customerRef, 'InvoiceSale', deleteSources: false);
    }

    /**
     * Tickets de caisse → une facture de vente unique.
     *
     * À la clôture, les tickets d'une session sont récapitulés par une facture,
     * un client à la fois. Un ticket est toujours réglé — une vente POS à
     * crédit devient un bon de livraison, pas un ticket — donc la facture naît
     * payée, avec les règlements et leurs moyens repris tels quels.
     *
     * @param  Collection<int, DocumentHeader>  $tickets
     * @return array{invoice: DocumentHeader, payments_moved: int, replaced: int}
     */
    public function fromTickets(
        Collection $tickets,
        ?string $issuedAt = null,
        ?string $sessionRef = null,
    ): array {
        $this->guardCommon($tickets, 'TicketSale', 'Seuls des tickets de caisse peuvent être facturés ici.');

        foreach ($tickets as $ticket) {
            if ($ticket->status === 'converted' || $ticket->children()->where('document_type', 'InvoiceSale')->exists()) {
                throw new HttpException(422, "Le ticket {$ticket->reference} est déjà facturé.");
            }
        }

        return $this->build($tickets, $issuedAt, $sessionRef, 'InvoiceSale', deleteSources: false);
    }

    /**
     * Factures d'achat existantes → une facture unique qui les remplace.
     *
     * @param  Collection<int, DocumentHeader>  $invoices
     * @return array{invoice: DocumentHeader, payments_moved: int, replaced: int}
     */
    public function fromInvoices(
        Collection $invoices,
        ?string $issuedAt = null,
        ?string $supplierRef = null,
    ): array {
        $this->guardCommon($invoices, 'InvoicePurchase', 'Seules des factures d\'achat peuvent être regroupées.');

        if ($invoices->count() < 2) {
            throw new HttpException(422, 'Il faut au moins deux factures à regrouper.');
        }

        return $this->build($invoices, $issuedAt, $supplierRef, 'InvoicePurchase', deleteSources: true);
    }

    // ── Fabrication ───────────────────────────────────────────────

    private function build(
        Collection $sources,
        ?string $issuedAt,
        ?string $supplierRef,
        string $targetType,
        bool $deleteSources,
    ): array {
        $sources->loadMissing(['lignes', 'footer', 'parent', 'payments', 'incrementor']);
        $sources = $sources->sortBy([['issued_at', 'asc'], ['id', 'asc']])->values();

        $issuedAt ??= $sources->max('issued_at')?->toDateString() ?? now()->toDateString();

        // Une facture groupée n'est pas une affaire nouvelle à annoncer : les
        // documents qu'elle récapitule ont déjà été notifiés à leur création.
        DocumentNotificationObserver::$silent = true;

        try {
            return DB::transaction(function () use ($sources, $issuedAt, $supplierRef, $targetType, $deleteSources) {
                $first     = $sources->first();
                $totalTtc  = $this->sumFooter($sources, 'total_ttc');
                $totalPaid = $this->sumFooter($sources, 'amount_paid');

                // Le compteur est celui des factures d'achat, pas celui du
                // document source : partir de l'incrementeur d'un bon de
                // reception donnerait a la facture une reference BDR-xxxx.
                $incrementor = $this->invoiceIncrementor($first, $targetType);

                $grouped = DocumentHeader::create([
                    'document_incrementor_id' => $incrementor?->id ?? $first->document_incrementor_id,
                    'reference'               => $this->nextReference($incrementor),
                    'document_type'           => $targetType,
                    'document_title'          => $targetType === 'InvoiceSale'
                        ? 'Facture Vente groupée'
                        : 'Facture Achat groupée',
                    'parent_id'               => null,
                    'thirdPartner_id'         => $first->thirdPartner_id,
                    'company_role'            => $first->company_role,
                    'warehouse_id'            => $first->warehouse_id,
                    // La facture herite de la session : elle se verrouille
                    // avec elle a la cloture, comme les tickets qu'elle
                    // recapitule.
                    'pos_session_id'          => $first->pos_session_id,
                    'user_id'                 => auth()->id() ?? $first->user_id,
                    'status'                  => 'pending',
                    'issued_at'               => $issuedAt,
                    'due_at'                  => date('Y-m-d', strtotime($issuedAt . ' +60 days')),
                    'notes'                   => $this->buildNotes($sources, $supplierRef, $deleteSources),
                ]);

                $this->copyLines($sources, $grouped);

                DocumentFooter::create([
                    'document_header_id' => $grouped->id,
                    'total_ht'           => $this->sumFooter($sources, 'total_ht'),
                    'total_discount'     => $this->sumFooter($sources, 'total_discount'),
                    'total_tax'          => $this->sumFooter($sources, 'total_tax'),
                    'total_ttc'          => $totalTtc,
                    'amount_paid'        => $totalPaid,
                    'amount_due'         => max(0, round($totalTtc - $totalPaid, 2)),
                    'payment_method'     => 'credit',
                ]);

                $paymentsMoved = $this->movePayments($sources, $grouped);

                foreach ($sources as $source) {
                    if ($deleteSources) {
                        // Rattrapage : le bon d'origine de chaque facture
                        // remplacée doit lui aussi sortir des facturables.
                        $source->parent?->update(['status' => 'converted']);
                        $source->delete();
                        continue;
                    }

                    // Cas courant : le bon reste, marqué facturé.
                    $source->update(['status' => 'converted']);
                }

                $grouped->setRelation('footer', $grouped->footer()->first());
                $grouped->footer->syncHeaderStatus();

                ThirdPartner::find($first->thirdPartner_id)?->recalculateEncours();

                return [
                    'invoice'        => $grouped->fresh(['footer', 'thirdPartner', 'lignes']),
                    'payments_moved' => $paymentsMoved,
                    'replaced'       => $sources->count(),
                ];
            });
        } finally {
            DocumentNotificationObserver::$silent = false;
        }
    }

    // ── Garde-fous ────────────────────────────────────────────────

    /**
     * Refuse tout ce qui produirait une facture incohérente.
     *
     * Le contrôle du fournisseur unique n'avait pas lieu d'être tant que seule
     * la commande appelait ce code : elle filtrait déjà par fournisseur. Depuis
     * un écran, rien n'empêche de cocher les bons de deux fournisseurs et de
     * fabriquer une dette attribuée au mauvais tiers.
     */
    private function guardCommon(Collection $sources, string $expectedType, string $typeMessage): void
    {
        if ($sources->isEmpty()) {
            throw new HttpException(422, 'Aucun document sélectionné.');
        }

        if ($sources->contains(fn ($d) => $d->document_type !== $expectedType)) {
            throw new HttpException(422, $typeMessage);
        }

        if ($sources->contains(fn ($d) => $d->status === 'cancelled')) {
            throw new HttpException(422, 'Un document annulé ne peut pas être regroupé.');
        }

        if ($sources->pluck('thirdPartner_id')->unique()->count() > 1) {
            throw new HttpException(422, 'Tous les documents doivent appartenir au même fournisseur.');
        }

        if ($sources->contains(fn ($d) => $d->thirdPartner_id === null)) {
            throw new HttpException(422, 'Un document sans fournisseur ne peut pas être regroupé.');
        }
    }

    // ── Plomberie ─────────────────────────────────────────────────

    private function copyLines(Collection $sources, DocumentHeader $grouped): void
    {
        $now       = now();
        $sortOrder = 0;
        $rows      = [];

        foreach ($sources as $source) {
            foreach ($source->lignes as $ligne) {
                $rows[] = [
                    'document_header_id' => $grouped->id,
                    'product_id'         => $ligne->product_id,
                    'variant_id'         => $ligne->variant_id,
                    'sort_order'         => ++$sortOrder,
                    'line_type'          => $ligne->line_type,
                    'designation'        => $ligne->designation,
                    'reference'          => $ligne->reference,
                    'quantity'           => $ligne->quantity,
                    'unit'               => $ligne->unit,
                    'unit_price'         => $ligne->unit_price,
                    'discount_percent'   => $ligne->discount_percent,
                    'tax_percent'        => $ligne->tax_percent,
                    'total_ligne_ht'     => $ligne->total_ligne_ht,
                    'total_tax'          => $ligne->total_tax,
                    'total_ttc'          => $ligne->total_ttc,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }
        }

        if ($rows) {
            DocumentLigne::insert($rows);
        }
    }

    /**
     * Les règlements suivent la nouvelle facture. Sans ça un paiement
     * pointerait vers un document supprimé : le montant payé disparaîtrait du
     * reste dû, et la dette fournisseur remonterait toute seule.
     */
    private function movePayments(Collection $sources, DocumentHeader $grouped): int
    {
        $moved = 0;

        foreach ($sources as $source) {
            foreach ($source->payments as $payment) {
                $payment->forceFill(['document_header_id' => $grouped->id])->saveQuietly();
                $moved++;
            }
        }

        return $moved;
    }

    private function buildNotes(Collection $sources, ?string $supplierRef, bool $fromInvoices): string
    {
        $refs = $fromInvoices
            ? $sources->map(fn ($i) => $i->parent?->reference)->filter()
            : collect();

        $label = 'BR : ';
        if ($refs->isEmpty()) {
            $refs  = $sources->pluck('reference');
            $label = match (true) {
                $fromInvoices                                     => 'Factures : ',
                $sources->first()?->document_type === 'DeliveryNote' => 'BL : ',
                $sources->first()?->document_type === 'TicketSale'   => 'Tickets : ',
                default                                           => 'BR : ',
            };
        }

        $notes = 'Facture groupée — ' . $label . $refs->implode(', ');

        if ($supplierRef) {
            $notes .= ' | Facture fournisseur n° ' . $supplierRef;
        }

        return $notes;
    }

    private function sumFooter(Collection $sources, string $column): float
    {
        return round($sources->sum(fn ($d) => (float) ($d->footer?->{$column} ?? 0)), 2);
    }

    /**
     * L'incrementeur des factures d'achat du tenant, quel que soit le type des
     * documents sources.
     */
    private function invoiceIncrementor(DocumentHeader $first, string $targetType): ?DocumentIncrementor
    {
        return DocumentIncrementor::where('di_model', $targetType)->first()
            ?? $first->incrementor;
    }

    /**
     * Le compteur a deja servi : on repart de sa valeur courante pour ne pas
     * percuter une reference existante.
     */
    private function nextReference(?DocumentIncrementor $incrementor): string
    {
        if (!$incrementor) {
            return 'FA-GROUP-' . now()->format('YmdHis');
        }

        return app(DocumentIncrementorService::class)->consumeNext($incrementor);
    }
}
