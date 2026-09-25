<?php

namespace App\Console\Commands;

use App\Models\DocumentIncrementor;
use App\Models\DocumentLigne;
use App\Models\Product;
use App\Models\StockMouvement;
use App\Models\Tenant;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\DailyPurchaseOrdersDrafted;
use App\Services\DocumentHeaderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;

/**
 * Prépare chaque jour un brouillon de bon de commande fournisseur (BCF, document_type
 * PurchaseOrder) par fournisseur, pour recommander exactement ce qui a été vendu sur la
 * fenêtre écoulée (--days, 1 jour = la veille par défaut).
 *
 * Volontairement inoffensif : DocumentHeaderService::createWithLinesAndFooter() force
 * toujours le statut `draft` et un PurchaseOrder ne déclenche aucun mouvement de stock
 * (voir StockMouvementService::processDocument(), qui ne connaît pas ce type). Le
 * brouillon n'est ni envoyé au fournisseur ni visible en dehors d'O3 tant qu'un humain
 * ne le fait pas passer à `confirmed` lui-même dans l'écran O3.
 *
 * Un produit vendu mais jamais acheté avant (aucun fournisseur historique) n'est jamais
 * deviné : il est exclu du BCF et listé à part dans la notification, pour assignation
 * manuelle du fournisseur.
 */
class DraftDailyPurchaseOrders extends Command
{
    private const DOC_TYPE = 'PurchaseOrder';
    private const SALE_REASONS = ['sale', 'sale_delivery', 'pos_sale'];
    private const NON_COUNTING_STATUSES = ['cancelled', 'draft', 'converted'];
    private const SERVICE_ACCOUNT_EMAIL = 'agent-ia.achats@jadema.o3app.local';

    protected $signature = 'achats:draft-daily-po
        {tenant=jadema : ID du tenant}
        {--days=1 : nombre de jours de ventes à recommander (1 = la veille)}
        {--dry-run : calcule et affiche sans rien créer}';

    protected $description = 'Prépare un brouillon de BCF par fournisseur pour recommander ce qui a été vendu la veille';

    public function __construct(private DocumentHeaderService $documentService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' introuvable.");
            return self::FAILURE;
        }

        $exit = self::FAILURE;
        $tenant->run(function () use (&$exit) {
            $exit = $this->draft();
        });

        return $exit;
    }

    private function draft(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $since = Carbon::now()->subDays($days);

        // 1. Quantité vendue par produit sur la fenêtre.
        $soldByProduct = StockMouvement::query()
            ->where('direction', 'out')
            ->whereIn('reason', self::SALE_REASONS)
            ->where('status', 'applied')
            ->where('created_at', '>=', $since)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->pluck('qty', 'product_id');

        if ($soldByProduct->isEmpty()) {
            $this->info('Aucune vente sur la période — rien à recommander.');
            return self::SUCCESS;
        }

        $productIds = $soldByProduct->keys()->all();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        // 2. Fournisseur habituel par produit, inféré depuis l'historique d'achat
        //    (fréquence, départagé par la commande la plus récente). Jamais deviné :
        //    un produit sans historique d'achat est exclu, pas assigné au hasard.
        $supplierByProduct = DocumentLigne::query()
            ->join('document_headers', 'document_lignes.document_header_id', '=', 'document_headers.id')
            ->whereIn('document_headers.document_type', ['ReceiptNotePurchase', 'InvoicePurchase'])
            ->whereNotIn('document_headers.status', self::NON_COUNTING_STATUSES)
            ->whereNull('document_headers.deleted_at')
            ->whereIn('document_lignes.product_id', $productIds)
            ->select(
                'document_lignes.product_id',
                'document_headers.thirdPartner_id',
                DB::raw('COUNT(*) as freq'),
                DB::raw('MAX(document_headers.issued_at) as last_date')
            )
            ->groupBy('document_lignes.product_id', 'document_headers.thirdPartner_id')
            ->orderByDesc('freq')
            ->orderByDesc('last_date')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->first()->thirdPartner_id);

        $unassigned = [];
        $linesBySupplier = [];
        foreach ($soldByProduct as $productId => $qty) {
            $product = $products->get($productId);
            if (!$product) {
                continue; // produit supprimé entre-temps
            }
            $supplierId = $supplierByProduct->get($productId);
            if (!$supplierId) {
                $unassigned[] = ['product' => $product, 'qty' => $qty];
                continue;
            }
            $linesBySupplier[$supplierId][] = [
                'product_id'       => $product->id,
                'designation'      => $product->p_title,
                'reference'        => $product->p_sku,
                'quantity'         => $qty,
                'unit'             => $product->p_unit ?? 'pièce',
                'unit_price'       => $product->p_purchasePrice,
                'discount_percent' => 0,
                'tax_percent'      => $product->p_taxRate,
            ];
        }

        if (empty($linesBySupplier)) {
            $this->info('Ventes trouvées mais aucun fournisseur historique connu pour ces produits — aucun BCF créé.');
            $this->reportUnassigned($unassigned);
            return self::SUCCESS;
        }

        $warehouse = $this->resolveWarehouse();
        $incrementor = DocumentIncrementor::where('di_model', self::DOC_TYPE)->first();
        $serviceUser = $this->resolveServiceUser();

        if (!$warehouse || !$incrementor || !$serviceUser) {
            $this->error('Pré-requis manquant (entrepôt, incrémenteur PurchaseOrder, ou compte de service) — aucun BCF créé.');
            return self::FAILURE;
        }

        $created = [];
        foreach ($linesBySupplier as $supplierId => $lines) {
            $supplier = ThirdPartner::find($supplierId);
            if (!$supplier) {
                continue;
            }

            if ($dryRun) {
                $this->info("[dry-run] BCF {$supplier->tp_title} : " . count($lines) . ' ligne(s)');
                foreach ($lines as $l) {
                    $this->line("   + {$l['reference']} — {$l['designation']} x{$l['quantity']}");
                }
                continue;
            }

            $totalHt = array_sum(array_map(fn ($l) => $l['quantity'] * $l['unit_price'], $lines));
            $totalTax = array_sum(array_map(fn ($l) => $l['quantity'] * $l['unit_price'] * $l['tax_percent'] / 100, $lines));

            $document = $this->documentService->createWithLinesAndFooter(
                [
                    'document_incrementor_id' => $incrementor->id,
                    'document_type'           => self::DOC_TYPE,
                    'document_title'          => 'Bon de Commande (Fournisseur)',
                    'thirdPartner_id'         => $supplier->id,
                    'company_role'            => 'supplier',
                    'warehouse_id'            => $warehouse->id,
                    'issued_at'               => Carbon::today(),
                    'due_at'                  => null,
                    'notes'                   => "Brouillon généré automatiquement (achats:draft-daily-po) — quantités vendues sur les {$days} dernier(s) jour(s). À relire et confirmer dans O3 avant tout envoi au fournisseur.",
                    'user_id'                 => $serviceUser->id,
                ],
                $lines,
                [
                    'total_ht'       => round($totalHt, 2),
                    'total_discount' => 0,
                    'total_tax'      => round($totalTax, 2),
                    'total_ttc'      => round($totalHt + $totalTax, 2),
                    'amount_paid'    => 0,
                    'amount_due'     => round($totalHt + $totalTax, 2),
                ]
            );

            $created[] = ['supplier' => $supplier, 'document' => $document, 'lines' => count($lines)];
            $this->info("BCF {$document->reference} créé pour {$supplier->tp_title} (" . count($lines) . ' ligne(s), statut draft).');
        }

        if (!$dryRun) {
            $this->notifyRecipients($created, $unassigned);
        } else {
            $this->reportUnassigned($unassigned);
        }

        return self::SUCCESS;
    }

    private function resolveWarehouse(): ?Warehouse
    {
        $actifs = Warehouse::where('wh_status', true)->get();
        if ($actifs->count() === 1) {
            return $actifs->first();
        }
        $this->error($actifs->isEmpty()
            ? 'Aucun entrepôt actif configuré dans O3.'
            : 'Plusieurs entrepôts actifs existent : impossible de choisir automatiquement lequel reçoit la commande.');
        return null;
    }

    private function resolveServiceUser(): ?User
    {
        $user = User::where('email', self::SERVICE_ACCOUNT_EMAIL)->first();
        if ($user) {
            return $user;
        }
        $this->error('Compte de service "Agent IA" introuvable (php artisan achats:agent-token). Aucun BCF ne peut être attribué à un utilisateur.');
        return null;
    }

    private function reportUnassigned(array $unassigned): void
    {
        if (empty($unassigned)) {
            return;
        }
        $this->warn(count($unassigned) . ' produit(s) vendu(s) sans fournisseur historique connu (à assigner manuellement) :');
        foreach ($unassigned as $u) {
            $this->line("   - {$u['product']->p_sku} — {$u['product']->p_title} (vendu : {$u['qty']})");
        }
    }

    private function notifyRecipients(array $created, array $unassigned): void
    {
        $recipients = User::whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'manager', 'warehouse']))
            ->where('is_active', true)
            ->get();

        if ($recipients->isEmpty()) {
            $this->warn('Aucun destinataire actif (admin/manager/warehouse) à notifier.');
            return;
        }

        if (empty($created) && empty($unassigned)) {
            return;
        }

        $notification = new DailyPurchaseOrdersDrafted($created, $unassigned);
        foreach ($recipients as $user) {
            $user->notify($notification);
        }
        $this->info("Notification envoyée à {$recipients->count()} utilisateur(s).");
    }
}
