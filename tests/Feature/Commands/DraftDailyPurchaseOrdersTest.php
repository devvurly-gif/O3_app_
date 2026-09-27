<?php

namespace Tests\Feature\Commands;

use App\Enums\TenantStatus;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

class DraftDailyPurchaseOrdersTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshTenantDatabase;

    private const SERVICE_ACCOUNT_EMAIL = 'agent-ia.achats@jadema.o3app.local';

    /** Tenants dotés du compte de service « Agent IA — Achats ». */
    private array $enrolled = [];

    /** @var array<string, array<int, string>> BCF brouillons relevés dans chaque tenant */
    private array $drafted = [];

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->tenantsOnTheTestDatabase([
            'jadema'    => [],
            'teliphoni' => ['status' => TenantStatus::Trial],
            // Pas de compte de service : tenant non concerné.
            'demo'      => [],
            // Lecture seule : aucun brouillon ne doit y apparaître.
            'impaye'    => ['status' => TenantStatus::PastDue],
        ]);
        $this->enrolled = ['jadema', 'teliphoni', 'impaye'];

        $this->isolateEachTenantRun(
            seed: fn (Tenant $tenant) => $this->seedSaleOf($tenant),
            inspect: function (Tenant $tenant) {
                $this->drafted[$tenant->id] = DocumentHeader::where('document_type', 'PurchaseOrder')
                    ->where('status', 'draft')
                    ->with('thirdPartner')
                    ->get()
                    ->map(fn (DocumentHeader $po) => $po->thirdPartner->tp_title)
                    ->all();
            },
        );
    }

    /**
     * Une vente d'un produit lié à un fournisseur propre au tenant, et tout ce
     * que la commande exige pour en tirer un BCF.
     */
    private function seedSaleOf(Tenant $tenant): void
    {
        $admin = User::factory()->admin()->create(['email' => "admin@{$tenant->id}.test"]);

        if (in_array($tenant->id, $this->enrolled, true)) {
            User::factory()->cashier()->create(['email' => self::SERVICE_ACCOUNT_EMAIL]);
        }

        $warehouse = Warehouse::factory()->create();

        DocumentIncrementor::factory()->create([
            'di_model'  => 'PurchaseOrder',
            'di_title'  => 'Bon de Commande Fournisseur',
            'template'  => 'BCF-{YYYY}-{NNNN}',
            'nextTrick' => 1,
        ]);

        $supplier = ThirdPartner::factory()->create([
            'tp_title' => "Fournisseur {$tenant->id}",
            'tp_Role'  => 'supplier',
        ]);
        $product = Product::factory()->create(['p_title' => "Produit {$tenant->id}"]);

        DB::table('product_suppliers')->insert([
            'product_id'       => $product->id,
            'third_partner_id' => $supplier->id,
            'purchase_price'   => 40,
            'priority'         => 1,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        DB::table('stock_mouvements')->insert([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'direction'    => 'out',
            'reason'       => 'sale',
            'status'       => 'applied',
            'quantity'     => 3,
            'stock_before' => 10,
            'stock_after'  => 7,
            'user_id'      => $admin->id,
            'created_at'   => now()->subHours(2),
            'updated_at'   => now()->subHours(2),
        ]);
    }

    /**
     * Régression : planifiée sans argument, la commande ne tournait que pour
     * jadema (valeur par défaut de l'argument `tenant`).
     */
    public function test_without_argument_it_drafts_for_every_enrolled_tenant_in_good_standing(): void
    {
        $this->artisan('achats:draft-daily-po')->assertSuccessful();

        $this->assertSame([
            'demo'      => [],
            'jadema'    => ['Fournisseur jadema'],
            'teliphoni' => ['Fournisseur teliphoni'],
        ], $this->drafted);
    }

    public function test_the_tenant_argument_still_targets_a_single_tenant(): void
    {
        $this->artisan('achats:draft-daily-po', ['tenant' => 'teliphoni'])->assertSuccessful();

        $this->assertSame(['teliphoni' => ['Fournisseur teliphoni']], $this->drafted);
    }

    public function test_an_explicit_tenant_without_service_account_is_an_error(): void
    {
        // Lancement manuel : l'absence du compte reste une erreur à signaler,
        // pas un tenant « non concerné ».
        $this->artisan('achats:draft-daily-po', ['tenant' => 'demo'])->assertFailed();

        $this->assertSame(['demo' => []], $this->drafted);
    }

    public function test_an_unknown_tenant_is_an_error(): void
    {
        $this->artisan('achats:draft-daily-po', ['tenant' => 'inconnu'])
            ->expectsOutputToContain("Tenant 'inconnu' introuvable.")
            ->assertFailed();
    }
}
