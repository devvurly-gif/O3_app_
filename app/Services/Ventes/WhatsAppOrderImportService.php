<?php

namespace App\Services\Ventes;

use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\Warehouse;
use App\Models\WhatsAppOrderImport;
use App\Services\DocumentHeaderService;
use App\Services\PriceResolver;
use App\Services\StockMouvementService;
use Illuminate\Support\Carbon;
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

    /** Mots ignorés quand on cherche un produit mot par mot. */
    private const STOPWORDS = ['de', 'du', 'des', 'la', 'le', 'les', 'et', 'en', 'au', 'aux', 'un', 'une', 'pour', 'avec', 'sur'];

    private string $origin = 'Commande WhatsApp';

    public function __construct(
        private DocumentHeaderService $documentService,
        private StockMouvementService $stockService,
        private PriceResolver $priceResolver,
        private CustomerLookup $customers,
    ) {
    }

    /**
     * @param ThirdPartner|null $knownCustomer client déjà identifié de façon sûre par
     *        l'appelant (chat client vérifié par code, client choisi par un employé) :
     *        la recherche par téléphone/code/nom est alors sautée. Usage interne
     *        uniquement — l'API HTTP ne le transmet jamais.
     * @param string $origin libellé de provenance écrit dans les notes du BL.
     */
    public function handle(array $payload, bool $dryRun, int $userId, ?ThirdPartner $knownCustomer = null, string $origin = 'Commande WhatsApp'): array
    {
        $this->errors = $this->warnings = [];
        $this->origin = $origin;
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

        $customer     = $knownCustomer ?? $this->resolveCustomer($payload['customer'] ?? []);
        $lines        = $this->resolveLines($payload['lines'], $customer);
        $warehouse    = $this->resolveWarehouse();
        $incrementor  = $this->resolveIncrementor();
        $computed     = $this->computeTotals($lines);

        $preview = [
            'customer'  => $customer ? $this->customerSummary($customer) : ($payload['customer'] ?? []),
            'warehouse' => $warehouse?->wh_title,
            'totals'    => $computed,
            'lines'     => array_map(fn ($l) => collect($l)->except('product')->all(), $lines),
        ];

        if ($this->errors) {
            return $this->log($userId, $payload, $hash, $dryRun,
                $this->respond('rejected', $payload, $dryRun, ['preview' => $preview]));
        }
        if ($dryRun) {
            // Indique le BL du jour qui recevrait cette commande (un BL par client et par jour).
            if ($target = $this->findTodayDraft($customer)) {
                $preview['target_document'] = ['id' => $target->id, 'reference' => $target->reference];
            }
            return $this->respond('valid', $payload, $dryRun, ['preview' => $preview]);
        }

        // Écriture : brouillon uniquement, stock en pending — la confirmation
        // (déduction réelle du stock) reste un geste manuel dans O3.
        // Un BL par client et par jour : s'il existe déjà un BL brouillon du
        // jour issu de la messagerie, la commande s'y ajoute.
        [$document, $appended] = DB::transaction(function () use ($payload, $customer, $warehouse, $incrementor, $lines, $computed, $userId) {
            if ($target = $this->findTodayDraft($customer, lock: true)) {
                $this->appendToDeliveryNote($target, $payload, $lines, $customer);
                return [$target->fresh(), true];
            }
            return [$this->createDeliveryNote($payload, $customer, $warehouse, $incrementor, $lines, $computed, $userId), false];
        });

        return $this->log($userId, $payload, $hash, false, $this->respond('created', $payload, false, [
            'preview'  => $preview,
            'document' => $this->documentSummary($document, $appended),
        ]), $document);
    }

    // ───────────────────────────── Un BL par client et par jour ─────────────────────────────

    /**
     * BL brouillon du jour de ce client, créé par la messagerie ou l'agent de
     * facturation client (présent dans whatsapp_order_imports) — jamais un BL
     * saisi à la main dans O3. « Aujourd'hui » s'entend dans le fuseau du
     * tenant (réglage locale.timezone), l'application stockant en UTC.
     */
    private function findTodayDraft(ThirdPartner $customer, bool $lock = false): ?DocumentHeader
    {
        $tz = Setting::get('locale', 'timezone') ?: config('app.timezone');
        try {
            $start = Carbon::now($tz)->startOfDay();
        } catch (\Throwable) {
            $start = Carbon::now()->startOfDay();
        }
        $from = $start->copy()->setTimezone(config('app.timezone'));
        $to = $start->copy()->addDay()->setTimezone(config('app.timezone'));

        $query = DocumentHeader::query()
            ->where('document_type', self::DOC_TYPE)
            ->where('status', 'draft')
            ->where('thirdPartner_id', $customer->id)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->whereIn('id', WhatsAppOrderImport::query()->where('status', 'created')->whereNotNull('document_id')->select('document_id'))
            ->latest('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Ajoute les lignes au BL du jour : un produit déjà présent voit sa
     * quantité augmenter (prix recalculé pour la nouvelle quantité, sauf s'il
     * a été modifié à la main), sinon nouvelle ligne. Pied recalculé depuis
     * toutes les lignes, paiements déjà saisis conservés, réservation de
     * stock reconstruite.
     */
    private function appendToDeliveryNote(DocumentHeader $document, array $p, array $lines, ThirdPartner $customer): void
    {
        $document->load('lignes', 'footer');
        $sort = (int) $document->lignes->max('sort_order');

        foreach ($lines as $l) {
            $existing = $l['product_id']
                ? $document->lignes->first(fn ($x) => (int) $x->product_id === (int) $l['product_id'])
                : null;

            if ($existing) {
                $oldQty = (float) $existing->quantity;
                $newQty = $oldQty + (float) $l['qty'];
                $untouched = abs((float) $existing->unit_price - $this->unitPriceFor($l['product_id'], $customer, $oldQty)) < 0.005;
                $existing->quantity = $newQty;
                if ($untouched) {
                    $existing->unit_price = $this->unitPriceFor($l['product_id'], $customer, $newQty);
                }
                $existing->save();
                continue;
            }

            $document->lignes()->create([
                'product_id'       => $l['product_id'],
                'designation'      => $l['designation'],
                'reference'        => $l['sku'],
                'quantity'         => $l['qty'],
                'unit'             => $l['unit'] ?? 'pièce',
                'unit_price'       => $l['unit_price_ht'],
                'discount_percent' => 0,
                'tax_percent'      => $l['vat_rate'],
                'sort_order'       => ++$sort,
            ]);
        }

        $document->load('lignes');
        $ht = round((float) $document->lignes->sum('total_ligne_ht'), 2);
        $tva = round((float) $document->lignes->sum('total_tax'), 2);
        $gross = round((float) $document->lignes->sum(fn ($x) => (float) $x->quantity * (float) $x->unit_price), 2);
        $totals = [
            'total_ht'       => $ht,
            'total_discount' => max(0, round($gross - $ht, 2)),
            'total_tax'      => $tva,
            'total_ttc'      => round($ht + $tva, 2),
        ];
        $footer = $document->footer
            ? tap($document->footer)->update($totals)
            : $document->footer()->create($totals + ['amount_paid' => 0, 'amount_due' => $totals['total_ttc']]);
        $footer->recalculateAmountDue();

        $tz = Setting::get('locale', 'timezone') ?: config('app.timezone');
        $when = Carbon::now($tz)->format('d/m H:i');
        $document->update(['notes' => trim(($document->notes ?? '') . sprintf(
            "\n---\nAjout du %s (%s) :\n%s\n[Import API %s]",
            $when,
            $this->origin,
            trim($p['notes'] ?? ($p['source_text'] ?? '')),
            $p['external_id']
        ))]);

        $this->stockService->resyncPending($document);
    }

    private function unitPriceFor(int $productId, ThirdPartner $customer, float $qty): float
    {
        $product = Product::find($productId);
        if (!$product) {
            return 0.0;
        }
        return (float) $this->priceResolver->resolve(product: $product, customer: $customer, quantity: (int) $qty, channel: 'all')['price_ht'];
    }

    /** Contenu complet du BL (toutes ses lignes) : sert au récapitulatif envoyé au client. */
    private function documentSummary(DocumentHeader $document, bool $appended): array
    {
        $document->loadMissing('lignes', 'footer');
        return [
            'id'        => $document->id,
            'reference' => $document->reference,
            'url'       => url("/ventes/documents/{$document->id}"),
            'appended'  => $appended,
            'lines'     => $document->lignes->sortBy('sort_order')->values()->map(fn ($x) => [
                'designation' => $x->designation,
                'sku'         => $x->reference,
                'qty'         => (float) $x->quantity,
            ])->all(),
            'totals'    => [
                'ht'  => (float) ($document->footer->total_ht ?? 0),
                'tva' => (float) ($document->footer->total_tax ?? 0),
                'ttc' => (float) ($document->footer->total_ttc ?? 0),
            ],
        ];
    }

    // ───────────────────────────── 1. Client ─────────────────────────────

    private function resolveCustomer(array $c): ?ThirdPartner
    {
        // Priorité : téléphone (le numéro WhatsApp de l'expéditeur, comparé
        // quel que soit son format de saisie) > code client > raison sociale exacte.
        $criteria = [
            'phone' => fn ($v) => $this->customers->byPhone($v),
            'code'  => fn ($v) => $this->customers->byCode($v),
            'name'  => fn ($v) => $this->customers->byExactName($v),
        ];

        foreach ($criteria as $field => $search) {
            if (empty($c[$field])) {
                continue;
            }
            $found = $search($c[$field]);
            if ($found->count() === 1) {
                return $found->first();
            }
            if ($found->count() > 1) {
                $list = $found->map(fn ($p) => "{$p->tp_code} ({$p->tp_title})")->implode(', ');
                $this->block('CUSTOMER_AMBIGUOUS', "customer.{$field}", "Plusieurs clients O3 correspondent à « {$c[$field]} » : {$list}. Préciser lequel.");
                return null;
            }
        }

        $this->block('CUSTOMER_NOT_FOUND', 'customer',
            "Aucun client O3 trouvé pour téléphone « " . ($c['phone'] ?? '—') . " » / code « " . ($c['code'] ?? '—') . " » / nom « " . ($c['name'] ?? '—') . " ». Vérifier la fiche client dans O3 — jamais de création automatique depuis un message.");
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

            $candidates = $this->searchProducts($query);

            if ($candidates->count() === 1) {
                $product = $candidates->first();
                $this->info('PRODUCT_MATCHED', "lines.{$i}.query", "« {$query} » rapproché de {$product->p_sku} — {$product->p_title}.");
            } elseif ($candidates->count() > 1) {
                $list = $candidates->map(fn ($p) => "{$p->p_sku} ({$p->p_title})")->implode(', ');
                $this->block('PRODUCT_AMBIGUOUS', "lines.{$i}.query", "« {$query} » correspond à plusieurs produits O3 : {$list}. Préciser lequel — jamais de choix au hasard.", [
                    'query'      => $query,
                    'candidates' => $candidates->map(fn ($p) => ['sku' => $p->p_sku, 'title' => $p->p_title])->all(),
                ]);
            } else {
                $this->block('PRODUCT_NOT_FOUND', "lines.{$i}.query", "« {$query} » ne correspond à aucun produit O3. Vérifier l'orthographe ou créer la fiche produit dans O3 avant de réessayer — jamais de création automatique depuis un texte client.", [
                    'query' => $query,
                ]);
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

    /**
     * Phrase entière d'abord ; si rien ne correspond, repli mot par mot
     * (« perceuses 18V » → « perceuse » ET « 18v ») : tous les mots doivent
     * se retrouver dans le même produit. Dans les deux cas l'appelant exige
     * un candidat unique — le repli élargit la recherche, jamais le choix.
     */
    private function searchProducts(string $query)
    {
        $columns = ['p_title', 'p_code', 'p_sku', 'p_ean13', 'p_description'];
        $matching = fn (string $term) => function ($q) use ($columns, $term) {
            foreach ($columns as $col) {
                $q->orWhere($col, 'like', '%' . $term . '%');
            }
        };

        $candidates = Product::query()->where($matching($query))->limit(10)->get(['id', 'p_title', 'p_sku']);
        if ($candidates->isNotEmpty()) {
            return $candidates;
        }

        $words = collect(preg_split('/[\s,;\/]+/u', mb_strtolower($query)))
            ->filter(fn ($w) => mb_strlen($w) >= 2 && !in_array($w, self::STOPWORDS, true))
            ->map(fn ($w) => mb_strlen($w) > 3 ? preg_replace('/(?<=[a-zà-ÿ])[sx]$/u', '', $w) : $w)
            ->unique()
            ->values();

        // Rien de nouveau à essayer : aucun mot utile, ou un seul mot identique à la phrase déjà cherchée.
        if ($words->isEmpty() || ($words->count() === 1 && $words->first() === mb_strtolower(trim($query)))) {
            return $candidates;
        }

        $q = Product::query();
        foreach ($words as $word) {
            $q->where($matching($word));
        }
        return $q->limit(10)->get(['id', 'p_title', 'p_sku']);
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
            "%s.\n%s\n[Import API %s]",
            $this->origin,
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

    /** @param array $extra données structurées en plus (ex. candidats), pour les réponses automatiques. */
    private function block(string $code, string $field, string $message, array $extra = []): void
    {
        $this->errors[] = array_merge(['level' => 'BLOQUANT', 'code' => $code, 'field' => $field, 'message' => $message], $extra);
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
