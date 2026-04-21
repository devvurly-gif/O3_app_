<?php

namespace App\Console\Commands;

use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\ThirdPartner;
use App\Repositories\Contracts\DocumentIncrementorRepositoryInterface;
use App\Services\DocumentIncrementorService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeneratePeriodicInvoices extends Command
{
    protected $signature = 'billing:generate-periodic-invoices
        {--dry-run : List what would be billed without creating invoices}';

    protected $description = 'Generate grouped invoices for en_compte clients based on their billing frequency';

    public function handle(
        DocumentIncrementorRepositoryInterface $incrementors,
        DocumentIncrementorService $incrementorService,
    ): int {
        $today = Carbon::today();

        // Determine which frequencies should be billed today
        $frequencies = $this->getFrequenciesToBill($today);

        if (empty($frequencies)) {
            $this->info("Pas de facturation périodique prévue aujourd'hui ({$today->format('d/m/Y')}).");
            return self::SUCCESS;
        }

        $this->info('Fréquences à facturer : ' . implode(', ', $frequencies));

        $partners = ThirdPartner::where('type_compte', 'en_compte')
            ->whereIn('frequence_facturation', $frequencies)
            ->get();

        if ($partners->isEmpty()) {
            $this->info('Aucun client en_compte trouvé pour ces fréquences.');
            return self::SUCCESS;
        }

        $isDryRun      = $this->option('dry-run');
        $invoiceCount   = 0;
        $totalAmount    = 0;
        $partnersBilled = 0;

        foreach ($partners as $partner) {
            $bls = $partner->uninvoicedDeliveryNotes()
                ->with(['lignes', 'footer'])
                ->orderBy('issued_at')
                ->get();

            if ($bls->isEmpty()) {
                $this->line("  {$partner->tp_title} : aucun BL non facturé — ignoré.");
                continue;
            }

            $blTotal = $bls->sum(fn ($bl) => (float) ($bl->footer?->total_ttc ?? 0));
            $blRefs  = $bls->pluck('reference')->implode(', ');

            if ($isDryRun) {
                $this->info("  [DRY-RUN] {$partner->tp_title} : {$bls->count()} BL(s) — {$blRefs} — Total : " . number_format($blTotal, 2) . ' DH');
                $partnersBilled++;
                $invoiceCount++;
                $totalAmount += $blTotal;
                continue;
            }

            try {
                DB::transaction(function () use ($partner, $bls, $incrementors, $incrementorService, $blRefs, &$invoiceCount, &$totalAmount) {
                    // Generate invoice reference
                    $reference = $this->generateReference($incrementors, $incrementorService);

                    // Sum up all footer values
                    $totalHt       = $bls->sum(fn ($bl) => (float) ($bl->footer?->total_ht ?? 0));
                    $totalDiscount = $bls->sum(fn ($bl) => (float) ($bl->footer?->total_discount ?? 0));
                    $totalTax      = $bls->sum(fn ($bl) => (float) ($bl->footer?->total_tax ?? 0));
                    $totalTtc      = $bls->sum(fn ($bl) => (float) ($bl->footer?->total_ttc ?? 0));

                    // Create the grouped invoice
                    $facture = DocumentHeader::create([
                        'document_incrementor_id' => $bls->first()->document_incrementor_id,
                        'reference'               => $reference,
                        'document_type'           => 'InvoiceSale',
                        'document_title'          => 'Facture Périodique',
                        'parent_id'               => null,
                        'thirdPartner_id'         => $partner->id,
                        'company_role'            => 'seller',
                        'warehouse_id'            => $bls->first()->warehouse_id,
                        'user_id'                 => 1, // System user
                        'status'                  => 'pending',
                        'issued_at'               => now(),
                        'due_at'                  => now()->addDays(60),
                        'notes'                   => "Facture groupée — BL : {$blRefs}",
                    ]);

                    // Copy all lines from all BLs
                    $sortOrder = 0;
                    foreach ($bls as $bl) {
                        foreach ($bl->lignes as $ligne) {
                            $facture->lignes()->create([
                                'product_id'       => $ligne->product_id,
                                'sort_order'       => $sortOrder++,
                                'line_type'        => $ligne->line_type,
                                'designation'      => $ligne->designation,
                                'reference'        => $ligne->reference,
                                'quantity'         => $ligne->quantity,
                                'unit'             => $ligne->unit,
                                'unit_price'       => $ligne->unit_price,
                                'discount_percent' => $ligne->discount_percent,
                                'tax_percent'      => $ligne->tax_percent,
                            ]);
                        }
                    }

                    // Create invoice footer
                    $facture->footer()->create([
                        'total_ht'       => $totalHt,
                        'total_discount' => $totalDiscount,
                        'total_tax'      => $totalTax,
                        'total_ttc'      => $totalTtc,
                        'amount_paid'    => 0,
                        'amount_due'     => $totalTtc,
                        'payment_method' => 'credit',
                    ]);

                    // Mark all BLs as delivered
                    foreach ($bls as $bl) {
                        $bl->update(['status' => 'delivered']);
                    }

                    // Recalculate encours authoritatively
                    // (BLs are now invoiced → they stop counting, invoice takes over)
                    if ($totalTtc > 0) {
                        $partner->recalculateEncours();
                    }

                    $invoiceCount++;
                    $totalAmount += $totalTtc;
                });

                $partnersBilled++;
                $this->info("  {$partner->tp_title} : facture créée pour {$bls->count()} BL(s) — {$blRefs}");
            } catch (\Throwable $e) {
                $this->error("  {$partner->tp_title} : erreur — {$e->getMessage()}");
                Log::error("Periodic billing failed for partner {$partner->id}: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
            }
        }

        $prefix = $isDryRun ? '[DRY-RUN] ' : '';
        $this->newLine();
        $this->info("{$prefix}Résumé : {$partnersBilled} client(s), {$invoiceCount} facture(s), total " . number_format($totalAmount, 2) . ' DH');

        return self::SUCCESS;
    }

    /**
     * Determine which billing frequencies should be processed today.
     */
    private function getFrequenciesToBill(Carbon $today): array
    {
        // Only bill on the 1st of the month
        if ($today->day !== 1) {
            return [];
        }

        $frequencies = ['mensuelle'];

        $month = $today->month;

        // Quarterly: Jan(1), Apr(4), Jul(7), Oct(10)
        if (in_array($month, [1, 4, 7, 10])) {
            $frequencies[] = 'trimestrielle';
        }

        // Semi-annual: Jan(1), Jul(7)
        if (in_array($month, [1, 7])) {
            $frequencies[] = 'semestrielle';
        }

        return $frequencies;
    }

    /**
     * Generate a reference for an InvoiceSale using the incrementor system.
     */
    private function generateReference(
        DocumentIncrementorRepositoryInterface $incrementors,
        DocumentIncrementorService $incrementorService,
    ): string {
        $incrementor = $incrementors
            ->all(orderBy: 'di_title')
            ->firstWhere('di_model', 'InvoiceSale');

        if (!$incrementor) {
            return sprintf('FAC-%d-%04d', now()->year, rand(1, 9999));
        }

        $reference = $incrementorService->formatReference(
            $incrementor->template,
            $incrementor->nextTrick
        );

        $incrementor->increment('nextTrick');

        return $reference;
    }
}
