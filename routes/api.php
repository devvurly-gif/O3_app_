<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DocumentFooterController;
use App\Http\Controllers\Api\DocumentHeaderController;
use App\Http\Controllers\Api\DocumentIncrementorController;
use App\Http\Controllers\Api\DocumentLigneController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StockMouvementController;
use App\Http\Controllers\Api\StockOperationController;
use App\Http\Controllers\Api\StructureIncrementorController;
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
use App\Http\Controllers\Api\DocumentPdfController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Pos\PosProductController;
use App\Http\Controllers\Api\Pos\PosSessionController;
use App\Http\Controllers\Api\Pos\PosTerminalController;
use App\Http\Controllers\Api\Pos\PosCustomerController;
use App\Http\Controllers\Api\Pos\PosTicketController;
use App\Http\Controllers\Api\WarehouseTransferController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\SlideController;
use App\Http\Controllers\Api\Ecom\EcomCatalogueController;
use App\Http\Controllers\Api\Ecom\EcomPromotionController;
use App\Http\Controllers\Api\Ecom\EcomOrderController;
use App\Http\Controllers\Api\Ecom\EcomSlideController;
use App\Http\Controllers\Api\Ecom\EcomConfigController;
use App\Http\Controllers\Api\StorageGalleryController;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
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
    Route::post('/login', [AuthController::class, 'login']);
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

    // ── Notifications ──────────────────────────────────────────────────
    Route::get('notifications',                [NotificationController::class, 'index']);
    Route::get('notifications/unread',         [NotificationController::class, 'unread']);
    Route::patch('notifications/{id}/read',    [NotificationController::class, 'markAsRead']);
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    // ── Read-only routes (all authenticated users) ────────────────────────
    Route::get('brands',                             [BrandController::class, 'index']);
    Route::get('brands/{brand}',                     [BrandController::class, 'show']);
    Route::get('categories',                         [CategoryController::class, 'index']);
    Route::get('categories/{category}',              [CategoryController::class, 'show']);
    Route::get('products',                           [ProductController::class, 'index']);
    Route::get('products/{product}',                 [ProductController::class, 'show']);
    Route::get('products/{product}/images',          [ProductImageController::class, 'index']);
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
    Route::get('storage/products',                   [StorageGalleryController::class, 'products']);

    // ── Exports (all authenticated users) ────────────────────────────────
    Route::prefix('export')->group(function () {
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

        Route::post('products/{product}/images',                     [ProductImageController::class, 'store']);
        Route::patch('products/{product}/images/{image}/set-primary',[ProductImageController::class, 'setPrimary']);
        Route::delete('products/{product}/images/{image}',           [ProductImageController::class, 'destroy']);

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

        Route::put('documents/{documentHeader}/footer',               [DocumentFooterController::class, 'upsert']);

        Route::post('payments',                                       [PaymentController::class, 'store']);
        Route::delete('payments/{payment}',                           [PaymentController::class, 'destroy']);

        Route::post('third-partners/{thirdPartner}/bulk-payment',     [ThirdPartnerController::class, 'bulkPayment']);

        Route::get('document-incrementors/{documentIncrementor}/reserve',  [DocumentIncrementorController::class, 'reserveNext']);
        Route::post('document-incrementors/{documentIncrementor}/confirm', [DocumentIncrementorController::class, 'confirmNext']);

        // ── Sales workflow (Ventes) ───────────────────────────────────
        Route::post('ventes/documents/{devis}/generer-bc',  [DocumentVenteController::class, 'generer_bc']);
        Route::post('ventes/documents/{bc}/generer-bl',     [DocumentVenteController::class, 'generer_bl']);
        Route::put('ventes/documents/{bl}/confirmer',       [DocumentVenteController::class, 'confirmer_reception']);

        // ── Purchase workflow (Achats) ───────────────────────────────
        Route::post('achats/documents/{commande}/generer-reception', [DocumentAchatController::class, 'generer_reception']);
        Route::put('achats/documents/{br}/confirmer-facture',        [DocumentAchatController::class, 'confirmer_facture']);
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

    // ── POS (module-gated) ──────────────────────────────────────────────────
    Route::middleware(['module:pos', 'permission:pos.access'])->prefix('pos')->group(function () {
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

        // Tickets
        Route::post('tickets',                 [PosTicketController::class, 'store']);
        Route::get('tickets',                  [PosTicketController::class, 'index']);
        Route::post('tickets/{ticket}/void',   [PosTicketController::class, 'void'])->middleware('permission:pos.void_ticket');
        Route::get('tickets/{ticket}/print',   [PosTicketController::class, 'print']);

        // Session closing report
        Route::get('sessions/{session}/report', [PosSessionController::class, 'closingReport']);

        // Daily consolidated POS report
        Route::get('report/daily',              [PosSessionController::class, 'dailyReport']);

        // Customers (search + quick-create from POS)
        Route::get('customers',                [PosCustomerController::class, 'index']);
        Route::post('customers',               [PosCustomerController::class, 'store']);

        // Products (optimized search)
        Route::get('products',                 [PosProductController::class, 'index']);
    });

    // ── Admin-only (users, settings, structures, incrementors, roles, modules) ──
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
        Route::get('structures/{structure}',             [StructureIncrementorController::class, 'show']);
        Route::put('structures/{structure}',             [StructureIncrementorController::class, 'update']);
        Route::delete('structures/{structure}',          [StructureIncrementorController::class, 'destroy']);

        Route::get('document-incrementors',                              [DocumentIncrementorController::class, 'index']);
        Route::post('document-incrementors',                             [DocumentIncrementorController::class, 'store']);
        Route::get('document-incrementors/{document_incrementor}',       [DocumentIncrementorController::class, 'show']);
        Route::put('document-incrementors/{document_incrementor}',       [DocumentIncrementorController::class, 'update']);
        Route::delete('document-incrementors/{document_incrementor}',    [DocumentIncrementorController::class, 'destroy']);

        // Modules
        Route::get('modules',                  [ModuleController::class, 'index']);
        Route::patch('modules/{module}',       [ModuleController::class, 'update']);

        Route::post('settings',   [SettingController::class, 'upsert']);
        Route::delete('settings', [SettingController::class, 'destroy']);
        Route::post('settings/logo',          [SettingController::class, 'uploadLogo']);
        Route::delete('settings/logo',        [SettingController::class, 'deleteLogo']);
        Route::post('settings/test-email',    [SettingController::class, 'testEmail']);
        Route::post('settings/test-whatsapp', [SettingController::class, 'testWhatsapp']);

        Route::post('cache/flush', function () {
            Cache::flush();
            return response()->json(['message' => 'Cache vidé avec succès.']);
        });

        // Activity Log (audit trail)
        Route::get('activity-log',              [ActivityLogController::class, 'index']);
        Route::get('activity-log/{activity}',   [ActivityLogController::class, 'show']);
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

    // Promotions
    Route::get('promotions',         [EcomPromotionController::class, 'index']);
    Route::get('promotions/{slug}',  [EcomPromotionController::class, 'show']);

    // Slides / Banners
    Route::get('slides',             [EcomSlideController::class, 'index']);

    // Orders
    Route::post('orders',            [EcomOrderController::class, 'store']);
});
