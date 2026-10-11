<?php

namespace App\Services\Ventes;

use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\Warehouse;
use App\Services\DocumentHeaderService;
use App\Services\StockMouvementService;
use Illuminate\Support\Facades\DB;

/**
 * Facture de vente déjà ÉMISE par l'entreprise (PDF déposé dans l'orchestrateur) qui manque dans O3 :
 * on la recopie telle qu'imprimée (numéro, date, prix, TVA), en BROUILLON « à confirmer ».
 *
 * Garde-fous, dans l'esprit de WhatsAppOrderImportService :
 *   - le client est identifié par l'appelant ; jamais créé ici ;
 *   - le numéro imprimé ne doit pas déjà exister dans O3 (jamais de doublon) ;
 *   - les prix et taux sont ceux du document, jamais ceux du tarif O3 ; un prix ou un taux illisible bloque ;
 *   - les totaux recalculés doivent tomber sur les totaux imprimés ;
 *   - un article sans fiche produit reste une ligne libre (pas de fiche créée depuis un PDF) ;
 *   - rien n'est confirmé : le mouvement de stock reste « en attente ».
 */
class IssuedInvoiceImportService
{
    private const DOC_TYPE = 'InvoiceSale';
    private const DOC_TITLE = 'Facture Vente';
    private const TOLERANCE = 0.05;

    private array $errors = [];
    private array $warnings = [];

    public function __construct(
        private DocumentHeaderService $documentService,
        private StockMouvementService $stockService,
    ) {
    }

    /**
     * @param array{external_id: string, reference: string, date: string, due_date?: ?string, prices_include_vat?: bool,
     *              totals?: array, lines: array<int, array>, notes?: ?string} $payload
     * @return array{status: string, errors: array, warnings: array, preview?: array, document?: array}
     */
    public function handle(array $payload, ThirdPartner $customer, bool $dryRun, int $userId): array
    {
        $this->errors = $this->warnings = [];
        $marker = "[Import orchestrateur {$payload['external_id']}]";

        $already = DocumentHeader::where('notes', 'like', '%' . $marker . '%')->first();
        if ($already) {
            return $this->respond('already_imported', ['document' => $this->summary($already)]);
        }
        if (DocumentHeader::where('reference', $payload['reference'])->exists()) {
            $this->block('REFERENCE_EXISTS', 'reference', "La référence {$payload['reference']} existe déjà dans O3 : je ne crée pas de doublon.");
        }

        $lines = $this->resolveLines($payload);
        $totals = $this->totals($lines);
        $this->checkTotals($payload['totals'] ?? [], $totals);
        $warehouse = $this->warehouse();
        $incrementor = DocumentIncrementor::where('di_model', self::DOC_TYPE)->first();
        $incrementor || $this->block('INCREMENTOR_NOT_FOUND', 'type', 'Aucun compteur de numérotation O3 configuré pour InvoiceSale.');

        $preview = ['customer' => "{$customer->tp_title} ({$customer->tp_code})", 'totals' => $totals, 'lines' => count($lines)];
        if ($this->errors) {
            return $this->respond('rejected', ['preview' => $preview]);
        }
        if ($dryRun) {
            return $this->respond('valid', ['preview' => $preview]);
        }

        $document = DB::transaction(fn () => $this->create($payload, $customer, $warehouse, $incrementor, $lines, $totals, $marker, $userId));

        return $this->respond('created', ['preview' => $preview, 'document' => $this->summary($document)]);
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveLines(array $p): array
    {
        $out = [];
        foreach ($p['lines'] as $i => $l) {
            $rate = $l['vat_rate'] ?? null;
            $price = $l['unit_price'] ?? null;
            if ($rate === null || $price === null) {
                $this->block('LINE_UNREADABLE', "lines.{$i}", 'Ligne ' . ($i + 1) . " (« {$l['designation']} ») : " . ($price === null ? 'prix' : 'taux de TVA') . ' illisible, je ne le devine pas.');
                continue;
            }
            $ht = ($p['prices_include_vat'] ?? false) ? (float) $price / (1 + $rate / 100) : (float) $price;
            $product = $this->product($l['sku'] ?? null, $l['designation']);
            $product || $this->warnings[] = ['level' => 'INFO', 'code' => 'FREE_LINE', 'field' => "lines.{$i}",
                'message' => "« {$l['designation']} » n'a pas de fiche produit dans O3 : ligne libre, sans mouvement de stock."];

            $out[] = [
                'product_id' => $product?->id, 'sku' => $product?->p_sku ?? ($l['sku'] ?? null), 'designation' => $l['designation'],
                'qty' => (float) $l['quantity'], 'unit' => $l['unit'] ?? 'pièce', 'unit_price' => round($ht, 4), 'vat_rate' => (float) $rate,
            ];
        }

        return $out;
    }

    /** Référence exacte, sinon intitulé exact : un seul candidat, sinon pas de rapprochement. */
    private function product(?string $sku, string $designation): ?Product
    {
        if ($sku) {
            $found = Product::where('p_sku', $sku)->get(['id', 'p_sku']);
            if ($found->count() === 1) {
                return $found->first();
            }
        }
        $found = Product::whereRaw('LOWER(p_title) = ?', [mb_strtolower(trim($designation))])->get(['id', 'p_sku']);

        return $found->count() === 1 ? $found->first() : null;
    }

    private function totals(array $lines): array
    {
        $ht = round(array_sum(array_map(fn ($l) => $l['qty'] * $l['unit_price'], $lines)), 2);
        $tva = round(array_sum(array_map(fn ($l) => $l['qty'] * $l['unit_price'] * $l['vat_rate'] / 100, $lines)), 2);

        return ['ht' => $ht, 'tva' => $tva, 'ttc' => round($ht + $tva, 2)];
    }

    private function checkTotals(array $printed, array $computed): void
    {
        foreach (['ht' => 'HT', 'tva' => 'TVA', 'ttc' => 'TTC'] as $key => $label) {
            if (isset($printed[$key]) && abs($printed[$key] - $computed[$key]) > self::TOLERANCE) {
                $this->block('TOTAL_MISMATCH', "totals.{$key}", "Total {$label} imprimé {$printed[$key]} ≠ recalculé {$computed[$key]} : je ne crée pas un document dont les montants ne tombent pas juste.");
            }
        }
    }

    private function warehouse(): ?Warehouse
    {
        $active = Warehouse::where('wh_status', true)->get();
        $active->count() === 1 || $this->block('WAREHOUSE', 'warehouse', $active->isEmpty() ? 'Aucun entrepôt actif configuré dans O3.' : 'Plusieurs entrepôts actifs : impossible de choisir automatiquement.');

        return $active->count() === 1 ? $active->first() : null;
    }

    private function create(array $p, ThirdPartner $customer, Warehouse $warehouse, DocumentIncrementor $incrementor, array $lines, array $totals, string $marker, int $userId): DocumentHeader
    {
        $document = $this->documentService->createWithLinesAndFooter([
            'document_incrementor_id' => $incrementor->id,
            'document_type'           => self::DOC_TYPE,
            'document_title'          => self::DOC_TITLE,
            'reference'               => $p['reference'],          // numéro déjà émis : on le garde tel quel
            'thirdPartner_id'         => $customer->id,
            'company_role'            => 'customer',
            'warehouse_id'            => $warehouse->id,
            'issued_at'               => $p['date'],
            'due_at'                  => $p['due_date'] ?? null,
            'notes'                   => trim("Facture déjà émise, saisie depuis le PDF déposé dans l'orchestrateur : à vérifier puis confirmer.\n" . trim($p['notes'] ?? '') . "\n{$marker}"),
            'user_id'                 => $userId,
        ], array_map(fn ($l) => [
            'product_id' => $l['product_id'], 'designation' => $l['designation'], 'reference' => $l['sku'], 'quantity' => $l['qty'],
            'unit' => $l['unit'], 'unit_price' => $l['unit_price'], 'discount_percent' => 0, 'tax_percent' => $l['vat_rate'],
        ], $lines), [
            'total_ht' => $totals['ht'], 'total_discount' => 0, 'total_tax' => $totals['tva'], 'total_ttc' => $totals['ttc'],
            'amount_paid' => 0, 'amount_due' => $totals['ttc'],
        ]);

        // Brouillon : seul un mouvement « en attente » est créé (les lignes sans produit sont ignorées) ;
        // la sortie de stock réelle se décide à la confirmation, dans O3.
        $this->stockService->processDocument($document, pending: true);

        return $document->fresh();
    }

    private function summary(DocumentHeader $d): array
    {
        return ['id' => $d->id, 'reference' => $d->reference, 'status' => $d->status];
    }

    private function respond(string $status, array $extra = []): array
    {
        return array_merge(['status' => $status, 'errors' => $this->errors, 'warnings' => $this->warnings], $extra);
    }

    private function block(string $code, string $field, string $message): void
    {
        $this->errors[] = ['level' => 'BLOQUANT', 'code' => $code, 'field' => $field, 'message' => $message];
    }
}
