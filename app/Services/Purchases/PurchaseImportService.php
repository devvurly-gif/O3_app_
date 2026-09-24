<?php

namespace App\Services\Purchases;

use App\Models\Category;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\Product;
use App\Models\PurchaseImport;
use App\Models\ThirdPartner;
use App\Models\Warehouse;
use App\Services\DocumentHeaderService;
use App\Services\StockMouvementService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import d'un document d'achat (bon de réception / facture achat) envoyé par l'agent de
 * saisie Jadema (voir « factures fournisseurs\CLAUDE.md »).
 *
 * Écrit dans le schéma RÉEL d'O3 (relu dans C:\laragon\www\O3_app_ le 2026-09-24) :
 *   - Fournisseur → ThirdPartner (tp_Role = supplier), code et structure_id générés
 *     automatiquement par BelongsToStructure (comme tout tiers créé depuis l'écran O3).
 *   - Article     → Product (p_sku, p_title…), même mécanisme de code automatique.
 *     Rapproché par SKU en priorité, ou par EAN13 (champ optionnel `lines[].ean13`, match
 *     exact uniquement) si le SKU est absent du catalogue — utile quand le document ne
 *     porte qu'un code-barres lisible.
 *   - Document    → DocumentHeaderService::createWithLinesAndFooter() : c'est le MÊME
 *     service que l'écran « Nouveau document » — numérotation via DocumentIncrementor,
 *     lignes et pied de document dans la même transaction.
 *   - Stock       → StockMouvementService::processDocument() (mouvement 'in' appliqué
 *     immédiatement), exactement comme OcrInvoiceController::confirm().
 *
 * Deux écarts du contrat JSON (importer\README.md) par rapport au schéma O3 actuel,
 * assumés ici plutôt que de modifier le schéma sans que ce soit demandé :
 *   - `document_headers` n'a pas de colonne pour le n° de pièce du FOURNISSEUR (seule
 *     `reference`, la numérotation O3, existe) → `supplier_reference` est tracé dans
 *     `notes`. La protection anti-doublon API repose sur l'idempotence `external_id`
 *     (table purchase_imports) ; le doublon ICE + n° fournisseur est contrôlé en amont
 *     par Claude sur le registre Excel (CLAUDE.md §3), avant même l'écriture du JSON.
 *   - `document_footers` n'a pas de colonne dédiée au timbre fiscal → le montant est
 *     inclus dans `total_ttc` et rappelé dans `notes`.
 *
 * Garde-fous anti-hallucination (une création n'a lieu que si elle est sûre) :
 *   - SKU « sosie » d'un SKU existant (J/I, O/0, 1/I/L, 5/S, 8/B, 2/Z, espaces, tirets…)
 *     → BLOQUANT, pas de création (SKU_LOOKALIKE).
 *   - Nom de fournisseur qui ressemble à un existant (sans accents ni SARL/SA/STE…,
 *     écart ≤ 2 caractères) → BLOQUANT (SUPPLIER_LOOKALIKE).
 *   - Même nom mais ICE différent d'un fournisseur existant → BLOQUANT
 *     (SUPPLIER_ICE_CONFLICT).
 *   - En dry_run, rien n'est écrit : la réponse liste ce qui SERAIT créé (`to_create`).
 *
 * Erreurs renvoyées au format : ['level' => BLOQUANT|ATTENTION|INFO, 'code' => ...,
 * 'field' => ..., 'message' => ...].
 */
class PurchaseImportService
{
    private const TOLERANCE = 0.01;
    private const MAX_PAYMENT_DAYS = 120; // loi 69-21

    /** Valeur API → document_type réel d'O3 (DocumentHeader::isType()). */
    private const DOC_TYPES = [
        'bon_reception' => 'ReceiptNotePurchase',
        'facture_achat' => 'InvoicePurchase',
    ];

    private const DOC_TITLES = [
        'ReceiptNotePurchase' => 'Bon de Réception',
        'InvoicePurchase'     => 'Facture Achat',
    ];

    /** Caractères souvent confondus à la lecture (scan, photo, OCR) → forme canonique. */
    private const LOOKALIKE = [
        'O' => '0', 'Q' => '0', 'D' => '0',
        'I' => '1', 'L' => '1', 'J' => '1', '|' => '1',
        'S' => '5', 'B' => '8', 'Z' => '2', 'G' => '6',
    ];

    /** Formes juridiques ignorées pour comparer les noms de fournisseurs. */
    private const LEGAL_FORMS = ['sarl au', 'sarlau', 'sarl', 'sa', 'snc', 'sas', 'ste', 'societe', 'ets', 'etablissements', 'etablissement', 'group', 'groupe', 'maroc', 'morocco'];

    private array $errors = [];
    private array $warnings = [];
    private bool $pricesIncludeVat = false;

    public function __construct(
        private DocumentHeaderService $documentService,
        private StockMouvementService $stockService,
    ) {
    }

    public function handle(array $payload, bool $dryRun, int $userId): array
    {
        $this->errors = $this->warnings = [];
        $this->pricesIncludeVat = (bool) ($payload['prices_include_vat'] ?? false);
        $externalId = $payload['external_id'];
        $hash = hash('sha256', json_encode(collect($payload)->except('dry_run')->all()));
        $allow = [
            'products' => (bool) data_get($payload, 'allow_create.products', true),
            'supplier' => (bool) data_get($payload, 'allow_create.supplier', true),
        ];

        // 0. Idempotence : déjà importé ?
        $existing = PurchaseImport::where('external_id', $externalId)->where('status', 'created')->first();
        if ($existing) {
            if ($existing->payload_hash !== $hash) {
                $this->block('ALREADY_IMPORTED_DIFFERENT', 'external_id',
                    "{$externalId} a déjà été importé avec un contenu différent (document {$existing->document_reference}). Corriger dans O3, ne pas réimporter.");
                return $this->respond('rejected', $payload, $dryRun);
            }
            return array_merge($existing->response, ['status' => 'already_imported']);
        }

        // 1. Lignes / produits  2. Fournisseur  3. Entrepôt / numérotation (+ totaux, échéance)
        $docType      = self::DOC_TYPES[$payload['type']];
        $lines        = $this->resolveLines($payload['lines'], $allow['products']);
        $supplierPlan = $this->resolveSupplier($payload['supplier'], $allow['supplier']);
        $warehouse    = $this->resolveWarehouse($payload['warehouse'] ?? null);
        $incrementor  = $this->resolveIncrementor($docType);
        $computed     = $this->checkTotals($payload, $lines);
        $this->checkDueDate($payload);

        $toCreate = [
            'products' => collect($lines)->where('action', 'create')
                ->map(fn ($l) => ['sku' => $l['sku'], 'designation' => $l['designation'], 'purchase_price' => $l['unit_price_ht'], 'vat_rate' => $l['vat_rate']])
                ->values()->all(),
            'supplier' => $supplierPlan['action'] === 'create' ? $supplierPlan['data'] : null,
        ];

        $preview = [
            'supplier'  => $supplierPlan['model'] ? $this->supplierSummary($supplierPlan['model']) : $supplierPlan['data'],
            'warehouse' => $warehouse?->wh_title,
            'totals'    => $computed,
            'lines'     => array_map(fn ($l) => collect($l)->except('product')->all(), $lines),
            'to_create' => $toCreate,
        ];

        if ($this->errors) {
            return $this->log($userId, $payload, $hash, $dryRun,
                $this->respond('rejected', $payload, $dryRun, ['preview' => $preview]));
        }
        if ($dryRun) {
            return $this->respond('valid', $payload, $dryRun, ['preview' => $preview]);
        }

        // 4. Écriture — tout ou rien, dans l'ordre demandé : produits → fournisseur → document → stock
        $created = ['products' => [], 'supplier' => null];

        $document = DB::transaction(function () use ($payload, $docType, &$lines, $supplierPlan, $warehouse, $incrementor, $computed, $userId, &$created) {
            foreach ($lines as &$l) {
                if ($l['action'] === 'create') {
                    $product = $this->createProduct($l, $payload['external_id']);
                    $l['product_id'] = $product->id;
                    $l['action'] = 'created';
                    $created['products'][] = ['id' => $product->id, 'sku' => $product->p_sku, 'designation' => $l['designation']];
                }
            }
            unset($l);

            $supplier = $supplierPlan['model'];
            if ($supplierPlan['action'] === 'create') {
                $supplier = $this->createSupplier($supplierPlan['data']);
                $created['supplier'] = $this->supplierSummary($supplier);
            }

            return $this->createDocument($payload, $docType, $supplier, $warehouse, $incrementor, $lines, $computed, $userId);
        });

        foreach ($created['products'] as $p) {
            $this->info('PRODUCT_CREATED', 'lines', "Produit créé : {$p['sku']} — {$p['designation']} (id {$p['id']}). À compléter dans O3 (catégorie, prix de vente, image).");
        }
        if ($created['supplier']) {
            $this->info('SUPPLIER_CREATED', 'supplier', "Fournisseur créé : {$created['supplier']['name']} (id {$created['supplier']['id']}). À compléter dans O3 (coordonnées, IF, RC).");
        }

        return $this->log($userId, $payload, $hash, false, $this->respond('created', $payload, false, [
            'preview'  => $preview,
            'created'  => $created,
            'document' => [
                'id'        => $document->id,
                'reference' => $document->reference,
                'url'       => url("/achats/documents/{$document->id}"),
            ],
        ]), $document);
    }

    // ───────────────────────────── 1. Lignes / produits ─────────────────────────────

    private function resolveLines(array $lines, bool $allowCreate): array
    {
        // Catalogue complet (id, sku, ean13, titre) — une seule requête, sert aux
        // correspondances exactes, normalisées, « sosies » et par code-barres EAN13.
        $catalog = Product::query()->select(['id', 'p_sku', 'p_ean13', 'p_title'])->get();
        $byNorm     = $catalog->groupBy(fn ($p) => $this->normSku($p->p_sku));
        $bySkeleton = $catalog->groupBy(fn ($p) => $this->skeletonSku($p->p_sku));
        $byEan13    = $catalog->filter(fn ($p) => !empty($p->p_ean13))->groupBy(fn ($p) => trim($p->p_ean13));

        $seenInPayload = [];
        $out = [];

        foreach ($lines as $i => $l) {
            $sku  = trim($l['sku']);
            $l['unit_price_ht'] = (float) ($l['unit_price'] ?? $l['unit_price_ht']); // prix tel qu'imprimé
            // Prix imprimés TTC (prices_include_vat) : ils sont convertis en HT plus bas, à partir du total TTC de ligne.
            $norm = $this->normSku($sku);
            $product = null;
            $action  = 'link';

            $exact = $catalog->firstWhere('p_sku', $sku);
            if ($exact) {
                $product = $exact;
            } elseif (($cands = $byNorm->get($norm, collect()))->count() === 1) {
                $product = $cands->first();
                $this->warn('SKU_NORMALIZED_MATCH', "lines.{$i}.sku",
                    "SKU lu « {$sku} » rapproché de « {$product->p_sku} » (casse / espaces / tirets).");
            } elseif ($cands->count() > 1) {
                $this->block('SKU_AMBIGUOUS', "lines.{$i}.sku", "Plusieurs produits O3 correspondent à « {$sku} ». Préciser le SKU exact.");
                $action = 'blocked';
            } elseif (($look = $bySkeleton->get($this->skeletonSku($sku), collect()))->isNotEmpty()) {
                // Un SKU presque identique existe : probable erreur de lecture → on NE crée PAS.
                $list = $look->map(fn ($p) => $p->p_sku)->implode(', ');
                $this->block('SKU_LOOKALIKE', "lines.{$i}.sku",
                    "« {$sku} » n'existe pas mais ressemble à : {$list}. Erreur de lecture probable — confirmer le SKU avant toute création.");
                $action = 'blocked';
            } elseif (!empty($l['ean13']) && ($eanCands = $byEan13->get(trim($l['ean13']), collect()))->count() === 1) {
                // Pas de logique « sosie » ici : un EAN13 se vérifie par sa clé de contrôle,
                // pas par ressemblance visuelle — un match exact suffit.
                $product = $eanCands->first();
                $this->info('EAN13_MATCH', "lines.{$i}.ean13",
                    "Article rapproché par EAN13 {$l['ean13']} : SKU lu « {$sku} » ≠ SKU O3 « {$product->p_sku} ».");
            } elseif (!empty($l['ean13']) && $eanCands->count() > 1) {
                $this->block('EAN13_AMBIGUOUS', "lines.{$i}.ean13", "Plusieurs produits O3 partagent l'EAN13 {$l['ean13']}. Préciser le SKU exact.");
                $action = 'blocked';
            } elseif (!$allowCreate) {
                $this->block('PRODUCT_NOT_FOUND', "lines.{$i}.sku", "Article {$sku} introuvable et création désactivée (allow_create.products = false).");
                $action = 'blocked';
            } elseif (mb_strlen(trim($l['designation'])) < 3 || (float) $l['unit_price_ht'] <= 0) {
                $this->block('PRODUCT_DATA_INSUFFICIENT', "lines.{$i}", "Article {$sku} à créer, mais désignation ou prix d'achat insuffisant.");
                $action = 'blocked';
            } elseif (isset($seenInPayload[$norm])) {
                $action = 'reuse'; // même nouveau SKU sur plusieurs lignes : créé une seule fois
            } else {
                $action = 'create';
                $seenInPayload[$norm] = true;
                $this->warn('PRODUCT_WILL_BE_CREATED', "lines.{$i}.sku", "Article {$sku} (« {$l['designation']} ») absent du catalogue : il sera créé.");
            }

            $discount = (float) ($l['discount_pct'] ?? 0);
            $vat = isset($l['vat_rate']) ? (float) $l['vat_rate'] : null;
            if ($vat === null) {
                if ($this->pricesIncludeVat) {
                    $this->block('VAT_RATE_MISSING', "lines.{$i}.vat_rate", "Prix TTC pour {$sku} mais taux de TVA absent : conversion en HT impossible.");
                } else {
                    $this->warn('VAT_RATE_MISSING', "lines.{$i}.vat_rate", "Taux de TVA non fourni pour {$sku} : 0 % appliqué.");
                }
                $vat = 0.0;
            }

            if ($this->pricesIncludeVat) {
                // Le TTC de ligne fait foi ; HT et TVA en sont déduits (pas d'écart d'arrondi sur le TTC).
                $ttcLine = round($l['qty'] * $l['unit_price_ht'] * (1 - $discount / 100), 2);
                $ht      = round($ttcLine / (1 + $vat / 100), 2);
                $tvaLine = round($ttcLine - $ht, 2);
                $puHt    = round($l['unit_price_ht'] / (1 + $vat / 100), 4);
            } else {
                $ht      = round($l['qty'] * $l['unit_price_ht'] * (1 - $discount / 100), 2);
                $tvaLine = round($ht * $vat / 100, 2);
                $puHt    = (float) $l['unit_price_ht'];
            }

            $out[] = [
                'index'         => $i,
                'action'        => $action,          // link | create | reuse | blocked
                'product_id'    => $product?->id,
                'product'       => $product,
                'sku'           => $product ? $product->p_sku : $sku,
                'designation'   => $l['designation'],
                'qty'           => (float) $l['qty'],
                'unit'          => $l['unit'] ?? null,
                'unit_price_ht' => $puHt,
                'discount_pct'  => $discount,
                'vat_rate'      => $vat,
                'total_ht'      => $ht,
                'total_tva'     => $tvaLine,
            ];
        }
        return $out;
    }

    // ───────────────────────────── 2. Fournisseur ─────────────────────────────

    /** @return array{action: 'link'|'create'|'blocked', model: ?ThirdPartner, data: ?array} */
    private function resolveSupplier(array $s, bool $allowCreate): array
    {
        $q = fn () => ThirdPartner::query()->whereIn('tp_Role', ['supplier', 'both']);
        $link = fn (ThirdPartner $model) => ['action' => 'link', 'model' => $model, 'data' => null];
        $blocked = ['action' => 'blocked', 'model' => null, 'data' => null];

        // Priorité : ICE > code > raison sociale
        if (!empty($s['ice']) && ($found = $q()->where('tp_Ice_Number', $s['ice'])->first())) {
            return $link($this->checkSupplierName($found, $s));
        }
        if (!empty($s['code']) && ($found = $q()->where('tp_code', $s['code'])->first())) {
            return $link($this->checkSupplierName($found, $s));
        }
        if (!empty($s['code'])) {
            $this->block('SUPPLIER_CODE_NOT_FOUND', 'supplier.code', "Code fournisseur « {$s['code']} » introuvable dans O3.");
            return $blocked;
        }

        if (empty($s['name'])) {
            $this->block('SUPPLIER_NOT_FOUND', 'supplier', "Aucun fournisseur O3 avec l'ICE {$s['ice']} et aucun nom fourni pour le créer.");
            return $blocked;
        }

        // Recherche par nom normalisé (sans accents, sans formes juridiques) + sosies (distance ≤ 2)
        $wanted = $this->normName($s['name']);
        $all = $q()->select(['id', 'tp_title', 'tp_Ice_Number', 'tp_code'])->get();
        $same  = $all->filter(fn ($x) => $this->normName($x->tp_title) === $wanted);
        $close = $all->filter(function ($x) use ($wanted) {
            $n = $this->normName($x->tp_title);
            return $wanted !== '' && $n !== $wanted && levenshtein($n, $wanted) <= 2;
        });

        if ($same->count() === 1 && empty($s['ice'])) {
            return $link($same->first());
        }
        if ($same->count() === 1 && !empty($s['ice'])) {
            // Même nom mais ICE différent / absent en base : on ne crée pas un 2e fournisseur.
            $o3 = $same->first();
            $this->block('SUPPLIER_ICE_CONFLICT', 'supplier.ice',
                "« {$o3->tp_title} » existe déjà dans O3 avec l'ICE « " . ($o3->tp_Ice_Number ?: 'vide') . " » ≠ ICE lu {$s['ice']}. Vérifier puis corriger la fiche O3 ou le document.");
            return $blocked;
        }
        if ($same->count() > 1) {
            $this->block('SUPPLIER_AMBIGUOUS', 'supplier.name', "Plusieurs fournisseurs O3 correspondent à « {$s['name']} ». Fournir l'ICE ou le code.");
            return $blocked;
        }
        if ($close->isNotEmpty()) {
            $names = $close->map(fn ($x) => $x->tp_title)->implode(', ');
            $this->block('SUPPLIER_LOOKALIKE', 'supplier.name',
                "« {$s['name']} » n'existe pas mais ressemble à : {$names}. Confirmer (fournir le code) avant toute création.");
            return $blocked;
        }

        if (!$allowCreate) {
            $this->block('SUPPLIER_NOT_FOUND', 'supplier', "Fournisseur « {$s['name']} » introuvable et création désactivée (allow_create.supplier = false).");
            return $blocked;
        }

        if (empty($s['ice'])) {
            $this->warn('SUPPLIER_CREATED_WITHOUT_ICE', 'supplier.ice', "Le fournisseur « {$s['name']} » sera créé sans ICE : à compléter dans O3.");
        }
        $this->warn('SUPPLIER_WILL_BE_CREATED', 'supplier', "Fournisseur « {$s['name']} » absent d'O3 : il sera créé.");

        return ['action' => 'create', 'model' => null, 'data' => array_filter([
            'name' => trim($s['name']),
            'ice'  => $s['ice'] ?? null,
            'city' => $s['city'] ?? null,
            'phone'=> $s['phone'] ?? null,
            'address' => $s['address'] ?? null,
        ])];
    }

    private function checkSupplierName(ThirdPartner $supplier, array $s): ThirdPartner
    {
        $o3Name = (string) $supplier->tp_title;
        if (!empty($s['name']) && $this->normName($s['name']) !== $this->normName($o3Name)) {
            $this->warn('SUPPLIER_NAME_MISMATCH', 'supplier.name', "Nom lu « {$s['name']} » ≠ nom O3 « {$o3Name} » (rapproché par ICE/code).");
        }
        return $supplier;
    }

    private function resolveWarehouse(?string $name): ?Warehouse
    {
        if ($name !== null) {
            $w = Warehouse::where('wh_title', $name)->first();
            if (!$w) {
                $this->block('WAREHOUSE_NOT_FOUND', 'warehouse', "Entrepôt « {$name} » introuvable dans O3.");
            }
            return $w;
        }

        // Aucun entrepôt précisé sur le document (normal : ce n'est jamais imprimé sur une
        // facture fournisseur). On ne devine que s'il n'existe qu'une seule possibilité.
        $actifs = Warehouse::where('wh_status', true)->get();
        if ($actifs->count() === 1) {
            return $actifs->first();
        }
        if ($actifs->count() > 1) {
            $this->block('WAREHOUSE_AMBIGUOUS', 'warehouse', 'Entrepôt non précisé et plusieurs entrepôts actifs existent dans O3 : préciser lequel (champ "warehouse").');
        } else {
            $this->block('WAREHOUSE_NOT_FOUND', 'warehouse', 'Aucun entrepôt actif configuré dans O3.');
        }
        return null;
    }

    private function resolveIncrementor(string $docType): ?DocumentIncrementor
    {
        $incrementor = DocumentIncrementor::where('di_model', $docType)->first();
        if (!$incrementor) {
            $this->block('INCREMENTOR_NOT_FOUND', 'type',
                "Aucun compteur de numérotation O3 (DocumentIncrementor) configuré pour {$docType}. À créer une fois dans O3 avant le premier import.");
        }
        return $incrementor;
    }

    // ───────────────────────────── Contrôles ─────────────────────────────

    private function checkTotals(array $p, array $lines): array
    {
        $ht    = round(array_sum(array_column($lines, 'total_ht')), 2);
        $tva   = round(array_sum(array_column($lines, 'total_tva')), 2);
        $stamp = (float) ($p['totals']['stamp'] ?? 0);
        $ttc   = round($ht + $tva + $stamp, 2);
        $given = $p['totals'];

        $cmp = function (string $key, float $calc, string $label) use ($given) {
            if (isset($given[$key]) && round(abs((float) $given[$key] - $calc), 2) > self::TOLERANCE) {
                $this->block('TOTAL_MISMATCH', "totals.{$key}",
                    sprintf('%s document = %.2f ≠ calculé depuis les lignes = %.2f.', $label, $given[$key], $calc));
            }
        };
        $cmp('ht', $ht, 'Total HT');
        $cmp('tva', $tva, 'TVA');
        $cmp('ttc', $ttc, 'Total TTC');

        return ['ht' => $ht, 'tva' => $tva, 'stamp' => $stamp, 'ttc' => $ttc];
    }

    private function checkDueDate(array $p): void
    {
        if ($p['type'] !== 'facture_achat') {
            return;
        }
        if (empty($p['due_date'])) {
            $this->warn('DUE_DATE_MISSING', 'due_date', 'Échéance absente sur la facture.');
            return;
        }
        $days = (int) Carbon::parse($p['date'])->diffInDays(Carbon::parse($p['due_date']));
        if ($days > self::MAX_PAYMENT_DAYS) {
            $this->warn('DUE_DATE_TOO_LONG', 'due_date', "Échéance à {$days} jours (> 120 j, loi 69-21).");
        }
    }

    // ───────────────────────────── Écriture ─────────────────────────────

    private function createProduct(array $l, string $externalId): Product
    {
        $category = Category::firstOrCreate(['ctg_title' => 'Non catégorisé'], ['ctg_status' => true]);

        return Product::create([
            'p_sku'           => $l['sku'],
            'p_title'         => $l['designation'],
            'p_description'   => "Créé automatiquement par l'import API {$externalId}.",
            'p_purchasePrice' => $l['unit_price_ht'],
            'p_salePrice'     => 0,          // à fixer par l'utilisateur dans O3
            'p_cost'          => $l['unit_price_ht'],
            'p_taxRate'       => $l['vat_rate'],
            'p_unit'          => $l['unit'] ?? 'pièce',
            'p_status'        => false,      // inactif tant que la fiche n'est pas complétée
            'is_ecom'         => false,      // jamais publié sur l'O3 Store automatiquement
            'category_id'     => $category->id,
        ]);
        // p_code (SKU alternatif) et structure_id sont générés automatiquement par
        // BelongsToStructure / Product::booted(), comme pour toute création depuis l'écran O3.
    }

    private function createSupplier(array $data): ThirdPartner
    {
        return ThirdPartner::create([
            'tp_title'      => $data['name'],
            'tp_Ice_Number' => $data['ice'] ?? null,
            'tp_Role'       => 'supplier',
            'tp_status'     => true,
            'tp_phone'      => $data['phone'] ?? null,
            'tp_address'    => $data['address'] ?? null,
            'tp_city'       => $data['city'] ?? null,
        ]);
        // tp_code et structure_id générés automatiquement (BelongsToStructure).
    }

    private function createDocument(array $p, string $docType, ThirdPartner $supplier, Warehouse $warehouse, DocumentIncrementor $incrementor, array $lines, array $totals, int $userId): DocumentHeader
    {
        $stampNote = $totals['stamp'] > 0 ? sprintf(' — Timbre fiscal inclus dans le TTC : %.2f MAD', $totals['stamp']) : '';
        $notes = trim(sprintf(
            "Réf. document fournisseur : %s%s\n%s\n[Import API %s]",
            $p['supplier_reference'],
            $stampNote,
            trim($p['notes'] ?? ''),
            $p['external_id']
        ));

        $headerData = [
            'document_incrementor_id' => $incrementor->id,
            'document_type'           => $docType,
            'document_title'          => self::DOC_TITLES[$docType],
            'thirdPartner_id'         => $supplier->id,
            'company_role'            => 'supplier',
            'warehouse_id'            => $warehouse->id,
            'issued_at'               => $p['date'],
            'due_at'                  => $p['due_date'] ?? null,
            'notes'                   => $notes,
            'user_id'                 => $userId,
        ];

        // Lignes « reuse » : même nouveau SKU que une ligne créée plus haut → récupérer son id.
        $idsBySku = collect($lines)->whereNotNull('product_id')
            ->mapWithKeys(fn ($l) => [$this->normSku($l['sku']) => $l['product_id']]);

        $linesData = array_map(fn ($l) => [
            'product_id'       => $l['product_id'] ?? $idsBySku->get($this->normSku($l['sku'])),
            'designation'      => $l['designation'],
            'reference'        => $l['sku'],
            'quantity'         => $l['qty'],
            'unit'             => $l['unit'] ?? 'pièce',
            'unit_price'       => $l['unit_price_ht'],
            'discount_percent' => $l['discount_pct'],
            'tax_percent'      => $l['vat_rate'],
        ], $lines);

        $totalDiscount = round(array_sum(array_map(
            fn ($l) => $l['qty'] * $l['unit_price_ht'] * ($l['discount_pct'] / 100),
            $lines
        )), 2);

        $footerData = [
            'total_ht'       => $totals['ht'],
            'total_discount' => $totalDiscount,
            'total_tax'      => $totals['tva'],
            'total_ttc'      => $totals['ttc'],
            'amount_paid'    => 0,
            'amount_due'     => $totals['ttc'],
        ];

        $document = $this->documentService->createWithLinesAndFooter($headerData, $linesData, $footerData);

        // Même flux que OcrInvoiceController::confirm() : brouillon → confirmé, puis stock.
        $document->update(['status' => 'confirmed']);
        $document->load('lignes');
        $this->stockService->processDocument($document);

        return $document->fresh();
    }

    // ───────────────────────────── Normalisation ─────────────────────────────

    private function normSku(?string $sku): string
    {
        return preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii((string) $sku)));
    }

    /** Forme « squelette » : les caractères confondables sont ramenés à une même forme. */
    private function skeletonSku(?string $sku): string
    {
        return strtr($this->normSku($sku), self::LOOKALIKE);
    }

    private function normName(?string $name): string
    {
        $n = Str::lower(Str::ascii((string) $name));
        $n = preg_replace('/[^a-z0-9 ]/', ' ', $n);
        foreach (self::LEGAL_FORMS as $f) {
            $n = preg_replace('/\b' . preg_quote($f, '/') . '\b/', ' ', $n);
        }
        return trim(preg_replace('/\s+/', ' ', $n));
    }

    // ───────────────────────────── Réponse / journal ─────────────────────────────

    private function respond(string $status, array $p, bool $dryRun, array $extra = []): array
    {
        return array_merge([
            'status'      => $status,        // valid | created | already_imported | rejected
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
        PurchaseImport::updateOrCreate(
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

    private function supplierSummary(ThirdPartner $s): array
    {
        return ['id' => $s->id, 'name' => $s->tp_title, 'ice' => $s->tp_Ice_Number, 'code' => $s->tp_code];
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
