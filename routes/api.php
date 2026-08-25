<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DocumentFooterController;
use App\Http\Controllers\Api\DocumentHeaderController;
use App\Http\Controllers\Api\DocumentImportController;
use App\Http\Controllers\Api\DocumentIncrementorController;
use App\Http\Controllers\Api\DocumentLigneController;
use App\Http\Controllers\Api\LabelPrintController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PriceListController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\ProductVideoController;
use App\Http\Controllers\Api\ProductDocumentController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StockMouvementController;
use App\Http\Controllers\Api\StockOperationController;
use App\Http\Controllers\Api\StructureIncrementorController;
use App\Http\Controllers\Api\TaxSettingsController;
use App\Http\Controllers\Api\ThirdPartnerController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\WarehouseStockController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Achats\DocumentAchatController;
use App\Http\Controllers\Api\Ventes\DocumentVenteController;
use App\Http\Controllers\Api\Stock\DocumentStockController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DashboardWidgetController;
use App\Http\Controllers\Api\DocumentPdfController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\Pos\PosProductController;
use App\Http\Controllers\Api\Pos\PosSessionController;
use App\Http\Controllers\Api\Pos\PosTerminalController;
use App\Http\Controllers\Api\Pos\PosCustomerController;
use App\Http\Controllers\Api\Pos\PosTicketController;
use App\Http\Controllers\Api\WarehouseTransferController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\SlideController;
use App\Http\Controllers\Api\VariantOptionController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\Ecom\EcomCatalogueController;
use App\Http\Controllers\Api\Ecom\EcomPromotionController;
use App\Http\Controllers\Api\Ecom\EcomOrderController;
use App\Http\Controllers\Api\Ecom\EcomSlideController;
use App\Http\Controllers\Api\Ecom\EcomConfigController;
use App\Http\Controllers\Api\StorageGalleryController;
use App\Http\Controllers\Api\Treasury\CashAccountController;
use App\Http\Controllers\Api\Treasury\CashCategoryController;
use App\Http\Controllers\Api\Treasury\CashRecurrenceController;
use App\Http\Controllers\Api\Treasury\CashTransactionController;
use App\Http\Controllers\Api\Treasury\TreasuryController;
use App\Services\CacheService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Role permissions:
|   admin     – full access to everything
|   manager   – catalogue, documents, stock (no users/settings/structures)
|   cashier   – documents & payments read/write, catalogue & stock read-only
|   warehouse – stock read/write, catalogue & documents read-only
|
*/

// ── Public ────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    // SECURITY: 5 attempts per IP per minute. Blocks credential-stuffing
    // without hurting legitimate typos. AuthController re-checks creds,
    // so a throttle hit just returns 429 — no side-channel leak about
    // whether the email exists.
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
});

// ── Protected (any authenticated user) ────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('/me',      [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/profile/password', [AuthController::class, 'updatePassword']);
    });

    // ── Dashboard ────────────────────────────────────────────────────────
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('dashboard/widgets', [DashboardWidgetController::class, 'index']);
    Route::put('dashboard/widgets', [DashboardWidgetController::class, 'update']);
    Route::get("tax-settings", [TaxSettingsController::class, "index"]);

    // ── Notifications ──────────────────────────────────────────────────
    Route::get('notifications',                [NotificationController::class, 'index']);
    Route::get('notifications/unread',         [NotificationController::class, 'unread']);
    Route::patch('notifications/{id}/read',    [NotificationController::class, 'markAsRead']);
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    // ── Notifications push (navigateur / téléphone) ────────────────────
    Route::get('push-subscriptions/vapid-key', [PushSubscriptionController::class, 'vapidKey']);
    Route::post('push-subscriptions',          [PushSubscriptionController::class, 'store']);
    Route::delete('push-subscriptions',        [PushSubscriptionController::class, 'destroy']);

    // ── Read-only routes (all authenticated users) ────────────────────────
    Route::get('brands',                             [BrandController::class, 'index']);
    Route::get('brands/{brand}',                     [BrandController::class, 'show']);
    Route::get('categories',                         [CategoryController::class, 'index']);
    Route::get('categories/{category}',              [CategoryController::class, 'show']);
    Route::get('products',                           [ProductController::class, 'index']);
    // Resolve unit prices for (product, quantity) pairs against the
    // channel-aware PriceResolver (sales-document form, quick quotes, etc.).
    Route::post('products/reprice',                  [ProductController::class, 'reprice']);
    // Must stay ahead of GET products/{product}: implicit route-model
    // binding would otherwise try (and fail) to resolve "trashed" as an id.
    Route::get('products/trashed',                   [ProductController::class, 'trashed'])->middleware('role:admin,manager');
    Route::post('products/{id}/restore',             [ProductController::class, 'restore'])->middleware('role:admin,manager');
    Route::get('products/{product}',                 [ProductController::class, 'show']);
    Route::get('products/{product}/statistics',      [ProductController::class, 'statistics']);
    Route::get('products/{product}/stock-history',   [ProductController::class, 'stockHistory']);
    Route::get('products/{product}/price-lists',     [ProductController::class, 'priceLists']);
    Route::get('products/{product}/images',          [ProductImageController::class, 'index']);
    Route::get('products/{product}/videos',          [ProductVideoController::class, 'index']);
    Route::get('products/{product}/documents',       [ProductDocumentController::class, 'index']);
    Route::get('third-partners',                     [ThirdPartnerController::class, 'index']);
    Route::get('third-partners/{thirdPartner}',      [ThirdPartnerController::class, 'show']);
    Route::get('warehouses',                         [WarehouseController::class, 'index']);
    Route::get('warehouses/{warehouse}',             [WarehouseController::class, 'show']);
    Route::get('warehouse-stocks',                   [WarehouseStockController::class, 'index']);
    Route::get('warehouse-stocks/{warehouseHasStock}',[WarehouseStockController::class, 'show']);
    Route::get('warehouse-transfers',                [WarehouseTransferController::class, 'index']);
    Route::get('warehouse-transfers/{warehouseTransfer}', [WarehouseTransferController::class, 'show']);
    Route::get('stock-mouvements',                   [StockMouvementController::class, 'index']);
    Route::get('stock-mouvements/{stockMouvement}',  [StockMouvementController::class, 'show']);
    Route::get('documents',                          [DocumentHeaderController::class, 'index']);
    Route::get('documents/{documentHeader}',         [DocumentHeaderController::class, 'show']);
    Route::get('documents/{documentHeader}/lines',   [DocumentLigneController::class, 'index']);
    Route::get('documents/{documentHeader}/footer',  [DocumentFooterController::class, 'show']);
    Route::get('documents/{documentHeader}/pdf/download', [DocumentPdfController::class, 'download']);
    Route::get('documents/{documentHeader}/pdf/stream',   [DocumentPdfController::class, 'stream']);
    Route::get('payments',                           [PaymentController::class, 'index']);
    Route::get('payments/{payment}',                 [PaymentController::class, 'show']);
    Route::get('settings',                           [SettingController::class, 'index']);

    // ── Trésorerie : lecture (tout utilisateur authentifié) ──────────────
    Route::get('treasury/summary',                   [TreasuryController::class, 'summary']);
    Route::get('treasury/journal',                   [TreasuryController::class, 'journal']);
    Route::get('cash-accounts',                      [CashAccountController::class, 'index']);
    Route::get('cash-accounts/{cashAccount}',        [CashAccountController::class, 'show']);
    Route::get('cash-categories',                    [CashCategoryController::class, 'index']);
    Route::get('cash-categories/{cashCategory}',     [CashCategoryController::class, 'show']);
    Route::get('cash-transactions',                  [CashTransactionController::class, 'index']);
    Route::get('cash-transactions/{cashTransaction}',[CashTransactionController::class, 'show']);
    Route::get('cash-recurrences',                   [CashRecurrenceController::class, 'index']);
    Route::get('cash-recurrences/{cashRecurrence}',  [CashRecurrenceController::class, 'show']);
    Route::get('storage/products',                   [StorageGalleryController::class, 'products']);

    // ── Label printing (TSPL) ────────────────────────────────────────────
    // Not admin-gated: printing price labels is shop-floor work. Only the
    // printer *configuration* (in the labels settings domain) is admin-only.
    Route::post('labels/tspl',  [LabelPrintController::class, 'payload']);
    Route::post('labels/print', [LabelPrintController::class, 'print']);

    // ── Exports (admin, manager) ─────────────────────────────────────────
    //
    // Un export sort la table entière dans un fichier qui quitte
    // l'application : le fichier clients, tous les règlements, tout
    // l'historique de stock. Ce n'est pas la même chose que consulter une
    // fiche à l'écran, et ça mérite le même garde-fou que les rapports
    // équivalents juste en dessous — qui, eux, étaient déjà restreints.
    // ── Trésorerie : paramétrage (admin, manager) ────────────────────────
    // Comptes, postes de dépense et récurrences décident de la lecture des
    // chiffres : un caissier saisit des écritures, il ne redéfinit pas le plan.
    Route::middleware('role:admin,manager')->group(function () {
        Route::post('cash-accounts',                       [CashAccountController::class, 'store']);
        Route::put('cash-accounts/{cashAccount}',          [CashAccountController::class, 'update']);
        Route::delete('cash-accounts/{cashAccount}',       [CashAccountController::class, 'destroy']);

        Route::post('cash-categories',                     [CashCategoryController::class, 'store']);
        Route::put('cash-categories/{cashCategory}',       [CashCategoryController::class, 'update']);
        Route::delete('cash-categories/{cashCategory}',    [CashCategoryController::class, 'destroy']);

        Route::post('cash-recurrences',                    [CashRecurrenceController::class, 'store']);
        Route::put('cash-recurrences/{cashRecurrence}',    [CashRecurrenceController::class, 'update']);
        Route::delete('cash-recurrences/{cashRecurrence}', [CashRecurrenceController::class, 'destroy']);
    });

    Route::middleware('role:admin,manager')->prefix('export')->group(function () {
        Route::get('products',         [ExportController::class, 'products']);
        Route::get('documents',        [ExportController::class, 'documents']);
        Route::get('third-partners',   [ExportController::class, 'thirdPartners']);
        Route::get('stock-mouvements', [ExportController::class, 'stockMouvements']);
        Route::get('payments',         [ExportController::class, 'payments']);
    });

    // ── Reports (admin, manager) ───────────────────────────────────────────
    Route::middleware('role:admin,manager')->prefix('reports')->group(function () {
        Route::get('sales',         [ReportController::class, 'sales']);
        Route::get('sales/pdf',     [ReportController::class, 'salesPdf']);
        Route::get('purchases',     [ReportController::class, 'purchases']);
        Route::get('purchases/pdf', [ReportController::class, 'purchasesPdf']);
        Route::get('stock',         [ReportController::class, 'stock']);
        Route::get('stock/pdf',     [ReportController::class, 'stockPdf']);
        Route::get('credit-clients',[ReportController::class, 'creditClients']);
    });

    // ── Catalogue write (admin, manager) ──────────────────────────────────
    Route::middleware('role:admin,manager')->group(function () {
        Route::post('brands',                        [BrandController::class, 'store']);
        Route::put('brands/{brand}',                 [BrandController::class, 'update']);
        Route::patch('brands/{brand}',               [BrandController::class, 'update']);
        Route::delete('brands/{brand}',              [BrandController::class, 'destroy']);

        Route::post('categories',                    [CategoryController::class, 'store']);
        Route::put('categories/{category}',          [CategoryController::class, 'update']);
        Route::patch('categories/{category}',        [CategoryController::class, 'update']);
        Route::delete('categories/{category}',       [CategoryController::class, 'destroy']);

        Route::post('products',                      [ProductController::class, 'store']);
        Route::put('products/{product}',             [ProductController::class, 'update']);
        Route::patch('products/{product}',           [ProductController::class, 'update']);
        Route::delete('products/{product}',          [ProductController::class, 'destroy']);
        Route::post('products/{product}/duplicate',  [ProductController::class, 'duplicate']);

        Route::post('products/{product}/images',                     [ProductImageController::class, 'store']);
        Route::patch('products/{product}/images/{image}/set-primary',[ProductImageController::class, 'setPrimary']);
        Route::delete('products/{product}/images/{image}',           [ProductImageController::class, 'destroy']);

        Route::post('products/{product}/videos',                     [ProductVideoController::class, 'store']);
        Route::delete('products/{product}/videos/{video}',           [ProductVideoController::class, 'destroy']);

        Route::post('products/{product}/documents',                  [ProductDocumentController::class, 'store']);
        Route::delete('products/{product}/documents/{document}',     [ProductDocumentController::class, 'destroy']);

        Route::post('storage/products/upload',                       [StorageGalleryController::class, 'upload']);
        Route::post('storage/products/assign',                       [StorageGalleryController::class, 'assign']);

        Route::post('third-partners',                     [ThirdPartnerController::class, 'store']);
        Route::put('third-partners/{thirdPartner}',       [ThirdPartnerController::class, 'update']);
        Route::patch('third-partners/{thirdPartner}',     [ThirdPartnerController::class, 'update']);
        Route::delete('third-partners/{thirdPartner}',    [ThirdPartnerController::class, 'destroy']);

        Route::post('warehouses',                    [WarehouseController::class, 'store']);
        Route::put('warehouses/{warehouse}',         [WarehouseController::class, 'update']);
        Route::patch('warehouses/{warehouse}',       [WarehouseController::class, 'update']);
        Route::delete('warehouses/{warehouse}',      [WarehouseController::class, 'destroy']);

        // ── Imports ─────────────────────────────────────────────────────
        Route::post('import/products',       [ImportController::class, 'products']);
        Route::post('import/third-partners', [ImportController::class, 'thirdPartners']);
        Route::post('import/categories',     [ImportController::class, 'categories']);
        Route::post('import/brands',         [ImportController::class, 'brands']);
        Route::post('import/preview',        [ImportController::class, 'preview']);
        Route::post('import/run',            [ImportController::class, 'import']);
        Route::get('import/template/{entity}', [ImportController::class, 'template']);
    });

    // ── Document write (admin, manager, cashier) ──────────────────────────
    Route::middleware('role:admin,manager,cashier')->group(function () {
        Route::post('documents',                                      [DocumentHeaderController::class, 'store']);
        Route::put('documents/{documentHeader}',                      [DocumentHeaderController::class, 'update']);
        Route::patch('documents/{documentHeader}',                    [DocumentHeaderController::class, 'update']);
        Route::delete('documents/{documentHeader}',                   [DocumentHeaderController::class, 'destroy']);

        Route::post('documents/{documentHeader}/lines',               [DocumentLigneController::class, 'store']);
        Route::patch('documents/{documentHeader}/lines/{documentLigne}',[DocumentLigneController::class, 'update']);
        Route::delete('documents/{documentHeader}/lines/{documentLigne}',[DocumentLigneController::class, 'destroy']);
        Route::post('documents/{documentHeader}/import-lines',        [DocumentImportController::class, 'importLines']);

        Route::put('documents/{documentHeader}/footer',               [DocumentFooterController::class, 'upsert']);

        Route::post('payments',                                       [PaymentController::class, 'store']);
        Route::delete('payments/{payment}',                           [PaymentController::class, 'destroy']);

        Route::post('third-partners/{thirdPartner}/bulk-payment',     [ThirdPartnerController::class, 'bulkPayment']);

        // ── Trésorerie : saisie (dépenses, recettes, virements) ───────
        Route::post('cash-transactions',                       [CashTransactionController::class, 'store']);
        Route::post('cash-transactions/{cashTransaction}',     [CashTransactionController::class, 'update']);
        Route::put('cash-transactions/{cashTransaction}',      [CashTransactionController::class, 'update']);
        Route::delete('cash-transactions/{cashTransaction}',   [CashTransactionController::class, 'destroy']);
        Route::post('cash-transfers',                          [CashTransactionController::class, 'transfer']);
        Route::post('cash-recurrences/run',                    [CashRecurrenceController::class, 'run']);

        Route::get('document-incrementors/{documentIncrementor}/reserve',  [DocumentIncrementorController::class, 'reserveNext']);
        Route::post('document-incrementors/{documentIncrementor}/confirm', [DocumentIncrementorController::class, 'confirmNext']);

        // ── Sales workflow (Ventes) ───────────────────────────────────
        Route::post('ventes/documents/{devis}/generer-bc',         [DocumentVenteController::class, 'generer_bc']);
        Route::post('ventes/documents/{bc}/generer-bl',            [DocumentVenteController::class, 'generer_bl']);
        Route::put('ventes/documents/{bl}/confirmer-bl',           [DocumentVenteController::class, 'confirmer_bl']);
        Route::put('ventes/documents/{bl}/confirmer',              [DocumentVenteController::class, 'confirmer_reception']);
        Route::post('ventes/documents/{bl}/annuler',               [DocumentVenteController::class, 'annuler_bl']);
        Route::post('ventes/documents/{document}/retour-client',   [DocumentVenteController::class, 'retour_client']);

        // ── Purchase workflow (Achats) ───────────────────────────────
        Route::post('achats/documents/{commande}/generer-reception',       [DocumentAchatController::class, 'generer_reception']);
        Route::put('achats/documents/{br}/confirmer-br',                   [DocumentAchatController::class, 'confirmer_br']);
        Route::put('achats/documents/{br}/confirmer-facture',              [DocumentAchatController::class, 'confirmer_facture']);
        Route::post('achats/documents/{br}/annuler',                       [DocumentAchatController::class, 'annuler_br']);
        Route::post('achats/documents/{document}/retour-fournisseur',      [DocumentAchatController::class, 'retour_fournisseur']);

        // ── OCR Invoice Import ──────────────────────────────────────
        Route::post('achats/ocr/parse',   [\App\Http\Controllers\Api\Achats\OcrInvoiceController::class, 'parse']);
        Route::post('achats/ocr/confirm', [\App\Http\Controllers\Api\Achats\OcrInvoiceController::class, 'confirm']);
    });

    // ── Stock write (admin, manager, warehouse) ───────────────────────────
    Route::middleware('role:admin,manager,warehouse')->group(function () {
        Route::patch('warehouse-stocks/{warehouseHasStock}', [WarehouseStockController::class, 'update']);

        // Transfers
        Route::post('warehouse-transfers',                                            [WarehouseTransferController::class, 'store']);
        Route::put('warehouse-transfers/{warehouseTransfer}',                         [WarehouseTransferController::class, 'update']);
        Route::post('warehouse-transfers/{warehouseTransfer}/execute',                [WarehouseTransferController::class, 'execute']);
        Route::post('warehouse-transfers/{warehouseTransfer}/cancel',                 [WarehouseTransferController::class, 'cancel']);
        Route::delete('warehouse-transfers/{warehouseTransfer}',                      [WarehouseTransferController::class, 'destroy']);

        // Stock operations (manual entry, exit, adjustment)
        Route::post('stock/entree',                                                   [StockOperationController::class, 'entree']);
        Route::post('stock/sortie',                                                   [StockOperationController::class, 'sortie']);
        Route::post('stock/ajustement',                                               [StockOperationController::class, 'ajustement']);

        // Stock document workflow (StockEntry, StockExit, StockAdjustment, StockTransfer)
        Route::post('stock/documents/{document}/appliquer',                           [DocumentStockController::class, 'appliquer']);
        Route::post('stock/documents/{document}/annuler',                             [DocumentStockController::class, 'annuler']);

        Route::post('stock-mouvements',                                               [StockMouvementController::class, 'store']);
    });

    // ── POS (gated on tenant feature flag) ─────────────────────────────────
    Route::middleware(['feature:pos', 'permission:pos.access'])->prefix('pos')->group(function () {
        // Terminals (admin/manager)
        Route::middleware('role:admin,manager,cashier')->group(function () {
            Route::get('terminals',              [PosTerminalController::class, 'index']);
            Route::post('terminals',             [PosTerminalController::class, 'store']);
            Route::get('terminals/{terminal}',   [PosTerminalController::class, 'show']);
            Route::put('terminals/{terminal}',   [PosTerminalController::class, 'update']);
            Route::delete('terminals/{terminal}',[PosTerminalController::class, 'destroy']);
        });

        // Sessions
        Route::get('sessions',                  [PosSessionController::class, 'index'])->middleware('role:admin,manager');
        Route::post('sessions/open',           [PosSessionController::class, 'open'])->middleware('permission:pos.open_session');
        Route::post('sessions/{session}/close', [PosSessionController::class, 'close'])->middleware('permission:pos.close_session');
        Route::post('sessions/{session}/force-close', [PosSessionController::class, 'forceClose'])->middleware('role:admin,manager');
        Route::get('sessions/current',          [PosSessionController::class, 'current']);

        // Live session stats (sales by payment method, refreshed during the session)
        Route::get('sessions/{session}/live-stats', [PosSessionController::class, 'liveStats']);

        // Tickets
        Route::post('tickets',                 [PosTicketController::class, 'store']);
        Route::get('tickets',                  [PosTicketController::class, 'index']);
        Route::post('tickets/{ticket}/void',   [PosTicketController::class, 'void'])->middleware('permission:pos.void_ticket');
        Route::post('tickets/{document}/retour', [PosTicketController::class, 'retour'])->middleware('permission:pos.void_ticket');
        Route::get('tickets/{ticket}/print',   [PosTicketController::class, 'print']);
        Route::get('tickets/{ticket}/print-html', [PosTicketController::class, 'printHtml']);

        // Session closing report
        Route::get('sessions/{session}/report', [PosSessionController::class, 'closingReport']);

        // Daily consolidated POS report
        Route::get('report/daily',              [PosSessionController::class, 'dailyReport']);

        // Customers (search + quick-create from POS)
        Route::get('customers',                [PosCustomerController::class, 'index']);
        Route::post('customers',               [PosCustomerController::class, 'store']);

        // Products (optimized search)
        Route::get('products',                 [PosProductController::class, 'index']);
        Route::post('products/reprice',        [PosProductController::class, 'reprice']);
    });

    // ── Admin-only (users, settings, structures, incrementors, roles) ─────
    Route::middleware('role:admin')->group(function () {
        Route::get('users',              [UserController::class, 'index']);
        Route::post('users',             [UserController::class, 'store']);
        Route::get('users/{user}',       [UserController::class, 'show']);
        Route::put('users/{user}',       [UserController::class, 'update']);
        Route::delete('users/{user}',    [UserController::class, 'destroy']);

        Route::get('roles',              [RoleController::class, 'index']);
        Route::post('roles',             [RoleController::class, 'store']);
        Route::get('roles/{role}',       [RoleController::class, 'show']);
        Route::put('roles/{role}',       [RoleController::class, 'update']);
        Route::delete('roles/{role}',    [RoleController::class, 'destroy']);
        Route::get('permissions',                   [PermissionController::class, 'index']);
        Route::get('permissions/grouped',           [PermissionController::class, 'grouped']);
        Route::get('structures',                         [StructureIncrementorController::class, 'index']);
        Route::post('structures',                        [StructureIncrementorController::class, 'store']);
        Route::get('structures/{structureIncrementor}',             [StructureIncrementorController::class, 'show']);
        Route::put('structures/{structureIncrementor}',             [StructureIncrementorController::class, 'update']);
        Route::delete('structures/{structureIncrementor}',          [StructureIncrementorController::class, 'destroy']);

        Route::get('document-incrementors',                              [DocumentIncrementorController::class, 'index']);
        Route::post('document-incrementors',                             [DocumentIncrementorController::class, 'store']);
        Route::get('document-incrementors/{document_incrementor}',       [DocumentIncrementorController::class, 'show']);
        Route::put('document-incrementors/{document_incrementor}',       [DocumentIncrementorController::class, 'update']);
        Route::delete('document-incrementors/{document_incrementor}',    [DocumentIncrementorController::class, 'destroy']);

        Route::post('settings',   [SettingController::class, 'upsert']);
        Route::delete('settings', [SettingController::class, 'destroy']);
        Route::post('settings/logo',          [SettingController::class, 'uploadLogo']);
        Route::delete('settings/logo',        [SettingController::class, 'deleteLogo']);
        Route::post('settings/test-email',    [SettingController::class, 'testEmail']);
        Route::post('settings/test-whatsapp', [SettingController::class, 'testWhatsapp']);
        Route::post('settings/reset-data',    [SettingController::class, 'resetTenantData']);

        Route::post('cache/flush', [SettingController::class, 'flushCache']);

        // Activity Log (audit trail)
        Route::get('activity-log',              [ActivityLogController::class, 'index']);
        Route::get('activity-log/{activity}',   [ActivityLogController::class, 'show']);
    });

    // ── Price resolver (all authenticated: POS + ecom internal consumers) ─
    // Product variants
    Route::get('products/{id}/variants',          [ProductVariantController::class, 'index']);
    Route::post('products/{id}/variants/sync',    [ProductVariantController::class, 'sync']);
    Route::delete('products/{id}/variants/{vid}', [ProductVariantController::class, 'destroy']);

    Route::get('price-lists-resolve', [PriceListController::class, 'resolve']);

    // ── Price Lists / Tarifs (admin, manager) ────────────────────────────
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('price-lists',                        [PriceListController::class, 'index']);
        Route::get('price-lists/{id}',                   [PriceListController::class, 'show']);
        Route::post('price-lists',                       [PriceListController::class, 'store']);
        Route::put('price-lists/{id}',                   [PriceListController::class, 'update']);
        Route::patch('price-lists/{id}',                 [PriceListController::class, 'update']);
        Route::delete('price-lists/{id}',                [PriceListController::class, 'destroy']);
        Route::post('price-lists/{id}/items',            [PriceListController::class, 'upsertItems']);
        Route::delete('price-lists/{id}/items/{itemId}', [PriceListController::class, 'destroyItem']);
    });

    // ── Marketing & Promotions (admin, manager) ──────────────────────────
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('promotions',                   [PromotionController::class, 'index']);
        Route::post('promotions',                  [PromotionController::class, 'store']);
        Route::get('promotions/{promotion}',       [PromotionController::class, 'show']);
        Route::put('promotions/{promotion}',       [PromotionController::class, 'update']);
        Route::delete('promotions/{promotion}',    [PromotionController::class, 'destroy']);

        Route::get('slides',                 [SlideController::class, 'index']);
        Route::get('slides/{slide}',         [SlideController::class, 'show']);
        Route::post('slides',                [SlideController::class, 'store']);
        Route::put('slides/{slide}',         [SlideController::class, 'update']);
        Route::patch('slides/{slide}',       [SlideController::class, 'update']);
        Route::delete('slides/{slide}',      [SlideController::class, 'destroy']);
        Route::post('slides/reorder',        [SlideController::class, 'reorder']);

        // Variant Options
        Route::get('variant-options',                             [VariantOptionController::class, 'index']);
        Route::post('variant-options',                            [VariantOptionController::class, 'store']);
        Route::put('variant-options/{id}',                        [VariantOptionController::class, 'update']);
        Route::delete('variant-options/{id}',                     [VariantOptionController::class, 'destroy']);
        Route::post('variant-options/{id}/values',                [VariantOptionController::class, 'storeValue']);
        Route::put('variant-options/{id}/values/{valueId}',       [VariantOptionController::class, 'updateValue']);
        Route::delete('variant-options/{id}/values/{valueId}',    [VariantOptionController::class, 'destroyValue']);
    });
});

// ── eCom Config (public, no API key required) ─────────────────────────────
Route::get('ecom/config', EcomConfigController::class)->middleware('throttle:30,1');

// ── eCom Public API (API Key auth, no session) ────────────────────────────
Route::prefix('ecom')->middleware(['ecom.key', 'throttle:60,1'])->group(function () {
    // Catalogue
    Route::get('products',           [EcomCatalogueController::class, 'products']);
    Route::get('products/{slug}',    [EcomCatalogueController::class, 'product']);
    Route::get('categories',         [EcomCatalogueController::class, 'categories']);
    Route::get('brands',             [EcomCatalogueController::class, 'brands']);

    // Promotions
    Route::get('promotions',         [EcomPromotionController::class, 'index']);
    Route::get('promotions/{slug}',  [EcomPromotionController::class, 'show']);

    // Slides / Banners
    Route::get('slides',             [EcomSlideController::class, 'index']);

    // Orders
    Route::post('orders',            [EcomOrderController::class, 'store']);
    Route::get('customers/lookup',   [EcomOrderController::class, 'lookupCustomer']);
});

// Package and Features info
Route::get('/package-info', [\App\Http\Controllers\Api\PackageInfoController::class, 'getPackageInfo']);
Route::get('/feature/{feature}/enabled', [\App\Http\Controllers\Api\PackageInfoController::class, 'isFeatureEnabled']);
