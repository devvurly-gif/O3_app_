<?php

namespace App\Services\Purchases;

use App\Models\DocumentHeader;
use App\Models\Product;
use Carbon\Carbon;

/**
 * Contrôle en LECTURE SEULE des documents d'achat présents dans O3 (bons de réception et
 * factures d'achat), à partir de la liste des documents — sans registre Excel.
 *
 * Même grille que les contrôles de l'import (CLAUDE.md §3) appliquée a posteriori, plus
 * ce que seul O3 sait : cohérence avec les prix d'achat TTC des produits, mouvements de
 * stock, doublons de n° fournisseur. Ne modifie rien.
 *
 * Format des anomalies : ['level' => BLOQUANT|ATTENTION|INFO, 'code' => ..., 'message' => ...].
 */
class PurchaseAuditService
{
    public const TYPES = ['ReceiptNotePurchase', 'InvoicePurchase'];

    private const TOLERANCE = 0.01;
    private const VAT_RATES = [0.0, 7.0, 10.0, 14.0, 20.0];
    private const MAX_PAYMENT_DAYS = 120;
    private const PRICE_GAP_RATIO = 0.01;

    /**
     * @param array{from?: ?string, to?: ?string, type?: ?string, limit?: int, only_issues?: bool} $filters
     */
    public function audit(array $filters = []): array
    {
        $q = DocumentHeader::query()
            ->with(['lignes', 'footer', 'thirdPartner'])
            ->withCount('stockMouvements')
            ->whereIn('document_type', $filters['type'] ?? null ? [$filters['type']] : self::TYPES)
            ->orderByDesc('issued_at')->orderByDesc('id');

        if (!empty($filters['from'])) {
            $q->whereDate('issued_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $q->whereDate('issued_at', '<=', $filters['to']);
        }
        $docs = $q->limit(min((int) ($filters['limit'] ?? 200), 500))->get();

        $products = Product::query()
            ->select(['id', 'p_sku', 'p_status', 'p_salePrice', 'p_purchasePrice'])
            ->whereIn('id', $docs->flatMap->lignes->pluck('product_id')->filter()->unique())
            ->get()->keyBy('id');

        // Doublon : même fournisseur + même n° de pièce fournisseur (trace dans notes).
        $refCount = [];
        $refs = [];
        foreach ($docs as $d) {
            $refs[$d->id] = $this->supplierRef($d->notes);
            if ($refs[$d->id]) {
                $key = $d->thirdPartner_id . '|' . mb_strtolower($refs[$d->id]);
                $refCount[$key] = ($refCount[$key] ?? 0) + 1;
            }
        }

        $report = [];
        foreach ($docs as $d) {
            $issues = $this->checkDocument($d, $products, $refs[$d->id], $refCount);
            if (!empty($filters['only_issues']) && !collect($issues)->whereIn('level', ['BLOQUANT', 'ATTENTION'])->count()) {
                continue;
            }
            $report[] = [
                'id'                 => $d->id,
                'reference'          => $d->reference,
                'type'               => $d->document_type,
                'status'             => $d->status,
                'date'               => optional($d->issued_at)->toDateString(),
                'supplier'           => $d->thirdPartner?->tp_title,
                'supplier_ice'       => $d->thirdPartner?->tp_Ice_Number,
                'supplier_reference' => $refs[$d->id],
                'external_id'        => $this->externalId($d->notes),
                'source'             => $this->externalId($d->notes) ? 'import_api' : 'saisie_manuelle',
                'total_ht'           => $d->footer ? (float) $d->footer->total_ht : null,
                'total_tva'          => $d->footer ? (float) $d->footer->total_tax : null,
                'total_ttc'          => $d->footer ? (float) $d->footer->total_ttc : null,
                'lines'              => $d->lignes->count(),
                'issues'             => $issues,
            ];
        }

        $levels = collect($report)->flatMap(fn ($r) => $r['issues'])->countBy('level');

        return [
            'generated_at' => now()->toIso8601String(),
            'summary'      => [
                'documents' => count($report),
                'bloquant'  => $levels->get('BLOQUANT', 0),
                'attention' => $levels->get('ATTENTION', 0),
                'info'      => $levels->get('INFO', 0),
            ],
            'documents'    => $report,
        ];
    }

    private function checkDocument(DocumentHeader $d, $products, ?string $supplierRef, array $refCount): array
    {
        $out = [];
        $add = function (string $level, string $code, string $msg) use (&$out) {
            $out[] = ['level' => $level, 'code' => $code, 'message' => $msg];
        };

        // Fournisseur
        $s = $d->thirdPartner;
        if (!$s) {
            $add('BLOQUANT', 'SUPPLIER_MISSING', 'Aucun fournisseur rattaché.');
        } elseif (!preg_match('/^\d{15}$/', (string) $s->tp_Ice_Number)) {
            $add('ATTENTION', 'SUPPLIER_ICE_INVALID', "ICE du fournisseur « {$s->tp_title} » absent ou invalide (15 chiffres attendus).");
        }

        // Pièce fournisseur / doublon
        if (!$supplierRef) {
            $add('ATTENTION', 'SUPPLIER_REF_MISSING', 'N° de pièce fournisseur introuvable dans les notes.');
        } elseif (($refCount[$d->thirdPartner_id . '|' . mb_strtolower($supplierRef)] ?? 0) > 1) {
            $add('BLOQUANT', 'DUPLICATE', "N° fournisseur « {$supplierRef} » présent sur plusieurs documents du même fournisseur.");
        }

        // Lignes et totaux
        if ($d->lignes->isEmpty()) {
            $add('BLOQUANT', 'NO_LINES', 'Document sans ligne.');
        }
        $sumHt = round((float) $d->lignes->sum('total_ligne_ht'), 2);
        $sumTax = round((float) $d->lignes->sum('total_tax'), 2);
        $f = $d->footer;
        if (!$f) {
            $add('BLOQUANT', 'NO_FOOTER', 'Pied de document absent (totaux).');
        } else {
            if (abs($sumHt - (float) $f->total_ht) > self::TOLERANCE) {
                $add('BLOQUANT', 'TOTAL_HT_MISMATCH', sprintf('Σ lignes HT %.2f ≠ total HT %.2f.', $sumHt, $f->total_ht));
            }
            $stamp = $this->stamp($d->notes);
            $gap = round((float) $f->total_ttc - ((float) $f->total_ht + (float) $f->total_tax), 2);
            if (abs($gap - $stamp) > self::TOLERANCE) {
                $add('BLOQUANT', 'TOTAL_TTC_MISMATCH', sprintf('HT %.2f + TVA %.2f + timbre %.2f ≠ TTC %.2f.', $f->total_ht, $f->total_tax, $stamp, $f->total_ttc));
            }
            if (abs($sumTax - (float) $f->total_tax) > self::TOLERANCE) {
                $add('ATTENTION', 'TOTAL_TVA_MISMATCH', sprintf('Σ TVA des lignes %.2f ≠ TVA du pied %.2f.', $sumTax, $f->total_tax));
            }
        }

        // Date et échéance
        if ($d->due_at) {
            $days = Carbon::parse($d->issued_at)->diffInDays($d->due_at, false);
            if ($days > self::MAX_PAYMENT_DAYS) {
                $add('ATTENTION', 'DUE_DATE_TOO_LATE', "Échéance à {$days} jours (> " . self::MAX_PAYMENT_DAYS . ', loi 69-21).');
            }
        } else {
            $add('INFO', 'DUE_DATE_MISSING', 'Échéance non renseignée.');
        }

        // Lignes une à une
        foreach ($d->lignes as $i => $l) {
            $n = $i + 1;
            $expected = round((float) $l->quantity * (float) $l->unit_price * (1 - (float) $l->discount_percent / 100), 2);
            if (abs($expected - (float) $l->total_ligne_ht) > self::TOLERANCE) {
                $add('BLOQUANT', 'LINE_TOTAL_MISMATCH', sprintf('Ligne %d (%s) : qté × PU − remise = %.2f ≠ total HT %.2f.', $n, $l->reference, $expected, $l->total_ligne_ht));
            }
            if (!in_array((float) $l->tax_percent, self::VAT_RATES, true)) {
                $add('BLOQUANT', 'VAT_RATE_INVALID', "Ligne {$n} ({$l->reference}) : taux de TVA {$l->tax_percent} % hors {0, 7, 10, 14, 20}.");
            }
            $p = $l->product_id ? $products->get($l->product_id) : null;
            if (!$p) {
                $add('ATTENTION', 'LINE_NO_PRODUCT', "Ligne {$n} ({$l->reference}) : produit introuvable dans le catalogue.");
                continue;
            }
            if (!$p->p_status) {
                $add('INFO', 'PRODUCT_INACTIVE', "{$p->p_sku} : produit inactif, fiche à compléter.");
            }
            if ((float) $p->p_salePrice <= 0) {
                $add('ATTENTION', 'PRODUCT_NO_SALE_PRICE', "{$p->p_sku} : prix de vente à 0.");
            }
            // Prix d'achat O3 = TTC : comparé au TTC unitaire net de la ligne.
            $ttcUnit = (float) $l->unit_price * (1 - (float) $l->discount_percent / 100) * (1 + (float) $l->tax_percent / 100);
            $po3 = (float) $p->p_purchasePrice;
            if ($po3 > 0 && $ttcUnit > 0 && abs($ttcUnit - $po3) / $po3 > self::PRICE_GAP_RATIO) {
                $add('ATTENTION', 'PURCHASE_PRICE_GAP', sprintf('%s : prix d\'achat TTC du document %.2f ≠ prix O3 %.2f.', $p->p_sku, $ttcUnit, $po3));
            } elseif ($po3 <= 0) {
                $add('ATTENTION', 'PRODUCT_NO_PURCHASE_PRICE', "{$p->p_sku} : prix d'achat O3 à 0.");
            }
        }

        // Stock
        if ($d->status === 'confirmed' && $d->stock_mouvements_count === 0) {
            $add('ATTENTION', 'NO_STOCK_MOVEMENT', 'Document confirmé sans mouvement de stock.');
        }
        if ($d->status === 'draft') {
            $add('INFO', 'DRAFT', 'Document encore à l\'état de brouillon.');
        }

        return $out;
    }

    private function supplierRef(?string $notes): ?string
    {
        return preg_match('/Réf\. document fournisseur : ([^\n—]+?)\s*(?:—|\n|$)/u', (string) $notes, $m) ? trim($m[1]) : null;
    }

    private function externalId(?string $notes): ?string
    {
        return preg_match('/\[Import API ([A-Za-z0-9\-_]+)\]/', (string) $notes, $m) ? $m[1] : null;
    }

    private function stamp(?string $notes): float
    {
        return preg_match('/Timbre fiscal inclus dans le TTC : ([\d.]+) MAD/', (string) $notes, $m) ? (float) $m[1] : 0.0;
    }
}
