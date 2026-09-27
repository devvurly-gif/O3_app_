<?php

namespace Tests\Feature\Commands;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\DocumentLigne;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\ThirdPartner;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

class GeneratePeriodicInvoicesTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Le planificateur lance la commande depuis le contexte central : sans
        // tenant enregistré, elle n'a plus rien à facturer.
        $this->tenantsOnTheTestDatabase(['jadema' => []]);

        $this->admin = User::factory()->admin()->create();
        $this->customer = ThirdPartner::factory()->create([
            'tp_title'              => 'Client en compte',
            'tp_Role'               => 'customer',
            'type_compte'           => 'en_compte',
            'frequence_facturation' => 'mensuelle',
        ]);

        DocumentIncrementor::factory()->create([
            'di_model'  => 'InvoiceSale',
            'di_title'  => 'Facture Vente',
            'template'  => 'FV-{YYYY}-{NNNN}',
            'nextTrick' => 1,
        ]);
    }

    private function deliveryNote(float $amount, ?ThirdPartner $customer = null): DocumentHeader
    {
        $bl = DocumentHeader::factory()->create([
            'document_type'   => 'DeliveryNote',
            'thirdPartner_id' => ($customer ?? $this->customer)->id,
            'user_id'         => $this->admin->id,
            'status'          => 'confirmed',
            'issued_at'       => now()->subDays(10),
        ]);

        DocumentLigne::create([
            'document_header_id' => $bl->id,
            'sort_order'         => 1,
            'line_type'          => 'product',
            'designation'        => 'Marchandise',
            'quantity'           => 1,
            'unit_price'         => $amount,
            'tax_percent'        => 0,
        ]);

        DocumentFooter::factory()->create([
            'document_header_id' => $bl->id,
            'total_ht'           => $amount,
            'total_tax'          => 0,
            'total_ttc'          => $amount,
            'amount_paid'        => 0,
            'amount_due'         => $amount,
        ]);

        return $bl;
    }

    /**
     * Régression : la facture périodique n'a pas les BL pour enfants — un
     * parent_id ne peut désigner qu'un document — et les BL restaient donc
     * comptés dans l'encours à côté de la facture qui les récapitule.
     */
    public function test_the_grouped_invoice_does_not_double_the_customer_balance(): void
    {
        $this->travelTo(now()->startOfMonth());

        $this->deliveryNote(12000);
        $this->deliveryNote(3500);

        $this->customer->recalculateEncours();
        $this->assertSame(15500.0, (float) $this->customer->fresh()->encours_actuel);

        $this->artisan('billing:generate-periodic-invoices')->assertSuccessful();

        $this->assertSame(1, DocumentHeader::where('document_type', 'InvoiceSale')->count());
        $this->assertSame(15500.0, (float) $this->customer->fresh()->encours_actuel);
    }

    public function test_it_bills_every_uninvoiced_delivery_note_at_once(): void
    {
        $this->travelTo(now()->startOfMonth());

        $this->deliveryNote(12000);
        $this->deliveryNote(3500);

        $this->artisan('billing:generate-periodic-invoices')->assertSuccessful();

        $invoice = DocumentHeader::where('document_type', 'InvoiceSale')->firstOrFail();

        $this->assertSame(15500.0, (float) $invoice->footer->total_ttc);
        $this->assertSame(2, $invoice->lignes()->count());
    }

    public function test_it_does_nothing_outside_the_first_of_the_month(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(9));

        $this->deliveryNote(12000);

        $this->artisan('billing:generate-periodic-invoices')->assertSuccessful();

        $this->assertSame(0, DocumentHeader::where('document_type', 'InvoiceSale')->count());
    }

    // ── Planificateur : un passage par tenant ─────────────────────

    /**
     * Client en compte propre au tenant, avec un BL à facturer, posé à
     * l'entrée dans son contexte.
     */
    private function seedCustomerOf(Tenant $tenant): void
    {
        $customer = ThirdPartner::factory()->create([
            'tp_title'              => "Client {$tenant->id}",
            'tp_Role'               => 'customer',
            'type_compte'           => 'en_compte',
            'frequence_facturation' => 'mensuelle',
        ]);

        $this->deliveryNote(1000, $customer);
    }

    /**
     * Régression : lancée depuis le contexte central par o3-scheduler, la
     * commande interrogeait la base centrale et ne facturait aucun client.
     */
    public function test_it_bills_the_customers_of_every_tenant_in_good_standing(): void
    {
        $this->travelTo(now()->startOfMonth());

        $this->tenantsOnTheTestDatabase([
            'teliphoni' => ['status' => TenantStatus::Trial],
            'impaye'    => ['status' => TenantStatus::PastDue],
            'coupe'     => ['is_active' => false],
        ]);

        $billed = [];
        $this->isolateEachTenantRun(
            seed: fn (Tenant $tenant) => $this->seedCustomerOf($tenant),
            inspect: function (Tenant $tenant) use (&$billed) {
                $billed[$tenant->id] = DocumentHeader::where('document_type', 'InvoiceSale')
                    ->with('thirdPartner')
                    ->get()
                    ->map(fn (DocumentHeader $invoice) => $invoice->thirdPartner->tp_title)
                    ->all();
            },
        );

        $this->artisan('billing:generate-periodic-invoices')->assertSuccessful();

        // Un tenant en impayé est en lecture seule, un tenant coupé n'a plus
        // accès : ni l'un ni l'autre ne doit voir apparaître de facture.
        $this->assertSame([
            'jadema'    => ['Client jadema'],
            'teliphoni' => ['Client teliphoni'],
        ], $billed);
    }

    public function test_a_failing_tenant_does_not_stop_the_others(): void
    {
        $this->travelTo(now()->startOfMonth());

        // Premier dans l'ordre de passage.
        $this->tenantsOnTheTestDatabase(['alpha' => []]);

        Log::spy();

        $billed = [];
        $this->isolateEachTenantRun(
            seed: function (Tenant $tenant) {
                if ($tenant->id === 'alpha') {
                    throw new \RuntimeException('Base tenantalpha introuvable');
                }

                $this->seedCustomerOf($tenant);
            },
            inspect: function (Tenant $tenant) use (&$billed) {
                $billed[$tenant->id] = DocumentHeader::where('document_type', 'InvoiceSale')->count();
            },
        );

        $this->artisan('billing:generate-periodic-invoices')
            ->expectsOutputToContain('Tenant(s) en échec : alpha')
            ->assertFailed();

        $this->assertSame(1, $billed['jadema']);
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context) => ($context['tenant_id'] ?? null) === 'alpha')
            ->once();
    }

    public function test_the_tenant_option_bills_a_single_tenant(): void
    {
        $this->travelTo(now()->startOfMonth());

        $this->tenantsOnTheTestDatabase(['teliphoni' => []]);

        $visited = [];
        $this->isolateEachTenantRun(seed: function (Tenant $tenant) use (&$visited) {
            $visited[] = $tenant->id;
        });

        $this->artisan('billing:generate-periodic-invoices', ['--tenant' => 'teliphoni'])->assertSuccessful();

        $this->assertSame(['teliphoni'], $visited);
    }
}
