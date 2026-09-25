<?php

namespace App\Services\Ventes;

use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\Warehouse;
use App\Models\WhatsAppOrderImport;
use App\Services\DocumentHeaderService;
use App\Services\PriceResolver;
use App\Services\StockMouvementService;
use Illuminate\Support\Facades\DB;

/**
 * Import d'une commande WhatsApp envoyée par l'agent de facturation client
 * Jadema (voir « facturation clients\CLAUDE.md »). Miroir, côté ventes, de
 * PurchaseImportService : mêmes principes (garde-fous anti-hallucination,
 * dry_run/preview, idempotence par external_id), mais le document créé
 * reste en brouillon avec un mouvement de stock seulement « pending » —
 * jamais confirmé automatiquement (contrairement aux achats, où
 * l'approbation humaine est déjà passée par le -Commit du script). C'est un
 * humain qui doit encore relire et confirmer le BL dans O3 avant que le
 * stock ne soit réellement déduit.
 *
 * Garde-fous anti-hallucination, plus stricts que côté achats :
 *   - Client introuvable ou ambigu (téléphone puis raison sociale exacte)
 *     → BLOQUANT, **jamais de création automatique** (contrairement aux
 *     fournisseurs) : un mauvais rapprochement impacte l'encours/crédit.
 *   - Produit introuvable ou ambigu (recherche texte libre sur
 *     titre/code/sku/ean13/description) → BLOQUANT, **jamais de création
 *     automatique** (contrairement aux achats) : le texte d'un client n'est
 *     pas une source fiable pour créer une fiche produit.
 */
class WhatsAppOrderImportService
{
    private const DOC_TYPE = 'DeliveryNote';
    private const DOC_TITLE = 'Bon de Livraison';

    private array $errors = [];
    private array $warnings = [];

    public function __construct(
        private DocumentHeaderService $documentService,
        private StockMouvementService $stockService,
        private PriceResolver $priceResolver,
    ) {
    }

    public function handle(array $payload, bool $dryRun, int $userId): array
    {
        $this->errors = $this->warnings = [];
        $externalId = $payload['external_id'];
        $hash = hash('sha256', json_encode(collect($payload)->except('dry_run')->all()));

        // 0. Idempotence : déjà importé ?
        $existing = WhatsAppOrderImport::where('external_id', $externalId)->where('status', 'created')->first();
        if ($existing) {
            if ($existing->payload_hash !== $hash) {
                $this->block('ALREADY_IMPORTED_DIFFERENT', 'external_id',
                    "{$externalId} a déjà été importé avec un contenu différent (document {$existing->document_reference}). Corriger dans O3, ne pas réimporter.");
                return $this->respond('rejected', $payload, $dryRun);
            }
            return array_merge($existing->response, ['status' => 'already_imported']);
        }

        $customer     = $this->resolveCustomer($payload['customer']);
        $lines        = $this->resolveLines($payload['lines'], $customer);
        $warehouse    = $this->resolveWarehouse();
        $incrementor  = $this->resolveIncrementor();
        $computed     = $this->computeTotals($lines);

        $preview = [
            'customer'  => $customer ? $this->customerSummary($customer) : $payload['customer'],
            'warehouse' => $warehouse?->wh_title,
            'totals'    => $computed,
            'lines'     => array_map(fn ($l) => collect($l)->except('product')->all(), $lines),
        ];

        if ($this->errors) {
            return $this->log($userId, $payload, $hash, $dryRun,
                $this->respond('rejected', $payload, $dryRun, ['preview' => $preview]));
        }
        if ($dryRun) {
            return $this->respond('valid', $payload, $dryRun, ['preview' => $preview]);
        }

        // Écriture : brouillon uniquement, stock en pending — la confirmation
        // (déduction réelle du stock) reste un geste manuel dans O3.
        $document = DB::transaction(function () use ($payload, $customer, $warehouse, $incrementor, $lines, $computed, $userId) {
            return $this->createDeliveryNote($payload, $customer, $warehouse, $incrementor, $lines, $computed, $userId);
        });

        return $this->log($userId, $payload, $hash, false, $this->respond('created', $payload, false, [
            'preview'  => $preview,
            'document' => [
                'id'        => $document->id,
                'reference' => $document->reference,
                'url'       => url("/ventes/documents/{$document->id}"),
            ],
        ]), $document);
    }

    // ───────────────────────────── 1. Client ─────────────────────────────

    private function resolveCustomer(array $c): ?ThirdPartner
    {
        $q = fn () => ThirdPartner::query()->whereIn('tp_Role', ['customer', 'both']);

        // Priorité : téléphone (le numéro WhatsApp de l'expéditeur) > raison sociale exacte.
        if (!empty($c['phone'])) {
            $found = $q()->where('tp_phone', $c['phone'])->get();
            if ($found->count() === 1) {
                return $found->first();
            }
            if ($found->count() > 1) {
                $this->block('CUSTOMER_AMBIGUOUS', 'customer.phone', "Plusieurs clients O3 partagent le téléphone « {$c['phone']} ». Préciser lequel.");
                return null;
            }
        }

        if (!empty($c['name'])) {
            $found = $q()->where('tp_title', $c['name'])->get();
            if ($found->count() === 1) {
                return $found->first();
            }
            if ($found->count() > 1) {
                $this->block('CUSTOMER_AMBIGUOUS', 'customer.name', "Plusieurs clients O3 portent le nom « {$c['name']} ». Préciser (téléphone) lequel.");
                return null;
            }
        }

        $this->block('CUSTOMER_NOT_FOUND', 'customer',
            "Aucun client O3 trouvé pour téléphone « " . ($c['phone'] ?? '—') . " » / nom « " . ($c['name'] ?? '—') . " ». Vérifier la fiche client dans O3 — jamais de création automatique depuis un message WhatsApp.");
        return null;
    }

    private function customerSummary(ThirdPartner $c): array
    {
        return ['id' => $c->id, 'name' => $c->tp_title, 'phone' => $c->tp_phone];
    }

    // ───────────────────────────── 2. Lignes / produits ─────────────────────────────

    private function resolveLines(array $lines, ?ThirdPartner $customer): array
    {
        $out = [];

        foreach ($lines as $i => $l) {
            $query = trim($l['query']);
            $qty = (float) $l['quantity'];
            $product = null;

            $candidates = Product::query()
                ->where(function ($q) use ($query) {
                    foreach (['p_title', 'p_code', 'p_sku', 'p_ean13', 'p_description'] as $col) {
                        $q->orWhere($col, 'like', '%' . $query . '%');
                    }
                })
                ->limit(10)
                ->get(['id', 'p_title', 'p_sku']);

            if ($candidates->count() === 1) {
                $product = $candidates->first();
                $this->info('PRODUCT_MATCHED', "lines.{$i}.query", "« {$query} » rapproché de {$product->p_sku} — {$product->p_title}.");
            } elseif ($candidates->count() > 1) {
                $list = $candidates->map(fn ($p) => "{$p->p_sku} ({$p->p_title})")->implode(', ');
                $this->block('PRODUCT_AMBIGUOUS', "lines.{$i}.query", "« {$query} » correspond à plusieurs produits O3 : {$list}. Préciser lequel — jamais de choix au hasard.");
            } else {
                $this->block('PRODUCT_NOT_FOUND', "lines.{$i}.query", "« {$query} » ne correspond à aucun produit O3. Vérifier l'orthographe ou créer la fiche produit dans O3 avant de réessayer — jamais de création automatique depuis un texte client.");
            }

            $unitPrice = 0.0;
            $vat = 0.0;
            if ($product && $customer) {
                $fullProduct = Product::find($product->id);
                $resolved = $this->priceResolver->resolve(
                    product: $fullProduct,
                    customer: $customer,
                    quantity: (int) $qty,
                    channel: 'all',
                );
                $unitPrice = (float) $resolved['price_ht'];
                $vat = (float) ($fullProduct->p_taxRate ?? 0);
            }

            $ht = round($qty * $unitPrice, 2);
            $tva = round($ht * $vat / 100, 2);

            $out[] = [
                'index'       => $i,
                'product'     => $product,
                'product_id'  => $product?->id,
                'sku'         => $product?->p_sku,
                'query'       => $query,
                'designation' => $product?->p_title ?? $query,
                'qty'         => $qty,
                'unit'        => $l['unit'] ?? null,
                'unit_price_ht' => $unitPrice,
                'vat_rate'    => $vat,
                'total_ht'    => $ht,
                'total_tva'   => $tva,
            ];
        }

        return $out;
    }

    // ───────────────────────────── 3. Entrepôt / numérotation ─────────────────────────────

    private function resolveWarehouse(): ?Warehouse
    {
        $actifs = Warehouse::where('wh_status', true)->get();
        if ($actifs->count() === 1) {
            return $actifs->first();
        }
        if ($actifs->count() > 1) {
            $this->block('WAREHOUSE_AMBIGUOUS', 'warehouse', 'Plusieurs entrepôts actifs existent dans O3 : impossible de choisir automatiquement.');
        } else {
            $this->block('WAREHOUSE_NOT_FOUND', 'warehouse', 'Aucun entrepôt actif configuré dans O3.');
        }
        return null;
    }

    private function resolveIncrementor(): ?DocumentIncrementor
    {
        $incrementor = DocumentIncrementor::where('di_model', self::DOC_TYPE)->first();
        if (!$incrementor) {
            $this->block('INCREMENTOR_NOT_FOUND', 'type', 'Aucun compteur de numérotation O3 (DocumentIncrementor) configuré pour DeliveryNote.');
        }
        return $incrementor;
    }

    private function computeTotals(array $lines): array
    {
        $ht = round(array_sum(array_column($lines, 'total_ht')), 2);
        $tva = round(array_sum(array_column($lines, 'total_tva')), 2);
        return ['ht' => $ht, 'tva' => $tva, 'ttc' => round($ht + $tva, 2)];
    }

    // ───────────────────────────── Écriture ─────────────────────────────

    private function createDeliveryNote(array $p, ThirdPartner $customer, Warehouse $warehouse, DocumentIncrementor $incrementor, array $lines, array $totals, int $userId): DocumentHeader
    {
        $notes = trim(sprintf(
            "Commande WhatsApp.\n%s\n[Import API %s]",
            trim($p['notes'] ?? ($p['source_text'] ?? '')),
            $p['external_id']
        ));

        $headerData = [
            'document_incrementor_id' => $incrementor->id,
            'document_type'           => self::DOC_TYPE,
            'document_title'          => self::DOC_TITLE,
            'thirdPartner_id'         => $customer->id,
            'company_role'            => 'customer',
            'warehouse_id'            => $warehouse->id,
            'issued_at'               => now(),
            'notes'                   => $notes,
            'user_id'                 => $userId,
        ];

        $linesData = array_map(fn ($l) => [
            'product_id'       => $l['product_id'],
            'designation'      => $l['designation'],
            'reference'        => $l['sku'],
            'quantity'         => $l['qty'],
            'unit'             => $l['unit'] ?? 'pièce',
            'unit_price'       => $l['unit_price_ht'],
            'discount_percent' => 0,
            'tax_percent'      => $l['vat_rate'],
        ], $lines);

        $footerData = [
            'total_ht'       => $totals['ht'],
            'total_discount' => 0,
            'total_tax'      => $totals['tva'],
            'total_ttc'      => $totals['ttc'],
            'amount_paid'    => 0,
            'amount_due'     => $totals['ttc'],
        ];

        $document = $this->documentService->createWithLinesAndFooter($headerData, $linesData, $footerData);

        // Contrairement aux achats (confirmé immédiatement), le BL reste en
        // brouillon : seul un mouvement de stock "pending" est créé, comme
        // pour une commande eCom (EcomOrderController::store()). La
        // déduction réelle attend la confirmation manuelle dans O3.
        $this->stockService->processDocument($document, pending: true);

        return $document->fresh();
    }

    // ───────────────────────────── Réponse / journal ─────────────────────────────

    private function respond(string $status, array $p, bool $dryRun, array $extra = []): array
    {
        return array_merge([
            'status'      => $status,
            'external_id' => $p['external_id'],
            'dry_run'     => $dryRun,
            'errors'      => $this->errors,
            'warnings'    => $this->warnings,
        ], $extra);
    }

    private function log(int $userId, array $p, string $hash, bool $dryRun, array $response, ?DocumentHeader $doc = null): array
    {
        if ($dryRun) {
            return $response;
        }
        WhatsAppOrderImport::updateOrCreate(
            ['external_id' => $p['external_id']],
            [
                'status'             => $response['status'],
                'payload_hash'       => $hash,
                'payload'            => $p,
                'response'           => $response,
                'document_id'        => $doc?->id,
                'document_reference' => $doc?->reference,
                'user_id'            => $userId,
            ]
        );
        return $response;
    }

    private function block(string $code, string $field, string $message): void
    {
        $this->errors[] = ['level' => 'BLOQUANT', 'code' => $code, 'field' => $field, 'message' => $message];
    }

    private function warn(string $code, string $field, string $message): void
    {
        $this->warnings[] = ['level' => 'ATTENTION', 'code' => $code, 'field' => $field, 'message' => $message];
    }

    private function info(string $code, string $field, string $message): void
    {
        $this->warnings[] = ['level' => 'INFO', 'code' => $code, 'field' => $field, 'message' => $message];
    }
}
