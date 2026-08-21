<?php

namespace Tests\Concerns;

use App\Models\DocumentIncrementor;
use App\Models\Permission;
use App\Models\PosSession;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;

/**
 * Scaffolding for the POS feature tests.
 *
 * Two things make POS harder to test than the rest of the API, and both are
 * handled here so the tests themselves stay about behaviour:
 *
 *   1. Every /api/pos/* route sits behind `feature:pos`, which reads
 *      `tenant()->pos_enabled`. The test suite runs without tenancy
 *      initialised, so `tenant()` is null and every POS request would 403.
 *      fakeTenant() binds an in-memory Tenant into the container — the same
 *      binding `tenant()` resolves — without touching the database or
 *      switching the connection out from under RefreshDatabase.
 *
 *   2. POS routes are also gated on fine-grained permissions (pos.access,
 *      pos.open_session, …) which only exist once RolePermissionSeeder has
 *      run. Tests don't seed, so grantPermissions() creates just the rows a
 *      given test needs. Admins bypass CheckPermission entirely, so only
 *      cashier/manager tests need it.
 */
trait InteractsWithPos
{
    protected PosTerminal $terminal;
    protected Warehouse $warehouse;

    /**
     * Bind an in-memory tenant so `feature:` middleware resolves.
     * Every flag defaults to enabled; pass overrides to turn one off.
     */
    protected function fakeTenant(array $features = []): Tenant
    {
        $tenant = new Tenant();
        $tenant->id   = 'test-tenant';
        $tenant->name = 'Tenant de test';

        foreach (array_merge([
            'pos_enabled'      => true,
            'ecom_enabled'     => false,
            'variants_enabled' => false,
            'imei_enabled'     => false,
        ], $features) as $flag => $value) {
            $tenant->{$flag} = $value;
        }

        $this->app->instance(TenantContract::class, $tenant);

        return $tenant;
    }

    /**
     * Attach permissions to the user's role, creating the Permission rows
     * on the fly. Returns the user for chaining.
     */
    protected function grantPermissions(User $user, string ...$names): User
    {
        $ids = collect($names)->map(function (string $name) {
            [$module, $action] = array_pad(explode('.', $name, 2), 2, 'access');

            return Permission::firstOrCreate(
                ['name' => $name],
                ['module' => $module, 'action' => $action, 'display_name' => $name],
            )->id;
        });

        $user->role->permissions()->syncWithoutDetaching($ids->all());
        $user->unsetRelation('role');

        return $user;
    }

    /**
     * Warehouse + active terminal, stored on the test case so individual
     * tests can reach them without re-creating the chain.
     */
    protected function setUpPosTerminal(): PosTerminal
    {
        $this->warehouse = Warehouse::factory()->create();
        $this->terminal  = PosTerminal::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        return $this->terminal;
    }

    protected function openSessionFor(User $user, float $openingCash = 0): PosSession
    {
        return PosSession::factory()->create([
            'pos_terminal_id' => $this->terminal->id,
            'user_id'         => $user->id,
            'opening_cash'    => $openingCash,
        ]);
    }

    /**
     * The numbering rows PosService::createTicket() looks up by di_model.
     * Without them the service aborts with a 422 before doing anything.
     *
     * The tenant migration already inserts DeliveryNote and ReturnSale (but
     * not TicketSale), so this fills the gap rather than inserting duplicates
     * the service would then have to pick between.
     */
    protected function setUpPosIncrementors(): void
    {
        foreach (['TicketSale', 'DeliveryNote', 'ReturnSale'] as $model) {
            DocumentIncrementor::firstOrCreate(
                ['di_model' => $model],
                [
                    'di_title'     => $model,
                    'di_domain'    => 'sales',
                    'template'     => strtoupper(substr($model, 0, 3)) . '-{NNNN}',
                    'nextTrick'    => 1,
                    'status'       => true,
                    'operatorSens' => 'out',
                ],
            );
        }
    }

    /**
     * The numbering row the app resolves for a document type — used to assert
     * a document was numbered from the right counter without hard-coding a
     * template that is tenant configuration, not POS behaviour.
     */
    protected function incrementorFor(string $documentType): DocumentIncrementor
    {
        return DocumentIncrementor::where('di_model', $documentType)->firstOrFail();
    }

    /**
     * The walk-in customer PosService falls back to when no customer is
     * selected. Its absence is a bug of its own (see PosTicketTest), so it
     * is created explicitly rather than assumed.
     */
    protected function setUpComptoirCustomer(): ThirdPartner
    {
        return ThirdPartner::factory()->create([
            'tp_code'  => 'CLIENT-COMPTOIR',
            'tp_title' => 'Client Comptoir',
            'tp_Role'  => 'customer',
        ]);
    }

    /**
     * A product with a known price and a known stock level in the terminal's
     * warehouse. Tax defaults to 0 so expected totals stay readable.
     */
    protected function stockedProduct(float $salePrice = 100, float $stock = 50, float $taxRate = 0): Product
    {
        $product = Product::factory()->create([
            'p_salePrice' => $salePrice,
            'p_taxRate'   => $taxRate,
        ]);

        WarehouseHasStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id'   => $product->id,
            'stockLevel'   => $stock,
            'user_id'      => User::first()?->id,
        ]);

        return $product;
    }

    protected function stockLevel(Product $product): float
    {
        return (float) WarehouseHasStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $product->id)
            ->value('stockLevel');
    }

    /**
     * A ticket payload for POST /api/pos/tickets.
     *
     * @param  array<int, array{product: Product, quantity?: float, unit_price?: float}>  $lines
     * @param  array<int, array{amount: float, method?: string}>  $payments
     */
    protected function ticketPayload(array $lines, array $payments, ?int $customerId = null): array
    {
        return [
            'items' => array_map(fn (array $line) => [
                'product_id'  => $line['product']->id,
                'designation' => $line['product']->p_title,
                'quantity'    => $line['quantity'] ?? 1,
                'unit_price'  => $line['unit_price'] ?? (float) $line['product']->p_salePrice,
                'tax_percent' => $line['tax_percent'] ?? (float) $line['product']->p_taxRate,
            ], $lines),
            'payments' => array_map(fn (array $payment) => [
                'amount' => $payment['amount'],
                'method' => $payment['method'] ?? 'cash',
            ], $payments),
            'customer_id' => $customerId,
        ];
    }
}
